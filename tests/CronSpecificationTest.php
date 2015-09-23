<?php

class CronSpecificationTest extends \PHPUnit_Framework_TestCase
{
    public function testCallHandler()
    {
        $s = new \Cron\Specification();
        $this->assertTrue($s->callHandler('CronSpecificationCall->testSimple') == 'simple');
        $this->assertTrue($s->callHandler('CronSpecificationCall.testSimple') == 'simple');
        $this->assertTrue($s->callHandler('CronSpecificationCall::testStatic') == 'static');
        $this->assertTrue($s->callHandler('CronSpecificationCall.testStatic') == 'static');
        $this->assertTrue($s->callHandler('CronSpecificationCall->chain->testChain') == 'chain');
        $this->assertTrue($s->callHandler('CronSpecificationCall.chain.testChain') == 'chain');
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessageRegExp #Configure option "mail_callable" to notify.*#
     */
    public function testCallNotify()
    {
        $s = new \Cron\Specification();
        $s->callHandler('\Cron\Notify->notifyAboutCrash', [['subject' => 'crash']]);
    }

    public function testCronTimeSpec()
    {
        $s = new \Cron\Specification();
        $this->assertTrue($s->isValid('* * * * *'));
        $this->assertTrue($s->isValid('30 12 * * *'));
        $this->assertTrue($s->isValid('30 12 1-10 * *'));

        $this->assertFalse($s->isValid('60 * * * *'));
        $this->assertSame($s->getLastError(), 'Incorrect execution specification. Check minutes.');

        $this->assertFalse($s->isValid('* 24 * * *'));
        $this->assertSame($s->getLastError(), 'Incorrect execution specification. Check hours.');

        $this->assertFalse($s->isValid('* * 32 * *'));
        $this->assertSame($s->getLastError(), 'Incorrect execution specification. Check days of days.');

        $this->assertFalse($s->isValid('* * * 13 *'));
        $this->assertSame($s->getLastError(), 'Incorrect execution specification. Check months.');

        $this->assertFalse($s->isValid('* * * * 7'));
        $this->assertSame($s->getLastError(), 'Incorrect execution specification. Check weekdays.');
    }

    public function testCronTimeMatch()
    {
        $s = new \Cron\Specification();
        $this->assertTrue($s->isTimeMatch('* * * * *'));
        $this->assertTrue($s->isTimeMatch('30 12 * * *', strtotime('2015-10-10 12:30:00')));
        $this->assertTrue($s->isTimeMatch('30 12 * * *', strtotime('2015-10-10 12:30:10')));
        $this->assertTrue($s->isTimeMatch('0 0 * * *', strtotime('Last Wednesday')));
        $this->assertTrue($s->isTimeMatch('0 13 7 * *', strtotime('2015-10-07 13:00')));
        $this->assertTrue($s->isTimeMatch('0 13 7 11 *', strtotime('2015-11-07 13:00')));

        $this->assertFalse($s->isTimeMatch('30 12 * * *', strtotime('2015-10-10 12:31:00')));
        $this->assertFalse($s->isTimeMatch('0 13 7 11 *', strtotime('2015-08-07 13:00')));
    }
}

class CronSpecificationCall
{
    public function testSimple()
    {
        return 'simple';
    }

    public static function testStatic()
    {
        return 'static';
    }

    public function chain()
    {
        return new CronSpecificationCallChain();
    }
}

class CronSpecificationCallChain
{
    public function testChain()
    {
        return 'chain';
    }
}