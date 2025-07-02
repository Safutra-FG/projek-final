<?php
include 'proses/proses_cek.php';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Edit Service - Thar'z Computer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <style>
        .select2-container--default .select2-selection--single {
            height: 42px;
            border: 1px solid #D1D5DB;
            border-radius: 0.375rem;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 42px;
            padding-left: 0.75rem;
            color: #374151;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
        }

        .select2-dropdown {
            border: 1px solid #D1D5DB;
            border-radius: 0.375rem;
        }
    </style>
</head>

<body class="bg-gray-100 text-gray-900 font-sans antialiased">
    <div class="flex min-h-screen">
        <?php include 'includes/sidebar.php'; ?>
        <div class="flex-1 flex flex-col">
            <header class="flex justify-between items-center p-5 bg-white shadow-md">
                <h2 class="text-2xl font-bold text-gray-800">Edit Service</h2>
                <div class="flex items-center space-x-5">
                    <span class="text-xl text-gray-600">👤</span>
                    <span class="text-lg font-semibold text-gray-700"><?php echo htmlspecialchars($namaAkun); ?></span>
                    <a href="../logout.php" class="ml-4 px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition duration-200 text-sm font-medium">Logout</a>
                </div>
            </header>

            <main class="flex-1 p-8 overflow-auto">
                <div class="mb-6">
                    <a href="data_service.php" class="inline-flex items-center px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-200 text-sm font-medium">&larr; Kembali ke Data Service</a>
                </div>

                <div class="bg-white p-8 rounded-lg shadow-lg">
                    <form method="POST" action="">
                        <div class="mb-8">
                            <h3 class="text-xl font-bold text-gray-800 border-b pb-3 mb-4">Informasi Service</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div><label class="block text-sm font-medium text-gray-600">ID Service</label>
                                    <p class="mt-1 p-2 w-full rounded-md bg-gray-100"><?php echo htmlspecialchars($service['id_service']); ?></p>
                                </div>
                                <div><label class="block text-sm font-medium text-gray-600">Customer</label>
                                    <p class="mt-1 p-2 w-full rounded-md bg-gray-100"><?php echo htmlspecialchars($service['nama_customer']); ?></p>
                                </div>
                                <div><label class="block text-sm font-medium text-gray-600">Device</label>
                                    <p class="mt-1 p-2 w-full rounded-md bg-gray-100"><?php echo htmlspecialchars($service['device']); ?></p>
                                </div>
                                <div><label class="block text-sm font-medium text-gray-600">Keluhan Awal</label>
                                    <p class="mt-1 p-2 w-full rounded-md bg-gray-100 min-h-[42px]"><?php echo nl2br(htmlspecialchars($service['keluhan'])); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="mb-8">
                            <h3 class="text-xl font-bold text-gray-800 border-b pb-3 mb-4">Hasil Diagnosa & Estimasi</h3>
                            <div class="space-y-4">
                                <div><label for="kerusakan" class="block text-sm font-medium text-gray-700">Kerusakan (Diagnosa Umum)</label><textarea name="kerusakan" id="kerusakan" rows="3" class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-white focus:ring-blue-500 focus:border-blue-500" placeholder="Jelaskan hasil diagnosa kerusakan di sini..." required><?php echo htmlspecialchars($service['kerusakan'] ?? ''); ?></textarea></div>
                                <div><label for="estimasi_waktu" class="block text-sm font-medium text-gray-700">Estimasi Waktu Pengerjaan</label><input type="text" name="estimasi_waktu" id="estimasi_waktu" value="<?php echo htmlspecialchars($service['estimasi_waktu'] ?? ''); ?>" class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-white focus:ring-blue-500 focus:border-blue-500" placeholder="Contoh: 1-2 hari kerja" required></div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-xl font-bold text-gray-800 border-b pb-3 mb-4">Rincian Service</h3>
                            <div class="bg-gray-50 p-6 rounded-lg border border-gray-200">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="id_barang" class="block mb-1 text-sm font-medium text-gray-700">Barang (Sparepart):</label>
                                        <select id="id_barang" name="id_barang" class="select2-barang block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-white focus:ring-blue-500 focus:border-blue-500">
                                            <option value="">-- Cari Barang --</option>
                                            <?php mysqli_data_seek($barangs_result, 0); ?>
                                            <?php while ($b = mysqli_fetch_assoc($barangs_result)): ?>
                                                <option value="<?php echo $b['id_barang']; ?>" data-harga="<?php echo $b['harga']; ?>"><?php echo htmlspecialchars($b['nama_barang']); ?> - Rp <?php echo number_format($b['harga'], 0, ',', '.'); ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="id_jasa" class="block mb-1 text-sm font-medium text-gray-700">Jasa:</label>
                                        <select id="id_jasa" name="id_jasa" class="select2-jasa block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-white focus:ring-blue-500 focus:border-blue-500">
                                            <option value="">-- Cari Jasa --</option>
                                            <?php mysqli_data_seek($jasas_result, 0); ?>
                                            <?php while ($j = mysqli_fetch_assoc($jasas_result)): ?>
                                                <option value="<?php echo $j['id_jasa']; ?>" data-harga="<?php echo $j['harga']; ?>"><?php echo htmlspecialchars($j['jenis_jasa']); ?> - Rp <?php echo number_format($j['harga'], 0, ',', '.'); ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="kerusakan_detail" class="block mb-1 text-sm font-medium text-gray-700">Deskripsi/Tindakan:</label>
                                        <textarea id="kerusakan_detail" name="kerusakan_detail" class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-white focus:ring-blue-500 focus:border-blue-500" rows="2"></textarea>
                                    </div>
                                    <input type="hidden" id="edit_id_ds" name="edit_id_ds" value="">
                                </div>
                                <button type="button" id="tambah_item_btn" class="mt-6 w-full px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 font-semibold">Tambah Item ke Rincian</button>
                            </div>

                            <div class="mt-6 overflow-x-auto">
                                <table class="min-w-full bg-white text-sm">
                                    <thead class="bg-gray-200">
                                        <tr>
                                            <th class="py-2 px-3 border-b text-left font-bold text-gray-600">Barang</th>
                                            <th class="py-2 px-3 border-b text-left font-bold text-gray-600">Jasa</th>
                                            <th class="py-2 px-3 border-b text-left font-bold text-gray-600">Deskripsi</th>
                                            <th class="py-2 px-3 border-b text-left font-bold text-gray-600">Total</th>
                                            <th class="py-2 px-3 border-b text-left font-bold text-gray-600">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="detail_items_body">
                                        <?php while ($detail = mysqli_fetch_assoc($result_detail)):
                                            $barang_name = !is_null($detail['id_barang']) ? $detail['nama_barang'] : '-';
                                            $jasa_name = !is_null($detail['id_jasa']) ? $detail['jenis_jasa'] : '-';
                                            $jumlah = !is_null($detail['id_barang']) ? ($detail['total'] / ($detail['total'] > 0 ? $detail['total'] : 1)) : 1; // Estimasi jumlah dari total
                                            $kerusakan = $detail['kerusakan'];
                                            $total = $detail['total'];
                                        ?>
                                            <tr>
                                                <td class="py-2 px-3 border-b"><?php echo htmlspecialchars($barang_name); ?></td>
                                                <td class="py-2 px-3 border-b"><?php echo htmlspecialchars($jasa_name); ?></td>
                                                <td class="py-2 px-3 border-b"><?php echo htmlspecialchars($kerusakan); ?></td>
                                                <td class="py-2 px-3 border-b">Rp <?php echo number_format($total, 0, ',', '.'); ?></td>
                                                <td class="py-2 px-3 border-b">
                                                    <button type="button" class="text-red-600 hover:text-red-800 hapus-item-btn font-medium">Hapus</button>
                                                    <input type="hidden" name="item_id_barangs[]" value="<?php echo $detail['id_barang'] ?? ''; ?>">
                                                    <input type="hidden" name="item_id_jasas[]" value="<?php echo $detail['id_jasa'] ?? ''; ?>">
                                                    <input type="hidden" name="item_kerusakans[]" value="<?php echo htmlspecialchars($kerusakan); ?>">
                                                    <input type="hidden" name="item_totals[]" value="<?php echo $total; ?>">
                                                    <input type="hidden" name="item_jumlahs[]" value="<?php echo $jumlah; ?>">
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-6 flex justify-end">
                                <div class="w-full md:w-1/3">
                                    <label for="estimasi_harga" class="block text-sm font-bold text-gray-700">TOTAL ESTIMASI HARGA</label>
                                    <input type="text" id="estimasi_harga_display" value="Rp 0" class="mt-1 block w-full rounded-md bg-blue-50 text-blue-800 text-xl font-bold p-3 text-right" readonly>
                                    <input type="hidden" name="estimasi_harga" id="estimasi_harga">
                                </div>
                            </div>
                        </div>

                        <div class="border-t mt-8 pt-6 flex justify-end space-x-4">
                            <a href="data_service.php" class="px-6 py-2 border rounded-md text-sm font-medium">Batal</a>
                            <button type="submit" class="px-6 py-2 border rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inisialisasi Select2 untuk dropdown barang
            $('#id_barang').select2({
                placeholder: 'Cari Barang...',
                allowClear: true,
                width: '100%',
                theme: 'default'
            });

            // Inisialisasi Select2 untuk dropdown jasa
            $('#id_jasa').select2({
                placeholder: 'Cari Jasa...',
                allowClear: true,
                width: '100%',
                theme: 'default'
            });

            const barangSelect = document.getElementById('id_barang');
            const jasaSelect = document.getElementById('id_jasa');
            const kerusakanInput = document.getElementById('kerusakan_detail');
            const tambahItemBtn = document.getElementById('tambah_item_btn');
            const detailBody = document.getElementById('detail_items_body');
            const estimasiHargaInput = document.getElementById('estimasi_harga');
            const estimasiHargaDisplay = document.getElementById('estimasi_harga_display');

            function formatRupiah(angka) {
                return new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    minimumFractionDigits: 0
                }).format(angka);
            }

            function updateTotalInput() {
                let total = 0;
                if (barangSelect.value) {
                    const selectedBarang = barangSelect.options[barangSelect.selectedIndex];
                    total += parseFloat(selectedBarang.dataset.harga || 0);
                } else if (jasaSelect.value) {
                    const selectedJasa = jasaSelect.options[jasaSelect.selectedIndex];
                    total += parseFloat(selectedJasa.dataset.harga || 0);
                }
                return total;
            }

            tambahItemBtn.addEventListener('click', function() {
                let id_barang = barangSelect.value || '';
                let id_jasa = jasaSelect.value || '';
                let kerusakan = kerusakanInput.value;
                let total = updateTotalInput();

                if (!id_barang && !id_jasa) {
                    alert('Pilih barang atau jasa terlebih dahulu.');
                    return;
                }

                let barang_name = '-';
                let jasa_name = '-';

                if (id_barang) {
                    const selectedBarang = barangSelect.options[barangSelect.selectedIndex];
                    barang_name = selectedBarang.text.split(' - ')[0];
                } 
                
                if (id_jasa) {
                    const selectedJasa = jasaSelect.options[jasaSelect.selectedIndex];
                    jasa_name = selectedJasa.text.split(' - ')[0];
                }

                const newRow = `<tr>
                    <td class="py-2 px-3 border-b">${barang_name}</td>
                    <td class="py-2 px-3 border-b">${jasa_name}</td>
                    <td class="py-2 px-3 border-b">${kerusakan}</td>
                    <td class="py-2 px-3 border-b">${formatRupiah(total)}</td>
                    <td class="py-2 px-3 border-b">
                        <button type="button" class="text-red-600 hover:text-red-800 hapus-item-btn font-medium">Hapus</button>
                        <input type="hidden" name="item_id_barangs[]" value="${id_barang}">
                        <input type="hidden" name="item_id_jasas[]" value="${id_jasa}">
                        <input type="hidden" name="item_kerusakans[]" value="${kerusakan}">
                        <input type="hidden" name="item_totals[]" value="${total}">
                    </td>
                </tr>`;

                detailBody.insertAdjacentHTML('beforeend', newRow);
                updateEstimasiHarga();

                // Reset form
                $('#id_barang').val('').trigger('change');
                $('#id_jasa').val('').trigger('change');
                kerusakanInput.value = '';
            });

            detailBody.addEventListener('click', function(e) {
                if (e.target.classList.contains('hapus-item-btn')) {
                    e.target.closest('tr').remove();
                    updateEstimasiHarga();
                }
            });

            function updateEstimasiHarga() {
                let grandTotal = 0;
                const rows = detailBody.querySelectorAll('tr');
                rows.forEach(row => {
                    const total = parseFloat(row.querySelector('input[name="item_totals[]"]').value) || 0;
                    grandTotal += total;
                });
                estimasiHargaInput.value = grandTotal;
                estimasiHargaDisplay.value = formatRupiah(grandTotal);
            }

            updateEstimasiHarga();
        });
    </script>
</body>

</html>