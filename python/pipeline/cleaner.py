# python/pipeline/cleaner.py
import pandas as pd
import pymysql
from datetime import datetime
from config import DB_CONFIG, DATE_FORMATS


def get_valid_product_ids() -> set:
    conn = pymysql.connect(**DB_CONFIG)
    cursor = conn.cursor()
    cursor.execute("SELECT id_product FROM products WHERE status = 'aktif'")
    ids = {row[0] for row in cursor.fetchall()}
    conn.close()
    return ids


def get_valid_user_ids() -> set:
    conn = pymysql.connect(**DB_CONFIG)
    cursor = conn.cursor()
    cursor.execute("SELECT id_user FROM users WHERE status = 'aktif' AND role = 'pelanggan'")
    ids = {row[0] for row in cursor.fetchall()}
    conn.close()
    return ids


def get_existing_item_keys() -> set:
    """Kunci duplikat terhadap data yang sudah ada di DB."""
    conn = pymysql.connect(**DB_CONFIG)
    cursor = conn.cursor()
    cursor.execute("""
        SELECT t.id_user, DATE(t.tanggal), ti.id_product, ti.qty,
               COALESCE(t.kode_transaksi, '')
        FROM transactions t
        JOIN transaction_items ti ON ti.id_transaction = t.id_transaction
    """)
    keys = set()
    for uid, tgl, pid, qty, kode in cursor.fetchall():
        keys.add(('kode', kode, int(pid), int(qty))) if kode else None
        keys.add(('legacy', int(uid), str(tgl), int(pid), int(qty)))
    conn.close()
    return keys


def parse_tanggal(val: str):
    if not val or str(val).strip() in ['', 'nan', 'NaT', 'None']:
        return None
    val = str(val).strip()
    for fmt in DATE_FORMATS:
        try:
            return datetime.strptime(val, fmt)
        except ValueError:
            continue
    return None


def clean_angka(val):
    if val is None or str(val).strip() in ['', 'nan']:
        return None
    val = str(val).strip()
    val = val.replace('Rp', '').replace('rp', '').replace(' ', '')
    val = val.replace('.', '').replace(',', '.')
    try:
        return float(val)
    except ValueError:
        return None


def clean_dataframe(df: pd.DataFrame, col_map: dict) -> tuple:
    valid_products = get_valid_product_ids()
    valid_users = get_valid_user_ids()
    existing_keys = get_existing_item_keys()

    records_valid = []
    records_invalid = []
    warnings = []

    df = df.copy()
    df.dropna(how='all', inplace=True)
    df.reset_index(drop=True, inplace=True)

    seen_keys = set()
    has_kode_col = 'kode_transaksi' in col_map

    if not has_kode_col:
        warnings.append(
            'Kolom kode_transaksi/transaction_id tidak ditemukan; '
            'pengelompokan memakai fallback (id_user + tanggal).'
        )

    for idx, row in df.iterrows():
        nomor_baris = idx + 2
        errors = []
        cleaned = {}

        raw_tgl = row.get(col_map.get('tanggal', ''), '')
        tgl = parse_tanggal(raw_tgl)
        if tgl is None:
            errors.append(f"tanggal tidak valid: '{raw_tgl}'")
        else:
            cleaned['tanggal'] = tgl.strftime('%Y-%m-%d %H:%M:%S')

        raw_pid = row.get(col_map.get('id_product', ''), '')
        try:
            pid = int(float(str(raw_pid).strip()))
            if pid not in valid_products:
                errors.append(f"id_product {pid} tidak ada / tidak aktif di database")
            else:
                cleaned['id_product'] = pid
        except (ValueError, TypeError):
            errors.append(f"id_product tidak valid: '{raw_pid}'")

        raw_qty = row.get(col_map.get('qty', ''), '')
        qty = clean_angka(raw_qty)
        if qty is None or qty <= 0:
            errors.append(f"qty tidak valid: '{raw_qty}'")
        else:
            cleaned['qty'] = int(qty)

        raw_harga = row.get(col_map.get('harga_satuan', ''), '')
        harga = clean_angka(raw_harga)
        if harga is None or harga <= 0:
            errors.append(f"harga_satuan tidak valid: '{raw_harga}'")
        else:
            cleaned['harga_satuan'] = harga

        raw_email = str(row.get(col_map.get('email', ''), '')).strip()
        cleaned['email'] = raw_email if raw_email and raw_email.lower() not in ['', 'nan', 'none'] else ''

        raw_hp = str(row.get(col_map.get('no_hp', ''), '')).strip()
        cleaned['no_hp'] = raw_hp if raw_hp and raw_hp.lower() not in ['', 'nan', 'none'] else ''

        # kode_transaksi opsional
        raw_kode = str(row.get(col_map.get('kode_transaksi', ''), '')).strip()
        cleaned['kode_transaksi'] = (
            raw_kode if raw_kode and raw_kode.lower() not in ['', 'nan', 'none'] else ''
        )

        raw_uid = row.get(col_map.get('id_user', ''), '')
        try:
            uid = int(float(str(raw_uid).strip()))
            if uid in valid_users:
                cleaned['id_user'] = uid
            else:
                cleaned['id_user'] = 0  # akan di-resolve / ditolak di user_resolver
                if not cleaned['email']:
                    errors.append(f"id_user {uid} tidak cocok dengan akun pelanggan aktif")
        except (ValueError, TypeError):
            cleaned['id_user'] = 0
            if not cleaned['email']:
                errors.append('id_user atau email wajib untuk mencocokkan pelanggan')

        raw_metode = str(row.get(col_map.get('metode_pembayaran', ''), 'Tunai')).strip()
        metode_map = {
            'tunai': 'Tunai', 'cash': 'Tunai',
            'transfer': 'Transfer', 'bank': 'Transfer', 'bca': 'Transfer',
            'qris': 'QRIS', 'qr': 'QRIS', 'gopay': 'QRIS', 'ovo': 'QRIS',
        }
        cleaned['metode_pembayaran'] = metode_map.get(raw_metode.lower(), 'Tunai')

        raw_status = str(row.get(col_map.get('status_pesanan', ''), 'Selesai')).strip()
        status_map = {
            'selesai': 'Selesai', 'done': 'Selesai', 'complete': 'Selesai',
            'diproses': 'Diproses', 'process': 'Diproses', 'proses': 'Diproses',
            'dikirim': 'Dikirim', 'kirim': 'Dikirim', 'shipped': 'Dikirim',
            'dibatalkan': 'Dibatalkan', 'batal': 'Dibatalkan', 'cancel': 'Dibatalkan',
        }
        cleaned['status_pesanan'] = status_map.get(raw_status.lower(), 'Selesai')

        raw_bayar = str(row.get(col_map.get('status_pembayaran', ''), '')).strip()
        bayar_map = {
            'dibayar': 'Dibayar', 'paid': 'Dibayar', 'lunas': 'Dibayar',
            'belum dibayar': 'Belum Dibayar', 'unpaid': 'Belum Dibayar',
            'pending': 'Pending', 'gagal': 'Gagal', 'expired': 'Expired',
            'refund': 'Refund',
        }
        if raw_bayar and raw_bayar.lower() not in ['', 'nan', 'none']:
            cleaned['status_pembayaran'] = bayar_map.get(raw_bayar.lower(), raw_bayar)
        else:
            cleaned['status_pembayaran'] = (
                'Dibayar' if cleaned['status_pesanan'] in ['Selesai', 'Dikirim'] else 'Belum Dibayar'
            )

        if cleaned.get('kode_transaksi'):
            dup_key = ('kode', cleaned['kode_transaksi'], cleaned.get('id_product'), cleaned.get('qty'))
        else:
            dup_key = (
                'legacy',
                cleaned.get('id_user') or cleaned.get('email', ''),
                cleaned.get('tanggal'),
                cleaned.get('id_product'),
                cleaned.get('qty'),
            )

        if errors:
            records_invalid.append({
                'nomor_baris': nomor_baris,
                'status_baris': 'invalid',
                'data_mentah': str(dict(row)),
                'keterangan': '; '.join(errors),
            })
        elif dup_key in seen_keys:
            records_invalid.append({
                'nomor_baris': nomor_baris,
                'status_baris': 'duplikat',
                'data_mentah': str(dict(row)),
                'keterangan': 'Baris duplikat dengan data sebelumnya dalam file yang sama',
            })
        elif dup_key in existing_keys or (
            cleaned.get('kode_transaksi')
            and ('kode', cleaned['kode_transaksi'], cleaned.get('id_product'), cleaned.get('qty')) in existing_keys
        ):
            records_invalid.append({
                'nomor_baris': nomor_baris,
                'status_baris': 'duplikat',
                'data_mentah': str(dict(row)),
                'keterangan': 'Baris duplikat dengan data yang sudah ada di database',
            })
        else:
            seen_keys.add(dup_key)
            cleaned['nomor_baris'] = nomor_baris
            cleaned['subtotal'] = cleaned.get('qty', 0) * cleaned.get('harga_satuan', 0)
            records_valid.append(cleaned)

    statistik = {
        'total_baris': len(df),
        'baris_valid': len(records_valid),
        'baris_invalid': sum(1 for r in records_invalid if r['status_baris'] == 'invalid'),
        'baris_duplikat': sum(1 for r in records_invalid if r['status_baris'] == 'duplikat'),
        'warnings': warnings,
    }

    return records_valid, records_invalid, statistik
