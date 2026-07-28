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
 * Tracks one phase of an asynchronous Planunterlagen (element) import so the browser can poll its
 * progress while a background worker does the work.
 *
 * An import has two phases, each dispatched as its own message but sharing this table via
 * {@see self::$phase}: PHASE_EXTRACT unpacks the uploaded archive and produces the file tree the
 * review page shows, PHASE_SAVE turns the reviewed tree into SingleDocument entities.
 *
 * The hash-to-path map lives on {@see self::$importList} rather than in the session, because a
 * worker runs without one. It is also why the job id — not a hash recomputed on both sides — is
 * the handle the browser passes back.
 *
 * @ORM\Entity
 *
 * @ORM\Table(name="element_import_job")
 */
class ElementImportJob extends CoreEntity implements UuidEntityInterface
{
    final public const STATUS_PENDING = 'pending';
    final public const STATUS_PROCESSING = 'processing';
    final public const STATUS_COMPLETED = 'completed';
    final public const STATUS_FAILED = 'failed';

    final public const PHASE_EXTRACT = 'extract';
    final public const PHASE_SAVE = 'save';

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
     * @ORM\Column(type="string", length=20, nullable=false)
     */
    protected $phase = self::PHASE_EXTRACT;

    /**
     * @var string
     *
     * @ORM\Column(name="procedure_id", type="string", length=36, options={"fixed":true}, nullable=false)
     */
    protected $procedureId;

    /**
     * @var string
     *
     * @ORM\Column(name="user_id", type="string", length=36, options={"fixed":true}, nullable=false)
     */
    protected $userId;

    /**
     * @var int
     *
     * @ORM\Column(name="files_total", type="integer", options={"default":0}, nullable=false)
     */
    protected $filesTotal = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="files_processed", type="integer", options={"default":0}, nullable=false)
     */
    protected $filesProcessed = 0;

    /**
     * Hash-to-path map of the extracted files, handed from the extract phase to the save phase.
     *
     * @var array<string,string>|null
     *
     * @ORM\Column(name="import_list", type="json", nullable=true)
     */
    protected $importList;

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

    public function getPhase(): string
    {
        return $this->phase;
    }

    public function setPhase(string $phase): void
    {
        $this->phase = $phase;
    }

    public function getProcedureId(): string
    {
        return $this->procedureId;
    }

    public function setProcedureId(string $procedureId): void
    {
        $this->procedureId = $procedureId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function getFilesTotal(): int
    {
        return $this->filesTotal;
    }

    public function setFilesTotal(int $filesTotal): void
    {
        $this->filesTotal = $filesTotal;
    }

    public function getFilesProcessed(): int
    {
        return $this->filesProcessed;
    }

    public function setFilesProcessed(int $filesProcessed): void
    {
        $this->filesProcessed = $filesProcessed;
    }

    /**
     * @return array<string,string>|null
     */
    public function getImportList(): ?array
    {
        return $this->importList;
    }

    /**
     * @param array<string,string>|null $importList
     */
    public function setImportList(?array $importList): void
    {
        $this->importList = $importList;
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
