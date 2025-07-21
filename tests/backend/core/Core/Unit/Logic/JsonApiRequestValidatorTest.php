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

use demosplan\DemosPlanCoreBundle\Logic\JsonApiRequestValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\UnitTestCase;

class JsonApiRequestValidatorTest extends UnitTestCase
{
    /**
     * @var JsonApiRequestValidator
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(JsonApiRequestValidator::class);
    }

    /**
     * @dataProvider requestProvider
     */
    public function testContentType(?string $content, ?string $contentType, ?int $responseCode): void
    {
        $request = new Request([], [], [], [], [], [], $content);
        // This request does not come from Symfony and thus does not have the framework.yml config
        $request->setFormat('jsonapi', 'application/vnd.json+api');

        if (\is_string($contentType)) {
            $request->headers->add(['Content-Type' => $contentType]);
        }

        $response = $this->sut->validateJsonApiRequest($request);

        if (is_null($responseCode)) {
            self::assertNull($response);

            return;
        }

        self::assertEquals($responseCode, $response->getStatusCode());
    }

    /**
     * @return array<int,array<int,mixed>>
     */
    public function requestProvider(): array
    {
        return [
            [null, 'text/plain', Response::HTTP_UNSUPPORTED_MEDIA_TYPE],
            [
                null,
                'application/vnd.json+api;extensions=drupal_filters',
                Response::HTTP_UNSUPPORTED_MEDIA_TYPE,
            ],
            [null, 'application/vnd.json+api', null],
            [null, '', null],
            [null, null, null],
        ];
    }
}
