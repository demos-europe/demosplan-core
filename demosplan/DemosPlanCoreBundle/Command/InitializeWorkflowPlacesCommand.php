<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Initialize default workflow places for procedures that don't have any places.
 */
#[AsCommand(name: 'dplan:workflow:init-places', description: 'Add default workflow places to procedures that have none')]
class InitializeWorkflowPlacesCommand extends CoreCommand
{
    /**
     * Default places that will be created (same as in LoadWorkflowPlaceData fixture).
     */
    private const DEFAULT_PLACES = [
        ['name' => 'Erwiderung verfassen', 'sortIndex' => 0, 'solved' => false],
        ['name' => 'Fachtechnische Prüfung', 'sortIndex' => 1, 'solved' => false],
        ['name' => 'Juristische Prüfung', 'sortIndex' => 2, 'solved' => false],
        ['name' => 'Lektorat', 'sortIndex' => 3, 'solved' => false],
        ['name' => 'Abgeschlossen', 'sortIndex' => 4, 'solved' => true],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        ParameterBagInterface $parameterBag,
        ?string $name = null,
    ) {
        parent::__construct($parameterBag, $name);
    }

    public function configure(): void
    {
        $this
            ->addOption(
                'dry-run',
                'd',
                InputOption::VALUE_NONE,
                'Show what would be done without making changes'
            )
            ->addOption(
                'procedure-id',
                'p',
                InputOption::VALUE_OPTIONAL,
                'Only process the procedure with this specific ID'
            )
            ->setHelp(
                <<<EOT
Add default workflow places to procedures that currently have no workflow places.

This command will:
1. Find all procedures that have no workflow places
2. Add the standard 5 workflow places to each procedure:
   - Erwiderung verfassen (Reply)
   - Fachtechnische Prüfung (Technical Review)
   - Juristische Prüfung (Legal Review)
   - Lektorat (Editorial)
   - Abgeschlossen (Completed)

Usage:
    php bin/console dplan:workflow:init-places
    php bin/console dplan:workflow:init-places --dry-run
    php bin/console dplan:workflow:init-places --procedure-id=abc123
EOT
            );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isDryRun = $input->getOption('dry-run');
        $procedureId = $input->getOption('procedure-id');

        $io->title('Initialize Workflow Places');

        if ($isDryRun) {
            $io->note('Running in DRY-RUN mode - no changes will be made');
        }

        try {
            // Find procedures without workflow places
            $proceduresWithoutPlaces = $this->findProceduresWithoutPlaces($procedureId);

            if ([] === $proceduresWithoutPlaces) {
                if ($procedureId) {
                    $io->success("Procedure {$procedureId} already has workflow places or doesn't exist.");
                } else {
                    $io->success('All procedures already have workflow places.');
                }

                return Command::SUCCESS;
            }

            $io->info(sprintf('Found %d procedure(s) without workflow places:', count($proceduresWithoutPlaces)));

            $processedCount = 0;
            $errorCount = 0;

            foreach ($proceduresWithoutPlaces as $procedure) {
                try {
                    $this->processProcedure($procedure, $io, $isDryRun);
                    ++$processedCount;
                } catch (Exception $e) {
                    ++$errorCount;
                    $io->error("Failed to process procedure {$procedure->getId()}: {$e->getMessage()}");
                }
            }

            if ($isDryRun) {
                $io->success("DRY-RUN: Would have processed {$processedCount} procedure(s)");
            } else {
                $io->success("Successfully processed {$processedCount} procedure(s)");
                if ($errorCount > 0) {
                    $io->warning("Failed to process {$errorCount} procedure(s)");
                }
            }

            return $errorCount > 0 ? Command::FAILURE : Command::SUCCESS;
        } catch (Exception $e) {
            $io->error("Command failed: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    /**
     * Find procedures that have no workflow places.
     *
     * @return Procedure[]
     */
    private function findProceduresWithoutPlaces(?string $procedureId = null): array
    {
        $qb = $this->entityManager->createQueryBuilder();

        $qb->select('p')
            ->from(Procedure::class, 'p')
            ->leftJoin('p.segmentPlaces', 'pl')
            ->where('pl.id IS NULL');

        if ($procedureId) {
            $qb->andWhere('p.id = :procedureId')
                ->setParameter('procedureId', $procedureId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Process a single procedure by adding default workflow places.
     */
    private function processProcedure(Procedure $procedure, SymfonyStyle $io, bool $isDryRun): void
    {
        $io->text("Processing procedure: {$procedure->getName()} (ID: {$procedure->getId()})");

        if ($isDryRun) {
            $io->text('  Would create 5 default workflow places');

            return;
        }

        foreach (self::DEFAULT_PLACES as $placeData) {
            $place = new Place(
                $procedure,
                $placeData['name'],
                $placeData['sortIndex']
            );
            $place->setSolved($placeData['solved']);

            // Validate the place
            $violations = $this->validator->validate($place);
            if (count($violations) > 0) {
                throw new Exception("Validation failed for place '{$placeData['name']}': {$violations}");
            }

            $this->entityManager->persist($place);
        }

        $this->entityManager->flush();

        $io->text('  ✓ Created 5 default workflow places');
    }
}
