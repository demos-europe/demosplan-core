<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Logic\FormOptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Base\FunctionalTestCase;
use Tests\Base\MockMethodDefinition;

class FormOptionsResolverTest extends FunctionalTestCase
{
    public function testResolve()
    {
        $transReturnValueExpected = 'Neu';
        $sut = $this->getSut($transReturnValueExpected);
        $resolvedValue = $sut->resolve(FormOptionsResolver::STATEMENT_STATUS, 'new');
        self::assertEquals($transReturnValueExpected, $resolvedValue);
    }

    protected function getSut(string $transReturnValue)
    {
        $mockMethods = [
            new MockMethodDefinition('getFormOptions', [
                FormOptionsResolver::STATEMENT_STATUS => [
                    'new'       => 'new',
                    'processing'=> 'processing',
                    'processed' => 'processed',
                ],
            ]),
        ];
        $globalConfig = $this->getMock(GlobalConfigInterface::class, $mockMethods);
        $mockMethodsTrans = [
            new MockMethodDefinition('trans', $transReturnValue),
        ];
        $trans = $this->getMock(TranslatorInterface::class, $mockMethodsTrans);

        return new FormOptionsResolver($globalConfig, $trans);
    }
}
