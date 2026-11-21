<?php
// Serveur de développement local : php -S 127.0.0.1:8099 -t branding branding/tools/dev-router.php
$p = (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$f = realpath(__DIR__ . '/..' . $p);
if ($p === '/' || ($f && (is_file($f) || is_dir($f)) && strpos($f, realpath(__DIR__ . '/..')) === 0 && !preg_match('#/(app|data|partials|tools|docs)(/|$)#', $p))) return false;
$_SERVER['SCRIPT_NAME'] = '/app.php';
require __DIR__ . '/../app.php';
