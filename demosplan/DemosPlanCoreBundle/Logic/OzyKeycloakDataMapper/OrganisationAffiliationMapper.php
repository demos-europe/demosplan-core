<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\OzyKeycloakDataMapper;

use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;

class OrganisationAffiliationMapper
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

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

    /**
     * Sync user's organisation links to match the given target set.
     * Adds missing links and removes stale ones no longer present in the token.
     *
     * @param array<int, Orga> $targetOrganisations
     */
    public function syncUserOrganisations(User $user, array $targetOrganisations): void
    {
        $targetIds = array_map(static fn (Orga $o): string => $o->getId(), $targetOrganisations);

        // Remove stale org links not in target set
        // Use unlinkUser/removeOrganisation to avoid setOrga()/unsetOrgas() side effects
        foreach ($user->getOrganisations()->toArray() as $currentOrga) {
            if (!in_array($currentOrga->getId(), $targetIds, true)) {
                $user->removeOrganisation($currentOrga);
                $currentOrga->unlinkUser($user);
                $this->entityManager->persist($currentOrga);
            }
        }

        // Add missing org links
        // Use linkUser/addOrganisation to avoid setOrga() overwriting the user's org collection
        foreach ($targetOrganisations as $orga) {
            if (!$user->getOrganisations()->contains($orga)) {
                $user->addOrganisation($orga);
                $orga->linkUser($user);
                $this->entityManager->persist($orga);
            }
        }

        $this->entityManager->persist($user);
    }
}
