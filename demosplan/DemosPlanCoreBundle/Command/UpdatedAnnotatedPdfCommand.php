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

use function array_key_exists;
use demosplan\DemosPlanCoreBundle\Entity\Statement\AnnotatedStatementPdf\AnnotatedStatementPdf;
use demosplan\DemosPlanCoreBundle\Utilities\Json;
use demosplan\DemosPlanStatementBundle\Logic\AnnotatedStatementPdf\AnnotatedStatementPdfHandler;
use demosplan\DemosPlanStatementBundle\Logic\AnnotatedStatementPdf\AnnotatedStatementPdfPageToEntityConverter;
use function file_get_contents;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Throwable;

final class UpdatedAnnotatedPdfCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:import:update-pdf';
    protected static $defaultDescription = 'Update AnnotatedStatementPdf entities from pipeline jsons directly';

    /**
     * @var AnnotatedStatementPdfHandler
     */
    private $annotatedStatementPdfHandler;

    /**
     * @var AnnotatedStatementPdfPageToEntityConverter
     */
    private $entityConverter;

    public function __construct(
        AnnotatedStatementPdfHandler $annotatedStatementPdfHandler,
        AnnotatedStatementPdfPageToEntityConverter $entityConverter,
        ParameterBagInterface $parameterBag,
        string $name = null
    ) {
        parent::__construct($parameterBag, $name);

        $this->annotatedStatementPdfHandler = $annotatedStatementPdfHandler;
        $this->entityConverter = $entityConverter;
    }

    protected function configure(): void
    {
        $this->addArgument('json', InputArgument::REQUIRED, 'A json file to update from');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $json = file_get_contents($input->getArgument('json'));
        $jsonArray = Json::decodeToArray($json);

        if (!array_key_exists('data', $jsonArray)
            || !array_key_exists('type', $jsonArray['data'])
            || 'AnnotatedStatementPdf' !== $jsonArray['data']['type']) {
            $output->writeln('Invalid input', OutputInterface::VERBOSITY_VERBOSE);

            return 1;
        }

        $annotatedStatementPdfId = $jsonArray['data']['id'];

        try {
            $annotatedStatementPdf = $this->annotatedStatementPdfHandler->findOneById(
                $annotatedStatementPdfId
            );
        } catch (InvalidArgumentException $e) {
            $output->writeln(
                'Matching entity not found for id '.$annotatedStatementPdfId,
                OutputInterface::VERBOSITY_VERBOSE
            );

            return 1;
        }

        try {
            $annotatedStatementPdf = $this->entityConverter->convert(
                $annotatedStatementPdf,
                $json,
                true
            );

            $annotatedStatementPdf->setStatus(AnnotatedStatementPdf::READY_TO_REVIEW);

            $this->annotatedStatementPdfHandler->updateObjects([$annotatedStatementPdf]);
        } catch (Throwable $e) {
            $output->writeln(
                'Update failed for id'.$annotatedStatementPdfId,
                OutputInterface::VERBOSITY_VERBOSE
            );
        }

        $output->writeln(
            'Update successful for id '.$annotatedStatementPdfId,
            OutputInterface::VERBOSITY_VERBOSE
        );

        return 0;
    }
}
