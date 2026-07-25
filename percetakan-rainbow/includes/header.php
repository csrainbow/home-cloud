<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Rainbow Printing - Percetakan Online Samarinda') ?></title>
    
    <!-- 🔥 Meta Tags SEO -->
    <meta name="description" content="Percetakan online terpercaya di Samarinda. Cetak undangan, stiker, banner, spanduk, dan kebutuhan percetakan lainnya. Harga terjangkau, kualitas terbaik.">
    <meta name="keywords" content="percetakan online samarinda, percetakan murah samarinda, cetak undangan samarinda, cetak stiker samarinda, cetak banner samarinda, cetak spanduk samarinda, cetak kartu nama samarinda, cetak brosur samarinda, cetak kalender samarinda, digital printing samarinda, percetakan terpercaya samarinda, rainbow printing samarinda, cetak foto samarinda, cetak flyer samarinda, percetakan offset samarinda, sablon samarinda, uv printer samarinda, percetakan terdekat samarinda">
    <meta name="robots" content="index, follow">
    <meta name="author" content="Rainbow Printing">
    <meta name="theme-color" content="#2c3e50">
    
    <!-- 🔥 Open Graph / Social Media -->
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle ?? 'Rainbow Printing') ?>">
    <meta property="og:description" content="Percetakan online terpercaya di Samarinda. Cetak undangan, stiker, banner, dan kebutuhan percetakan lainnya.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://rainbowprinting.web.id">
    <meta property="og:image" content="https://rainbowprinting.web.id/og-image.jpg">
    
    <!-- 🔥 Favicon -->
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    
    <!-- 🔥 CSS Utama -->
    <link rel="stylesheet" href="/css/style.css?v=<?= filemtime(__DIR__ . '/../css/style.css') ?: time() ?>">
    
    <!-- 🔥 Font Awesome (Ikon) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- 🔥 Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- 🔥 jQuery (CDN stabil) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- 🔥 HAPUS Cloudflare Insights - Sudah dibuang! -->
    
    <style>
        /* ============================================
           CSS DASAR UNTUK HEADER
           ============================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: 'Poppins', sans-serif; 
            background: #f8f9fa; 
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 0 15px; 
        }
        
        .main-content { 
            min-height: 500px; 
            padding: 20px 0;
            flex: 1;
        }
        
        /* ============================================
           NAVBAR
           ============================================ */
        .navbar { 
            background: #2c3e50; 
            padding: 12px 0; 
            color: #fff;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
        }
        
        .navbar .container { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            flex-wrap: wrap; 
        }
        
        .navbar-brand { 
            font-size: 22px; 
            font-weight: 700; 
            color: #fff; 
            text-decoration: none;
            transition: color 0.3s;
            letter-spacing: 0.5px;
        }
        .navbar-brand:hover { color: #f39c12; }
        .navbar-brand span { color: #f39c12; }
        
        .navbar-nav { 
            display: flex; 
            list-style: none; 
            gap: 20px; 
            flex-wrap: wrap; 
            align-items: center;
        }
        
        .navbar-nav a { 
            color: rgba(255,255,255,0.85); 
            text-decoration: none; 
            font-size: 14px; 
            transition: all 0.3s;
            padding: 6px 0;
            border-bottom: 2px solid transparent;
            position: relative;
        }
        
        .navbar-nav a:hover { 
            color: #f39c12; 
            border-bottom-color: #f39c12;
        }
        
        .navbar-nav a.active { 
            color: #f39c12; 
            font-weight: 600;
            border-bottom-color: #f39c12;
        }
        
        /* 🔥 Cart Badge */
        .cart-badge { 
            background: #e74c3c; 
            color: #fff; 
            border-radius: 50%; 
            padding: 2px 8px; 
            font-size: 11px; 
            margin-left: 4px;
            display: inline-block;
            min-width: 20px;
            text-align: center;
            transition: transform 0.2s;
            font-weight: 600;
        }
        .cart-badge.pulse {
            animation: pulse 0.5s ease;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.4); }
            100% { transform: scale(1); }
        }
        
        /* 🔥 Mobile Toggle */
        .navbar-toggle { 
            display: none; 
            background: none; 
            border: none; 
            color: #fff; 
            font-size: 24px; 
            cursor: pointer;
            padding: 4px 10px;
            border-radius: 4px;
            transition: background 0.3s;
        }
        .navbar-toggle:hover { background: rgba(255,255,255,0.1); }
        
        /* 🔥 Responsive */
        @media (max-width: 768px) { 
            .navbar-toggle { display: block; }
            .navbar-nav { 
                display: none; 
                width: 100%; 
                flex-direction: column; 
                gap: 8px; 
                padding: 15px 0 5px;
                border-top: 1px solid rgba(255,255,255,0.08);
                margin-top: 10px;
            }
            .navbar-nav.open { display: flex; }
            .navbar-nav a { 
                padding: 10px 12px;
                border-bottom: none;
                border-radius: 4px;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .navbar-nav a:hover {
                border-bottom: none;
                background: rgba(255,255,255,0.05);
                padding-left: 16px;
            }
            .navbar-nav a.active {
                border-bottom: none;
                background: rgba(243,156,18,0.15);
            }
            .navbar-nav a i {
                width: 20px;
                text-align: center;
            }
        }
        
        @media (max-width: 480px) {
            .navbar-brand {
                font-size: 18px;
            }
            .navbar-nav a {
                font-size: 13px;
            }
        }
        
        /* ============================================
           ALERT
           ============================================ */
        .alert { 
            padding: 12px 16px; 
            border-radius: 8px; 
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        .alert i { font-size: 18px; flex-shrink: 0; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .alert .close-btn {
            margin-left: auto;
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            opacity: 0.6;
            color: inherit;
            padding: 0 4px;
        }
        .alert .close-btn:hover { opacity: 1; }
        
        /* ============================================
           FOOTER STICKY
           ============================================ */
        .footer {
            background: #2c3e50;
            color: #fff;
            padding: 30px 0 15px;
            margin-top: auto;
            border-top: 4px solid #f39c12;
        }
        .footer a { color: #f39c12; text-decoration: none; }
        .footer a:hover { text-decoration: underline; }
        .footer .container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        .footer h4 { margin-bottom: 10px; color: #f39c12; font-size: 16px; }
        .footer ul { list-style: none; padding: 0; }
        .footer ul li { margin-bottom: 6px; }
        .footer ul li a { color: #ccc; font-size: 13px; transition: color 0.3s; }
        .footer ul li a:hover { color: #f39c12; text-decoration: none; }
        .footer-bottom {
            grid-column: 1 / -1;
            text-align: center;
            padding-top: 15px;
            border-top: 1px solid rgba(255,255,255,0.08);
            font-size: 12px;
            color: #999;
        }
        @media (max-width: 768px) {
            .footer .container { grid-template-columns: 1fr; text-align: center; }
            .footer ul li a { display: inline-block; }
        }
        
        /* ============================================
           LOADING SPINNER
           ============================================ */
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 3px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* ============================================
           STATUS BADGE (DEFAULT)
           ============================================ */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-pending { background: #f39c12; color: #fff; }
        .status-desain { background: #8e44ad; color: #fff; }
        .status-processed { background: #3498db; color: #fff; }
        .status-printing { background: #2c3e50; color: #fff; }
        .status-done { background: #27ae60; color: #fff; }
        .status-cancelled { background: #e74c3c; color: #fff; }
        .status-failed { background: #e74c3c; color: #fff; }
        .status-unpaid { background: #95a5a6; color: #fff; }
        .status-dp { background: #f39c12; color: #fff; }
        .status-paid { background: #27ae60; color: #fff; }
        .status-pending_verification { background: #3498db; color: #fff; }
        .status-verified { background: #27ae60; color: #fff; }
        .status-rejected { background: #e74c3c; color: #fff; }
    </style>
</head>
<body>

<!-- 🔥 NAVBAR -->
<nav class="navbar" role="navigation" aria-label="Menu Utama">
    <div class="container">
        <a href="/" class="navbar-brand" aria-label="Rainbow Printing Beranda">
            Rainbow <span>Printing</span>
        </a>
        
        <button class="navbar-toggle" 
                onclick="document.querySelector('.navbar-nav').classList.toggle('open')"
                aria-label="Toggle menu">
            <i class="fas fa-bars"></i>
        </button>
        
        <ul class="navbar-nav" role="menubar">
            <li role="none">
                <a href="/" role="menuitem" class="<?= $_SERVER['REQUEST_URI'] === '/' ? 'active' : '' ?>">
                    <i class="fas fa-home"></i> Beranda
                </a>
            </li>
            <li role="none">
                <a href="/products.php" role="menuitem" class="<?= strpos($_SERVER['REQUEST_URI'], 'products') !== false ? 'active' : '' ?>">
                    <i class="fas fa-box"></i> Produk
                </a>
            </li>
            <li role="none">
                <a href="/cart.php" role="menuitem" class="<?= strpos($_SERVER['REQUEST_URI'], 'cart') !== false ? 'active' : '' ?>">
                    <i class="fas fa-shopping-cart"></i> Keranjang
                    <span class="cart-badge" id="cartCount">0</span>
                </a>
            </li>
            <li role="none">
                <a href="/tentang-kami.php" role="menuitem" class="<?= strpos($_SERVER['REQUEST_URI'], 'tentang') !== false ? 'active' : '' ?>">
                    <i class="fas fa-info-circle"></i> Tentang Kami
                </a>
            </li>
            
            <?php if (isset($_SESSION['customer_id']) && $_SESSION['customer_id'] > 0): ?>
                <li role="none">
                    <a href="/customer/dashboard.php" role="menuitem" class="<?= strpos($_SERVER['REQUEST_URI'], 'customer/dashboard') !== false ? 'active' : '' ?>">
                        <i class="fas fa-user"></i> Pesanan Saya
                    </a>
                </li>
                <li role="none">
                    <a href="/logout.php" role="menuitem">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            <?php else: ?>
                <li role="none">
                    <a href="/login.php" role="menuitem" class="<?= strpos($_SERVER['REQUEST_URI'], 'login') !== false ? 'active' : '' ?>">
                        <i class="fas fa-key"></i> Login / Daftar
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </div>
</nav>

<!-- 🔥 MAIN CONTENT START -->
<div class="container main-content">

<?php
// 🔥 Tampilkan pesan flash jika ada
if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?= $_SESSION['success']; unset($_SESSION['success']); ?>
        <button class="close-btn" onclick="this.parentElement.remove()">&times;</button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?= $_SESSION['error']; unset($_SESSION['error']); ?>
        <button class="close-btn" onclick="this.parentElement.remove()">&times;</button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['warning'])): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i>
        <?= $_SESSION['warning']; unset($_SESSION['warning']); ?>
        <button class="close-btn" onclick="this.parentElement.remove()">&times;</button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['info'])): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i>
        <?= $_SESSION['info']; unset($_SESSION['info']); ?>
        <button class="close-btn" onclick="this.parentElement.remove()">&times;</button>
    </div>
<?php endif; ?>

<script>
var isLoggedIn = <?= isset($_SESSION['customer_id']) && $_SESSION['customer_id'] > 0 ? 'true' : 'false' ?>;
</script>
<script>
/**
 * 🔥 Update Jumlah Keranjang - Versi Sempurna
 * Dengan fallback ke berbagai metode
 */
(function() {
    'use strict';
    
    var cartBadge = document.getElementById('cartCount');
    if (!cartBadge) return;
    
    function updateCartCount() {
        
        if (!window.isLoggedIn) {
            var localCart = JSON.parse(localStorage.getItem('cart') || '[]');
            var total = localCart.reduce(function(s, i) { return s + (i.qty || 0); }, 0);
            updateBadge(cartBadge, total);
            return;
        }
        
        // 🔥 Coba ambil dari API
        fetch('/api-cart.php?action=count')
            .then(function(res) {
                if (!res.ok) throw new Error('API tidak tersedia');
                return res.json();
            })
            .then(function(data) {
                var count = parseInt(data.count) || 0;
                // 🔥 Fallback ke localStorage jika API return 0
                if (count === 0) {
                    var localCart = JSON.parse(localStorage.getItem('cart') || '[]');
                    count = localCart.reduce(function(s, i) { return s + (i.qty || 0); }, 0);
                }
                updateBadge(cartBadge, count);
            })
            .catch(function() {
                // 🔥 Fallback ke localStorage
                var localCart = JSON.parse(localStorage.getItem('cart') || '[]');
                var count = localCart.reduce(function(s, i) { return s + (i.qty || 0); }, 0);
                updateBadge(cartBadge, count);
            });
    }
    
    function updateBadge(element, count) {
        var oldCount = parseInt(element.textContent) || 0;
        element.textContent = count;
        
        // 🔥 Animasi jika berubah
        if (oldCount !== count) {
            element.classList.remove('pulse');
            // Trigger reflow untuk restart animasi
            void element.offsetWidth;
            element.classList.add('pulse');
        }
    }
    
    // 🔥 Jalankan saat DOM siap
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', updateCartCount);
    } else {
        updateCartCount();
    }
    
    // 🔥 Update otomatis setiap 30 detik (jika login)
    <?php if (isset($_SESSION['customer_id']) && $_SESSION['customer_id'] > 0): ?>
    setInterval(updateCartCount, 30000);
    <?php endif; ?>
    
    // 🔥 Update saat cart berubah (event listener)
    document.addEventListener('cartUpdated', updateCartCount);
    
})();

// 🔥 Fungsi global untuk dipanggil dari halaman lain
function refreshCartCount() {
    var event = new Event('cartUpdated');
    document.dispatchEvent(event);
}
</script>