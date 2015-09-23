<?php

namespace Cron\DbBridge;

abstract class DbBridge
{
    /**
     * @param $sql
     * @param array $binds
     * @return array
     */
    abstract public function query($sql, array $binds = []);

    /**
     * @param $sql
     * @param $binds
     * @return int
     */
    abstract public function exec($sql, $binds);
}