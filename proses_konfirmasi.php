<?php
include 'koneksi.php';

// Fungsi untuk validasi file
function validateFile($file) {
    $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
    $max_size = 5 * 1024 * 1024; // 5MB

    if ($file['error'] !== 0) {
        return "Terjadi kesalahan saat upload file.";
    }

    if ($file['size'] > $max_size) {
        return "Ukuran file terlalu besar. Maksimal 5MB.";
    }

    $file_type = mime_content_type($file['tmp_name']);
    if (!in_array($file_type, $allowed_types)) {
        return "Tipe file tidak didukung. Gunakan JPG, PNG, atau PDF.";
    }

    return null;
}

// Fungsi untuk menyimpan file
function saveFile($file, $id_service) {
    $upload_dir = "uploads/bukti_pembayaran/" . date('Y/m');
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_file_name = "bukti_" . $id_service . "_" . time() . "." . $file_extension;
    $target_path = $upload_dir . "/" . $new_file_name;

    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return "uploads/bukti_pembayaran/" . date('Y/m') . "/" . $new_file_name;
    }

    return null;
}

$error_message = null;
$success_message = null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_konfirmasi'])) {
    $id_service = intval(trim($_POST['id_service']));
    $amount = intval($_POST['amount']);
    $metode_transfer = trim($_POST['metode_transfer']);
    $nama_pengirim = trim($_POST['nama_pengirim']);

    // Validasi input dasar
    if (empty($id_service) || empty($metode_transfer) || empty($nama_pengirim)) {
        $error_message = "Semua field harus diisi.";
    } else {
        // Validasi file bukti pembayaran
        if (!isset($_FILES['bukti_pembayaran'])) {
            $error_message = "Bukti pembayaran harus diunggah.";
        } else {
            $file_error = validateFile($_FILES['bukti_pembayaran']);
            if ($file_error) {
                $error_message = $file_error;
            } else {
                // Simpan file
                $path_bukti = saveFile($_FILES['bukti_pembayaran'], $id_service);
                if (!$path_bukti) {
                    $error_message = "Gagal menyimpan bukti pembayaran.";
                } else {
                    // Ambil id_customer dari service
                    $sql_cust = "SELECT id_customer FROM service WHERE id_service = ?";
                    $stmt_cust = $koneksi->prepare($sql_cust);
                    $stmt_cust->bind_param("i", $id_service);
                    $stmt_cust->execute();
                    $stmt_cust->bind_result($id_customer);
                    $stmt_cust->fetch();
                    $stmt_cust->close();

                    if (empty($id_customer)) {
                        $error_message = "ID Customer tidak ditemukan untuk service ini.";
                    } else {
                        // Mulai transaksi database
                        $koneksi->begin_transaction();
                        try {
                            // Insert ke tabel transaksi
                            $sql_transaksi = "INSERT INTO transaksi (id_service, id_customer, jenis, status, tanggal, total) VALUES (?, ?, 'service', 'menunggu pembayaran', NOW(), ?)";
                            $stmt_transaksi = $koneksi->prepare($sql_transaksi);
                            $stmt_transaksi->bind_param("iii", $id_service, $id_customer, $amount);
                            $stmt_transaksi->execute();
                            $id_transaksi = $koneksi->insert_id;
                            $stmt_transaksi->close();

                            // Insert ke tabel bayar
                            $sql_bayar = "INSERT INTO bayar (id_transaksi, tanggal, jumlah, metode, status, bukti, catatan) VALUES (?, NOW(), 0 , ?, 'menunggu konfirmasi', ?, ?)";
                            $stmt_bayar = $koneksi->prepare($sql_bayar);
                            $catatan = "Konfirmasi pembayaran dari " . $nama_pengirim . " via " . $metode_transfer;
                            $stmt_bayar->bind_param("isss", $id_transaksi, $metode_transfer, $path_bukti, $catatan);
                            $stmt_bayar->execute();
                            $stmt_bayar->close();

                            $koneksi->commit();
                            $success_message = "Konfirmasi pembayaran berhasil dikirim! Tim kami akan segera memverifikasi pembayaran Anda.";
                        } catch (Exception $e) {
                            $koneksi->rollback();
                            $error_message = "Terjadi kesalahan: " . $e->getMessage();
                        }
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Konfirmasi Pembayaran - Thar'z Computer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="bi bi-check-circle"></i> Status Konfirmasi Pembayaran</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error_message); ?>
                            </div>
                            <div class="text-center mt-4">
                                <a href="javascript:history.back()" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Kembali
                                </a>
                            </div>
                        <?php elseif ($success_message): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($success_message); ?>
                            </div>
                            <div class="text-center mt-4">
                                <a href="index.php" class="btn btn-primary">
                                    <i class="bi bi-house"></i> Kembali ke Beranda
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>