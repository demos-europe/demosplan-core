<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Export\Unit;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\ExportFieldsConfiguration;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Export\FieldDecider;
use Tests\Base\UnitTestCase;

class FieldDeciderTest extends UnitTestCase
{
    /** @var FieldDecider */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(FieldDecider::class);
    }

    public function testFieldDecider()
    {
        self::markSkippedForCIIntervention();

        /** @var ExportFieldsConfiguration $exportConfig */
        // By default all fields are exportable
        $exportConfig = $this->getReference('defaultExportFieldsConfiguration');

        /** @var Statement $statement */
        $statement = $this->getReference('testStatement');
        $statement->setName('My Awesome Name');

        /** @var User $statement */
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->logIn($user);

        // ID
        // --- Test use of $exportConfig
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_ID, $exportConfig));
        $exportConfig->setIdExportable(false);
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_ID, $exportConfig));
        $exportConfig->setIdExportable(true);
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_ID, $exportConfig));

        // Statement Name
        // --- Test use of $exportConfig
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_STATEMENT_NAME, $exportConfig, $statement));
        $exportConfig->setStatementNameExportable(false);
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_STATEMENT_NAME, $exportConfig, $statement));
        $exportConfig->setStatementNameExportable(true);
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_STATEMENT_NAME, $exportConfig, $statement));
        // --- Test Statement Name has valid info
        $statement->setName('');
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_STATEMENT_NAME, $exportConfig, $statement));

        // Creation Date
        // --- Test use of $exportConfig
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_CREATION_DATE, $exportConfig));
        $exportConfig->setCreationDateExportable(false);
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_CREATION_DATE, $exportConfig));
        $exportConfig->setCreationDateExportable(true);
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_CREATION_DATE, $exportConfig));

        // Procedure Name
        // --- Test use of $exportConfig
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_PROCEDURE_NAME, $exportConfig, $statement));
        $exportConfig->setProcedureNameExportable(false);
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_PROCEDURE_NAME, $exportConfig, $statement));
        $exportConfig->setProcedureNameExportable(true);
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_PROCEDURE_NAME, $exportConfig, $statement));
        // --- Test Procedure name exists
        $procedure = $statement->getProcedure();
        $statement->setProcedure(null);
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_PROCEDURE_NAME, $exportConfig, $statement));
        $statement->setProcedure($procedure);
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_PROCEDURE_NAME, $exportConfig, $statement));

        // Procedure Phase
        // --- Test use of $exportConfig
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_PROCEDURE_PHASE, $exportConfig, $statement));
        $exportConfig->setProcedurePhaseExportable(false);
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_PROCEDURE_PHASE, $exportConfig, $statement));
        $exportConfig->setProcedurePhaseExportable(true);
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_PROCEDURE_PHASE, $exportConfig, $statement));
        // --- Test Procedure phase exists
        $procedure = $statement->getProcedure();
        $statement->setProcedure(null);
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_PROCEDURE_PHASE, $exportConfig, $statement));
        $statement->setProcedure($procedure);
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_PROCEDURE_PHASE, $exportConfig, $statement));

        // Votes Num
        $statement->setNumberOfAnonymVotes(1);
        // --- Test use of $exportConfig
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_VOTES_NUM, $exportConfig, $statement));
        $exportConfig->setVotesNumExportable(false);
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_VOTES_NUM, $exportConfig, $statement));
        $exportConfig->setVotesNumExportable(true);
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_VOTES_NUM, $exportConfig, $statement));
        // --- Test has some votes
        $statement->setNumberOfAnonymVotes(0);
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_VOTES_NUM, $exportConfig, $statement));

        // User State
        $statement->getMeta()->setMiscDataValue('userState', 'aaa');
        // --- Test use of $exportConfig
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_USER_STATE, $exportConfig, $statement));
        $exportConfig->setUserStateExportable(false);
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_USER_STATE, $exportConfig, $statement));
        $exportConfig->setUserStateExportable(true);
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_USER_STATE, $exportConfig, $statement));
        // --- Test 'userGroup' has valid info
        $statement->getMeta()->setMiscDataValue('userState', null);
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_USER_STATE, $exportConfig, $statement));

        // User Group
        $statement->getMeta()->setMiscDataValue('userGroup', 'aaa');
        // --- Test use of $exportConfig
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_USER_GROUP, $exportConfig, $statement));
        $exportConfig->setUserGroupExportable(false);
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_USER_GROUP, $exportConfig, $statement));
        $exportConfig->setUserGroupExportable(true);
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_USER_GROUP, $exportConfig, $statement));
        // --- Test 'userGroup' has valid info
        $statement->getMeta()->setMiscDataValue('userGroup', null);
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_USER_GROUP, $exportConfig, $statement));

        // User Organisation
        $statement->getMeta()->setMiscDataValue('userOrganisation', 'aaa');
        // --- Test use of $exportConfig
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_USER_ORGANISATION, $exportConfig, $statement));
        $exportConfig->setUserOrganisationExportable(false);
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_USER_ORGANISATION, $exportConfig, $statement));
        $exportConfig->setUserOrganisationExportable(true);
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_USER_ORGANISATION, $exportConfig, $statement));
        // --- Test 'userOrganisation' has valid info
        $statement->getMeta()->setMiscDataValue('userOrganisation', null);
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_USER_ORGANISATION, $exportConfig, $statement));

        // User Position
        $statement->getMeta()->setMiscDataValue('userPosition', 'aaa');
        // --- Test use of $exportConfig
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_USER_POSITION, $exportConfig, $statement));
        $exportConfig->setUserPositionExportable(false);
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_USER_POSITION, $exportConfig, $statement));
        $exportConfig->setUserPositionExportable(true);
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_USER_POSITION, $exportConfig, $statement));
        // --- Test 'userPosition' has valid info
        $statement->getMeta()->setMiscDataValue('userPosition', null);
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_USER_POSITION, $exportConfig, $statement));

        // Orga Name
        // --- Test use of $exportConfig
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_ORGA_NAME, $exportConfig));
        $exportConfig->setOrgaNameExportable(false);
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_ORGA_NAME, $exportConfig));
        $exportConfig->setOrgaNameExportable(true);
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_ORGA_NAME, $exportConfig));

        // Orga Department
        // --- Test use of $exportConfig
        $this->assertTrue($this->sut->isExportable(
            FieldDecider::FIELD_ORGA_DEPARTMENT,
            $exportConfig,
            $statement,
            ['orgaDepartment' => 'aaa']
        ));
        $exportConfig->setDepartmentNameExportable(false);
        $this->assertFalse($this->sut->isExportable(
            FieldDecider::FIELD_ORGA_DEPARTMENT,
            $exportConfig,
            $statement,
            ['orgaDepartment' => 'aaa']
        ));
        $exportConfig->setDepartmentNameExportable(true);
        $this->assertTrue($this->sut->isExportable(
            FieldDecider::FIELD_ORGA_DEPARTMENT,
            $exportConfig,
            $statement,
            ['orgaDepartment' => 'aaa']
        ));
        // --- Test $data['orgaDepartment'] has valid info
        $this->assertFalse($this->sut->isExportable(
            FieldDecider::FIELD_ORGA_DEPARTMENT,
            $exportConfig,
            $statement,
            ['orgaDepartment' => null]
        ));

        // Submitter Name
        // --- Test use of $exportConfig
        $this->assertTrue($this->sut->isExportable(
            FieldDecider::FIELD_SUBMITTER_NAME,
            $exportConfig,
            $statement,
            [],
            false
        ));
        $exportConfig->setSubmitterNameExportable(false);
        $this->assertFalse($this->sut->isExportable(
            FieldDecider::FIELD_SUBMITTER_NAME,
            $exportConfig,
            $statement,
            [],
            false
        ));
        $exportConfig->setSubmitterNameExportable(true);
        $this->assertTrue($this->sut->isExportable(
            FieldDecider::FIELD_SUBMITTER_NAME,
            $exportConfig,
            $statement,
            [],
            false
        ));
        // --- Test anonymous
        $this->assertFalse($this->sut->isExportable(
            FieldDecider::FIELD_SUBMITTER_NAME,
            $exportConfig,
            $statement,
            [],
            true
        ));

        // Citizen Info
        // --- Test orgaName value (must be BÜRGER)
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_CITIZEN_INFO, $exportConfig, $statement));
        $statement->getMeta()->setOrgaName(FieldDecider::CITIZEN_ORGA_NAME);
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_CITIZEN_INFO, $exportConfig, $statement));

        // Address
        // --- Test use of $exportConfig
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_ADDRESS, $exportConfig, $statement, [], false));
        $exportConfig->setStreetExportable(false);
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_ADDRESS, $exportConfig, $statement, [], false));
        $exportConfig->setStreetExportable(true);
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_ADDRESS, $exportConfig, $statement, [], false));
        // --- Test anonymous
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_ADDRESS, $exportConfig, $statement, [], true));

        // Email
        $this->enablePermissions(['field_statement_submitter_email_address']);
        $statement->getMeta()->setOrgaEmail('aaa@aaa.com');
        // --- Test use of $exportConfig
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_EMAIL, $exportConfig, $statement, [], false));
        $exportConfig->setEmailExportable(false);
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_EMAIL, $exportConfig, $statement, [], false));
        $exportConfig->setEmailExportable(true);
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_EMAIL, $exportConfig, $statement, [], false));
        // --- Test existing Email
        $statement->getMeta()->setOrgaEmail('');
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_EMAIL, $exportConfig, $statement, [], false));
        $statement->getMeta()->setOrgaEmail('aaa@aaa.com');
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_EMAIL, $exportConfig, $statement, [], false));
        // --- Test anonymous
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_EMAIL, $exportConfig, $statement, [], true));
        // --- Test permission
        $this->disablePermissions(['field_statement_submitter_email_address']);
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_EMAIL, $exportConfig, $statement, [], false));

        // Phone Number
        $data = ['phoneNumber' => '999887766'];
        // --- Test use of $exportConfig
        $this->assertTrue($this->sut->isExportable(
            FieldDecider::FIELD_PHONE_NUMBER,
            $exportConfig,
            $statement,
            $data,
            false
        ));
        $exportConfig->setPhoneNumberExportable(false);
        $this->assertFalse($this->sut->isExportable(
            FieldDecider::FIELD_PHONE_NUMBER,
            $exportConfig,
            $statement,
            $data,
            false
        ));
        $exportConfig->setPhoneNumberExportable(true);
        $this->assertTrue($this->sut->isExportable(
            FieldDecider::FIELD_PHONE_NUMBER,
            $exportConfig,
            $statement,
            $data,
            false
        ));
        // --- Test existing Phone Number
        $data = [];
        $this->assertFalse($this->sut->isExportable(
            FieldDecider::FIELD_PHONE_NUMBER,
            $exportConfig,
            $statement,
            $data,
            false
        ));
        $data = ['phoneNumber' => '999887766'];
        $this->assertTrue($this->sut->isExportable(
            FieldDecider::FIELD_PHONE_NUMBER,
            $exportConfig,
            $statement,
            $data,
            false
        ));
        // --- Test anonymous
        $this->assertFalse($this->sut->isExportable(
            FieldDecider::FIELD_PHONE_NUMBER,
            $exportConfig,
            $statement,
            $data,
            true
        ));

        // Show in public area
        $this->enablePermissions(['field_statement_public_allowed']);
        // --- Test use of $exportConfig
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_SHOW_IN_PUBLIC_AREA, $exportConfig, $statement, [], false));
        $exportConfig->setShowInPublicAreaExportable(false);
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_SHOW_IN_PUBLIC_AREA, $exportConfig, $statement, [], false));
        $exportConfig->setShowInPublicAreaExportable(true);
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_SHOW_IN_PUBLIC_AREA, $exportConfig, $statement, [], false));
        // --- Test permission
        $this->disablePermissions(['field_statement_public_allowed']);
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_SHOW_IN_PUBLIC_AREA, $exportConfig, $statement, [], false));

        // Document
        // --- Test use of $exportConfig
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_DOCUMENT, $exportConfig, $statement, [], false));
        $exportConfig->setDocumentExportable(false);
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_DOCUMENT, $exportConfig, $statement, [], false));
        $exportConfig->setDocumentExportable(true);
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_DOCUMENT, $exportConfig, $statement, [], false));
        // --- Test Document exists
        $statement->setElement(null);
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_DOCUMENT, $exportConfig, $statement, [], false));

        // Paragraph
        // --- Test use of $exportConfig
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_PARAGRAPH, $exportConfig, $statement, [], false));
        $exportConfig->setParagraphExportable(false);
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_PARAGRAPH, $exportConfig, $statement, [], false));
        $exportConfig->setParagraphExportable(true);
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_PARAGRAPH, $exportConfig, $statement, [], false));
        // --- Test Document exists
        $statement->setParagraph(null);
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_PARAGRAPH, $exportConfig, $statement, [], false));

        // Files
        $statement->setFiles([new File()]);
        // --- Test use of $exportConfig
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_FILES, $exportConfig, $statement, [], false));
        $exportConfig->setFilesExportable(false);
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_FILES, $exportConfig, $statement, [], false));
        $exportConfig->setFilesExportable(true);
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_FILES, $exportConfig, $statement, [], false));
        // --- Test Files exist
        $statement->setFiles([]);
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_FILES, $exportConfig, $statement, [], false));

        // Attachments
        // --- Test use of $exportConfig
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_ATTACHMENTS, $exportConfig, $statement, [], false));
        $exportConfig->setAttachmentsExportable(false);
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_ATTACHMENTS, $exportConfig, $statement, [], false));
        $exportConfig->setAttachmentsExportable(true);
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_ATTACHMENTS, $exportConfig, $statement, [], false));

        // Priority
        $statement->setPriority('aaa');
        // --- Test use of $exportConfig
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_PRIORITY, $exportConfig, $statement, [], false));
        $exportConfig->setPriorityExportable(false);
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_PRIORITY, $exportConfig, $statement, [], false));
        $exportConfig->setPriorityExportable(true);
        $this->assertTrue($this->sut->isExportable(FieldDecider::FIELD_PRIORITY, $exportConfig, $statement, [], false));
        // --- Test Priority exist
        $statement->setPriority('');
        $this->assertFalse($this->sut->isExportable(FieldDecider::FIELD_PRIORITY, $exportConfig, $statement, [], false));
    }
}
