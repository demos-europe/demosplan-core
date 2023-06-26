<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Traits;

/**
 * Provide a standard way to transform request variables for
 * multiform post data, e.g. lists of editable users, statements, etc.
 *
 * The expected form format is
 *
 * <code>
 *   <form>
 *      <input name="elementid:inputname">
 *   </form>
 * </code>
 *
 * where the elementid will usually map on an existing entity id.
 */
trait CanTransformRequestVariablesTrait
{
    /**
     * @param array $requestData Usually ParameterBag::all()
     */
    protected function transformRequestVariables(array $requestData): array
    {
        $transformedRequestData = [];

        foreach ($requestData as $key => $value) {
            if (strpos($key, ':') > 0) {
                [$ident, $keyName] = explode(':', $key);

                $transformedRequestData[$ident][$keyName] = $value;
            } elseif (0 === strpos($key, ':')) {
                $transformedRequestData[substr($key, 1)] = $value;
            } else {
                $transformedRequestData[$key] = $value;
            }
        }

        return $transformedRequestData;
    }
}
