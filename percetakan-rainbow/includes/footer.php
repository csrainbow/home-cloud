<?php
// 🔥 AMBIL SETTINGS UNTUK FOOTER
$footerText = getSetting('footer_text') ?: '';
$storeName = getSetting('store_name') ?: SITE_NAME;
$storeAddress = getSetting('store_address') ?: '';
$storePhone = getSetting('store_phone') ?: '';
$adminEmail = getSetting('admin_email') ?: '';
$whatsappNumber = getSetting('whatsapp_number') ?: '';
?>

</div> <!-- 🔥 CLOSE MAIN-CONTENT -->

<!-- ============================================
     FOOTER
     ============================================ -->
<footer class="footer">
    <div class="container">
        <!-- 🔥 ROW 1: STORE INFO -->
        <div class="footer-column">
            <h4><?= htmlspecialchars($storeName) ?></h4>
            <p><?= nl2br(htmlspecialchars($storeAddress)) ?></p>
            <?php if ($storePhone): ?>
                <p><i class="fas fa-phone"></i> <?= htmlspecialchars($storePhone) ?></p>
            <?php endif; ?>
            <?php if ($adminEmail): ?>
                <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($adminEmail) ?></p>
            <?php endif; ?>
            <?php if ($whatsappNumber): ?>
                <p>
                    <i class="fab fa-whatsapp"></i> 
                    <a href="https://wa.me/<?= htmlspecialchars($whatsappNumber) ?>" target="_blank">
                        <?= htmlspecialchars($whatsappNumber) ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>

        <!-- 🔥 ROW 2: QUICK LINKS -->
        <div class="footer-column">
            <h4>Menu</h4>
            <ul>
                <li><a href="/"><i class="fas fa-home"></i> Beranda</a></li>
                <li><a href="/products.php"><i class="fas fa-box"></i> Produk</a></li>
                <li><a href="/tentang-kami.php"><i class="fas fa-info-circle"></i> Tentang Kami</a></li>
                <?php if (isset($_SESSION['customer_id']) && $_SESSION['customer_id'] > 0): ?>
                    <li><a href="/customer/dashboard.php"><i class="fas fa-user"></i> Dashboard</a></li>
                <?php else: ?>
                    <li><a href="/login.php"><i class="fas fa-key"></i> Login / Daftar</a></li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- 🔥 ROW 3: CUSTOMER SERVICE -->
        <div class="footer-column">
            <h4>Customer Service</h4>
            <ul>
                <li><a href="/faq.php"><i class="fas fa-question-circle"></i> FAQ</a></li>
                <li><a href="/tentang-kami.php#kontak"><i class="fas fa-phone"></i> Kontak</a></li>
                <li><a href="/privacy-policy.php"><i class="fas fa-shield-alt"></i> Privasi</a></li>
                <li><a href="/terms-of-service.php"><i class="fas fa-file-contract"></i> Syarat & Ketentuan</a></li>
            </ul>
        </div>

        <!-- 🔥 ROW 4: SOCIAL MEDIA -->
        <?php $socialLinksExist = getSetting('facebook_url') || getSetting('instagram_url') || getSetting('youtube_url') || $whatsappNumber; ?>
        <div class="footer-column">
            <h4>Ikuti Kami</h4>
            <div class="social-links">
                <?php if (getSetting('facebook_url')): ?>
                    <a href="<?= htmlspecialchars(getSetting('facebook_url')) ?>" target="_blank" class="social-link facebook" aria-label="Facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                <?php endif; ?>
                <?php if (getSetting('instagram_url')): ?>
                    <a href="<?= htmlspecialchars(getSetting('instagram_url')) ?>" target="_blank" class="social-link instagram" aria-label="Instagram">
                        <i class="fab fa-instagram"></i>
                    </a>
                <?php endif; ?>
                <?php if (getSetting('youtube_url')): ?>
                    <a href="<?= htmlspecialchars(getSetting('youtube_url')) ?>" target="_blank" class="social-link youtube" aria-label="YouTube">
                        <i class="fab fa-youtube"></i>
                    </a>
                <?php endif; ?>
                <?php if ($whatsappNumber): ?>
                    <a href="https://wa.me/<?= htmlspecialchars($whatsappNumber) ?>" target="_blank" class="social-link whatsapp" aria-label="WhatsApp">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                <?php endif; ?>
                <?php if (!$socialLinksExist): ?>
                    <p style="font-size:12px;color:#666;">Belum ada sosial media</p>
                <?php endif; ?>
            </div>
            
            <!-- 🔥 PAYMENT METHODS -->
            <div style="margin-top:15px;">
                <p style="font-size:12px;color:#666;margin-bottom:6px;">Metode Pembayaran</p>
                <div class="payment-icons">
                    <i class="fas fa-credit-card" title="Kartu Kredit"></i>
                    <i class="fas fa-university" title="Transfer Bank"></i>
                    <i class="fas fa-mobile-alt" title="QRIS"></i>
                    <i class="fas fa-building" title="COD"></i>
                </div>
            </div>
        </div>

        <!-- 🔥 FOOTER BOTTOM -->
        <div class="footer-bottom">
            <p>
                &copy; <?= date('Y') ?> <?= htmlspecialchars($storeName) ?>. All rights reserved.
                <?php if ($footerText): ?>
                    <br><span style="font-size:11px;"><?= nl2br(htmlspecialchars($footerText)) ?></span>
                <?php endif; ?>
            </p>
            <p style="font-size:10px;color:#777;margin-top:4px;">
                <i class="fas fa-code"></i> Dibangun dengan ❤️ di Samarinda
            </p>
        </div>
    </div>
</footer>

<!-- ============================================
     SCRIPTS
     ============================================ -->
<script src="/script.js?v=<?= filemtime(__DIR__ . '/../script.js') ?: time() ?>"></script>
<?php if (isset($pageTitle) && strpos($pageTitle, 'Invoice') !== false): ?>
    <!-- 🔥 SCRIPT UNTUK INVOICE -->
    <script>
        // Auto print invoice
        (function() {
            if (window.location.pathname.includes('invoice.php')) {
                // Tunggu 1 detik setelah halaman load
                setTimeout(function() {
                    window.print();
                }, 1000);
            }
        })();
    </script>
<?php endif; ?>

<?php if (isset($_SESSION['customer_id'])): ?>
    <!-- 🔥 SCRIPT UNTUK UPDATE CART -->
    <script>
        function updateCartCount() {
            var cartBadge = document.getElementById('cartCount');
            if (!cartBadge) return;
            
            fetch('/api-cart.php?action=count')
                .then(function(res) {
                    if (!res.ok) throw new Error('Network error');
                    return res.json();
                })
                .then(function(data) {
                    var count = parseInt(data.count) || 0;
                    cartBadge.textContent = count;
                    
                    // Animasi pulse
                    if (count > 0) {
                        cartBadge.classList.remove('pulse');
                        void cartBadge.offsetWidth;
                        cartBadge.classList.add('pulse');
                    }
                })
                .catch(function() {
                    // Jika API error, jangan tampilkan error
                });
        }
        
        // 🔥 Update saat halaman dimuat
        document.addEventListener('DOMContentLoaded', function() {
            updateCartCount();
            
            // 🔥 Update setiap 30 detik
            setInterval(updateCartCount, 30000);
        });
        
        // 🔥 Fungsi global untuk refresh cart dari halaman lain
        window.refreshCart = function() {
            updateCartCount();
        };
    </script>
<?php endif; ?>

<!-- ============================================
     FOOTER STYLES (TERSISIP DI SINI)
     ============================================ -->
<style>
/* 🔥 FOOTER */
.footer {
    background: #2c3e50;
    color: #ccc;
    padding: 40px 0 15px;
    margin-top: 40px;
    border-top: 4px solid #f39c12;
}

.footer .container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 30px;
}

.footer-column h4 {
    color: #f39c12;
    font-size: 16px;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.footer-column p {
    font-size: 13px;
    line-height: 1.7;
    color: #ccc;
    margin: 4px 0;
}

.footer-column ul {
    list-style: none;
    padding: 0;
}

.footer-column ul li {
    margin-bottom: 6px;
}

.footer-column ul li a {
    color: #ccc;
    text-decoration: none;
    font-size: 13px;
    transition: all 0.3s;
}

.footer-column ul li a:hover {
    color: #f39c12;
    padding-left: 4px;
}

.footer-column ul li a i {
    width: 18px;
    color: #f39c12;
}

/* 🔥 SOCIAL LINKS */
.social-links {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.social-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    color: #fff;
    text-decoration: none;
    transition: all 0.3s;
    font-size: 16px;
}

.social-link:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.social-link.facebook {
    background: #1877f2;
}
.social-link.instagram {
    background: linear-gradient(135deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888);
}
.social-link.youtube {
    background: #ff0000;
}
.social-link.whatsapp {
    background: #25d366;
}
.social-link.twitter {
    background: #1da1f2;
}

/* 🔥 PAYMENT ICONS */
.payment-icons {
    display: flex;
    gap: 8px;
    font-size: 22px;
    color: #ddd;
}

.payment-icons i {
    transition: all 0.3s;
    cursor: default;
}

.payment-icons i:hover {
    color: #f39c12;
    transform: scale(1.1);
}

/* 🔥 FOOTER BOTTOM */
.footer-bottom {
    grid-column: 1 / -1;
    text-align: center;
    padding-top: 15px;
    margin-top: 15px;
    border-top: 1px solid rgba(255,255,255,0.08);
    font-size: 13px;
    color: #999;
}

.footer-bottom a {
    color: #f39c12;
    text-decoration: none;
}

.footer-bottom a:hover {
    text-decoration: underline;
}

/* 🔥 RESPONSIVE FOOTER */
@media (max-width: 768px) {
    .footer .container {
        grid-template-columns: 1fr;
        text-align: center;
        gap: 20px;
    }
    
    .footer-column h4 {
        border-bottom: none;
        text-align: center;
    }
    
    .social-links {
        justify-content: center;
    }
    
    .payment-icons {
        justify-content: center;
    }
    
    .footer-column ul li a i {
        width: auto;
        margin-right: 4px;
    }
}

@media (max-width: 480px) {
    .footer {
        padding: 25px 0 10px;
    }
    .footer .container {
        gap: 15px;
    }
    .footer-column h4 {
        font-size: 14px;
    }
    .footer-column p,
    .footer-column ul li a {
        font-size: 12px;
    }
}
</style>

</body>
</html>