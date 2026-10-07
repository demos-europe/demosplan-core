<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\DependencyInjection;

use demosplan\DemosPlanCoreBundle\DependencyInjection\Configuration\MenusTreeBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

class MenusTreeBuilderTest extends TestCase
{
    public function testAddonMenuEntryIsMergedIntoTheMenuOfTheCore(): void
    {
        // Arrange: the menu of the core and the menu entry an addon brings
        $coreMenu = [
            'sidemenu' => [
                'administer' => [
                    'label'      => 'administer',
                    'path'       => null,
                    'permission' => 'area_preferences',
                    'children'   => [
                        'organisations' => [
                            'label'      => 'organisations',
                            'path'       => 'DemosPlan_orga_list',
                            'permission' => 'area_organisations',
                        ],
                    ],
                ],
            ],
        ];
        $addonMenu = [
            'sidemenu' => [
                'administer' => [
                    'children' => [
                        'addon_page' => [
                            'label'       => 'addon.page',
                            'path'        => 'DemosPlan_addon_page',
                            'path_params' => ['hookName' => 'addon.hook'],
                            'permission'  => 'feature_of_the_addon',
                            'addon'       => 'vendor/addon',
                        ],
                    ],
                ],
            ],
        ];

        // Act
        $merged = (new Processor())->processConfiguration(new MenusTreeBuilder(), [$coreMenu, $addonMenu]);

        // Assert
        $children = $merged['sidemenu']['administer']['children'];
        self::assertSame(['organisations', 'addon_page'], array_keys($children));
        self::assertSame('vendor/addon', $children['addon_page']['addon']);
        self::assertSame(['hookName' => 'addon.hook'], $children['addon_page']['path_params']);
        self::assertArrayNotHasKey('addon', $children['organisations']);
    }
}
