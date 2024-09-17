<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20240108110342 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs T34551: alter property customer within the procedure table to determine customer related activity';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // alters the property customer for the procedure table
        // This property was used to indicate a blueprint that is just for a specific customer
        // Now this property shall indicate that the procedure is owned by an organisation within this customer
        // This is necessary to determine all customer related activity.
        // The logic to filter a customer-blueprint has to be adjusted to check the additional property _p_master
        // as the customer will always be set now

        // The assumption is that every organisation exists only once as a "Kommune".
        // Because only a "Kommune" can create procedures
        // the customer can be set for all procedures by their related organisation.
        // An exception are Anhörungsbehörden - they can also create procedures.
        // So if an organisation would be a Kommune in one customer and a Anhörungsbehörde in another customer - this system will fail.
        //
        // The only organisations that are "Kommune" for multiple customers are our own Test-Organisations.
        // For procedures created by our own Test-Organisations the first found customer can be set.

        // foreach customer we select the organisations that are
        // accepted as "Kommune" OLAUTH or "Anhörungsbehörde" OHAUTH
        $customerKommunes = $this->selectCustomerKommunes('accepted');
        // List Organisations that are accepted as "Kommune" in more than one customer
        // these are expected to be our test-Organisations!
        echo $this->transformKommunesOfMultipleCustomerIntoReadableOutput(
            $this->getKommunesOfMultipleCustomers($customerKommunes)
        );
        // proceed setting the customer property for procedures but do not overwrite existing relations
        $this->setCustomerRelations($customerKommunes);
        // if there are procedures left without customer relation - try to set the leftovers with now pending kommunes/Ahbs
        $pendingCustomerKommunes = $this->selectCustomerKommunes('pending');
        $this->setCustomerRelations($pendingCustomerKommunes);
        // if there are procedures left without customer relation - try to set the leftovers with now rejected kommunes/Ahbs
        $rejectedCustomerKommunes = $this->selectCustomerKommunes('rejected');
        $this->setCustomerRelations($rejectedCustomerKommunes);
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('UPDATE _procedure SET customer = NULL WHERE master_template = 0 AND _p_id NOT IN (SELECT c._procedure FROM customer c WHERE c._procedure IS NOT NULL)');
    }

    private function setCustomerRelations(array $customerKommunes): void
    {
        foreach ($customerKommunes as $customer) {
            foreach ($customer['kommunes'] as $kommuneId) {
                $this->addSql(
                    'UPDATE _procedure SET customer = :customerId
                        WHERE _o_id = :orgaId
                        AND (customer IS NULL OR customer = :emptyString)
                        AND master_template = 0',
                    ['customerId' => $customer['id'], 'orgaId' => $kommuneId, 'emptyString' => '']
                );
            }
        }
    }

    private function selectCustomerKommunes(string $status): array
    {
        $customerKommunes = [];
        $customers = $this->connection->fetchAllAssociative(
            'SELECT customer._c_id as id, customer._c_subdomain as shortage FROM customer'
        );
        foreach ($customers as $customer) {
            $kommunesQueryResult = $this->connection->fetchAllAssociative(
                'SELECT rcoot._o_id as id FROM relation_customer_orga_orga_type rcoot, _orga_type ot
                        WHERE rcoot._ot_id = ot._ot_id
                        AND (ot._ot_name = :olauth OR ot._ot_name = :ohauth)
                        AND rcoot._c_id = :customerId
                        AND rcoot.status = :status',
                ['customerId' => $customer['id'], 'status' => $status, 'olauth' => 'OLAUTH', 'ohauth' => 'OHAUTH']
            );
            $kommunes = [];
            foreach ($kommunesQueryResult as $kommune) {
                $kommunes[] = $kommune['id'];
            }
            $customer['kommunes'] = $kommunes;
            $customerKommunes[] = $customer;
        }

        return $customerKommunes;
    }

    private function getKommunesOfMultipleCustomers(array $customerCommunes): array
    {
        $resultingKommunes = [];
        foreach ($customerCommunes as $customer) {
            foreach ($customer['kommunes'] as $kommune) {
                $organisationName = $this->connection->fetchOne(
                    'SELECT o._o_name as name FROM _orga o WHERE o._o_id = :orgaId',
                    ['orgaId' => $kommune]
                );
                if (null !== $organisationName && '' !== $organisationName) {
                    $kommune = $kommune.' '.$organisationName;
                }
                if (array_key_exists($kommune, $resultingKommunes)) {
                    $resultingKommunes[$kommune][] = $customer['shortage'];
                } else {
                    $resultingKommunes[$kommune] = [$customer['shortage']];
                }
            }
        }

        return array_filter($resultingKommunes, static fn (array $customersOfkommune): bool => count($customersOfkommune) > 1);
    }

    private function transformKommunesOfMultipleCustomerIntoReadableOutput(array $resultingKommunes): string
    {
        // transform result into string
        $readableOutput = '';
        foreach ($resultingKommunes as $key => $customerShortens) {
            $readableOutput .= $key.' - is "Kommune or Anhörungsbehörde" in customers: '.implode(', ', $customerShortens)."\n";
        }

        return $readableOutput;
    }

    /**
     * @throws Exception
     */
    private function abortIfNotMysql(): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on 'mysql'."
        );
    }
}
