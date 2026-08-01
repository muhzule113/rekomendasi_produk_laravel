"""Parity skor cosine PHP-Python untuk fixture wajib (toleransi 1e-6)."""
import json
import os
import sys
import unittest

sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..'))

from cf.similarity import cosine_from_binary_vectors, build_item_user_matrix, compute_cosine_similarity, extract_unique_pairs
import pandas as pd


EXPECTED = {
    'ab': 0.5,
    'ac': 0.707107,
    'bc': 0.0,
}


class ParityFixtureTest(unittest.TestCase):
    def test_vector_parity_with_php_expected(self):
        a = [1, 1, 1, 1]
        b = [1, 0, 0, 0]
        c = [0, 1, 1, 0]
        self.assertAlmostEqual(cosine_from_binary_vectors(a, b), EXPECTED['ab'], delta=1e-6)
        self.assertAlmostEqual(cosine_from_binary_vectors(a, c), EXPECTED['ac'], delta=1e-6)
        self.assertAlmostEqual(cosine_from_binary_vectors(b, c), EXPECTED['bc'], delta=1e-6)

    def test_pair_extraction_matches_php_storage_rules(self):
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
        pairs = {(min(a, b), max(a, b)): score for a, b, score, _ in extract_unique_pairs(sim_df, co_df)}
        self.assertAlmostEqual(pairs[(10, 20)], 0.5, delta=1e-6)
        self.assertAlmostEqual(pairs[(10, 30)], 0.707107, delta=1e-6)
        self.assertNotIn((20, 30), pairs)

        # Dump untuk referensi parity eksternal
        out = {
            'pairs': {f'{a}-{b}': round(s, 6) for (a, b), s in pairs.items()},
            'tolerance': 1e-6,
        }
        path = os.path.join(os.path.dirname(__file__), 'expected_parity.json')
        with open(path, 'w', encoding='utf-8') as f:
            json.dump(out, f, indent=2)


if __name__ == '__main__':
    unittest.main()
