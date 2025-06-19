<?php
session_start();
include '../koneksi.php';

if (!isset($_GET['id_service']) || !is_numeric($_GET['id_service'])) {
    die('ID Service tidak valid.');
}
$id_service = intval($_GET['id_service']);

// Ambil data service utama
$stmt = $koneksi->prepare("SELECT s.*, c.nama_customer, c.no_telepon, c.email FROM service s LEFT JOIN customer c ON s.id_customer = c.id_customer WHERE s.id_service = ?");
$stmt->bind_param("i", $id_service);
$stmt->execute();
$result = $stmt->get_result();
$service = $result->fetch_assoc();
$stmt->close();

if (!$service) {
    die('Data service tidak ditemukan.');
}

// Ambil detail service
$stmt_detail = $koneksi->prepare("SELECT ds.*, b.nama_barang, j.jenis_jasa FROM detail_service ds LEFT JOIN stok b ON ds.id_barang = b.id_barang LEFT JOIN jasa j ON ds.id_jasa = j.id_jasa WHERE ds.id_service = ? ORDER BY ds.id_ds ASC");
$stmt_detail->bind_param("i", $id_service);
$stmt_detail->execute();
$result_detail = $stmt_detail->get_result();
$details = [];
$total = 0;
while ($row = $result_detail->fetch_assoc()) {
    $details[] = $row;
    $total += $row['total'];
}
$stmt_detail->close();

$koneksi->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Nota Service</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; background: #fff; color: #222; }
        .nota-container { max-width: 600px; margin: 30px auto; background: #fff; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 8px #eee; padding: 32px; }
        .nota-header { text-align: center; margin-bottom: 24px; }
        .nota-header img { width: 60px; margin-bottom: 8px; }
        .nota-title { font-size: 1.5em; font-weight: bold; margin-bottom: 4px; }
        .nota-info, .nota-customer { margin-bottom: 16px; }
        .nota-info table, .nota-customer table { width: 100%; font-size: 1em; }
        .nota-info td, .nota-customer td { padding: 2px 0; }
        .nota-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .nota-table th, .nota-table td { border: 1px solid #ccc; padding: 8px; text-align: left; font-size: 0.98em; }
        .nota-table th { background: #f5f5f5; }
        .nota-total { text-align: right; font-size: 1.1em; font-weight: bold; }
        .nota-footer { margin-top: 24px; text-align: center; font-size: 0.95em; color: #888; }
        .print-btn { display: block; margin: 20px auto 0 auto; padding: 10px 28px; background: #2563eb; color: #fff; border: none; border-radius: 5px; font-size: 1em; cursor: pointer; }
        @media print { .print-btn { display: none; } .nota-container { box-shadow: none; border: none; } }
    </style>
</head>
<body>

    <div class="nota-container">
        <div class="nota-header">
            <img src="../icons/logo.png" alt="Logo Toko">
            <div class="nota-title">Thar'z Computer</div>
            <div>Jl. Contoh Alamat No. 123, Kota Contoh</div>
            <div>Telp: 0812-3456-7890</div>
        </div>
        <div class="nota-info">
            <table>
                <tr><td><b>No. Service</b></td><td>: <?php echo htmlspecialchars($service['id_service']); ?></td></tr>
                <tr><td><b>Tanggal Masuk</b></td><td>: <?php echo date('d M Y', strtotime($service['tanggal'])); ?></td></tr>
                <tr><td><b>Device</b></td><td>: <?php echo htmlspecialchars($service['device']); ?></td></tr>
            </table>
        </div>
        <div class="nota-customer">
            <table>
                <tr><td><b>Nama Customer</b></td><td>: <?php echo htmlspecialchars($service['nama_customer']); ?></td></tr>
                <tr><td><b>No. Telepon</b></td><td>: <?php echo htmlspecialchars($service['no_telepon']); ?></td></tr>
                <tr><td><b>Email</b></td><td>: <?php echo htmlspecialchars($service['email']); ?></td></tr>
            </table>
        </div>
        <table class="nota-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Barang</th>
                    <th>Jasa</th>
                    <th>Deskripsi/Tindakan</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($details) > 0): $no=1; foreach ($details as $d): ?>
                <tr>
                    <td><?php echo $no++; ?></td>
                    <td><?php echo htmlspecialchars($d['nama_barang'] ?: '-'); ?></td>
                    <td><?php echo htmlspecialchars($d['jenis_jasa'] ?: '-'); ?></td>
                    <td><?php echo htmlspecialchars($d['kerusakan']); ?></td>
                    <td>Rp <?php echo number_format($d['total'], 0, ',', '.'); ?></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="5" style="text-align:center;">Belum ada detail service.</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="nota-total">Total Biaya Service</td>
                    <td class="nota-total">Rp <?php echo number_format($total, 0, ',', '.'); ?></td>
                </tr>
            </tfoot>
        </table>
        <button class="print-btn" onclick="window.print()">Cetak Ulang Nota</button>
        <div class="nota-footer">
            Terima kasih telah mempercayakan service di Thar'z Computer.<br>
            Barang yang sudah diambil tidak dapat diklaim ulang.
        </div>
    </div>
    <script>
        window.onload = function () {
            window.print();
        }
    </script>
</body>
</html> 