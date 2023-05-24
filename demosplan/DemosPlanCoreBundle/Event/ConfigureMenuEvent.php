<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event;

use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;

class ConfigureMenuEvent extends DPlanEvent
{
    private $menuName;
    /** @var FactoryInterface */
    private $factory;
    /** @var ItemInterface */
    private $menu;

    public function __construct(string $menuName, FactoryInterface $factory, ItemInterface $menu)
    {
        $this->menuName = $menuName;
        $this->factory = $factory;
        $this->menu = $menu;
    }

    public function getFactory(): FactoryInterface
    {
        return $this->factory;
    }

    public function getMenu(): ItemInterface
    {
        return $this->menu;
    }

    public function getMenuName(): string
    {
        return $this->menuName;
    }
}
