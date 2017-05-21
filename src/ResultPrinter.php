<?php

namespace Http\Client\Tests;

// If PHPUnit 6
if (class_exists('\\PHPUnit\\TextUI\\ResultPrinter'))
    class ResultPrinter extends PHPUnit\TextUI\ResultPrinter {}
} else {
    class ResultPrinter extends \PHPUnit_TextUI_ResultPrinter {}
}
