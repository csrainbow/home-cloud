<?php
require_once __DIR__ . '/config.php';

// 🔥 Jika sudah login, redirect ke dashboard
if (isset($_SESSION['customer_id'])) {
    header('Location: customer/dashboard.php');
    exit;
}

$pageTitle = 'Pesanan Saya - Rainbow Printing';
include 'includes/header.php';
?>

<style>
/* ============================================
   PESANAN SAYA STYLES
   ============================================ */
.pesanan-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 20px 0;
}
.pesanan-container h1 {
    font-size: 28px;
    color: #2c3e50;
    text-align: center;
    margin-bottom: 8px;
}
.pesanan-container .subtitle {
    text-align: center;
    color: #6c757d;
    font-size: 16px;
    margin-bottom: 30px;
}

/* 🔥 GRID */
.pilihan-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
}
.pilihan-card {
    background: #fff;
    border-radius: 12px;
    padding: 30px 25px;
    border: 2px solid #e9ecef;
    text-align: center;
    transition: all 0.3s;
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
}
.pilihan-card:hover {
    border-color: #f39c12;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    transform: translateY(-3px);
}
.pilihan-card .icon {
    font-size: 52px;
    margin-bottom: 15px;
    display: block;
}
.pilihan-card h2 {
    font-size: 20px;
    color: #2c3e50;
    margin-bottom: 10px;
}
.pilihan-card p {
    font-size: 14px;
    color: #6c757d;
    line-height: 1.6;
    margin-bottom: 6px;
}
.pilihan-card .highlight {
    color: #2c3e50;
    font-weight: 600;
}
.pilihan-card .btn-group {
    margin-top: 18px;
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
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
    box-shadow: 0 4px 12px rgba(44,62,80,0.3);
}
.btn-outline {
    background: #fff;
    color: #2c3e50;
    border: 1px solid #2c3e50;
}
.btn-outline:hover {
    background: #f8f9fa;
    transform: translateY(-1px);
}
.btn-success {
    background: #27ae60;
    color: #fff;
}
.btn-success:hover {
    background: #1e8449;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(39,174,96,0.3);
}

/* 🔥 FEATURES LIST */
.features-list {
    text-align: left;
    margin: 10px 0 15px;
    padding: 0;
    list-style: none;
}
.features-list li {
    font-size: 13px;
    color: #555;
    padding: 4px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.features-list li::before {
    content: '✅';
    font-size: 14px;
}

/* 🔥 RESPONSIVE */
@media (max-width: 600px) {
    .pilihan-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    .pesanan-container h1 {
        font-size: 22px;
    }
    .pilihan-card {
        padding: 25px 20px;
    }
    .pilihan-card .icon {
        font-size: 40px;
    }
    .btn-group {
        flex-direction: column;
        align-items: center;
    }
    .btn-group .btn {
        width: 100%;
        max-width: 200px;
    }
}

@media (max-width: 400px) {
    .pesanan-container {
        padding: 10px;
    }
    .pilihan-card {
        padding: 20px 15px;
    }
    .pilihan-card h2 {
        font-size: 18px;
    }
    .btn {
        padding: 8px 18px;
        font-size: 13px;
    }
}
</style>

<div class="pesanan-container">
    <h1>📋 Pesanan Saya</h1>
    <p class="subtitle">Pilih cara untuk melihat status pesanan kamu:</p>

    <div class="pilihan-grid">
        <!-- 🔥 CARD 1: CEK VIA KODE -->
        <div class="pilihan-card">
            <span class="icon">🔍</span>
            <h2>Cek via Kode Pesanan</h2>
            <p>Cocok untuk yang <span class="highlight">tidak ingin daftar akun</span>.</p>
            <p style="font-size:13px;color:#999;">
                Cukup masukkan kode pesanan dan nomor WhatsApp yang didaftarkan saat order.
            </p>
            <ul class="features-list">
                <li>Tanpa perlu registrasi</li>
                <li>Cek status pesanan</li>
                <li>Upload bukti pembayaran</li>
            </ul>
            <div class="btn-group">
                <a href="cek-pesanan.php" class="btn btn-primary">
                    <i class="fas fa-search"></i> Cek Pesanan
                </a>
            </div>
        </div>

        <!-- 🔥 CARD 2: LOGIN / DAFTAR -->
        <div class="pilihan-card">
            <span class="icon">👤</span>
            <h2>Masuk / Daftar Akun</h2>
            <p>Cocok untuk yang ingin <span class="highlight">pantau semua pesanan</span> dalam satu akun.</p>
            <p style="font-size:13px;color:#999;">
                Lihat histori pesanan, status desain, dan dapatkan notifikasi otomatis.
            </p>
            <ul class="features-list">
                <li>Lihat semua pesanan</li>
                <li>Download hasil desain</li>
                <li>Notifikasi status</li>
            </ul>
            <div class="btn-group">
                <a href="login.php" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Masuk
                </a>
                <a href="register.php" class="btn btn-success">
                    <i class="fas fa-user-plus"></i> Daftar
                </a>
            </div>
        </div>
    </div>

    <!-- 🔥 LINK KEMBALI -->
    <div style="text-align:center;margin-top:30px;">
        <a href="/" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Kembali ke Beranda
        </a>
    </div>
</div>

<script>
/**
 * 🔥 AUTO HIDE ALERT
 */
document.addEventListener('DOMContentLoaded', function() {
    var alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function() { alert.remove(); }, 500);
        }, 5000);
    });
});
</script>

<?php include 'includes/footer.php'; ?>