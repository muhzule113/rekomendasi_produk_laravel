# python/cf/cf_engine.py
import pymysql
import sys
import os
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..'))
from cf.similarity import run_similarity_analysis
from config import DB_CONFIG, CF_CONFIG
from datetime import datetime


def run_cf_engine() -> None:
    print("=" * 55)
    print("  ITEM-BASED CF ENGINE - TOKO SINAR MANIS")
    print("=" * 55)

    start = datetime.now()

    sim_df, co_df = run_similarity_analysis()
    products = sim_df.index.tolist()
    pairs    = []

    for i in range(len(products)):
        row     = sim_df.iloc[i]
        top     = row.iloc[i+1:].nlargest(CF_CONFIG['top_k'])
        for j_label, score in top.items():
            if score >= CF_CONFIG['min_score']:
                a, b = int(products[i]), int(j_label)
                try:
                    co = int(co_df.loc[a, b])
                except:
                    co = 0
                pairs.append((a, b, float(score), co))

    conn   = pymysql.connect(**DB_CONFIG)
    cursor = conn.cursor()

    # Hapus data lama
    cursor.execute("DELETE FROM product_similarity WHERE source = 'cf_purchase'")

    # Insert bidirectional
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

    for i in range(0, len(all_pairs), batch):
        cursor.executemany(sql, all_pairs[i:i+batch])
        conn.commit()

    elapsed = (datetime.now() - start).seconds
    print(f"[OK] {len(pairs)} pasang similarity disimpan ke database dalam {elapsed} detik!")

    # Log ke cf_run_logs
    cursor.execute("""
        INSERT INTO cf_run_logs
            (started_at, finished_at, total_pairs, duration_seconds, status)
        VALUES (%s, NOW(), %s, %s, 'success')
    """, (start.strftime('%Y-%m-%d %H:%M:%S'), len(pairs), elapsed))
    conn.commit()
    conn.close()
