<?php
session_start();
$koneksi = new mysqli("localhost", "root", "", "tharz_computer");

// Cek kalau form dikirim
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $id_service = $_POST['id_service'];
    $status     = $_POST['status'];

    // Validasi simple
    $allowed = ['diajukan', 'dikonfirmasi', 'menunggu sparepart', 'diperbaiki', 'selesai', 'dibatalkan'];
    if (!in_array($status, $allowed)) {
        echo "Status tidak valid";
        exit;
    }

    $stmt = $koneksi->prepare("UPDATE service SET status = ? WHERE id_service = ?");
    $stmt->bind_param("si", $status, $id_service);
    $stmt->execute();
}
?>

<?php
$namaAkun = "Teknisi"; // Mengatur nama akun sebagai Teknisi
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Progres Servis - Thraz Computer</title>
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
                    <p class="text-sm text-gray-400">Teknisi Panel</p>
                </div>

                <ul class="px-6 space-y-3">
                    <li>
                        <a href="dashboard.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
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
                        <a href="data_service.php" class="flex items-center space-x-3 p-3 rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition duration-200">
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
                <h2 class="text-2xl font-bold text-gray-800">Progres Servis</h2>
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

                <div class="mb-6">
                    <a href="dashboard.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 transition duration-200">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                        Kembali ke Dashboard
                    </a>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-xl font-semibold mb-6 text-gray-800 text-center">Update Progres Servis</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white border border-gray-200 rounded-lg overflow-hidden">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="py-3 px-4 border-b text-left text-sm font-semibold text-gray-600">ID Pesanan</th>
                                    <th class="py-3 px-4 border-b text-left text-sm font-semibold text-gray-600">Device</th>
                                    <th class="py-3 px-4 border-b text-left text-sm font-semibold text-gray-600">Keluhan</th>
                                    <th class="py-3 px-4 border-b text-left text-sm font-semibold text-gray-600">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php
                                // Include koneksi.php jika belum di-include di bagian atas file
                                // include '../koneksi.php'; // Ini sudah di-include di awal script

                                $sql = "SELECT id_service, device, keluhan, status FROM service";
                                $result = $koneksi->query($sql);

                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        echo "<tr class='hover:bg-gray-50'>";
                                        echo "<td class='py-2 px-4 border-b text-sm text-gray-700'>" . $row["id_service"] . "</td>";
                                        echo "<td class='py-2 px-4 border-b text-sm text-gray-700'>" . $row["device"] . "</td>";
                                        echo "<td class='py-2 px-4 border-b text-sm text-gray-700'>" . htmlspecialchars($row["keluhan"]) . "</td>";

                                        // Mulai form untuk update status
                                        echo "<td class='py-2 px-4 border-b text-sm text-gray-700'>";
                                ?>
                                        <form method="POST" class="flex items-center space-x-2">
                                            <input type="hidden" name="id_service" value="<?php echo $row['id_service']; ?>">
                                            <select name="status" class="block w-full py-1 px-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                <?php
                                                $statusList = ['diajukan', 'dikonfirmasi', 'menunggu sparepart', 'diperbaiki', 'selesai', 'dibatalkan'];
                                                foreach ($statusList as $status) {
                                                    $selected = ($row['status'] == $status) ? 'selected' : '';
                                                    echo "<option value='$status' $selected>" . ucfirst($status) . "</option>";
                                                }
                                                ?>
                                            </select>
                                            <button type="submit" name="update_status" class="inline-flex justify-center py-1 px-3 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-200">Update</button>
                                        </form>
                                <?php
                                        echo "</td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='4' class='py-4 text-center text-gray-500'>Belum ada data progres servis.</td></tr>";
                                }

                                // $koneksi->close(); // Tutup koneksi di sini agar bisa digunakan lagi di bagian lain jika perlu
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>