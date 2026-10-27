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
    public function testStoredModeWins(): void
    {
        $sut = $this->resolver($this->provider([AiModes::CLASSIC, AiModes::LLM], AiModes::LLM));

        self::assertSame(AiModes::CLASSIC, $sut->resolve($this->orga('a', AiModes::CLASSIC)));
    }

    public function testNullUsesFirstProviderDefault(): void
    {
        $sut = $this->resolver(
            $this->provider([AiModes::CLASSIC, AiModes::LLM], null),
            $this->provider([AiModes::CLASSIC], AiModes::CLASSIC),
        );

        self::assertSame(AiModes::CLASSIC, $sut->resolve($this->orga('a', null)));
    }

    public function testNullWithLlmDeclaredButNoDefaultGivesLlm(): void
    {
        $sut = $this->resolver($this->provider([AiModes::CLASSIC, AiModes::LLM], null));

        self::assertSame(AiModes::LLM, $sut->resolve($this->orga('a', null)));
    }

    public function testNullWithOnlyClassicDeclaredGivesNone(): void
    {
        $sut = $this->resolver($this->provider([AiModes::CLASSIC], null));

        self::assertSame(AiModes::NONE, $sut->resolve($this->orga('a', null)));
    }

    public function testNullWithoutProvidersGivesNone(): void
    {
        self::assertSame(AiModes::NONE, $this->resolver()->resolve($this->orga('a', null)));
    }

    public function testDisallowedStoredModeDegrades(): void
    {
        $sut = $this->resolver($this->provider([AiModes::CLASSIC], null));

        self::assertSame(AiModes::NONE, $sut->resolve($this->orga('a', AiModes::LLM)));
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
        $provider->method('getAvailableModes')->willReturn([AiModes::LLM]);
        $provider->expects(self::exactly(2))->method('getDefaultMode')->willReturn(AiModes::LLM);
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
