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

class PiTextRecognitionRequester extends PiCommunication
{
    /**
     * @param AnnotatedStatementPdf $annotatedStatementPdf
     *
     * @return array<mixed>
     */
    public function getRequestData($annotatedStatementPdf): array
    {
        $pipelineId = $this->aiPipelineConfiguration->getAiPipelineAnnotatedStatementPdfReviewedId();
        if (null === $pipelineId || '' === $pipelineId) {
            $errorMsg = 'Missing Doc Reviewed Pipeline Id for AI => DPLAN requests.';
            $this->logger->error($errorMsg);
            throw new InvalidConfigurationException($errorMsg);
        }
        $pipelineDemosAuth = $this->aiPipelineConfiguration->getPipelineDemosAuthorization();
        if (null === $pipelineDemosAuth || '' === $pipelineDemosAuth) {
            $errorMsg = 'Missing Dplan Auth Configuration for AI => DPLAN requests.';
            $this->logger->error($errorMsg);
            throw new InvalidConfigurationException($errorMsg);
        }

        $urlSource = $this->router->generate(
            'dplan_ai_api_get_annotation_statement_pdf',
            [
                'annotatedStatementPdfId' => $annotatedStatementPdf->getId(),
                'include'                 => 'statement,annotatedStatementPdfPages,procedure',
            ],
            RouterInterface::ABSOLUTE_URL
        );
        $responseUrl = $this->router->generate(
            'dplan_ai_api_annotation_statement_pdf_text_proposal',
            [
                'annotatedStatementPdfId' => $annotatedStatementPdf->getId(),
            ],
            RouterInterface::ABSOLUTE_URL
        );
        $errorUrl = $this->router->generate(
            'dplan_ai_api_annotation_statement_pdf_text_proposal_error',
            [
                'annotatedStatementPdfId' => $annotatedStatementPdf->getId(),
            ],
            RouterInterface::ABSOLUTE_URL
        );
        $parameters = [
            PiTextRecognitionRequester::PI_PARAMETER_SOURCE_URL           => $urlSource,
            PiTextRecognitionRequester::PI_PARAMETER_SOURCE_AUTHORIZATION => $pipelineDemosAuth,
            PiTextRecognitionRequester::PI_PARAMETER_TARGET_URL           => $responseUrl,
            PiTextRecognitionRequester::PI_PARAMETER_TARGET_AUTHORIZATION => $pipelineDemosAuth,
        ];

        $requestData['data'] = [
            'type'       => 'launch',
            'id'         => $annotatedStatementPdf->getId(),
            'attributes' => [
                PiTextRecognitionRequester::PI_ATTRIBUTE_PIPELINE_ID => $pipelineId,
                'parameters'                                         => $parameters,
                PiTextRecognitionRequester::PI_ATTRIBUTE_ERROR_URL   => $errorUrl,
                PiTextRecognitionRequester::PI_ATTRIBUTE_ERROR_AUTH  => $pipelineDemosAuth,
            ],
        ];

        return $requestData;
    }
}
