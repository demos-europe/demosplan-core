<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Procedure;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use TypeError;

/**
 * Interface ProcedureSorterInterface.
 */
interface ProcedureSorterInterface
{
    /**
     * Sorts the given array of procedures.
     * <p>
     * The sorting will return a new array containing the sorted items with the given array remaining unchanged.
     *
     * @param array $procedures Array of procedures. Each procedure is an array with properties.
     *
     * @return array the sorted procedures
     *
     * @throws TypeError thrown if the given $procedures is null or not of type array
     */
    public function sortLegacyArrays(array $procedures): array;

    /**
     * Sorts the given array of procedures.
     * <p>
     * The sorting will return a new array containing the sorted items with the given array remaining unchanged.
     *
     * @param Procedure[] $procedures Array of procedures. Each procedure is an instance of an Procedure entity object.
     *
     * @return Procedure[] the sorted procedures
     *
     * @throws TypeError thrown if the given $procedures is null or not of type array
     */
    public function sortEntities(array $procedures): array;
}
