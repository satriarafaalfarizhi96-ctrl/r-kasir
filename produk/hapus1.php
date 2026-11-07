<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../koneksi.php';
if (empty($_SESSION['username'])) { header('Location: ../login.php'); exit; }
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$username = $_SESSION['username'];
$gambarAdmin = 'default.png';
$stmtAdmin = $conn->prepare('SELECT gambar FROM admin WHERE username = ? LIMIT 1');
if ($stmtAdmin) { $stmtAdmin->bind_param('s', $username); $stmtAdmin->execute(); $admin = $stmtAdmin->get_result()->fetch_assoc(); if (!empty($admin['gambar'])) $gambarAdmin = $admin['gambar']; $stmtAdmin->close(); }
$cartItemCount = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? count($_SESSION['cart']) : 0;

$id=filter_input(INPUT_GET,'id_produk',FILTER_VALIDATE_INT);if(!$id||$id<1){$_SESSION['flash_produk']='ID produk tidak valid.';header('Location: Tproduk.php');exit;}
$check=$conn->prepare('SELECT foto FROM produk WHERE id_produk=?');$check->bind_param('i',$id);$check->execute();$product=$check->get_result()->fetch_assoc();$check->close();if(!$product){$_SESSION['flash_produk']='Produk tidak ditemukan.';header('Location: Tproduk.php');exit;}
$used=$conn->prepare('SELECT COUNT(*) jumlah FROM detail_transaksi WHERE id_produk=?');$used->bind_param('i',$id);$used->execute();$count=(int)$used->get_result()->fetch_assoc()['jumlah'];$used->close();if($count>0){$_SESSION['flash_produk']='Produk tidak dapat dihapus karena sudah tercatat dalam transaksi.';header('Location: Tproduk.php');exit;}
$del=$conn->prepare('DELETE FROM produk WHERE id_produk=?');$del->bind_param('i',$id);if($del->execute()){if(!empty($product['foto'])&&is_file(__DIR__.'/gambar/'.$product['foto']))@unlink(__DIR__.'/gambar/'.$product['foto']);$_SESSION['flash_produk']='Produk berhasil dihapus!';}else{$_SESSION['flash_produk']='Produk gagal dihapus: '.$del->error;}$del->close();header('Location: Tproduk.php');exit;
?>