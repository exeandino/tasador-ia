<?php
$cfg = is_file(__DIR__.'/../config/settings.php') ? require __DIR__.'/../config/settings.php' : [];
require __DIR__.'/middleware.php';
destroyUserSession($cfg);
$appUrl = rtrim($cfg['site_url'] ?? $cfg['app_url'] ?? '..', '/');
header("Location: {$appUrl}/auth/login.php?msg=logged_out");
exit;
