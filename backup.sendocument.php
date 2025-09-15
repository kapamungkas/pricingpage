<?php

// Fungsi untuk mencatat log
function write_log($message) {
    $log_file = __DIR__ . '/webhook_log.txt'; // Sesuaikan path file log
    file_put_contents($log_file, date('[Y-m-d H:i:s]') . ' ' . $message . PHP_EOL, FILE_APPEND);
}

// Load WP environment agar fungsi WooCommerce bisa digunakan.
require_once __DIR__ . '/../wp-load.php';
write_log("WP environment berhasil dimuat.");

// Ambil payload dari webhook
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);
write_log("Payload webhook diterima: " . $payload);

// Dapatkan order ID dari payload webhook (pastikan webhook-nya untuk order)
$order_id = isset($data['id']) ? intval($data['id']) : 0;
if (!$order_id) {
    write_log('Error: Order ID tidak ditemukan dalam payload webhook. Proses dihentikan.');
    die('Order ID tidak ditemukan dalam payload webhook.');
}
write_log("Order ID ditemukan: " . $order_id);

// Cari nilai 'send_document' dalam meta_data
$send_document = 'not sent'; // Default value
if (isset($data['meta_data'])) {
    foreach ($data['meta_data'] as $meta) {
        if ($meta['key'] === 'send_document') {
            $send_document = $meta['value'];
            write_log("Meta 'send_document' ditemukan: " . $send_document);
            break;
        }
    }
}
write_log("Nilai 'send_document' setelah pencarian: " . $send_document);


// Jika 'send_document' bukan 'not sent', lewati semua proses PandaDoc dan pembaruan metadata
if ($send_document !== 'not sent') {
    write_log("Kondisi: Meta 'send_document' bukan 'not sent' ('" . $send_document . "'), proses PandaDoc dan pembaruan metadata dibatalkan.");
    echo "Meta 'send_document' bukan 'not sent', proses dibatalkan.\n";
    exit;
}
write_log("Kondisi: Meta 'send_document' adalah 'not sent', melanjutkan proses.");


// Extract data from webhook
$billing = $data['billing'] ?? [];
$line_items = $data['line_items'] ?? [];
$meta_data = $data['meta_data'] ?? [];
write_log("Data billing, line_items, dan meta_data diekstrak dari payload.");


// Get additional billing info from meta_data
$billing_birth_date = '';
$billing_born_address = '';
$billing_finishing = '';
$billing_floor_plan = '';

foreach ($meta_data as $meta) {
    switch ($meta['key']) {
        case '_billing_birth_date':
        case 'billing_birth_date':
            $billing_birth_date = $meta['value'];
            write_log("Meta billing_birth_date ditemukan: " . $billing_birth_date);
            break;
        case '_billing_born_address':
        case 'billing_born_address':
            $billing_born_address = $meta['value'];
            write_log("Meta billing_born_address ditemukan: " . $billing_born_address);
            break;
        case '_billing_finishing':
        case 'billing_finishing':
            $billing_finishing = $meta['value'];
            write_log("Meta billing_finishing ditemukan: " . $billing_finishing);
            break;
        case '_billing_floor_plan':
        case 'billing_floor_plan':
            $billing_floor_plan = $meta['value'];
            write_log("Meta billing_floor_plan ditemukan: " . $billing_floor_plan);
            break;
    }
}

// Get unit information from line items meta_data
$unit_number = '';
$block_name = '';
$bedroom = '';
$bathroom = '';
$price_idr = '';
$price_usd = '';
$booking_fee = '';

if (!empty($line_items)) {
    $line_item = $line_items[0]; // Get first line item
    write_log("Line item pertama ditemukan.");
    if (isset($line_item['meta_data'])) {
        foreach ($line_item['meta_data'] as $meta) {
            switch ($meta['key']) {
                case 'order_unit_number':
                    $unit_number = $meta['value'];
                    write_log("Meta order_unit_number ditemukan: " . $unit_number);
                    break;
                case 'order_block_name':
                    $block_name = ucfirst($meta['value']); // Capitalize first letter
                    write_log("Meta order_block_name ditemukan dan diformat: " . $block_name);
                    break;
                case 'order_bedroom':
                    $bedroom = $meta['value'];
                    write_log("Meta order_bedroom ditemukan: " . $bedroom);
                    break;
                case 'order_bathroom':
                    $bathroom = $meta['value'];
                    write_log("Meta order_bathroom ditemukan: " . $bathroom);
                    break;
                case 'order_price_IDR':
                    $price_idr = $meta['value'];
                    write_log("Meta order_price_IDR ditemukan: " . $price_idr);
                    break;
                case 'order_price_USD':
                    $price_usd = $meta['value'];
                    write_log("Meta order_price_USD ditemukan: " . $price_usd);
                    break;
                case 'order_fee_IDR':
                    $booking_fee = $meta['value'];
                    write_log("Meta order_fee_IDR ditemukan: " . $booking_fee);
                    break;
            }
        }
    }
} else {
    write_log("Line items kosong, tidak ada informasi unit yang diekstrak.");
}

// Format birth date (assuming it's in Y-m-d format, convert to readable format)
$formatted_birth_date = $billing_birth_date;
if ($billing_birth_date && $billing_birth_date !== '2025-05-23') {
    $date = DateTime::createFromFormat('Y-m-d', $billing_birth_date);
    if ($date) {
        $formatted_birth_date = $date->format('d F Y');
        write_log("Tanggal lahir diformat: " . $formatted_birth_date);
    } else {
        write_log("Gagal memformat tanggal lahir: " . $billing_birth_date);
    }
} else {
    write_log("Tanggal lahir kosong atau sama dengan '2025-05-23', tidak diformat.");
}


// API Key dan Cookie PandaDoc (Updated with new API key)
$apiKey = '3f3a9f3e885c708316cd9a8eb05870c8e03d51f1'; // Sebaiknya simpan di environment variable
$cookie = 'incap_ses_1743_2627658=0dbbA0bKG3bYUO1plWEwGCWoL2gAAAAAdhT03r5qECKb/cYSh6Jyqg==; nlbi_2627658=cRqVGPEVfAogZWluSeCpSgAAAAD2it03FVChZPavHk6p/0NR; visid_incap_2627658=tTqQuPUUSSygwLgABnH2lpNKB2gAAAAAQUIPAAAAAAD1j3i3ePGjUT3xGmKKAVaY'; // Sebaiknya simpan di environment variable
write_log("API Key dan Cookie PandaDoc disiapkan.");

// Data untuk membuat dokumen (Updated with webhook data)
$dataCreateDocument = [
    "name" => "Booking Document - Unit " . $unit_number,
    "template_uuid" => "LRAUwAP3XaG6MU8Ndy4uuW",
    "recipients" => [
        [
            "email" => $billing['email'] ?? 'kap21kap@gmail.com',
            "first_name" => $billing['first_name'] ?? 'Client',
            "last_name" => $billing['last_name'] ?? 'Name',
            "role" => "Client"
        ]
    ],
    "tokens" => [
        ["name" => "Client.FirstName", "value" => $billing['first_name'] ?? ''],
        ["name" => "Client.LastName", "value" => $billing['last_name'] ?? ''],
        ["name" => "Client.BornAddress", "value" => $billing_born_address],
        ["name" => "Client.BornDate", "value" => $formatted_birth_date],
        ["name" => "Client.Nationality", "value" => "Indonesia"],
        ["name" => "Client.PassportNumber", "value" => ""],
        ["name" => "Unit.BlockName", "value" => $block_name],
        ["name" => "Unit.Bedroom", "value" => $bedroom],
        ["name" => "Unit.BathRoom", "value" => $bathroom],
        ["name" => "Unit.InternalSQM", "value" => ""],
        ["name" => "Unit.ExternalSQM", "value" => ""],
        ["name" => "Unit.Number", "value" => $unit_number],
        ["name" => "Unit.Price.IDR", "value" => $price_idr],
        ["name" => "Unit.Price.USD", "value" => $price_usd],
        ["name" => "Unit.Option", "value" => $billing_floor_plan . " - " . $billing_finishing],
        ["name" => "Booking.Fee.IDR", "value" => $booking_fee]
    ],
    "metadata" => [
        "order_id" => $order_id,
        "unit_number" => $unit_number,
        "block_name" => $block_name
    ],
    "tags" => ["created_via_webhook", "booking_document", "order_" . $order_id]
];
write_log("Data untuk pembuatan dokumen PandaDoc disiapkan.");


// Kirim request buat dokumen
$curlCreateDocument = curl_init();
curl_setopt_array($curlCreateDocument, array(
    CURLOPT_URL => 'https://api.pandadoc.com/public/v1/documents',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
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
write_log("Request pembuatan dokumen PandaDoc dikirim. HTTP Code: " . $httpCodeCreateDocument);

if ($httpCodeCreateDocument != 201) {
    write_log('Error: Gagal membuat dokumen PandaDoc. Response: ' . $responseCreateDocument);
    die('Error membuat dokumen: ' . $responseCreateDocument);
}

$responseData = json_decode($responseCreateDocument, true);
$documentId = $responseData['id'];
write_log("Dokumen PandaDoc berhasil dibuat dengan ID: " . $documentId);
echo "Dokumen berhasil dibuat dengan ID: $documentId\n";

// Mengecek status dokumen sampai draft
function checkDocumentStatus($documentId, $apiKey, $cookie, $log_function) {
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
        $log_function('Error: Gagal saat cek status dokumen. Response: ' . $response);
        die('Error saat cek status dokumen: ' . $response);
    }
    return json_decode($response, true)['status'];
}

$attempt = 0;
$status = '';
write_log("Memulai pengecekan status dokumen hingga 'document.draft'.");
while ($status !== 'document.draft' && $attempt < 30) {
    $status = checkDocumentStatus($documentId, $apiKey, $cookie, 'write_log');
    write_log("Cek status dokumen ke-" . ($attempt + 1) . ": Status saat ini: " . $status);
    echo "Cek ke-" . ($attempt + 1) . ": Status $status\n";
    if ($status !== 'document.draft') sleep(10);
    $attempt++;
}

if ($status !== 'document.draft') {
    write_log('Error: Dokumen tidak mencapai status "document.draft" dalam 5 menit. Status terakhir: ' . $status);
    die('Dokumen tidak mencapai draft dalam waktu 5 menit.');
}
write_log("Dokumen mencapai status 'document.draft'.");
echo "Mengirim dokumen...\n";

// Kirim dokumen
$dataSendDocument = [
    "message" => "Hello " . $billing['first_name'] . "! This is your booking document for Unit " . $unit_number . ". Please review and sign the document.",
    "silent" => true
];
write_log("Data untuk pengiriman dokumen disiapkan.");

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
write_log("Request pengiriman dokumen PandaDoc dikirim. HTTP Code: " . $httpCodeSend);

if ($httpCodeSend != 200) {
    write_log('Error: Gagal mengirim dokumen PandaDoc. Response: ' . $responseSend);
    die('Gagal mengirim dokumen: ' . $responseSend);
}

write_log("Dokumen PandaDoc berhasil dikirim.");
echo "Dokumen berhasil dikirim!\n";

// ==============================
// UPDATE META DI ITEM ORDER
// ==============================

if (function_exists('wc_get_order')) {
    write_log("Fungsi 'wc_get_order' tersedia.");
    $order = wc_get_order($order_id);
    if ($order) {
        write_log("Order dengan ID " . $order_id . " ditemukan.");
        $order->update_meta_data('send_document', 'sent');
        $order->save();
        write_log("Meta 'send_document' pada order " . $order_id . " berhasil diperbarui menjadi 'sent'.");
        echo "Meta 'send_document' diperbarui menjadi 'sent' di order $order_id.\n";
    } else {
        write_log("Error: Order ID " . $order_id . " tidak ditemukan oleh 'wc_get_order'.");
        echo "Order ID $order_id tidak ditemukan.\n";
    }
} else {
    write_log("Error: Fungsi WooCommerce 'wc_get_order' tidak tersedia.");
    echo "Fungsi WooCommerce tidak tersedia (wc_get_order).\n";
}

?>