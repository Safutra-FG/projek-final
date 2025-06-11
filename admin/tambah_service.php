<?php
include '../koneksi.php';
include 'auth.php';

$namaAkun = getNamaUser();

// Proses form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nama_customer = $_POST['nama_customer'];
    $no_telepon = $_POST['no_telepon'];
    $email = $_POST['email'];
    $device = $_POST['device'];
    $keluhan = $_POST['keluhan'];
    $status = 'diajukan';
    $tanggal = date('Y-m-d H:i:s');

    // Cek apakah customer sudah ada
    $check_sql = "SELECT id_customer FROM customer WHERE nama_customer = ? AND no_telepon = ?";
    $check_stmt = $koneksi->prepare($check_sql);
    $check_stmt->bind_param("ss", $nama_customer, $no_telepon);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Customer sudah ada, gunakan id_customer yang ada
        $customer = $result->fetch_assoc();
        $id_customer = $customer['id_customer'];
    } else {
        // Customer belum ada, insert customer baru
        $insert_customer_sql = "INSERT INTO customer (nama_customer, no_telepon, email) VALUES (?, ?, ?)";
        $insert_customer_stmt = $koneksi->prepare($insert_customer_sql);
        $insert_customer_stmt->bind_param("sss", $nama_customer, $no_telepon, $email);
        
        if ($insert_customer_stmt->execute()) {
            $id_customer = $koneksi->insert_id;
        } else {
            echo "<script>alert('Error: " . $insert_customer_stmt->error . "');</script>";
            exit;
        }
        $insert_customer_stmt->close();
    }
    $check_stmt->close();

    // Query untuk menyimpan data service
    $sql = "INSERT INTO service (id_customer, device, keluhan, estimasi_harga, status, tanggal) 
            VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param("issdss", $id_customer, $device, $keluhan, $estimasi_harga, $status, $tanggal);
    
    if ($stmt->execute()) {
        echo "<script>
                alert('Service berhasil ditambahkan!');
                window.location.href = 'dashboard.php';
              </script>";
    } else {
        echo "<script>alert('Error: " . $stmt->error . "');</script>";
    }
    
    $stmt->close();
}

// Ambil data customer untuk dropdown
$sql_customers = "SELECT id_customer, nama_customer, no_telepon FROM customer ORDER BY nama_customer ASC";
$result_customers = $koneksi->query($sql_customers);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Service Baru - Thar'z Computer</title>
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
        <!-- Sidebar -->
        <div class="w-64 bg-gray-800 shadow-lg flex flex-col justify-between py-6">
            <div>
                <div class="flex flex-col items-center mb-10">
                    <img src="../icons/logo.png" alt="Logo" class="w-16 h-16 rounded-full mb-3 border-2 border-blue-400">
                    <h1 class="text-2xl font-extrabold text-white text-center">Thar'z Computer</h1>
                    <p class="text-sm text-gray-400">Admin Panel</p>
                </div>

                <ul class="px-6 space-y-3">
                    <li>
                        <a href="dashboard.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                            <span class="text-xl">🏠</span>
                            <span class="font-medium">Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="pembayaran_service.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                            <span class="text-xl">💰</span>
                            <span class="font-medium">Pembayaran Service</span>
                        </a>
                    </li>
                    <li>
                        <a href="kelola_penjualan.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                            <span class="text-xl">💰</span>
                            <span class="font-medium">Kelola Penjualan</span>
                        </a>
                    </li>
                    <li>
                        <a href="data_service.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                            <span class="text-xl">📝</span>
                            <span class="font-medium">Data Service</span>
                        </a>
                    </li>
                    <li>
                        <a href="data_pelanggan.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                            <span class="text-xl">👥</span>
                            <span class="font-medium">Data Pelanggan</span>
                        </a>
                    </li>
                    <li>
                        <a href="riwayat_transaksi.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                            <span class="text-xl">💳</span>
                            <span class="font-medium">Riwayat Transaksi</span>
                        </a>
                    </li>
                    <li>
                        <a href="stok_gudang.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                            <span class="text-xl">📦</span>
                            <span class="font-medium">Stok Gudang</span>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="p-4 border-t border-gray-700 text-center text-sm text-gray-400">
                &copy; Thar'z Computer 2025
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col">
            <!-- Header -->
            <div class="flex justify-between items-center p-5 bg-white shadow-md">
                <h2 class="text-2xl font-bold text-gray-800">Tambah Service Baru</h2>
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

            <!-- Form Content -->
            <div class="flex-1 p-8 overflow-y-auto">
                <div class="max-w-2xl mx-auto bg-white rounded-lg shadow-md p-6">
                    <form action="" method="POST" class="space-y-6">
                        <!-- Customer Information -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-medium text-gray-900">Informasi Pelanggan</h3>
                            
                            <div>
                                <label for="nama_customer" class="block text-sm font-medium text-gray-700 mb-2">Nama Pelanggan <span class="text-red-500">*</span></label>
                                <input type="text" name="nama_customer" id="nama_customer" required 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Masukkan nama pelanggan">
                            </div>

                            <div>
                                <label for="no_telepon" class="block text-sm font-medium text-gray-700 mb-2">Nomor Telepon <span class="text-red-500">*</span></label>
                                <input type="tel" name="no_telepon" id="no_telepon" required 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Contoh: 081234567890">
                            </div>

                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                                <input type="email" name="email" id="email"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Contoh: pelanggan@email.com">
                            </div>
                        </div>

                        <!-- Service Information -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-medium text-gray-900">Informasi Service</h3>

                            <div>
                                <label for="device" class="block text-sm font-medium text-gray-700 mb-2">Device <span class="text-red-500">*</span></label>
                                <input type="text" name="device" id="device" required 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Contoh: Laptop Asus ROG">
                            </div>

                            <div>
                                <label for="keluhan" class="block text-sm font-medium text-gray-700 mb-2">Keluhan <span class="text-red-500">*</span></label>
                                <textarea name="keluhan" id="keluhan" required rows="4"
                                          class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                          placeholder="Jelaskan keluhan pelanggan..."></textarea>
                            </div>

                        </div>

                        <!-- Submit Button -->
                        <div class="flex justify-end space-x-4">
                            <a href="dashboard.php" 
                               class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition duration-200">
                                Batal
                            </a>
                            <button type="submit" 
                                    class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-200">
                                Simpan Service
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Format nomor telepon - hanya angka
        document.getElementById('no_telepon').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            e.target.value = value;
        });
    </script>
</body>
</html> 