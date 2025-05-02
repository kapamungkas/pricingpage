<?php
// Konfigurasi
$spreadsheetId = "14xAl_e8-vJxc5SNCIAeMKud7doadGSBLUtjMnag9U8w"; // Ganti dengan Spreadsheet ID kamu
$range = "pricing"; // Ganti dengan nama sheet
$apiKey = "AIzaSyBSm-B7-6THyiGpsr8TAhgJayaz9pMZ7KE"; // Ganti dengan API Key kamu

// URL API Google Sheets untuk membaca data
$url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/{$range}?key={$apiKey}";

// Ambil data dari API Google Sheets
$response = file_get_contents($url);
$data = json_decode($response, true);

// Periksa jika ada permintaan untuk memperbarui status
if (isset($_GET['action']) && $_GET['action'] === 'update_booked' && isset($_GET['unit_number'])) {
    // Ambil unit_number dari parameter GET
    $unitNumberToUpdate = $_GET['unit_number'];
    
    // Cari unit yang sesuai dan persiapkan data untuk responsnya
    if (isset($data['values'])) {
        $headers = $data['values'][0]; // Ambil header (baris pertama)
        
        // Cari indeks kolom yang dibutuhkan
        $unitNumberIndex = array_search('unit_number', $headers);
        $statusIndex = array_search('status', $headers);
        
        // Jika 'status' tidak ditemukan, coba cari di indeks terakhir berdasarkan contoh
        if ($statusIndex === false) {
            $statusIndex = count($headers) - 1;
        }
        
        if ($unitNumberIndex === false) {
            // Kolom unit_number tidak ditemukan
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => "Kolom 'unit_number' tidak ditemukan di spreadsheet"
            ]);
            exit;
        }
        
        $rowToUpdate = null;
        $rowIndex = null;
        
        // Debug informasi untuk memeriksa struktur data
        $debugInfo = [
            'headers' => $headers,
            'unit_number_index' => $unitNumberIndex,
            'status_index' => $statusIndex,
            'found_unit' => false
        ];
        
        // Cari baris dengan unit_number yang sesuai
        for ($i = 1; $i < count($data['values']); $i++) {
            $row = $data['values'][$i];
            
            // Pastikan indeks ada dalam array
            if (isset($row[$unitNumberIndex]) && $row[$unitNumberIndex] === $unitNumberToUpdate) {
                $rowToUpdate = $row;
                $rowIndex = $i + 1; // Google Sheets API indeks baris dimulai dari 1
                $debugInfo['found_unit'] = true;
                $debugInfo['row_data'] = $row;
                $debugInfo['row_index'] = $rowIndex;
                break;
            }
        }
        
        // Jika unit ditemukan, beri informasi untuk update manual
        if ($rowToUpdate !== null) {
            // Hitung kolom status sebagai huruf (A, B, C, ...)
            $statusColumn = '';
            $colIndex = $statusIndex;
            
            // Konversi indeks kolom ke format huruf (A, B, C, ...)
            do {
                $statusColumn = chr(65 + ($colIndex % 26)) . $statusColumn;
                $colIndex = floor($colIndex / 26) - 1;
            } while ($colIndex >= 0);
            
            // Siapkan data untuk tampilkan hasil operasi
            $resultData = [
                'success' => true,
                'message' => "Informasi untuk update unit {$unitNumberToUpdate} menjadi booked",
                'unit_info' => [
                    'unit_number' => $unitNumberToUpdate,
                    'row_in_sheet' => $rowIndex,
                    'status_column' => $statusColumn,
                    'cell_reference' => $statusColumn . $rowIndex,
                    'current_status' => isset($rowToUpdate[$statusIndex]) ? $rowToUpdate[$statusIndex] : 'tidak diketahui'
                ],
                'debug_info' => $debugInfo,
                'manual_update_instructions' => "Untuk memperbarui status unit ini secara manual, buka Google Sheet Anda dan ubah nilai di sel {$statusColumn}{$rowIndex} menjadi 'booked'"
            ];
            
            // Tampilkan hasil dengan petunjuk manual update
            header('Content-Type: application/json');
            echo json_encode($resultData, JSON_PRETTY_PRINT);
            exit;
        } else {
            // Unit tidak ditemukan
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => "Unit dengan nomor {$unitNumberToUpdate} tidak ditemukan",
                'debug_info' => $debugInfo
            ]);
            exit;
        }
    }
}

// Jika tidak ada action update, tampilkan semua data seperti biasa
// Ubah format menjadi JSON API
$result = [];
if (isset($data['values'])) {
    $headers = array_shift($data['values']); // Ambil header (baris pertama)
    foreach ($data['values'] as $row) {
        $item = [];
        for ($i = 0; $i < count($headers); $i++) {
            $item[$headers[$i]] = $row[$i] ?? '';
        }
        $result[] = $item;
    }
}

// Tampilkan hasil dalam format JSON
header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT);
?>