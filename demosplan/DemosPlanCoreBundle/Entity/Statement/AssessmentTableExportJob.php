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

use demosplan\DemosPlanCoreBundle\Entity\Export\AsyncExportJob;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tracks the state of an asynchronous Abwägungstabelle export so the browser can poll for it
 * and download the result once the background worker has finished.
 */
#[ORM\Table(name: 'assessment_table_export_job')]
// Covers the duplicate-suppression lookup a client performs on every export click.
#[ORM\Index(name: 'at_export_job_lookup', columns: ['user_id', 'procedure_id', 'parameters_hash', 'status'])]
// Covers the maintenance sweep over unfinished and expired jobs.
#[ORM\Index(name: 'at_export_job_status_modified', columns: ['status', 'modified_date'])]
#[ORM\Entity]
class AssessmentTableExportJob extends AsyncExportJob
{
    #[ORM\Column(name: 'procedure_id', type: 'string', length: 36, options: ['fixed' => true], nullable: false)]
    protected string $procedureId;

    public function getProcedureId(): string
    {
        return $this->procedureId;
    }

    public function setProcedureId(string $procedureId): void
    {
        $this->procedureId = $procedureId;
    }
}
