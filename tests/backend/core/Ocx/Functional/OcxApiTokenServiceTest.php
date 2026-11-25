<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Ocx\Functional;

use DateTime;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\Entity\Ocx\OcxApiToken;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Ocx\OcxApiTokenService;
use demosplan\DemosPlanCoreBundle\Logic\Ocx\OcxApiUserCreator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Base\FunctionalTestCase;

class OcxApiTokenServiceTest extends FunctionalTestCase
{
    /** @var OcxApiTokenService|null */
    protected $sut;
    private ?User $user = null;
    private ?string $token = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(OcxApiTokenService::class);
        $orga = $this->getProcedureReference(LoadProcedureData::TESTPROCEDURE)->getOrga();
        [$this->user, $this->token] = $this->getContainer()->get(OcxApiUserCreator::class)
            ->create($orga, $orga->getCustomers()->first(), 'test');
    }

    public function testIssuedTokenResolvesItsUserAndOnlyTheHashIsStored(): void
    {
        self::assertMatchesRegularExpression('/^dplan_ocx_[a-z2-9]{44}$/', $this->token);
        self::assertSame($this->user->getId(), $this->sut->findActiveToken($this->token)?->getUser()->getId());

        $stored = $this->findToken();
        self::assertStringNotContainsString(substr($this->token, 22), $stored->getTokenHash());
        self::assertNotNull($stored->getLastUsedAt());
    }

    public function testLastUsedIsWrittenAtMostEveryFewMinutes(): void
    {
        $recent = new DateTime('-1 minute');
        $this->findToken()->markUsed($recent);
        $this->getEntityManager()->flush();

        $this->sut->findActiveToken($this->token);

        self::assertEquals($recent, $this->findToken()->getLastUsedAt());
    }

    public static function foreignTokens(): array
    {
        return [
            'other kind'      => ['dplan_pat_abcdefghijkmnpqrstuvwxyz23456789abcdefghijkm'],
            'wrong length'    => ['dplan_ocx_abc'],
            'unknown prefix'  => ['dplan_ocx_222222222222abcdefghijkmnpqrstuvwxyz23456789'],
        ];
    }

    #[DataProvider('foreignTokens')]
    public function testMalformedOrUnknownTokenResolvesNoUser(string $token): void
    {
        self::assertNull($this->sut->findActiveToken($token));
    }

    public function testWrongSecretResolvesNoUser(): void
    {
        $tampered = substr($this->token, 0, -1).('a' === substr($this->token, -1) ? 'b' : 'a');

        self::assertNull($this->sut->findActiveToken($tampered));
    }

    public function testRevokedTokenResolvesNoUser(): void
    {
        self::assertTrue($this->sut->revoke($this->findToken()->getTokenPrefix()));
        self::assertFalse($this->sut->revoke($this->findToken()->getTokenPrefix()));

        self::assertNull($this->sut->findActiveToken($this->token));
    }

    public function testTokenOfDeletedUserResolvesNoUser(): void
    {
        $this->user->setDeleted(true);
        $this->getEntityManager()->flush();

        self::assertNull($this->sut->findActiveToken($this->token));
    }

    public function testFurtherTokenForTheSameUserWorksAlongside(): void
    {
        $second = $this->sut->issue($this->user, $this->findToken()->getCustomer(), 'rotation');

        self::assertSame($this->user->getId(), $this->sut->findActiveToken($second)?->getUser()->getId());
        self::assertSame($this->user->getId(), $this->sut->findActiveToken($this->token)?->getUser()->getId());
    }

    private function findToken(): OcxApiToken
    {
        $token = $this->getEntityManager()->getRepository(OcxApiToken::class)
            ->findOneBy(['user' => $this->user], ['createdAt' => 'ASC']);
        self::assertInstanceOf(OcxApiToken::class, $token);

        return $token;
    }
}
