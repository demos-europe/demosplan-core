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
use demosplan\DemosPlanStatementBundle\Logic\AnnotatedStatementPdf\PiTextRecognitionRequester;

class PiTextRecognitionTest extends PiCommTestAbstract
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = self::$container->get(PiTextRecognitionRequester::class);
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
        $demosAuthorization = $this->globalConfig->getPipelineDemosAuthorization();
        $pipelineId = $this->globalConfig->getAiPipelineAnnotatedStatementPdfReviewedId();
        $attributes = $requestData['data']['attributes'];
        $parameters = $attributes['parameters'];
        $errorUrl = $this->router->generate(
            'dplan_ai_api_annotation_statement_pdf_text_proposal_error',
            [
                'annotatedStatementPdfId' => $annotatedStatementPdf->getId(),
            ],
        );
        $sourceUrl = $this->router->generate(
            'dplan_ai_api_get_annotation_statement_pdf',
            ['annotatedStatementPdfId' => $annotatedStatementPdf->getId()],
        );
        $responseUrl = $this->router->generate(
            'dplan_ai_api_annotation_statement_pdf_text_proposal',
            [
                'annotatedStatementPdfId' => $annotatedStatementPdf->getId(),
            ],
        );
        $this->assertEquals('launch', $requestData['data']['type']);
        $this->assertEquals($annotatedStatementPdf->getId(), $requestData['data']['id']);
        $this->assertEquals(
            $demosAuthorization,
            $attributes[PiTextRecognitionRequester::PI_ATTRIBUTE_ERROR_AUTH]
        );

        $urlParts = parse_url($attributes[PiTextRecognitionRequester::PI_ATTRIBUTE_ERROR_URL]);
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
            $attributes[PiTextRecognitionRequester::PI_ATTRIBUTE_PIPELINE_ID]);
        $this->assertEquals(
            $demosAuthorization,
            $parameters[PiTextRecognitionRequester::PI_PARAMETER_SOURCE_AUTHORIZATION]
        );
        $this->assertEquals(
            $demosAuthorization,
            $parameters[PiTextRecognitionRequester::PI_PARAMETER_TARGET_AUTHORIZATION]
        );

        $urlParts = parse_url($parameters[PiTextRecognitionRequester::PI_PARAMETER_SOURCE_URL]);
        $expectedUrlParts = parse_url($sourceUrl);
        $this->assertEquals(
            $expectedUrlParts['path'],
            $urlParts['path']
        );
        $this->assertStringContainsString(
            'jwt=',
            $urlParts['query']
        );

        $urlParts = parse_url($parameters[PiTextRecognitionRequester::PI_PARAMETER_TARGET_URL]);
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
     * text recognition.
     */
    protected function getJsonSchemaPath(): string
    {
        return DemosPlanPath::getRootPath('tests/backend/core/PiCommunication/Functional/JsonSchemas/text-recognition/text-recognition-schema.json');
    }

    /**
     * Returns the folder where wrong jsons are stored.
     */
    protected function getWrongJsonsFolderPath(): string
    {
        return DemosPlanPath::getRootPath('tests/backend/core/PiCommunication/Functional/JsonSchemas/text-recognition/wrong-jsons/');
    }

    /**
     * @return AnnotatedStatementPdf
     */
    protected function getRequestDataObject(): object
    {
        return $this->fixtures->getReference('notPendingBlockedAnnotatedStatementPdf');
    }
}
