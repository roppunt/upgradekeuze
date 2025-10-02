<?php
// D:\api\check.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/models.php';
require_once __DIR__ . '/prices.php';

// --- Input ---
$input = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!$input || !is_array($input)) {
  http_response_code(400);
  echo json_encode(['error' => 'Geen geldige input']);
  exit;
}

$required = ['brand','model','ram','storage_type','cpu_year','use'];
foreach ($required as $f) {
  if (!isset($input[$f]) || $input[$f] === '') {
    http_response_code(422);
    echo json_encode(['error' => "Ontbrekend veld: $f"]);
    exit;
  }
}

$brand       = trim((string)$input['brand']);
$model       = trim((string)$input['model']);
$ram         = (int)$input['ram'];
$storageType = (string)$input['storage_type']; // HDD | SSD | Onbekend
$storageGB   = isset($input['storage_gb']) && $input['storage_gb'] !== '' ? (int)$input['storage_gb'] : null;
$cpuEra      = (string)$input['cpu_year'];     // pre2015 | 2015-2017 | 2018-2020 | 2021plus
$use         = (string)$input['use'];          // basis | standaard | zwaar

// --- Model lookup via models.php (defensief) ---
$modelInfo = null;
if (function_exists('lookup_model')) {
  $modelInfo = lookup_model($brand, $model);
}

$issues=[]; $tips=[]; $actions=[]; $kpis=[]; $prices=[];
$badge=['tone'=>'warn','text'=>'Advies beschikbaar'];
$title='Advies';
$summary='';
$win11Likely=false;
$maxRam = null;

// DB-result meenemen
if (is_array($modelInfo)) {
  $miBrand = $modelInfo['brand'] ?? $brand;
  $miModel = $modelInfo['model'] ?? $model;
  $miYear  = $modelInfo['year']  ?? null;

  $kpis[] = "Gevonden model: {$miBrand} {$miModel}" . ($miYear ? " ({$miYear})" : '');

  if (!empty($modelInfo['win11_supported'])) $win11Likely = true;
  if (isset($modelInfo['max_ram']) && is_numeric($modelInfo['max_ram'])) $maxRam = (int)$modelInfo['max_ram'];
} else {
  $tips[] = "Model niet exact gevonden; advies op basis van algemene richtlijnen.";
}

// Heuristiek: 2018+ & SSD & RAM≥8 ⇒ kans op W11 goed
if (!$win11Likely) {
  if (in_array($cpuEra, ['2018-2020','2021plus'], true) && $storageType === 'SSD' && $ram >= 8) {
    $win11Likely = true;
  }
}

// RAM
if ($ram < 8) {
  $issues[]  = "Minder dan 8 GB RAM veroorzaakt vaak traagheid.";
  $actions[] = "Upgrade naar minimaal 8 GB RAM" . ($use === 'zwaar' ? " (liefst 16 GB)" : "") . ".";
  $kpis[]    = "RAM: {$ram} GB (laag)";
} else {
  if ($use === 'zwaar' && $ram < 16) $tips[] = "Voor zware taken is 16 GB RAM merkbaar beter.";
  $kpis[] = "RAM OK: {$ram} GB";
}

// Opslag
if ($storageType === 'HDD') {
  $issues[]  = "HDD is traag bij opstarten en updates.";
  $actions[] = "Vervang HDD door SSD.";
  $kpis[]    = "Opslag: HDD → vervang door SSD";
} elseif ($storageType === 'SSD') {
  $kpis[] = "Opslag OK: SSD";
} else {
  $tips[] = "Opslagtype onbekend. Als dit HDD is: zeker naar SSD upgraden.";
}
if ($storageGB && $storageGB < 240) {
  $tips[] = "Opslag is aan de krappe kant; 240–512 GB is comfortabeler.";
  $kpis[] = "Opslag: {$storageGB} GB (krap)";
} elseif ($storageGB) {
  $kpis[] = "Opslag: {$storageGB} GB";
}

// Eindoordeel
if ($win11Likely && $ram >= 8 && ($storageType === 'SSD' || $storageType === 'Onbekend')) {
  $badge=['tone'=>'ok','text'=>'Waarschijnlijk geschikt voor Windows 11'];
  $title='Goed nieuws 🎉';
  $summary='Je systeem lijkt geschikt voor Windows 11. We controleren dit graag definitief en voeren de upgrade veilig uit (incl. back-up).';
  array_unshift($actions, 'Plan een Windows 11-upgrade (inclusief back-up/controle).');
} else {
  if ($storageType==='HDD' || $ram<8 || in_array($cpuEra, ['pre2015','2015-2017'], true)) {
    $badge=['tone'=>'warn','text'=>'Upgrade aanbevolen'];
    $title='Slimme upgrade aanbevolen';
    $summary='Met een SSD en voldoende RAM voelt je computer weer als nieuw. Is Windows 11 minder geschikt, dan is Zorin OS (Linux) een snelle optie voor dagelijks gebruik.';
    array_unshift($actions, 'SSD-upgrade (en eventueel RAM).');
    $actions[] = 'Alternatief: overstap naar Zorin OS (inclusief begeleiding).';
  } else {
    $badge=['tone'=>'ok','text'=>'Kansrijk met optimalisaties'];
    $summary='Met een SSD en voldoende RAM is je systeem waarschijnlijk prima voor Windows 11 of snelle Windows 10.';
  }
}

// Prijzen via prices.php
if (function_exists('get_prices')) {
  $pl = get_prices(); // verwacht keys: ssd_install, ram_install, win11_min, win11_max, linux_install
  if ($storageType === 'HDD') $prices[] = "SSD inbouw: €{$pl['ssd_install']} + SSD";
  if ($ram < 8)              $prices[] = "RAM-upgrade: €{$pl['ram_install']} + RAM";
  $prices[] = "Windows 11 upgrade: €{$pl['win11_min']} – €{$pl['win11_max']}";
  $prices[] = "Linux (Zorin) installatie + training: €{$pl['linux_install']}";
} else {
  // Fallback vaste waarden
  if ($storageType === 'HDD') $prices[] = "SSD inbouw: €40,00 + SSD";
  if ($ram < 8)              $prices[] = "RAM-upgrade: €40,00 + RAM";
  $prices[] = "Windows 11 upgrade: €59,00 – €89,00";
  $prices[] = "Linux (Zorin) installatie + training: €79,00";
}

// Max RAM hint
if ($maxRam !== null && $ram < 8 && $maxRam < 8) {
  $tips[] = "Dit model ondersteunt mogelijk maximaal {$maxRam} GB RAM; Linux kan dan een betere keuze zijn.";
}

echo json_encode([
  'badge'   => $badge,
  'title'   => $title,
  'summary' => $summary,
  'issues'  => $issues,
  'tips'    => $tips,
  'actions' => $actions,
  'kpis'    => $kpis,
  'prices'  => $prices,
], JSON_UNESCAPED_UNICODE);

