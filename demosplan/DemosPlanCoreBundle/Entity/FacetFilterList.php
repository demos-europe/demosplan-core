<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity;

class FacetFilterList
{
    /**
     * @var array Filter
     */
    protected $list = [];

    /**
     * @var array Filter
     */
    protected $activeFiltersList = [];

    /**
     * @return array
     */
    public function getList()
    {
        return $this->list;
    }

    /**
     * @return array
     */
    public function getActiveFiltersList()
    {
        return $this->activeFiltersList;
    }

    public function addFilter(FacetFilter $filter)
    {
        if ($filter->isActive()) {
            $this->activeFiltersList[$filter->getFilterName()] = $filter->getValue();
        }

        // Der Filter ist noch gar nicht in der Liste
        $filterSet = isset($this->list[$filter->getFilterName()]);
        $entitySet = isset($this->list[$filter->getFilterName()][$filter->getEntityId()]);
        if (!$filterSet || !$entitySet) {
            $filter->setCount(1);
            $this->list[$filter->getFilterName()][$filter->getEntityId()] = $filter;

            return;
        }

        // Der Filter ist registriert, erhöhe den Count um einen
        if (true === $entitySet) {
            $filter = $this->list[$filter->getFilterName()][$filter->getEntityId()];
            $filter->setCount($filter->getCount() + 1);
        }
    }
}
