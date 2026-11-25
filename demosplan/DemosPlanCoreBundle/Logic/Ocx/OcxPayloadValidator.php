<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Ocx;

use demosplan\DemosPlanCoreBundle\Exception\OcxProblemException;
use JsonException;
use stdClass;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * What this receiver accepts in a delivery, and the checks of manifests, parts and digests against it.
 */
class OcxPayloadValidator
{
    /**
     * OCX versions this receiver accepts on its base path.
     */
    public const OCX_VERSIONS = ['0.1'];

    /**
     * Module id => versions this receiver understands. A delivery that requires any other is refused.
     */
    private const SUPPORTED_MODULES = ['tags' => ['0.1']];

    /**
     * Upper bounds for the counts announced in a manifest; the schema has none.
     */
    private const MAX_PARTS = 10000;
    private const MAX_ENTRIES = 50000;

    /**
     * Validation cost grows with the number of entries, not with their size: one entry of 45 MB
     * validates in 0.1 s, 100,000 small entries take 4.6 s.
     */
    private const MAX_ENTRIES_PER_PART = 2000;

    public function __construct(
        private readonly OcxSchemaValidator $schemaValidator,
        #[Autowire('%ocx_max_body_size%')]
        private readonly int $maxBodySize,
        #[Autowire('%ocx_max_attachment_size%')]
        private readonly int $maxAttachmentSize,
    ) {
    }

    /**
     * As described by `capabilities.schema.json`.
     *
     * @return array<string, mixed>
     */
    public function getCapabilities(): array
    {
        $modules = [];
        foreach (self::SUPPORTED_MODULES as $id => $versions) {
            $modules[] = ['id' => $id, 'versions' => $versions];
        }

        return [
            'ocxVersions' => self::OCX_VERSIONS,
            'modules'     => $modules,
            'limits'      => [
                'maxEntriesPerDelivery' => self::MAX_ENTRIES,
                'maxEntriesPerPart'     => self::MAX_ENTRIES_PER_PART,
                'maxPartBytes'          => $this->maxBodySize,
                'maxAttachmentBytes'    => $this->maxAttachmentSize,
            ],
            'extensions'  => new stdClass(),
        ];
    }

    /**
     * @throws OcxProblemException
     * @throws JsonException
     */
    public function parseManifest(string $body): stdClass
    {
        $this->assertBodySize($body);
        $manifest = $this->decode($body);
        $this->assertVersionSupported($manifest);
        $this->validate($manifest, OcxSchemaValidator::MANIFEST);
        $this->assertCountsBounded($manifest);
        $this->assertModulesSupported($manifest);
        $this->assertAttachmentsValid($manifest);

        return $manifest;
    }

    /**
     * @return int the number of entries in the part
     *
     * @throws OcxProblemException
     * @throws JsonException
     */
    public function parseEntriesPart(string $body, int $partIndex, int $totalParts): int
    {
        $document = $this->decode($body);
        if (is_array($document->entries ?? null) && count($document->entries) > self::MAX_ENTRIES_PER_PART) {
            throw OcxProblemException::tooManyEntries(self::MAX_ENTRIES_PER_PART);
        }
        $this->validate($document, OcxSchemaValidator::ENTRIES);
        if ($document->part->index !== $partIndex || $document->part->total !== $totalParts) {
            throw OcxProblemException::unprocessable('part.index and part.total must match the path and the manifest.');
        }

        return count($document->entries);
    }

    /**
     * @throws OcxProblemException
     */
    public function assertBodySize(string $body): void
    {
        if (strlen($body) > $this->maxBodySize) {
            throw OcxProblemException::tooLarge($this->maxBodySize);
        }
    }

    /**
     * Checks the RFC 9530 `Content-Digest` header, which must contain a `sha-256` member.
     *
     * @throws OcxProblemException
     */
    public function assertContentDigest(?string $header, string $sha256): void
    {
        if (null === $header || 1 !== preg_match('/(?:^|,)\s*sha-256=:([A-Za-z0-9+\/=]+):/', $header, $matches)) {
            throw OcxProblemException::unprocessable('Header Content-Digest with a sha-256 member is required.');
        }
        $expected = base64_encode((string) hex2bin($sha256));
        if (!hash_equals($expected, $matches[1])) {
            throw OcxProblemException::unprocessable('Content-Digest does not match the body.');
        }
    }

    /**
     * @throws OcxProblemException
     */
    private function decode(string $body): stdClass
    {
        try {
            $data = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw OcxProblemException::unprocessable('Body is not valid JSON.');
        }
        if (!$data instanceof stdClass) {
            throw OcxProblemException::unprocessable('Body must be a JSON object.');
        }

        return $data;
    }

    /**
     * @throws OcxProblemException
     * @throws JsonException
     */
    private function validate(stdClass $data, string $schemaName): void
    {
        $violations = $this->schemaValidator->validate($data, $schemaName);
        if ([] !== $violations) {
            throw OcxProblemException::unprocessable('Body does not match the OCX '.$schemaName.' schema.', ['violations' => $violations]);
        }
    }

    /**
     * A well-formed version this receiver does not accept gets its own answer, so the sender can pick
     * another one; anything malformed is left to the schema.
     *
     * @throws OcxProblemException
     */
    private function assertVersionSupported(stdClass $manifest): void
    {
        $version = $manifest->ocxVersion ?? null;
        if (is_string($version) && 1 === preg_match('/^\d+\.\d+$/', $version) && !in_array($version, self::OCX_VERSIONS, true)) {
            throw OcxProblemException::unprocessable('OCX version '.$version.' is not supported.', ['unsupportedVersion' => $version, 'supportedVersions'  => self::OCX_VERSIONS]);
        }
    }

    /**
     * @throws OcxProblemException
     */
    private function assertModulesSupported(stdClass $manifest): void
    {
        $unsupported = [];
        foreach ($manifest->modules as $module) {
            if ($module->required && !in_array($module->version, self::SUPPORTED_MODULES[$module->id] ?? [], true)) {
                $unsupported[] = $module->id;
            }
        }
        if ([] !== $unsupported) {
            throw OcxProblemException::unprocessable('The delivery requires modules this receiver does not support.', ['unsupportedModules' => $unsupported]);
        }
    }

    /**
     * @throws OcxProblemException
     */
    private function assertCountsBounded(stdClass $manifest): void
    {
        if ($manifest->counts->parts > self::MAX_PARTS || $manifest->counts->entries > self::MAX_ENTRIES) {
            throw OcxProblemException::unprocessable('A delivery may have at most '.self::MAX_PARTS.' parts and '.self::MAX_ENTRIES.' entries.');
        }
    }

    /**
     * @throws OcxProblemException
     */
    private function assertAttachmentsValid(stdClass $manifest): void
    {
        foreach ($manifest->attachments as $attachment) {
            if ($attachment->size > $this->maxAttachmentSize) {
                throw OcxProblemException::unprocessable('Attachment '.$attachment->id.' exceeds the '.$this->maxAttachmentSize.' bytes this receiver accepts per attachment.');
            }
            $filename = $attachment->filename;
            if (str_contains($filename, '/') || str_contains($filename, '\\') || str_contains($filename, '..') || str_contains($filename, "\0")) {
                throw OcxProblemException::unprocessable('Attachment '.$attachment->id.': filename must not contain a path.');
            }
        }

        $ids = array_column($manifest->attachments, 'id');
        if (count($ids) !== count(array_unique($ids))) {
            throw OcxProblemException::unprocessable('Attachment ids in the manifest must be unique.');
        }
        if (count($ids) !== $manifest->counts->attachments) {
            throw OcxProblemException::unprocessable('counts.attachments does not match the number of attachments in the manifest.');
        }
    }
}
