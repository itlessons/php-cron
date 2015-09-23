<?php

namespace Cron;

class Notify
{
    /**
     * @param $data All columns of crashed handler from db and subject
     */
    public function notifyAboutCrash($data)
    {
        //$data['subject']
        throw new \LogicException(sprintf('Configure option "mail_callable" to notify admins about crashed processes! %s', $data['subject']));
    }
}