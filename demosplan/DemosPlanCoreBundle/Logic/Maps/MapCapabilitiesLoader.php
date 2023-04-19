<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Maps;

use demosplan\DemosPlanCoreBundle\Exception\ExternalDataFetchException;
use demosplan\DemosPlanCoreBundle\Logic\HttpCall;
use demosplan\DemosPlanCoreBundle\ValueObject\Map\MapCapabilities;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function in_array;
use function simplexml_load_string;
use function str_replace;
use function strtolower;

class MapCapabilitiesLoader
{
    private const REQUEST_PARAMETER = 'Request=GetCapabilities';

    /**
     * @var HttpCall
     */
    private $httpCall;

    public function __construct(HttpCall $httpCall)
    {
        $this->httpCall = $httpCall;
    }

    /**
     * Issue a capabilities request to a web mapping service.
     *
     * @return mixed
     *
     * @throws Exception
     *
     * @deprecated Use {@link self::getCapabilities()} instead
     */
    public function sendGetCapabilitiesRequest(string $path)
    {
        return $this->httpCall->request('GET', $path, []);
    }

    /**
     * Issue a capabilities request to a web mapping service.
     */
    public function getCapabilities(string $url): MapCapabilities
    {
        $optimizedUrl = $this->ensureUrlRequestsCapabilities($url);

        try {
            $response = $this->httpCall->request('GET', $optimizedUrl, []);

            if (200 > $response['responseCode'] || 300 < $response['responseCode']) {
                throw ExternalDataFetchException::fetchFailed($response['responseCode']);
            }
        } catch (Throwable $e) {
            throw ExternalDataFetchException::fetchFailed(Response::HTTP_INTERNAL_SERVER_ERROR, $e);
        }

        return $this->evaluateCapabilitiesXML($response['body']);
    }

    /**
     * Extract the service type from a successful capabiltiies response.
     */
    public function evaluateCapabilitiesXML(string $content): MapCapabilities
    {
        $dom = simplexml_load_string($content);
        $namespaces = $dom->getNamespaces();

        $type = MapCapabilities::TYPE_UNKNOWN;
        if (in_array(MapCapabilities::TYPE_WMS_XMLNS, $namespaces, true)) {
            $type = MapCapabilities::TYPE_WMS;
        }

        if (in_array(MapCapabilities::TYPE_WMTS_XMLNS, $namespaces, true)) {
            $type = MapCapabilities::TYPE_WMTS;
        }

        $capabilities = new MapCapabilities();
        $capabilities->setXml($content);
        $capabilities->setType($type);

        $capabilities->lock();

        return $capabilities;
    }

    /**
     * Automagically append the required request parameter.
     */
    public function ensureUrlRequestsCapabilities(string $url): string
    {
        $query = parse_url($url, \PHP_URL_QUERY);
        $parameters = (null !== $query) ? explode('&', $query) : [];

        if (0 === count($parameters)) {
            $url .= '?';
        }

        $hasCapabilities = 1 === collect($parameters)->map(static function (string $parameter) {
            return explode('=', $parameter);
        })->filter(static function (array $parameter) {
            [$name, $value] = $parameter;

            return 'request' === strtolower($name) && 'getcapabilities' === strtolower($value);
        })->count();

        if (!$hasCapabilities) {
            $parameters[] = self::REQUEST_PARAMETER;
        }

        $compiledParams = implode('&', $parameters);
        if (null !== $query) {
            return str_replace($query, $compiledParams, $url);
        }

        return $url.$compiledParams;
    }
}
