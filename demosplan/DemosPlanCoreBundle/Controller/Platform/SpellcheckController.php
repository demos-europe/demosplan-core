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
use demosplan\DemosPlanCoreBundle\Exception\LanguageToolUnavailableException;
use demosplan\DemosPlanCoreBundle\Logic\Spellcheck\LanguageToolService;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class SpellcheckController extends APIController
{
    #[DplanPermissions('feature_spellcheck')]
    #[Route(path: '/api/1.0/spellcheck/check', name: 'core_spellcheck_check', options: ['expose' => true], methods: ['POST'])]
    public function checkText(Request $request, LanguageToolService $languageToolService): Response
    {
        try {
            return new JsonResponse($languageToolService->checkText($request->toArray()));
        } catch (Exception $e) {
            return $this->handleSpellcheckError($e);
        }
    }

    #[DplanPermissions('feature_spellcheck')]
    #[Route(path: '/api/1.0/spellcheck/languages', name: 'core_spellcheck_languages', options: ['expose' => true], methods: ['GET'])]
    public function getLanguages(LanguageToolService $languageToolService): Response
    {
        try {
            return new JsonResponse($languageToolService->getLanguages());
        } catch (Exception $e) {
            return $this->handleSpellcheckError($e);
        }
    }

    /**
     * An unavailable LanguageTool is no application error. The request that finds it down tells the
     * user once, requests answered while the circuit breaker is open stay silent.
     */
    private function handleSpellcheckError(Exception $e): Response
    {
        if ($e instanceof LanguageToolUnavailableException) {
            return $this->createResponse(['errors' => [['status' => Response::HTTP_SERVICE_UNAVAILABLE]]], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if ($e instanceof TransportExceptionInterface || $e instanceof ServerExceptionInterface) {
            $this->logger->warning('LanguageTool is unavailable', ['exception' => $e]);
            $this->messageBag->add('info', 'spellcheck.unavailable');

            return $this->createResponse(['errors' => [['status' => Response::HTTP_SERVICE_UNAVAILABLE]]], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->handleApiError($e);
    }
}
