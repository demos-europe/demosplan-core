<?php

declare(strict_types=1);

namespace Tests\Core\Core\Functional;

use demosplan\DemosPlanCoreBundle\Logic\FormOptionsResolver;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfigInterface;
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
