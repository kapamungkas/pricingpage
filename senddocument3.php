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

// Extract data from webhook
$billing = $data['billing'] ?? [];
$line_items = $data['line_items'] ?? [];
$meta_data = $data['meta_data'] ?? [];

// Get additional billing info from meta_data
$billing_birth_date = '';
$billing_born_address = '';
$billing_finishing = '';
$billing_floor_plan = '';
$billing_nationality = '';
$billing_passport_number = '';
// $billing_nationality sudah didefinisikan sebelumnya, tidak perlu didefinisikan ulang

foreach ($meta_data as $meta) {
    switch ($meta['key']) {
        case '_billing_birth_date':
        case 'billing_birth_date':
            $billing_birth_date = $meta['value'];
            break;
        case '_billing_born_address':
        case 'billing_born_address':
            $billing_born_address = $meta['value'];
            break;
        case '_billing_finishing':
        case 'billing_finishing':
            $billing_finishing = $meta['value'];
            break;
        case '_billing_floor_plan':
        case 'billing_floor_plan':
            $billing_floor_plan = $meta['value'];
            break;
        case '_billing_nationality':
        case 'billing_nationality':
            $billing_nationality = $meta['value'];
            break;
        case '_billing_passport_number':
        case 'billing_passport_number':
            $billing_passport_number = $meta['value'];
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
// $nationality sudah didefinisikan sebelumnya, tidak perlu didefinisikan ulang
$internalSQM = '';
$externalSQM = '';

if (!empty($line_items)) {
    $line_item = $line_items[0]; // Get first line item
    if (isset($line_item['meta_data'])) {
        foreach ($line_item['meta_data'] as $meta) {
            switch ($meta['key']) {
                case 'order_unit_number':
                    $unit_number = $meta['value'];
                    break;
                case 'order_block_name':
                    $block_name = ucfirst($meta['value']); // Capitalize first letter
                    break;
                case 'order_bedroom':
                    $bedroom = $meta['value'];
                    break;
                case 'order_bathroom':
                    $bathroom = $meta['value'];
                    break;
                case 'order_price_IDR':
                    $price_idr = $meta['value'];
                    break;
                case 'order_price_USD':
                    $price_usd = $meta['value'];
                    break;
                case 'order_fee_IDR':
                    $booking_fee = $meta['value'];
                    break;
                case 'order_internalSQM':
                    $internalSQM = $meta['value'];
                    break;
                case 'order_externalSQM':
                    $externalSQM = $meta['value'];
                    break;
            }
        }
    }
}

// Format birth date (assuming it's in Y-m-d format, convert to readable format)
$formatted_birth_date = $billing_birth_date;
if ($billing_birth_date && $billing_birth_date !== '2025-05-23') { // Gunakan tanggal yang realistis atau hapus pengecekan tanggal hardcoded
    $date = DateTime::createFromFormat('Y-m-d', $billing_birth_date);
    if ($date) {
        $formatted_birth_date = $date->format('d F Y');
    }
}

// API Key dan Cookie PandaDoc (Updated with new API key)
$apiKey = 'ce535a2b9f6774d43a451de8c92174f32d300e43';
$cookie = 'incap_ses_1743_2627658=0dbbA0bKG3bYUO1plWEwGCWoL2gAAAAAdhT03r5qECKb/cYSh6Jyqg==; nlbi_2627658=cRqVGPEVfAogZWluSeCpSgAAAAD2it03FVChZPavHk6p/0NR; visid_incap_2627658=tTqQuPUUSSygwLgABnH2lpNKB2gAAAAAQUIPAAAAAAD1j3i3ePGjUT3xGmKKAVaY';

// Data untuk membuat dokumen (Updated with webhook data)
$dataCreateDocument = [
    "name" => "Booking Document - Unit " . $unit_number,
    "template_uuid" => "LRAUwAP3XaG6MU8Ndy4uuW", // Updated template UUID
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
        ["name" => "Client.Nationality", "value" => $billing_nationality],
        ["name" => "Client.PassportNumber", "value" => $billing_passport_number],
        ["name" => "Unit.BlockName", "value" => $block_name],
        ["name" => "Unit.Bedroom", "value" => $bedroom],
        ["name" => "Unit.BathRoom", "value" => $bathroom],
        ["name" => "Unit.InternalSQM", "value" => $internalSQM],
        ["name" => "Unit.ExternalSQM", "value" => $externalSQM],
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
    "message" => "Hello " . $billing['first_name'] . "! This is your booking document for Unit " . $unit_number . ". Please review and sign the document.",
    "silent" => false
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

// --- Ambil Shared Link dari Response Kirim Dokumen ---
$responseSendDecoded = json_decode($responseSend, true);
$shared_link = '';

if (isset($responseSendDecoded['recipients']) && !empty($responseSendDecoded['recipients'])) {
    if (isset($responseSendDecoded['recipients'][0]['shared_link'])) {
        $shared_link = $responseSendDecoded['recipients'][0]['shared_link'];
        echo "Shared Link Dokumen: $shared_link\n";
    }
} else {
    echo "Tidak dapat menemukan 'recipients' atau 'shared_link' dalam respons pengiriman dokumen.\n";
}


// ==============================
// UPDATE META DI ITEM ORDER & KIRIM EMAIL
// ==============================

if (function_exists('wc_get_order')) {
    $order = wc_get_order($order_id);
    if ($order) {
        $order->update_meta_data('send_document', 'sent');
        // --- Tambahkan Shared Link ke Meta Data Order ---
        if (!empty($shared_link)) {
            $order->update_meta_data('_pandadoc_shared_link', $shared_link);
            echo "Meta '_pandadoc_shared_link' diperbarui dengan: $shared_link\n";
        }
        $order->save();
        echo "Meta 'send_document' diperbarui menjadi 'sent' di order $order_id.\n";

        // --- Bagian Pengiriman Email ---
        // Hanya kirim email jika status order adalah pending
        if ( $order->get_status() === 'pending' ) {
            $to = $order->get_billing_email();
            $payment_link = $order->get_checkout_payment_url();
            $pandadoc_shared_link = $order->get_meta('_pandadoc_shared_link'); // Ambil lagi untuk memastikan nilai terbaru

            $subject = 'Mohon Selesaikan Pemesanan Anda – Langkah Selanjutnya Diperlukan';

            $message  = "Pelanggan Yth,<br><br>";
            $message .= "Terima kasih atas pemesanan Anda baru-baru ini. Proses pemesanan Anda belum selesai.<br><br>";
            $message .= "Untuk menyelesaikan reservasi unit Anda, mohon ikuti langkah-langkah di bawah ini:<br><br>";

            // Langkah 1: Tanda Tangan Dokumen
            $message .= "<strong>1. Mohon tanda tangani dokumen pemesanan terlebih dahulu:</strong><br>";
            if ( ! empty( $pandadoc_shared_link ) ) {
                $message .= "Untuk menandatangani dokumen Anda, silakan klik tautan di bawah ini:<br>";
                $message .= "<a href='" . esc_url( $pandadoc_shared_link ) . "' style='display:inline-block; padding:10px 20px; background-color:#007bff; color:#ffffff; text-decoration:none; border-radius:3px;' target='_blank'>Tanda Tangani Dokumen Anda</a><br><br>";
            } else {
                $message .= "Kami telah mengirimkan undangan untuk menandatangani dokumen pemesanan. Mohon periksa kotak masuk email Anda (dan folder spam).<br><br>";
            }

            // Langkah 2: Lakukan Pembayaran
            $message .= "<strong>2. Baru lakukan pembayaran:</strong><br>";
            $message .= "Mohon lanjutkan dengan pembayaran menggunakan tautan di bawah ini:<br>";
            $message .= "<a href='" . esc_url( $payment_link ) . "' style='display:inline-block; padding:10px 20px; background-color:#28a745; color:#ffffff; text-decoration:none; border-radius:3px;'>Bayar Sekarang</a><br><br>";

            $message .= "Jika Anda memiliki pertanyaan atau menghadapi masalah, jangan ragu untuk menghubungi kami melalui tautan berikut:<br>";
            $message .= "<a href='https://elementbali.com/contact-us/'>https://elementbali.com/contact-us/</a><br><br>";
            $message .= "Hormat kami,<br>";
            $message .= "Tim Element Residence";

            $headers = array('Content-Type: text/html; charset=UTF-8');

            wc_mail( $to, $subject, $message, $headers );
            echo "Email notifikasi pending berhasil dikirim.\n";
        } else {
            echo "Email notifikasi tidak dikirim karena status order bukan 'pending'.\n";
        }
        // --- Akhir Bagian Pengiriman Email ---

    } else {
        echo "Order ID $order_id tidak ditemukan.\n";
    }
} else {
    echo "Fungsi WooCommerce tidak tersedia (wc_get_order).\n";
}

?>