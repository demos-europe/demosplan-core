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
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Tests\Base\UnitTestCase;

class DocumentWriterSelectorTest extends UnitTestCase
{
    protected $sut;
    protected $permissions;
    protected $requestStack;
    protected $request;
    protected $requestAttributes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->permissions = $this->createMock(PermissionsInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->request = $this->createMock(Request::class);
        $this->requestAttributes = $this->createMock(ParameterBag::class);

        $this->request->attributes = $this->requestAttributes;
        $this->requestStack->method('getCurrentRequest')->willReturn($this->request);
        $this->sut = new DocumentWriterSelector($this->permissions, $this->requestStack);
    }

    public function testGetWriterTypeReturnsOdtWhenPermissionEnabled(): void
    {
        // Arrange
        $this->requestAttributes->method('get')
            ->with('export_format')
            ->willReturn(null);
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
        $this->requestAttributes->method('get')
            ->with('export_format')
            ->willReturn(null);
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
        $this->requestAttributes->method('get')
            ->with('export_format')
            ->willReturn(null);
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
        $this->requestAttributes->method('get')
            ->with('export_format')
            ->willReturn(null);
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
        $this->requestAttributes->method('get')
            ->with('export_format')
            ->willReturn(null);
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
        $this->requestAttributes->method('get')
            ->with('export_format')
            ->willReturn(null);
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
        $this->requestAttributes->method('get')
            ->with('export_format')
            ->willReturn(null);
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
        $this->requestAttributes->method('get')
            ->with('export_format')
            ->willReturn(null);
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
            'valign'        => 'center',
            'textDirection' => 'rtl',
            'bgColor'       => 'FFFFFF',
            'borderSize'    => 1,
        ];

        // Act
        $result = $this->sut->getCellStyleForFormat($cellStyle);

        // Assert
        self::assertSame($cellStyle, $result);
        self::assertArrayHasKey('valign', $result);
        self::assertArrayHasKey('bgColor', $result);
    }

    public function testRequestAttributeOverridesPermissionBasedSelection(): void
    {
        // Arrange - permission would return false, but format is set to odt in request attributes
        $this->requestAttributes->method('get')
            ->with('export_format')
            ->willReturn('odt');
        $this->permissions->expects($this->never())
            ->method('hasPermission');

        // Assert
        self::assertSame('ODText', $this->sut->getWriterType());
        self::assertSame('.odt', $this->sut->getFileExtension());
        self::assertTrue($this->sut->isOdtFormat());
    }

    public function testRequestAttributeDocxOverridesPermission(): void
    {
        // Arrange - permission would return true, but format is set to docx in request attributes
        $this->requestAttributes->method('get')
            ->with('export_format')
            ->willReturn('docx');
        $this->permissions->expects($this->never())
            ->method('hasPermission');

        // Assert
        self::assertSame('Word2007', $this->sut->getWriterType());
        self::assertSame('.docx', $this->sut->getFileExtension());
        self::assertFalse($this->sut->isOdtFormat());
    }

    public function testFallsBackToPermissionWhenNoFormatSet(): void
    {
        // Arrange
        $this->requestAttributes->method('get')
            ->with('export_format')
            ->willReturn(null);
        $this->permissions->expects($this->atLeastOnce())
            ->method('hasPermission')
            ->with('feature_export_odt_instead_of_docx')
            ->willReturn(true);

        // Act - no export_format in request attributes, should use permission
        $result = $this->sut->getWriterType();

        // Assert
        self::assertSame('ODText', $result);
    }

    public function testRequestAttributeHandlesNullRequest(): void
    {
        // Arrange
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->requestStack->method('getCurrentRequest')->willReturn(null);
        $this->sut = new DocumentWriterSelector($this->permissions, $this->requestStack);
        $this->permissions->expects($this->once())
            ->method('hasPermission')
            ->with('feature_export_odt_instead_of_docx')
            ->willReturn(false);

        // Act
        $result = $this->sut->getWriterType();

        // Assert
        self::assertSame('Word2007', $result);
    }
}
