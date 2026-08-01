# python/pipeline/transformer.py
from datetime import datetime
from itertools import groupby
import pymysql
from config import DB_CONFIG


def get_next_transaction_counter() -> int:
    conn = pymysql.connect(**DB_CONFIG)
    cursor = conn.cursor()
    cursor.execute("SELECT COUNT(*) FROM transactions WHERE DATE(tanggal) = CURDATE()")
    count = cursor.fetchone()[0]
    conn.close()
    return count + 1


def get_product_names(product_ids: set) -> dict:
    if not product_ids:
        return {}
    conn = pymysql.connect(**DB_CONFIG)
    cursor = conn.cursor()
    placeholders = ','.join(['%s'] * len(product_ids))
    cursor.execute(
        f"SELECT id_product, nama_product FROM products WHERE id_product IN ({placeholders})",
        list(product_ids),
    )
    names = {row[0]: row[1] for row in cursor.fetchall()}
    conn.close()
    return names


def get_user_profiles(user_ids: set) -> dict:
    if not user_ids:
        return {}
    conn = pymysql.connect(**DB_CONFIG)
    cursor = conn.cursor()
    placeholders = ','.join(['%s'] * len(user_ids))
    cursor.execute(
        f"SELECT id_user, nama, no_hp, alamat FROM users WHERE id_user IN ({placeholders})",
        list(user_ids),
    )
    profiles = {
        row[0]: {
            'nama': row[1] or f'Pelanggan {row[0]}',
            'no_hp': row[2] or '',
            'alamat': row[3] or 'Diisi dari data upload',
        }
        for row in cursor.fetchall()
    }
    conn.close()
    return profiles


def group_into_transactions(records: list, sumber_data: str = 'upload_csv') -> tuple[list, list]:
    """
    Kelompokkan baris menjadi transaksi.
    Prioritas kunci grup: kode_transaksi; fallback: (id_user, tanggal).
    Returns (transactions, warnings).
    """
    if not records:
        return [], []

    warnings = []
    product_ids = {r['id_product'] for r in records}
    user_ids = {r['id_user'] for r in records}
    product_names = get_product_names(product_ids)
    profiles = get_user_profiles(user_ids)

    used_fallback = False
    for r in records:
        if not r.get('kode_transaksi'):
            used_fallback = True
            break
    if used_fallback:
        warnings.append(
            'Sebagian/seluruh baris tanpa kode_transaksi dikelompokkan dengan fallback id_user+tanggal.'
        )

    def group_key(r):
        kode = r.get('kode_transaksi') or ''
        if kode:
            return ('kode', kode)
        return ('legacy', r['id_user'], r['tanggal'])

    records_sorted = sorted(records, key=lambda r: group_key(r))
    counter = get_next_transaction_counter()
    transactions = []

    for key, group in groupby(records_sorted, key=group_key):
        items = list(group)
        subtotal = sum(i['subtotal'] for i in items)
        tgl = items[0]['tanggal']
        uid = items[0]['id_user']
        profile = profiles.get(uid, {
            'nama': f'Pelanggan {uid}',
            'no_hp': '',
            'alamat': 'Diisi dari data upload',
        })

        if key[0] == 'kode':
            kode = key[1]
        else:
            tgl_dt = datetime.strptime(tgl, '%Y-%m-%d %H:%M:%S')
            kode = f"TRX-{tgl_dt.strftime('%Y%m%d')}-{counter:05d}"
            counter += 1

        metode = items[0].get('metode_pembayaran', 'Tunai')
        status = items[0].get('status_pesanan', 'Selesai')
        s_bayar = items[0].get('status_pembayaran')
        if not s_bayar:
            s_bayar = 'Dibayar' if status in ['Selesai', 'Dikirim'] else 'Belum Dibayar'

        transactions.append({
            'header': {
                'id_user': uid,
                'kode_transaksi': kode,
                'tanggal': tgl,
                'subtotal': subtotal,
                'ongkir': 0,
                'diskon': 0,
                'total': subtotal,
                'alamat_pengiriman': profile['alamat'] or 'Diisi dari data upload',
                'nama_penerima': profile['nama'],
                'no_hp_penerima': profile['no_hp'] or '08000000000',
                'metode_pembayaran': metode,
                'status_pembayaran': s_bayar,
                'status_pesanan': status,
                'sumber_data': sumber_data,
            },
            'items': [{
                'id_product': i['id_product'],
                'nama_snapshot': product_names.get(i['id_product'], f"Produk {i['id_product']}"),
                'harga_snapshot': i['harga_satuan'],
                'qty': i['qty'],
                'subtotal': i['subtotal'],
                'nomor_baris': i['nomor_baris'],
            } for i in items],
        })

    return transactions, warnings
