<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use Cocur\Slugify\Slugify;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

class Router implements RouterInterface, WarmableInterface
{
    /**
     * @var array<string,?string>
     */
    protected $procedureIdCache = [];

    /**
     * This router decorates Symfony\Bundle\FrameworkBundle\Routing.
     */
    public function __construct(private readonly GlobalConfigInterface $globalConfig, private readonly ProcedureRepository $procedureRepository, private readonly RouterInterface $router)
    {
    }

    public function generate($route, $parameters = [], $referenceType = self::ABSOLUTE_PATH): string
    {
        $scheme = $this->globalConfig->getUrlScheme();

        // convert procedureId to slug to get nice urls
        $parameters = $this->convertProcedureIdParameter($parameters);

        $generatedRoute = $this->router->generate($route, $parameters, $referenceType);
        // add pathprefix between host and path if needed
        if (0 < strlen($this->globalConfig->getUrlPathPrefix())) {
            $generatedRoute = $this->injectUrlPathPrefix($generatedRoute);
        }
        // replace scheme
        // explicitly set scheme, if relative or network scheme is needed to avoid problems
        // with proxies which talk via http with dplan but ssl with the wide wide world (www)
        if (self::NETWORK_PATH === $referenceType) {
            $generatedRoute = $scheme.':'.$generatedRoute;
        } else {
            // replace with scheme configured in parameters
            $generatedRoute = preg_replace('/^(http|https):\/\//', $scheme.'://', $generatedRoute);
        }

        // strip .berlin. from dev urls as apache sends internal server name even to external users
        $generatedRoute = str_ireplace(['.ad.berlin.', '.berlin.'], '.', $generatedRoute);

        return $generatedRoute;
    }

    public function setContext(RequestContext $context): void
    {
        $this->router->setContext($context);
    }

    public function getContext(): RequestContext
    {
        return $this->router->getContext();
    }

    public function getRouteCollection(): RouteCollection
    {
        return $this->router->getRouteCollection();
    }

    public function match($pathinfo): array
    {
        // convert slug back to procedureId
        $parameters = $this->router->match($pathinfo);

        return $this->decodeProcedureIdParam($parameters);
    }

    public function matchRequest(Request $request): array
    {
        // convert slug back to procedureId
        $parameters = $this->router->matchRequest($request);

        return $this->decodeProcedureIdParam($parameters);
    }

    /**
     * Safely inject the url path prefix into the generated url
     * without adding double slashes if the prefix starts or
     * ends with a slash.
     *
     * Also avoid adding the prefix again if it is already
     * present within the route.
     */
    private function injectUrlPathPrefix(string $generatedRoute): string
    {
        $prefix = $this->globalConfig->getUrlPathPrefix();

        if ('/' === $prefix[0]) {
            $prefix = \substr($prefix, 1);
        }

        if ('/' === $prefix[-1]) {
            $prefix = \substr($prefix, 0, -1);
        }

        if (false !== stripos($generatedRoute, $prefix.'/')) {
            return $generatedRoute;
        }

        return preg_replace(
            '/:\/\/([^\/]*)/',
            '://$1/'.$prefix,
            $generatedRoute
        );
    }

    /**
     * Convert a procedureId into its slug so that links are built with the slug
     * Will be reversed by $this->decodeProcedureIdParam().
     */
    private function convertProcedureIdParameter(array $parameters): array
    {
        if (array_key_exists('procedure', $parameters) && is_array($parameters['procedure']) && array_key_exists('id', $parameters['procedure']) && null !== $parameters['procedure']['id']) {
            $parameters['procedure'] = $this->slugifyProcedureIdParam($parameters['procedure']['id']);

            return $parameters;
        }

        if (array_key_exists('procedure', $parameters) && null !== $parameters['procedure']) {
            $parameters['procedure'] = $this->slugifyProcedureIdParam($parameters['procedure']);
        }
        if (array_key_exists('procedureId', $parameters) && null !== $parameters['procedureId']) {
            $parameters['procedureId'] = $this->slugifyProcedureIdParam($parameters['procedureId']);
        }

        return $parameters;
    }

    private function slugifyProcedureIdParam(string $procedureId): string
    {
        // the cache is only useful, when $procedureId is the slug
        // as otherwise doctrine will cache the query result itself
        if (array_key_exists($procedureId, $this->procedureIdCache)) {
            return $this->procedureIdCache[$procedureId];
        }

        $this->procedureIdCache[$procedureId] = $procedureId;
        $slug = $procedureId;
        $shortUrl = $this->procedureRepository->findShortUrlById($procedureId);
        if ($shortUrl) {
            $this->procedureIdCache[$procedureId] = $shortUrl;
            $slug = $shortUrl;
        }

        return $slug;
    }

    /**
     * Convert procedureId slugs in routes back into proper procedureIds
     * to be used throughout the program.
     */
    private function decodeProcedureIdParam(array $parameters): array
    {
        if (array_key_exists('procedure', $parameters)) {
            $parameters['procedure'] = $this->getProcedureBySlug($parameters['procedure']);
        }
        if (array_key_exists('procedureId', $parameters)) {
            $parameters['procedureId'] = $this->getProcedureBySlug($parameters['procedureId']);
        }

        return $parameters;
    }

    private function getProcedureBySlug(string $procedureIdOrSlug): string
    {
        $procedureId = $procedureIdOrSlug;
        $slugify = new Slugify();
        $slug = $slugify->slugify($procedureIdOrSlug);
        $procedure = $this->procedureRepository->getProcedureBySlug($slug);
        if ($procedure instanceof Procedure) {
            $procedureId = $procedure->getId();
        }

        return $procedureId;
    }

    public function warmUp(string $cacheDir): array
    {
        return $this->router->warmUp($cacheDir);
    }
}
