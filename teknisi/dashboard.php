<?php
// dashboard.php (untuk peran Teknisi)
include 'koneksi.php'; // Pastikan file koneksi.php ada dan benar

session_start();
// Logika otentikasi sederhana (opsional, untuk produksi gunakan yang lebih kuat)
// if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'teknisi') {
//     header("Location: ../login.php");
//     exit();
// }

$namaAkun = "Teknisi"; // Mengatur nama akun sebagai Teknisi

// --- Ambil data statistik dari database ---
$totalServisHariIni = 0;
$servisDalamProses = 0;
$servisMenungguSparepart = 0;
$servisSelesaiHariIni = 0;

$today = date("Y-m-d"); // Tanggal hari ini

// Query untuk total servis hari ini
// Menggunakan kolom 'tanggal' sebagai tanggal masuk
$sqlTotal = "SELECT COUNT(*) AS total FROM service WHERE DATE(tanggal) = '$today'";
$resultTotal = $conn->query($sqlTotal);
if ($resultTotal && $resultTotal->num_rows > 0) {
    $row = $resultTotal->fetch_assoc();
    $totalServisHariIni = $row['total'];
}

// Query untuk servis dalam proses
$sqlDalamProses = "SELECT COUNT(*) AS total FROM service WHERE status = 'Dalam Proses'";
$resultDalamProses = $conn->query($sqlDalamProses);
if ($resultDalamProses && $resultDalamProses->num_rows > 0) {
    $row = $resultDalamProses->fetch_assoc();
    $servisDalamProses = $row['total'];
}

// Query untuk servis menunggu sparepart
$sqlMenungguSparepart = "SELECT COUNT(*) AS total FROM service WHERE status = 'Menunggu Sparepart'";
$resultMenungguSparepart = $conn->query($sqlMenungguSparepart);
if ($resultMenungguSparepart && $resultMenungguSparepart->num_rows > 0) {
    $row = $resultMenungguSparepart->fetch_assoc();
    $servisMenungguSparepart = $row['total'];
}

// Query untuk servis selesai hari ini
// Menggunakan kolom 'tanggal_selesai' (pastikan kolom ini ada di DB Anda)
$sqlSelesaiHariIni = "SELECT COUNT(*) AS total FROM service WHERE status = 'Selesai' AND DATE(tanggal_selesai) = '$today'";
$resultSelesaiHariIni = $conn->query($sqlSelesaiHariIni);
if ($resultSelesaiHariIni && $resultSelesaiHariIni->num_rows > 0) {
    $row = $resultSelesaiHariIni->fetch_assoc();
    $servisSelesaiHariIni = $row['total'];
}

$conn->close(); // Tutup koneksi database
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Teknisi - Thraz Computer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Gaya dasar untuk card, agar lebih menarik dan konsisten dengan Tailwind */
        .card {
            background-color: #fff;
            padding: 24px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transition: transform 0.2s ease-in-out;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card h3 {
            margin-top: 0;
            color: #4A5568; /* Warna teks yang lebih gelap */
            font-size: 1.125rem; /* Ukuran font lebih proporsional */
            margin-bottom: 12px;
            font-weight: 600; /* Sedikit lebih tebal */
        }

        .card p {
            font-size: 2.25em; /* Ukuran angka lebih besar */
            font-weight: bold;
            color: #2D3748; /* Warna angka lebih gelap */
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-900 font-sans antialiased">

    <div class="flex min-h-screen">

        <div class="w-64 bg-gray-800 shadow-lg flex flex-col justify-between py-6">
            <div>
                <div class="flex flex-col items-center mb-10">
                    <img src="../icons/logo.png" alt="Logo" class="w-16 h-16 rounded-full mb-3 border-2 border-blue-400">
                    <h1 class="text-2xl font-extrabold text-white text-center">Thraz Computer</h1>
                    <p class="text-sm text-gray-400">Teknisi Panel</p> </div>

                <ul class="px-6 space-y-3">
                    <li>
                        <a href="dashboard.php" class="flex items-center space-x-3 p-3 rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition duration-200">
                            <span class="text-xl">🏠</span>
                            <span class="font-medium">Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="daftar_service.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                            <span class="text-xl">📝</span>
                            <span class="font-medium">Daftar Servis</span>
                        </a>
                    </li>
                    <li>
                        <a href="data_service.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                            <span class="text-xl">⚙️</span>
                            <span class="font-medium">Progres Servis</span>
                        </a>
                    </li>
                    </ul>
            </div>

            <div class="p-4 border-t border-gray-700 text-center text-sm text-gray-400">
                &copy; Tharz Computer 2025
            </div>
        </div>

        <div class="flex-1 flex flex-col">

            <div class="flex justify-between items-center p-5 bg-white shadow-md">
                <h2 class="text-2xl font-bold text-gray-800">Dashboard</h2>
                <div class="flex items-center space-x-5">
                    <button class="relative text-gray-600 hover:text-blue-600 transition duration-200" title="Pemberitahuan">
                        <span class="text-2xl">🔔</span>
                    </button>
                    <div class="flex items-center space-x-3">
                        <span class="text-xl text-gray-600">👤</span>
                        <span class="text-lg font-semibold text-gray-700"><?php echo htmlspecialchars($namaAkun); ?></span>
                        <a href="../logout.php" class="ml-4 px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition duration-200 text-sm font-medium">Logout</a>
                    </div>
                </div>
            </div>

            <div class="flex-1 p-8 overflow-y-auto">
                <h1 class="text-3xl font-extrabold mb-8 text-center text-gray-800">Selamat Datang Teknisi, SEMANGAT KERJANYAA!</h1>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10"> <div class="card bg-blue-100 text-blue-800">
                        <h3>Total Servis Hari Ini</h3>
                        <p class="text-blue-700"><?php echo $totalServisHariIni; ?></p>
                    </div>
                    <div class="card bg-yellow-100 text-yellow-800">
                        <h3>Servis Dalam Proses</h3>
                        <p class="text-yellow-700"><?php echo $servisDalamProses; ?></p>
                    </div>
                    <div class="card bg-purple-100 text-purple-800">
                        <h3>Servis Menunggu Sparepart</h3>
                        <p class="text-purple-700"><?php echo $servisMenungguSparepart; ?></p>
                    </div>
                    <div class="card bg-green-100 text-green-800">
                        <h3>Servis Selesai Hari Ini</h3>
                        <p class="text-green-700"><?php echo $servisSelesaiHariIni; ?></p>
                    </div>
                    </div>

                <div class="text-center mt-12">
                    <p class="text-lg text-gray-600">Gunakan menu di samping untuk mengelola pesanan servis.</p>
                </div>
            </div>

        </div>
    </div>

</body>
</html>