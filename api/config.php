<?php
// Pas aan op jouw XAMPP/MySQL setup
$DB_HOST = '127.0.0.1';
$DB_NAME = 'upgradekeuze';
$DB_USER = 'root';
$DB_PASS = ''; // of jouw wachtwoord

$pdo = new PDO(
  "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
  $DB_USER, $DB_PASS,
  [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC ]
);

function json_out($data, $code=200){
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

session_start();

