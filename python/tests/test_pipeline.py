import os
import sys
import unittest
from unittest.mock import patch

sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..'))

from pipeline.user_resolver import resolve_users
from pipeline.transformer import group_into_transactions
from pipeline.ingest import detect_columns
import pandas as pd


class PipelineTest(unittest.TestCase):
    def test_user_resolver_rejects_unknown_without_default_password(self):
        records = [
            {'id_user': 0, 'email': 'unknown@example.com', 'nomor_baris': 2},
            {'id_user': 0, 'email': '', 'nomor_baris': 3},
        ]

        with patch('pipeline.user_resolver.pymysql.connect') as mock_connect:
            cursor = mock_connect.return_value.cursor.return_value
            cursor.fetchall.return_value = [(5, 'known@example.com')]
            resolved, rejected = resolve_users(records, id_upload=1)

        self.assertEqual(resolved, [])
        self.assertEqual(len(rejected), 2)
        self.assertTrue(all('tidak' in r['keterangan'].lower() or 'wajib' in r['keterangan'].lower()
                            or 'cocok' in r['keterangan'].lower() for r in rejected))
        # Pastikan modul tidak mengekspos password default
        import pipeline.user_resolver as ur
        self.assertFalse(hasattr(ur, 'DEFAULT_PASSWORD'))

    def test_group_by_kode_transaksi(self):
        records = [
            {
                'id_user': 1, 'tanggal': '2025-01-01 10:00:00', 'kode_transaksi': 'TRX-A',
                'id_product': 1, 'harga_satuan': 1000, 'qty': 1, 'subtotal': 1000,
                'nomor_baris': 2, 'metode_pembayaran': 'Tunai', 'status_pesanan': 'Selesai',
                'status_pembayaran': 'Dibayar',
            },
            {
                'id_user': 1, 'tanggal': '2025-01-01 10:00:00', 'kode_transaksi': 'TRX-A',
                'id_product': 2, 'harga_satuan': 2000, 'qty': 1, 'subtotal': 2000,
                'nomor_baris': 3, 'metode_pembayaran': 'Tunai', 'status_pesanan': 'Selesai',
                'status_pembayaran': 'Dibayar',
            },
            {
                'id_user': 1, 'tanggal': '2025-01-01 10:00:00', 'kode_transaksi': 'TRX-B',
                'id_product': 3, 'harga_satuan': 3000, 'qty': 1, 'subtotal': 3000,
                'nomor_baris': 4, 'metode_pembayaran': 'Tunai', 'status_pesanan': 'Selesai',
                'status_pembayaran': 'Dibayar',
            },
        ]

        with patch('pipeline.transformer.get_next_transaction_counter', return_value=1), \
             patch('pipeline.transformer.get_product_names', return_value={1: 'P1', 2: 'P2', 3: 'P3'}), \
             patch('pipeline.transformer.get_user_profiles', return_value={1: {'nama': 'U', 'no_hp': '08', 'alamat': 'A'}}):
            transactions, warnings = group_into_transactions(records, sumber_data='upload_csv')

        self.assertEqual(len(transactions), 2)
        self.assertEqual(transactions[0]['header']['kode_transaksi'], 'TRX-A')
        self.assertEqual(len(transactions[0]['items']), 2)
        self.assertEqual(transactions[0]['items'][0]['nama_snapshot'], 'P1')
        self.assertEqual(transactions[0]['header']['sumber_data'], 'upload_csv')

    def test_detect_kode_transaksi_column(self):
        df = pd.DataFrame(columns=[
            'kode_transaksi', 'tanggal', 'id_user', 'email', 'id_product',
            'qty', 'harga_satuan', 'metode_pembayaran', 'status_pembayaran', 'status_pesanan',
        ])
        col_map = detect_columns(df)
        self.assertEqual(col_map['kode_transaksi'], 'kode_transaksi')
        self.assertEqual(col_map['status_pembayaran'], 'status_pembayaran')


if __name__ == '__main__':
    unittest.main()
