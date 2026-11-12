<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Functional;

use demosplan\DemosPlanCoreBundle\Logic\Statement\PointCountySplit\PointCountySplitService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use ReflectionMethod;
use Symfony\Component\Validator\Exception\ValidatorException;
use Tests\Base\FunctionalTestCase;

class StatementHandlerPublicDataTest extends FunctionalTestCase
{
    /** @var StatementHandler|null */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(StatementHandler::class);
        $this->sut->setDisplayNotices(false);
    }

    private function getPublicData(array $overrides = []): array
    {
        $data = array_merge([
            'r_privacy'   => 'on',
            'r_text'      => 'Some text',
            'r_useName'   => '1',
            'r_firstname' => 'Erika',
            'r_lastname'  => 'Mustermann',
            'r_email'     => 'erika@example.com',
        ], $overrides);

        $method = new ReflectionMethod(StatementHandler::class, 'getStatementPublicData');

        return $method->invoke($this->sut, $data, []);
    }

    public function testNameIsTrimmedAndJoined(): void
    {
        $statement = $this->getPublicData(['r_firstname' => '  Erika ', 'r_lastname' => ' Mustermann  ']);

        self::assertSame('Erika Mustermann', $statement['uName']);
    }

    public function testWhitespaceOnlyLastnameIsRejected(): void
    {
        $this->expectException(ValidatorException::class);

        $this->getPublicData(['r_lastname' => ' ']);
    }

    public function testWhitespaceOnlyNameIsAcceptedWhenAnonymous(): void
    {
        $statement = $this->getPublicData(['r_useName' => '0', 'r_firstname' => ' ', 'r_lastname' => ' ']);

        self::assertSame('', $statement['uName']);
    }

    public function testInvalidNameIsIgnoredWhenAnonymous(): void
    {
        $statement = $this->getPublicData(['r_useName' => '0', 'r_firstname' => 'https://spam.example', 'r_lastname' => str_repeat('a', 300)]);

        self::assertSame('', $statement['uName']);
    }

    /**
     * @dataProvider urlLikeNameProvider
     */
    public function testUrlLikeNameIsRejected(string $value): void
    {
        $this->expectException(ValidatorException::class);

        $this->getPublicData(['r_firstname' => $value]);
    }

    public static function urlLikeNameProvider(): array
    {
        return [
            'http'  => ['http://spam.example'],
            'https' => ['https://spam.example'],
            'www'   => ['www.spam.example'],
            'mail'  => ['spam@example.com'],
        ];
    }

    public function testTagsAreStrippedFromAddressAndMiscFields(): void
    {
        $statement = $this->getPublicData([
            'r_street'       => '<b>Main</b> Street',
            'r_city'         => '<script>alert(1)</script>Berlin',
            'r_userPosition' => '<i>Boss</i>',
        ]);

        self::assertSame('Main Street', $statement['uStreet']);
        self::assertSame('alert(1)Berlin', $statement['uCity']);
        self::assertSame('Boss', $statement['miscData']['userPosition']);
    }

    public function testTooLongPostalCodeIsRejected(): void
    {
        $this->expectException(ValidatorException::class);

        $this->getPublicData(['r_postalCode' => '1234567']);
    }

    public function testForeignPostalCodeIsAccepted(): void
    {
        $statement = $this->getPublicData(['r_postalCode' => '1002']);

        self::assertSame('1002', $statement['uPostalCode']);
    }

    /**
     * @dataProvider invalidPhoneProvider
     */
    public function testInvalidPhoneIsRejected(string $phone): void
    {
        $this->expectException(ValidatorException::class);

        $this->getPublicData(['r_phone' => $phone]);
    }

    public static function invalidPhoneProvider(): array
    {
        return [
            'letters'  => ['call me maybe'],
            'url'      => ['http://spam.example'],
            'too long' => [str_repeat('1', 51)],
        ];
    }

    public function testValidPhoneIsStored(): void
    {
        $statement = $this->getPublicData(['r_phone' => '+49 (30) 123-45/6']);

        self::assertSame('+49 (30) 123-45/6', $statement['miscData']['userPhone']);
    }

    public function testLinksAreRemovedFromText(): void
    {
        $statement = $this->getPublicData(['r_text' => '<p>See <a href="https://spam.example" onclick="x()">here</a></p>']);

        self::assertSame('<p>See here</p>', $statement['text']);
    }

    private function featureCollection(string $geometryType, array $coordinates): string
    {
        return json_encode([
            'type'     => 'FeatureCollection',
            'features' => [['type' => 'Feature', 'geometry' => ['type' => $geometryType, 'coordinates' => $coordinates], 'properties' => (object) []]],
        ], JSON_THROW_ON_ERROR);
    }

    public function testPointAndCountyGeometryAreJoinedWithPermission(): void
    {
        $this->loginTestUser();
        $this->enablePermissions(['feature_diplankarte_split_point_county']);

        $statement = $this->getPublicData([
            'r_location'                                      => 'point',
            PointCountySplitService::POINT_FIELD              => $this->featureCollection('Point', [1, 2]),
            PointCountySplitService::LOCATION_REFERENCE_FIELD => $this->featureCollection('Polygon', [[[0, 0], [4, 0], [4, 4], [0, 0]]]),
        ]);

        $features = json_decode($statement['polygon'], false, 512, JSON_THROW_ON_ERROR)->features;
        self::assertSame(['point', 'locationReference'], array_map(static fn (object $feature): string => $feature->properties->locationType, $features));
    }

    public function testCountyGeometryIsIgnoredWithoutPermission(): void
    {
        $this->loginTestUser();
        $point = $this->featureCollection('Point', [1, 2]);

        $statement = $this->getPublicData([
            'r_location'                                      => 'point',
            PointCountySplitService::POINT_FIELD              => $point,
            PointCountySplitService::LOCATION_REFERENCE_FIELD => $this->featureCollection('Polygon', [[[0, 0], [4, 0], [4, 4], [0, 0]]]),
        ]);

        self::assertSame($point, $statement['polygon']);
    }
}
