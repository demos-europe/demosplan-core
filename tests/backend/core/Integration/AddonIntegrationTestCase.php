<?php

declare(strict_types=1);

namespace Tests\Core\Integration;

use demosplan\DemosPlanCoreBundle\Tests\Integration\AddonIntegrationTestInterface;
use Exception;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Tests\Base\FunctionalTestCase;

class AddonIntegrationTestCase extends FunctionalTestCase
{

    /**
     * The current method runs multiple addon integration tests within a single PHPUnit test method. Each addon test needs isolated setup/cleanup cycles, but they're all within one
     * testAddonIntegrations() execution.
 * Test all registered addon integrations
     */
    public function testAddonIntegrations(): void
    {
        $container = self::getContainer();
        $addonTests = $this->getAddonTests();

        $testsRun = 0;
        $testsPassed = 0;
        $testsFailed = 0;

        foreach ($addonTests as $addonTest) {
            $testsRun++;

            echo "Running {$addonTest->getAddonName()} - {$addonTest->getTestName()}\n ";

            try {
                // Setup test data
                $addonTest->setupTestData($container, $this);

                // Run the integration test
                $result = $addonTest->runIntegrationTest($container);

                // Cleanup test data
                $addonTest->cleanupTestData($container);

                if ($result->isSuccess()) {
                    $testsPassed++;

                    $this->debugSuccessTestResult($result);

                    // Assert success
                    static::assertTrue($result->isSuccess(), $result->getMessage());
                } else {
                    $testsFailed++;
                    echo "❌ FAILED: {$result->getMessage()}\n";
                    static::fail($result->getMessage());
                }
            } catch (Exception $e) {
                $testsFailed++;
                echo "💥 EXCEPTION: {$e->getMessage()}\n";

                // Always cleanup on exception
                try {
                    $addonTest->cleanupTestData($container);
                } catch (Exception $cleanupException) {
                    echo "⚠️ CLEANUP ERROR: {$cleanupException->getMessage()}\n";
                }

                throw $e;
            }
        }

        $this->debugTestSummary($testsRun, $testsPassed, $testsFailed);

        // Final assertion
        static::assertEquals(0, $testsFailed, "Some addon integration tests failed");
        static::assertGreaterThan(0, $testsRun, "No addon integration tests were discovered");
    }

    /**
     * Get all addon integration test services using the existing addon registry
     * @return AddonIntegrationTestInterface[]
     */
    private function getAddonTests(): array
    {
        $addonTests = [];

        try {
            $container = self::getContainer();


            // The test needs to find addon directories (addonDev/ and addons/)
            // but doesn't know the absolute project path at runtime.
            // This line gets the canonical project root from Symfony's kernel.
            $projectDir = $container->getParameter('kernel.project_dir');

            // Look for addon directory
            $addonBasePath = $projectDir . '/addons/vendor/demos-europe';

            if (!is_dir($addonBasePath)) {
                return [];
            }


            echo "🔍 Scanning addon path: {$addonBasePath}\n";
            $finder = new Finder();
            $addonDirs = [];
            foreach ($finder->directories()->depth(0)->name('demosplan-addon-*')->in($addonBasePath) as $dir) {
                $addonDirs[] = $dir->getRealPath();
            }



            foreach ($addonDirs as $addonDir) {
                $addonName = basename($addonDir);
                echo "🔍 Found addon: {$addonName} at {$addonDir}\n";

                $testFinder = new Finder();
                $integrationTestFiles = [];
                $testPath = $addonDir . '/tests/Integration';
                if (is_dir($testPath)) {
                    foreach ($testFinder->files()->name('*IntegrationTestService.php')->in($testPath) as $file) {
                        $integrationTestFiles[] = $file->getRealPath();
                    }
                }

                echo "   Found " . count($integrationTestFiles) . " integration test files\n";

                foreach ($integrationTestFiles as $testFile) {
                    $testService = $this->loadAddonIntegrationTest($testFile);
                    if ($testService) {
                        $addonTests[] = $testService;
                    }
                }
            }


        } catch (Exception $e) {
            echo "❌ Error discovering addons: " . $e->getMessage() . "\n";
        }

        echo "✅ Found " . count($addonTests) . " addon integration test services\n";
        return $addonTests;
    }

    /**
     * Load and instantiate an addon integration test service from a file
     *
     * Takes a PHP file path, loads the file, extracts the class name, and creates an instance of the test service.
     * Returns the service object if it implements AddonIntegrationTestInterface, otherwise returns null.
     */
    private function loadAddonIntegrationTest(string $classFile): ?AddonIntegrationTestInterface
    {

        echo "🔍 Loading integration test: {$classFile}\n";

        if (!file_exists($classFile)) {
            echo "❌ Class file does not exist\n";
            return null;
        }

        // Loads the integration test PHP file into memory so PHP can access the test service classes defined inside it.
        require_once $classFile;

        // Extract namespace and class name from file
        $className = $this->extractClassNameFromFile($classFile);

        if (!$className) {
            echo "❌ Could not determine class name from file\n";
            return null;
        }

        if (!class_exists($className)) {
            echo "❌ Class not available after requiring file: {$className}\n";
            return null;
        }

        echo "✅ Class is available: {$className}\n";

        // Check if class is abstract before attempting instantiation
        $reflection = new ReflectionClass($className);
        if ($reflection->isAbstract()) {
            echo "⏭️ Skipping abstract class: {$className}\n";
            return null;
        }

        try {
            //Creates an instance of the integration test service class and returns it only
            // if it properly implements the required AddonIntegrationTestInterface, otherwise return snull.

  $service = new $className();

            if ($service instanceof AddonIntegrationTestInterface) {
                echo "✅ Service implements AddonIntegrationTestInterface\n";
                return $service;
            }
            echo "❌ Service does not implement AddonIntegrationTestInterface\n";
            return null;

        } catch (Exception $e) {
            echo "❌ Could not instantiate service: " . $e->getMessage() . "\n";
            return null;
        }
    }

    /**
     * Extract the fully qualified class name from a PHP file
     * We use PhpParser to parse the PHP code into nodes, anc consequently detect class name
     */
    private function extractClassNameFromFile(string $filePath): ?string
    {
        try {
            $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
            $nodeFinder = new NodeFinder();

            $code = file_get_contents($filePath);
            $ast = $parser->parse($code);

            $namespaces = $nodeFinder->findInstanceOf($ast, Namespace_::class);
            $classes = $nodeFinder->findInstanceOf($ast, Class_::class);

            if (count($classes) === 0) {
                return null;
            }

            //We use [0] to get the first (and usually only) namespace found.
            $namespace = $namespaces[0]->name->toString();

            // Find first non-abstract class with null safety
            foreach ($classes as $class) {
                // Add null check before calling toString()
                if (!$class->isAbstract() && $class->name !== null) {
                    $className = $class->name->toString();
                    if ($className) {  // Extra safety check
                        return $namespace . '\\' . $className;
                    }
                }
            }

            return null;

        } catch (Exception $e) {
            // Add debug info to see what's failing
            echo "❌ PhpParser error in {$filePath}: " . $e->getMessage() . "\n";
            return null;
        }
    }



    private function debugSuccessTestResult($result){
        echo "✅ SUCCESS: {$result->getMessage()}\n";

        // Display result details
        foreach ($result->getDetails() as $key => $value) {
            echo "   {$key}: {$value}\n";
        }
    }

    private function debugTestSummary($testsRun, $testsPassed, $testsFailed) {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "📊 ADDON INTEGRATION TEST SUMMARY\n";
        echo str_repeat("=", 80) . "\n";
        echo "Tests Run: {$testsRun}\n";
        echo "Tests Passed: {$testsPassed}\n";
        echo "Tests Failed: {$testsFailed}\n";
        echo str_repeat("=", 80) . "\n";

    }

    /**
     * Test a specific addon by name (helper method, not a test)
     */
    private function runSpecificAddon(string $addonName): void
    {
        $container = self::getContainer();
        $addonTests = $this->getAddonTests();

        $addonTest = null;
        foreach ($addonTests as $test) {
            if ($test->getAddonName() === $addonName) {
                $addonTest = $test;
                break;
            }
        }
        $this->assertNotNull($addonTest, "Addon test '{$addonName}' not found");

        echo "\n🧪 Running specific addon test: {$addonName}\n";

        $addonTest->setupTestData($container, $this);
        $result = $addonTest->runIntegrationTest($container);
        $addonTest->cleanupTestData($container);

        $this->assertTrue($result->isSuccess(), $result->getMessage());
    }
}
