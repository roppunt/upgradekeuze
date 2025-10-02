<?php

if (!function_exists('get_prices')) {
  /**
   * Haal prijzen op uit price_matrix (keys):
   *  ssd_install, ram_install, win11_min, win11_max, linux_install
   * Valt terug op defaults als tabel/keys ontbreken.
   */
  function get_prices(): array {
    $defaults = [
      'ssd_install'   => '40,00',
      'ram_install'   => '40,00',
      'win11_min'     => '59,00',
      'win11_max'     => '89,00',
      'linux_install' => '79,00',
    ];

    try {
      require __DIR__ . '/config.php';
      $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ]);
      $q = $pdo->query("SHOW TABLES LIKE 'price_matrix'");
      if (!$q || !$q->fetchColumn()) return $defaults;

      $map = $defaults;
      $stmt = $pdo->query("SELECT `key`, `price_eur` FROM price_matrix");
      foreach ($stmt as $row) {
        $k = $row['key'];
        $v = number_format((float)$row['price_eur'], 2, ',', '.');
        $map[$k] = $v;
      }
      // Sommige keys kunnen ontbreken → vul aan met defaults
      foreach ($defaults as $k=>$v) if (!isset($map[$k])) $map[$k] = $v;
      return $map;
    } catch (Throwable $e) {
      return $defaults;
    }
  }
}

$directAccess = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__;
if (!$directAccess) {
  return;
}

require __DIR__.'/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $rows = $pdo->query("SELECT pkey, value_cents, description FROM prices")->fetchAll();
  json_out(['ok'=>true,'prices'=>$rows]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (empty($_SESSION['uid'])) json_out(['ok'=>false,'error'=>'Unauthorized'], 401);

  $input = json_decode(file_get_contents('php://input'), true) ?? [];
  // verwacht: {"pkey":"ssd_upgrade","value_cents":4500}
  if (empty($input['pkey']) || !isset($input['value_cents'])) json_out(['ok'=>false,'error'=>'Invalid payload'], 400);

  $stmt = $pdo->prepare("UPDATE prices SET value_cents=?, updated_at=NOW() WHERE pkey=?");
  $stmt->execute([ (int)$input['value_cents'], (string)$input['pkey'] ]);
  json_out(['ok'=>true]);
}

json_out(['ok'=>false,'error'=>'Method not allowed'], 405);
