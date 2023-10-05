<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use demosplan\DemosPlanCoreBundle\Exception\ContentTypeInspectorException;
use Symfony\Component\HttpFoundation\Request;

use function explode;

/**
 * Get more info out of HTTP content-type with media queries.
 *
 * This class can be used to extract more information from the Content-Type
 * header of a Request which may be necessary e.g. when validating if
 * a Request to an API endpoint meets the specification criteria.
 */
final class ContentTypeInspector
{
    /**
     * @var string
     */
    private $contentType;

    /**
     * @var string
     */
    private $canonicalType;

    /**
     * @var array<string,mixed>
     */
    private $parameters;

    public function __construct(Request $request)
    {
        $this->contentType = $this->validate($request->headers->get('Content-Type'));

        $this->parse();
    }

    public function getCanonicalType(): ?string
    {
        return $this->canonicalType;
    }

    public function hasParameters(): bool
    {
        return 0 < count($this->parameters);
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Parses a content type header string.
     *
     * Content-Type headers follow the media type specification
     * (@see https://tools.ietf.org/html/rfc6838). This means,
     * their general format is either
     *
     * ```
     * type/subtype
     * ```
     *
     * or
     *
     * ```
     * type/subtype; parameter-list
     * ```
     *
     * If a `parameter-list` is given, it MUST be a comma-separated
     * string of the format
     *
     * ```
     * parameter=value
     * ```
     */
    private function parse(): void
    {
        $canonical = explode(';', $this->contentType, 2);

        if (false === $canonical || 1 > count($canonical)) {
            throw ContentTypeInspectorException::invalidContentType();
        }

        $this->canonicalType = $canonical[0];
        $this->parameters = [];

        if (2 === count($canonical)) {
            $parameters = \array_map('trim', explode(',', $canonical[1]));
            foreach ($parameters as $parameter) {
                [$name, $value] = explode('=', $parameter, 2);
                $this->parameters[$name] = $value;
            }
        }
    }

    /**
     * Ensure the Request contains a Content-type.
     *
     * Requests with a null or empty string Content-Type
     * cannot be inspected.
     */
    private function validate(?string $contentType): string
    {
        if (null === $contentType) {
            throw ContentTypeInspectorException::emptyContentType();
        }

        if ('' === $contentType) {
            throw ContentTypeInspectorException::emptyContentType();
        }

        return $contentType;
    }
}
