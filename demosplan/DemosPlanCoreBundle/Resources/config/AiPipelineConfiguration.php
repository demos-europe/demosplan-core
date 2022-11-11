<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Resources\config;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Validation;

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

    /** @var string */
    protected $aiServiceSalt = '';

    /** @var string */
    protected $aiServicePostUrl = '';

    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->parameterBag = $parameterBag;
        $this->aiPipelineAuthorization = $this->parameterBag->get('pipeline.ai.authorization');
        $this->aiPipelineUrl = $this->parameterBag->get('pipeline.ai.url');
        $this->aiPipelineAnnotatedStatementPdfCreatedId = $this->parameterBag->get('pipeline.ai.annotated.statement.pdf.created.id');
        $this->aiPipelineAnnotatedStatementPdfReviewedId = $this->parameterBag->get('pipeline.ai.annotated.statement.pdf.reviewed.id');
        $this->pipelineDemosAuthorization = $this->parameterBag->get('pipeline.demos.authorization');
        $this->piPipelineConfirmedSegmentsId = $this->parameterBag->get('pi.pipeline.confirmed.segments.id');
        $this->piPipelineSegmentRecognitionId = $this->parameterBag->get('pi.pipeline.segment.recognition.id');
        if ($this->parameterBag->has('pipeline.ai.labels')) {
            $this->aiPipelineLabels = $this->parameterBag->get('pipeline.ai.labels');
        }
        if ($parameterBag->has('ai_service_salt')) {
            $aiServiceSalt = $parameterBag->get('ai_service_salt');
            if (is_string($aiServiceSalt)) {
                $this->aiServiceSalt = $aiServiceSalt;
            }
        }
        if ($parameterBag->has('ai_service_post_url')) {
            $aiServicePostUrl = $parameterBag->get('ai_service_post_url');
            $validator = Validation::createValidator();
            $violations = $validator->validate($aiServicePostUrl, new Url());
            if (0 === $violations->count()) {
                $this->aiServicePostUrl = $aiServicePostUrl;
            }
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
            return 'Basic '.base64_encode($this->htaccessUser.':'.$this->htaccessPass ?? '');
        }

        return $this->pipelineDemosAuthorization;
    }

    public function getAiPipelineLabels(): array
    {
        return $this->aiPipelineLabels;
    }

    /**
     * @return string may be empty if not configured properly
     */
    public function getAiServiceSalt(): string
    {
        return $this->aiServiceSalt;
    }

    /**
     * @return string may be empty if not configured properly
     */
    public function getAiServicePostUrl(): string
    {
        return $this->aiServicePostUrl;
    }
}
