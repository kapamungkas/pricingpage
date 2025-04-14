<?php
// Konfigurasi
$spreadsheetId = "14xAl_e8-vJxc5SNCIAeMKud7doadGSBLUtjMnag9U8w"; // Ganti dengan Spreadsheet ID kamu
$range = "pricing"; // Ganti dengan nama sheet
$apiKey = "AIzaSyBSm-B7-6THyiGpsr8TAhgJayaz9pMZ7KE"; // Ganti dengan API Key kamu

// URL API Google Sheets
$url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/{$range}?key={$apiKey}";

// Ambil data dari API Google Sheets
$response = file_get_contents($url);
$data = json_decode($response, true);

// Ubah format menjadi JSON API
$result = [];
if (isset($data['values'])) {
    $headers = array_shift($data['values']); // Ambil header (baris pertama)
    foreach ($data['values'] as $row) {
        $result[] = array_combine($headers, $row); // Gabungkan header dengan data
    }
}

// Tampilkan hasil dalam format JSON
header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT);
?>
