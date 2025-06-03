<?php
// daftar_service.php
include 'koneksi.php';

session_start();
// Logika otentikasi sederhana (opsional)
// if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'teknisi') {
//     header("Location: ../login.php");
//     exit();
// }

$namaAkun = "Teknisi";

$daftarServis = [];

// --- Ambil data servis dari database ---
// Menggunakan kolom 'tanggal' sebagai tanggal masuk
$sql = "SELECT
            id_service,
            id_customer,
            tanggal,
            device,
            keluhan,
            status
        FROM
            service
        ORDER BY tanggal DESC";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $daftarServis[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Servis - Thraz Computer</title>
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
        .status-badge {
            padding: 4px 8px;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.75rem;
            display: inline-block;
        }
        .status-menunggu { background-color: #FFEDD5; color: #F97316; }
        .status-dalam-proses { background-color: #DBEAFE; color: #3B82F6; }
        .status-menunggu-sparepart { background-color: #FEF3C7; color: #D97706; }
        .status-selesai { background-color: #D1FAE5; color: #10B981; }
        .status-dibatalkan { background-color: #FEE2E2; color: #EF4444; }
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
                        <a href="daftar_service.php" class="flex items-center space-x-3 p-3 rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition duration-200">
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
                <h2 class="text-2xl font-bold text-gray-800">Daftar Servis</h2>
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
                <h1 class="text-3xl font-extrabold mb-8 text-center text-gray-800">Daftar Servis Pelanggan</h1>

                <div class="bg-white p-6 rounded-lg shadow-md">
                    <div class="mb-4">
                        <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Cari pesanan berdasarkan ID, Pelanggan, Perangkat, Keluhan, atau Status..." class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white border border-gray-200 rounded-lg overflow-hidden" id="serviceTable">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="py-3 px-4 border-b text-left text-sm font-semibold text-gray-600">ID Pesanan</th>
                                    <th class="py-3 px-4 border-b text-left text-sm font-semibold text-gray-600">ID Customer</th>
                                    <th class="py-3 px-4 border-b text-left text-sm font-semibold text-gray-600">Perangkat</th>
                                    <th class="py-3 px-4 border-b text-left text-sm font-semibold text-gray-600">Keluhan</th>
                                    <th class="py-3 px-4 border-b text-left text-sm font-semibold text-gray-600">Status</th>
                                    <th class="py-3 px-4 border-b text-left text-sm font-semibold text-gray-600">Tanggal Masuk</th>
                                    <th class="py-3 px-4 border-b text-left text-sm font-semibold text-gray-600">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php if (!empty($daftarServis)): ?>
                                    <?php foreach ($daftarServis as $servis): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-2 px-4 border-b text-sm text-gray-700"><?php echo htmlspecialchars($servis['id_service']); ?></td>
                                        <td class="py-2 px-4 border-b text-sm text-gray-700"><?php echo htmlspecialchars($servis['id_customer']); ?></td>
                                        <td class="py-2 px-4 border-b text-sm text-gray-700"><?php echo htmlspecialchars($servis['device']); ?></td>
                                        <td class="py-2 px-4 border-b text-sm text-gray-700"><?php echo htmlspecialchars($servis['keluhan']); ?></td>
                                        <td class="py-2 px-4 border-b text-sm text-gray-700">
                                            <span class="status-badge
                                                <?php
                                                $statusClass = strtolower(str_replace(' ', '-', $servis['status']));
                                                echo 'status-' . $statusClass;
                                                ?>">
                                                <?php echo htmlspecialchars($servis['status']); ?>
                                            </span>
                                        </td>
                                        <td class="py-2 px-4 border-b text-sm text-gray-700"><?php echo htmlspecialchars($servis['tanggal']); ?></td>
                                        <td class="py-2 px-4 border-b text-sm text-gray-700">
                                            <a href="progres_service.php?id=<?php echo htmlspecialchars($servis['id_service']); ?>" class="bg-blue-500 hover:bg-blue-600 text-white text-sm py-1 px-3 rounded-md transition duration-200">Lihat/Update</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="py-4 text-center text-gray-500">Tidak ada pesanan servis.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
    function filterTable() {
        var input, filter, table, tr, td, i, txtValue;
        input = document.getElementById("searchInput");
        filter = input.value.toUpperCase();
        table = document.getElementById("serviceTable");
        tr = table.getElementsByTagName("tr");

        for (i = 0; i < tr.length; i++) {
            if (tr[i].getElementsByTagName("th").length > 0) continue; // Skip table header row

            let found = false;
            // Kolom yang ingin dicari (ID Pesanan, ID Customer, Perangkat, Keluhan, Status)
            let colsToSearch = [0, 1, 2, 3, 4];
            for (let j = 0; j < colsToSearch.length; j++) {
                td = tr[i].getElementsByTagName("td")[colsToSearch[j]];
                if (td) {
                    txtValue = td.textContent || td.innerText;
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        found = true;
                        break;
                    }
                }
            }

            if (found) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }
    </script>
</body>
</html>