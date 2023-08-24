<?php declare(strict_types=1);


namespace demosplan\DemosPlanCoreBundle\Command\Documentation;


use DemosEurope\DemosplanAddon\Exception\JsonException;
use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use http\Exception\InvalidArgumentException;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EventFinder extends CoreCommand
{

    // todo: rename to demos-event-list or something!?
    protected static $defaultName = 'documentation:generate:event-list';
    protected static $defaultDescription = 'Deletes a specific customer within all related data from the DB.';

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('start');
        $startPath = DemosPlanPath::getRootPath().'demosplan';
        $output->writeln('rootdir: ' . $startPath);

        if (!is_dir($startPath)) {
            throw new InvalidArgumentException('Invalid directory given.');
        }

        $phpFileNames = $this->scanDirectoryForEventClasses($startPath, $output);
        $result = $this->filterFilesForEventUsage($phpFileNames, $output);

        try {
            $output->writeln(implode("\n", $result));
        } catch (JsonException) {
            $output->writeln('{"error": "Event export failed."}');

            return (int) Command::FAILURE;
        }
        $output->writeln('end');

        return (int) Command::SUCCESS;
    }


    private function scanDirectoryForEventClasses(string $dir, $output): array
    {
        $classNames = [];
        if ($openedDir = opendir($dir)) {
            while (is_string($fileName = readdir($openedDir))) {
                if ('.' !== $fileName
                    && '..' !== $fileName
                ) {
//                    $output->writeln('filename: '.$fileName);
                    $fullPath = $dir . "/" . $fileName;

                    if (is_dir($fullPath)) {
//                        $output->writeln('directory found');
                        $classNames[] = $this->scanDirectoryForEventClasses($fullPath, $output);
                    } elseif (str_ends_with($fileName, '.php')) {
//                        $output->writeln('filename found!: '.$fullPath);
                        $classNames[] = [$fullPath];
                    }
                }
            }

            closedir($openedDir);
        }

        //flat the result
        return array_merge([], ...$classNames);
    }

    private function filterFilesForEventUsage(array $phpFileNames, OutputInterface $output): array
    {
        $result = [];
        $nodeFinder = new NodeFinder();
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);


        foreach ($phpFileNames as $fileName) {
            $code = file_get_contents($fileName);

            try {
                $ast = $parser->parse($code);


                // traverse AST and find nodes
                $classes = $nodeFinder->findInstanceOf($ast, Class_::class);
//                $uses = $nodeFinder->findInstanceOf($ast, Use_::class);
                foreach ($classes as $class){
//                    if (isset($class->extends)) {
//                        $output->writeln($class->extends->getParts()[0]);
//                    }

                    if (isset($class->extends) && 'DPlanEvent' === $class->extends->getParts()[0]) {
                        $result[] = $class->name;
                    }

                    //todo: vererbung von geerbten fehlt noch
                    //*Event *EventInterface *EventListener

                    //lösungsoptionen:
                    // 1. classen/filname pattern matchen

                    // 2. attribute/annotations manuell ranklatschen und danach suchen
                    // 3. Eine einzige parenteventinterfacedatei (auch manuell) hinzuzufügen

                    // 4. nur implementierungen beachten (interfaces missachten)
                    // 5. vererbungen von dplanEvent suchen und von diesen die interfaces auflisten
                    // *. ggf. kombinationen der einzelnen Ansätze sinnvoll
                    //          bsp.:5/1 wie 5 aber nur interfaces die mit 1. matchen
                }

            } catch (Error $e) {
                echo "Parse Error: {$e->getMessage()}\n";
            }



        }
        return $result;

    }

}
