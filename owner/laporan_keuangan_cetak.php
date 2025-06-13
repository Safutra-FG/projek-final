<?php
include '../koneksi.php';

$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$where_clause = "WHERE s.status = 'Selesai'";
if ($start_date && $end_date) {
    $start_date_safe = $koneksi->real_escape_string($start_date);
    $end_date_safe = $koneksi->real_escape_string($end_date);
    $where_clause .= " AND DATE(s.tanggal_selesai) BETWEEN '$start_date_safe' AND '$end_date_safe'";
} elseif ($start_date) {
    $start_date_safe = $koneksi->real_escape_string($start_date);
    $where_clause .= " AND DATE(s.tanggal_selesai) >= '$start_date_safe'";
} elseif ($end_date) {
    $end_date_safe = $koneksi->real_escape_string($end_date);
    $where_clause .= " AND DATE(s.tanggal_selesai) <= '$end_date_safe'";
}

$sqlPendapatan = "SELECT SUM(estimasi_harga) AS total_pendapatan FROM service s $where_clause";
$resultPendapatan = $koneksi->query($sqlPendapatan);
$totalPendapatan = 0;
if ($resultPendapatan && $resultPendapatan->num_rows > 0) {
    $row = $resultPendapatan->fetch_assoc();
    $totalPendapatan = $row['total_pendapatan'];
}

$sqlTransaksi = "SELECT
    s.id_service AS id_transaksi,
    c.nama_customer AS deskripsi,
    s.estimasi_harga AS jumlah,
    s.tanggal_selesai AS tanggal_transaksi,
    'Pendapatan Servis' AS jenis_transaksi
FROM service s
JOIN customer c ON s.id_customer = c.id_customer
$where_clause
ORDER BY s.tanggal_selesai ASC";
$resultTransaksi = $koneksi->query($sqlTransaksi);
$dataTransaksi = [];
if ($resultTransaksi && $resultTransaksi->num_rows > 0) {
    while ($row = $resultTransaksi->fetch_assoc()) {
        $dataTransaksi[] = $row;
    }
}
$koneksi->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Laporan Keuangan</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h2 { text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #333; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .total { text-align: right; margin-top: 20px; font-size: 18px; }
        @media print {
            button { display: none; }
        }
    </style>
</head>
<body onload="window.print()">

    <h2>Laporan Keuangan - Thar'z Computer</h2>
    <?php if ($start_date || $end_date): ?>
        <p><strong>Periode:</strong> <?php echo htmlspecialchars($start_date ?: '...'); ?> s/d <?php echo htmlspecialchars($end_date ?: '...'); ?></p>
    <?php endif; ?>

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
                        <td>Servis untuk <?php echo htmlspecialchars($row['deskripsi']); ?></td>
                        <td>Rp <?php echo number_format($row['jumlah'], 0, ',', '.'); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <p class="total"><strong>Total Pendapatan:</strong> Rp <?php echo number_format($totalPendapatan, 0, ',', '.'); ?></p>
    <button onclick="window.print()">Cetak Ulang</button>
    <a href="laporan_keuangan.php" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"> Kembali</a>
</body>
</html>
