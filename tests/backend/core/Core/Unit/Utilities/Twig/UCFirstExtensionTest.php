<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Utilities\Twig;

use demosplan\DemosPlanCoreBundle\Twig\Extension\UCFirstExtension;
use Tests\Base\FunctionalTestCase;
use Twig\TwigFilter;

class UCFirstExtensionTest extends FunctionalTestCase
{
    /**
     * @var UCFirstExtension
     */
    private $twigExtension;

    public function setUp(): void
    {
        parent::setUp();

        $this->twigExtension = new UCFirstExtension(self::getContainer());
    }

    public function testGetName()
    {
        $name = $this->twigExtension->getName();

        static::assertEquals('uCFirst_extension', $name);
    }

    public function testGetFilters()
    {
        $result = $this->twigExtension->getFilters();
        static::assertTrue(is_array($result) && isset($result[0]));
        static::assertTrue($result[0] instanceof TwigFilter);

        $callable = $result[0]->getCallable();
        static::assertTrue(is_callable($callable));

        $inputChange = 'ein String mit groß und KLEINSCHREIBUNG';
        $expectedChange = 'Ein String mit groß und KLEINSCHREIBUNG';

        static::assertEquals($expectedChange, $callable($inputChange));

        $inputUntouched = 'Okay';
        $expectedUntouched = 'Okay';

        static::assertEquals($expectedUntouched, $callable($inputUntouched));

        $inputAccent = 'éllo';
        $expectedAccent = 'Éllo';

        static::assertEquals($expectedAccent, $callable($inputAccent));
    }
}
