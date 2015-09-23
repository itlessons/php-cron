<?php

class CronTestUtil
{
    /**
     * @return PDO
     */
    public static function createConnection()
    {
        if (!self::hasRequiredConnectionParams()) {
            throw new \LogicException('Configure database connection in phpunit.xml.dist file!');
        }

        $dsn = sprintf('mysql:host=%s;port=%s', $GLOBALS['db_host'], $GLOBALS['db_port']);
        $conn = new \PDO($dsn, $GLOBALS['db_username'], $GLOBALS['db_password']);

        self::constructDbSchemaIfNeed($conn);

        return $conn;
    }

    public static function truncateTable(\PDO $conn, $table)
    {
        $conn->query(sprintf('TRUNCATE TABLE %s', $table));
    }

    private static function hasRequiredConnectionParams()
    {
        return isset(
            $GLOBALS['db_username'],
            $GLOBALS['db_password'],
            $GLOBALS['db_host'],
            $GLOBALS['db_name'],
            $GLOBALS['db_port']
        );
    }

    private static function constructDbSchemaIfNeed(\PDO $conn)
    {
        $sql = sprintf('CREATE DATABASE IF NOT EXISTS %s
                        CHARACTER SET utf8
                        COLLATE utf8_general_ci;', $GLOBALS['db_name']);

        $conn->query($sql);

        $conn->query(sprintf('USE %s', $GLOBALS['db_name']));

        $sql = "CREATE TABLE IF NOT EXISTS `cron` (
              `id` INTEGER(11) NOT NULL AUTO_INCREMENT,
              `handler_spec` VARCHAR(255) COLLATE utf8_general_ci NOT NULL DEFAULT '',
              `vars` TEXT COLLATE utf8_general_ci,
              `exec_spec` VARCHAR(255) COLLATE utf8_general_ci NOT NULL DEFAULT '',
              `start_process` DATETIME DEFAULT NULL,
              `start_exec` DATETIME DEFAULT NULL,
              `finish_exec` DATETIME DEFAULT NULL,
              `exec_time` INTEGER(11) NOT NULL DEFAULT '0',
              `active` TINYINT(1) NOT NULL DEFAULT '1',
              `priority` INTEGER(11) NOT NULL DEFAULT '100',
              `cdate` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
              PRIMARY KEY (`id`)
           )ENGINE=MyISAM CHARACTER SET 'utf8' COLLATE 'utf8_general_ci';";

        $conn->query($sql);
    }
}