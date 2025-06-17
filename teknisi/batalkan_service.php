<?php
session_start();
include '../koneksi.php';

if (!isset($_GET['id'])) {
    header("Location: data_service.php");
    exit;
}

$id_service = intval($_GET['id']);

// Cek apakah service ada dan belum dibatalkan
$query_check = "SELECT status FROM service WHERE id_service = ?";
$stmt_check = $koneksi->prepare($query_check);
$stmt_check->bind_param("i", $id_service);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows === 0) {
    echo "<script>
            alert('Service tidak ditemukan!');
            window.location.href='data_service.php';
          </script>";
    exit;
}

$service_data = $result_check->fetch_assoc();
if ($service_data['status'] == 'dibatalkan') {
    echo "<script>
            alert('Service ini sudah dibatalkan sebelumnya!');
            window.location.href='edit_service.php?id=" . $id_service . "';
          </script>";
    exit;
}

// Update status service menjadi 'dibatalkan'
$query_update = "UPDATE service SET status = 'dibatalkan' WHERE id_service = ?";
$stmt_update = $koneksi->prepare($query_update);
$stmt_update->bind_param("i", $id_service);

if ($stmt_update->execute()) {
    echo "<script>
            alert('Service berhasil dibatalkan!');
            window.location.href='edit_service.php?id=" . $id_service . "';
          </script>";
} else {
    echo "<script>
            alert('Gagal membatalkan service!');
            window.location.href='edit_service.php?id=" . $id_service . "';
          </script>";
}

$stmt_check->close();
$stmt_update->close();
mysqli_close($koneksi);
?> 