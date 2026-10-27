<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Orga;

use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Logic\Ai\AiModeResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'dplan:orga:ai-mode',
    description: 'Shows or sets the AI mode of an orga'
)]
class OrgaAiModeCommand extends CoreCommand
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AiModeResolver $resolver,
        ParameterBagInterface $parameterBag,
        ?string $name = null,
    ) {
        parent::__construct($parameterBag, $name);
    }

    protected function configure(): void
    {
        $this->addArgument('orga', InputArgument::OPTIONAL, 'Orga id, code or (part of) the name; asked for interactively if omitted');
        $this->addArgument('mode', InputArgument::OPTIONAL, 'Mode to store; omit to show the current value');
        $this->addOption('unset', null, InputOption::VALUE_NONE, 'Remove the explicit choice (NULL)');
        $this->addOption('list', null, InputOption::VALUE_NONE, 'List the allowed modes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $allowed = $this->resolver->getAllowedModes();

        if ($input->getOption('list')) {
            $io->listing($allowed);

            return self::SUCCESS;
        }

        $orgaIdentifier = $input->getArgument('orga');
        if (null === $orgaIdentifier) {
            if (!$input->isInteractive()) {
                $io->error('Argument "orga" is required.');

                return self::FAILURE;
            }
            $question = new Question('Orga (id, code or part of the name)');
            $question->setAutocompleterValues($this->getOrgaNames());
            $orgaIdentifier = (string) $io->askQuestion($question);
        }

        $matches = $this->findOrgas($orgaIdentifier);
        if ([] === $matches) {
            $io->error("No orga matching \"$orgaIdentifier\".");

            return self::FAILURE;
        }
        if (count($matches) > 1) {
            if (!$input->isInteractive()) {
                $io->error("\"$orgaIdentifier\" is ambiguous, use the id or code:");
                $io->table(['Id', 'Code', 'Name'], array_map($this->describe(...), $matches));

                return self::FAILURE;
            }
            $choices = [];
            foreach ($matches as $match) {
                $choices[sprintf('%s [%s] %s', $match->getName(), $match->getCode() ?? '-', $match->getId())] = $match;
            }
            $choice = $io->choice('Several orgas match', array_keys($choices));
            $orga = $choices[$choice];
        } else {
            $orga = $matches[0];
        }

        $mode = $input->getArgument('mode');
        if ($input->getOption('unset')) {
            if (null !== $mode) {
                $io->error('Use either a mode or --unset, not both.');

                return self::FAILURE;
            }
            $orga->setAiMode(null);
        } elseif (null !== $mode) {
            if (!in_array($mode, $allowed, true)) {
                $io->error(sprintf('Mode "%s" is not allowed. Allowed: %s', $mode, implode(', ', $allowed)));

                return self::FAILURE;
            }
            $orga->setAiMode($mode);
        }

        if (null !== $mode || $input->getOption('unset')) {
            $this->entityManager->flush();
            $this->resolver->reset();
        }

        $io->definitionList(
            ['Orga' => sprintf('%s (%s)', $orga->getName(), $orga->getId())],
            ['Stored' => $orga->getAiMode() ?? '(none chosen)'],
            ['Resolved' => $this->resolver->resolve($orga)],
        );

        return self::SUCCESS;
    }

    /**
     * Exact id or code first, then a name search.
     *
     * @return list<Orga>
     */
    private function findOrgas(string $identifier): array
    {
        $repository = $this->entityManager->getRepository(Orga::class);
        $exact = $repository->find($identifier) ?? $repository->findOneBy(['code' => $identifier]);
        if ($exact instanceof Orga) {
            return [$exact];
        }

        return $this->entityManager->createQueryBuilder()
            ->select('o')
            ->from(Orga::class, 'o')
            ->where('o.deleted = false')
            ->andWhere('o.name LIKE :name')
            ->setParameter('name', '%'.addcslashes($identifier, '%_').'%')
            ->orderBy('o.name')
            ->setMaxResults(25)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<string>
     */
    private function getOrgaNames(): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('DISTINCT o.name')
            ->from(Orga::class, 'o')
            ->where('o.deleted = false')
            ->andWhere('o.name IS NOT NULL')
            ->orderBy('o.name')
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * @return array{string, string, string}
     */
    private function describe(Orga $orga): array
    {
        return [$orga->getId(), $orga->getCode() ?? '-', (string) $orga->getName()];
    }
}
