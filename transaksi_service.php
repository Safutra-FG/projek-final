<?php

include 'koneksi.php';

$id_service_input = null;
$amount_to_pay = null;
$service_data = null;
$error_message = null;
$namaAkun = "Customer"; // Default account name for this page



// 1. Ambil dan validasi parameter dari URL
if (isset($_GET['id_service']) && isset($_GET['amount'])) {
    $id_service_input = trim($_GET['id_service']);
    $amount_to_pay = filter_var($_GET['amount'], FILTER_VALIDATE_FLOAT);

    if (empty($id_service_input)) {
        $error_message = "ID Service tidak boleh kosong atau tidak valid.";
    } elseif ($amount_to_pay === false || $amount_to_pay < 0) {
        $error_message = "Jumlah tagihan tidak valid.";
    } else {
        $koneksi = mysqli_connect("localhost", "root", "", "revisi");

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
                $error_message = "Data service dengan ID '" . htmlspecialchars($id_service_input) . "' tidak ditemukan.";
            }
            $stmt->close();
        } else {
            error_log("Gagal menyiapkan query: " . $koneksi->error);
            $error_message = "Terjadi kesalahan dalam mengambil data service. Silakan coba lagi nanti.";
        }
        $koneksi->close();
    }
} else {
    $error_message = "Informasi ID Service atau jumlah tagihan tidak lengkap. Silakan kembali ke halaman tracking dan coba lagi.";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instruksi Pembayaran - Thar'z Computer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { display: flex; flex-direction: column; font-family: sans-serif; min-height: 100vh; background-color: #f8f9fa; }
        .navbar { background-color: #ffffff; padding: 15px 20px; border-bottom: 1px solid #dee2e6; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); }
        .navbar .logo-img { width: 40px; height: 40px; border-radius: 50%; margin-right: 10px; border: 2px solid #0d6efd; }
        .navbar .nav-link { padding: 10px 15px; color: #495057; font-weight: 500; transition: background-color 0.2s, color 0.2s; border-radius: 0.25rem; display: flex; align-items: center; }
        .navbar .nav-link.active, .navbar .nav-link:hover { background-color: #e9ecef; color: #007bff; }
        .navbar .nav-link i { margin-right: 8px; }
        .main-content { flex: 1; padding: 20px; display: flex; flex-direction: column; }
        .main-header { display: flex; justify-content: center; align-items: center; padding-bottom: 15px; border-bottom: 1px solid #dee2e6; margin-bottom: 20px; }
        .total-tagihan-display { font-size: 1.25em; font-weight: bold; color: #dc3545; }
        .label-summary { min-width: 140px; display: inline-block; font-weight: 500; }
        @media (max-width: 768px) {
            .main-header { flex-direction: column; align-items: flex-start; gap: 15px; }
            .main-header h2 { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light">
    </nav>

<div class="main-content">
    <div class="main-header">
        <h2 class="h4 text-dark mb-0 text-center flex-grow-1">Instruksi Pembayaran Service</h2>
    </div>

    <div class="flex-grow-1 p-3">
        <?php if ($error_message): ?>
            <div class="alert alert-danger text-center" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
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
                                <p>Anda dapat melakukan pembayaran secara tunai atau metode lain yang tersedia di toko kami dan tunjukkan halaman ini.</p>
                                </div>
                        </div>

                        <div class="col-md-6">
                            <div class="p-3 border rounded-3 h-100">
                                <h3 class="mb-3"><i class="bi bi-bank"></i> 2. Transfer Bank</h3>
                                <p>Lakukan transfer ke salah satu rekening resmi berikut:</p>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header bg-warning">
                            <h4 class="alert-heading mb-0"><i class="bi bi-check-circle-fill"></i> Penting: Konfirmasi Pembayaran Anda di Sini</h4>
                        </div>
                        <div class="card-body">
                            <p>Setelah melakukan transfer, mohon segera lakukan konfirmasi dengan mengisi formulir di bawah ini agar service Anda dapat segera kami proses.</p>
                            
                            <form action="proses_konfirmasi.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="id_service" value="<?php echo htmlspecialchars($service_data['id_service']); ?>">
                                <input type="hidden" name="amount" value="<?php echo htmlspecialchars($amount_to_pay); ?>">

                                <div class="mb-3">
                                    <label for="metode_transfer" class="form-label">Metode Pembayaran:</label>
                                    <select class="form-select" id="metode_transfer" name="metode_transfer" required>
                                        <option value="" disabled selected>-- Pilih Metode Pembayaran --</option>
                                        <option value="cash">Cash di Toko</option>
                                        <option value="transfer">Transfer Bank</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="nama_pengirim" class="form-label">Nama Pemilik Rekening Pengirim:</label>
                                    <input type="text" class="form-control" id="nama_pengirim" name="nama_pengirim" placeholder="Contoh: Budi Santoso" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="bukti_pembayaran" class="form-label">Unggah Bukti Transfer:</label>
                                    <input class="form-control" type="file" id="bukti_pembayaran" name="bukti_pembayaran" accept="image/jpeg, image/png, application/pdf" required>
                                    <div class="form-text">Format file yang diizinkan: JPG, PNG, atau PDF. Ukuran maks: 5MB.</div>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" name="submit_konfirmasi" class="btn btn-success btn-lg">
                                        <i class="bi bi-send-check"></i> Kirim Konfirmasi Pembayaran
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    </div>
            </div>

            <div class="text-center mt-4 py-4 border-top">
                <a href="index.php" class="btn btn-outline-secondary btn-lg ms-2"><i class="bi bi-house"></i> Kembali ke Beranda</a>
            </div>

        <?php else: ?>
            <?php endif; ?>
    </div>

    <footer class="mt-auto p-4 border-top text-center text-muted small">
        <p>&copy; <?php echo date("Y"); ?> Thar'z Computer. All rights reserved.</p>
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>