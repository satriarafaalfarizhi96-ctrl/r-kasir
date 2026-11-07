<?php
// File ini menggunakan database sarapan2 melalui ../koneksi.php.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../koneksi.php';

if (!isset($_SESSION['username']) || $_SESSION['username'] === '') {
    header('Location: ../login.php');
    exit;
}

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$username = $_SESSION['username'];
$gambar = 'default.png';

$stmtAdmin = $conn->prepare('SELECT gambar FROM admin WHERE username = ? LIMIT 1');
if ($stmtAdmin) {
    $stmtAdmin->bind_param('s', $username);
    $stmtAdmin->execute();
    $admin = $stmtAdmin->get_result()->fetch_assoc();
    if (!empty($admin['gambar'])) {
        $gambar = $admin['gambar'];
    }
    $stmtAdmin->close();
}

$cartItemCount = isset($_SESSION['cart']) && is_array($_SESSION['cart'])
    ? count($_SESSION['cart']) : 0;
?>

<?php
$idKategori = filter_input(INPUT_GET, 'id_kategori', FILTER_VALIDATE_INT);
if (!$idKategori || $idKategori < 1) {
    header('Location: categori.php?status=invalid');
    exit;
}

// Pastikan kategori masih ada.
$stmt = $conn->prepare('SELECT nama_kategori FROM kategori WHERE id_kategori = ? LIMIT 1');
$stmt->bind_param('i', $idKategori);
$stmt->execute();
$kategori = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$kategori) {
    header('Location: categori.php?status=notfound');
    exit;
}

// Cek apakah kategori masih dipakai produk. Kolom id_kategori dipertahankan
// sesuai relasi yang digunakan oleh kode aplikasi sebelumnya.
$stmtCheck = $conn->prepare('SELECT COUNT(*) AS jumlah FROM produk WHERE id_kategori = ?');
if (!$stmtCheck) {
    die('Gagal memeriksa penggunaan kategori: ' . e($conn->error));
}
$stmtCheck->bind_param('i', $idKategori);
$stmtCheck->execute();
$jumlahProduk = (int)$stmtCheck->get_result()->fetch_assoc()['jumlah'];
$stmtCheck->close();

if ($jumlahProduk > 0) {
    echo '<script>alert("Kategori ini masih digunakan oleh ' . $jumlahProduk . ' produk sehingga tidak dapat dihapus."); window.location.href="categori.php";</script>';
    exit;
}

$stmtDelete = $conn->prepare('DELETE FROM kategori WHERE id_kategori = ?');
if (!$stmtDelete) {
    die('Gagal menyiapkan penghapusan: ' . e($conn->error));
}
$stmtDelete->bind_param('i', $idKategori);

if (!$stmtDelete->execute()) {
    $error = $stmtDelete->error;
    $stmtDelete->close();
    die('Kategori gagal dihapus: ' . e($error));
}
$stmtDelete->close();

echo '<script>
    alert("Kategori berhasil dihapus!");
    window.location.href = "categori.php";
</script>';
exit;
?>
