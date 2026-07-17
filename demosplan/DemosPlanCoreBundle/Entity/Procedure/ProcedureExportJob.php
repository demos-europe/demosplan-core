<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Procedure;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tracks the state of an asynchronous procedure export (Gesamtabzug) so the browser can poll for it
 * and download the result once the background worker has finished.
 *
 * Unlike {@link \demosplan\DemosPlanCoreBundle\Entity\Statement\AssessmentTableExportJob} this job
 * has no single procedure: a procedure export covers a selection of procedures, so the selected ids
 * are carried on the dispatched message, not on this row.
 *
 * @ORM\Entity
 *
 * @ORM\Table(name="procedure_export_job")
 */
class ProcedureExportJob extends CoreEntity implements UuidEntityInterface
{
    final public const STATUS_PENDING = 'pending';
    final public const STATUS_PROCESSING = 'processing';
    final public const STATUS_COMPLETED = 'completed';
    final public const STATUS_FAILED = 'failed';

    /**
     * @var string|null
     *
     * @ORM\Column(type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=20, nullable=false)
     */
    protected $status = self::STATUS_PENDING;

    /**
     * @var string
     *
     * @ORM\Column(name="user_id", type="string", length=36, options={"fixed":true}, nullable=false)
     */
    protected $userId;

    /**
     * Hash of the generated result file, once available.
     *
     * @var string|null
     *
     * @ORM\Column(name="file_hash", type="string", length=36, options={"fixed":true}, nullable=true)
     */
    protected $fileHash;

    /**
     * @var string|null
     *
     * @ORM\Column(name="file_name", type="string", length=255, nullable=true)
     */
    protected $fileName;

    /**
     * @var string|null
     *
     * @ORM\Column(name="error_message", type="text", nullable=true)
     */
    protected $errorMessage;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="created_date", type="datetime", nullable=false)
     */
    protected $createdDate;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="modified_date", type="datetime", nullable=false)
     */
    protected $modifiedDate;

    public function __construct()
    {
        $this->createdDate = new DateTime();
        $this->modifiedDate = new DateTime();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function getFileHash(): ?string
    {
        return $this->fileHash;
    }

    public function setFileHash(?string $fileHash): void
    {
        $this->fileHash = $fileHash;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function setFileName(?string $fileName): void
    {
        $this->fileName = $fileName;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): void
    {
        $this->errorMessage = $errorMessage;
    }

    public function getCreatedDate(): DateTime
    {
        return $this->createdDate;
    }

    public function getModifiedDate(): DateTime
    {
        return $this->modifiedDate;
    }

    public function setModifiedDate(DateTime $modifiedDate): void
    {
        $this->modifiedDate = $modifiedDate;
    }
}
