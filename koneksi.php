<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "kasir-sederhana"; // 

$conn = mysqli_connect($host, $user, $pass, $db);

if (!mysqli_connect_errno()) {
    mysqli_set_charset($conn, "utf8mb4");
} else {
    die("Koneksi database gagal: " . mysqli_connect_error());
}
?>