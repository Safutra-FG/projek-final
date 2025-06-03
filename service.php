<?php
include 'koneksi.php'; // koneksi ke DB ente

if (isset($_POST['submit'])) {
    $nama       = $_POST['nama'];
    $no_hp      = $_POST['nomor_telepon'];
    $email      = $_POST['email'];
    $device     = $_POST['device'];
    $keluhan    = $_POST['keluhan'];

    // 1. Masukin data ke tabel customer
    $insertCustomer = mysqli_query($koneksi, "INSERT INTO customer (nama_customer, no_telepon, email)
                                               VALUES ('$nama', '$no_hp', '$email')");

    if ($insertCustomer) {
        // 2. Ambil ID customer yang barusan dimasukin
        $id_customer = mysqli_insert_id($koneksi);

        // 3. Masukin ke tabel service
        $tanggal = date('Y-m-d');
        $insertService = mysqli_query($koneksi, "INSERT INTO service (id_customer, tanggal, device, keluhan)
                                                  VALUES ('$id_customer', '$tanggal', '$device', '$keluhan')");
        $id_service = mysqli_insert_id($koneksi);
        if ($insertService) {
            echo "<script>
                document.addEventListener('DOMContentLoaded', function () {
                    showCustomAlert('Data berhasil diajukan!, ID Service kamu: #$id_service');
                });
            </script>";
        } else {
            echo "Gagal input service: " . mysqli_error($koneksi);
        }
    } else {
        echo "Gagal input customer: " . mysqli_error($koneksi);
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thar'z Computer - Pengajuan Service</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(to bottom right, rgb(5, 75, 145), rgb(93, 124, 188));
            color: #333;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* Custom styles for consistency if Tailwind doesn't cover everything */
        .gradient-blue-header {
            background: linear-gradient(to right, #0a3d62, #1d5fab);
        }

        .form-header-bg {
            background-color: #1d5fab;
        }

        .border-blue-custom {
            border-bottom: 3px solid #1d5fab;
        }

        .text-blue-custom {
            color: #1d5fab;
        }

        .submit-btn {
            background-color: #2ecc71;
            transition: background-color 0.3s ease;
        }

        .submit-btn:hover {
            background-color: #27ae60;
        }
    </style>
</head>

<body class="antialiased">
    <div class="container max-w-2xl mx-auto my-5 bg-white rounded-lg shadow-xl overflow-hidden">
        <header class="gradient-blue-header text-white p-6 text-center">
            <h1 class="text-3xl font-bold">THAR'<span>Z</span> COMPUTER</h1>
        </header>

        <div class="form-header-bg text-white p-4 text-center font-bold text-lg">Formulir Pengajuan Service</div>

        <div class="form-section p-6">
            <h3 class="text-xl font-bold text-gray-800 pb-2 mb-6 border-blue-custom">Data Pelanggan</h3>

            <form method="POST" id="serviceForm">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Nama Lengkap <span class="text-blue-custom">*</span></label>
                    <input type="text" name="nama" placeholder="Masukkan nama lengkap" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline bg-gray-50">
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Nomor Handphone <span class="text-blue-custom">*</span></label>
                    <input type="text" name="nomor_telepon" pattern="\d{10,12}" maxlength="12" placeholder="Contoh: 081234567890" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline bg-gray-50">
                    <small class="text-gray-600 text-xs mt-1 block">* Nomor harus 10-12 digit angka</small>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Alamat Email <span class="text-blue-custom">*</span></label>
                    <input type="email" name="email" placeholder="Contoh: nama@email.com" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline bg-gray-50">
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Jenis Perangkat <span class="text-blue-custom">*</span></label>
                    <input type="text" name="device" placeholder="Contoh: Laptop ASUS ROG" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline bg-gray-50">
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Keluhan/Kerusakan <span class="text-blue-custom">*</span></label>
                    <textarea name="keluhan" placeholder="Jelaskan keluhan atau kerusakan yang dialami secara detail" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline h-32 resize-y bg-gray-50"></textarea>
                </div>

                <div id="alertBox" class="p-4 mb-4 text-green-800 bg-green-100 border border-green-200 rounded-lg relative" style="display:none;">
                    <span id="alertMessage"></span>
                    <button id="closeAlert" class="absolute top-2 right-3 text-lg font-bold text-green-700">&times;</button>
                </div>

                <button type="submit" name="submit" class="submit-btn w-full text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:shadow-outline text-lg">Ajukan Service</button>
            </form>
        </div>

        <a href="index.php" class="inline-block mt-4 ml-6 mb-5 text-blue-custom hover:underline font-bold transition duration-200">&larr; Kembali ke Beranda</a>

        <div class="p-6 bg-gray-900 text-white text-center">
            <h3 class="text-xl font-bold mb-3">Informasi Pembayaran</h3>
            <p class="text-gray-300 text-sm">Estimasi biaya akan diberikan setelah teknisi kami memeriksa perangkat Anda. Kami akan menghubungi Anda untuk konfirmasi sebelum melakukan perbaikan.</p>
        </div>
    </div>

    <script>
        document.getElementById("serviceForm").addEventListener("submit", function(e) {
            const noHp = document.querySelector('input[name="nomor_telepon"]').value;
            const email = document.querySelector('input[name="email"]').value;

            const hpValid = /^\d{10,12}$/.test(noHp);
            const emailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);

            if (!hpValid) {
                alert("Nomor handphone harus terdiri dari 10 hingga 12 digit angka.");
                e.preventDefault();
            }

            if (!emailValid) {
                alert("Alamat email tidak valid. Pastikan mengandung '@' dan domain.");
                e.preventDefault();
            }
        });

        function showCustomAlert(message) {
            const alertBox = document.getElementById("alertBox");
            const alertMessage = document.getElementById("alertMessage");

            alertMessage.textContent = message;
            alertBox.style.display = "block";
        }

        document.getElementById("closeAlert").addEventListener("click", function() {
            document.getElementById("alertBox").style.display = "none";
        });
    </script>
</body>

</html>