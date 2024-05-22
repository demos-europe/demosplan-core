<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Command;

use demosplan\DemosPlanCoreBundle\Command\ClassGenerator\EntityClassGeneratorTrait;
use EDT\DqlQuerying\ClassGeneration\PathClassFromEntityGenerator;
use EDT\DqlQuerying\ClassGeneration\ResourceConfigBuilderFromEntityGenerator;
use EDT\Parsing\Utilities\TypeResolver;
use EDT\Parsing\Utilities\Types\ClassOrInterfaceType;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Parameter;
use Nette\PhpGenerator\PhpFile;
use phpDocumentor\Reflection\DocBlock\Tags\Method;
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
        string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @throws ReflectionException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        //im core ausfphren, aber in der contracktschicht haben

        //todo: make this a parameter
        $namespace = "DemosEurope\DemosplanAddon\Contracts\Entities";
        $targetNamespace = "DemosEurope\DemosplanAddon\Contracts\Entities\GeneratedTestClasses";
        $outputDirectory = "vendor\demos-europe\demosplan-addon\src\Contracts\Entities\GeneratedTestClasses";

//        TypeResolver::class
//        $entityClass = ClassOrInterfaceType::fromFqcn($namespace);

        $classes = collect(get_declared_classes()); // fixme: does not got all classes!?
//        generator(klasse) (im addon) jeweil der klasse aufrufen so wie TypeHolderGenerator(!)
//        generator im core aufrufen (command)



        $relevantClasses = $classes->filter(function ($fqdn) {
            if ("demosplan\DemosPlanCoreBundle\Entity" !== $fqdn) {
                return str_starts_with($fqdn, "demosplan\\DemosPlanCoreBundle\\Entity\\");
            }
        });

        $this->generateClasses($relevantClasses, $targetNamespace, $outputDirectory);




        // get list of all classes
        // mit stream io oder sowas?
        // for each class create new class, or overwrite it
        //
        /**
         * php documenter oder php docparser
         */

        // classes in subfolders? = muss im entites ordner sein und subfolders inkludieren
        // ich lasse mir den pfad geben (default?) und per default alle subfolder
        // ordner geben lassen (liste kann später)
        // namespace der zu generierenden klassen
        // outputfolder ziel
        // option für lvl of deepness?
        // statt ordner namespace geben lassen!?

        //mit reflection class kann ich mir alles aus der klasese raus ziehen (methoden, properties, namespaces)
        // kann ich davon ausgehen dass alle classes die ich bruache bereits autoloaded sind? -> muss ich, ja
        // gib mir alle typen die du kennst (classes gemischt mit klassen?!)



        // 1. ich will alle classes in einem betsimmten namespace potentiell in nested namespaces
            // villt gibt es dazu shortcuts?! (alle classes aber dann filtern nach namespace)
            // oder alle typen innerhalb eines namespaces?!
            // 1.1 (fallback) zur not alle bekanntne klassen und classes geben lassen. (als fully quallified names)
            // 1.2 start string with string with... bla bla
//        (new \ReflectionClass(fqdn))->isInterface() filter by type
//        voila: liste der classes i want als reflection class instanzes

        // 2. refelction class nutzen um alle methoden namen zu holen

//    use Nette\PhpGenerator\PhpFile;
        //PathClassFromEntityGenerator::generateEntryPointClass
        // andere beispiele:
            // TypeHolderGenerator
            // ResourceConfigBuilderFromEntityGenerator
            // test like testGenerateConfigBuilderClass



    // 1. io/read/instream/ folder with classes
        //namespace: namespace DemosEurope\DemosplanAddon\Contracts\Entities;


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

            if (str_starts_with($method->getName(), 'set')) {
                $this->generateSetterBody($method, $generatedMethod);
            }

            if (str_starts_with($method->getName(), 'get')) {
                $this->generateGetterBody($method, $generatedMethod);
            }

            if (str_starts_with($method->getName(), 'is')) {
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
            //  todo   $namespace->addUse()?;

            $classMock = $namespace->addClass($reflectionClass->getShortName());
            $classMock->setExtends('CoreEntity'); // fixme: fqdn needed
//            $classMock->addImplement($reflectionClass->getName());//fixme needed?
            $namespace->addUse('CoreEntity');// fixme: fqdn needed
//            $namespace->addUse($reflectionClass->getName());


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
            if ($isTyped && !$property->getType()->allowsNull()) {
                $propertyName = $property->getType()->getName();
                switch ($propertyName) {
                    case 'int':
                        $generatedMethod->addBody("\$this->".$propertyName." = 0;");
                    case 'string':
                        $generatedMethod->addBody("\$this->".$propertyName." = '';");
                    case 'DateTime':
                        $generatedMethod->addBody("\$this->".$propertyName." = DateTime::createFromFormat('d.m.Y', '2.1.1970');");
                    case 'array':
                    case 'Collection':
                    $generatedMethod->addBody("\$this->".$propertyName." = new ArrayCollection();");
                    case 'bool':
                        $generatedMethod->addBody("\$this->".$propertyName." = false;");
                }

            }
        }
    }

    private function generateSetterBody(ReflectionMethod $method, \Nette\PhpGenerator\Method $generatedMethod): void
    {
        $propertyName = substr($method->getName(), 3);
        $generatedMethod->addBody("\$this->".$propertyName." = \$$propertyName;");
    }

    private function generateGetterBody(ReflectionMethod $method, \Nette\PhpGenerator\Method $generatedMethod): void
    {
        $propertyName = substr($method->getName(), 3);
        $generatedMethod->addBody("return \$this->".$propertyName.";");
    }

    private function generateIsserBody(ReflectionMethod $method, \Nette\PhpGenerator\Method $generatedMethod)
    {
        $propertyName = substr($method->getName(), 3);
        $generatedMethod->addBody("return \$this->".$propertyName.";");
    }

}
