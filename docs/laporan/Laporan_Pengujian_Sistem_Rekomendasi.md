# LAPORAN PENGUJIAN DAN ANALISIS DATA

## Sistem Rekomendasi Produk Berbasis Item-Based Collaborative Filtering  
### Studi Kasus: Toko Sinar Manis

---

| | |
|---|---|
| **Judul Studi** | Pengembangan Sistem Rekomendasi Produk Berbasis Analisis Data terhadap Transaksi Pelanggan — Toko Sinar Manis |
| **Platform** | Laravel 12 (PHP) + Pipeline Python (pandas/numpy) + MySQL |
| **Metode Inti** | Item-Based Collaborative Filtering (IBCF) dengan metrik Cosine Similarity pada matriks biner (pure CF; rating hanya fallback) |
| **Dokumen** | Laporan Pengujian & Analisis Data |
| **Versi** | 1.0 |

---

## Daftar Isi

1. [Pendahuluan](#1-pendahuluan)
2. [Teknik Analisis Data](#2-teknik-analisis-data)
3. [Teknik Pengumpulan Data](#3-teknik-pengumpulan-data)
4. [Preprocessing Data](#4-preprocessing-data)
5. [Analisis (Algoritma / Metode)](#5-analisis-algoritma--metode)
6. [Visualisasi Data](#6-visualisasi-data)
7. [Hasil Analisis dan Pembahasan](#7-hasil-analisis-dan-pembahasan)
8. [Skenario Pengujian Sistem](#8-skenario-pengujian-sistem)
9. [Kesimpulan](#9-kesimpulan)
10. [Lampiran](#10-lampiran)

---

## 1. Pendahuluan

Laporan ini menjelaskan teknik analisis data yang diterapkan pada sistem rekomendasi produk Toko Sinar Manis, beserta rangkaian tahapan: pengumpulan data, preprocessing, penerapan algoritma, visualisasi, serta hasil analisis.

Sistem mempelajari pola pembelian pelanggan dari data transaksi, menghitung kemiripan antarproduk, lalu menyajikan rekomendasi personal. Implementasi utama berada pada layanan `RecommenderService` (PHP) dan mesin Collaborative Filtering Python (`python/cf/`).

Tujuan pengujian dan analisis dalam dokumen ini:

1. Menjelaskan teknik analisis data yang digunakan secara konseptual dan teknis.
2. Mendokumentasikan sumber data serta cara pengumpulannya.
3. Menjelaskan tahapan pembersihan dan transformasi data (ETL).
4. Menjabarkan algoritma/metode rekomendasi beserta rumus yang diimplementasikan.
5. Menunjukkan bentuk visualisasi hasil analisis di sisi admin dan pelanggan.
6. Menyusun kerangka hasil analisis dan skenario pengujian yang dapat diisi dengan angka aktual dari environment pengguna.

---

## 2. Teknik Analisis Data

### 2.1 Jenis dan Pendekatan Analisis

Teknik analisis yang digunakan adalah **analisis pola transaksi pelanggan** (*transactional / behavioral analytics*) dengan pendekatan **Collaborative Filtering**.

| Aspek | Keterangan |
|-------|------------|
| Jenis analisis | Analisis pola pembelian bersama antarproduk |
| Pendekatan | Collaborative Filtering |
| Varian | **Item-Based Collaborative Filtering (IBCF)** |
| Representasi data | Matriks user–item biner |
| Metrik kemiripan | **Cosine Similarity** (matriks interaksi biner) |
| Penyempurnaan ranking | Hybrid scoring (similarity + rating ulasan) |
| Evaluasi performa | Precision@K, Recall@K, F1-Score@K (K = 5) |

Sistem **tidak** menggunakan Apriori/association rules, content-based filtering berbasis fitur produk, maupun matrix factorization sebagai engine utama. Filter kategori pada halaman detail produk bersifat tampilan tambahan, bukan bagian perhitungan matriks CF.

### 2.2 Konsep Item-Based Collaborative Filtering

Pada IBCF, kemiripan dihitung antar **item (produk)**, bukan antar user. Asumsi utamanya: jika dua produk sering dibeli oleh pelanggan yang sama, maka keduanya dianggap mirip. Ketika seorang pelanggan membeli produk A, sistem merekomendasikan produk B yang memiliki skor kemiripan tinggi terhadap A.

Alur konseptual:

```
Transaksi selesai → Matriks User×Item → Skor kemiripan antar produk
       → Simpan product_similarity → Ranking rekomendasi per user
       → (opsional) Evaluasi Precision/Recall/F1
```

### 2.3 Metrik Kemiripan: Cosine Similarity (biner)

Setiap produk digambarkan sebagai himpunan user yang pernah membelinya (transaksi `Selesai` + `Dibayar`). Untuk pasangan produk A dan B pada vektor biner:

\[
cosine(A,B) = \frac{|A \cap B|}{\sqrt{|A| \times |B|}}
\]

di mana:

- \(|A \cap B|\) = jumlah user yang membeli produk A dan B (*co-occurrence*),
- \(|A|\), \(|B|\) = jumlah user unik yang membeli masing-masing produk.

Skor cosine sudah berada pada rentang \([0,1]\); **tidak** dinormalisasi terhadap skor maksimum katalog. Pasangan dengan co-occurrence di bawah `CF_MIN_CO_OCCURRENCE` (default 2) tidak disimpan. Rating tidak memengaruhi skor IBCF.

### 2.4 Prediksi Personal (tanpa hybrid rating)

\[
prediction\_score(u,c)=\frac{\sum_{i \in I_u} similarity(i,c)}{|I_u|}
\]

dengan \(I_u\) = himpunan produk valid yang pernah dibeli user. Rating/popularitas hanya dipakai pada fallback cold start yang diberi label eksplisit sebagai bukan CF.

### 2.5 Metrik Evaluasi

Pipeline Python mengevaluasi akurasi rekomendasi menggunakan holdout berbasis waktu tanpa data leakage:

1. Untuk setiap user dengan minimal 2 produk berbeda dalam riwayat valid,
2. Interaksi diurutkan waktu; produk terbaru dijadikan *test item*, sisanya *training*,
3. Similarity dibangun ulang hanya dari data latih (bukan tabel produksi),
4. Sistem mengambil Top-K dengan agregasi yang sama seperti aplikasi,
5. Dihitung:

\[
Precision@K = \frac{|rekomendasi \cap test|}{K},\quad
Recall@K = \frac{|rekomendasi \cap test|}{1}
\]

\[
F1@K = \frac{2 \times Precision \times Recall}{Precision + Recall}
\]

Nilai yang dilaporkan adalah rata-rata antar user yang dievaluasi (default \(K = 5\)).

### 2.6 Ruang Lingkup Sinyal Analisis

| Sinyal | Digunakan di matriks CF | Digunakan di ranking |
|--------|-------------------------|----------------------|
| Riwayat pembelian (transaksi selesai) | Ya (biner) | Ya |
| Rating/ulasan produk | Tidak | Tidak (hanya tampilan / fallback cold start) |
| Jumlah terjual (`terjual`) | Tidak | Ya (fallback best seller) |
| View produk | Tidak | Tidak |
| Isi keranjang | Tidak | Tidak |
| Kategori produk | Tidak | Hanya tampilan terkait |

---

## 3. Teknik Pengumpulan Data

### 3.1 Sumber Data Primer: Transaksi Pembelian

Data interaksi utama dikumpulkan dari aktivitas belanja nyata maupun impor massal.

#### a. Transaksi langsung (online)

Pelanggan melakukan alur:

`Browse produk → Keranjang → Checkout → Pembayaran (Bayar di Toko / Midtrans)`

Data tersimpan pada:

| Tabel | Peran |
|-------|--------|
| `transactions` | Header pesanan: user, tanggal, status pesanan, status pembayaran, total |
| `transaction_items` | Detail item: `id_product`, qty, harga satuan |

Sinyal CF dibentuk dari pasangan `(id_user, id_product)` pada transaksi yang memenuhi filter status.

#### b. Upload data historis (admin)

Admin mengunggah file CSV/Excel transaksi melalui menu Upload. File dicatat di `data_uploads`, diproses pipeline Python, lalu dimuat ke tabel transaksi yang sama. Hash file (SHA-256) mencegah unggah ulang file identik.

### 3.2 Sumber Data Sekunder: Ulasan Produk

| Tabel | Peran |
|-------|--------|
| `product_reviews` | Rating 1–5 dan komentar (satu ulasan per user per produk) |

Ulasan dipakai untuk:

- tampilan rating pada kartu/detail produk,
- ranking fallback cold-start (best seller + rating) yang berlabel bukan CF.
  Rating **tidak** memengaruhi skor maupun filter hasil IBCF.

### 3.3 Data Pendukung (Tidak Masuk Matriks CF)

| Tabel / entitas | Keterangan |
|-----------------|------------|
| `products`, `categories` | Katalog; filter produk aktif & stok |
| `product_views` | Skema tersedia, belum dipakai engine rekomendasi |
| `cart` | Keranjang sesi; bukan input CF |
| `users` | Identitas pelanggan |

### 3.4 Data Hasil Perhitungan dan Audit

| Tabel | Peran |
|-------|--------|
| `product_similarity` | Skor kemiripan antarproduk (hasil analisis) |
| `recommendation_logs` | Jejak rekomendasi yang ditampilkan ke user |
| `cf_run_logs` | Log eksekusi batch CF |
| `upload_logs` | Log baris ETL upload |
| `system_settings` | Flag `recommendation_dirty` setelah transaksi baru |

### 3.5 Ringkasan Teknik Pengumpulan

| Teknik | Instrumen | Output |
|--------|-----------|--------|
| Observasi transaksi digital | Checkout aplikasi + Midtrans | Record transaksi & item |
| Impor data sekunder | Template CSV/Excel admin | Dataset historis terseragamkan |
| Umpan balik eksplisit | Form ulasan produk | Rating & komentar |
| Logging sistem | Service rekomendasi & pipeline | Log evaluasi dan audit |

---

## 4. Preprocessing Data

Preprocessing dijalankan terutama pada pipeline Python setelah upload:

`ingest → clean → resolve user → transform → load → (CF + evaluasi)`

### 4.1 Ingest (`python/pipeline/ingest.py`)

- Membaca file CSV atau Excel.
- Mendeteksi alias nama kolom secara otomatis.
- Menyiapkan pemetaan kolom ke skema standar sistem.

### 4.2 Cleaning (`python/pipeline/cleaner.py`)

Kegiatan pembersihan meliputi:

1. Menghapus baris kosong.
2. Validasi dan parsing tanggal (multi-format).
3. Validasi `id_product` terhadap produk aktif di database.
4. Validasi/resolusi user (`id_user` atau email).
5. Pembersihan angka qty dan harga (menghapus simbol mata uang, normalisasi desimal).
6. Mapping nilai metode pembayaran dan status pesanan ke enum sistem.
7. Deteksi duplikasi berdasarkan kunci `(user/email, tanggal, id_product, qty)`.
8. Pemisahan record valid vs invalid beserta alasan error per baris.

**Kolom wajib:** `tanggal`, `id_product`, `qty`, `harga_satuan`  
**Kolom opsional:** `email`, `no_hp`, `id_user`, `metode_pembayaran`, `status_pesanan`

### 4.3 Resolusi User (`user_resolver.py`)

- Mencocokkan email dengan user yang sudah ada.
- Membuat user baru bila diperlukan (untuk data historis yang belum terdaftar).

### 4.4 Transformasi (`transformer.py`)

Baris detail digabung menjadi transaksi berdasarkan `(id_user, tanggal)`, kemudian dipecah kembali menjadi header transaksi + item line.

### 4.5 Load (`loader.py`)

Data bersih dimasukkan ke MySQL (`transactions`, `transaction_items`) dengan penanda sumber upload.

### 4.6 Preprocessing Khusus untuk Collaborative Filtering

Sebelum membangun matriks (jalur PHP admin “Hitung Ulang”):

1. Filter `status_pesanan = 'Selesai'`.
2. Filter `status_pembayaran = 'Dibayar'` (pada implementasi PHP).
3. Agregasi ke bentuk biner: pernah beli = 1, belum = 0 (qty tidak dipakai sebagai bobot).
4. Hanya kolom produk berstatus `aktif`.
5. Pasangan tanpa *intersection* (tidak ada user bersama) dilewati.

> Catatan: jalur Python CF memfilter `status_pesanan = 'Selesai'` dan menyimpan tetangga Top-K per produk (`top_k = 20`, `min_score = 0.01`), sedangkan jalur PHP dapat menyimpan pasangan dua arah secara lebih lengkap sesuai hasil perhitungan.

---

## 5. Analisis (Algoritma / Metode)

### 5.1 Arsitektur Perhitungan

Terdapat dua jalur perhitungan kemiripan yang bermuara pada tabel `product_similarity`:

| Jalur | Pemicu | Komponen |
|-------|--------|----------|
| PHP runtime | Tombol **Hitung Ulang** di Admin → Analisis | `SimilarityController` → `RecommenderService` |
| Python batch | Setelah upload CSV/Excel sukses | `pipeline_runner` → `batch_runner` → `cf_engine` |

Serving rekomendasi ke pelanggan selalu melalui `RecommenderService` (PHP), membaca skor yang sudah tersimpan.

### 5.2 Tahap 1 — Membangun User–Item Matrix

Method: `buildUserItemMatrix()`

1. Join `transactions` dengan `transaction_items`.
2. Ambil pasangan user–produk unik pada transaksi valid.
3. Bentuk matriks biner `user × product`.

Contoh ilustratif:

| User | Produk A | Produk B | Produk C |
|------|----------|----------|----------|
| U1 | 1 | 1 | 0 |
| U2 | 1 | 0 | 1 |
| U3 | 0 | 1 | 1 |

### 5.3 Tahap 2 — Menghitung Cosine Similarity

Method PHP: `calculateCosineSimilarity()`  
Fungsi Python: `compute_cosine_similarity()`

Langkah:

1. Bangun himpunan pembeli per produk (transaksi Selesai + Dibayar).
2. Untuk setiap pasangan produk \((A,B)\):
   - hitung intersection,
   - terapkan ambang `CF_MIN_CO_OCCURRENCE`,
   - hitung cosine biner,
   - simpan juga `co_occurrence`.
3. **Tanpa** normalisasi terhadap skor maksimum.
4. Simpan ke `product_similarity` secara atomik (pasangan dua arah).

### 5.4 Tahap 3 — Generate Rekomendasi Personal

Method: `recommendForCustomer()` / `getFullRecommendation()`

1. Ambil seluruh produk valid yang pernah dibeli user.
2. Jika riwayat kosong → **cold start** (fallback berlabel, bukan CF).
3. Kumpulkan tetangga dari seluruh produk riwayat.
4. Exclude produk yang sudah dibeli; agregasi skor kandidat sebelum limit.
5. Filter produk aktif dan stok > 0 (kelayakan tampil).
6. Urutkan `prediction_score` menurun.
7. Catat ke `recommendation_logs` dengan sumber `ibcf_cosine` / fallback.
8. Susun alasan: *“Direkomendasikan berdasarkan kemiripan pola pembelian dengan {nama produk}.”*

### 5.5 Tahap 4 — Fallback Cascade

Jika hasil CF kosong atau tidak memadai:

1. **Buy Again** — produk yang sering dibeli user sebelumnya.
2. **Best Seller + Rating** — skor gabungan:
   \[
   (0{,}6 \times \frac{avg\_rating}{5}) + (0{,}4 \times \frac{terjual}{\max terjual})
   \]
3. **Produk tersedia** — produk aktif berstok, diurut terbaru.

### 5.6 Rekomendasi Produk Serupa

Pada halaman detail produk, sistem mencari `product_b` dengan `product_a` = produk yang sedang dilihat, diurutkan berdasarkan skor kemiripan dan co-occurrence. Ini memanfaatkan hasil IBCF yang sama.

### 5.7 Evaluasi Batch (Python)

`python/cf/evaluator.py` menghitung Precision@5, Recall@5, dan F1@5 setelah matriks kemiripan tersedia. Hasil evaluasi dapat dipakai sebagai indikator kuantitatif pada subbab hasil analisis.

### 5.8 Ringkasan Komponen Kode

| File | Fungsi |
|------|--------|
| `app/Services/RecommenderService.php` | Matrix biner, cosine, prediksi, fallback, logging |
| `app/Http/Controllers/Api/SimilarityController.php` | Endpoint hitung ulang |
| `app/Http/Controllers/Admin/AnalisisController.php` | Data dashboard analisis + evaluasi |
| `python/cf/similarity.py` | Cosine biner + co-occurrence (NumPy) |
| `python/cf/cf_engine.py` | Persistensi hasil CF (atomik) |
| `python/cf/evaluator.py` | Time-based holdout + Precision/Recall/F1/Hit/Coverage |
| `python/pipeline/*` | ETL upload transaksi (tanpa auto-create user) |

---

## 6. Visualisasi Data

### 6.1 Dashboard Analisis Admin (`/admin/analisis`)

Halaman ini menjadi pusat visualisasi hasil Collaborative Filtering, antara lain:

1. Kartu statistik (jumlah pasangan similarity, coverage, ringkasan run).
2. **Grafik distribusi skor** (Chart.js — bar chart) untuk melihat sebaran nilai kemiripan.
3. Tabel Top produk yang sering muncul di rekomendasi.
4. Riwayat eksekusi CF (`cf_run_logs`).
5. Tabel pasangan produk mirip (product_a, product_b, score, co-occurrence).
6. Log rekomendasi harian.
7. Tombol **Hitung Ulang** untuk menjalankan ulang perhitungan PHP.

### 6.2 Visualisasi Lain di Admin

| Halaman | Visualisasi |
|---------|-------------|
| `/admin/dashboard` | Grafik transaksi/pendapatan bulanan; popularitas kategori |
| `/admin/reviews` | Distribusi rating bintang 1–5 (Chart.js) |
| `/admin/laporan` | Laporan transaksi dengan filter tanggal |
| `/admin/upload` | Status progress pipeline ETL + CF |

### 6.3 Visualisasi / Presentasi di Sisi Pelanggan

| Halaman | Bentuk penyajian hasil analisis |
|---------|----------------------------------|
| `/rekomendasi` | Kartu rekomendasi personal + badge metode + alasan |
| Detail produk | Bagian “Produk Serupa” berbasis similarity |
| Beranda | Penjelasan singkat mekanisme rekomendasi (edukatif) |

### 6.4 API Pendukung

| Endpoint | Fungsi |
|----------|--------|
| `GET /api/rekomendasi?action=personal\|similar\|popular` | Data rekomendasi JSON |
| `POST /admin/similarity` | Trigger perhitungan ulang |
| `GET /admin/pipeline-status` | Status pipeline upload |

Visualisasi bersifat *dashboard analytics* untuk admin dan *recommendation UI* untuk pelanggan; keduanya menggunakan hasil analisis yang sama dari `product_similarity`.

---

## 7. Hasil Analisis dan Pembahasan

> Subbab ini menyediakan kerangka hasil. **Isi angka aktual** setelah menjalankan perhitungan CF pada dataset environment Anda (Admin → Analisis / setelah upload pipeline).

### 7.1 Ringkasan Dataset Uji

| Parameter | Nilai (isi hasil aktual) |
|-----------|---------------------------|
| Jumlah transaksi selesai | … |
| Jumlah user dengan riwayat beli | … |
| Jumlah produk aktif | … |
| Jumlah pasangan di `product_similarity` | … |
| Coverage matriks / produk berkoneksi | … % |
| Tanggal terakhir hitung ulang CF | … |

### 7.2 Hasil Perhitungan Kemiripan

Temuan yang diharapkan untuk dibahas:

1. Distribusi skor (dari grafik admin): apakah mayoritas pasangan lemah, sedang, atau kuat.
2. Contoh pasangan produk dengan skor tertinggi dan interpretasi bisnis (apakah masuk akal dibeli bersama).
3. Peran `co_occurrence` sebagai bukti frekuensi pembelian bersama.

**Contoh tabel hasil (template):**

| Produk A | Produk B | Score | Co-occurrence | Interpretasi singkat |
|----------|----------|-------|---------------|----------------------|
| … | … | … | … | Sering dibeli bersama |
| … | … | … | … | … |

### 7.3 Hasil Evaluasi Kuantitatif

Ambil dari output evaluator / log CF:

| Metrik | Nilai |
|--------|-------|
| Precision@5 | … |
| Recall@5 | … |
| F1-Score@5 | … |
| Jumlah user dievaluasi | … |

**Pembahasan singkat yang disarankan:**

- Precision rendah dapat terjadi jika katalog besar dan Top-5 jarang mengenai tepat satu *holdout item*.
- Recall@5 pada skema holdout 1 item bernilai 0 atau 1 per user; rata-rata mencerminkan proporsi user yang “terkena” rekomendasi.
- F1 menyeimbangkan keduanya sebagai ringkasan tunggal.

### 7.4 Hasil Uji Fungsional Rekomendasi

| Kondisi user | Perilaku sistem yang diamati | Sumber rekomendasi |
|--------------|------------------------------|--------------------|
| Memiliki riwayat transaksi selesai | Muncul rekomendasi personal + alasan | IBCF cosine |
| Belum pernah beli / guest | Muncul produk populer / best seller | Fallback |
| Membuka detail produk | Muncul produk serupa | Similarity item |
| Produk stok habis / nonaktif | Tidak ditampilkan | Filter stok & status |

### 7.5 Pembahasan Kelebihan dan Keterbatasan

**Kelebihan**

1. Memanfaatkan data transaksi riil (implisit), tidak bergantung pada pengisian profil.
2. Hybrid scoring mengurangi risiko merekomendasikan produk mirip tetapi berkualitas rendah.
3. Ada jalur batch (Python) untuk data besar dan jalur manual (PHP) untuk hitung ulang cepat.
4. Tersedia logging dan dashboard analisis untuk audit hasil.

**Keterbatasan**

1. Cold-start user baru belum bisa dipersonalisasi penuh tanpa riwayat.
2. Produk baru yang jarang dibeli sulit mendapat tetangga similarity.
3. Matriks biner mengabaikan intensitas qty/frekuensi sebagai bobot.
4. View produk belum dimanfaatkan sebagai sinyal minat.
5. Perbedaan filter status antara PHP dan Python perlu diperhatikan saat membandingkan hasil.

### 7.6 Implikasi untuk Toko Sinar Manis

Hasil analisis kemiripan dapat dipakai untuk:

- menata *cross-selling* di kasir/online,
- menyusun bundling produk yang sering dibeli bersama,
- memantau produk yang sering direkomendasikan tetapi stoknya menipis,
- mengevaluasi ulang katalog yang jarang muncul di tetangga similarity.

---

## 8. Skenario Pengujian Sistem

### 8.1 Pengujian Black-Box

| ID | Skenario | Langkah | Hasil yang diharapkan |
|----|----------|---------|------------------------|
| Uji-01 | Hitung ulang similarity | Login admin → Analisis → Hitung Ulang | `product_similarity` terisi; statistik terbarui |
| Uji-02 | Upload transaksi | Upload CSV valid → pantau pipeline | Transaksi masuk; CF batch jalan; log sukses |
| Uji-03 | Upload data kotor | Upload CSV dengan tanggal/produk invalid | Baris invalid ditolak; tercatat di `upload_logs` |
| Uji-04 | Rekomendasi user berriwayat | Login pelanggan yang punya transaksi selesai → `/rekomendasi` | Muncul rekomendasi CF + alasan |
| Uji-05 | Cold start | Login user tanpa transaksi → `/rekomendasi` | Fallback best seller / produk tersedia |
| Uji-06 | Produk serupa | Buka detail produk yang punya tetangga | Section produk serupa tampil |
| Uji-07 | Filter stok | Habiskan stok produk rekomendasi | Produk tidak muncul di rekomendasi |
| Uji-08 | Rating tidak memengaruhi IBCF | Beri ulasan rendah pada kandidat | Ranking IBCF tetap berdasarkan cosine/prediksi |
| Uji-09 | Log rekomendasi | Tampilkan rekomendasi lalu cek admin/analisis | Ada entri `ibcf_cosine` / fallback, bukan `CF_personal` |
| Uji-10 | Evaluasi metrik | Jalankan CF Python + evaluator | Tercatat Precision/Recall/F1/Hit/Coverage |

### 8.2 Pengujian Validasi Rumus (Sampel Manual)

Untuk satu pasangan produk A dan B:

1. Hitung manual jumlah pembeli A, B, dan irisan dari database.
2. Hitung cosine: \(|A\cap B| / \sqrt{|A|\times|B|}\).
3. Bandingkan dengan nilai di `product_similarity` (tanpa normalisasi max; toleransi \(10^{-6}\)).

Template:

| Item | Nilai |
|------|-------|
| \|A\| | … |
| \|B\| | … |
| \|A ∩ B\| | … |
| Cosine | … |
| Score tersimpan | … |
| Sesuai? | Ya / Tidak |

### 8.3 Kriteria Keberhasilan Pengujian

Pengujian dianggap berhasil jika:

1. Pipeline ETL menolak data invalid dan menerima data valid.
2. Perhitungan similarity menghasilkan pasangan dengan `score > 0` dan `co_occurrence ≥ CF_MIN_CO_OCCURRENCE`.
3. Halaman rekomendasi menampilkan sumber yang konsisten (CF atau fallback).
4. Dashboard analisis menampilkan grafik dan tabel tanpa error.
5. Metrik evaluasi dapat dihasilkan ulang setelah batch CF.

---

## 9. Kesimpulan

1. Teknik analisis data pada sistem Toko Sinar Manis adalah **Item-Based Collaborative Filtering** atas data transaksi, dengan metrik **Cosine Similarity** pada matriks biner (pure CF; rating hanya untuk fallback berlabel).

2. Pengumpulan data mengandalkan transaksi online, impor CSV/Excel, dan ulasan produk; sinyal utama CF adalah pembelian implisit biner.
3. Preprocessing dilakukan melalui pipeline ETL Python (validasi, cleaning, resolusi user, transformasi, load) serta filter status transaksi sebelum pembentukan matriks.
4. Analisis dijalankan dalam dua jalur (PHP dan Python) yang menyimpan hasil ke `product_similarity`, lalu disajikan sebagai rekomendasi personal dan produk serupa.
5. Visualisasi hasil tersedia di dashboard admin (distribusi skor, statistik CF, log) dan antarmuka pelanggan (daftar rekomendasi serta alasan).
6. Hasil analisis kuantitatif (Precision/Recall/F1) dan kualitatif (contoh pasangan produk, perilaku cold-start) dilengkapi melalui skenario pengujian pada Bab 8 dan diisi dengan data aktual dari environment produksi/pengujian.

---

## 10. Lampiran

### Lampiran A — Rumus Ringkas

**Cosine Similarity (biner)**

\[
cosine(A,B)=\frac{|A\cap B|}{\sqrt{|A|\times|B|}}
\]

**Prediction Score**

\[
prediction(u,c)=\frac{\sum_{i\in I_u}sim(i,c)}{|I_u|}
\]

**Best Seller Fallback**

\[
score=0{,}6\cdot\frac{\bar{r}}{5}+0{,}4\cdot\frac{terjual}{\max(terjual)}
\]

### Lampiran B — Struktur Tabel Inti

- `users`
- `products`, `categories`
- `transactions`, `transaction_items`
- `product_reviews`
- `product_similarity`
- `recommendation_logs`
- `data_uploads`, `upload_logs`
- `cf_run_logs`

### Lampiran C — File Implementasi Utama

- `app/Services/RecommenderService.php`
- `app/Http/Controllers/Api/SimilarityController.php`
- `app/Http/Controllers/Admin/AnalisisController.php`
- `app/Http/Controllers/RekomendasiController.php`
- `python/pipeline/pipeline_runner.py`
- `python/pipeline/cleaner.py`
- `python/cf/similarity.py`
- `python/cf/cf_engine.py`
- `python/cf/evaluator.py`
- `resources/views/admin/analisis.blade.php`
- `resources/views/customer/rekomendasi.blade.php`

### Lampiran D — Cara Mengisi Angka Hasil Aktual

1. Pastikan ada transaksi dengan status selesai (dan dibayar untuk jalur PHP).
2. Jalankan **Hitung Ulang** di `/admin/analisis` atau upload CSV hingga pipeline selesai.
3. Catat statistik di halaman analisis (pasangan, coverage, distribusi skor).
4. Ambil Precision@5, Recall@5, F1@5 dari log CF / output evaluator.
5. Salin contoh Top pasangan similarity ke tabel Bab 7.
6. Lakukan skenario Uji-04 s.d. Uji-06 dan lampirkan screenshot.

### Lampiran E — Glosarium Singkat

| Istilah | Arti |
|---------|------|
| IBCF | Item-Based Collaborative Filtering |
| Cold start | Kondisi user/produk tanpa cukup riwayat |
| Co-occurrence | Jumlah user yang membeli dua produk sekaligus |
| Holdout | Data uji yang disisihkan untuk evaluasi |
| ETL | Extract, Transform, Load |
| Hybrid scoring | Penggabungan skor similarity dan rating |

---

*Dokumen ini disusun berdasarkan implementasi kode pada repositori sistem rekomendasi produk Toko Sinar Manis (Laravel + Python CF). Angka hasil evaluasi pada Bab 7 perlu dilengkapi dari environment pengujian pengguna.*
