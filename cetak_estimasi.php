<?php
session_start();
require_once 'config/database.php';

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
            display: flex;
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
        <div class="row">
            <div class="col-left">Nama Barang:</div>
            <div class="col-right">' . htmlspecialchars($estimasi['nama_barang']) . '</div>
        </div>
        <div class="row">
            <div class="col-left">Ukuran:</div>
            <div class="col-right">' . $estimasi['panjang'] . 'cm x ' . $estimasi['lebar'] . 'cm x ' . $estimasi['tinggi'] . 'cm</div>
        </div>
        <div class="row">
            <div class="col-left">Volume:</div>
            <div class="col-right">' . number_format($estimasi['volume'], 3) . ' m³</div>
        </div>
        <div class="row">
            <div class="col-left">Berat:</div>
            <div class="col-right">' . $estimasi['berat'] . ' kg</div>
        </div>
        <div class="row">
            <div class="col-left">Jenis Kayu:</div>
            <div class="col-right">' . $estimasi['jenis_kayu_nama'] . '</div>
        </div>
        <div class="row">
            <div class="col-left">Kategori Harga:</div>
            <div class="col-right">' . ($estimasi['harga_dimensi_nama'] ?? 'Harga Standar') . '</div>
        </div>
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
        <div class="section-title">Informasi Pengiriman</div>
        <div class="row">
            <div class="col-left">Lokasi Tujuan:</div>
            <div class="col-right">' . htmlspecialchars($estimasi['lokasi_tujuan']) . '</div>
        </div>
        <div class="row">
            <div class="col-left">Kontak:</div>
            <div class="col-right">' . htmlspecialchars($estimasi['nomor_whatsapp']) . '</div>
        </div>
    </div>
    
    <div class="footer">
        <p>Tanggal Estimasi: ' . date('d/m/Y H:i', strtotime($estimasi['created_at'])) . '</p>
        <p>Dokumen ini bukan bukti pembayaran. Silakan hubungi kami untuk informasi lebih lanjut.</p>
        <p>&copy; ' . date('Y') . ' Packing Kayu Indonesia. All rights reserved.</p>
    </div>
</body>
</html>';

// Menggunakan tcpdf sebagai alternatif karena tidak perlu instalasi tambahan
require_once 'vendor/autoload.php';

// Cek apakah autoload berhasil dimuat
if (class_exists('TCPDF')) {
    // Gunakan TCPDF
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
    $pdf->SetCreator('Estimasi Packing Kayu');
    $pdf->SetAuthor('Admin');
    $pdf->SetTitle('Estimasi Packing Kayu - ' . $estimasi['kode_pesanan']);
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetHeaderMargin(0);
    $pdf->SetFooterMargin(0);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage();
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('estimasi_' . $estimasi['kode_pesanan'] . '.pdf', 'I');
} elseif (class_exists('Dompdf\Dompdf')) {
    // Gunakan Dompdf jika tersedia
    $dompdf = new \Dompdf\Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream('estimasi_' . $estimasi['kode_pesanan'] . '.pdf', array('Attachment' => 0));
} else {
    // Jika tidak ada library PDF yang tersedia, tampilkan HTML saja
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
}
?> 