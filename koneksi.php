<?php
$koneksi = mysqli_connect("localhost", "root", "", "projek1");

if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>
