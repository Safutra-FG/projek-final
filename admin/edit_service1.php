<?php
include '../koneksi.php';

$id_service = null;
if (isset($_GET['id'])) {
    $id_service = intval($_GET['id']);
} else {
    echo "ID Service tidak ditemukan.";
    exit;
}
// Proses update data service utama dan penambahan detail service
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_service_post = intval($_POST['id_service']); // Ambil id_service dari hidden input

    // Update data service utama
    $id_customer = mysqli_real_escape_string($koneksi, $_POST['id_customer']);
    $device = mysqli_real_escape_string($koneksi, $_POST['device']);
    $keluhan = mysqli_real_escape_string($koneksi, $_POST['keluhan']);
    // $status = mysqli_real_escape_string($koneksi, $_POST['status']);
    $estimasi_waktu = mysqli_real_escape_string($koneksi, $_POST['estimasi_waktu']);
    $estimasi_harga = !empty($_POST['estimasi_harga']) ? mysqli_real_escape_string($koneksi, $_POST['estimasi_harga']) : 'NULL';
    // $tanggal_selesai = !empty($_POST['tanggal_selesai']) ? "'" . mysqli_real_escape_string($koneksi, $_POST['tanggal_selesai']) . "'" : 'NULL';

    $status_baru = mysqli_real_escape_string($koneksi, $_POST['status']);
    $kolom_tanggal_selesai_update = ""; // String tambahan untuk query SQL

    // Ambil status lama dari database untuk perbandingan
    $query_cek_status_lama = "SELECT status, tanggal_selesai FROM service WHERE id_service = $id_service_post";
    $hasil_cek = mysqli_query($koneksi, $query_cek_status_lama);
    $data_lama = mysqli_fetch_assoc($hasil_cek);
    $status_lama = null;
    $tanggal_selesai_saat_ini = null;

    if ($data_lama) {
        $status_lama = $data_lama['status'];
        $tanggal_selesai_saat_ini = $data_lama['tanggal_selesai'];
    }
    // Jika status diubah menjadi 'selesai' DAN sebelumnya bukan 'selesai' (atau tanggal_selesai masih kosong)
    if ($status_baru == 'selesai' && ($status_lama != 'selesai' || empty($tanggal_selesai_saat_ini))) {
        // Set tanggal_selesai ke tanggal hari ini
        $kolom_tanggal_selesai_update = ", tanggal_selesai = CURDATE()";
    }


    // --- AWAL BAGIAN YANG DIUBAH UNTUK KONSTRUKSI QUERY ---
    $set_clauses = []; // Array untuk menampung bagian SET
    $set_clauses[] = "id_customer = '$id_customer'";
    $set_clauses[] = "device = '$device'";
    $set_clauses[] = "keluhan = '$keluhan'";
    $set_clauses[] = "status = '$status_baru'";
    $set_clauses[] = "estimasi_waktu = '$estimasi_waktu'";
    $set_clauses[] = "estimasi_harga = $estimasi_harga";

    // Logika untuk tanggal_selesai tetap sama, tapi kita tambahkan ke array $set_clauses
    if ($status_baru == 'selesai' && ($status_lama != 'selesai' || empty($tanggal_selesai_saat_ini))) {
        $set_clauses[] = "tanggal_selesai = CURDATE()";
    }
    // Jika admin mengubah status DARI 'selesai' ke status lain, dan kamu ingin tanggal_selesai di-reset:
    // else if ($status_lama == 'selesai' && $status_baru != 'selesai') {
    //     $set_clauses[] = "tanggal_selesai = NULL"; 
    // }
    // Jika tidak, tanggal_selesai yang sudah ada akan tetap.

    $query_update_service = "UPDATE service SET " . implode(", ", $set_clauses) . " WHERE id_service = $id_service_post";
    // --- AKHIR BAGIAN YANG DIUBAH UNTUK KONSTRUKSI QUERY ---

    $service_update_successful = false;
    $detail_add_attempted = false;
    $detail_add_successful = false;
    $error_messages = []; // Tampung pesan error


    if (mysqli_query($koneksi, $query_update_service)) {
        $service_update_successful = true;
    } else {
        $error_messages[] = "Error updating data service utama: " . mysqli_error($koneksi);
    }

    // Proses penambahan detail service baru jika ada input
    if (!empty(trim($_POST['kerusakan_detail'])) || !empty($_POST['id_barang']) || !empty($_POST['id_jasa']) || !empty($_POST['total_detail'])) {
        $detail_add_attempted = true;

        // Ambil nilai, persiapkan untuk NULL jika kosong
        // Pastikan kolom id_barang dan id_jasa di DB kamu memperbolehkan NULL
        $id_barang_val = !empty($_POST['id_barang']) ? intval($_POST['id_barang']) : null;
        $id_jasa_val = !empty($_POST['id_jasa']) ? intval($_POST['id_jasa']) : null; // Jika id_jasa WAJIB, jangan set null.
        $kerusakan_detail_val = mysqli_real_escape_string($koneksi, $_POST['kerusakan_detail']);
        $total_detail_val = !empty($_POST['total_detail']) ? intval($_POST['total_detail']) : 0; // Default 0 jika kosong

        // Validasi minimal: Jika semua opsional kosong, mungkin tidak perlu insert.
        // Namun, jika kerusakan_detail diisi, itu sudah cukup.
        if ($id_barang_val === null && $id_jasa_val === null && empty(trim($kerusakan_detail_val)) && $total_detail_val == 0) {
            // Tidak ada data detail yang signifikan untuk ditambahkan
            $detail_add_attempted = false; // Anggap tidak ada percobaan tambah detail
        } else {
            $stmt_add_detail = $koneksi->prepare("INSERT INTO detail_service (id_service, id_barang, id_jasa, kerusakan, total) VALUES (?, ?, ?, ?, ?)");
            if ($stmt_add_detail) {
                // Tipe data: i (id_service), i (id_barang), i (id_jasa), s (kerusakan), i (total)
                // Perhatikan tipe data untuk id_barang dan id_jasa jika null.
                // Jika kolomnya INT dan nullable, PHP null akan dihandle dengan benar oleh prepared statement.
                $stmt_add_detail->bind_param("iiisi", $id_service_post, $id_barang_val, $id_jasa_val, $kerusakan_detail_val, $total_detail_val);
                if ($stmt_add_detail->execute()) {
                    $detail_add_successful = true;
                } else {
                    $error_messages[] = "Error menambahkan detail service: " . $stmt_add_detail->error;
                }
                $stmt_add_detail->close();
            } else {
                $error_messages[] = "Error preparing statement untuk detail service: " . $koneksi->error;
            }
        }
    }

    if (!empty($error_messages)) {
        echo "<script>alert('Terjadi kesalahan:\\n" . implode("\\n", array_map('addslashes', $error_messages)) . "');</script>";
        // Jika ingin tetap redirect meskipun ada error parsial (misal service utama sukses, detail gagal):
        // echo "<script>window.location.href='edit_service.php?id=$id_service_post';</script>";
    }

    if ($service_update_successful) {
        if ($detail_add_attempted) {
            if ($detail_add_successful) {
                echo "<script>alert('Data service utama DAN detail berhasil diupdate!'); window.location.href='edit_service.php?id=$id_service_post';</script>";
            } else if (empty($error_messages)) { // Detail tidak berhasil tapi tidak ada error spesifik di array (jarang terjadi jika logic benar)
                echo "<script>alert('Data service utama berhasil diupdate, tetapi ada masalah saat menambah detail.'); window.location.href='edit_service.php?id=$id_service_post';</script>";
            } else if (!empty($error_messages) && $service_update_successful) {
                // Error sudah ditampilkan, mungkin hanya redirect atau pesan tambahan bahwa service utama tetap terupdate.
                // Untuk simpelnya, jika service utama sukses dan tidak ada error lain yang fatal, kita bisa redirect
                echo "<script>alert('Data service utama berhasil diupdate. Lihat pesan error untuk detail.'); window.location.href='edit_service.php?id=$id_service_post';</script>";
            }
        } else { // Service utama sukses, tidak ada percobaan tambah detail
            echo "<script>alert('Data service utama berhasil diupdate!'); window.location.href='edit_service.php?id=$id_service_post';</script>";
        }
    } else if (empty($error_messages)) { // Jika service utama tidak sukses, tapi tidak ada pesan error (jarang terjadi)
        echo "<script>alert('Update data service utama gagal tanpa pesan error spesifik.');</script>";
    }
    // Jika service_update_successful false dan ada error_messages, error sudah ditampilkan. Tidak perlu alert tambahan.

    exit; // Hentikan eksekusi setelah menampilkan alert dan redirect agar sisa HTML tidak diproses ulang dengan data lama.
}

// Ambil data service yang akan diedit
$query_service = "SELECT * FROM service WHERE id_service = $id_service";
$result_service = mysqli_query($koneksi, $query_service);
if (!$result_service || mysqli_num_rows($result_service) == 0) {
    echo "Data service tidak ditemukan.";
    exit;
}
$service = mysqli_fetch_assoc($result_service);

// Ambil data detail service yang terkait
$query_detail = "SELECT ds.*, b.nama_barang, j.jenis_jasa 
                 FROM detail_service ds
                 LEFT JOIN stok b ON ds.id_barang = b.id_barang
                 LEFT JOIN jasa j ON ds.id_jasa = j.id_jasa
                 WHERE ds.id_service = $id_service";
$result_detail = mysqli_query($koneksi, $query_detail);

// Ambil data untuk dropdown barang dan jasa
$barangs = mysqli_query($koneksi, "SELECT id_barang, nama_barang, harga FROM stok ORDER BY nama_barang");
$jasas = mysqli_query($koneksi, "SELECT id_jasa, jenis_jasa, harga FROM jasa ORDER BY jenis_jasa");

?>
<!DOCTYPE html>
<html>

<head>
    <title>Edit Service & Tambah Detail</title>
    <style>
        body {
            font-family: sans-serif;
        }

        .container {
            width: 70%;
            margin: auto;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        label {
            display: block;
            margin-bottom: 5px;
        }

        input[type="text"],
        input[type="number"],
        input[type="date"],
        textarea,
        select {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 3px;
            box-sizing: border-box;
        }

        input[type="submit"] {
            background-color: #007bff;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }

        .back-link {
            display: inline-block;
            margin-top: 15px;
            text-align: center;
            padding: 8px 12px;
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 3px;
        }

        .section-title {
            margin-top: 20px;
            margin-bottom: 10px;
            font-size: 1.2em;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>Edit Service ID: <?php echo htmlspecialchars($service['id_service']); ?></h2>
        <form method="POST" action="edit_service.php?id=<?php echo $id_service; ?>">
            <input type="hidden" name="id_service" value="<?php echo $service['id_service']; ?>">

            <h3 class="section-title">Data Service Utama</h3>
            <label for="id_customer">ID Customer:</label>
            <input type="number" id="id_customer" name="id_customer" value="<?php echo htmlspecialchars($service['id_customer']); ?>" readonly>

            <label for="device">Device:</label>
            <input type="text" id="device" name="device" value="<?php echo htmlspecialchars($service['device']); ?>" maxlength="20" readonly>

            <label for="keluhan">Keluhan:</label>
            <textarea id="keluhan" name="keluhan" rows="3" readonly><?php echo htmlspecialchars($service['keluhan']); ?></textarea>

            <label for="status">Status:</label>
            <select id="status" name="status">
                <option value="diajukan" <?php echo ($service['status'] == 'diajukan') ? 'selected' : ''; ?>>Diajukan</option>
                <option value="dikonfirmasi" <?php echo ($service['status'] == 'dikonfirmasi') ? 'selected' : ''; ?>>Dikonfirmasi</option>
                <option value="menunggu sparepart" <?php echo ($service['status'] == 'menunggu sparepart') ? 'selected' : ''; ?>>Menunggu Sparepart</option>
                <option value="diperbaiki" <?php echo ($service['status'] == 'diperbaiki') ? 'selected' : ''; ?>>Diperbaiki</option>
                <option value="selesai" <?php echo ($service['status'] == 'selesai') ? 'selected' : ''; ?>>Selesai</option>
                <option value="dibatalkan" <?php echo ($service['status'] == 'dibatalkan') ? 'selected' : ''; ?>>Dibatalkan</option>
            </select>

            <label for="estimasi_waktu">Estimasi Waktu (misal: 3 hari, 1 minggu):</label>
            <input type="text" id="estimasi_waktu" name="estimasi_waktu" value="<?php echo htmlspecialchars($service['estimasi_waktu']); ?>" maxlength="100">

            <label for="estimasi_harga">Estimasi Harga (Rp):</label>
            <input type="number" id="estimasi_harga" name="estimasi_harga" value="<?php echo htmlspecialchars($service['estimasi_harga']); ?>">

            <label for="tanggal_selesai">Tanggal Selesai (Otomatis jika status "Selesai"):</label>
            <input type="date" id="tanggal_selesai" name="tanggal_selesai" value="<?php echo htmlspecialchars($service['tanggal_selesai']); ?>" readonly>

            <h3 class="section-title">Detail Service Saat Ini</h3>
            <?php if (mysqli_num_rows($result_detail) > 0): ?>
                <table>
                    <tr>
                        <th>ID Detail</th>
                        <th>Barang</th>
                        <th>Jasa</th>
                        <th>Kerusakan</th>
                        <th>Total</th>
                    </tr>
                    <?php while ($detail = mysqli_fetch_assoc($result_detail)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($detail['id_ds']); ?></td>
                            <td><?php echo htmlspecialchars($detail['nama_barang'] ?: 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($detail['jenis_jasa'] ?: 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($detail['kerusakan']); ?></td>
                            <td><?php echo number_format($detail['total']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </table>
            <?php else: ?>
                <p>Belum ada detail service untuk service ini.</p>
            <?php endif; ?>

            <h3 class="section-title">Tambah Detail Service Baru</h3>
            <label for="id_barang">Barang (Sparepart):</label>
            <select id="id_barang" name="id_barang">
                <option value="">-- Pilih Barang --</option>
                <?php while ($b = mysqli_fetch_assoc($barangs)): ?>
                    <option value="<?php echo $b['id_barang']; ?>" data-harga="<?php echo $b['harga']; ?>"><?php echo htmlspecialchars($b['nama_barang']) . " (Rp " . number_format($b['harga']) . ")"; ?></option>
                <?php endwhile; ?>
            </select>

            <label for="id_jasa">Jasa:</label>
            <select id="id_jasa" name="id_jasa">
                <option value="">-- Pilih Jasa --</option>
                <?php while ($j = mysqli_fetch_assoc($jasas)): ?>
                    <option value="<?php echo $j['id_jasa']; ?>" data-harga="<?php echo $j['harga']; ?>"><?php echo htmlspecialchars($j['jenis_jasa']) . " (Rp " . number_format($j['harga']) . ")"; ?></option>
                <?php endwhile; ?>
            </select>

            <label for="kerusakan_detail">Deskripsi Kerusakan/Tindakan (Detail):</label>
            <textarea id="kerusakan_detail" name="kerusakan_detail" rows="2"></textarea>

            <label for="total_detail">Total Biaya Detail (Rp):</label>
            <input type="number" id="total_detail" name="total_detail" placeholder="Akan terisi otomatis jika barang/jasa dipilih">

            <p><strong>Catatan:</strong> Isi bagian "Tambah Detail Service Baru" hanya jika ingin menambahkan item baru saat menyimpan perubahan service utama.</p>

            <input type="submit" value="Simpan Perubahan & Tambah Detail (jika diisi)">
        </form>
        <a href="data_service.php" class="back-link">Kembali ke Daftar Service</a>
    </div>

    <script>
        // Script sederhana untuk menghitung total detail_service (opsional)
        document.addEventListener('DOMContentLoaded', function() {
            const barangSelect = document.getElementById('id_barang');
            const jasaSelect = document.getElementById('id_jasa');
            const totalDetailInput = document.getElementById('total_detail');

            function updateTotal() {
                let total = 0;
                const selectedBarang = barangSelect.options[barangSelect.selectedIndex];
                const selectedJasa = jasaSelect.options[jasaSelect.selectedIndex];

                if (selectedBarang && selectedBarang.dataset.harga) {
                    total += parseFloat(selectedBarang.dataset.harga);
                }
                if (selectedJasa && selectedJasa.dataset.harga) {
                    total += parseFloat(selectedJasa.dataset.harga);
                }
                totalDetailInput.value = total;
            }

            if (barangSelect) barangSelect.addEventListener('change', updateTotal);
            if (jasaSelect) jasaSelect.addEventListener('change', updateTotal);
        });
    </script>

</body>

</html>
<?php
mysqli_free_result($result_service);
mysqli_free_result($result_detail);
mysqli_free_result($barangs);
mysqli_free_result($jasas);
mysqli_close($koneksi);
?>