<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\ClassGenerator;

use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use Doctrine\ORM\EntityManagerInterface;
use EDT\DqlQuerying\ClassGeneration\PathClassFromEntityGenerator;
use EDT\DqlQuerying\ClassGeneration\ResourceConfigBuilderFromEntityGenerator;
use EDT\DqlQuerying\ClassGeneration\TypeHolderGenerator;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\JsonApi\ResourceConfig\Builder\MagicResourceConfigBuilder;
use EDT\Parsing\Utilities\Types\ClassOrInterfaceType;
use EDT\Parsing\Utilities\Types\NonClassOrInterfaceType;
use EDT\PathBuilding\DocblockPropertyByTraitEvaluator;
use Exception;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Traversable;
use Webmozart\Assert\Assert;

use function get_class;

/**
 * FIXME: this command should be automatically executed. Approaches may be using the "cache warmer" or coupling it to a doctrine:diff execution.
 *
 * TODO: this class does not automatically delete generated classes whose corresponding entities do no longer exist.
 */
class EntityCompanionGeneratorCommand extends CoreCommand
{
    use EntityClassGeneratorTrait;

    protected static $defaultName = 'dplan:generator:entity:companion';
    protected static $defaultDescription = 'Generate companion classes for entities.';

    private readonly ClassOrInterfaceType $sortingClass;
    private readonly ClassOrInterfaceType $conditionClass;

    /**
     * @param Traversable<DplanResourceType> $resourceTypes
     */
    public function __construct(
        protected readonly Traversable $resourceTypes,
        protected readonly EntityManagerInterface $entityManager,
        protected readonly DocblockPropertyByTraitEvaluator $traitEvaluator,
        protected readonly PathClassFromEntityGenerator $pathClassGenerator,
        protected readonly TypeHolderGenerator $typeHolderGenerator,
        ParameterBagInterface $parameterBag,
        string $name = null
    ) {
        parent::__construct($parameterBag, $name);

        $this->sortingClass = ClassOrInterfaceType::fromFqcn(OrderBySortMethodInterface::class);
        $this->conditionClass = ClassOrInterfaceType::fromFqcn(
            ClauseFunctionInterface::class,
            [NonClassOrInterfaceType::fromRawString('bool')]
        );
    }

    public function configure(): void
    {
        parent::configure();

        // $this->setHelp('');
        // $this->addArgument('', InputArgument::OPTIONAL, '', '');

        $this->addOption('builderDir', null, InputOption::VALUE_OPTIONAL, 'The output directory to store the generated builder classes in.', 'demosplan/DemosPlanCoreBundle/ResourceConfigBuilder');
        $this->addOption('builderNs', null, InputOption::VALUE_OPTIONAL, 'The output directory to store the generated builder classes in.', 'demosplan\DemosPlanCoreBundle\ResourceConfigBuilder');
        $this->addOption('pathDir', null, InputOption::VALUE_OPTIONAL, 'The output directory to store the generated path classes in.', 'demosplan/DemosPlanCoreBundle/EntityPath');
        $this->addOption('pathNs', null, InputOption::VALUE_OPTIONAL, 'The namespace to use for the generated path classes.', 'demosplan\DemosPlanCoreBundle\EntityPath');
        $this->addOption('pathEntryPointName', null, InputOption::VALUE_OPTIONAL, 'The name of the special entry point utility class.', 'Paths');
        $this->addOption('typeHolderName', null, InputOption::VALUE_OPTIONAL, 'The name of the special class providing all resource types.', 'ResourceTypeStore');
        $this->addOption('typeHolderNs', null, InputOption::VALUE_OPTIONAL, 'The namespace to use for the special class providing all resource types.', 'demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType');
        $this->addOption('typeHolderDir', null, InputOption::VALUE_OPTIONAL, 'The output directory to store the generated special class providing all resource types.', 'demosplan/DemosPlanCoreBundle/Logic/ApiRequest/ResourceType');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $configBuilderOutputDirectory = $input->getOption('builderDir');
            $configBuilderClassNamespace = $input->getOption('builderNs');
            $pathClassOutputDirectory = $input->getOption('pathDir');
            $pathClassNamespace = $input->getOption('pathNs');
            $pathEntryPointClassName = $input->getOption('pathEntryPointName');
            $typeHolderName = $input->getOption('typeHolderName');
            $typeHolderNamespace = $input->getOption('typeHolderNs');
            $typeHolderOutputDir = $input->getOption('typeHolderDir');

            Assert::stringNotEmpty($configBuilderOutputDirectory);
            Assert::stringNotEmpty($configBuilderClassNamespace);
            Assert::stringNotEmpty($pathClassOutputDirectory);
            Assert::stringNotEmpty($pathClassNamespace);
            Assert::stringNotEmpty($pathEntryPointClassName);
            Assert::stringNotEmpty($typeHolderName);
            Assert::stringNotEmpty($typeHolderNamespace);
            Assert::stringNotEmpty($typeHolderOutputDir);

            $pathClasses = [];
            $entityClasses = $this->getEntities($this->entityManager);
            $output->writeln('Found '.count($entityClasses).' Doctrine entity classes.');
            foreach ($entityClasses as $entityClass) {
                // generate config class
                $entityClass = ClassOrInterfaceType::fromFqcn($entityClass);
                $configGenerator = $this->getConfigClassGenerator($entityClass);
                $entityShortName = $entityClass->getShortClassName();
                $configBuilderClassName = "Base{$entityShortName}ResourceConfigBuilder";
                $configBuilderFile = $configGenerator->generateConfigBuilderClass(
                    $entityClass,
                    $configBuilderClassName,
                    $configBuilderClassNamespace
                );
                $this->overwriteFile($configBuilderOutputDirectory, $configBuilderClassName, (string) $configBuilderFile);

                // generate path class
                $pathClassName = "{$entityShortName}Path";
                $pathFile = $this->pathClassGenerator->generatePathClass(
                    new ReflectionClass($entityClass->getFullyQualifiedName()),
                    $pathClassName,
                    $pathClassNamespace
                );
                $this->overwriteFile($pathClassOutputDirectory, $pathClassName, (string) $pathFile);
                $pathClasses[] = ClassOrInterfaceType::fromFqcn("$pathClassNamespace\\$pathClassName");
            }
            $output->writeln('Generated '.count($pathClasses)." config builder classes into `$configBuilderOutputDirectory`.");
            $output->writeln('Generated '.count($pathClasses)." path classes into `$pathClassOutputDirectory`.");

            // generate path entry point
            $entryPointClass = $this->pathClassGenerator->generateEntryPointClass(
                $pathClasses,
                $pathEntryPointClassName,
                $pathClassNamespace,
                [$this, 'pathClassToMethodName']
            );
            $this->overwriteFile($pathClassOutputDirectory, $pathEntryPointClassName, (string) $entryPointClass);
            $output->writeln("Generated path entry point class `$pathEntryPointClassName` into `$pathClassOutputDirectory`.");

            // generate resource type quick access
            $resourceTypes = [];
            foreach ($this->resourceTypes as $resourceType) {
                $resourceTypes[] = ClassOrInterfaceType::fromFqcn(get_class($resourceType));
            }
            $output->writeln('Found '.count($resourceTypes).' resource type classes.');
            $typeHolder = $this->typeHolderGenerator->generateTypeHolder(
                $resourceTypes,
                $typeHolderName,
                $typeHolderNamespace,
            );
            $this->overwriteFile($typeHolderOutputDir, $typeHolderName, (string) $typeHolder);
            $output->writeln("Generated resource type holder class `$typeHolderName` into `$typeHolderOutputDir`.");
        } catch (Exception $exception) {
            $logger = $this->getLoggingOutput($output, true);
            $logger->writeln($exception->getMessage());
            $logger->writeln($exception->getTraceAsString());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Expects the name of the given class to end with `Path`.
     *
     * A class name like `StatementPath` becomes the method name `statement`.
     *
     * @return non-empty-string
     */
    public function pathClassToMethodName(ClassOrInterfaceType $type): string
    {
        return lcfirst(
            substr($type->getShortClassName(), 0, -4)
        );
    }

    protected function getConfigClassGenerator(ClassOrInterfaceType $entityType): ResourceConfigBuilderFromEntityGenerator
    {
        $parentClass = ClassOrInterfaceType::fromFqcn(
            MagicResourceConfigBuilder::class,
            [$this->conditionClass, $this->sortingClass, $entityType]
        );

        return new ResourceConfigBuilderFromEntityGenerator(
            $this->conditionClass,
            $this->sortingClass,
            $parentClass,
            $this->traitEvaluator
        );
    }
}
