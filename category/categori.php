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

$flashSuccess = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);
?>

<?php
$batas = 4;
$halaman = max(1, (int)($_GET['halaman'] ?? 1));

$resultCount = $conn->query('SELECT COUNT(*) AS jumlah FROM kategori');
if (!$resultCount) {
    die('Gagal menghitung kategori: ' . e($conn->error));
}
$jumlahData = (int)$resultCount->fetch_assoc()['jumlah'];
$totalHalaman = max(1, (int)ceil($jumlahData / $batas));
$halaman = min($halaman, $totalHalaman);
$offset = ($halaman - 1) * $batas;

$stmt = $conn->prepare('SELECT id_kategori, nama_kategori FROM kategori ORDER BY id_kategori DESC LIMIT ? OFFSET ?');
$stmt->bind_param('ii', $batas, $offset);
$stmt->execute();
$kategori = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kategori - Kasir Management System</title>
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
.header h2{color:white;font-size:19px;margin:0}.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:11px 17px;border-radius:10px;text-decoration:none;border:0;cursor:pointer;font-weight:700;font-size:14px}
.primary{background:var(--blue);color:#fff}.secondary{background:#eaf0fb;color:var(--navy)}.danger{background:#fff;color:#b42318;border:1px solid #f3c4c0}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:18px}.card,.panel,.empty{background:#fff;border-radius:17px;box-shadow:0 15px 35px #020a402b}
.card{overflow:hidden}.card-head{padding:22px;display:flex;align-items:center;gap:14px}.tag{width:44px;height:44px;border-radius:50%;display:grid;place-items:center;background:var(--ice);color:var(--blue);font-size:20px}
.card h3{margin:0;color:var(--navy);overflow-wrap:anywhere}.card-foot{padding:14px 20px;background:var(--ice);border-top:1px solid var(--line);display:flex;justify-content:flex-end;gap:9px}
.pagination{display:flex;justify-content:center;gap:8px;flex-wrap:wrap;margin:25px 0;padding:0;list-style:none}.pagination a{display:grid;place-items:center;min-width:40px;height:40px;padding:0 10px;background:#fff;border-radius:10px;text-decoration:none;color:var(--navy);font-weight:700}.pagination a.active{background:var(--blue);color:white}
.panel{max-width:700px;overflow:hidden}.panel-head{padding:20px;background:var(--ice);border-bottom:1px solid var(--line);color:var(--navy);font-weight:800}.panel-body{padding:26px}
label{display:block;font-weight:700;margin-bottom:8px}input[type=text]{width:100%;padding:13px;border:1px solid #ccd5e5;border-radius:9px;font:inherit}input:focus{outline:2px solid #93c5fd;border-color:var(--blue)}
.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:22px}.empty{padding:45px 22px;text-align:center}.empty h3{color:var(--navy)}.notice{padding:12px 15px;border-radius:10px;background:#fff3cd;color:#664d03;margin-bottom:16px}
.cart{position:fixed;right:28px;bottom:28px;width:56px;height:56px;border-radius:50%;display:grid;place-items:center;background:var(--blue);color:white;text-decoration:none;box-shadow:0 0 0 4px var(--sky);font-size:21px}
@media(max-width:850px){.container{display:block}nav{position:relative;width:100%;height:auto}.main{margin:0;padding:20px 14px}.top{align-items:flex-start;flex-direction:column}}
</style>

</head><body>
<?php if ($flashSuccess !== ''): ?>
<script>alert(<?= json_encode($flashSuccess, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);</script>
<?php endif; ?>
<div class="container">

<nav><div class="navbar"><div class="profile">
<img class="avatar" src="../uploads/<?= e($gambar) ?>" alt="Foto admin">
<span class="admin-name"><?= e($username) ?></span></div>
<ul>
<li><a href="../laporan/laporan.php"><i class="fas fa-chart-bar"></i> Laporan</a></li>
<li><a class="active" href="categori.php"><i class="fas fa-tags"></i> Kategori</a></li>
<li><a href="../produk/Tproduk.php"><i class="fas fa-box"></i> Produk</a></li>
<li><a href="../admin/admin.php"><i class="fas fa-user-shield"></i> Admin</a></li>
<li><a href="../checkout/transaksi.php"><i class="fas fa-receipt"></i> Transaksi</a></li>
<li><a href="../logout/Tlogout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
</ul></div></nav>

<main class="main">
<div class="top"><h1>Kategori Kasir</h1><div class="date"><i class="far fa-calendar-alt"></i> <?= date('d F Y') ?></div></div>
<div class="header"><h2><i class="fas fa-tags"></i> Daftar Kategori Produk</h2><a class="btn primary" href="tambah.php"><i class="fas fa-plus"></i> Tambah Kategori</a></div>
<?php if ($kategori->num_rows > 0): ?>
<div class="grid">
<?php while ($row = $kategori->fetch_assoc()): ?>
<article class="card"><div class="card-head"><div class="tag"><i class="fas fa-tag"></i></div><h3><?= e($row['nama_kategori']) ?></h3></div>
<div class="card-foot">
<a class="btn primary" href="edit.php?id_kategori=<?= (int)$row['id_kategori'] ?>"><i class="fas fa-edit"></i> Edit</a>
<a class="btn danger" href="hapus.php?id_kategori=<?= (int)$row['id_kategori'] ?>" onclick="return confirm('Yakin ingin menghapus kategori ini?')"><i class="fas fa-trash"></i> Hapus</a>
</div></article>
<?php endwhile; ?>
</div>
<ul class="pagination">
<li><a href="?halaman=<?= max(1,$halaman-1) ?>" aria-label="Sebelumnya">&laquo;</a></li>
<?php for($i=1;$i<=$totalHalaman;$i++): ?><li><a class="<?= $i===$halaman?'active':'' ?>" href="?halaman=<?= $i ?>"><?= $i ?></a></li><?php endfor; ?>
<li><a href="?halaman=<?= min($totalHalaman,$halaman+1) ?>" aria-label="Berikutnya">&raquo;</a></li>
</ul>
<?php else: ?>
<div class="empty"><div class="tag" style="margin:auto"><i class="fas fa-tag"></i></div><h3>Belum ada kategori</h3><p>Tambahkan kategori untuk mengelompokkan produk kasir.</p><a class="btn primary" href="tambah.php"><i class="fas fa-plus"></i> Tambah Kategori</a></div>
<?php endif; ?>
</main>
<a class="cart" href="../checkout/pesanan_pembeli.php" aria-label="Buka keranjang"><i class="fas fa-shopping-cart"></i><?php if($cartItemCount>0): ?><sup><?= $cartItemCount ?></sup><?php endif; ?></a>
</div></body></html>
