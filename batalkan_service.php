<?php
session_start();
include '../koneksi.php';

// 1. Validasi Input dan CSRF
if (!isset($_GET['id']) || !isset($_GET['csrf_token'])) {
    die("Akses tidak valid.");
}

if (!hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
    die("Kesalahan validasi token CSRF. Silakan coba lagi.");
}

$id_service = intval($_GET['id']);

// 2. Update Status di Database
$query = "UPDATE service SET status = 'dibatalkan', tanggal_selesai = NULL WHERE id_service = ?";
$stmt = $koneksi->prepare($query);

if ($stmt) {
    $stmt->bind_param("i", $id_service);
    if ($stmt->execute()) {
        // Jika berhasil, kirim pesan sukses dan redirect
        $success_message = "Status service #" . $id_service . " berhasil diubah menjadi 'Dibatalkan'.";
        echo "<script>
                alert('" . addslashes($success_message) . "');
                window.location.href = 'edit_service.php?id=" . $id_service . "';
              </script>";
    } else {
        // Jika gagal, kirim pesan error
        $error_message = "Gagal memperbarui status: " . $stmt->error;
        echo "<script>
                alert('" . addslashes($error_message) . "');
                window.history.back();
              </script>";
    }
    $stmt->close();
} else {
    // Jika statement gagal disiapkan
    $error_message = "Gagal menyiapkan statement: " . $koneksi->error;
    echo "<script>
            alert('" . addslashes($error_message) . "');
            window.history.back();
          </script>";
}

mysqli_close($koneksi);
exit;
?>