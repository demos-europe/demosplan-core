<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Import;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\ImportJobRepository")
 *
 * @ORM\Table(name="import_job")
 *
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * Entities naturally have many methods due to standard getters/setters for each property.
 * This is acceptable as it follows the active record pattern and Doctrine conventions.
 */
class ImportJob extends CoreEntity
{
    final public const STATUS_PENDING = 'pending';
    final public const STATUS_PROCESSING = 'processing';
    final public const STATUS_COMPLETED = 'completed';
    final public const STATUS_FAILED = 'failed';

    /**
     * @var string|null
     *
     * @ORM\Id
     *
     * @ORM\Column(type="string", length=36, nullable=false, options={"fixed":true})
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * @var Procedure
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure")
     *
     * @ORM\JoinColumn(name="procedure_id", referencedColumnName="_p_id", nullable=false, onDelete="CASCADE")
     */
    protected $procedure;

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\User")
     *
     * @ORM\JoinColumn(name="user_id", referencedColumnName="_u_id", nullable=false, onDelete="CASCADE")
     */
    protected $user;

    /**
     * The organisation context when this job was created.
     * Used to restore organisation context during background processing.
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Orga")
     *
     * @ORM\JoinColumn(name="organisation_id", referencedColumnName="_o_id", nullable=true, onDelete="SET NULL")
     */
    protected ?Orga $organisation = null;

    /**
     * @var string
     *
     * @ORM\Column(name="file_path", type="string", length=500)
     */
    protected $filePath;

    /**
     * @var string
     *
     * @ORM\Column(name="file_name", type="string", length=255)
     */
    protected $fileName;

    /**
     * @var string
     *
     * @ORM\Column(name="status", type="string", length=50)
     */
    protected $status = self::STATUS_PENDING;

    /**
     * @var DateTime|null
     *
     * @ORM\Column(name="last_activity_at", type="datetime", nullable=true)
     */
    protected $lastActivityAt;

    /**
     * @var array|null
     *
     * @ORM\Column(name="result", type="json", nullable=true)
     */
    protected $result;

    /**
     * @var string|null
     *
     * @ORM\Column(name="error", type="text", nullable=true)
     */
    protected $error;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="created_at", type="datetime")
     */
    protected $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTime();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getProcedure(): Procedure
    {
        return $this->procedure;
    }

    public function setProcedure(Procedure $procedure): self
    {
        $this->procedure = $procedure;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getOrganisation(): ?Orga
    {
        return $this->organisation;
    }

    public function setOrganisation(?Orga $organisation): self
    {
        $this->organisation = $organisation;

        return $this;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): self
    {
        $this->filePath = $filePath;

        return $this;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): self
    {
        $this->fileName = $fileName;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function isPending(): bool
    {
        return self::STATUS_PENDING === $this->status;
    }

    public function isProcessing(): bool
    {
        return self::STATUS_PROCESSING === $this->status;
    }

    public function isCompleted(): bool
    {
        return self::STATUS_COMPLETED === $this->status;
    }

    public function isFailed(): bool
    {
        return self::STATUS_FAILED === $this->status;
    }

    public function getLastActivityAt(): ?DateTime
    {
        return $this->lastActivityAt;
    }

    public function setLastActivityAt(?DateTime $lastActivityAt): self
    {
        $this->lastActivityAt = $lastActivityAt;

        return $this;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error): self
    {
        $this->error = $error;

        return $this;
    }

    public function getResult(): ?array
    {
        return $this->result;
    }

    public function setResult(?array $result): self
    {
        $this->result = $result;

        return $this;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function markAsProcessing(): self
    {
        $this->status = self::STATUS_PROCESSING;
        $this->lastActivityAt = new DateTime();

        return $this;
    }

    public function markAsCompleted(array $result): self
    {
        $this->status = self::STATUS_COMPLETED;
        $this->lastActivityAt = new DateTime();
        $this->result = $result;

        return $this;
    }

    public function markAsFailed(string $error): self
    {
        $this->status = self::STATUS_FAILED;
        $this->lastActivityAt = new DateTime();
        $this->error = $error;

        return $this;
    }

}
