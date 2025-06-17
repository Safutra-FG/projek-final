<?php
session_start();
include '../koneksi.php'; 

// Aktifkan pelaporan error untuk debugging
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Pastikan semua parameter dari URL ada
if (!isset($_GET['id_bayar'], $_GET['id_service'], $_GET['aksi'])) {
    die("Akses tidak valid. Parameter tidak lengkap.");
}

// Ambil dan bersihkan data dari URL
$id_pembayaran = intval($_GET['id_bayar']);
$id_service = intval($_GET['id_service']);
$aksi = $_GET['aksi'];

// Mulai transaksi database.
$koneksi->begin_transaction();

try {
    // Logika jika tombol 'verifikasi' yang diklik
    if ($aksi == 'verifikasi') {
        
        // Query 1: Update status di tabel 'pembayaran'
        $status_pembayaran_baru = 'Diverifikasi';
        $stmt1 = $koneksi->prepare("UPDATE pembayaran SET status_verifikasi = ? WHERE id_pembayaran = ?");
        $stmt1->bind_param("si", $status_pembayaran_baru, $id_pembayaran);
        $stmt1->execute();
        $stmt1->close();

        // Query 2: Update status di tabel 'service' utama
        $status_service_baru = 'Diverifikasi';
        $stmt2 = $koneksi->prepare("UPDATE service SET status = ? WHERE id_service = ?");
        $stmt2->bind_param("si", $status_service_baru, $id_service);
        $stmt2->execute();
        $stmt2->close();
        
        $_SESSION['pesan_sukses'] = "Pembayaran berhasil diverifikasi dan status service telah diupdate.";

    } elseif ($aksi == 'tolak') {
        // Logika jika tombol 'tolak' yang diklik
        $status_pembayaran_baru = 'Ditolak';
        $stmt = $koneksi->prepare("UPDATE pembayaran SET status_verifikasi = ? WHERE id_pembayaran = ?");
        $stmt->bind_param("si", $status_pembayaran_baru, $id_pembayaran);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['pesan_sukses'] = "Pembayaran telah ditolak.";
    }
    
    // Simpan semua perubahan
    $koneksi->commit();

} catch (Exception $e) {
    // Batalkan semua perubahan jika ada error
    $koneksi->rollback();
    $_SESSION['pesan_error'] = "GAGAL! Terjadi kesalahan pada database: " . $e->getMessage();
}

// Tutup koneksi database
$koneksi->close();

// Arahkan pengguna kembali ke halaman detail service
header("Location: edit_service.php?id=" . $id_service);
exit();
?>