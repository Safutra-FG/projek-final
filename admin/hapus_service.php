<?php
session_start();
include '../koneksi.php';

// Cek jika admin sudah login (sesuaikan dengan sistem login Anda)
// if (!isset($_SESSION['admin_id'])) {
//     header("Location: login.php");
//     exit();
// }

// Pastikan ada ID yang dikirim
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Redirect jika tidak ada ID
    header("Location: data_service.php?status=hapus_gagal");
    exit();
}

$id_service = intval($_GET['id']);

// Mulai transaksi database untuk memastikan semua data terkait terhapus
$koneksi->begin_transaction();

try {
    // 1. Dapatkan semua ID Transaksi yang terkait dengan service ini
    $transaksi_ids = [];
    $stmt_get_transaksi = $koneksi->prepare("SELECT id_transaksi FROM transaksi WHERE id_service = ?");
    $stmt_get_transaksi->bind_param("i", $id_service);
    $stmt_get_transaksi->execute();
    $result_transaksi = $stmt_get_transaksi->get_result();
    while ($row = $result_transaksi->fetch_assoc()) {
        $transaksi_ids[] = $row['id_transaksi'];
    }
    $stmt_get_transaksi->close();

    // 2. Hapus semua data pembayaran (bayar) yang terkait dengan transaksi di atas
    if (!empty($transaksi_ids)) {
        $ids_placeholder = implode(',', array_fill(0, count($transaksi_ids), '?'));
        $types = str_repeat('i', count($transaksi_ids));
        
        $stmt_del_bayar = $koneksi->prepare("DELETE FROM bayar WHERE id_transaksi IN ($ids_placeholder)");
        $stmt_del_bayar->bind_param($types, ...$transaksi_ids);
        if (!$stmt_del_bayar->execute()) {
            throw new Exception("Gagal menghapus data pembayaran.");
        }
        $stmt_del_bayar->close();
    }

    // 3. Hapus semua data transaksi (transaksi)
    $stmt_del_transaksi = $koneksi->prepare("DELETE FROM transaksi WHERE id_service = ?");
    $stmt_del_transaksi->bind_param("i", $id_service);
    if (!$stmt_del_transaksi->execute()) {
        throw new Exception("Gagal menghapus data transaksi.");
    }
    $stmt_del_transaksi->close();

    // 4. Hapus semua detail service (detail_service)
    $stmt_del_detail = $koneksi->prepare("DELETE FROM detail_service WHERE id_service = ?");
    $stmt_del_detail->bind_param("i", $id_service);
    if (!$stmt_del_detail->execute()) {
        throw new Exception("Gagal menghapus detail service.");
    }
    $stmt_del_detail->close();

    // 5. Hapus data utama service (service)
    $stmt_del_service = $koneksi->prepare("DELETE FROM service WHERE id_service = ?");
    $stmt_del_service->bind_param("i", $id_service);
    if (!$stmt_del_service->execute()) {
        throw new Exception("Gagal menghapus data service utama.");
    }
    $stmt_del_service->close();

    // Jika semua berhasil, commit transaksi
    $koneksi->commit();
    header("Location: data_service.php?status=hapus_sukses");

} catch (Exception $e) {
    // Jika ada satu saja yang gagal, rollback semua perubahan
    $koneksi->rollback();
    // Redirect dengan pesan error
    header("Location: data_service.php?status=hapus_gagal&error=" . urlencode($e->getMessage()));
}

$koneksi->close();
exit();
?>