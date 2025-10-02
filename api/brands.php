<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/config.php';

try {
    $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Detect available table (pc_models or models)
    $table = null;
    foreach (['pc_models','models'] as $t) {
        $q = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($t));
        if ($q && $q->fetchColumn()) {
            $table = $t;
            break;
        }
    }
    if (!$table) {
        echo json_encode([]);
        exit;
    }

    $stmt = $pdo->query("SELECT DISTINCT brand FROM {$table} WHERE brand IS NOT NULL AND brand <> '' ORDER BY brand ASC");
    $brands = array_map(fn($r) => $r['brand'], $stmt->fetchAll());
    echo json_encode($brands, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([]);
}
