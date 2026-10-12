<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Addon;

use DemosEurope\DemosplanAddon\Permission\PermissionEvaluatorInterface;
use DemosEurope\DemosplanAddon\Permission\PermissionIdentifierInterface;
use DemosEurope\DemosplanAddon\Permission\PermissionInitializerInterface;
use demosplan\DemosPlanCoreBundle\Addon\AddonInfo;
use demosplan\DemosPlanCoreBundle\Addon\AddonRegistry;
use demosplan\DemosPlanCoreBundle\Addon\FrontendAssetProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The system under test needs neither container nor database, so this is a plain unit test.
 *
 * @group UnitTest
 */
class FrontendAssetProviderTest extends TestCase
{
    private const ADDON_NAME = 'demos-europe/demosplan-addon-fixture';
    private const HOOK_NAME = 'import.tabs';
    private const INSTALL_PATH = 'tests/backend/core/Core/res/frontendAssetProvider/addon';
    private const ADDON_PERMISSION = 'feature_fixture_addon_permission';
    private const CORE_PERMISSION = 'feature_fixture_core_permission';

    protected ?FrontendAssetProvider $sut = null;

    /**
     * @var (PermissionEvaluatorInterface&MockObject)|null
     */
    private ?PermissionEvaluatorInterface $permissionEvaluator = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->permissionEvaluator = $this->createMock(PermissionEvaluatorInterface::class);
    }

    public function testHookWithoutPermissionsIsDeliveredWithoutAnyPermissionCheck(): void
    {
        $this->createSut($this->createAddonInfo([]));

        $this->permissionEvaluator->expects(self::never())->method('isPermissionEnabled');

        $assets = $this->sut->getFrontendClassesForHook(self::HOOK_NAME);

        self::assertSame(['TestEntry'], $this->getDeliveredEntries($assets));
    }

    /**
     * A permission declared by the addon itself lives in a collection of its own. It has to be
     * looked up with the owning addon as identifier, otherwise the core collection is searched
     * and the hook is silently dropped even though the user is allowed to see it.
     */
    public function testAddonPermissionIsResolvedWithTheOwningAddonAsIdentifier(): void
    {
        $this->createSut($this->createAddonInfo(['permissions' => [self::ADDON_PERMISSION]]));

        $this->expectPermissionKnownAsAddonPermission(self::ADDON_PERMISSION, true);
        $this->permissionEvaluator->expects(self::once())
            ->method('isPermissionEnabled')
            ->with(self::callback(
                fn (PermissionIdentifierInterface $identifier) => self::ADDON_NAME === $identifier->getAddonIdentifier()
                    && self::ADDON_PERMISSION === $identifier->getPermissionName()
            ))
            ->willReturn(true);

        $assets = $this->sut->getFrontendClassesForHook(self::HOOK_NAME);

        self::assertSame(['TestEntry'], $this->getDeliveredEntries($assets));
    }

    public function testDisabledAddonPermissionOmitsTheHook(): void
    {
        $this->createSut($this->createAddonInfo(['permissions' => [self::ADDON_PERMISSION]]));

        $this->expectPermissionKnownAsAddonPermission(self::ADDON_PERMISSION, true);
        $this->permissionEvaluator->method('isPermissionEnabled')->willReturn(false);

        self::assertSame([], $this->sut->getFrontendClassesForHook(self::HOOK_NAME));
    }

    /**
     * Hooks may also be guarded by core permissions, which carry no addon identifier.
     */
    public function testPermissionUnknownToTheAddonIsResolvedAgainstTheCore(): void
    {
        $this->createSut($this->createAddonInfo(['permissions' => [self::CORE_PERMISSION]]));

        $this->expectPermissionKnownAsAddonPermission(self::CORE_PERMISSION, false);
        $this->permissionEvaluator->expects(self::once())
            ->method('isPermissionEnabled')
            ->with(self::callback(
                fn (PermissionIdentifierInterface $identifier) => null === $identifier->getAddonIdentifier()
                    && self::CORE_PERMISSION === $identifier->getPermissionName()
            ))
            ->willReturn(true);

        $assets = $this->sut->getFrontendClassesForHook(self::HOOK_NAME);

        self::assertSame(['TestEntry'], $this->getDeliveredEntries($assets));
    }

    public function testAnyOfTheConfiguredPermissionsSufficesToDeliverTheHook(): void
    {
        $this->createSut($this->createAddonInfo([
            'permissions' => [self::CORE_PERMISSION, self::ADDON_PERMISSION],
        ]));

        $this->permissionEvaluator->method('isPermissionKnown')->willReturnCallback(
            fn (PermissionIdentifierInterface $identifier) => self::ADDON_PERMISSION === $identifier->getPermissionName()
        );
        $this->permissionEvaluator->method('isPermissionEnabled')->willReturnCallback(
            fn (PermissionIdentifierInterface $identifier) => self::ADDON_PERMISSION === $identifier->getPermissionName()
        );

        $assets = $this->sut->getFrontendClassesForHook(self::HOOK_NAME);

        self::assertSame(['TestEntry'], $this->getDeliveredEntries($assets));
    }

    public function testDisabledAddonIsSkipped(): void
    {
        $this->createSut($this->createAddonInfo([], enabled: false));

        self::assertSame([], $this->sut->getFrontendClassesForHook(self::HOOK_NAME));
    }

    public function testUnrequestedHookIsNotDelivered(): void
    {
        $this->createSut($this->createAddonInfo([]));

        self::assertSame([], $this->sut->getFrontendClassesForHook('some.other.hook'));
    }

    public function testEntryWithoutJavascriptIsNotDelivered(): void
    {
        $this->createSut($this->createAddonInfo([], entry: 'StylesOnlyEntry'));

        self::assertSame([], $this->sut->getFrontendClassesForHook(self::HOOK_NAME));
    }

    public function testEntryMissingFromTheManifestIsNotDelivered(): void
    {
        $this->createSut($this->createAddonInfo([], entry: 'NotInTheManifest'));

        self::assertSame([], $this->sut->getFrontendClassesForHook(self::HOOK_NAME));
    }

    public function testMissingManifestIsNotDelivered(): void
    {
        $this->createSut($this->createAddonInfo([], installPath: self::INSTALL_PATH.'/does-not-exist'));

        self::assertSame([], $this->sut->getFrontendClassesForHook(self::HOOK_NAME));
    }

    private function createSut(AddonInfo $addonInfo): void
    {
        $registry = new AddonRegistry();
        $registry->boot([$addonInfo]);

        $this->sut = new FrontendAssetProvider($this->permissionEvaluator, $registry);
    }

    /**
     * @param array<string, mixed> $hookOptions
     */
    private function createAddonInfo(
        array $hookOptions,
        bool $enabled = true,
        string $entry = 'TestEntry',
        string $installPath = self::INSTALL_PATH,
    ): AddonInfo {
        return new AddonInfo(
            self::ADDON_NAME,
            [
                'enabled'      => $enabled,
                'install_path' => $installPath,
                'manifest'     => [
                    'ui' => [
                        'manifest' => 'dist/assets-manifest.json',
                        'hooks'    => [
                            self::HOOK_NAME => [
                                'entry'   => $entry,
                                'options' => $hookOptions,
                            ],
                        ],
                    ],
                ],
            ],
            $this->createMock(PermissionInitializerInterface::class)
        );
    }

    private function expectPermissionKnownAsAddonPermission(string $permissionName, bool $known): void
    {
        $this->permissionEvaluator->expects(self::once())
            ->method('isPermissionKnown')
            ->with(self::callback(
                fn (PermissionIdentifierInterface $identifier) => self::ADDON_NAME === $identifier->getAddonIdentifier()
                    && $permissionName === $identifier->getPermissionName()
            ))
            ->willReturn($known);
    }

    /**
     * @param array<string, array<string, mixed>> $assets
     *
     * @return list<string>
     */
    private function getDeliveredEntries(array $assets): array
    {
        return array_values(array_map(static fn (array $asset) => $asset['entry'], $assets));
    }
}
