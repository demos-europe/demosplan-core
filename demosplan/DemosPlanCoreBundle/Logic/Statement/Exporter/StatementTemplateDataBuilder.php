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
use DateTimeZone;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\ValueObject\Statement\StatementTemplateData;
use Exception;

/**
 * Builds the {@see StatementTemplateData} mapping from a Procedure + Statement,
 * without any PHPWord coupling. Kept pure so the placeholder → value contract
 * is unit-testable in isolation from the DOCX renderer in
 * {@see StatementViaTemplateExporter}.
 */
class StatementTemplateDataBuilder
{
    private const DATE_FORMAT = 'd.m.Y';
    private const DEFAULT_TIMEZONE = 'Europe/Berlin';

    public function build(Procedure $procedure, Statement $statement): StatementTemplateData
    {
        $meta = $statement->getMeta();
        try {
            $todayDate = (new DateTimeImmutable('now', new DateTimeZone(self::DEFAULT_TIMEZONE)))
                ->format(self::DATE_FORMAT);
        } catch (Exception) {
            // Unreachable: hardcoded valid zone + 'now' cannot throw at runtime.
            // Empty fallback keeps the type system happy without a misleading default value.
            $todayDate = '';
        }

        $data = new StatementTemplateData();
        $data->setSubmitterName($meta->getAuthorName());
        $data->setSubmitterOrgaName($meta->getOrgaName());
        $data->setSubmitterStreet($meta->getOrgaStreet());
        $data->setSubmitterHouseNumber($meta->getHouseNumber());
        $data->setSubmitterPostalCode($meta->getOrgaPostalCode());
        $data->setSubmitterCity($meta->getOrgaCity());
        $data->setStatementExternId($statement->getExternId());
        $data->setStatementInternId($statement->getInternId());
        $data->setStatementSubmitDate($statement->getSubmitDateString());
        $data->setProcedureName($procedure->getName());
        $data->setTodayDate($todayDate);
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
