<?php

/* Copyright (c) 2018 Nils Haagen <nils.haagen@concepts-and-training.de> Extended GPL, see docs/LICENSE */

require_once(__DIR__ . "/../../../../../libs/composer/vendor/autoload.php");
require_once(__DIR__ . "/../../../Base.php");


use ILIAS\UI\Implementation\Component\SignalGenerator;
use \ILIAS\UI\Implementation\Component\Input\NameSource;
use \ILIAS\UI\Component\Input\Field;
use \ILIAS\Data;
use \ILIAS\Refinery\Validation;
use \ILIAS\Refinery\Transformation;

class DurationInputTest extends ILIAS_UI_TestBase
{
    public function setUp()
    {
        $this->name_source = new DefNamesource();
        $this->data_factory = new Data\Factory();
        $this->factory = $this->buildFactory();
    }

    protected function buildFactory()
    {
        $df = new Data\Factory();
        $language = $this->createMock(\ilLanguage::class);

        return new ILIAS\UI\Implementation\Component\Input\Field\Factory(
            new SignalGenerator(),
            $this->data_factory,
            new \ILIAS\Refinery\Factory($df, $language)
        );
    }

    public function test_withFormat()
    {
        $format = $this->data_factory->dateFormat()->germanShort();
        $duration = $this->factory->duration('label', 'byline')
            ->withFormat($format);

        $this->assertEquals(
            $format,
            $duration->getFormat()
        );
    }

    public function test_withMinValue()
    {
        $dat = new \DateTimeImmutable('2019-01-09');
        $duration = $this->factory->duration('label', 'byline')
            ->withMinValue($dat);

        $this->assertEquals(
            $dat,
            $duration->getMinValue()
        );
    }

    public function test_withMaxValue()
    {
        $dat = new \DateTimeImmutable('2019-01-09');
        $duration = $this->factory->duration('label', 'byline')
            ->withMaxValue($dat);

        $this->assertEquals(
            $dat,
            $duration->getMaxValue()
        );
    }

    public function test_withUseTime()
    {
        $datetime = $this->factory->duration('label', 'byline');
        $this->assertFalse($datetime->getUseTime());
        $this->assertTrue($datetime->withUseTime(true)->getUseTime());
    }

    public function test_withTimeOnly()
    {
        $datetime = $this->factory->duration('label', 'byline');
        $this->assertFalse($datetime->getTimeOnly());
        $this->assertTrue($datetime->withTimeOnly(true)->getTimeOnly());
    }

    public function test_withTimeZone()
    {
        $datetime = $this->factory->duration('label', 'byline');
        $this->assertNull($datetime->getTimeZone());
        $tz = 'Europe/Moscow';
        $this->assertEquals(
            $tz,
            $datetime->withTimeZone($tz)->getTimeZone()
        );
    }

    public function test_withInvalidTimeZone()
    {
        $this->expectException(\InvalidArgumentException::class);
        $datetime = $this->factory->duration('label', 'byline');
        $tz = 'NOT/aValidTZ';
        $datetime->withTimeZone($tz);
    }
}
