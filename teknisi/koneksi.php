<?php
// koneksi.php

$servername = "localhost";
$username = "root";     // Ganti dengan username database Anda
$password = "";         // Ganti dengan password database Anda
$dbname = "tharz_computer"; // Ganti dengan nama database Anda

// Membuat koneksi
$conn = new mysqli($servername, $username, $password, $dbname);

// Mengecek koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}
//echo "Koneksi berhasil"; // Baris ini bisa dihapus setelah Anda yakin koneksi berhasil
?>