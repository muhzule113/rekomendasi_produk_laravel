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


def group_into_transactions(records: list) -> list:
    """
    Kelompokkan baris-baris menjadi transaksi (header + items).
    Baris yang punya id_user + tanggal yang sama digabung dalam 1 transaksi.
    """
    records_sorted = sorted(records, key=lambda r: (r['id_user'], r['tanggal']))

    counter = get_next_transaction_counter()
    transactions = []

    for (uid, tgl), group in groupby(records_sorted,
                                      key=lambda r: (r['id_user'], r['tanggal'])):
        items = list(group)
        subtotal = sum(i['subtotal'] for i in items)
        total    = subtotal

        tgl_dt = datetime.strptime(tgl, '%Y-%m-%d %H:%M:%S')
        kode   = f"TRX-{tgl_dt.strftime('%Y%m%d')}-{counter:05d}"
        counter += 1

        metode  = items[0].get('metode_pembayaran', 'Tunai')
        status  = items[0].get('status_pesanan', 'Selesai')
        s_bayar = 'Dibayar' if status in ['Selesai', 'Dikirim'] else 'Belum Dibayar'

        transactions.append({
            'header': {
                'id_user':            uid,
                'kode_transaksi':     kode,
                'tanggal':            tgl,
                'subtotal':           subtotal,
                'ongkir':             0,
                'diskon':             0,
                'total':              total,
                'alamat_pengiriman':  'Diisi dari data upload',
                'nama_penerima':      f'Pelanggan {uid}',
                'no_hp_penerima':     '08000000000',
                'metode_pembayaran':  metode,
                'status_pembayaran':  s_bayar,
                'status_pesanan':     status,
                'sumber_data':        'upload_csv',
            },
            'items': [{
                'id_product':    i['id_product'],
                'nama_snapshot': f"Produk {i['id_product']}",
                'harga_snapshot': i['harga_satuan'],
                'qty':           i['qty'],
                'subtotal':      i['subtotal'],
                'nomor_baris':   i['nomor_baris'],
            } for i in items]
        })

    return transactions
