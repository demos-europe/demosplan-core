<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment\RpcBulkEditor;

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Logic\Rpc\RpcMethodSolverInterface;
use DemosEurope\DemosplanAddon\Validator\JsonSchemaValidator;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\JsonApiEsService;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\SearchParams;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcErrorGenerator;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementSegmentResourceType;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Constraint\CustomFieldOptionSelection;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\JsonApi\RequestHandling\JsonApiSortingParser;
use EDT\Querying\ConditionParsers\Drupal\DrupalFilterParser;
use EDT\Querying\Contracts\PathsBasedInterface;
use Exception;
use Psr\Log\LoggerInterface;
use stdClass;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Uuid;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Webmozart\Assert\Assert;

class RpcSegmentIdLoader implements RpcMethodSolverInterface
{
    final public const SEGMENT_ID_LOAD = 'segment.load.id';

    /** Group key prefix of custom field conditions, followed by the field id (customField_<fieldId>), mirrors useApiPlatformFilters.js */
    private const CUSTOM_FIELD_GROUP_PREFIX = 'customField_';

    private const CUSTOM_FIELDS_PERMISSION = 'field_segments_custom_fields';

    public function __construct(
        protected readonly DqlConditionFactory $conditionFactory,
        protected readonly DrupalFilterParser $drupalFilterParser,
        protected readonly JsonSchemaValidator $jsonValidator,
        protected readonly StatementSegmentResourceType $segmentResourceType,
        protected readonly JsonApiSortingParser $sortingParser,
        protected readonly JsonApiEsService $jsonApiEsService,
        protected readonly LoggerInterface $logger,
        protected RpcErrorGenerator $errorGenerator,
        protected readonly PermissionsInterface $permissions,
        protected readonly ValidatorInterface $validator,
    ) {
    }

    public function supports(string $method): bool
    {
        return self::SEGMENT_ID_LOAD === $method;
    }

    public function execute(?ProcedureInterface $procedure, $rpcRequests): array
    {
        $expectedProcedureId = $procedure?->getId();
        Assert::stringNotEmpty($expectedProcedureId);

        $rpcRequests = is_object($rpcRequests)
            ? [$rpcRequests]
            : $rpcRequests;

        $resultResponse = [];
        foreach ($rpcRequests as $rpcRequest) {
            try {
                $params = $rpcRequest->params;

                $conditions = $this->getConditions($params, $expectedProcedureId);
                $sortMethods = isset($params->sort) ? $this->sortingParser->createFromQueryParamValue($params->sort) : [];
                $segmentIdentifiers = $this->segmentResourceType->listEntityIdentifiers($conditions, $sortMethods);
                $searchParams = SearchParams::createOptional(isset($params->search) ? $this->toArray($params->search) : []);
                if ($searchParams instanceof SearchParams) {
                    $elasticsearchResult = $this->jsonApiEsService->getEsFilteredResult(
                        $this->segmentResourceType,
                        $segmentIdentifiers,
                        $searchParams,
                        [] === $sortMethods,
                        null
                    );
                    $esResultArrays = $this->jsonApiEsService->toLegacyResultES($elasticsearchResult);
                    $segmentIdentifiers = array_column($esResultArrays, 'id');
                }

                $resultResponse[] = $this->generateMethodResult($rpcRequest, $segmentIdentifiers);
            } catch (ValidatorException $exception) {
                $this->logger->info('Invalid custom field selection while loading segment IDs', ['exception' => $exception]);
                $resultResponse[] = $this->errorGenerator->invalidParams($rpcRequest);
            } catch (Exception $exception) {
                $this->logger->error('Error while loading segment IDs', ['exception' => $exception]);
                $resultResponse[] = $this->errorGenerator->serverError($rpcRequest);
            }
        }

        return $resultResponse;
    }

    /**
     * @param list<non-empty-string> $segmentIds
     */
    public function generateMethodResult(object $rpcRequest, array $segmentIds): object
    {
        $result = new stdClass();
        $result->jsonrpc = '2.0';
        $result->result = $segmentIds;
        $result->id = $rpcRequest->id;

        return $result;
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function validateRpcRequest(object $rpcRequest): void
    {
        $params = $rpcRequest->params;
        if (property_exists($params, 'sort')) {
            Assert::stringNotEmpty($params->sort);
        }
        // Both arrive as an object for `{...}` and as an array for the empty
        // `[]` a client sends when nothing is set.
        foreach (['filter', 'search'] as $parameterName) {
            if (property_exists($params, $parameterName)) {
                Assert::true(
                    $params->$parameterName instanceof stdClass || is_array($params->$parameterName),
                    sprintf('Expected RPC parameter "%s" to be an object or an array.', $parameterName)
                );
            }
        }
    }

    private function getConditions(stdClass $params, string $procedureId): array
    {
        $drupalFilter = $this->toArray($params->filter ?? null);
        $conditions = [] === $drupalFilter
            ? []
            : $this->drupalFilterParser->parseFilter($this->drupalFilterParser->validateFilter($drupalFilter));

        /*
         * Custom field conditions are only understood by the 3.0 API (CustomFieldFilter); here they
         * arrive as Drupal conditions on the raw JSON column, which an equality match cannot express.
         * Replace them with the same substring semantics the 3.0 filter applies: a segment matches if
         * its customFields JSON contains the quoted option id.
         */
        [$customFieldConditions, $conditions] = $this->extractCustomFieldConditions($drupalFilter, $conditions);
        array_push($conditions, ...$customFieldConditions);

        $conditions[] = $this->conditionFactory->propertyHasValue(
            $procedureId,
            $this->segmentResourceType->procedure->id
        );

        return $conditions;
    }

    /**
     * Builds one substring condition per custom field option and removes the untranslatable
     * equality conditions (including their group wrappers) from the parsed conditions.
     *
     * @param array<non-empty-string, mixed> $drupalFilter
     * @param list<PathsBasedInterface>      $conditions
     *
     * @return array{0: list<PathsBasedInterface>, 1: list<PathsBasedInterface>}
     */
    private function extractCustomFieldConditions(array $drupalFilter, array $conditions): array
    {
        $customFieldFilter = $this->collectCustomFieldOptions($drupalFilter);

        if ([] === $customFieldFilter || !$this->permissions->hasPermission(self::CUSTOM_FIELDS_PERMISSION)) {
            return [[], $conditions];
        }

        $this->assertValidCustomFieldSelection($customFieldFilter);

        $customFieldConditions = [];
        foreach ($customFieldFilter as $optionIds) {
            $anyOptionMatches = array_map(
                fn (string $optionId): PathsBasedInterface => $this->conditionFactory->propertyHasStringContainingCaseInsensitiveValue(
                    '"'.$optionId.'"',
                    $this->segmentResourceType->customFields
                ),
                $optionIds
            );

            // Options of one field are OR-combined, different fields are AND-combined
            $customFieldConditions[] = $this->conditionFactory->anyConditionApplies(...$anyOptionMatches);
        }

        // The parsed equality conditions on customFields never match the stored JSON and their OR groups
        // only ever contained those, so drop them entirely; the substring conditions replace them.
        $conditions = array_values(array_filter(
            $conditions,
            fn (PathsBasedInterface $condition) => !$this->touchesCustomFields($condition)
        ));

        return [$customFieldConditions, $conditions];
    }

    /**
     * The same rules as the `customField` parameter of the 3.0 API: every option is a UUID that belongs to
     * the custom field it is selected for. Anything else would be dropped or never match without an error.
     *
     * @param array<non-empty-string, list<mixed>> $customFieldFilter
     *
     * @throws ValidatorException
     */
    private function assertValidCustomFieldSelection(array $customFieldFilter): void
    {
        $violations = $this->validator->validate($customFieldFilter, new All([
            new All([
                new Sequentially([new Type('string'), new NotBlank(), new Uuid(strict: false)]),
            ]),
        ]));
        if (0 === count($violations)) {
            // Only look the options up once the shape is known to be valid
            $violations = $this->validator->validate($customFieldFilter, new CustomFieldOptionSelection());
        }

        if (0 !== count($violations)) {
            throw new ValidatorException((string) $violations);
        }
    }

    /**
     * Extracts the option ids per custom field from the group conditions the filter panel sends.
     *
     * @param array<non-empty-string, mixed> $drupalFilter
     *
     * @return array<non-empty-string, list<mixed>>
     */
    private function collectCustomFieldOptions(array $drupalFilter): array
    {
        $customFieldFilter = [];
        foreach ($drupalFilter as $filter) {
            $memberOf = $filter['condition']['memberOf'] ?? null;
            if (is_string($memberOf) && str_starts_with($memberOf, self::CUSTOM_FIELD_GROUP_PREFIX)) {
                $fieldId = substr($memberOf, strlen(self::CUSTOM_FIELD_GROUP_PREFIX));
                // Collected as sent: a value of the wrong type has to fail the validation, not vanish with the replaced conditions
                $customFieldFilter[$fieldId][] = $filter['condition']['value'] ?? null;
            }
        }

        return $customFieldFilter;
    }

    /**
     * Whether the condition's property paths include the customFields column.
     */
    private function touchesCustomFields(PathsBasedInterface $condition): bool
    {
        foreach ($condition->getPropertyPaths() as $propertyPath) {
            if (in_array('customFields', $propertyPath->getPath()->getAsNames(), true)) {
                return true;
            }
        }

        return false;
    }

    private function toArray(stdClass|array|null $value): array
    {
        return null === $value ? [] : json_decode(json_encode($value), true);
    }
}
