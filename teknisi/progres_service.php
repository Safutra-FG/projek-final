<?php
// progres_servis.php
include 'koneksi.php';

session_start();
// Anda bisa menambahkan logika otentikasi sesi di sini
// Misalnya, memeriksa apakah pengguna sudah login dan memiliki peran 'teknisi'
// if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'teknisi') {
//      header("Location: ../login.php");
//      exit();
// }

$namaAkun = "Teknisi";

$servisId = $_GET['id'] ?? null;

$detailServis = null;

// --- Ambil data detail servis dari database ---
if ($servisId) {
    // Gunakan prepared statement untuk keamanan
    // SESUAIKAN KOLOM DENGAN ISI DATABASE ANDA
    $stmt = $conn->prepare("SELECT
                                  id_service,
                                  id_customer,
                                  tanggal,
                                  device,
                                  keluhan,
                                  kerusakan,      -- Kolom yang bisa diupdate/ditambahkan teknisi
                                  status,         -- Kolom status
                                  estimasi_waktu, -- Kolom estimasi
                                  estimasi_harga  -- Kolom estimasi
                                FROM
                                  service
                                WHERE
                                  id_service = ?");
    $stmt->bind_param("i", $servisId);
    $stmt->execute();
    $result = $stmt->get_result();
    $detailServis = $result->fetch_assoc();
    $stmt->close();
}

// Logika untuk menangani POST request (saat teknisi update progres)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $servisId) {
    $newStatus = $_POST['status'] ?? '';
    $newKerusakan = $_POST['kerusakan_update'] ?? ''; // Menggunakan kolom 'kerusakan' untuk update teknisi

    // Anda bisa menambahkan logika untuk kolom tanggal_selesai jika ada di database Anda
    // $completionDate = null;
    // if ($newStatus === 'Selesai') {
    //      $completionDate = date("Y-m-d");
    // }

    // Gunakan prepared statement untuk update
    // SESUAIKAN KOLOM DENGAN ISI DATABASE ANDA
    // Jika Anda punya kolom 'updated_at' di database, tambahkan ke query update
    // Asumsi: 'kerusakan' adalah kolom yang diupdate oleh teknisi sebagai detail perbaikan.
    $stmt = $conn->prepare("UPDATE service SET status = ?, kerusakan = ? WHERE id_service = ?");
    $stmt->bind_param("ssi", $newStatus, $newKerusakan, $servisId);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Progres servis berhasil diperbarui!";
        $_SESSION['message_type'] = "success";
        header("Location: daftar_service.php"); // Mengarahkan kembali ke daftar_service.php setelah update
        exit();
    } else {
        $_SESSION['message'] = "Gagal memperbarui progres servis: " . $stmt->error;
        $_SESSION['message_type'] = "error";
        header("Location: progres_servis.php?id=" . $servisId);
        exit();
    }
    $stmt->close();
}

$conn->close();

// Jika ID tidak valid atau data tidak ditemukan setelah query
if (!$detailServis) {
    $_SESSION['message'] = "Pesanan servis tidak ditemukan.";
    $_SESSION['message_type'] = "error";
    header("Location: daftar_service.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Progres Servis #<?php echo htmlspecialchars($servisId); ?> - Thraz Computer</title>
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

        /* Hapus CSS kustom untuk item aktif yang sebelumnya dibuat */
        /* .sidebar-active-item {
            border-left: 4px solid #3B82F6;
            padding-left: 12px;
            background-color: #374151;
            color: white;
        } */
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
                        <a href="data_service.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                            <span class="text-xl">⚙️</span>
                            <span class="font-medium">Progres Servis</span> </a>
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
                <h1 class="text-3xl font-extrabold mb-8 text-center text-gray-800">Update Progres Servis #<?php echo htmlspecialchars($detailServis['id_service']); ?></h1>

                <div class="bg-white p-6 rounded-lg shadow-md max-w-2xl mx-auto">
                    <?php
                    if (isset($_SESSION['message'])):
                    ?>
                        <div class="p-4 mb-4 rounded-lg
                            <?php echo ($_SESSION['message_type'] == 'success') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                            <?php echo $_SESSION['message']; ?>
                        </div>
                    <?php
                        unset($_SESSION['message']);
                        unset($_SESSION['message_type']);
                    endif;
                    ?>

                    <h2 class="text-2xl font-bold mb-6 text-gray-800">Detail Pesanan</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
                        <div><p class="text-gray-700"><strong>ID Servis:</strong> <?php echo htmlspecialchars($detailServis['id_service']); ?></p></div>
                        <div><p class="text-gray-700"><strong>ID Customer:</strong> <?php echo htmlspecialchars($detailServis['id_customer']); ?></p></div>
                        <div><p class="text-gray-700"><strong>Tanggal Masuk:</strong> <?php echo htmlspecialchars($detailServis['tanggal']); ?></p></div>
                        <div><p class="text-gray-700"><strong>Device:</strong> <?php echo htmlspecialchars($detailServis['device']); ?></p></div>
                        <div><p class="text-gray-700"><strong>Keluhan Awal:</strong> <?php echo htmlspecialchars($detailServis['keluhan']); ?></p></div>
                        <div><p class="text-gray-700"><strong>Estimasi Waktu:</strong> <?php echo htmlspecialchars($detailServis['estimasi_waktu']); ?></p></div>
                        <div><p class="text-gray-700"><strong>Estimasi Harga:</strong> Rp <?php echo number_format($detailServis['estimasi_harga'], 0, ',', '.'); ?></p></div>
                        <div>
                            <p class="text-gray-700"><strong>Status Saat Ini:</strong>
                                <span class="status-badge
                                        <?php
                                        $statusClass = strtolower(str_replace(' ', '-', $detailServis['status']));
                                        echo 'status-' . $statusClass;
                                        ?>">
                                        <?php echo htmlspecialchars($detailServis['status']); ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="mb-6">
                        <p class="text-gray-700"><strong>Kerusakan (Catatan Teknisi):</strong></p>
                        <p class="bg-gray-50 p-3 rounded-md border border-gray-200 text-gray-800"><?php echo htmlspecialchars($detailServis['kerusakan'] ?? '-'); ?></p>
                    </div>

                    <h2 class="text-2xl font-bold mt-8 mb-6 text-gray-800">Update Progres</h2>
                    <form action="progres_servis.php?id=<?php echo htmlspecialchars($servisId); ?>" method="POST">
                        <div class="mb-4">
                            <label for="status" class="block text-gray-700 text-sm font-semibold mb-2">Status Pengerjaan:</label>
                            <select name="status" id="status" class="block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="Menunggu" <?php if ($detailServis['status'] == 'Menunggu') echo 'selected'; ?>>Menunggu</option>
                                <option value="Dalam Proses" <?php if ($detailServis['status'] == 'Dalam Proses') echo 'selected'; ?>>Dalam Proses</option>
                                <option value="Menunggu Sparepart" <?php if ($detailServis['status'] == 'Menunggu Sparepart') echo 'selected'; ?>>Menunggu Sparepart</option>
                                <option value="Selesai" <?php if ($detailServis['status'] == 'Selesai') echo 'selected'; ?>>Selesai</option>
                                <option value="Dibatalkan" <?php if ($detailServis['status'] == 'Dibatalkan') echo 'selected'; ?>>Dibatalkan</option>
                            </select>
                        </div>
                        <div class="mb-6">
                            <label for="kerusakan_update" class="block text-gray-700 text-sm font-semibold mb-2">Update Kerusakan/Catatan Perbaikan:</label>
                            <textarea name="kerusakan_update" id="kerusakan_update" rows="5" class="block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Tulis detail kerusakan yang ditemukan atau langkah perbaikan..."><?php echo htmlspecialchars($detailServis['kerusakan'] ?? ''); ?></textarea>
                        </div>
                        <div class="flex items-center justify-end space-x-4">
                            <a href="daftar_service.php" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-200">Batal</a>
                            <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-200">
                                Update Progres
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>