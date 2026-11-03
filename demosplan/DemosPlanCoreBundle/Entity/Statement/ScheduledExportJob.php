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
use demosplan\DemosPlanCoreBundle\Entity\Export\AsyncExportJob;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tracks a single firing of an {@see ExportSchedule}: the state of the resulting Synopse export,
 * so the completion/failure mail and the download page can act on it once the background worker
 * has finished.
 */
#[ORM\Table(name: 'scheduled_export_job')]
// Covers "which schedule does this run belong to" lookups from the maintenance sweep and the download page.
#[ORM\Index(name: 'scheduled_export_job_schedule_lookup', columns: ['schedule_id', 'status'])]
// Covers the stale-job sweep over unfinished jobs, same as AssessmentTableExportJob.
#[ORM\Index(name: 'scheduled_export_job_status_modified', columns: ['status', 'modified_date'])]
// Covers the expired-result purge sweep: rows whose deletion date has passed.
#[ORM\Index(name: 'scheduled_export_job_delete_after', columns: ['delete_after'])]
#[ORM\Entity]
class ScheduledExportJob extends AsyncExportJob
{
    #[ORM\Column(name: 'schedule_id', type: 'string', length: 36, options: ['fixed' => true], nullable: false)]
    protected string $scheduleId;

    #[ORM\Column(name: 'procedure_id', type: 'string', length: 36, options: ['fixed' => true], nullable: false)]
    protected string $procedureId;

    /**
     * When the stored file (and this row) should be deleted - double the schedule's interval,
     * per the retention AC. Set once the job reaches a final status; null while pending/processing,
     * since the retention window is only known once we know when the job actually finished.
     */
    #[ORM\Column(name: 'delete_after', type: 'datetime', nullable: true)]
    protected ?DateTime $deleteAfter = null;

    public function getScheduleId(): string
    {
        return $this->scheduleId;
    }

    public function setScheduleId(string $scheduleId): void
    {
        $this->scheduleId = $scheduleId;
    }

    public function getProcedureId(): string
    {
        return $this->procedureId;
    }

    public function setProcedureId(string $procedureId): void
    {
        $this->procedureId = $procedureId;
    }

    public function getDeleteAfter(): ?DateTime
    {
        return $this->deleteAfter;
    }

    public function setDeleteAfter(?DateTime $deleteAfter): void
    {
        $this->deleteAfter = $deleteAfter;
    }
}
