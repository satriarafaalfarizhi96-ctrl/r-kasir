<?php
// Pastikan file ../koneksi.php menghubungkan aplikasi ke database sarapan2.
include '../koneksi.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['username']) || $_SESSION['username'] === '') {
    header('Location: ../login.php');
    exit;
}

$username = $_SESSION['username'];
$gambar = 'default.png';

// Struktur tabel admin pada database sarapan2: username, gambar.
$stmtAdmin = $conn->prepare('SELECT gambar FROM admin WHERE username = ? LIMIT 1');
if ($stmtAdmin) {
    $stmtAdmin->bind_param('s', $username);
    $stmtAdmin->execute();
    $admin = $stmtAdmin->get_result()->fetch_assoc();
    if (!empty($admin['gambar'])) $gambar = $admin['gambar'];
    $stmtAdmin->close();
}

// Filter periode. Kolom tanggal pada sarapan2 bertipe DATETIME.
$currentYear = (int)date('Y');
$currentMonth = (int)date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : $currentYear;
$month = isset($_GET['month']) ? (int)$_GET['month'] : $currentMonth;
$week = isset($_GET['week']) ? (int)$_GET['week'] : 0;
$day = isset($_GET['day']) ? (int)$_GET['day'] : 0;

$years = [];
$resYears = $conn->query('SELECT DISTINCT YEAR(tanggal) AS tahun FROM transaksi WHERE tanggal IS NOT NULL ORDER BY tahun DESC');
if ($resYears) while ($r = $resYears->fetch_assoc()) if ($r['tahun']) $years[] = (int)$r['tahun'];
if (!$years) $years = [$currentYear];
if (!in_array($year, $years, true)) $year = $years[0];
if ($month < 1 || $month > 12) $month = $currentMonth;
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
if ($day < 0 || $day > $daysInMonth) $day = 0;
$maxWeek = (int)ceil($daysInMonth / 7);
if ($week < 0 || $week > $maxWeek) $week = 0;

$startDate = sprintf('%04d-%02d-01 00:00:00', $year, $month);
$endDate = date('Y-m-d H:i:s', strtotime($startDate . ' +1 month'));
$where = 'tanggal >= ? AND tanggal < ?';
$params = [$startDate, $endDate];
$types = 'ss';
if ($day > 0) {
    $where .= ' AND DAY(tanggal) = ?';
    $params[] = $day; $types .= 'i';
} elseif ($week > 0) {
    $startDay = (($week - 1) * 7) + 1;
    $endDay = min($week * 7, $daysInMonth);
    $where .= ' AND DAY(tanggal) BETWEEN ? AND ?';
    $params[] = $startDay; $params[] = $endDay; $types .= 'ii';
}

// Grafik harian (atau per jam jika satu hari dipilih).
if ($day > 0) {
    $sql = "SELECT HOUR(tanggal) AS periode, COALESCE(SUM(total),0) AS total_sales
            FROM transaksi WHERE $where GROUP BY HOUR(tanggal) ORDER BY periode";
} else {
    $sql = "SELECT DAY(tanggal) AS periode, COALESCE(SUM(total),0) AS total_sales
            FROM transaksi WHERE $where GROUP BY DAY(tanggal) ORDER BY periode";
}
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$labels = []; $salesData = []; $rowsReport = [];
while ($row = $result->fetch_assoc()) {
    $period = (int)$row['periode'];
    if ($day > 0) {
        $next = ($period + 1) % 24;
        $label = sprintf('%02d:00 - %02d:00', $period, $next);
    } else {
        $label = sprintf('%02d %s', $period, date('M', mktime(0, 0, 0, $month, 1, $year)));
    }
    $amount = (float)$row['total_sales'];
    $labels[] = $label; $salesData[] = $amount;
    $rowsReport[] = ['label' => $label, 'total' => $amount];
}
$stmt->close();

$totalSales = array_sum($salesData);
$activePeriods = count(array_filter($salesData, fn($v) => $v > 0));
$averageSales = $activePeriods ? $totalSales / $activePeriods : 0;
$peakIndex = $salesData ? array_search(max($salesData), $salesData, true) : false;
$peakLabel = ($peakIndex !== false && $salesData[$peakIndex] > 0) ? $labels[$peakIndex] : 'Tidak ada transaksi';
$peakValue = ($peakIndex !== false) ? $salesData[$peakIndex] : 0;

$monthName = date('F', mktime(0, 0, 0, $month, 1, $year));
if ($day > 0) $reportTitle = 'Laporan Harian: ' . date('d F Y', mktime(0, 0, 0, $month, $day, $year));
elseif ($week > 0) $reportTitle = "Laporan Minggu $week: " . (($week - 1) * 7 + 1) . ' - ' . min($week * 7, $daysInMonth) . " $monthName $year";
else $reportTitle = "Laporan Bulanan: $monthName $year";

// Ringkasan jumlah transaksi pada periode terpilih.
$sqlCount = "SELECT COUNT(*) AS jumlah FROM transaksi WHERE $where";
$stmtCount = $conn->prepare($sqlCount);
$stmtCount->bind_param($types, ...$params);
$stmtCount->execute();
$transactionCount = (int)$stmtCount->get_result()->fetch_assoc()['jumlah'];
$stmtCount->close();

// ===================== REKAP PER METODE PEMBAYARAN =====================
// Memerlukan kolom transaksi.metode_pembayaran (ENUM 'cash','qris','bca').
$metodeInfo = [
    'cash' => ['label' => 'Cash', 'icon' => 'fa-money-bill-wave', 'color' => '#0b2559'],
    'qris' => ['label' => 'QRIS', 'icon' => 'fa-qrcode',          'color' => '#38bdf8'],
    'bca'  => ['label' => 'BCA',  'icon' => 'fa-university',      'color' => '#1d4ed8'],
];
$metodeData = [];
foreach ($metodeInfo as $k => $_) $metodeData[$k] = ['jumlah' => 0, 'total' => 0.0];

$hasMetodeCol = false;
$chkCol = $conn->query("SHOW COLUMNS FROM transaksi LIKE 'metode_pembayaran'");
if ($chkCol && $chkCol->num_rows > 0) $hasMetodeCol = true;

if ($hasMetodeCol) {
    $sqlMetode = "SELECT LOWER(metode_pembayaran) AS metode, COUNT(*) AS jumlah, COALESCE(SUM(total),0) AS total_sales
                  FROM transaksi WHERE $where GROUP BY LOWER(metode_pembayaran)";
    $stmtMetode = $conn->prepare($sqlMetode);
    $stmtMetode->bind_param($types, ...$params);
    $stmtMetode->execute();
    $resMetode = $stmtMetode->get_result();
    while ($mr = $resMetode->fetch_assoc()) {
        $k = $mr['metode'];
        if (isset($metodeData[$k])) {
            $metodeData[$k]['jumlah'] += (int)$mr['jumlah'];
            $metodeData[$k]['total']  += (float)$mr['total_sales'];
        }
    }
    $stmtMetode->close();
}
$metodeTotalAll = array_sum(array_column($metodeData, 'total'));
$metodeCountAll = array_sum(array_column($metodeData, 'jumlah'));
$metodeLabels = array_column($metodeInfo, 'label');
$metodeTotals = array_values(array_column($metodeData, 'total'));
$metodeColors = array_values(array_column($metodeInfo, 'color'));

// Data perbandingan mingguan untuk tampilan bulanan.
$weeklyLabels = []; $weeklySales = [];
if ($day === 0 && $week === 0) {
    $weeklySql = "SELECT CEIL(DAY(tanggal)/7) AS minggu, COALESCE(SUM(total),0) AS total_sales
                  FROM transaksi WHERE tanggal >= ? AND tanggal < ?
                  GROUP BY CEIL(DAY(tanggal)/7) ORDER BY minggu";
    $stmtWeekly = $conn->prepare($weeklySql);
    $stmtWeekly->bind_param('ss', $startDate, $endDate);
    $stmtWeekly->execute();
    $weeklyResult = $stmtWeekly->get_result();
    while ($wr = $weeklyResult->fetch_assoc()) {
        $weeklyLabels[] = 'Minggu ' . (int)$wr['minggu'];
        $weeklySales[] = (float)$wr['total_sales'];
    }
    $stmtWeekly->close();
}

function rupiah($value) { return 'Rp ' . number_format((float)$value, 0, ',', '.'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laporan - Sarapan</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700&family=Nunito+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
<style>
:root{--navy:#0b2559;--deep:#081c44;--blue:#1d4ed8;--sky:#38bdf8;--ice:#eef4ff;--ink:#14213d;--muted:#667799;--line:#d5def0;--shadow:0 12px 32px rgba(2,10,40,.16)}
*{box-sizing:border-box;margin:0;padding:0}body{font-family:'Nunito Sans',sans-serif;color:var(--ink);background:var(--navy);min-height:100vh}.container{display:flex;min-height:100vh}
nav{width:250px;position:fixed;inset:0 auto 0 0;background:var(--deep);padding:24px 12px;z-index:5;border-right:1px solid rgba(56,189,248,.18)}.navbar ul{list-style:none}.navbar li{padding:3px 0}.navbar a{display:flex;align-items:center;padding:12px 15px;border-radius:12px;color:#cfe0ff;text-decoration:none;font-weight:700;font-size:14px}.navbar a i{width:24px;margin-right:10px}.navbar a:hover,.navbar a.active{background:var(--blue);color:#fff}.profile-container{display:flex;align-items:center;gap:12px;padding:12px;background:rgba(255,255,255,.07);border:1px solid rgba(56,189,248,.2);border-radius:14px;margin-bottom:14px}.admin-avatar{width:44px;height:44px;border-radius:50%;object-fit:cover;background:#fff}.admin-name{color:#fff;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block}
.main{position:relative;flex:1;margin-left:250px;padding:30px;min-width:0;overflow:hidden}.main:before{content:'';position:absolute;width:550px;height:550px;right:-220px;top:-260px;border:70px solid rgba(56,189,248,.12);border-radius:50%;pointer-events:none}.main>*{position:relative;z-index:1}.panel,.main-top,.summary-card,.chart-container,.table-container{background:#fff;border-radius:18px;box-shadow:var(--shadow)}.main-top{padding:22px 26px;margin-bottom:20px;border-left:7px solid var(--sky);display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}.main-top h1{font:700 21px Sora;color:var(--navy)}.date-info{color:var(--muted);font-weight:700}.panel{padding:20px 24px;margin-bottom:20px}.filter-title,.chart-title{font:700 16px Sora;color:var(--navy);margin-bottom:14px}.filter-form{display:grid;grid-template-columns:repeat(4,minmax(130px,1fr));gap:14px}.filter-group{display:flex;flex-direction:column;gap:6px}.filter-label{font-size:13px;font-weight:700;color:var(--muted)}.filter-select{height:44px;padding:0 12px;border:1px solid var(--line);border-radius:10px;background:var(--ice);font:600 14px 'Nunito Sans';color:var(--ink)}.dashboard-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px;margin-bottom:20px}.summary-card{padding:20px}.card-title{font-size:13px;font-weight:700;color:var(--muted);margin-bottom:12px}.card-value{font:700 21px Sora;color:var(--navy);overflow-wrap:anywhere}.card-subtitle{font-size:12px;color:var(--muted);margin-top:7px}.action-buttons{display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap}.btn{border:0;border-radius:10px;padding:13px 20px;font:700 14px 'Nunito Sans';cursor:pointer}.btn-primary{background:var(--blue);color:#fff}.btn-secondary{background:#fff;color:var(--blue)}.chart-section{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px}.chart-container,.table-container{padding:22px;min-width:0}.chart-canvas-container{height:300px;position:relative}.table-container{overflow-x:auto}.table{width:100%;border-collapse:collapse}.table th{background:var(--ice);color:var(--navy);text-align:left;font-size:13px;padding:13px}.table td{padding:13px;border-bottom:1px solid var(--line);font-size:14px}.table tfoot th{background:var(--navy);color:#fff}.empty{text-align:center;color:var(--muted);padding:20px!important}

/* Rekap metode pembayaran */
.section-label{font:700 15px Sora;color:#fff;margin:6px 0 12px;display:flex;align-items:center;gap:10px}
.method-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;margin-bottom:20px}
.method-card{display:flex;gap:16px;align-items:center}
.m-ic{width:52px;height:52px;flex:none;border-radius:14px;display:grid;place-items:center;font-size:22px;color:#fff}
.m-body{min-width:0}.m-body .card-title{margin-bottom:6px}
.m-count{display:inline-block;margin-top:8px;padding:3px 10px;border-radius:999px;background:var(--ice);color:var(--blue);font-size:12px;font-weight:800}
.dot{display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:8px}
.notice-col{padding:16px 20px;border-radius:14px;background:#fff3cd;color:#664d03;margin-bottom:20px;font-size:14px;line-height:1.5}
.notice-col code{display:block;margin-top:8px;padding:10px 12px;background:#fffbeb;border-radius:8px;font:600 12.5px 'Courier New',monospace;overflow-x:auto;white-space:pre}

@media(max-width:1100px){.dashboard-grid{grid-template-columns:repeat(2,1fr)}.chart-section{grid-template-columns:1fr}.method-grid{grid-template-columns:1fr}}@media(max-width:850px){.container{display:block}nav{position:relative;width:100%;height:auto}.navbar ul{display:flex;flex-wrap:wrap}.profile-bar{width:100%}.main{margin-left:0;padding:18px}.filter-form{grid-template-columns:repeat(2,1fr)}}@media(max-width:520px){.dashboard-grid,.filter-form{grid-template-columns:1fr}.main-top h1{font-size:17px}}
@media print{body{background:#fff}.main{margin:0;padding:0}.main:before,nav,.filter-controls,.action-buttons{display:none!important}.main-top,.summary-card,.chart-container,.table-container{box-shadow:none;border:1px solid #ddd;break-inside:avoid}.main-top h1{color:#000}.section-label{color:#000}.chart-section{grid-template-columns:1fr 1fr}.chart-canvas-container{height:260px}.print-only{display:block!important}}
</style>
</head>
<body>
<div class="container">
<nav><div class="navbar"><ul>
<li class="profile-bar"><div class="profile-container"><img class="admin-avatar" src="../uploads/<?= htmlspecialchars($gambar, ENT_QUOTES, 'UTF-8'); ?>" alt="Foto admin"><span class="admin-name"><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></span></div></li>
<li><a class="active" href="../laporan/laporan.php"><i class="fas fa-chart-bar"></i>Laporan</a></li>
<li><a href="../category/categori.php"><i class="fas fa-tags"></i>Category</a></li>
<li><a href="../produk/Tproduk.php"><i class="fas fa-box"></i>Produk</a></li>
<li><a href="../admin/admin.php"><i class="fas fa-user-shield"></i>Admin</a></li>
<li><a href="../checkout/transaksi.php"><i class="fas fa-receipt"></i>Transaksi</a></li>
<li><a href="../logout/Tlogout.php"><i class="fas fa-sign-out-alt"></i>Logout</a></li>
</ul></div></nav>
<main class="main">
<div class="main-top"><h1><?= htmlspecialchars($reportTitle, ENT_QUOTES, 'UTF-8'); ?></h1><div class="date-info"><i class="far fa-calendar-alt"></i> <?= date('l, d F Y'); ?></div></div>
<div class="panel filter-controls"><div class="filter-title"><i class="fas fa-filter"></i> Filter Laporan</div>
<form class="filter-form" method="GET">
<div class="filter-group"><label class="filter-label" for="year">Tahun</label><select class="filter-select" id="year" name="year" onchange="this.form.submit()"><?php foreach($years as $y): ?><option value="<?= $y ?>" <?= $year===$y?'selected':'' ?>><?= $y ?></option><?php endforeach; ?></select></div>
<div class="filter-group"><label class="filter-label" for="month">Bulan</label><select class="filter-select" id="month" name="month" onchange="this.form.submit()"><?php for($m=1;$m<=12;$m++): ?><option value="<?= $m ?>" <?= $month===$m?'selected':'' ?>><?= date('F',mktime(0,0,0,$m,1)) ?></option><?php endfor; ?></select></div>
<div class="filter-group"><label class="filter-label" for="week">Minggu</label><select class="filter-select" id="week" name="week" onchange="this.form.submit()"><option value="0">Semua Minggu</option><?php for($w=1;$w<=$maxWeek;$w++): ?><option value="<?= $w ?>" <?= $week===$w?'selected':'' ?>>Minggu <?= $w ?> (<?= (($w-1)*7+1) ?>-<?= min($w*7,$daysInMonth) ?>)</option><?php endfor; ?></select></div>
<div class="filter-group"><label class="filter-label" for="day">Hari</label><select class="filter-select" id="day" name="day" onchange="this.form.submit()"><option value="0">Semua Hari</option><?php for($d=1;$d<=$daysInMonth;$d++): ?><option value="<?= $d ?>" <?= $day===$d?'selected':'' ?>><?= date('d M (l)',mktime(0,0,0,$month,$d,$year)) ?></option><?php endfor; ?></select></div>
</form></div>
<div class="dashboard-grid">
<div class="summary-card"><div class="card-title">Total Penjualan</div><div class="card-value"><?= rupiah($totalSales) ?></div><div class="card-subtitle"><?= htmlspecialchars($reportTitle) ?></div></div>
<div class="summary-card"><div class="card-title">Jumlah Transaksi</div><div class="card-value"><?= number_format($transactionCount,0,',','.') ?></div><div class="card-subtitle">Transaksi pada periode terpilih</div></div>
<div class="summary-card"><div class="card-title"><?= $day>0?'Rata-rata per Jam':'Rata-rata per Hari' ?></div><div class="card-value"><?= rupiah($averageSales) ?></div><div class="card-subtitle">Dihitung dari periode yang memiliki transaksi</div></div>
<div class="summary-card"><div class="card-title">Waktu Terpadat</div><div class="card-value"><?= htmlspecialchars($peakLabel) ?></div><div class="card-subtitle"><?= rupiah($peakValue) ?></div></div>
</div>

<!-- Rekap per metode pembayaran -->
<div class="section-label"><i class="fas fa-wallet"></i> Penjualan per Metode Pembayaran</div>
<?php if(!$hasMetodeCol): ?>
<div class="notice-col"><b>Kolom metode pembayaran belum tersedia.</b> Jalankan perintah SQL berikut di phpMyAdmin agar rekap per metode dapat ditampilkan:
<code>ALTER TABLE transaksi
  ADD COLUMN metode_pembayaran ENUM('cash','qris','bca') NOT NULL DEFAULT 'cash' AFTER total;</code></div>
<?php else: ?>
<div class="method-grid">
<?php foreach($metodeInfo as $k=>$mi): ?>
 <div class="summary-card method-card">
  <div class="m-ic" style="background:<?= $mi['color'] ?>"><i class="fas <?= $mi['icon'] ?>"></i></div>
  <div class="m-body">
   <div class="card-title"><?= htmlspecialchars($mi['label']) ?></div>
   <div class="card-value"><?= rupiah($metodeData[$k]['total']) ?></div>
   <span class="m-count"><?= number_format($metodeData[$k]['jumlah'],0,',','.') ?> transaksi</span>
  </div>
 </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<div class="action-buttons"><button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Cetak Laporan</button><button class="btn btn-secondary" onclick="exportToExcel()"><i class="fas fa-file-excel"></i> Export Excel</button></div>
<section id="print-section">
<div class="chart-section">
<div class="chart-container"><div class="chart-title"><i class="fas fa-chart-pie"></i> Distribusi Penjualan</div><div class="chart-canvas-container"><canvas id="salesPieChart"></canvas></div></div>
<div class="chart-container"><div class="chart-title"><i class="fas fa-chart-bar"></i> Grafik Penjualan</div><div class="chart-canvas-container"><canvas id="salesBarChart"></canvas></div></div>
</div>
<div class="table-container"><div class="chart-title">Rincian Penjualan</div>
<table class="table exportable" id="reportTable"><thead><tr><th>No</th><th>Periode</th><th>Total Penjualan</th><th>Persentase</th></tr></thead><tbody>
<?php if (!$rowsReport): ?><tr><td colspan="4" class="empty">Tidak ada transaksi pada periode ini</td></tr><?php else: foreach($rowsReport as $i=>$item): $percentage=$totalSales>0?($item['total']/$totalSales)*100:0; ?><tr><td><?= $i+1 ?></td><td><?= htmlspecialchars($item['label']) ?></td><td><?= rupiah($item['total']) ?></td><td><?= number_format($percentage,2,',','.') ?>%</td></tr><?php endforeach; endif; ?>
</tbody><?php if($totalSales>0): ?><tfoot><tr><th colspan="2">Total</th><th><?= rupiah($totalSales) ?></th><th>100%</th></tr></tfoot><?php endif; ?></table>
</div>

<?php if($hasMetodeCol): ?>
<div class="chart-section" style="margin-top:20px">
<div class="chart-container"><div class="chart-title"><i class="fas fa-chart-pie"></i> Komposisi Metode Pembayaran</div><div class="chart-canvas-container"><canvas id="methodChart"></canvas></div></div>
<div class="table-container"><div class="chart-title">Rincian per Metode Pembayaran</div>
<table class="table exportable" id="methodTable"><thead><tr><th>Metode</th><th>Transaksi</th><th>Total Penjualan</th><th>Persentase</th></tr></thead><tbody>
<?php foreach($metodeInfo as $k=>$mi): $pm=$metodeTotalAll>0?($metodeData[$k]['total']/$metodeTotalAll)*100:0; ?>
<tr><td><span class="dot" style="background:<?= $mi['color'] ?>"></span><?= htmlspecialchars($mi['label']) ?></td><td><?= number_format($metodeData[$k]['jumlah'],0,',','.') ?></td><td><?= rupiah($metodeData[$k]['total']) ?></td><td><?= number_format($pm,2,',','.') ?>%</td></tr>
<?php endforeach; ?>
</tbody><?php if($metodeTotalAll>0): ?><tfoot><tr><th>Total</th><th><?= number_format($metodeCountAll,0,',','.') ?></th><th><?= rupiah($metodeTotalAll) ?></th><th>100%</th></tr></tfoot><?php endif; ?></table>
</div>
</div>
<?php endif; ?>

<?php if ($day === 0 && $week === 0): ?>
<div class="chart-container" style="margin-top:20px"><div class="chart-title"><i class="fas fa-calendar-week"></i> Perbandingan Mingguan</div><div class="chart-canvas-container"><canvas id="weeklyComparisonChart"></canvas></div></div>
<?php endif; ?>
</section>
</main></div>
<script>
const labels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;
const salesData = <?= json_encode($salesData) ?>;
const weeklyLabels = <?= json_encode($weeklyLabels, JSON_UNESCAPED_UNICODE) ?>;
const weeklySales = <?= json_encode($weeklySales) ?>;
const methodLabels = <?= json_encode($metodeLabels, JSON_UNESCAPED_UNICODE) ?>;
const methodTotals = <?= json_encode($metodeTotals) ?>;
const methodColors = <?= json_encode($metodeColors) ?>;
const colors = ['#0b2559','#1d4ed8','#38bdf8','#1739a8','#7dd3fc','#0d47a1','#1976d2','#2196f3','#42a5f5','#64b5f6'];
const money = v => new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:0}).format(v);
if (window.ChartDataLabels) Chart.register(ChartDataLabels);
Chart.defaults.font.family = "'Nunito Sans', sans-serif";
new Chart(document.getElementById('salesPieChart'),{type:'pie',data:{labels,datasets:[{data:salesData,backgroundColor:colors,borderColor:'#fff',borderWidth:2}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'right'},datalabels:{color:'#fff',formatter:v=>v>0?(salesData.reduce((a,b)=>a+b,0)>0?(v/salesData.reduce((a,b)=>a+b,0)*100).toFixed(1)+'%':'') : ''},tooltip:{callbacks:{label:c=>`${c.label}: ${money(c.raw)}`}}}}});
new Chart(document.getElementById('salesBarChart'),{type:'bar',data:{labels,datasets:[{label:'Penjualan',data:salesData,backgroundColor:'#1d4ed8',borderColor:'#0b2559',borderWidth:1,borderRadius:6}]},options:{responsive:true,maintainAspectRatio:false,scales:{y:{beginAtZero:true,ticks:{callback:v=>money(v)}},x:{grid:{display:false}}},plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>money(c.raw)}}}}});
if (document.getElementById('methodChart')) {
 const mSum = methodTotals.reduce((a,b)=>a+b,0);
 new Chart(document.getElementById('methodChart'),{type:'doughnut',data:{labels:methodLabels,datasets:[{data:methodTotals,backgroundColor:methodColors,borderColor:'#fff',borderWidth:2}]},options:{responsive:true,maintainAspectRatio:false,cutout:'55%',plugins:{legend:{position:'right'},datalabels:{color:'#fff',formatter:v=>(v>0&&mSum>0)?(v/mSum*100).toFixed(1)+'%':''},tooltip:{callbacks:{label:c=>`${c.label}: ${money(c.raw)}`}}}}});
}
if (document.getElementById('weeklyComparisonChart')) {
 new Chart(document.getElementById('weeklyComparisonChart'),{type:'bar',data:{labels:weeklyLabels,datasets:[{label:'Total Penjualan Mingguan',data:weeklySales,backgroundColor:'#38bdf8',borderColor:'#0b2559',borderWidth:1,borderRadius:6}]},options:{responsive:true,maintainAspectRatio:false,scales:{y:{beginAtZero:true,ticks:{callback:v=>money(v)}},x:{grid:{display:false}}},plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>money(c.raw)}}}}});
}
function exportToExcel(){
 let csv=[];
 document.querySelectorAll('table.exportable').forEach((table,idx)=>{
  if(idx>0) csv.push('');
  table.querySelectorAll('tr').forEach(tr=>{let cells=[...tr.querySelectorAll('th,td')].map(td=>'"'+td.innerText.replace(/"/g,'""')+'"');csv.push(cells.join(','));});
 });
 const blob=new Blob(['\ufeff'+csv.join('\r\n')],{type:'text/csv;charset=utf-8;'});const link=document.createElement('a');
 link.href=URL.createObjectURL(blob);link.download='<?= preg_replace('/[^A-Za-z0-9_-]/','_', $reportTitle) ?>.csv';document.body.appendChild(link);link.click();link.remove();URL.revokeObjectURL(link.href);
}
</script>
</body></html>