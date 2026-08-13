<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Export;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\ORM\Mapping as ORM;

/**
 * State every asynchronous export job row carries, regardless of what is exported: which user asked
 * for it, what was asked for, how far it got and where the result ended up.
 *
 * Table name and indexes are configured on each concrete subclass, as each export gets its own table.
 */
#[ORM\MappedSuperclass]
abstract class AsyncExportJob extends CoreEntity implements UuidEntityInterface, AsyncExportJobInterface
{
    /**
     * @var string|null
     */
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36, nullable: false, options: ['fixed' => true])]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidV4Generator::class)]
    protected $id;

    /**
     * @var string
     */
    #[ORM\Column(type: 'string', length: 20, nullable: false)]
    protected $status = self::STATUS_PENDING;

    /**
     * @var string
     */
    #[ORM\Column(name: 'user_id', type: 'string', length: 36, options: ['fixed' => true], nullable: false)]
    protected $userId;

    /**
     * Identifies the export request, so a repeated request joins the running job instead of
     * starting a second one.
     *
     * @var string
     */
    #[ORM\Column(name: 'parameters_hash', type: 'string', length: 64, options: ['fixed' => true], nullable: false)]
    protected $parametersHash = '';

    /**
     * Hash of the generated result file, once available.
     *
     * @var string|null
     */
    #[ORM\Column(name: 'file_hash', type: 'string', length: 36, options: ['fixed' => true], nullable: true)]
    protected $fileHash;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'file_name', type: 'string', length: 255, nullable: true)]
    protected $fileName;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'error_message', type: 'text', nullable: true)]
    protected $errorMessage;

    /**
     * @var DateTime
     */
    #[ORM\Column(name: 'created_date', type: 'datetime', nullable: false)]
    protected $createdDate;

    /**
     * @var DateTime
     */
    #[ORM\Column(name: 'modified_date', type: 'datetime', nullable: false)]
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

    public function getParametersHash(): string
    {
        return $this->parametersHash;
    }

    public function setParametersHash(string $parametersHash): void
    {
        $this->parametersHash = $parametersHash;
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
