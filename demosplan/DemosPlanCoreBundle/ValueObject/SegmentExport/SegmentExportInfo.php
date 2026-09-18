<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\SegmentExport;

use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;

/**
 * @method string     getSearchPhrase()
 * @method array|null getTagNames()
 * @method array|null getAssigneeNames()
 * @method array|null getPlaceNames()
 * @method array|null getSelectedColumnKeys()
 * @method bool       getIsManualSelection()
 * @method bool       getIsFiltered()
 */
class SegmentExportInfo extends ValueObject
{
    protected readonly bool $isFiltered;

    public function __construct(
        protected readonly ?string $searchPhrase,
        protected readonly ?array $tagNames,
        protected readonly ?array $assigneeNames,
        protected readonly ?array $placeNames,
        protected readonly ?array $selectedColumnKeys,
        protected readonly bool $isManualSelection,
    ) {
        $this->isFiltered = null !== $searchPhrase
            || null !== $tagNames
            || null !== $assigneeNames
            || null !== $placeNames
            || $isManualSelection;

        $this->lock();
    }
}
