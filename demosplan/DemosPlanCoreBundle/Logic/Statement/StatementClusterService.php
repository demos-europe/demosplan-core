<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\EntityFetcher;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ClusterStatementResourceType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use EDT\ConditionFactory\ConditionFactoryInterface;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use Exception;
use ReflectionException;

class StatementClusterService extends CoreService
{
    /** @var StatementService */
    protected $statementService;

    /**
     * @var EntityFetcher
     */
    private $entityFetcher;

    /**
     * @var ClusterStatementResourceType
     */
    private $clusterStatementResourceType;

    /**
     * @var ConditionFactoryInterface
     */
    private $conditionFactory;

    /** @var StatementCopier */
    private $statementCopier;
    /**
     * @var StatementRepository
     */
    private $statementRepository;

    public function __construct(
        ClusterStatementResourceType $clusterStatementResourceType,
        DqlConditionFactory $conditionFactory,
        EntityFetcher $entityFetcher,
        StatementCopier $statementCopier,
        StatementRepository $statementRepository,
        StatementService $statementService
    ) {
        $this->clusterStatementResourceType = $clusterStatementResourceType;
        $this->conditionFactory = $conditionFactory;
        $this->entityFetcher = $entityFetcher;
        $this->statementCopier = $statementCopier;
        $this->statementRepository = $statementRepository;
        $this->statementService = $statementService;
    }

    /**
     * Creates a new Statement, which will hold a cluster of statements.
     * Only creates a new StatementCluster if there are elements in the given $statementIdsToCluster.
     *
     * @param string[] $statementIdsToCluster
     *
     * @return bool|Statement
     *
     * @throws Exception
     */
    public function newStatementCluster(Statement $representativeStatement, array $statementIdsToCluster)
    {
        /** @var Connection $doctrineConnection */
        $doctrineConnection = $this->getDoctrine()->getConnection();

        try {
            $doctrineConnection->beginTransaction();
            // Cluster needs to be copied to be available in Assessmenttable
            $statementAssessmentTable = $this->statementCopier->copyStatementObjectWithinProcedure(
                $representativeStatement,
                true,
                true
            );

            if (!$statementAssessmentTable instanceof Statement) {
                throw new Exception();
            }

            $headStatement = $this->statementRepository
                ->addCluster($statementAssessmentTable, $statementIdsToCluster);

            $doctrineConnection->commit();

            return $headStatement;
        } catch (Exception $e) {
            $doctrineConnection->rollBack();
            $this->logger->error('Create new StatementCluster failed:', [$e]);
            throw $e;
        }
    }

    /**
     * @return array|Statement|false|null
     *
     * @throws InvalidDataException
     * @throws MessageBagException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws ReflectionException
     */
    public function copyClusterToProcedure(Statement $headStatement, Procedure $targetProcedure)
    {
        if (!$headStatement->isClusterStatement()) {
            return $this->statementCopier->copyStatementToProcedure($headStatement, $targetProcedure);
        }

        $copiedHeadStatement = $this->statementCopier->copyStatementToProcedure($headStatement, $targetProcedure);
        $copiedMembers = [];
        foreach ($headStatement->getCluster() as $member) {
            $copiedMembers[] = $this->statementCopier->copyStatementToProcedure($member, $targetProcedure);
        }

        $copiedHeadStatement->setCluster($copiedMembers);
        foreach ($copiedMembers as $member) {
            $this->statementService->updateStatementFromObject($member, true, true, false);
        }

        return $this->statementService->updateStatementFromObject($copiedHeadStatement, true, true, true);
    }

    public function getClustersOfProcedure(string $procedureId)
    {
        $sortMethods = $this->clusterStatementResourceType->getDefaultSortMethods();
        $conditions = [
            $this->clusterStatementResourceType->getAccessCondition(),
            $this->conditionFactory->propertyHasValue(
                $procedureId,
                $this->clusterStatementResourceType->procedure->id
            ),
        ];

        return $this->entityFetcher->listEntitiesUnrestricted(Statement::class, $conditions, $sortMethods);
    }
}
