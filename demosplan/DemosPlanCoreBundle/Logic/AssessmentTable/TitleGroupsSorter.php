<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\AssessmentTable;

use demosplan\DemosPlanCoreBundle\Logic\Grouping\EntityGroupInterface;

/**
 * Class TitleGroupsSorter
 * <p>
 * Sorts {@link EntityGroupInterface} instances by their title (case insensitive).
 */
class TitleGroupsSorter implements ArraySorterInterface
{
    /**
     * @param EntityGroupInterface[] $array
     *
     * @return EntityGroupInterface[]
     */
    public function sortArray(array $array): array
    {
        uasort(
            $array,
            static function (EntityGroupInterface $a, EntityGroupInterface $b) {
                return strcasecmp($a->getTitle(), $b->getTitle());
            }
        );

        return $array;
    }
}
