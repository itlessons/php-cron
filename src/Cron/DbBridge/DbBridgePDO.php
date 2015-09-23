<?php

namespace Cron\DbBridge;

class DbBridgePDO extends DbBridge
{
    public function __construct(\PDO $connection)
    {
        $this->connection = $connection;
    }

    public function query($sql, array $binds = [])
    {
        $sth = $this->connection->prepare($sql);
        $sth->execute($binds);
        $sth->setFetchMode(\PDO::FETCH_ASSOC);
        return iterator_to_array($sth);
    }

    public function exec($sql, $binds)
    {
        $sth = $this->connection->prepare($sql);
        $sth->execute($binds);
        return $sth->rowCount();
    }
}