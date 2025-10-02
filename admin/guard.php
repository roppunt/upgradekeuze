<?php
require __DIR__.'/../api/config.php';
if (empty($_SESSION['uid'])) { header('Location: /admin/login.php'); exit; }

