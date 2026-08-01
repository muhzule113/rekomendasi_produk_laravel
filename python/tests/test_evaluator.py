import os
import sys
import unittest
from datetime import datetime, timedelta

sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..'))

import pandas as pd
from cf.evaluator import prepare_holdout_splits, recommend_top_k, evaluate_at_k
from cf.similarity import build_item_user_matrix, compute_cosine_similarity, extract_unique_pairs


class EvaluatorTest(unittest.TestCase):
    def test_time_based_holdout_not_set_order(self):
        t0 = datetime(2025, 1, 1)
        events = pd.DataFrame([
            {'id_user': 1, 'id_product': 10, 'tanggal': t0 + timedelta(days=2), 'id_transaction': 3},
            {'id_user': 1, 'id_product': 20, 'tanggal': t0 + timedelta(days=0), 'id_transaction': 1},
            {'id_user': 1, 'id_product': 30, 'tanggal': t0 + timedelta(days=1), 'id_transaction': 2},
        ])
        splits = prepare_holdout_splits(events)
        self.assertEqual(len(splits), 1)
        self.assertEqual(splits[0]['test_item'], 10)  # latest by time
        self.assertNotIn(10, splits[0]['train_items'])
        self.assertEqual(sorted(splits[0]['train_items']), [20, 30])

    def test_topk_no_duplicates_and_manual_hit(self):
        # Train items 1,2 ; candidate 3 similar to both
        sim_map = {
            (1, 3): 0.8,
            (3, 1): 0.8,
            (2, 3): 0.6,
            (3, 2): 0.6,
            (1, 4): 0.1,
            (4, 1): 0.1,
        }
        top = recommend_top_k([1, 2], sim_map, k=2)
        self.assertEqual(len(top), len(set(top)))
        self.assertEqual(top[0], 3)
        metrics = evaluate_at_k(
            [{'train_items': [1, 2], 'test_item': 3}],
            sim_map,
            k=1,
            catalog_size=4,
        )
        self.assertEqual(metrics['hit_rate'], 1.0)
        self.assertEqual(metrics['precision'], 1.0)

    def test_similarity_from_train_excludes_holdout_pair_leakage(self):
        # If holdout product 99 only co-occurs in test, it must not appear in train sim
        train_df = pd.DataFrame([
            {'id_user': 1, 'id_product': 1, 'qty': 1},
            {'id_user': 1, 'id_product': 2, 'qty': 1},
            {'id_user': 2, 'id_product': 1, 'qty': 1},
            {'id_user': 2, 'id_product': 2, 'qty': 1},
        ])
        matrix = build_item_user_matrix(train_df)
        sim_df, co_df = compute_cosine_similarity(matrix, min_co_occurrence=1)
        pairs = extract_unique_pairs(sim_df, co_df)
        products = {a for a, b, _, _ in pairs} | {b for a, b, _, _ in pairs}
        self.assertNotIn(99, products)


if __name__ == '__main__':
    unittest.main()
