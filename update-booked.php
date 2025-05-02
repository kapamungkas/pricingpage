<?php

/**
 * WooCommerce Webhook Receiver
 * 
 * File ini menerima webhook dari WooCommerce saat pembayaran berhasil,
 * mengambil meta data order_unit_number, dan melakukan request ke Google Apps Script.
 */

// Aktifkan log untuk debugging
$log_file = 'webhook-log.txt';
function writeLog($message)
{
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

// Inisialisasi log
writeLog("Webhook diterima");

// Tangkap data webhook dari WooCommerce
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

// Verifikasi bahwa data berhasil di-parse
if (json_last_error() !== JSON_ERROR_NONE) {
    writeLog("Error: Gagal mem-parse JSON data");
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data']);
    exit;
}

// Verifikasi signature (opsional, tetapi sangat direkomendasikan untuk keamanan)
// $signature = isset($_SERVER['HTTP_X_WC_WEBHOOK_SIGNATURE']) ? $_SERVER['HTTP_X_WC_WEBHOOK_SIGNATURE'] : '';
// $secret = 'YOUR_WEBHOOK_SECRET'; // Ganti dengan webhook secret Anda

// if (!empty($signature)) {
//     $calculated_signature = base64_encode(hash_hmac('sha256', $payload, $secret, true));
//     if (!hash_equals($signature, $calculated_signature)) {
//         writeLog("Error: Signature tidak valid");
//         http_response_code(401);
//         echo json_encode(['status' => 'error', 'message' => 'Invalid signature']);
//         exit;
//     }
// }

// Log data yang diterima untuk debugging
writeLog("Data diterima: " . json_encode($data));

// Pastikan ini adalah event pembayaran berhasil
// Sesuaikan kondisi ini sesuai dengan webhook event yang Anda konfigurasi di WooCommerce
// Pastikan ini adalah event pembayaran berhasil
if (isset($data['status']) && $data['status'] === 'processing') {
    writeLog("Status pembayaran: processing");

    $unit_number = null;

    // Cari di meta_data level atas
    if (isset($data['meta_data']) && is_array($data['meta_data'])) {
        foreach ($data['meta_data'] as $meta) {
            if (isset($meta['key']) && $meta['key'] === 'order_unit_number') {
                $unit_number = $meta['value'];
                writeLog("Unit number ditemukan di meta_data: $unit_number");
                break;
            }
        }
    }

    // Jika tidak ditemukan, cari di order.meta_data
    if ($unit_number === null && isset($data['order']['meta_data']) && is_array($data['order']['meta_data'])) {
        foreach ($data['order']['meta_data'] as $meta) {
            if (isset($meta['key']) && $meta['key'] === 'order_unit_number') {
                $unit_number = $meta['value'];
                writeLog("Unit number ditemukan di order.meta_data: $unit_number");
                break;
            }
        }
    }

    // Jika masih belum ditemukan, cari di line_items meta_data
    if ($unit_number === null && isset($data['line_items']) && is_array($data['line_items'])) {
        foreach ($data['line_items'] as $item) {
            if (isset($item['meta_data']) && is_array($item['meta_data'])) {
                foreach ($item['meta_data'] as $meta) {
                    if (isset($meta['key']) && $meta['key'] === 'order_unit_number') {
                        $unit_number = $meta['value'];
                        writeLog("Unit number ditemukan di line_items.meta_data: $unit_number");
                        break 2; // Keluar dari kedua loop setelah ditemukan
                    }
                }
            }
        }
    }

    // Jika unit number ditemukan, lakukan request ke Google Apps Script
    if ($unit_number !== null) {
        $google_script_url = "https://script.google.com/macros/s/AKfycbwAwfN8clUTDOpdTTVyV72buTENyuh-nBpVqYcYMLTbnO3vY1W742zqT-J9AbZu9-Im/exec";
        $request_url = $google_script_url . "?action=update_booked&unit_number=" . urlencode($unit_number);

        writeLog("Mengirim request ke: $request_url");

        // Lakukan HTTP request
        $curl = curl_init($request_url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if ($error) {
            writeLog("Error saat mengirim request: $error");
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to send request to Google Apps Script']);
        } else {
            writeLog("Response dari Google Apps Script ($http_code): $response");
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'message' => 'Successfully processed webhook and updated unit status',
                'unit_number' => $unit_number,
                'google_script_response' => $response
            ]);
        }
    } else {
        writeLog("Error: order_unit_number tidak ditemukan di mana pun yang dicari");
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Unit number not found in order meta data']);
    }
} else {
    writeLog("Bukan event pembayaran berhasil atau format tidak sesuai");
    http_response_code(200); // Tetap kembalikan 200 untuk webhook yang tidak perlu diproses
    echo json_encode(['status' => 'ignored', 'message' => 'Not a payment completion event']);
}
