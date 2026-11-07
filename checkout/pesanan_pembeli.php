<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../koneksi.php';
if (!isset($_SESSION['username']) || $_SESSION['username'] === '') { header('Location: ../login.php'); exit; }
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$username=(string)$_SESSION['username'];$gambarLogin='default.png';$idAdmin=null;
$stmt=$conn->prepare('SELECT id_admin,gambar,role FROM admin WHERE username=? LIMIT 1');$stmt->bind_param('s',$username);$stmt->execute();$u=$stmt->get_result()->fetch_assoc();$stmt->close();
if(!$u){session_destroy();header('Location: ../login.php');exit;}$idAdmin=(int)$u['id_admin'];$gambarLogin=$u['gambar']?:'default.png';
$isAdmin = (($u['role'] ?? '') === 'admin');

// ---- Pengaturan metode pembayaran ----
const NOMOR_BCA = '0128123888'; // Nomor rekening BCA
const MIN_BCA = 10000;          // Minimal belanja untuk pembayaran via BCA
$METODE_VALID = ['cash','qris','bca'];
$qrisDir = __DIR__ . '/../uploads/qris/';

$cart=$_SESSION['cart']??[];if(!is_array($cart))$cart=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
 $action=$_POST['action']??'';$pid=filter_input(INPUT_POST,'product_id',FILTER_VALIDATE_INT);

 // Kelola foto QRIS (hanya admin)
 if($action==='upload_qris' || $action==='hapus_qris'){
  $ok=false;
  if(!$isAdmin){ $msg='Hanya admin yang dapat mengelola foto QRIS.'; }
  elseif($action==='hapus_qris'){
   foreach(glob($qrisDir.'qris.*') ?: [] as $old) @unlink($old);
   $ok=true; $msg='Foto QRIS berhasil dihapus.';
  } else {
   $f=$_FILES['qris']??null;
   $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
   if(!$f || $f['error']!==UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) $msg='Pilih foto QRIS terlebih dahulu.';
   else {
    $mime=mime_content_type($f['tmp_name']);
    if($f['size']>5*1024*1024 || !isset($allowed[$mime])) $msg='Foto QRIS harus JPG, PNG, atau WEBP dan maksimal 5 MB.';
    else {
     if(!is_dir($qrisDir)) mkdir($qrisDir,0755,true);
     foreach(glob($qrisDir.'qris.*') ?: [] as $old) @unlink($old);
     if(move_uploaded_file($f['tmp_name'],$qrisDir.'qris.'.$allowed[$mime])){ $ok=true; $msg='Foto QRIS berhasil diperbarui.'; }
     else $msg='Gagal mengunggah foto QRIS.';
    }
   }
  }
  $_SESSION['flash_qris']=['ok'=>$ok,'msg'=>$msg];
  header('Location: pesanan_pembeli.php?metode=qris');exit;
 }

 if($action==='clear'){unset($_SESSION['cart']);header('Location: pesanan_pembeli.php');exit;}
 if($pid && isset($cart[$pid])){
  if($action==='remove')unset($cart[$pid]);
  elseif($action==='minus'){if($cart[$pid]>1)$cart[$pid]--;else unset($cart[$pid]);}
  elseif($action==='plus'){$cart[$pid]++;}
  elseif($action==='set'){$q=filter_input(INPUT_POST,'quantity',FILTER_VALIDATE_INT);if($q&&$q>0)$cart[$pid]=$q;else unset($cart[$pid]);}
  $_SESSION['cart']=$cart;
 }
 header('Location: pesanan_pembeli.php');exit;
}
$items=[];$total=0;
if($cart){$ids=array_filter(array_map('intval',array_keys($cart)));$in=implode(',', $ids);$res=$conn->query("SELECT id_produk,nama_produk,harga,stok,foto FROM produk WHERE id_produk IN ($in)");while($p=$res->fetch_assoc()){$qty=max(1,(int)$cart[$p['id_produk']]);$sub=(int)$p['harga']*$qty;$p['qty']=$qty;$p['subtotal']=$sub;$items[]=$p;$total+=$sub;}}

// ---- Hanya untuk tampilan (tidak mengubah data keranjang) ----
$totalQty = array_sum(array_column($items, 'qty'));
$adaMelebihiStok = false;
foreach ($items as $it) { if ((int)$it['qty'] > (int)$it['stok']) { $adaMelebihiStok = true; break; } }

// Pesan "uang kurang" dari server: beli.php cukup redirect ke
// pesanan_pembeli.php?kurang=1&bayar=NOMINAL jika pembayaran < total.
$kurangServer = isset($_GET['kurang']);
$bayarGet = filter_input(INPUT_GET, 'bayar', FILTER_VALIDATE_INT);
if ($bayarGet === false || $bayarGet === null || $bayarGet < 0) $bayarGet = null;
$selisihServer = ($kurangServer && $bayarGet !== null) ? max(0, $total - $bayarGet) : 0;

// Metode terpilih awal & foto QRIS
$metodeAwal = $_GET['metode'] ?? 'cash';
if (!in_array($metodeAwal, $METODE_VALID, true) || $kurangServer) $metodeAwal = $kurangServer ? 'cash' : $metodeAwal;
if (!in_array($metodeAwal, $METODE_VALID, true)) $metodeAwal = 'cash';

$qrisUrl = null;
$qrisFiles = glob($qrisDir.'qris.*') ?: [];
if ($qrisFiles) { $qf = basename($qrisFiles[0]); $qrisUrl = '../uploads/qris/'.$qf.'?v='.(int)@filemtime($qrisDir.$qf); }
$flashQris = $_SESSION['flash_qris'] ?? null; unset($_SESSION['flash_qris']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Keranjang - Kasir Management System</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700&family=Nunito+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--navy:#0b2559;--deep:#081c44;--blue:#1d4ed8;--blue-dark:#1739a8;--sky:#38bdf8;--ice:#eef4ff;--ink:#14213d;--muted:#667799;--line:#d5def0;--danger:#b42318;--shadow:0 12px 32px rgba(2,10,40,.18)}
*{box-sizing:border-box;margin:0;padding:0}body{font-family:'Nunito Sans',sans-serif;color:var(--ink);background:var(--navy);min-height:100vh}.container{display:flex;min-height:100vh}

/* Sidebar (dipertahankan) */
nav{width:250px;position:fixed;inset:0 auto 0 0;background:var(--deep);padding:24px 12px;z-index:5;border-right:1px solid rgba(56,189,248,.18);overflow:auto}.navbar ul{list-style:none}.navbar li{padding:3px 0}.navbar a{display:flex;align-items:center;padding:12px 15px;border-radius:12px;color:#cfe0ff;text-decoration:none;font-weight:700;font-size:14px}.navbar a i{width:24px;margin-right:10px}.navbar a:hover,.navbar a.active{background:var(--blue);color:#fff}
.profile-container{display:flex;align-items:center;gap:12px;padding:12px;background:rgba(255,255,255,.07);border:1px solid rgba(56,189,248,.2);border-radius:14px;margin-bottom:14px}.admin-avatar{width:44px;height:44px;border-radius:50%;object-fit:cover;background:#fff}.admin-name{color:#fff;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block}

/* Konten */
.main{flex:1;margin-left:250px;padding:30px;min-width:0}
.page-head{display:flex;justify-content:space-between;align-items:flex-end;gap:14px;flex-wrap:wrap;margin-bottom:22px}
.page-head h1{font:700 26px Sora;color:#fff;display:flex;align-items:center;gap:12px}
.page-head h1 .count{font:700 13px 'Nunito Sans';background:var(--sky);color:var(--deep);padding:4px 11px;border-radius:999px}
.page-head p{color:#a9c1ee;font-size:14px;margin-top:6px}
.back{color:#cfe0ff;text-decoration:none;font-weight:700;font-size:14px;display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border:1px solid rgba(207,224,255,.3);border-radius:10px}.back:hover{background:rgba(255,255,255,.08)}

.cart-layout{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:22px;align-items:start}

/* Daftar item */
.cart-list{display:flex;flex-direction:column;gap:14px}
.cart-item{display:grid;grid-template-columns:96px minmax(0,1fr) auto;gap:18px;align-items:center;background:#fff;border-radius:18px;padding:16px;box-shadow:var(--shadow)}
.thumb{width:96px;height:96px;border-radius:14px;object-fit:cover;background:var(--ice);display:block}
.thumb-ph{width:96px;height:96px;border-radius:14px;background:var(--ice);color:var(--sky);display:grid;place-items:center;font-size:30px}
.info{min-width:0}.pname{font:700 16px Sora;color:var(--navy);overflow-wrap:anywhere;margin-bottom:6px}
.price{font-weight:800;color:var(--blue);font-size:15px}.price small{font-weight:600;color:var(--muted)}
.stock{font-size:12.5px;color:var(--muted);margin-top:6px}
.badge{display:inline-block;margin-top:8px;padding:3px 10px;border-radius:999px;font-size:11.5px;font-weight:800;background:#fff3cd;color:#664d03}
.side{display:flex;flex-direction:column;align-items:flex-end;gap:12px}
.subtotal{font:700 17px Sora;color:var(--navy);white-space:nowrap}
.stepper{display:inline-flex;align-items:center;background:var(--ice);border:1px solid var(--line);border-radius:12px;padding:3px}
.stepper button{width:34px;height:34px;border:0;border-radius:9px;background:#fff;color:var(--navy);font:800 17px 'Nunito Sans';cursor:pointer;transition:.15s}.stepper button:hover{background:var(--blue);color:#fff}
.stepper span{min-width:38px;text-align:center;font-weight:800;color:var(--navy)}
.remove{background:none;border:0;color:var(--danger);font:700 13px 'Nunito Sans';cursor:pointer;display:inline-flex;align-items:center;gap:6px;padding:4px 2px}.remove:hover{text-decoration:underline}

.list-foot{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-top:2px}
.link-btn{background:none;border:0;color:#cfe0ff;font:700 14px 'Nunito Sans';cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:8px}.link-btn:hover{color:#fff;text-decoration:underline}

/* Ringkasan pesanan */
.summary{position:sticky;top:24px;background:#fff;border-radius:18px;box-shadow:var(--shadow);overflow:hidden;max-height:calc(100vh - 48px);overflow-y:auto}
.summary-head{padding:18px 22px;background:var(--ice);border-bottom:1px solid var(--line);font:700 16px Sora;color:var(--navy)}
.summary-body{padding:22px}
.row{display:flex;justify-content:space-between;gap:12px;padding:8px 0;font-size:14.5px;color:var(--muted)}.row b{color:var(--ink)}
.row.total{border-top:2px dashed var(--line);margin-top:8px;padding-top:16px;align-items:center}.row.total span{font-weight:800;color:var(--navy);font-size:15px}.row.total b{font:700 22px Sora;color:var(--navy)}
.field{margin:14px 0 16px}label.lbl,.field label{display:block;font-size:13px;font-weight:700;color:var(--muted);margin-bottom:7px}
.input-wrap{position:relative}.input-wrap span{position:absolute;left:14px;top:50%;transform:translateY(-50%);font-weight:800;color:var(--muted)}
input[type=number]{width:100%;height:50px;padding:0 14px 0 44px;border:1px solid var(--line);border-radius:12px;background:var(--ice);font:800 16px 'Nunito Sans';color:var(--ink)}input:focus{outline:2px solid #93c5fd;border-color:var(--blue);background:#fff}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;border:0;border-radius:12px;padding:0 18px;height:50px;font:800 15px 'Nunito Sans';cursor:pointer;text-decoration:none;width:100%}
.btn-primary{background:var(--blue);color:#fff;box-shadow:0 8px 18px rgba(29,78,216,.3)}.btn-primary:hover{background:var(--blue-dark)}.btn-light{background:#eaf0fb;color:var(--navy)}.btn-light:hover{background:#dce6f8}
.btn-sm{height:38px;font-size:13px;padding:0 14px;width:auto;border-radius:10px}
.btn-del{background:#fff;color:var(--danger);border:1px solid #f3c4c0}
.notice{padding:12px 15px;border-radius:12px;background:#fff3cd;color:#664d03;margin-bottom:16px;font-size:13.5px;line-height:1.4}
.pay-msg{display:none;align-items:flex-start;gap:10px;padding:12px 14px;border-radius:12px;font-size:13.5px;line-height:1.4;margin:-4px 0 16px}
.pay-msg i{margin-top:2px}.pay-msg b{font-weight:800}
.pay-msg.show{display:flex}
.pay-msg.kurang{background:#fef3f2;color:#b42318;border:1px solid #f3c4c0}
.pay-msg.cukup{background:#ecfdf3;color:#067647;border:1px solid #abefc6}
input.invalid{border-color:#f04438;background:#fffbfa}input.invalid:focus{outline-color:#fda29b}
.btn:disabled{opacity:.55;cursor:not-allowed;box-shadow:none}
.secure{margin-top:14px;text-align:center;font-size:12.5px;color:var(--muted)}

/* Metode pembayaran */
.methods{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:18px 0 6px}
.method{position:relative;cursor:pointer}
.method input{position:absolute;opacity:0;inset:0;width:100%;height:100%;cursor:pointer}
.m-box{display:flex;flex-direction:column;align-items:center;gap:6px;padding:12px 6px;border:2px solid var(--line);border-radius:14px;background:#fff;color:var(--muted);font-size:13px;font-weight:800;transition:.15s;text-align:center}
.m-box i{font-size:20px}
.method:hover .m-box{border-color:#9db8ee}
.method input:checked + .m-box{border-color:var(--blue);background:#eaf0ff;color:var(--blue);box-shadow:0 0 0 3px rgba(29,78,216,.12)}
.method input:focus-visible + .m-box{outline:2px solid var(--sky);outline-offset:2px}
.pay-panel{display:none;margin-top:12px}.pay-panel.show{display:block}
.qris-box{text-align:center;padding:16px;border:1px solid var(--line);border-radius:14px;background:#fff}
.qris-img{width:100%;max-width:250px;border-radius:12px;display:block;margin:0 auto 10px;border:1px solid var(--line)}
.qris-ph{padding:28px 12px;border:2px dashed var(--line);border-radius:12px;color:var(--muted);font-size:13.5px;background:var(--ice)}.qris-ph i{font-size:30px;color:var(--sky);display:block;margin-bottom:8px}
.qris-cap{font-size:13px;color:var(--muted)}.qris-cap b{color:var(--navy)}
.qris-admin{margin-top:14px;padding-top:14px;border-top:1px dashed var(--line);text-align:left}
.qris-admin .t{font-size:12.5px;font-weight:800;color:var(--navy);margin-bottom:8px}
.qris-admin input[type=file]{width:100%;font:600 13px 'Nunito Sans';margin-bottom:10px}
.qris-admin .acts{display:flex;gap:8px;flex-wrap:wrap}
.bank{padding:16px;border-radius:14px;background:linear-gradient(135deg,var(--navy),var(--blue));color:#fff}
.bank .nm{font-size:12.5px;font-weight:700;opacity:.85;display:flex;align-items:center;gap:8px}
.bank .no{font:700 22px Sora;letter-spacing:.06em;margin:8px 0 12px;overflow-wrap:anywhere}
.bank button{background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.35);color:#fff;border-radius:10px;padding:8px 14px;font:700 13px 'Nunito Sans';cursor:pointer}.bank button:hover{background:rgba(255,255,255,.28)}
.bank .amt{margin-top:12px;font-size:13px;opacity:.9}
.bank .min{margin-top:4px;font-size:12.5px;opacity:.8}
.flash{padding:10px 12px;border-radius:10px;font-size:13px;margin-bottom:12px}.flash.ok{background:#ecfdf3;color:#067647;border:1px solid #abefc6}.flash.err{background:#fef3f2;color:#b42318;border:1px solid #f3c4c0}

/* Kosong */
.empty-state{background:#fff;border-radius:18px;box-shadow:var(--shadow);text-align:center;padding:64px 24px;max-width:560px;margin:10px auto}
.empty-state .ic{width:96px;height:96px;margin:0 auto 18px;border-radius:50%;background:var(--ice);color:var(--blue);display:grid;place-items:center;font-size:38px;box-shadow:0 0 0 8px var(--sky)}
.empty-state h3{font:700 20px Sora;color:var(--navy);margin-bottom:8px}.empty-state p{color:var(--muted);margin-bottom:22px}.empty-state .btn{width:auto;padding:0 26px}

@media(max-width:1100px){.cart-layout{grid-template-columns:1fr}.summary{position:static;max-height:none}}
@media(max-width:850px){.container{display:block}nav{position:relative;width:100%;height:auto}.navbar ul{display:flex;flex-wrap:wrap}.profile-bar{width:100%}.main{margin-left:0;padding:18px}}
@media(max-width:560px){.cart-item{grid-template-columns:72px minmax(0,1fr);gap:14px}.thumb,.thumb-ph{width:72px;height:72px}.side{grid-column:1/-1;flex-direction:row;justify-content:space-between;align-items:center;flex-wrap:wrap}.page-head h1{font-size:21px}}
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
  <h1><i class="fas fa-shopping-cart"></i> Keranjang <?php if($items): ?><span class="count"><?= (int)$totalQty ?> item</span><?php endif; ?></h1>
  <p>Periksa pesanan sebelum melakukan pembayaran</p>
 </div>
 <a class="back" href="transaksi.php"><i class="fas fa-arrow-left"></i> Lanjut memilih produk</a>
</div>

<?php if(!$items): ?>
 <div class="empty-state">
  <div class="ic"><i class="fas fa-shopping-basket"></i></div>
  <h3>Keranjang masih kosong</h3>
  <p>Belum ada produk yang dipilih untuk transaksi ini.</p>
  <a class="btn btn-primary" href="transaksi.php"><i class="fas fa-box-open"></i> Pilih Produk</a>
 </div>
<?php else: ?>

<div class="cart-layout">

 <!-- Daftar item -->
 <section class="cart-list">
  <?php if($adaMelebihiStok): ?><div class="notice"><i class="fas fa-exclamation-triangle"></i> Ada produk dengan jumlah melebihi stok tersedia. Kurangi jumlahnya sebelum memproses pembayaran.</div><?php endif; ?>

  <?php foreach($items as $it): ?>
  <article class="cart-item">
   <?php if(!empty($it['foto'])): ?>
    <img class="thumb" src="../produk/gambar/<?= e($it['foto']) ?>" alt="<?= e($it['nama_produk']) ?>" onerror="this.outerHTML='<div class=\'thumb-ph\'><i class=\'fas fa-utensils\'></i></div>'">
   <?php else: ?>
    <div class="thumb-ph"><i class="fas fa-utensils"></i></div>
   <?php endif; ?>

   <div class="info">
    <div class="pname"><?= e($it['nama_produk']) ?></div>
    <div class="price">Rp <?= number_format($it['harga'],0,',','.') ?> <small>/ item</small></div>
    <div class="stock"><i class="fas fa-cubes"></i> Stok tersedia: <?= (int)$it['stok'] ?></div>
    <?php if((int)$it['qty']>(int)$it['stok']): ?><span class="badge">Melebihi stok</span><?php endif; ?>
   </div>

   <div class="side">
    <div class="subtotal">Rp <?= number_format($it['subtotal'],0,',','.') ?></div>
    <form method="post" class="stepper">
     <input type="hidden" name="product_id" value="<?= (int)$it['id_produk'] ?>">
     <button name="action" value="minus" aria-label="Kurangi">−</button>
     <span><?= (int)$it['qty'] ?></span>
     <button name="action" value="plus" aria-label="Tambah">+</button>
    </form>
    <form method="post">
     <input type="hidden" name="product_id" value="<?= (int)$it['id_produk'] ?>">
     <button class="remove" name="action" value="remove"><i class="fas fa-trash-alt"></i> Hapus</button>
    </form>
   </div>
  </article>
  <?php endforeach; ?>

  <div class="list-foot">
   <a class="link-btn" href="transaksi.php"><i class="fas fa-plus-circle"></i> Tambah produk lain</a>
   <form method="post" onsubmit="return confirm('Kosongkan seluruh keranjang?')">
    <button class="link-btn" name="action" value="clear"><i class="fas fa-trash"></i> Kosongkan keranjang</button>
   </form>
  </div>
 </section>

 <!-- Ringkasan pesanan -->
 <aside class="summary">
  <div class="summary-head"><i class="fas fa-receipt"></i> Ringkasan Pesanan</div>
  <div class="summary-body">
   <div class="row"><span>Jenis produk</span><b><?= count($items) ?></b></div>
   <div class="row"><span>Total item</span><b><?= (int)$totalQty ?></b></div>
   <div class="row total"><span>Total</span><b>Rp <?= number_format($total,0,',','.') ?></b></div>

   <?php if($flashQris): ?><div class="flash <?= $flashQris['ok']?'ok':'err' ?>" style="margin-top:14px"><?= e($flashQris['msg']) ?></div><?php endif; ?>

   <form method="post" action="beli.php" id="payForm" novalidate>
    <!-- Pilihan metode pembayaran -->
    <div class="methods" role="radiogroup" aria-label="Metode pembayaran">
     <label class="method"><input type="radio" name="metode" value="cash" <?= $metodeAwal==='cash'?'checked':'' ?>><span class="m-box"><i class="fas fa-money-bill-wave"></i>Cash</span></label>
     <label class="method"><input type="radio" name="metode" value="qris" <?= $metodeAwal==='qris'?'checked':'' ?>><span class="m-box"><i class="fas fa-qrcode"></i>QRIS</span></label>
     <label class="method"><input type="radio" name="metode" value="bca" <?= $metodeAwal==='bca'?'checked':'' ?>><span class="m-box"><i class="fas fa-university"></i>BCA</span></label>
    </div>

    <!-- Cash -->
    <div class="pay-panel" id="panel-cash">
     <div class="field" id="cashField">
      <label for="bayar">Uang Pembayaran</label>
      <div class="input-wrap"><span>Rp</span><input type="number" id="bayar" name="bayar" min="0" step="1" required placeholder="Masukkan nominal" value="<?= $bayarGet !== null ? (int)$bayarGet : '' ?>"></div>
     </div>

     <?php if($kurangServer): ?>
     <div class="pay-msg kurang show" id="serverMsg"><i class="fas fa-exclamation-circle"></i><div><b>Uang kurang<?= $selisihServer>0 ? ' Rp '.number_format($selisihServer,0,',','.') : '' ?>.</b><br>Pembayaran belum mencukupi total pesanan. Silakan masukkan nominal yang sesuai.</div></div>
     <?php endif; ?>
     <div class="pay-msg" id="payMsg" aria-live="polite"></div>
    </div>

    <!-- QRIS -->
    <div class="pay-panel" id="panel-qris">
     <div class="qris-box">
      <?php if($qrisUrl): ?>
       <img class="qris-img" src="<?= e($qrisUrl) ?>" alt="QRIS pembayaran">
       <div class="qris-cap">Scan QRIS lalu bayar <b>Rp <?= number_format($total,0,',','.') ?></b></div>
      <?php else: ?>
       <div class="qris-ph"><i class="fas fa-qrcode"></i>Foto QRIS belum diunggah.<?= $isAdmin ? '<br>Unggah di bawah ini.' : '<br>Hubungi admin untuk mengunggahnya.' ?></div>
      <?php endif; ?>

      <?php if($isAdmin): ?>
      <div class="qris-admin">
       <div class="t"><i class="fas fa-image"></i> Kelola foto QRIS</div>
       <input type="file" name="qris" accept="image/jpeg,image/png,image/webp" form="qrisForm">
       <div class="acts">
        <button class="btn btn-light btn-sm" type="submit" name="action" value="upload_qris" form="qrisForm"><i class="fas fa-upload"></i> <?= $qrisUrl?'Ganti Foto':'Unggah Foto' ?></button>
        <?php if($qrisUrl): ?><button class="btn btn-del btn-sm" type="submit" name="action" value="hapus_qris" form="qrisForm" onclick="return confirm('Hapus foto QRIS?')"><i class="fas fa-trash-alt"></i> Hapus</button><?php endif; ?>
       </div>
      </div>
      <?php endif; ?>
     </div>
    </div>

    <!-- BCA -->
    <div class="pay-panel" id="panel-bca">
     <div class="bank">
      <div class="nm"><i class="fas fa-university"></i> Transfer Bank BCA</div>
      <div class="no" id="noBca"><?= e(NOMOR_BCA) ?></div>
      <button type="button" id="copyBca"><i class="far fa-copy"></i> Salin nomor</button>
      <div class="amt">Nominal transfer: <b>Rp <?= number_format($total,0,',','.') ?></b></div>
      <div class="min">Minimal belanja via BCA: <b>Rp <?= number_format(MIN_BCA,0,',','.') ?></b></div>
     </div>
     <?php if($total < MIN_BCA): ?>
     <div class="pay-msg kurang show" style="margin:12px 0 0"><i class="fas fa-exclamation-circle"></i><div><b>Belum memenuhi minimal belanja BCA.</b><br>Tambah belanja minimal Rp <?= number_format(MIN_BCA-$total,0,',','.') ?> lagi, atau pilih Cash/QRIS.</div></div>
     <?php endif; ?>
    </div>

    <div style="margin-top:16px"></div>
    <button class="btn btn-primary" id="payBtn"><i class="fas fa-check-circle"></i> Proses Pembayaran</button>
   </form>
   <div class="secure"><i class="fas fa-lock"></i> Periksa kembali pesanan sebelum membayar</div>
  </div>
 </aside>

</div>

<!-- Form terpisah untuk upload/hapus foto QRIS (dipakai lewat atribut form="qrisForm") -->
<form id="qrisForm" method="post" enctype="multipart/form-data"></form>
<?php endif; ?>
</main></div>

<?php if($items): ?>
<script>
(function () {
  const total   = <?= (int)$total ?>;
  const hasQris = <?= $qrisUrl ? 'true' : 'false' ?>;
  const minBca  = <?= (int)MIN_BCA ?>;
  const form  = document.getElementById('payForm');
  const input = document.getElementById('bayar');
  const field = document.getElementById('cashField');
  const msg   = document.getElementById('payMsg');
  const btn   = document.getElementById('payBtn');
  const srv   = document.getElementById('serverMsg');
  const radios = form.querySelectorAll('input[name="metode"]');
  const rp = n => 'Rp ' + new Intl.NumberFormat('id-ID').format(n);
  let cashVal = input.value;

  const metode = () => form.querySelector('input[name="metode"]:checked').value;

  function cekCash() {
    const raw = input.value.trim();
    msg.className = 'pay-msg';
    input.classList.remove('invalid');
    btn.disabled = false;
    if (raw === '') { msg.innerHTML = ''; return true; }

    const bayar = parseInt(raw, 10);
    if (srv) srv.style.display = 'none';

    if (isNaN(bayar) || bayar < total) {
      const kurang = total - (isNaN(bayar) ? 0 : bayar);
      msg.className = 'pay-msg kurang show';
      msg.innerHTML = '<i class="fas fa-exclamation-circle"></i><div><b>Uang kurang ' + rp(kurang) + '.</b><br>Total pesanan ' + rp(total) + ', pembayaran belum mencukupi.</div>';
      input.classList.add('invalid');
      btn.disabled = true;
      return false;
    }
    msg.className = 'pay-msg cukup show';
    msg.innerHTML = '<i class="fas fa-check-circle"></i><div><b>Pembayaran mencukupi.</b><br>Kembalian ' + rp(bayar - total) + '.</div>';
    return true;
  }

  let prev = null;
  function tampil() {
    const m = metode();
    ['cash', 'qris', 'bca'].forEach(k => document.getElementById('panel-' + k).classList.toggle('show', k === m));

    if (m === 'cash') {
      if (prev && prev !== 'cash') input.value = cashVal;
      input.required = true;
      cekCash();
    } else {
      if (prev === 'cash' || prev === null) cashVal = input.value;
      // Non-cash: nominal dibayar = total pesanan (dikirim lewat field "bayar")
      input.value = total;
      input.required = false;
      input.classList.remove('invalid');
      msg.className = 'pay-msg'; msg.innerHTML = '';
      if (srv) srv.style.display = 'none';
      btn.disabled = (m === 'qris' && !hasQris) || (m === 'bca' && total < minBca);
    }
    prev = m;
  }

  radios.forEach(r => r.addEventListener('change', tampil));
  input.addEventListener('input', function () { if (metode() === 'cash') { cashVal = input.value; cekCash(); } });

  form.addEventListener('submit', function (e) {
    const m = metode();
    if (m === 'cash') {
      if (input.value.trim() === '') { e.preventDefault(); input.focus(); msg.className = 'pay-msg kurang show'; msg.innerHTML = '<i class="fas fa-exclamation-circle"></i><div><b>Masukkan nominal pembayaran.</b></div>'; return; }
      if (!cekCash()) e.preventDefault();
    } else if (m === 'qris' && !hasQris) {
      e.preventDefault();
    } else if (m === 'bca' && total < minBca) {
      e.preventDefault();
    }
  });

  // Salin nomor BCA
  const copyBtn = document.getElementById('copyBca');
  copyBtn.addEventListener('click', function () {
    const no = document.getElementById('noBca').textContent.trim();
    const done = () => { copyBtn.innerHTML = '<i class="fas fa-check"></i> Tersalin'; setTimeout(() => copyBtn.innerHTML = '<i class="far fa-copy"></i> Salin nomor', 1800); };
    if (navigator.clipboard) navigator.clipboard.writeText(no).then(done);
    else { const t = document.createElement('textarea'); t.value = no; document.body.appendChild(t); t.select(); document.execCommand('copy'); t.remove(); done(); }
  });

  tampil();
})();
</script>
<?php endif; ?>
</body>
</html>