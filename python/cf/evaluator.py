# python/cf/evaluator.py
"""
Evaluasi akademik Item-Based CF tanpa data leakage.

Desain:
1. Transaksi valid: Selesai + Dibayar
2. Urutkan interaksi per user berdasarkan waktu + id_transaction
3. Holdout item terbaru sebagai test; sisanya train
4. Bangun ulang similarity HANYA dari data latih (bukan tabel produksi)
5. Top-K dengan agregasi sama seperti aplikasi:
   prediction = sum(sim(purchased, candidate)) / |purchased|
"""
from __future__ import annotations

import sys
import os
from collections import defaultdict
from datetime import datetime

import numpy as np
import pandas as pd
import pymysql

sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..'))
from config import DB_CONFIG, CF_CONFIG
from cf.data_loader import load_interaction_events
from cf.similarity import (
    build_item_user_matrix,
    compute_cosine_similarity,
    extract_unique_pairs,
)


def _build_similarity_from_train(
    train_df: pd.DataFrame,
    min_co_occurrence: int,
) -> dict[tuple[int, int], float]:
    """Bangun peta similarity (a,b)->score dari interaksi latih saja."""
    if train_df.empty:
        return {}

    agg = (
        train_df.groupby(['id_user', 'id_product'], as_index=False)['qty']
        .sum()
        if 'qty' in train_df.columns
        else train_df.assign(qty=1).groupby(['id_user', 'id_product'], as_index=False)['qty'].sum()
    )

    matrix = build_item_user_matrix(agg)
    sim_df, co_df = compute_cosine_similarity(matrix, min_co_occurrence=min_co_occurrence)
    pairs = extract_unique_pairs(sim_df, co_df)

    sim_map: dict[tuple[int, int], float] = {}
    for a, b, score, _co in pairs:
        sim_map[(a, b)] = score
        sim_map[(b, a)] = score
    return sim_map


def recommend_top_k(
    purchased: list[int],
    sim_map: dict[tuple[int, int], float],
    k: int,
) -> list[int]:
    """Agregasi prediksi sama dengan RecommenderService::recommendForCustomer."""
    if not purchased or k <= 0:
        return []

    purchased_set = set(purchased)
    n = len(purchased)
    scores: dict[int, float] = defaultdict(float)
    co_tie: dict[int, int] = defaultdict(int)

    for item in purchased:
        for (a, b), score in sim_map.items():
            if a != item:
                continue
            if b in purchased_set:
                continue
            scores[b] += score
            co_tie[b] += 1  # proxy tie-break: more supporting edges

    ranked = sorted(
        scores.items(),
        key=lambda x: (-(x[1] / n), -co_tie[x[0]], x[0]),
    )
    return [pid for pid, _ in ranked[:k]]


def prepare_holdout_splits(events: pd.DataFrame) -> list[dict]:
    """
    Untuk setiap user dengan >= 2 produk berbeda:
    - urutkan interaksi by tanggal, id_transaction, id_product
    - first-seen order of distinct products
    - holdout = produk terakhir; train = sisanya
    """
    if events.empty:
        return []

    df = events.copy()
    df['tanggal'] = pd.to_datetime(df['tanggal'])
    df = df.sort_values(
        ['id_user', 'tanggal', 'id_transaction', 'id_product']
    ).reset_index(drop=True)

    splits = []
    for user_id, group in df.groupby('id_user', sort=True):
        group = group.reset_index(drop=True)

        # First appearance order of each product (time-ordered)
        seen = []
        seen_set = set()
        first_pos = {}
        for pos, row in group.iterrows():
            pid = int(row['id_product'])
            if pid not in seen_set:
                seen.append(pid)
                seen_set.add(pid)
                first_pos[pid] = pos

        if len(seen) < 2:
            continue

        test_item = seen[-1]
        # Train events: semua baris sebelum kemunculan pertama item holdout
        train_events = group.iloc[: first_pos[test_item]].copy()
        train_products = set(int(x) for x in train_events['id_product'].tolist())
        train_products.discard(test_item)

        if not train_products:
            continue

        splits.append({
            'user_id': int(user_id),
            'train_items': sorted(train_products),
            'test_item': test_item,
            'train_events': train_events,
        })

    return splits


def evaluate_at_k(
    splits: list[dict],
    sim_map: dict[tuple[int, int], float],
    k: int,
    catalog_size: int,
) -> dict:
    precision_list = []
    recall_list = []
    hit_list = []
    recommended_catalog: set[int] = set()

    for split in splits:
        top_k = recommend_top_k(split['train_items'], sim_map, k)
        # Pastikan tidak ada duplikat
        assert len(top_k) == len(set(top_k))

        recommended_catalog.update(top_k)
        hits = 1 if split['test_item'] in top_k else 0
        precision_list.append(hits / k)
        recall_list.append(hits / 1.0)
        hit_list.append(hits)

    n = len(precision_list)
    avg_p = float(np.mean(precision_list)) if n else 0.0
    avg_r = float(np.mean(recall_list)) if n else 0.0
    f1 = (2 * avg_p * avg_r / (avg_p + avg_r)) if (avg_p + avg_r) > 0 else 0.0
    hit_rate = float(np.mean(hit_list)) if n else 0.0
    catalog_coverage = (len(recommended_catalog) / catalog_size * 100) if catalog_size > 0 else 0.0

    return {
        'k': k,
        'precision': round(avg_p, 6),
        'recall': round(avg_r, 6),
        'f1_score': round(f1, 6),
        'hit_rate': round(hit_rate, 6),
        'catalog_coverage': round(catalog_coverage, 4),
        'users_evaluated': n,
        'unique_recommended': len(recommended_catalog),
    }


def run_evaluation(k_values: list[int] | None = None, persist: bool = True) -> dict:
    if k_values is None:
        k_values = [5, 10]

    start = datetime.now()
    events = load_interaction_events()
    if events.empty:
        print('[EVAL] Tidak ada data interaksi valid.')
        return {'results': [], 'users_evaluated': 0}

    events = events.assign(qty=1)
    splits = prepare_holdout_splits(events)
    if not splits:
        print('[EVAL] Tidak ada user yang memenuhi syarat holdout.')
        return {'results': [], 'users_evaluated': 0}

    # Bangun train pool gabungan untuk similarity (tanpa item holdout tiap user)
    train_frames = []
    for split in splits:
        te = split['train_events'].copy()
        te['qty'] = 1
        train_frames.append(te[['id_user', 'id_product', 'qty']])

    # Juga sertakan user yang tidak dievaluasi? Untuk fairness academic,
    # similarity hanya dari train interactions of evaluated users +
    # full history of non-evaluated users (yang <2 products) — mereka
    # tidak punya holdout sehingga tidak bocor.
    evaluated_users = {s['user_id'] for s in splits}
    other = events[~events['id_user'].isin(evaluated_users)].copy()
    if not other.empty:
        other['qty'] = 1
        train_frames.append(other[['id_user', 'id_product', 'qty']])

    train_df = pd.concat(train_frames, ignore_index=True) if train_frames else pd.DataFrame()
    min_co = CF_CONFIG['min_co_occurrence']
    sim_map = _build_similarity_from_train(train_df, min_co)

    catalog_size = int(events['id_product'].nunique())
    results = []
    for k in k_values:
        metrics = evaluate_at_k(splits, sim_map, k, catalog_size)
        results.append(metrics)
        print(f"[EVAL] Precision@{k}: {metrics['precision']:.4f}")
        print(f"[EVAL] Recall@{k}:    {metrics['recall']:.4f}")
        print(f"[EVAL] F1@{k}:        {metrics['f1_score']:.4f}")
        print(f"[EVAL] HitRate@{k}:   {metrics['hit_rate']:.4f}")
        print(f"[EVAL] CatalogCov@{k}:{metrics['catalog_coverage']:.2f}%")
        print(f"[EVAL] Users:         {metrics['users_evaluated']}")

    elapsed = (datetime.now() - start).total_seconds()
    payload = {
        'method': 'ibcf_cosine_time_holdout',
        'min_co_occurrence': min_co,
        'users_evaluated': results[0]['users_evaluated'] if results else 0,
        'catalog_size': catalog_size,
        'duration_seconds': round(elapsed, 4),
        'results': results,
    }

    if persist and results:
        _persist_evaluation(payload)

    return payload


def _persist_evaluation(payload: dict) -> None:
    conn = pymysql.connect(**DB_CONFIG)
    cursor = conn.cursor()
    try:
        for m in payload['results']:
            cursor.execute("""
                INSERT INTO evaluation_logs
                    (evaluated_at, method, k_value, users_evaluated, precision_at_k,
                     recall_at_k, f1_at_k, hit_rate_at_k, catalog_coverage_at_k,
                     duration_seconds, notes)
                VALUES (NOW(), %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """, (
                payload['method'],
                m['k'],
                m['users_evaluated'],
                m['precision'],
                m['recall'],
                m['f1_score'],
                m['hit_rate'],
                m['catalog_coverage'],
                payload['duration_seconds'],
                f"min_co={payload['min_co_occurrence']}; catalog={payload['catalog_size']}",
            ))
        conn.commit()
    except Exception as e:
        conn.rollback()
        print(f"[EVAL] Gagal menyimpan evaluation_logs: {e}")
    finally:
        conn.close()


if __name__ == '__main__':
    run_evaluation(k_values=[5, 10])
