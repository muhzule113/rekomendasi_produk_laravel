# python/pipeline/user_resolver.py
"""
Resolve id_user untuk baris upload.
Hanya mencocokkan akun pelanggan aktif yang sudah ada (id_user atau email).
Tidak membuat akun baru dan tidak memakai fallback id_user=1.
"""
import re
import pymysql
from config import DB_CONFIG

EMAIL_RE = re.compile(r'^[^@\s]+@[^@\s]+\.[^@\s]+$')


def resolve_users(records: list, id_upload: int) -> tuple[list, list]:
    """
    Returns
    -------
    resolved : list record dengan id_user valid
    rejected : list dict invalid (nomor_baris, keterangan, data_mentah)
    """
    if not records:
        return [], []

    conn = pymysql.connect(**DB_CONFIG)
    cursor = conn.cursor()

    cursor.execute(
        "SELECT id_user, email FROM users WHERE status = 'aktif' AND role = 'pelanggan'"
    )
    rows = cursor.fetchall()
    conn.close()

    by_id = {int(r[0]): (r[1] or '').lower() for r in rows}
    by_email = {email: uid for uid, email in by_id.items() if email}

    resolved = []
    rejected = []

    for r in records:
        uid = int(r.get('id_user') or 0)
        email = (r.get('email') or '').strip().lower()

        if uid > 0 and uid in by_id:
            r['id_user'] = uid
            resolved.append(r)
            continue

        if email:
            if not EMAIL_RE.match(email):
                rejected.append({
                    'nomor_baris': r.get('nomor_baris'),
                    'status_baris': 'invalid',
                    'data_mentah': str(r),
                    'keterangan': f"format email tidak valid: '{email}'",
                })
                continue
            if email in by_email:
                r['id_user'] = by_email[email]
                resolved.append(r)
                continue
            rejected.append({
                'nomor_baris': r.get('nomor_baris'),
                'status_baris': 'invalid',
                'data_mentah': str(r),
                'keterangan': f"email '{email}' tidak cocok dengan akun pelanggan aktif",
            })
            continue

        rejected.append({
            'nomor_baris': r.get('nomor_baris'),
            'status_baris': 'invalid',
            'data_mentah': str(r),
            'keterangan': 'pelanggan tidak dapat dicocokkan: butuh id_user aktif atau email akun yang sudah ada',
        })

    print(f"  [USER] resolved={len(resolved)} rejected={len(rejected)}")
    return resolved, rejected
