<?php
$koneksi = mysqli_connect("localhost", "root", "", "projek");

if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>
