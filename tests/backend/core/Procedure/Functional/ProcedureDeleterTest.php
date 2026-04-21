<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace backend\core\Procedure\Functional;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\CustomFields\CustomFieldConfigurationFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\FileFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureDeleter;
use demosplan\DemosPlanCoreBundle\Services\Queries\SqlQueriesService;
use League\Flysystem\FilesystemOperator;
use Tests\Base\FunctionalTestCase;
use Zenstruck\Foundry\Persistence\Proxy;

class ProcedureDeleterTest extends FunctionalTestCase
{
    private Procedure|Proxy|null $testProcedure;
    private ?array $testProcedures;

    /** @var ProcedureDeleter */
    protected $sut;

    /** @var SqlQueriesService */
    private $queriesService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(ProcedureDeleter::class);
        $this->queriesService = $this->getContainer()->get(SqlQueriesService::class);
        $this->testProcedure = ProcedureFactory::createOne();
        $this->testProcedures = ProcedureFactory::createMany(2);
    }

    public function testDeleteProcedure(): void
    {
        // Arrange
        $procedureIds = $this->collectTotalProcedureIds();
        static::assertContains($this->testProcedure->getId(), $procedureIds);
        $totalProceduresBefore = $this->countEntries(Procedure::class);

        // Act
        $this->sut->deleteProcedures([$this->testProcedure->getId()], false);

        // Assert
        $expectedProceduresAfter = $totalProceduresBefore - 1;
        static::assertSame($expectedProceduresAfter, $this->countEntries(Procedure::class));
    }

    public function testDeleteProcedures(): void
    {
        // Arrange
        $procedureIds = $this->extractTestProcedureIds($this->testProcedures);
        $allProcedureIds = $this->collectTotalProcedureIds();
        $this->assertProceduresExist($procedureIds, $allProcedureIds);
        $totalProceduresBefore = $this->countEntries(Procedure::class);

        // Act
        $this->sut->deleteProcedures($procedureIds, false);

        // Assert
        $expectedProceduresAfter = $totalProceduresBefore - count($procedureIds);
        static::assertSame($expectedProceduresAfter, $this->countEntries(Procedure::class));
    }

    public function testProcedureDeleteCustomFields(): void
    {
        // Arrange
        $procedureIds = $this->extractTestProcedureIds($this->testProcedures);

        // Verify no custom fields exist initially
        static::assertSame(0, $this->countEntries(CustomFieldConfiguration::class));

        // Create custom fields for procedures
        $customFieldsCount = $this->createCustomFieldsForProcedures();

        // Verify custom fields were created
        static::assertSame($customFieldsCount, $this->countEntries(CustomFieldConfiguration::class));

        // Act
        $this->sut->deleteProcedures($procedureIds, false);

        // Assert
        static::assertSame(0, $this->countEntries(CustomFieldConfiguration::class),
            'All custom fields should be deleted when their procedures are deleted');
    }

    /**
     * Creates custom fields for the test procedures.
     *
     * @return int The total number of custom fields created
     */
    private function createCustomFieldsForProcedures(): int
    {
        $customFieldsCount = 0;

        foreach ($this->testProcedures as $procedure) {
            CustomFieldConfigurationFactory::new()
                ->withRelatedProcedure($procedure->_real())
                ->asRadioButton('Color1')->create();
            CustomFieldConfigurationFactory::new()
                ->withRelatedProcedure($procedure->_real())
                ->asRadioButton('Color2')->create();
            $customFieldsCount += 2;
        }

        return $customFieldsCount;
    }

    public function testDeleteProcedureRemovesFilesFromStorage(): void
    {
        // Arrange
        $defaultStorage = $this->getContainer()->get('default.storage');
        \assert($defaultStorage instanceof FilesystemOperator);

        $procedure = $this->testProcedure->_real();
        $filePath = 'files/test';
        $fileHash = md5(uniqid('', true));
        $flysystemPath = $filePath.'/'.$fileHash;

        $defaultStorage->write($flysystemPath, 'test content');
        static::assertTrue($defaultStorage->fileExists($flysystemPath));

        FileFactory::createOne([
            'hash'      => $fileHash,
            'path'      => $filePath,
            'filename'  => 'test.txt',
            'mimetype'  => 'text/plain',
            'size'      => 12,
            'procedure' => $procedure,
        ]);

        // Act
        $this->sut->deleteProcedures([$procedure->getId()], false);

        // Assert
        static::assertFalse($defaultStorage->fileExists($flysystemPath), 'File should be deleted from storage');
        static::assertSame(0, $this->countEntries(File::class, ['procedure' => $procedure]));
    }

    public function testDeleteProcedureDryRunKeepsFilesInStorage(): void
    {
        // Arrange
        $defaultStorage = $this->getContainer()->get('default.storage');
        \assert($defaultStorage instanceof FilesystemOperator);

        $procedure = $this->testProcedure->_real();
        $filePath = 'files/test';
        $fileHash = md5(uniqid('', true));
        $flysystemPath = $filePath.'/'.$fileHash;

        $defaultStorage->write($flysystemPath, 'test content');

        FileFactory::createOne([
            'hash'      => $fileHash,
            'path'      => $filePath,
            'filename'  => 'test.txt',
            'mimetype'  => 'text/plain',
            'size'      => 12,
            'procedure' => $procedure,
        ]);

        // Act
        $this->sut->deleteProcedures([$procedure->getId()], true);

        // Assert
        static::assertTrue($defaultStorage->fileExists($flysystemPath), 'File should still exist in storage after dry run');
    }

    /**
     * Collects the IDs of all procedures in the database.
     *
     * @return array List of procedure IDs
     */
    private function collectTotalProcedureIds(): array
    {
        $procedureIds = [];
        foreach ($this->getEntries(Procedure::class) as $procedure) {
            $procedureIds[] = $procedure->getId();
        }

        return $procedureIds;
    }

    /**
     * Extracts test procedure IDs from an array of procedures.
     *
     * @param array $procedures Array of procedure objects
     *
     * @return array List of procedure IDs
     */
    private function extractTestProcedureIds(array $procedures): array
    {
        $ids = [];
        foreach ($procedures as $procedure) {
            $ids[] = $procedure->getId();
        }

        return $ids;
    }

    /**
     * Assert that all procedure IDs in the first array exist in the second array.
     */
    private function assertProceduresExist(array $procedureIds, array $allProcedureIds): void
    {
        static::assertCount(count(array_intersect($procedureIds, $allProcedureIds)), $procedureIds);
    }
}
