# Toko Sinar Manis — Sistem Rekomendasi Produk

Aplikasi toko online kebutuhan sehari-hari dengan modul **rekomendasi produk berbasis Item-Based Collaborative Filtering**. Sistem mempelajari pola pembelian pelanggan dari data transaksi, menghitung kemiripan antarproduk (cosine similarity), lalu menyarankan produk yang relevan.

> Studi kasus: *Pengembangan Sistem Rekomendasi Produk Berbasis Analisis Big Data terhadap Transaksi Pelanggan — Toko Sinar Manis.*

---

## Fitur Utama

### Pelanggan
- Katalog produk **dengan gambar**, detail produk, dan keranjang belanja (session)
- Checkout dengan metode **Bayar di Toko** atau **Midtrans Snap** (GoPay, VA, kartu, dll.)
- Riwayat transaksi + verifikasi status pembayaran Midtrans
- Halaman rekomendasi personal (`/rekomendasi`)
- Produk serupa di halaman detail produk
- Gambar produk tampil di katalog, home, detail, rekomendasi, keranjang, dan riwayat

### Admin
- Dashboard, kelola produk (**upload gambar** per produk), pelanggan, transaksi, ulasan, dan laporan
- Import produk CSV dengan kolom opsional `foto` (path relatif di `storage/app/public`)
- Analisis CF + tombol **Hitung Ulang** kemiripan produk
- Upload data transaksi (CSV/Excel) lewat pipeline Python (ETL + CF)
- Riwayat upload dan log perhitungan CF

---

## Tech Stack

| Komponen | Teknologi |
|----------|-----------|
| Backend | Laravel 12, PHP 8.2+ |
| Pembayaran | Midtrans Snap (`midtrans/midtrans-php`) |
| Database | MySQL (disarankan) / SQLite untuk development Laravel |
| Frontend | Blade, Vite, Tailwind CSS |
| Pipeline data | Python 3 (pandas, numpy, scikit-learn, pymysql) |
| Deploy VPS | Docker Compose (PHP + Nginx + MySQL + Python venv) |

---

## Struktur Folder Penting

```
app/
  Http/Controllers/          # Web (customer + admin)
  Http/Controllers/Api/      # Cart, Midtrans, similarity, pipeline
  Services/
    RecommenderService.php   # CF runtime (PHP)
    MidtransPaymentService.php
    UploadService.php        # Trigger pipeline Python
  Models/
database/migrations/         # Skema domain utama
python/
  pipeline/                  # ETL: ingest → clean → load
  cf/                        # CF engine (scikit-learn)
resources/views/customer/    # Tampilan toko
resources/views/admin/       # Panel admin
routes/web.php
routes/api.php
storage/app/public/products/ # Gambar produk (diakses via /storage/products/...)
storage/app/uploads/         # File upload (raw / processed / logs)
public/uploads/templates/    # Template CSV import produk
docker/                      # Nginx, supervisord, entrypoint (deploy Docker)
```

---

## Setup di Device Baru

> **Mulai dari sini** jika Anda clone repo ini di laptop/PC/VPS baru. Pilih **satu** jalur di bawah.

### Jalur A — Development lokal (Laragon / XAMPP / manual)

**Prasyarat:** PHP 8.2+, Composer, Node.js, MySQL (wajib untuk pipeline Python), Python 3 + pip (opsional, untuk upload transaksi)

```bash
# 1. Clone & masuk folder proyek
git clone https://github.com/muhzule113/rekomendasi_produk_laravel.git
cd rekomendasi_produk_laravel

# 2. Dependency PHP
composer install

# 3. Environment
cp .env.example .env
php artisan key:generate

# 4. Database — edit .env (contoh MySQL)
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=db_sinar_manis
# DB_USERNAME=root
# DB_PASSWORD=

# 5. Migrasi & symlink gambar produk
php artisan migrate
php artisan storage:link

# 6. (Opsional) Frontend Vite — UI memakai public/assets/ statis, npm tidak wajib
# npm install && npm run build

# 7. Jalankan
php artisan serve
# atau serve + queue + vite sekaligus:
composer run dev
```

**Checklist device baru (lokal):**

| # | Langkah | Wajib? |
|---|---------|--------|
| 1 | `composer install` | Ya |
| 2 | Copy `.env` + `php artisan key:generate` | Ya |
| 3 | Set `DB_*` di `.env` | Ya |
| 4 | `php artisan migrate` | Ya |
| 5 | `php artisan storage:link` | Ya (gambar produk) |
| 6 | `npm install && npm run build` | Tidak (UI pakai `public/assets/`; npm hanya jika develop Vite) |
| 7 | `pip install -r python/requirements.txt` | Hanya jika pakai upload transaksi |
| 8 | Set `PYTHON_BIN` & `PIPELINE_SCRIPT` di `.env` | Hanya jika pakai upload transaksi |

Jika memakai Laragon / virtual host, arahkan document root ke folder `public/`.

---

### Jalur B — Production VPS (Docker)

**Prasyarat:** Git, Docker & Docker Compose di VPS. Python **sudah otomatis** terpasang di image Docker (tidak perlu `pip install` manual).

```bash
# 1. Clone di VPS
git clone https://github.com/muhzule113/rekomendasi_produk_laravel.git
cd rekomendasi_produk_laravel

# 2. Environment Docker
cp .env.docker.example .env

# 3. Build image dulu
docker compose build

# 4. Generate APP_KEY ke .env
docker compose run --rm app php artisan key:generate --show
# Salin output, tempel sebagai APP_KEY=... di .env

# 5. Edit .env — minimal:
# APP_KEY=base64:...
# APP_URL=http://IP_VPS_ANDA
# DB_PASSWORD=... (ganti password default)
# DB_ROOT_PASSWORD=...

# 6. Jalankan
docker compose up -d

# 7. Cek log
docker compose logs -f app
```

**Checklist device baru (Docker/VPS):**

| # | Langkah | Wajib? |
|---|---------|--------|
| 1 | `cp .env.docker.example .env` + isi `APP_KEY`, `APP_URL` | Ya |
| 2 | Ganti `DB_PASSWORD` & `DB_ROOT_PASSWORD` | Ya |
| 3 | `docker compose build && docker compose up -d` | Ya |
| 4 | Pastikan port `APP_PORT` (default 80) terbuka di firewall | Ya |
| 5 | `PYTHON_BIN=/opt/venv/bin/python` sudah di `.env.docker.example` | Otomatis |
| 6 | Pull update: `git pull && docker compose build && docker compose up -d` | Saat deploy ulang |

Container `app` menjalankan migrasi & `storage:link` otomatis lewat `docker/entrypoint.sh`. Service `queue` menjalankan `php artisan queue:work`.

---

## Instalasi & Konfigurasi Lanjutan

### Gambar produk

Gambar disimpan di `storage/app/public/products/` dan diakses via URL `/storage/products/nama-file.webp`.

| Cara | Keterangan |
|------|------------|
| Admin → Produk → Tambah/Edit | Upload file gambar langsung |
| Import CSV produk | Kolom opsional `foto` — path relatif, contoh `products/nama.jpg`. File harus sudah ada di `storage/app/public/` |
| Tanpa gambar | Tampil emoji kategori sebagai placeholder |

Pastikan symlink sudah dibuat:

```bash
php artisan storage:link
```

Template CSV: `public/uploads/templates/template_produk.csv`

### Dependency Python (hanya jalur lokal / non-Docker)

```bash
cd python
pip install -r requirements.txt
```

`python/config.py` membaca kredensial database dari variabel lingkungan (`DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`, `DB_DATABASE`) — sama dengan `.env` Laravel. Pastikan variabel tersebut tersedia saat pipeline dijalankan (Docker Compose sudah meneruskannya otomatis).

### Variabel lingkungan

Contoh tambahan di `.env`:

```env
# Midtrans (sandbox)
MIDTRANS_SERVER_KEY=SB-Mid-server-xxxx
MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxx
MIDTRANS_IS_PRODUCTION=false

# Pipeline upload transaksi (lokal)
PYTHON_BIN=python
PIPELINE_SCRIPT=python/pipeline/pipeline_runner.py

# Pipeline upload transaksi (Docker — sudah ada di .env.docker.example)
# PYTHON_BIN=/opt/venv/bin/python
# PIPELINE_SCRIPT=python/pipeline/pipeline_runner.py
```

**Notification URL Midtrans (production/ngrok):**  
`https://domain-anda/api/midtrans/notification`

---

## Workflow Aplikasi

### 1. Alur belanja pelanggan

```
Browse produk → Tambah keranjang → Checkout
       ↓
  Bayar di Toko          Midtrans Snap
       ↓                      ↓
  Menunggu admin         Bayar di popup Snap
  konfirmasi             → verify-payment / webhook
       ↓                      ↓
  Stok berkurang saat    Stok berkurang saat status
  checkout (non-Midtrans) settlement / capture
       ↓
  Riwayat transaksi → (opsional) rekomendasi diperbarui
```

### 2. Alur admin data & rekomendasi

```
Upload CSV/Excel transaksi (Admin → Upload)
       ↓
Python ETL (pipeline_runner)
       ↓
Load ke MySQL + jalankan CF engine (Python)
       ↓
Tabel product_similarity terisi
       ↓
Halaman /rekomendasi memakai skor kemiripan

ATAU

Admin → Analisis → Hitung Ulang
       ↓
RecommenderService (PHP): matrix → cosine → saveSimilarity
```

### 3. Role

| Role | Akses |
|------|--------|
| `pelanggan` | Toko, keranjang, checkout, riwayat, rekomendasi |
| `admin` | `/admin/*` (katalog, transaksi, analisis, upload) |

Registrasi publik hanya membuat akun **pelanggan**.

---

## Mekanisme Sistem Rekomendasi

Inti logika ada di `App\Services\RecommenderService`.

### Ringkasan arsitektur

```mermaid
flowchart TD
    A[Transaksi Selesai + Dibayar] --> B[User-Item Matrix]
    B --> C[Cosine Similarity antar produk]
    C --> D[(product_similarity)]
    E[Riwayat beli user] --> F[Ambil tetangga produk]
    D --> F
    F --> G[Hybrid scoring + rating]
    G --> H[Daftar rekomendasi]
    H --> I{Hasil kosong?}
    I -->|Ya| J[Fallback: Beli Lagi / Best Seller / Tersedia]
    I -->|Tidak| K[Tampilkan ke pelanggan]
```

### 1. Membangun User–Item Matrix

Method: `buildUserItemMatrix()`

- Sumber: `transactions` + `transaction_items`
- Filter: `status_pesanan = Selesai` **dan** `status_pembayaran = Dibayar`
- Nilai: **binary** (1 = pernah membeli produk, 0 = belum)
- Kolom: hanya produk berstatus `aktif`

Hasilnya matriks `user × product`.

### 2. Cosine Similarity & Co-occurrence

Method: `calculateCosineSimilarity()`

Setiap produk digambarkan sebagai vektor pembelian user. Untuk pasangan produk \(A\) dan \(B\):

\[
\text{similarity}(A,B) = \frac{A \cdot B}{\|A\| \,\|B\|}
\]

- **Co-occurrence**: jumlah user yang membeli keduanya
- Hanya pasangan dengan `similarity > 0` yang disimpan

### 3. Menyimpan hasil ke database

Method: `saveSimilarity()`

- Menghapus isi lama tabel `product_similarity`
- Menyimpan pasangan **dua arah** (`product_a` ↔ `product_b`) beserta `score` dan `co_occurrence`

### 4. Memberi rekomendasi ke pelanggan

Method: `recommendForCustomer()` → dipanggil dari `getFullRecommendation()`

1. Ambil produk yang pernah dibeli user
2. Cari produk mirip lewat `product_similarity`
3. Kecualikan: produk sudah dibeli + produk rating rendah (rata-rata ≤ 2.0 dengan ≥ 2 ulasan)
4. Hanya produk `aktif` dan `stok > 0`
5. Alasan contoh: *"Sering dibeli bersama Indomie Goreng"*

### 5. Hybrid scoring (similarity + rating)

Method: `applyHybridScoring()`

```
hybrid = (similarity × 0.7) + (avg_rating / 5 × 0.3)
+ 0.1  jika avg_rating ≥ 4.0 dan jumlah ulasan ≥ 2
```

Hasil diurutkan berdasarkan `hybrid_score` tertinggi.

### 6. Cold start & fallback

Method: `getFullRecommendation()` — cascade:

| Kondisi | Metode | Sumber |
|---------|--------|--------|
| Belum ada riwayat beli | Rating Tertinggi (Cold Start) | Best seller + rating |
| Ada riwayat + CF berhasil | Item-Based CF + Hybrid Rating | `product_similarity` |
| CF kosong | Beli Lagi | Produk pernah dibeli (stok tersedia) |
| Masih kosong | Best Seller + Rating | Skor: rating 60% + terjual 40% |
| Terakhir | Produk Tersedia | Produk aktif berstok |

Skor best seller:

```
(avg_rating / 5 × 0.6) + (terjual / max_terjual × 0.4)
```

### 7. Kapan similarity dihitung ulang?

| Pemicu | Cara |
|--------|------|
| Tombol **Hitung Ulang** di Admin → Analisis | `POST /admin/similarity` → `RecommenderService` (PHP) |
| Upload file transaksi | Pipeline Python → `cf_engine.py` (scikit-learn) |

Setelah pembayaran Midtrans sukses (atau admin menandai transaksi selesai/dibayar), sistem menandai `recommendation_dirty` di `system_settings`. Flag ini menandakan data CF sudah usang; perhitungan ulang tetap dijalankan lewat **Hitung Ulang** atau **upload pipeline**.

### 8. PHP vs Python CF

| Aspek | PHP (`RecommenderService`) | Python (`cf_engine`) |
|-------|---------------------------|----------------------|
| Filter transaksi | Selesai + Dibayar | Selesai |
| Representasi | Binary (0/1) | Quantity (+ scaling) |
| Library | Implementasi manual | scikit-learn cosine |
| Digunakan saat | Hitung ulang dari admin | Setelah upload data |

Keduanya menulis ke tabel `product_similarity` yang sama, yang dibaca halaman rekomendasi.

### 9. Endpoint terkait rekomendasi

| Endpoint | Fungsi |
|----------|--------|
| `GET /rekomendasi` | Halaman rekomendasi personal + produk populer |
| `GET /produk/{id}` | Detail + produk serupa |
| `POST /admin/similarity` | Hitung ulang cosine similarity (admin) |
| `GET /api/rekomendasi` | API: `similar` / `personal` / `popular` |

---

## Alur Pembayaran Midtrans (ringkas)

1. Checkout memilih **Online Payment (Midtrans)** → transaksi dibuat status `Pending`
2. Snap token dibuat → popup pembayaran Midtrans
3. Setelah bayar, status disinkronkan lewat:
   - Webhook `POST /api/midtrans/notification`, dan/atau
   - `POST /verify-payment` (otomatis dari halaman checkout/riwayat)
4. Status `settlement` / `capture` sukses → `Dibayar` + `Diproses`, stok berkurang

Di lingkungan lokal, pastikan webhook dapat dijangkau Midtrans (ngrok/domain publik), atau andalkan verifikasi client (`verify-payment`).

---

## Akun & data awal

Seeder bawaan Laravel belum disesuaikan dengan skema `users` aplikasi (`nama`, `role`, dll.). Buat akun admin/pelanggan secara manual di database atau lewat form register (pelanggan).

Contoh role di tabel `users`:
- `role = admin`
- `role = pelanggan`

---

## Lisensi

Proyek ini dibangun di atas [Laravel](https://laravel.com) (MIT). Kode aplikasi Toko Sinar Manis mengikuti kebutuhan studi kasus proyek.
