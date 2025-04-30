<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Utilities\Twig;

use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Twig\Extension\JsonDecodeExtension;
use Tests\Base\FunctionalTestCase;
use Twig\TwigFilter;

class JsonDecodeExtensionTest extends FunctionalTestCase
{
    /**
     * @var JsonDecodeExtension
     */
    private $twigExtension;

    public function setUp(): void
    {
        parent::setUp();

        $this->twigExtension = new JsonDecodeExtension(self::getContainer());
    }

    public function testGetName()
    {
        $name = $this->twigExtension->getName();

        static::assertEquals('jsonDecode_extension', $name);
    }

    public function testGetFilters()
    {
        $result = $this->twigExtension->getFilters();
        static::assertTrue(is_array($result) && isset($result[0]));
        static::assertTrue($result[0] instanceof TwigFilter);

        $callable = $result[0]->getCallable();
        static::assertTrue(is_callable($callable));

        $inputJson = Json::encode(['key' => 'value']);

        $output = $callable($inputJson);

        static::assertInstanceOf('stdClass', $output);

        $outputArray = $callable($inputJson, true);
        static::assertArrayHasKey('key', $outputArray);
        static::assertEquals('value', $outputArray['key']);
    }
}
