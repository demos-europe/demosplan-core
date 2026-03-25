<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\OAuth;

use DateTime;
use DateTimeZone;
use demosplan\DemosPlanCoreBundle\Entity\User\OAuthToken;
use demosplan\DemosPlanCoreBundle\ValueObject\PendingRequestData;
use Exception;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Short-lived cache bridge for pending request data during the org-selection detour.
 *
 * When a multi-org user re-authenticates after token expiry, the authenticator reads
 * the buffered request from the OAuthToken entity and stores it here before storeTokens()
 * clears the entity. The OrganisationSelectionController reads from this cache to decide
 * whether to redirect to the pending page or replay the buffered POST.
 */
class PendingRequestCacheService
{
    private const CACHE_KEY_PREFIX = 'pending_reauth_request_';
    private const CACHE_TTL_SECONDS = 1800; // 30 minutes

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly TokenEncryptionService $encryptionService,
    ) {
    }

    /**
     * Store pending request data in cache for retrieval after org selection.
     *
     * Expects the body to be already encrypted (pass $decryptBody=false to getPendingRequest).
     */
    public function store(string $userId, PendingRequestData $requestData): void
    {
        try {
            $cacheKey = self::CACHE_KEY_PREFIX.$userId;

            // Delete existing entry to guarantee reset
            $this->cache->delete($cacheKey);

            $data = json_encode([
                'pageUrl'                => $requestData->getPageUrl(),
                'selectedOrganisationId' => $requestData->getSelectedOrganisationId(),
                'requestUrl'             => $requestData->getRequestUrl(),
                'method'                 => $requestData->getMethod(),
                'encryptedBody'          => $requestData->getBody(),
                'contentType'            => $requestData->getContentType(),
                'hasFiles'               => $requestData->getHasFiles(),
                'filesMetadata'          => $requestData->getFilesMetadata(),
                'timestamp'              => $requestData->getTimestamp()->format('Y-m-d H:i:s'),
            ], JSON_THROW_ON_ERROR);

            $this->cache->get($cacheKey, function (ItemInterface $item) use ($data): string {
                $item->expiresAfter(self::CACHE_TTL_SECONDS);

                return $data;
            });

            $this->logger->info('Pending request data cached for org-selection flow', [
                'user_id'  => $userId,
                'page_url' => $requestData->getPageUrl(),
                'ttl'      => self::CACHE_TTL_SECONDS,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to cache pending request data', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Retrieve pending request data from cache.
     *
     * The encrypted body is decrypted before filling the value object.
     * Returns null if no entry exists or it has expired.
     */
    public function retrieve(string $userId): ?PendingRequestData
    {
        try {
            $cacheKey = self::CACHE_KEY_PREFIX.$userId;

            $cached = $this->cache->get($cacheKey, function (): string {
                return '';
            });

            if ('' === $cached) {
                $this->cache->delete($cacheKey);

                return null;
            }

            $data = json_decode($cached, true, 512, JSON_THROW_ON_ERROR);

            $clearBody = null;
            if (null !== ($data['encryptedBody'] ?? null)) {
                $clearBody = $this->encryptionService->decrypt($data['encryptedBody']);
            }

            $timezone = new DateTimeZone(OAuthToken::TIMEZONE);
            $timestamp = DateTime::createFromFormat('Y-m-d H:i:s', $data['timestamp'], $timezone);

            $requestData = new PendingRequestData();
            $requestData->fill([
                'pageUrl'                => $data['pageUrl'],
                'selectedOrganisationId' => $data['selectedOrganisationId'] ?? null,
                'requestUrl'             => $data['requestUrl'] ?? null,
                'method'                 => $data['method'] ?? null,
                'body'                   => $clearBody,
                'contentType'            => $data['contentType'] ?? null,
                'hasFiles'               => $data['hasFiles'] ?? false,
                'filesMetadata'          => $data['filesMetadata'] ?? null,
                'timestamp'              => $timestamp,
            ]);

            return $requestData;
        } catch (Exception $e) {
            $this->logger->error('Failed to retrieve cached pending request data', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Delete pending request data from cache.
     */
    public function delete(string $userId): void
    {
        try {
            $cacheKey = self::CACHE_KEY_PREFIX.$userId;
            $this->cache->delete($cacheKey);

            $this->logger->info('Pending request cache entry deleted', [
                'user_id' => $userId,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to delete cached pending request data', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
