<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\JsonApi\Functional;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\FileFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureSettingsFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\FileResourceType;
use Tests\Base\FunctionalTestCase;
use Zenstruck\Foundry\Persistence\Proxy;

class FileResourceTypeTest extends FunctionalTestCase
{
    protected ?FileResourceType $sut = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(FileResourceType::class);
    }

    public function testListIsScopedToCurrentProcedure(): void
    {
        // Arrange
        $procedure = $this->createProcedure();
        $foreignProcedure = $this->createProcedure();
        $procedureFile = $this->createFile($procedure);
        $foreignFile = $this->createFile($foreignProcedure);
        $globalFile = $this->createFile(null);

        $user = UserFactory::createOne(['deleted' => false]);
        $this->logIn($user->_real());
        $this->enablePermissions(['area_admin_assessmenttable']);
        $this->getContainer()->get(CurrentProcedureService::class)->setProcedure($procedure->_real());

        // Act
        $files = $this->sut->getEntities([], []);

        // Assert
        $fileIds = array_map(static fn (File $file): string => $file->getIdent(), $files);
        self::assertContains($procedureFile->getIdent(), $fileIds);
        self::assertContains($globalFile->getIdent(), $fileIds);
        self::assertNotContains($foreignFile->getIdent(), $fileIds);
    }

    public function testListWithoutProcedureContextExcludesProcedureBoundFiles(): void
    {
        // Arrange
        $procedure = $this->createProcedure();
        $procedureFile = $this->createFile($procedure);
        $globalFile = $this->createFile(null);

        $user = UserFactory::createOne(['deleted' => false]);
        $this->logIn($user->_real());
        $this->enablePermissions(['area_admin_assessmenttable']);

        // Act
        $files = $this->sut->getEntities([], []);

        // Assert
        $fileIds = array_map(static fn (File $file): string => $file->getIdent(), $files);
        self::assertContains($globalFile->getIdent(), $fileIds);
        self::assertNotContains($procedureFile->getIdent(), $fileIds);
    }

    private function createProcedure(): Proxy
    {
        $procedure = ProcedureFactory::createOne();
        ProcedureSettingsFactory::createOne(['procedure' => $procedure]);

        return $procedure;
    }

    private function createFile(?Proxy $procedure): Proxy
    {
        $attributes = [
            'hash'     => bin2hex(random_bytes(16)),
            'filename' => 'test.txt',
            'mimetype' => 'text/plain',
        ];
        if (null !== $procedure) {
            $attributes['procedure'] = $procedure->_real();
        }

        return FileFactory::createOne($attributes);
    }
}
