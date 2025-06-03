<?php
$koneksi = mysqli_connect("localhost", "root", "", "Tharz_Computer");

if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>
