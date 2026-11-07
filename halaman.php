<?php
// Beranda / landing page publik Dikkun Kriuks
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/auth.php';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// ---------- Status login ----------
$isLogin  = !empty($_SESSION['username']);
$username = $isLogin ? (string)$_SESSION['username'] : '';
$namaUser = $isLogin ? (string)($_SESSION['nama_lengkap'] ?? $username) : '';
$roleUser = $isLogin ? Role::dariString($_SESSION['role'] ?? null) : null;
$gambarUser = 'default.png';
if ($isLogin) {
    $st = $conn->prepare('SELECT gambar FROM admin WHERE username = ? LIMIT 1');
    if ($st) {
        $st->bind_param('s', $username);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        if (!empty($r['gambar'])) $gambarUser = $r['gambar'];
        $st->close();
    }
}

// ---------- Data dari CRUD kategori & produk ----------
$kategori = [];
$q = $conn->query('SELECT k.id_kategori, k.nama_kategori, COUNT(p.id_produk) AS jumlah
                   FROM kategori k LEFT JOIN produk p ON p.id_kategori = k.id_kategori
                   GROUP BY k.id_kategori, k.nama_kategori
                   ORDER BY k.nama_kategori ASC');
if ($q) while ($row = $q->fetch_assoc()) $kategori[] = $row;

$produk = [];
$q = $conn->query('SELECT p.id_produk, p.id_kategori, p.nama_produk, p.harga, p.stok, p.foto, k.nama_kategori
                   FROM produk p LEFT JOIN kategori k ON p.id_kategori = k.id_kategori
                   ORDER BY p.id_produk DESC LIMIT 48');
if ($q) while ($row = $q->fetch_assoc()) $produk[] = $row;

$totalProduk   = (int)($conn->query('SELECT COUNT(*) c FROM produk')->fetch_assoc()['c'] ?? 0);
$totalKategori = count($kategori);
$totalStok     = (int)($conn->query('SELECT COALESCE(SUM(stok),0) c FROM produk')->fetch_assoc()['c'] ?? 0);

// Foto produk untuk hiasan hero (maks 3)
$heroFoto = [];
foreach ($produk as $p) {
    if (!empty($p['foto']) && is_file(__DIR__ . '/produk/gambar/' . $p['foto'])) $heroFoto[] = $p;
    if (count($heroFoto) >= 3) break;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dikkun Kriuks - Camilan Renyah, Dikelola Rapi</title>
<meta name="description" content="Dikkun Kriuks menyediakan beragam camilan pilihan dengan sistem kasir modern.">
<link rel="icon" href="img/kun.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Nunito+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--navy:#0b2559;--deep:#081c44;--blue:#1d4ed8;--blue-dark:#1739a8;--sky:#38bdf8;--ice:#eef4ff;--ink:#14213d;--muted:#667799;--line:#d5def0;--amber:#f59e0b}
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth;scroll-padding-top:84px}
body{font-family:'Nunito Sans',Arial,sans-serif;color:var(--ink);background:#fff;line-height:1.6;overflow-x:hidden}
img{max-width:100%;display:block}
a{color:inherit}
.wrap{width:min(1160px,100% - 40px);margin-inline:auto}
h1,h2,h3{font-family:'Sora',sans-serif;letter-spacing:-.02em;line-height:1.2}
:focus-visible{outline:3px solid var(--sky);outline-offset:3px;border-radius:6px}

/* ---------- Header ---------- */
.site-header{position:sticky;top:0;z-index:50;background:rgba(8,28,68,.92);backdrop-filter:blur(12px);border-bottom:1px solid rgba(255,255,255,.08)}
.nav{display:flex;align-items:center;gap:24px;height:72px}
.brand{display:flex;align-items:center;gap:12px;text-decoration:none;color:#fff}
.brand .logo{width:46px;height:46px;border-radius:50%;background:#fff;padding:5px;box-shadow:0 0 0 3px var(--sky);flex:none}
.brand .logo img{width:100%;height:100%;object-fit:contain}
.brand b{font:800 18px 'Sora',sans-serif;letter-spacing:-.02em;display:block;line-height:1.1}
.brand small{font-size:11.5px;color:#9fc3ff;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
.menu{display:flex;gap:4px;margin-left:auto;list-style:none}
.menu a{display:block;padding:9px 15px;border-radius:10px;color:#cfe0ff;text-decoration:none;font-weight:700;font-size:15px;transition:background .2s,color .2s}
.menu a:hover{background:rgba(255,255,255,.1);color:#fff}
.auth{display:flex;align-items:center;gap:10px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;height:44px;padding:0 20px;border-radius:12px;border:0;font:800 14.5px 'Nunito Sans',sans-serif;text-decoration:none;cursor:pointer;transition:background .2s,transform .15s,box-shadow .2s;white-space:nowrap}
.btn:active{transform:translateY(1px)}
.btn-primary{background:var(--blue);color:#fff;box-shadow:0 8px 18px rgba(29,78,216,.38)}.btn-primary:hover{background:var(--blue-dark)}
.btn-sky{background:var(--sky);color:var(--deep)}.btn-sky:hover{background:#7dd3fc}
.btn-outline{background:transparent;color:#fff;box-shadow:inset 0 0 0 1.5px rgba(255,255,255,.35)}.btn-outline:hover{background:rgba(255,255,255,.1)}
.btn-light{background:var(--ice);color:var(--navy)}.btn-light:hover{background:#e0eaff}
.user{display:flex;align-items:center;gap:10px;padding:5px 14px 5px 5px;border-radius:999px;background:rgba(255,255,255,.1);color:#fff;text-decoration:none}
.user img{width:36px;height:36px;border-radius:50%;object-fit:cover;background:#fff}
.user span{font-weight:800;font-size:14px;line-height:1.15}.user small{display:block;color:#9fc3ff;font-size:11.5px;font-weight:700}
.burger{display:none;margin-left:auto;width:44px;height:44px;border-radius:12px;border:0;background:rgba(255,255,255,.1);color:#fff;font-size:18px;cursor:pointer}

/* ---------- Hero ---------- */
.hero{position:relative;background:var(--navy);color:#fff;overflow:hidden;padding:84px 0 110px}
.hero::before{content:"";position:absolute;width:760px;height:760px;top:-320px;right:-220px;border-radius:50%;border:96px solid rgba(56,189,248,.13)}
.hero::after{content:"";position:absolute;width:480px;height:480px;bottom:-240px;left:-160px;border-radius:50%;background:var(--blue);opacity:.5}
.hero-grid{position:relative;z-index:1;display:grid;grid-template-columns:1.1fr .9fr;gap:56px;align-items:center}
.eyebrow{display:inline-flex;align-items:center;gap:8px;padding:7px 15px;border-radius:999px;background:rgba(56,189,248,.16);color:#7dd3fc;font-weight:800;font-size:13px;margin-bottom:22px}
.hero h1{font-size:clamp(2.2rem,5vw,3.7rem);font-weight:800;margin-bottom:20px}
.hero h1 em{font-style:normal;color:var(--sky)}
.hero p.lead{font-size:18px;color:#c3d5f5;max-width:520px;margin-bottom:32px}
.hero-cta{display:flex;gap:12px;flex-wrap:wrap}
.hero-cta .btn{height:52px;padding:0 26px;font-size:15.5px}
.hero-visual{position:relative;min-height:380px;display:grid;place-items:center}
.disc{width:290px;height:290px;border-radius:50%;background:#fff;display:grid;place-items:center;padding:42px;box-shadow:0 0 0 14px var(--sky),0 40px 80px rgba(2,10,40,.5)}
.disc img{width:100%;height:100%;object-fit:contain}
.float{position:absolute;display:flex;align-items:center;gap:10px;padding:8px 16px 8px 8px;border-radius:16px;background:#fff;color:var(--ink);box-shadow:0 18px 40px rgba(2,10,40,.35);font-weight:800;font-size:13.5px;animation:bob 5s ease-in-out infinite}
.float img,.float .ph{width:46px;height:46px;border-radius:12px;object-fit:cover;background:var(--ice);display:grid;place-items:center;color:var(--blue)}
.float small{display:block;color:var(--muted);font-weight:700;font-size:12px}
.f1{top:6%;left:-2%}.f2{bottom:8%;right:-4%;animation-delay:-1.6s}.f3{bottom:30%;left:-10%;animation-delay:-3.2s}
@keyframes bob{50%{transform:translateY(-10px)}}

/* ---------- Statistik ---------- */
.stats{position:relative;z-index:2;margin-top:-58px}
.stats-grid{display:grid;grid-template-columns:repeat(3,1fr);background:#fff;border-radius:22px;box-shadow:0 24px 60px rgba(11,37,89,.18);overflow:hidden}
.stat{display:flex;align-items:center;gap:18px;padding:28px 32px}
.stat + .stat{border-left:1px solid var(--line)}
.stat .ic{width:56px;height:56px;border-radius:16px;background:var(--ice);color:var(--blue);display:grid;place-items:center;font-size:22px;flex:none}
.stat b{display:block;font:800 30px 'Sora',sans-serif;color:var(--navy);line-height:1.1}
.stat span{color:var(--muted);font-weight:700;font-size:14px}

/* ---------- Section umum ---------- */
section{padding:92px 0}
.kicker{display:block;color:var(--blue);font-weight:800;font-size:13px;letter-spacing:.14em;text-transform:uppercase;margin-bottom:10px}
.sec-head{max-width:640px;margin-bottom:46px}
.sec-head.center{text-align:center;margin-inline:auto}
.sec-head h2{font-size:clamp(1.7rem,3.4vw,2.5rem);color:var(--navy);margin-bottom:14px}
.sec-head p{color:var(--muted);font-size:17px}

/* ---------- Tentang ---------- */
.about{display:grid;grid-template-columns:1fr 1fr;gap:64px;align-items:center}
.about-art{position:relative;aspect-ratio:1/1;max-width:460px;margin-inline:auto;width:100%}
.about-art .ring{position:absolute;inset:0;border-radius:50%;background:var(--navy)}
.about-art .ring::after{content:"";position:absolute;inset:-18px;border-radius:50%;border:2px dashed #b6c8ee}
.about-art .logo-big{position:absolute;inset:17%;border-radius:50%;background:#fff;display:grid;place-items:center;padding:12%;box-shadow:0 0 0 10px var(--sky)}
.about-art .logo-big img{width:100%;height:100%;object-fit:contain}
.about-art .badge{position:absolute;right:-4%;bottom:10%;background:var(--amber);color:#3b2400;border-radius:18px;padding:14px 20px;font-weight:800;box-shadow:0 16px 30px rgba(245,158,11,.4)}
.about-art .badge b{display:block;font:800 26px 'Sora',sans-serif;line-height:1}
.about-text h2{font-size:clamp(1.7rem,3.2vw,2.4rem);color:var(--navy);margin-bottom:18px}
.about-text p{color:#41527a;font-size:16.5px;margin-bottom:16px}
.checks{list-style:none;display:grid;gap:12px;margin:24px 0 0}
.checks li{display:flex;gap:12px;font-weight:700;color:var(--ink)}
.checks i{flex:none;width:26px;height:26px;border-radius:50%;background:var(--ice);color:var(--blue);display:grid;place-items:center;font-size:12px;margin-top:1px}

/* ---------- Keunggulan ---------- */
.values{background:var(--ice)}
.cards3{display:grid;grid-template-columns:repeat(3,1fr);gap:22px}
.vcard{background:#fff;border-radius:20px;padding:32px 28px;border:1px solid var(--line);transition:transform .25s,box-shadow .25s}
.vcard:hover{transform:translateY(-6px);box-shadow:0 24px 50px rgba(11,37,89,.14)}
.vcard .ic{width:58px;height:58px;border-radius:16px;background:var(--navy);color:var(--sky);display:grid;place-items:center;font-size:23px;margin-bottom:20px}
.vcard h3{font-size:19px;color:var(--navy);margin-bottom:10px}
.vcard p{color:var(--muted);font-size:15.5px}

/* ---------- Produk ---------- */
.tabs{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:30px}
.tab{height:42px;padding:0 18px;border-radius:999px;border:1.5px solid var(--line);background:#fff;color:var(--navy);font:800 14px 'Nunito Sans',sans-serif;cursor:pointer;transition:all .2s}
.tab:hover{border-color:var(--blue);color:var(--blue)}
.tab.active{background:var(--blue);border-color:var(--blue);color:#fff;box-shadow:0 8px 18px rgba(29,78,216,.3)}
.tab em{font-style:normal;opacity:.7;margin-left:4px}
.pgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:22px}
.pcard{background:#fff;border:1px solid var(--line);border-radius:20px;overflow:hidden;display:flex;flex-direction:column;transition:transform .25s,box-shadow .25s}
.pcard:hover{transform:translateY(-6px);box-shadow:0 24px 50px rgba(11,37,89,.16)}
.pimg{position:relative;aspect-ratio:4/3;background:var(--ice);display:grid;place-items:center;color:#9db4e6;font-size:44px;overflow:hidden}
.pimg img{width:100%;height:100%;object-fit:cover;transition:transform .4s}
.pcard:hover .pimg img{transform:scale(1.06)}
.pcat{position:absolute;top:12px;left:12px;padding:5px 12px;border-radius:999px;background:rgba(8,28,68,.85);color:#fff;font-size:12px;font-weight:800;backdrop-filter:blur(4px)}
.pbody{padding:18px 20px 20px;display:flex;flex-direction:column;gap:6px;flex:1}
.pbody h3{font-size:17px;color:var(--navy);overflow-wrap:anywhere}
.price{font:800 20px 'Sora',sans-serif;color:var(--blue)}
.stock{font-size:13px;font-weight:800;display:inline-flex;align-items:center;gap:7px;color:#15803d}
.stock::before{content:"";width:8px;height:8px;border-radius:50%;background:currentColor}
.stock.low{color:#b45309}.stock.out{color:#be123c}
.pfoot{margin-top:auto;padding-top:12px}
.pfoot .btn{width:100%;height:42px}
.empty{text-align:center;padding:60px 20px;border:2px dashed var(--line);border-radius:22px;color:var(--muted)}
.empty i{font-size:46px;color:#9db4e6;margin-bottom:14px}
.empty h3{color:var(--navy);margin-bottom:6px}
.more{text-align:center;margin-top:36px;color:var(--muted);font-weight:700}

/* ---------- CTA ---------- */
.cta{padding:0 0 92px}
.cta-box{position:relative;overflow:hidden;background:var(--navy);color:#fff;border-radius:28px;padding:64px 48px;text-align:center}
.cta-box::before{content:"";position:absolute;width:420px;height:420px;right:-120px;top:-200px;border-radius:50%;border:60px solid rgba(56,189,248,.15)}
.cta-box::after{content:"";position:absolute;width:300px;height:300px;left:-100px;bottom:-160px;border-radius:50%;background:var(--blue);opacity:.55}
.cta-box > *{position:relative;z-index:1}
.cta-box h2{font-size:clamp(1.6rem,3.2vw,2.3rem);margin-bottom:12px}
.cta-box p{color:#c3d5f5;max-width:540px;margin:0 auto 28px;font-size:17px}
.cta-box .hero-cta{justify-content:center}

/* ---------- Footer ---------- */
footer{background:var(--deep);color:#a9bde6;padding:60px 0 0}
.fgrid{display:grid;grid-template-columns:1.4fr 1fr 1fr;gap:44px;padding-bottom:44px}
footer .brand{margin-bottom:16px}
footer p{font-size:15px;max-width:360px}
footer h4{font:800 14px 'Sora',sans-serif;color:#fff;letter-spacing:.04em;margin-bottom:16px}
footer ul{list-style:none;display:grid;gap:10px}
footer li a{text-decoration:none;font-weight:600;transition:color .2s}
footer li a:hover{color:var(--sky)}
.copy{border-top:1px solid rgba(255,255,255,.1);padding:20px 0;text-align:center;font-size:13.5px}

/* ---------- Responsif ---------- */
@media(max-width:960px){
  .burger{display:grid;place-items:center}
  .nav{flex-wrap:wrap;height:auto;min-height:72px;padding:10px 0;gap:0}
  .menu,.auth{display:none;width:100%}
  .nav.open .menu{display:grid;gap:2px;margin:12px 0 4px}
  .nav.open .auth{display:flex;padding:8px 0 10px}
  .nav.open .auth .btn,.nav.open .auth .user{flex:1;justify-content:center}
  .hero{padding:56px 0 100px}
  .hero-grid,.about{grid-template-columns:1fr;gap:44px}
  .hero-visual{min-height:340px}.f1{left:2%}.f2{right:0}.f3{left:0}
  .cards3{grid-template-columns:1fr}
  .stats-grid{grid-template-columns:1fr}.stat + .stat{border-left:0;border-top:1px solid var(--line)}
  section{padding:68px 0}
  .fgrid{grid-template-columns:1fr 1fr}.fgrid > div:first-child{grid-column:1/-1}
}
@media(max-width:520px){
  .disc{width:230px;height:230px}.float{font-size:12px}.f3{display:none}
  .cta-box{padding:46px 22px}
  .fgrid{grid-template-columns:1fr}
}
@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}*{animation:none!important;transition:none!important}}
</style>
</head>
<body>

<!-- ================= HEADER ================= -->
<header class="site-header">
  <div class="wrap">
    <div class="nav" id="nav">
      <a class="brand" href="halaman.php" aria-label="Dikkun Kriuks - Beranda">
        <span class="logo"><img src="img/kun.png" alt="Logo Dikkun Kriuks"></span>
        <span><b>Dikkun Kriuks</b><small>Kasir Management</small></span>
      </a>

      <ul class="menu">
        <li><a href="#beranda">Beranda</a></li>
        <li><a href="#tentang">Tentang</a></li>
        <li><a href="#keunggulan">Keunggulan</a></li>
        <li><a href="#produk">Produk</a></li>
      </ul>

      <div class="auth">
        <?php if ($isLogin): ?>
          <a class="user" href="laporan/laporan.php" title="Buka dashboard">
            <img src="uploads/<?= e($gambarUser) ?>" alt="Foto profil" onerror="this.src='uploads/default.png'">
            <span><?= e($namaUser) ?><small><?= e($roleUser ? Role::label($roleUser) : 'Pengguna') ?></small></span>
          </a>
          <a class="btn btn-sky" href="laporan/laporan.php"><i class="fas fa-gauge-high"></i> Dashboard</a>
          <a class="btn btn-outline" href="logout/Tlogout.php"><i class="fas fa-right-from-bracket"></i> Keluar</a>
        <?php else: ?>
          <a class="btn btn-outline" href="login.php"><i class="fas fa-right-to-bracket"></i> Masuk</a>
          <a class="btn btn-sky" href="tambah-admin.php"><i class="fas fa-user-plus"></i> Buat Akun</a>
        <?php endif; ?>
      </div>

      <button class="burger" id="burger" aria-label="Buka menu" aria-expanded="false"><i class="fas fa-bars"></i></button>
    </div>
  </div>
</header>

<main>
<!-- ================= HERO ================= -->
<section class="hero" id="beranda" style="padding-bottom:110px">
  <div class="wrap hero-grid">
    <div>
      <span class="eyebrow"><i class="fas fa-star"></i> Camilan pilihan, transaksi rapi</span>
      <h1>Camilan <em>renyah</em> untuk setiap momen.</h1>
      <p class="lead">Dikkun Kriuks menghadirkan aneka camilan berkualitas yang dikelola dengan sistem kasir modern, supaya setiap pesanan tercatat cepat, akurat, dan transparan.</p>
      <div class="hero-cta">
        <a class="btn btn-sky" href="#produk"><i class="fas fa-bag-shopping"></i> Lihat Produk</a>
        <?php if ($isLogin): ?>
          <a class="btn btn-outline" href="checkout/transaksi.php"><i class="fas fa-cash-register"></i> Buka Kasir</a>
        <?php else: ?>
          <a class="btn btn-outline" href="login.php"><i class="fas fa-right-to-bracket"></i> Masuk ke Kasir</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="hero-visual" aria-hidden="true">
      <div class="disc"><img src="img/kun.png" alt=""></div>
      <?php foreach ($heroFoto as $i => $p): ?>
        <div class="float f<?= $i + 1 ?>">
          <img src="produk/gambar/<?= rawurlencode($p['foto']) ?>" alt="">
          <span><?= e(mb_strimwidth($p['nama_produk'], 0, 18, '…')) ?><small>Rp <?= number_format((int)$p['harga'], 0, ',', '.') ?></small></span>
        </div>
      <?php endforeach; ?>
      <?php if (!$heroFoto): ?>
        <div class="float f1"><span class="ph"><i class="fas fa-bolt"></i></span><span>Transaksi cepat<small>Kasir real-time</small></span></div>
        <div class="float f2"><span class="ph"><i class="fas fa-shield-halved"></i></span><span>Data aman<small>Akses berbasis role</small></span></div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ================= STATISTIK ================= -->
<div class="stats">
  <div class="wrap">
    <div class="stats-grid">
      <div class="stat"><span class="ic"><i class="fas fa-box-open"></i></span><div><b><?= number_format($totalProduk, 0, ',', '.') ?></b><span>Varian produk</span></div></div>
      <div class="stat"><span class="ic"><i class="fas fa-tags"></i></span><div><b><?= number_format($totalKategori, 0, ',', '.') ?></b><span>Kategori tersedia</span></div></div>
      <div class="stat"><span class="ic"><i class="fas fa-warehouse"></i></span><div><b><?= number_format($totalStok, 0, ',', '.') ?></b><span>Total stok siap jual</span></div></div>
    </div>
  </div>
</div>

<!-- ================= TENTANG ================= -->
<section id="tentang">
  <div class="wrap about">
    <div class="about-art" aria-hidden="true">
      <div class="ring"></div>
      <div class="logo-big"><img src="img/kun.png" alt=""></div>
      <div class="badge"><b><?= number_format($totalProduk, 0, ',', '.') ?>+</b>produk pilihan</div>
    </div>
    <div class="about-text">
      <span class="kicker">Tentang Perusahaan</span>
      <h2>Dikkun Kriuks, camilan yang dikelola dengan serius.</h2>
      <p>Kami adalah usaha camilan yang percaya bahwa rasa yang enak harus didukung pengelolaan yang baik. Mulai dari pemilihan produk, pengaturan stok, hingga pencatatan transaksi, semuanya berjalan lewat satu sistem kasir terpadu.</p>
      <p>Dengan data penjualan yang tercatat otomatis, kami dapat menjaga ketersediaan produk favorit pelanggan dan melayani pesanan lebih cepat setiap hari.</p>
      <ul class="checks">
        <li><i class="fas fa-check"></i> Produk dikelompokkan per kategori agar mudah dipilih</li>
        <li><i class="fas fa-check"></i> Pembayaran fleksibel: Cash, QRIS, dan Transfer BCA</li>
        <li><i class="fas fa-check"></i> Laporan penjualan harian hingga tahunan untuk pengambilan keputusan</li>
      </ul>
    </div>
  </div>
</section>

<!-- ================= KEUNGGULAN ================= -->
<section class="values" id="keunggulan">
  <div class="wrap">
    <div class="sec-head center">
      <span class="kicker">Mengapa Kami</span>
      <h2>Layanan yang cepat, jelas, dan terpercaya</h2>
      <p>Tiga hal yang kami jaga di setiap transaksi.</p>
    </div>
    <div class="cards3">
      <article class="vcard"><div class="ic"><i class="fas fa-bolt"></i></div><h3>Transaksi Cepat</h3><p>Keranjang, pembayaran, dan nota dibuat dalam hitungan detik sehingga antrean tidak menumpuk.</p></article>
      <article class="vcard"><div class="ic"><i class="fas fa-chart-line"></i></div><h3>Laporan Akurat</h3><p>Penjualan tercatat otomatis dan tersaji dalam grafik, memudahkan pemantauan performa usaha.</p></article>
      <article class="vcard"><div class="ic"><i class="fas fa-user-shield"></i></div><h3>Akses Terkontrol</h3><p>Hak akses dibedakan antara Admin dan Kasir, sehingga data penting hanya dikelola pihak berwenang.</p></article>
    </div>
  </div>
</section>

<!-- ================= PRODUK (dari CRUD produk) ================= -->
<section id="produk">
  <div class="wrap">
    <div class="sec-head">
      <span class="kicker">Produk Kami</span>
      <h2>Pilihan produk berdasarkan kategori</h2>
      <p>Daftar di bawah ini selalu mengikuti data produk terbaru yang dikelola tim kami.</p>
    </div>

    <?php if ($produk): ?>
      <div class="tabs" role="tablist" aria-label="Filter kategori">
        <button class="tab active" role="tab" data-cat="all" aria-selected="true">Semua <em>(<?= count($produk) ?>)</em></button>
        <?php foreach ($kategori as $k): if ((int)$k['jumlah'] === 0) continue; ?>
          <button class="tab" role="tab" data-cat="<?= (int)$k['id_kategori'] ?>" aria-selected="false"><?= e($k['nama_kategori']) ?> <em>(<?= (int)$k['jumlah'] ?>)</em></button>
        <?php endforeach; ?>
      </div>

      <div class="pgrid" id="pgrid">
        <?php foreach ($produk as $p):
            $stok = (int)$p['stok'];
            $kelas = $stok <= 0 ? 'out' : ($stok <= 5 ? 'low' : '');
            $label = $stok <= 0 ? 'Stok habis' : ($stok <= 5 ? "Sisa $stok" : "Tersedia ($stok)");
            $ada = !empty($p['foto']) && is_file(__DIR__ . '/produk/gambar/' . $p['foto']);
        ?>
        <article class="pcard" data-cat="<?= (int)$p['id_kategori'] ?>">
          <div class="pimg">
            <?php if ($ada): ?><img loading="lazy" src="produk/gambar/<?= rawurlencode($p['foto']) ?>" alt="<?= e($p['nama_produk']) ?>"><?php else: ?><i class="fas fa-image"></i><?php endif; ?>
            <span class="pcat"><?= e($p['nama_kategori'] ?? 'Umum') ?></span>
          </div>
          <div class="pbody">
            <h3><?= e($p['nama_produk']) ?></h3>
            <div class="price">Rp <?= number_format((int)$p['harga'], 0, ',', '.') ?></div>
            <span class="stock <?= $kelas ?>"><?= e($label) ?></span>
            <div class="pfoot">
              <?php if ($isLogin): ?>
                <a class="btn btn-light" href="checkout/transaksi.php"><i class="fas fa-cash-register"></i> Proses di Kasir</a>
              <?php else: ?>
                <a class="btn btn-light" href="login.php"><i class="fas fa-lock"></i> Masuk untuk memesan</a>
              <?php endif; ?>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
      <?php if ($totalProduk > count($produk)): ?>
        <p class="more">Menampilkan <?= count($produk) ?> dari <?= $totalProduk ?> produk terbaru.</p>
      <?php endif; ?>
    <?php else: ?>
      <div class="empty">
        <i class="fas fa-box-open"></i>
        <h3>Produk belum tersedia</h3>
        <p>Produk akan tampil di sini setelah ditambahkan melalui halaman kelola produk.</p>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- ================= CTA ================= -->
<div class="cta">
  <div class="wrap">
    <div class="cta-box">
      <h2><?= $isLogin ? 'Selamat datang kembali, ' . e($namaUser) . '!' : 'Siap mengelola penjualan dengan lebih mudah?' ?></h2>
      <p><?= $isLogin ? 'Lanjutkan ke dashboard untuk melihat laporan dan memproses transaksi.' : 'Masuk dengan akun Anda atau buat akun baru untuk mulai memakai sistem kasir Dikkun Kriuks.' ?></p>
      <div class="hero-cta">
        <?php if ($isLogin): ?>
          <a class="btn btn-sky" href="laporan/laporan.php"><i class="fas fa-gauge-high"></i> Ke Dashboard</a>
        <?php else: ?>
          <a class="btn btn-sky" href="tambah-admin.php"><i class="fas fa-user-plus"></i> Buat Akun</a>
          <a class="btn btn-outline" href="login.php"><i class="fas fa-right-to-bracket"></i> Masuk</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</main>

<!-- ================= FOOTER ================= -->
<footer>
  <div class="wrap">
    <div class="fgrid">
      <div>
        <a class="brand" href="halaman.php"><span class="logo"><img src="img/kun.png" alt="Logo Dikkun Kriuks"></span><span><b>Dikkun Kriuks</b><small>Kasir Management</small></span></a>
        <p>Camilan renyah dengan pengelolaan kasir yang rapi, cepat, dan transparan.</p>
      </div>
      <div>
        <h4>Navigasi</h4>
        <ul>
          <li><a href="#beranda">Beranda</a></li>
          <li><a href="#tentang">Tentang</a></li>
          <li><a href="#keunggulan">Keunggulan</a></li>
          <li><a href="#produk">Produk</a></li>
        </ul>
      </div>
      <div>
        <h4>Akun</h4>
        <ul>
          <?php if ($isLogin): ?>
            <li><a href="laporan/laporan.php">Dashboard</a></li>
            <li><a href="logout/Tlogout.php">Keluar</a></li>
          <?php else: ?>
            <li><a href="login.php">Masuk</a></li>
            <li><a href="tambah-admin.php">Buat Akun</a></li>
            <li><a href="login/forgetpass.php">Lupa Password</a></li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
    <div class="copy">&copy; <?= date('Y') ?> Database kasir Dikkun Kriuks. Semua hak dilindungi.</div>
  </div>
</footer>

<script>
(function () {
  // Menu mobile
  var nav = document.getElementById('nav'), burger = document.getElementById('burger');
  burger.addEventListener('click', function () {
    var open = nav.classList.toggle('open');
    burger.setAttribute('aria-expanded', open);
    burger.querySelector('i').className = open ? 'fas fa-xmark' : 'fas fa-bars';
  });
  nav.querySelectorAll('.menu a').forEach(function (a) {
    a.addEventListener('click', function () { nav.classList.remove('open'); });
  });

  // Filter produk berdasarkan kategori
  var tabs = document.querySelectorAll('.tab'), cards = document.querySelectorAll('.pcard');
  tabs.forEach(function (t) {
    t.addEventListener('click', function () {
      tabs.forEach(function (x) { x.classList.remove('active'); x.setAttribute('aria-selected', 'false'); });
      t.classList.add('active'); t.setAttribute('aria-selected', 'true');
      var c = t.dataset.cat;
      cards.forEach(function (card) { card.style.display = (c === 'all' || card.dataset.cat === c) ? '' : 'none'; });
    });
  });
})();
</script>
</body>
</html>