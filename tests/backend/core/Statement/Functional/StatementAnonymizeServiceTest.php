<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Functional;

use demosplan\DemosPlanCoreBundle\Entity\EntityContentChange;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementAnonymizeService;
use Exception;
use Symfony\Component\HttpFoundation\Session\Session;
use Tests\Base\FunctionalTestCase;

class StatementAnonymizeServiceTest extends FunctionalTestCase
{
    /**
     * @var StatementAnonymizeService
     */
    protected $sut;

    /**
     * @var Session
     */
    protected $mockSession;

    public function testAnonymizeAddressDataWithFullDataToAnonymize(): void
    {
        $this->disablePermissions(['feature_keep_street_on_anonymize']);
        static::assertFalse($this->sut->getPermissions()->hasPermission('feature_keep_street_on_anonymize'));
        $statement = $this->getUnpersistedFullAddressStatement();
        $meta = $statement->getMeta();
        self::assertNotSame('', $meta->getOrgaStreet());
        self::assertNotSame('', $meta->getHouseNumber());
        self::assertNotSame('', $meta->getOrgaEmail());
        self::assertNotSame('', $meta->getOrgaPostalCode());
        self::assertNotSame('', $meta->getOrgaCity());
        $this->sut->anonymizeAddressData($statement);
        self::assertSame('', $meta->getOrgaStreet());
        self::assertSame('', $meta->getHouseNumber());
        self::assertSame('', $meta->getOrgaEmail());
        self::assertSame('', $meta->getOrgaPostalCode());
        self::assertSame('', $meta->getOrgaCity());
    }

    public function testAnonymizeAddressDataWithFullDataToAnonymizeKeepStreet(): void
    {
        $statement = $this->getUnpersistedFullAddressStatement();
        $meta = $statement->getMeta();
        $streetBefore = $meta->getOrgaStreet();

        self::assertNotSame('', $meta->getOrgaStreet());
        self::assertNotSame('', $meta->getHouseNumber());
        self::assertNotSame('', $meta->getOrgaEmail());
        self::assertNotSame('', $meta->getOrgaPostalCode());
        self::assertNotSame('', $meta->getOrgaCity());
        $this->enablePermissions(['feature_keep_street_on_anonymize']);
        static::assertTrue($this->sut->getPermissions()->hasPermission('feature_keep_street_on_anonymize'));
        $this->sut->anonymizeAddressData($statement);

        self::assertSame($streetBefore, $meta->getOrgaStreet());
        self::assertSame('', $meta->getHouseNumber());
        self::assertSame('', $meta->getOrgaEmail());
        self::assertSame('', $meta->getOrgaPostalCode());
        self::assertSame('', $meta->getOrgaCity());
    }

    public function testAnonymizeAddressDataWithIncompleteDataToAnonymize(): void
    {
        $this->disablePermissions(['feature_keep_street_on_anonymize']);
        static::assertFalse($this->sut->getPermissions()->hasPermission('feature_keep_street_on_anonymize'));
        $statement = $this->getUnpersistedIncompleteAddressStatement();
        $meta = $statement->getMeta();
        self::assertNotSame('', $meta->getOrgaStreet());
        self::assertSame('', $meta->getHouseNumber());
        self::assertNotSame('', $meta->getOrgaEmail());
        self::assertNotSame('', $meta->getOrgaPostalCode());
        self::assertNotSame('', $meta->getOrgaCity());
        $this->sut->anonymizeAddressData($statement);
        self::assertSame('', $meta->getOrgaStreet());
        self::assertSame('', $meta->getHouseNumber());
        self::assertSame('', $meta->getOrgaEmail());
        self::assertSame('', $meta->getOrgaPostalCode());
        self::assertSame('', $meta->getOrgaCity());
    }

    public function testAnonymizeAddressDataWithIncompleteDataToAnonymizeKeepStreet(): void
    {
        $statement = $this->getUnpersistedIncompleteAddressStatement();
        $meta = $statement->getMeta();
        self::assertNotSame('', $meta->getOrgaStreet());
        self::assertSame('', $meta->getHouseNumber());
        self::assertNotSame('', $meta->getOrgaEmail());
        self::assertNotSame('', $meta->getOrgaPostalCode());
        self::assertNotSame('', $meta->getOrgaCity());
        $this->enablePermissions(['feature_keep_street_on_anonymize']);
        static::assertTrue($this->sut->getPermissions()->hasPermission('feature_keep_street_on_anonymize'));
        $this->sut->anonymizeAddressData($statement);
        self::assertSame('', $meta->getOrgaStreet());
        self::assertSame('', $meta->getHouseNumber());
        self::assertSame('', $meta->getOrgaEmail());
        self::assertSame('', $meta->getOrgaPostalCode());
        self::assertSame('', $meta->getOrgaCity());
    }

    private function getUnpersistedFullAddressStatement(): Statement
    {
        // statement with complete address data
        $fullAddressStatementMeta = new StatementMeta();
        $fullAddressStatementMeta->setOrgaStreet('Sesamstraße');
        $fullAddressStatementMeta->setHouseNumber('2');
        $fullAddressStatementMeta->setOrgaEmail('sesam@example.com');
        $fullAddressStatementMeta->setOrgaPostalCode('12345');
        $fullAddressStatementMeta->setOrgaCity('Neverland');
        $fullAddressStatement = new Statement();
        $fullAddressStatement->setMeta($fullAddressStatementMeta->setStatement($fullAddressStatement));

        return $fullAddressStatement;
    }

    private function getUnpersistedIncompleteAddressStatement(): Statement
    {
        // statement with incomplete address data (no house number)
        $addressStatementMeta = new StatementMeta();
        $addressStatementMeta->setOrgaStreet('Sesamstraße');
        $addressStatementMeta->setHouseNumber('');
        $addressStatementMeta->setOrgaEmail('sesam@example.com');
        $addressStatementMeta->setOrgaPostalCode('12345');
        $addressStatementMeta->setOrgaCity('Neverland');
        $addressStatement = new Statement();
        $addressStatement->setMeta($addressStatementMeta->setStatement($addressStatement));

        return $addressStatement;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::$container->get(StatementAnonymizeService::class);

        $user = $this->getUserReference('testUserPlanningOffice');
        $this->logIn($user);

        $permissions = $this->sut->getPermissions();
        $permissions->initPermissions($user);
        $permissions->enablePermissions(['feature_statements_fragment_edit']);
        $this->sut->setPermissions($permissions);
    }

    public function testAnonymizeHistoryOfStatementText(): void
    {
        /** @var Statement $testStatement */
        $testStatement = $this->getReference('testStatement');
        $testOriginalStatement = $testStatement->getOriginal();

        $textChangesBefore = $this->getEntries(EntityContentChange::class, ['entityId' => $testOriginalStatement->getId(), 'entityField' => 'text']);
        foreach ($testOriginalStatement->getChildren() as $child) {
            $textChanges = $this->getEntries(EntityContentChange::class, ['entityId' => $child->getId(), 'entityField' => 'text']);
            $textChangesBefore = array_merge($textChangesBefore, $textChanges);
        }

        $allChangesBefore = $this->getEntries(EntityContentChange::class);
        static::assertNotEmpty($allChangesBefore);
        static::assertNotEmpty($textChangesBefore);

        $successfully = false;
        try {
            $this->sut->deleteHistoryOfTextsRecursively($testStatement);
            $successfully = true;
        } catch (Exception $e) {
        }
        static::assertTrue($successfully);

        $textChangesAfter = $this->getEntries(EntityContentChange::class, ['entityId' => $testOriginalStatement->getId(), 'entityField' => 'text']);
        foreach ($testOriginalStatement->getChildren() as $child) {
            $textChanges = $this->getEntries(EntityContentChange::class, ['entityId' => $child->getId(), 'entityField' => 'text']);
            $textChangesAfter = array_merge($textChangesAfter, $textChanges);
        }
        static::assertEmpty($textChangesAfter);
        $allChangesAfter = $this->getEntries(EntityContentChange::class);

        static::assertSame(count($allChangesAfter), count($allChangesBefore) - count($textChangesBefore));
    }

    public function testReportEntryOnAnonymizeUserDataOfStatement(): void
    {
        self::markSkippedForCIIntervention();

        /** @var Statement $testStatement */
        $testStatement = $this->getReference('testStatement');
        $testOriginalStatement = $testStatement->getOriginal();
        static::assertTrue($testOriginalStatement->isOriginal());

        $reportEntriesBefore = $this->countEntries(ReportEntry::class, ['category' => 'anonymize', 'group' => 'statement']);
        $this->sut->anonymizeUserDataOfStatement(
            $testOriginalStatement,
            true,
            false,
            User::ANONYMOUS_USER_ID,
            false
        );
        $reportEntriesAfter = $this->countEntries(ReportEntry::class, ['category' => 'anonymize', 'group' => 'statement']);

        static::assertSame($reportEntriesBefore + 1, $reportEntriesAfter);
    }

    public function testReportEntryOnDeleteHistoryOfTextsRecursively(): void
    {
        self::markSkippedForCIIntervention();

        /** @var Statement $testStatement */
        $testStatement = $this->getReference('testStatement');
        $testOriginalStatement = $testStatement->getOriginal();
        static::assertTrue($testOriginalStatement->isOriginal());

        $reportEntriesBefore = $this->countEntries(ReportEntry::class, ['category' => 'delete', 'group' => 'statementTextFieldHistory']);
        $this->sut->deleteHistoryOfTextsRecursively($testOriginalStatement);
        $reportEntriesAfter = $this->countEntries(ReportEntry::class, ['category' => 'delete', 'group' => 'statementTextFieldHistory']);

        static::assertSame($reportEntriesBefore + 1, $reportEntriesAfter);
    }
}
