<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Export;

use JsonException;

/**
 * Identifies an export request by its inputs, so a repeated request can be recognised as the same
 * job instead of queueing a second one.
 *
 * A background export gives the user no progress feedback, so an export that takes minutes invites
 * re-triggering. Without this, every click produces another job on a strictly serial worker, and
 * the client only keeps a handle to the most recent one — earlier jobs are still built, then never
 * collected.
 */
final class ExportJobFingerprint
{
    /**
     * The filter hash list is part of the fingerprint because it decides *which* statements are
     * exported and is not represented in the export parameters. Two exports whose parameters are
     * byte-identical are different exports when the filter changed in between.
     *
     * @param array<string, mixed> $parameters export parameters as sent to the exporter
     * @param array<string, mixed> $hashList   session filter hash list
     */
    public static function forAssessmentTable(array $parameters, array $hashList): string
    {
        return self::hash([$parameters, $hashList]);
    }

    /**
     * The selection is sorted so that picking the same procedures in a different order still counts
     * as the same export.
     *
     * @param array<int, string> $procedureIds
     */
    public static function forProcedureSelection(array $procedureIds, bool $useExternalProcedureName): string
    {
        $sorted = array_values($procedureIds);
        sort($sorted);

        return self::hash([$sorted, $useExternalProcedureName]);
    }

    /**
     * @param array<int, mixed> $input
     */
    private static function hash(array $input): string
    {
        $canonical = self::canonicalise($input);

        try {
            $encoded = json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            // Unencodable input must not collide with a real fingerprint, so fall back to a value
            // that never matches an existing job and simply lets the export through.
            $encoded = serialize($canonical);
        }

        return hash('sha256', $encoded);
    }

    /**
     * Sorts nested arrays by key so that differing insertion order — which request and session
     * arrays do not guarantee — cannot change the hash.
     */
    private static function canonicalise(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $canonical = array_map(self::canonicalise(...), $value);

        if (!array_is_list($canonical)) {
            ksort($canonical);
        }

        return $canonical;
    }
}
