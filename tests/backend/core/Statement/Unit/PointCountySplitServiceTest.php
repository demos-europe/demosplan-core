<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Unit;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Logic\Statement\PointCountySplit\PointCountySplitService;
use PHPUnit\Framework\TestCase;

class PointCountySplitServiceTest extends TestCase
{
    private PointCountySplitService $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->createSut(true);
    }

    private function createSut(bool $hasPermission): PointCountySplitService
    {
        // The user is only a dependency of the service under test, not the service itself
        $currentUser = $this->createStub(CurrentUserInterface::class);
        $currentUser->method('hasPermission')->willReturn($hasPermission);

        return new PointCountySplitService($currentUser);
    }

    private function featureCollection(string ...$geometryTypes): string
    {
        $features = array_map(static fn (string $type): array => [
            'type'       => 'Feature',
            'geometry'   => ['type' => $type, 'coordinates' => 'Point' === $type ? [1, 2] : [[[0, 0], [4, 0], [4, 4], [0, 0]]]],
            'properties' => (object) [],
        ], $geometryTypes);

        return json_encode(['type' => 'FeatureCollection', 'features' => $features], JSON_THROW_ON_ERROR);
    }

    public function testExtractGeometryReturnsOnlyPointWithoutPermission(): void
    {
        $point = $this->featureCollection('Point');

        $polygon = $this->createSut(false)->extractGeometry([
            PointCountySplitService::POINT_FIELD  => $point,
            PointCountySplitService::COUNTY_FIELD => $this->featureCollection('Polygon'),
        ]);

        self::assertSame($point, $polygon);
    }

    public function testExtractGeometryJoinsPointAndCountyWithPermission(): void
    {
        $polygon = $this->sut->extractGeometry([
            PointCountySplitService::POINT_FIELD  => $this->featureCollection('Point'),
            PointCountySplitService::COUNTY_FIELD => $this->featureCollection('Polygon'),
        ]);

        $features = json_decode($polygon, false, 512, JSON_THROW_ON_ERROR)->features;
        self::assertSame(['point', 'county'], array_map(static fn (object $feature): string => $feature->properties->locationType, $features));
    }

    public function testExtractGeometryKeepsCountyWhenThePointWasRemoved(): void
    {
        $polygon = $this->sut->extractGeometry([
            PointCountySplitService::POINT_FIELD  => '',
            PointCountySplitService::COUNTY_FIELD => $this->featureCollection('Polygon'),
        ]);

        $features = json_decode($polygon, false, 512, JSON_THROW_ON_ERROR)->features;
        self::assertSame(['county'], array_map(static fn (object $feature): string => $feature->properties->locationType, $features));
    }

    public function testExtractGeometryReturnsEmptyStringWithoutFields(): void
    {
        self::assertSame('', $this->sut->extractGeometry([]));
        self::assertSame('', $this->createSut(false)->extractGeometry([]));
    }

    public function testMergeTagsFeaturesAndPutsPointFirst(): void
    {
        $merged = $this->sut->merge($this->featureCollection('Point'), $this->featureCollection('Polygon'));

        $features = json_decode($merged, false, 512, JSON_THROW_ON_ERROR)->features;
        self::assertSame(['point', 'county'], array_map(static fn (object $feature): string => $feature->properties->locationType, $features));
        self::assertSame(['Point', 'Polygon'], array_map(static fn (object $feature): string => $feature->geometry->type, $features));
    }

    public function testMergeReturnsEmptyStringWithoutFeatures(): void
    {
        self::assertSame('', $this->sut->merge('', ''));
    }

    public function testMergeKeepsCountyWhenPointIsEmpty(): void
    {
        $merged = $this->sut->merge('', $this->featureCollection('Polygon'));

        $features = json_decode($merged, false, 512, JSON_THROW_ON_ERROR)->features;
        self::assertCount(1, $features);
        self::assertSame('county', $features[0]->properties->locationType);
    }

    public function testMergeKeepsExistingFeatureProperties(): void
    {
        $point = '{"type":"FeatureCollection","features":[{"type":"Feature","geometry":{"type":"Point","coordinates":[1,2]},"properties":{"name":"A"}}]}';

        $merged = $this->sut->merge($point, '');

        $properties = json_decode($merged, false, 512, JSON_THROW_ON_ERROR)->features[0]->properties;
        self::assertSame('A', $properties->name);
        self::assertSame('point', $properties->locationType);
    }

    public function testMergeDropsFeaturesWithoutGeometry(): void
    {
        $noGeometry = '{"type":"FeatureCollection","features":[{"type":"Feature","geometry":null,"properties":{}}]}';

        self::assertSame('', $this->sut->merge($noGeometry, ''));
    }

    public function testSplitReturnsBothPartsUnderTheFieldNames(): void
    {
        $merged = $this->sut->merge($this->featureCollection('Point'), $this->featureCollection('Polygon'));

        $split = $this->sut->split($merged);

        $point = json_decode($split[PointCountySplitService::POINT_FIELD], false, 512, JSON_THROW_ON_ERROR);
        $county = json_decode($split[PointCountySplitService::COUNTY_FIELD], false, 512, JSON_THROW_ON_ERROR);
        self::assertSame('FeatureCollection', $point->type);
        self::assertSame('Point', $point->features[0]->geometry->type);
        self::assertSame('Polygon', $county->features[0]->geometry->type);
    }

    public function testSplitTreatsUntaggedFeaturesAsPoint(): void
    {
        $split = $this->sut->split($this->featureCollection('Polygon'));

        self::assertNotSame('', $split[PointCountySplitService::POINT_FIELD]);
        self::assertSame('', $split[PointCountySplitService::COUNTY_FIELD]);
    }

    public function testSplitReturnsEmptyPartsForEmptyPolygon(): void
    {
        $split = $this->sut->split('');

        self::assertSame('', $split[PointCountySplitService::POINT_FIELD]);
        self::assertSame('', $split[PointCountySplitService::COUNTY_FIELD]);
    }

    public function testSplitReturnsNonGeoJsonAsPoint(): void
    {
        $split = $this->sut->split('randomLocation');

        self::assertSame('randomLocation', $split[PointCountySplitService::POINT_FIELD]);
        self::assertSame('', $split[PointCountySplitService::COUNTY_FIELD]);
    }
}
