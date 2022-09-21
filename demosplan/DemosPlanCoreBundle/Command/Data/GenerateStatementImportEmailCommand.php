<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Data;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementImportEmail\StatementImportEmail;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Exception;
use Faker\Factory;
use Faker\Generator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class GenerateStatementImportEmailCommand extends DataProviderCommand
{
    protected static $defaultName = 'dplan:data:generate:statement-import-email';

    protected static $defaultDescription = 'Generate a (number of) Statement-Import-Email(s)';

    /**
     * @var Generator
     */
    protected $faker;

    /**
     * @var ObjectManager
     */
    protected $em;

    /**
     * @var \Parsedown
     */
    private $parsedown;
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        ManagerRegistry $registry,
        ParameterBagInterface $parameterBag,
        string $name = null
    ) {
        parent::__construct($parameterBag, $name);

        $this->em = $registry->getManager();
        $this->faker = Factory::create('de_DE');
        $this->parsedown = new \Parsedown();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->addArgument(
            'procedure',
            InputArgument::REQUIRED,
            'The ID of the procedure into which to generate the e-mails.'
        );

        $this->addArgument(
            'senderEmailAddress',
            InputArgument::OPTIONAL,
            'The email address of the sender.',
            'testing@demos-plan.de'
        );

        $this->addArgument(
            'amount',
            InputArgument::OPTIONAL,
            'The amount of e-mails to be generated.',
            1
        );
    }

    protected function handle(): int
    {
        $amount = $this->getArgument('amount');

        $procedureId = $this->getArgument('procedure');
        $procedureRepository = $this->em->getRepository(Procedure::class);
        $procedure = $procedureRepository->find($procedureId);

        $senderEmailAddress = $this->getArgument('senderEmailAddress');

        try {
            $progressBar = $this->createGeneratorProgressBar($amount);
            $progressBar->setMessage('Generating statement-import e-mails...');

            for ($i = 0; $i < $amount; ++$i) {
                $this->createStatementImportEmailData($senderEmailAddress, $procedure);
                $progressBar->advance();
            }
            $this->entityManager->flush();
            $progressBar->finish();
        } catch (Exception $e) {
            $this->error($e);

            return 2;
        }

        return 0;
    }

    /**
     * Creates and persists a single statementImportEmail with random content
     */
    private function createStatementImportEmailData(string $senderEmailAddress, Procedure $procedure): void
    {
        $subject = $this->faker->sentence();
        $text = $this->faker->text();

        $statementImportEmail = new StatementImportEmail();
        $statementImportEmail->setProcedure($procedure);
        $statementImportEmail->setSubject($subject);
        $statementImportEmail->setHtmlTextContent($this->parsedown->parse($text));
        $statementImportEmail->setPlainTextContent($text);
        $statementImportEmail->setRawEmailText($text);
        $statementImportEmail->setFrom($senderEmailAddress);

        $this->entityManager->persist($statementImportEmail);
    }
}
