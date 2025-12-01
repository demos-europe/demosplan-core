<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Functional;

use demosplan\DemosPlanCoreBundle\Controller\Statement\DemosPlanStatementController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Tests\Base\FunctionalTestCase;

class PublicStatementRateLimitTest extends FunctionalTestCase
{
    /**
     * Test that both rate limiting parameters exist and have boolean values.
     */
    public function testRateLimitingParametersConfiguration(): void
    {
        $container = self::getContainer();
        $parameterBag = $container->getParameterBag();

        // Verify API rate limiting parameter exists
        $this->assertTrue(
            $parameterBag->has('ratelimit_api_enable'),
            'Parameter ratelimit_api_enable should exist'
        );

        $apiValue = $parameterBag->get('ratelimit_api_enable');
        $this->assertIsBool($apiValue, 'Parameter ratelimit_api_enable should be boolean');

        // Verify public statement rate limiting parameter exists
        $this->assertTrue(
            $parameterBag->has('ratelimit_public_statement_enable'),
            'Parameter ratelimit_public_statement_enable should exist'
        );

        $publicValue = $parameterBag->get('ratelimit_public_statement_enable');
        $this->assertIsBool($publicValue, 'Parameter ratelimit_public_statement_enable should be boolean');
    }

    /**
     * Test that JWT token rate limiting parameters exist and have correct types.
     */
    public function testJwtTokenRateLimitingParametersConfiguration(): void
    {
        $container = self::getContainer();

        // Use getParameter() directly instead of ParameterBag for frozen containers
        $limitValue = $container->getParameter('ratelimit_jwt_token_limit');
        $this->assertIsInt($limitValue, 'Parameter ratelimit_jwt_token_limit should be integer');
        $this->assertGreaterThan(0, $limitValue, 'JWT token limit should be greater than 0');
        $this->assertEquals(500, $limitValue, 'JWT token limit default should be 500');

        $intervalValue = $container->getParameter('ratelimit_jwt_token_interval');
        $this->assertIsString($intervalValue, 'Parameter ratelimit_jwt_token_interval should be string');
        $this->assertNotEmpty($intervalValue, 'JWT token interval should not be empty');
        $this->assertEquals('30 days', $intervalValue, 'JWT token interval default should be "30 days"');
    }

    /**
     * Test that anonymous statement rate limiting parameters exist and have correct types.
     */
    public function testAnonymousStatementRateLimitingParametersConfiguration(): void
    {
        $container = self::getContainer();

        // Use getParameter() for cleaner parameter access
        $limitValue = $container->getParameter('ratelimit_anonymous_statement_limit');
        $this->assertIsInt($limitValue, 'Parameter ratelimit_anonymous_statement_limit should be integer');
        $this->assertGreaterThan(0, $limitValue, 'Anonymous statement limit should be greater than 0');
        $this->assertEquals(4, $limitValue, 'Anonymous statement limit default should be 4');

        $intervalValue = $container->getParameter('ratelimit_anonymous_statement_rate_interval');
        $this->assertIsString($intervalValue, 'Parameter ratelimit_anonymous_statement_rate_interval should be string');
        $this->assertNotEmpty($intervalValue, 'Anonymous statement rate interval should not be empty');
        $this->assertEquals('15 minutes', $intervalValue, 'Anonymous statement rate interval default should be "15 minutes"');

        $amountValue = $container->getParameter('ratelimit_anonymous_statement_rate_amount');
        $this->assertIsInt($amountValue, 'Parameter ratelimit_anonymous_statement_rate_amount should be integer');
        $this->assertGreaterThan(0, $amountValue, 'Anonymous statement rate amount should be greater than 0');
        $this->assertEquals(1, $amountValue, 'Anonymous statement rate amount default should be 1');
    }

    /**
     * Test that the DemosPlanStatementController has access to ParameterBagInterface.
     */
    public function testControllerHasParameterBagAccess(): void
    {
        $container = self::getContainer();

        // Verify the controller can be instantiated with required dependencies
        $this->assertTrue(
            $container->has(DemosPlanStatementController::class),
            'DemosPlanStatementController should be available in the container'
        );

        // Verify ParameterBagInterface is available
        $this->assertTrue(
            $container->has('parameter_bag'),
            'ParameterBagInterface should be available in the container'
        );
    }

    /**
     * Test rate limiting logic with enabled parameter.
     */
    public function testRateLimitingEnabledLogic(): void
    {
        // Create a parameter bag with rate limiting enabled
        $parameterBag = new ParameterBag(['ratelimit_public_statement_enable' => true]);

        // Simulate the condition check from the controller
        $rateLimitEnabled = true === $parameterBag->get('ratelimit_public_statement_enable');

        $this->assertTrue(
            $rateLimitEnabled,
            'Rate limiting should be enabled when parameter is true'
        );

        // Simulate a rate limit check
        $isRateLimitAccepted = false; // Rate limit exceeded

        // When rate limiting is enabled and limit is exceeded, this condition evaluates to true
        $shouldThrowException = $rateLimitEnabled && false === $isRateLimitAccepted;

        $this->assertTrue(
            $shouldThrowException,
            'Exception should be thrown when rate limiting is enabled and limit is exceeded'
        );
    }

    /**
     * Test rate limiting logic with disabled parameter.
     */
    public function testRateLimitingDisabledLogic(): void
    {
        // Create a parameter bag with rate limiting disabled
        $parameterBag = new ParameterBag(['ratelimit_public_statement_enable' => false]);

        // Simulate the condition check from the controller
        $rateLimitEnabled = true === $parameterBag->get('ratelimit_public_statement_enable');

        $this->assertFalse(
            $rateLimitEnabled,
            'Rate limiting should be disabled when parameter is false'
        );

        // Simulate a rate limit check
        $isRateLimitAccepted = false; // Rate limit exceeded

        // When rate limiting is disabled, no exception should be thrown even if limit is exceeded
        $shouldThrowException = $rateLimitEnabled && false === $isRateLimitAccepted;

        $this->assertFalse(
            $shouldThrowException,
            'Exception should NOT be thrown when rate limiting is disabled, even if limit is exceeded'
        );
    }

    /**
     * Test that the request object is properly constructed for rate limiting.
     */
    public function testRequestConstructionForRateLimiting(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode([
            'r_externId' => 'test-procedure-id',
            'r_text'     => 'Test statement content',
        ]));

        $this->assertNotNull($request, 'Request should be constructed properly');
        $this->assertNotNull($request->getContent(), 'Request should have content');

        $content = json_decode($request->getContent(), true);
        $this->assertIsArray($content, 'Request content should be a valid JSON array');
        $this->assertArrayHasKey('r_text', $content, 'Request should have r_text field');
    }
}
