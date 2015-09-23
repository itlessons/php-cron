<?php

namespace Cron;

use Cron\DbBridge\DbBridge;

/**
 * Class Scheduler
 * @package Cron
 *
 * SYNOPSIS:
 *
 *   // Add handler to cron table
 *   $scheduler->addHandler('MyClass->MyHandlerA', '24 * * * *', ["p1" => "v1", "p2" => "v2"]);
 *   $scheduler->addHandler('MyClass::MyMethod', '* * * * *');
 *   $scheduler->addHandler('MyFunction', '* * * * *');
 *
 *   // Delete handler from cron table
 *   $scheduler->deleteHandler('MyClass->MyHandlerA');
 *   $scheduler->deleteHandler('MyLibrary.MyMethod', '* 10-18 * * *');
 *
 *   // Process all cron table
 *   $scheduler->process();
 *
 *   // Process one cron table record
 *   $scheduler->process($record_id);
 *
 *   // Process search for crashed handlers
 *   // This function should be executed every 30 minutes, you need to add this function into the crontab file
 *   $scheduler->check();
 *
 * DESCRIPTION:
 *
 *   This library allows to execute function & class methods according certain time specification.
 */
class Scheduler
{
    private $options = [
        'lock_name' => 'cron',
        'table' => 'cron',
        'crash_time' => 3600,
        'crash_callback' => '\Cron\Notify->notifyAboutCrash',
    ];

    /**
     * @var Specification
     */
    private $spec;

    /**
     * @var DbBridge
     */
    private $db;

    public function __construct(DbBridge $db, $options = [], Specification $spec = null)
    {
        $this->spec = $spec == null ? new Specification() : $spec;
        $this->db = $db;
        $this->options = array_merge($this->options, $options);
    }


    /**
     * Add handler to the cron table.
     *
     * @param   string $handlerSpec handler specification, possible values:
     *
     *          MyClass::MyMethod    - execute method "MyMethod" of class "MyClass" statically
     *          MyClass->MyMethod    - create instance of "MyClass" and execute his method "MyMethod"
     *          MyFunction           - execute function
     *
     * @param   string $execSpec execution specification (crontab format)
     *
     *          Execution specification follows a particular format as a series of fields,
     *          separated by spaces. Each field can have a single value or a series of values.
     *
     *          >> Fields:
     *
     *          * * * * *
     *          | | | | |
     *          | | | | ----- day of week    (0 - 6) (0 - Sunday, 1 - Monday ... 6 - Saturday)
     *          | | | ------- month          (1 - 12)
     *          | | --------- day of month   (1 - 31)
     *          | ----------- hour           (0 - 23)
     *          ------------- min            (0 - 59)
     *
     *          >> Operators:
     *
     *          There are several ways of specifying multiple values in a field:
     *
     *            -    The comma (',') operator specifies a list of values, for example: "1,3,4,7,8"
     *            -    The dash ('-') operator specifies a range of values, for example: "1-6", which is equivalent to "1,2,3,4,5,6"
     *            -    The asterisk ('*') operator specifies all possible values for a field.
     *          For example, an asterisk in the hour time field would be equivalent to 'every hour'..
     *
     *          >> Examples:
     *
     *          * * * * *
     *          Handler will be executed every minute of every hour of every day of every month.
     *
     *          0 2 1-10 * *
     *          Handler will be executed every 2am on the 1st thru the 10th of each month.
     *
     *          0 12 1,15 * 5
     *          Handler will be executed each Friday AND the first and fifteenth of every month.
     *
     * @param null $vars
     * @param null $priority
     *
     * @return bool
     * @throws \InvalidArgumentException if not valid data
     */
    public function addHandler($handlerSpec, $execSpec, $vars = null, $priority = null)
    {
        if (mb_strlen($handlerSpec) <= 0) {
            throw new \InvalidArgumentException('Scheduler: You need to specify handler.');
        }

        if (mb_strlen($execSpec) <= 0) {
            throw new \InvalidArgumentException('Scheduler: You need to specify execution specification.');
        }

        if (!$this->spec->isValid($execSpec)) {
            throw new \InvalidArgumentException(sprintf('Scheduler: %s', $this->spec->getLastError()));
        }

        $sql = 'INSERT INTO ' . $this->getOption('table') . ' '
            . 'SET cdate=now(), '
            . 'handler_spec = ?, '
            . 'exec_spec = ? ';

        $binds = [$handlerSpec, $execSpec];

        if ($priority) {
            $sql .= ', priority = ?';
            $binds[] = $priority;
        }

        if ($vars) {
            $sql .= ', vars = ?';
            $binds[] = serialize($vars);
        }

        return (bool)$this->db->exec($sql, $binds);
    }

    /**
     * Delete handler from cron table.
     *
     * @param  string $handlerSpec
     * @param  string $execSpec
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function deleteHandler($handlerSpec, $execSpec = null)
    {
        if (!$handlerSpec) {
            throw new \InvalidArgumentException('Scheduler: You need to specify handler.');
        }

        $sql = 'DELETE FROM ' . $this->getOption('table') . ' WHERE handler_spec=?';
        $binds = [$handlerSpec];

        if ($execSpec) {
            $sql .= ' AND exec_spec=?';
            $binds[] = $execSpec;
        }

        return (bool)$this->db->exec($sql, $binds);

    }

    public function getHandlers()
    {
        $sql = 'SELECT id,handler_spec,exec_spec,vars
			    FROM ' . $this->getOption('table') . '
			    WHERE active=1
			    ORDER BY priority DESC, id ASC';

        $data = $this->db->query($sql);
        foreach ($data as $k => $v)
            $data[$k]['vars'] = mb_strlen($v['vars']) ? unserialize($v['vars']) : null;

        return $data;
    }

    /**
     * Process cron table. Select handlers, check execution specifications and execute them if it's needed.
     * If handler returns some variable then this variable will be transferred into the handler params next time.
     *
     * @param int $id id of sheduler table record for processing
     *
     * @return  bool    true - if some handlers were executed; false - if queue was locked or nothing to execute
     */
    public function process($id = null)
    {
        $id = intval($id);
        $lock_name = $id > 0 ? $this->getOption('lock_name') . $id : $this->getOption('lock_name');

        $lock = $this->db->query('SELECT GET_LOCK(?, 0) as L', [$lock_name]);
        if ($lock[0]['L'] == '0') {
            return false;
        }

        $where = $id > 0 ? ' AND id = ' . $id : '';

        $sql = 'SELECT id, handler_spec,exec_spec,vars
			    FROM ' . $this->getOption('table') . '
			    WHERE active=1 ' . $where . ' AND start_process IS NULL
			    ORDER BY priority DESC, id ASC';

        $arHandlers = array();

        $data = $this->db->query($sql);

        foreach ($data as $ar) {
            if ($id > 0 or $this->spec->isTimeMatch($ar['exec_spec'])) {
                $arHandlers[$ar['id']] = [
                    'spec' => $ar['handler_spec'],
                    'vars' => mb_strlen($ar['vars']) ? unserialize($ar['vars']) : null
                ];

                // fix time of processing start to avoid simultaneous execution of the same handlers
                $this->db->exec('UPDATE ' . $this->getOption('table') . '
                                 SET start_process=now(), start_exec=null, finish_exec=null
                                 WHERE id=?', [$ar['id']]);

            }
        }

        $this->db->exec("SELECT RELEASE_LOCK(?)", [$lock_name]);

        // process handlers
        foreach ($arHandlers as $id => $ar) {
            // fix time of execution start
            $this->db->exec('UPDATE ' . $this->getOption('table') . '
                             SET start_exec=now(), finish_exec=null
                             WHERE id=?', [$id]);

            // execute handler
            $start = time();
            try {
                $result = $this->spec->callHandler($ar['spec'], [$ar['vars']]);
            } catch (\Exception $e) {

                if (!is_array($ar['vars']) && mb_strlen($ar['vars']) > 0) {
                    $ar['vars'] = [$ar['vars']];
                }

                $ar['vars']['exception'] = $e->__toString();

                $this->db->exec('UPDATE ' . $this->getOption('table') . '
                             SET vars=?
                             WHERE id=?', [serialize($ar['vars']), $id]);

                continue;
            }

            // fix time of execution end
            $this->db->exec('UPDATE ' . $this->getOption('table') . '
                             SET start_process=null, start_exec=null, finish_exec=now(), exec_time=?, vars=?
                             WHERE id=?', [time() - $start, serialize($result), $id]);
        }
        return true;
    }

    /**
     * Search for crashed handlers & email developers.
     *
     * @return  array   array of crashed handler identifiers
     */
    public function check()
    {
        $res = [];
        $crash_time = $this->getOption('crash_time');

        if ($crash_time) {
            $sql = 'SELECT *
				    FROM ' . $this->getOption('table') . '
				    WHERE UNIX_TIMESTAMP(now()) - UNIX_TIMESTAMP(start_exec) > ? AND active = ?';

            $rs = $this->db->query($sql, [$crash_time, 1]);

            foreach ($rs as $ar) {
                $ar['message'] = 'Cron job crashed [' . $ar['handler_spec'] . ']';
                $ar['vars'] = mb_strlen($ar['vars']) ? unserialize($ar['vars']) : null;

                $this->spec->callHandler($this->getOption('crash_callback'), [$ar]);

                $this->db->exec('UPDATE ' . $this->getOption('table') . '
                                 SET start_process=null, start_exec=null
                                 WHERE id=?', [$ar["id"]]);

                $res[] = $ar['id'];
            }
        }

        return $res;
    }

    public function getOption($key, $default = null)
    {
        return isset($this->options[$key]) ? $this->options[$key] : $default;
    }

    public function setOption($key, $value)
    {
        $this->options[$key] = $value;
    }
}