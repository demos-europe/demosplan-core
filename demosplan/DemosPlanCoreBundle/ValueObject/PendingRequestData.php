<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject;

use DateTime;

/**
 * Value object for pending request data (buffered during token expiration).
 *
 * @method string      getPageUrl()
 * @method string      getRequestUrl()
 * @method string      getMethod()
 * @method string|null getBody()
 * @method string|null getContentType()
 * @method bool        getHasFiles()
 * @method array|null  getFilesMetadata()
 * @method DateTime    getTimestamp()
 */
class PendingRequestData extends ValueObject
{
    /**
     * The page URL to redirect the user back to after re-authentication.
     */
    protected string $pageUrl;

    /**
     * The actual request URL to replay.
     */
    protected string $requestUrl;

    /**
     * HTTP method (GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS).
     */
    protected string $method;

    /**
     * Decrypted request body (null for GET/HEAD/OPTIONS/DELETE requests).
     */
    protected ?string $body = null;

    /**
     * Content type of the request (e.g., 'application/json', 'application/x-www-form-urlencoded').
     */
    protected ?string $contentType = null;

    /**
     * Whether the request includes file uploads.
     */
    protected bool $hasFiles = false;

    /**
     * Metadata about uploaded files (if any).
     */
    protected ?array $filesMetadata = null;

    /**
     * Timestamp when the request was buffered.
     */
    protected DateTime $timestamp;

    /**
     * Fill the value object with clear (unencrypted) request data.
     *
     * Note: This method expects all data to be in clear/unencrypted form.
     * Encryption/decryption must be handled by the caller before passing data to fill().
     *
     * @param array $data Array with keys: pageUrl, requestUrl, method, body, contentType, hasFiles, filesMetadata, timestamp
     */
    public function fill(array $data): void
    {
        $this->pageUrl = $data['pageUrl'];
        $this->requestUrl = $data['requestUrl'];
        $this->method = $data['method'];
        $this->timestamp = $data['timestamp'];
        $this->contentType = $data['contentType'] ?? null;
        $this->hasFiles = $data['hasFiles'] ?? false;
        $this->filesMetadata = $data['filesMetadata'] ?? null;
        $this->body = $data['body'] ?? null;

        $this->lock();
    }
}
