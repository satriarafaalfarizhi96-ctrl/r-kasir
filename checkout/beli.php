<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../koneksi.php';

if (!isset($_SESSION['username']) || $_SESSION['username'] === '') {
    header('Location: ../login.php');
    exit;
}

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$username = (string)$_SESSION['username'];

$stmt = $conn->prepare(
    'SELECT id_admin, gambar FROM admin WHERE username=? LIMIT 1'
);
$stmt->bind_param('s', $username);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$u) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

$idAdmin = (int)$u['id_admin'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pesanan_pembeli.php');
    exit;
}

// Ambil metode pembayaran dari form
$metode = strtolower(trim($_POST['metode'] ?? ''));

$metodeValid = ['cash', 'qris', 'bca'];

if (!in_array($metode, $metodeValid, true)) {
    echo '<script>alert("Metode pembayaran tidak valid.");location="pesanan_pembeli.php";</script>';
    exit;
}

$cart = $_SESSION['cart'] ?? [];

if (!$cart) {
    echo '<script>alert("Keranjang masih kosong.");location="transaksi.php";</script>';
    exit;
}

$conn->begin_transaction();

try {
    $items = [];
    $total = 0;

    // Periksa produk dan stok
    foreach ($cart as $pid => $qty) {
        $pid = (int)$pid;
        $qty = (int)$qty;

        if ($pid < 1 || $qty < 1) {
            throw new Exception('Data keranjang tidak valid.');
        }

        $st = $conn->prepare(
            'SELECT nama_produk,harga,stok FROM produk WHERE id_produk=? FOR UPDATE'
        );
        $st->bind_param('i', $pid);
        $st->execute();
        $p = $st->get_result()->fetch_assoc();
        $st->close();

        if (!$p) {
            throw new Exception('Salah satu produk tidak ditemukan.');
        }

        if ((int)$p['stok'] < $qty) {
            throw new Exception('Stok '.$p['nama_produk'].' tidak mencukupi.');
        }

        $sub = (int)$p['harga'] * $qty;
        $total += $sub;

        $items[] = [
            'id_produk' => $pid,
            'qty' => $qty,
            'harga' => (int)$p['harga'],
            'subtotal' => $sub
        ];
    }

    // Perhitungan pembayaran berdasarkan metode
    if ($metode === 'cash') {
        $bayar = filter_input(INPUT_POST, 'bayar', FILTER_VALIDATE_INT);

        if ($bayar === false || $bayar === null || $bayar < $total) {
            $conn->rollback();

            header(
                'Location: pesanan_pembeli.php?kurang=1&bayar=' .
                urlencode((string)($_POST['bayar'] ?? 0))
            );
            exit;
        }

        $kembali = $bayar - $total;

    } else {
        // BCA: minimal belanja Rp 10.000 (divalidasi di server)
        if ($metode === 'bca' && $total < 10000) {
            $conn->rollback();
            echo '<script>alert("Pembayaran via BCA minimal belanja Rp 10.000.");location="pesanan_pembeli.php?metode=bca";</script>';
            exit;
        }

        // QRIS dan BCA menggunakan nominal sesuai total pesanan
        $bayar = $total;
        $kembali = 0;
    }

    $nota = 'TRX' . date('YmdHis') .
            strtoupper(bin2hex(random_bytes(2)));

    // SIMPAN METODE PEMBAYARAN KE DATABASE
    $st = $conn->prepare(
        'INSERT INTO transaksi
        (nomor_nota,id_admin,total,metode_pembayaran,bayar,kembalian,tanggal)
        VALUES (?,?,?,?,?,?,NOW())'
    );

    $st->bind_param(
        'siisii',
        $nota,
        $idAdmin,
        $total,
        $metode,
        $bayar,
        $kembali
    );

    $st->execute();
    $transaksiId = $conn->insert_id;
    $st->close();

    // Simpan detail transaksi dan kurangi stok
    foreach ($items as $it) {

        $d = $conn->prepare(
            'INSERT INTO detail_transaksi
            (id_transaksi,id_produk,qty,harga_saat_ini,subtotal)
            VALUES (?,?,?,?,?)'
        );

        $d->bind_param(
            'iiiii',
            $transaksiId,
            $it['id_produk'],
            $it['qty'],
            $it['harga'],
            $it['subtotal']
        );

        $d->execute();
        $d->close();

        $up = $conn->prepare(
            'UPDATE produk SET stok=stok-?
            WHERE id_produk=? AND stok>=?'
        );

        $up->bind_param(
            'iii',
            $it['qty'],
            $it['id_produk'],
            $it['qty']
        );

        $up->execute();

        if ($up->affected_rows !== 1) {
            throw new Exception('Gagal memperbarui stok produk.');
        }

        $up->close();
    }

    $conn->commit();

    unset($_SESSION['cart']);

    // SIMPAN METODE PEMBAYARAN KE NOTA
    $_SESSION['last_receipt'] = [
        'nota' => $nota,
        'total' => $total,
        'bayar' => $bayar,
        'kembalian' => $kembali,
        'metode' => $metode,
        'tanggal' => date('d-m-Y H:i:s'),
        'id_transaksi' => $transaksiId
    ];

    header('Location: pesanan.php');
    exit;

} catch (Throwable $e) {

    $conn->rollback();

    $msg = htmlspecialchars(
        $e->getMessage(),
        ENT_QUOTES,
        'UTF-8'
    );

    echo '<script>alert("Transaksi gagal: '.$msg.'");location="pesanan_pembeli.php";</script>';
    exit;
}
?>