# python/pipeline/loader.py
import pymysql
from config import DB_CONFIG


def insert_transactions(transactions: list, id_upload: int) -> list:
    """Insert header + items + kembalikan log; semua dalam satu transaksi DB."""
    if not transactions:
        return []

    conn = pymysql.connect(**DB_CONFIG)
    cursor = conn.cursor()
    logs = []
    berhasil = 0

    try:
        conn.begin()
        for trans in transactions:
            h = trans['header']
            sql_trans = """
                INSERT INTO transactions
                    (id_user, id_upload, kode_transaksi, tanggal, subtotal, ongkir,
                     diskon, total, alamat_pengiriman, nama_penerima, no_hp_penerima,
                     metode_pembayaran, status_pembayaran, status_pesanan, sumber_data)
                VALUES
                    (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
            """
            cursor.execute(sql_trans, (
                h['id_user'], id_upload, h['kode_transaksi'], h['tanggal'],
                h['subtotal'], h['ongkir'], h['diskon'], h['total'],
                h['alamat_pengiriman'], h['nama_penerima'], h['no_hp_penerima'],
                h['metode_pembayaran'], h['status_pembayaran'],
                h['status_pesanan'], h['sumber_data'],
            ))
            id_transaction = cursor.lastrowid

            for item in trans['items']:
                sql_item = """
                    INSERT INTO transaction_items
                        (id_transaction, id_product, nama_snapshot,
                         harga_snapshot, qty, harga, subtotal)
                    VALUES (%s,%s,%s,%s,%s,%s,%s)
                """
                cursor.execute(sql_item, (
                    id_transaction, item['id_product'], item['nama_snapshot'],
                    item['harga_snapshot'], item['qty'], item['harga_snapshot'],
                    item['subtotal'],
                ))
                logs.append({
                    'id_upload': id_upload,
                    'nomor_baris': item['nomor_baris'],
                    'status_baris': 'imported',
                    'id_transaction': id_transaction,
                    'keterangan': f'Berhasil -> id_transaction={id_transaction}',
                })
                berhasil += 1

        # Tandai model rekomendasi perlu dihitung ulang
        cursor.execute("""
            INSERT INTO system_settings (setting_key, setting_value, updated_at)
            VALUES ('recommendation_dirty', '1', NOW())
            ON DUPLICATE KEY UPDATE setting_value = '1', updated_at = NOW()
        """)
        conn.commit()
    except Exception:
        conn.rollback()
        conn.close()
        raise

    conn.close()
    print(f"  [OK] {berhasil} item berhasil diimport ke database")
    return logs


def save_logs(logs: list, id_upload: int) -> None:
    if not logs:
        return
    conn = pymysql.connect(**DB_CONFIG)
    cursor = conn.cursor()
    try:
        conn.begin()
        sql = """
            INSERT INTO upload_logs
                (id_upload, nomor_baris, status_baris, data_mentah,
                 data_bersih, id_transaction, keterangan)
            VALUES (%s,%s,%s,%s,%s,%s,%s)
        """
        data = [(
            l['id_upload'], l['nomor_baris'], l['status_baris'],
            l.get('data_mentah'), l.get('data_bersih'),
            l.get('id_transaction'), l.get('keterangan'),
        ) for l in logs]
        cursor.executemany(sql, data)
        conn.commit()
    except Exception:
        conn.rollback()
        raise
    finally:
        conn.close()


def update_upload_status(id_upload: int, status: str, stats: dict) -> None:
    conn = pymysql.connect(**DB_CONFIG)
    cursor = conn.cursor()
    sql = """
        UPDATE data_uploads SET
            status        = %s,
            total_baris   = %s,
            baris_valid   = %s,
            baris_invalid = %s,
            baris_duplikat= %s,
            baris_diimport= %s,
            pesan_error   = %s,
            processed_at  = NOW()
        WHERE id_upload = %s
    """
    cursor.execute(sql, (
        status,
        stats.get('total_baris', 0),
        stats.get('baris_valid', 0),
        stats.get('baris_invalid', 0),
        stats.get('baris_duplikat', 0),
        stats.get('baris_diimport', 0),
        stats.get('pesan_error'),
        id_upload,
    ))
    conn.commit()
    conn.close()
