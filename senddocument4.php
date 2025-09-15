<?php

// Define the log file path
define('LOG_FILE', __DIR__ . '/webhook_log.log');

// Function to write messages to the log file
function log_message($message) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents(LOG_FILE, "[$timestamp] $message\n", FILE_APPEND);
}

log_message("--- Webhook execution started ---");

// Load WP environment to use WooCommerce functions.
require_once __DIR__ . '/../wp-load.php';
log_message("WordPress environment loaded.");

// Get the payload from the webhook
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);
log_message("Webhook payload received and decoded.");

// Get the order ID from the webhook payload (ensure the webhook is for an order)
$order_id = isset($data['id']) ? intval($data['id']) : 0;
if (!$order_id) {
    log_message("ERROR: Order ID not found in webhook payload. Exiting.");
    die('Order ID not found in webhook payload.');
}
log_message("Order ID: " . $order_id);

// Check the 'send_document' meta_data value
$send_document = 'not sent'; // Default value
if (isset($data['meta_data'])) {
    foreach ($data['meta_data'] as $meta) {
        if ($meta['key'] === 'send_document') {
            $send_document = $meta['value'];
            break;
        }
    }
}
log_message("Initial 'send_document' meta from payload: " . $send_document);

// If 'send_document' is not 'not sent', skip all PandaDoc processing and metadata updates
if ($send_document !== 'not sent') {
    log_message("INFO: Meta 'send_document' is not 'not sent' ('" . $send_document . "'), process aborted to prevent duplicate sending.");
    echo "Meta 'send_document' is not 'not sent', process aborted.\n";
    exit;
}
log_message("Meta 'send_document' is 'not sent', proceeding with PandaDoc process.");

// Extract data from webhook
$billing = $data['billing'] ?? [];
$line_items = $data['line_items'] ?? [];
$meta_data = $data['meta_data'] ?? [];
log_message("Extracted billing, line items, and meta data from payload.");

// Get additional billing info from meta_data
$billing_birth_date = '';
$billing_born_address = '';
$billing_finishing = '';
$billing_floor_plan = '';
$billing_nationality = '';
$billing_passport_number = '';

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
log_message("Extracted additional billing info from meta_data.");

// Get unit information from line items meta_data
$unit_number = '';
$block_name = '';
$bedroom = '';
$bathroom = '';
$price_idr = '';
$price_usd = '';
$booking_fee = '';
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
    log_message("Extracted unit information from line items.");
} else {
    log_message("WARNING: No line items found in webhook payload.");
}

// Format birth date (assuming it's in Y-m-d format, convert to readable format)
$formatted_birth_date = $billing_birth_date;
if ($billing_birth_date && $billing_birth_date !== '2025-05-23') { // Replace '2025-05-23' with proper validation for empty/invalid date if needed.
    $date = DateTime::createFromFormat('Y-m-d', $billing_birth_date);
    if ($date) {
        $formatted_birth_date = $date->format('d F Y');
        log_message("Formatted birth date: " . $formatted_birth_date);
    } else {
        log_message("WARNING: Failed to format birth date '" . $billing_birth_date . "'. Using original value.");
    }
} else {
    log_message("INFO: Birth date is empty or matches placeholder, using original value.");
}


// PandaDoc API Key and Cookie
$apiKey = 'ce535a2b9f6774d43a451de8c92174f32d300e43'; // It's generally better to store API keys securely, e.g., in environment variables.
$cookie = 'incap_ses_1743_2627658=0dbbA0bKG3bYUO1plWEwGCWoL2gAAAAAdhT03r5qECKb/cYSh6Jyqg==; nlbi_2627658=cRqVGPEVfAogZWluSeCpSgAAAAD2it03FVChZPavHk6p/0NR; visid_incap_2627658=tTqQuPUUSSygwLgABnH2lpNKB2gAAAAAQUIPAAAAAAD1j3i3ePGjUT3xGmKKAVaY';
log_message("PandaDoc API Key and Cookie set.");

// Data for creating the document
$dataCreateDocument = [
    "name" => "Booking Document - Unit " . $unit_number,
    "template_uuid" => "LRAUwAP3XaG6MU8Ndy4uuW", // PandaDoc Template UUID
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
log_message("PandaDoc document creation data prepared.");

// Send request to create document
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
    log_message("ERROR: Failed to create PandaDoc document. HTTP Code: " . $httpCodeCreateDocument . ". Response: " . $responseCreateDocument);
    die('Error creating document: ' . $responseCreateDocument);
}

$responseData = json_decode($responseCreateDocument, true);
$documentId = $responseData['id'];
log_message("PandaDoc document successfully created. Document ID: " . $documentId);
echo "Document successfully created with ID: $documentId\n";

// Function to check document status until it becomes 'document.draft'
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
        log_message("ERROR: Failed to check document status (HTTP Code: " . $statusCode . "). Response: " . $response);
        die('Error checking document status: ' . $response);
    }
    return json_decode($response, true)['status'];
}

$attempt = 0;
$status = '';
log_message("Starting to check document status until 'document.draft'.");
// Wait for document to be in draft status (max 5 minutes = 30 attempts * 10 seconds)
while ($status !== 'document.draft' && $attempt < 30) {
    $status = checkDocumentStatus($documentId, $apiKey, $cookie);
    log_message("Attempt " . ($attempt + 1) . ": Document status is '" . $status . "'.");
    echo "Check #" . ($attempt + 1) . ": Status $status\n";
    if ($status !== 'document.draft') sleep(10); // Wait 10 seconds before next check
    $attempt++;
}

if ($status !== 'document.draft') {
    log_message("ERROR: Document did not reach 'document.draft' status within 5 minutes. Current status: " . $status);
    die('Document did not reach draft status within 5 minutes.');
}
log_message("Document reached 'document.draft' status. Proceeding to send.");

echo "Sending document...\n";

// Send the document
$dataSendDocument = [
    "message" => "Hello " . $billing['first_name'] . "! This is your booking document for Unit " . $unit_number . ". Please review and sign the document.",
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
    log_message("ERROR: Failed to send PandaDoc document. HTTP Code: " . $httpCodeSend . ". Response: " . $responseSend);
    die('Failed to send document: ' . $responseSend);
}
log_message("PandaDoc document successfully sent. HTTP Code: " . $httpCodeSend);
echo "Document successfully sent!\n";

// --- Extract Shared Link from Send Document Response ---
$responseSendDecoded = json_decode($responseSend, true);
$shared_link = '';

if (isset($responseSendDecoded['recipients']) && !empty($responseSendDecoded['recipients'])) {
    if (isset($responseSendDecoded['recipients'][0]['shared_link'])) {
        $shared_link = $responseSendDecoded['recipients'][0]['shared_link'];
        log_message("Extracted Shared Link: " . $shared_link);
        echo "Document Shared Link: $shared_link\n";
    } else {
        log_message("WARNING: 'shared_link' not found for the first recipient in the send document response.");
    }
} else {
    log_message("WARNING: 'recipients' array is empty or not found in the send document response.");
    echo "Could not find 'recipients' or 'shared_link' in the document send response.\n";
}


// ==============================
// UPDATE ORDER META & SEND CUSTOM EMAIL
// ==============================
log_message("Attempting to update order meta and send custom email.");
if (function_exists('wc_get_order')) {
    $order = wc_get_order($order_id);
    if ($order) {
        log_message("WooCommerce order found for ID: " . $order_id);
        // Update 'send_document' meta to 'sent'
        $order->update_meta_data('send_document', 'sent');
        log_message("Order meta 'send_document' set to 'sent'.");

        // --- Add Shared Link to Order Meta Data ---
        if (!empty($shared_link)) {
            $order->update_meta_data('_pandadoc_shared_link', $shared_link); // Using _ for hidden meta
            log_message("Order meta '_pandadoc_shared_link' updated with: " . $shared_link);
        } else {
            log_message("INFO: Shared link is empty, '_pandadoc_shared_link' not updated.");
        }
        $order->save();
        log_message("Order data saved.");
        echo "Meta 'send_document' updated to 'sent' for order $order_id.\n";

        // --- Custom Email Sending Logic ---
        // Only send email if the order status is 'pending'
        if ( $order->get_status() === 'pending' ) {
            log_message("Order status is 'pending', proceeding to send custom email.");
            $to = $order->get_billing_email();
            $payment_link = $order->get_checkout_payment_url();
            // Re-fetch shared_link from order meta to ensure it's the saved value
            $pandadoc_shared_link_from_meta = $order->get_meta('_pandadoc_shared_link');

            $subject = 'Please Complete Your Booking – Next Steps Required';

            $message  = "Dear Customer,<br><br>";
            $message .= "Thank you for your recent booking. Your booking process is not yet complete.<br><br>";
            $message .= "To finalize your unit reservation, please follow the steps below:<br><br>";

            // Step 1: Sign the document
            $message .= "<strong>1. Please sign the booking document first:</strong><br>";
            if ( ! empty( $pandadoc_shared_link_from_meta ) ) {
                $message .= "To sign your document, please click the link below:<br>";
                $message .= "<a href='" . esc_url( $pandadoc_shared_link_from_meta ) . "' style='display:inline-block; padding:10px 20px; background-color:#007bff; color:#ffffff; text-decoration:none; border-radius:3px;' target='_blank'>Sign Your Document</a><br><br>";
                log_message("Email includes PandaDoc shared link.");
            } else {
                $message .= "We have sent you an invitation to sign the booking agreement. Please check your email inbox (and spam folder).<br><br>";
                log_message("Email does NOT include PandaDoc shared link (link was empty).");
            }

            // Step 2: Make the payment
            $message .= "<strong>2. Then proceed with the payment:</strong><br>";
            $message .= "Please proceed with the payment using the link below:<br>";
            $message .= "<a href='" . esc_url( $payment_link ) . "' style='display:inline-block; padding:10px 20px; background-color:#28a745; color:#ffffff; text-decoration:none; border-radius:3px;'>Pay Now</a><br><br>";

            $message .= "If you have any questions or face any issues, feel free to contact us through the following link:<br>";
            $message .= "<a href='https://elementbali.com/contact-us/'>https://elementbali.com/contact-us/</a><br><br>";
            $message .= "Best regards,<br>";
            $message .= "Element Residence Team";

            $headers = array('Content-Type: text/html; charset=UTF-8');

            wc_mail( $to, $subject, $message, $headers );
            log_message("Custom pending notification email sent to: " . $to);
            echo "Pending notification email sent successfully.\n";
        } else {
            log_message("INFO: Custom pending notification email not sent because order status is '" . $order->get_status() . "' (not 'pending').");
            echo "Pending notification email not sent because order status is not 'pending'.\n";
        }
        // --- End Custom Email Sending Logic ---

    } else {
        log_message("ERROR: WooCommerce Order ID " . $order_id . " not found in wc_get_order().");
        echo "Order ID $order_id not found.\n";
    }
} else {
    log_message("ERROR: WooCommerce functions are not available (wc_get_order).");
    echo "WooCommerce functions are not available (wc_get_order).\n";
}

log_message("--- Webhook execution finished ---");

?>