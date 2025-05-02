<?php

// Load WP environment agar fungsi WooCommerce bisa digunakan.
require_once __DIR__ . '/../wp-load.php';
// Ambil payload dari webhook
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

// Dapatkan order ID dari payload webhook (pastikan webhook-nya untuk order)
$order_id = isset($data['id']) ? intval($data['id']) : 0;
if (!$order_id) {
    die('Order ID tidak ditemukan dalam payload webhook.');
}

// Cari nilai 'send_document' dalam meta_data
$send_document = 'not sent'; // Default value
if (isset($data['meta_data'])) {
    foreach ($data['meta_data'] as $meta) {
        if ($meta['key'] === 'send_document') {
            $send_document = $meta['value'];
            break;
        }
    }
}

// Jika 'send_document' bukan 'not sent', lewati semua proses PandaDoc dan pembaruan metadata
if ($send_document !== 'not sent') {
    echo "Meta 'send_document' bukan 'not sent', proses dibatalkan.\n";
    exit;
}

// API Key dan Cookie PandaDoc
$apiKey = 'cdcba2c2f787260234bdfb5a5f43ff5b90b897df';
$cookie = 'PandaDoc=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...'; // lanjutkan token kamu

// Data untuk membuat dokumen
$dataCreateDocument = [
    "name" => "Simple API Sample Document from PandaDoc Template",
    "template_uuid" => "Q6DHGjhLjtG3f6ZxrmdtqY",
    "recipients" => [
        [
            "email" => "kap21kap@gmail.com",
            "first_name" => "Josh",
            "last_name" => "Ron",
            "role" => "Client"
        ]
    ],
    "tokens" => [
        [
            "name" => "Favorite.Pet",
            "value" => "Panda"
        ]
    ],
    "fields" => [
        "Favorite.Color" => ["value" => "PandaDoc green"],
        "Delivery" => ["value" => "Same Day Delivery"],
        "Like" => ["value" => true],
        "Date" => ["value" => "2019-12-31T00:00:00.000Z"]
    ],
    "metadata" => [
        "my_favorite_pet" => "Panda"
    ],
    "tags" => ["created_via_api", "test_document"]
];

// Kirim request buat dokumen
$curlCreateDocument = curl_init();
curl_setopt_array($curlCreateDocument, array(
    CURLOPT_URL => 'https://api.pandadoc.com/public/v1/documents',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode($dataCreateDocument),
    CURLOPT_HTTPHEADER => [
        'Authorization: API-Key ' . $apiKey,
        'Content-Type: application/json',
        'Cookie: ' . $cookie
    ],
));
$responseCreateDocument = curl_exec($curlCreateDocument);
$httpCodeCreateDocument = curl_getinfo($curlCreateDocument, CURLINFO_HTTP_CODE);
curl_close($curlCreateDocument);

if ($httpCodeCreateDocument != 201) {
    die('Error membuat dokumen: ' . $responseCreateDocument);
}

$responseData = json_decode($responseCreateDocument, true);
$documentId = $responseData['id'];

echo "Dokumen berhasil dibuat dengan ID: $documentId\n";

// Mengecek status dokumen sampai draft
function checkDocumentStatus($documentId, $apiKey, $cookie) {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api.pandadoc.com/public/v1/documents/' . $documentId,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => [
            'Authorization: API-Key ' . $apiKey,
            'Cookie: ' . $cookie
        ],
    ]);
    $response = curl_exec($curl);
    $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($statusCode != 200) {
        die('Error saat cek status dokumen: ' . $response);
    }
    return json_decode($response, true)['status'];
}

$attempt = 0;
$status = '';
while ($status !== 'document.draft' && $attempt < 30) {
    $status = checkDocumentStatus($documentId, $apiKey, $cookie);
    echo "Cek ke-" . ($attempt + 1) . ": Status $status\n";
    if ($status !== 'document.draft') sleep(10);
    $attempt++;
}

if ($status !== 'document.draft') {
    die('Dokumen tidak mencapai draft dalam waktu 5 menit.');
}

echo "Mengirim dokumen...\n";

// Kirim dokumen
$dataSendDocument = [
    "message" => "Hello! This document was sent from the PandaDoc API.",
    "silent" => true
];

$curlSend = curl_init();
curl_setopt_array($curlSend, [
    CURLOPT_URL => "https://api.pandadoc.com/public/v1/documents/$documentId/send",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode($dataSendDocument),
    CURLOPT_HTTPHEADER => [
        'Authorization: API-Key ' . $apiKey,
        'Content-Type: application/json',
        'Cookie: ' . $cookie
    ],
]);
$responseSend = curl_exec($curlSend);
$httpCodeSend = curl_getinfo($curlSend, CURLINFO_HTTP_CODE);
curl_close($curlSend);

if ($httpCodeSend != 200) {
    die('Gagal mengirim dokumen: ' . $responseSend);
}

echo "Dokumen berhasil dikirim!\n";

// ==============================
// UPDATE META DI ITEM ORDER
// ==============================

if (function_exists('wc_get_order')) {
    $order = wc_get_order($order_id);
    if ($order) {
        $order->update_meta_data('send_document', 'sent');
        $order->save();
        echo "Meta 'send_document' diperbarui menjadi 'Sent' di semua item order $order_id.\n";
    } else {
        echo "Order ID $order_id tidak ditemukan.\n";
    }
} else {
    echo "Fungsi WooCommerce tidak tersedia (wc_get_order).\n";
}
