<?php
include '../koneksi.php';
include 'auth.php';

$namaAkun = getNamaUser();
$alert_message = '';
$alert_type = '';
if (isset($_SESSION['alert_message'])) {
    $alert_message = $_SESSION['alert_message'];
    $alert_type = $_SESSION['alert_type'];

    // Hapus pesan dari session agar tidak muncul lagi saat halaman di-refresh
    unset($_SESSION['alert_message']);
    unset($_SESSION['alert_type']);
}
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
        <?php
        include 'includes/sidebar.php';
        ?>

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
                <?php if (!empty($alert_message)): ?>
                    <div>
                        <?php echo $alert_message; ?>
                    </div>
                <?php endif; ?>
                <div class="max-w-2xl mx-auto bg-white rounded-lg shadow-md p-6">
                    <form action="proses/proses_tambah_service.php" method="POST" class="space-y-6">
                        <!-- Customer Information -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-medium text-gray-900">Informasi Pelanggan</h3>

                            <div>
                                <label for="nama_customer" class="block text-sm font-medium text-gray-700 mb-2">Nama Pelanggan <span class="text-red-500">*</span></label>
                                <input type="text" name="nama" id="nama" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="Masukkan nama pelanggan">
                            </div>

                            <div>
                                <label for="no_telepon" class="block text-sm font-medium text-gray-700 mb-2">Nomor Telepon <span class="text-red-500">*</span></label>
                                <input type="tel" name="nomor_telepon" id="nomor_telepon" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="Contoh: 081234567890"
                                    minlength="12" maxlength="13">
                                <p id="error_telepon" class="mt-1 text-sm text-red-600 hidden">Nomor telepon harus 12-13 digit</p>
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
                            <button type="submit" name="submit"
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
        // Format nomor telepon - hanya angka dan validasi panjang
        document.getElementById('no_telepon').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            e.target.value = value;

            // Validasi panjang nomor telepon
            const errorElement = document.getElementById('error_telepon');
            if (value.length < 12 || value.length > 13) {
                errorElement.classList.remove('hidden');
                e.target.setCustomValidity('Nomor telepon harus 12-13 digit dan berupap angka');
            } else {
                errorElement.classList.add('hidden');
                e.target.setCustomValidity('');
            }
        });
    </script>
</body>

</html>