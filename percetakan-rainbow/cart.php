<?php
require_once __DIR__ . '/config.php';

// 🔥 CEK SESSION UNTUK CART
$isLoggedIn = isset($_SESSION['customer_id']) && $_SESSION['customer_id'] > 0;

$pageTitle = 'Keranjang Belanja';
include 'includes/header.php';
?>

<style>
/* ============================================
   CART PAGE STYLES
   ============================================ */
.cart-container {
    max-width: 900px;
    margin: 0 auto;
}
.cart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 20px;
}
.cart-header h1 {
    font-size: 24px;
    color: #2c3e50;
    margin: 0;
}
.cart-header .cart-count {
    font-size: 14px;
    color: #6c757d;
}

/* 🔥 CART ITEMS */
.cart-items {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 20px;
}
.cart-item {
    display: flex;
    gap: 15px;
    padding: 15px 20px;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    align-items: center;
    transition: all 0.3s;
    border: 1px solid #e9ecef;
}
.cart-item:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
    border-color: #f39c12;
}
.cart-item .item-image {
    width: 70px;
    height: 70px;
    border-radius: 8px;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    flex-shrink: 0;
    overflow: hidden;
}
.cart-item .item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.cart-item .item-info {
    flex: 1;
    min-width: 0;
}
.cart-item .item-name {
    font-weight: 600;
    font-size: 15px;
    color: #2c3e50;
}
.cart-item .item-detail {
    font-size: 13px;
    color: #6c757d;
    margin-top: 2px;
}
.cart-item .item-price {
    font-weight: 600;
    font-size: 15px;
    color: #2c3e50;
    white-space: nowrap;
}
.cart-item .item-qty {
    display: flex;
    align-items: center;
    gap: 6px;
}
.cart-item .item-qty button {
    width: 30px;
    height: 30px;
    border: 1px solid #ddd;
    background: #f8f9fa;
    border-radius: 6px;
    cursor: pointer;
    font-size: 16px;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.cart-item .item-qty button:hover {
    background: #2c3e50;
    color: #fff;
    border-color: #2c3e50;
}
.cart-item .item-qty button:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
.cart-item .item-qty input {
    width: 40px;
    height: 30px;
    text-align: center;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    -moz-appearance: textfield;
}
.cart-item .item-qty input::-webkit-inner-spin-button,
.cart-item .item-qty input::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.cart-item .item-subtotal {
    font-weight: 700;
    font-size: 16px;
    color: #2c3e50;
    min-width: 100px;
    text-align: right;
}
.cart-item .item-remove {
    background: none;
    border: none;
    color: #e74c3c;
    font-size: 20px;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 4px;
    transition: all 0.2s;
}
.cart-item .item-remove:hover {
    background: #f8d7da;
    transform: scale(1.1);
}

/* 🔥 CART SUMMARY */
.cart-summary {
    background: #fff;
    padding: 20px 25px;
    border-radius: 10px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    border: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}
.cart-summary .summary-left {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}
.cart-summary .summary-item {
    text-align: center;
}
.cart-summary .summary-item .label {
    font-size: 12px;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.cart-summary .summary-item .value {
    font-size: 20px;
    font-weight: 700;
    color: #2c3e50;
}
.cart-summary .summary-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* 🔥 EMPTY CART */
.empty-cart {
    text-align: center;
    padding: 60px 20px;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.empty-cart .icon {
    font-size: 64px;
    margin-bottom: 15px;
    opacity: 0.5;
}
.empty-cart h2 {
    color: #2c3e50;
    margin-bottom: 8px;
}
.empty-cart p {
    color: #6c757d;
    margin-bottom: 20px;
}

/* 🔥 RECOMMENDED PRODUCTS */
.recommended {
    margin-top: 30px;
}
.recommended h3 {
    font-size: 18px;
    color: #2c3e50;
    margin-bottom: 15px;
}
.recommended-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 15px;
}
.recommended-card {
    background: #fff;
    border-radius: 8px;
    padding: 15px;
    text-align: center;
    border: 1px solid #e9ecef;
    transition: all 0.3s;
    text-decoration: none;
    color: #2c3e50;
}
.recommended-card:hover {
    border-color: #f39c12;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transform: translateY(-2px);
}
.recommended-card .icon {
    font-size: 32px;
    margin-bottom: 8px;
}
.recommended-card .name {
    font-size: 13px;
    font-weight: 600;
}
.recommended-card .price {
    font-size: 13px;
    color: #f39c12;
    font-weight: 600;
}

/* 🔥 RESPONSIVE */
@media (max-width: 768px) {
    .cart-item {
        flex-wrap: wrap;
        padding: 12px 15px;
    }
    .cart-item .item-image {
        width: 50px;
        height: 50px;
        font-size: 24px;
    }
    .cart-item .item-info {
        flex: 1 1 100%;
        order: 2;
    }
    .cart-item .item-price {
        order: 3;
    }
    .cart-item .item-qty {
        order: 4;
        width: 100%;
        justify-content: center;
    }
    .cart-item .item-subtotal {
        order: 5;
        width: 100%;
        text-align: center;
        padding-top: 8px;
        border-top: 1px solid #eee;
    }
    .cart-item .item-remove {
        order: 6;
        position: absolute;
        top: 8px;
        right: 8px;
    }
    .cart-item {
        position: relative;
    }
    .cart-summary {
        flex-direction: column;
        text-align: center;
    }
    .cart-summary .summary-left {
        justify-content: center;
    }
    .cart-summary .summary-actions {
        justify-content: center;
        width: 100%;
    }
    .cart-summary .summary-actions .btn {
        flex: 1;
        text-align: center;
    }
}

@media (max-width: 480px) {
    .recommended-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<div class="cart-container">
    <!-- 🔥 HEADER -->
    <div class="cart-header">
        <div>
            <h1>🛒 Keranjang Belanja</h1>
            <span class="cart-count" id="cartCountDisplay">0 item</span>
        </div>
        <div>
            <button onclick="clearCart()" class="btn btn-outline btn-sm" id="clearCartBtn" style="display:none;">
                <i class="fas fa-trash"></i> Kosongkan
            </button>
        </div>
    </div>

    <!-- 🔥 CART ITEMS -->
    <div id="cart-contents">
        <div class="empty-cart">
            <div class="icon">🛒</div>
            <h2>Keranjang Kosong</h2>
            <p>Belum ada produk di keranjang Anda. Yuk, mulai belanja!</p>
            <a href="products.php" class="btn btn-primary">Mulai Belanja</a>
        </div>
    </div>

    <!-- 🔥 CART SUMMARY -->
    <div id="cart-summary" style="display:none;">
        <div class="cart-summary">
            <div class="summary-left">
                <div class="summary-item">
                    <div class="label">Total Item</div>
                    <div class="value" id="totalItems">0</div>
                </div>
                <div class="summary-item">
                    <div class="label">Total Harga</div>
                    <div class="value" id="totalPrice">Rp 0</div>
                </div>
            </div>
            <div class="summary-actions">
                <a href="products.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Lanjut Belanja
                </a>
                <a href="checkout.php" class="btn btn-primary" id="checkoutBtn">
                    <i class="fas fa-credit-card"></i> Lanjut ke Checkout
                </a>
            </div>
        </div>
    </div>

    <!-- 🔥 RECOMMENDED PRODUCTS -->
    <div class="recommended" id="recommended-section" style="display:none;">
        <h3>💡 Produk yang Mungkin Kamu Suka</h3>
        <div class="recommended-grid" id="recommended-grid">
            <!-- Akan diisi oleh JavaScript -->
        </div>
    </div>
</div>

<script>
/**
 * 🔥 GET CART (dari localStorage)
 */
function getCart() {
    try {
        var cart = localStorage.getItem('cart');
        return cart ? JSON.parse(cart) : [];
    } catch (e) {
        return [];
    }
}

/**
 * 🔥 RENDER CART
 */
function renderCart() {
    var cart = getCart();
    var container = document.getElementById('cart-contents');
    var summary = document.getElementById('cart-summary');
    var checkoutBtn = document.getElementById('checkoutBtn');
    var clearBtn = document.getElementById('clearCartBtn');
    var countDisplay = document.getElementById('cartCountDisplay');
    var totalItemsEl = document.getElementById('totalItems');
    var totalPriceEl = document.getElementById('totalPrice');
    
    if (!container) return;
    
    // 🔥 Jika kosong
    if (cart.length === 0) {
        container.innerHTML = `
            <div class="empty-cart">
                <div class="icon">🛒</div>
                <h2>Keranjang Kosong</h2>
                <p>Belum ada produk di keranjang Anda. Yuk, mulai belanja!</p>
                <a href="products.php" class="btn btn-primary">Mulai Belanja</a>
            </div>
        `;
        summary.style.display = 'none';
        if (clearBtn) clearBtn.style.display = 'none';
        if (countDisplay) countDisplay.textContent = '0 item';
        document.getElementById('recommended-section').style.display = 'block';
        loadRecommended();
        return;
    }
    
    // 🔥 Render items
    var html = '<div class="cart-items">';
    var totalItems = 0;
    var totalPrice = 0;
    
    cart.forEach(function(item) {
        var subtotal = item.price * item.qty;
        totalItems += item.qty;
        totalPrice += subtotal;
        
        var isCustom = item.sizeUnit && item.sizeUnit !== 'none';
        var dimLabel = isCustom && item.label ? ' (' + item.label + ')' : '';
        var dsLabel = '';
        if (item.designService === 'jasa') {
            dsLabel = ' <span style="color:#e67e22;">🎨 +Jasa Desain</span>';
        } else if (item.designService === 'upload') {
            dsLabel = item.designFile ? ' <span style="color:#27ae60;">✅ File terupload</span>' : ' <span style="color:#f39c12;">📎 File Desain (upload di checkout)</span>';
        }
        
        var itemKey = getItemKey(item);
        
        html += `
            <div class="cart-item" data-key="${itemKey}">
                <div class="item-image">
                    ${item.image ? `<img src="/uploads/${item.image}" alt="${item.name}">` : '📦'}
                </div>
                <div class="item-info">
                    <div class="item-name">${item.name}${dsLabel}</div>
                    <div class="item-detail">${dimLabel}</div>
                </div>
                <div class="item-price">${formatRupiah(item.price)}</div>
                <div class="item-qty">
                    <button onclick="updateCartQty('${itemKey}', -1)" ${item.qty <= 1 ? 'disabled' : ''}>−</button>
                    <input type="text" value="${item.qty}" readonly>
                    <button onclick="updateCartQty('${itemKey}', 1)" ${item.qty >= 999 ? 'disabled' : ''}>+</button>
                </div>
                <div class="item-subtotal">${formatRupiah(subtotal)}</div>
                <button class="item-remove" onclick="removeFromCart('${itemKey}')" title="Hapus item">×</button>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
    
    // 🔥 Update summary
    summary.style.display = 'block';
    if (clearBtn) clearBtn.style.display = 'inline-block';
    if (countDisplay) countDisplay.textContent = totalItems + ' item' + (totalItems > 1 ? 's' : '');
    if (totalItemsEl) totalItemsEl.textContent = totalItems;
    if (totalPriceEl) totalPriceEl.textContent = formatRupiah(totalPrice);
    
    // 🔥 Recommended products
    document.getElementById('recommended-section').style.display = 'block';
    loadRecommended();
}

/**
 * 🔥 LOAD RECOMMENDED PRODUCTS
 */
function loadRecommended() {
    var grid = document.getElementById('recommended-grid');
    if (!grid) return;
    
    var recommended = [
        { name: 'Cetak Brosur A4', icon: '📄', price: 1500, link: '/product.php?id=1' },
        { name: 'Cetak Kartu Nama', icon: '🪪', price: 35000, link: '/product.php?id=2' },
        { name: 'Cetak Sticker Chromo', icon: '🏷️', price: 12000, link: '/product.php?id=4' },
        { name: 'Cetak Undangan', icon: '💌', price: 5000, link: '/product.php?id=5' }
    ];
    
    var html = '';
    recommended.forEach(function(p) {
        html += `
            <a href="${p.link}" class="recommended-card">
                <div class="icon">${p.icon}</div>
                <div class="name">${p.name}</div>
                <div class="price">${formatRupiah(p.price)}</div>
            </a>
        `;
    });
    
    grid.innerHTML = html;
}

/**
 * 🔥 CLEAR CART
 */
function clearCart() {
    if (!confirm('Yakin ingin mengosongkan keranjang?')) return;
    saveCart([]);
    renderCart();
    updateCartBadge();
    showNotification('Keranjang berhasil dikosongkan!');
}

/**
 * 🔥 UPDATE CART BADGE (override dari header)
 */
function updateCartBadge() {
    var cart = getCart();
    var count = cart.reduce(function(total, item) {
        return total + (parseInt(item.qty) || 0);
    }, 0);
    
    var badges = document.querySelectorAll('.cart-badge');
    badges.forEach(function(badge) {
        badge.textContent = count;
    });
}

/**
 * 🔥 SHOW NOTIFICATION
 */
function showNotification(msg, type) {
    type = type || 'success';
    var existing = document.querySelector('.notif-toast');
    if (existing) existing.remove();
    
    var div = document.createElement('div');
    div.className = 'notif-toast';
    var bgColor = type === 'success' ? '#27ae60' : type === 'error' ? '#e74c3c' : '#f39c12';
    div.style.cssText = 'position:fixed;top:15px;left:50%;transform:translateX(-50%);background:' + bgColor + ';color:#fff;padding:12px 24px;border-radius:8px;z-index:99999;font-size:14px;box-shadow:0 4px 12px rgba(0,0,0,.15);text-align:center;max-width:90%;';
    div.textContent = msg;
    document.body.appendChild(div);
    setTimeout(function() {
        div.style.opacity = '0';
        div.style.transition = '.3s';
    }, 2500);
    setTimeout(function() { div.remove(); }, 3000);
}

// 🔥 INIT
document.addEventListener('DOMContentLoaded', function() {
    renderCart();
});
</script>

<?php include 'includes/footer.php'; ?>