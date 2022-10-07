<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Plugin\Unit;

use demosplan\DemosPlanPluginBundle\Exception\ManagePluginException;
use Tests\Base\FunctionalTestCase;

class PluginListTest extends FunctionalTestCase
{
    /** @var \demosplan\DemosPlanPluginBundle\Logic\PluginList */
    protected $sut;

    protected $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheDir = $this->getContainer()->getParameter('kernel.cache_dir');

        // delete maybe existing plugin.yml
        $fs = new \Symfony\Component\Filesystem\Filesystem();
        $fs->remove($this->cacheDir.'/app/config/plugins.yml');

        $this->sut = new \demosplan\DemosPlanPluginBundle\Logic\PluginList();
        $this->sut->setPluginsFilePath($this->cacheDir);
        $dplanRootDir = dirname(dirname(dirname($this->getContainer()->getParameter('kernel.root_dir'))));
        $this->sut->setDplanRootPath($dplanRootDir);
    }

    public function testGetActivatedPluginsCoreOnly()
    {
        $plugins = $this->sut->getEnabledPlugins();
        static::assertInstanceOf(\Tightenco\Collect\Support\Collection::class, $plugins);
        static::assertEquals(1, $plugins->count());
        static::assertEquals('demosplan\plugins\workflow\SegmentsManager\SegmentsManager', $plugins->first());
    }

    public function testGetAllPlugins()
    {
        $plugins = $this->sut->getAllPlugins();
        static::assertInstanceOf(\Tightenco\Collect\Support\Collection::class, $plugins);
        static::assertGreaterThan(3, $plugins->count());
        $plugins = $plugins->values()->all();
        static::assertEquals('BimschgAntrag', $plugins[0]['name']);
        // any project plugin may be last as list is sorted case sensitively and regular Plugins
        // should start with Uppercase, project Plugins has prefix projects/[Pluginname]
        static::assertNotFalse(mb_stripos($plugins[count($plugins) - 1]['name'], 'projects/'));
    }

    public function testGetAllPluginsStructure()
    {
        $plugins = $this->sut->getAllPlugins();
        $plugins = $plugins->values()->all();
        static::assertArrayHasKey('name', $plugins[0]);
        static::assertArrayHasKey('description', $plugins[0]);
        static::assertArrayHasKey('version', $plugins[0]);
        static::assertArrayHasKey('enabled', $plugins[0]);
    }

    public function testAddPlugin()
    {
        $plugins = $this->sut->getEnabledPlugins();
        static::assertEquals(1, $plugins->count());

        $activated = $this->sut->enablePlugin('ExamplePlugin');
        static::assertTrue($activated);
        $plugins = $this->sut->getEnabledPlugins();
        static::assertEquals(2, $plugins->count());
    }

    public function testAddNotExistingPlugin()
    {
        $this->expectException(ManagePluginException::class);

        $this->sut->enablePlugin('NotExistantPlugin');
    }

    public function testAddAlreadyEnabledPlugin()
    {
        $this->expectException(ManagePluginException::class);

        $this->sut->enablePlugin('ExamplePlugin');
        $this->sut->enablePlugin('ExamplePlugin');
    }

    public function testRemovePlugin()
    {
        $this->sut->enablePlugin('ExamplePlugin');
        $plugins = $this->sut->getEnabledPlugins();
        static::assertEquals(2, $plugins->count());

        $disabled = $this->sut->disablePlugin('ExamplePlugin');
        static::assertTrue($disabled);
        $plugins = $this->sut->getEnabledPlugins();
        static::assertEquals(1, $plugins->count());
    }

    public function testRemoveNotExistingPlugin()
    {
        $this->expectException(ManagePluginException::class);

        $this->sut->disablePlugin('NotExistantPlugin');
    }

    public function testRemoveAlreadyRemovedPlugin()
    {
        $this->expectException(ManagePluginException::class);

        $this->sut->enablePlugin('ExamplePlugin');
        $this->sut->disablePlugin('ExamplePlugin');
        // test did not fail yet
        static::assertTrue(true);
        $this->sut->disablePlugin('ExamplePlugin');
    }

    public function testRemoveNotEnabledPlugin()
    {
        $this->expectException(ManagePluginException::class);

        $this->sut->enablePlugin('XBau');
        $this->sut->disablePlugin('ExamplePlugin');
    }

    public function testIsValidPlugin()
    {
        $isValidPlugin = $this->sut->isValidPlugin('ExamplePlugin');
        static::assertTrue($isValidPlugin);

        $isValidPlugin = $this->sut->isValidPlugin('projects/BTHGWegewerk');
        static::assertTrue($isValidPlugin);

        $isValidPlugin = $this->sut->isValidPlugin('NotExistant');
        static::assertFalse($isValidPlugin);

        $isValidPlugin = $this->sut->isValidPlugin('projects/NotExistant');
        static::assertFalse($isValidPlugin);
    }

    public function testHasEnabledPlugin()
    {
        $isEnabled = $this->sut->hasEnabledPlugin('ExamplePlugin');
        static::assertFalse($isEnabled);

        $this->sut->enablePlugin('ExamplePlugin');

        $isEnabled = $this->sut->hasEnabledPlugin('ExamplePlugin');
        static::assertTrue($isEnabled);
    }

    public function testGetCorePluginsDisabledByDefault()
    {
        $plugins = $this->sut->getCorePluginsDisabledByDefault();
        static::assertInstanceOf(\Tightenco\Collect\Support\Collection::class, $plugins);
        static::assertEquals(0, $plugins->count());
    }
}
