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
    $order->update_meta_data('send_document', 'not sent');
}, 10, 4);

// REDIRECT KE CHECKOUT
add_filter('woocommerce_add_to_cart_redirect', function($url) {
    return wc_get_checkout_url();
});