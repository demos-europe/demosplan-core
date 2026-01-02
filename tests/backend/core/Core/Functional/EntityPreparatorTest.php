<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use demosplan\DemosPlanCoreBundle\Entity\ExportFieldsConfiguration;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\Export\EntityPreparator;
use Tests\Base\FunctionalTestCase;

class EntityPreparatorTest extends FunctionalTestCase
{
    /** @var EntityPreparator */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(EntityPreparator::class);
    }

    public function testPrepareEntity()
    {
        /** @var Procedure $procedure */
        $procedure = $this->getReference('testProcedure2');
        $emptyExportConfig = new ExportFieldsConfiguration($procedure);
        $data = [
            'r_statement_name'          => true,
            'r_submitted_date'          => true,
            'r_procedure_name'          => true,
            'r_userState'               => true,
            'r_userOrganisation'        => true,
            'institution'               => true,
            'r_public_participation'    => true,
            'r_orga_name'               => true,
            'r_author_name'             => true,
            'r_public_show'             => true,
            'r_paragraph'               => true,
            'r_attachment'              => true,
            'r_submitterEmailAddress'   => true,
            'r_phone'                   => true,
            'r_orga_street'             => true,
            'r_orga_city'               => true,
        ];

        $this->sut->prepareEntity($data, $emptyExportConfig);

        static::assertEquals(false, $emptyExportConfig->isIdExportable());
        static::assertEquals(true, $emptyExportConfig->isStatementNameExportable());
        static::assertEquals(true, $emptyExportConfig->isCreationDateExportable());
        static::assertEquals(true, $emptyExportConfig->isProcedureNameExportable());
        static::assertEquals(false, $emptyExportConfig->isProcedurePhaseExportable());
        static::assertEquals(false, $emptyExportConfig->isVotesNumExportable());
        static::assertEquals(true, $emptyExportConfig->isUserStateExportable());
        static::assertEquals(false, $emptyExportConfig->isUserGroupExportable());
        static::assertEquals(true, $emptyExportConfig->isUserOrganisationExportable());
        static::assertEquals(false, $emptyExportConfig->isUserPositionExportable());
        static::assertEquals(true, $emptyExportConfig->isInstitutionExportable());
        static::assertEquals(true, $emptyExportConfig->isPublicParticipationExportable());
        static::assertEquals(true, $emptyExportConfig->isOrgaNameExportable());
        static::assertEquals(false, $emptyExportConfig->isDepartmentNameExportable());
        static::assertEquals(true, $emptyExportConfig->isSubmitterNameExportable());
        static::assertEquals(true, $emptyExportConfig->isShowInPublicAreaExportable());
        static::assertEquals(false, $emptyExportConfig->isDocumentExportable());
        static::assertEquals(true, $emptyExportConfig->isParagraphExportable());
        static::assertEquals(false, $emptyExportConfig->isFilesExportable());
        static::assertEquals(true, $emptyExportConfig->isAttachmentsExportable());
        static::assertEquals(false, $emptyExportConfig->isPriorityExportable());
        static::assertEquals(true, $emptyExportConfig->isEmailExportable());
        static::assertEquals(true, $emptyExportConfig->isPhoneNumberExportable());
        static::assertEquals(true, $emptyExportConfig->isStreetExportable());
        static::assertEquals(false, $emptyExportConfig->isStreetNumberExportable());
        static::assertEquals(false, $emptyExportConfig->isPostalCodeExportable());
        static::assertEquals(true, $emptyExportConfig->isCityExportable());
        static::assertEquals(false, $emptyExportConfig->isInstitutionOrCitizenExportable());
    }
}
