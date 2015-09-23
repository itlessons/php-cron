<?php

require_once __DIR__.'/handlers.php';

$db_host = 'localhost';
$db_username = 'root';
$db_password = '111222';
$db_name = 'itlessons_cron_tests';
$db_port = '3306';

$dsn = sprintf('mysql:host=%s;port=%s;db_name=%s', $db_host, $db_port, $db_name);
$conn = new \PDO($dsn, $db_username, $db_password);
$bridge = new \Cron\DbBridge\DbBridgePDO($conn);
$scheduler = new \Cron\Scheduler($bridge);

$scheduler->setOption('crash_callback', 'Handlers.crashCallback');
