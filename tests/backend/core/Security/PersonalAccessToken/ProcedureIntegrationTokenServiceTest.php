<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Security\PersonalAccessToken;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureIntegrationToken;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\PersonalAccessToken;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureIntegrationTokenRepository;
use demosplan\DemosPlanCoreBundle\Security\ApiToken\ApiTokenSecretService;
use demosplan\DemosPlanCoreBundle\Security\PersonalAccessToken\PersonalAccessTokenScope;
use demosplan\DemosPlanCoreBundle\Security\ProcedureIntegrationToken\ProcedureIntegrationTokenService;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Base\UnitTestCase;

/**
 * Uses the real {@see ApiTokenSecretService} from the container so that issue-then-authenticate is
 * exercised against the configured hasher rather than a stub agreeing with itself.
 */
class ProcedureIntegrationTokenServiceTest extends UnitTestCase
{
    private ?ProcedureIntegrationTokenService $sut = null;
    private ?MockObject $repository = null;
    private ?ProcedureIntegrationToken $stored = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stored = null;
        $this->repository = $this->createMock(ProcedureIntegrationTokenRepository::class);
        // The prefix-uniqueness probe during create() must miss; the lookup during authentication must
        // hit. Both answers come from the same holder.
        $this->repository->method('findByPrefix')->willReturnCallback(
            fn (): ?ProcedureIntegrationToken => $this->stored
        );
        $this->repository->method('persistAndFlush')->willReturnCallback(
            function (ProcedureIntegrationToken $token): void {
                $this->stored = $token;
            }
        );

        $this->sut = new ProcedureIntegrationTokenService(
            $this->repository,
            $this->createMock(EntityManagerInterface::class),
            self::getContainer()->get(ApiTokenSecretService::class),
        );
    }

    public function testIssuesATokenWithTheIntegrationLiteralPrefix(): void
    {
        $result = $this->create();

        self::assertStringStartsWith(ProcedureIntegrationToken::TOKEN_LITERAL_PREFIX, $result->plaintext);
        self::assertStringStartsNotWith(PersonalAccessToken::TOKEN_LITERAL_PREFIX, $result->plaintext);
    }

    public function testNeverPersistsTheSecretInClear(): void
    {
        $result = $this->create();

        self::assertNotSame($result->plaintext, $result->token->getTokenHash());
        self::assertStringNotContainsString($result->token->getTokenHash(), $result->plaintext);
    }

    public function testTheIssuedTokenAuthenticates(): void
    {
        $result = $this->create();

        self::assertSame($result->token, $this->sut->findByPlaintext($result->plaintext));
    }

    public function testAWrongSecretDoesNotAuthenticate(): void
    {
        $result = $this->create();
        $wrong = ProcedureIntegrationToken::TOKEN_LITERAL_PREFIX
            .$result->token->getTokenPrefix()
            .str_repeat('q', 32);

        self::assertNull($this->sut->findByPlaintext($wrong));
    }

    /**
     * A PAT string must not be accepted here even if it is otherwise valid: the prefixes are what keep
     * the two credential kinds apart.
     */
    public function testAPersonalAccessTokenStringIsNotAccepted(): void
    {
        $result = $this->create();
        $asPat = PersonalAccessToken::TOKEN_LITERAL_PREFIX
            .substr($result->plaintext, strlen(ProcedureIntegrationToken::TOKEN_LITERAL_PREFIX));

        self::assertNull($this->sut->findByPlaintext($asPat));
    }

    public function testIssuesWithoutExpiryByDefault(): void
    {
        self::assertNull($this->create()->token->getExpiresAt());
    }

    public function testRejectsAnExpiryInThePast(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->create(expiresAt: new DateTime('-1 day'));
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->create(name: '   ');
    }

    public function testRejectsScopesThatAreAllUnknown(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->create(scopes: ['bogus:scope']);
    }

    public function testRevokedTokenStopsAuthenticating(): void
    {
        $result = $this->create();
        $this->sut->revoke($result->token);

        self::assertTrue($result->token->isRevoked());
        self::assertNull($this->sut->findByPlaintext($result->plaintext));
    }

    /**
     * @param list<string> $scopes
     */
    private function create(
        string $name = 'integration',
        array $scopes = [PersonalAccessTokenScope::RECOMMENDATIONS_WRITE],
        ?DateTime $expiresAt = null,
    ) {
        $procedure = $this->createMock(Procedure::class);
        $procedure->method('getId')->willReturn('procedure-1');
        $procedure->method('isDeleted')->willReturn(false);

        return $this->sut->create(
            procedure: $procedure,
            customer: $this->createMock(Customer::class),
            name: $name,
            scopes: $scopes,
            expiresAt: $expiresAt,
        );
    }
}
