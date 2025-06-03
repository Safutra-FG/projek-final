<?php
session_start();
$koneksi = new mysqli("localhost", "root", "", "tharz_computer");

// Proses data pembayaran
if(isset($_POST['submit_pembayaran'])) {
    $metode_pembayaran = $_POST['metode_pembayaran'];
    $total_harga = $_POST['total_harga'];
    $estimasi_waktu = $_POST['estimasi_waktu'];
    
    // Simpan ke database
    $sql = "INSERT INTO pembayaran (metode, total_harga, estimasi_waktu) 
            VALUES ('$metode_pembayaran', '$total_harga', '$estimasi_waktu')";
    
    if($koneksi->query($sql) === TRUE) {
        $message = "Pembayaran berhasil diproses!";
    } else {
        $message = "Error: " . $sql . "<br>" . $koneksi->error;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thariz Computer - Pembayaran Service</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .container {
            max-width: 800px;
            margin: 20px auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            display: flex;
            align-items: center;
            background-color: rgb(29, 95, 171);
            padding: 15px 20px;
            color: white;
        }
        
        .logo {
            width: 50px;
            height: 50px;
            margin-right: 15px;
        }
        
        .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: bold;
        }
        
        .menu-container {
            display: flex;
            border-bottom: 1px solid #ddd;
        }
        
        .menu-section {
            flex: 1;
            padding: 15px;
            border-right: 1px solid #ddd;
        }
        
        .menu-section:last-child {
            border-right: none;
        }
        
        .menu-title {
            font-weight: bold;
            color: rgb(29, 95, 171);
            margin-bottom: 10px;
        }
        
        .menu-item {
            padding: 8px 0;
            cursor: pointer;
        }
        
        .menu-item.active {
            color: rgb(29, 95, 171);
            font-weight: bold;
        }
        
        .content {
            padding: 20px;
        }
        
        .sparepart-list {
            margin: 20px 0;
        }
        
        .sparepart-item {
            padding: 8px 0;
            border-bottom: 1px dashed #ddd;
        }
        
        .estimasi {
            margin: 20px 0;
        }
        
        .estimasi-row {
            display: flex;
            margin-bottom: 10px;
        }
        
        .estimasi-label {
            width: 150px;
            font-weight: bold;
        }
        
        .payment-method {
            margin: 20px 0;
        }
        
        .payment-option {
            margin: 10px 0;
        }
        
        .payment-option input {
            margin-right: 10px;
        }
        
        .btn-submit {
            background-color: rgb(29, 95, 171);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            margin-top: 20px;
        }
        
        .buyer-section {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            font-weight: bold;
        }
        
        .message {
            padding: 10px;
            margin: 15px 0;
            border-radius: 5px;
            text-align: center;
        }
        
        .success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">
                <img src="icons/logo.png" alt="Thar'z Computer Logo">
            </div>
            <div class="company-name">THAR'Z COMPUTER</div>
        </div>
        
        <div class="menu-container">
            <div class="menu-section">
                <div class="menu-title">Menu</div>
                <div class="menu-item active">Pengajuan Service</div>
                <div class="menu-item">Pembayaran</div>
            </div>
            <div class="menu-section">
                <div class="menu-title">Pilih Pembayaran</div>
                <div class="menu-item">Keluhan</div>
                <div class="menu-item">Keluhan yang dijelaskan</div>
            </div>
        </div>
        
        <div class="content">
            <?php if(isset($message)): ?>
                <div class="message <?php echo strpos($message, 'berhasil') !== false ? 'success' : 'error'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <h3>Sparepart yang digunakan dan harganya</h3>
            <div class="sparepart-list">
                <div class="sparepart-item">- Item One</div>
                <div class="sparepart-item">- Item Two</div>
                <div class="sparepart-item">- Item Three</div>
            </div>
            
            <div class="estimasi">
                <div class="estimasi-row">
                    <div class="estimasi-label">Estimasi harga:</div>
                    <div>Rp. <input type="text" name="total_harga" placeholder="Masukkan total harga"></div>
                </div>
                <div class="estimasi-row">
                    <div class="estimasi-label">Estimasi waktu:</div>
                    <div><input type="text" name="estimasi_waktu" placeholder="Contoh: 2 Hari"> Hari</div>
                </div>
            </div>
            
            <div class="payment-method">
                <h3>Metode pembayaran</h3>
                <div class="payment-option">
                    <input type="radio" id="dp" name="metode_pembayaran" value="DP" checked>
                    <label for="dp">DP</label>
                </div>
                <div class="payment-option">
                    <input type="radio" id="lunas" name="metode_pembayaran" value="Lunas">
                    <label for="lunas">Lunas</label>
                </div>
            </div>
            
            <button type="submit" name="submit_pembayaran" class="btn-submit">Proses Pembayaran</button>
            
            <div class="buyer-section">
                Buyer
            </div>
        </div>
    </div>
</body>
</html>

<?php
$koneksi->close();
?>