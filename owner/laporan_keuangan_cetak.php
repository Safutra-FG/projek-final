<?php
include '../koneksi.php';

$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// --- Laporan Keuangan ---
$where_clause_keuangan = "WHERE t.status = 'lunas'";
if ($start_date && $end_date) {
    $start_date_safe = $koneksi->real_escape_string($start_date);
    $end_date_safe = $koneksi->real_escape_string($end_date);
    $where_clause_keuangan .= " AND DATE(t.tanggal) BETWEEN '$start_date_safe' AND '$end_date_safe'";
} elseif ($start_date) {
    $start_date_safe = $koneksi->real_escape_string($start_date);
    $where_clause_keuangan .= " AND DATE(t.tanggal) >= '$start_date_safe'";
} elseif ($end_date) {
    $end_date_safe = $koneksi->real_escape_string($end_date);
    $where_clause_keuangan .= " AND DATE(t.tanggal) <= '$end_date_safe'";
}

$sqlPendapatan = "SELECT SUM(total) AS total_pendapatan FROM transaksi t $where_clause_keuangan";
$resultPendapatan = $koneksi->query($sqlPendapatan);
$totalPendapatan = 0;
if ($resultPendapatan && $resultPendapatan->num_rows > 0) {
    $row = $resultPendapatan->fetch_assoc();
    $totalPendapatan = $row['total_pendapatan'];
}

$sqlTransaksi = "SELECT t.id_transaksi, t.jenis AS jenis_transaksi, t.total AS jumlah, t.tanggal AS tanggal_transaksi, c.nama_customer AS deskripsi 
                 FROM transaksi t 
                 JOIN customer c ON t.id_customer = c.id_customer 
                 $where_clause_keuangan 
                 ORDER BY t.tanggal DESC";
$resultTransaksi = $koneksi->query($sqlTransaksi);
$dataTransaksi = [];
if ($resultTransaksi && $resultTransaksi->num_rows > 0) {
    while ($row = $resultTransaksi->fetch_assoc()) {
        $dataTransaksi[] = $row;
    }
}

// --- Laporan Pesanan ---
$where_clause_pesanan = "WHERE 1=1";
if ($start_date && $end_date) {
    $where_clause_pesanan .= " AND DATE(s.tanggal) BETWEEN '$start_date_safe' AND '$end_date_safe'";
} elseif ($start_date) {
    $where_clause_pesanan .= " AND DATE(s.tanggal) >= '$start_date_safe'";
} elseif ($end_date) {
    $where_clause_pesanan .= " AND DATE(s.tanggal) <= '$end_date_safe'";
}

$sqlPesanan = "SELECT s.id_service, c.nama_customer, s.device, s.keluhan, s.status, s.tanggal, s.tanggal_selesai
               FROM service s
               JOIN customer c ON s.id_customer = c.id_customer
               $where_clause_pesanan
               ORDER BY s.tanggal DESC";
$resultPesanan = $koneksi->query($sqlPesanan);
$dataPesanan = [];
if ($resultPesanan && $resultPesanan->num_rows > 0) {
    while ($row = $resultPesanan->fetch_assoc()) {
        $dataPesanan[] = $row;
    }
}

// --- Laporan Sparepart ---
$sqlStokBarang = "SELECT id_barang, nama_barang, stok, harga FROM stok ORDER BY nama_barang ASC";
$resultStokBarang = $koneksi->query($sqlStokBarang);
$dataStokBarang = [];
if ($resultStokBarang && $resultStokBarang->num_rows > 0) {
    while ($row = $resultStokBarang->fetch_assoc()) {
        $dataStokBarang[] = $row;
    }
}

// Ambil data penggunaan service
$penggunaanService = [];
$sqlService = "SELECT ds.id_barang, COUNT(ds.id_barang) as jumlah 
               FROM detail_service ds 
               JOIN service s ON ds.id_service = s.id_service 
               WHERE ds.id_barang IS NOT NULL";
if ($start_date && $end_date) {
    $sqlService .= " AND DATE(s.tanggal) BETWEEN '$start_date_safe' AND '$end_date_safe'";
}
$sqlService .= " GROUP BY ds.id_barang";
$resultService = $koneksi->query($sqlService);
while($row = $resultService->fetch_assoc()) {
    $penggunaanService[$row['id_barang']] = (int)$row['jumlah'];
}

// Ambil data penjualan
$penjualanBarang = [];
$sqlPenjualan = "SELECT dt.id_barang, SUM(dt.jumlah) as jumlah 
                 FROM detail_transaksi dt 
                 JOIN transaksi t ON dt.id_transaksi = t.id_transaksi 
                 WHERE dt.id_barang IS NOT NULL";
if ($start_date && $end_date) {
    $sqlPenjualan .= " AND DATE(t.tanggal) BETWEEN '$start_date_safe' AND '$end_date_safe'";
}
$sqlPenjualan .= " GROUP BY dt.id_barang";
$resultPenjualan = $koneksi->query($sqlPenjualan);
while($row = $resultPenjualan->fetch_assoc()) {
    $penjualanBarang[$row['id_barang']] = (int)$row['jumlah'];
}

$koneksi->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Laporan Lengkap - Thar'z Computer</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px;
            font-size: 12px;
        }
        h2, h3 { 
            text-align: center; 
            margin: 20px 0;
        }
        h2 { font-size: 18px; }
        h3 { font-size: 14px; }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 10px;
            margin-bottom: 20px;
        }
        th, td { 
            border: 1px solid #333; 
            padding: 6px; 
            text-align: left; 
        }
        th { 
            background-color: #f2f2f2; 
            font-weight: bold;
        }
        .total { 
            text-align: right; 
            margin-top: 10px; 
            font-size: 14px;
            font-weight: bold;
        }
        .page-break {
            page-break-after: always;
        }
        @media print {
            button { display: none; }
        }
    </style>
</head>
<body onload="window.print()">

    <h2>Laporan Lengkap - Thar'z Computer</h2>
    <?php if ($start_date || $end_date): ?>
        <p><strong>Periode:</strong> <?php echo htmlspecialchars($start_date ?: '...'); ?> s/d <?php echo htmlspecialchars($end_date ?: '...'); ?></p>
    <?php endif; ?>

    <!-- Laporan Keuangan -->
    <h3>Laporan Keuangan</h3>
    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Jenis Transaksi</th>
                <th>Deskripsi</th>
                <th>Jumlah</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($dataTransaksi)): ?>
                <tr>
                    <td colspan="4" style="text-align:center;">Tidak ada data transaksi</td>
                </tr>
            <?php else: ?>
                <?php foreach ($dataTransaksi as $row): ?>
                    <tr>
                        <td><?php echo date('d-m-Y', strtotime($row['tanggal_transaksi'])); ?></td>
                        <td><?php echo htmlspecialchars($row['jenis_transaksi']); ?></td>
                        <td><?php echo htmlspecialchars($row['deskripsi']); ?></td>
                        <td>Rp <?php echo number_format($row['jumlah'], 0, ',', '.'); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <p class="total">Total Pendapatan: Rp <?php echo number_format($totalPendapatan, 0, ',', '.'); ?></p>

    <div class="page-break"></div>

    <!-- Laporan Pesanan -->
    <h3>Laporan Pesanan Servis</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Pelanggan</th>
                <th>Device</th>
                <th>Keluhan</th>
                <th>Status</th>
                <th>Tgl Masuk</th>
                <th>Tgl Selesai</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($dataPesanan)): ?>
                <tr>
                    <td colspan="7" style="text-align:center;">Tidak ada data pesanan</td>
                </tr>
            <?php else: ?>
                <?php foreach ($dataPesanan as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['id_service']); ?></td>
                        <td><?php echo htmlspecialchars($row['nama_customer']); ?></td>
                        <td><?php echo htmlspecialchars($row['device']); ?></td>
                        <td><?php echo htmlspecialchars($row['keluhan']); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($row['status'])); ?></td>
                        <td><?php echo date('d-m-Y', strtotime($row['tanggal'])); ?></td>
                        <td><?php echo $row['tanggal_selesai'] ? date('d-m-Y', strtotime($row['tanggal_selesai'])) : '-'; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="page-break"></div>

    <!-- Laporan Sparepart -->
    <h3>Laporan Stok dan Penggunaan Sparepart</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nama Barang</th>
                <th>Stok Tersedia</th>
                <th>Penggunaan Service</th>
                <th>Penjualan</th>
                <th>Total Penggunaan</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($dataStokBarang)): ?>
                <tr>
                    <td colspan="6" style="text-align:center;">Tidak ada data sparepart</td>
                </tr>
            <?php else: ?>
                <?php foreach ($dataStokBarang as $barang): ?>
                    <?php
                    $id_barang = $barang['id_barang'];
                    $jml_service = isset($penggunaanService[$id_barang]) ? $penggunaanService[$id_barang] : 0;
                    $jml_penjualan = isset($penjualanBarang[$id_barang]) ? $penjualanBarang[$id_barang] : 0;
                    $total_penggunaan = $jml_service + $jml_penjualan;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($barang['id_barang']); ?></td>
                        <td><?php echo htmlspecialchars($barang['nama_barang']); ?></td>
                        <td><?php echo htmlspecialchars($barang['stok']); ?></td>
                        <td><?php echo $jml_service; ?></td>
                        <td><?php echo $jml_penjualan; ?></td>
                        <td><?php echo $total_penggunaan; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <button onclick="window.print()">Cetak Ulang</button>
</body>
</html>
