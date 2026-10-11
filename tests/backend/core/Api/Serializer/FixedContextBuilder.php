<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Api\Serializer;

use ApiPlatform\State\SerializerContextBuilderInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Stands in for the context builder API Platform provides, returning whatever context a test sets.
 */
final class FixedContextBuilder implements SerializerContextBuilderInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(public array $context = [])
    {
    }

    public function createFromRequest(Request $request, bool $normalization, ?array $extractedAttributes = null): array
    {
        return $this->context;
    }
}
