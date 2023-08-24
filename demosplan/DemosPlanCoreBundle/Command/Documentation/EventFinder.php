<?php declare(strict_types=1);


namespace demosplan\DemosPlanCoreBundle\Command\Documentation;


use DemosEurope\DemosplanAddon\Exception\JsonException;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\ValueObject\EventMatch;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Util\Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Assert\Assert;

class EventFinder extends CoreCommand
{
    protected static $defaultName = 'documentation:generate:demos-event-list';
    protected static $defaultDescription = '';

    private const OPTION_START_PATHS = 'startPaths';
    private const OPTION_PARENTS = 'parent';

    /**
     * limitierungen auflisten!
     * false posoitives beispiel: GenerateEvent.php (generiert source code) würde in der ergebnisliste auftauchen obwohle s kein event sein wird
     *
     */
    protected function configure(): void
    {
        $this->addOption(
            self::OPTION_PARENTS,
            'p',
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'todo' //todo
        );

        $this->addOption(
            self::OPTION_START_PATHS,
            's',
            InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            'startpaths' //todo
        );
    }


    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $startPaths = $input->getOption(self::OPTION_START_PATHS); //fixme
        $parents = $input->getOption(self::OPTION_PARENTS);
        $startPaths[] = DemosPlanPath::getRootPath().'demosplan';
        $phpFilePaths = [];

        foreach ($startPaths as $startPath) {
            if (!is_dir($startPath)) {
                throw new InvalidArgumentException('Invalid directory given. '. $startPath);
            }

            $phpFilePaths[] = $this->scanDirectoryForEventClasses($startPath);
        }

        $phpFilePaths = array_merge([], ...$phpFilePaths);
        $eventMatches = $this->getEventClassNames($phpFilePaths, $parents);
        $this->findEventUsagesInAllFiles($phpFilePaths, $eventMatches);

        try {
            $eventMatches2 = array_map(fn (EventMatch $eventMatch) => $eventMatch->toArray(), $eventMatches);
            $output->writeln(Json::encode($eventMatches2, \JSON_PRETTY_PRINT));

        } catch (JsonException) {
            $output->writeln('{"error": "Event export failed."}');

            return (int) Command::FAILURE;
        }

        return (int) Command::SUCCESS;
    }


    private function scanDirectoryForEventClasses(string $dir): array
    {
        $classNames = [];
        if ($openedDir = opendir($dir)) {
            while (is_string($fileName = readdir($openedDir))) {
                if ('.' !== $fileName
                    && '..' !== $fileName
                ) {
                    $fullPath = $dir . "/" . $fileName;

                    if (is_dir($fullPath)) {
                        $classNames[] = $this->scanDirectoryForEventClasses($fullPath);
                    } elseif (str_ends_with($fileName, '.php')) {
                        $classNames[] = [$fullPath];
                    }
                }
            }

            closedir($openedDir);
        }

        //flat the result
        return array_merge([], ...$classNames);
    }

    private function getEventClassNames(array $phpFilePaths, array $parents): array
    {
        $result = [];
        $nodeFinder = new NodeFinder();
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);


        foreach ($phpFilePaths as $filePath) {

            $code = file_get_contents($filePath);

            try {
                $ast = $parser->parse($code);
                $classes = $nodeFinder->findInstanceOf($ast, Class_::class);
                $namespaces = $nodeFinder->findInstanceOf($ast, Namespace_::class);
                Assert::count($namespaces, 1, 'to much namespaces');
                Assert::lessThanEq(count($classes), 1, 'to much calsses');
                /** @var Namespace_ $namespace */
                $namespace = $namespaces[0];

                if (1 === count($classes)) {
                    $class = $classes[0];
                    $extends = $class->extends?->getParts() ?? [];
                    $intersect = array_intersect($extends, $parents);

                    $className = substr(basename($filePath),0 , -4);
                    $matchingName = $this->isProbablyAnEvent($className);
                    $matchingParent = $intersect[0] ?? null;
                    if (null !== $matchingParent || $matchingName) {
                        $eventMatch = new EventMatch(
                            $filePath,
                            $namespace->name->toString(),
                            $className,
                            $matchingParent,
                            $matchingName
                        );
                        $result[$filePath] = $eventMatch;
                    }
                }

            } catch (Exception $e) {
                echo "Parse Error: {$e->getMessage()}\n";
            }

        }
        return $result;

    }

    private function isProbablyAnEvent($stringToCompare): bool
    {
        return str_ends_with($stringToCompare, 'EventInterface')
            || str_ends_with($stringToCompare,'Event');
    }

    /**
     * @param array<int, string>        $phpFilePaths
     * @param array<string, EventMatch> $eventMatches
     */
    private function findEventUsagesInAllFiles(array $phpFilePaths, array $eventMatches): void
    {
        foreach ($phpFilePaths as $filePath) {

            $code = file_get_contents($filePath);
            foreach ($eventMatches as $eventMatch) {
                if ($eventMatch->getFilePath() !== $filePath && preg_match(
                        "/\b{$eventMatch->getClassName()}\b/",
                        $code
                    )) {
                    $eventMatch->addUsage($filePath);
                }
            }

        }
    }

}
