# python/pipeline/ingest.py
import pandas as pd
import os
from config import UPLOAD_RAW_DIR


def read_file(path: str) -> pd.DataFrame:
    """
    Baca file CSV atau Excel menjadi DataFrame.
    Otomatis deteksi tipe berdasarkan ekstensi.
    """
    ext = os.path.splitext(path)[1].lower()

    if ext == '.csv':
        for enc in ['utf-8', 'latin-1', 'cp1252']:
            try:
                df = pd.read_csv(path, encoding=enc, dtype=str)
                print(f"  [OK] Berhasil baca CSV ({enc}): {len(df)} baris")
                return df
            except UnicodeDecodeError:
                continue
        raise ValueError("Tidak bisa membaca file CSV (encoding tidak dikenal)")

    elif ext in ['.xlsx', '.xls']:
        df = pd.read_excel(path, dtype=str)
        print(f"  [OK] Berhasil baca Excel: {len(df)} baris")
        return df

    else:
        raise ValueError(f"Format file tidak didukung: {ext}")


def detect_columns(df: pd.DataFrame) -> dict:
    """
    Deteksi otomatis mapping kolom file -> kolom DB.
    Toleran terhadap nama kolom yang sedikit berbeda.
    """
    col_map = {}
    cols_lower = {c.lower().strip(): c for c in df.columns}

    aliases = {
        'tanggal':           ['tanggal', 'date', 'tgl', 'waktu', 'transaction_date', 'order_date'],
        'id_product':        ['id_product', 'product_id', 'kode_produk', 'id produk', 'produk_id'],
        'nama_product':      ['nama_product', 'nama produk', 'product_name', 'nama barang'],
        'qty':               ['qty', 'jumlah', 'quantity', 'kuantitas', 'banyak'],
        'harga_satuan':      ['harga_satuan', 'harga', 'price', 'harga satuan', 'unit_price'],
        'id_user':           ['id_user', 'user_id', 'id pelanggan', 'customer_id', 'pelanggan_id'],
        'kode_transaksi':    ['kode_transaksi', 'transaction_id', 'trx_id', 'kode transaksi', 'order_id', 'no_transaksi'],
        'metode_pembayaran': ['metode_pembayaran', 'metode', 'payment_method', 'pembayaran'],
        'status_pesanan':    ['status_pesanan', 'status', 'order_status'],
        'status_pembayaran': ['status_pembayaran', 'payment_status', 'status bayar'],
        'email':             ['email', 'e-mail', 'surel'],
        'no_hp':             ['no_hp', 'no hp', 'nomor_hp', 'phone', 'no_telp', 'telepon'],
    }

    for field, alias_list in aliases.items():
        for alias in alias_list:
            if alias in cols_lower:
                col_map[field] = cols_lower[alias]
                break

    return col_map
