function disable_xendit_script() {
    // Hanya jalankan di halaman order received WooCommerce
    if (!is_wc_endpoint_url('order-received')) {
        return;
    }
    
    ?>
    <script>
    // Method 1: Override setInterval untuk mencegah countdown
    (function() {
        var originalSetInterval = window.setInterval;
        window.setInterval = function(func, delay) {
            // Block interval 1000ms yang kemungkinan adalah Xendit countdown
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
            
            // Setup button click handler
            var button = document.getElementById('xendit-invoice-onclick');
            if (button) {
                button.onclick = function() {
                    var invoiceUrl = "https://checkout-staging.xendit.co/web/6846509afb9505de50194e82#CREDIT_CARD";
                    window.location.href = invoiceUrl;
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
        // Clear semua interval yang mungkin berjalan
        for (var i = 1; i < 99999; i++) {
            window.clearInterval(i);
        }
        console.log('Semua timer dibersihkan');
    }, 5000);
    </script>
    
    <style>
    /* Method 5: Style button agar terlihat menarik */
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

// Hook ke wp_head dengan priority tinggi, hanya di halaman order received
add_action('wp_head', 'disable_xendit_script', 1);
