# BAB V  
# KESIMPULAN DAN SARAN

## 5.1 Kesimpulan

Berdasarkan hasil perancangan, implementasi, dan pengujian sistem rekomendasi produk berbasis analisis big data terhadap transaksi pelanggan pada studi kasus Toko Sinar Manis, dapat ditarik kesimpulan sebagai berikut.

1. **Sistem rekomendasi produk berhasil dikembangkan** dalam bentuk aplikasi web Toko Sinar Manis yang mendukung aktivitas belanja pelanggan (katalog, keranjang, checkout, riwayat) sekaligus menyediakan modul admin untuk pengelolaan produk, pelanggan, transaksi, unggah data, analisis rekomendasi, ulasan, dan laporan.

2. **Analisis data transaksi pelanggan** dapat diterapkan sebagai dasar pemberian rekomendasi. Data transaksi yang valid (`status_pembayaran = Dibayar` dan `status_pesanan = Selesai`) diolah melalui tahapan pengumpulan, preprocessing, transformasi ke matriks interaksi user–item biner, serta perhitungan pola pembelian bersama antarproduk.

3. **Metode Item-Based Collaborative Filtering (IBCF) dengan Cosine Similarity** berhasil diimplementasikan sebagai metode inti rekomendasi. Sistem menghitung kemiripan antarproduk berdasarkan perilaku pembelian implisit pelanggan, kemudian menyusun daftar rekomendasi personal berdasarkan riwayat transaksi pengguna. Pada kondisi cold start (pengguna tanpa riwayat yang memadai), sistem menyediakan mekanisme fallback berupa produk populer/terlaris yang diberi label secara transparan.

4. **Hasil rekomendasi dapat disajikan kepada pengguna** melalui halaman rekomendasi personal dan bagian produk serupa pada detail produk, sehingga pelanggan terbantu dalam menemukan produk yang relevan dengan pola belanjanya. Di sisi admin, hasil analisis dapat dipantau melalui dashboard analisis rekomendasi, termasuk statistik similarity, riwayat perhitungan, dan evaluasi model.

5. Secara keseluruhan, penelitian ini menunjukkan bahwa **pemanfaatan data transaksi pelanggan secara sistematis** mampu mendukung proses rekomendasi produk pada Toko Sinar Manis, mengurangi ketergantungan pada pemilihan produk secara manual, serta memberikan nilai tambah bagi pelanggan maupun pihak toko dalam pengambilan keputusan berbasis data.

---

## 5.2 Saran

Berdasarkan keterbatasan yang ditemukan selama pengembangan dan pengujian, diajukan beberapa saran sebagai berikut.

### 5.2.1 Saran bagi Pengguna Sistem (Toko Sinar Manis)

1. Admin disarankan secara berkala melakukan **hitung ulang similarity** setelah terdapat banyak transaksi valid baru, agar model rekomendasi tetap relevan dengan pola belanja terkini.
2. Unggah data transaksi historis sebaiknya menggunakan **format template CSV yang sesuai**, agar proses ETL berjalan optimal dan baris invalid dapat diminimalkan.
3. Hasil analisis kemiripan produk dapat dimanfaatkan untuk mendukung strategi toko, misalnya **cross-selling**, bundling produk yang sering dibeli bersama, serta pemantauan stok produk yang sering direkomendasikan.

### 5.2.2 Saran bagi Pengembangan Selanjutnya

1. **Penanganan cold start** dapat ditingkatkan, misalnya dengan memanfaatkan sinyal tambahan (seperti view produk) atau pendekatan hybrid yang lebih kaya, tanpa menghilangkan peran utama IBCF berbasis transaksi.
2. Representasi interaksi dapat dikembangkan dari matriks biner menjadi model berbobot (misalnya mempertimbangkan frekuensi atau kuantitas pembelian), agar intensitas minat pelanggan lebih tercermin dalam perhitungan.
3. Evaluasi sistem dapat diperluas dengan pengujian pengguna secara langsung (*user acceptance*) serta pemantauan metrik bisnis (misalnya konversi dari rekomendasi ke pembelian), di samping metrik Precision/Recall/F1.
4. Skalabilitas pemrosesan data dapat ditingkatkan apabila volume transaksi terus membesar, misalnya melalui optimalisasi batch processing, penjadwalan pipeline, atau arsitektur pemrosesan yang lebih efisien.
5. Penelitian lanjutan dapat membandingkan IBCF–Cosine Similarity dengan metode rekomendasi lain (misalnya user-based CF atau content-based filtering) untuk menilai keunggulan relatif pada konteks data Toko Sinar Manis.

---

*Bab ini merupakan bagian dari skripsi: “Pengembangan Sistem Rekomendasi Produk Berbasis Analisis Big Data terhadap Transaksi Pelanggan (Studi Kasus Toko Sinar Manis)”.*
