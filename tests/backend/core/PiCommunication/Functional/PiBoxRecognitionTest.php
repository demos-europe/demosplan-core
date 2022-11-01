<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\PiCommunication\Functional;

use demosplan\DemosPlanCoreBundle\Entity\Statement\AnnotatedStatementPdf\AnnotatedStatementPdf;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanStatementBundle\Logic\AnnotatedStatementPdf\PiBoxRecognitionRequester;

class PiBoxRecognitionTest extends PiCommTestAbstract
{
    public function setUp(): void
    {
        parent::setUp();
        $this->sut = self::$container->get(PiBoxRecognitionRequester::class);
    }

    public function testGetPiUrl(): void
    {
        parent::assertGetPiUrl();
    }

    public function testGetPiAuthorization(): void
    {
        parent::assertGetPiAuthorization();
    }

    public function testGetRequestData(): void
    {
        parent::assertGetRequestData();
    }

    /**
     * @param AnnotatedStatementPdf $annotatedStatementPdf
     */
    protected function checkRequestDataContent(
        array $requestData,
        $annotatedStatementPdf
    ): void {
        $demosAuthorization = $this->aiPipelineConfiguration->getPipelineDemosAuthorization();
        $pipelineId = $this->aiPipelineConfiguration->getAiPipelineAnnotatedStatementPdfCreatedId();
        $attributes = $requestData['data']['attributes'];
        $parameters = $attributes['parameters'];
        $errorUrl = $this->router->generate(
            'dplan_ai_api_annotation_statement_pdf_boxes_proposal_error',
            [
                'annotatedStatementPdfId' => $annotatedStatementPdf->getId(),
            ],
        );
        $sourceUrl = $this->router->generate(
            'core_file',
            ['hash' => $annotatedStatementPdf->getFile()->getHash()],
        );
        $responseUrl = $this->router->generate(
            'dplan_ai_api_annotation_statement_pdf_boxes_proposal',
            [
                'annotatedStatementPdfId' => $annotatedStatementPdf->getId(),
            ],
        );

        $this->assertEquals(
            $demosAuthorization,
            $attributes[PiBoxRecognitionRequester::PI_ATTRIBUTE_ERROR_AUTH]
        );
        $urlParts = parse_url($attributes[PiBoxRecognitionRequester::PI_ATTRIBUTE_ERROR_URL]);
        $expectedUrlParts = parse_url($errorUrl);
        $this->assertEquals(
            $expectedUrlParts['path'],
            $urlParts['path']
        );
        $this->assertStringContainsString(
            'jwt=',
            $urlParts['query']
        );
        $this->assertEquals(
            $pipelineId,
            $attributes[PiBoxRecognitionRequester::PI_ATTRIBUTE_PIPELINE_ID]
        );
        $this->assertEquals(
            $demosAuthorization,
            $parameters[PiBoxRecognitionRequester::PI_PARAMETER_SOURCE_AUTHORIZATION]
        );
        $this->assertEquals(
            $demosAuthorization,
            $parameters[PiBoxRecognitionRequester::PI_PARAMETER_TARGET_AUTHORIZATION]
        );
        $this->assertEquals(
            $sourceUrl,
            $parameters[PiBoxRecognitionRequester::PI_PARAMETER_SOURCE_URL]
        );
        $urlParts = parse_url($parameters[PiBoxRecognitionRequester::PI_PARAMETER_TARGET_URL]);
        $expectedUrlParts = parse_url($responseUrl);
        $this->assertEquals(
            $expectedUrlParts['path'],
            $urlParts['path']
        );
        $this->assertStringContainsString(
            'jwt=',
            $urlParts['query']
        );
    }

    /**
     * Returns the json schema that defines the proper json structure to request PI for
     * box recognition.
     */
    protected function getJsonSchemaPath(): string
    {
        return DemosPlanPath::getRootPath('tests/backend/core/PiCommunication/Functional/JsonSchemas/box-recognition/box-recognition-schema.json');
    }

    /**
     * Returns the folder where wrong jsons are stored.
     */
    protected function getWrongJsonsFolderPath(): string
    {
        return DemosPlanPath::getRootPath('tests/backend/core/PiCommunication/Functional/JsonSchemas/box-recognition/wrong-jsons/');
    }

    /**
     * @return AnnotatedStatementPdf
     */
    protected function getRequestDataObject(): object
    {
        return $this->fixtures->getReference('notPendingBlockedAnnotatedStatementPdf');
    }
}
