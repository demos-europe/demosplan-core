<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Logic\OAuth;

use DateTime;
use DateTimeZone;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\OAuth\OAuthTokenFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\OAuthToken;
use demosplan\DemosPlanCoreBundle\Logic\OAuth\OAuthTokenStorageService;
use demosplan\DemosPlanCoreBundle\Repository\OAuthTokenRepository;
use demosplan\DemosPlanCoreBundle\Utilities\Crypto\SecretEncryptor;
use Tests\Base\FunctionalTestCase;

class OAuthTokenStorageServiceTest extends FunctionalTestCase
{
    private const TEST_API_URL = '/api/2.0/statement';
    private const TEST_PAGE_URL = '/verfahren/123/import';
    private const TIMEZONE = 'Europe/Berlin';

    /** @var OAuthTokenStorageService */
    protected $sut;

    /** @var OAuthTokenRepository */
    private $oauthTokenRepository;

    /** @var SecretEncryptor */
    private $encryptionService;

    protected function setUp(): void
    {
        parent::setUp();

        $container = self::getContainer();
        $this->sut = $container->get(OAuthTokenStorageService::class);
        $this->oauthTokenRepository = $container->get(OAuthTokenRepository::class);
        $this->encryptionService = $container->get(SecretEncryptor::class);
    }

    // ===== getPendingRequest =====

    public function testGetPendingRequestReturnsNullWhenNoTokenExists(): void
    {
        $result = $this->sut->getPendingRequest('non-existent-user-id');

        self::assertNull($result);
    }

    public function testGetPendingRequestReturnsNullWhenNoPendingData(): void
    {
        // Arrange: token exists but has no pending data
        $oauthToken = OAuthTokenFactory::createOne()->_real();

        // Act
        $result = $this->sut->getPendingRequest($oauthToken->getUser()->getId());

        // Assert
        self::assertNull($result);
    }

    public function testGetPendingRequestReturnsDataWithEncryptedBodyByDefault(): void
    {
        // Arrange: token with pending page URL and encrypted body
        $clearBody = '{"data":{"type":"Statement","attributes":{"text":"Testtext"}}}';
        $encryptedBody = $this->encryptionService->encrypt($clearBody);
        $timezone = new DateTimeZone(self::TIMEZONE);

        /** @var OAuthToken $oauthToken */
        $oauthToken = OAuthTokenFactory::createOne()->_real();
        $oauthToken->setPendingPageUrl('/faq');
        $oauthToken->setPendingRequestUrl(self::TEST_API_URL);
        $oauthToken->setPendingRequestMethod('POST');
        $oauthToken->setPendingRequestContentType('application/vnd.api+json');
        $oauthToken->setPendingRequestBody($encryptedBody);
        $oauthToken->setPendingRequestTimestamp(new DateTime('now', $timezone));
        $this->getEntityManager()->flush();

        // Act: default decryptBody=false
        $result = $this->sut->getPendingRequest($oauthToken->getUser()->getId());

        // Assert: body is still encrypted
        self::assertNotNull($result);
        self::assertSame('/faq', $result->getPageUrl());
        self::assertSame(self::TEST_API_URL, $result->getRequestUrl());
        self::assertSame('POST', $result->getMethod());
        self::assertSame($encryptedBody, $result->getBody());
        self::assertNotSame($clearBody, $result->getBody());
    }

    public function testGetPendingRequestDecryptsBodyWhenRequested(): void
    {
        // Arrange
        $clearBody = '{"data":{"type":"Statement","attributes":{"text":"Testtext"}}}';
        $timezone = new DateTimeZone(self::TIMEZONE);

        /** @var OAuthToken $oauthToken */
        $oauthToken = OAuthTokenFactory::createOne()->_real();
        $oauthToken->setPendingPageUrl('/faq');
        $oauthToken->setPendingRequestUrl(self::TEST_API_URL);
        $oauthToken->setPendingRequestMethod('POST');
        $oauthToken->setPendingRequestBody($this->encryptionService->encrypt($clearBody));
        $oauthToken->setPendingRequestTimestamp(new DateTime('now', $timezone));
        $this->getEntityManager()->flush();

        // Act: decryptBody=true
        $result = $this->sut->getPendingRequest($oauthToken->getUser()->getId(), true);

        // Assert: body is decrypted
        self::assertSame($clearBody, $result->getBody());
    }

    public function testGetPendingRequestReturnsPageUrlOnlyEntry(): void
    {
        // Arrange: only page URL set (GET request, no buffered POST)
        $timezone = new DateTimeZone(self::TIMEZONE);

        /** @var OAuthToken $oauthToken */
        $oauthToken = OAuthTokenFactory::createOne()->_real();
        $oauthToken->setPendingPageUrl(self::TEST_PAGE_URL);
        $oauthToken->setPendingRequestTimestamp(new DateTime('now', $timezone));
        $this->getEntityManager()->flush();

        // Act
        $result = $this->sut->getPendingRequest($oauthToken->getUser()->getId());

        // Assert
        self::assertNotNull($result);
        self::assertSame(self::TEST_PAGE_URL, $result->getPageUrl());
        self::assertNull($result->getRequestUrl());
        self::assertNull($result->getMethod());
        self::assertNull($result->getBody());
    }

    // ===== deleteTokensUnlessPendingData =====

    public function testDeleteTokensUnlessPendingDataDeletesWhenNoPendingData(): void
    {
        // Arrange
        /** @var OAuthToken $oauthToken */
        $oauthToken = OAuthTokenFactory::createOne()->_real();
        $userId = $oauthToken->getUser()->getId();

        // Act
        $this->sut->deleteTokensUnlessPendingData($userId);

        // Assert: token is deleted
        self::assertNull($this->oauthTokenRepository->findByUserId($userId));
    }

    public function testDeleteTokensUnlessPendingDataPreservesWhenPendingDataExists(): void
    {
        // Arrange: token with pending page URL
        $timezone = new DateTimeZone(self::TIMEZONE);

        /** @var OAuthToken $oauthToken */
        $oauthToken = OAuthTokenFactory::createOne()->_real();
        $oauthToken->setPendingPageUrl(self::TEST_PAGE_URL);
        $oauthToken->setPendingRequestTimestamp(new DateTime('now', $timezone));
        $this->getEntityManager()->flush();

        $userId = $oauthToken->getUser()->getId();

        // Act
        $this->sut->deleteTokensUnlessPendingData($userId);

        // Assert: token is preserved
        self::assertNotNull($this->oauthTokenRepository->findByUserId($userId));
    }

    public function testDeleteTokensUnlessPendingDataHandlesNonExistentUser(): void
    {
        // Act: should not throw
        $this->sut->deleteTokensUnlessPendingData('non-existent-user-id');

        // Assert: no exception means success
        self::assertTrue(true);
    }

    // ===== storePendingPageUrl =====

    public function testStorePendingPageUrlSetsUrlAndClearsTokens(): void
    {
        // Arrange
        /** @var OAuthToken $oauthToken */
        $oauthToken = OAuthTokenFactory::createOne()->_real();
        $oauthToken->setAccessToken('fake-encrypted-token');
        $oauthToken->setAccessTokenExpiresAt(new DateTime('+1 hour'));
        $this->getEntityManager()->flush();

        // Act
        $this->sut->storePendingPageUrl($oauthToken, self::TEST_PAGE_URL);

        // Assert
        self::assertSame(self::TEST_PAGE_URL, $oauthToken->getPendingPageUrl());
        self::assertNull($oauthToken->getAccessToken());
        self::assertNotNull($oauthToken->getPendingRequestTimestamp());
    }

    public function testStorePendingPageUrlDoesNotOverwriteExistingTimestamp(): void
    {
        // Arrange: token already has a timestamp from a previous buffer
        $timezone = new DateTimeZone(self::TIMEZONE);
        $originalTimestamp = new DateTime('2026-01-01 12:00:00', $timezone);

        /** @var OAuthToken $oauthToken */
        $oauthToken = OAuthTokenFactory::createOne()->_real();
        $oauthToken->setPendingRequestTimestamp($originalTimestamp);
        $this->getEntityManager()->flush();

        // Act
        $this->sut->storePendingPageUrl($oauthToken, '/faq');

        // Assert: timestamp unchanged
        self::assertSame(
            $originalTimestamp->format('Y-m-d H:i:s'),
            $oauthToken->getPendingRequestTimestamp()->format('Y-m-d H:i:s')
        );
    }

    // ===== bufferRequestIfNeeded =====

    public function testBufferRequestIfNeededSkipsGetRequests(): void
    {
        // Arrange
        $oauthToken = OAuthTokenFactory::createOne()->_real();

        // Act: bufferRequestIfNeeded reads from RequestStack — in test context
        // the current request is null or GET, so it should skip
        $this->sut->bufferRequestIfNeeded($oauthToken);

        // Assert: no pending request data
        self::assertFalse($oauthToken->hasPendingRequest());
    }
}
