<?php
session_start();
include '../koneksi.php';

// Pastikan ada email di sesi
if (!isset($_SESSION['reset_email'])) {
    echo "<script>
            alert('Akses ditolak! Harap masukkan email terlebih dahulu.');
            window.location.href = 'forgetpass.php';
          </script>";
    exit();
}

if (isset($_POST['submit'])) {
    $email = $_SESSION['reset_email'];
    $new_password = mysqli_real_escape_string($conn, $_POST['new_password']);
    $confirm_password = mysqli_real_escape_string($conn, $_POST['confirm_password']);

    if ($new_password === $confirm_password) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT); // Hash password dengan bcrypt

        // Update password di database
        $update_sql = "UPDATE admin SET password='$hashed_password' WHERE email='$email'";
        if (mysqli_query($conn, $update_sql)) {
            session_destroy(); // Hapus sesi setelah reset password
            echo "<script>
                    alert('Password berhasil diperbarui! Silakan login kembali.');
                    window.location.href = '../login.php';
                  </script>";
        } else {
            echo "<script>alert('Terjadi kesalahan! Coba lagi.');</script>";
        }
    } else {
        echo "<script>alert('Konfirmasi password tidak cocok!');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Kasir Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .container {
            display: flex;
            height: 100vh;
            width: 100%;
            overflow: hidden;
            position: relative;
        }

        .left {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(145deg, #1a2980, #26d0ce);
            position: relative;
            z-index: 1;
            box-shadow: 0 0 30px rgba(0,0,0,0.1);
        }

        .left-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            width: 80%;
            z-index: 2;
            padding: 2rem;
        }

        .left img {
            width: 60%;
            max-width: 300px;
            margin-bottom: 30px;
            filter: drop-shadow(0 10px 20px rgba(0,0,0,0.15));
            transition: transform 0.5s ease;
        }

        .pattern-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.6;
            z-index: 1;
        }

        .right {
            flex: 1;
            background: #ffffff;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #333;
            flex-direction: column;
            padding: 50px;
            position: relative;
            z-index: 1;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 40px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
            border: 1px solid rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }

        .welcome-text {
            text-align: center;
            margin-bottom: 35px;
        }

        .welcome-text h2 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #1a2980;
        }

        .welcome-text p {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 5px;
        }

        .form-group {
            width: 100%;
            margin-bottom: 25px;
            position: relative;
        }

        .form-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #1a2980;
            font-size: 18px;
            transition: all 0.3s;
        }

        .form-group input {
            width: 100%;
            padding: 15px;
            padding-left: 45px;
            border-radius: 8px;
            border: 1px solid #e1e5eb;
            background: #f8f9fa;
            color: #495057;
            font-size: 15px;
            font-weight: 400;
            transition: all 0.3s ease;
        }

        .form-group input::placeholder {
            color: #adb5bd;
        }

        .form-group input:focus {
            outline: none;
            background: #ffffff;
            border-color: #1a2980;
            box-shadow: 0 0 0 3px rgba(26, 41, 128, 0.1);
        }

        .form-group input:focus + i {
            color: #1a2980;
        }

        .login-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(to right, #1a2980, #26d0ce);
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(26, 41, 128, 0.2);
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(26, 41, 128, 0.3);
        }

        .login-btn:active {
            transform: translateY(0);
            box-shadow: 0 4px 8px rgba(26, 41, 128, 0.2);
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 13px;
            color: #6c757d;
            font-weight: 400;
        }

        @media (max-width: 992px) {
            .container {
                flex-direction: column;
            }
            .left, .right {
                flex: none;
                width: 100%;
            }
            .left {
                height: 40vh;
                min-height: 300px;
            }
            .right {
                height: 60vh;
                padding: 30px 20px;
            }
            .left img {
                width: 40%;
                max-width: 200px;
                margin-bottom: 15px;
            }
            .left h1 {
                font-size: 1.8rem;
            }
            .left p {
                font-size: 0.9rem;
                max-width: 90%;
            }
            .login-container {
                padding: 30px;
            }
        }

        @media (max-width: 576px) {
            .left {
                height: 35vh;
                min-height: 250px;
            }
            .right {
                height: 65vh;
                padding: 20px 15px;
            }
            .left img {
                width: 35%;
                max-width: 150px;
            }
            .left h1 {
                font-size: 1.6rem;
            }
            .left p {
                font-size: 0.85rem;
            }
            .welcome-text h2 {
                font-size: 22px;
            }
            .login-container {
                padding: 20px;
            }
            .form-group input {
                padding: 12px;
                padding-left: 40px;
            }
            .login-btn {
                padding: 12px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Left side with logo and introduction -->
    <div class="left">
        <div class="pattern-overlay"></div>
        
        <div class="left-content">
            <img src="../img/lg.png" alt="Kasir Management System Logo">
        </div>
    </div>

    <!-- Right side with reset password form -->
    <div class="right">
        <div class="login-container">
            <div class="welcome-text">
                <h2>Reset Password</h2>
                <p>Silakan masukkan password baru Anda</p>
            </div>
            
            <form method="POST" action="">
                <div class="form-group">
                    <input type="password" name="new_password" placeholder="Password baru" required>
                    <i class="fas fa-lock"></i>
                </div>
                <div class="form-group">
                    <input type="password" name="confirm_password" placeholder="Konfirmasi password" required>
                    <i class="fas fa-lock-open"></i>
                </div>
                <button type="submit" name="submit" class="login-btn">
                    Reset Password <i class="fas fa-arrow-right" style="margin-left: 8px;"></i>
                </button>
            </form>
            
            <div class="footer">
                &copy; 2025 Breakfast Management System. All rights reserved.
            </div>
        </div>
    </div>
</div>

</body>
</html>