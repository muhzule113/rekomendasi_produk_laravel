# python/cf/data_loader.py
import pandas as pd
import sys
import os
from sqlalchemy import create_engine
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..'))
from config import DB_CONFIG


def load_transaction_data() -> pd.DataFrame:
    """
    Muat semua transaksi SELESAI dari MySQL.
    Mencakup transaksi langsung DAN dari hasil upload CSV.
    """
    url = f"mysql+pymysql://{DB_CONFIG['user']}:{DB_CONFIG['password']}@{DB_CONFIG['host']}/{DB_CONFIG['database']}?charset={DB_CONFIG['charset']}"
    engine = create_engine(url)
    query = """
        SELECT t.id_user, ti.id_product, SUM(ti.qty) AS qty
        FROM transaction_items ti
        JOIN transactions t ON ti.id_transaction = t.id_transaction
        WHERE t.status_pesanan = 'Selesai'
        GROUP BY t.id_user, ti.id_product
    """
    df = pd.read_sql(query, engine)
    engine.dispose()
    row_count = len(df)
    user_count = df['id_user'].nunique()
    prod_count = df['id_product'].nunique()
    print(f"[OK] Data dimuat: {row_count} baris "
          f"({user_count} user, {prod_count} produk)")
    return df
