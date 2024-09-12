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

class RecommendationConverter
{
    public function __construct(private readonly ImageLinkConverter $imageLinkConverter)
    {
    }

    /**
     * @param Segment[] $sortedSegments
     *
     * @return array<string, string> - key is externId, value is recommendation text with image references
     */
    public function convertImagesToReferencesInRecommendations(array $sortedSegments): array
    {
        $recommendationTexts = [];
        foreach ($sortedSegments as $segment) {
            $externId = $segment->getExternId();
            $convertedSegment = $this->imageLinkConverter->convert($segment, $externId, false);
            $recommendationTexts[$externId] = $convertedSegment->getRecommendationText();
        }
        $this->imageLinkConverter->resetImages();

        return $recommendationTexts;
    }

    public function updateRecommendationsWithTextReferences(array $segmentsOrStatements, array $adjustedRecommendations): array
    {
        foreach ($segmentsOrStatements as $key => $segmentOrStatement) {
            $isNotSegment = !array_key_exists('recommendation', $segmentOrStatement);
            $externIdIsNotOfSegment = !array_key_exists($segmentOrStatement['externId'], $adjustedRecommendations);
            if ($isNotSegment || $externIdIsNotOfSegment) {
                continue;
            }

            $segmentOrStatement['recommendation'] = $adjustedRecommendations[$segmentOrStatement['externId']];
            $segmentsOrStatements[$key] = $segmentOrStatement;
        }

        return $segmentsOrStatements;
    }
}
