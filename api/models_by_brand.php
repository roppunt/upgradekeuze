<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/config.php';

$brand = isset($_GET['brand']) ? trim((string)$_GET['brand']) : '';
$q     = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

if ($brand === '') {
    echo json_encode([]);
    exit;
}

try {
    $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $table = null;
    foreach (['pc_models','models'] as $t) {
        $chk = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($t));
        if ($chk && $chk->fetchColumn()) {
            $table = $t;
            break;
        }
    }
    if (!$table) {
        echo json_encode([]);
        exit;
    }

    if ($q !== '') {
        $like = '%' . preg_replace('/\s+/', '%', $q) . '%';
        $sql = "SELECT DISTINCT model FROM {$table} WHERE brand = :brand AND model LIKE :q ORDER BY model ASC LIMIT 100";
        $st = $pdo->prepare($sql);
        $st->execute([':brand' => $brand, ':q' => $like]);
    } else {
        $sql = "SELECT DISTINCT model FROM {$table} WHERE brand = :brand ORDER BY model ASC LIMIT 200";
        $st = $pdo->prepare($sql);
        $st->execute([':brand' => $brand]);
    }
    $models = array_map(fn($r) => $r['model'], $st->fetchAll());
    echo json_encode($models, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([]);
}
