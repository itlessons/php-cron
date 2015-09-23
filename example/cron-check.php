<?php

#
# need add to cron
# 30 * * * * user php /path_to_project/cron-check.php
#

require_once __DIR__ . '/bootstrap.php';
$scheduler->check();