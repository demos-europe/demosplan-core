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

use function array_keys;

/**
 * What applying one `statement` entry collects: the warnings for its result and the delivered files
 * the statement took over.
 */
final class OcxEntryContext
{
    /**
     * @var list<array{code: string, pointer: ?string, detail: ?string}>
     */
    private array $warnings = [];

    /**
     * @var array<string, true>
     */
    private array $usedFileIds = [];

    /**
     * @var array<string, string> file id by OCX attachment id
     */
    private readonly array $fileIds;

    /**
     * @param array<string, array{sha256: string, fileId: string}> $receivedAttachments by OCX attachment id
     */
    public function __construct(public readonly string $procedureId, array $receivedAttachments)
    {
        $fileIds = [];
        foreach ($receivedAttachments as $attachmentId => $received) {
            $fileIds[(string) $attachmentId] = $received['fileId'];
        }
        $this->fileIds = $fileIds;
    }

    public function warn(string $code, ?string $pointer, ?string $detail): void
    {
        $this->warnings[] = ['code' => $code, 'pointer' => $pointer, 'detail' => $detail];
    }

    /**
     * @return list<array{code: string, pointer: ?string, detail: ?string}>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function getFileId(string $attachmentId): ?string
    {
        return $this->fileIds[$attachmentId] ?? null;
    }

    public function useFile(string $fileId): void
    {
        $this->usedFileIds[$fileId] = true;
    }

    /**
     * @return list<string>
     */
    public function getUsedFileIds(): array
    {
        return array_keys($this->usedFileIds);
    }
}
