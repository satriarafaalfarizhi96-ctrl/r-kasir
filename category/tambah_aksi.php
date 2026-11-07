<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../koneksi.php';

if (empty($_SESSION['username'])) { header('Location: ../login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: tambah.php'); exit; }

// Database sarapan2 menggunakan kolom nama_kategori (bukan kategori).
$namaKategori = trim($_POST['nama_kategori'] ?? '');
if ($namaKategori === '') {
    echo '<script>alert("Nama kategori wajib diisi.");window.location.href="tambah.php";</script>'; exit;
}
if (mb_strlen($namaKategori) > 100) {
    echo '<script>alert("Nama kategori maksimal 100 karakter.");window.location.href="tambah.php";</script>'; exit;
}

$stmtCheck = $conn->prepare('SELECT id_kategori FROM kategori WHERE nama_kategori = ? LIMIT 1');
if (!$stmtCheck) {
    http_response_code(500);
    die('Gagal memeriksa kategori: ' . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8'));
}
$stmtCheck->bind_param('s', $namaKategori);
$stmtCheck->execute();
$existing = $stmtCheck->get_result()->fetch_assoc();
$stmtCheck->close();

if ($existing) {
    echo '<script>alert("Kategori sudah ada.");window.location.href="tambah.php";</script>'; exit;
}

// created_at dibuat otomatis oleh DEFAULT CURRENT_TIMESTAMP.
$stmtInsert = $conn->prepare('INSERT INTO kategori (nama_kategori) VALUES (?)');
if (!$stmtInsert) {
    http_response_code(500);
    die('Gagal menyiapkan penambahan kategori: ' . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8'));
}
$stmtInsert->bind_param('s', $namaKategori);
if ($stmtInsert->execute()) {
    $stmtInsert->close();
    $_SESSION['flash_success'] = 'Kategori berhasil ditambahkan!';
    header('Location: categori.php'); exit;
}
$error = $stmtInsert->error;
$stmtInsert->close();
http_response_code(500);
die('Kategori gagal ditambahkan: ' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8'));
?>
