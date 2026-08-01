# python/cf/data_loader.py
import pandas as pd
import sys
import os
from sqlalchemy import create_engine
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..'))
from config import DB_CONFIG


VALID_TRANSACTION_SQL = """
    t.status_pesanan = 'Selesai'
    AND t.status_pembayaran = 'Dibayar'
"""


def load_transaction_data() -> pd.DataFrame:
    """
    Muat interaksi user-produk dari transaksi valid (Selesai + Dibayar)
    dan hanya produk berstatus aktif. Nilai qty dijumlahkan lalu dibinarisasi
    di build_item_user_matrix.
    """
    url = (
        f"mysql+pymysql://{DB_CONFIG['user']}:{DB_CONFIG['password']}"
        f"@{DB_CONFIG['host']}/{DB_CONFIG['database']}"
        f"?charset={DB_CONFIG['charset']}"
    )
    engine = create_engine(url)
    query = f"""
        SELECT t.id_user, ti.id_product, SUM(ti.qty) AS qty
        FROM transaction_items ti
        JOIN transactions t ON ti.id_transaction = t.id_transaction
        JOIN products p ON ti.id_product = p.id_product
        WHERE {VALID_TRANSACTION_SQL}
          AND p.status = 'aktif'
        GROUP BY t.id_user, ti.id_product
    """
    df = pd.read_sql(query, engine)
    engine.dispose()
    row_count = len(df)
    user_count = df['id_user'].nunique() if not df.empty else 0
    prod_count = df['id_product'].nunique() if not df.empty else 0
    print(f"[OK] Data dimuat: {row_count} baris "
          f"({user_count} user, {prod_count} produk)")
    return df


def load_interaction_events() -> pd.DataFrame:
    """
    Muat peristiwa pembelian per transaksi (untuk evaluasi time-based).
    Satu baris = (user, product, tanggal, id_transaction).
    """
    url = (
        f"mysql+pymysql://{DB_CONFIG['user']}:{DB_CONFIG['password']}"
        f"@{DB_CONFIG['host']}/{DB_CONFIG['database']}"
        f"?charset={DB_CONFIG['charset']}"
    )
    engine = create_engine(url)
    query = f"""
        SELECT t.id_user, ti.id_product, t.tanggal, t.id_transaction
        FROM transaction_items ti
        JOIN transactions t ON ti.id_transaction = t.id_transaction
        JOIN products p ON ti.id_product = p.id_product
        WHERE {VALID_TRANSACTION_SQL}
          AND p.status = 'aktif'
        GROUP BY t.id_user, ti.id_product, t.tanggal, t.id_transaction
        ORDER BY t.id_user, t.tanggal, t.id_transaction, ti.id_product
    """
    df = pd.read_sql(query, engine)
    engine.dispose()
    return df
