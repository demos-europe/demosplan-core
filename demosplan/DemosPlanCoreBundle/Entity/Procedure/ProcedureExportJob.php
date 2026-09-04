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

use demosplan\DemosPlanCoreBundle\Entity\Export\AsyncExportJob;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tracks the state of an asynchronous procedure export (Gesamtabzug) so the browser can poll for it
 * and download the result once the background worker has finished.
 *
 * Unlike {@link \demosplan\DemosPlanCoreBundle\Entity\Statement\AssessmentTableExportJob} this job
 * has no single procedure: a procedure export covers a selection of procedures, so the selected ids
 * are carried on the dispatched message, not on this row.
 */
#[ORM\Table(name: 'procedure_export_job')]
// Covers the duplicate-suppression lookup a client performs on every export click.
#[ORM\Index(name: 'procedure_export_job_lookup', columns: ['user_id', 'parameters_hash', 'status'])]
// Covers the maintenance sweep over unfinished and expired jobs.
#[ORM\Index(name: 'procedure_export_job_status_modified', columns: ['status', 'modified_date'])]
#[ORM\Entity]
class ProcedureExportJob extends AsyncExportJob
{
}
