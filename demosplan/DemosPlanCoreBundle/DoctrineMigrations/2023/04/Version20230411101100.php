<?php declare(strict_types = 1);

namespace Application\Migrations;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanStatementBundle\Logic\StatementService;
use demosplan\DemosPlanStatementBundle\Repository\StatementRepository;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Exception;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Version20230411101100 extends AbstractMigration implements ContainerAwareInterface
{
    private StatementService $statementService;
    private StatementRepository $statementRepository;
    private ContainerInterface $container;

    public function getDescription(): string
    {
        return 'refs T28005: Resolve duplicate externIds of statements.';
    }

    /**
     * Set new externId in statements to resolve the duplicates.
     *
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->statementService = $this->container->get(StatementService::class);
        $duplicates = [];
        $procedureExternIdMapping = [];

        $allStatements = $this->statementService->getAllStatements();

        echo "Removing duplicate extern_ids in statements \n";
        echo "logfile: app/logs/externIdUpdates_T28005.log \n";

        // Analyze duplicates per procedure
        foreach ($allStatements as $statement) {
            $procedureId = $statement->getProcedureId();
            $externId = $statement->getExternId();
            // collect duplicates:
            if (array_key_exists($procedureId, $procedureExternIdMapping) && in_array($externId, $procedureExternIdMapping[$procedureId])) {
                // Climb up the tree to see if the duplicate is valid
                if (!$this->isDuplicateValid($statement, $externId)) {
                    $duplicates[] = $statement->getId();
                }
            } else {
                $procedureExternIdMapping[$procedureId][] = $externId;
            }
        }

        $duplicatesCount = count($duplicates);

        echo 'Found '.$duplicatesCount." duplicates.\n";

        // Get repository for getting and updating statements
        $this->statementRepository = $this->statementService->getDoctrine()->getRepository(Statement::class);

        // Get logger to log changes
        /** @var Logger $logger */
        $logger = new Logger(
            'Update',
            [new StreamHandler('app/logs/externIdUpdates_T28005.log', LogLevel::INFO)]
        );

        $updateCounter = 1;
        // Update Duplicates with new externIds
        foreach ($duplicates as $statementId) {
            echo 'Updating Statement '.$updateCounter.'/'.$duplicatesCount.' with id '.$statementId."\n";
            /** @var Statement $statement */
            $statement = $this->statementRepository->find($statementId);

            $oldExternId = $statement->getExternId();
            if ($statement->isManual()) {
                $newExternId = $this->statementRepository->getNextValidManualExternalIdForProcedure($statement->getProcedureId());
            } else {
                $newExternId = $this->statementRepository->getNextValidExternalIdForProcedure($statement->getProcedureId());
            }

            $statement->setExternId($newExternId);
            $this->statementRepository->updateObject($statement);

            $logger->log(LogLevel::INFO, 'Updated Statement(ID: '.$statement->getId().', PROCEDURE: '.$statement->getProcedureId().') changed externId from '.$oldExternId.' to '.$newExternId);
            ++$updateCounter;
        }
    }

    /**
     * RECURSION !!!
     *
     * Statements can have valid duplicates in extern_id
     * -> isOriginal
     * -> isCopy (or even a copy of a copy of a copy ...)
     *
     * In this case the duplicate extern_id is valid if
     * it can be found somewhere in the tree
     */
    private function isDuplicateValid(Statement $statement, string $dupId): bool
    {
        // If I am the duplicate or my original-statement
        if ($statement->getId() == $dupId || (null !== $statement->getOriginal() && $statement->getOriginal()->getExternId() == $dupId)) {
            return true;
        }
        // If not check all my children the same way
        else {
            $parents = $this->statementRepository->findBy(['parent' => $statement->getId()]);
            $index = 0;
            $valid = false;
            while (!$valid && $index < count($parents)) {
                $valid = $this->isDuplicateValid($this->statementService->getStatement($parents[$index]['id']), $dupId);
                ++$index;
            }

            return $valid;
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }
}
