<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\OzyKeycloakDataMapper;

class OrganisationAffiliationMapper
{
    /**
     * Compute the cartesian product of affiliations × responsibilities.
     * Organisation (affiliations) is always >= 1, responsibilities is 0..n.
     *
     * Fallback rules:
     * 1. Both present → cartesian product (gwId = aff.id + '|' + resp.id)
     * 2. Only affiliations (no responsibilities) → use affiliations alone
     * 3. No affiliations → empty array (caller falls back to organisationId)
     *
     * @param array<int, array{id: string, name: string}> $affiliations
     * @param array<int, array{id: string, name: string}> $responsibilities
     *
     * @return array<int, array{gwId: string, name: string}>
     */
    public function buildOrganisationEntries(array $affiliations, array $responsibilities): array
    {
        if ([] === $affiliations) {
            return [];
        }

        // Affiliations + responsibilities → cartesian product
        if ([] !== $responsibilities) {
            $entries = [];
            foreach ($affiliations as $aff) {
                foreach ($responsibilities as $resp) {
                    $entries[] = [
                        'gwId' => $aff['id'].'|'.$resp['id'],
                        'name' => $aff['name'].' - '.$resp['name'],
                    ];
                }
            }

            return $entries;
        }

        // Affiliations only (no responsibilities)
        return array_map(static fn (array $a): array => ['gwId' => $a['id'], 'name' => $a['name']], $affiliations);
    }
}
