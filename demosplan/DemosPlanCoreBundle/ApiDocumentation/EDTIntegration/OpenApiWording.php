<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\ApiDocumentation\EDTIntegration;

use EDT\JsonApi\ApiDocumentation\OpenApiWordingInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

/**
 * This class is provided to allow easy access to the previous behavior of the {@link OpenApiDocumentBuilder}.
 */
class OpenApiWording implements OpenApiWordingInterface
{
    public function __construct(
        protected readonly TranslatorInterface $translator
    ) {}

    public function getOpenApiDescription(): string
    {
        return $this->translator->trans('description');
    }

    public function getOpenApiTitle(): string
    {
        $value = $this->translator->trans('title');
        Assert::stringNotEmpty($value);

        return $value;
    }

    public function getIncludeParameterDescription(): string
    {
        return $this->translator->trans('parameter.query.include');
    }

    public function getExcludeParameterDescription(): string
    {
        return $this->translator->trans('parameter.query.exclude');
    }

    public function getPageNumberParameterDescription(): string
    {
        return $this->translator->trans('parameter.query.page_number');
    }

    public function getPageSizeParameterDescription(): string
    {
        return $this->translator->trans('parameter.query.page_size');
    }

    public function getFilterParameterDescription(): string
    {
        return $this->translator->trans('parameter.query.filter');
    }

    public function getTagName(string $typeName): string
    {
        $value = $this->translator->trans(
            'resource.section',
            ['type' => $typeName]
        );
        Assert::stringNotEmpty($value);

        return $value;
    }
}
