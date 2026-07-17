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

use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\ValueObject\SegmentExport\ConvertedSegment;

class RecommendationConverter
{
    public function __construct(private readonly ImageLinkConverter $imageLinkConverter)
    {
    }

    /**
     * @param Segment[] $sortedSegments
     *
     * @return array<string, ConvertedSegment> - key is externId, value is ConvertedSegment
     */
    public function convertImagesToReferencesInRecommendations(array $sortedSegments): array
    {
        $convertedSegments = [];
        foreach ($sortedSegments as $segment) {
            $externId = $segment->getExternId();
            $convertedSegments[$externId] = $this->imageLinkConverter->convert(
                $segment,
                $externId,
                false
            );
        }
        $this->imageLinkConverter->resetImages();

        return $convertedSegments;
    }

    /**
     * @param array<string, mixed>            $segmentsOrStatements
     * @param array<string, ConvertedSegment> $convertedSegments
     *
     * @return array<string, mixed>
     */
    public function updateRecommendationsWithTextReferences(
        array $segmentsOrStatements,
        array $convertedSegments,
    ): array {
        foreach ($segmentsOrStatements as $key => $segmentOrStatement) {
            $isNotSegment = !array_key_exists('recommendation', $segmentOrStatement);
            $externIdIsNotOfSegment = !array_key_exists($segmentOrStatement['externId'], $convertedSegments);
            if ($isNotSegment || $externIdIsNotOfSegment) {
                continue;
            }

            $segmentOrStatement['text'] = $convertedSegments[$segmentOrStatement['externId']]->getText();
            $segmentOrStatement['recommendation'] =
                $convertedSegments[$segmentOrStatement['externId']]->getRecommendationText();
            $segmentsOrStatements[$key] = $segmentOrStatement;
        }

        return $segmentsOrStatements;
    }
}
