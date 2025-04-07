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
use demosplan\DemosPlanCoreBundle\Logic\JsonApiRequestValidator;
use demosplan\DemosPlanCoreBundle\Service\InputValidationService;
use demosplan\DemosPlanCoreBundle\Validator\ContentSanitizer;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tests\Base\UnitTestCase;

class InputValidationServiceTest extends UnitTestCase
{
    private InputValidationService $sut;
    private JsonApiRequestValidator|MockObject $jsonApiValidator;
    private ContentSanitizer|MockObject $contentSanitizer;
    private ValidatorInterface|MockObject $validator;
    private RequestStack|MockObject $requestStack;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->jsonApiValidator = $this->createMock(JsonApiRequestValidator::class);
        $this->contentSanitizer = $this->createMock(ContentSanitizer::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        
        $this->sut = new InputValidationService(
            $this->jsonApiValidator,
            $this->contentSanitizer,
            $this->validator,
            $this->requestStack
        );
    }

    public function testValidateRequestWithJsonApiRequest(): void
    {
        $request = new Request();
        
        // Configure JsonApiRequestValidator to identify this as a JSON:API request and validate successfully
        $this->jsonApiValidator->method('isApiRequest')->willReturn(true);
        $this->jsonApiValidator->method('validateJsonApiRequest')->willReturn(null);
        
        // Configure content sanitizer to return sanitized values
        $this->contentSanitizer->method('sanitize')->willReturnCallback(function ($value) {
            return $value . '_sanitized';
        });
        
        // Configure request stack to return the current request
        $this->requestStack->method('getCurrentRequest')->willReturn($request);
        
        // Call the method
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
        
        // Configure content sanitizer to sanitize input
        $this->contentSanitizer->method('sanitize')->willReturnCallback(function ($value) {
            if ($value === 'value1') {
                return 'value1_sanitized';
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
        
        // Assert query parameters were sanitized
        self::assertEquals('value1_sanitized', $request->query->get('param1'));
        self::assertEquals('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $request->query->get('param2'));
    }
    
    public function testValidateJsonContent(): void
    {
        $jsonContent = '{"key": "value", "script": "<script>alert(1)</script>"}';
        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], $jsonContent);
        $request->headers->set('Content-Type', 'application/json');
        
        // Configure JsonApiRequestValidator for a non-JSON:API request
        $this->jsonApiValidator->method('isApiRequest')->willReturn(false);
        
        // Configure content sanitizer to sanitize JSON data
        $this->contentSanitizer->method('sanitize')->willReturnCallback(function ($value) {
            if ($value === 'value') {
                return 'value_sanitized';
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
        
        // Assert JSON content was sanitized and stored
        $sanitizedJson = $request->attributes->get('sanitized_json');
        self::assertIsArray($sanitizedJson);
        self::assertEquals('value_sanitized', $sanitizedJson['key']);
        self::assertEquals('&lt;script&gt;alert(1)&lt;/script&gt;', $sanitizedJson['script']);
    }
    
    public function testValidateInvalidJsonContent(): void
    {
        $invalidJson = '{invalid: json}';
        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], $invalidJson);
        $request->headers->set('Content-Type', 'application/json');
        
        // Configure JsonApiRequestValidator for a non-JSON:API request
        $this->jsonApiValidator->method('isApiRequest')->willReturn(false);
        
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage('Invalid JSON format');
        
        // Call the method
        $this->sut->validateRequest($request);
    }
}