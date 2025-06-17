<?php
// Mulai session untuk menangani pesan feedback (opsional tapi sangat direkomendasikan)
session_start();

// 1. Sertakan file koneksi database Anda
// Pastikan path-nya benar
include '../koneksi.php'; // Ganti 'koneksi.php' dengan nama file koneksi Anda

// 2. Periksa apakah ID dikirim melalui URL
if (isset($_GET['id'])) {
    // 3. Ambil dan bersihkan ID dari URL untuk keamanan
    $id_service = $_GET['id'];

    // 4. Buat query SQL untuk UPDATE status
    // GANTI 'tabel_service' dengan nama tabel Anda
    // GANTI 'id_service' dengan nama kolom ID Anda
    $sql = "UPDATE service SET status = 'dikonfirmasi' WHERE id_service = ?";

    // 5. Gunakan Prepared Statements untuk mencegah SQL Injection (Sangat Penting!)
    $stmt = $koneksi->prepare($sql);
    
    // Periksa apakah prepare statement berhasil
    if ($stmt === false) {
        die("Error preparing statement: " . $koneksi->error);
    }
    
    // 'i' berarti tipe datanya adalah integer. Jika ID Anda string, gunakan 's'.
    $stmt->bind_param("i", $id_service);

    // 6. Eksekusi query
    if ($stmt->execute()) {
        // Jika berhasil, buat pesan sukses
        $_SESSION['pesan'] = "Status service berhasil diubah menjadi 'Dikonfirmasi'.";
    } else {
        // Jika gagal, buat pesan error
        $_SESSION['pesan'] = "Error: Gagal mengubah status. " . $stmt->error;
    }

    // Tutup statement
    $stmt->close();

} else {
    // Jika tidak ada ID yang dikirim, buat pesan error
    $_SESSION['pesan'] = "Error: Tidak ada ID yang dipilih.";
}

// 7. Tutup koneksi database
$koneksi->close();

// 8. Alihkan pengguna kembali ke halaman sebelumnya
// GANTI 'halaman_data_service.php' dengan halaman tempat tabel Anda berada
header("Location: data_service.php");
exit(); // Pastikan tidak ada kode lain yang dieksekusi setelah redirect

?>