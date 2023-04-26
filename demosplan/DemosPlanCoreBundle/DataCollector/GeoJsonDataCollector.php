<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataCollector;

use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Statement\DraftStatementHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Throwable;

class GeoJsonDataCollector extends DataCollector
{
    /**
     * @var DraftStatementHandler
     */
    private $draftStatementHandler;

    public function __construct(DraftStatementHandler $draftStatementHandler)
    {
        $this->draftStatementHandler = $draftStatementHandler;
    }

    /**
     * @throws UserNotFoundException
     */
    public function collect(Request $request, Response $response, Throwable $exception = null): void
    {
        $currentRoute = $request->get('_route');
        if ('DemosPlan_statement_list_draft' === $currentRoute) {
            $geoJsonInfo = [];
            $draftStatements = $this
                ->draftStatementHandler
                ->findCurrentUserDraftStatements($request->get('procedure'));

            foreach ($draftStatements as $draftStatement) {
                /** @var DraftStatement $draftStatement */
                if ('' !== $draftStatement->getPolygon()) {
                    $geoJsonInfo[] = [
                        'draftStatementId'     => $draftStatement->getId(),
                        'draftStatementNumber' => $draftStatement->getNumber(),
                        'geoJson'              => $this->jsonPrettify($draftStatement->getPolygon()),
                    ];
                }
            }

            if (!empty($geoJsonInfo)) {
                $this->data['geo_json_info'] = $geoJsonInfo;
            }
        }
    }

    public function getName(): string
    {
        return 'app.geo_json_data_collector';
    }

    public function reset(): void
    {
        $this->data = [];
    }

    public function getGeoJsonInfo(): ?array
    {
        return isset($this->data['geo_json_info']) ? $this->data['geo_json_info'] : null;
    }

    private function jsonPrettify(string $json): string
    {
        return nl2br(
            str_replace(
                ' ',
                '&nbsp;',
                Json::encode(
                    Json::decodeToMatchingType($json), JSON_PRETTY_PRINT
                )
            )
        );
    }
}
