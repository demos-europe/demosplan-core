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

use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementImportEmail\StatementImportEmail;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\JsonException;
use demosplan\DemosPlanCoreBundle\Http\JsonApiClient;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Repository\StatementImportEmail\MaillaneConnectionRepository;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\Utilities\Json;
use demosplan\DemosPlanUserBundle\Logic\UserService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use EDT\JsonApi\Schema\ContentField;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Throwable;

/**
 * Handles everything related to fetching mails from Maillane.
 */
class MailFetcher
{
    /**
     * @var FileService
     */
    private $fileService;

    /**
     * @var MaillaneConnectionRepository
     */
    private $maillaneConnectionRepository;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var JsonApiClient
     */
    private $jsonApiClient;
    /**
     * @var UrlGeneratorInterface
     */
    private $router;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        FileService $fileService,
        JsonApiClient $jsonApiClient,
        LoggerInterface $logger,
        MaillaneConnectionRepository $maillaneConnectionRepository,
        UrlGeneratorInterface $router,
        UserService $userService
    )
    {
        $this->fileService = $fileService;
        $this->jsonApiClient = $jsonApiClient;
        $this->maillaneConnectionRepository = $maillaneConnectionRepository;
        $this->userService = $userService;
        $this->router = $router;
        $this->logger = $logger;
    }

    /**
     * Tries to fetch the individual emails from Maillane based on the urls sent by Maillane.
     *
     * @param array<int, string[]> $availableMails
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws HttpExceptionInterface
     * @throws TransportExceptionInterface
     * @throws JsonException|Throwable
     */
    public function fetchMailsForAccount(array $availableMails, Procedure $procedure): void
    {
        foreach ($availableMails as $mailInfo) {
            $url = $mailInfo[ContentField::ATTRIBUTES]['url'];
            $mailResponse = $this->jsonApiClient->apiRequest(Request::METHOD_GET, $url, []);
            $decodedBody = Json::decodeToArray($mailResponse->getContent());
            $content = $decodedBody[ContentField::DATA][ContentField::ATTRIBUTES];

            // save mail as StatementImportEmail
            $statementImportEmail = $this->createStatementImportEmail($content, $procedure, $url);
            $this->maillaneConnectionRepository->persistEntities([$statementImportEmail]);
            $this->maillaneConnectionRepository->flushEverything();
            // delete fetched mails to keep Maillane clean
            $this->jsonApiClient->apiRequest(Request::METHOD_DELETE, $url, []);
        }
    }

    /**
     * Creates a StatementImportEmail with the data from Maillane.
     *
     * @param array<int, mixed> $content
     * @throws JsonException
     * @throws TransportExceptionInterface
     * @throws HttpExceptionInterface
     * @throws Throwable
     * @throws FileNotFoundException
     */
    private function createStatementImportEmail(
        array $content,
        Procedure $procedure,
        string $url
    ): StatementImportEmail {
        $statementImportEmail = new StatementImportEmail();
        $statementImportEmail->setProcedure($procedure);
        $statementImportEmail->setSubject($content['headers']['Subject']);
        $statementImportEmail->setHtmlTextContent($content['html_content'] ?? '');
        $statementImportEmail->setPlainTextContent($content['text_content'] ?? '');
        $statementImportEmail->setRawEmailText($content['raw'] ?? '');

        $statementImportEmail->setFrom('-');
        if (array_key_exists('From', $content['headers'])) {
            $from = $content['headers']['From'];
            $statementImportEmail->setFrom($from);

            // Check if we can match a user based on the sender mail address
            $mimeAddress = Address::create($from);
            $user = $this->userService->findDistinctUserByEmailOrLogin($mimeAddress->getAddress());
            if ($user instanceof User) {
                $statementImportEmail->setForwardingUser($user);
            }
        }

        // Fetch all attachments, save them and add them to the statementImportEmail
        $attachmentFiles = $this->collectAttachments($content['attachment_urls'], basename($url), $procedure);
        $statementImportEmail->setAttachments(new ArrayCollection($attachmentFiles));
        $this->removeImgTagsFromHtmlContent($statementImportEmail);

        return $statementImportEmail;
    }

    /**
     * Download and save attachments as temporary files
     *
     * @param array<int, string> $attachmentUrls
     * @return File[]
     * @throws JsonException
     * @throws Throwable
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @see File.
     *
     */
    private function collectAttachments(array $attachmentUrls, string $mailId, Procedure $procedure): array
    {
        $attachmentFiles = [];
        foreach ($attachmentUrls as $attachmentUrl) {
            $attachmentResponse = $this->jsonApiClient->apiRequest(
                Request::METHOD_GET,
                $attachmentUrl,
                []
            );
            $this->jsonApiClient->verifyApiResponse($attachmentResponse, [Response::HTTP_OK]);
            $attachmentHeaders = $attachmentResponse->getHeaders();

            $mimeTypes = new MimeTypes();
            $fileExtension = $mimeTypes->getExtensions(
                $attachmentHeaders['content-type'][0]
            );
            $basename = uniqid($mailId, true).'.'.$fileExtension[0];
            $path = DemosPlanPath::getTemporaryPath($basename);

            file_put_contents($path, $attachmentResponse->getContent());
            $data = $this->fileService->saveTemporaryFile(
                $path,
                $basename,
                null,
                $procedure->getId(),
            );

            $newFile = $this->fileService->getFileById($data->getId());
            if (null === $newFile) {
                throw new FileNotFoundException('Attachment from Maillane could not be saved.');
            }

            $attachmentFiles[] = $newFile;
        }

        return $attachmentFiles;
    }

    /**
     * This method removes img tags from the html content of a mail
     */
    private function removeImgTagsFromHtmlContent(StatementImportEmail $statementImportEmail): void
    {
        $htmlContent = $statementImportEmail->getHtmlTextContent();
        if ('' === $htmlContent) {
            return;
        }

        $htmlContent = preg_replace('/<img[^>]+>/', '', $htmlContent);

        $statementImportEmail->setHtmlTextContent($htmlContent);
    }
}
