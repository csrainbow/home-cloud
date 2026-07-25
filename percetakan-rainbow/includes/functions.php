<?php
// ============================================
// FUNCTIONS - Rainbow Printing
// ============================================

// 🔥 Inisialisasi Database
function initDatabase() {
    global $db;
    
    // 🔥 TABEL CUSTOMERS
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS customers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            phone TEXT NOT NULL,
            password TEXT NOT NULL,
            address TEXT DEFAULT '',
            remember_token TEXT,
            remember_expires DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (Exception $e) {
        error_log("Failed to create customers table: " . $e->getMessage());
    }

    // 🔥 TABEL PRODUCTS
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT UNIQUE NOT NULL,
            description TEXT,
            price REAL NOT NULL,
            category TEXT,
            image TEXT,
            stock INTEGER DEFAULT 0,
            custom_size INTEGER DEFAULT 0,
            price_per_m2 REAL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (Exception $e) {
        error_log("Failed to create products table: " . $e->getMessage());
    }

    // 🔥 TABEL CATEGORIES
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT UNIQUE NOT NULL,
            icon TEXT DEFAULT '📄'
        )");
    } catch (Exception $e) {
        error_log("Failed to create categories table: " . $e->getMessage());
    }

    // 🔥 TABEL PRODUCT_IMAGES
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS product_images (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            image TEXT NOT NULL,
            sort_order INTEGER DEFAULT 0,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        )");
    } catch (Exception $e) {
        error_log("Failed to create product_images table: " . $e->getMessage());
    }

    // 🔥 TABEL PRODUCT_MATERIALS
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS product_materials (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            price_per_m2 REAL NOT NULL,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        )");
    } catch (Exception $e) {
        error_log("Failed to create product_materials table: " . $e->getMessage());
    }

    // 🔥 TABEL ORDERS
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_code TEXT UNIQUE NOT NULL,
            customer_name TEXT NOT NULL,
            customer_phone TEXT NOT NULL,
            customer_address TEXT,
            notes TEXT,
            total REAL NOT NULL,
            status TEXT DEFAULT 'pending',
            payment_method TEXT,
            payment_status TEXT DEFAULT 'unpaid',
            customer_id INTEGER DEFAULT 0,
            payment_deadline DATETIME DEFAULT NULL,
            printer_type TEXT DEFAULT '',
            dimension_note TEXT DEFAULT '',
            midtrans_token TEXT DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (Exception $e) {
        error_log("Failed to create orders table: " . $e->getMessage());
    }

    // 🔥 TABEL ORDER_ITEMS
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            product_name TEXT NOT NULL,
            quantity INTEGER NOT NULL,
            price REAL NOT NULL,
            subtotal REAL NOT NULL,
            width REAL DEFAULT 0,
            height REAL DEFAULT 0,
            unit_label TEXT DEFAULT '',
            material_name TEXT DEFAULT '',
            material_price_per_m2 REAL DEFAULT 0,
            design_service TEXT DEFAULT '',
            design_file TEXT DEFAULT '',
            design_original_name TEXT DEFAULT '',
            design_result_file TEXT DEFAULT '',
            FOREIGN KEY (order_id) REFERENCES orders(id)
        )");
    } catch (Exception $e) {
        error_log("Failed to create order_items table: " . $e->getMessage());
    }

    // 🔥 TABEL PAYMENTS
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS payments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL,
            bank_name TEXT,
            account_number TEXT,
            account_name TEXT,
            amount REAL NOT NULL,
            proof_image TEXT,
            payment_type TEXT DEFAULT 'dp',
            status TEXT DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id)
        )");
    } catch (Exception $e) {
        error_log("Failed to create payments table: " . $e->getMessage());
    }

    // 🔥 TABEL SETTINGS
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT
        )");
    } catch (Exception $e) {
        error_log("Failed to create settings table: " . $e->getMessage());
    }

    // 🔥 TABEL CONTENT_PAGES
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS content_pages (
            slug TEXT PRIMARY KEY,
            title TEXT NOT NULL,
            content TEXT,
            meta_title VARCHAR(255),
            meta_description TEXT,
            meta_keywords VARCHAR(255),
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (Exception $e) {
        error_log("Failed to create content_pages table: " . $e->getMessage());
    }

    // 🔥 TABEL CART
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS cart (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_id INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            quantity INTEGER DEFAULT 1,
            width REAL DEFAULT 0,
            height REAL DEFAULT 0,
            material_name TEXT DEFAULT '',
            material_price REAL DEFAULT 0,
            design_service TEXT DEFAULT '',
            design_file TEXT DEFAULT '',
            design_original_name TEXT DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id),
            FOREIGN KEY (product_id) REFERENCES products(id)
        )");
    } catch (Exception $e) {
        error_log("Failed to create cart table: " . $e->getMessage());
    }

    // 🔥 TABEL LOGIN_ATTEMPTS (Rate Limiting)
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip_address VARCHAR(45) NOT NULL,
            username VARCHAR(100),
            attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (Exception $e) {
        error_log("Failed to create login_attempts table: " . $e->getMessage());
    }

    // 🔥 TABEL ADMIN_SESSIONS (Remember Me)
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS admin_sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            admin_id INTEGER NOT NULL,
            remember_token VARCHAR(64),
            expires_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (Exception $e) {
        error_log("Failed to create admin_sessions table: " . $e->getMessage());
    }

    // 🔥 TABEL ADMIN_LOGS
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS admin_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            admin_id INTEGER,
            username VARCHAR(100),
            action VARCHAR(50),
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (Exception $e) {
        error_log("Failed to create admin_logs table: " . $e->getMessage());
    }

    // 🔥 TABEL CUSTOMER_LOGS
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS customer_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_id INTEGER,
            action VARCHAR(50),
            ip_address VARCHAR(45),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (Exception $e) {
        error_log("Failed to create customer_logs table: " . $e->getMessage());
    }

    // 🔥 TABEL REGISTER_ATTEMPTS (Rate Limiting Registrasi)
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS register_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip_address VARCHAR(45) NOT NULL,
            attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (Exception $e) {
        error_log("Failed to create register_attempts table: " . $e->getMessage());
    }

    // 🔥 TABEL PRODUCT VARIANTS
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS product_variants (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            price REAL DEFAULT 0,
            is_active INTEGER DEFAULT 1,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        )");
    } catch (Exception $e) {
        error_log("Failed to create product_variants table: " . $e->getMessage());
    }

    // 🔥 🔥 ALTER TABLES - TAMBAHKAN KOLOM YANG MUNGKIN BELUM ADA 🔥 🔥
    $alterQueries = [
        "ALTER TABLE products ADD COLUMN custom_size INTEGER DEFAULT 0",
        "ALTER TABLE products ADD COLUMN price_per_m2 REAL DEFAULT 0",
        "ALTER TABLE products ADD COLUMN size_unit TEXT DEFAULT 'none'",
        "ALTER TABLE products ADD COLUMN updated_at DATETIME DEFAULT NULL",
        "ALTER TABLE order_items ADD COLUMN width REAL DEFAULT 0",
        "ALTER TABLE order_items ADD COLUMN height REAL DEFAULT 0",
        "ALTER TABLE order_items ADD COLUMN unit_label TEXT DEFAULT ''",
        "ALTER TABLE order_items ADD COLUMN material_name TEXT DEFAULT ''",
        "ALTER TABLE order_items ADD COLUMN material_price_per_m2 REAL DEFAULT 0",
        "ALTER TABLE order_items ADD COLUMN design_service TEXT DEFAULT ''",
        "ALTER TABLE order_items ADD COLUMN design_file TEXT DEFAULT ''",
        "ALTER TABLE order_items ADD COLUMN design_original_name TEXT DEFAULT ''",
        "ALTER TABLE order_items ADD COLUMN design_result_file TEXT DEFAULT ''",
        "ALTER TABLE order_items ADD COLUMN variants TEXT DEFAULT ''",
        "ALTER TABLE orders ADD COLUMN dimension_note TEXT DEFAULT ''",
        "ALTER TABLE orders ADD COLUMN customer_id INTEGER DEFAULT 0",
        "ALTER TABLE orders ADD COLUMN payment_deadline DATETIME DEFAULT NULL",
        "ALTER TABLE orders ADD COLUMN printer_type TEXT DEFAULT ''",
        "ALTER TABLE orders ADD COLUMN midtrans_token TEXT DEFAULT ''",
        "ALTER TABLE customers ADD COLUMN address TEXT DEFAULT ''",
        "ALTER TABLE customers ADD COLUMN remember_token TEXT DEFAULT ''",
        "ALTER TABLE customers ADD COLUMN remember_expires DATETIME DEFAULT NULL",
        "ALTER TABLE payments ADD COLUMN payment_type TEXT DEFAULT 'dp'",
        "ALTER TABLE content_pages ADD COLUMN meta_title VARCHAR(255)",
        "ALTER TABLE content_pages ADD COLUMN meta_description TEXT",
        "ALTER TABLE content_pages ADD COLUMN meta_keywords VARCHAR(255)",
        "ALTER TABLE content_pages ADD COLUMN is_active INTEGER DEFAULT 1",
    ];

    foreach ($alterQueries as $sql) {
        try {
            $db->exec($sql);
        } catch (Exception $e) {
            // Kolom sudah ada - abaikan
        }
    }

    // 🔥 SEED DATA
    try {
        $count = $db->query("SELECT COUNT(*) as c FROM products")->fetch()['c'];
        if ($count == 0) {
            seedProducts($db);
        }
    } catch (Exception $e) {
        error_log("Failed to seed products: " . $e->getMessage());
    }

    try {
        $countPages = $db->query("SELECT COUNT(*) as c FROM content_pages")->fetch()['c'];
        if ($countPages == 0) {
            seedContentPages($db);
        }
    } catch (Exception $e) {
        error_log("Failed to seed content pages: " . $e->getMessage());
    }

    try {
        $countCats = $db->query("SELECT COUNT(*) as c FROM categories")->fetch()['c'];
        if ($countCats == 0) {
            seedCategories($db);
        }
    } catch (Exception $e) {
        error_log("Failed to seed categories: " . $e->getMessage());
    }

    try {
        $countSettings = $db->query("SELECT COUNT(*) as c FROM settings")->fetch()['c'];
        if ($countSettings == 0) {
            seedSettings($db);
        }
    } catch (Exception $e) {
        error_log("Failed to seed settings: " . $e->getMessage());
    }
}

// ============================================
// 🔥 SEED FUNCTIONS
// ============================================

if (!function_exists('seedProducts')) {
function seedProducts($db) {
    $products = [
        ['name' => 'Cetak Brosur A4', 'slug' => 'cetak-brosur-a4', 'description' => 'Brosur A4 full color, kertas art paper 150gr. Minimum order 100 lembar.', 'price' => 1500, 'category' => 'Brosur', 'stock' => 9999],
        ['name' => 'Cetak Kartu Nama', 'slug' => 'cetak-kartu-nama', 'description' => 'Kartu nama art carton 310gr, laminasi doff/glossy. 1 box = 100 lembar.', 'price' => 35000, 'category' => 'Kartu Nama', 'stock' => 9999],
        ['name' => 'Cetak Banner Custom', 'slug' => 'cetak-banner-custom', 'description' => 'Banner flexi 280gr, finishing ring/plat. Pesan sesuai ukuran yang diinginkan. Harga per meter persegi.', 'price' => 35000, 'category' => 'Banner', 'stock' => 9999, 'custom_size' => 1, 'size_unit' => 'm2', 'price_per_m2' => 35000],
        ['name' => 'Cetak Sticker Chromo', 'slug' => 'cetak-sticker-chromo', 'description' => 'Sticker chromo laminated, ukuran A3. Tahan air.', 'price' => 12000, 'category' => 'Sticker', 'stock' => 9999],
        ['name' => 'Cetak Undangan', 'slug' => 'cetak-undangan', 'description' => 'Undangan A5, art paper 260gr, full color 2 sisi. Min 50 pcs.', 'price' => 5000, 'category' => 'Undangan', 'stock' => 9999],
        ['name' => 'Cetak Foto 4R', 'slug' => 'cetak-foto-4r', 'description' => 'Foto 4R (10x15cm) kertas glossy, hasil lab quality.', 'price' => 3000, 'category' => 'Foto', 'stock' => 9999],
        ['name' => 'Desain Grafis', 'slug' => 'desain-grafis', 'description' => 'Jasa desain logo, brosur, banner, dan lainnya. Konsultasi gratis.', 'price' => 100000, 'category' => 'Desain', 'stock' => 9999],
        ['name' => 'Cetak Kalender Meja', 'slug' => 'cetak-kalender-meja', 'description' => 'Kalender meja 2024, kertas art 260gr, spiral binding.', 'price' => 25000, 'category' => 'Kalender', 'stock' => 9999],
    ];

    $stmt = $db->prepare("INSERT INTO products (name, slug, description, price, category, stock, custom_size, size_unit, price_per_m2) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($products as $p) {
        $stmt->execute([$p['name'], $p['slug'], $p['description'], $p['price'], $p['category'], $p['stock'], $p['custom_size'] ?? 0, $p['size_unit'] ?? 'none', $p['price_per_m2'] ?? 0]);
    }

    // 🔥 Materials untuk Banner
    $bannerId = $db->query("SELECT id FROM products WHERE slug='cetak-banner-custom' LIMIT 1")->fetch();
    if ($bannerId) {
        $materials = [
            ['name' => 'Flexi 280gr (Standar)', 'price' => 35000],
            ['name' => 'Flexi 340gr (Tebal)', 'price' => 45000],
            ['name' => 'Luster 260gr (Mewah)', 'price' => 55000],
            ['name' => 'Albatex (Indoor)', 'price' => 40000],
            ['name' => 'Canvas', 'price' => 75000],
        ];
        $stmt = $db->prepare("INSERT INTO product_materials (product_id, name, price_per_m2) VALUES (?, ?, ?)");
        foreach ($materials as $m) {
            $stmt->execute([$bannerId['id'], $m['name'], $m['price']]);
        }
    }
}
}

if (!function_exists('seedContentPages')) {
function seedContentPages($db) {
    $pages = [
        ['slug' => 'tentang-kami', 'title' => 'Tentang Kami', 'content' => '
<h2>Selamat Datang di Percetakan Rainbow</h2>
<p>Percetakan Rainbow adalah percetakan kecil yang berkomitmen memberikan solusi cetak dan desain grafis berkualitas untuk UMKM, komunitas, dan masyarakat umum. Kami percaya setiap usaha berhak tampil profesional dengan desain dan cetakan yang menarik.</p>

<h3>🔥 Konsep Desain yang Menarik Perhatian Publik</h3>
<p>Di era digital, <strong>desain adalah senjata utama</strong> untuk merebut perhatian. Konsep desain kami bertumpu pada tiga pilar:</p>
<ul>
    <li><strong>Warna Berani & Kontras Tinggi</strong> — kombinasi warna mencolok yang bikin produk, banner, atau brosur Anda langsung kebaca dari jauh.</li>
    <li><strong>Hierarki Visual yang Tajam</strong> — informasi penting ditaruh di posisi paling strategis, pesan Anda sampai dalam hitungan detik.</li>
    <li><strong>Konteks Lokal, Tampilan Modern</strong> — desain yang relevan dengan budaya sekitar tetapi tetap mengikuti tren visual kekinian.</li>
</ul>
<p>Dari banner pinggir jalan sampai feed Instagram—kami bikin dagangan Anda <strong>dilihat, diingat, dan dipilih</strong>.</p>

<h3>🎨 Layanan Desain Grafis</h3>
<p>Bingung mau bikin desain sendiri? Tenang, kami siap bantuin. Layanan desain kami meliputi:</p>
<ul>
    <li>Desain logo dan identitas merek (branding)</li>
    <li>Desain brosur, flyer, leaflet, dan poster</li>
    <li>Desain banner, spanduk, dan backdrop</li>
    <li>Desain konten media sosial (feed & story Instagram, Facebook)</li>
    <li>Desain kemasan produk (label, box, paperbag)</li>
    <li>Edit foto dan manipulasi gambar dasar</li>
</ul>

<h3>🖨️ Layanan Cetak</h3>
<ul>
    <li>Cetak brosur, kartu nama, undangan, dan kalender</li>
    <li>Cetak banner flexi, luster, canvas, dan albatex</li>
    <li>Cetak sticker chromo, vinyl, dan cutting sticker</li>
    <li>Cetak foto ukuran 4R, 5R, 8R, dan polaroid</li>
    <li>Cetak custom ukuran, bahan, dan finishing</li>
</ul>

<h3>🏪 Kenapa Pilih Percetakan Rainbow?</h3>
<ul>
    <li><strong>Harga UMKM Pas di Kantong</strong> — kualitas tidak harus mahal. Kami kasih harga yang ramah buat usaha kecil dan menengah.</li>
    <li><strong>Bisa Custom Sesuai Mau</strong> — ukuran, bahan, desain, semuanya bisa diatur sesuai kebutuhan Anda.</li>
    <li><strong>Proses Cepat & Komunikatif</strong> — dari desain sampai cetak, kami update terus. Ada yang kurang? Tinggal bilang, kami revisi.</li>
    <li><strong>Online & Offline</strong> — pesan lewat website, konsultasi lewat WhatsApp, atau langsung datang ke tempat kami.</li>
</ul>

<h3>📍 Lokasi</h3>
<p>Kami berbasis di Kota Anda, siap melayani cetak dan desain untuk wilayah sekitar maupun pengiriman ke seluruh Indonesia.</p>

<p><em>Percetakan Rainbow — Cetak Profesional, Harga UMKM.</em></p>'],
        ['slug' => 'privacy-policy', 'title' => 'Kebijakan Privasi', 'content' => '<h2>Kebijakan Privasi</h2><p>Kami menghargai privasi Anda. Data pribadi Anda aman dan tidak akan disebarluaskan.</p>'],
        ['slug' => 'terms-of-service', 'title' => 'Syarat & Ketentuan', 'content' => '<h2>Syarat & Ketentuan</h2><p>Dengan menggunakan layanan kami, Anda menyetujui syarat dan ketentuan yang berlaku.</p>'],
        ['slug' => 'faq', 'title' => 'FAQ', 'content' => '<h2>Pertanyaan yang Sering Diajukan</h2><p><strong>Q: Berapa lama proses cetak?</strong><br>A: Proses cetak memakan waktu 1-3 hari kerja.</p>'],
        ['slug' => 'contact', 'title' => 'Kontak', 'content' => '<h2>Hubungi Kami</h2><p>Silakan hubungi kami melalui WhatsApp atau email.</p>'],
    ];

    $stmt = $db->prepare("INSERT OR IGNORE INTO content_pages (slug, title, content) VALUES (?, ?, ?)");
    foreach ($pages as $p) {
        $stmt->execute([$p['slug'], $p['title'], $p['content']]);
    }
}
}

if (!function_exists('seedCategories')) {
function seedCategories($db) {
    $categories = [
        ['name' => 'Brosur', 'icon' => '📄'],
        ['name' => 'Kartu Nama', 'icon' => '🪪'],
        ['name' => 'Banner', 'icon' => '🖼️'],
        ['name' => 'Sticker', 'icon' => '🏷️'],
        ['name' => 'Undangan', 'icon' => '💌'],
        ['name' => 'Foto', 'icon' => '📸'],
        ['name' => 'Desain', 'icon' => '🎨'],
        ['name' => 'Kalender', 'icon' => '📅'],
        ['name' => 'Lainnya', 'icon' => '📦'],
    ];

    $stmt = $db->prepare("INSERT OR IGNORE INTO categories (name, icon) VALUES (?, ?)");
    foreach ($categories as $c) {
        $stmt->execute([$c['name'], $c['icon']]);
    }
}
}

if (!function_exists('seedSettings')) {
function seedSettings($db) {
    $settings = [
        ['key' => 'bank1_name', 'value' => 'BRI'],
        ['key' => 'bank1_account', 'value' => '1234567890'],
        ['key' => 'bank1_name_holder', 'value' => 'Rainbow Printing'],
        ['key' => 'bank2_name', 'value' => 'BCA'],
        ['key' => 'bank2_account', 'value' => '0987654321'],
        ['key' => 'bank2_name_holder', 'value' => 'Rainbow Printing'],
        ['key' => 'bank3_name', 'value' => 'Mandiri'],
        ['key' => 'bank3_account', 'value' => '5555555555'],
        ['key' => 'bank3_name_holder', 'value' => 'Rainbow Printing'],
        ['key' => 'midtrans_server_key', 'value' => ''],
        ['key' => 'midtrans_client_key', 'value' => ''],
        ['key' => 'store_name', 'value' => 'Rainbow Printing'],
        ['key' => 'store_address', 'value' => 'Jl. Contoh No. 123, Samarinda'],
        ['key' => 'store_phone', 'value' => '081234567890'],
        ['key' => 'whatsapp_number', 'value' => '6281234567890'],
        ['key' => 'admin_email', 'value' => 'admin@rainbowprinting.com'],
        ['key' => 'qris_name', 'value' => ''],
        ['key' => 'qris_merchant_id', 'value' => ''],
        ['key' => 'qris_image', 'value' => ''],
        ['key' => 'sendgrid_api_key', 'value' => ''],
        ['key' => 'invoice_template', 'value' => 'classic'],
        ['key' => 'invoice_footer', 'value' => 'Terima kasih telah berbelanja di Rainbow Printing'],
        ['key' => 'printer_options', 'value' => 'In-Fus/Solvent,Digital Printing,Offset,UV Printer,Sablon'],
        ['key' => 'footer_text', 'value' => 'Percetakan online terpercaya di Samarinda'],
        ['key' => 'social_facebook', 'value' => ''],
        ['key' => 'social_instagram', 'value' => ''],
        ['key' => 'social_youtube', 'value' => ''],
        ['key' => 'social_twitter', 'value' => ''],
    ];

    $stmt = $db->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)");
    foreach ($settings as $s) {
        $stmt->execute([$s['key'], $s['value']]);
    }
}
}

// ============================================
// 🔥 FUNGSI LAINNYA (FORMAT, GET SETTING, DLL)
// ============================================

if (!function_exists('formatRupiah')) {
    function formatRupiah($amount) {
        $amount = is_numeric($amount) ? $amount : 0;
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}

if (!function_exists('getSetting')) {
    function getSetting($key) {
        global $db;
        try {
            $stmt = $db->prepare("SELECT value FROM settings WHERE key = ?");
            $stmt->execute([$key]);
            $row = $stmt->fetch();
            return $row ? $row['value'] : null;
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('sendEmail')) {
    function sendEmail($to, $subject, $message, $contentType = 'text/plain') {
        $apiKey = getSetting('sendgrid_api_key');
        if ($apiKey) {
            $fromEmail = 'admin@rainbowprinting.web.id';
            $data = [
                'personalizations' => [['to' => [['email' => $to]]]],
                'from' => ['email' => $fromEmail, 'name' => 'Rainbow Printing'],
                'subject' => $subject,
                'content' => [['type' => $contentType, 'value' => $message]],
            ];
            $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
            curl_setopt_array($ch, [
                CURLOPT_POST => 1,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return $httpCode >= 200 && $httpCode < 300;
        }
        $headers = "From: admin@rainbowprinting.web.id\r\n";
        $headers .= "Reply-To: admin@rainbowprinting.web.id\r\n";
        $headers .= "Content-Type: $contentType; charset=UTF-8\r\n";
        return @mail($to, $subject, $message, $headers);
    }
}

if (!function_exists('getCategories')) {
    function getCategories() {
        global $db;
        return $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
    }
}

if (!function_exists('ensureCategory')) {
    function ensureCategory($name) {
        global $db;
        $stmt = $db->prepare("INSERT OR IGNORE INTO categories (name) VALUES (?)");
        $stmt->execute([$name]);
    }
}

if (!function_exists('getFirstProductImage')) {
    function getFirstProductImage($productId) {
        $images = getProductImages($productId);
        return !empty($images) ? $images[0]['image'] : null;
    }
}

if (!function_exists('getProductImages')) {
    function getProductImages($productId) {
        global $db;
        $stmt = $db->prepare("SELECT * FROM product_images WHERE product_id=? ORDER BY sort_order, id");
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    }

    function getProductVariants($productId) {
        global $db;
        $stmt = $db->prepare("SELECT * FROM product_variants WHERE product_id=? AND is_active=1 ORDER BY id");
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    }
}

if (!function_exists('deleteProductImages')) {
    function deleteProductImages($productId) {
        global $db;
        $images = getProductImages($productId);
        foreach ($images as $img) {
            $path = __DIR__ . '/../uploads/' . $img['image'];
            if (file_exists($path)) unlink($path);
        }
        $db->prepare("DELETE FROM product_images WHERE product_id=?")->execute([$productId]);
    }
}

if (!function_exists('generateOrderCode')) {
    function generateOrderCode() {
        return 'INV/' . date('Ymd') . '/' . strtoupper(substr(uniqid(), -6));
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    }
}

if (!function_exists('redirect')) {
    function redirect($url) {
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('uploadImage')) {
    function uploadImage($file, $targetDir) {
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) return null;
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowed)) return null;
        $filename = uniqid() . '.' . $ext;
        $targetPath = $targetDir . '/' . $filename;
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return $filename;
        }
        return null;
    }
}
// TIDAK ADA SPASI ATAU KARAKTER SETELAH INI