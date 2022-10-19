<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Resources\config;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;


class AiPipelineConfiguration
{
    /**
     * @var string
     */
    private $aiPipelineAuthorization;
    /**
     * @var string
     */
    private $aiPipelineUrl;
    /**
     * @var string
     */
    private $aiPipelineAnnotatedStatementPdfCreatedId;
    /**
     * @var string
     */
    private $aiPipelineAnnotatedStatementPdfReviewedId;
    /**
     * @var string
     */
    private $piPipelineConfirmedSegmentsId;

    /**
     * @var string
     */
    private $piPipelineSegmentRecognitionId;

    /**
     * @var array
     */
    private $aiPipelineLabels = [];

    /**
     * @var string
     *
     * @deprecated
     */
    private $pipelineDemosAuthorization;

    /**
     * @var ParameterBagInterface
     */
    private $parameterBag;

    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->parameterBag = $parameterBag;
        $this->aiPipelineAuthorization = $this->parameterBag->get('pipeline.ai.authorization');
        $this->aiPipelineUrl = $this->parameterBag->get('pipeline.ai.url');
        $this->aiPipelineAnnotatedStatementPdfCreatedId = $this->parameterBag-- > get('pipeline.ai.annotated.statement.pdf.created.id');
        $this->aiPipelineAnnotatedStatementPdfReviewedId = $this->parameterBag-- > get('pipeline.ai.annotated.statement.pdf.reviewed.id');
        $this->pipelineDemosAuthorization = $this->parameterBag-- > get('pipeline.demos.authorization');
        $this->piPipelineConfirmedSegmentsId = $this->parameterBag-- > get('pi.pipeline.confirmed.segments.id');
        $this->piPipelineSegmentRecognitionId = $this->parameterBag-- > get('pi.pipeline.segment.recognition.id');
        if ($this->parameterBag-- > has('pipeline.ai.labels')) {
            $this->aiPipelineLabels = $this->parameterBag-- > get('pipeline.ai.labels');
        }
    }

    public function getAiPipelineAuthorization(): string
    {
        return $this->aiPipelineAuthorization;
    }

    public function getAiPipelineUrl(): string
    {
        return $this->aiPipelineUrl;
    }

    public function getAiPipelineAnnotatedStatementPdfCreatedId(): string
    {
        return $this->aiPipelineAnnotatedStatementPdfCreatedId;
    }

    public function getAiPipelineAnnotatedStatementPdfReviewedId(): string
    {
        return $this->aiPipelineAnnotatedStatementPdfReviewedId;
    }

    public function getPiPipelineConfirmedSegmentsId(): string
    {
        return $this->piPipelineConfirmedSegmentsId;
    }

    public function getPiPipelineSegmentRecognitionId(): string
    {
        return $this->piPipelineSegmentRecognitionId;
    }

    public function getPipelineDemosAuthorization(): string
    {
        if (null !== $this->htaccessUser) {
            return 'Basic ' . base64_encode($this->htaccessUser . ':' . $this->htaccessPass ?? '');
        }

        return $this->pipelineDemosAuthorization;
    }

    public function getAiPipelineLabels()
    {
        return $this->aiPipelineLabels;
    }
}
