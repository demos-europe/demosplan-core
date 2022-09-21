<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\MaillaneConnector;

use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\APIController;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PrefilledResourceTypeProvider;
use demosplan\DemosPlanCoreBundle\Logic\Import\MaillaneConnector\MailFetcher;
use demosplan\DemosPlanCoreBundle\Logic\Logger\ApiLogger;
use demosplan\DemosPlanCoreBundle\Repository\StatementImportEmail\MaillaneConnectionRepository;
use demosplan\DemosPlanCoreBundle\Response\APIResponse;
use demosplan\DemosPlanCoreBundle\Response\EmptyResponse;
use demosplan\DemosPlanCoreBundle\Utilities\Json;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use EDT\JsonApi\Schema\ContentField;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class MaillaneApiController extends APIController
{
    /**
     * @var MaillaneConnectionRepository
     */
    private $maillaneConnectionRepository;

    /**
     * @var MailFetcher
     */
    private $mailFetcher;

    public function __construct(
        ApiLogger $apiLogger,
        MaillaneConnectionRepository $maillaneConnectionRepository,
        MailFetcher $mailFetcher,
        PrefilledResourceTypeProvider $resourceTypeProvider,
        TranslatorInterface $translator)
    {
        parent::__construct($apiLogger, $resourceTypeProvider, $translator);

        $this->maillaneConnectionRepository = $maillaneConnectionRepository;
        $this->mailFetcher = $mailFetcher;
    }

    /**
     * Triggers a fetch for all given mail-urls to retrieve new mails from Maillane
     * and saves them as new StatementImportMails.
     *
     * @Route(
     *        path="/api/plugins/maillane-connector/account/{accountId}/mail/{authToken}",
     *        methods={"POST"},
     *        name="dplan_api_maillane_connector_account_mail"
     *     )
     *
     * **PLEASE NOTE**: We technically want feature_import_statement_via_email as access
     * permission here. Due to current time constraints, this is not possible as we
     * do not want to give the guest user that permission. Authenticating from maillane
     * with any other user would require implementing JWT support which will happen
     * in the near future. Until then, access is limited with a purpose-generated
     * token stored in `maillane_api_token`.
     *
     * @DplanPermissions("area_demosplan")
     *
     * @return APIResponse
     */
    public function importNewImportableMailAction(Request $request, string $authToken, string $accountId): Response
    {
        if ($authToken !== $this->getParameter('maillane_api_token')) {
            return new EmptyResponse();
        }

        // We get mail links from Maillane
        $requestBody = Json::decodeToArray($request->getContent());
        $availableMails = $requestBody[ContentField::DATA];

        // we have to check if a corresponding procedure exists and get it if so
        try {
            $procedure = $this->maillaneConnectionRepository->getProcedureByMaillaneAccountId($accountId);
            $this->mailFetcher->fetchMailsForAccount($availableMails, $procedure);
        } catch (NoResultException|NonUniqueResultException $e) {
            $this->logger->error('No unique procedure found for given account ID', [
                'accountId' => $accountId,
                'exception' => $e->getMessage()
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Could not fetch all mails for given account ID', [
                'accountId' => $accountId,
                'exception' => $e->getMessage()
            ]);
        }

        return new EmptyResponse();
    }
}
