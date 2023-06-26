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
use demosplan\DemosPlanCoreBundle\Entity\User\MasterToeb;

class MasterPublicAgencyReportEntryFactory extends AbstractReportEntryFactory
{
    public function createDeletionEntry(MasterToeb $masterToeb): ReportEntry
    {
        $incomingData = ['status' => true];

        $message['ident'] = $masterToeb->getIdent();
        $message['orgaName'] = $masterToeb->getOrgaName();
        $message['districtHHMitte'] = $masterToeb->getDistrictHHMitte();
        $message['districtEimsbuettel'] = $masterToeb->getDistrictEimsbuettel();
        $message['districtAltona'] = $masterToeb->getDistrictAltona();
        $message['districtHHNord'] = $masterToeb->getDistrictHHNord();
        $message['districtWandsbek'] = $masterToeb->getDistrictWandsbek();
        $message['districtBergedorf'] = $masterToeb->getDistrictBergedorf();
        $message['districtHarburg'] = $masterToeb->getDistrictHarburg();
        $message['districtBsu'] = $masterToeb->getDistrictBsu();
        $message['documentRoughAgreement'] = $masterToeb->getDocumentRoughAgreement();
        $message['documentAgreement'] = $masterToeb->getDocumentAgreement();
        $message['documentNotice'] = $masterToeb->getDocumentNotice();
        $message['documentAssessment'] = $masterToeb->getDocumentAssessment();
        $message['registered'] = $masterToeb->getRegistered();
        $message['oId'] = $masterToeb->getOId();
        $message['createdDate'] = $masterToeb->getCreatedDate();
        $message['modifiedDate'] = $masterToeb->getModifiedDate();

        $entry = $this->createReportEntry();
        $entry->setCategory(ReportEntry::CATEGORY_DELETE);
        $entry->setUser($this->currentUserProvider->getUser());
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_MASTER_PUBLIC_AGENCY);
        $entry->setIdentifier($masterToeb->getIdent());
        $entry->setMessage(Json::encode($message, JSON_UNESCAPED_UNICODE));
        $entry->setIncoming(Json::encode($incomingData, JSON_UNESCAPED_UNICODE));

        return $entry;
    }

    public function createAdditionEntry(array $data): ReportEntry
    {
        $entry = $this->createReportEntry();
        $entry->setCategory(ReportEntry::CATEGORY_ADD);
        $entry->setUser($this->currentUserProvider->getUser());
        $entry->setIdentifier('');
        $entry->setIdentifierType('');
        $entry->setMessage('');
        $entry->setIdentifier('');
        $entry->setIncoming(Json::encode($data, JSON_UNESCAPED_UNICODE));

        return $entry;
    }

    public function createMergeEntry(array $resultofMerging): ReportEntry
    {
        $entry = $this->createReportEntry();
        $entry->setCategory(ReportEntry::CATEGORY_MERGE);
        $entry->setUser($this->currentUserProvider->getUser());
        $entry->setIdentifier($resultofMerging['masterToebId']);
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_MASTER_PUBLIC_AGENCY);
        $entry->setMessage('');
        $entry->setIncoming(Json::encode(['status' => true], JSON_UNESCAPED_UNICODE));
        $entry->setMessage(Json::encode($resultofMerging, JSON_UNESCAPED_UNICODE));

        return $entry;
    }

    public function createUpdateEntry($masterToebIdent, array $message, array $incoming): ReportEntry
    {
        $entry = $this->createReportEntry();
        $entry->setCategory(ReportEntry::CATEGORY_UPDATE);
        $entry->setUser($this->currentUserProvider->getUser());
        $entry->setIdentifier($masterToebIdent);
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_MASTER_PUBLIC_AGENCY);
        $entry->setIncoming(Json::encode($incoming, JSON_UNESCAPED_UNICODE));
        $entry->setMessage(Json::encode($message, JSON_UNESCAPED_UNICODE));

        return $entry;
    }

    protected function createReportEntry(): ReportEntry
    {
        $entry = parent::createReportEntry();
        $entry->setGroup(ReportEntry::GROUP_MASTER_PUBLIC_AGENCY);

        return $entry;
    }
}
