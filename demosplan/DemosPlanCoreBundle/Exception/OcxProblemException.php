<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

use JsonException;
use Symfony\Component\HttpFoundation\Response;

/**
 * An OCX request that is answered with `application/problem+json`. Also builds that answer for
 * failures outside the intake controller (authentication, routing).
 */
class OcxProblemException extends DemosException
{
    /**
     * @param array<string, mixed> $members additional OCX problem members, e.g. `missingParts`
     */
    public function __construct(
        private readonly int $status,
        private readonly ?string $detail = null,
        private readonly array $members = [],
    ) {
        parent::__construct($detail ?? $this->getTitle());
    }

    public static function notFound(): self
    {
        return new self(Response::HTTP_NOT_FOUND);
    }

    public static function unauthorized(string $detail): self
    {
        return new self(Response::HTTP_UNAUTHORIZED, $detail);
    }

    public static function conflict(string $detail): self
    {
        return new self(Response::HTTP_CONFLICT, $detail);
    }

    public static function tooLarge(int $limit): self
    {
        return new self(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, 'Body exceeds '.$limit.' bytes.');
    }

    public static function tooManyEntries(int $limit): self
    {
        return new self(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, 'A part may contain at most '.$limit.' entries.');
    }

    /**
     * @param array<string, mixed> $members
     */
    public static function unprocessable(string $detail, array $members = []): self
    {
        return new self(Response::HTTP_UNPROCESSABLE_ENTITY, $detail, $members);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function toProblem(): array
    {
        $problem = ['type' => 'about:blank', 'title' => $this->getTitle(), 'status' => $this->status];
        if (null !== $this->detail) {
            $problem['detail'] = $this->detail;
        }

        return [...$problem, ...$this->members];
    }

    /**
     * A plain response on purpose: {@see \demosplan\DemosPlanCoreBundle\EventListener\DemosPlanResponseEventSubscriber}
     * adds `meta.messages` to every JsonResponse, which the OCX schemas do not allow.
     *
     * @throws JsonException
     */
    public function toResponse(): Response
    {
        return new Response(
            json_encode($this->toProblem(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $this->status,
            ['Content-Type' => 'application/problem+json']
        );
    }

    private function getTitle(): string
    {
        return Response::$statusTexts[$this->status] ?? 'Error';
    }
}
