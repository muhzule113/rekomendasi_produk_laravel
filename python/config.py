import os
from dotenv import load_dotenv

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
load_dotenv(os.path.join(BASE_DIR, '.env'))

DB_CONFIG = {
    'host':     os.getenv('DB_HOST', 'localhost'),
    'user':     os.getenv('DB_USERNAME', 'root'),
    'password': os.getenv('DB_PASSWORD', ''),
    'database': os.getenv('DB_DATABASE', 'db_sinar_manis'),
    'charset':  'utf8mb4',
}

CF_CONFIG = {
    'min_score': 0.0,  # cosine asli; pasangan nol tidak disimpan
    'min_co_occurrence': int(os.getenv('CF_MIN_CO_OCCURRENCE', '2')),
    'batch_size': 1000,
}

UPLOAD_RAW_DIR = os.path.join(BASE_DIR, 'storage', 'app', 'uploads', 'raw')
UPLOAD_DONE_DIR = os.path.join(BASE_DIR, 'storage', 'app', 'uploads', 'processed')
UPLOAD_REJ_DIR = os.path.join(BASE_DIR, 'storage', 'app', 'uploads', 'rejected')

# Kolom wajib di file upload
REQUIRED_COLUMNS = ['tanggal', 'id_product', 'qty', 'harga_satuan']

# Format tanggal yang diterima
DATE_FORMATS = ['%Y-%m-%d', '%d/%m/%Y', '%d-%m-%Y', '%Y/%m/%d', '%Y-%m-%d %H:%M:%S']
