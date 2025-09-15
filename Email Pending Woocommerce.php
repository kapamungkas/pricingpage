<?php
add_action( 'woocommerce_checkout_order_processed', 'send_custom_pending_email', 20, 1 );
function send_custom_pending_email( $order_id ) {
    if ( ! $order_id ) {
        return;
    }

    $order = wc_get_order( $order_id );
    
    // Only trigger if the order is pending
    if ( $order->get_status() !== 'pending' ) return;

    $to = $order->get_billing_email();
    $payment_link = $order->get_checkout_payment_url();

    $subject = 'Complete Your Booking – Next Steps Required';

    $message  = "Dear Customer,<br><br>";
    $message .= "Thank you for your recent booking. Your booking process is not yet complete.<br><br>";
    $message .= "To finalize your unit reservation, please follow the steps below:<br><br>";
    $message .= "<strong>1. Sign the Booking Agreement:</strong><br>";
    $message .= "We have sent you an invitation to sign the booking agreement. Please check your email inbox (and spam folder).<br><br>";
    $message .= "<strong>2. Make the Payment:</strong><br>";
    $message .= "Please proceed with the payment using the link below:<br>";
    $message .= "<a href='" . esc_url( $payment_link ) . "' style='display:inline-block; padding:10px 20px; background-color:#28a745; color:#ffffff; text-decoration:none; border-radius:3px;'>Pay Now</a><br><br>";
    $message .= "If you have any questions or face any issues, feel free to contact us through the following link:<br>";
    $message .= "<a href='https://pellago.com/elements/contact-us/'>https://pellago.com/elements/contact-us/</a><br><br>";
    $message .= "Best regards,<br>";
    $message .= "Element Residence Team";

    $headers = array('Content-Type: text/html; charset=UTF-8');

    wc_mail( $to, $subject, $message, $headers );
}
