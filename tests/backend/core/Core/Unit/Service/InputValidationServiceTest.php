<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Service;

use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Exception\NullByteDetectedException;
use demosplan\DemosPlanCoreBundle\Logic\JsonApiRequestValidator;
use demosplan\DemosPlanCoreBundle\Service\InputValidationService;
use demosplan\DemosPlanCoreBundle\Validator\InputValidator;
use JsonException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class InputValidationServiceTest extends TestCase
{
    /**
     * @var InputValidationService
     */
    protected $sut;
    private ?JsonApiRequestValidator $jsonApiValidator;
    private ?InputValidator $inputValidator;
    private ?RequestStack $requestStack;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jsonApiValidator = $this->createMock(JsonApiRequestValidator::class);
        $this->inputValidator = $this->createMock(InputValidator::class);
        $this->requestStack = $this->createMock(RequestStack::class);

        $this->sut = new InputValidationService(
            $this->jsonApiValidator,
            $this->inputValidator,
            $this->requestStack
        );
    }

    public function testValidateRequestWithJsonApiRequest(): void
    {
        $request = new Request();

        // Configure JsonApiRequestValidator to identify this as a JSON:API request and validate successfully
        $this->jsonApiValidator->method('isApiRequest')->willReturn(true);
        $this->jsonApiValidator->method('validateJsonApiRequest')->willReturn(null);

        // Configure input validator to return processed values
        $this->inputValidator->method('validateAndEscape')->willReturnCallback(function ($value) {
            return $value . '_processed';
        });

        // Configure request stack to return the current request
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        // Call the method - should not throw an exception
        $this->sut->validateRequest($request);

        // Assert that the request was marked as validated
        self::assertTrue($request->attributes->get('validated'));
    }

    public function testValidateRequestWithInvalidJsonApiRequest(): void
    {
        $request = new Request();

        // Configure JsonApiRequestValidator to identify this as a JSON:API request but validation fails
        $this->jsonApiValidator->method('isApiRequest')->willReturn(true);
        $this->jsonApiValidator->method('validateJsonApiRequest')->willReturn(new Response('', Response::HTTP_UNSUPPORTED_MEDIA_TYPE));

        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage('Invalid JSON:API request');

        // Call the method
        $this->sut->validateRequest($request);
    }

    public function testValidateQueryParameters(): void
    {
        $request = new Request(['param1' => 'value1', 'param2' => '<script>alert("xss")</script>']);

        // Configure JsonApiRequestValidator for a non-JSON:API request
        $this->jsonApiValidator->method('isApiRequest')->willReturn(false);

        // Configure input validator to validate and escape input
        $this->inputValidator->method('validateAndEscape')->willReturnCallback(function ($value) {
            if ($value === 'value1') {
                return 'value1_processed';
            }
            if ($value === '<script>alert("xss")</script>') {
                return '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;';
            }
            return $value;
        });

        // Configure request stack
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        // Call the method
        $this->sut->validateRequest($request);

        // Assert query parameters were validated and escaped
        self::assertEquals('value1_processed', $request->query->get('param1'));
        self::assertEquals('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $request->query->get('param2'));
    }

    public function testValidateJsonContent(): void
    {
        $jsonContent = '{"key": "value", "script": "<script>alert(1)</script>"}';
        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], $jsonContent);
        $request->headers->set('Content-Type', 'application/json');

        // Configure JsonApiRequestValidator for a non-JSON:API request
        $this->jsonApiValidator->method('isApiRequest')->willReturn(false);

        // Configure input validator to validate and escape JSON data
        $this->inputValidator->method('validateAndEscape')->willReturnCallback(function ($value) {
            if ($value === 'value') {
                return 'value_processed';
            }
            if ($value === '<script>alert(1)</script>') {
                return '&lt;script&gt;alert(1)&lt;/script&gt;';
            }
            return $value;
        });

        // Configure request stack
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        // Call the method
        $this->sut->validateRequest($request);

        // Assert JSON content was validated, escaped and stored
        $processedJson = $request->attributes->get('sanitized_json');
        self::assertIsArray($processedJson);
        self::assertEquals('value_processed', $processedJson['key']);
        self::assertEquals('&lt;script&gt;alert(1)&lt;/script&gt;', $processedJson['script']);
    }

    public function testValidateInvalidJsonContent(): void
    {
        $invalidJson = '{invalid: json}';
        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], $invalidJson);
        $request->headers->set('Content-Type', 'application/json');

        // Configure JsonApiRequestValidator for a non-JSON:API request
        $this->jsonApiValidator->method('isApiRequest')->willReturn(false);

        $this->expectException(JsonException::class);

        // Call the method
        $this->sut->validateRequest($request);
    }

    public function testNullByteInQueryParametersRejectsRequest(): void
    {
        // Create request with null byte in query parameter
        $request = new Request(['param' => "malicious\0value"]);

        // Configure JsonApiRequestValidator for a non-JSON:API request
        $this->jsonApiValidator->method('isApiRequest')->willReturn(false);

        // Configure input validator to throw NullByteDetectedException
        $this->inputValidator->method('validateAndEscape')
            ->willThrowException(new NullByteDetectedException('Null byte detected in input string'));

        // Expect InvalidDataException to be thrown with appropriate message
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage('Request rejected: Null byte detected in input');

        // Call the method
        $this->sut->validateRequest($request);
    }

    public function testNullByteInRequestBodyRejectsRequest(): void
    {
        // Create request with JSON body containing null byte
        $jsonContent = '{"key": "malicious\u0000value"}';
        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], $jsonContent);
        $request->headers->set('Content-Type', 'application/json');

        // Configure JsonApiRequestValidator for a non-JSON:API request
        $this->jsonApiValidator->method('isApiRequest')->willReturn(false);

        // Configure input validator to throw NullByteDetectedException when processing the value
        $this->inputValidator->method('validateAndEscape')
            ->willThrowException(new NullByteDetectedException('Null byte detected in input string'));

        // Expect InvalidDataException to be thrown
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage('Request rejected: Null byte detected in input');

        // Call the method
        $this->sut->validateRequest($request);
    }
}
