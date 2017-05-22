<?php

namespace Http\Client\Tests;

use PHPUnit\Framework\Test;

// If PHPUnit 6
if (class_exists('\\PHPUnit\\TextUI\\ResultPrinter')) {
    class ResultPrinter extends \PHPUnit\TextUI\ResultPrinter {
        use FeatureTestListener;
        public function startTest(Test $test)
        {
            return $this->doStartTest($test);
        }
        public function endTest(Test $test, $time)
        {
            return $this->doEndTest($test, $time);
        }
    }
} else {
    class ResultPrinter extends \PHPUnit_TextUI_ResultPrinter {
        use FeatureTestListener;
        public function startTest(\PHPUnit_Framework_Test $test)
        {
            return $this->doStartTest($test);
        }
        public function endTest(\PHPUnit_Framework_Test $test, $time)
        {
            return $this->doEndTest($test, $time);
        }
    }
}
