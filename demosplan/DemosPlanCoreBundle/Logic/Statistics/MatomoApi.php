<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statistics;

use Carbon\Carbon;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanProcedureBundle\Repository\ProcedureRepository;
use JsonException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MatomoApi
{
    /**
     * @var string
     */
    private $matomoURL;
    /**
     * @var string
     */
    private $matomoToken;
    /**
     * @var int
     */
    private $matomoSiteId;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly LoggerInterface $logger,
        ParameterBagInterface $parameterBag,
        private readonly ProcedureRepository $procedureRepository,
        private readonly RouterInterface $router
    ) {
        // @improve T24944
        $this->matomoURL = sprintf('%s://%s', $parameterBag->get('url_scheme'), $parameterBag->get('piwik_url'));
        $this->matomoToken = $parameterBag->get('matomo_token');
        $this->matomoSiteId = $parameterBag->get('piwik_site_id');
    }

    /**
     * @param array<string,string> $additionalQueryValues
     *
     * @return array<string, string>
     *
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws JsonException
     */
    private function request(array $additionalQueryValues): array
    {
        $baseQuery = [
            'module'        => 'API',
            'token_auth'    => $this->matomoToken,
            'idSite'        => $this->matomoSiteId,
            'format'        => 'JSON',
        ];
        $query = [...$baseQuery, ...$additionalQueryValues];
        $response = $this->client->request('GET', $this->matomoURL, [
            'query' => $query,
        ]);

        $result = Json::decodeToArray($response->getContent());

        if (isset($result['result']) && 'error' === $result['result']) {
            $this->logger->error('Matomo-Api returned an error.', [
                'url'      => $this->matomoURL,
                'params'   => $query,
                'response' => $result['message'],
            ]);

            return [];
        }

        return $result;
    }

    /**
     * @return array<int, array<string, int>>
     *
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws JsonException
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws InvalidArgumentException
     */
    public function getProcedureStatistics(string $procedureId): array
    {
        $procedure = $this->procedureRepository->find($procedureId);
        if (null === $procedure) {
            throw new InvalidArgumentException("No procedure found for given ID: $procedureId");
        }
        $dateRange = $this->getDateRange($procedure);
        $statisticsData = [];
        // Get Data for slugs (including ID)
        $slugs = $procedure->getSlugs();
        foreach ($slugs as $slug) {
            $slugData = $this->getPageStatisticsPerMonth($slug->getName(), $dateRange);
            $statisticsData = $this->addStatisticsData($statisticsData, $slugData);
        }

        return $statisticsData;
    }

    private function getDateRange(Procedure $procedure): string
    {
        $createdDate = $procedure->getCreatedDate();
        $today = Carbon::today();

        return sprintf('%s,%s', $createdDate->format('Y-m-d'), $today->format('Y-m-d'));
    }

    /**
     * @param array<int, array<string, int>> $statisticsData
     * @param array<int, array>              $newData
     *
     * @return array<int, array<string, int>>
     */
    private function addStatisticsData(array $statisticsData, array $newData): array
    {
        foreach ($newData as $month => $intervalData) {
            if (!isset($statisticsData[$month])) {
                $statisticsData[$month] = [
                    'views' => 0,
                ];
            }
            if (isset($intervalData[0])) {
                $statisticsData[$month]['views'] += $intervalData[0]['nb_visits'];
            }
        }

        return $statisticsData;
    }

    /**
     * @return array<string, string>
     *
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws JsonException
     */
    private function getPageStatisticsPerMonth(string $pageIdentifier, string $date): array
    {
        $query = [
            'method'        => 'Actions.getPageUrl',
            'pageUrl'       => $this->router->generate('DemosPlan_procedure_public_detail', ['procedure' => $pageIdentifier], RouterInterface::ABSOLUTE_PATH),
            'date'          => $date,
            'period'        => 'month',
        ];

        return $this->request($query);
    }
}
