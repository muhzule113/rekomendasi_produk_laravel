DB_CONFIG = {
    'host':     'localhost',
    'user':     'root',
    'password': 'root',
    'database': 'db_sinar_manis',
    'charset':  'utf8mb4',
}

CF_CONFIG = {
    'min_score':   0.01,
    'top_k':       20,
    'batch_size':  1000,
}

import os
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
UPLOAD_RAW_DIR  = os.path.join(BASE_DIR, 'storage', 'app', 'uploads', 'raw')
UPLOAD_DONE_DIR = os.path.join(BASE_DIR, 'storage', 'app', 'uploads', 'processed')
UPLOAD_REJ_DIR  = os.path.join(BASE_DIR, 'storage', 'app', 'uploads', 'rejected')

# Kolom wajib di file upload
REQUIRED_COLUMNS = ['tanggal', 'id_product', 'qty', 'harga_satuan']

# Format tanggal yang diterima
DATE_FORMATS = ['%Y-%m-%d', '%d/%m/%Y', '%d-%m-%Y', '%Y/%m/%d']
