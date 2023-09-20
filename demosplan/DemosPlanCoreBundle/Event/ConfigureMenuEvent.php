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
    public function __construct(private readonly string $menuName, private readonly FactoryInterface $factory, private readonly ItemInterface $menu)
    {
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
