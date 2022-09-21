<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Import\MaillaneConnector;

use Cocur\Slugify\Slugify;
use demosplan\DemosPlanCoreBundle\Entity\EmailAddress;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\MaillaneConnection;
use demosplan\DemosPlanCoreBundle\Exception\JsonException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Http\JsonApiClient;
use demosplan\DemosPlanCoreBundle\Logic\ILogic\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Logic\Import\MaillaneConnector\Exception\MaillaneApiException;
use demosplan\DemosPlanCoreBundle\Logic\Import\MaillaneConnector\Exception\MaillaneConfigurationException;
use demosplan\DemosPlanCoreBundle\Repository\EmailAddressRepository;
use demosplan\DemosPlanCoreBundle\Repository\StatementImportEmail\MaillaneConnectionRepository;
use demosplan\DemosPlanCoreBundle\Utilities\Json;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use EDT\JsonApi\Schema\ContentField;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Throwable;

/**
 * Manage access synchronization with a Maillane instance.
 *
 * Access synchronization entails handling accounts and
 * users and ensuring user lists between a local mapping
 * on e.g. procedures are kept in sync with Maillanes database.
 *
 * The terminology differs a bit between DemosPlan and Maillane:
 * RecipientEmailAddress => Account
 * AllowedEmailAddress   => User
 */
class MaillaneSynchronizer
{
    private const MAILLANE_X_HEADER = 'X-Maillane-Consumer-Host';
    /**
     * @var string
     */
    private $emailDomain;

    /**
     * @var MaillaneRouter
     */
    private $maillaneRouter;

    /**
     * @var JsonApiClient
     */
    private $jsonApiClient;

    /**
     * @var EmailAddressRepository
     */
    private $emailAddressRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var MessageBagInterface
     */
    private $messageBag;

    /**
     * @var MaillaneConnectionRepository
     */
    private $maillaneConnectionRepository;

    /**
     * @var string
     */
    private $hostname = '';

    public function __construct(
        EmailAddressRepository $emailAddressRepository,
        JsonApiClient $jsonApiClient,
        LoggerInterface $logger,
        MaillaneConnectionRepository $maillaneConnectionRepository,
        MaillaneRouter $maillaneRouter,
        MessageBagInterface $messageBag,
        ParameterBagInterface $parameterBag,
        RequestStack $requestStack
    ) {
        if (!$parameterBag->has('maillane_email_domain')) {
            throw MaillaneConfigurationException::missingParameter('maillane_email_domain');
        }

        $this->emailDomain = $parameterBag->get('maillane_email_domain');

        $this->emailAddressRepository = $emailAddressRepository;
        $this->jsonApiClient = $jsonApiClient;
        $this->logger = $logger;
        $this->maillaneConnectionRepository = $maillaneConnectionRepository;
        $this->maillaneRouter = $maillaneRouter;
        $this->messageBag = $messageBag;

        $request = $requestStack->getCurrentRequest();
        if ($request instanceof Request) {
            $this->hostname = $request->getHttpHost();
        }
    }

    /**
     * Create a new MaillaneConnection.
     *
     * This creates a new Account in the connected Maillane instance
     * and returns the reference to that account.
     *
     * This method should be used by another service to attach the
     * MaillaneConnection to an entity which can actually receive
     * mails, e.g. Procedure, and therefore **DOES NOT YET FLUSH
     * THE NEWLY CREATED ENTITY**.
     *
     * @throws MaillaneApiException if the interaction with the server fails
     * @throws ViolationsException  if validation fails
     */
    public function createAccount(string $email): MaillaneConnection
    {
        $url = $this->maillaneRouter->accountList();
        try {
            $this->logger->debug('Hostname for API request headers', [
                'header'         => self::MAILLANE_X_HEADER,
                'hostname'       => $this->hostname,
            ]);
            $response = $this->jsonApiClient->apiRequest(
                Request::METHOD_POST,
                $url,
                $this->jsonApiClient->createRequestData('Account', compact('email')),
                [self::MAILLANE_X_HEADER => $this->hostname]
            );
            $this->jsonApiClient->verifyApiResponse($response, [Response::HTTP_CREATED]);

            $responseContent = Json::decodeToArray($response->getContent());
        } catch (Throwable $e) {
            throw MaillaneApiException::interactionFailed($url);
        }

        $maillaneConnection = $this->maillaneConnectionRepository->createMaillaneConnection(
            $responseContent[ContentField::DATA][ContentField::ID],
            $responseContent[ContentField::DATA][ContentField::ATTRIBUTES]['email']
        );
        $this->maillaneConnectionRepository->persistEntities([$maillaneConnection]);

        return $maillaneConnection;
    }

    /**
     * Synchronizes the allowedSenders/users with Maillane.
     * This means ignoring all users in the update data that already exist in
     * Maillane, adding all that do not and deleting all existing in Maillane but not
     * in the update data.
     *
     * @param array<string, mixed> $updateData
     *
     * @throws HttpExceptionInterface
     * @throws JsonException
     * @throws MessageBagException
     * @throws TransportExceptionInterface
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function editAccount(MaillaneConnection $maillaneConnection, array $updateData): void
    {
        // Get sender addresses that are in the updateData
        $mailAddressesToUpdate = [];
        if (isset($updateData['allowedSenderEmailAddresses']) && 0 < count(
                $updateData['allowedSenderEmailAddresses']
            )) {
            $mailAddressesToUpdate = $updateData['allowedSenderEmailAddresses'];
        }

        // Get the pre-update list of already existing sender addresses
        $allowedSenderEmailAddresses = $maillaneConnection->getAllowedSenderEmailAddresses();
        // get all users for the account from Maillane
        $existingUsersMaillane = $this->getAllUsersForTheAccountFromMaillane(
            $maillaneConnection->getMaillaneAccountId()
        )[ContentField::DATA];

        // Add new sender to the maillane account
        $savedAllowedMailAddresses = $this->addAllowedEmailAddressesToTheAccount(
            $maillaneConnection->getMaillaneAccountId(),
            $mailAddressesToUpdate,
            $existingUsersMaillane
        );

        // Keep emailAddresses that already exist and are in updateData
        foreach ($allowedSenderEmailAddresses as $allowedSenderEmailAddress) {
            if (in_array($allowedSenderEmailAddress->getFullAddress(), $mailAddressesToUpdate, true)
                && in_array(
                    $allowedSenderEmailAddress->getFullAddress(),
                    $existingUsersMaillane,
                    true
                )) {
                $savedAllowedMailAddresses[] = $allowedSenderEmailAddress;
            }
        }

        // Delete no longer used users in Maillane
        $savedAllowedMailAddresses = $this->deleteNoLongerUsedUsersInMaillane(
            $existingUsersMaillane,
            $mailAddressesToUpdate,
            $maillaneConnection->getMaillaneAccountId(),
            $allowedSenderEmailAddresses,
            $savedAllowedMailAddresses
        );

        // Only persist and flush the successfully generated senders
        $maillaneConnection->setAllowedSenderEmailAddresses(
            new ArrayCollection($savedAllowedMailAddresses)
        );
    }

    /**
     * Tries to delete an account in Maillane.
     */
    public function deleteAccount(string $accountId): void
    {
        $requestData = $this->jsonApiClient->createRequestData('Account', []);
        $url = $this->maillaneRouter->accountDetail($accountId);

        try {
            $response = $this->jsonApiClient->apiRequest(Request::METHOD_DELETE, $url, $requestData);
            $this->jsonApiClient->verifyApiResponse($response, [Response::HTTP_OK]);
        } catch (Throwable $e) {
            $this->logger->error('Could not delete Maillane account', [
                'url'       => $url,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate a partially randomized email address for Maillane account.
     */
    public function generateRecipientMailAddress(string $mainIdentifier): string
    {
        $slugify = Slugify::create();

        $procedureName = substr($slugify->slugify($mainIdentifier), 0, 10);

        $salt = substr(md5(Uuid::uuid4()->toString()), 0, 8);

        return strtolower($procedureName.'.'.substr($salt, 0, 8).'@'.$this->emailDomain);
    }

    /**
     * Fetches all users for a given account from Maillane.
     *
     * @return array<int, string>
     *
     * @throws HttpExceptionInterface
     * @throws TransportExceptionInterface
     * @throws JsonException
     */
    private function getAllUsersForTheAccountFromMaillane(string $accountId): array
    {
        $fetchRequestData = $this->jsonApiClient->createRequestData('User', []);
        $fetchUrl = $this->maillaneRouter->userList($accountId);

        $fetchResponse = $this->jsonApiClient->apiRequest(
            Request::METHOD_GET,
            $fetchUrl,
            $fetchRequestData
        );
        $this->jsonApiClient->verifyApiResponse($fetchResponse, [Response::HTTP_OK]);

        return Json::decodeToArray($fetchResponse->getContent());
    }

    /**
     * Adds new users to a given Maillane account.
     *
     * @param array<int, string> $mailAddressesToUpdate
     * @param array<int, string> $existingUsersMaillane
     *
     * @return array<int, string>
     *
     * @throws MessageBagException
     */
    private function addAllowedEmailAddressesToTheAccount(
        string $accountId,
        array $mailAddressesToUpdate,
        array $existingUsersMaillane
    ): array {
        $postUrl = $this->maillaneRouter->userList($accountId);
        $savedAllowedMailAddresses = [];

        // Try to create all users unknown to Maillane
        foreach ($mailAddressesToUpdate as $allowedEmailAddress) {
            if (!in_array($allowedEmailAddress, $existingUsersMaillane, true)) {
                $requestData = $this->jsonApiClient->createRequestData('User', [
                    'email' => $allowedEmailAddress,
                ]);

                try {
                    $response = $this->jsonApiClient->apiRequest(
                        Request::METHOD_POST,
                        $postUrl,
                        $requestData,
                    );
                    $this->jsonApiClient->verifyApiResponse($response, [Response::HTTP_CREATED]);

                    $savedAllowedMailAddresses[] = $this->emailAddressRepository->getOrCreateEmailAddress(
                        $allowedEmailAddress
                    );
                } catch (Throwable $e) {
                    $this->logger->error('Could not create Maillane user', [
                        'url'       => $postUrl,
                        'exception' => $e->getMessage(),
                    ]);
                    $this->messageBag->add(
                        'error',
                        'error.statement.import.mail.maillane.user.creation.failed',
                        ['senderEmail' => $allowedEmailAddress]
                    );
                }
            }
        }
        $this->emailAddressRepository->persistEntities($savedAllowedMailAddresses);

        return $savedAllowedMailAddresses;
    }

    /**
     * Deletes users in Maillane that have been removed in DemosPlan.
     *
     * @param array<string, array>          $existingUsersMaillane
     * @param array<int, array>             $mailAddressesToUpdate
     * @param Collection<int, EmailAddress> $allowedSenderEmailAddresses
     * @param array<int, EmailAddress>      $savedAllowedMailAddresses
     *
     * @return array<int, EmailAddress>
     *
     * @throws MessageBagException
     */
    private function deleteNoLongerUsedUsersInMaillane(
        array $existingUsersMaillane,
        array $mailAddressesToUpdate,
        string $accountId,
        Collection $allowedSenderEmailAddresses,
        array $savedAllowedMailAddresses
    ): array {
        foreach ($existingUsersMaillane as $user) {
            if (!in_array($user, $mailAddressesToUpdate, true)) {
                $userId = $user[ContentField::ID];
                $userMail = $user[ContentField::ATTRIBUTES]['email'];
                $deleteData = $this->jsonApiClient->createRequestData('User', []);
                $deleteUrl = $this->maillaneRouter->userDetail($accountId, $userId);

                try {
                    $response = $this->jsonApiClient->apiRequest(
                        Request::METHOD_DELETE,
                        $deleteUrl,
                        $deleteData
                    );
                    $this->jsonApiClient->verifyApiResponse($response, [Response::HTTP_OK]);
                } catch (Throwable $e) {
                    $this->messageBag->add(
                        'error',
                        sprintf('Absenderadresse %s konnte nicht korrekt gelöscht werden.', $user)
                    );

                    $savedAllowedMailAddresses[] = $allowedSenderEmailAddresses->filter(
                        function (EmailAddress $emailAddress) use ($userMail) {
                            return $userMail === $emailAddress->getFullAddress();
                        }
                    )->first();
                }
            }
        }

        return $savedAllowedMailAddresses;
    }
}
