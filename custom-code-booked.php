<?php
// SHORTCODE BUTTON
function custom_checkout_button_shortcode($atts) {
    $atts = shortcode_atts(array(
        'product_id'   => '',
        'title'        => '',
        'desc'         => '',
        'label'        => 'BOOK NOW'
    ), $atts);
    ob_start();
    ?>
    <form action="<?php echo esc_url(wc_get_cart_url()); ?>" method="post" style="display:inline;">
        <?php wp_nonce_field('woocommerce-cart', 'woocommerce-cart-nonce'); ?>
        <input type="hidden" name="add-to-cart" value="<?php echo esc_attr($atts['product_id']); ?>">
        <input type="hidden" name="order_unit_number" value="<?php echo esc_attr($atts['title']); ?>" class="order_unit_number">
        <input type="hidden" name="custom_title" value="<?php echo esc_attr($atts['title']); ?>" class="custom_title">
        <input type="hidden" name="custom_desc" value="<?php echo esc_attr($atts['desc']); ?>" class="custom_desc">
        <input type="hidden" name="clear_cart_first" value="1">

        <input type="hidden" name="order_block_name" value="" class="order_block_name">
        <input type="hidden" name="order_bedroom" value="" class="order_bedroom">
        <input type="hidden" name="order_bathroom" value="" class="order_bathroom">
        <input type="hidden" name="order_internalSQM" value="" class="order_internalSQM">
        <input type="hidden" name="order_externalSQM" value="" class="order_externalSQM">
        <input type="hidden" name="order_price_IDR" value="" class="order_price_IDR">
        <input type="hidden" name="order_price_USD" value="" class="order_price_USD">
        <input type="hidden" name="order_booking_fee_IDR" value="" class="order_booking_fee_IDR">
        
        <button type="submit" style="background:black; color:white; border:none; border-radius:0; width:100%;"><?php echo esc_html($atts['label']); ?></button>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('custom_checkout_button', 'custom_checkout_button_shortcode');

// CLEAR CART SEBELUM ADD-TO-CART (FIXED VERSION)
add_action('wp_loaded', 'custom_clear_cart_before_add', 5);
function custom_clear_cart_before_add() {
    if (isset($_POST['add-to-cart']) && isset($_POST['clear_cart_first']) && WC()->cart) {
        // Clear cart before adding new item
        WC()->cart->empty_cart();
    }
}

// TANGKAP DATA KUSTOM
add_filter('woocommerce_add_cart_item_data', function($cart_item_data, $product_id, $variation_id) {
    if (isset($_POST['custom_title'])) {
        $cart_item_data['custom_title'] = sanitize_text_field($_POST['custom_title']);
    }
    if (isset($_POST['custom_desc'])) {
        $cart_item_data['custom_desc'] = sanitize_text_field($_POST['custom_desc']);
    }
    if (isset($_POST['order_unit_number'])) {
        $cart_item_data['order_unit_number'] = sanitize_text_field($_POST['order_unit_number']);
    }
    
    // Tangkap semua data order yang baru
    if (isset($_POST['order_block_name'])) {
        $cart_item_data['order_block_name'] = sanitize_text_field($_POST['order_block_name']);
    }
    if (isset($_POST['order_bedroom'])) {
        $cart_item_data['order_bedroom'] = sanitize_text_field($_POST['order_bedroom']);
    }
    if (isset($_POST['order_bathroom'])) {
        $cart_item_data['order_bathroom'] = sanitize_text_field($_POST['order_bathroom']);
    }
    if (isset($_POST['order_internalSQM'])) {
        $cart_item_data['order_internalSQM'] = sanitize_text_field($_POST['order_internalSQM']);
    }
    if (isset($_POST['order_externalSQM'])) {
        $cart_item_data['order_externalSQM'] = sanitize_text_field($_POST['order_externalSQM']);
    }
    if (isset($_POST['order_price_IDR'])) {
        $cart_item_data['order_price_IDR'] = sanitize_text_field($_POST['order_price_IDR']);
    }
    if (isset($_POST['order_price_USD'])) {
        $cart_item_data['order_price_USD'] = sanitize_text_field($_POST['order_price_USD']);
    }
    if (isset($_POST['order_booking_fee_IDR'])) {
        $cart_item_data['order_booking_fee_IDR'] = sanitize_text_field($_POST['order_booking_fee_IDR']);
    }
    
    return $cart_item_data;
}, 10, 3);

// TAMPILKAN DI CART & CHECKOUT
add_filter('woocommerce_get_item_data', function($item_data, $cart_item) {
    if (!empty($cart_item['custom_title'])) {
        $item_data[] = [
            'name'  => 'Title',
            'value' => $cart_item['custom_title'],
        ];
    }
    if (!empty($cart_item['custom_desc'])) {
        $item_data[] = [
            'name'  => 'Description',
            'value' => $cart_item['custom_desc'],
        ];
    }
    
    // Tampilkan data order di cart & checkout
    if (!empty($cart_item['order_block_name'])) {
        $item_data[] = [
            'name'  => 'Block Name',
            'value' => $cart_item['order_block_name'],
        ];
    }
    if (!empty($cart_item['order_bedroom'])) {
        $item_data[] = [
            'name'  => 'Bedroom',
            'value' => $cart_item['order_bedroom'],
        ];
    }
    if (!empty($cart_item['order_bathroom'])) {
        $item_data[] = [
            'name'  => 'Bathroom',
            'value' => $cart_item['order_bathroom'],
        ];
    }
    if (!empty($cart_item['order_internalSQM'])) {
        $item_data[] = [
            'name'  => 'Internal SQM',
            'value' => $cart_item['order_internalSQM'],
        ];
    }
    if (!empty($cart_item['order_externalSQM'])) {
        $item_data[] = [
            'name'  => 'External SQM',
            'value' => $cart_item['order_externalSQM'],
        ];
    }
    if (!empty($cart_item['order_price_IDR'])) {
        $item_data[] = [
            'name'  => 'Price IDR',
            'value' => $cart_item['order_price_IDR'],
        ];
    }
    if (!empty($cart_item['order_price_USD'])) {
        $item_data[] = [
            'name'  => 'Price USD',
            'value' => $cart_item['order_price_USD'],
        ];
    }
    if (!empty($cart_item['order_booking_fee_IDR'])) {
        $item_data[] = [
            'name'  => 'Booking Fee IDR',
            'value' => $cart_item['order_booking_fee_IDR'],
        ];
    }
    
    return $item_data;
}, 10, 2);

// SIMPAN DI ORDER
add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values, $order) {
    if (!empty($values['custom_title'])) {
        $item->add_meta_data('Title', $values['custom_title']);
    }
    if (!empty($values['custom_desc'])) {
        $item->add_meta_data('Description', $values['custom_desc']);
    }
    
    if (!empty($values['order_unit_number'])) {
        $item->add_meta_data('order_unit_number', $values['order_unit_number']);
    }
    
    // Simpan semua data order ke meta order
    if (!empty($values['order_block_name'])) {
        $item->add_meta_data('order_block_name', $values['order_block_name']);
    }
    if (!empty($values['order_bedroom'])) {
        $item->add_meta_data('order_bedroom', $values['order_bedroom']);
    }
    if (!empty($values['order_bathroom'])) {
        $item->add_meta_data('order_bathroom', $values['order_bathroom']);
    }
    if (!empty($values['order_internalSQM'])) {
        $item->add_meta_data('order_internalSQM', $values['order_internalSQM']);
    }
    if (!empty($values['order_externalSQM'])) {
        $item->add_meta_data('order_externalSQM', $values['order_externalSQM']);
    }
    if (!empty($values['order_price_IDR'])) {
        $item->add_meta_data('order_price_IDR', $values['order_price_IDR']);
    }
    if (!empty($values['order_price_USD'])) {
        $item->add_meta_data('order_price_USD', $values['order_price_USD']);
    }
    if (!empty($values['order_booking_fee_IDR'])) {
        $item->add_meta_data('order_booking_fee_IDR', $values['order_booking_fee_IDR']);
    }
    
    $order->update_meta_data('send_document', 'not sent');
}, 10, 4);

// REDIRECT KE CHECKOUT
add_filter('woocommerce_add_to_cart_redirect', function($url) {
    return wc_get_checkout_url();
});