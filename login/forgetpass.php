<?php
// Lupa password: reset dilakukan oleh admin (tidak ada email/OTP di aplikasi ini).
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['username'])) { header('Location: newpass.php'); exit; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - Kasir Management System</title>
    <link rel="icon" href="../img/kun.png">
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
            --red: #e11d48;
            --red-soft: #fff1f3;
            --green: #15803d;
            --green-soft: #ecfdf3;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Nunito Sans', sans-serif; color: var(--ink); min-height: 100vh; background: var(--navy); overflow-x: hidden; }
        .container { position: relative; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 20px; overflow: hidden; }
        .pattern-overlay { position: absolute; inset: 0; pointer-events: none; }
        .pattern-overlay::before, .pattern-overlay::after { content: ""; position: absolute; border-radius: 50%; }
        .pattern-overlay::before { width: 720px; height: 720px; top: -300px; right: -240px; border: 90px solid rgba(56,189,248,0.14); }
        .pattern-overlay::after { width: 460px; height: 460px; bottom: -220px; left: -150px; background: var(--blue); opacity: 0.55; }
        .login-container { position: relative; z-index: 1; width: 100%; max-width: 440px; margin-top: 70px; padding: 88px 40px 32px; background: #fff; border-radius: 24px; box-shadow: 0 30px 70px rgba(2,10,40,0.45); animation: enter .6s cubic-bezier(.2,.7,.2,1) both; }
        @keyframes enter { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: none; } }
        .logo-badge { position: absolute; top: -70px; left: 50%; transform: translateX(-50%); width: 140px; height: 140px; display: grid; place-items: center; padding: 22px; background: #fff; border-radius: 50%; box-shadow: 0 0 0 8px var(--sky), 0 14px 30px rgba(2,10,40,0.3); }
        .logo-badge img { width: 100%; height: 100%; object-fit: contain; }
        .welcome-text { text-align: center; margin-bottom: 26px; }
        .welcome-text h2 { font-family: 'Sora', sans-serif; font-size: 1.75rem; letter-spacing: -0.02em; color: var(--navy); margin-bottom: 8px; }
        .welcome-text p { color: var(--muted); font-size: 15px; line-height: 1.55; }
        .welcome-text p b { color: var(--navy); }
        .form-group { position: relative; margin-bottom: 16px; }
        .form-group input { width: 100%; height: 54px; padding: 0 48px; border: 1.5px solid var(--line); border-radius: 12px; background: var(--ice); color: var(--ink); font: 600 15px 'Nunito Sans', sans-serif; transition: border-color .2s, box-shadow .2s, background .2s; }
        .form-group input::placeholder { color: #93a3c4; font-weight: 400; }
        .form-group input:focus { outline: none; background: #fff; border-color: var(--blue); box-shadow: 0 0 0 4px rgba(29,78,216,0.15); }
        .form-group > i { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 15px; transition: color .2s; }
        .form-group input:focus ~ i { color: var(--blue); }
        .toggle-pass { position: absolute; right: 7px; top: 50%; transform: translateY(-50%); width: 40px; height: 40px; border: 0; border-radius: 10px; background: transparent; color: var(--muted); cursor: pointer; }
        .toggle-pass:hover { color: var(--blue); background: #e2eaf9; }
        .toggle-pass:focus-visible { outline: 2px solid var(--blue); }
        .hint { font-size: 13px; color: var(--muted); margin: -4px 0 18px; }
        .alert { display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; border-radius: 12px; font-size: 14px; line-height: 1.45; margin-bottom: 18px; }
        .alert i { margin-top: 2px; }
        .alert.err { background: var(--red-soft); color: #b42318; border: 1px solid #f3c4c0; }
        .alert.ok { background: var(--green-soft); color: #067647; border: 1px solid #abefc6; }
        .login-btn { width: 100%; height: 54px; border: 0; border-radius: 12px; background: var(--blue); color: #fff; font: 700 16px 'Nunito Sans', sans-serif; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; text-decoration: none; box-shadow: 0 8px 18px rgba(29,78,216,0.35); transition: background .2s, transform .15s; }
        .login-btn:hover { background: var(--blue-dark); }
        .login-btn:active { transform: translateY(1px); }
        .login-btn:focus-visible { outline: 3px solid var(--sky); outline-offset: 3px; }
        .steps { list-style: none; counter-reset: s; background: var(--ice); border: 1px solid var(--line); border-radius: 16px; padding: 14px 16px; margin-bottom: 22px; }
        .steps li { counter-increment: s; display: flex; gap: 12px; align-items: flex-start; padding: 9px 0; font-size: 14.5px; line-height: 1.5; font-weight: 600; }
        .steps li + li { border-top: 1px dashed var(--line); }
        .steps li::before { content: counter(s); flex: none; width: 26px; height: 26px; border-radius: 50%; background: var(--blue); color: #fff; display: grid; place-items: center; font: 800 13px 'Sora', sans-serif; }
        .steps small { display: block; font-weight: 600; color: var(--muted); font-size: 13px; }
        .switch-form { margin-top: 20px; text-align: center; font-size: 14px; color: var(--muted); }
        .switch-form a { color: var(--blue); font-weight: 700; text-decoration: none; }
        .switch-form a:hover { text-decoration: underline; text-underline-offset: 3px; }
        .switch-form a:focus-visible { outline: 2px solid var(--blue); outline-offset: 3px; border-radius: 4px; }
        .footer { margin-top: 28px; text-align: center; font-size: 12.5px; color: var(--muted); }
        @media (max-width: 480px) {
            .login-container { padding: 84px 22px 26px; border-radius: 20px; }
            .welcome-text h2 { font-size: 1.5rem; }
            .logo-badge { width: 120px; height: 120px; top: -60px; padding: 18px; }
        }
        @media (prefers-reduced-motion: reduce) { .login-container { animation: none; } * { transition: none !important; } }
    </style>
</head>
<body>
<div class="container">
    <div class="pattern-overlay"></div>
    <div class="login-container">
        <div class="logo-badge">
            <img src="../img/kun.png" alt="Kasir Management System Logo">
        </div>

        <div class="welcome-text">
            <h2>Lupa Password?</h2>
            <p>Reset password dilakukan oleh <b>admin</b> demi keamanan akun Anda.</p>
        </div>

        <ol class="steps">
            <li><span>Hubungi admin toko.</span></li>
            <li><span>Admin membuka menu <b>Admin → Edit</b> pada akun Anda, lalu mengisi <b>Password Baru</b>.<small>Kolom password boleh dikosongkan bila tidak ingin mengubahnya.</small></span></li>
            <li><span>Login dengan password baru, lalu ganti sesuai keinginan lewat menu <b>Ganti Password</b>.</span></li>
        </ol>

        <a class="login-btn" href="../login.php"><i class="fas fa-arrow-left"></i> Kembali ke Login</a>

        <div class="switch-form">
            Anda admin dan lupa password? Lihat bagian <b>Lupa password admin</b> di README.
        </div>

        <div class="footer">&copy; Database kasir Dikkun Kriuks.</div>
    </div>
</div>
</body>
</html>