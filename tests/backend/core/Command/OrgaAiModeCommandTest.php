<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Command;

use DemosEurope\DemosplanAddon\Contracts\Ai\AiModeProviderInterface;
use DemosEurope\DemosplanAddon\Contracts\Ai\AiModes;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use demosplan\DemosPlanCoreBundle\Application\ConsoleApplication;
use demosplan\DemosPlanCoreBundle\Command\Orga\OrgaAiModeCommand;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Logic\Ai\AiModeResolver;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Tests\Base\FunctionalTestCase;
use Zenstruck\Foundry\Persistence\Proxy;

class OrgaAiModeCommandTest extends FunctionalTestCase
{
    protected $sut;
    private ?CommandTester $commandTester = null;
    /** @var Orga|Proxy<Orga>|null */
    private Orga|Proxy|null $orga = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orga = OrgaFactory::createOne([
            'name'    => 'AI mode test orga '.uniqid('', true),
            'code'    => 'ai-mode-test-'.uniqid('', true),
            'deleted' => false,
        ]);

        // The resolver gets a fixed provider so the allowed modes do not depend on installed addons.
        $provider = new class implements AiModeProviderInterface {
            public function getAvailableModes(): array
            {
                return [AiModes::CLASSIC, AiModes::LLM];
            }

            public function getDefaultMode(OrgaInterface $orga): ?string
            {
                return null;
            }
        };

        $this->sut = new OrgaAiModeCommand(
            $this->getEntityManager(),
            new AiModeResolver([$provider], new NullLogger()),
            $this->getContainer()->get(ParameterBagInterface::class),
        );

        $application = new ConsoleApplication(self::$kernel, false);
        $application->add($this->sut);
        $this->commandTester = new CommandTester($this->sut);
    }

    public function testShowsStoredAndResolvedMode(): void
    {
        $exitCode = $this->execute(['orga' => $this->orga->getId()]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('(none chosen)', $output);
        self::assertMatchesRegularExpression('/Resolved\s+llm/', $output);
        self::assertNull($this->reloadedAiMode());
    }

    public function testStoresAllowedMode(): void
    {
        $exitCode = $this->execute(['orga' => $this->orga->getId(), 'mode' => AiModes::CLASSIC]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertMatchesRegularExpression('/Resolved\s+classic/', $this->commandTester->getDisplay());
        self::assertSame(AiModes::CLASSIC, $this->reloadedAiMode());
    }

    public function testRejectsModeThatIsNotAllowed(): void
    {
        $exitCode = $this->execute(['orga' => $this->orga->getId(), 'mode' => 'unknown']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Mode "unknown" is not allowed', $this->commandTester->getDisplay());
        self::assertNull($this->reloadedAiMode());
    }

    public function testUnsetRemovesStoredMode(): void
    {
        $this->storeClassicMode();

        $exitCode = $this->execute(['orga' => $this->orga->getId(), '--unset' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertNull($this->reloadedAiMode());
    }

    public function testRejectsModeTogetherWithUnset(): void
    {
        $this->storeClassicMode();

        $exitCode = $this->execute(['orga' => $this->orga->getId(), 'mode' => AiModes::LLM, '--unset' => true]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame(AiModes::CLASSIC, $this->reloadedAiMode());
    }

    public function testListsAllowedModes(): void
    {
        $exitCode = $this->execute(['--list' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        foreach ([AiModes::NONE, AiModes::CLASSIC, AiModes::LLM] as $mode) {
            self::assertStringContainsString($mode, $output);
        }
    }

    public function testFindsOrgaByCode(): void
    {
        $exitCode = $this->execute(['orga' => $this->orga->getCode(), 'mode' => AiModes::LLM]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame(AiModes::LLM, $this->reloadedAiMode());
    }

    public function testFindsOrgaByUniqueNamePart(): void
    {
        $exitCode = $this->execute(['orga' => substr($this->orga->getName(), 5), 'mode' => AiModes::LLM]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame(AiModes::LLM, $this->reloadedAiMode());
    }

    public function testAmbiguousNameFailsWithoutInteraction(): void
    {
        $sharedName = 'AI mode ambiguous '.uniqid('', true);
        OrgaFactory::createOne(['name' => $sharedName.' A', 'deleted' => false]);
        OrgaFactory::createOne(['name' => $sharedName.' B', 'deleted' => false]);

        $exitCode = $this->execute(['orga' => $sharedName, 'mode' => AiModes::LLM]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('is ambiguous', $this->commandTester->getDisplay());
    }

    public function testUnknownOrgaFails(): void
    {
        $exitCode = $this->execute(['orga' => 'no orga is called like this '.uniqid('', true)]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('No orga matching', $this->commandTester->getDisplay());
    }

    public function testMissingOrgaFailsWithoutInteraction(): void
    {
        $exitCode = $this->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Argument "orga" is required', $this->commandTester->getDisplay());
    }

    /**
     * @param array<string, mixed> $input
     */
    private function execute(array $input): int
    {
        return $this->commandTester->execute($input, ['interactive' => false]);
    }

    private function storeClassicMode(): void
    {
        $this->orga->_real()->setAiMode(AiModes::CLASSIC);
        $this->getEntityManager()->flush();
    }

    private function reloadedAiMode(): ?string
    {
        $this->getEntityManager()->clear();

        return $this->getEntityManager()->find(Orga::class, $this->orga->getId())?->getAiMode();
    }
}
