<?php

namespace Http\Client\Tests;

class FeatureTestListener extends \PHPUnit_TextUI_ResultPrinter
{
    public function write($buffer)
    {
    }

    public function startTest(\PHPUnit_Framework_Test $test)
    {
        $feature = $this->extractFeature($test);

        if (empty($feature)) {
            $feature = $test->getName();
        } else {
            $feature = $feature[0];
        }

        echo sprintf('%-40.s : ', $feature);
    }

    public function endTest(\PHPUnit_Framework_Test $test, $time)
    {
        if (!$this->lastTestFailed) {
            echo $this->formatWithColor('fg-green', 'Supported')."\n";
        } else {
            echo $this->formatWithColor('fg-red', 'Not supported')."\n";
        }

        $this->lastTestFailed = false;
    }

    private function extractFeature(\PHPUnit_Framework_Test $test)
    {
        $class = get_class($test);
        $method = $test->getName();
        $reflection = new \ReflectionMethod($class, $method);

        return $this->parseDocBlock($reflection->getDocComment(), '@feature');
    }

    private function parseDocBlock($doc_block, $tag)
    {
        $matches = [];

        if (empty($doc_block)) {
            return $matches;
        }

        $regex = "/{$tag} (.*)(\\r\\n|\\r|\\n)/U";
        preg_match_all($regex, $doc_block, $matches);

        if (empty($matches[1])) {
            return [];
        }

        $matches = $matches[1];

        foreach ($matches as $ix => $match) {
            $matches[ $ix ] = trim($match);
        }

        return $matches;
    }
}
