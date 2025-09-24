<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Tests\Integration;

class AddonTestResult
{
    public function __construct(
        private readonly bool $success,
        private readonly string $message,
        private readonly array $details = [],
        private readonly ?string $auditId = null,
        private readonly int $initialCount = 0,
        private readonly int $finalCount = 0
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getDetails(): array
    {
        return $this->details;
    }

    public function getAuditId(): ?string
    {
        return $this->auditId;
    }

    public function getInitialCount(): int
    {
        return $this->initialCount;
    }

    public function getFinalCount(): int
    {
        return $this->finalCount;
    }

    public function getItemsCreated(): int
    {
        return $this->finalCount - $this->initialCount;
    }
}