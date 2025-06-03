<?php
// Koneksi ke database (samakan dengan file tracking_service.php)
$koneksi = new mysqli("localhost", "root", "", "tharz_computer");
if ($koneksi->connect_error) {
    error_log("Koneksi database gagal: " . $koneksi->connect_error);
    // Di production, jangan tampilkan error detail ke user
    die("Terjadi masalah koneksi. Silakan coba beberapa saat lagi atau hubungi administrator.");
}

$id_service_input = null;
$amount_to_pay = null;
$service_data = null;
$error_message = null;

// 1. Ambil dan validasi parameter dari URL
if (isset($_GET['id_service']) && isset($_GET['amount'])) {
    $id_service_input = trim($_GET['id_service']);
    $amount_to_pay = filter_var($_GET['amount'], FILTER_VALIDATE_FLOAT);

    if (empty($id_service_input)) {
        $error_message = "ID Service tidak boleh kosong atau tidak valid.";
    } elseif ($amount_to_pay === false || $amount_to_pay < 0) {
        $error_message = "Jumlah tagihan tidak valid.";
    } else {
        // 2. Ambil data service dan customer
        $sql = "SELECT s.id_service, s.device, c.nama_customer
                FROM service s
                JOIN customer c ON s.id_customer = c.id_customer
                WHERE s.id_service = ?";
        $stmt = $koneksi->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("s", $id_service_input);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $service_data = $result->fetch_assoc();
            } else {
                $error_message = "Data service dengan ID '".htmlspecialchars($id_service_input)."' tidak ditemukan.";
            }
            $stmt->close();
        } else {
            // Di production, catat error ini dan jangan tampilkan $koneksi->error ke user
            error_log("Gagal menyiapkan query: " . $koneksi->error);
            $error_message = "Terjadi kesalahan dalam mengambil data service.";
        }
    }
} else {
    $error_message = "Informasi ID Service atau jumlah tagihan tidak lengkap.";
}

$koneksi->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instruksi Pembayaran - Thar'z Computer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* Sedikit custom style jika diperlukan, usahakan minimalkan */
        .logo-img {
            max-height: 50px; /* Sesuaikan ukuran logo */
        }
        .company-name-header {
            font-size: 1.75rem; /* Ukuran font nama perusahaan */
            font-weight: bold;
        }
        .total-tagihan-display {
            font-size: 1.25em;
            font-weight: bold;
            color: #dc3545; /* Warna merah Bootstrap untuk tagihan */
        }
        .label-summary {
            min-width: 140px; /* Agar titik dua di ringkasan rapi */
            display: inline-block;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container my-4">
        <header class="d-flex flex-wrap align-items-center justify-content-center justify-content-md-between py-3 mb-4 border-bottom">
            <div class="col-md-3 mb-2 mb-md-0">
                <a href="index.php" class="d-inline-flex align-items-center link-body-emphasis text-decoration-none">
                    <img src="icons/logo.png" alt="Logo Thar'z Computer" class="logo-img me-2">
                    <span class="company-name-header">Thar'z Computer</span>
                </a>
            </div>
            </header>

        <h2 class="text-center mb-4 display-6">Instruksi Pembayaran Service</h2>

        <?php if ($error_message): ?>
            <div class="alert alert-danger text-center" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <div class="text-center mt-4">
                <?php if ($id_service_input && !$service_data): // Error karena ID tidak ditemukan, tapi ID ada ?>
                    <a href="tracking_service.php?id_service=<?php echo htmlspecialchars($id_service_input); ?>" class="btn btn-primary"><i class="bi bi-arrow-left-circle"></i> Coba Lagi / Cek Status</a>
                <?php else: // Error umum atau parameter tidak lengkap ?>
                    <a href="tracking_service.php" class="btn btn-primary"><i class="bi bi-search"></i> Kembali ke Halaman Tracking</a>
                <?php endif; ?>
                <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-house"></i> Kembali ke Beranda</a>
            </div>
        <?php elseif ($service_data && $amount_to_pay !== null): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light py-3">
                    <h4 class="my-0 fw-normal">Ringkasan Tagihan</h4>
                </div>
                <div class="card-body">
                    <p class="card-text fs-5"><span class="label-summary">ID Service:</span> <?php echo htmlspecialchars($service_data['id_service']); ?></p>
                    <p class="card-text fs-5"><span class="label-summary">Nama Customer:</span> <?php echo htmlspecialchars($service_data['nama_customer']); ?></p>
                    <p class="card-text fs-5"><span class="label-summary">Perangkat:</span> <?php echo htmlspecialchars($service_data['device']); ?></p>
                    <hr>
                    <p class="card-text fs-5"><span class="label-summary">Total Tagihan:</span> <span class="total-tagihan-display">Rp <?php echo number_format($amount_to_pay, 0, ',', '.'); ?></span></p>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white py-3">
                     <h4 class="my-0"><i class="bi bi-credit-card"></i> Metode Pembayaran</h4>
                </div>
                <div class="card-body p-lg-4">
                    <p class="text-center lead mb-4">Silakan selesaikan pembayaran Anda melalui salah satu metode berikut:</p>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="p-3 border rounded-3 h-100">
                                <h3 class="mb-3"><i class="bi bi-shop"></i> 1. Bayar Langsung di Toko</h3>
                                <p>Anda dapat melakukan pembayaran secara tunai atau metode lain yang tersedia di toko kami:</p>
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-2"><strong class="d-block">Alamat Toko:</strong><strong>[WAJIB ISI: Alamat Lengkap Toko Thar'z Computer Anda]</strong></li>
                                    <li class="mb-2"><strong class="d-block">Jam Operasional:</strong>[Contoh: Senin - Sabtu, pukul 09:00 - 17:00 WIB]</li>
                                    <li>Harap informasikan <strong>ID Service (<?php echo htmlspecialchars($service_data['id_service']); ?>)</strong> kepada staf kami.</li>
                                </ul>
                            </div>
                        </div>

                        <div class="col-md-6">
                             <div class="p-3 border rounded-3 h-100">
                                <h3 class="mb-3"><i class="bi bi-bank"></i> 2. Transfer Bank Manual</h3>
                                <p>Lakukan transfer ke salah satu rekening resmi berikut:</p>
                                <ul class="list-unstyled">
                                    <li class="mb-2"><strong>Bank BCA</strong>
                                        <ul class="list-unstyled ps-3">
                                            <li>No. Rekening: <strong>[WAJIB ISI: Nomor Rekening BCA Anda]</strong></li>
                                            <li>Atas Nama: <strong>[WAJIB ISI: Nama Sesuai Rekening BCA Anda]</strong></li>
                                        </ul>
                                    </li>
                                    <li class="mb-2"><strong>Bank Mandiri</strong> <small class="text-muted">(Hapus jika tidak ada)</small>
                                        <ul class="list-unstyled ps-3">
                                            <li>No. Rekening: <strong>[WAJIB ISI: Nomor Rekening Mandiri Anda]</strong></li>
                                            <li>Atas Nama: <strong>[WAJIB ISI: Nama Sesuai Rekening Mandiri Anda]</strong></li>
                                        </ul>
                                    </li>
                                    </ul>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning mt-4" role="alert">
                        <h4 class="alert-heading"><i class="bi bi-exclamation-triangle-fill"></i> Penting: Konfirmasi Pembayaran!</h4>
                        <p>Setelah melakukan transfer, mohon segera lakukan konfirmasi pembayaran dengan mengirimkan <strong>bukti transfer</strong> Anda beserta informasi berikut:</p>
                        <ul class="list-unstyled">
                            <li><i class="bi bi-patch-check-fill text-success"></i> ID Service: <strong><?php echo htmlspecialchars($service_data['id_service']); ?></strong></li>
                            <li><i class="bi bi-person-fill text-primary"></i> Nama Pengirim (sesuai rekening bank):</li>
                            <li><i class="bi bi-wallet-fill text-info"></i> Jumlah Transfer: Rp <?php echo number_format($amount_to_pay, 0, ',', '.'); ?></li>
                        </ul>
                        <hr>
                        <p class="mb-1">Konfirmasi dapat dikirimkan melalui salah satu kontak berikut:</p>
                        <ul class="list-unstyled">
                            <li><i class="bi bi-whatsapp text-success"></i> WhatsApp: <strong>[WAJIB ISI: Nomor WhatsApp Admin Konfirmasi]</strong></li>
                            <li><i class="bi bi-envelope-at-fill text-danger"></i> Email: <strong>[WAJIB ISI: Alamat Email Admin Konfirmasi]</strong></li>
                        </ul>
                    </div>
                    <p class="mt-3 fst-italic text-center text-muted">Service Anda akan kami proses/siapkan untuk pengambilan setelah pembayaran Anda berhasil diverifikasi oleh tim kami.</p>
                </div>
            </div>

            <div class="text-center mt-4 py-4 border-top">
                <a href="tracking_service.php?id_service=<?php echo htmlspecialchars($id_service_input); ?>" class="btn btn-primary btn-lg"><i class="bi bi-eye"></i> Lihat Status Service Saya</a>
                <a href="index.php" class="btn btn-outline-secondary btn-lg ms-2"><i class="bi bi-house"></i> Kembali ke Beranda</a>
            </div>

        <?php else: ?>
            <div class="alert alert-info text-center" role="alert">
                Data instruksi pembayaran tidak dapat ditampilkan. Silakan coba lagi dari halaman tracking.
            </div>
            <div class="text-center mt-3">
                <a href="tracking_service.php" class="btn btn-primary"><i class="bi bi-search"></i> Kembali ke Halaman Tracking</a>
                <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-house"></i> Kembali ke Beranda</a>
            </div>
        <?php endif; ?>

        <footer class="text-center text-muted py-4 mt-4 border-top">
            <p>&copy; <?php echo date("Y"); ?> Thar'z Computer. All rights reserved.</p>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>