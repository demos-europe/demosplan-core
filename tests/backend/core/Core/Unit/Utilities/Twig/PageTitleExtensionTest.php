<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Utilities\Twig;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Twig\Extension\PageTitleExtension;
use demosplan\DemosPlanCoreBundle\Twig\Extension\ProcedureExtension;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Base\MockMethodDefinition;
use Tests\Base\UnitTestCase;

/**
 * Teste DateExtension
 * Class DateExtensionTest.
 *
 * @group UnitTest
 */
class PageTitleExtensionTest extends UnitTestCase
{
    /**
     * @var PageTitleExtension
     */
    private $twigExtension;
    private $translatedVariable = 'Translated value';

    public function setUp(): void
    {
        parent::setUp();
    }

    public function testPageTitle()
    {
        $this->createSut(false);

        $result = $this->twigExtension->pageTitle('someKey');
        self::assertEquals($this->translatedVariable.' | project pagetitle', $result);

        $result = $this->twigExtension->pageTitle('');
        self::assertEquals('project pagetitle', $result);

        $this->createSut();
        $result = $this->twigExtension->pageTitle('someKey');
        self::assertEquals('Translated value | Procedure Name | project pagetitle', $result);

        $result = $this->twigExtension->pageTitle('');
        self::assertEquals('Procedure Name | project pagetitle', $result);
    }

    public function testBreadcrumbTitle()
    {
        $this->createSut(false);
        $result = $this->twigExtension->breadcrumbTitle('someKey');
        self::assertEquals($this->translatedVariable, $result);

        $this->createSut(false, '');
        $result = $this->twigExtension->breadcrumbTitle('');
        self::assertEquals('', $result);

        $this->createSut();
        $result = $this->twigExtension->breadcrumbTitle('someKey');
        self::assertEquals($this->translatedVariable, $result);

        $this->createSut(true, '');
        $result = $this->twigExtension->breadcrumbTitle('');
        self::assertEquals('', $result);
    }

    private function createSut(bool $inProcedure = true, string $transReturn = 'Translated value'): void
    {
        $globalConfig = $this->createStub(GlobalConfigInterface::class);
        $globalConfig->method('getProjectName')
            ->willReturn('project name');
        $globalConfig->method('getProjectPagetitle')
            ->willReturn('project pagetitle');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')
            ->willReturn($transReturn);

        $procedureExtension = $this->createStub(ProcedureExtension::class);
        $procedureExtension->method('getNameFunction')
            ->willReturn('Procedure Name');

        $mockMethods = [
            new MockMethodDefinition('getProcedure', $inProcedure ? $this->fixtures->getReference(LoadProcedureData::TESTPROCEDURE) : null),
        ];
        $currentProcedure = $this->getMock(CurrentProcedureService::class, $mockMethods);

        $this->twigExtension = new PageTitleExtension(
            self::getContainer(),
            $globalConfig,
            $translator,
            $currentProcedure,
            $procedureExtension
        );
    }
}
