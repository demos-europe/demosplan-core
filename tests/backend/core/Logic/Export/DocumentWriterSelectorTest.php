<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Logic\Export;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Logic\Export\DocumentWriterSelector;
use Tests\Base\UnitTestCase;

class DocumentWriterSelectorTest extends UnitTestCase
{
    protected $sut;
    protected $permissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->permissions = $this->createMock(PermissionsInterface::class);
        $this->sut = new DocumentWriterSelector($this->permissions);
    }

    public function testGetWriterTypeReturnsOdtWhenPermissionEnabled(): void
    {
        // Arrange
        $this->permissions->expects($this->once())
            ->method('hasPermission')
            ->with('feature_export_odt_instead_of_docx')
            ->willReturn(true);

        // Act
        $result = $this->sut->getWriterType();

        // Assert
        self::assertSame('ODText', $result);
    }

    public function testGetWriterTypeReturnsWord2007WhenPermissionDisabled(): void
    {
        // Arrange
        $this->permissions->expects($this->once())
            ->method('hasPermission')
            ->with('feature_export_odt_instead_of_docx')
            ->willReturn(false);

        // Act
        $result = $this->sut->getWriterType();

        // Assert
        self::assertSame('Word2007', $result);
    }

    public function testGetFileExtensionReturnsOdtWhenPermissionEnabled(): void
    {
        // Arrange
        $this->permissions->expects($this->once())
            ->method('hasPermission')
            ->with('feature_export_odt_instead_of_docx')
            ->willReturn(true);

        // Act
        $result = $this->sut->getFileExtension();

        // Assert
        self::assertSame('.odt', $result);
    }

    public function testGetFileExtensionReturnsDocxWhenPermissionDisabled(): void
    {
        // Arrange
        $this->permissions->expects($this->once())
            ->method('hasPermission')
            ->with('feature_export_odt_instead_of_docx')
            ->willReturn(false);

        // Act
        $result = $this->sut->getFileExtension();

        // Assert
        self::assertSame('.docx', $result);
    }

    public function testGetContentTypeReturnsOdtMimeTypeWhenPermissionEnabled(): void
    {
        // Arrange
        $this->permissions->expects($this->once())
            ->method('hasPermission')
            ->with('feature_export_odt_instead_of_docx')
            ->willReturn(true);

        // Act
        $result = $this->sut->getContentType();

        // Assert
        self::assertSame('application/vnd.oasis.opendocument.text', $result);
    }

    public function testGetContentTypeReturnsDocxMimeTypeWhenPermissionDisabled(): void
    {
        // Arrange
        $this->permissions->expects($this->once())
            ->method('hasPermission')
            ->with('feature_export_odt_instead_of_docx')
            ->willReturn(false);

        // Act
        $result = $this->sut->getContentType();

        // Assert
        self::assertSame('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $result);
    }

    public function testIsOdtFormatReturnsTrueWhenPermissionEnabled(): void
    {
        // Arrange
        $this->permissions->expects($this->once())
            ->method('hasPermission')
            ->with('feature_export_odt_instead_of_docx')
            ->willReturn(true);

        // Act
        $result = $this->sut->isOdtFormat();

        // Assert
        self::assertTrue($result);
    }

    public function testIsOdtFormatReturnsFalseWhenPermissionDisabled(): void
    {
        // Arrange
        $this->permissions->expects($this->once())
            ->method('hasPermission')
            ->with('feature_export_odt_instead_of_docx')
            ->willReturn(false);

        // Act
        $result = $this->sut->isOdtFormat();

        // Assert
        self::assertFalse($result);
    }

    public function testGetTableStyleForFormatReturnsOriginalStyle(): void
    {
        // Arrange
        $tableStyle = ['borderSize' => 6, 'borderColor' => '000000'];

        // Act
        $result = $this->sut->getTableStyleForFormat($tableStyle);

        // Assert
        self::assertSame($tableStyle, $result, 'Table style should be returned unchanged for ODT compatibility');
    }

    public function testGetCellStyleForFormatReturnsOriginalStyle(): void
    {
        // Arrange
        $cellStyle = ['valign' => 'top', 'textDirection' => 'ltr'];

        // Act
        $result = $this->sut->getCellStyleForFormat($cellStyle);

        // Assert
        self::assertSame($cellStyle, $result, 'Cell style should be returned unchanged for ODT compatibility');
    }

    public function testGetTableStyleForFormatHandlesEmptyArray(): void
    {
        // Arrange
        $tableStyle = [];

        // Act
        $result = $this->sut->getTableStyleForFormat($tableStyle);

        // Assert
        self::assertSame($tableStyle, $result);
    }

    public function testGetCellStyleForFormatHandlesComplexStyle(): void
    {
        // Arrange
        $cellStyle = [
            'valign' => 'center',
            'textDirection' => 'rtl',
            'bgColor' => 'FFFFFF',
            'borderSize' => 1
        ];

        // Act
        $result = $this->sut->getCellStyleForFormat($cellStyle);

        // Assert
        self::assertSame($cellStyle, $result);
        self::assertArrayHasKey('valign', $result);
        self::assertArrayHasKey('bgColor', $result);
    }
}
