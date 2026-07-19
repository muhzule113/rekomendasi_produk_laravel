# python/pipeline/cleaner.py
import pandas as pd
import pymysql
from datetime import datetime
from config import DB_CONFIG, REQUIRED_COLUMNS, DATE_FORMATS


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
    cursor.execute("SELECT id_user FROM users WHERE status = 'aktif'")
    ids = {row[0] for row in cursor.fetchall()}
    conn.close()
    return ids


def lookup_user_by_email(email: str):
    conn = pymysql.connect(**DB_CONFIG)
    cursor = conn.cursor()
    cursor.execute("SELECT id_user FROM users WHERE email = %s AND status = 'aktif'", (email,))
    row = cursor.fetchone()
    conn.close()
    return row[0] if row else None


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
    valid_users    = get_valid_user_ids()

    records_valid   = []
    records_invalid = []

    df = df.copy()
    df.dropna(how='all', inplace=True)
    df.reset_index(drop=True, inplace=True)

    seen_keys = set()

    for idx, row in df.iterrows():
        nomor_baris = idx + 2
        errors = []
        cleaned = {}

        # --- Tanggal ---
        raw_tgl = row.get(col_map.get('tanggal', ''), '')
        tgl = parse_tanggal(raw_tgl)
        if tgl is None:
            errors.append(f"tanggal tidak valid: '{raw_tgl}'")
        else:
            cleaned['tanggal'] = tgl.strftime('%Y-%m-%d %H:%M:%S')

        # --- id_product ---
        raw_pid = row.get(col_map.get('id_product', ''), '')
        try:
            pid = int(float(str(raw_pid).strip()))
            if pid not in valid_products:
                errors.append(f"id_product {pid} tidak ada di database")
            else:
                cleaned['id_product'] = pid
        except (ValueError, TypeError):
            errors.append(f"id_product tidak valid: '{raw_pid}'")

        # --- qty ---
        raw_qty = row.get(col_map.get('qty', ''), '')
        qty = clean_angka(raw_qty)
        if qty is None or qty <= 0:
            errors.append(f"qty tidak valid: '{raw_qty}'")
        else:
            cleaned['qty'] = int(qty)

        # --- harga_satuan ---
        raw_harga = row.get(col_map.get('harga_satuan', ''), '')
        harga = clean_angka(raw_harga)
        if harga is None or harga <= 0:
            errors.append(f"harga_satuan tidak valid: '{raw_harga}'")
        else:
            cleaned['harga_satuan'] = harga

        # --- email (opsional) ---
        raw_email = str(row.get(col_map.get('email', ''), '')).strip()
        cleaned['email'] = raw_email if raw_email and raw_email.lower() not in ['', 'nan', 'none'] else ''

        # --- no_hp (opsional) ---
        raw_hp = str(row.get(col_map.get('no_hp', ''), '')).strip()
        cleaned['no_hp'] = raw_hp if raw_hp and raw_hp.lower() not in ['', 'nan', 'none'] else ''

        # --- id_user (opsional) ---
        raw_uid = row.get(col_map.get('id_user', ''), '')
        try:
            uid = int(float(str(raw_uid).strip()))
            cleaned['id_user'] = uid if uid in valid_users else 1
        except (ValueError, TypeError):
            # id_user tidak ada di CSV — coba cari via email
            if cleaned.get('email'):
                found = lookup_user_by_email(cleaned['email'])
                if found:
                    cleaned['id_user'] = found
                else:
                    # Email belum terdaftar — tandai 0 untuk auto-create nanti
                    cleaned['id_user'] = 0
            else:
                cleaned['id_user'] = 1

        # --- metode_pembayaran (opsional) ---
        raw_metode = str(row.get(col_map.get('metode_pembayaran', ''), 'Tunai')).strip()
        metode_map = {
            'tunai': 'Tunai', 'cash': 'Tunai',
            'transfer': 'Transfer', 'bank': 'Transfer', 'bca': 'Transfer',
            'qris': 'QRIS', 'qr': 'QRIS', 'gopay': 'QRIS', 'ovo': 'QRIS',
        }
        cleaned['metode_pembayaran'] = metode_map.get(raw_metode.lower(), 'Tunai')

        # --- status_pesanan (opsional) ---
        raw_status = str(row.get(col_map.get('status_pesanan', ''), 'Selesai')).strip()
        status_map = {
            'selesai': 'Selesai', 'done': 'Selesai', 'complete': 'Selesai',
            'diproses': 'Diproses', 'process': 'Diproses', 'proses': 'Diproses',
            'dikirim': 'Dikirim', 'kirim': 'Dikirim', 'shipped': 'Dikirim',
            'dibatalkan': 'Dibatalkan', 'batal': 'Dibatalkan', 'cancel': 'Dibatalkan',
        }
        cleaned['status_pesanan'] = status_map.get(raw_status.lower(), 'Selesai')

        # --- Cek duplikat ---
        if cleaned.get('id_user'):
            dup_key = (cleaned['id_user'], cleaned.get('tanggal'),
                       cleaned.get('id_product'), cleaned.get('qty'))
        else:
            dup_key = (cleaned.get('email', ''), cleaned.get('tanggal'),
                       cleaned.get('id_product'), cleaned.get('qty'))

        if errors:
            records_invalid.append({
                'nomor_baris': nomor_baris,
                'status_baris': 'invalid',
                'data_mentah': str(dict(row)),
                'keterangan': '; '.join(errors)
            })
        elif dup_key in seen_keys:
            records_invalid.append({
                'nomor_baris': nomor_baris,
                'status_baris': 'duplikat',
                'data_mentah': str(dict(row)),
                'keterangan': 'Baris duplikat dengan data sebelumnya'
            })
        else:
            seen_keys.add(dup_key)
            cleaned['nomor_baris'] = nomor_baris
            cleaned['subtotal'] = cleaned.get('qty', 0) * cleaned.get('harga_satuan', 0)
            records_valid.append(cleaned)

    statistik = {
        'total_baris':    len(df),
        'baris_valid':    len(records_valid),
        'baris_invalid':  sum(1 for r in records_invalid if r['status_baris'] == 'invalid'),
        'baris_duplikat': sum(1 for r in records_invalid if r['status_baris'] == 'duplikat'),
    }

    return records_valid, records_invalid, statistik
