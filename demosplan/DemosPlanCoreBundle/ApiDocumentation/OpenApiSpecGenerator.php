<?php

namespace demosplan\DemosPlanCoreBundle\ApiDocumentation;

use cebe\openapi\exceptions\TypeErrorException;
use cebe\openapi\spec\OpenApi;
use demosplan\DemosPlanCoreBundle\ApiDocumentation\EDTIntegration\GetActionConfig;
use demosplan\DemosPlanCoreBundle\ApiDocumentation\EDTIntegration\ListActionConfig;
use demosplan\DemosPlanCoreBundle\ApiDocumentation\EDTIntegration\OpenApiWording;
use demosplan\DemosPlanCoreBundle\Exception\ProcedureNotFoundException;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ConsultationTokenResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\CustomFieldResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\FaqCategoryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\SingleDocumentReportEntryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\SingleDocumentResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\TagTopicResourceType;
use EDT\JsonApi\Manager;
use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;
use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class OpenApiSpecGenerator
{
    /**
     * Some resource types are currently not documentable because their field configuration
     * requires e.g. a current procedure.
     *
     * @var \class-string[]
     */
    private const UNDOCUMENTABLE_RESOURCE_TYPES = [
        ConsultationTokenResourceType::class, // no procedure available
        CustomFieldResourceType::class, // property name has no discernible type
        FaqCategoryResourceType::class, // resource config builder fails
    ];

    public function __construct(
        private readonly Manager             $manager,
        private readonly RouterInterface     $router,
        private readonly TranslatorInterface $translator,
        private readonly RewindableGenerator $resourceTypes,
    )
    {
    }

    /**
     * Build an OpenApi spec with the most permissive permissions configuration possible.
     *
     * A user who is logged in in any role and has all available permissions enabled
     * should result in all (non-procedure) permission checks evaluating true.
     *
     * With this user, all possible ResourceTypes should be included in the spec build.
     *
     * @throws TypeErrorException
     */
    public function generate(): OpenApi
    {
        $types = $this->getTypes();

        $this->manager->setPaginationDefaultPageSize(25);
        $this->manager->registerGetableTypes($types);

        $schemaGenerator = $this->manager->createOpenApiDocumentBuilder();

        $schemaGenerator->setGetActionConfig(new GetActionConfig($this->router, $this->translator));
        $schemaGenerator->setListActionConfig(new ListActionConfig($this->router, $this->translator));

        $openApiSpec = $schemaGenerator->buildDocument(new OpenApiWording($this->translator));

        return $openApiSpec;
    }

    /**
     * @return array
     */
    public function getTypes(): array
    {
        return collect(iterator_to_array($this->resourceTypes))
            ->filter(static function (ResourceTypeInterface $resourceType) {
                return !in_array($resourceType::class, self::UNDOCUMENTABLE_RESOURCE_TYPES);
            })
            ->toArray();
    }
}
