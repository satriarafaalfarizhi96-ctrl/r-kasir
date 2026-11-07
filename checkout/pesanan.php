<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../koneksi.php';
if (!isset($_SESSION['username']) || $_SESSION['username'] === '') { header('Location: ../login.php'); exit; }
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$username=(string)$_SESSION['username'];$gambarLogin='default.png';$idAdmin=null;
$stmt=$conn->prepare('SELECT id_admin,gambar FROM admin WHERE username=? LIMIT 1');$stmt->bind_param('s',$username);$stmt->execute();$u=$stmt->get_result()->fetch_assoc();$stmt->close();
if(!$u){session_destroy();header('Location: ../login.php');exit;}$idAdmin=(int)$u['id_admin'];$gambarLogin=$u['gambar']?:'default.png';
$r=$_SESSION['last_receipt']??null;
// Nota hanya ditampilkan sekali, lalu dihapus dari sesi (perilaku sama seperti sebelumnya)
unset($_SESSION['last_receipt']);

// ---- Metode pembayaran yang diproses dari pesanan_pembeli.php (disimpan beli.php di nota) ----
const NOMOR_BCA = '0128123888'; // Nomor rekening BCA
$METODE = [
  'cash' => ['label' => 'Cash',         'icon' => 'fa-money-bill-wave'],
  'qris' => ['label' => 'QRIS',         'icon' => 'fa-qrcode'],
  'bca'  => ['label' => 'Transfer BCA', 'icon' => 'fa-university'],
];
$metode = 'cash';
if ($r) {
  $m = strtolower((string)($r['metode'] ?? 'cash'));
  if (isset($METODE[$m])) $metode = $m;
}
$isCash = ($metode === 'cash');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Transaksi Selesai - Kasir Management System</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700&family=Nunito+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--navy:#0b2559;--deep:#081c44;--blue:#1d4ed8;--blue-dark:#1739a8;--sky:#38bdf8;--ice:#eef4ff;--ink:#14213d;--muted:#667799;--line:#d5def0;--ok:#067647;--ok-bg:#ecfdf3;--shadow:0 12px 32px rgba(2,10,40,.18)}
*{box-sizing:border-box;margin:0;padding:0}body{font-family:'Nunito Sans',sans-serif;color:var(--ink);background:var(--navy);min-height:100vh}.container{display:flex;min-height:100vh}

/* Sidebar */
nav{width:250px;position:fixed;inset:0 auto 0 0;background:var(--deep);padding:24px 12px;z-index:5;border-right:1px solid rgba(56,189,248,.18);overflow:auto}.navbar ul{list-style:none}.navbar li{padding:3px 0}.navbar a{display:flex;align-items:center;padding:12px 15px;border-radius:12px;color:#cfe0ff;text-decoration:none;font-weight:700;font-size:14px}.navbar a i{width:24px;margin-right:10px}.navbar a:hover,.navbar a.active{background:var(--blue);color:#fff}
.profile-container{display:flex;align-items:center;gap:12px;padding:12px;background:rgba(255,255,255,.07);border:1px solid rgba(56,189,248,.2);border-radius:14px;margin-bottom:14px}.admin-avatar{width:44px;height:44px;border-radius:50%;object-fit:cover;background:#fff}.admin-name{color:#fff;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block}

/* Konten */
.main{flex:1;margin-left:250px;padding:30px;min-width:0}
.page-head{display:flex;justify-content:space-between;align-items:flex-end;gap:14px;flex-wrap:wrap;margin-bottom:22px}
.page-head h1{font:700 26px Sora;color:#fff;display:flex;align-items:center;gap:12px}
.page-head p{color:#a9c1ee;font-size:14px;margin-top:6px}

.btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;border:0;border-radius:12px;padding:0 20px;height:48px;font:800 14.5px 'Nunito Sans';cursor:pointer;text-decoration:none}
.btn-primary{background:var(--blue);color:#fff;box-shadow:0 8px 18px rgba(29,78,216,.3)}.btn-primary:hover{background:var(--blue-dark)}
.btn-light{background:#eaf0fb;color:var(--navy)}.btn-light:hover{background:#dce6f8}
.btn-white{background:#fff;color:var(--navy)}

.receipt-wrap{max-width:520px;margin:0 auto}

/* Kartu nota */
.receipt{position:relative;background:#fff;border-radius:20px;box-shadow:var(--shadow);overflow:hidden}
.receipt-top{text-align:center;padding:34px 28px 26px;background:linear-gradient(180deg,var(--ok-bg),#fff)}
.check{width:84px;height:84px;margin:0 auto 16px;border-radius:50%;background:var(--ok);color:#fff;display:grid;place-items:center;font-size:36px;box-shadow:0 0 0 8px #abefc6}
.receipt-top h2{font:700 21px Sora;color:var(--navy);margin-bottom:6px}
.receipt-top p{color:var(--muted);font-size:14px}

.tear{position:relative;height:0;border-top:2px dashed var(--line);margin:0 22px}
.tear:before,.tear:after{content:'';position:absolute;top:-13px;width:24px;height:24px;border-radius:50%;background:var(--navy)}
.tear:before{left:-34px}.tear:after{right:-34px}

.receipt-body{padding:24px 28px}
.meta{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px}
.meta .lbl{font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px}
.meta .val{font-weight:800;color:var(--navy);overflow-wrap:anywhere}
.meta .val.mono{font-family:'Courier New',monospace;letter-spacing:.02em}
.method-chip{display:inline-flex;align-items:center;gap:7px;padding:4px 11px;border-radius:999px;background:var(--ice);color:var(--blue);font-weight:800;font-size:13.5px}

.rows{border-top:1px solid var(--line);padding-top:6px}
.row{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:11px 0;font-size:15px;color:var(--muted)}
.row b{color:var(--ink);font-weight:800}
.row.grand{margin-top:6px;padding:14px 16px;background:var(--ice);border-radius:12px}
.row.grand span{color:var(--navy);font-weight:800}.row.grand b{font:700 20px Sora;color:var(--navy)}
.row.change{margin-top:8px;padding:14px 16px;background:var(--ok-bg);border:1px solid #abefc6;border-radius:12px}
.row.change span{color:var(--ok);font-weight:800}.row.change b{font:700 20px Sora;color:var(--ok)}
.row.paid{margin-top:8px;padding:14px 16px;background:var(--ok-bg);border:1px solid #abefc6;border-radius:12px}
.row.paid span{color:var(--ok);font-weight:800}.row.paid b{color:var(--ok);font-weight:800}

.receipt-foot{padding:4px 28px 26px;text-align:center;color:var(--muted);font-size:13px}
.thanks{font-weight:800;color:var(--navy);margin-bottom:4px;font-size:14.5px}

.actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:20px}.actions .btn{flex:1;min-width:170px}

/* Kosong */
.empty-state{background:#fff;border-radius:20px;box-shadow:var(--shadow);text-align:center;padding:60px 24px;max-width:520px;margin:0 auto}
.empty-state .ic{width:92px;height:92px;margin:0 auto 18px;border-radius:50%;background:var(--ice);color:var(--blue);display:grid;place-items:center;font-size:36px;box-shadow:0 0 0 8px var(--sky)}
.empty-state h3{font:700 20px Sora;color:var(--navy);margin-bottom:8px}.empty-state p{color:var(--muted);margin-bottom:22px}

@media(max-width:850px){.container{display:block}nav{position:relative;width:100%;height:auto}.navbar ul{display:flex;flex-wrap:wrap}.profile-bar{width:100%}.main{margin-left:0;padding:18px}.tear:before{left:-22px}.tear:after{right:-22px}}
@media(max-width:480px){.meta{grid-template-columns:1fr}.page-head h1{font-size:21px}.receipt-body,.receipt-foot{padding-left:20px;padding-right:20px}}

/* Cetak: hanya nota */
@media print{
 body{background:#fff}
 nav,.page-head,.actions,.tear:before,.tear:after{display:none!important}
 .main{margin:0;padding:0}
 .receipt-wrap{max-width:100%}
 .receipt{box-shadow:none;border:1px solid #ddd;border-radius:0}
 .receipt-top{background:#fff}
 .check{box-shadow:none}
}
</style>
</head>
<body>
<div class="container">
<nav><div class="navbar"><ul>
<li class="profile-bar"><div class="profile-container"><img class="admin-avatar" src="../uploads/<?= e($gambarLogin) ?>" alt="Foto admin"><span class="admin-name"><?= e($username) ?></span></div></li>
<li><a href="../laporan/laporan.php"><i class="fas fa-chart-bar"></i>Laporan</a></li>
<li><a href="../category/categori.php"><i class="fas fa-tags"></i>Kategori</a></li>
<li><a href="../produk/Tproduk.php"><i class="fas fa-box"></i>Produk</a></li>
<li><a href="../admin/admin.php"><i class="fas fa-user-shield"></i>Admin</a></li>
<li><a class="active" href="transaksi.php"><i class="fas fa-receipt"></i>Transaksi</a></li>
<li><a href="../logout/Tlogout.php"><i class="fas fa-sign-out-alt"></i>Logout</a></li>
</ul></div></nav>

<main class="main">
<div class="page-head">
 <div>
  <h1><i class="fas fa-receipt"></i> Transaksi Selesai</h1>
  <p>Ringkasan transaksi dari database sarapan2</p>
 </div>
 <a class="btn btn-white" href="transaksi.php"><i class="fas fa-plus"></i> Transaksi Baru</a>
</div>

<?php if($r): ?>
<div class="receipt-wrap">
 <section class="receipt" id="nota">
  <div class="receipt-top">
   <div class="check"><i class="fas fa-check"></i></div>
   <h2>Pembayaran berhasil!</h2>
   <p>Transaksi telah tercatat di sistem</p>
  </div>

  <div class="tear"></div>

  <div class="receipt-body">
   <div class="meta">
    <div><div class="lbl">Nomor Nota</div><div class="val mono"><?= e($r['nota']) ?></div></div>
    <div><div class="lbl">Tanggal</div><div class="val"><?= e($r['tanggal']) ?></div></div>
    <div><div class="lbl">Kasir</div><div class="val"><?= e($username) ?></div></div>
    <div><div class="lbl">Metode Pembayaran</div><div class="val"><span class="method-chip"><i class="fas <?= e($METODE[$metode]['icon']) ?>"></i> <?= e($METODE[$metode]['label']) ?></span></div></div>
   </div>

   <div class="rows">
    <?php if($isCash): ?>
     <div class="row"><span>Bayar</span><b>Rp <?= number_format($r['bayar'],0,',','.') ?></b></div>
     <div class="row grand"><span>Total</span><b>Rp <?= number_format($r['total'],0,',','.') ?></b></div>
     <div class="row change"><span>Kembalian</span><b>Rp <?= number_format($r['kembalian'],0,',','.') ?></b></div>
    <?php else: ?>
     <?php if($metode==='bca'): ?><div class="row"><span>Rekening tujuan</span><b>BCA <?= e(NOMOR_BCA) ?></b></div><?php endif; ?>
     <div class="row grand"><span>Total</span><b>Rp <?= number_format($r['total'],0,',','.') ?></b></div>
     <div class="row paid"><span>Dibayar via <?= e($METODE[$metode]['label']) ?></span><b>Lunas</b></div>
    <?php endif; ?>
   </div>
  </div>

  <div class="receipt-foot">
   <div class="thanks">Terima kasih atas pembelian Anda</div>
   Simpan nota ini sebagai bukti transaksi.
  </div>
 </section>

 <div class="actions">
  <button class="btn btn-light" onclick="window.print()"><i class="fas fa-print"></i> Cetak Nota</button>
  <a class="btn btn-primary" href="transaksi.php"><i class="fas fa-plus-circle"></i> Transaksi Baru</a>
 </div>
</div>

<?php else: ?>
<div class="empty-state">
 <div class="ic"><i class="fas fa-file-invoice"></i></div>
 <h3>Belum ada transaksi terbaru</h3>
 <p>Nota akan muncul di sini setelah pembayaran berhasil diproses.</p>
 <a class="btn btn-primary" href="transaksi.php"><i class="fas fa-box-open"></i> Mulai Transaksi</a>
</div>
<?php endif; ?>
</main></div>
</body>
</html>