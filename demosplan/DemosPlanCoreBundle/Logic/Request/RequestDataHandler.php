<?php


/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Request;

class RequestDataHandler
{
    /**
     * @var array
     */
    protected $requestValues = [];

    /**
     * Definition der incoming Data.
     */
    protected function incomingDataDefinition()
    {
        return [];
    }

    /**
     * @param array $request
     */
    public function setRequestValues($request)
    {
        $this->requestValues = $request;
    }

    /**
     * @return array
     */
    public function getRequestValues()
    {
        return $this->requestValues;
    }

    /**
     * Filter incoming Datafields.
     *
     * @param string $action
     */
    public function prepareIncomingData($action): array
    {
        $result = [];

        $incomingFields = $this->incomingDataDefinition();

        $request = $this->getRequestValues();

        foreach ($incomingFields[$action] as $key) {
            if (array_key_exists($key, $request)) {
                $result[$key] = $request[$key];
            }
        }

        return $result;
    }
}
