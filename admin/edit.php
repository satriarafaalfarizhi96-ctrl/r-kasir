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
$id=filter_input(INPUT_GET,'id_admin',FILTER_VALIDATE_INT); if(!$id){header('Location: admin.php');exit;}
$stmt=$conn->prepare('SELECT id_admin,username,nama_lengkap,role,gambar FROM admin WHERE id_admin=?');$stmt->bind_param('i',$id);$stmt->execute();$data=$stmt->get_result()->fetch_assoc();$stmt->close();if(!$data){header('Location: admin.php');exit;}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 $nama=trim($_POST['nama_lengkap']??'');$user=trim($_POST['username']??'');$role=$_POST['role']??'kasir';$pass=$_POST['password']??'';$foto=$data['gambar']?:'default.png';
 if($nama===''||$user===''||!in_array($role,['admin','kasir'],true))$error='Data tidak valid.';
 else{
  $cek=$conn->prepare('SELECT id_admin FROM admin WHERE username=? AND id_admin<>?');$cek->bind_param('si',$user,$id);$cek->execute();$dupe=$cek->get_result()->num_rows>0;$cek->close();
  if($dupe)$error='Username sudah digunakan akun lain.';
  if($error===''&&isset($_FILES['gambar'])&&$_FILES['gambar']['error']===UPLOAD_ERR_OK){$ext=strtolower(pathinfo($_FILES['gambar']['name'],PATHINFO_EXTENSION));if(!in_array($ext,['jpg','jpeg','png','webp'],true))$error='Format foto tidak didukung.';else{$dir=__DIR__.'/../uploads/';if(!is_dir($dir))mkdir($dir,0775,true);$foto='admin_'.bin2hex(random_bytes(6)).'.'.$ext;if(!move_uploaded_file($_FILES['gambar']['tmp_name'],$dir.$foto))$error='Gagal mengunggah foto.';}}
  if($error===''){if($pass!==''){$hash=password_hash($pass,PASSWORD_DEFAULT);$up=$conn->prepare('UPDATE admin SET username=?,nama_lengkap=?,role=?,gambar=?,password=? WHERE id_admin=?');$up->bind_param('sssssi',$user,$nama,$role,$foto,$hash,$id);}else{$up=$conn->prepare('UPDATE admin SET username=?,nama_lengkap=?,role=?,gambar=? WHERE id_admin=?');$up->bind_param('ssssi',$user,$nama,$role,$foto,$id);}if($up->execute()){$up->close();if($data['username']===$username)$_SESSION['username']=$user;echo '<script>alert("Data admin berhasil diperbarui!");window.location="admin.php";</script>';exit;}$error='Gagal memperbarui: '.$up->error;$up->close();}
 }
 $data['nama_lengkap']=$nama;$data['username']=$user;$data['role']=$role;$data['gambar']=$foto;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Edit Admin - Kasir Management System</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">

<style>
:root{--navy:#0b2559;--deep:#081c44;--blue:#1d4ed8;--sky:#38bdf8;--ice:#eef4ff;--ink:#14213d;--muted:#667799;--line:#d5def0}
*{box-sizing:border-box}body{margin:0;background:var(--navy);font-family:'Nunito Sans',Arial,sans-serif;color:var(--ink)}
.container{display:flex;min-height:100vh}nav{width:250px;background:var(--deep);padding:24px 12px;position:fixed;inset:0 auto 0 0;overflow:auto}
.navbar ul{list-style:none;margin:0;padding:0}.navbar li{padding:4px 0}
.navbar a{display:flex;gap:12px;align-items:center;padding:12px 15px;border-radius:10px;color:#cfe0ff;text-decoration:none;font-weight:700}
.navbar a:hover,.navbar a.active{background:var(--blue);color:#fff}.profile{display:flex;align-items:center;gap:12px;padding:12px;margin:0 0 20px;background:#ffffff12;border-radius:14px;color:white}
.avatar{width:44px;height:44px;border-radius:50%;object-fit:cover;background:white}.admin-name{font-weight:700;color:#fff;overflow-wrap:anywhere}
.main{margin-left:250px;padding:32px;flex:1;min-width:0}.top{background:white;padding:22px 26px;border-radius:18px;margin-bottom:24px;display:flex;justify-content:space-between;gap:12px;align-items:center;box-shadow:0 15px 35px #020a402b}
.top h1{font-size:22px;margin:0;color:var(--navy)}.date{color:var(--muted)}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:11px 17px;border-radius:10px;text-decoration:none;border:0;cursor:pointer;font-weight:700;font-size:14px}
.primary{background:var(--blue);color:#fff}.secondary{background:#eaf0fb;color:var(--navy)}
.panel{background:#fff;border-radius:17px;box-shadow:0 15px 35px #020a402b;max-width:700px;overflow:hidden}.panel-head{padding:20px;background:var(--ice);border-bottom:1px solid var(--line);color:var(--navy);font-weight:800}.panel-body{padding:26px}
.form-group{margin-bottom:18px}
label{display:block;font-weight:700;margin-bottom:8px}
input[type=text],input[type=password],input[type=file],select{width:100%;padding:13px;border:1px solid #ccd5e5;border-radius:9px;font:inherit;background:#fff}
input:focus,select:focus{outline:2px solid #93c5fd;border-color:var(--blue)}
.hint{font-size:13px;color:var(--muted);margin:7px 0 0;overflow-wrap:anywhere}
.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:22px}.notice{padding:12px 15px;border-radius:10px;background:#fff3cd;color:#664d03;margin-bottom:16px}
@media(max-width:850px){.container{display:block}nav{position:relative;width:100%;height:auto}.main{margin:0;padding:20px 14px}.top{align-items:flex-start;flex-direction:column}}
</style>

</head><body><div class="container">

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
<div class="top"><h1>Edit Admin</h1><div class="date"><i class="far fa-calendar-alt"></i> <?= date('d F Y') ?></div></div>
<section class="panel"><div class="panel-head"><i class="fas fa-user-edit"></i> Perbarui Informasi Akun</div><div class="panel-body">
<?php if($error): ?><div class="notice"><?= e($error) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data">
 <div class="form-group">
  <label for="nama_lengkap">Nama Lengkap</label>
  <input type="text" id="nama_lengkap" name="nama_lengkap" maxlength="100" required placeholder="Masukkan nama lengkap" value="<?= e($data['nama_lengkap']) ?>">
 </div>
 <div class="form-group">
  <label for="username">Username</label>
  <input type="text" id="username" name="username" maxlength="50" required placeholder="Masukkan username" value="<?= e($data['username']) ?>">
 </div>
 <div class="form-group">
  <label for="password">Password Baru</label>
  <input type="password" id="password" name="password" placeholder="Kosongkan jika tidak diubah">
 </div>
 <div class="form-group">
  <label for="role">Role</label>
  <select name="role" id="role">
   <option value="kasir" <?= $data['role']==='kasir'?'selected':'' ?>>Kasir</option>
   <option value="admin" <?= $data['role']==='admin'?'selected':'' ?>>Admin</option>
  </select>
 </div>
 <div class="form-group">
  <label for="gambar">Foto Profil</label>
  <input type="file" id="gambar" name="gambar" accept=".jpg,.jpeg,.png,.webp">
  <p class="hint">Foto saat ini: <?= e($data['gambar']) ?></p>
 </div>
 <div class="actions">
  <button type="submit" class="btn primary"><i class="fas fa-save"></i> Simpan Perubahan</button>
  <a href="admin.php" class="btn secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
 </div>
</form></div></section>
</main></div></body></html>