<?php
session_start();
require_once 'config/database.php';

// Pastikan error reporting diaktifkan untuk debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$database = new Database();
$db = $database->getConnection();

$estimasi_id = $_GET['id'] ?? null;

if (!$estimasi_id) {
    header("Location: index.php");
    exit();
}

// Ambil data estimasi
$query = "SELECT e.*, jk.nama as jenis_kayu_nama, jk.harga_per_m3, 
          hd.nama as harga_dimensi_nama, hd.harga_panjang, hd.harga_lebar, 
          hd.harga_tinggi, hd.harga_per_kg
          FROM estimasi e 
          JOIN jenis_kayu jk ON e.jenis_kayu_id = jk.id 
          LEFT JOIN harga_dimensi hd ON e.harga_dimensi_id = hd.id
          WHERE e.id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$estimasi_id]);
$estimasi = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$estimasi) {
    header("Location: index.php");
    exit();
}

// Ambil harga dimensi yang aktif
$query = "SELECT * FROM harga_dimensi WHERE is_active = 1 ORDER BY id DESC LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute();
$harga_dimensi = $stmt->fetch(PDO::FETCH_ASSOC);

// Jika ditemukan harga dimensi, hitung biaya dimensi
$biaya_dimensi = 0;
$biaya_panjang = 0;
$biaya_lebar = 0;
$biaya_tinggi = 0;
$biaya_berat = 0;
$biaya_jenis_kayu = 0;

if(!empty($estimasi['harga_dimensi_id'])) {
    // Hitung biaya berdasarkan dimensi
    $biaya_panjang = $estimasi['panjang'] * $estimasi['harga_panjang'];
    $biaya_lebar = $estimasi['lebar'] * $estimasi['harga_lebar'];
    $biaya_tinggi = $estimasi['tinggi'] * $estimasi['harga_tinggi'];
    $biaya_berat = $estimasi['berat'] * $estimasi['harga_per_kg'];
    $biaya_dimensi = $biaya_panjang + $biaya_lebar + $biaya_tinggi + $biaya_berat;
} else {
    // Hitung biaya berdasarkan volume kayu
    $biaya_jenis_kayu = $estimasi['volume'] * $estimasi['harga_per_m3'];
}

// Total estimasi biaya sesuai dengan yang disimpan
$total_biaya = $estimasi['estimasi_biaya'];

// Ambil foto barang
$query = "SELECT * FROM foto_barang WHERE estimasi_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$estimasi_id]);
$foto_barang = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Membuat file PDF menggunakan HTML
$html = '<html>
<head>
    <title>Estimasi Packing Kayu</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .title {
            font-size: 24px;
            font-weight: bold;
        }
        .subtitle {
            font-size: 16px;
            color: #666;
        }
        .divider {
            border-top: 1px solid #ddd;
            margin: 20px 0;
        }
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .row {
            margin-bottom: 5px;
        }
        .col-left {
            width: 40%;
            font-weight: bold;
        }
        .col-right {
            width: 60%;
        }
        .footer {
            margin-top: 30px;
            font-size: 12px;
            text-align: center;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .text-right {
            text-align: right;
        }
        .total-row {
            font-weight: bold;
            background-color: #f8f9fa;
        }
        .info-text {
            font-size: 12px;
            color: #4682B4;
            margin-top: 10px;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Estimasi Packing Kayu</div>
        <div class="subtitle">Kode Pesanan: ' . $estimasi['kode_pesanan'] . '</div>
    </div>
    
    <div class="section">
        <div class="section-title">Detail Barang</div>
        <table>
            <tr>
                <td class="col-left">Nama Barang:</td>
                <td class="col-right">' . htmlspecialchars($estimasi['nama_barang']) . '</td>
            </tr>
            <tr>
                <td class="col-left">Ukuran:</td>
                <td class="col-right">' . $estimasi['panjang'] . 'cm x ' . $estimasi['lebar'] . 'cm x ' . $estimasi['tinggi'] . 'cm</td>
            </tr>
            <tr>
                <td class="col-left">Volume:</td>
                <td class="col-right">' . number_format($estimasi['volume'], 3) . ' m³</td>
            </tr>
            <tr>
                <td class="col-left">Berat:</td>
                <td class="col-right">' . $estimasi['berat'] . ' kg</td>
            </tr>
            <tr>
                <td class="col-left">Jenis Kayu:</td>
                <td class="col-right">' . $estimasi['jenis_kayu_nama'] . '</td>
            </tr>
            <tr>
                <td class="col-left">Kategori Harga:</td>
                <td class="col-right">' . ($estimasi['harga_dimensi_nama'] ?? 'Harga Standar') . '</td>
            </tr>
        </table>
    </div>
    
    <div class="divider"></div>
    
    <div class="section">
        <div class="section-title">Rincian Biaya</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 30%;">Komponen</th>
                    <th style="width: 45%;">Detail</th>
                    <th style="width: 25%;" class="text-right">Biaya</th>
                </tr>
            </thead>
            <tbody>';

if(!empty($estimasi['harga_dimensi_id'])) {
    $html .= '
                <tr>
                    <td>Biaya Panjang</td>
                    <td>' . $estimasi['panjang'] . ' cm x Rp ' . number_format($estimasi['harga_panjang'], 0, ',', '.') . '</td>
                    <td class="text-right">Rp ' . number_format($biaya_panjang, 0, ',', '.') . '</td>
                </tr>
                <tr>
                    <td>Biaya Lebar</td>
                    <td>' . $estimasi['lebar'] . ' cm x Rp ' . number_format($estimasi['harga_lebar'], 0, ',', '.') . '</td>
                    <td class="text-right">Rp ' . number_format($biaya_lebar, 0, ',', '.') . '</td>
                </tr>
                <tr>
                    <td>Biaya Tinggi</td>
                    <td>' . $estimasi['tinggi'] . ' cm x Rp ' . number_format($estimasi['harga_tinggi'], 0, ',', '.') . '</td>
                    <td class="text-right">Rp ' . number_format($biaya_tinggi, 0, ',', '.') . '</td>
                </tr>
                <tr>
                    <td>Biaya Berat</td>
                    <td>' . $estimasi['berat'] . ' kg x Rp ' . number_format($estimasi['harga_per_kg'], 0, ',', '.') . '</td>
                    <td class="text-right">Rp ' . number_format($biaya_berat, 0, ',', '.') . '</td>
                </tr>';
} else {
    $html .= '
                <tr>
                    <td>Biaya Kayu</td>
                    <td>' . $estimasi['jenis_kayu_nama'] . ' (' . number_format($estimasi['volume'], 3) . ' m³ x Rp ' . number_format($estimasi['harga_per_m3'], 0, ',', '.') . ')</td>
                    <td class="text-right">Rp ' . number_format($biaya_jenis_kayu, 0, ',', '.') . '</td>
                </tr>';
}

$html .= '
                <tr class="total-row">
                    <td colspan="2"><strong>Total Estimasi Biaya</strong></td>
                    <td class="text-right"><strong>Rp ' . number_format($total_biaya, 0, ',', '.') . '</strong></td>
                </tr>
            </tbody>
        </table>';

$html .= '
        <div class="info-text">
            ' . (!empty($estimasi['harga_dimensi_id']) 
                    ? 'Biaya dihitung berdasarkan dimensi (panjang, lebar, tinggi) dan berat dengan kategori harga ' . $estimasi['harga_dimensi_nama'] 
                    : 'Biaya dihitung berdasarkan volume kayu') . '
        </div>
    </div>
    
    <div class="divider"></div>
    
    <div class="section">
        <div class="section-title">Informasi Kontak</div>
        <table>
            <tr>
                <td class="col-left">Nomor WhatsApp:</td>
                <td class="col-right">' . htmlspecialchars($estimasi['nomor_whatsapp']) . '</td>
            </tr>
        </table>
    </div>
    
    <div class="footer">
        <p>Tanggal Estimasi: ' . date('d/m/Y H:i', strtotime($estimasi['created_at'])) . '</p>
        <p>Dokumen ini bukan bukti pembayaran. Silakan hubungi kami untuk informasi lebih lanjut.</p>
        <p>&copy; ' . date('Y') . ' Packing Kayu Indonesia. All rights reserved.</p>
    </div>
</body>
</html>';

try {
    // Menggunakan TCPDF
    require_once 'vendor/autoload.php';
    
    // Pastikan tidak ada output sebelumnya yang mengganggu
    if (ob_get_contents()) ob_end_clean();
    
    // Buat instance TCPDF
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
    
    // Set dokumen informasi
    $pdf->SetCreator('Packing Kayu');
    $pdf->SetAuthor('Admin');
    $pdf->SetTitle('Estimasi Packing Kayu - ' . $estimasi['kode_pesanan']);
    $pdf->SetSubject('Estimasi Packing Kayu');
    
    // Hapus header dan footer default
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set margin
    $pdf->SetMargins(15, 15, 15);
    
    // Set font default
    $pdf->SetFont('helvetica', '', 10);
    
    // Tambahkan halaman
    $pdf->AddPage();
    
    // Tulis HTML ke PDF
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Keluarkan PDF (I = inline di browser)
    header('Content-Type: application/pdf');
    header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0, max-age=1');
    header('Pragma: public');
    $pdf->Output('estimasi_' . $estimasi['kode_pesanan'] . '.pdf', 'I');
    exit;
} catch (Exception $e) {
    // Jika terjadi error, tampilkan pesan error
    echo '<div style="color:red; padding:20px; border:1px solid red; margin:20px;">';
    echo '<h3>Error saat membuat PDF:</h3>';
    echo '<p>' . $e->getMessage() . '</p>';
    echo '<p>Silakan hubungi administrator.</p>';
    echo '</div>';
    
    // Tampilkan HTML sebagai fallback
    echo $html;
}
?> 