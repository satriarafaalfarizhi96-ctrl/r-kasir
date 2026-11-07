<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../auth.php';

if (!isset($_SESSION['username']) || $_SESSION['username'] === '') {
    header('Location: ../login.php');
    exit;
}

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$username = (string)$_SESSION['username'];
$gambarLogin = 'default.png';

$stmt = $conn->prepare('SELECT gambar, role FROM admin WHERE username = ? LIMIT 1');
$stmt->bind_param('s', $username);
$stmt->execute();
$loginData = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$loginData) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}
$gambarLogin = $loginData['gambar'] ?: 'default.png';

if (!bolehAkses(Role::dariString($loginData['role'] ?? null), Role::Admin)) {
    tampilkanAksesDitolak('../', (string)$username, $loginData['role'] ?? null);
}

$cartItemCount = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? count($_SESSION['cart']) : 0;

$rows = $conn->query('SELECT id_admin, username, nama_lengkap, role, gambar, created_at FROM admin ORDER BY id_admin DESC');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin - Kasir Management System</title>
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
.card,.panel,.empty{background:#fff;border-radius:17px;box-shadow:0 15px 35px #020a402b}
.panel{overflow:hidden}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:720px}
th{background:var(--ice);color:var(--navy);text-align:left;font-weight:800;padding:16px 20px;border-bottom:1px solid var(--line);white-space:nowrap}
td{padding:14px 20px;border-bottom:1px solid var(--line);vertical-align:middle;overflow-wrap:anywhere}
tbody tr:last-child td{border-bottom:0}tbody tr:hover{background:#f7faff}
.avatar-small{width:44px;height:44px;border-radius:50%;object-fit:cover;background:var(--ice);display:block}
.role{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:999px;background:var(--ice);color:var(--blue);font-size:13px;font-weight:700}
.actions{display:flex;gap:9px;flex-wrap:wrap;align-items:center}.muted{color:var(--muted);font-size:14px;font-weight:600}
.empty{padding:45px 22px;text-align:center}.empty h3{color:var(--navy)}
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
<li><a class="active" href="admin.php"><i class="fas fa-user-shield"></i> Admin</a></li>
<li><a href="../checkout/transaksi.php"><i class="fas fa-receipt"></i> Transaksi</a></li>
<li><a href="../logout/Tlogout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
</ul></div></nav>

<main class="main">
<div class="top"><h1>Manajemen Admin</h1><div class="date"><i class="far fa-calendar-alt"></i> <?= date('d F Y') ?></div></div>

<div class="header">
<h2><i class="fas fa-user-shield"></i> Daftar Admin dan Kasir</h2>
<a class="btn primary" href="tambah.php"><i class="fas fa-plus"></i> Tambah Admin</a>
</div>

<?php if ($rows && $rows->num_rows): ?>
<section class="panel"><div class="table-wrap">
<table>
<thead><tr><th>Foto</th><th>Nama lengkap</th><th>Username</th><th>Role</th><th>Dibuat</th><th>Aksi</th></tr></thead>
<tbody>
<?php while ($r = $rows->fetch_assoc()): ?>
<tr>
<td><img class="avatar-small" src="../uploads/<?= e($r['gambar'] ?: 'default.png') ?>" alt=""></td>
<td><?= e($r['nama_lengkap']) ?></td>
<td><?= e($r['username']) ?></td>
<td><span class="role"><i class="fas fa-user-tag"></i> <?= e(ucfirst($r['role'])) ?></span></td>
<td><?= e($r['created_at']) ?></td>
<td><div class="actions">
<a class="btn primary" href="edit.php?id_admin=<?= (int)$r['id_admin'] ?>"><i class="fas fa-edit"></i> Edit</a>
<?php if ($r['username'] !== $username): ?>
<a class="btn danger" href="hapus.php?id_admin=<?= (int)$r['id_admin'] ?>" onclick="return confirm('Hapus akun ini?')"><i class="fas fa-trash"></i> Hapus</a>
<?php else: ?>
<span class="muted">Akun aktif</span>
<?php endif; ?>
</div></td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div></section>
<?php else: ?>
<div class="empty"><div class="tag" style="margin:auto"><i class="fas fa-user-shield"></i></div><h3>Belum ada data admin</h3><p>Tambahkan akun admin atau kasir untuk mengelola kasir.</p><a class="btn primary" href="tambah.php"><i class="fas fa-plus"></i> Tambah Admin</a></div>
<?php endif; ?>
</main>

<a class="cart" href="../checkout/pesanan_pembeli.php" aria-label="Buka keranjang"><i class="fas fa-shopping-cart"></i><?php if ($cartItemCount > 0): ?><sup><?= $cartItemCount ?></sup><?php endif; ?></a>
</div></body></html>