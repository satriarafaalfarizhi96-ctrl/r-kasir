# R-Kasir (Kasir Management System)

Aplikasi web kasir sederhana berbasis **PHP + MySQL**, dengan landing page toko, transaksi kasir,
pembayaran Cash / QRIS / Transfer BCA, laporan penjualan, serta pengelolaan produk, kategori, dan akun.

## Akun awal

Setelah database diimpor, tersedia satu akun admin:

| Username  | Password | Role  |
|-----------|----------|-------|
| `s-admin` | `tes`    | admin |

> **Segera ganti password** setelah login pertama (menu **Admin → Edit**). Jangan memakai password ini di server online.

## Kebutuhan

- PHP 7.4 atau lebih baru (disarankan 8.1+), ekstensi `mysqli` dan `fileinfo` aktif
- MySQL / MariaDB
- Apache, misalnya lewat **XAMPP**

## Instalasi (XAMPP)

1. Salin folder `r-kasir` ke `C:\xampp\htdocs\`.
2. Nyalakan **Apache** dan **MySQL** di XAMPP Control Panel.
3. Buka `http://localhost/phpmyadmin` → tab **Import** → pilih `kasir-sederhana.sql` → **Import**.
   Database `kasir-sederhana` dan akun `s-admin` terbuat otomatis.
4. Cek `koneksi.php` (bawaan XAMPP: user `root`, password kosong).
5. Buka `http://localhost/r-kasir/login.php` dan masuk dengan `s-admin` / `tes`.

Beranda publik ada di `http://localhost/r-kasir/halaman.php`.

### Membuka dari HP (satu jaringan WiFi)

1. Di laptop jalankan `ipconfig`, catat **IPv4 Address** (contoh `192.168.1.10`).
2. Di browser HP buka `http://192.168.1.10/r-kasir/halaman.php`.
3. Bila gagal, izinkan **Apache HTTP Server** di Windows Firewall dan pastikan jaringan WiFi laptop bertipe **Private**.

## Mengosongkan data / mulai dari awal

Untuk menghapus seluruh data (produk, kategori, transaksi, akun) dan membuat ulang akun `s-admin`:

1. Ekspor dulu bila datanya penting (phpMyAdmin → **Export**).
2. phpMyAdmin → pilih database `kasir-sederhana` → tab **SQL**.
3. Tempel isi file `bersihkan-data.sql` → **Go**.

Foto yang sudah terunggah tidak ikut terhapus oleh SQL. Hapus manual isi `produk/gambar/` (foto produk) dan
`uploads/` yang berawalan `admin_` (foto profil) bila ingin dibersihkan.

## Fitur

### Beranda publik (`halaman.php`)
- Landing page toko dengan menu Beranda, Tentang, Keunggulan, dan Produk.
- Daftar produk ditampilkan per kategori, mengambil data langsung dari menu Produk dan Kategori.
- Tombol Masuk / Buat Akun bila belum login; nama dan role tampil di header bila sudah login.

### Login dan akun
- **Login** (`login.php`): password disimpan sebagai hash; hash lama otomatis diperbarui saat login.
- **Buat akun** (`tambah-admin.php`): nama lengkap, username, password minimal 6 karakter, konfirmasi password,
  dan foto profil opsional (JPG/PNG/WEBP, maks. 2 MB). Akun baru selalu berperan **kasir**.
- **Logout** tersedia di setiap menu.

### Hak akses (role)
| Role  | Hak akses |
|-------|-----------|
| admin | Semua menu, termasuk **Admin** (kelola akun) dan upload/hapus foto QRIS |
| kasir | Transaksi, produk, kategori, laporan; tidak bisa membuka menu Admin |

Kasir yang membuka halaman Admin akan melihat halaman **Akses Ditolak (403)**.

### Manajemen Admin (`admin/`, khusus admin)
- Lihat daftar akun (foto, nama lengkap, username, role, tanggal dibuat).
- Tambah, edit (termasuk mengubah role), dan hapus akun.
- Akun yang sedang dipakai login tidak dapat dihapus.

### Kategori (`category/`)
- Tambah, edit, dan hapus kategori.
- Kategori yang masih dipakai produk tidak dapat dihapus.

### Produk (`produk/`)
- Tambah, edit, dan hapus produk: kategori, nama (maks. 150 karakter), harga, stok, dan foto (JPG/PNG/WEBP).
- Pencarian produk berdasarkan nama atau kategori.
- Produk yang sudah pernah terjual tidak dapat dihapus agar riwayat transaksi tetap utuh.

### Transaksi kasir (`checkout/transaksi.php`)
- Daftar produk dengan pencarian nama atau kategori dan tampilan stok tersedia.
- Tambah ke keranjang; jumlah tidak boleh melebihi stok.

### Keranjang dan pembayaran (`checkout/pesanan_pembeli.php`)
- Ubah jumlah (+ / − / ketik angka), hapus item, atau kosongkan keranjang.
- Ringkasan: jenis produk, total item, dan total harga.
- Tiga metode pembayaran:
  - **Cash**: isi uang pembayaran; sistem menghitung kembalian dan memberi peringatan bila uang kurang.
  - **QRIS**: menampilkan gambar QRIS untuk discan pembeli (lihat bagian pengaturan di bawah).
  - **Transfer BCA**: menampilkan nomor rekening dengan tombol **Salin nomor**. **Minimal belanja Rp 10.000**;
    di bawah itu tombol proses dinonaktifkan, dan server juga menolak transaksinya.
- Stok produk otomatis berkurang saat transaksi berhasil.

### Nota (`checkout/pesanan.php`)
- Nomor nota otomatis (format `TRX` + tanggal + kode acak), rincian item, metode, bayar, dan kembalian.
- Tombol **Cetak Nota** untuk mencetak lewat browser.

### Laporan (`laporan/laporan.php`)
- Filter berdasarkan tahun, bulan, minggu, dan hari.
- Grafik pie dan bar penjualan, serta tabel laporan.
- Grafik dan tabel berdasarkan metode pembayaran (Cash / QRIS / BCA).
- Grafik perbandingan antar minggu.

## Pengaturan pembayaran

**Nomor BCA** (sekarang `0128123888`) dan **minimal belanja BCA** diatur lewat konstanta di:
- `checkout/pesanan_pembeli.php`: `NOMOR_BCA` dan `MIN_BCA` (nilai `10000`)
- `checkout/pesanan.php`: `NOMOR_BCA`
- `checkout/beli.php`: angka `10000` pada pengecekan minimal belanja BCA (validasi server)

Bila ingin mengubah nominal minimal, ubah angkanya di `MIN_BCA` dan di `beli.php`.

**Gambar QRIS** (format JPG / PNG / WEBP, maks. 5 MB):
1. Login sebagai **admin**.
2. Buka **Transaksi**, tambahkan produk ke keranjang, lalu buka halaman keranjang (`pesanan_pembeli.php`).
3. Pilih metode **QRIS** → bagian **Kelola foto QRIS** → pilih file → **Unggah Foto**.
4. Gambar tersimpan sebagai `uploads/qris/qris.jpg` (atau `.png` / `.webp`). Untuk mengganti, unggah lagi; untuk menghapus, klik **Hapus**.

Selama gambar QRIS belum diunggah, metode QRIS tidak dapat dipakai untuk memproses pembayaran.

## Memasukkan / mengganti logo

Logo diambil dari folder `img/`. Cara termudah: **timpa file dengan nama yang sama**, tanpa mengubah kode.

| File          | Dipakai di |
|---------------|------------|
| `img/kun.png` | Header dan footer beranda, bagian Tentang, halaman Login, halaman Buat Akun, dan favicon tab browser |
| `img/lg.png`  | Halaman lupa password dan password baru (`login/forgetpass.php`, `login/newpass.php`) |

Langkah:
1. Siapkan logo berformat **PNG**, sebaiknya persegi (contoh 512 × 512 px), latar transparan, ukuran di bawah 200 KB.
2. Ubah nama file menjadi `kun.png` (dan/atau `lg.png`).
3. Salin ke folder `r-kasir/img/` dan pilih **Replace** (timpa file lama).
4. Muat ulang browser dengan **Ctrl + F5** agar logo lama dari cache tidak muncul.

Bila ingin memakai nama file lain (misalnya `logo-toko.png`), taruh di `img/` lalu ganti `img/kun.png` menjadi
`img/logo-toko.png` di `halaman.php`, `login.php`, dan `tambah-admin.php` (cari teks `kun.png`). Untuk
`lg.png`, ubah di `login/forgetpass.php` dan `login/newpass.php` (path-nya `../img/lg.png`).

Nama toko **"Dikkun Kriuks"** pada beranda berupa teks biasa. Ganti lewat pencarian teks `Dikkun Kriuks` di `halaman.php`.

## Struktur folder

```
r-kasir/
├── halaman.php            Beranda / landing page publik
├── login.php              Halaman login
├── tambah-admin.php       Pendaftaran akun
├── auth.php               Class Role + pengecekan hak akses (halaman "Akses Ditolak")
├── koneksi.php            Koneksi database
├── kasir-sederhana.sql    Struktur database + akun s-admin
├── bersihkan-data.sql     Kosongkan semua data + buat ulang akun s-admin
├── admin/                 Kelola akun admin/kasir
├── category/              Kelola kategori
├── produk/                Kelola produk (foto di produk/gambar/)
├── checkout/              Transaksi, keranjang, pembayaran, nota
├── laporan/               Laporan penjualan
├── login/                 Halaman lupa/ubah password
├── logout/                Logout
├── img/                   Logo aplikasi (kun.png, lg.png)
└── uploads/               Foto profil, default.png, dan QRIS (uploads/qris/)
```

## Hosting online

1. Pilih hosting dengan PHP dan MySQL, upload folder lewat File Manager atau FTP.
2. Buat database dan user di panel hosting, lalu impor `kasir-sederhana.sql`
   (hapus dulu baris `CREATE DATABASE` dan `USE` di bagian atas bila hosting mengharuskan nama database berawalan).
3. Ubah `koneksi.php` sesuai host, user, password, dan nama database dari hosting.
4. Pastikan `uploads/`, `uploads/qris/`, dan `produk/gambar/` dapat ditulis (permission 755 atau 775).
5. Aktifkan HTTPS, ganti password `s-admin`, lalu hapus `kasir-sederhana.sql` dan `bersihkan-data.sql` dari server.

## Catatan

- **Lupa password** (`login/forgetpass.php`) mencari akun berdasarkan kolom `email`, sedangkan tabel `admin`
  tidak punya kolom tersebut, sehingga fitur ini belum berfungsi. Sementara itu, ganti password lewat
  menu **Admin → Edit** atau lewat phpMyAdmin.
- Pendaftaran akun terbuka untuk siapa saja. Bila dipakai di internet, batasi atau nonaktifkan `tambah-admin.php`.
- Jangan menyimpan `koneksi.php` berisi kredensial asli di repositori publik.
- Isi `uploads/` dan `produk/gambar/` diabaikan Git (`.gitignore`) agar foto tidak ikut terpublikasi.
