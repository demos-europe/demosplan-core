<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter;

use DateTimeImmutable;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\ValueObject\Statement\StatementTemplateData;

/**
 * Builds the {@see StatementTemplateData} mapping from a Procedure + Statement
 * + the current user, without any PHPWord coupling. Kept pure so the
 * placeholder → value contract is unit-testable in isolation from the DOCX
 * renderer in {@see StatementViaTemplateExporter}.
 */
class StatementTemplateDataBuilder
{
    private const DATE_FORMAT = 'd.m.Y';

    public function __construct(
        private readonly CurrentUserInterface $currentUser,
    ) {
    }

    public function build(Procedure $procedure, Statement $statement): StatementTemplateData
    {
        $meta = $statement->getMeta();

        $data = new StatementTemplateData();
        $data->setSubmitterName($meta->getAuthorName());
        $data->setSubmitterOrgaName($meta->getOrgaName());
        $data->setSubmitterStreet($meta->getOrgaStreet());
        $data->setSubmitterPostalCode($meta->getOrgaPostalCode());
        $data->setSubmitterCity($meta->getOrgaCity());
        $data->setSubmitterEmail($meta->getOrgaEmail());
        $data->setStatementExternId($statement->getExternId());
        $data->setStatementSubmitDate($statement->getSubmitDateString());
        $data->setProcedureName($procedure->getName());
        $data->setProcedureExternId($procedure->getExternId());
        $data->setTodayDate((new DateTimeImmutable())->format(self::DATE_FORMAT));
        $data->setPlanningAgencyName($procedure->getOrga()?->getName());
        $data->setPlanner($this->currentUser->getUser()->getName());
        $data->setSegments($this->orderedSegments($statement));
        $data->lock();

        return $data;
    }

    /**
     * @return list<Segment>
     */
    private function orderedSegments(Statement $statement): array
    {
        $segments = $statement->getSegmentsOfStatement()->toArray();
        usort(
            $segments,
            static fn (Segment $first, Segment $second): int => $first->getOrderInProcedure() <=> $second->getOrderInProcedure()
        );

        return array_values($segments);
    }
}
