import math
import os
import sys
import unittest

sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..'))

import pandas as pd
from cf.similarity import (
    build_item_user_matrix,
    compute_cosine_similarity,
    cosine_from_binary_vectors,
    extract_unique_pairs,
)


class CosineSimilarityTest(unittest.TestCase):
    def test_mandatory_fixture(self):
        a = [1, 1, 1, 1]
        b = [1, 0, 0, 0]
        c = [0, 1, 1, 0]
        self.assertAlmostEqual(cosine_from_binary_vectors(a, b), 0.5, delta=1e-6)
        self.assertAlmostEqual(cosine_from_binary_vectors(a, c), 0.707107, delta=1e-6)
        self.assertAlmostEqual(cosine_from_binary_vectors(b, c), 0.0, delta=1e-6)

    def test_identical_and_no_intersection(self):
        self.assertAlmostEqual(cosine_from_binary_vectors([1, 1, 0], [1, 1, 0]), 1.0, delta=1e-6)
        self.assertAlmostEqual(cosine_from_binary_vectors([1, 0], [0, 1]), 0.0, delta=1e-6)

    def test_symmetry_and_range(self):
        a = [1, 1, 0, 1]
        b = [1, 0, 1, 1]
        ab = cosine_from_binary_vectors(a, b)
        ba = cosine_from_binary_vectors(b, a)
        self.assertAlmostEqual(ab, ba, delta=1e-6)
        self.assertGreaterEqual(ab, 0.0)
        self.assertLessEqual(ab, 1.0)

    def test_matrix_fixture_no_max_norm_no_diagonal(self):
        # A={u1..u4}, B={u1}, C={u2,u3}
        df = pd.DataFrame([
            {'id_user': 1, 'id_product': 10, 'qty': 1},
            {'id_user': 1, 'id_product': 20, 'qty': 1},
            {'id_user': 2, 'id_product': 10, 'qty': 1},
            {'id_user': 2, 'id_product': 30, 'qty': 1},
            {'id_user': 3, 'id_product': 10, 'qty': 1},
            {'id_user': 3, 'id_product': 30, 'qty': 1},
            {'id_user': 4, 'id_product': 10, 'qty': 1},
        ])
        matrix = build_item_user_matrix(df)
        sim_df, co_df = compute_cosine_similarity(matrix, min_co_occurrence=1)
        self.assertAlmostEqual(float(sim_df.loc[10, 20]), 0.5, delta=1e-6)
        self.assertAlmostEqual(float(sim_df.loc[10, 30]), 0.707107, delta=1e-6)
        self.assertAlmostEqual(float(sim_df.loc[20, 30]), 0.0, delta=1e-6)
        self.assertAlmostEqual(float(sim_df.loc[20, 10]), float(sim_df.loc[10, 20]), delta=1e-6)
        self.assertEqual(float(sim_df.loc[10, 10]), 0.0)
        # No max-normalization: strongest pair ~0.707 not forced to 1
        self.assertLess(float(sim_df.values.max()), 1.0 + 1e-9)
        pairs = extract_unique_pairs(sim_df, co_df)
        self.assertTrue(all(p[2] > 0 for p in pairs))
        self.assertTrue(all(p[0] != p[1] for p in pairs))

    def test_min_co_occurrence(self):
        df = pd.DataFrame([
            {'id_user': 1, 'id_product': 1, 'qty': 1},
            {'id_user': 1, 'id_product': 2, 'qty': 1},
            {'id_user': 2, 'id_product': 1, 'qty': 1},
            {'id_user': 2, 'id_product': 3, 'qty': 1},
            {'id_user': 3, 'id_product': 1, 'qty': 1},
            {'id_user': 3, 'id_product': 3, 'qty': 1},
        ])
        matrix = build_item_user_matrix(df)
        sim_df, co_df = compute_cosine_similarity(matrix, min_co_occurrence=2)
        pairs = extract_unique_pairs(sim_df, co_df)
        pair_set = {(min(a, b), max(a, b)) for a, b, _, _ in pairs}
        self.assertIn((1, 3), pair_set)
        self.assertNotIn((1, 2), pair_set)


if __name__ == '__main__':
    unittest.main()
