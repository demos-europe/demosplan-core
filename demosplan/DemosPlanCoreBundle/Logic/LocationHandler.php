<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use Symfony\Contracts\Translation\TranslatorInterface;

class LocationHandler
{
    /** @var LocationService */
    private $locationService;

    /** @var TranslatorInterface */
    private $translator;

    public function __construct(LocationService $locationService, TranslatorInterface $translator)
    {
        $this->locationService = $locationService;
        $this->translator = $translator;
    }

    public function findByArs(string $ars): array
    {
        return $this->locationService->findByArs($ars);
    }

    public function findByMunicipalCode(string $municipalCode): array
    {
        return $this->locationService->findByMunicipalCode($municipalCode);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getFilterResultMessage(array $locations, int $nResults): string
    {
        $parameters = 0 < count($locations)
            ? ['locationName' => $locations[0]->getName()]
            : [];
        $parameters['count'] = count($parameters);

        $transMsg = 0 === $nResults
            ? 'public.index.filter.gfk.location.noresults'
            : 'public.index.filter.gfk.location.results';

        return $this->translator->trans($transMsg, $parameters);
    }
}
