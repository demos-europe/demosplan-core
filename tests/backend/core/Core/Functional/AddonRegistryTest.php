<?php
declare(strict_types=1);

namespace Tests\Core\Core\Functional;

use DemosEurope\DemosplanAddon\Permission\PermissionInitializerInterface;
use demosplan\DemosPlanCoreBundle\Addon\AddonInfo;
use demosplan\DemosPlanCoreBundle\Addon\AddonRegistry;
use demosplan\DemosPlanCoreBundle\Exception\AddonException;
use Tests\Base\FunctionalTestCase;

class AddonRegistryTest extends FunctionalTestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = new AddonRegistry();
    }

    public function testBootEmpty(): void
    {
        $this->sut->boot([]);
        self::assertEquals([], $this->sut->getAddonInfos());
    }

    public function testBootMethod(): void
    {
        $addonInfos = $this->getAddonInfoFixture();
        $this->sut->boot($addonInfos);
        $this->assertEquals($addonInfos, $this->sut->getAddonInfos());
    }

    public function testGetInstalledAndEnabledAddons(): void
    {
        $addonInfos = $this->getAddonInfoFixture();

        $this->sut->boot($addonInfos);

        $installedAddons = $this->sut->getInstalledAddons();
        $this->assertIsArray($installedAddons);

        $this->assertCount(2, $installedAddons);
        $this->assertEquals($addonInfos, $installedAddons);

        $enabledAddons = $this->sut->getEnabledAddons();
        $this->assertIsArray($enabledAddons);

        $this->assertCount(1, $enabledAddons);
        $this->assertEquals($addonInfos['addon1'], $enabledAddons['addon1']);
    }


    public function testOffsetExists(): void
    {
        $addonInfos = $this->getAddonInfoFixture();

        $this->sut->boot($addonInfos);

        $this->assertTrue($this->sut->offsetExists('addon1'));
        $this->assertTrue($this->sut->offsetExists('addon2'));
    }
    public function testOffsetGet(): void
    {
        $addonInfos = $this->getAddonInfoFixture();

        $this->sut->boot($addonInfos);

        $this->assertEquals($addonInfos['addon1'], $this->sut->offsetGet('addon1'));
    }
    public function testOffsetSet(): void
    {
        $addonInfos = $this->getAddonInfoFixture();

        $this->sut->boot($addonInfos);
        $this->expectException(AddonException::class);

        $this->sut->offsetSet('addon1', 'anything');
    }
    public function testOffsetUnset(): void
    {
        $addonInfos = $this->getAddonInfoFixture();

        $this->sut->boot($addonInfos);
        $this->expectException(AddonException::class);

        $this->sut->offsetUnset('addon1');
    }

    public function testBootImmutable(): void
    {
        $addonInfos = $this->getAddonInfoFixture();
        $this->sut->boot($addonInfos);
        $this->expectException(AddonException::class);
        $this->sut->boot($addonInfos);
    }

    private function getAddonInfoFixture(): array
    {
        return [
            'addon1' =>
                new AddonInfo(
                    'addon1', ['enabled' => true],
                    $this->createMock(PermissionInitializerInterface::class)
                ),
            'addon2' =>
                new AddonInfo(
                    'addon2', ['enabled' => false],
                    $this->createMock(PermissionInitializerInterface::class)
                ),
        ];
    }
}
