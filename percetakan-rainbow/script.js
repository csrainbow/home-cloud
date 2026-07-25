/**
 * ============================================
 * RAINBOW PRINTING - MAIN SCRIPT v3.0
 * ============================================
 * Fitur: Cart, Checkout, Midtrans, DP, WhatsApp
 * ============================================
 */

// ============================================
// UTILITY FUNCTIONS
// ============================================

/**
 * Format Rupiah
 */
function formatRupiah(amount) {
    return 'Rp ' + Number(amount).toLocaleString('id-ID');
}

/**
 * Format Rupiah Short (untuk tampilan ringkas)
 */
function formatRupiahShort(amount) {
    amount = Number(amount) || 0;
    if (amount >= 1000000) {
        return 'Rp ' + (amount / 1000000).toFixed(1) + ' Jt';
    } else if (amount >= 1000) {
        return 'Rp ' + (amount / 1000).toFixed(0) + ' Rb';
    }
    return formatRupiah(amount);
}

/**
 * Format Tanggal
 */
function formatDate(dateString) {
    if (!dateString) return '-';
    var d = new Date(dateString);
    return d.toLocaleDateString('id-ID', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Validasi Nomor WhatsApp
 */
function validatePhone(phone) {
    var clean = phone.replace(/[^0-9]/g, '');
    return /^(0|62)\d{8,13}$/.test(clean);
}

/**
 * Tampilkan Notifikasi Toast
 */
function showNotification(msg, type) {
    type = type || 'success';
    var existing = document.querySelector('.notif-toast');
    if (existing) existing.remove();
    
    var div = document.createElement('div');
    div.className = 'notif-toast';
    
    var bgColor = type === 'success' ? '#27ae60' : type === 'error' ? '#e74c3c' : type === 'warning' ? '#f39c12' : '#3498db';
    var icon = type === 'success' ? '✅' : type === 'error' ? '❌' : type === 'warning' ? '⚠️' : 'ℹ️';
    
    div.style.cssText = 'position:fixed;top:15px;left:50%;transform:translateX(-50%);background:' + bgColor + ';color:#fff;padding:12px 24px;border-radius:8px;z-index:99999;font-size:14px;box-shadow:0 4px 12px rgba(0,0,0,.15);text-align:center;max-width:90%;';
    div.innerHTML = icon + ' ' + msg;
    document.body.appendChild(div);
    
    setTimeout(function() {
        div.style.opacity = '0';
        div.style.transition = '.3s';
    }, 3000);
    setTimeout(function() { div.remove(); }, 3500);
}

/**
 * Konfirmasi dengan Dialog
 */
function confirmAction(message) {
    return confirm(message);
}

// ============================================
// MOBILE MENU
// ============================================

function toggleMenu() {
    document.querySelector('.navbar-nav')?.classList.toggle('open');
}

// ============================================
// CART FUNCTIONS
// ============================================

/**
 * Ambil data cart dari localStorage
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
 * Simpan cart ke localStorage
 */
function saveCart(cart) {
    localStorage.setItem('cart', JSON.stringify(cart));
    updateCartBadge();
    // Trigger event untuk update badge di header
    if (typeof refreshCartCount === 'function') {
        refreshCartCount();
    }
}

/**
 * Update badge keranjang
 */
function updateCartBadge() {
    var cart = getCart();
    var count = cart.reduce(function(total, item) {
        return total + (parseInt(item.qty) || 0);
    }, 0);
    var badges = document.querySelectorAll('.cart-badge, #cart-count');
    badges.forEach(function(badge) {
        badge.textContent = count;
    });
}

/**
 * Get item key untuk identifikasi unik
 */
function getItemKey(item) {
    if (item._key) return item._key;
    if (item.customSize || (item.sizeUnit && item.sizeUnit !== 'none')) {
        var key = item.id + '-' + (item.width || 0) + 'x' + (item.height || 0);
        if (item.material) key += '-' + item.material;
        if (item.designService) key += '-' + item.designService;
        return key;
    }
    return String(item.id);
}



/**
 * Hitung harga banner berdasarkan ukuran
 */
function calcBannerPrice(pricePerM2) {
    var wInput = document.getElementById('size-width');
    var hInput = document.getElementById('size-height');
    
    if (!wInput || !hInput) return null;
    
    var w = parseInt(wInput.value) || 100;
    var h = parseInt(hInput.value) || 100;
    
    // Validasi ukuran
    if (w < 10 || h < 10) {
        showNotification('Ukuran minimal 10×10 cm', 'warning');
        return null;
    }
    if (w > 500 || h > 500) {
        showNotification('Ukuran maksimal 500×500 cm', 'warning');
        return null;
    }
    
    var m2 = (w * h) / 10000;
    var total = Math.round(m2 * pricePerM2);
    
    // Update display
    var m2Display = document.getElementById('m2-display');
    var calcPrice = document.getElementById('calculated-price');
    var displayPrice = document.getElementById('display-price');
    
    if (m2Display) m2Display.textContent = m2.toFixed(2);
    if (calcPrice) calcPrice.textContent = formatRupiah(total);
    if (displayPrice) displayPrice.textContent = formatRupiah(total);
    
    return { width: w, height: h, m2: m2, total: total };
}

/**
 * Ubah quantity di keranjang
 */
function updateCartQty(key, delta) {
    var cart = getCart();
    var item = cart.find(function(i) {
        return getItemKey(i) === key;
    });
    if (!item) return;
    
    item.qty = Math.max(1, Math.min(999, item.qty + delta));
    saveCart(cart);
    renderCart();
}

/**
 * Hapus item dari keranjang
 */
function removeFromCart(key) {
    if (!confirmAction('Hapus item ini dari keranjang?')) return;
    
    var cart = getCart().filter(function(item) {
        return getItemKey(item) !== key;
    });
    saveCart(cart);
    renderCart();
}

/**
 * Kosongkan keranjang
 */
function clearCart() {
    if (!confirmAction('Yakin ingin mengosongkan keranjang?')) return;
    saveCart([]);
    renderCart();
    updateCartBadge();
    showNotification('Keranjang berhasil dikosongkan!');
}

/**
 * Render keranjang
 */
function renderCart() {
    var container = document.getElementById('cart-contents');
    var checkoutBtn = document.getElementById('checkoutBtn') || document.getElementById('checkout-btn');
    var summary = document.getElementById('cart-summary');
    if (!container) return;
    
    var cart = getCart();
    
    if (cart.length === 0) {
        if (summary) summary.style.display = 'none';
        container.innerHTML = '<div class="empty-cart"><p style="text-align:center;padding:40px;color:#999;">🛒 Keranjang belanja kosong</p></div>';
        if (checkoutBtn) checkoutBtn.style.display = 'none';
        return;
    }
    
    var html = '<div class="cart-items">';
    var totalItems = 0;
    var total = 0;
    
    cart.forEach(function(item) {
        var subtotal = item.price * item.qty;
        totalItems += item.qty;
        total += subtotal;
        var dimLabel = item.customSize ? ' <span style="font-size:12px;color:#666;">(' + (item.label || '') + ')</span>' : '';
        var dsLabel = '';
        if (item.designService === 'jasa') {
            dsLabel = ' <span style="font-size:12px;color:#e67e22;">🎨 +Jasa Desain</span>';
        } else if (item.designService === 'upload') {
            dsLabel = item.designFile ? ' <span style="font-size:12px;color:#27ae60;">✅ File terupload</span>' : ' <span style="font-size:12px;color:#f39c12;">📎 File Desain (upload di checkout)</span>';
        }
        var itemKey = getItemKey(item);
        
        html += `
            <div class="cart-item">
                <div style="flex:1;">
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <button class="btn btn-sm btn-outline" onclick="updateCartQty('${itemKey}', -1)">−</button>
                        <span style="font-weight:600;min-width:30px;text-align:center;">${item.qty}</span>
                        <button class="btn btn-sm btn-outline" onclick="updateCartQty('${itemKey}', 1)">+</button>
                    </div>
                </div>
                <div class="cart-item-info">
                    <div class="cart-item-name">${item.name}${dimLabel}${dsLabel}</div>
                    <div class="cart-item-price">${formatRupiah(item.price)} × ${item.qty}</div>
                </div>
                <div class="cart-item-total">${formatRupiah(subtotal)}</div>
                <button class="cart-item-remove" onclick="removeFromCart('${itemKey}')">×</button>
            </div>
        `;
    });
    
    html += '</div>';
    
    if (!summary) {
        html += `
            <div class="cart-summary">
                <div>Total: <span class="total-text">${formatRupiah(total)}</span></div>
            </div>
        `;
    }
    
    container.innerHTML = html;
    if (summary) {
        summary.style.display = 'block';
        var totalItemsEl = document.getElementById('totalItems');
        var totalPriceEl = document.getElementById('totalPrice');
        if (totalItemsEl) totalItemsEl.textContent = totalItems;
        if (totalPriceEl) totalPriceEl.textContent = formatRupiah(total);
    }
    if (checkoutBtn) checkoutBtn.style.display = 'inline-block';
}

// ============================================
// CHECKOUT FUNCTIONS
// ============================================

/**
 * Render ringkasan checkout
 */
function renderCheckoutSummary() {
    var container = document.getElementById('order-summary');
    var checkoutEmpty = document.getElementById('checkout-empty');
    var form = document.getElementById('checkout-form');
    if (!container) return;
    
    var cart = getCart();
    
    if (cart.length === 0) {
        if (checkoutEmpty) checkoutEmpty.style.display = 'block';
        if (form) form.style.display = 'none';
        return;
    }
    if (form) form.style.display = 'grid';
    
    var html = '';
    var total = 0;
    
    cart.forEach(function(item) {
        var subtotal = item.price * item.qty;
        total += subtotal;
        var dim = item.customSize && item.label ? ' (' + item.label + ')' : '';
        var ds = item.designService === 'jasa' ? ' +Jasa Desain' : item.designService === 'upload' ? ' +File Desain' : '';
        
        html += `
            <div class="summary-item">
                <span>${item.name}${dim}${ds} × ${item.qty}</span>
                <span>${formatRupiah(subtotal)}</span>
            </div>
        `;
    });
    
    html += `<div class="summary-total"><span>Total</span><span>${formatRupiah(total)}</span></div>`;
    container.innerHTML = html;
}

/**
 * Submit Order
 */
async function submitOrder(event) {
    event.preventDefault();
    var form = document.getElementById('checkout-form');
    if (!form) return;
    
    var formData = new FormData(form);
    var items = getCart();
    
    if (items.length === 0) {
        showNotification('Keranjang kosong!', 'error');
        return;
    }
    
    var data = {
        name: formData.get('name'),
        phone: formData.get('phone'),
        address: formData.get('address'),
        notes: formData.get('notes'),
        payment_method: formData.get('payment_method'),
        items: items.map(function(item) {
            return {
                id: item.id,
                qty: item.qty,
                width: item.width || 0,
                height: item.height || 0,
                customSize: item.customSize || false,
                material: item.material || '',
                matPrice: item.pricePerM2 || 0,
                designService: item.designService || '',
                designFile: item.designFile || '',
                designOriginalName: item.designOriginalName || ''
            };
        })
    };
    
    // 🔥 Validasi COD dengan Jasa Desain
    if (data.payment_method === 'cod' && items.some(function(i) { return i.designService === 'jasa'; })) {
        showNotification('Pesanan dengan Jasa Desain tidak bisa menggunakan COD. Silakan pilih Transfer Bank / QRIS.', 'error');
        return;
    }
    
    // 🔥 Validasi nomor WhatsApp
    if (!validatePhone(data.phone)) {
        showNotification('Format nomor WhatsApp tidak valid! Gunakan 08123456789 atau 628123456789.', 'error');
        return;
    }
    
    var btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.textContent = '⏳ Memproses...';
    
    try {
        var response = await fetch('api-order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        var result = await response.json();
        
        if (result.success) {
            localStorage.removeItem('cart');
            // Update badge
            updateCartBadge();
            window.location.href = 'order-success.php?order=' + encodeURIComponent(result.order_code);
        } else {
            showNotification(result.message || 'Gagal memproses pesanan', 'error');
            btn.disabled = false;
            btn.textContent = 'Buat Pesanan';
        }
    } catch (err) {
        showNotification('Terjadi kesalahan, coba lagi', 'error');
        btn.disabled = false;
        btn.textContent = 'Buat Pesanan';
    }
}

// ============================================
// PAYMENT FUNCTIONS
// ============================================

/**
 * Payment method toggle
 */
function initPaymentToggle() {
    var paymentSelect = document.querySelector('select[name="payment_method"]');
    if (!paymentSelect) return;
    
    paymentSelect.addEventListener('change', function() {
        var bankInfo = document.getElementById('bank-info');
        var qrisInfo = document.getElementById('qris-info');
        var midtransInfo = document.getElementById('midtrans-info');
        var codInfo = document.getElementById('cod-info');
        
        if (bankInfo) bankInfo.style.display = 'none';
        if (qrisInfo) qrisInfo.style.display = 'none';
        if (midtransInfo) midtransInfo.style.display = 'none';
        if (codInfo) codInfo.style.display = 'none';
        
        if (this.value === 'transfer' && bankInfo) bankInfo.style.display = 'block';
        if (this.value === 'qris' && qrisInfo) qrisInfo.style.display = 'block';
        if (this.value === 'midtrans' && midtransInfo) midtransInfo.style.display = 'block';
        if (this.value === 'cod' && codInfo) codInfo.style.display = 'block';
    });
}

/**
 * Pay with Midtrans
 */
async function payMidtrans(orderCode) {
    var btn = document.querySelector('button[onclick*="' + orderCode + '"]');
    if (btn) {
        btn.disabled = true;
        btn.textContent = '⏳ Mengarahkan...';
    }
    
    try {
        var response = await fetch('/payment/create.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_code: orderCode })
        });
        var result = await response.json();
        
        if (result.success && result.redirect_url) {
            window.location.href = result.redirect_url;
        } else {
            var statusDiv = document.getElementById('midtrans-payment-status');
            if (statusDiv) {
                statusDiv.innerHTML = '<div class="alert alert-error">❌ ' + (result.message || 'Gagal memproses pembayaran') + '</div>';
            }
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Bayar Sekarang';
            }
        }
    } catch (err) {
        var statusDiv = document.getElementById('midtrans-payment-status');
        if (statusDiv) {
            statusDiv.innerHTML = '<div class="alert alert-error">❌ Terjadi kesalahan, coba lagi</div>';
        }
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Bayar Sekarang';
        }
    }
}

/**
 * 🔥 Kirim Link WhatsApp
 */
function sendWhatsAppLink(phone, message) {
    var cleanPhone = phone.replace(/[^0-9]/g, '');
    if (cleanPhone.startsWith('0')) {
        cleanPhone = '62' + cleanPhone.substring(1);
    }
    var encodedMessage = encodeURIComponent(message);
    var url = 'https://wa.me/' + cleanPhone + '?text=' + encodedMessage;
    window.open(url, '_blank');
}

// ============================================
// PRODUCT FUNCTIONS
// ============================================

/**
 * Change quantity
 */
function changeQty(delta) {
    var input = document.getElementById('qty-input');
    if (!input) return;
    var val = parseInt(input.value) || 1;
    val = Math.max(1, Math.min(999, val + delta));
    input.value = val;
}

/**
 * Update material price (untuk custom size)
 */
function updateMaterialPrice() {
    var select = document.getElementById('material-select');
    if (!select) return;
    
    var price = parseFloat(select.value) || 0;
    var priceDisplay = document.getElementById('material-price-display');
    if (priceDisplay) {
        priceDisplay.textContent = formatRupiah(price);
    }
    
    // Recalculate
    if (typeof calcBannerPrice === 'function') {
        calcBannerPrice(price);
    }
}

/**
 * 🔥 Upload file desain (preview)
 */
function previewDesignFile() {
    var input = document.getElementById('design-file');
    if (!input || !input.files.length) return;
    
    var file = input.files[0];
    var preview = document.getElementById('design-preview');
    if (!preview) return;
    
    var maxSize = 5 * 1024 * 1024; // 5MB
    if (file.size > maxSize) {
        showNotification('Ukuran file maksimal 5MB!', 'error');
        input.value = '';
        preview.innerHTML = '';
        return;
    }
    
    var allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    if (!allowedTypes.includes(file.type)) {
        showNotification('Format file tidak didukung! (JPG, PNG, GIF, PDF)', 'error');
        input.value = '';
        preview.innerHTML = '';
        return;
    }
    
    var sizeKB = (file.size / 1024).toFixed(1);
    var sizeLabel = sizeKB > 1024 ? (sizeKB / 1024).toFixed(1) + ' MB' : sizeKB + ' KB';
    
    if (file.type.startsWith('image/')) {
        var reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `
                <div style="margin-top:8px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <img src="${e.target.result}" style="max-width:150px;max-height:150px;border-radius:6px;border:1px solid #ddd;padding:4px;">
                    <span style="font-size:12px;color:#27ae60;">✅ ${file.name} (${sizeLabel})</span>
                </div>
            `;
        };
        reader.readAsDataURL(file);
    } else {
        preview.innerHTML = `
            <div style="margin-top:8px;display:flex;align-items:center;gap:10px;">
                <span style="font-size:24px;">📄</span>
                <span style="font-size:12px;color:#27ae60;">✅ ${file.name} (${sizeLabel})</span>
            </div>
        `;
    }
}

// ============================================
// 🔥 UPDATE CART BADGE WITH API FALLBACK
// ============================================

/**
 * Update cart badge menggunakan API dengan fallback
 */
function updateCartBadgeWithAPI() {
    var cartBadge = document.getElementById('cart-count');
    if (!cartBadge) return;
    
    // 🔥 Cek apakah user login via PHP (dari header)
    var isLoggedIn = typeof isUserLoggedIn === 'function' ? isUserLoggedIn() : false;
    
    if (!isLoggedIn) {
        cartBadge.textContent = '0';
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
            cartBadge.textContent = count;
        })
        .catch(function() {
            // 🔥 Fallback: tetap 0 (tidak error)
            cartBadge.textContent = '0';
        });
}

// ============================================
// INITIALIZATION
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    // 🔥 Update cart badge
    updateCartBadge();
    
    // 🔥 Render cart
    renderCart();
    
    // 🔥 Render checkout summary
    renderCheckoutSummary();
    
    // 🔥 Payment method toggle
    initPaymentToggle();
    
    // 🔥 Material price update
    var materialSelect = document.getElementById('material-select');
    if (materialSelect) {
        materialSelect.addEventListener('change', function() {
            var price = parseFloat(this.value) || 0;
            var priceDisplay = document.getElementById('material-price-display');
            if (priceDisplay) {
                priceDisplay.textContent = formatRupiah(price);
            }
            if (typeof calcBannerPrice === 'function') {
                calcBannerPrice(price);
            }
        });
    }
    
    // 🔥 Size input change
    var sizeInputs = document.querySelectorAll('#size-width, #size-height');
    sizeInputs.forEach(function(input) {
        input.addEventListener('input', function() {
            var select = document.getElementById('material-select');
            var price = select ? parseFloat(select.value) || 0 : 0;
            if (price > 0 && typeof calcBannerPrice === 'function') {
                calcBannerPrice(price);
            }
        });
    });
    
    // 🔥 Design file preview
    var designFile = document.getElementById('design-file');
    if (designFile) {
        designFile.addEventListener('change', previewDesignFile);
    }
});

// ============================================
// EXPOSE FUNCTIONS GLOBAL
// ============================================

window.formatRupiah = formatRupiah;
window.formatRupiahShort = formatRupiahShort;
window.formatDate = formatDate;
window.validatePhone = validatePhone;
window.showNotification = showNotification;
window.confirmAction = confirmAction;
window.toggleMenu = toggleMenu;
window.getCart = getCart;
window.saveCart = saveCart;
window.updateCartBadge = updateCartBadge;
window.getItemKey = getItemKey;

window.calcBannerPrice = calcBannerPrice;
window.updateCartQty = updateCartQty;
window.removeFromCart = removeFromCart;
window.clearCart = clearCart;
window.renderCart = renderCart;
window.renderCheckoutSummary = renderCheckoutSummary;
window.submitOrder = submitOrder;
window.payMidtrans = payMidtrans;
window.sendWhatsAppLink = sendWhatsAppLink;
window.changeQty = changeQty;
window.updateMaterialPrice = updateMaterialPrice;
window.previewDesignFile = previewDesignFile;
window.updateCartBadgeWithAPI = updateCartBadgeWithAPI;