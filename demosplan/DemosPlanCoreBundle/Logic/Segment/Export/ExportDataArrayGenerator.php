<?php
declare(strict_types=1);


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
        $exportData['meta'] = $this->entityHelper->toArray($exportData['meta']);
        $exportData['submitDateString'] = $segmentOrStatement->getSubmitDateString();
        $exportData['countyNames'] = $segmentOrStatement->getCountyNames();
        $exportData['meta']['authoredDate'] = $segmentOrStatement->getAuthoredDateString();

        // Some data is stored on parentStatement instead on Segment and have to get from there
        if ($segmentOrStatement instanceof Segment) {
            $exportData['meta']['orgaCity'] = $segmentOrStatement->getParentStatementOfSegment()->getOrgaCity();
            $exportData['meta']['orgaStreet'] = $segmentOrStatement->getParentStatementOfSegment()->getOrgaStreet();
            $exportData['meta']['orgaPostalCode'] = $segmentOrStatement->getParentStatementOfSegment()->getOrgaPostalCode();
            $exportData['meta']['orgaEmail'] = $segmentOrStatement->getParentStatementOfSegment()->getOrgaEmail();
            $exportData['meta']['authorName'] = $segmentOrStatement->getParentStatementOfSegment()->getAuthorName();
            $exportData['meta']['submitName'] = $segmentOrStatement->getParentStatementOfSegment()->getSubmitterName();
            $exportData['meta']['houseNumber'] = $segmentOrStatement->getParentStatementOfSegment()->getMeta()->getHouseNumber();
            $exportData['memo'] = $segmentOrStatement->getParentStatementOfSegment()->getMemo();
            $exportData['internId'] = $segmentOrStatement->getParentStatementOfSegment()->getInternId();
            $exportData['oName'] = $segmentOrStatement->getParentStatementOfSegment()->getOName();
            $exportData['meta']['authoredDate'] = $segmentOrStatement->getParentStatementOfSegment()->getAuthoredDateString();
            $exportData['dName'] = $segmentOrStatement->getParentStatementOfSegment()->getDName();
            $exportData['status'] = $segmentOrStatement->getPlace()->getName(); // Segments using place instead of status
            $exportData['fileNames'] = $segmentOrStatement->getParentStatementOfSegment()->getFileNames();
            $exportData['submitDateString'] = $segmentOrStatement->getParentStatementOfSegment()->getSubmitDateString();
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
}
