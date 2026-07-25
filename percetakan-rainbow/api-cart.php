<?php
/**
 * api-cart.php - API untuk update jumlah keranjang
 * Versi sederhana tanpa rate limiting
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

// 🔥 CEK SESSION
if (!isset($_SESSION['customer_id']) || empty($_SESSION['customer_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'count' => 0,
        'message' => 'Unauthorized'
    ]);
    exit;
}

// 🔥 HITUNG JUMLAH ITEM DI KERANJANG
try {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(quantity), 0) as total 
        FROM cart 
        WHERE customer_id = ?
    ");
    $stmt->execute([$_SESSION['customer_id']]);
    $result = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'count' => (int)($result['total'] ?? 0)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'count' => 0,
        'message' => 'Internal Server Error'
    ]);
}

exit;