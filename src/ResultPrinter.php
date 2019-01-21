<?php

namespace Http\Client\Tests;

use PHPUnit\Framework\Test;
use PHPUnit\TextUI\ResultPrinter as BaseResultPrinter;

class ResultPrinter extends BaseResultPrinter
{
    use FeatureTestListener;

    public function startTest(Test $test): void
    {
        return $this->doStartTest($test);
    }

    public function endTest(Test $test, float $time): void
    {
        return $this->doEndTest($test, $time);
    }
}
