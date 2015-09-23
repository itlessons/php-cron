<?php

namespace Cron;


class Specification
{
    private $error;

    /**
     * Check execute specification format.
     *
     * @param   string $spec specification
     *
     * @return  bool    true - specification is valid; false - otherwise
     */
    public function isValid($spec)
    {
        $error = $this->error = '';
        $arSpec = explode(' ', $spec);
        if (count($arSpec) <> 5) {
            $error = 'Incorrect execution specification. Check number of fields.';
        }

        $arSpecDate = array();

        foreach ($arSpec as $key => $value) {
            $value = trim($value);
            $arSpecDate[] = ($value == '*') ? $value : $this->parseSpecificationField($value);
        }

        foreach ($arSpecDate as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    switch ($key) {
                        case 0: // minute
                            if ($v < 0 or $v > 59)
                                $error = 'Incorrect execution specification. Check minutes.';
                            break;
                        case 1: // hour
                            if ($v < 0 or $v > 23)
                                $error = 'Incorrect execution specification. Check hours.';
                            break;
                        case 2: // day
                            if ($v < 1 or $v > 31)
                                $error = 'Incorrect execution specification. Check days of days.';
                            break;
                        case 3: // month
                            if ($v < 1 or $v > 12)
                                $error = 'Incorrect execution specification. Check months.';
                            break;
                        case 4: // weekday
                            if ($v < 0 or $v > 6)
                                $error = 'Incorrect execution specification. Check weekdays.';
                            break;
                    }
                }
            }
        }

        $this->error = $error;
        return mb_strlen($error) <= 0;
    }

    /**
     * Parse execute specification field.
     *
     * @param   interval    string        interval - "start number - end number"
     *
     * @return  array   array of numbers
     */
    protected function parseSpecificationField($item)
    {
        $arTmp = array();
        $arr = explode(',', $item);
        foreach ($arr as $v) {
            $ar = $this->makeSequence($v);
            $arTmp = array_merge($arTmp, $ar);
        }
        sort($arTmp);
        return array_unique($arTmp);
    }

    /**
     * Create array of sequential numbers from interval
     *
     * @param   string $interval "start number - end number"
     *
     * @return  array
     */
    protected function makeSequence($interval)
    {
        $res = array();
        $ar = explode('-', $interval);
        $res[] = $start = intval($ar[0]);
        $end = intval(@$ar[1]);

        for ($i = $start + 1; $i <= $end; $i++) {
            $res[] = $i;
        }

        return $res;
    }

    /**
     * Check certain timestamp for matching the execution specification.
     *
     * @param   string $spec execution specification (see details of this specification format in "addHandler" method description)
     * @param   string $stmp timestamp for checking, if it isn't filled then current timstamp will be checked
     *
     * @return  bool        true - timestamp match specification; false - otherwise
     */
    public function isTimeMatch($spec, $stmp = NULL)
    {
        $arSpec = explode(' ', $spec);

        if (count($arSpec) <> 5) {
            return false;
        }

        if (!$stmp) {
            $stmp = time();
        }

        $stmp = explode(' ', trim(date('i H d m w', $stmp)));
        $i = 0;
        while (isset($arSpec[$i])) {
            if (preg_match('/^' . $stmp[$i] . '$/', $arSpec[$i]) || preg_match('/^\*$/', $arSpec[$i])) {
                $i++;
                continue 1;
            } elseif (preg_match('/^(([0-9]{1,2}|[0-9]{1,2}-[0-9]{1,2}),?)+$/', $arSpec[$i])) {
                $arAlt = explode(',', $arSpec[$i]);
                $i_alt = 0;
                while (isset($arAlt[$i_alt])) {
                    if ($arAlt[$i_alt] == $stmp[$i]) {
                        $i++;
                        continue 2;
                    }

                    if (preg_match('/^([0-9]{1,2}-[0-9]{1,2})$/', $arAlt[$i_alt])) {
                        $range = explode('-', $arAlt[$i_alt]);
                        if ($stmp[$i] >= $range[0] && $stmp[$i] <= $range[1]) {
                            $i++;
                            continue 2;
                        }
                    }
                    $i_alt++;
                }
                return false;
            } else {
                return false;
            }
        }
        return true;
    }

    /**
     * Executes handler specification and pass args to it.
     *
     *   MyClass->execHandler //call method execHandler of class MyClass
     *   MyUser->getSomeClass->execSomeHandler // get object from MyUser->getSomeClass() and exec method execSomeHandler
     *   MyClass::execHandler //call static method execHandler of class MyClass
     **
     * @param $spec
     * @param array $args
     * @return mixed
     */
    public function callHandler($spec, array $args = [])
    {
        if (is_callable($spec)) {
            // do nothing
        } elseif (!(preg_match('/^([\\a-zA-Z0-9]+)(::|\.|->)(\w+)$/', $spec, $m) || preg_match('/^([\\a-zA-Z0-9]+)(\.|->)(\w+)(\.|->)?(\w+)*$/', $spec, $m))) {
            throw new \InvalidArgumentException(sprintf('Malformed handler: $s', $spec));
        } else {
            $spec = explode($m[2], $spec);
            $spec[0] = new $spec[0];
            if (count($spec) == 3) {
                $spec[0] = $spec[0]->$spec[1]();
                $spec[1] = $spec[2];
                unset($spec[2]);
            }
        }

        return call_user_func_array($spec, $args);
    }

    public function getLastError()
    {
        return $this->error;
    }
}