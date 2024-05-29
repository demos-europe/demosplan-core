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
use demosplan\DemosPlanCoreBundle\Repository\ManualListSortRepository;
use demosplan\DemosPlanCoreBundle\Repository\NewsRepository;
use Exception;

class ManualListSortService extends CoreService
{
    public function __construct(
        private readonly ManualListSortRepository $manualListSortRepository,
        private readonly NewsRepository $newsRepository,
        private readonly ArrayHelper $arrayHelper,
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

            $mlsIdents = $this->getMlsIdents($sourceProcedureManualListSort, $newProcedureId);

            $newManualListSort->setIdents(implode(',', $mlsIdents));


            $this->manualListSortRepository->persistEntities([$newManualListSort]);
            $this->manualListSortRepository->flushEverything();
        } catch (Exception $e) {
            $this->logger->warning('Copy Manual List Sort failed. Message: ', [$e]);
            throw $e;
        }
    }

    private function getMlsIdents(array $sourceProcedureManualListSort, string $newProcedureId): array
    {
        $sourceMlsIdents = explode(',', (string) $sourceProcedureManualListSort[0]->getIdents());

        $sourceNews = $this->newsRepository->findBy(['ident' => $sourceMlsIdents]);

        $sourceNewsArray = [];

        foreach ($sourceNews as $news) {
            $sourceNewsArray[] = $news->toArray();
        }

        $sortSourceNewsArray = $this->arrayHelper->orderArrayByIds($sourceMlsIdents, $sourceNewsArray, 'ident');

        $newProcedureNews = $this->newsRepository->findBy(['pId' => $newProcedureId]);

        foreach ($newProcedureNews as $news) {
            $newProcedureNewsArray[] = $news->toArray();
        }

        $newProcedureMlsIdents = [];
        foreach ($sortSourceNewsArray as $key => $value) {
            foreach ($newProcedureNewsArray as $newNews) {
                if (
                    $value['title'] === $newNews['title']
                && $value['description'] === $newNews['description']
                && $value['text'] === $newNews['text']
                && $value['title'] === $newNews['title']
                && $value['pictitle'] === $newNews['pictitle']
                && $value['pdf'] === $newNews['pdf']
                && $value['pdftitle'] === $newNews['pdftitle']
                && $value['enabled'] === $newNews['enabled']
                && $value['deleted'] === $newNews['deleted']
                ) {
                    $newProcedureMlsIdents[$key] = $newNews['ident'];
                }
            }
        }

        return $newProcedureMlsIdents;
    }
}
