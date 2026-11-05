<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\User\Functional;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Logic\User\RpcOrgaTwoFactorRequiredBulkSetter;
use stdClass;
use Tests\Base\FunctionalTestCase;

class RpcOrgaTwoFactorRequiredBulkSetterTest extends FunctionalTestCase
{
    protected ?RpcOrgaTwoFactorRequiredBulkSetter $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(RpcOrgaTwoFactorRequiredBulkSetter::class);
        $this->loginTestUser();
    }

    public function testSupportsOnlyItsMethod(): void
    {
        static::assertTrue($this->sut->supports(RpcOrgaTwoFactorRequiredBulkSetter::METHOD));
        static::assertFalse($this->sut->supports('segments.bulk.delete'));
        static::assertTrue($this->sut->isTransactional());
    }

    public function testSetsFlagAndWritesReportEntryPerOrganisation(): void
    {
        $this->enablePermissions([RpcOrgaTwoFactorRequiredBulkSetter::PERMISSION, 'area_manage_orgas_all']);
        $first = OrgaFactory::createOne(['deleted' => false, 'twoFactorRequired' => false]);
        $second = OrgaFactory::createOne(['deleted' => false, 'twoFactorRequired' => true]);

        $result = $this->sut->execute(null, $this->buildRequest([$first->getId(), $second->getId()], true));

        static::assertCount(1, $result);
        static::assertEqualsCanonicalizing([$first->getId(), $second->getId()], $result[0]->result['updatedIds']);
        static::assertSame([], $result[0]->result['skippedIds']);
        static::assertTrue(OrgaFactory::find($first->getId())->isTwoFactorRequired());
        static::assertTrue(OrgaFactory::find($second->getId())->isTwoFactorRequired());
        // only the orga whose value changed gets an entry
        static::assertCount(1, $this->getTwoFactorReportEntries($first->getId()));
        static::assertCount(0, $this->getTwoFactorReportEntries($second->getId()));
    }

    public function testDeniesAccessWithoutPermission(): void
    {
        $this->disablePermissions([RpcOrgaTwoFactorRequiredBulkSetter::PERMISSION]);
        $orga = OrgaFactory::createOne(['deleted' => false, 'twoFactorRequired' => false]);

        $result = $this->sut->execute(null, $this->buildRequest([$orga->getId()], true));

        static::assertObjectHasProperty('error', $result[0]);
        static::assertFalse(OrgaFactory::find($orga->getId())->isTwoFactorRequired());
    }

    public function testSkipsOrganisationsOutsideTheCurrentCustomer(): void
    {
        // disabling re-initialises the permission set, hence it has to come first
        $this->disablePermissions(['area_manage_orgas_all']);
        $this->enablePermissions([RpcOrgaTwoFactorRequiredBulkSetter::PERMISSION]);
        // a fresh orga has no status row in any customer, hence it is foreign to the current one
        $foreign = OrgaFactory::createOne(['deleted' => false, 'twoFactorRequired' => false]);

        $result = $this->sut->execute(null, $this->buildRequest([$foreign->getId(), 'does-not-exist'], true));

        static::assertSame([], $result[0]->result['updatedIds']);
        static::assertEqualsCanonicalizing([$foreign->getId(), 'does-not-exist'], $result[0]->result['skippedIds']);
        static::assertFalse(OrgaFactory::find($foreign->getId())->isTwoFactorRequired());
    }

    public function testRejectsRequestWithoutIds(): void
    {
        $this->enablePermissions([RpcOrgaTwoFactorRequiredBulkSetter::PERMISSION]);

        $result = $this->sut->execute(null, $this->buildRequest([], false));

        static::assertObjectHasProperty('error', $result[0]);
    }

    /**
     * @param list<string> $organisationIds
     */
    private function buildRequest(array $organisationIds, bool $twoFactorRequired): stdClass
    {
        $params = new stdClass();
        $params->organisationIds = $organisationIds;
        $params->twoFactorRequired = $twoFactorRequired;

        $request = new stdClass();
        $request->jsonrpc = '2.0';
        $request->method = RpcOrgaTwoFactorRequiredBulkSetter::METHOD;
        $request->id = 'test-request';
        $request->params = $params;

        return $request;
    }

    /**
     * @return list<ReportEntry>
     */
    private function getTwoFactorReportEntries(string $orgaId): array
    {
        return $this->getEntries(ReportEntry::class, [
            'category'   => ReportEntry::CATEGORY_ORGA_TWO_FACTOR_REQUIRED_CHANGE,
            'identifier' => $orgaId,
        ]);
    }
}
