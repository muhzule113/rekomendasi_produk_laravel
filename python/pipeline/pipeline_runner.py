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
from config import DB_CONFIG, UPLOAD_DONE_DIR, UPLOAD_REJ_DIR


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
            id_upload,
        ))
        conn.commit()
        conn.close()
    except Exception as e:
        print(f"[FATAL] Gagal update status ke DB: {e}")


def _detect_sumber_data(path_file: str) -> str:
    ext = os.path.splitext(path_file)[1].lower()
    if ext == '.csv':
        return 'upload_csv'
    if ext in ('.xlsx', '.xls'):
        return 'upload_excel'
    return 'upload_csv'


def run_pipeline(id_upload: int) -> None:
    print("=" * 55)
    print(f"  PIPELINE RUNNER - Upload ID: {id_upload}")
    print("=" * 55)

    import shutil

    from pipeline.ingest import read_file, detect_columns
    from pipeline.cleaner import clean_dataframe
    from pipeline.user_resolver import resolve_users
    from pipeline.transformer import group_into_transactions
    from pipeline.loader import insert_transactions, save_logs, update_upload_status

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
    sumber_data = _detect_sumber_data(path_file)

    update_status(id_upload, 'memproses')

    try:
        print("\n[1/4] Membaca file...")
        df = read_file(path_file)
        col_map = detect_columns(df)
        print(f"  Kolom terdeteksi: {col_map}")
        update_status(id_upload, 'memproses', {'total_baris': len(df)})

        print("\n[2/4] Cleaning & validasi data...")
        records_valid, records_invalid, statistik = clean_dataframe(df, col_map)
        print(f"  Valid: {statistik['baris_valid']} | "
              f"Invalid: {statistik['baris_invalid']} | "
              f"Duplikat: {statistik['baris_duplikat']}")

        print("\n[2.5/4] Resolve user (tanpa auto-create)...")
        records_valid, rejected_users = resolve_users(records_valid, id_upload)
        records_invalid.extend(rejected_users)
        statistik['baris_valid'] = len(records_valid)
        statistik['baris_invalid'] = sum(
            1 for r in records_invalid if r['status_baris'] == 'invalid'
        )
        print(f"  User resolved: {len(records_valid)} records")

        print("\n[3/4] Transformasi ke format DB...")
        transactions, transform_warnings = group_into_transactions(
            records_valid, sumber_data=sumber_data
        )
        warnings = list(statistik.get('warnings') or []) + transform_warnings
        print(f"  {len(transactions)} transaksi terbentuk")

        print("\n[4/4] Insert ke database...")
        logs_valid = insert_transactions(transactions, id_upload)
        logs_invalid = [{
            'id_upload': id_upload,
            'nomor_baris': r['nomor_baris'],
            'status_baris': r['status_baris'],
            'data_mentah': r.get('data_mentah'),
            'data_bersih': None,
            'id_transaction': None,
            'keterangan': r['keterangan'],
        } for r in records_invalid]
        save_logs(logs_valid + logs_invalid, id_upload)

        statistik['baris_diimport'] = len(logs_valid)
        if warnings:
            statistik['pesan_error'] = 'Peringatan: ' + ' | '.join(warnings)

        # Pindahkan file ke processed HANYA setelah insert + status sukses
        update_upload_status(id_upload, 'selesai', statistik)
        shutil.move(path_file, os.path.join(UPLOAD_DONE_DIR, info['nama_file_disk']))

        print(f"\n[OK] Pipeline selesai! {statistik['baris_diimport']} item diimport.")
        if warnings:
            print(f"[WARN] {statistik['pesan_error']}")

        print("\nMenjalankan CF Engine untuk update rekomendasi...")
        import subprocess
        batch_path = os.path.join(os.path.dirname(__file__), '..', 'batch_runner.py')
        py = sys.executable or ('python' if os.name == 'nt' else 'python3')
        if os.name == 'nt':
            subprocess.Popen(
                [py, batch_path],
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL,
                creationflags=subprocess.CREATE_NO_WINDOW,
            )
        else:
            subprocess.Popen(
                [py, batch_path],
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL,
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

    update_status(args.upload_id, 'memproses')

    try:
        run_pipeline(args.upload_id)
    except Exception:
        sys.exit(1)
