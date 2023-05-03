<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanStatementBundle\EventListener;

use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\ParagraphVersion;
use demosplan\DemosPlanCoreBundle\Entity\EntityContentChange;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementVote;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Logic\SearchIndexTaskService;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentService;
use demosplan\DemosPlanStatementBundle\Logic\StatementService;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Exception;

/**
 * @deprecated should not be used anymore as default indexing via fos elastica is used
 */
class UpdateElasticaStatementPostListener
{
    /**
     * @var SearchIndexTaskService
     */
    protected $searchIndexTaskService;

    /** @var StatementService */
    private $statementService;
    /**
     * @var SegmentService
     */
    private $segmentService;

    public function __construct(
        StatementService $statementService,
        SearchIndexTaskService $searchIndexTaskService,
        SegmentService $segmentService
    ) {
        $this->statementService = $statementService;
        $this->searchIndexTaskService = $searchIndexTaskService;
        $this->segmentService = $segmentService;
    }

    public function preRemove(LifecycleEventArgs $eventArgs): void
    {
        $this->checkAndUpdate($eventArgs);
    }

    public function postPersist(LifecycleEventArgs $eventArgs): void
    {
        $this->checkAndUpdate($eventArgs);
    }

    public function postUpdate(LifecycleEventArgs $eventArgs): void
    {
        $this->checkAndUpdate($eventArgs);
    }

    /**
     * Check whether Elasticsearch needs to update parents of nested Entities.
     */
    protected function checkAndUpdate(LifecycleEventArgs $eventArgs): void
    {
        $entity = $eventArgs->getEntity();

        if ($entity instanceof Tag) {
            $this->updateAllEntities($entity->getStatements()->toArray());
        } elseif ($entity instanceof Elements) {
            // no need to update statements that refer to map category
            if ('map' !== $entity->getCategory()) {
                $statements = $this->statementService->getStatementsAssignedToElementsId($entity->getId());
                $this->updateAllEntities($statements);
            }
        } elseif ($entity instanceof ParagraphVersion) {
            $statements = $this->statementService->getStatementsAssignedToParagraphVersionId($entity->getId());
            $this->updateAllEntities($statements);
        } elseif ($entity instanceof StatementMeta) {
            $this->updateStatement($entity->getStatement());
        } elseif ($entity instanceof StatementVote) {
            $this->updateStatement($entity->getStatement());
        } elseif ($entity instanceof Statement) {
            $this->updateStatement($entity);
        } elseif ($entity instanceof EntityContentChange) {
            $this->handleEntityContentChange($entity);
        }
    }

    private function handleEntityContentChange(EntityContentChange $entityContentChange): void
    {
        if (Segment::class === $entityContentChange->getEntityType()) {
            $this->addIndexTask(Segment::class, $entityContentChange->getEntityId());
        }
    }

    /**
     * Updates a single statement and all related segments if necessary.
     */
    protected function updateStatement(Statement $statement): void
    {
        $statementId = $statement->getId();
        $segments = $this->segmentService->findByParentStatementId($statement->getId());

        $this->addIndexTask(Statement::class, $statementId);
        foreach ($segments as $segment) {
            $this->updateSegment($segment);
        }
    }

    /**
     * Updates a segment.
     */
    private function updateSegment(Segment $segment): void
    {
        $segmentId = $segment->getId();
        $this->addIndexTask(Segment::class, $segmentId);
    }

    /**
     * Iterates over all changed entities and updates them accordingly.
     *
     * @param array<int, Statement|Segment> $entities (as objects)
     */
    private function updateAllEntities(array $entities): void
    {
        foreach ($entities as $entity) {
            if ($entity instanceof Statement) {
                $this->updateStatement($entity);
            }
            if ($entity instanceof Segment) {
                $this->updateSegment($entity);
            }
        }
    }

    private function addIndexTask(string $entityClass, string $entityId): void
    {
        try {
            $this->searchIndexTaskService->addIndexTask($entityClass, $entityId);
        } catch (Exception $e) {
            // catch exception to prevent bubbling
            // do not use Logging atm to save resources. Might be added later
        }
    }
}
