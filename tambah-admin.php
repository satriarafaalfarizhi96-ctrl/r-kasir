<?php
include 'koneksi.php';
session_start();

// Jika sudah login, arahkan ke halaman produk
if (isset($_SESSION['username']) && !empty($_SESSION['username'])) {
    header("Location: produk/Tproduk.php");
    exit();
}

// Token CSRF
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$pesan = "";
$tipe_pesan = "";
$nama_lengkap = "";
$username = "";
$role = "kasir";

$upload_dir = __DIR__ . '/uploads/';

// Buat folder uploads jika belum ada
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// ================= PROSES TAMBAH AKUN =================

if (isset($_POST['tambah_akun'])) {

    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $konfirmasi = $_POST['konfirmasi_password'] ?? '';

    $nama_gambar = 'default.png';
    $file_berhasil_upload = false;
    $lokasi_gambar = '';

    // Validasi CSRF
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {

        $pesan = "Sesi form tidak valid. Muat ulang halaman.";
        $tipe_pesan = "danger";

    } elseif (
        $nama_lengkap === '' ||
        $username === '' ||
        $password === ''
    ) {

        $pesan = "Semua kolom wajib diisi.";
        $tipe_pesan = "danger";

    } elseif (mb_strlen($nama_lengkap) > 100) {

        $pesan = "Nama lengkap maksimal 100 karakter.";
        $tipe_pesan = "danger";

    } elseif (mb_strlen($username) > 50) {

        $pesan = "Username maksimal 50 karakter.";
        $tipe_pesan = "danger";

    } elseif (strlen($password) < 6) {

        $pesan = "Password minimal 6 karakter.";
        $tipe_pesan = "danger";

    } elseif ($password !== $konfirmasi) {

        $pesan = "Konfirmasi password tidak cocok.";
        $tipe_pesan = "danger";

    } elseif (
        !isset($_FILES['gambar']) ||
        $_FILES['gambar']['error'] === UPLOAD_ERR_NO_FILE
    ) {

        $pesan = "Silakan pilih gambar profil.";
        $tipe_pesan = "danger";

    } elseif ($_FILES['gambar']['error'] !== UPLOAD_ERR_OK) {

        $pesan = "Gagal mengunggah gambar.";
        $tipe_pesan = "danger";

    } elseif ($_FILES['gambar']['size'] > 2 * 1024 * 1024) {

        $pesan = "Ukuran gambar maksimal 2 MB.";
        $tipe_pesan = "danger";

    } else {

        // Validasi gambar berdasarkan MIME file
        $file_tmp = $_FILES['gambar']['tmp_name'];

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file_tmp);

        $format_valid = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];

        if (!isset($format_valid[$mime])) {

            $pesan = "Format gambar harus JPG, PNG, atau WEBP.";
            $tipe_pesan = "danger";

        } elseif (getimagesize($file_tmp) === false) {

            $pesan = "File yang diunggah bukan gambar yang valid.";
            $tipe_pesan = "danger";

        } else {

            // Role ditentukan oleh server
            $role = 'kasir';

            // Cek username
            $cek = mysqli_prepare(
                $conn,
                "SELECT id_admin FROM admin WHERE username = ?"
            );

            mysqli_stmt_bind_param($cek, "s", $username);
            mysqli_stmt_execute($cek);
            mysqli_stmt_store_result($cek);

            $sudah_ada = mysqli_stmt_num_rows($cek) > 0;

            mysqli_stmt_close($cek);

            if ($sudah_ada) {

                $pesan = "Username sudah digunakan. Pilih username lain.";
                $tipe_pesan = "danger";

            } else {

                // Nama file acak agar tidak bentrok
                $nama_gambar = bin2hex(random_bytes(16))
                    . '.' . $format_valid[$mime];

                $lokasi_gambar = $upload_dir . $nama_gambar;

                // Simpan gambar ke folder uploads
                if (move_uploaded_file($file_tmp, $lokasi_gambar)) {

                    $file_berhasil_upload = true;

                    // Hash password
                    $hash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    // Simpan data admin beserta gambar
                    try {

                        $ins = mysqli_prepare(
                            $conn,
                            "INSERT INTO admin
                            (username, password, nama_lengkap, role, gambar)
                            VALUES (?, ?, ?, ?, ?)"
                        );

                        mysqli_stmt_bind_param(
                            $ins,
                            "sssss",
                            $username,
                            $hash,
                            $nama_lengkap,
                            $role,
                            $nama_gambar
                        );

                        mysqli_stmt_execute($ins);
                        mysqli_stmt_close($ins);

                        $pesan = "Akun kasir berhasil didaftarkan.";
                        $tipe_pesan = "success";

                        // Kosongkan form
                        $nama_lengkap = "";
                        $username = "";
                        $role = "kasir";

                    } catch (mysqli_sql_exception $e) {

                        // Hapus gambar jika database gagal menyimpan
                        if ($file_berhasil_upload && file_exists($lokasi_gambar)) {
                            unlink($lokasi_gambar);
                        }

                        if ($e->getCode() == 1062) {
                            $pesan = "Username sudah digunakan.";
                        } else {
                            $pesan = "Pendaftaran gagal. Silakan coba lagi.";
                        }

                        $tipe_pesan = "danger";
                    }

                } else {

                    $pesan = "Gagal menyimpan gambar ke folder uploads.";
                    $tipe_pesan = "danger";
                }
            }
        }
    }
}

// Fungsi keamanan output HTML
function e($teks) {
    return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Akun - Kasir Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700&family=Nunito+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --navy: #0b2559;
            --blue: #1d4ed8;
            --blue-dark: #1739a8;
            --sky: #38bdf8;
            --ice: #eef4ff;
            --ink: #14213d;
            --muted: #667799;
            --line: #d5def0;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Nunito Sans', sans-serif;
            color: var(--ink);
            min-height: 100vh;
            background: var(--navy);
            overflow-x: hidden;
        }

        .container {
            position: relative;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            overflow: hidden;
        }

        .pattern-overlay { position: absolute; inset: 0; pointer-events: none; }
        .pattern-overlay::before,
        .pattern-overlay::after {
            content: "";
            position: absolute;
            border-radius: 50%;
        }
        .pattern-overlay::before {
            width: 720px; height: 720px;
            top: -300px; right: -240px;
            border: 90px solid rgba(56,189,248,0.14);
        }
        .pattern-overlay::after {
            width: 460px; height: 460px;
            bottom: -220px; left: -150px;
            background: var(--blue);
            opacity: 0.55;
        }

        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 440px;
            margin-top: 70px;
            padding: 88px 40px 32px;
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 30px 70px rgba(2,10,40,0.45);
            animation: enter .6s cubic-bezier(.2,.7,.2,1) both;
        }
        .upload-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 700;
    font-size: 14px;
    color: var(--navy);
}

.upload-group input[type="file"] {
    height: auto;
    min-height: 54px;
    padding: 14px;
    font-size: 13px;
    cursor: pointer;
}

.upload-group small {
    display: block;
    margin-top: 7px;
    color: var(--muted);
    font-size: 12px;
}

#previewGambar {
    width: 100px;
    height: 100px;
    margin-top: 12px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--sky);
}
        @keyframes enter {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: none; }
        }

        .logo-badge {
            position: absolute;
            top: -70px;
            left: 50%;
            transform: translateX(-50%);
            width: 140px; height: 140px;
            display: grid;
            place-items: center;
            padding: 22px;
            background: #fff;
            border-radius: 50%;
            box-shadow: 0 0 0 8px var(--sky), 0 14px 30px rgba(2,10,40,0.3);
        }
        .logo-badge img { width: 100%; height: 100%; object-fit: contain; }

        .welcome-text { text-align: center; margin-bottom: 30px; }

        .welcome-text h2 {
            font-family: 'Sora', sans-serif;
            font-size: 1.75rem;
            letter-spacing: -0.02em;
            color: var(--navy);
            margin-bottom: 8px;
        }

        .welcome-text p { color: var(--muted); font-size: 15px; }

        .alert {
            padding: 12px 16px;
            margin-bottom: 18px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            text-align: center;
        }
        .alert-success { background: #dcfce7; color: #14532d; }
        .alert-danger  { background: #fee2e2; color: #7f1d1d; }

        .form-group { position: relative; margin-bottom: 16px; }

        .form-group input,
        .form-group select {
            width: 100%;
            height: 54px;
            padding: 0 48px;
            border: 1.5px solid var(--line);
            border-radius: 12px;
            background: var(--ice);
            color: var(--ink);
            font: 600 15px 'Nunito Sans', sans-serif;
            transition: border-color .2s, box-shadow .2s, background .2s;
        }
        .form-group select {
            appearance: none;
            -webkit-appearance: none;
            cursor: pointer;
        }
        .form-group input::placeholder { color: #93a3c4; font-weight: 400; }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            background: #fff;
            border-color: var(--blue);
            box-shadow: 0 0 0 4px rgba(29,78,216,0.15);
        }

        .form-group > i {
            position: absolute;
            left: 18px; top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 15px;
            transition: color .2s;
            pointer-events: none;
        }
        .form-group input:focus ~ i,
        .form-group select:focus ~ i { color: var(--blue); }
        .form-group > i.chevron { left: auto; right: 18px; }

        .toggle-pass {
            position: absolute;
            right: 7px; top: 50%;
            transform: translateY(-50%);
            width: 40px; height: 40px;
            border: 0; border-radius: 10px;
            background: transparent;
            color: var(--muted);
            cursor: pointer;
        }
        .toggle-pass:hover { color: var(--blue); background: #e2eaf9; }
        .toggle-pass:focus-visible { outline: 2px solid var(--blue); }

        .login-btn {
            width: 100%;
            height: 54px;
            margin-top: 8px;
            border: 0;
            border-radius: 12px;
            background: var(--blue);
            color: #fff;
            font: 700 16px 'Nunito Sans', sans-serif;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 8px 18px rgba(29,78,216,0.35);
            transition: background .2s, transform .15s;
        }
        .login-btn:hover { background: var(--blue-dark); }
        .login-btn:active { transform: translateY(1px); }
        .login-btn:focus-visible { outline: 3px solid var(--sky); outline-offset: 3px; }

        .switch-form {
            margin-top: 20px;
            text-align: center;
            font-size: 14px;
            color: var(--muted);
        }
        .switch-form a {
            color: var(--blue);
            font-weight: 700;
            text-decoration: none;
        }
        .switch-form a:hover { text-decoration: underline; text-underline-offset: 3px; }
        .switch-form a:focus-visible { outline: 2px solid var(--blue); outline-offset: 3px; border-radius: 4px; }

        .footer {
            margin-top: 28px;
            text-align: center;
            font-size: 12.5px;
            color: var(--muted);
        }

        @media (max-width: 480px) {
            .login-container { padding: 84px 22px 26px; border-radius: 20px; }
            .welcome-text h2 { font-size: 1.5rem; }
            .logo-badge { width: 120px; height: 120px; top: -60px; padding: 18px; }
        }

        @media (prefers-reduced-motion: reduce) {
            .login-container { animation: none; }
            * { transition: none !important; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="pattern-overlay"></div>

    <div class="login-container">
        <div class="logo-badge">
            <img src="img/kun.png" alt="Kasir Management System Logo">
        </div>

        <div class="welcome-text">
            <h2>Tambah akun</h2>
            <p>Isi data berikut untuk membuat akun baru</p>
        </div>

        <?php if ($pesan !== ""): ?>
            <div class="alert alert-<?php echo e($tipe_pesan); ?>" role="alert">
                <?php echo e($pesan); ?>
            </div>
        <?php endif; ?>

       <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?php echo e($_SESSION['csrf']); ?>">

            <div class="form-group">
                <input type="text" name="nama_lengkap" placeholder="Nama lengkap" autocomplete="off"
                       maxlength="100" value="<?php echo e($nama_lengkap); ?>" required>
                <i class="fas fa-id-card"></i>
            </div>
            <div class="form-group">
                <input type="text" name="username" placeholder="Username" autocomplete="off"
                       maxlength="50" value="<?php echo e($username); ?>" required>
                <i class="fas fa-user"></i>
            </div>
            <!-- Upload Foto Profil -->
<div class="form-group upload-group">
    <label for="gambar">Foto Profil Kasir</label>

    <input
        type="file"
        name="gambar"
        id="gambar"
        accept="image/jpeg,image/png,image/webp"
        required
    >

    <small>Format JPG, PNG, WEBP. Maksimal 2 MB.</small>

    <img
        id="previewGambar"
        src="img/default.png"
        alt="Preview foto profil"
        style="display:none;"
    >
</div>
            <div class="form-group">
    <input
        type="text"
        value="Kasir"
        readonly
        aria-label="Role akun"
    >
    <i class="fas fa-user-shield"></i>
                <i class="fas fa-user-shield"></i>
                <i class="fas fa-chevron-down chevron"></i>
            </div>
            <div class="form-group">
                <input type="password" name="password" id="password" placeholder="Password (min. 6 karakter)"
                       autocomplete="new-password" minlength="6" required>
                <i class="fas fa-lock"></i>
                <button type="button" class="toggle-pass" id="togglePass" aria-label="Tampilkan password">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
            <div class="form-group">
                <input type="password" name="konfirmasi_password" id="konfirmasi" placeholder="Konfirmasi password"
                       autocomplete="new-password" required>
                <i class="fas fa-lock"></i>
            </div>

            <button type="submit" name="tambah_akun" class="login-btn">
                Simpan akun <i class="fas fa-user-plus"></i>
            </button>
        </form>

        <div class="switch-form">
            <a href="halaman.php"><i class="fas fa-arrow-left"></i> Kembali ke Beranda</a><br><br>
            Sudah punya akun? <a href="login.php">Masuk</a>
        </div>

        <div class="footer">
            &copy; Database kasir Dikkun Kriuks.
        </div>
    </div>
</div>

<script>
    // Tampilkan / sembunyikan password
    (function () {
        const input = document.getElementById('password');
        const btn = document.getElementById('togglePass');
        btn.addEventListener('click', function () {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
            btn.querySelector('i').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
        });
    })();
</script>
// Preview foto sebelum diunggah
document.getElementById('gambar').addEventListener('change', function () {

    const file = this.files[0];
    const preview = document.getElementById('previewGambar');

    if (file) {
        const allowed = [
            'image/jpeg',
            'image/png',
            'image/webp'
        ];

        if (!allowed.includes(file.type)) {
            alert('Format gambar harus JPG, PNG, atau WEBP.');
            this.value = '';
            preview.style.display = 'none';
            return;
        }

        if (file.size > 2 * 1024 * 1024) {
            alert('Ukuran gambar maksimal 2 MB.');
            this.value = '';
            preview.style.display = 'none';
            return;
        }

        preview.src = URL.createObjectURL(file);
        preview.style.display = 'block';
    }
});
</body>
</html>