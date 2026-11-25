<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Ocx;

use DateTime;
use demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Repository\OcxDeliveryRepository;
use demosplan\DemosPlanCoreBundle\Types\OcxDeliveryFailure;
use demosplan\DemosPlanCoreBundle\Types\OcxDeliveryStatus;
use Doctrine\ORM\Mapping as ORM;

/**
 * One delivery of the OCX intake API: the manifest, which parts and attachments arrived, and the
 * per-entry results after processing. Part and attachment contents live in the file storage.
 *
 * @SuppressWarnings("PHPMD.TooManyFields")
 */
#[ORM\Table(name: 'ocx_delivery')]
#[ORM\UniqueConstraint(name: 'ocx_delivery_user_idempotency_key', columns: ['user_id', 'idempotency_key'])]
#[ORM\Index(name: 'ocx_delivery_status_created_at', columns: ['status', 'created_at'])]
#[ORM\Entity(repositoryClass: OcxDeliveryRepository::class)]
class OcxDelivery extends CoreEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36, nullable: false, options: ['fixed' => true])]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidV4Generator::class)]
    protected ?string $id = null;

    #[ORM\JoinColumn(name: 'procedure_id', referencedColumnName: '_p_id', nullable: false, onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: Procedure::class)]
    protected Procedure $procedure;

    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: '_u_id', nullable: false, onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: User::class)]
    protected User $user;

    #[ORM\JoinColumn(name: 'organisation_id', referencedColumnName: '_o_id', nullable: true, onDelete: 'SET NULL')]
    #[ORM\ManyToOne(targetEntity: Orga::class)]
    protected ?Orga $organisation = null;

    #[ORM\Column(name: 'idempotency_key', type: 'string', length: 255)]
    protected string $idempotencyKey;

    #[ORM\Column(name: 'status', type: 'string', length: 16, enumType: OcxDeliveryStatus::class)]
    protected OcxDeliveryStatus $status = OcxDeliveryStatus::OPEN;

    #[ORM\Column(name: 'failure', type: 'string', length: 32, nullable: true, enumType: OcxDeliveryFailure::class)]
    protected ?OcxDeliveryFailure $failure = null;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(name: 'manifest', type: 'json')]
    protected array $manifest;

    /**
     * Part index => ['sha256' => body digest, 'path' => path in the default storage, 'entries' => number of entries].
     *
     * @var array<int, array{sha256: string, path: string, entries: int}>
     */
    #[ORM\Column(name: 'received_parts', type: 'json')]
    protected array $receivedParts = [];

    /**
     * Attachment id => ['sha256' => content digest, 'fileId' => file id].
     *
     * @var array<string, array{sha256: string, fileId: string}>
     */
    #[ORM\Column(name: 'received_attachments', type: 'json')]
    protected array $receivedAttachments = [];

    /**
     * Entry kinds the sender was permitted to deliver when it opened the delivery. Processing runs
     * without a request, so the permissions of the request are kept here.
     *
     * @var list<string>
     */
    #[ORM\Column(name: 'permitted_kinds', type: 'json')]
    protected array $permittedKinds;

    /**
     * @var list<array<string, mixed>>
     */
    #[ORM\Column(name: 'results', type: 'json')]
    protected array $results = [];

    /**
     * @var array{created: int, updated: int, skipped: int, failed: int}
     */
    #[ORM\Column(name: 'counts', type: 'json')]
    protected array $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    protected DateTime $createdAt;

    #[ORM\Column(name: 'completed_at', type: 'datetime', nullable: true)]
    protected ?DateTime $completedAt = null;

    #[ORM\Column(name: 'processed_at', type: 'datetime', nullable: true)]
    protected ?DateTime $processedAt = null;

    #[ORM\Column(name: 'processed_entries', type: 'integer', options: ['default' => 0])]
    protected int $processedEntries = 0;

    /**
     * Set on completion and whenever processing advances; null while the delivery is open.
     */
    #[ORM\Column(name: 'progress_updated_at', type: 'datetime', nullable: true)]
    protected ?DateTime $progressUpdatedAt = null;

    /**
     * @param array<string, mixed> $manifest
     * @param list<string>         $permittedKinds
     */
    public function __construct(Procedure $procedure, User $user, string $idempotencyKey, array $manifest, array $permittedKinds)
    {
        $this->permittedKinds = $permittedKinds;
        $this->procedure = $procedure;
        $this->user = $user;
        $this->organisation = $user->getOrga();
        $this->idempotencyKey = $idempotencyKey;
        $this->manifest = $manifest;
        $this->createdAt = new DateTime();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getProcedure(): Procedure
    {
        return $this->procedure;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getOrganisation(): ?Orga
    {
        return $this->organisation;
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function getStatus(): OcxDeliveryStatus
    {
        return $this->status;
    }

    public function setStatus(OcxDeliveryStatus $status): void
    {
        $this->status = $status;
    }

    public function fail(OcxDeliveryFailure $failure): void
    {
        $this->status = OcxDeliveryStatus::FAILED;
        $this->failure = $failure;
    }

    public function getFailure(): ?OcxDeliveryFailure
    {
        return $this->failure;
    }

    /**
     * @return list<string>
     */
    public function getPermittedKinds(): array
    {
        return $this->permittedKinds;
    }

    /**
     * @return array<string, mixed>
     */
    public function getManifest(): array
    {
        return $this->manifest;
    }

    /**
     * @return array<int, array{sha256: string, path: string, entries: int}>
     */
    public function getReceivedParts(): array
    {
        return $this->receivedParts;
    }

    public function addReceivedPart(int $index, string $sha256, string $path, int $entries): void
    {
        $this->receivedParts[$index] = ['sha256' => $sha256, 'path' => $path, 'entries' => $entries];
    }

    /**
     * @return array<string, array{sha256: string, fileId: string}>
     */
    public function getReceivedAttachments(): array
    {
        return $this->receivedAttachments;
    }

    public function addReceivedAttachment(string $attachmentId, string $sha256, string $fileId): void
    {
        $this->receivedAttachments[$attachmentId] = ['sha256' => $sha256, 'fileId' => $fileId];
    }

    public function clearReceived(): void
    {
        $this->receivedParts = [];
        $this->receivedAttachments = [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * @param list<array<string, mixed>>                                  $results
     * @param array{created: int, updated: int, skipped: int, failed: int} $counts
     */
    public function setResults(array $results, array $counts): void
    {
        $this->results = $results;
        $this->counts = $counts;
    }

    /**
     * @return array{created: int, updated: int, skipped: int, failed: int}
     */
    public function getCounts(): array
    {
        return $this->counts;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getCompletedAt(): ?DateTime
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?DateTime $completedAt): void
    {
        $this->completedAt = $completedAt;
    }

    public function getProcessedAt(): ?DateTime
    {
        return $this->processedAt;
    }

    public function setProcessedAt(?DateTime $processedAt): void
    {
        $this->processedAt = $processedAt;
    }

    public function getProcessedEntries(): int
    {
        return $this->processedEntries;
    }

    public function getProgressUpdatedAt(): ?DateTime
    {
        return $this->progressUpdatedAt;
    }

    public function setProgress(int $processedEntries, DateTime $updatedAt): void
    {
        $this->processedEntries = $processedEntries;
        $this->progressUpdatedAt = $updatedAt;
    }
}
