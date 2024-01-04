<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use DemosEurope\DemosplanAddon\Contracts\Repositories\EmailAddressRepositoryInterface;
use demosplan\DemosPlanCoreBundle\Entity\EmailAddress;

/**
 * @template-extends CoreRepository<EmailAddress>
 */
class EmailAddressRepository extends CoreRepository implements EmailAddressRepositoryInterface
{
    /**
     * @param string[] $inputEmailAddressStrings
     *
     * @return EmailAddress[] emailAddress entities with the given $inputEmailAddressString as
     *                        keys in the array and stored as their fullAddresses
     */
    public function getOrCreateEmailAddresses(array $inputEmailAddressStrings): array
    {
        $foundEmailAddressEntities = $this->findBy(['fullAddress' => $inputEmailAddressStrings]);
        $foundEmailAddressStrings = array_map(static fn (EmailAddress $emailAddress) => $emailAddress->getFullAddress(), $foundEmailAddressEntities);

        $newEmailAddressStrings = array_diff($inputEmailAddressStrings, $foundEmailAddressStrings);
        $newEmailAddressEntities = array_map(static function (string $emailAddressString) {
            $emailAddressEntity = new EmailAddress();
            $emailAddressEntity->setFullAddress($emailAddressString);

            return $emailAddressEntity;
        }, $newEmailAddressStrings);

        $mergedEmailAddressEntities = array_merge($foundEmailAddressEntities, $newEmailAddressEntities);

        return $this->sortByGivenArray($inputEmailAddressStrings, $mergedEmailAddressEntities);
    }

    public function getOrCreateEmailAddress(string $inputEmailAddressString): EmailAddress
    {
        $foundEmailAddressEntity = $this->findOneBy(['fullAddress' => $inputEmailAddressString]);
        if (null === $foundEmailAddressEntity) {
            $emailAddressEntity = new EmailAddress();
            $emailAddressEntity->setFullAddress($inputEmailAddressString);

            return $emailAddressEntity;
        }

        return $foundEmailAddressEntity;
    }

    public function deleteOrphanEmailAddresses(array $emailIds): int
    {
        $connection = $this->getEntityManager()->getConnection();

        $emailIdsCount = count($emailIds);
        if (0 === $emailIdsCount) {
            return $connection->exec(
                'DELETE e'
                .' FROM email_address AS e'
                .' LEFT JOIN procedure_agency_extra_email_address  AS p  ON p.email_address_id = e.id'
                .' WHERE p.procedure_id   IS NULL'
            );
        } else {
            $emailIdsString = array_fill(0, $emailIdsCount, '?');
            $emailIdsString = implode(',', $emailIdsString);

            return $connection->executeStatement(
                'DELETE e'
                .' FROM email_address AS e'
                .' LEFT JOIN procedure_agency_extra_email_address  AS p  ON p.email_address_id = e.id'
                .' WHERE p.procedure_id   IS NULL'
                .' AND e.id NOT IN ('.$emailIdsString.')', $emailIds
            );
        }
    }

    /**
     * Returns an array containing the values in $sortedStrings as keys. The value for each key
     * is the corresponding EmailAddress instance with the key as fullAddress.
     *
     * If no corresponding EmailAddress exists the value in the returned array will be null.
     * EmailAddress instances in the given $unsortedArray that have no corresponding value in
     * the given $sortedStrings array are omitted from the return.
     *
     * @param string[]       $sortedStrings    the values in this array are used as keys in the
     *                                         returned array
     * @param EmailAddress[] $unsortedEntities The entities to assign to the given $sortedStrings
     *
     * @return EmailAddress[]|null[]
     */
    protected function sortByGivenArray(array $sortedStrings, array $unsortedEntities): array
    {
        $sortedEmailAddresses = array_fill_keys($sortedStrings, null);
        foreach ($unsortedEntities as $emailAddressEntity) {
            $fullAddress = $emailAddressEntity->getFullAddress();
            $sortedEmailAddresses[$fullAddress] = $emailAddressEntity;
        }

        return $sortedEmailAddresses;
    }
}
