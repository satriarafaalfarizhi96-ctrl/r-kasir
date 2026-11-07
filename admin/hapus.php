<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../auth.php';
if (!isset($_SESSION['username']) || $_SESSION['username'] === '') { header('Location: ../login.php'); exit; }
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$username = (string)$_SESSION['username'];
$gambarLogin = 'default.png';
$stmt = $conn->prepare('SELECT gambar, role FROM admin WHERE username = ? LIMIT 1');
$stmt->bind_param('s', $username); $stmt->execute(); $loginData = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$loginData) { session_destroy(); header('Location: ../login.php'); exit; }
$gambarLogin = $loginData['gambar'] ?: 'default.png';
if (!bolehAkses(Role::dariString($loginData['role'] ?? null), Role::Admin)) {
    tampilkanAksesDitolak('../', (string)$username, $loginData['role'] ?? null);
}
?>
<?php
$id=filter_input(INPUT_GET,'id_admin',FILTER_VALIDATE_INT);if(!$id){header('Location: admin.php');exit;}
$stmt=$conn->prepare('SELECT username FROM admin WHERE id_admin=?');$stmt->bind_param('i',$id);$stmt->execute();$target=$stmt->get_result()->fetch_assoc();$stmt->close();
if(!$target){echo '<script>alert("Admin tidak ditemukan.");location="admin.php";</script>';exit;}
if($target['username']===$username){echo '<script>alert("Akun yang sedang digunakan tidak dapat dihapus.");location="admin.php";</script>';exit;}
$del=$conn->prepare('DELETE FROM admin WHERE id_admin=?');$del->bind_param('i',$id);
if($del->execute())echo '<script>alert("Admin berhasil dihapus!");location="admin.php";</script>';else echo '<script>alert("Gagal menghapus admin. Pastikan tidak ada kendala relasi database.");location="admin.php";</script>';$del->close();
?>
