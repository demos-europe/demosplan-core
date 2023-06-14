<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity;

use DemosEurope\DemosplanAddon\Contracts\Entities\CoreEntityInterface;

class CoreEntity implements CoreEntityInterface
{
    /**
     * The Database field to store content change of an Entity, is modeled as "text".
     * Therefore string, int or bool can be (casted and) stored.
     *
     * At least the ID of the object will be returned.
     *
     * @return string|int|bool
     */
    public function getEntityContentChangeIdentifier()
    {
        if (method_exists($this, 'getDisplayId')) {
            return $this->getDisplayId();
        }

        if (method_exists($this, 'getExternId')) {
            return $this->getExternId();
        }

        if (method_exists($this, 'getName')) {
            return $this->getName();
        }

        if (method_exists($this, 'getTitle')) {
            return $this->getTitle();
        }

        if (method_exists($this, 'getId')) {
            return $this->getId();
        }

        return '';
    }

    /*
     *
     * When you try to extend CoreEntity from demosplan\DemosPlanCoreBundle\Logic\ArrayObject:
     *                              HERE BE DRAGONS
     *
     * When overriding serialize() and unserialize() to return empty values
     * I see e.g. the effect, that doctrine returns only organisationIds attached
     * by ManyToMany to Procedure instead of Objects on second request
     *
     * When not overriding serialize() and unserialize() the effect is, that on serializing the session
     * the fatal error "You cannot serialize or unserialize PDO instances" occurs
     */
}
