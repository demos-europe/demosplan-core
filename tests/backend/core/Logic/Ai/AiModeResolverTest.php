<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Logic\Ai;

use DemosEurope\DemosplanAddon\Contracts\Ai\AiModeProviderInterface;
use DemosEurope\DemosplanAddon\Contracts\Ai\AiModes;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Logic\Ai\AiModeResolver;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class AiModeResolverTest extends TestCase
{
    private const MODE_A = 'mode-a';
    private const MODE_B = 'mode-b';

    public function testStoredModeWins(): void
    {
        $sut = $this->resolver($this->provider([self::MODE_A, self::MODE_B], self::MODE_B));

        self::assertSame(self::MODE_A, $sut->resolve($this->orga('a', self::MODE_A)));
    }

    public function testNullUsesFirstProviderDefault(): void
    {
        $sut = $this->resolver(
            $this->provider([self::MODE_A, self::MODE_B], null),
            $this->provider([self::MODE_A], self::MODE_A),
        );

        self::assertSame(self::MODE_A, $sut->resolve($this->orga('a', null)));
    }

    public function testNullWithoutAnyProviderDefaultGivesNone(): void
    {
        $sut = $this->resolver($this->provider([self::MODE_A, self::MODE_B], null));

        self::assertSame(AiModes::NONE, $sut->resolve($this->orga('a', null)));
    }

    public function testNullWithoutProvidersGivesNone(): void
    {
        self::assertSame(AiModes::NONE, $this->resolver()->resolve($this->orga('a', null)));
    }

    public function testDisallowedStoredModeDegrades(): void
    {
        $sut = $this->resolver($this->provider([self::MODE_A], null));

        self::assertSame(AiModes::NONE, $sut->resolve($this->orga('a', self::MODE_B)));
    }

    public function testNoneIsAlwaysAllowed(): void
    {
        $sut = $this->resolver();

        self::assertSame(AiModes::NONE, $sut->resolve($this->orga('a', AiModes::NONE)));
        self::assertSame([AiModes::NONE], $sut->getAllowedModes());
    }

    public function testResultIsMemoisedPerOrgaUntilReset(): void
    {
        $provider = $this->createMock(AiModeProviderInterface::class);
        $provider->method('getAvailableModes')->willReturn([self::MODE_B]);
        $provider->expects(self::exactly(2))->method('getDefaultMode')->willReturn(self::MODE_B);
        $sut = $this->resolver($provider);
        $orga = $this->orga('a', null);

        $sut->resolve($orga);
        $sut->resolve($orga);
        $sut->reset();
        $sut->resolve($orga);
    }

    private function resolver(AiModeProviderInterface ...$providers): AiModeResolver
    {
        return new AiModeResolver($providers, new NullLogger());
    }

    /**
     * @param array<int, string> $modes
     */
    private function provider(array $modes, ?string $default): AiModeProviderInterface
    {
        $provider = $this->createStub(AiModeProviderInterface::class);
        $provider->method('getAvailableModes')->willReturn($modes);
        $provider->method('getDefaultMode')->willReturn($default);

        return $provider;
    }

    private function orga(string $id, ?string $mode): OrgaInterface
    {
        $orga = new Orga();
        $orga->setId($id);
        $orga->setAiMode($mode);

        return $orga;
    }
}
