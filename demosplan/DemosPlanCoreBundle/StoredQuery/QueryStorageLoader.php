<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\StoredQuery;

/**
 * Handle loading and saving StoredQueryInterface in QuerySet.
 */
class QueryStorageLoader
{
    private const QUERY_CLASSES = [
        AssessmentTableQuery::class,
        SegmentListQuery::class,
    ];

    /**
     * Resolves the QuerySet fields `query` and `queryFormat`
     * to the corresponding implementation of
     * `StoredQueryInterface`.
     */
    public function loadFromJson(
        array $json,
        string $queryFormat
    ): StoredQueryInterface {
        return collect(self::QUERY_CLASSES)
            ->map(
                static function (string $queryClass) {
                    // explicitly switch the classes to get IDE-findable class uses
                    // this is not a permanent solution but solely due to this being
                    // used in an entity where we do not (want) to enable proper DI
                    $query = null;

                    switch ($queryClass) {
                        case AssessmentTableQuery::class:
                            $query = new AssessmentTableQuery();
                            break;

                        case SegmentListQuery::class:
                            $query = new SegmentListQuery();
                            break;
                    }

                    return $query;
                }
            )
            ->filter(
                static function (StoredQueryInterface $query) use (
                    $queryFormat
                ) {
                    return $query->getFormat() === $queryFormat;
                }
            )
            ->map(
                static function (StoredQueryInterface $query) use (
                    $json
                ) {
                    $query->fromJson($json);

                    return $query;
                }
            )
            ->first();
    }
}
