<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataCollector;

use demosplan\DemosPlanCoreBundle\Services\SubdomainHandlerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Throwable;

class ConfigParametersDataCollector extends DataCollector
{
    public function __construct(private readonly ParameterBagInterface $parameterBag, private readonly SubdomainHandlerInterface $subdomainHandler)
    {
    }

    public function collect(
        Request $request,
        Response $response,
        ?Throwable $exception = null,
    ) {
        $esUrls = collect($this->parameterBag->get('elasticsearch_urls'))->toArray();

        $this->data = [
            'database_host'        => $this->parameterBag->get('database_host'),
            'database_name'        => $this->parameterBag->get('database_name'),
            'es_host'              => implode(', ', $esUrls),
            'project_prefix'       => $this->parameterBag->get('project_prefix'),
            'subdomain'            => $this->subdomainHandler->getSubdomain($request),
        ];
    }

    public function getName(): string
    {
        return 'app.config_parameters_collector';
    }

    public function reset(): void
    {
        $this->data = [];
    }

    public function getDatabaseHost(): string
    {
        return $this->data['database_host'];
    }

    public function getDatabaseName(): string
    {
        return $this->data['database_name'];
    }

    public function getEsHost(): string
    {
        return $this->data['es_host'];
    }

    public function getProjectPrefix(): string
    {
        return $this->data['project_prefix'];
    }

    public function getSubdomain(): string
    {
        return $this->data['subdomain'];
    }
}
