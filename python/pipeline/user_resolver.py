# python/pipeline/user_resolver.py
"""
Resolve user ID untuk record dengan id_user = 0.
Cari user berdasarkan email di database.
Kalau belum ada, auto-create user baru dengan password default "pelanggan123".
"""
import pymysql
import bcrypt
from config import DB_CONFIG

DEFAULT_PASSWORD = "pelanggan123"


def _hash_password(password: str) -> str:
    return bcrypt.hashpw(password.encode('utf-8'), bcrypt.gensalt()).decode('utf-8')


def resolve_users(records: list, id_upload: int) -> list:
    conn = pymysql.connect(**DB_CONFIG)
    cursor = conn.cursor()

    unresolved = [r for r in records if r.get('id_user') == 0]
    if not unresolved:
        conn.close()
        return records

    # Ambil unique email yang perlu di-resolve
    email_set = set()
    for r in unresolved:
        email = r.get('email', '').strip().lower()
        if email:
            email_set.add(email)

    if not email_set:
        # Tidak ada email — fallback ke user ID 1
        for r in unresolved:
            r['id_user'] = 1
        conn.close()
        return records

    # Cari user yang sudah ada berdasarkan email
    placeholders = ','.join(['%s'] * len(email_set))
    cursor.execute(
        f"SELECT id_user, email FROM users WHERE email IN ({placeholders})",
        list(email_set)
    )
    existing = {row[1].lower(): row[0] for row in cursor.fetchall()}

    email_created = {}
    for email in email_set:
        if email in existing:
            email_created[email] = existing[email]
            print(f"  [USER] {email} -> id_user={existing[email]} (existing)")
        else:
            # Buat user baru
            nama = email.split('@')[0].replace('.', ' ').title()
            if not nama:
                nama = "Pelanggan Baru"
            hp = ''
            for r in unresolved:
                if r.get('email', '').strip().lower() == email and r.get('no_hp'):
                    hp = r['no_hp']
                    break

            hashed = _hash_password(DEFAULT_PASSWORD)
            cursor.execute(
                "INSERT INTO users (nama, email, no_hp, password, role, status) VALUES (%s, %s, %s, %s, 'pelanggan', 'aktif')",
                (nama, email, hp, hashed)
            )
            new_id = cursor.lastrowid
            conn.commit()
            email_created[email] = new_id
            print(f"  [USER] CREATE {email} -> id_user={new_id} (new, pass={DEFAULT_PASSWORD})")

    # Update records
    for r in unresolved:
        email = r.get('email', '').strip().lower()
        if email and email in email_created:
            r['id_user'] = email_created[email]
        elif not email:
            r['id_user'] = 1

    conn.commit()
    conn.close()
    return records
