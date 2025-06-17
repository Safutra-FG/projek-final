<?php
session_start();
include '../koneksi.php';

if (!isset($_GET['id'])) {
    header("Location: data_service.php");
    exit;
}

$id_service = intval($_GET['id']);

// Cek apakah service ada dan statusnya 'selesai' atau 'siap diambil'
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
if ($service_data['status'] !== 'selesai' && $service_data['status'] !== 'siap diambil') {
    echo "<script>
            alert('Hanya service dengan status Selesai atau Siap Diambil yang dapat dikonfirmasi pengambilannya!');
            window.location.href='edit_service.php?id=" . $id_service . "';
          </script>";
    exit;
}

// Update status service menjadi 'sudah diambil'
$query_update = "UPDATE service SET status = 'sudah diambil' WHERE id_service = ?";
$stmt_update = $koneksi->prepare($query_update);
$stmt_update->bind_param("i", $id_service);

if ($stmt_update->execute()) {
    echo "<script>
            alert('Status service berhasil diubah menjadi Sudah Diambil!');
            window.location.href='edit_service.php?id=" . $id_service . "';
          </script>";
} else {
    echo "<script>
            alert('Gagal mengubah status service!');
            window.location.href='edit_service.php?id=" . $id_service . "';
          </script>";
}

$stmt_check->close();
$stmt_update->close();
mysqli_close($koneksi);
?> 