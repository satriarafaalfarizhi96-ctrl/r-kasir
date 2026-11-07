<?php
/**
 * auth.php - Logika hak akses berbasis role (enum).
 * Kompatibel PHP 7.4+ (tanpa enum).
 *
 * Pemakaian di halaman khusus admin:
 *   require_once __DIR__ . '/../auth.php';
 *   if (!bolehAkses(Role::dariString($loginData['role'] ?? null), Role::Admin)) {
 *       tampilkanAksesDitolak('../', $username, $loginData['role'] ?? null);
 *   }
 */

final class Role
{
    // Pengganti enum (enum baru ada di PHP 8.1+). Nilainya string biasa.
    const Admin = 'admin';
    const Kasir = 'kasir';

    /** Ubah string menjadi role valid ('admin'/'kasir'), atau null bila tidak dikenal. */
    public static function dariString($nilai)
    {
        $nilai = strtolower(trim((string)$nilai));
        return ($nilai === self::Admin || $nilai === self::Kasir) ? $nilai : null;
    }

    /** Label tampilan untuk sebuah role. */
    public static function label($role)
    {
        switch ($role) {
            case self::Admin: return 'Admin';
            case self::Kasir: return 'Kasir';
            default:          return 'Tidak dikenal';
        }
    }
}

/** Apakah role pengguna termasuk salah satu role yang diizinkan? */
function bolehAkses($role, ...$diizinkan)
{
    return $role !== null && in_array($role, $diizinkan, true);
}

/**
 * Menampilkan halaman "Akses Ditolak" (HTTP 403) lalu menghentikan eksekusi.
 * $base = awalan path menuju root aplikasi dari halaman pemanggil ('../' untuk subfolder).
 */
function tampilkanAksesDitolak($base = '', $username = '', $roleSaatIni = null)
{
    http_response_code(403);
    $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $role = Role::dariString($roleSaatIni);
    $roleLabel = Role::label($role);
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Akses Ditolak - Kasir Management System</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Nunito+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--navy:#0b2559;--deep:#081c44;--blue:#1d4ed8;--sky:#38bdf8;--ice:#eef4ff;--ink:#14213d;--muted:#667799;--line:#d5def0;--red:#e11d48;--red-soft:#fff1f3}
*{box-sizing:border-box;margin:0;padding:0}
body{min-height:100vh;display:grid;place-items:center;padding:28px 18px;background:var(--navy);font-family:'Nunito Sans',Arial,sans-serif;color:var(--ink);overflow-x:hidden;position:relative}
body::before,body::after{content:"";position:fixed;border-radius:50%;pointer-events:none}
body::before{width:720px;height:720px;top:-300px;right:-240px;border:90px solid rgba(56,189,248,.14)}
body::after{width:460px;height:460px;bottom:-220px;left:-150px;background:var(--blue);opacity:.55}
.card{position:relative;z-index:1;width:100%;max-width:520px;background:#fff;border-radius:26px;padding:84px 38px 34px;text-align:center;box-shadow:0 30px 70px rgba(2,10,40,.5);animation:enter .6s cubic-bezier(.2,.7,.2,1) both}
@keyframes enter{from{opacity:0;transform:translateY(22px)}to{opacity:1;transform:none}}
.lock{position:absolute;top:-56px;left:50%;transform:translateX(-50%);width:112px;height:112px;border-radius:50%;display:grid;place-items:center;background:linear-gradient(145deg,#fb7185,var(--red));color:#fff;font-size:44px;box-shadow:0 0 0 8px #fff,0 0 0 16px rgba(225,29,72,.25),0 16px 30px rgba(225,29,72,.4)}
.lock i{animation:shake 2.8s ease-in-out infinite;transform-origin:50% 20%}
@keyframes shake{0%,70%,100%{transform:rotate(0)}74%{transform:rotate(-12deg)}78%{transform:rotate(10deg)}82%{transform:rotate(-8deg)}86%{transform:rotate(5deg)}}
.code{display:inline-block;margin-bottom:12px;padding:5px 14px;border-radius:999px;background:var(--red-soft);color:var(--red);font:800 13px 'Sora',sans-serif;letter-spacing:.14em}
h1{font-family:'Sora',sans-serif;font-size:1.9rem;letter-spacing:-.02em;color:var(--navy);margin-bottom:10px}
.lead{color:var(--muted);line-height:1.65;font-size:15.5px;margin-bottom:22px}
.lead strong{color:var(--navy)}
.rules{text-align:left;background:var(--ice);border:1px solid var(--line);border-radius:16px;padding:16px 18px;margin-bottom:26px}
.rules h2{font-size:12.5px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:10px}
.rule{display:flex;align-items:center;gap:12px;padding:8px 0;font-weight:700;font-size:14.5px}
.rule + .rule{border-top:1px dashed var(--line)}
.rule .ic{width:32px;height:32px;border-radius:10px;display:grid;place-items:center;font-size:14px;flex:none}
.ok .ic{background:#dcfce7;color:#15803d}.no .ic{background:var(--red-soft);color:var(--red)}
.rule small{display:block;font-weight:600;color:var(--muted);font-size:12.5px}
.rule .tag{margin-left:auto;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:800}
.ok .tag{background:#dcfce7;color:#15803d}.no .tag{background:var(--red-soft);color:var(--red)}
.actions{display:flex;flex-direction:column;gap:10px}
.btn{display:flex;align-items:center;justify-content:center;gap:10px;height:50px;border-radius:12px;font:800 15px 'Nunito Sans',sans-serif;text-decoration:none;cursor:pointer;border:0;transition:transform .15s,background .2s}
.btn:active{transform:translateY(1px)}
.btn.primary{background:var(--blue);color:#fff;box-shadow:0 8px 18px rgba(29,78,216,.35)}.btn.primary:hover{background:#1739a8}
.btn.ghost{background:var(--ice);color:var(--navy)}.btn.ghost:hover{background:#e0eaff}
.btn:focus-visible{outline:3px solid var(--sky);outline-offset:3px}
.foot{margin-top:20px;font-size:12.5px;color:var(--muted)}
@media(max-width:480px){.card{padding:80px 22px 28px;border-radius:22px}h1{font-size:1.6rem}}
@media(prefers-reduced-motion:reduce){.card,.lock i{animation:none}}
</style>
</head>
<body>
<main class="card" role="alert">
  <div class="lock"><i class="fas fa-lock"></i></div>
  <span class="code">ERROR 403</span>
  <h1>Akses Ditolak</h1>
  <p class="lead">Halaman ini <strong>tidak dapat diakses</strong>. Hanya akun dengan role <strong>Admin</strong> yang diizinkan membuka area admin.</p>

  <div class="rules">
    <h2>Ketentuan akses</h2>
    <div class="rule no">
      <span class="ic"><i class="fas fa-user"></i></span>
      <span>Akun Anda<small><?= $h($username !== '' ? $username : 'Pengguna') ?></small></span>
      <span class="tag"><?= $h($roleLabel) ?></span>
    </div>
    <div class="rule ok">
      <span class="ic"><i class="fas fa-user-shield"></i></span>
      <span>Role yang dibutuhkan<small>Mengelola akun admin &amp; kasir</small></span>
      <span class="tag">Admin</span>
    </div>
  </div>

  <div class="actions">
    <a class="btn primary" href="<?= $h($base) ?>laporan/laporan.php"><i class="fas fa-chart-line"></i> Kembali ke Dashboard</a>
    <a class="btn ghost" href="<?= $h($base) ?>halaman.php"><i class="fas fa-house"></i> Ke Beranda</a>
    <a class="btn ghost" href="<?= $h($base) ?>logout/Tlogout.php"><i class="fas fa-right-from-bracket"></i> Masuk dengan akun admin</a>
  </div>
  <p class="foot">Merasa ini keliru? Hubungi admin untuk mengubah role akun Anda.</p>
</main>
</body>
</html>
    <?php
    exit;
}