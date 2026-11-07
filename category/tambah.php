<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../koneksi.php';
if (empty($_SESSION['username'])) { header('Location: ../login.php'); exit; }
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$username = $_SESSION['username'];
$gambar = 'default.png';
$stmtAdmin = $conn->prepare('SELECT gambar FROM admin WHERE username = ? LIMIT 1');
if ($stmtAdmin) {
    $stmtAdmin->bind_param('s', $username);
    $stmtAdmin->execute();
    $admin = $stmtAdmin->get_result()->fetch_assoc();
    if (!empty($admin['gambar'])) $gambar = $admin['gambar'];
    $stmtAdmin->close();
}
$cartItemCount = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
?>
<!DOCTYPE html>
<html lang="id"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tambah Kategori - Kasir Management System</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--navy:#0b2559;--deep:#081c44;--blue:#1d4ed8;--sky:#38bdf8;--ice:#eef4ff;--ink:#14213d;--muted:#667799;--line:#d5def0}
*{box-sizing:border-box}body{margin:0;background:var(--navy);font-family:'Nunito Sans',Arial,sans-serif;color:var(--ink)}
.container{display:flex;min-height:100vh}nav{width:250px;background:var(--deep);padding:24px 12px;position:fixed;inset:0 auto 0 0;overflow-y:auto;z-index:1000}
.navbar ul{list-style:none;margin:0;padding:0}.navbar li{padding:4px 0}.navbar a{display:flex;gap:12px;align-items:center;padding:12px 15px;border-radius:10px;color:#cfe0ff;text-decoration:none;font-weight:700}
.navbar a:hover,.navbar a.active{background:var(--blue);color:#fff}.profile{display:flex;align-items:center;gap:12px;padding:12px;margin:0 0 20px;background:#ffffff12;border-radius:14px;color:#fff}
.avatar{width:44px;height:44px;border-radius:50%;object-fit:cover;background:#fff}.admin-name{font-weight:700;color:#fff;overflow-wrap:anywhere}
.main{margin-left:250px;padding:32px;flex:1;min-width:0}.top{background:#fff;padding:22px 26px;border-radius:18px;margin-bottom:24px;display:flex;justify-content:space-between;gap:12px;align-items:center;box-shadow:0 15px 35px #020a402b}
.top h1{font-size:22px;margin:0;color:var(--navy)}.date{color:var(--muted)}.panel{max-width:760px;background:#fff;border-radius:17px;overflow:hidden;box-shadow:0 15px 35px #020a402b}
.panel-head{padding:20px;background:var(--ice);border-bottom:1px solid var(--line);color:var(--navy);font-weight:800;font-size:18px}.panel-body{padding:28px}
.form-group{margin-bottom:20px}label{display:block;font-weight:800;margin-bottom:8px}
input[type=text]{width:100%;padding:13px 15px;border:1px solid #ccd5e5;border-radius:9px;font:inherit}input[type=text]:focus{outline:2px solid #93c5fd;border-color:var(--blue)}
.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:22px}.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 17px;border-radius:10px;text-decoration:none;border:0;cursor:pointer;font-weight:800;font-size:14px}
.primary{background:var(--blue);color:#fff}.secondary{background:#eaf0fb;color:var(--navy)}.hint{font-size:13px;color:var(--muted);margin-top:7px}
.cart{position:fixed;right:28px;bottom:28px;width:56px;height:56px;border-radius:50%;display:grid;place-items:center;background:var(--blue);color:#fff;text-decoration:none;box-shadow:0 0 0 4px var(--sky);font-size:21px;z-index:1001}
@media(max-width:850px){.container{display:block}nav{position:relative;width:100%;height:auto}.main{margin:0;padding:20px 14px}.top{align-items:flex-start;flex-direction:column}}
</style></head><body><div class="container">
<nav><div class="navbar">
<div class="profile"><img src="../uploads/<?= e($gambar) ?>" alt="Foto admin" class="avatar"><span class="admin-name"><?= e($username) ?></span></div>
<ul>
<li><a href="../laporan/laporan.php"><i class="fas fa-chart-bar"></i> Laporan</a></li>
<li><a href="categori.php" class="active"><i class="fas fa-tags"></i> Kategori</a></li>
<li><a href="../produk/Tproduk.php"><i class="fas fa-box"></i> Produk</a></li>
<li><a href="../admin/admin.php"><i class="fas fa-user-shield"></i> Admin</a></li>
<li><a href="../checkout/transaksi.php"><i class="fas fa-receipt"></i> Transaksi</a></li>
<li><a href="../logout/Tlogout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
</ul></div></nav>
<main class="main"><div class="top"><h1>Tambah Kategori</h1><div class="date"><i class="far fa-calendar-alt"></i> <?= date('d F Y') ?></div></div>
<section class="panel"><div class="panel-head"><i class="fas fa-plus-circle"></i> Tambah Kategori Baru</div><div class="panel-body">
<form action="tambah_aksi.php" method="POST">
<div class="form-group"><label for="nama_kategori">Nama Kategori</label>
<input type="text" id="nama_kategori" name="nama_kategori" maxlength="100" placeholder="Masukkan nama kategori" required autofocus>
<p class="hint">Nama kategori maksimal 100 karakter.</p></div>
<div class="actions"><button type="submit" class="btn primary"><i class="fas fa-save"></i> Simpan Kategori</button>
<a href="categori.php" class="btn secondary"><i class="fas fa-arrow-left"></i> Kembali</a></div>
</form></div></section></main>
<a class="cart" href="../checkout/pesanan_pembeli.php" aria-label="Buka keranjang"><i class="fas fa-shopping-cart"></i><?php if($cartItemCount>0): ?><sup><?= $cartItemCount ?></sup><?php endif; ?></a>
</div></body></html>
