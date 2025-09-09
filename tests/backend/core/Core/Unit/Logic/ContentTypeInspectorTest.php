<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Logic;

use demosplan\DemosPlanCoreBundle\Logic\ContentTypeInspector;
use LogicException;
use Symfony\Component\HttpFoundation\Request;
use Tests\Base\UnitTestCase;

class ContentTypeInspectorTest extends UnitTestCase
{
    public function testThrowsWithoutContentType(): void
    {
        $this->expectException(LogicException::class);

        new ContentTypeInspector(new Request());
    }

    /**
     * @dataProvider canonicalTypeProvider
     */
    public function testReadsCanonicalType(string $contentType, string $canonicalType): void
    {
        $inspector = $this->createInspectorFromContentType($contentType);

        self::assertEquals($canonicalType, $inspector->getCanonicalType());
    }

    /**
     * @return array<int,array<int,mixed>>
     */
    public function canonicalTypeProvider(): array
    {
        return [
            ['text/plain', 'text/plain'],
            ['text/plain; charset=utf-8', 'text/plain'],
        ];
    }

    /**
     * @dataProvider parametersDataProvider
     */
    public function testParameters(string $contentType, array $parameters): void
    {
        $inspector = $this->createInspectorFromContentType($contentType);

        self::assertEquals($parameters, $inspector->getParameters());
    }

    public function parametersDataProvider()
    {
        return [
            ['text/plain', []],
            ['text/html; charset=utf-8', ['charset' => 'utf-8']],
            [
                'application/json; charset=utf-8, ext=geojson',
                ['charset' => 'utf-8', 'ext' => 'geojson'],
            ],
        ];
    }

    private function createInspectorFromContentType(string $contentType): ContentTypeInspector
    {
        $request = new Request();
        $request->headers->add(['Content-Type' => $contentType]);

        return new ContentTypeInspector($request);
    }
}
