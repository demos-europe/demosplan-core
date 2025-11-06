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
use League\Flysystem\PathPrefixer;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\Visibility;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use League\MimeTypeDetection\MimeTypeDetector;
use Throwable;

/**
 * AWS S3 adapter that does not set ACLs on uploaded objects.
 *
 * This is needed for S3/IONOS Cloud buckets that have ACLs disabled.
 * The standard AwsS3V3Adapter always tries to set ACLs via the upload() method,
 * which causes "AccessControlListNotSupported" errors with buckets configured
 * with BucketOwnerEnforced ownership controls.
 *
 * This adapter overrides write() and writeStream() to use putObject() directly
 * without ACL parameters instead of the upload() helper method.
 *
 * @see https://docs.aws.amazon.com/AmazonS3/latest/userguide/about-object-ownership.html
 */
class AwsS3V3AdapterWithoutAcl extends AwsS3V3Adapter
{
    private S3ClientInterface $client;
    private string $bucket;
    private PathPrefixer $prefixer;
    private MimeTypeDetector $mimeTypeDetector;
    private array $options;

    public function __construct(
        S3ClientInterface $client,
        string $bucket,
        string $prefix = '',
        ?VisibilityConverter $visibility = null,
        ?MimeTypeDetector $mimeTypeDetector = null,
        array $options = [],
        bool $streamReads = true
    ) {
        parent::__construct($client, $bucket, $prefix, $visibility ?? new PortableVisibilityConverter(), $mimeTypeDetector, $options, $streamReads);

        $this->client = $client;
        $this->bucket = $bucket;
        $this->prefixer = new PathPrefixer($prefix);
        $this->mimeTypeDetector = $mimeTypeDetector ?? new FinfoMimeTypeDetector();
        $this->options = $options;
    }

    /**
     * Write without setting ACL.
     */
    public function write(string $path, string $contents, Config $config): void
    {
        $this->uploadWithoutAcl($path, $contents, $config);
    }

    /**
     * Write stream without setting ACL.
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->uploadWithoutAcl($path, $contents, $config);
    }

    /**
     * Upload file without ACL parameter.
     */
    private function uploadWithoutAcl(string $path, $body, Config $config): void
    {
        $key = $this->prefixer->prefixPath($path);
        $params = [
            'Bucket' => $this->bucket,
            'Key' => $key,
            'Body' => $body,
        ];

        // Add mimetype if not explicitly set
        if ($mimeType = $config->get('mimetype') ?? $this->mimeTypeDetector->detectMimeType($key, $body)) {
            $params['ContentType'] = $mimeType;
        }

        // Add other configured options (but NOT ACL)
        foreach ($this->options as $key => $value) {
            if ($key !== 'ACL') {
                $params[$key] = $value;
            }
        }

        // Add config options (but NOT ACL or visibility)
        $configOptions = $config->get('options', []);
        foreach ($configOptions as $key => $value) {
            if ($key !== 'ACL' && $key !== Config::OPTION_VISIBILITY) {
                $params[$key] = $value;
            }
        }

        try {
            $this->client->putObject($params);
        } catch (Throwable $exception) {
            throw UnableToWriteFile::atLocation($path, $exception->getMessage(), $exception);
        }
    }
}
