add_filter( 'woocommerce_thankyou_order_received_text', 'custom_styled_thank_you_message', 10, 2 );

function custom_styled_thank_you_message( $text, $order ) {
    // Pastikan order adalah objek WC_Order yang valid
    if ( ! $order instanceof WC_Order ) {
        return $text; // Kembali ke teks default jika order tidak valid
    }

    // Periksa status order
    // Pesan kustom hanya akan ditampilkan jika statusnya 'processing' atau 'completed'
    $order_status = $order->get_status();

    if ( $order_status === 'processing' || $order_status === 'completed' ) {
        // Ambil nomor unit dari meta order item
        $unit_number = '';
        foreach ( $order->get_items() as $item_id => $item ) {
            $unit_number = wc_get_order_item_meta( $item_id, 'order_unit_number', true );
            if ( $unit_number ) {
                break; // Ambil nomor unit pertama yang ditemukan
            }
        }

        // Jika nomor unit tidak ditemukan, gunakan pesan default
        if ( empty( $unit_number ) ) {
            $unit_number = 'N/A'; // Atau pesan yang lebih deskriptif seperti 'unit yang Anda pesan'
        }

        // Pesan dengan nama pelanggan dan nomor unit
        $message = sprintf(
            'Congratulations, %s! You`ve successfully booked your unit %s!' ,
            esc_html( $order->get_billing_first_name() ),
            esc_html( $unit_number )
        );

        // Tambahkan gaya CSS untuk tampilan menarik
        $custom_style = '
            <style>
                .custom-thankyou-message {
                    background-color: #f0f9ff;
                    border: 2px solid #3b82f6;
                    border-radius: 10px;
                    padding: 20px;
                    text-align: center;
                    font-size: 18px;
                    color: #1e3a8a;
                    margin: 20px 0;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                }
                .custom-thankyou-message strong {
                    color: #3b82f6;
                    font-weight: 700;
                }
                .custom-thankyou-message .checkmark {
                    font-size: 30px;
                    color: #22c55e;
                    margin-bottom: 10px;
                    display: block;
                }
                /* Sembunyikan teks default "Order received" jika ada dan Anda ingin menggantinya sepenuhnya */
                #booking-intro {
                    display: none;
                }
            </style>
        ';

        // Gabungkan pesan dengan ikon checkmark dan gaya
        $output = $custom_style . '<div class="custom-thankyou-message"><div class="checkmark">✔</div>' . $message . '</div>';

        return $output;
    } else {
        // Jika status order bukan 'processing' atau 'completed', kembalikan teks default WooCommerce
        return $text;
    }
}