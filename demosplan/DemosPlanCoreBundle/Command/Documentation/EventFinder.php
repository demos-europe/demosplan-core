<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Documentation;

use DemosEurope\DemosplanAddon\Exception\JsonException;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\ValueObject\EventMatch;
use demosplan\DemosPlanCoreBundle\ValueObject\UnnamedEventMatch;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Util\Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EventFinder extends CoreCommand
{
    protected static $defaultName = 'dplan:documentation:generate:event-list';
    protected static $defaultDescription = '';

    private const OPTION_START_PATHS = 'startPaths';
    private const OPTION_PARENTS = 'parents';

    private array $unnamedEventMatches = [];
    private array $namedEventMatches = [];

    /**
     * The list of events, created by this command may be incomplete and/or contains false-positives,
     * caused by limited options to identify relevant events as such, while event classes are not loaded.
     * To do so, the name of the class will be used too, by looking for the term "event" as part of the name.
     */
    protected function configure(): void
    {
        $this->addOption(
            self::OPTION_PARENTS,
            'p',
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'The parent class name(s) which will be used as filter to determine the correct event-classes.'
        );

        // -s path/to/dir/one -s path/to/dir/two
        $this->addOption(
            self::OPTION_START_PATHS,
            's',
            InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            'Start path(s), where to search for event classes.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $startPaths = $input->getOption(self::OPTION_START_PATHS);
            $targetParentClassNames = $input->getOption(self::OPTION_PARENTS);
            // add root-path of demosplan will be searched anyway
            $startPaths[] = DemosPlanPath::getRootPath('demosplan');

            $phpFilePaths = self::findPhpClassFilesInDirectories($startPaths);
            $this->collectEventMatches($phpFilePaths, $targetParentClassNames);
            $this->findUsagesOfEvents($phpFilePaths, $this->namedEventMatches);

            // Convert content to array to allow encode to json
            $this->namedEventMatches = array_map(
                static fn (EventMatch $eventMatch): array => $eventMatch->toArray(),
                $this->namedEventMatches
            );

            // Convert content to array to allow encode to json
            $this->unnamedEventMatches = array_map(
                static fn (array $nestedEventMatches): array => array_map(
                    static fn (UnnamedEventMatch $eventMatch): array => $eventMatch->toArray(),
                    $nestedEventMatches
                ),
                $this->unnamedEventMatches
            );

            $output->writeln(
                Json::encode([
                    'named Events'   => $this->namedEventMatches,
                    'unnamed Events' => $this->unnamedEventMatches,
                ],
                    \JSON_PRETTY_PRINT)
            );
        } catch (JsonException) {
            $output->writeln('{"error": "Event export failed."}');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Collects all php files within the given directory, recursively.
     *
     * @return list<string>
     */
    private static function findPhpClassFilesInDirectory(string $directory): array
    {
        $classNames = [];
        if ($openedDir = opendir($directory)) {
            while (is_string($fileName = readdir($openedDir))) {
                if ('.' !== $fileName && '..' !== $fileName) {
                    $fullPath = $directory.DIRECTORY_SEPARATOR.$fileName;

                    if (is_dir($fullPath)) {
                        $classNames[] = self::findPhpClassFilesInDirectory($fullPath);
                    } elseif (str_ends_with($fileName, '.php')) {
                        $classNames[] = [$fullPath];
                    }
                }
            }

            closedir($openedDir);
        }

        return array_merge([], ...$classNames);
    }

    /**
     * Identifies event-classes and fills them into class attributes namedEventMatches and unnamedEventMatches.
     *
     * @param list<non-empty-string> $phpFilePaths
     * @param list<non-empty-string> $targetParentClassNames
     */
    private function collectEventMatches(array $phpFilePaths, array $targetParentClassNames): void
    {
        $nodeFinder = new NodeFinder();
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $this->namedEventMatches = [];
        $this->unnamedEventMatches = [];

        foreach ($phpFilePaths as $classFilePath) {
            // uses local file, no need for flysystem
            $code = file_get_contents($classFilePath);

            try {
                $abstractSyntaxTree = $parser->parse($code);

                /** @var Class_[] $classes */
                $classes = $nodeFinder->findInstanceOf($abstractSyntaxTree, Class_::class);

                /** @var Namespace_[] $namespaces */
                $namespaces = $nodeFinder->findInstanceOf($abstractSyntaxTree, Namespace_::class);

                // Skip files without exactly one namespace, but log it
                if (1 !== count($namespaces)) {
                    echo 'Skipping file with '.count($namespaces).' namespaces: '.$classFilePath."\n";
                    continue;
                }

                $namespaceName = $namespaces[0]->name->toString();

                $this->findNamedEventMatches($classes, $classFilePath, $targetParentClassNames, $namespaceName);
                $this->findUnnamedEventMatches($classes, $classFilePath, $targetParentClassNames, $namespaceName);
            } catch (Exception $e) {
                echo "Parse Error: {$e->getMessage()}\n";
            }
        }
    }

    /**
     * Returns true if given stringToCompare ends with 'EventInterface' or 'Event',
     * otherwise false.
     *
     * @param non-empty-string $stringToCompare
     *
     * @return true if the given stringToCompare ending with 'EventInterface' or 'Event', otherwise false
     */
    private static function isEventLikeName(string $stringToCompare): bool
    {
        return str_ends_with($stringToCompare, 'EventInterface')
            || str_ends_with($stringToCompare, 'Event');
    }

    /**
     * Searches for usage of names events in specific paths and add the found usages to the existing EventMatches.
     *
     * @param array<int, string>        $phpFilePaths
     * @param array<string, EventMatch> $eventMatches
     */
    private function findUsagesOfEvents(array $phpFilePaths, array $eventMatches): void
    {
        foreach ($phpFilePaths as $filePath) {
            // uses local file, no need for flysystem
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

    /**
     * Extracts parent class name if it matches one of the given parents, otherwise null will be returned.
     *
     * @param list<non-empty-string> $targetParentClassNames
     *
     * @return non-empty-string|null
     */
    private function extractMatchingParentClassName(Class_ $class, array $targetParentClassNames): ?string
    {
        $extends = $class->extends?->getParts() ?? [];
        $intersect = array_intersect($extends, $targetParentClassNames);

        return $intersect[0] ?? null;
    }

    /**
     * Detects all php files within the given directories, recursively.
     *
     * @return list<string>
     */
    private static function findPhpClassFilesInDirectories(array $startPaths): array
    {
        $phpFilePaths = [];

        foreach ($startPaths as $directory) {
            if (!is_dir($directory)) {
                throw new InvalidArgumentException('Invalid directory given. '.$directory);
            }

            $phpFilePaths[] = self::findPhpClassFilesInDirectory($directory);
        }

        return array_merge([], ...$phpFilePaths);
    }

    /**
     * Identifies anonymous event-classes and fills them into class attribute $unnamedEventMatches.
     * Matches will be identified by having
     * the given parent classes in $parentClasses
     * or
     * classnames ending with 'Event' or 'EventInterface'.
     *
     * @param list<Class_>           $classes
     * @param non-empty-string       $classFilePath
     * @param list<non-empty-string> $targetParentClassNames
     * @param non-empty-string       $namespace
     */
    private function findUnnamedEventMatches(
        array $classes,
        string $classFilePath,
        array $targetParentClassNames,
        string $namespace,
    ): void {
        $prettifiedFilePath = str_replace(DIRECTORY_SEPARATOR, '\\', $classFilePath);

        // Get only classes without names:
        $anonymousClasses = array_filter($classes, static fn (Class_ $class): bool => null === ($class->name?->name ?? null));

        // collect anonymous classes which are inherit given parent(s)
        if ([] !== $anonymousClasses) {
            foreach ($anonymousClasses as $class) {
                $matchingParent = $this->extractMatchingParentClassName($class, $targetParentClassNames);
                $className = self::getClassName($classFilePath);

                if (null !== $matchingParent || self::isEventLikeName($className)) {
                    $this->unnamedEventMatches[$prettifiedFilePath][] = new UnnamedEventMatch(
                        $prettifiedFilePath,
                        $namespace,
                        $matchingParent
                    );
                }
            }
        }
    }

    /**
     * Identifies event-classes and fills them into class attribute $namedEventMatches.
     * Matches will be identified by having
     * the given parent classes in $parentClasses
     * or
     * classnames ending with 'Event' or 'EventInterface'.
     *
     * @param list<Class_>           $classes
     * @param non-empty-string       $classFilePath
     * @param list<non-empty-string> $targetParentClassNames
     * @param non-empty-string       $namespace
     */
    private function findNamedEventMatches(
        array $classes,
        string $classFilePath,
        array $targetParentClassNames,
        string $namespace,
    ): void {
        $prettifiedFilePath = str_replace(DIRECTORY_SEPARATOR, '\\', $classFilePath);

        // Get only classes with names:
        $namedClasses = array_filter($classes, static fn (Class_ $class): bool => null !== ($class->name?->name ?? null));

        // Skip files with more than one named class
        if (count($namedClasses) > 1) {
            echo 'Skipping file with '.count($namedClasses).' named classes: '.$classFilePath."\n";

            return;
        }

        // files without classes are ignored
        if ([] !== $namedClasses) {
            $class = $namedClasses[0];
            $matchingParent = $this->extractMatchingParentClassName($class, $targetParentClassNames);
            $className = self::getClassName($classFilePath);

            // is it identified as event class?
            if (null !== $matchingParent || self::isEventLikeName($className)) {
                $this->namedEventMatches[$prettifiedFilePath] = new EventMatch(
                    $prettifiedFilePath,
                    $namespace,
                    $className,
                    $matchingParent,
                    self::isEventLikeName($className)
                );
            }
        }
    }

    /**
     * Extracts classname from a given filepath.
     *
     * @param non-empty-string $classFilePath
     *
     * @return non-empty-string
     */
    private static function getClassName(string $classFilePath): string
    {
        return substr(basename($classFilePath), 0, -4);
    }
}
