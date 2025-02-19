<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Document;

use DemosEurope\DemosplanAddon\Contracts\Entities\SingleDocumentInterface;
use DemosEurope\DemosplanAddon\Contracts\Services\SingleDocumentServiceInterface;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocumentVersion;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\DateHelper;
use demosplan\DemosPlanCoreBundle\Logic\EntityHelper;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Report\SingleDocumentReportEntryFactory;
use demosplan\DemosPlanCoreBundle\Logic\Report\ReportService;
use demosplan\DemosPlanCoreBundle\Repository\SingleDocumentRepository;
use demosplan\DemosPlanCoreBundle\Repository\SingleDocumentVersionRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;
use ReflectionException;

class SingleDocumentService extends CoreService implements SingleDocumentServiceInterface
{
    /**
     * @var FileService
     */
    protected $fileService;

    public function __construct(
        private readonly DateHelper $dateHelper,
        private readonly EntityHelper $entityHelper,
        FileService $fileService,
        private readonly SingleDocumentRepository $singleDocumentRepository,
        private readonly SingleDocumentVersionRepository $singleDocumentVersionRepository,
        private readonly SingleDocumentReportEntryFactory $reportEntryFactory,
        private readonly ReportService $reportService,
    ) {
        $this->fileService = $fileService;
    }

    /**
     * Ruft alle Dokumente eines Verfahrens ab
     * Die Dokumente müssen sichtbar sein (visible = true).
     *
     * @param string $procedureId
     * @param null   $search
     * @param bool   $legacy
     *
     * @return array
     *
     * @throws ReflectionException
     */
    public function getSingleDocumentList($procedureId, $search = null, $legacy = true)
    {
        $result = $this->singleDocumentRepository->findBy([
            'procedure' => $procedureId,
            'visible'   => true,
            'deleted'   => false,
        ]);

        if (!$legacy) {
            return $result;
        }

        $resArray = [];
        foreach ($result as $sd) {
            $res = $this->entityHelper->toArray($sd);
            $res = $this->convertDateTime($res);
            // Legacy structure
            $res['statement_enabled'] = $res['statementEnabled'];
            $resArray[] = $res;
        }

        if (null === $search) {
            $resArray['search'] = '';
        } else {
            $resArray['search'] = $search;
        }

        return $this->toLegacyResult($resArray);
    }

    /**
     * Sendet einen Sortierauftrag an den Service.
     *
     * @param array $documents
     *
     * @throws Exception
     */
    public function sortDocuments($documents): bool
    {
        if (empty($documents)) {
            return false;
        }

        $i = 1;
        foreach ($documents as $document) {
            $this->singleDocumentRepository->update($document, ['order' => $i++]);
        }

        return true;
    }

    /**
     * Ruft alle Documente eines Verfahrens ab
     * Die Dokumente müssen nicht sichtbar sein (visible = false oder true).
     *
     * @param string      $procedureId
     * @param string      $category
     * @param string|null $search
     *
     * @throws ReflectionException
     */
    public function getSingleDocumentAdminList($procedureId, $category, $search = null): array
    {
        $result = $this->singleDocumentRepository->findBy([
            'procedure' => $procedureId,
            'category'  => $category,
            'deleted'   => false,
        ]);

        $resArray = [];
        foreach ($result as $sd) {
            $res = $this->entityHelper->toArray($sd);
            $res = $this->convertDateTime($res);
            // Legacy structure
            $res['statement_enabled'] = $res['statementEnabled'];
            $resArray[] = $res;
        }

        if (is_null($search)) {
            $resArray['search'] = '';
        } else {
            $resArray['search'] = $search;
        }

        return $this->toLegacyResult($resArray);
    }

    /**
     * Ruft alle Documente eines Verfahrens ab
     * Die Dokumente müssen nicht sichtbar sein (visible = false oder true).
     *
     * @param string $procedureId
     * @param string $search
     *
     * @return array
     *
     * @throws ReflectionException
     */
    public function getSingleDocumentAdminListAll($procedureId, $search = null)
    {
        /*
         * Filter und Suche wird über Elasticsearch umgesetzt
         *
        */
        $result = $this->singleDocumentRepository->findBy([
            'procedure' => $procedureId,
            'deleted'   => false,
        ]);

        $resArray = [];
        foreach ($result as $sd) {
            $res = $this->entityHelper->toArray($sd);
            $res = $this->convertDateTime($res);
            // Legacy structure
            $res['statement_enabled'] = $res['statementEnabled'];
            $resArray[] = $res;
        }

        if (null === $search) {
            $resArray['search'] = '';
        } else {
            $resArray['search'] = $search;
        }

        return $this->toLegacyResult($resArray);
    }

    /**
     * Ruft ein einzelnes Dokument auf.
     *
     * @param string $ident
     *
     * @return SingleDocumentInterface|array|null
     *
     * @throws ReflectionException
     *
     * @psalm-return SingleDocumentInterface|array{statement_enabled: mixed}|null
     */
    public function getSingleDocument($ident, bool $legacy = true)
    {
        $result = $this->singleDocumentRepository->get($ident);

        if (null !== $result && $legacy) {
            $result = $this->entityHelper->toArray($result);
            $result = $this->convertDateTime($result);
            // Legacy structure
            $result['statement_enabled'] = $result['statementEnabled'];
        }

        return $result;
    }

    /**
     * Get SingleDocumentVersions.
     *
     * @param string $singleDocumentId
     *
     * @return SingleDocumentVersion[]
     *
     * @throws Exception
     */
    public function getVersions($singleDocumentId)
    {
        return $this->singleDocumentVersionRepository->findBy(['singleDocument' => $singleDocumentId]);
    }

    /**
     * Fügt ein Dokument hinzu.
     *
     * @param array $data
     *
     * @return array
     *
     * @throws ReflectionException
     */
    public function addSingleDocument($data)
    {
        $singleDocument = $this->singleDocumentRepository->add($data);

        $report = $this->reportEntryFactory->createSingleDocumentCreateEntry($singleDocument);
        $this->reportService->persistAndFlushReportEntries($report);

        $singleDocument = $this->entityHelper->toArray($singleDocument);
        $singleDocument = $this->convertDateTime($singleDocument);
        // Legacy structure
        $singleDocument['statement_enabled'] = $singleDocument['statementEnabled'];

        return $singleDocument;
    }

    /**
     * Löscht ein Dokument, bzw.
     * setzt die Flag deleted auf true.
     *
     * @param string $idents
     */
    public function deleteSingleDocument($idents): bool
    {
        try {
            if (!is_array($idents)) {
                $idents = [$idents];
            }
            $success = true;
            foreach ($idents as $documentId) {
                try {
                    $documentToDelete = $this->getSingleDocument($documentId, false);
                    $report = $this->reportEntryFactory->createSingleDocumentDeleteEntry($documentToDelete);
                    $this->singleDocumentRepository->delete($documentId);
                    $this->reportService->persistAndFlushReportEntries($report);
                } catch (Exception $e) {
                    $this->logger->error('Fehler beim Löschen eines SingleDocuments: ', [$e]);
                    $success = false;
                }
            }

            return $success;
        } catch (Exception $e) {
            $this->logger->warning('Fehler beim Löschen eines SingleDocuments: ', [$e]);

            return false;
        }
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws ReflectionException
     * @throws \DemosEurope\DemosplanAddon\Exception\JsonException
     */
    public function updateSingleDocument($data): array
    {
        $updatedDocument = $this->singleDocumentRepository->update($data['ident'], $data);

        $report = $this->reportEntryFactory->createSingleDocumentUpdateEntry($updatedDocument);
        $this->reportService->persistAndFlushReportEntries($report);

        $updatedDocument = $this->entityHelper->toArray($updatedDocument);
        $updatedDocument = $this->convertDateTime($updatedDocument);
        // Legacy structure
        $updatedDocument['statement_enabled'] = $updatedDocument['statementEnabled'];

        return $updatedDocument;
    }

    /**
     * @param array<int, SingleDocument> $planningDocuments
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function persistAndFlushNewPlanningDocumentsFromImport(array $planningDocuments): void
    {
        $this->singleDocumentRepository->persistEntities($planningDocuments);
        $this->singleDocumentRepository->flushEverything();
    }

    /**
     * Convert datetime paragraph array.
     *
     * @param array $singleDocument
     *
     * @return array
     */
    private function convertDateTime($singleDocument)
    {
        $singleDocument = $this->dateHelper->convertDatesToLegacy($singleDocument);

        $singleDocument['createdate'] = $singleDocument['createDate'];
        $singleDocument['modifydate'] = $singleDocument['modifyDate'];
        $singleDocument['deletedate'] = $singleDocument['deleteDate'];
        unset($singleDocument['createDate']);
        unset($singleDocument['modifyDate']);
        unset($singleDocument['deleteDate']);

        return $singleDocument;
    }

    /**
     * @param array $singleDocument
     */
    private function toLegacyResult($singleDocument): array
    {
        $result = [
            'result'     => $singleDocument,
            'filterSet'  => [],
            'sortingSet' => [],
            'search'     => $singleDocument['search'],
        ];

        unset($result['result']['search'], $singleDocument['search']);
        $result['total'] = sizeof($singleDocument);

        return $result;
    }

    /**
     * @param string $title
     *
     * @return string
     */
    public function convertSingleDocumentTitle($title)
    {
        $documentParts = \explode(':', $title);
        // set somehow misleading title 'title.pdf' to avoid missing docType in Windows
        return $documentParts[0] ?? 'title.pdf';
    }

    /**
     * Given an array of SingleDocument ids and a Procedure id, returns all SingleDocument's ids not belonging to the
     * project or empty array if they all belong to it.
     */
    public function getSingleDocumentsNotInProcedure(array $singleDocumentIds, string $procedureId): array
    {
        $procedureSingleDocs = $this->singleDocumentRepository->getSingleDocumentsByProcedureId($procedureId);
        $procedureSingleDocIds = array_map(
            static fn (SingleDocument $singleDocument) => $singleDocument->getId(),
            $procedureSingleDocs
        );

        return array_diff($singleDocumentIds, $procedureSingleDocIds);
    }

    /**
     * Given a $procedureId returns all SingleDocuments belonging to it with the given visibility status.
     */
    public function getProcedureDocumentsByVisibleStatus(string $procedureId, bool $visible): array
    {
        return $this->singleDocumentRepository->getProcedureDocumentsByVisibleStatus($procedureId, $visible);
    }

    /**
     * Given a $procedureId returns all ids for SingleDocuments belonging to it with the given visibility status.
     */
    public function getProcedureDocumentIdsByVisibleStatus(string $procedureId, bool $visible): array
    {
        $procedureSingleDocs = $this->getProcedureDocumentsByVisibleStatus($procedureId, $visible);

        return array_map(
            static fn (SingleDocument $singleDocument) => $singleDocument->getId(),
            $procedureSingleDocs
        );
    }

    /**
     * Given a list of SingleDocument ids and a $procedureId, returns all ids belonging to the procedure with visible
     * status set to true.
     */
    public function getNotVisibleSingleDocuments(array $singleDocumentIds, string $procedureId): array
    {
        $visibleProcedureDocIds = $this->getProcedureDocumentIdsByVisibleStatus($procedureId, true);

        return array_diff($singleDocumentIds, $visibleProcedureDocIds);
    }

    /**
     * Create Version of SingleDocument.
     *
     * @return SingleDocumentVersion
     *
     * @throws Exception
     */
    public function createSingleDocumentVersion(SingleDocumentInterface $singleDocument)
    {
        return $this->singleDocumentVersionRepository->createVersion($singleDocument);
    }
}
