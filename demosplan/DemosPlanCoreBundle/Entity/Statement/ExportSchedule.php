<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Statement;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\ORM\Mapping as ORM;

/**
 * A recurring rule configured by a Sachbearbeiterin: which Synopse export to repeat, on what
 * cadence, and when it is due next. Each due firing produces one {@see ScheduledExportJob} row.
 */
#[ORM\Table(name: 'export_schedule')]
// Covers the daily dispatcher sweep for schedules due today.
#[ORM\Index(name: 'export_schedule_due_lookup', columns: ['next_run_at'])]
// Covers the "my scheduled exports" list in the export flow.
#[ORM\Index(name: 'export_schedule_user_lookup', columns: ['user_id'])]
#[ORM\Entity]
class ExportSchedule extends CoreEntity implements UuidEntityInterface
{
    public const FREQUENCY_DAILY = 'daily';
    public const FREQUENCY_WEEKLY = 'weekly';
    public const FREQUENCY_MONTHLY = 'monthly';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36, nullable: false, options: ['fixed' => true])]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidV4Generator::class)]
    protected ?string $id = null;

    #[ORM\Column(name: 'user_id', type: 'string', length: 36, options: ['fixed' => true], nullable: false)]
    protected string $userId;

    #[ORM\Column(name: 'procedure_id', type: 'string', length: 36, options: ['fixed' => true], nullable: false)]
    protected string $procedureId;

    /**
     * The serialized filter/sort state to re-run the export with, at execution time.
     */
    #[ORM\Column(type: 'text', nullable: false)]
    protected string $parameters;

    /**
     * Fingerprint of {@see $parameters}, so future filtering/sorting over schedules can group by
     * identical export requests without re-parsing the serialized filter state.
     */
    #[ORM\Column(name: 'parameters_hash', type: 'string', length: 64, options: ['fixed' => true], nullable: false)]
    protected string $parametersHash;

    #[ORM\Column(type: 'string', length: 20, nullable: false)]
    protected string $frequency;

    /**
     * ISO-8601 day of week (1 = Monday .. 7 = Sunday). Only set when {@see $frequency} is weekly.
     */
    #[ORM\Column(type: 'smallint', nullable: true)]
    protected ?int $weekday = null;

    /**
     * Day of month, restricted by the frontend to a fixed preset (1, 5, 10, ..., 25). Only set
     * when {@see $frequency} is monthly.
     */
    #[ORM\Column(name: 'day_of_month', type: 'smallint', nullable: true)]
    protected ?int $dayOfMonth = null;

    /**
     * When the dispatcher should generate this export next.
     */
    #[ORM\Column(name: 'next_run_at', type: 'datetime', nullable: false)]
    protected DateTime $nextRunAt;

    /**
     * When this schedule last actually fired, so a schedule that already ran today is not picked
     * up a second time if the dispatcher runs more than once on the same day.
     */
    #[ORM\Column(name: 'last_run_at', type: 'datetime', nullable: true)]
    protected ?DateTime $lastRunAt = null;

    #[ORM\Column(name: 'created_date', type: 'datetime', nullable: false)]
    protected DateTime $createdDate;

    #[ORM\Column(name: 'modified_date', type: 'datetime', nullable: false)]
    protected DateTime $modifiedDate;

    public function __construct()
    {
        $this->createdDate = new DateTime();
        $this->modifiedDate = new DateTime();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function getProcedureId(): string
    {
        return $this->procedureId;
    }

    public function setProcedureId(string $procedureId): void
    {
        $this->procedureId = $procedureId;
    }

    public function getParameters(): string
    {
        return $this->parameters;
    }

    public function setParameters(string $parameters): void
    {
        $this->parameters = $parameters;
    }

    public function getParametersHash(): string
    {
        return $this->parametersHash;
    }

    public function setParametersHash(string $parametersHash): void
    {
        $this->parametersHash = $parametersHash;
    }

    public function getFrequency(): string
    {
        return $this->frequency;
    }

    public function setFrequency(string $frequency): void
    {
        $this->frequency = $frequency;
    }

    public function getWeekday(): ?int
    {
        return $this->weekday;
    }

    public function setWeekday(?int $weekday): void
    {
        $this->weekday = $weekday;
    }

    public function getDayOfMonth(): ?int
    {
        return $this->dayOfMonth;
    }

    public function setDayOfMonth(?int $dayOfMonth): void
    {
        $this->dayOfMonth = $dayOfMonth;
    }

    public function getNextRunAt(): DateTime
    {
        return $this->nextRunAt;
    }

    public function setNextRunAt(DateTime $nextRunAt): void
    {
        $this->nextRunAt = $nextRunAt;
    }

    public function getLastRunAt(): ?DateTime
    {
        return $this->lastRunAt;
    }

    public function setLastRunAt(?DateTime $lastRunAt): void
    {
        $this->lastRunAt = $lastRunAt;
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
