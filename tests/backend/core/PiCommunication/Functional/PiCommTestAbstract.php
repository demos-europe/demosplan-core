<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\PiCommunication\Functional;

use DirectoryIterator;
use JsonException;
use JsonSchema\Exception\InvalidSchemaException;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Tests\Base\FunctionalTestCase;
use Tests\Base\PluginTestTrait;
use demosplan\DemosPlanCoreBundle\Logic\JwtRouter;
use demosplan\DemosPlanCoreBundle\Logic\ProductIntelligence\PiCommunication;
use demosplan\DemosPlanCoreBundle\Resources\config\AiPinelineConnection;
use demosplan\DemosPlanCoreBundle\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Validate\JsonSchemaValidator;
use demosplan\plugins\workflow\SegmentsManager\SegmentsManager;

abstract class PiCommTestAbstract extends FunctionalTestCase
{
    use PluginTestTrait;

    /**
     * @var PiCommunication
     */
    protected $sut;

    /**
     * @var string
     */
    protected $jsonSchemaPath;

    /**
     * @var JsonSchemaValidator
     */
    protected $jsonValidator;

    /**
     * @var AiPinelineConnection
     */
    protected $aiPinelineConnection;

    /**
     * @var JwtRouter
     */
    protected $router;

    protected static function getEnabledPlugins(): array
    {
        return [SegmentsManager::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->jsonSchemaPath = $this->getJsonSchemaPath();
        $this->jsonValidator = self::$container->get(JsonSchemaValidator::class);
        $this->router = self::$container->get(JwtRouter::class);
        $this->aiPinelineConnection = self::$container->get(AiPinelineConnection::class);
    }

    /**
     * Tests the pipeline url used to communicate with PI is right (overwrite in child class
     * if some url different from this one is used).
     */
    protected function assertGetPiUrl(): void
    {
        $expectedPiUrl = $this->aiPinelineConnection->getAiPipelineUrl();
        $this->assertEquals($expectedPiUrl, $this->sut->getPiUrl());
    }

    /**
     * Tests the authorization code used to communicate with PI is right (overwrite in child
     * class if some url different from this one is used).
     */
    protected function assertGetPiAuthorization(): void
    {
        $expectedAuthorization = 'Bearer '.$this->aiPinelineConnection->getAiPipelineAuthorization();
        $this->assertEquals($expectedAuthorization, $this->sut->getAuthorization());
    }

    /**
     * Tests the method that prepares the body to request to PI.
     */
    protected function assertGetRequestData(): void
    {
        $requestDataObject = $this->getRequestDataObject();
        $requestData = $this->sut->getRequestData($requestDataObject);
        $this->checkProperRequestDataStructure($requestData);
        $this->checkRequestDataContent($requestData, $requestDataObject);
        $this->checkWrongRequestDataStructure();
    }

    /**
     * Tests that a proper json structure is accepted, based on the json schemas of each
     * implementation.
     *
     * @throws InvalidSchemaException
     */
    private function checkProperRequestDataStructure(array $requestData): void
    {
        $allGood = true;
        try {
            $this->jsonValidator->validate(
                Json::encode($requestData),
                $this->getJsonSchemaPath()
            );
        } catch (InvalidSchemaException $e) {
            $allGood = false;
        }

        $this->assertTrue($allGood);
    }

    /**
     * Goes over the directory where wrong jsons (request body) are stored and, based on the
     * specific jsonSchema, tests that errors are detected.
     */
    private function checkWrongRequestDataStructure(): void
    {
        $wrongJsonFiles = new DirectoryIterator($this->getWrongJsonsFolderPath());

        foreach ($wrongJsonFiles as $wrongJsonFile) {
            if ($wrongJsonFile->isDot()) {
                continue;
            }
            $wrongJsonPath = $this->getWrongJsonsFolderPath().$wrongJsonFile->getFilename();
            $wrongJsonContents = $this->getFileContents($wrongJsonPath);
            $errorDetected = false;

            try {
                $this->jsonValidator->validate($wrongJsonContents, $this->getJsonSchemaPath());
            } catch (InvalidSchemaException|JsonException $e) {
                $errorDetected = true;
            }

            self::assertTrue($errorDetected);
        }
    }

    /**
     * @param $fullPath
     */
    private function getFileContents($fullPath): string
    {
        if (!$fileContents = file_get_contents($fullPath)) {
            throw new FileNotFoundException('File not found in path: '.$this->jsonSchemaPath);
        }

        return $fileContents;
    }

    /**
     * Implementation can just call assetGetPiUrl from this class.
     */
    abstract public function testGetPiUrl(): void;

    /**
     * Implementation can just call assetGetPiAuthorization from this class.
     */
    abstract public function testGetPiAuthorization(): void;

    /**
     * * Implementation can just call assetGetRequestData from this class.
     */
    abstract public function testGetRequestData(): void;

    /**
     * Every implementation must test that the contents of $requestData based on $object.
     *
     * @param object $object
     */
    abstract protected function checkRequestDataContent(array $requestData, $object): void;

    /**
     * Every implementation returns the path to the json schema file that validates the
     * structure of the data sent to PI.
     */
    abstract protected function getJsonSchemaPath(): string;

    /**
     * Every implementation returns the path to the folder storing wrong jsons.
     */
    abstract protected function getWrongJsonsFolderPath(): string;

    /**
     * Every implementation returns the object they are using for their requests.
     */
    abstract protected function getRequestDataObject(): object;
}
