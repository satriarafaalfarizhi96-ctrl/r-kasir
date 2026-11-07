<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../koneksi.php';

if (!isset($_SESSION['username']) || $_SESSION['username'] === '') {
    header('Location: ../login.php');
    exit;
}

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$username = (string)$_SESSION['username'];
$gambarLogin = 'default.png';
$idAdmin = null;

$stmt = $conn->prepare('SELECT id_admin, gambar FROM admin WHERE username = ? LIMIT 1');
$stmt->bind_param('s', $username);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$u) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}
$idAdmin = (int)$u['id_admin'];
$gambarLogin = $u['gambar'] ?: 'default.png';

// ===== Tambah ke keranjang =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    $pid = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $qty = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

    if (!$pid || !$qty || $qty < 1) {
        echo '<script>alert("Jumlah produk tidak valid.");location="transaksi.php";</script>';
        exit;
    }

    $st = $conn->prepare('SELECT stok FROM produk WHERE id_produk=?');
    $st->bind_param('i', $pid);
    $st->execute();
    $p = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$p) {
        echo '<script>alert("Produk tidak ditemukan.");location="transaksi.php";</script>';
        exit;
    }

    $inCart = (int)($_SESSION['cart'][$pid] ?? 0);
    if ($qty + $inCart > (int)$p['stok']) {
        echo '<script>alert("Jumlah melebihi stok tersedia.");history.back();</script>';
        exit;
    }

    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) $_SESSION['cart'] = [];
    $_SESSION['cart'][$pid] = $inCart + $qty;
    header('Location: pesanan_pembeli.php');
    exit;
}

// ===== Daftar produk =====
$search = trim($_GET['search'] ?? '');
$sql = 'SELECT p.id_produk, p.nama_produk, p.harga, p.stok, p.foto, k.nama_kategori
        FROM produk p INNER JOIN kategori k ON k.id_kategori = p.id_kategori';
if ($search !== '') {
    $sql .= ' WHERE p.nama_produk LIKE ? OR k.nama_kategori LIKE ?';
}
$sql .= ' ORDER BY p.nama_produk ASC';
$st = $conn->prepare($sql);
if ($search !== '') {
    $like = '%' . $search . '%';
    $st->bind_param('ss', $like, $like);
}
$st->execute();
$products = $st->get_result();

$cartCount = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Transaksi - Kasir Management System</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">

<style>
:root{--navy:#0b2559;--deep:#081c44;--blue:#1d4ed8;--sky:#38bdf8;--ice:#eef4ff;--ink:#14213d;--muted:#667799;--line:#d5def0}
*{box-sizing:border-box}body{margin:0;background:var(--navy);font-family:'Nunito Sans',Arial,sans-serif;color:var(--ink)}
.container{display:flex;min-height:100vh}nav{width:250px;background:var(--deep);padding:24px 12px;position:fixed;inset:0 auto 0 0;overflow:auto}
.navbar ul{list-style:none;margin:0;padding:0}.navbar li{padding:4px 0}
.navbar a{display:flex;gap:12px;align-items:center;padding:12px 15px;border-radius:10px;color:#cfe0ff;text-decoration:none;font-weight:700}
.navbar a:hover,.navbar a.active{background:var(--blue);color:#fff}.profile{display:flex;align-items:center;gap:12px;padding:12px;margin:0 0 20px;background:#ffffff12;border-radius:14px;color:white}
.avatar{width:44px;height:44px;border-radius:50%;object-fit:cover;background:white}.admin-name{font-weight:700;color:#fff}
.main{margin-left:250px;padding:32px;flex:1;min-width:0}.top{background:white;padding:22px 26px;border-radius:18px;margin-bottom:24px;display:flex;justify-content:space-between;gap:12px;align-items:center;box-shadow:0 15px 35px #020a402b}
.top h1{font-size:22px;margin:0;color:var(--navy)}.date{color:var(--muted)}
.header{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:18px}
.header h2{color:white;font-size:19px;margin:0}.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:11px 17px;border-radius:10px;text-decoration:none;border:0;cursor:pointer;font-weight:700;font-size:14px;font-family:inherit}
.primary{background:var(--blue);color:#fff}.secondary{background:#eaf0fb;color:var(--navy)}.danger{background:#fff;color:#b42318;border:1px solid #f3c4c0}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:18px}.card,.panel,.empty{background:#fff;border-radius:17px;box-shadow:0 15px 35px #020a402b}
.card{overflow:hidden;display:flex;flex-direction:column}
.card-img{width:100%;height:180px;object-fit:cover;background:var(--ice);display:block}
.card-body{padding:20px;display:flex;flex-direction:column;gap:8px;flex:1}
.card h3{margin:0;color:var(--navy);overflow-wrap:anywhere}
.badge{display:inline-flex;align-items:center;gap:6px;align-self:flex-start;padding:5px 11px;border-radius:999px;background:var(--ice);color:var(--blue);font-size:13px;font-weight:700}
.price{font-size:20px;font-weight:800;color:var(--navy)}.muted{color:var(--muted);font-size:14px}
.card-foot{padding:14px 20px;background:var(--ice);border-top:1px solid var(--line)}
.card-foot form{display:flex;flex-direction:column;gap:8px}
.card-foot .btn{width:100%}
label{display:block;font-weight:700;margin-bottom:2px;font-size:14px}
input[type=text],input[type=number]{width:100%;padding:12px 13px;border:1px solid #ccd5e5;border-radius:9px;font:inherit;background:#fff}
input:focus{outline:2px solid #93c5fd;border-color:var(--blue)}
.panel{overflow:hidden}.panel-body{padding:18px 20px}
.search{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.search input{flex:1;min-width:200px}
.empty{padding:45px 22px;text-align:center;grid-column:1/-1}.empty h3{color:var(--navy)}
.tag{width:44px;height:44px;border-radius:50%;display:grid;place-items:center;background:var(--ice);color:var(--blue);font-size:20px}
.cart{position:fixed;right:28px;bottom:28px;width:56px;height:56px;border-radius:50%;display:grid;place-items:center;background:var(--blue);color:white;text-decoration:none;box-shadow:0 0 0 4px var(--sky);font-size:21px}
@media(max-width:850px){.container{display:block}nav{position:relative;width:100%;height:auto}.main{margin:0;padding:20px 14px}.top{align-items:flex-start;flex-direction:column}}
</style>

</head><body>
<div class="container">

<nav><div class="navbar"><div class="profile">
<img class="avatar" src="../uploads/<?= e($gambarLogin) ?>" alt="Foto admin">
<span class="admin-name"><?= e($username) ?></span></div>
<ul>
<li><a href="../laporan/laporan.php"><i class="fas fa-chart-bar"></i> Laporan</a></li>
<li><a href="../category/categori.php"><i class="fas fa-tags"></i> Kategori</a></li>
<li><a href="../produk/Tproduk.php"><i class="fas fa-box"></i> Produk</a></li>
<li><a href="../admin/admin.php"><i class="fas fa-user-shield"></i> Admin</a></li>
<li><a class="active" href="transaksi.php"><i class="fas fa-receipt"></i> Transaksi</a></li>
<li><a href="../logout/Tlogout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
</ul></div></nav>

<main class="main">
<div class="top"><h1>Transaksi Kasir</h1><div class="date"><i class="far fa-calendar-alt"></i> <?= date('d F Y') ?></div></div>

<div class="header">
<h2><i class="fas fa-receipt"></i> Pilih Produk</h2>
<a class="btn primary" href="pesanan_pembeli.php"><i class="fas fa-shopping-cart"></i> Keranjang (<?= (int)$cartCount ?>)</a>
</div>

<section class="panel" style="margin-bottom:22px"><div class="panel-body">
<form class="search" method="get">
<input type="text" name="search" placeholder="Cari nama produk atau kategori..." value="<?= e($search) ?>">
<button class="btn primary"><i class="fas fa-search"></i> Cari</button>
<a class="btn secondary" href="transaksi.php"><i class="fas fa-rotate-left"></i> Reset</a>
</form>
</div></section>

<?php if ($products->num_rows): ?>
<div class="grid">
<?php while ($p = $products->fetch_assoc()): ?>
<article class="card">
<?php if (!empty($p['foto'])): ?>
<img class="card-img" src="../produk/gambar/<?= rawurlencode($p['foto']) ?>" alt="<?= e($p['nama_produk']) ?>" onerror="this.onerror=null;this.src='../uploads/default.png'">
<?php else: ?>
<div class="card-img" style="display:grid;place-items:center;color:var(--blue)"><i class="fas fa-image fa-3x"></i></div>
<?php endif; ?>
<div class="card-body">
<h3><?= e($p['nama_produk']) ?></h3>
<span class="badge"><i class="fas fa-tag"></i> <?= e($p['nama_kategori']) ?></span>
<div class="price">Rp <?= number_format((int)$p['harga'], 0, ',', '.') ?></div>
<div class="muted">Stok tersedia: <?= (int)$p['stok'] ?></div>
</div>
<div class="card-foot">
<?php if ((int)$p['stok'] > 0): ?>
<form method="post">
<input type="hidden" name="product_id" value="<?= (int)$p['id_produk'] ?>">
<label for="qty<?= (int)$p['id_produk'] ?>">Jumlah</label>
<input id="qty<?= (int)$p['id_produk'] ?>" type="number" name="quantity" min="1" max="<?= (int)$p['stok'] ?>" value="1" required>
<button class="btn primary"><i class="fas fa-cart-plus"></i> Tambah ke Keranjang</button>
</form>
<?php else: ?>
<span class="btn danger" style="width:100%"><i class="fas fa-ban"></i> Stok Habis</span>
<?php endif; ?>
</div>
</article>
<?php endwhile; ?>
</div>
<?php else: ?>
<div class="empty"><div class="tag" style="margin:auto"><i class="fas fa-box-open"></i></div><h3>Produk tidak ditemukan</h3><p>Coba kata kunci lain atau reset pencarian.</p><a class="btn primary" href="transaksi.php"><i class="fas fa-rotate-left"></i> Reset</a></div>
<?php endif; ?>
</main>

<a class="cart" href="pesanan_pembeli.php" aria-label="Buka keranjang"><i class="fas fa-shopping-cart"></i><?php if ($cartCount > 0): ?><sup><?= (int)$cartCount ?></sup><?php endif; ?></a>
</div></body></html>