<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command;

use demosplan\DemosPlanStatementBundle\Logic\AnnotatedStatementPdf\AnnotatedStatementPdfHandler;
use demosplan\DemosPlanStatementBundle\Logic\AnnotatedStatementPdf\PiBoxRecognitionRequester;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\RouterInterface;

class RestartPdfImportCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:import:restart-job';
    protected static $defaultDescription = 'Restart a pipeline job for an AnnotatedStatementPdf id';

    /**
     * @var AnnotatedStatementPdfHandler
     */
    private $annotatedStatementPdfHandler;

    /**
     * @var PiBoxRecognitionRequester
     */
    private $boxRecognitionRequester;

    /**
     * @var RouterInterface
     */
    private $router;

    public function __construct(
        AnnotatedStatementPdfHandler $annotatedStatementPdfHandler,
        ParameterBagInterface $parameterBag,
        PiBoxRecognitionRequester $boxRecognitionRequester,
        RouterInterface $router,
        string $name = null
    ) {
        parent::__construct($parameterBag, $name);
        $this->annotatedStatementPdfHandler = $annotatedStatementPdfHandler;
        $this->boxRecognitionRequester = $boxRecognitionRequester;
        $this->router = $router;
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'AnnotatedStatementPdf id');
        $this->addOption('host', '', InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->resetHost($input->getOption('host'));

        $annotatedStatementPdfId = $input->getArgument('id');

        try {
            $annotatedStatementPdf = $this->annotatedStatementPdfHandler->findOneById(
                $annotatedStatementPdfId
            );
        } catch (\InvalidArgumentException $e) {
            $output->writeln(
                'Matching entity not found for id '.$annotatedStatementPdfId,
                OutputInterface::VERBOSITY_VERBOSE
            );

            return 1;
        }

        $this->boxRecognitionRequester->request($annotatedStatementPdf);

        return 0;
    }

    protected function resetHost(?string $host): void
    {
        if (is_string($host) && '' !== $host) {
            $parsedHost = parse_url($host);

            $context = $this->router->getContext();

            $context->setHost($parsedHost['host']);
            $context->setScheme($parsedHost['scheme']);
        }
    }
}
