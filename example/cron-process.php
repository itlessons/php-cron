<?php
#
# need add to cron
# * * * * * user php /path_to_project/cron-process.php
#

require_once __DIR__ . '/bootstrap.php';
$scheduler->process();