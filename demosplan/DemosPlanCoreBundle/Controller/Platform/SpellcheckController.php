<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Platform;

use DemosEurope\DemosplanAddon\Controller\APIController;
use demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Logic\Spellcheck\LanguageToolService;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SpellcheckController extends APIController
{
    #[DplanPermissions('feature_spellcheck')]
    #[Route(path: '/api/1.0/spellcheck/check', methods: ['POST'], name: 'core_spellcheck_check', options: ['expose' => true])]
    public function checkText(Request $request, LanguageToolService $languageToolService): Response
    {
        try {
            return new JsonResponse($languageToolService->checkText($request->toArray()));
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }

    #[DplanPermissions('feature_spellcheck')]
    #[Route(path: '/api/1.0/spellcheck/languages', methods: ['GET'], name: 'core_spellcheck_languages', options: ['expose' => true])]
    public function getLanguages(LanguageToolService $languageToolService): Response
    {
        try {
            return new JsonResponse($languageToolService->getLanguages());
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }
}
