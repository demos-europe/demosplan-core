<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Command;

use demosplan\DemosPlanCoreBundle\Command\ClassGenerator\EntityClassGeneratorTrait;
use Doctrine\ORM\EntityManagerInterface;
use EDT\DqlQuerying\ClassGeneration\PathClassFromEntityGenerator;
use EDT\Parsing\Utilities\Types\ClassOrInterfaceType;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tightenco\Collect\Support\Collection;

#[AsCommand(
    name: 'demos_plan_addon:entity_class_generator',
    description: 'Generate classes for each existing EntityInterface in DemosEurope\DemosplanAddon\Contracts\Entities.',
    hidden: false,
    aliases: ['dplan:generate_classes']
)]
class EntityClassGeneratorCommand extends Command
{

    use EntityClassGeneratorTrait;

    public function __construct(
        protected readonly PathClassFromEntityGenerator $pathClassGenerator,
        protected readonly EntityManagerInterface $entityManager,
        string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @throws ReflectionException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        //execute in cored ,but stored in contractlayer
        $namespace = "DemosEurope\DemosplanAddon\Contracts\Entities";
        $targetNamespace = "DemosEurope\DemosplanAddon\Contracts\Entities\GeneratedTestClasses";
        $outputDirectory = "vendor/demos-europe/demosplan-addon/src/Contracts/Entities/GeneratedTestClasses";

        $relevantClasses = collect($this->getEntities($this->entityManager));
        $this->generateCoreEntity($targetNamespace, $outputDirectory);
        $this->generateClasses($relevantClasses, $targetNamespace, $outputDirectory);

        return Command::SUCCESS;
    }

    public function pathClassToMethodName(ClassOrInterfaceType $type): string
    {
        return lcfirst(
            substr($type->getShortClassName(), 0, -4)
        );
    }

    /**
     * @throws ReflectionException
     */
    private function generateParameters(\Nette\PhpGenerator\Method $generatedMethod, ReflectionMethod $method): void
    {
        foreach($method->getParameters() as $parameter) {
            $generatedMethod->addParameter($parameter->getName());
            $newGeneratedParameter = $generatedMethod->getParameter($parameter->getName());

            if ($parameter->isDefaultValueAvailable()) {
                $newGeneratedParameter->setDefaultValue($parameter->getDefaultValue());
            }
            $newGeneratedParameter->setNullable($parameter->allowsNull());
            $newGeneratedParameter->setType((string) $parameter->getType());
        }
    }

    private function generateMethods(ClassType $classMock, ReflectionClass $reflectionClass): void
    {
        foreach ($reflectionClass->getMethods() as $method) {

            $generatedMethod = $classMock->addMethod($method->getName());
            $generatedMethod->setVisibility('public');// keep it easy: public for each case
            $generatedMethod->setReturnType((string) $method->getReturnType());
            $this->generateParameters($generatedMethod, $method);

            if ($method->isConstructor() && $method->isUserDefined()) {
                $this->generateConstructorBody($reflectionClass->getProperties(), $generatedMethod);
            }

            if (str_starts_with($method->getName(), 'set') && 3 < strlen($method->getName())) {
                $this->generateSetterBody($method, $generatedMethod);
            }

            if (str_starts_with($method->getName(), 'get') && 3 < strlen($method->getName())) {
                $this->generateGetterBody($method, $generatedMethod);
            }

            if (str_starts_with($method->getName(), 'is') && 2 < strlen($method->getName())) {
                $this->generateIsserBody($method, $generatedMethod);
            }
        }


        //todo: needed?
//        $generatedMethod->setAttributes($method->getAttributes()); //fixme: type of attributes wrong!


        //is method name starts with, get, set, is, has,
        //cut this part und check if we already have this as a property
        //if not, create it.


    }

    /**
     * @throws ReflectionException
     */
    private function generateClasses(
        Collection $relevantClasses,
        string $targetNamespace,
        string $outputDirectory
    ): void {
        foreach ($relevantClasses as $class) {

            $reflectionClass = new ReflectionClass($class);
            $newFile = new PhpFile();

            $newFile->setStrictTypes();
            $namespace = $newFile->addNamespace($targetNamespace);

            $classMock = $namespace->addClass($reflectionClass->getShortName());
            if ('CoreEntity' !== $classMock->getName()) {
//                $namespace->addUse('DemosEurope\DemosplanAddon\Contracts\Entities\GeneratedTestClasses\CoreEntity', 'CoreEntity');
//                $namespace->addUse('demosplan\DemosPlanCoreBundle\Entity\CoreEntity');
                $classMock->setExtends('CoreEntity');
            }

            $this->generateMethods($classMock, $reflectionClass);

            $this->overwriteFile($outputDirectory, $classMock->getName(), (string) $newFile);
        }

    }

    /**
     * @param ReflectionProperty[] $properties
     *
     */
    private function generateConstructorBody(array $properties, \Nette\PhpGenerator\Method $generatedMethod): void
    {
        foreach($properties as $property) {

            $isTyped = null !== $property->getType();
            $propertyName = lcfirst($property->getName());
            $extractedType = null;

            if ($isTyped && !$property->getType()->allowsNull()) {
                $extractedType = $property->getType()->getName();
            } elseif (false !== $property->getDocComment()) {
                $extractedType = self::extractTypeFromDocComment(
                    $property->getDocComment()
                );
            }

            switch ($extractedType) {
                case 'int':
                    $generatedMethod->addBody("\$this->".$propertyName." = 0;");
                    break;
                case 'string':
                    $generatedMethod->addBody("\$this->".$propertyName." = '';");
                    break;
                case 'DateTime':
                    $generatedMethod->addBody("\$this->".$propertyName." = DateTime::createFromFormat('d.m.Y', '2.1.1970');");
                    break;
                case 'array':
                case 'Collection':
                case 'ArrayCollection':
                    $generatedMethod->addBody("\$this->".$propertyName." = new ArrayCollection();");
                    break;
                case 'bool':
                    $generatedMethod->addBody("\$this->".$propertyName." = false;");
                    break;
                default:
            }
        }
    }

    private function generateSetterBody(ReflectionMethod $method, Method $generatedMethod): void
    {
        $propertyName = substr($method->getName(), 3);
        $generatedMethod->addBody("\$this->".lcfirst($propertyName)." = \$".lcfirst($propertyName).";");
    }

    private function generateGetterBody(ReflectionMethod $method, Method $generatedMethod): void
    {
        $propertyName = substr($method->getName(), 3);
        $generatedMethod->addBody("return \$this->".lcfirst($propertyName).";");
    }

    private function generateIsserBody(ReflectionMethod $method, Method $generatedMethod)
    {
        $propertyName = substr($method->getName(), 3);
        $generatedMethod->addBody("return \$this->".lcfirst($propertyName).";");
    }

    /**
     * Try to extract tye of property from docblock.
     */
    private static function extractTypeFromDocComment(string $docComment): ?string
    {
        $parts = explode("\n", $docComment);
        foreach($parts as $part) {
            if (str_contains($part, 'null')) {
                return null;
            }

            if (str_contains($part, '@var ')) {
                $typeAsString = str_replace('@var ', '', trim($part));
                $typeAsString = str_replace('|', '', $typeAsString);
                $typeAsString = str_replace('*', '', $typeAsString);
                $typeAsString = str_replace(' ', '', $typeAsString);
                $typeAsString = str_replace('null', '',$typeAsString);

                if (str_contains($part, 'Collection')
                    || str_contains($part, 'ArrayCollection')
                    || str_contains($part, 'ArrayCollection')) {
                    $typeAsString = 'ArrayCollection';
                }

                    return $typeAsString;
            }
        }
        return null;
    }

    private function generateCoreEntity(string $targetNamespace, string $outputDirectory): void
    {
        $reflectionClass = new ReflectionClass("demosplan\DemosPlanCoreBundle\Entity\CoreEntity");
        $newFile = new PhpFile();

        $newFile->setStrictTypes();
        $namespace = $newFile->addNamespace($targetNamespace);
        $classMock = $namespace->addClass($reflectionClass->getShortName());

        $this->generateMethods($classMock, $reflectionClass);
        $this->overwriteFile($outputDirectory, $classMock->getName(), (string) $newFile);
    }


}
