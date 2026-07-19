# python/pipeline/pipeline_runner.py
"""
Orkestrasi ETL Pipeline.
Dipanggil oleh PHP via:
  python pipeline_runner.py --upload_id 42
"""
import sys
import os

sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..'))

import pymysql
from config import DB_CONFIG, UPLOAD_RAW_DIR, UPLOAD_DONE_DIR, UPLOAD_REJ_DIR


def update_status(id_upload: int, status: str, stats: dict = None):
    """Update upload status langsung (fallback jika import gagal)."""
    stats = stats or {}
    try:
        conn = pymysql.connect(**DB_CONFIG)
        cursor = conn.cursor()
        cursor.execute("""
            UPDATE data_uploads SET
                status = %s, total_baris = %s, baris_valid = %s,
                baris_invalid = %s, baris_duplikat = %s,
                baris_diimport = %s, pesan_error = %s, processed_at = NOW()
            WHERE id_upload = %s
        """, (
            status,
            stats.get('total_baris', 0),
            stats.get('baris_valid', 0),
            stats.get('baris_invalid', 0),
            stats.get('baris_duplikat', 0),
            stats.get('baris_diimport', 0),
            stats.get('pesan_error'),
            id_upload
        ))
        conn.commit()
        conn.close()
    except Exception as e:
        print(f"[FATAL] Gagal update status ke DB: {e}")


def run_pipeline(id_upload: int) -> None:
    print("=" * 55)
    print(f"  PIPELINE RUNNER - Upload ID: {id_upload}")
    print("=" * 55)

    import argparse
    import shutil

    from config import UPLOAD_RAW_DIR, UPLOAD_DONE_DIR, UPLOAD_REJ_DIR
    from pipeline.ingest         import read_file, detect_columns
    from pipeline.cleaner        import clean_dataframe
    from pipeline.user_resolver  import resolve_users
    from pipeline.transformer   import group_into_transactions
    from pipeline.loader         import insert_transactions, save_logs, update_upload_status

    conn = pymysql.connect(**DB_CONFIG)
    cursor = conn.cursor(pymysql.cursors.DictCursor)
    cursor.execute("SELECT * FROM data_uploads WHERE id_upload = %s", (id_upload,))
    info = cursor.fetchone()
    conn.close()

    if not info:
        print(f"[ERROR] Upload ID {id_upload} tidak ditemukan!")
        return

    path_file = info['path_file']
    print(f"  File: {info['nama_file_asli']}")

    update_status(id_upload, 'memproses')

    try:
        # STEP 1: Ingest
        print("\n[1/4] Membaca file...")
        df = read_file(path_file)
        col_map = detect_columns(df)
        print(f"  Kolom terdeteksi: {col_map}")

        # STEP 2: Cleaning
        print("\n[2/4] Cleaning & validasi data...")
        records_valid, records_invalid, statistik = clean_dataframe(df, col_map)
        print(f"  Valid: {statistik['baris_valid']} | "
              f"Invalid: {statistik['baris_invalid']} | "
              f"Duplikat: {statistik['baris_duplikat']}")

        # STEP 2.5: Resolve user (auto-create jika perlu)
        print("\n[2.5/4] Resolve user...")
        records_valid = resolve_users(records_valid, id_upload)
        print(f"  User resolved: {len(records_valid)} records")

        # STEP 3: Transform
        print("\n[3/4] Transformasi ke format DB...")
        transactions = group_into_transactions(records_valid)
        print(f"  {len(transactions)} transaksi terbentuk")

        # STEP 4: Load ke DB
        print("\n[4/4] Insert ke database...")
        logs_valid   = insert_transactions(transactions, id_upload)
        logs_invalid = [{
            'id_upload':   id_upload,
            'nomor_baris': r['nomor_baris'],
            'status_baris': r['status_baris'],
            'data_mentah':  r['data_mentah'],
            'data_bersih':  None,
            'id_transaction': None,
            'keterangan':   r['keterangan'],
        } for r in records_invalid]
        save_logs(logs_valid + logs_invalid, id_upload)

        statistik['baris_diimport'] = len(logs_valid)
        update_status(id_upload, 'selesai', statistik)

        shutil.move(path_file, os.path.join(UPLOAD_DONE_DIR, info['nama_file_disk']))

        print(f"\n[OK] Pipeline selesai! {statistik['baris_diimport']} item diimport.")

        print("\nMenjalankan CF Engine untuk update rekomendasi...")
        import subprocess
        batch_path = os.path.join(os.path.dirname(__file__), '..', 'batch_runner.py')
        if os.name == 'nt':
            subprocess.Popen(
                ['python', batch_path],
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL,
                creationflags=subprocess.CREATE_NO_WINDOW
            )
        else:
            subprocess.Popen(
                ['python3', batch_path],
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL
            )

    except Exception as e:
        print(f"\n[ERROR] {e}")
        import traceback
        traceback.print_exc()
        update_status(id_upload, 'gagal', {'pesan_error': str(e)})
        if os.path.exists(path_file):
            shutil.move(path_file, os.path.join(UPLOAD_REJ_DIR, info['nama_file_disk']))
        raise


if __name__ == '__main__':
    import argparse
    parser = argparse.ArgumentParser()
    parser.add_argument('--upload_id', type=int, required=True)
    args = parser.parse_args()

    try:
        run_pipeline(args.upload_id)
    except Exception:
        # Status sudah diupdate di dalam run_pipeline, log sudah tercetak
        sys.exit(1)
