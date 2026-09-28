<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\File;

use Aws\S3\S3ClientInterface;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\AwsS3V3\PortableVisibilityConverter;
use League\Flysystem\AwsS3V3\VisibilityConverter;
use League\Flysystem\Config;
use League\Flysystem\FilesystemException;
use League\Flysystem\PathPrefixer;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToWriteFile;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use League\MimeTypeDetection\MimeTypeDetector;
use Throwable;

/**
 * AWS S3 / S3-compatible adapter with configurable ACL handling.
 *
 * The standard AwsS3V3Adapter always attaches an ACL (x-amz-acl) to PutObject,
 * CopyObject and PutObjectAcl requests. Buckets with ownership controls set to
 * BucketOwnerEnforced (e.g. IONOS Cloud Object Storage) reject any ACL with
 * "AccessControlListNotSupported (client): The bucket does not allow ACLs".
 *
 * When $disableAcl is true this adapter still routes writes and copies through
 * the SDK's upload()/copy() helpers (so large objects keep their multipart
 * handling) but passes a null ACL, which drops the x-amz-acl header from the
 * request. setVisibility becomes a no-op, since object access is then governed
 * by bucket policies instead. When $disableAcl is false it delegates to the
 * parent unchanged, so existing ACL-based S3 deployments keep their behaviour.
 *
 * Toggle via the S3_DISABLE_ACL environment variable.
 *
 * @see https://docs.aws.amazon.com/AmazonS3/latest/userguide/about-object-ownership.html
 */
class ConfigurableAclS3Adapter extends AwsS3V3Adapter
{
    private readonly S3ClientInterface $client;
    private readonly string $bucket;
    private readonly PathPrefixer $prefixer;
    private readonly MimeTypeDetector $mimeTypeDetector;
    private readonly array $options;

    public function __construct(
        S3ClientInterface $client,
        string $bucket,
        string $prefix = '',
        ?VisibilityConverter $visibility = null,
        ?MimeTypeDetector $mimeTypeDetector = null,
        array $options = [],
        bool $streamReads = true,
        private readonly bool $disableAcl = false,
    ) {
        parent::__construct($client, $bucket, $prefix, $visibility ?? new PortableVisibilityConverter(), $mimeTypeDetector, $options, $streamReads);

        $this->client = $client;
        $this->bucket = $bucket;
        $this->prefixer = new PathPrefixer($prefix);
        $this->mimeTypeDetector = $mimeTypeDetector ?? new FinfoMimeTypeDetector();
        $this->options = $options;
    }

    public function write(string $path, string $contents, Config $config): void
    {
        if (!$this->disableAcl) {
            parent::write($path, $contents, $config);

            return;
        }

        $this->uploadWithoutAcl($path, $contents, $config);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        if (!$this->disableAcl) {
            parent::writeStream($path, $contents, $config);

            return;
        }

        $this->uploadWithoutAcl($path, $contents, $config);
    }

    public function createDirectory(string $path, Config $config): void
    {
        if (!$this->disableAcl) {
            parent::createDirectory($path, $config);

            return;
        }

        $this->uploadWithoutAcl(rtrim($path, '/').'/', '', $config);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        if (!$this->disableAcl) {
            parent::copy($source, $destination, $config);

            return;
        }

        if ($source === $destination) {
            return;
        }

        $options = $this->buildOptions($config);
        $options['MetadataDirective'] = $config->get('MetadataDirective', 'COPY');

        try {
            // Null ACL drops the x-amz-acl header (an empty string would still
            // send the header); ObjectCopier still falls back to a multipart
            // copy for objects above the size threshold.
            $this->client->copy(
                $this->bucket,
                $this->prefixer->prefixPath($source),
                $this->bucket,
                $this->prefixer->prefixPath($destination),
                null,
                $options,
            );
        } catch (Throwable $exception) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $exception);
        }
    }

    /**
     * Visibility is enforced through bucket policies when ACLs are disabled,
     * so there is nothing to set per object.
     *
     * @throws FilesystemException
     */
    public function setVisibility(string $path, string $visibility): void
    {
        if (!$this->disableAcl) {
            parent::setVisibility($path, $visibility);
        }
    }

    /**
     * Upload an object via the SDK's upload() helper with a null ACL.
     *
     * Passing null instead of an ACL drops the x-amz-acl header, while the
     * helper keeps the parent adapter's behaviour of switching to a multipart
     * upload once the object exceeds the size threshold.
     *
     * @param string|resource $body
     */
    private function uploadWithoutAcl(string $path, $body, Config $config): void
    {
        $key = $this->prefixer->prefixPath($path);
        $options = $this->buildOptions($config);

        if (!array_key_exists('ContentType', $options['params'])
            && $mimeType = $this->mimeTypeDetector->detectMimeType($key, $body)) {
            $options['params']['ContentType'] = $mimeType;
        }

        try {
            // Null ACL omits the x-amz-acl header (an empty string would still
            // send the header).
            $this->client->upload($this->bucket, $key, $body, null, $options);
        } catch (Throwable $exception) {
            throw UnableToWriteFile::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    /**
     * Build the options array for the upload()/copy() helpers.
     *
     * Mirrors the parent adapter's option forwarding (request params plus
     * multipart-upload options) but never forwards ACL, so it is never sent to
     * the bucket.
     *
     * @return array{params: array<string, mixed>}&array<string, mixed>
     */
    private function buildOptions(Config $config): array
    {
        $config = $config->withDefaults($this->options);
        $options = ['params' => []];

        if ($mimetype = $config->get('mimetype')) {
            $options['params']['ContentType'] = $mimetype;
        }

        foreach (self::AVAILABLE_OPTIONS as $option) {
            if ('ACL' === $option) {
                continue;
            }

            $value = $config->get($option, '__NOT_SET__');
            if ('__NOT_SET__' !== $value) {
                $options['params'][$option] = $value;
            }
        }

        foreach (self::MUP_AVAILABLE_OPTIONS as $option) {
            $value = $config->get($option, '__NOT_SET__');
            if ('__NOT_SET__' !== $value) {
                $options[$option] = $value;
            }
        }

        return $options;
    }
}
