<?php
session_start();
include 'koneksi.php'; // Pastikan path ini benar
$id_transaksi_display = null;
$transaksi_info = null; // Untuk menyimpan data transaksi dan customer
$error_page = null;

if (isset($_GET['id_transaksi'])) {
    $id_transaksi_input = $_GET['id_transaksi'];

    // Ambil detail transaksi dan nama customer untuk ditampilkan
    // Asumsi id_transaksi di tabel transaksi adalah integer. Jika bukan, sesuaikan tipe di bind_param.
    // Dari gambar schema sebelumnya, transaksi.id_transaksi adalah int(4) AUTO_INCREMENT
    $sql_pesanan = "SELECT 
                        t.id_transaksi, 
                        t.tanggal, 
                        t.total, 
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
    <style>
        .payment-method-section {
            margin-bottom: 1.5rem;
        }
        .important-note {
            color: #dc3545; 
            font-weight: bold;
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
                
                <hr class="my-4">

                <div class="card mb-4">
                    <div class="card-header">
                        Ringkasan Tagihan
                    </div>
                    <div class="card-body">
                        <p class="fs-5"><strong>Nomor Pesanan:</strong> <?php echo htmlspecialchars($id_transaksi_display); ?></p>
                        <p class="fs-5"><strong>Nama Pemesan:</strong> <?php echo htmlspecialchars($transaksi_info['nama_customer']); ?></p>
                        <p class="fs-5"><strong>Total Tagihan:</strong> <span class="fw-bold text-danger fs-4">Rp <?php echo number_format($transaksi_info['total'], 0, ',', '.'); ?></span></p>
                        <p class="text-muted small">Rincian barang dapat dilihat pada email konfirmasi pesanan (jika ada fitur email) atau silakan simpan nomor pesanan ini.</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="my-1"><i class="bi bi-credit-card"></i> Instruksi Pembayaran</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-center mb-3">Silakan selesaikan pembayaran Anda sejumlah <strong>Rp <?php echo number_format($transaksi_info['total'], 0, ',', '.'); ?></strong> melalui salah satu metode berikut:</p>

                        <div class="payment-method-section p-3 border rounded">
                            <h5 class="fw-semibold"><i class="bi bi-shop"></i> 1. Bayar Langsung di Toko</h5>
                            <p>Anda dapat melakukan pembayaran secara tunai atau metode lain yang tersedia di toko kami:</p>
                            <ul class="list-unstyled ps-3">
                                <li><strong>Alamat Toko:</strong> <strong>[WAJIB ISI: Alamat Lengkap Toko Thar'z Computer Anda]</strong></li>
                                <li><strong>Jam Operasional:</strong> [Contoh: Senin - Sabtu, pukul 09:00 - 17:00 WIB]</li>
                                <li>Harap informasikan <strong>Nomor Pesanan (#<?php echo htmlspecialchars($id_transaksi_display); ?>)</strong> kepada staf kami.</li>
                            </ul>
                        </div>

                        <div class="payment-method-section p-3 border rounded mt-3">
                            <h5 class="fw-semibold"><i class="bi bi-bank"></i> 2. Transfer Bank Manual</h5>
                            <p>Lakukan transfer ke salah satu rekening resmi Thar'z Computer berikut:</p>
                            <ul class="list-unstyled ps-3">
                                <li class="mb-2"><strong>Bank BCA</strong>
                                    <ul class="list-unstyled ps-3">
                                        <li>No. Rekening: <strong>[WAJIB ISI: Nomor Rekening BCA Anda]</strong></li>
                                        <li>Atas Nama: <strong>[WAJIB ISI: Nama Sesuai Rekening BCA Anda]</strong></li>
                                    </ul>
                                </li>
                                <li class="mb-2"><strong>Bank Mandiri</strong> <small class="text-muted">(Contoh, hapus jika tidak ada)</small>
                                    <ul class="list-unstyled ps-3">
                                        <li>No. Rekening: <strong>[WAJIB ISI: Nomor Rekening Mandiri Anda]</strong></li>
                                        <li>Atas Nama: <strong>[WAJIB ISI: Nama Sesuai Rekening Mandiri Anda]</strong></li>
                                    </ul>
                                </li>
                                </ul>
                        </div>
                        
                        <div class="alert alert-warning mt-4" role="alert">
                            <h5 class="alert-heading fw-bold"><i class="bi bi-exclamation-triangle-fill"></i> PENTING: Konfirmasi Pembayaran!</h5>
                            <p>Setelah melakukan transfer, mohon segera lakukan konfirmasi pembayaran dengan mengirimkan <strong>bukti transfer</strong> Anda beserta informasi berikut:</p>
                            <ul class="list-unstyled ps-3">
                                <li>Nomor Pesanan: <strong>#<?php echo htmlspecialchars($id_transaksi_display); ?></strong></li>
                                <li>Nama Pengirim (sesuai nama di rekening bank Anda):</li>
                                <li>Jumlah Transfer: Rp <?php echo number_format($transaksi_info['total'], 0, ',', '.'); ?></li>
                            </ul>
                            <hr>
                            <p class="mb-1">Konfirmasi dapat dikirimkan melalui salah satu kontak berikut:</p>
                            <ul class="list-unstyled ps-3">
                                <li><i class="bi bi-whatsapp"></i> WhatsApp: <strong>[WAJIB ISI: Nomor WhatsApp Admin Konfirmasi]</strong></li>
                                <li><i class="bi bi-envelope-at"></i> Email: <strong>[WAJIB ISI: Alamat Email Admin Konfirmasi]</strong></li>
                            </ul>
                        </div>
                        <p class="mt-3 text-center text-muted small">Pesanan Anda akan kami proses setelah pembayaran berhasil diverifikasi.</p>
                    </div>
                </div>

                <div class="text-center mt-4 py-4 border-top">
                    <a href="https://wa.link/k70iaj" target="_blank" class="btn btn-primary btn-lg">
                        <i class="bi bi-whatsapp"></i> Konfirmasi Pembayaran
                    </a>

                    <a href="index.php" class="btn btn-outline-secondary btn-lg ms-2">
                        <i class="bi bi-house"></i> Kembali ke Beranda
                    </a>
                </div>
            </div> </div> <?php else: ?>
        <div class="alert alert-warning text-center" role="alert">Tidak ada informasi pesanan untuk ditampilkan. Mungkin sesi Anda berakhir atau ada kesalahan.</div>
        <div class="text-center mt-3">
             <a href="index.php" class="btn btn-primary">Kembali ke Beranda</a>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php $koneksi->close(); ?>
</body>
</html>