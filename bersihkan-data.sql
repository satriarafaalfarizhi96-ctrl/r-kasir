-- ======================================================================
-- bersihkan-data.sql
-- Mengosongkan SEMUA data di database `kasir-sederhana`, lalu membuat
-- 1 akun admin:  username = s-admin  |  password = tes
--
-- Cara pakai: phpMyAdmin -> pilih database `kasir-sederhana` -> tab SQL
--             -> tempel isi file ini -> Go.
-- PERINGATAN: data tidak bisa dikembalikan. Ekspor dulu bila perlu.
-- ======================================================================

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE `detail_transaksi`;
TRUNCATE TABLE `transaksi`;
TRUNCATE TABLE `produk`;
TRUNCATE TABLE `kategori`;
TRUNCATE TABLE `admin`;

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO `admin` (`id_admin`, `username`, `password`, `nama_lengkap`, `role`, `gambar`) VALUES
(1, 's-admin', '$2y$10$opo9G4J23oJ7ZTasfDuTf.08cJdcN8iJwMTrxvF2dIFmjkyP9n3JS', 'Super Admin', 'admin', 'default.png');
