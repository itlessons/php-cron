<?php

require_once __DIR__ . '/bootstrap.php';

// delete all old handlers to prevent clones when time spec changed
$scheduler->deleteHandler('Handlers.exec1');
$scheduler->deleteHandler('Handlers.exec2');
$scheduler->deleteHandler('Handlers.exec3');

// install new
$scheduler->addHandler('Handlers.exec1', '10 0 * * *');
$scheduler->addHandler('Handlers.exec2', '20 0 * * *');
$scheduler->addHandler('Handlers.exec3', '30 0 * * *');