<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Report;

use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\User\PersonalAccessToken;
use demosplan\DemosPlanCoreBundle\Entity\User\User;

/**
 * Produces {@see ReportEntry} records for the lifecycle of a personal access token.
 *
 * Per-request *use* of a token is intentionally not audited here — that volume would
 * dominate the audit table. Lifecycle events (creation and revocation) are the forensic
 * anchor points a reviewer needs when investigating unexpected API activity.
 */
class PersonalAccessTokenReportEntryFactory extends AbstractReportEntryFactory
{
    public function createCreationEntry(PersonalAccessToken $token): ReportEntry
    {
        $entry = $this->createReportEntry();
        $entry->setCategory(ReportEntry::CATEGORY_ADD);
        $entry->setUser($token->getUser());
        $entry->setIdentifier((string) $token->getId());
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_PERSONAL_ACCESS_TOKEN);
        $entry->setMessage(Json::encode([
            'tokenPrefix' => $token->getTokenPrefix(),
            'name'        => $token->getName(),
            'scopes'      => $token->getScopes(),
            'procedureIds' => $token->getProcedureIds(),
            'expiresAt'   => $token->getExpiresAt()->format(DATE_ATOM),
        ], JSON_UNESCAPED_UNICODE));

        return $entry;
    }

    public function createRevocationEntry(PersonalAccessToken $token, ?User $revokedBy = null): ReportEntry
    {
        $entry = $this->createReportEntry();
        $entry->setCategory(ReportEntry::CATEGORY_DELETE);
        // Record the revoker where available so forensic review can distinguish
        // self-revocation from admin-forced revocation.
        $entry->setUser($revokedBy ?? $token->getUser());
        $entry->setIdentifier((string) $token->getId());
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_PERSONAL_ACCESS_TOKEN);
        $entry->setMessage(Json::encode([
            'tokenPrefix' => $token->getTokenPrefix(),
            'name'        => $token->getName(),
            'ownerId'     => $token->getUser()->getId(),
            'revokedById' => $revokedBy?->getId(),
            'adminForced' => null !== $revokedBy && $revokedBy->getId() !== $token->getUser()->getId(),
        ], JSON_UNESCAPED_UNICODE));

        return $entry;
    }

    protected function createReportEntry(): ReportEntry
    {
        $entry = parent::createReportEntry();
        $entry->setGroup(ReportEntry::GROUP_PERSONAL_ACCESS_TOKEN);

        return $entry;
    }
}
