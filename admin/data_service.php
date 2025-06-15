<?php
include '../koneksi.php';
include 'auth.php';

// Pastikan fungsi ini ada di auth.php atau sesuaikan
if (function_exists('getNamaUser')) {
    $namaAkun = getNamaUser();
} else {
    // Fallback jika fungsi tidak ditemukan
    $namaAkun = 'Admin';
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Data Service - Thar'z Computer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Gaya tambahan untuk select agar terlihat lebih rapi dengan Tailwind */
        .form-select {
            display: block;
            width: 100%;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            line-height: 1.25rem;
            color: #4A5568;
            background-color: #F7FAFC;
            border: 1px solid #CBD5E0;
            border-radius: 0.375rem;
            appearance: none;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .form-select:focus {
            outline: none;
            border-color: #63B3ED;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.5);
        }
    </style>
</head>

<body class="bg-gray-100 text-gray-900 font-sans antialiased">

    <div class="flex min-h-screen">
        <?php include 'includes/sidebar.php'; ?>

        <div class="flex-1 flex flex-col">
            <div class="flex justify-between items-center p-5 bg-white shadow-md">
                <h2 class="text-2xl font-bold text-gray-800">Data Service</h2>
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

            <div class="flex-1 p-8 overflow-auto">
                <div class="mb-6">
                    <a href="dashboard.php" class="inline-flex items-center px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-200 text-sm font-medium">
                        &larr; Kembali ke Dashboard
                    </a>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-xl font-semibold mb-4 text-gray-700">Daftar Data Service</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 border border-gray-300">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Pesanan</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Device</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Keluhan</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php
                                $sql = "SELECT service.*, customer.nama_customer FROM service JOIN customer ON service.id_customer = customer.id_customer ORDER BY id_service DESC";
                                $result = $koneksi->query($sql);

                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        echo "<tr>";
                                        echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-900'>" . htmlspecialchars($row["id_service"]) . "</td>";
                                        echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-900'>" . htmlspecialchars($row["nama_customer"]) . "</td>";
                                        echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-900'>" . htmlspecialchars($row["device"]) . "</td>";
                                        echo "<td class='px-6 py-4 text-sm text-gray-900'>" . htmlspecialchars($row["keluhan"]) . "</td>";

                                        // Tampilan status
                                        echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-900'>";
                                        $statusClass = '';
                                        switch ($row['status']) {
                                            case 'diajukan':
                                                $statusClass = 'bg-gray-100 text-gray-800';
                                                break;
                                            case 'dikonfirmasi':
                                                $statusClass = 'bg-blue-100 text-blue-800';
                                                break;
                                            case 'diverifikasi':
                                                $statusClass = 'bg-blue-100 text-blue-800';
                                                break;
                                            case 'menunggu sparepart':
                                                $statusClass = 'bg-purple-100 text-purple-800';
                                                break;
                                            case 'diperbaiki':
                                                $statusClass = 'bg-yellow-100 text-yellow-800';
                                                break;
                                            case 'selesai':
                                                $statusClass = 'bg-green-100 text-green-800';
                                                break;
                                            case 'dibatalkan':
                                                $statusClass = 'bg-red-100 text-red-800';
                                                break;
                                            default:
                                                $statusClass = 'bg-gray-100 text-gray-800';
                                                break;
                                        }
                                        echo "<span class='px-2 inline-flex text-xs leading-5 font-semibold rounded-full " . $statusClass . "'>" . ucfirst(htmlspecialchars($row['status'])) . "</span>";
                                        echo "</td>";
                                        echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-center space-x-2'>";
                                        if ($row['status'] == 'menunggu konfirmasi') {
                                            
                                            $id_service = $row['id_service'];

                                            echo "<a href='konfirmasi_aksi.php?id={$id_service}' 
                                                    onclick=\"return confirm('Apakah Anda yakin ingin mengkonfirmasi service ini?');\" 
                                                    class='inline-block bg-blue-500 hover:bg-blue-700 text-white font-semibold py-2 px-3 rounded-md shadow-sm transition duration-200' 
                                                    title='Klik untuk mengkonfirmasi'>
                                                    Konfirmasi
                                                </a>";
                                        } else {
                                            $link_edit = ($row['status'] == 'diajukan') ? 'cek.php' : 'edit_service.php';
                                            echo "<a href='" . $link_edit . "?id=" . htmlspecialchars($row['id_service']) . "' class='inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-3 rounded-md shadow-sm transition duration-200' title='Edit Detail Service'>Proses</a>";
                                        }
                                        echo "</td>"; // ========================================================

                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='7' class='px-6 py-4 text-center text-sm text-gray-500'>Tidak ada data service.</td></tr>";
                                }

                                $koneksi->close();
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