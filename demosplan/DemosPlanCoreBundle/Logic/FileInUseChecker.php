<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use demosplan\DemosPlanCoreBundle\Entity\Branding;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph;
use demosplan\DemosPlanCoreBundle\Entity\Document\ParagraphVersion;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocumentVersion;
use demosplan\DemosPlanCoreBundle\Entity\FileContainer;
use demosplan\DemosPlanCoreBundle\Entity\Forum\ForumEntryFile;
use demosplan\DemosPlanCoreBundle\Entity\GlobalContent;
use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayer;
use demosplan\DemosPlanCoreBundle\Entity\News\News;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatementFile;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatementVersion;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\StatementAttachment;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\Video;
use demosplan\DemosPlanCoreBundle\Event\CheckFileIsUsedEvent;
use demosplan\DemosPlanCoreBundle\EventDispatcher\TraceableEventDispatcher;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use Psr\Log\LoggerInterface;

class FileInUseChecker
{
    public function __construct(private readonly ManagerRegistry $managerRegistry, private readonly LoggerInterface $logger, private readonly TraceableEventDispatcher $eventDispatcher)
    {
    }

    /**
     * Check whether File is in use somewhere.
     */
    public function isFileInUse(string $fileId): bool
    {
        if (
            $this->isUsedInProcedure($fileId) ||
            $this->isUsedInProcedureSettings($fileId) ||
            $this->isUsedOutsideProcedure($fileId) ||
            $this->isUsedOutsideProcedureManyToOne($fileId) ||
            $this->isUsedInReferences($fileId)
        ) {
            return true;
        }

        $this->logger->info('File not used any more', [$fileId]);
        // if we did not find any occurrence file is unused
        return false;
    }

    private function isUsedInProcedure(string $fileId): bool
    {
        $procedureFieldsToCheck = [
            DraftStatement::class        => ['file', 'mapFile'],
            DraftStatementVersion::class => ['file', 'mapFile'],
            Elements::class              => ['file'],
            // used as tags within text
            Paragraph::class             => ['text'],
            ParagraphVersion::class      => ['text'],
            SingleDocument::class        => ['document'],
            SingleDocumentVersion::class => ['document'],
            Statement::class             => ['file', 'mapFile'],
        ];
        foreach ($procedureFieldsToCheck as $entityClass => $fieldArray) {
            foreach ($fieldArray as $field) {
                /** @var EntityRepository $repos */
                $repos = $this->managerRegistry->getRepository($entityClass);
                $entities = $repos->createQueryBuilder('e')
                    ->select('IDENTITY(e.procedure)')
                    ->where('e.'.$field.' LIKE :id')
                    ->setParameter(':id', '%'.$fileId.'%')
                    ->andWhere('e.deleted = :deleted')
                    ->setParameter(':deleted', false)
                    ->getQuery()
                    ->getResult();
                if (0 < (is_countable($entities) ? count($entities) : 0)) {
                    return true;
                }
            }
        }

        return $this->isGisLayerFileUsedInProcedure($fileId) ||
            $this->isNewsFileUsedInProcedure($fileId) ||
            $this->isLogoFileUsedInProcedure($fileId);
    }

    private function isUsedInProcedureSettings(string $fileId): bool
    {
        $procedureSettingsFieldsToCheck = [
            'planPDF',
            'planPara1PDF',
            'planPara2PDF',
            'planDrawPDF',
            'pictogram',
        ];
        // check for ProcedureSettings as they do not have an own Repository
        foreach ($procedureSettingsFieldsToCheck as $field) {
            try {
                /** @var EntityRepository $repos */
                $repos = $this->managerRegistry->getRepository(Procedure::class);
                $result = $repos->createQueryBuilder('p')
                    ->leftJoin('p.settings', 'ps')
                    ->where('ps.'.$field.' LIKE :id')
                    ->setParameter(':id', '%'.$fileId.'%')
                    ->andWhere('p.deleted = :deleted')
                    ->setParameter(':deleted', false)
                    ->getQuery()
                    ->getResult();
                if (0 < (is_countable($result) ? count($result) : 0)) {
                    // file is in use
                    return true;
                }
            } catch (Exception $e) {
                $this->logger->error('Some error occurred', [$e]);
                // better be safe
                return true;
            }
        }

        return false;
    }

    private function isUsedOutsideProcedure(string $fileId): bool
    {
        $nonProcedureFieldsToCheck = [
            ForumEntryFile::class => ['string'],
            GlobalContent::class  => ['picture', 'pdf'],
        ];
        // check for Non procedure related fields
        foreach ($nonProcedureFieldsToCheck as $entityClass => $fields) {
            foreach ($fields as $field) {
                try {
                    /** @var EntityRepository $repos */
                    $repos = $this->managerRegistry->getRepository($entityClass);
                    $entities = $repos->createQueryBuilder('e')
                        ->where('e.'.$field.' LIKE :id')
                        ->setParameter(':id', '%'.$fileId.'%')
                        ->andWhere('e.deleted = :deleted')
                        ->setParameter(':deleted', false)
                        ->getQuery()
                        ->getResult();
                    if (0 < (is_countable($entities) ? count($entities) : 0)) {
                        // file is in use
                        return true;
                    }
                } catch (Exception $e) {
                    $this->logger->error('Some error occurred', [$e]);
                    // better be safe
                    return true;
                }
            }
        }

        return false;
    }

    private function isUsedOutsideProcedureManyToOne(string $fileId): bool
    {
        $nonProcedureManyToOneFieldsToCheck = [
            Branding::class            => ['logo'],
        ];

        // check for Non procedure related fields that have ManyToOne Relation fields
        foreach ($nonProcedureManyToOneFieldsToCheck as $entityClass => $fields) {
            foreach ($fields as $field) {
                try {
                    /** @var EntityRepository $repos */
                    $repos = $this->managerRegistry->getRepository($entityClass);
                    $qb = $repos->createQueryBuilder('e')
                        ->where('IDENTITY(e.'.$field.') LIKE :id')
                        ->setParameter(':id', '%'.$fileId.'%');

                    // Customer does not have deleted field
                    $ignoreDeletedField = [
                        Customer::class,
                        Branding::class,
                    ];
                    if (!in_array($entityClass, $ignoreDeletedField, true)) {
                        $qb->andWhere('e.deleted = :deleted')
                            ->setParameter(':deleted', false);
                    }

                    $entities = $qb->getQuery()->getResult();
                    if (0 < (is_countable($entities) ? count($entities) : 0)) {
                        // file is in use
                        return true;
                    }
                } catch (Exception $e) {
                    $this->logger->error('Something happened', [$e]);
                    // better be safe
                    return true;
                }
            }
        }

        return false;
    }

    private function isUsedInReferences(string $fileId): bool
    {
        $references = [
            Branding::class              => 'logo',
            DraftStatementFile::class    => 'file',
            FileContainer::class         => 'file',
            StatementAttachment::class   => 'file',
            Video::class                 => 'file',
        ];

        /** @var CheckFileIsUsedEvent $event * */
        $event = $this->eventDispatcher->dispatch(new CheckFileIsUsedEvent($fileId));
        if ($event->getIsUsed()) {
            return true;
        }
        foreach ($references as $class => $field) {
            /** @var EntityRepository $repos */
            $repos = $this->managerRegistry->getRepository($class);
            $result = $repos->createQueryBuilder('e')
                ->select('IDENTITY(e.'.$field.')')
                ->where('IDENTITY(e.'.$field.') = :fileId')
                ->setParameter(':fileId', $fileId)
                ->getQuery()
                ->getResult();
            if (0 < (is_countable($result) ? count($result) : 0)) {
                // file is in use
                return true;
            }
        }

        // This could probably be achieved with dql
        /** @var Connection $connection */
        $connection = $this->managerRegistry->getConnection();
        $sql = 'SELECT file FROM procedureproposal_file_doctrine WHERE file = :fileId';
        $stmt = $connection->prepare($sql);
        $doctrineResult = $stmt->executeQuery(['fileId' => $fileId]);
        $result = $doctrineResult->fetchAllAssociative();

        return 0 < count($result);
    }

    private function isGisLayerFileUsedInProcedure(string $fileId): bool
    {
        /** @var EntityRepository $repos */
        $repos = $this->managerRegistry->getRepository(GisLayer::class);
        $entities = $repos->createQueryBuilder('e')
            ->select('e.procedureId')
            ->where('e.legend LIKE :id')
            ->setParameter(':id', '%'.$fileId.'%')
            ->andWhere('e.deleted = :deleted')
            ->setParameter(':deleted', false)
            ->getQuery()
            ->getResult();

        return 0 < (is_countable($entities) ? count($entities) : 0);
    }

    private function isNewsFileUsedInProcedure(string $fileId): bool
    {
        foreach (['picture', 'pdf'] as $field) {
            /** @var EntityRepository $repos */
            $repos = $this->managerRegistry->getRepository(News::class);
            $entities = $repos->createQueryBuilder('e')
                ->select('e.pId')
                ->where('e.'.$field.' LIKE :id')
                ->setParameter(':id', '%'.$fileId.'%')
                ->andWhere('e.deleted = :deleted')
                ->setParameter(':deleted', false)
                ->getQuery()
                ->getResult();
            if (0 < (is_countable($entities) ? count($entities) : 0)) {
                return true;
            }
        }

        return false;
    }

    private function isLogoFileUsedInProcedure(string $fileId): bool
    {
        /** @var EntityRepository $repos */
        $repos = $this->managerRegistry->getRepository(Procedure::class);
        $entities = $repos->createQueryBuilder('e')
            ->select('e.id')
            ->where('e.logo LIKE :id')
            ->setParameter(':id', '%'.$fileId.'%')
            ->andWhere('e.deleted = :deleted')
            ->setParameter(':deleted', false)
            ->getQuery()
            ->getResult();

        return 0 < (is_countable($entities) ? count($entities) : 0);
    }
}
