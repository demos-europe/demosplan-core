<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment\Export;

use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Logic\EntityHelper;
use Doctrine\Common\Collections\ArrayCollection;
use ReflectionException;

class ExportDataArrayGenerator
{
    public function __construct(private readonly EntityHelper $entityHelper)
    {
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ReflectionException
     */
    public function convertIntoExportableArray(StatementInterface $segmentOrStatement): array
    {
        $exportData = $this->entityHelper->toArray($segmentOrStatement);
        $exportData = $this->extractMetaData($segmentOrStatement, $exportData);
        $exportData['submitDateString'] = $segmentOrStatement->getSubmitDateString();
        $exportData['countyNames'] = $segmentOrStatement->getCountyNames();

        if ($segmentOrStatement instanceof Segment) {
            // Some data is stored on parentStatement instead on Segment and have to get from there
            $exportData = $this->extractParentStatementData($segmentOrStatement, $exportData);
            // Segments using place instead of status
            $exportData['status'] = $segmentOrStatement->getPlace()->getName();
        }
        $exportData = $this->extractTagsData($segmentOrStatement, $exportData);
        $exportData['isClusterStatement'] = $segmentOrStatement->isClusterStatement();

        return $exportData;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ReflectionException
     */
    private function extractMetaData(StatementInterface $segmentOrStatement, array $exportData): array
    {
        $exportData['meta'] = $this->entityHelper->toArray($exportData['meta']);
        $exportData['meta']['submitName'] = $segmentOrStatement->getSubmitterName();
        $exportData['meta']['authoredDate'] = $segmentOrStatement->getAuthoredDateString();

        // Some data is stored on parentStatement instead on Segment and have to get from there
        if ($segmentOrStatement instanceof Segment) {
            $parentStatement = $segmentOrStatement->getParentStatementOfSegment();
            $exportData['meta']['orgaCity'] = $parentStatement->getOrgaCity();
            $exportData['meta']['orgaStreet'] = $parentStatement->getOrgaStreet();
            $exportData['meta']['orgaPostalCode'] = $parentStatement->getOrgaPostalCode();
            $exportData['meta']['orgaEmail'] = $parentStatement->getOrgaEmail();
            $exportData['meta']['authorName'] = $parentStatement->getAuthorName();
            $exportData['meta']['submitName'] = $parentStatement->getSubmitterName();
            $exportData['meta']['houseNumber'] = $parentStatement->getMeta()->getHouseNumber();
            $exportData['meta']['authoredDate'] = $parentStatement->getAuthoredDateString();
        }

        return $exportData;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractParentStatementData(Segment $segment, array $exportData): array
    {
        $parentStatement = $segment->getParentStatementOfSegment();

        $exportData['memo'] = $parentStatement->getMemo();
        $exportData['internId'] = $parentStatement->getInternId();
        $exportData['oName'] = $parentStatement->getOName();
        $exportData['dName'] = $parentStatement->getDName();
        $exportData['fileNames'] = $parentStatement->getFileNames();
        $exportData['submitDateString'] = $parentStatement->getSubmitDateString();

        return $exportData;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractTagsData(StatementInterface $segmentOrStatement, array $exportData): array
    {
        $exportData['tagNames'] = $segmentOrStatement->getTagNames();
        /** @var ArrayCollection $tagsCollection */
        $tagsCollection = $exportData['tags'];
        $exportData['tags'] = array_map($this->entityHelper->toArray(...), $tagsCollection->toArray());
        foreach ($exportData['tags'] as $key => $tag) {
            /** @var TagTopic $tagTopic */
            $tagTopic = $tag['topic'];
            $exportData['tags'][$key]['topicTitle'] = $tagTopic->getTitle();
        }
        $exportData['topicNames'] = $segmentOrStatement->getTopicNames();

        return $exportData;
    }
}
