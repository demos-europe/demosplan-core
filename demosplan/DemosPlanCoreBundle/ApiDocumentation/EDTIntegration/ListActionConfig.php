<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\ApiDocumentation\EDTIntegration;

use EDT\JsonApi\ApiDocumentation\ActionConfigInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

/**
 * This class is provided to allow easy access to the previous behavior for `list` actions in the {@link OpenApiDocumentBuilder}.
 */
class ListActionConfig implements ActionConfigInterface
{
    public function __construct(
        protected readonly RouterInterface $router,
        protected readonly TranslatorInterface $translator
    ) {}

    public function getOperationDescription(string $typeName): string
    {
        return $this->translator->trans(
            'method.list.description',
            ['type' => $typeName]
        );
    }

    public function getPathDescription(): string
    {
        return $this->translator->trans('resource.id');
    }

    public function getSelfLink(string $typeName): string
    {
        $link = $this->router->generate(
            'api_resource_list',
            ['resourceType' => $typeName]
        );
        Assert::stringNotEmpty($link);

        return $link;
    }
}
