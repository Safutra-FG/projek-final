<?php
// Konfigurasi database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'tharz_computer');

// Fungsi untuk membuat koneksi database
function connect_db() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        error_log("Koneksi database gagal: " . $conn->connect_error);
        die("Terjadi masalah koneksi database.");
    }
    return $conn;
}

$message = '';
$message_type = 'danger';

if (isset($_POST['submit_konfirmasi'])) {
    $koneksi = connect_db();

    // 1. Ambil dan bersihkan data dari form
    $id_service = $koneksi->real_escape_string($_POST['id_service']);
    $amount = $koneksi->real_escape_string($_POST['amount']);
    $metode_transfer = $koneksi->real_escape_string($_POST['metode_transfer']);
    $nama_pengirim = $koneksi->real_escape_string($_POST['nama_pengirim']);

    // 2. Validasi dasar
    if (empty($id_service) || empty($amount) || empty($metode_transfer) || empty($nama_pengirim)) {
        $message = "Semua field harus diisi.";
    } elseif (!isset($_FILES['bukti_pembayaran']) || $_FILES['bukti_pembayaran']['error'] != 0) {
        $message = "Gagal mengunggah file. Pastikan Anda memilih file bukti pembayaran.";
    } else {
        // 3. Proses unggah file
        $target_dir = "uploads/bukti_pembayaran/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true); // Buat folder jika belum ada
        }
        
        $file = $_FILES['bukti_pembayaran'];
        $file_name = $file['name'];
        $file_tmp_name = $file['tmp_name'];
        $file_size = $file['size'];
        $file_error = $file['error'];
        
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];

        if (in_array($file_ext, $allowed_ext)) {
            if ($file_error === 0) {
                if ($file_size <= 5000000) { // 5MB
                    // Buat nama file baru yang unik untuk menghindari penimpaan file
                    $new_file_name = "bukti_" . $id_service . "_" . time() . "." . $file_ext;
                    $file_destination = $target_dir . $new_file_name;

                    if (move_uploaded_file($file_tmp_name, $file_destination)) {
                        
                        // 4. Simpan ke database menggunakan prepared statements
                        $koneksi->begin_transaction();
                        try {
                            // Masukkan ke tabel pembayaran
                            $sql_insert = "INSERT INTO pembayaran (id_service, nama_pengirim, metode_transfer, jumlah_transfer, file_bukti) VALUES (?, ?, ?, ?, ?)";
                            $stmt_insert = $koneksi->prepare($sql_insert);
                            $stmt_insert->bind_param("sssds", $id_service, $nama_pengirim, $metode_transfer, $amount, $new_file_name);
                            $stmt_insert->execute();

                            // =================================================================================
                            // PERUBAHAN UTAMA DI SINI
                            // Alih-alih mengubah status, kita TIDAK melakukan apa-apa pada status service.
                            // Atau, jika status sebelumnya adalah 'Menunggu Pembayaran', kita ubah kembali ke 'Dikonfirmasi'.
                            // Baris di bawah ini akan memastikan statusnya adalah 'Dikonfirmasi'.
                            // =================================================================================
                            $sql_update = "UPDATE service SET status = 'Dikonfirmasi' WHERE id_service = ?";
                            $stmt_update = $koneksi->prepare($sql_update);
                            $stmt_update->bind_param("s", $id_service);
                            $stmt_update->execute();
                            
                            $koneksi->commit();

                            $message = "Konfirmasi pembayaran berhasil dikirim! ID Service Anda: " . htmlspecialchars($id_service) . ". Kami akan segera memprosesnya.";
                            $message_type = 'success';

                        } catch (mysqli_sql_exception $exception) {
                            $koneksi->rollback();
                            error_log("Database transaction failed: " . $exception->getMessage());
                            $message = "Terjadi kesalahan pada database. Gagal menyimpan konfirmasi.";
                        }

                    } else {
                        $message = "Gagal memindahkan file yang diunggah.";
                    }
                } else {
                    $message = "Ukuran file terlalu besar. Maksimal 5MB.";
                }
            } else {
                $message = "Terjadi kesalahan saat mengunggah file.";
            }
        } else {
            $message = "Format file tidak diizinkan. Harap unggah file JPG, PNG, atau PDF.";
        }
    }
    $koneksi->close();
} else {
    // Jika halaman diakses langsung tanpa submit form
    header('Location: index.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Konfirmasi Pembayaran</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center" style="min-height: 100vh;">
            <div class="col-md-8 text-center">
                <div class="card shadow-sm">
                    <div class="card-body p-5">
                        <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="alert">
                            <h4 class="alert-heading">
                                <?php echo ($message_type == 'success') ? '<i class="bi bi-check2-circle"></i> Berhasil!' : '<i class="bi bi-exclamation-triangle"></i> Gagal!'; ?>
                            </h4>
                            <p><?php echo htmlspecialchars($message); ?></p>
                        </div>
                        <hr>
                        <p class="mb-0">Anda dapat melacak status service Anda melalui halaman Tracking.</p>
                        <div class="mt-4">
                            <a href="tracking.php?id_service=<?php echo htmlspecialchars(isset($_POST['id_service']) ? $_POST['id_service'] : ''); ?>" class="btn btn-primary"><i class="bi bi-search"></i> Lacak Status Service</a>
                            <a href="index.php" class="btn btn-secondary"><i class="bi bi-house"></i> Kembali ke Beranda</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>