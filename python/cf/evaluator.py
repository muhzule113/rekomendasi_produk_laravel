# python/cf/evaluator.py
import pymysql
import sys
import os
import numpy as np
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..'))
from config import DB_CONFIG


def run_evaluation(k: int = 5) -> dict:
    """
    Hitung Precision@K, Recall@K, F1-Score@K.
    Mengukur seberapa akurat rekomendasi Item-Based CF.
    """
    conn = pymysql.connect(**DB_CONFIG)
    cursor = conn.cursor()

    # Ambil semua produk yang dibeli tiap user (ground truth)
    cursor.execute("""
        SELECT t.id_user, ti.id_product
        FROM transaction_items ti
        JOIN transactions t ON ti.id_transaction = t.id_transaction
        WHERE t.status_pesanan = 'Selesai'
        GROUP BY t.id_user, ti.id_product
    """)
    rows = cursor.fetchall()

    # Bangun ground truth: user -> set of product IDs
    user_items = {}
    for uid, pid in rows:
        user_items.setdefault(uid, set()).add(pid)

    precision_list = []
    recall_list    = []

    for user_id, relevant_items in user_items.items():
        if len(relevant_items) < 2:
            continue

        # Split: ambil 1 produk terakhir sebagai "holdout", sisanya sebagai "riwayat"
        relevant_list = list(relevant_items)
        test_item = relevant_list[-1]
        train_items = relevant_list[:-1]

        if not train_items:
            continue

        # Cari rekomendasi dari similarity
        train_str = ','.join(map(str, train_items))
        cursor.execute(f"""
            SELECT ps.product_b
            FROM product_similarity ps
            WHERE ps.product_a IN ({train_str})
            AND ps.product_b NOT IN ({train_str})
            ORDER BY ps.score DESC
            LIMIT {k}
        """)
        recommended = [row[0] for row in cursor.fetchall()]

        if not recommended:
            continue

        hits = len(set(recommended) & {test_item})
        precision = hits / k
        recall    = hits / 1

        precision_list.append(precision)
        recall_list.append(recall)

    conn.close()

    avg_precision = np.mean(precision_list) if precision_list else 0
    avg_recall    = np.mean(recall_list) if recall_list else 0
    f1_score      = 2 * avg_precision * avg_recall / (avg_precision + avg_recall) if (avg_precision + avg_recall) > 0 else 0

    results = {
        'precision': round(float(avg_precision), 4),
        'recall':    round(float(avg_recall), 4),
        'f1_score':  round(float(f1_score), 4),
        'users_evaluated': len(precision_list),
        'k': k,
    }

    print(f"[EVAL] Precision@{k}: {results['precision']:.4f}")
    print(f"[EVAL] Recall@{k}:    {results['recall']:.4f}")
    print(f"[EVAL] F1-Score@{k}:  {results['f1_score']:.4f}")
    print(f"[EVAL] Users evaluated: {results['users_evaluated']}")

    return results


if __name__ == '__main__':
    run_evaluation(k=5)
