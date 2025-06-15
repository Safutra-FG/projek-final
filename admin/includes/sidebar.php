<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<div class="w-64 bg-gray-800 shadow-lg flex flex-col justify-between py-6">
    <div>
        <div class="flex flex-col items-center mb-10">
            <img src="../icons/logo.png" alt="Logo" class="w-16 h-16 rounded-full mb-3 border-2 border-blue-400">
            <h1 class="text-2xl font-extrabold text-white text-center">Thar'z Computer</h1>
            <p class="text-sm text-gray-400">Admin Panel</p>
        </div>

        <ul class="px-6 space-y-3">
            <li>
                <a href="dashboard.php" class="flex items-center space-x-3 p-3 rounded-lg <?php echo $currentPage == 'dashboard.php' ? 'text-white bg-blue-600' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> transition duration-200">
                    <span class="text-xl">🏠</span>
                    <span class="font-medium">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="pembayaran_service.php" class="flex items-center space-x-3 p-3 rounded-lg <?php echo $currentPage == 'pembayaran_service.php' ? 'text-white bg-blue-600' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> transition duration-200">
                    <span class="text-xl">💰</span>
                    <span class="font-medium">Pembayaran Service</span>
                </a>
            </li>
            <li>
                <a href="kelola_penjualan.php" class="flex items-center space-x-3 p-3 rounded-lg <?php echo $currentPage == 'kelola_penjualan.php' ? 'text-white bg-blue-600' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> transition duration-200">
                    <span class="text-xl">💰</span>
                    <span class="font-medium">Kelola Penjualan</span>
                </a>
            </li>
            <li>
                <a href="data_service.php" class="flex items-center space-x-3 p-3 rounded-lg <?php echo $currentPage == 'data_service.php' ? 'text-white bg-blue-600' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> transition duration-200">
                    <span class="text-xl">📝</span>
                    <span class="font-medium">Data Service</span>
                </a>
            </li>
            <li>
                <a href="data_pelanggan.php" class="flex items-center space-x-3 p-3 rounded-lg <?php echo $currentPage == 'data_pelanggan.php' ? 'text-white bg-blue-600' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> transition duration-200">
                    <span class="text-xl">👥</span>
                    <span class="font-medium">Data Pelanggan</span>
                </a>
            </li>
            <li>
                <a href="riwayat_transaksi.php" class="flex items-center space-x-3 p-3 rounded-lg <?php echo $currentPage == 'riwayat_transaksi.php' ? 'text-white bg-blue-600' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> transition duration-200">
                    <span class="text-xl">💳</span>
                    <span class="font-medium">Riwayat Transaksi</span>
                </a>
            </li>
            <li>
                <a href="stok_gudang.php" class="flex items-center space-x-3 p-3 rounded-lg <?php echo $currentPage == 'stok_gudang.php' ? 'text-white bg-blue-600' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> transition duration-200">
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