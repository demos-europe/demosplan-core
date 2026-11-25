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

/**
 * One `entryResult` of `delivery.schema.json`.
 */
final readonly class OcxEntryResult
{
    public const CREATED = 'created';
    public const UPDATED = 'updated';
    public const SKIPPED = 'skipped';
    public const FAILED = 'failed';

    private const FAILURE_DETAILS = [
        'not_permitted'        => 'Entries of this kind are not accepted for this procedure.',
        'unknown_target'       => 'No statement with this id in the procedure.',
        'foreign_procedure'    => 'The statement belongs to another procedure.',
        'target_not_updatable' => 'The statement is not assessable: an original, a segment, deleted or moved.',
        'internal_error'       => 'The entry could not be processed.',
    ];

    /**
     * @param list<array{code: string, pointer: ?string, detail: ?string}> $warnings
     */
    private function __construct(
        public string $senderId,
        public string $outcome,
        public ?string $reason = null,
        public ?string $receiverId = null,
        public ?string $receiptNumber = null,
        public ?string $detail = null,
        public array $warnings = [],
    ) {
    }

    public static function fromStatement(string $senderId, OcxCreatedStatement $statement): self
    {
        return $statement->created
            ? new self($senderId, self::CREATED, null, $statement->copyId, $statement->externId, null, $statement->warnings)
            : new self($senderId, self::SKIPPED, 'already_exists', $statement->copyId, $statement->externId);
    }

    public static function updated(string $senderId, ?string $receiverId, ?string $receiptNumber): self
    {
        return new self($senderId, self::UPDATED, null, $receiverId, $receiptNumber);
    }

    public static function skipped(string $senderId, string $reason, ?string $receiverId = null, ?string $receiptNumber = null): self
    {
        return new self($senderId, self::SKIPPED, $reason, $receiverId, $receiptNumber);
    }

    /**
     * @param string|null $detail defaults to the generic description of $reason
     */
    public static function failed(string $senderId, string $reason, ?string $detail = null): self
    {
        return new self($senderId, self::FAILED, $reason, null, null, $detail ?? self::FAILURE_DETAILS[$reason] ?? null);
    }

    /**
     * @param list<self> $results
     *
     * @return array{created: int, updated: int, skipped: int, failed: int}
     */
    public static function countOutcomes(array $results): array
    {
        $counts = [self::CREATED => 0, self::UPDATED => 0, self::SKIPPED => 0, self::FAILED => 0];
        foreach ($results as $result) {
            ++$counts[$result->outcome];
        }

        return $counts;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'senderId'      => $this->senderId,
            'outcome'       => $this->outcome,
            'reason'        => $this->reason,
            'receiverId'    => $this->receiverId,
            'receiptNumber' => '' === $this->receiptNumber ? null : $this->receiptNumber,
            'detail'        => $this->detail,
            'warnings'      => $this->warnings,
        ];
    }
}
