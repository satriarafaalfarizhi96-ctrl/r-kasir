<?php
// Menghubungkan file koneksi database
include 'koneksi.php';

// Memulai sesi untuk menyimpan data pengguna yang login
session_start();

// Jika sudah login, arahkan ke halaman produk
if (isset($_SESSION['username']) && !empty($_SESSION['username'])) {
    header("Location: produk/Tproduk.php");
    exit();
}

/**
 * Memeriksa password terhadap hash di database.
 * Mendukung:
 *  1. bcrypt/argon (hasil password_hash)            -> password_verify
 *  2. MD5 (32 karakter hex, mis. hasil INSERT SQL)   -> md5()
 *  3. Teks biasa (mis. INSERT ... 'admin123')        -> perbandingan langsung
 * Mengembalikan [valid(bool), perluUpgrade(bool)]
 */
function cekPassword(string $input, string $tersimpan): array
{
    // 1. Hash modern (bcrypt / argon2)
    if (preg_match('/^\$(2y|2a|2b|argon2i|argon2id)\$/', $tersimpan)) {
        $ok = password_verify($input, $tersimpan);
        return [$ok, $ok && password_needs_rehash($tersimpan, PASSWORD_DEFAULT)];
    }

    // 2. MD5 (32 karakter heksadesimal)
    if (preg_match('/^[a-f0-9]{32}$/i', $tersimpan)) {
        $ok = hash_equals(strtolower($tersimpan), md5($input));
        return [$ok, $ok];
    }

    // 3. Teks biasa
    $ok = hash_equals($tersimpan, $input);
    return [$ok, $ok];
}

// ===================== PROSES LOGIN =====================
if (isset($_POST['proses'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare('SELECT id_admin, username, password, nama_lengkap, role FROM admin WHERE username = ? LIMIT 1');
    if (!$stmt) {
        die("Query Error: " . $conn->error);
    }
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        [$valid, $upgrade] = cekPassword($password, (string)$row['password']);

        if ($valid) {
            // Ubah otomatis hash lama (MD5/teks biasa) menjadi bcrypt
            if ($upgrade) {
                $baru = password_hash($password, PASSWORD_DEFAULT);
                $up = $conn->prepare('UPDATE admin SET password = ? WHERE id_admin = ?');
                if ($up) {
                    $up->bind_param('si', $baru, $row['id_admin']);
                    $up->execute();
                    $up->close();
                }
            }

            session_regenerate_id(true);
            $_SESSION['username'] = $row['username'];
            $_SESSION['nama_lengkap'] = $row['nama_lengkap'];
            $_SESSION['id'] = $row['id_admin'];
            $_SESSION['role'] = $row['role'];

            echo "<script> 
                alert('Login successful!');
                window.location.href= 'laporan/laporan.php';
            </script>";
            exit();
        } else {
            echo "<script>alert('Incorrect password!'); window.location.href='login.php';</script>";
        }
    } else {
        echo "<script>alert('Username not found!'); window.location.href='login.php';</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - Kasir Management System</title>
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

        .form-group { position: relative; margin-bottom: 16px; }

        .form-group input {
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
        .form-group input::placeholder { color: #93a3c4; font-weight: 400; }
        .form-group input:focus {
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
        }
        .form-group input:focus ~ i { color: var(--blue); }

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

        .forgot-password { text-align: right; margin: 2px 0 24px; }
        .forgot-password a {
            color: var(--blue);
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
        }
        .forgot-password a:hover { text-decoration: underline; text-underline-offset: 3px; }
        .forgot-password a:focus-visible { outline: 2px solid var(--blue); outline-offset: 3px; border-radius: 4px; }

        .login-btn {
            width: 100%;
            height: 54px;
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
            <h2>Selamat datang!</h2>
            <p>Silakan masuk untuk melanjutkan</p>
        </div>

        <form method="POST" action="">
            <div class="form-group">
                <input type="text" name="username" placeholder="Username" autocomplete="username" required>
                <i class="fas fa-user"></i>
            </div>
            <div class="form-group">
                <input type="password" name="password" id="password" placeholder="Password" autocomplete="current-password" required>
                <i class="fas fa-lock"></i>
                <button type="button" class="toggle-pass" id="togglePass" aria-label="Tampilkan password">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
            <div class="forgot-password">
                <a href="login/forgetpass.php">Lupa Password?</a>
            </div>
            <button type="submit" name="proses" class="login-btn">
                Masuk <i class="fas fa-arrow-right"></i>
            </button>
        </form>

        <div class="switch-form">
            <a href="halaman.php"><i class="fas fa-arrow-left"></i> Kembali ke Beranda</a><br><br>
            Belum punya akun? <a href="tambah-admin.php">Daftar</a>
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

</body>
</html>