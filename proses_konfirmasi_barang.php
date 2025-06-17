<?php
session_start();
include 'koneksi.php';

// Fungsi helper untuk mencatat error ke log file
function log_error($message) {
    error_log(date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, 3, 'error_log.txt');
}

$error_message = null;
$success_message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_konfirmasi'])) {
    $nama = trim($_POST['nama'] ?? '');
    $nohp = trim($_POST['nohp'] ?? '');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $metode_transfer = trim($_POST['metode_transfer'] ?? '');
    $nama_pengirim = trim($_POST['nama_pengirim'] ?? '');
    $jumlah = 0;
    $id_transaksi = null;

    // Validasi input
    if (empty($nama) || empty($nohp) || !$email || empty($metode_transfer) || empty($nama_pengirim)) {
        $error_message = "Semua field wajib diisi dengan format yang benar.";
    } elseif (!in_array($metode_transfer, ['cash', 'transfer'])) {
        $error_message = "Metode pembayaran tidak valid.";
    } else {
        // Validasi file upload
        if (!isset($_FILES['bukti_pembayaran']) || $_FILES['bukti_pembayaran']['error'] !== UPLOAD_ERR_OK) {
            $error_message = "Gagal mengunggah file bukti pembayaran.";
        } else {
            $file = $_FILES['bukti_pembayaran'];
            $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($file['type'], $allowed_types)) {
                $error_message = "Format file tidak didukung. Gunakan JPG, PNG, atau PDF.";
            } elseif ($file['size'] > $max_size) {
                $error_message = "Ukuran file terlalu besar. Maksimal 5MB.";
            } else {
                $upload_dir = 'uploads/bukti_pembayaran/' . date('Y/m/');
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'bukti_' . time() . '_' . uniqid() . '.' . $file_extension;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    try {
                        $koneksi->begin_transaction();

                        // Cari id_customer
                        $id_customer = null;
                        $sql_cek_customer = "SELECT id_customer FROM customer WHERE nama_customer = ? AND no_telepon = ? AND email = ?";
                        $stmt_cek = $koneksi->prepare($sql_cek_customer);
                        if (!$stmt_cek) throw new Exception("Prepare statement cek customer gagal: " . $koneksi->error);
                        $stmt_cek->bind_param("sss", $nama, $nohp, $email);
                        $stmt_cek->execute();
                        $result_cek = $stmt_cek->get_result();
                        if ($result_cek->num_rows > 0) {
                            $row_cust = $result_cek->fetch_assoc();
                            $id_customer = $row_cust['id_customer'];
                        } else {
                            $sql_insert_customer = "INSERT INTO customer (nama_customer, no_telepon, email) VALUES (?, ?, ?)";
                            $stmt_insert_cust = $koneksi->prepare($sql_insert_customer);
                            if (!$stmt_insert_cust) throw new Exception("Prepare statement insert customer gagal: " . $koneksi->error);
                            $stmt_insert_cust->bind_param("sss", $nama, $nohp, $email);
                            if (!$stmt_insert_cust->execute()) throw new Exception("Insert customer baru gagal: " . $stmt_insert_cust->error);
                            $id_customer = $koneksi->insert_id;
                            $stmt_insert_cust->close();
                        }
                        $stmt_cek->close();
                        if (!$id_customer) throw new Exception("Gagal mendapatkan ID Customer.");

                        // Ambil data keranjang dan hitung total
                        $cart = $_SESSION['cart'] ?? [];
                        log_error("Cart contents (proses_konfirmasi_barang): " . json_encode($cart));
                        if (empty($cart)) throw new Exception("Keranjang belanja kosong.");
                        $items_to_process = [];
                        $total_belanja = 0;
                        $placeholders = implode(',', array_fill(0, count($cart), '?'));
                        $types = str_repeat('i', count($cart));
                        $item_ids = array_keys($cart);
                        log_error("Item IDs from cart (proses_konfirmasi_barang): " . json_encode($item_ids));
                        $sql_harga = "SELECT id_barang, harga FROM stok WHERE id_barang IN ($placeholders) FOR UPDATE";
                        $stmt_harga = $koneksi->prepare($sql_harga);
                        if (!$stmt_harga) throw new Exception("Prepare statement harga gagal: " . $koneksi->error);
                        $stmt_harga->bind_param($types, ...$item_ids);
                        $stmt_harga->execute();
                        $result_harga = $stmt_harga->get_result();
                        
                        log_error("Number of rows found for cart items (proses_konfirmasi_barang): " . $result_harga->num_rows . " out of " . count($cart));

                        if ($result_harga->num_rows !== count($cart)) {
                             // --- Debugging: Log missing items --- 
                             $found_ids = [];
                             while ($row_temp = $result_harga->fetch_assoc()) {
                                 $found_ids[] = $row_temp['id_barang'];
                             }
                             $missing_ids = array_diff($item_ids, $found_ids);
                             log_error("Missing item IDs (proses_konfirmasi_barang): " . json_encode($missing_ids));
                             // --- End Debugging --- 
                            throw new Exception("Beberapa item di keranjang tidak ditemukan di database.");
                        }
                        while ($barang_db = $result_harga->fetch_assoc()) {
                            log_error("Found item in DB (proses_konfirmasi_barang): " . json_encode($barang_db));
                            $id_b = $barang_db['id_barang'];
                            $qty_pesan = $cart[$id_b];
                            $subtotal_item = $qty_pesan * $barang_db['harga'];
                            $total_belanja += $subtotal_item;
                            $items_to_process[] = [
                                'id_barang' => $id_b,
                                'jumlah' => $qty_pesan,
                                'harga_satuan' => $barang_db['harga'],
                                'subtotal' => $subtotal_item
                            ];
                        }
                        $stmt_harga->close();
                        $jumlah = $total_belanja;

                        // Buat transaksi jika belum ada (status: menunggu pembayaran)
                        $sql_trans = "INSERT INTO transaksi (id_customer, jenis, tanggal, total, status) VALUES (?, 'penjualan', NOW(), ?, 'menunggu pembayaran')";
                        $stmt_trans = $koneksi->prepare($sql_trans);
                        $stmt_trans->bind_param("id", $id_customer, $jumlah);
                        if (!$stmt_trans->execute()) throw new Exception("Insert transaksi gagal: " . $stmt_trans->error);
                        $id_transaksi = $koneksi->insert_id;
                        $stmt_trans->close();

                        // Buat detail transaksi
                        $sql_insert_detail = "INSERT INTO detail_transaksi (id_transaksi, id_barang, jumlah, subtotal) VALUES (?, ?, ?, ?)";
                        $stmt_detail = $koneksi->prepare($sql_insert_detail);
                        if (!$stmt_detail) throw new Exception("Prepare statement insert detail_transaksi gagal: " . $koneksi->error);
                        foreach ($items_to_process as $item) {
                            $stmt_detail->bind_param("iiid", $id_transaksi, $item['id_barang'], $item['jumlah'], $item['subtotal']);
                            if (!$stmt_detail->execute()) throw new Exception("Insert detail_transaksi gagal: " . $stmt_detail->error);
                        }
                        $stmt_detail->close();

                        // Insert ke tabel bayar
                        $sql_bayar = "INSERT INTO bayar (id_transaksi, tanggal, jumlah, metode, status, bukti, catatan) VALUES (?, NOW(), ?, ?, 'menunggu konfirmasi', ?, '')";
                        $stmt_bayar = $koneksi->prepare($sql_bayar);
                        $stmt_bayar->bind_param("idss", $id_transaksi, $jumlah, $metode_transfer, $filepath);
                        if (!$stmt_bayar->execute()) throw new Exception("Insert bayar gagal: " . $stmt_bayar->error);
                        $stmt_bayar->close();

                        $koneksi->commit();
                        $_SESSION['cart'] = [];
                        $success_message = "Konfirmasi pembayaran berhasil dikirim! Pesanan Anda sedang menunggu validasi admin.";
                        header("Location: pesanan_sukses.php?id_transaksi=" . $id_transaksi . "&total=" . $jumlah . "&status=menunggu_validasi");
                        exit;
                    } catch (Exception $e) {
                        if (isset($koneksi)) $koneksi->rollback();
                        log_error("Error in proses_konfirmasi_barang: " . $e->getMessage());
                        $error_message = "Terjadi kesalahan: " . $e->getMessage();
                        if (file_exists($filepath)) unlink($filepath);
                    } finally {
                        if (isset($koneksi)) $koneksi->close();
                    }
                } else {
                    $error_message = "Gagal menyimpan file bukti pembayaran.";
                }
            }
        }
    }
}

if ($error_message) {
    $_SESSION['error_message'] = $error_message;
    header("Location: transaksi_barang.php");
    exit;
}
?> 