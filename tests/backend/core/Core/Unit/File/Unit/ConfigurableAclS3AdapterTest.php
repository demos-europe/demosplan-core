<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\File\Unit;

use Aws\CommandInterface;
use Aws\Result;
use Aws\S3\S3Client;
use demosplan\DemosPlanCoreBundle\Logic\File\ConfigurableAclS3Adapter;
use GuzzleHttp\Promise\Create;
use League\Flysystem\Config;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * Verifies the configurable ACL behaviour of {@link ConfigurableAclS3Adapter}.
 *
 * The adapter is driven against a real {@link S3Client} whose handler is replaced
 * with a closure that records each issued command together with the resulting
 * request, instead of performing network I/O. The contract that matters to
 * BucketOwnerEnforced buckets (e.g. IONOS) is the absence of the x-amz-acl
 * header on the wire, so the assertions inspect the captured request headers.
 */
class ConfigurableAclS3AdapterTest extends TestCase
{
    private const BUCKET = 'test-bucket';

    /** @var array<int, array{name: string, aclHeader: string}> */
    private array $captured = [];

    private function createAdapter(bool $disableAcl): ConfigurableAclS3Adapter
    {
        $this->captured = [];

        $client = new S3Client([
            'region'      => 'eu-central-2',
            'version'     => 'latest',
            'credentials' => ['key' => 'key', 'secret' => 'secret'],
            'handler'     => function (CommandInterface $command, RequestInterface $request) {
                $this->captured[] = [
                    'name'      => $command->getName(),
                    'aclHeader' => $request->getHeaderLine('x-amz-acl'),
                ];

                return Create::promiseFor(new Result([]));
            },
        ]);

        return new ConfigurableAclS3Adapter(
            $client,
            self::BUCKET,
            '',
            null,
            null,
            [],
            true,
            $disableAcl,
        );
    }

    /**
     * @return array<int, array{name: string, aclHeader: string}>
     */
    private function commandsNamed(string $name): array
    {
        return array_values(array_filter(
            $this->captured,
            static fn (array $command): bool => $command['name'] === $name,
        ));
    }

    public function testWriteOmitsAclHeaderWhenDisabled(): void
    {
        $adapter = $this->createAdapter(disableAcl: true);

        $adapter->write('2024/11/some-hash', 'contents', new Config());

        $putObjects = $this->commandsNamed('PutObject');
        self::assertCount(1, $putObjects);
        self::assertSame('', $putObjects[0]['aclHeader']);
    }

    public function testWriteStreamOmitsAclHeaderWhenDisabled(): void
    {
        $adapter = $this->createAdapter(disableAcl: true);

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, 'contents');
        rewind($stream);

        $adapter->writeStream('2024/11/some-hash', $stream, new Config());

        $putObjects = $this->commandsNamed('PutObject');
        self::assertCount(1, $putObjects);
        self::assertSame('', $putObjects[0]['aclHeader']);
    }

    public function testWriteSendsAclHeaderWhenNotDisabled(): void
    {
        $adapter = $this->createAdapter(disableAcl: false);

        $adapter->write('2024/11/some-hash', 'contents', new Config());

        $putObjects = $this->commandsNamed('PutObject');
        self::assertCount(1, $putObjects);
        self::assertSame('private', $putObjects[0]['aclHeader']);
    }

    public function testCopyOmitsAclHeaderWhenDisabled(): void
    {
        $adapter = $this->createAdapter(disableAcl: true);

        $adapter->copy('source/key', 'destination/key', new Config());

        $copyObjects = $this->commandsNamed('CopyObject');
        self::assertCount(1, $copyObjects);
        self::assertSame('', $copyObjects[0]['aclHeader']);
    }

    public function testCopySendsAclHeaderWhenNotDisabled(): void
    {
        $adapter = $this->createAdapter(disableAcl: false);

        $adapter->copy('source/key', 'destination/key', new Config());

        $copyObjects = $this->commandsNamed('CopyObject');
        self::assertCount(1, $copyObjects);
        self::assertSame('private', $copyObjects[0]['aclHeader']);
    }

    public function testSetVisibilityIsNoOpWhenDisabled(): void
    {
        $adapter = $this->createAdapter(disableAcl: true);

        $adapter->setVisibility('2024/11/some-hash', 'private');

        self::assertSame([], $this->commandsNamed('PutObjectAcl'));
    }
}
