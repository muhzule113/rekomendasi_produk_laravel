# Rencana Eksekusi Perbaikan Sistem Rekomendasi Produk

## 1. Tujuan

Dokumen ini menjadi instruksi kerja untuk memperbaiki repositori `rekomendasi_produk_laravel` agar implementasinya konsisten dengan proposal penelitian Zaldi:

> Pengembangan sistem rekomendasi produk berdasarkan data transaksi pelanggan menggunakan Item-Based Collaborative Filtering dan cosine similarity pada Toko Sinar Manis.

Target akhirnya adalah sistem Laravel yang:

- benar-benar memakai **Item-Based Collaborative Filtering (IBCF)**;
- menghitung **cosine similarity**, bukan Dice similarity;
- memakai transaksi yang valid dan konsisten;
- memberi rekomendasi berdasarkan data historis transaksi, bukan rating;
- mempunyai evaluasi akademik tanpa kebocoran data;
- mempunyai pengujian otomatis dan dokumentasi yang sesuai kode;
- tetap mempertahankan fitur toko, admin, upload data, pembayaran, dan antarmuka yang sudah ada.

## 2. Keputusan Metodologis yang Tidak Boleh Diubah

1. Metode utama adalah **pure Item-Based Collaborative Filtering**.
2. Matriks interaksi adalah matriks biner:
   - `1`: pelanggan pernah membeli produk dalam transaksi valid;
   - `0`: pelanggan belum pernah membeli produk.
3. Transaksi valid wajib memenuhi:
   - `status_pesanan = 'Selesai'`; dan
   - `status_pembayaran = 'Dibayar'`.
4. Kemiripan antarproduk dihitung dengan cosine similarity:

   ```text
   cosine(A, B) = intersection(A, B) / sqrt(buyers(A) * buyers(B))
   ```

   Rumus tersebut berlaku karena vektor interaksi bersifat biner.
5. Jangan membagi skor similarity dengan skor maksimum katalog. Cosine sudah berada pada rentang 0 sampai 1.
6. Rating, jumlah ulasan, kategori, harga, stok, dan popularitas **tidak boleh ikut menghitung skor CF**.
7. Stok dan status produk boleh digunakan setelah pemeringkatan sebagai filter kelayakan tampil.
8. Rating dan popularitas hanya boleh digunakan pada fallback cold start dan harus diberi label sebagai fallback, bukan hasil IBCF.
9. PHP dan Python harus menghasilkan pasangan serta skor yang sama untuk dataset yang sama, dengan toleransi selisih maksimal `1e-6`.
10. Istilah “big data” tidak boleh diklaim sebagai kemampuan teknis sistem selama implementasi masih memakai MySQL dan pemrosesan in-memory. Gunakan istilah **analisis data transaksi pelanggan** pada dokumentasi teknis.

## 3. Aturan Keselamatan Pengerjaan

- Baca seluruh dokumen ini sebelum mengubah kode.
- Periksa `AGENTS.md` jika tersedia dan patuhi instruksinya.
- Jalankan `git status --short` sebelum mulai.
- Pertahankan seluruh perubahan pengguna yang sudah ada.
- Jangan menjalankan `git reset --hard`, `git checkout --`, atau perintah destruktif sejenis.
- Jangan melakukan commit, push, force-push, deploy, atau perubahan remote.
- Jangan mengubah desain visual kecuali diperlukan untuk memperbaiki label/angka yang salah.
- Jangan menghapus fitur Midtrans, keranjang, checkout, admin, upload, dan laporan.
- Gunakan migration baru untuk perubahan skema; jangan mengedit migration lama jika database mungkin sudah digunakan.
- Jangan menggunakan data produksi untuk pengujian.

## 4. Urutan Eksekusi

Kerjakan secara berurutan. Selesaikan dan uji satu fase sebelum masuk ke fase berikutnya.

---

## Fase 0 - Baseline dan Inventarisasi

### Tugas

- [ ] Catat branch aktif dan kondisi worktree.
- [ ] Identifikasi versi PHP, Composer, Laravel, Node.js, Python, MySQL/SQLite, dan dependency Python.
- [ ] Jalankan pengujian yang sudah ada sebagai baseline tanpa mengubah kode.
- [ ] Catat kegagalan yang memang sudah ada sebelum perbaikan.
- [ ] Temukan seluruh penggunaan istilah atau implementasi berikut:
  - `Dice` atau `dice`;
  - `Cosine` atau `cosine`;
  - `calculateCosineSimilarity`;
  - `compute_dice_similarity`;
  - `applyHybridScoring`;
  - `getBoughtProducts`;
  - `getBuyAgainProducts`;
  - `run_evaluation`;
  - `recommendation_dirty`.

### Keluaran fase

- Daftar file yang akan diubah.
- Hasil baseline test dan keterbatasan lingkungan.

---

## Fase 1 - Ganti Dice Menjadi Cosine Similarity yang Benar

### 1.1 PHP

Target utama: `app/Services/RecommenderService.php`.

- [ ] Pertahankan atau rapikan nama `calculateCosineSimilarity()`.
- [ ] Hapus perhitungan Dice:

  ```text
  2 * intersection / (sizeA + sizeB)
  ```

- [ ] Ganti dengan cosine biner:

  ```text
  intersection / sqrt(sizeA * sizeB)
  ```

- [ ] Hapus normalisasi terhadap `$maxRaw`.
- [ ] Jangan simpan diagonal produk terhadap dirinya sendiri.
- [ ] Jangan simpan pasangan dengan skor nol.
- [ ] Tambahkan minimum co-occurrence yang dapat dikonfigurasi melalui environment:

  ```env
  CF_MIN_CO_OCCURRENCE=2
  ```

- [ ] Tambahkan konfigurasi Laravel yang sesuai. Default penelitian adalah `2`. Jika dataset demonstrasi terlalu kecil, nilai boleh diubah melalui `.env`, bukan hard-code.
- [ ] Pastikan statistik `min_score`, `max_score`, `avg_score`, jumlah pasangan, dan coverage dihitung dari skor cosine asli.
- [ ] Jika hasil kalkulasi kosong, jangan membiarkan data similarity lama terlihat sebagai hasil terbaru. Lakukan penggantian tabel secara atomik atau tandai kalkulasi gagal tanpa mengklaim berhasil.

### 1.2 Python

Target utama:

- `python/cf/similarity.py`
- `python/cf/cf_engine.py`
- `python/cf/data_loader.py`
- `python/config.py`

Tugas:

- [ ] Ganti `compute_dice_similarity()` menjadi `compute_cosine_similarity()`.
- [ ] Gunakan matriks item-user biner yang sama dengan PHP.
- [ ] Hitung cosine tanpa normalisasi maksimum.
- [ ] Filter transaksi menjadi `Selesai` dan `Dibayar`.
- [ ] Sertakan hanya produk berstatus `aktif` dalam matriks.
- [ ] Gunakan nilai `CF_MIN_CO_OCCURRENCE` yang sama dengan PHP.
- [ ] Hilangkan pemilihan pasangan yang bergantung pada urutan indeks seperti `row.iloc[i+1:].nlargest(...)` apabila membuat pasangan penting terbuang secara asimetris.
- [ ] Simpan semua pasangan unik yang memenuhi ambang, lalu simpan bentuk dua arah ke database.
- [ ] Bungkus penghapusan dan insert similarity dalam satu transaksi database.
- [ ] Rollback jika insert gagal agar data lama tidak hilang sebagian.
- [ ] Isi `cf_run_logs` secara konsisten: total users, total products, total pairs, coverage, max score, average score, duration, dan status.

### 1.3 Uji matematis wajib

Gunakan fixture berikut pada PHP dan Python:

```text
Produk A = [1, 1, 1, 1]
Produk B = [1, 0, 0, 0]
Produk C = [0, 1, 1, 0]
```

Hasil yang wajib:

```text
cosine(A, B) = 0.500000
cosine(A, C) = 0.707107
cosine(B, C) = 0.000000
```

Tambahkan juga pengujian untuk:

- vektor identik menghasilkan `1`;
- tanpa irisan menghasilkan `0`;
- sifat simetris `sim(A,B) = sim(B,A)`;
- diagonal tidak disimpan;
- skor selalu berada pada rentang 0 sampai 1.

---

## Fase 2 - Satukan Dataset PHP dan Python

### Tugas

- [ ] Buat satu definisi transaksi valid dan gunakan di seluruh query rekomendasi.
- [ ] Terapkan filter `Selesai + Dibayar` pada:
  - pembentukan matriks PHP;
  - pembentukan matriks Python;
  - `getBoughtProducts()`;
  - `getBuyAgainProducts()`;
  - evaluasi;
  - statistik admin yang mengklaim transaksi valid.
- [ ] Produk nonaktif tidak boleh masuk model baru.
- [ ] Transaksi dibatalkan, belum dibayar, gagal, expired, atau refund tidak boleh membentuk riwayat pembelian untuk CF.
- [ ] Tambahkan test yang membuktikan transaksi tidak valid tidak memengaruhi matriks, daftar produk pernah dibeli, maupun rekomendasi.
- [ ] Buat test parity: dataset fixture yang sama harus menghasilkan pasangan dan skor identik dari implementasi PHP serta Python dengan toleransi `1e-6`.

### Catatan arsitektur

Kedua jalur berikut boleh dipertahankan:

1. tombol admin menjalankan perhitungan PHP; dan
2. upload transaksi menjalankan pipeline Python.

Namun keduanya harus menghasilkan model yang sama. Jika parity sulit dijamin, pilih satu mesin sebagai sumber kebenaran dan buat jalur lain memanggil mesin yang sama. Dokumentasikan keputusan tersebut.

---

## Fase 3 - Perbaiki Skor Prediksi Rekomendasi Personal

Target utama: `recommendForCustomer()` pada `RecommenderService`.

### Masalah yang harus dihilangkan

- Query saat ini membatasi baris sebelum deduplikasi.
- Kandidat yang sama dari beberapa produk riwayat tidak diagregasi.
- Kandidat praktis dinilai berdasarkan satu hubungan tertinggi saja.
- Rating dicampurkan ke skor CF.

### Tugas

- [ ] Ambil seluruh produk valid yang pernah dibeli pelanggan.
- [ ] Bentuk kandidat dari seluruh tetangga produk tersebut.
- [ ] Kecualikan produk yang sudah pernah dibeli.
- [ ] Kelompokkan berdasarkan produk kandidat sebelum menerapkan limit.
- [ ] Hitung skor prediksi implicit-feedback sebagai:

  ```text
  prediction_score(user, candidate)
    = sum(similarity(purchased_item, candidate))
      / jumlah_produk_valid_yang_pernah_dibeli_user
  ```

  Similarity yang tidak tersedia diperlakukan sebagai nol. Dengan demikian skor tetap berada pada rentang 0 sampai 1 dan kandidat yang berkaitan dengan beberapa produk riwayat memperoleh nilai lebih kuat.

- [ ] Urutkan berdasarkan `prediction_score` menurun.
- [ ] Gunakan co-occurrence dan ID produk hanya sebagai tie-breaker deterministik.
- [ ] Terapkan limit setelah agregasi, filter, dan pengurutan.
- [ ] Simpan produk riwayat dengan kontribusi terbesar sebagai alasan rekomendasi.
- [ ] Ubah alasan menjadi lebih akurat, misalnya:

  ```text
  Direkomendasikan berdasarkan kemiripan pola pembelian dengan Indomie Goreng.
  ```

  Jangan memakai kalimat “sering dibeli bersama” jika perhitungannya berbasis kesamaan pembeli lintas transaksi, bukan market basket dalam transaksi yang sama.

### Hapus hybrid rating dari hasil IBCF

- [ ] Jangan panggil `applyHybridScoring()` pada rekomendasi IBCF.
- [ ] Hapus rating sebagai pengali, bonus, filter produk rating rendah, atau tie-breaker hasil IBCF.
- [ ] Rating tetap boleh ditampilkan pada kartu produk.
- [ ] Fallback cold start boleh memakai popularitas/rating, tetapi `method` dan log harus menyatakan bahwa hasil tersebut bukan CF.

### Log rekomendasi

- [ ] Catat metode yang benar untuk setiap impresi: `ibcf_cosine`, `cold_start_popular`, `buy_again`, atau `available_fallback`.
- [ ] Jangan mencatat fallback sebagai `CF_personal`.
- [ ] Hindari log duplikat berlebihan dari refresh halaman yang sama jika memungkinkan.

---

## Fase 4 - Bangun Evaluasi Akademik Tanpa Data Leakage

Target utama: `python/cf/evaluator.py`.

### Desain evaluasi wajib

1. Ambil hanya transaksi `Selesai + Dibayar`.
2. Urutkan interaksi setiap pelanggan berdasarkan waktu transaksi dan ID transaksi sebagai tie-breaker.
3. Gunakan pelanggan dengan minimal dua produk berbeda yang memenuhi syarat.
4. Tahan interaksi terbaru sebagai data uji secara deterministik.
5. Bangun ulang matriks dan similarity hanya dari data latih.
6. Jangan membaca tabel similarity produksi yang dihitung dari seluruh data untuk evaluasi holdout.
7. Hasilkan Top-K menggunakan algoritma agregasi yang sama dengan aplikasi.
8. Hitung minimal:
   - Precision@K;
   - Recall@K;
   - F1@K;
   - Hit Rate@K;
   - Catalog Coverage@K;
   - jumlah pengguna yang dievaluasi.
9. Evaluasi minimal untuk `K = 5` dan `K = 10` jika jumlah produk memungkinkan.
10. Hasil harus deterministik ketika dataset tidak berubah.

### Penyimpanan hasil

- [ ] Buat migration baru untuk tabel log evaluasi atau perluas skema melalui migration baru.
- [ ] Simpan waktu evaluasi, versi/metode, K, jumlah pengguna, Precision, Recall, F1, Hit Rate, Coverage, dan durasi.
- [ ] Tampilkan hasil evaluasi terbaru pada halaman Admin → Analisis.
- [ ] Bedakan dengan jelas:
  - **pair coverage**: persentase pasangan produk yang memiliki kemiripan; dan
  - **catalog coverage@K**: persentase katalog yang pernah muncul pada daftar rekomendasi evaluasi.

### Pengujian evaluator

- [ ] Buat dataset kecil dengan hasil hit/miss yang dapat dihitung manual.
- [ ] Pastikan item holdout benar-benar tidak ada di data latih.
- [ ] Pastikan similarity evaluasi tidak memakai data uji.
- [ ] Pastikan pemilihan item terakhir tidak berasal dari urutan `set`.
- [ ] Pastikan kandidat Top-K tidak duplikat.

---

## Fase 5 - Perbaiki Integritas Pipeline Upload

Target utama:

- `python/pipeline/cleaner.py`
- `python/pipeline/user_resolver.py`
- `python/pipeline/transformer.py`
- `python/pipeline/loader.py`
- template upload transaksi.

### Tugas wajib

- [ ] Jangan mengalihkan pelanggan tidak dikenal ke `id_user = 1`.
- [ ] Jangan membuat akun dengan password universal `pelanggan123`.
- [ ] Jangan mencetak password ke log.
- [ ] Untuk ruang lingkup penelitian ini, baris dengan pelanggan yang tidak dapat dicocokkan harus ditolak sebagai invalid dengan alasan yang jelas.
- [ ] Cocokkan pelanggan menggunakan `id_user` aktif atau email akun yang sudah ada.
- [ ] Validasi format email jika email dipakai.
- [ ] Tambahkan dukungan `kode_transaksi` atau `transaction_id` pada template dan deteksi kolom.
- [ ] Kelompokkan item menggunakan kode transaksi, bukan hanya kombinasi user dan tanggal.
- [ ] Jika kode transaksi tidak tersedia untuk file lama, gunakan fallback yang terdokumentasi dan tulis peringatan eksplisit pada hasil import.
- [ ] Isi `sumber_data` sebagai `upload_csv` atau `upload_excel` sesuai tipe file sebenarnya.
- [ ] Cegah duplikasi terhadap data yang sudah ada, bukan hanya duplikasi di dalam satu file.
- [ ] Gunakan transaksi database untuk insert header, items, dan upload logs agar kegagalan tidak menghasilkan data parsial.
- [ ] Gunakan nama produk aktual sebagai `nama_snapshot`, bukan `Produk {id}`.
- [ ] Pindahkan file ke processed hanya setelah insert dan pencatatan status sukses.

### Template transaksi baru

Minimal memuat:

```csv
kode_transaksi,tanggal,id_user,email,id_product,qty,harga_satuan,metode_pembayaran,status_pembayaran,status_pesanan
```

Tetapkan aturan bahwa salah satu dari `id_user` atau `email` harus dapat dicocokkan dengan akun pelanggan yang ada.

---

## Fase 6 - Perbaiki Bug Pendukung yang Memengaruhi Rekomendasi

### Ulasan produk

- [ ] Perbaiki insert `product_reviews` yang menulis `updated_at` padahal kolom tersebut tidak tersedia, atau tambahkan kolom melalui migration baru jika memang diperlukan.
- [ ] Pastikan endpoint ulasan memakai middleware session/auth yang benar. Jika endpoint berada di `routes/api.php` tetapi membutuhkan session web, pindahkan ke `routes/web.php` di dalam grup `auth` dan role pelanggan, atau gunakan mekanisme autentikasi API yang benar.
- [ ] Tambahkan test bahwa pengguna dapat memberi satu ulasan dan duplikasi ditolak.
- [ ] Jika rating hanya ditampilkan, pastikan perubahan ini tidak mengembalikan rating ke skor IBCF.

### Seeder dan factory

- [ ] Sesuaikan `UserFactory` dengan kolom `nama`, `email`, `no_hp`, `alamat`, `password`, `role`, dan `status`.
- [ ] Hapus penggunaan kolom yang tidak tersedia seperti `name`, `email_verified_at`, dan `remember_token`, kecuali ditambahkan melalui migration baru dengan alasan jelas.
- [ ] Jangan menaruh kredensial admin tetap di repository.
- [ ] Sediakan cara aman membuat admin untuk development melalui dokumentasi atau command yang membaca environment.

### File dan JavaScript lama

- [ ] Hapus file sampah root bernama `toArray())`.
- [ ] Hapus duplikasi fungsi chart lama pada `public/assets/js/admin.js`.
- [ ] Hapus referensi endpoint lama seperti `../api/dashboard.php` dan `../api/analysis.php` jika endpoint tersebut tidak ada.
- [ ] Pastikan dashboard memakai data Laravel yang benar dan tidak menghasilkan error console.

### Status model rekomendasi

- [ ] Gunakan `recommendation_dirty` secara nyata:
  - set menjadi dirty setelah transaksi valid berubah;
  - clear setelah kalkulasi similarity berhasil;
  - tampilkan peringatan admin ketika model perlu dihitung ulang.
- [ ] Jangan clear flag jika kalkulasi gagal.

---

## Fase 7 - Tambahkan Pengujian Otomatis

### Laravel/PHP

Tambahkan unit/feature test untuk:

- [ ] rumus cosine dengan fixture wajib;
- [ ] filter transaksi `Selesai + Dibayar`;
- [ ] simetri pasangan similarity;
- [ ] agregasi skor kandidat sebelum limit;
- [ ] produk yang sudah dibeli tidak direkomendasikan kembali oleh IBCF;
- [ ] transaksi batal/tidak dibayar tidak memengaruhi rekomendasi;
- [ ] fallback diberi label dan log yang benar;
- [ ] endpoint admin similarity hanya dapat dipakai admin;
- [ ] ulasan produk berhasil disimpan;
- [ ] upload menolak pelanggan yang tidak dapat di-resolve.

### Python

Gunakan `unittest` atau framework test yang sudah disepakati. Tambahkan test untuk:

- [ ] cosine fixture;
- [ ] filter loader;
- [ ] minimum co-occurrence;
- [ ] parity terhadap expected fixture PHP;
- [ ] evaluasi time-based tanpa leakage;
- [ ] pipeline tidak memakai default user/password;
- [ ] transformasi berdasarkan kode transaksi.

### Larangan

- Jangan mengganti test dengan assertion kosong.
- Jangan menandai test skip hanya untuk mendapatkan hasil hijau, kecuali dependency eksternal benar-benar tidak tersedia dan alasannya didokumentasikan.

---

## Fase 8 - Sinkronkan Dokumentasi dan Antarmuka

Target utama:

- `README.md`
- halaman rekomendasi pelanggan;
- halaman Admin → Analisis;
- `.env.example` dan `.env.docker.example`.

### Tugas

- [ ] Ganti seluruh penjelasan Dice menjadi cosine yang benar.
- [ ] Hapus keterangan Python menggunakan quantity/scaling/scikit-learn cosine jika tidak sesuai implementasi akhir.
- [ ] Jelaskan matriks biner, filter transaksi, rumus, minimum co-occurrence, agregasi kandidat, dan fallback.
- [ ] Ubah label metode hasil utama menjadi `Item-Based CF - Cosine Similarity`.
- [ ] Jangan menyebut hasil fallback sebagai rekomendasi CF.
- [ ] Tampilkan metrik evaluasi dengan definisi yang tepat.
- [ ] Tambahkan `CF_MIN_CO_OCCURRENCE` ke contoh environment.
- [ ] Ubah klaim “big data” menjadi “analisis data transaksi” pada dokumentasi teknis, kecuali ada implementasi dan bukti skala yang benar-benar mendukung big data.
- [ ] Perbarui daftar teknologi pada proposal/dokumentasi jika Python tetap digunakan: Python, pandas, NumPy, PyMySQL, dan SQLAlchemy.

---

## 5. Kriteria Penerimaan Akhir

Pekerjaan dianggap selesai hanya jika seluruh syarat berikut terpenuhi:

- [ ] Tidak ada rumus Dice pada jalur produksi.
- [ ] Tidak ada normalisasi similarity terhadap skor maksimum katalog.
- [ ] Fixture wajib menghasilkan `0.5`, `0.707107`, dan `0` sesuai cosine.
- [ ] PHP dan Python menghasilkan pasangan/skor yang sama dengan toleransi `1e-6`.
- [ ] Semua query riwayat CF hanya memakai transaksi `Selesai + Dibayar`.
- [ ] Rating tidak memengaruhi skor atau filter IBCF.
- [ ] Kandidat diagregasi sebelum limit dan tidak duplikat.
- [ ] Minimum co-occurrence dapat dikonfigurasi dan diuji.
- [ ] Evaluasi memakai split waktu dan similarity dari data latih saja.
- [ ] Precision@K, Recall@K, F1@K, Hit Rate@K, dan Catalog Coverage@K tersedia.
- [ ] Pipeline tidak memakai user ID fallback dan tidak membuat password universal.
- [ ] Review produk dapat disimpan tanpa error skema/auth.
- [ ] Seeder/factory sesuai skema.
- [ ] File `toArray())` tidak ada.
- [ ] Tidak ada fetch ke endpoint `.php` lama yang tidak tersedia.
- [ ] Test baru benar-benar menguji algoritma dan seluruh test lulus.
- [ ] README, UI, dan kode menyebut metode yang sama.
- [ ] Tidak ada commit atau push otomatis.

## 6. Perintah Verifikasi yang Diharapkan

Sesuaikan dengan lingkungan, tetapi usahakan menjalankan seluruh perintah berikut:

```bash
git status --short
composer install
php artisan optimize:clear
php artisan migrate --force
php artisan route:list
php artisan test
python3 -m compileall -q python
python3 -m unittest discover -s python/tests -v
npm install
npm run build
docker compose config
```

Jika Docker tersedia dan aman digunakan pada lingkungan pengujian:

```bash
docker compose build
docker compose up -d
docker compose ps
docker compose logs --no-color --tail=200 app
```

Jangan menjalankan migration terhadap database produksi. Gunakan database test atau container terisolasi.

## 7. Format Laporan Akhir Agent

Agent pelaksana wajib memberikan laporan akhir berisi:

1. ringkasan perubahan;
2. daftar file yang diubah, ditambah, dan dihapus;
3. rumus final similarity dan prediction score;
4. penjelasan cara mencegah data leakage;
5. hasil parity PHP-Python;
6. hasil seluruh test dan build;
7. perintah yang tidak dapat dijalankan beserta alasannya;
8. risiko atau pekerjaan tersisa;
9. konfirmasi bahwa agent tidak melakukan commit, push, atau deploy.

## 8. Prompt Eksekusi Ringkas

Gunakan prompt berikut dari root repositori:

```text
Baca file REMEDIASI_REKOMENDASI_PRODUK.md sampai selesai, lalu laksanakan seluruh fase secara berurutan. Jangan hanya membuat analisis atau rencana: lakukan perubahan kode, migration, test, dokumentasi, dan verifikasi yang diminta. Mulai dengan membaca AGENTS.md jika ada dan menjalankan git status --short. Pertahankan perubahan pengguna, jangan menggunakan perintah Git destruktif, dan jangan commit, push, atau deploy. Metode final wajib pure Item-Based Collaborative Filtering dengan matriks biner dan cosine similarity asli tanpa normalisasi maksimum; rating tidak boleh memengaruhi skor IBCF. Pastikan PHP dan Python memiliki filter, rumus, threshold, serta hasil yang sama. Perbaiki evaluator agar time-based dan bebas data leakage. Jalankan semua pengujian yang tersedia. Jika ada hambatan lingkungan, lanjutkan bagian lain yang aman lalu dokumentasikan perintah yang gagal dan alasannya. Selesaikan dengan laporan sesuai bagian “Format Laporan Akhir Agent”.
```
