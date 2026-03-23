<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter;

use DemosEurope\DemosplanAddon\Contracts\Entities\FileInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Logic\EntityHelper;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use Doctrine\Common\Collections\ArrayCollection;
use ReflectionException;

/**
 * Service responsible for converting Statement and Segment entities into exportable arrays.
 *
 * This service handles the polymorphic conversion of StatementInterface objects,
 * enriching them with computed fields and handling data inheritance from parent statements.
 */
class StatementArrayConverter
{
    public function __construct(
        private readonly EntityHelper $entityHelper,
        private readonly StatementService $statementService,
    ) {
    }

    /**
     * Converts a Statement or Segment entity into an exportable array format.
     *
     * This method:
     * - Converts the entity to array using EntityHelper
     * - Adds computed fields like submitDateString, countyNames, phase
     * - For segments, pulls missing data from the parent statement
     * - Processes tags and topics information
     * - Handles the polymorphic nature of StatementInterface
     *
     * @param StatementInterface $segmentOrStatement The statement or segment to convert
     *
     * @return array<string, mixed> The exportable array representation
     *
     * @throws ReflectionException If reflection fails during conversion
     */
    public function convertIntoExportableArray(StatementInterface $segmentOrStatement): array
    {
        $exportData = $this->entityHelper->toArray($segmentOrStatement);
        $exportData['meta'] = $this->entityHelper->toArray($exportData['meta']);
        $exportData['submitDateString'] = $segmentOrStatement->getSubmitDateString();
        $exportData['countyNames'] = $segmentOrStatement->getCountyNames();
        $exportData['meta']['authoredDate'] = $segmentOrStatement->getAuthoredDateString();
        $exportData['phase'] = $this->statementService->getProcedurePhaseName(
            $segmentOrStatement->getPhase(),
            $segmentOrStatement->isSubmittedByCitizen()
        );

        $exportData['fileNames'] = $this->getFileNamesWithOriginal($segmentOrStatement);

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
            $exportData['memo'] = $parentStatement->getMemo();
            $exportData['internId'] = $parentStatement->getInternId();
            $exportData['oName'] = $parentStatement->getOName();
            $exportData['meta']['authoredDate'] = $parentStatement->getAuthoredDateString();
            $exportData['dName'] = $parentStatement->getDName();
            $exportData['status'] = $segmentOrStatement->getPlace()->getName(); // Segments using place instead of status
            $exportData['fileNames'] = $this->getFileNamesWithOriginal($parentStatement);
            $exportData['submitDateString'] = $parentStatement->getSubmitDateString();
        }

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
        $exportData['isClusterStatement'] = $segmentOrStatement->isClusterStatement();

        return $exportData;
    }

    /**
     * Retrieves file names associated with the statement, including the original file if it exists.
     *
     * @param StatementInterface $statement The statement from which to retrieve file names
     *
     * @return array<string> An array of file names, including the original file if available
     */
    private function getFileNamesWithOriginal(StatementInterface $statement): array
    {
        $fileNames = $statement->getFileNames();
        $originalFile = $statement->getOriginalFile();
        if ($originalFile instanceof FileInterface) {
            $fileNames[] = $originalFile->getFilename();
        }

        return $fileNames;
    }
}
