# python/cf/cf_engine.py
import pymysql
import sys
import os
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..'))
from cf.similarity import run_similarity_analysis
from config import DB_CONFIG, CF_CONFIG
from datetime import datetime


def _clear_recommendation_dirty(cursor) -> None:
    cursor.execute("""
        INSERT INTO system_settings (setting_key, setting_value, updated_at)
        VALUES ('recommendation_dirty', '0', NOW())
        ON DUPLICATE KEY UPDATE setting_value = '0', updated_at = NOW()
    """)


def run_cf_engine() -> dict:
    print("=" * 55)
    print("  ITEM-BASED CF ENGINE (COSINE) - TOKO SINAR MANIS")
    print("=" * 55)

    start = datetime.now()
    sim_df, co_df, pairs = run_similarity_analysis()

    total_products = int(len(sim_df.index)) if not sim_df.empty else 0
    total_users = 0
    if not sim_df.empty:
        # users ≈ non-zero columns across matrix; re-load via data_loader stats
        from cf.data_loader import load_transaction_data
        df = load_transaction_data()
        total_users = int(df['id_user'].nunique()) if not df.empty else 0

    scores = [p[2] for p in pairs]
    max_score = max(scores) if scores else 0.0
    avg_score = (sum(scores) / len(scores)) if scores else 0.0
    expected_pairs = (total_products * (total_products - 1)) / 2 if total_products > 1 else 0
    coverage = (len(pairs) / expected_pairs * 100) if expected_pairs > 0 else 0.0

    if not pairs:
        print("[WARN] Tidak ada pasangan similarity yang memenuhi ambang. Data lama tidak diubah.")
        elapsed = (datetime.now() - start).total_seconds()
        conn = pymysql.connect(**DB_CONFIG)
        cursor = conn.cursor()
        cursor.execute("""
            INSERT INTO cf_run_logs
                (started_at, finished_at, total_users, total_products, total_pairs,
                 coverage, max_score, avg_score, duration_seconds, status, error_message)
            VALUES (%s, NOW(), %s, %s, 0, %s, %s, %s, %s, 'failed', %s)
        """, (
            start.strftime('%Y-%m-%d %H:%M:%S'),
            total_users,
            total_products,
            round(coverage, 2),
            round(max_score, 6),
            round(avg_score, 6),
            int(elapsed),
            'Hasil kosong: tidak ada pasangan dengan co-occurrence >= '
            f"{CF_CONFIG['min_co_occurrence']}",
        ))
        conn.commit()
        conn.close()
        return {
            'status': 'failed',
            'total_pairs': 0,
            'total_users': total_users,
            'total_products': total_products,
        }

    conn = pymysql.connect(**DB_CONFIG)
    cursor = conn.cursor()

    sql = """
        INSERT INTO product_similarity
            (product_a, product_b, score, co_occurrence, source, updated_at)
        VALUES (%s,%s,%s,%s,'cf_purchase',NOW())
    """
    batch = CF_CONFIG['batch_size']

    all_pairs = []
    for (a, b, score, co) in pairs:
        all_pairs.append((a, b, score, co))
        all_pairs.append((b, a, score, co))

    try:
        conn.begin()
        cursor.execute("DELETE FROM product_similarity")
        for i in range(0, len(all_pairs), batch):
            cursor.executemany(sql, all_pairs[i:i + batch])
        _clear_recommendation_dirty(cursor)

        elapsed = (datetime.now() - start).total_seconds()
        cursor.execute("""
            INSERT INTO cf_run_logs
                (started_at, finished_at, total_users, total_products, total_pairs,
                 coverage, max_score, avg_score, duration_seconds, status)
            VALUES (%s, NOW(), %s, %s, %s, %s, %s, %s, %s, 'success')
        """, (
            start.strftime('%Y-%m-%d %H:%M:%S'),
            total_users,
            total_products,
            len(pairs),
            round(coverage, 2),
            round(max_score, 6),
            round(avg_score, 6),
            int(elapsed),
        ))
        conn.commit()
        print(f"[OK] {len(pairs)} pasang similarity disimpan dalam {int(elapsed)} detik!")
    except Exception as e:
        conn.rollback()
        elapsed = (datetime.now() - start).total_seconds()
        print(f"[ERROR] Gagal menyimpan similarity: {e}")
        try:
            cursor.execute("""
                INSERT INTO cf_run_logs
                    (started_at, finished_at, total_users, total_products, total_pairs,
                     coverage, max_score, avg_score, duration_seconds, status, error_message)
                VALUES (%s, NOW(), %s, %s, %s, %s, %s, %s, %s, 'failed', %s)
            """, (
                start.strftime('%Y-%m-%d %H:%M:%S'),
                total_users,
                total_products,
                len(pairs),
                round(coverage, 2),
                round(max_score, 6),
                round(avg_score, 6),
                int(elapsed),
                str(e)[:500],
            ))
            conn.commit()
        except Exception:
            pass
        conn.close()
        raise
    finally:
        conn.close()

    return {
        'status': 'success',
        'total_pairs': len(pairs),
        'total_users': total_users,
        'total_products': total_products,
        'coverage': round(coverage, 2),
        'max_score': round(max_score, 6),
        'avg_score': round(avg_score, 6),
    }
