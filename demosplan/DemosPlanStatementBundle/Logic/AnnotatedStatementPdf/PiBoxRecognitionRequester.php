<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanStatementBundle\Logic\AnnotatedStatementPdf;

use demosplan\DemosPlanCoreBundle\Entity\Statement\AnnotatedStatementPdf\AnnotatedStatementPdf;
use demosplan\DemosPlanCoreBundle\Logic\ProductIntelligence\PiCommunication;
use Symfony\Component\Form\Exception\InvalidConfigurationException;
use Symfony\Component\Routing\RouterInterface;

class PiBoxRecognitionRequester extends PiCommunication
{
    /**
     * @param AnnotatedStatementPdf $annotatedStatementPdf
     *
     * @return array<mixed>
     */
    public function getRequestData($annotatedStatementPdf): array
    {
        $sourceUrl = $this->router->generate(
            'core_file',
            ['hash' => $annotatedStatementPdf->getFile()->getHash()],
        );
        $responseUrl = $this->router->generate(
            'dplan_ai_api_annotation_statement_pdf_boxes_proposal',
            [
                'annotatedStatementPdfId' => $annotatedStatementPdf->getId(),
            ],
            RouterInterface::ABSOLUTE_URL
        );
        $errorUrl = $this->router->generate(
            'dplan_ai_api_annotation_statement_pdf_boxes_proposal_error',
            [
                'annotatedStatementPdfId' => $annotatedStatementPdf->getId(),
            ],
            RouterInterface::ABSOLUTE_URL
        );

        $aiPipelineId = $this->globalConfig->getAiPipelineAnnotatedStatementPdfCreatedId();
        if (null === $aiPipelineId || '' === $aiPipelineId) {
            $this->logger->error('Missing Pipeline Id for new AnnotatedStatementPdf notification.');
            throw new InvalidConfigurationException('Generic error');
        }
        $pipelineDemosAuthorization = $this->globalConfig->getPipelineDemosAuthorization();
        if (null === $pipelineDemosAuthorization || '' === $pipelineDemosAuthorization) {
            $this->logger->error('Missing Authorization for AI => DPLAN requests.');
            throw new InvalidConfigurationException('Generic error');
        }

        return [
            'data' => [
                'attributes' => [
                    PiBoxRecognitionRequester::PI_ATTRIBUTE_PIPELINE_ID => $aiPipelineId,
                    'parameters'                                        => [
                        PiBoxRecognitionRequester::PI_PARAMETER_SOURCE_AUTHORIZATION => $pipelineDemosAuthorization,
                        PiBoxRecognitionRequester::PI_PARAMETER_SOURCE_URL           => $sourceUrl,
                        PiBoxRecognitionRequester::PI_PARAMETER_TARGET_AUTHORIZATION => $pipelineDemosAuthorization,
                        PiBoxRecognitionRequester::PI_PARAMETER_TARGET_URL           => $responseUrl,
                    ],
                    PiBoxRecognitionRequester::PI_ATTRIBUTE_ERROR_URL  => $errorUrl,
                    PiBoxRecognitionRequester::PI_ATTRIBUTE_ERROR_AUTH => $pipelineDemosAuthorization,
                ],
            ],
        ];
    }
}
