<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ProductIntelligence;

use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\ILogic\ApiClientInterface;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfigInterface;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Routing\RouterInterface;

abstract class PiCommunication extends CoreService
{
    public const PI_ATTRIBUTE_ERROR_AUTH = 'error_auth';
    public const PI_ATTRIBUTE_ERROR_URL = 'error_url';
    public const PI_ATTRIBUTE_PIPELINE_ID = 'pipeline_id';
    public const PI_PARAMETER_SOURCE_AUTHORIZATION = 'de.demos-deutschland.URLSource:0:authorization';
    public const PI_PARAMETER_SOURCE_URL = 'de.demos-deutschland.URLSource:0:URL';
    public const PI_PARAMETER_TARGET_AUTHORIZATION = 'de.demos-deutschland.URLTarget:0:authorization';
    public const PI_PARAMETER_TARGET_URL = 'de.demos-deutschland.URLTarget:0:URL';

    /**
     * @var ApiClientInterface
     */
    protected $apiClient;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var JWTTokenManagerInterface
     */
    protected $jwtManager;
    /**
     * @var GlobalConfigInterface
     */
    protected $globalConfig;

    public function __construct(
        ApiClientInterface $apiClient,
        GlobalConfigInterface $globalConfig,
        JWTTokenManagerInterface $jwtManager,
        RouterInterface $jwtRouter
    ) {
        $this->apiClient = $apiClient;
        $this->globalConfig = $globalConfig;
        $this->jwtManager = $jwtManager;
        $this->router = $jwtRouter;
    }

    public function request(object $object): void
    {
        try {
            $aiPipelineUrl = $this->getPiUrl();
            $requestData = $this->getRequestData($object);
            $piAuthorization = $this->getAuthorization();

            $options = [
                'json'        => $requestData,
                'http_errors' => false, // do not throw exceptions based on statusCode
            ];
            if ('' !== $piAuthorization) {
                $options['headers'] = ['Authorization' => $piAuthorization];
            }

            $this->apiClient->request($aiPipelineUrl, $options, ApiClientInterface::POST);
        } catch (GuzzleException $e) { // Don't trust your IDE: GuzzleException may be thrown here
            $requestData = $requestData ?? [];
            $this->logger->error('GuzzleException on call AI pipeline via api.', [$e, $requestData]);
        } catch (Exception $e) {
            $requestData = $requestData ?? [];
            $this->logger->error('Exception while trying to launch AI pipeline.', [$e, $requestData]);
        }
    }

    public function getPiUrl(): string
    {
        return $this->globalConfig->getAiPipelineUrl();
    }

    public function getAuthorization(): string
    {
        return 'Bearer '.$this->globalConfig->getAiPipelineAuthorization();
    }

    /**
     * @return array<mixed>
     */
    abstract public function getRequestData(object $annotatedStatementPdf): array;
}
