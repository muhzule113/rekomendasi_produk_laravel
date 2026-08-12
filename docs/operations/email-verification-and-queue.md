# Verifikasi Email dan Queue

## SMTP generik

Email verifikasi Pelanggan dikirim melalui mailer Laravel. Lingkungan yang mengirim ke inbox nyata perlu mengisi variabel berikut tanpa memasukkan kredensial ke repository:

```env
APP_URL=https://domain-toko.example
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.provider.example
MAIL_PORT=465
MAIL_USERNAME=akun-smtp
MAIL_PASSWORD=<isi-melalui-secret-manager>
MAIL_FROM_ADDRESS=notifikasi@domain-toko.example
MAIL_FROM_NAME="Toko Sinar Manis"
```

Gunakan `MAIL_SCHEME=smtps` untuk TLS implisit (umumnya port 465), atau skema dan port STARTTLS yang didukung provider. `MAIL_PASSWORD` adalah rahasia runtime dan hanya boleh diberikan melalui secret manager atau file `.env` yang tidak dilacak. `APP_URL` harus merupakan URL publik yang benar karena menjadi dasar signed URL verifikasi.

Perubahan konfigurasi setelah deploy:

```bash
php artisan config:clear
php artisan config:cache
```

Di lingkungan lokal, `MAIL_MAILER=log` tetap aman untuk inspeksi tanpa mengirim email. Test Laravel menggunakan array mailer.

## Queue database

Email verifikasi diimplementasikan sebagai queued notification. Production tetap memakai queue database yang sudah tersedia:

```env
QUEUE_CONNECTION=database
CACHE_STORE=database
```

Jalankan worker standalone dari root proyek:

```bash
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

`composer run dev` menjalankan worker lokal dengan tiga percobaan. Docker Compose menjalankan service `queue` dengan konfigurasi worker yang sama. Setelah deploy, jalankan migrasi sebelum worker baru memproses email:

```bash
php artisan migrate --force
```

Migrasi verifikasi email menambahkan `email_verified_at` dan mengisi timestamp untuk Pelanggan lama. Pendaftaran baru tetap memiliki nilai `NULL` sampai signed URL yang sah digunakan.
