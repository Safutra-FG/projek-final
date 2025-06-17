<?php
session_start();
include 'koneksi.php'; // Pastikan path ini benar
$id_transaksi_display = null;
$transaksi_info = null; // Untuk menyimpan data transaksi dan customer
$error_page = null;
$status_pembayaran = null;

if (isset($_GET['id_transaksi'])) {
    $id_transaksi_input = $_GET['id_transaksi'];
    $status_pembayaran = $_GET['status'] ?? null;

    // Ambil detail transaksi dan nama customer untuk ditampilkan
    // Asumsi id_transaksi di tabel transaksi adalah integer. Jika bukan, sesuaikan tipe di bind_param.
    // Dari gambar schema sebelumnya, transaksi.id_transaksi adalah int(4) AUTO_INCREMENT
    $sql_pesanan = "SELECT 
                        t.id_transaksi, 
                        t.tanggal, 
                        t.total, 
                        t.status,
                        c.nama_customer 
                    FROM 
                        transaksi t
                    JOIN 
                        customer c ON t.id_customer = c.id_customer
                    WHERE 
                        t.id_transaksi = ? AND t.jenis = 'penjualan'";
    
    $stmt = $koneksi->prepare($sql_pesanan);
    if ($stmt) {
        $stmt->bind_param("i", $id_transaksi_input); // "i" untuk integer
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $transaksi_info = $result->fetch_assoc();
            $id_transaksi_display = $transaksi_info['id_transaksi'];
        } else {
            $error_page = "Detail pesanan tidak ditemukan atau bukan transaksi penjualan.";
        }
        $stmt->close();
    } else {
        $error_page = "Gagal menyiapkan data pesanan: " . $koneksi->error;
    }
} else {
    $error_page = "Nomor pesanan tidak valid.";
}
// $koneksi->close(); // Sebaiknya ditutup di akhir halaman setelah semua potensi query selesai
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pesanan Berhasil - Thar'z Computer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .status-badge {
            font-size: 1.1em;
            padding: 0.5rem 1rem;
        }
    </style>
</head>
<body>
<div class="container mt-4 mb-5">
    <?php if ($error_page): ?>
        <div class="alert alert-danger text-center" role="alert">
            <h4 class="alert-heading">Terjadi Kesalahan!</h4>
            <p><?php echo htmlspecialchars($error_page); ?></p>
            <hr>
            <a href="index.php" class="btn btn-primary">Kembali ke Beranda</a>
        </div>
    <?php elseif ($id_transaksi_display && $transaksi_info): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-success text-white text-center">
                <h4 class="my-1">🎉 Pesanan Anda Berhasil Dibuat! 🎉</h4>
            </div>
            <div class="card-body p-4">
                <p class="lead text-center">Terima kasih, <?php echo htmlspecialchars($transaksi_info['nama_customer']); ?>!</p>
                <p class="text-center">Pesanan Anda dengan Nomor: <strong>#<?php echo htmlspecialchars($id_transaksi_display); ?></strong> telah kami terima pada tanggal <?php echo date('d M Y, H:i', strtotime($transaksi_info['tanggal'])); ?>.</p>
                
                <?php if ($status_pembayaran === 'menunggu_validasi' || $transaksi_info['status'] === 'menunggu validasi'): ?>
                    <div class="alert alert-warning text-center" role="alert">
                        <h5 class="alert-heading"><i class="bi bi-clock"></i> Status Pembayaran</h5>
                        <span class="badge bg-warning status-badge">Menunggu konfirmasi Admin</span>
                        <p class="mt-2 mb-0">Konfirmasi pembayaran Anda telah dikirim dan sedang menunggu konfirmasi dari admin. Kami akan segera memproses pesanan Anda setelah pembayaran divalidasi.</p>
                    </div>
                <?php endif; ?>
                
                <hr class="my-4">

                <div class="card mb-4">
                    <div class="card-header">
                        Ringkasan Tagihan
                    </div>
                    <div class="card-body">
                        <p class="fs-5"><strong>Nomor Pesanan:</strong> <?php echo htmlspecialchars($id_transaksi_display); ?></p>
                        <p class="fs-5"><strong>Nama Pemesan:</strong> <?php echo htmlspecialchars($transaksi_info['nama_customer']); ?></p>
                        <p class="fs-5"><strong>Total Tagihan:</strong> <span class="fw-bold text-danger fs-4">Rp <?php echo number_format($transaksi_info['total'], 0, ',', '.'); ?></span></p>
                    </div>
                </div>

                <div class="text-center mt-4 py-4 border-top">
                    <a href="index.php" class="btn btn-outline-secondary btn-lg">
                        <i class="bi bi-house"></i> Kembali ke Beranda
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning text-center" role="alert">
            Tidak ada informasi pesanan untuk ditampilkan. Mungkin sesi Anda berakhir atau ada kesalahan.
        </div>
        <div class="text-center mt-3">
            <a href="index.php" class="btn btn-primary">Kembali ke Beranda</a>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php $koneksi->close(); ?>
</body>
</html>