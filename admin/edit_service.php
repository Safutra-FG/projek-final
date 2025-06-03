<?php
session_start(); // Untuk CSRF token dan pesan flash (jika dikembangkan)
include '../koneksi.php';

// Fungsi untuk menghasilkan CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$id_service = null;
if (isset($_GET['id'])) {
    $id_service = intval($_GET['id']);
} else {
    // Jika menggunakan pesan flash dengan Bootstrap, bisa disimpan di session
    // $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'ID Service tidak ditemukan.'];
    // header('Location: data_service.php'); // Redirect ke halaman daftar
    echo "ID Service tidak ditemukan."; // Pesan sederhana untuk sekarang
    exit;
}

$error_messages = []; // Tampung pesan error
$success_message = ""; // Tampung pesan sukses

// Proses update data service utama dan penambahan detail service
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Validasi CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_messages[] = "Kesalahan validasi CSRF token. Silakan coba lagi.";
        // Regenerate token untuk keamanan
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $csrf_token = $_SESSION['csrf_token'];
    } else {
        // Hapus token lama setelah digunakan, buat yang baru untuk request selanjutnya
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        // $csrf_token akan di-refresh saat halaman di-render ulang jika ada error,
        // atau tidak masalah jika redirect.

        $id_service_post = intval($_POST['id_service']);

        // Mulai transaksi
        mysqli_begin_transaction($koneksi);

        try {
            // Update data service utama
            $id_customer = mysqli_real_escape_string($koneksi, $_POST['id_customer']); // Diasumsikan tidak diubah, tapi tetap diambil
            $device = mysqli_real_escape_string($koneksi, $_POST['device']); // Diasumsikan tidak diubah
            $keluhan = mysqli_real_escape_string($koneksi, $_POST['keluhan']); // Diasumsikan tidak diubah

            $status_baru = mysqli_real_escape_string($koneksi, $_POST['status']);
            $estimasi_waktu = mysqli_real_escape_string($koneksi, $_POST['estimasi_waktu']);
            // Estimasi harga: jika kosong, set ke NULL, jika tidak, konversi ke float/decimal
            $estimasi_harga_input = trim($_POST['estimasi_harga']);
            $estimasi_harga = !empty($estimasi_harga_input) ? (float)$estimasi_harga_input : null;

            // Ambil status lama dan tanggal selesai saat ini dari database
            $query_cek_status_lama = "SELECT status, tanggal_selesai FROM service WHERE id_service = ?";
            $stmt_cek_status = $koneksi->prepare($query_cek_status_lama);
            $stmt_cek_status->bind_param("i", $id_service_post);
            $stmt_cek_status->execute();
            $hasil_cek = $stmt_cek_status->get_result();
            $data_lama = $hasil_cek->fetch_assoc();
            $stmt_cek_status->close();

            $status_lama = null;
            $tanggal_selesai_saat_ini = null;
            if ($data_lama) {
                $status_lama = $data_lama['status'];
                $tanggal_selesai_saat_ini = $data_lama['tanggal_selesai'];
            }

            $set_parts = [];
            $bind_types_update = "";
            $bind_params_update = [];

            // Kolom yang PASTI diupdate (jika boleh diubah dari form)
            // Untuk field readonly, Anda mungkin tidak perlu menambahkannya ke update jika memang tidak boleh diubah.
            // Contoh: id_customer, device, keluhan mungkin tidak perlu di-set ulang jika readonly.
            // Tapi jika ada kemungkinan field readonly ini diubah via inspect element, maka tetap set:
            $set_parts[] = "id_customer = ?";
            $bind_types_update .= "s"; // atau 'i' jika id_customer adalah int
            $bind_params_update[] = $id_customer;

            $set_parts[] = "device = ?";
            $bind_types_update .= "s";
            $bind_params_update[] = $device;

            $set_parts[] = "keluhan = ?";
            $bind_types_update .= "s";
            $bind_params_update[] = $keluhan;


            $set_parts[] = "status = ?";
            $bind_types_update .= "s";
            $bind_params_update[] = $status_baru;

            $set_parts[] = "estimasi_waktu = ?";
            $bind_types_update .= "s";
            $bind_params_update[] = $estimasi_waktu;

            $set_parts[] = "estimasi_harga = ?";
            $bind_types_update .= "d"; // 'd' untuk double/decimal, 'i' jika integer
            $bind_params_update[] = $estimasi_harga; // PHP null akan dikonversi ke SQL NULL

            // Logika untuk tanggal_selesai
            if ($status_baru == 'selesai' && ($status_lama != 'selesai' || empty($tanggal_selesai_saat_ini))) {
                $set_parts[] = "tanggal_selesai = CURDATE()"; // CURDATE() tidak di-bind
            } elseif ($status_lama == 'selesai' && $status_baru != 'selesai' && !empty($tanggal_selesai_saat_ini)) {
                // Jika status diubah DARI 'selesai' ke status lain, dan tanggal_selesai ada isinya
                $set_parts[] = "tanggal_selesai = NULL"; // Tidak di-bind
            }
            // Jika tidak ada kondisi di atas, tanggal_selesai tidak diubah oleh query ini.

            if (!empty($set_parts)) {
                $query_update_service = "UPDATE service SET " . implode(", ", $set_parts) . " WHERE id_service = ?";
                $stmt_update_service = $koneksi->prepare($query_update_service);

                if ($stmt_update_service) {
                    $bind_types_update .= "i"; // Untuk id_service di WHERE
                    $bind_params_update[] = $id_service_post;

                    // Spread operator (...) untuk $bind_params_update
                    $stmt_update_service->bind_param($bind_types_update, ...$bind_params_update);

                    if (!$stmt_update_service->execute()) {
                        $error_messages[] = "Error updating data service utama: " . $stmt_update_service->error;
                    }
                    $stmt_update_service->close();
                } else {
                    $error_messages[] = "Error preparing statement untuk service utama: " . $koneksi->error;
                }
            }

            // Proses penambahan detail service baru jika ada input dan tidak ada error sebelumnya
            if (empty($error_messages) && (!empty(trim($_POST['kerusakan_detail'])) || !empty($_POST['id_barang']) || !empty($_POST['id_jasa']))) {
                $id_barang_val = !empty($_POST['id_barang']) ? intval($_POST['id_barang']) : null;
                $id_jasa_val = !empty($_POST['id_jasa']) ? intval($_POST['id_jasa']) : null;
                $kerusakan_detail_val = mysqli_real_escape_string($koneksi, $_POST['kerusakan_detail']); // Tetap escape untuk string

                // Kalkulasi total_detail di server
                $server_calculated_total_detail = 0;
                if ($id_barang_val) {
                    $stmt_harga_brg = $koneksi->prepare("SELECT harga FROM stok WHERE id_barang = ?");
                    $stmt_harga_brg->bind_param("i", $id_barang_val);
                    $stmt_harga_brg->execute();
                    $res_brg = $stmt_harga_brg->get_result();
                    if ($row_brg = $res_brg->fetch_assoc()) {
                        $server_calculated_total_detail += (float)$row_brg['harga'];
                    }
                    $stmt_harga_brg->close();
                }
                if ($id_jasa_val) {
                    $stmt_harga_jsa = $koneksi->prepare("SELECT harga FROM jasa WHERE id_jasa = ?");
                    $stmt_harga_jsa->bind_param("i", $id_jasa_val);
                    $stmt_harga_jsa->execute();
                    $res_jsa = $stmt_harga_jsa->get_result();
                    if ($row_jsa = $res_jsa->fetch_assoc()) {
                        $server_calculated_total_detail += (float)$row_jsa['harga'];
                    }
                    $stmt_harga_jsa->close();
                }

                // Validasi minimal: Harus ada kerusakan ATAU barang/jasa dipilih
                if ($id_barang_val === null && $id_jasa_val === null && empty(trim($kerusakan_detail_val))) {
                    // Tidak ada data detail yang signifikan untuk ditambahkan
                } else {
                    $stmt_add_detail = $koneksi->prepare("INSERT INTO detail_service (id_service, id_barang, id_jasa, kerusakan, total) VALUES (?, ?, ?, ?, ?)");
                    if ($stmt_add_detail) {
                        // Tipe data: i (id_service), i (id_barang), i (id_jasa), s (kerusakan), d (total)
                        $stmt_add_detail->bind_param("iiisd", $id_service_post, $id_barang_val, $id_jasa_val, $kerusakan_detail_val, $server_calculated_total_detail);
                        if (!$stmt_add_detail->execute()) {
                            $error_messages[] = "Error menambahkan detail service: " . $stmt_add_detail->error;
                        }
                        $stmt_add_detail->close();
                    } else {
                        $error_messages[] = "Error preparing statement untuk detail service: " . $koneksi->error;
                    }
                }
            }

            // Commit atau Rollback
            if (empty($error_messages)) {
                mysqli_commit($koneksi);
                $success_message = "Data service berhasil diupdate";
                if (isset($stmt_add_detail) && $stmt_add_detail) { // Cek jika proses detail dijalankan
                    $success_message .= " dan detail baru berhasil ditambahkan!";
                } else if (!empty(trim($_POST['kerusakan_detail'])) || !empty($_POST['id_barang']) || !empty($_POST['id_jasa'])) {
                    // Ada upaya tambah detail tapi mungkin tidak signifikan (misal kerusakan kosong dan tidak ada barang/jasa)
                    // Atau jika ada error saat preparing statement detail.
                    // Jika $error_messages kosong, berarti tidak ada error, namun detail mungkin tidak ditambahkan jika tidak signifikan.
                }


                // Redirect setelah sukses untuk mencegah resubmission form
                // Anda bisa menggunakan flash message session di sini jika mau.
                echo "<script>
                        alert('" . $success_message . "');
                        window.location.href='edit_service.php?id=$id_service_post';
                      </script>";
                exit;
            } else {
                mysqli_rollback($koneksi);
                // Error messages sudah ada di $error_messages
            }
        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            $error_messages[] = "Terjadi pengecualian: " . $e->getMessage();
        }
    } // End CSRF validation
} // End POST request

// Ambil data service yang akan diedit (setelah potensi update jika ada error dan halaman dirender ulang)
$query_service = "SELECT * FROM service WHERE id_service = ?";
$stmt_service = $koneksi->prepare($query_service);
$stmt_service->bind_param("i", $id_service);
$stmt_service->execute();
$result_service = $stmt_service->get_result();

if (!$result_service || $result_service->num_rows == 0) {
    echo "Data service tidak ditemukan.";
    exit;
}
$service = $result_service->fetch_assoc();
$stmt_service->close();

// Ambil data detail service yang terkait
$query_detail = "SELECT ds.*, b.nama_barang, j.jenis_jasa 
                 FROM detail_service ds
                 LEFT JOIN stok b ON ds.id_barang = b.id_barang
                 LEFT JOIN jasa j ON ds.id_jasa = j.id_jasa
                 WHERE ds.id_service = ? ORDER BY ds.id_ds ASC"; // Urutkan detail
$stmt_detail = $koneksi->prepare($query_detail);
$stmt_detail->bind_param("i", $id_service);
$stmt_detail->execute();
$result_detail = $stmt_detail->get_result();

// Ambil data untuk dropdown barang dan jasa
$barangs_result = mysqli_query($koneksi, "SELECT id_barang, nama_barang, harga FROM stok ORDER BY nama_barang");
$jasas_result = mysqli_query($koneksi, "SELECT id_jasa, jenis_jasa, harga FROM jasa ORDER BY jenis_jasa");

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Service & Tambah Detail - <?php echo htmlspecialchars($service['id_service']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-dark">
    <div class="container bg-white p-4 p-md-5 rounded shadow-sm my-4 my-md-5" style="max-width: 960px;">
        <h2 class="text-primary mb-4 pb-2 border-bottom border-primary border-2 fw-semibold fs-3">
            Edit Service ID: <?php echo htmlspecialchars($service['id_service']); ?>
        </h2>

        <?php if (!empty($error_messages)): ?>
            <div class="alert alert-danger" role="alert">
                <strong>Terjadi kesalahan:</strong>
                <ul>
                    <?php foreach ($error_messages as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="edit_service.php?id=<?php echo $id_service; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="id_service" value="<?php echo $service['id_service']; ?>">

            <div class="card mb-4 shadow-sm">
                <div class="card-header">
                    <h3 class="mb-0 fs-5 fw-medium">Data Service Utama</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="id_customer" class="form-label fw-medium">ID Customer:</label>
                            <input type="number" id="id_customer" name="id_customer" class="form-control" value="<?php echo htmlspecialchars($service['id_customer']); ?>" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="device" class="form-label fw-medium">Device:</label>
                            <input type="text" id="device" name="device" class="form-control" value="<?php echo htmlspecialchars($service['device']); ?>" maxlength="20" readonly>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="keluhan" class="form-label fw-medium">Keluhan:</label>
                        <textarea id="keluhan" name="keluhan" class="form-control" rows="3" readonly><?php echo htmlspecialchars($service['keluhan']); ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label fw-medium">Status:</label>
                            <select id="status" name="status" class="form-select">
                                <option value="diajukan" <?php echo ($service['status'] == 'diajukan') ? 'selected' : ''; ?>>Diajukan</option>
                                <option value="dikonfirmasi" <?php echo ($service['status'] == 'dikonfirmasi') ? 'selected' : ''; ?>>Dikonfirmasi</option>
                                <option value="menunggu sparepart" <?php echo ($service['status'] == 'menunggu sparepart') ? 'selected' : ''; ?>>Menunggu Sparepart</option>
                                <option value="diperbaiki" <?php echo ($service['status'] == 'diperbaiki') ? 'selected' : ''; ?>>Diperbaiki</option>
                                <option value="selesai" <?php echo ($service['status'] == 'selesai') ? 'selected' : ''; ?>>Selesai</option>
                                <option value="dibatalkan" <?php echo ($service['status'] == 'dibatalkan') ? 'selected' : ''; ?>>Dibatalkan</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="tanggal_selesai" class="form-label fw-medium">Tanggal Selesai:</label>
                            <input type="date" id="tanggal_selesai" name="tanggal_selesai" class="form-control" value="<?php echo htmlspecialchars($service['tanggal_selesai']); ?>" readonly>
                            <div class="form-text">Otomatis jika status "Selesai" & sebelumnya kosong.</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="estimasi_waktu" class="form-label fw-medium">Estimasi Waktu:</label>
                            <input type="text" id="estimasi_waktu" name="estimasi_waktu" class="form-control" value="<?php echo htmlspecialchars($service['estimasi_waktu']); ?>" maxlength="100">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="estimasi_harga" class="form-label fw-medium">Estimasi Harga (Rp):</label>
                            <input type="number" step="any" id="estimasi_harga" name="estimasi_harga" class="form-control" value="<?php echo htmlspecialchars($service['estimasi_harga']); ?>" placeholder="Kosongkan jika belum ada">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4 shadow-sm">
                <div class="card-header">
                    <h3 class="mb-0 fs-5 fw-medium">Detail Service Saat Ini</h3>
                </div>
                <div class="card-body <?php if ($result_detail && $result_detail->num_rows > 0) echo 'p-0'; ?>">
                    <?php if ($result_detail && $result_detail->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="fw-semibold">ID Detail</th>
                                        <th class="fw-semibold">Barang</th>
                                        <th class="fw-semibold">Jasa</th>
                                        <th class="fw-semibold">Kerusakan/Tindakan</th>
                                        <th class="text-end fw-semibold">Total (Rp)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($detail = $result_detail->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($detail['id_ds']); ?></td>
                                            <td><?php echo htmlspecialchars($detail['nama_barang'] ?: 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($detail['jenis_jasa'] ?: 'N/A'); ?></td>
                                            <td><?php echo nl2br(htmlspecialchars($detail['kerusakan'])); ?></td>
                                            <td class="text-end"><?php echo number_format($detail['total'], 0, ',', '.'); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="m-3">Belum ada detail service untuk service ini.</p> <?php endif; ?>
                </div>
            </div>

            <div class="card mb-4 shadow-sm">
                <div class="card-header">
                    <h3 class="mb-0 fs-5 fw-medium">Tambah Detail Service Baru</h3>
                </div>
                <div class="card-body">
                    <p class="form-text mb-3">Isi bagian ini hanya jika ingin menambahkan item baru. Total Biaya Detail akan dihitung otomatis di server.</p>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="id_barang" class="form-label fw-medium">Barang (Sparepart):</label>
                            <select id="id_barang" name="id_barang" class="form-select">
                                <option value="">-- Pilih Barang --</option>
                                <?php mysqli_data_seek($barangs_result, 0); ?>
                                <?php while ($b = mysqli_fetch_assoc($barangs_result)): ?>
                                    <option value="<?php echo $b['id_barang']; ?>" data-harga="<?php echo $b['harga']; ?>"><?php echo htmlspecialchars($b['nama_barang']) . " (Rp " . number_format($b['harga'], 0, ',', '.') . ")"; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="id_jasa" class="form-label fw-medium">Jasa:</label>
                            <select id="id_jasa" name="id_jasa" class="form-select">
                                <option value="">-- Pilih Jasa --</option>
                                <?php mysqli_data_seek($jasas_result, 0); ?>
                                <?php while ($j = mysqli_fetch_assoc($jasas_result)): ?>
                                    <option value="<?php echo $j['id_jasa']; ?>" data-harga="<?php echo $j['harga']; ?>"><?php echo htmlspecialchars($j['jenis_jasa']) . " (Rp " . number_format($j['harga'], 0, ',', '.') . ")"; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="kerusakan_detail" class="form-label fw-medium">Deskripsi Kerusakan/Tindakan (Detail Baru):</label>
                        <textarea id="kerusakan_detail" name="kerusakan_detail" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="total_detail_display" class="form-label fw-medium">Perkiraan Total Biaya Detail (Client Side):</label>
                        <input type="text" id="total_detail_display" name="total_detail_display" class="form-control" placeholder="Terisi otomatis jika barang/jasa dipilih" readonly>
                        <div class="form-text">Estimasi sisi klien. Total final dihitung server.</div>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary me-2">Simpan Perubahan & Tambah Detail (jika diisi)</button>
                <a href="data_service.php" class="btn btn-secondary">Kembali ke Daftar Service</a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const barangSelect = document.getElementById('id_barang');
            const jasaSelect = document.getElementById('id_jasa');
            const totalDetailDisplayInput = document.getElementById('total_detail_display'); // Display only

            function updateTotalDisplay() {
                let total = 0;
                const selectedBarang = barangSelect.options[barangSelect.selectedIndex];
                const selectedJasa = jasaSelect.options[jasaSelect.selectedIndex];

                if (selectedBarang && selectedBarang.dataset.harga) {
                    total += parseFloat(selectedBarang.dataset.harga);
                }
                if (selectedJasa && selectedJasa.dataset.harga) {
                    total += parseFloat(selectedJasa.dataset.harga);
                }
                // Format sebagai mata uang Rupiah untuk tampilan
                totalDetailDisplayInput.value = total.toLocaleString('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    minimumFractionDigits: 0
                });
            }

            if (barangSelect) barangSelect.addEventListener('change', updateTotalDisplay);
            if (jasaSelect) jasaSelect.addEventListener('change', updateTotalDisplay);

            // Inisialisasi tampilan total jika ada nilai awal (biasanya tidak untuk form tambah baru)
            updateTotalDisplay();


            // Script untuk tanggal selesai dinamis
            const statusSelect = document.getElementById('status');
            const tanggalSelesaiInput = document.getElementById('tanggal_selesai');
            const originalStatus = '<?php echo htmlspecialchars($service['status']); ?>';
            const originalTanggalSelesai = tanggalSelesaiInput.value; // Ambil nilai saat load

            statusSelect.addEventListener('change', function() {
                if (this.value === 'selesai') {
                    // Hanya set tanggal hari ini jika tanggal_selesai masih kosong atau status sebelumnya bukan 'selesai'
                    if (!tanggalSelesaiInput.value || originalStatus !== 'selesai') {
                        const today = new Date();
                        const year = today.getFullYear();
                        const month = ('0' + (today.getMonth() + 1)).slice(-2);
                        const day = ('0' + today.getDate()).slice(-2);
                        tanggalSelesaiInput.value = `${year}-${month}-${day}`;
                    } else {
                        // Jika sudah 'selesai' dan ada tanggalnya, biarkan.
                        tanggalSelesaiInput.value = originalTanggalSelesai;
                    }
                } else {
                    // Jika status diubah DARI 'selesai' ke status lain
                    if (originalStatus === 'selesai' && originalTanggalSelesai) {
                        // tanggalSelesaiInput.value = ''; // Akan di-handle backend untuk NULL
                        // Untuk konsistensi visual, bisa di-clear, tapi backend yg menentukan.
                        // Jika mau, biarkan saja, backend akan set ke NULL jika logikanya demikian.
                    }
                }
            });
            // Panggil sekali saat load untuk inisialisasi jika status awal 'selesai' tapi tanggal kosong (jarang terjadi jika logic benar)
            if (statusSelect.value === 'selesai' && !tanggalSelesaiInput.value) {
                const today = new Date();
                const year = today.getFullYear();
                const month = ('0' + (today.getMonth() + 1)).slice(-2);
                const day = ('0' + today.getDate()).slice(-2);
                tanggalSelesaiInput.value = `${year}-${month}-${day}`;
            }

        });
    </script>

</body>

</html>
<?php
// Free results
if (isset($result_service) && $result_service instanceof mysqli_result) mysqli_free_result($result_service);
if (isset($result_detail) && $result_detail instanceof mysqli_result) mysqli_free_result($result_detail);
if (isset($barangs_result) && $barangs_result instanceof mysqli_result) mysqli_free_result($barangs_result);
if (isset($jasas_result) && $jasas_result instanceof mysqli_result) mysqli_free_result($jasas_result);

// Close connection
mysqli_close($koneksi);
?>