<?php
require_once __DIR__ . '/config.php';

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header('Location: products.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM products WHERE slug = ?");
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) {
    header("HTTP/1.0 404 Not Found");
    $pageTitle = 'Produk Tidak Ditemukan';
    include 'includes/header.php';
    echo '<div style="text-align:center;padding:60px 20px;">';
    echo '<h1 style="font-size:48px;color:#2c3e50;">404</h1>';
    echo '<p style="color:#6c757d;">Produk yang Anda cari tidak ditemukan.</p>';
    echo '<a href="products.php" class="btn btn-primary">Kembali ke Produk</a>';
    echo '</div>';
    include 'includes/footer.php';
    exit;
}

$pageTitle = htmlspecialchars($product['name']) . ' - Rainbow Printing';

// 🔥 AMBIL PRODUK TERKAIT
$related = $db->prepare("SELECT * FROM products WHERE category = ? AND id != ? LIMIT 4");
$related->execute([$product['category'], $product['id']]);
$relatedProducts = $related->fetchAll();

// 🔥 AMBIL MATERIAL (jika custom size)
$materials = [];
if ($product['custom_size']) {
    $stmt = $db->prepare("SELECT * FROM product_materials WHERE product_id=? ORDER BY id");
    $stmt->execute([$product['id']]);
    $materials = $stmt->fetchAll();
}

// 🔥 AMBIL GAMBAR PRODUK
$productImages = getProductImages($product['id']);

// 🔥 AMBIL VARIAN
$variants = getProductVariants($product['id']);

// 🔥 ICON KATEGORI
$categoryIcons = [
    'Brosur' => '📄',
    'Kartu Nama' => '🪪',
    'Banner' => '🖼️',
    'Sticker' => '🏷️',
    'Undangan' => '💌',
    'Foto' => '📸',
    'Desain' => '🎨',
    'Kalender' => '📅'
];
$categoryIcon = $categoryIcons[$product['category']] ?? '📄';

include 'includes/header.php';
?>

<style>
/* ============================================
   PRODUCT DETAIL STYLES
   ============================================ */
.product-detail {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-bottom: 40px;
}

@media (max-width: 768px) {
    .product-detail {
        grid-template-columns: 1fr;
        gap: 20px;
    }
}

/* 🔥 GALLERY */
.product-gallery {
    position: relative;
}
.slider-container {
    position: relative;
    overflow: hidden;
    border-radius: 10px;
    background: #f8f9fa;
}
.slider-track {
    display: flex;
    transition: transform 0.4s ease;
}
.slider-slide {
    min-width: 100%;
}
.slider-slide img {
    width: 100%;
    height: 350px;
    object-fit: contain;
    display: block;
    background: #fff;
}
@media (max-width: 480px) {
    .slider-slide img {
        height: 250px;
    }
}
.slider-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0,0,0,0.5);
    color: #fff;
    border: none;
    width: 40px;
    height: 50px;
    font-size: 24px;
    cursor: pointer;
    transition: background 0.3s;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: center;
}
.slider-btn:hover {
    background: rgba(0,0,0,0.8);
}
.slider-prev { left: 0; border-radius: 0 6px 6px 0; }
.slider-next { right: 0; border-radius: 6px 0 0 6px; }
.slider-dots {
    position: absolute;
    bottom: 12px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 8px;
    z-index: 10;
}
.slider-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: rgba(255,255,255,0.5);
    cursor: pointer;
    transition: all 0.3s;
}
.slider-dot.active {
    background: #fff;
    transform: scale(1.2);
}
.slider-thumbs {
    display: flex;
    gap: 6px;
    margin-top: 8px;
    flex-wrap: wrap;
}
.slider-thumb {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 6px;
    cursor: pointer;
    border: 2px solid transparent;
    transition: all 0.3s;
    opacity: 0.6;
}
.slider-thumb.active,
.slider-thumb:hover {
    opacity: 1;
    border-color: #f39c12;
}

/* 🔥 PRODUCT INFO */
.product-detail-info {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.product-detail-info .product-category {
    font-size: 13px;
    color: #999;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.product-detail-info h1 {
    font-size: 26px;
    color: #2c3e50;
    margin: 0;
}
.product-detail-info .product-price {
    font-size: 24px;
    font-weight: 700;
    color: #2c3e50;
}
.product-detail-info .product-description {
    color: #555;
    line-height: 1.8;
    font-size: 14px;
}
.custom-size-inputs {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}
.custom-size-inputs label {
    font-weight: 600;
    display: block;
    margin-bottom: 5px;
    font-size: 14px;
    color: #2c3e50;
}
.custom-size-inputs select,
.custom-size-inputs input {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
}
.custom-size-inputs select {
    width: 100%;
}
.custom-size-inputs input[type="number"] {
    width: 100px;
}
#size-total {
    font-size: 14px;
    color: #555;
    margin-top: 10px;
}
#size-total strong {
    color: #2c3e50;
}

/* 🔥 DESIGN SERVICE */
.design-service-options {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}
.design-service-options label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-weight: normal;
    font-size: 14px;
}
.design-service-options input[type="radio"] {
    width: 16px;
    height: 16px;
    accent-color: #f39c12;
}
#design-upload-area {
    margin: 8px 0 8px 28px;
    display: none;
}
#design-upload-area input[type="file"] {
    font-size: 13px;
    padding: 6px;
}
#design-file-status {
    font-size: 12px;
    color: #6c757d;
    margin-top: 4px;
}

/* 🔥 QTY CONTROL */
.qty-control {
    display: flex;
    align-items: center;
    gap: 0;
}
.qty-control button {
    width: 40px;
    height: 40px;
    border: 1px solid #ddd;
    background: #f8f9fa;
    font-size: 18px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.qty-control button:hover {
    background: #2c3e50;
    color: #fff;
    border-color: #2c3e50;
}
.qty-control input {
    width: 50px;
    height: 40px;
    text-align: center;
    border: 1px solid #ddd;
    border-left: none;
    border-right: none;
    font-size: 16px;
    -moz-appearance: textfield;
}
.qty-control input::-webkit-inner-spin-button,
.qty-control input::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

/* 🔥 BUTTONS */
.btn {
    display: inline-block;
    padding: 10px 24px;
    border-radius: 6px;
    font-size: 14px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.3s;
}
.btn-primary {
    background: #2c3e50;
    color: #fff;
}
.btn-primary:hover {
    background: #1a252f;
    transform: translateY(-1px);
}
.btn-lg {
    padding: 12px 30px;
    font-size: 16px;
    width: 100%;
    text-align: center;
}

/* 🔥 RELATED */
.related {
    margin-top: 30px;
}
.related h2 {
    font-size: 20px;
    color: #2c3e50;
    margin-bottom: 15px;
}
.related .product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 15px;
}
.related .product-card {
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    border: 1px solid #e9ecef;
    transition: all 0.3s;
}
.related .product-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
    border-color: #f39c12;
}
.related .product-img-link {
    display: block;
    height: 150px;
    overflow: hidden;
    background: #f8f9fa;
}
.related .product-img {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    background-size: cover;
    background-position: center;
}
.related .product-info {
    padding: 12px 14px;
}
.related .product-info h3 {
    font-size: 13px;
    margin: 0 0 4px;
    line-height: 1.4;
    color: #2c3e50;
}
.related .product-info .product-price {
    font-size: 15px;
    font-weight: 700;
    color: #2c3e50;
}
.related .product-info .btn {
    display: block;
    width: 100%;
    text-align: center;
    font-size: 12px;
    padding: 6px;
    background: transparent;
    color: #2c3e50;
    border: 1px solid #2c3e50;
    border-radius: 4px;
}
.related .product-info .btn:hover {
    background: #2c3e50;
    color: #fff;
}

/* 🔥 NOTIFICATION TOAST */
.notif-toast {
    position: fixed;
    top: 15px;
    left: 50%;
    transform: translateX(-50%);
    background: #27ae60;
    color: #fff;
    padding: 12px 24px;
    border-radius: 8px;
    z-index: 99999;
    font-size: 14px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    text-align: center;
    max-width: 90%;
    animation: slideDown 0.3s ease;
}
@keyframes slideDown {
    from { transform: translateX(-50%) translateY(-20px); opacity: 0; }
    to { transform: translateX(-50%) translateY(0); opacity: 1; }
}

/* 🔥 RESPONSIVE */
@media (max-width: 480px) {
    .product-detail-info h1 {
        font-size: 20px;
    }
    .product-detail-info .product-price {
        font-size: 20px;
    }
    .custom-size-inputs input[type="number"] {
        width: 80px;
    }
    .slider-slide img {
        height: 200px;
    }
    .slider-thumb {
        width: 40px;
        height: 40px;
    }
    .related .product-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
}
</style>

<div class="product-detail">
    <!-- 🔥 GALLERY -->
    <div class="product-gallery">
        <?php if (!empty($productImages)): ?>
        <div class="slider-container">
            <div class="slider-track" id="slider-track">
                <?php foreach ($productImages as $pi): ?>
                <div class="slider-slide">
                    <img src="/uploads/<?= htmlspecialchars($pi['image'], ENT_QUOTES) ?>" 
                         alt="<?= htmlspecialchars($product['name'], ENT_QUOTES) ?>"
                         loading="lazy">
                </div>
                <?php endforeach; ?>
            </div>
            <?php if (count($productImages) > 1): ?>
            <button class="slider-btn slider-prev" onclick="slideGallery(-1)" aria-label="Previous">&#8249;</button>
            <button class="slider-btn slider-next" onclick="slideGallery(1)" aria-label="Next">&#8250;</button>
            <div class="slider-dots">
                <?php foreach ($productImages as $i => $pi): ?>
                <span class="slider-dot <?= $i === 0 ? 'active' : '' ?>" onclick="goToSlide(<?= $i ?>)"></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php if (count($productImages) > 1): ?>
        <div class="slider-thumbs">
            <?php foreach ($productImages as $i => $pi): ?>
            <img src="/uploads/<?= htmlspecialchars($pi['image'], ENT_QUOTES) ?>" 
                 class="slider-thumb <?= $i === 0 ? 'active' : '' ?>" 
                 onclick="goToSlide(<?= $i ?>)" 
                 alt="Thumbnail <?= $i+1 ?>"
                 loading="lazy">
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="slider-container" style="background:#e0e0e0;height:350px;display:flex;align-items:center;justify-content:center;font-size:80px;border-radius:10px;">
            <?= $categoryIcon ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- 🔥 INFO -->
    <div class="product-detail-info">
        <span class="product-category"><?= htmlspecialchars($product['category'], ENT_QUOTES) ?></span>
        <h1><?= htmlspecialchars($product['name'], ENT_QUOTES) ?></h1>
        <?php
        $sizeUnit = $product['size_unit'] ?? 'none';
        $unitLabels = ['none'=>'','m2'=>'/m²','meter'=>'/m','lembar'=>'/lembar','buku'=>'/buku','rim'=>'/rim','pcs'=>'/pcs'];
        $ul = $unitLabels[$sizeUnit] ?? '';
        ?>
        <p class="product-price" id="display-price">
            <?= $sizeUnit !== 'none' 
                ? formatRupiah($product['price_per_m2']) . ' ' . $ul
                : formatRupiah($product['price']) ?>
        </p>
        <p class="product-description"><?= nl2br(htmlspecialchars($product['description'], ENT_QUOTES)) ?></p>

        <form class="add-to-cart-form" method="POST" 
              data-id="<?= $product['id'] ?>" 
              data-name="<?= htmlspecialchars($product['name'], ENT_QUOTES) ?>" 
              data-price="<?= $product['price'] ?>" 
              data-custom="<?= $sizeUnit !== 'none' ? 1 : 0 ?>"
              data-size-unit="<?= $sizeUnit ?>">

            <?php if ($sizeUnit !== 'none'): ?>
            <div class="custom-size-inputs" id="size-inputs"
                 data-unit="<?= $sizeUnit ?>"
                 data-price-per-unit="<?= $product['price_per_m2'] ?>">
                <?php if (!empty($materials)): ?>
                <div style="margin-bottom:12px;">
                    <label for="material-select">Pilih Bahan:</label>
                    <select id="material-select" onchange="calcPrice()">
                        <?php foreach ($materials as $m): ?>
                        <option value="<?= $m['price_per_m2'] ?>" 
                                data-matname="<?= htmlspecialchars($m['name'], ENT_QUOTES) ?>"
                                data-matprice="<?= $m['price_per_m2'] ?>">
                            <?= htmlspecialchars($m['name'], ENT_QUOTES) ?> — <?= formatRupiah($m['price_per_m2']) ?><?= $ul ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <?php if ($sizeUnit === 'm2'): ?>
                <label>Ukuran (cm):</label>
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:6px;">
                    <div>
                        <label style="font-size:12px;display:block;">Lebar</label>
                        <input type="number" id="size-width" value="100" min="10" max="500" oninput="calcPrice()">
                    </div>
                    <span style="font-size:20px;margin-top:12px;">×</span>
                    <div>
                        <label style="font-size:12px;display:block;">Tinggi</label>
                        <input type="number" id="size-height" value="100" min="10" max="500" oninput="calcPrice()">
                    </div>
                    <span style="font-size:14px;margin-top:12px;color:#666;">cm</span>
                </div>
                <div id="size-total">
                    <span id="unit-price-label"><?= formatRupiah($product['price_per_m2']) ?></span><?= $ul ?> × 
                    <span id="unit-qty-display">1.00</span> m² = 
                    <strong id="calculated-price"><?= formatRupiah($product['price_per_m2']) ?></strong>
                </div>
                <?php elseif ($sizeUnit === 'meter'): ?>
                <label>Panjang (cm):</label>
                <div style="margin-top:6px;">
                    <input type="number" id="size-length" value="100" min="1" max="5000" oninput="calcPrice()" style="width:120px;">
                    <span style="font-size:14px;color:#666;margin-left:6px;">cm</span>
                </div>
                <div id="size-total">
                    <span id="unit-price-label"><?= formatRupiah($product['price_per_m2']) ?></span><?= $ul ?> × 
                    <span id="unit-qty-display">1.00</span> m = 
                    <strong id="calculated-price"><?= formatRupiah($product['price_per_m2']) ?></strong>
                </div>
                <?php else: ?>
                <!-- Lembar, Buku, Rim, Pcs — no size input, price per unit -->
                <p style="font-size:13px;color:#666;">Harga per <?= $sizeUnit ?>: <strong><?= formatRupiah($product['price_per_m2']) ?></strong></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- 🔥 VARIAN -->
            <?php if (!empty($variants)): ?>
            <div class="variant-options">
                <label style="font-weight:600;margin-bottom:8px;display:block;">Varian Tambahan:</label>
                <?php foreach ($variants as $v): ?>
                <div style="margin-bottom:4px;">
                    <label>
                        <input type="checkbox" class="variant-checkbox" value="<?= $v['id'] ?>" 
                               data-vname="<?= htmlspecialchars($v['name'], ENT_QUOTES) ?>"
                               data-vprice="<?= $v['price'] ?>" onchange="calcPrice()">
                        <?= htmlspecialchars($v['name']) ?> 
                        <span style="color:#e67e22;font-size:13px;">+ <?= formatRupiah($v['price']) ?></span>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- 🔥 DESIGN SERVICE -->
            <div class="design-service-options">
                <label style="font-weight:600;margin-bottom:8px;display:block;">Pilihan Jasa Desain:</label>
                <div style="margin-bottom:6px;">
                    <label>
                        <input type="radio" name="design_service" value="upload" onchange="toggleDesignService()">
                        Desain Sudah Jadi — Upload File (JPG/JPEG/PDF)
                    </label>
                    <div id="design-upload-area">
                        <input type="file" name="design_file" id="design-file" accept=".jpg,.jpeg,.pdf" 
                               style="font-size:13px;padding:6px;">
                        <div id="design-file-status" style="font-size:12px;color:#6c757d;margin-top:4px;"></div>
                    </div>
                </div>
                <div>
                    <label>
                        <input type="radio" name="design_service" value="jasa" onchange="toggleDesignService()">
                        Jasa Desain (+ Rp 25.000)
                    </label>
                </div>
            </div>

            <!-- 🔥 QUANTITY -->
            <div class="qty-control">
                <button type="button" onclick="qtyChange(-1)">−</button>
                <input type="number" name="qty" value="1" min="1" max="999" id="qty-input">
                <button type="button" onclick="qtyChange(1)">+</button>
            </div>

            <button type="submit" class="btn btn-primary btn-lg">🛒 Tambah ke Keranjang</button>
        </form>

        <!-- 🔥 INFO CEK PESANAN -->
        <div style="padding:12px;background:#f0f8ff;border:1px solid #d0e8f8;border-radius:8px;font-size:12px;color:#555;line-height:1.6;">
            <strong>📦 Cara Cek Pesanan:</strong><br>
            • <strong>Pesan langsung</strong> — nanti cek status via <a href="/cek-pesanan.php" style="text-decoration:underline;">kode pesanan</a><br>
            • <strong><a href="/register.php" style="text-decoration:underline;">Daftar akun</a></strong> — pantau semua pesanan dalam satu dashboard
        </div>
    </div>
</div>

<!-- 🔥 RELATED PRODUCTS -->
<?php if (!empty($relatedProducts)): ?>
<section class="related">
    <h2>💡 Produk Terkait</h2>
    <div class="product-grid">
        <?php foreach ($relatedProducts as $rp): 
            $rpImg = getFirstProductImage($rp['id']);
            $rpIcon = $categoryIcons[$rp['category']] ?? '📄';
        ?>
        <div class="product-card">
            <a href="product.php?slug=<?= urlencode($rp['slug']) ?>" class="product-img-link">
                <div class="product-img" style="<?= $rpImg ? 'background-image:url(/uploads/' . htmlspecialchars($rpImg, ENT_QUOTES) . ');' : '' ?>">
                    <?php if (!$rpImg): echo $rpIcon; endif; ?>
                </div>
            </a>
            <div class="product-info">
                <h3><?= htmlspecialchars($rp['name'], ENT_QUOTES) ?></h3>
                <p class="product-price"><?= formatRupiah($rp['price']) ?></p>
                <a href="product.php?slug=<?= urlencode($rp['slug']) ?>" class="btn">Detail</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<script>
/**
 * 🔥 PRODUCT PAGE SCRIPT
 */

// 🔥 VARIABLES
var currentSlide = 0;
var totalSlides = <?= count($productImages) ?>;

// 🔥 PRICE CALCULATION (with variants)
function getBasePrice() {
    <?php if ($sizeUnit !== 'none'): ?>
    var sel = document.getElementById('material-select');
    return sel ? parseFloat(sel.value) : parseFloat(document.getElementById('size-inputs').dataset.pricePerUnit);
    <?php else: ?>
    return <?= $product['price'] ?>;
    <?php endif; ?>
}

function getUnitQty() {
    <?php if ($sizeUnit === 'm2'): ?>
    var w = parseInt(document.getElementById('size-width').value) || 100;
    var h = parseInt(document.getElementById('size-height').value) || 100;
    return (w * h) / 10000;
    <?php elseif ($sizeUnit === 'meter'): ?>
    var len = parseInt(document.getElementById('size-length').value) || 100;
    return len / 100;
    <?php elseif ($sizeUnit !== 'none'): ?>
    return 1;
    <?php else: ?>
    return 1;
    <?php endif; ?>
}

function getVariantTotal() {
    var sum = 0;
    var cbs = document.querySelectorAll('.variant-checkbox:checked');
    cbs.forEach(function(cb) { sum += parseFloat(cb.dataset.vprice) || 0; });
    return sum;
}

function calcPrice() {
    var p = getBasePrice();
    var qty = getUnitQty();
    var base = Math.round(qty * p);
    var varTotal = getVariantTotal();
    var total = base + varTotal;
    
    <?php if ($sizeUnit !== 'none'): ?>
    var unit = document.getElementById('size-inputs').dataset.unit;
    var unitLabel = unit === 'meter' ? 'm' : (unit === 'm2' ? 'm\u00B2' : unit);
    var label = document.getElementById('unit-price-label');
    if (label) label.textContent = formatRupiah(p);
    var qtyDisplay = document.getElementById('unit-qty-display');
    if (qtyDisplay) qtyDisplay.textContent = qty.toFixed(2);
    var totalEl = document.getElementById('calculated-price');
    if (totalEl) totalEl.textContent = formatRupiah(base);
    <?php endif; ?>
    
    document.getElementById('display-price').textContent = formatRupiah(total);
}

function formatRupiah(amount) {
    return 'Rp ' + Number(amount).toLocaleString('id-ID');
}

// 🔥 INIT
document.addEventListener('DOMContentLoaded', function() {
    var sel = document.getElementById('material-select');
    if (sel) sel.addEventListener('change', calcPrice);
    var cbs = document.querySelectorAll('.variant-checkbox');
    cbs.forEach(function(cb) { cb.addEventListener('change', calcPrice); });
    calcPrice();
});

// 🔥 QUANTITY
function qtyChange(d) {
    var inp = document.getElementById('qty-input');
    var v = parseInt(inp.value) || 1;
    v = Math.max(1, Math.min(999, v + d));
    inp.value = v;
}

// 🔥 DESIGN SERVICE TOGGLE
function toggleDesignService() {
    var checked = document.querySelector('input[name="design_service"]:checked');
    var area = document.getElementById('design-upload-area');
    if (area) {
        area.style.display = (checked && checked.value === 'upload') ? 'block' : 'none';
    }
}

// 🔥 SLIDER
function goToSlide(n) {
    if (totalSlides <= 1) return;
    currentSlide = n;
    if (currentSlide < 0) currentSlide = totalSlides - 1;
    if (currentSlide >= totalSlides) currentSlide = 0;
    
    var track = document.getElementById('slider-track');
    if (track) {
        track.style.transform = 'translateX(-' + (currentSlide * 100) + '%)';
    }
    
    var dots = document.querySelectorAll('.slider-dot');
    var thumbs = document.querySelectorAll('.slider-thumb');
    dots.forEach(function(d, i) {
        d.classList.toggle('active', i === currentSlide);
    });
    thumbs.forEach(function(t, i) {
        t.classList.toggle('active', i === currentSlide);
    });
}

function slideGallery(d) {
    goToSlide(currentSlide + d);
}

// 🔥 TOUCH SUPPORT
(function() {
    var container = document.querySelector('.slider-container');
    if (!container || totalSlides <= 1) return;
    var startX = 0, endX = 0;
    container.addEventListener('touchstart', function(e) {
        startX = e.changedTouches[0].screenX;
    });
    container.addEventListener('touchend', function(e) {
        endX = e.changedTouches[0].screenX;
        if (Math.abs(startX - endX) > 50) {
            slideGallery(startX > endX ? 1 : -1);
        }
    });
})();

// 🔥 ADD TO CART
document.addEventListener('DOMContentLoaded', function() {
    var form = document.querySelector('.add-to-cart-form');
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        var id = parseInt(this.dataset.id);
        var name = this.dataset.name;
        var isCustom = parseInt(this.dataset.custom);
        var designInput = document.querySelector('input[name="design_service"]:checked');
        
        if (!designInput) {
            showNotification('Pilih jasa desain terlebih dahulu!', 'error');
            return;
        }
        
        var designService = designInput.value;
        var qty = parseInt(document.getElementById('qty-input').value) || 1;
        var price, width, height, matPrice, matName, label, key;
        
        // 🔥 Process price
        var sizeUnit = this.dataset.sizeUnit || 'none';
        if (isCustom) {
            matPrice = getBasePrice();
            if (sizeUnit === 'm2') {
                width = parseInt(document.getElementById('size-width').value) || 100;
                height = parseInt(document.getElementById('size-height').value) || 100;
                var m2 = (width * height) / 10000;
                price = Math.round(m2 * matPrice);
                label = width + '×' + height + ' cm';
                key = id + '-' + width + 'x' + height;
            } else if (sizeUnit === 'meter') {
                width = parseInt(document.getElementById('size-length').value) || 100;
                height = 0;
                price = Math.round((width / 100) * matPrice);
                label = width + ' cm';
                key = id + '-' + width + 'cm';
            } else {
                // lembar, buku, rim, pcs — price per unit
                width = 0; height = 0;
                price = matPrice;
                label = sizeUnit;
                key = id + '-' + sizeUnit;
            }
            var sel = document.getElementById('material-select');
            matName = sel ? sel.options[sel.selectedIndex].getAttribute('data-matname') || '' : '';
            if (matName) { label += ' (' + matName + ')'; key += '-' + matName; }
        } else {
            price = parseInt(this.dataset.price);
            width = 0; height = 0; matPrice = 0; matName = ''; label = '';
            key = String(id);
        }
        
        // 🔥 Add variant prices
        var variantList = [];
        var varCbs = document.querySelectorAll('.variant-checkbox:checked');
        varCbs.forEach(function(cb) {
            var vprice = parseFloat(cb.dataset.vprice) || 0;
            price += vprice;
            variantList.push({ id: parseInt(cb.value), name: cb.dataset.vname, price: vprice });
        });
        var variantJson = variantList.length ? JSON.stringify(variantList) : '';
        
        // 🔥 Add design service fee
        if (designService === 'jasa') {
            price += 25000;
        }
        
        // 🔥 Upload file jika ada, lalu add to cart
        if (designService === 'upload') {
            var fileInput = document.getElementById('design-file');
            if (fileInput && fileInput.files && fileInput.files.length > 0) {
                var btn = this.querySelector('button[type="submit"]');
                btn.disabled = true;
                btn.textContent = '⏳ Mengupload...';
                uploadDesignFile(fileInput.files[0], function(filename) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-cart-plus"></i> Tambah ke Keranjang';
                    addToCart(id, name, qty, price, isCustom, width, height, matPrice, matName, label, designService, filename, key, variantJson, sizeUnit);
                });
                return;
            }
        }
        
        addToCart(id, name, qty, price, isCustom, width, height, matPrice, matName, label, designService, '', key, variantJson, sizeUnit);
    });
});

function addToCart(id, name, qty, price, isCustom, width, height, matPrice, matName, label, designService, designFile, key, variantJson, szUnit) {
    var cart = JSON.parse(localStorage.getItem('cart') || '[]');
    var fullKey = key + (designService ? '-ds-' + designService : '');
    
    var found = false;
    for (var i = 0; i < cart.length; i++) {
        var k = cart[i]._key || String(cart[i].id);
        if (k === fullKey) {
            cart[i].qty += qty;
            found = true;
            break;
        }
    }
    
    if (!found) {
        var item = {
            id: id,
            name: name,
            qty: qty,
            price: price,
            designService: designService,
            designFile: designFile,
            sizeUnit: szUnit || 'none',
            customSize: isCustom,
            variants: variantJson,
            _key: fullKey
        };
        if (isCustom) {
            item.customSize = true;
            item.width = width;
            item.height = height;
            item.pricePerM2 = matPrice;
            item.material = matName;
            item.label = label;
        }
        cart.push(item);
    }
    
    localStorage.setItem('cart', JSON.stringify(cart));
    
    // 🔥 Update badge
    var total = cart.reduce(function(sum, item) { return sum + (item.qty || 0); }, 0);
    var badges = document.querySelectorAll('.cart-badge, #cart-count');
    badges.forEach(function(b) { b.textContent = total; });
    
    // 🔥 Show notification
    var msg = name;
    if (designService === 'jasa') msg += ' + Jasa Desain';
    if (designService === 'upload') msg += ' (dengan file desain)';
    msg += ' ditambahkan!';
    showNotification(msg, 'success');
}

function uploadDesignFile(file, callback) {
    var status = document.getElementById('design-file-status');
    status.textContent = '⏳ Mengupload...';
    status.style.color = '#f39c12';
    
    var formData = new FormData();
    formData.append('design_file', file);
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'upload-design.php', true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.success) {
                    status.textContent = '✅ File berhasil diupload';
                    status.style.color = '#27ae60';
                    callback(resp.filename);
                } else {
                    status.textContent = '❌ ' + (resp.message || 'Gagal upload');
                    status.style.color = '#e74c3c';
                }
            } catch (e) {
                status.textContent = '❌ Gagal upload file';
                status.style.color = '#e74c3c';
            }
        } else {
            status.textContent = '❌ Server error (' + xhr.status + ')';
            status.style.color = '#e74c3c';
        }
    };
    xhr.onerror = function() {
        status.textContent = '❌ Network error';
        status.style.color = '#e74c3c';
    };
    xhr.send(formData);
}

function showNotification(msg, type) {
    type = type || 'success';
    var old = document.querySelector('.notif-toast');
    if (old) old.remove();
    
    var div = document.createElement('div');
    div.className = 'notif-toast';
    div.style.backgroundColor = type === 'error' ? '#e74c3c' : '#27ae60';
    div.textContent = msg;
    document.body.appendChild(div);
    
    setTimeout(function() {
        div.style.opacity = '0';
        div.style.transition = 'opacity 0.3s';
        setTimeout(function() { div.remove(); }, 500);
    }, 2500);
}
</script>

<?php include 'includes/footer.php'; ?>