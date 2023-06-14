<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest;

use Symfony\Component\Validator\Constraints as Assert;

// @improve T13574

/**
 * Interfaces extending from this interface act as the data structure contract between frontend and
 * backend. An example usage may be the JSON:API, but they should be kept generic enough to be
 * usable for other API implementations as well like HTTP form POSTs.
 *
 * Extending interfaces should provide validation informations as well. See the existing sub
 * interfaces for examples.
 */
interface ResourceInterface
{
    /**
     * @Assert\Length(min=36, max=36)
     *
     * @return string|null
     */
    public function getId();

    /**
     * Get the methods returning a value that was actually set in the implementing class as callbacks.
     *
     * Use this method to determine which methods return null because the corresponding value was set to null and which
     * return null because their value was not set at all.
     *
     * One usage example is a POST request issued by a client where values for some properties of the resource were
     * provided to be used to update the resource. The properties for which no value was provided should be left
     * unchanged. However if a get method for a property for which no value was provided would simply return null it
     * would not be clear for the accessor if the property should be updated to null or left unchanged.
     *
     * @return string[] An array of mappings from the field key to the method name. You can use the method name to
     *                  dynamically access the method (eg. <code>$interface->$methodName()</code>), but you should avoid this, as it
     *                  makes debugging the usage of the getter methods pretty hard, time consuming and error prone.
     */
    public function getActiveGetters(): array;
}
