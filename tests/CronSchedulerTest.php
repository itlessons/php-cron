<?php

class CronSchedulerTest extends \PHPUnit_Framework_TestCase
{
    public function testAddAndDeleteHandlers()
    {
        $s = $this->getScheduler(true);

        $s->addHandler('CronTestHandlers.calc', '30 12 * * *');
        $s->addHandler('CronTestHandlers.calc', '30 0 * * *', ['v1' => 1, 'v2' => 2]);

        $data = $s->getHandlers();
        $this->assertTrue(is_array($data));
        $this->assertEquals(count($data), 2);
        $this->assertEquals($data[0]['handler_spec'], 'CronTestHandlers.calc');
        $this->assertEquals($data[0]['exec_spec'], '30 12 * * *');
        $this->assertEquals($data[1]['handler_spec'], 'CronTestHandlers.calc');
        $this->assertEquals($data[1]['exec_spec'], '30 0 * * *');
        $this->assertEquals($data[1]['vars'], ['v1' => 1, 'v2' => 2]);

        $s->deleteHandler('CronTestHandlers.calc');
        $data = $s->getHandlers();
        $this->assertEquals(count($data), 0);

        $s->addHandler('CronTestHandlers.calc', '30 12 * * *');
        $s->addHandler('CronTestHandlers.calc', '30 0 * * *');

        $this->assertEquals(count($s->getHandlers()), 2);
        $s->deleteHandler('CronTestHandlers.calc', '30 12 * * *');
        $data = $s->getHandlers();
        $this->assertEquals(count($data), 1);
        $this->assertEquals($data[0]['handler_spec'], 'CronTestHandlers.calc');
        $this->assertEquals($data[0]['exec_spec'], '30 0 * * *');
    }

    public function testProcessHandlerWithVars()
    {
        $s = $this->getScheduler(true);

        $s->addHandler('CronTestHandlers.calc', '* * * * *', ['v1' => 1, 'v2' => 2]);
        $s->process(1);

        $data = $s->getHandlers();
        $this->assertEquals($data[0]['vars'], ['v3' => 3, 'v4' => 4]);

        $s->process(1);

        $data = $s->getHandlers();
        $this->assertEquals($data[0]['vars'], ['v1' => 1, 'v2' => 2]);
    }

    public function testCheckHandler()
    {
        $s = $this->getScheduler(true);

        $s->addHandler('CronTestHandlers.calc', '* * * * *');
        $s->addHandler('CronTestHandlers.calc2', '* * * * *');
        $s->addHandler('CronTestHandlers.calc3', '* * * * *');

        $s->process();

        $data = $s->getHandlers();

        $this->assertEquals($data[2]['vars'], 'done');


        $mock = $this->getMock('stdClass', array('notifyCrashed'));

        $mock->expects($this->once())
            ->method('notifyCrashed')
            ->will($this->returnCallback(function ($args) {
                return isset($args['exception']);
            }));

        $s->setOption('crash_callback', [$mock, 'notifyCrashed']);
        $s->setOption('crash_time', 1);

        sleep(2);

        $s->check();
    }

    private function getScheduler($clearDb = false)
    {
        $c = CronTestUtil::createConnection();
        $db = new Cron\DbBridge\DbBridgePDO($c);
        $s = new \Cron\Scheduler($db);
        if ($clearDb) {
            CronTestUtil::truncateTable($c, $s->getOption('table'));
        }

        return $s;
    }
}


class CronTestHandlers
{
    public function calc(array $vars = null)
    {
        if ($vars == ['v1' => 1, 'v2' => 2])
            return ['v3' => 3, 'v4' => 4];

        return ['v1' => 1, 'v2' => 2];
    }

    public function calc2()
    {
        throw new \Exception('some error');
    }

    public function calc3()
    {
        return 'done';
    }
}