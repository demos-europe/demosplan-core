<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20240105113406 extends AbstractMigration
{
    private array $customerKommunes;

    public function getDescription(): string
    {
        return 'refs T34551: alters the property customer for the procedure table
            This property was used to indicate a blueprint that is just for a specific customer
            Now this property shall indicate that the procedure is owned by an organisation within this customer
            This is necessary to determine all customer related activity.
            The logic to filter a customer-blueprint has to be adjusted to check the additional property _p_master
            as the customer will always be set now';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // The assumption is that every organisation exists only once as a "Kommune".
        // Because only a "Kommune" can create procedures
        // the customer can be set for all procedures by their related organisation.
        //
        // The only organisations that are "Kommune" for multiple customers are our own Test-Organisations.
        // For procedures created by our own Test-Organisations the default customer can be set if exists.
        // random otherwise as no logic filters procedures by their customer but their organisation anyway.

        // foreach customer we select the organisations that are accepted as "Kommune" OLAUTH
        // set the corresponding customer within the procedure table
        //
        // There might be left outs - if a "Kommune" gets rejected after creating procedures in the past...
        // But the Organisation could very well be rejected without ever creating a Procedure...

        $this->selectCustomerKommunes();
        // List Organisations that are accepted as "Kommune" in more than one customer
        // these are expected to be our test-Organisations!
        echo $this->transformKommunesOfMultipleCustomerIntoReadableOutput($this->getKommunesOfMultipleCustomers());
        // proceed setting the customer property for procedures but do not overwrite existing relations
        foreach ($this->customerKommunes as $customer) {
            foreach ($customer['kommunes'] as $kommuneId) {
                $this->addSql(
                    'UPDATE _procedure SET customer = :customerId
                        WHERE _o_id = :orgaId
                        AND (customer IS NULL OR customer = "")
                        AND master_template = 0',
                    ['customerId' => $customer['id'], 'orgaId' => $kommuneId]
                );
            }
        }


//        echo var_dump($this->customerKommunes);

    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('UPDATE _procedure SET customer = NULL WHERE _p_master = 0');
    }

    private function selectCustomerKommunes(): void
    {
        $customers = $this->connection->fetchAllAssociative(
            'SELECT customer._c_id as id, customer._c_subdomain as shortage FROM customer'
        );
        foreach ($customers as $customer) {
            $kommunesQueryResult = $this->connection->fetchAllAssociative(
                'SELECT rcoot._o_id as id FROM relation_customer_orga_orga_type rcoot, _orga_type ot
                        WHERE rcoot._ot_id = ot._ot_id
                        AND ot._ot_name = "OLAUTH"
                        AND rcoot._c_id = :customerId
                        AND rcoot.status = "accepted"',
                ['customerId' => $customer['id']]
            );
            $kommunes = [];
            foreach ($kommunesQueryResult as $kommune) {
                $kommunes[] = $kommune['id'];
            }
            $customer['kommunes'] = $kommunes;
            $this->customerKommunes[] = $customer;
        }
    }

    private function getKommunesOfMultipleCustomers(): array
    {
        $resultingKommunes = [];
        foreach ($this->customerKommunes as $customer) {
            foreach ($customer['kommunes'] as $kommune) {
                $organisationName = $this->connection->fetchOne(
                    'SELECT o._o_name as name FROM _orga o WHERE o._o_id = :orgaId',
                    ['orgaId' => $kommune]
                );
                if (null !== $organisationName && '' !== $organisationName) {
                    $kommune = $organisationName;
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
            $readableOutput .= $key.' - is "Kommune" in customers: '.implode(', ', $customerShortens)."\n";
        }

        return $readableOutput;
    }

    /**
     * @throws Exception
     */
    private function abortIfNotMysql(): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySqlPlatform,
            "Migration can only be executed safely on 'mysql'."
        );
    }
}
