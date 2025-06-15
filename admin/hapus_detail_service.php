<?php
session_start();
include '../koneksi.php';

// Atur header untuk merespon sebagai JSON
header('Content-Type: application/json');

// Pastikan request adalah POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode request tidak valid.']);
    exit;
}

// Pastikan ID Detail Service ada
if (!isset($_POST['id_ds'])) {
    echo json_encode(['success' => false, 'message' => 'ID Detail tidak ditemukan.']);
    exit;
}

$id_ds = intval($_POST['id_ds']);
$id_barang = isset($_POST['id_barang']) ? intval($_POST['id_barang']) : null;

// Mulai transaksi database untuk memastikan semua query berhasil atau gagal bersamaan
mysqli_begin_transaction($koneksi);

try {
    // 1. Jika ada barang/sparepart yang digunakan, kembalikan stoknya
    if ($id_barang) {
        $stmt_stok = $koneksi->prepare("UPDATE stok SET stok = stok + 1 WHERE id_barang = ?");
        if (!$stmt_stok) {
            throw new Exception("Gagal mempersiapkan statement stok: " . $koneksi->error);
        }
        $stmt_stok->bind_param("i", $id_barang);
        if (!$stmt_stok->execute()) {
            throw new Exception("Gagal mengembalikan stok: " . $stmt_stok->error);
        }
        $stmt_stok->close();
    }

    // 2. Hapus data dari tabel detail_service
    $stmt_hapus = $koneksi->prepare("DELETE FROM detail_service WHERE id_ds = ?");
    if (!$stmt_hapus) {
        throw new Exception("Gagal mempersiapkan statement hapus: " . $koneksi->error);
    }
    $stmt_hapus->bind_param("i", $id_ds);
    if (!$stmt_hapus->execute()) {
        throw new Exception("Gagal menghapus detail service: " . $stmt_hapus->error);
    }
    $stmt_hapus->close();

    // Jika semua query berhasil, commit transaksi
    mysqli_commit($koneksi);
    echo json_encode(['success' => true, 'message' => 'Detail service berhasil dihapus.']);

} catch (Exception $e) {
    // Jika terjadi error, batalkan semua perubahan (rollback)
    mysqli_rollback($koneksi);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

mysqli_close($koneksi);
?>