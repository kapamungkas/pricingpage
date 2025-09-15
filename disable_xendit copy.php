function disable_xendit_script() {
    // Hanya jalankan di halaman order received WooCommerce
    if (!is_wc_endpoint_url('order-received')) {
        return;
    }

    // Ambil order ID dari query string
    $order_id = absint(get_query_var('order-received'));
    if (!$order_id) {
        return;
    }

    // Ambil data order
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    // Ambil URL invoice dari metadata Xendit (sesuaikan dengan kunci meta yang digunakan plugin Xendit)
    $invoice_url = $order->get_meta('Xendit_invoice_url'); // Ganti '_xendit_invoice_url' dengan kunci meta yang benar
    if (empty($invoice_url)) {
        // Jika tidak ada URL di metadata, coba logika lain atau fallback
        $invoice_url = ''; // Bisa diganti dengan logika lain untuk mendapatkan URL
    }

    ?>
    <script>
    // Method 1: Override setInterval untuk mencegah countdown
    (function() {
        var originalSetInterval = window.setInterval;
        window.setInterval = function(func, delay) {
            if (delay === 1000) {
                var funcString = func.toString();
                if (funcString.includes('xendit-invoice-countdown') || 
                    funcString.includes('timeLeft') || 
                    funcString.includes('checkout-staging.xendit.co')) {
                    console.log('Xendit countdown script diblokir');
                    return null;
                }
            }
            return originalSetInterval(func, delay);
        };
    })();
    
    // Method 2: Stop countdown tapi tetap tampilkan button
    document.addEventListener('DOMContentLoaded', function() {
        var elem = document.getElementById('xendit-invoice-countdown');
        if (elem) {
            // Ganti konten dengan button langsung tanpa countdown
            elem.innerHTML = 'Check your email for the contract and return here after you have signed! </br></br><button id="xendit-invoice-onclick">Pay Now</button>';
            
            // Setup button click handler dengan URL dinamis
            var button = document.getElementById('xendit-invoice-onclick');
            if (button) {
                button.onclick = function() {
                    var invoiceUrl = <?php echo json_encode(esc_url($invoice_url)); ?>;
                    if (invoiceUrl) {
                        window.location.href = invoiceUrl;
                    } else {
                        console.log('Invoice URL tidak tersedia');
                        alert('Invoice URL tidak tersedia. Silakan hubungi dukungan.');
                    }
                };
            }
            console.log('Countdown dihentikan, button tetap aktif');
        }
    });
    
    // Method 3: Override window.location.replace
    window.addEventListener('load', function() {
        var originalReplace = window.location.replace;
        window.location.replace = function(url) {
            if (url && url.includes('checkout-staging.xendit.co')) {
                console.log('Redirect ke Xendit diblokir: ' + url);
                return false;
            }
            return originalReplace.call(window.location, url);
        };
    });
    
    // Method 4: Clear semua timer yang ada setelah 5 detik (safety net)
    setTimeout(function() {
        for (var i = 1; i < 99999; i++) {
            window.clearInterval(i);
        }
        console.log('Semua timer dibersihkan');
    }, 5000);
    </script>
    
    <style>
    #xendit-invoice-onclick {
        background-color: #007cba;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 16px;
        margin-left: 10px;
    }
    
    #xendit-invoice-onclick:hover {
        background-color: #005a87;
    }
    </style>
    <?php
}

// Hook ke wp_head dengan priority tinggi
add_action('wp_head', 'disable_xendit_script', 1);