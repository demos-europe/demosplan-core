<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use demosplan\DemosPlanCoreBundle\Entity\ManualListSort;
use demosplan\DemosPlanCoreBundle\Logic\News\ProcedureNewsService;
use demosplan\DemosPlanCoreBundle\Repository\ManualListSortRepository;
use demosplan\DemosPlanCoreBundle\Repository\NewsRepository;
use Exception;

class ManualListSortService extends CoreService
{
    public function __construct(
        private readonly ManualListSortRepository $manualListSortRepository,
        private readonly NewsRepository $newsRepository,
        private readonly ArrayHelper $arrayHelper,
        private readonly EntityHelper $entityHelper,
        private readonly ProcedureNewsService $procedureNewsService
    ) {
    }

    /**
     * @throws Exception
     */
    public function copyManualListSort(string $sourceProcedureId, string $newProcedureId): void
    {
        try {
            $sourceProcedureManualListSort = $this->manualListSortRepository->findBy(['pId' => $sourceProcedureId]);

            /** @var ManualListSort $newManualListSort */
            $newManualListSort = clone collect($sourceProcedureManualListSort)->first();

            $newManualListSort->setId(null);
            $newManualListSort->setPId($newProcedureId);
            $newManualListSort->setContext('procedure:'.$newProcedureId);

            $mlsIdents = $this->getMlsIdents($sourceProcedureId, $newProcedureId);

            $newManualListSort->setIdents(implode(',', $mlsIdents));

            $this->manualListSortRepository->persistEntities([$newManualListSort]);
            $this->manualListSortRepository->flushEverything();
        } catch (Exception $e) {
            $this->logger->warning('Copy Manual List Sort failed. Message: ', [$e]);
            throw $e;
        }
    }

    private function getMlsIdents(string $sourceProcedureId, string $newProcedureId): array
    {
//        $sourceMlsIdents = explode(',', (string) $sourceProcedureManualListSort[0]->getIdents());
//
//        $sourceNews = $this->newsRepository->findBy(['ident' => $sourceMlsIdents]);
//
//        $sourceNewsArray = [];
//
//        foreach ($sourceNews as $news) {
//            $sourceNewsArray[] = $news->toArray();
//        }
//
//        $sortSourceNewsArray = $this->arrayHelper->orderArrayByIds($sourceMlsIdents, $sourceNewsArray, 'ident');

        $sourceMlsIdents = $this->procedureNewsService->getProcedureNewsAdminList(
            $sourceProcedureId,
            'procedure:'.$sourceProcedureId
        );

        $newProcedureNews = $this->newsRepository->findBy(['pId' => $newProcedureId]);

        $newProcedureNewsArray = [];
        foreach ($newProcedureNews as $news) {
            $newProcedureNewsArray[] = $this->procedureNewsService->convertToLegacy($news);
            //$newProcedureNewsArray[] = $news->toArray();
            //$newProcedureNewsArray[] = $this->entityHelper->toArray($news);
        }

//        $newProcedureNews = $this->procedureNewsService->getNewsList($newProcedureId, null);

        $newProcedureMlsIdents = [];
        foreach ($sourceMlsIdents['result'] as $key => $value) {
            foreach ($newProcedureNewsArray as $newNews) {
                if ($value['title'] === $newNews['title']
                && $value['description'] === $newNews['description']
                && $value['text'] === $newNews['text']
                && $value['picture'] === $newNews['picture']
                && $value['pictitle'] === $newNews['pictitle']
                && $value['pdf'] === $newNews['pdf']
                && $value['pdftitle'] === $newNews['pdftitle']
                && $value['enabled'] === $newNews['enabled']
                && $value['deleted'] === $newNews['deleted']
                && $value['roles'] === $newNews['roles']
                ) {
                    $newProcedureMlsIdents[$key] = $newNews['ident'];
                }
            }
        }

        return $newProcedureMlsIdents;
    }
}
