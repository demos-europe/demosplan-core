<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Base;

use function array_key_exists;
use function data_get;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Exception\ConcurrentEditionException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\PersistResourceException;
use demosplan\DemosPlanCoreBundle\Exception\ResourceNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\DplanPropertyPathProcessorFactory;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\Normalizer;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PrefilledResourceTypeProvider;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PropertyUpdateAccessException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\TopLevel;
use demosplan\DemosPlanCoreBundle\Logic\Logger\ApiLogger;
use demosplan\DemosPlanCoreBundle\Response\APIResponse;
use demosplan\DemosPlanCoreBundle\Response\EmptyResponse;
use demosplan\DemosPlanCoreBundle\Services\ApiResourceService;
use demosplan\DemosPlanCoreBundle\Utilities\Json;
use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;
use demosplan\DemosPlanStatementBundle\Exception\DuplicateInternIdException;
use EDT\JsonApi\OutputTransformation\ExcludeException;
use EDT\JsonApi\RequestHandling\MessageFormatter;
use EDT\JsonApi\RequestHandling\UrlParameter;
use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;
use EDT\JsonApi\Validation\FieldsValidator;
use EDT\Wrapping\Contracts\PropertyAccessException;
use EDT\Wrapping\Contracts\TypeRetrievalAccessException;
use EDT\Wrapping\Utilities\SchemaPathProcessor;
use EDT\Wrapping\Utilities\TypeAccessor;
use InvalidArgumentException;
use function is_array;
use function is_string;
use League\Fractal\Manager;
use League\Fractal\Resource\Item;
use League\Fractal\Resource\ResourceAbstract;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\SessionUnavailableException;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

abstract class APIController extends BaseController
{
    /**
     * @var Manager
     */
    protected $fractal;

    /**
     * @var ApiResourceService
     */
    protected $resourceService;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var TopLevel|null
     */
    protected $requestData;

    /**
     * The json data that was transported with the request.
     *
     * @var array|null
     */
    protected $requestJson;
    /**
     * @var TranslatorInterface
     */
    private $translator;
    /**
     * @var PrefilledResourceTypeProvider
     */
    protected $resourceTypeProvider;

    /**
     * @var ApiLogger
     */
    private $apiLogger;

    /**
     * @var SchemaPathProcessor
     */
    private $schemaPathProcessor;

    /**
     * @var FieldsValidator
     */
    private $fieldsValidator;

    /**
     * @var MessageFormatter
     */
    private $messageFormatter;

    public function __construct(
        ApiLogger $apiLogger,
        PrefilledResourceTypeProvider $resourceTypeProvider,
        TranslatorInterface $translator
    ) {
        $this->translator = $translator;
        $this->resourceTypeProvider = $resourceTypeProvider;
        $this->apiLogger = $apiLogger;
        $this->schemaPathProcessor = new SchemaPathProcessor(
            new DplanPropertyPathProcessorFactory($apiLogger),
            $resourceTypeProvider
        );
        $this->fieldsValidator = new FieldsValidator(
            new TypeAccessor($resourceTypeProvider),
            Validation::createValidator()
        );
        $this->messageFormatter = new MessageFormatter();
    }

    /**
     * This method is called during the `kernel.controller` event for
     * Api controller specific initialization tasks. It is named
     * how it is named to avoid any conflicts and confusion with the
     * core `initialize` method.
     */
    public function setupApiController(
        RequestStack $requestStack,
        ApiResourceService $resourceService
    ): void {
        $this->request = $requestStack->getCurrentRequest();
        $content = $this->getRequestBody();
        $this->setupApiControllerFromRequestContent($resourceService, $content);
    }

    /**
     * @param string|resource $content
     */
    protected function setupApiControllerFromRequestContent(
        ApiResourceService $resourceService,
        $content
    ): void {
        if ('' !== $content) {
            try {
                $normalizer = new Normalizer();
                $this->requestData = $normalizer->normalize($content);
            } catch (InvalidArgumentException $e) {
                $this->getLogger()->info(
                    'Request did not contain valid json, but was expected to.',
                    [$e, $e->getTraceAsString()]
                );
            }
        }

        $this->resourceService = $resourceService;
        $this->fractal = $resourceService->getFractal();

        // include those entities if they are in the availableIncludes of the transformer
        $rawIncludes = $this->request->get(UrlParameter::INCLUDE);
        $resourceType = $this->request->attributes->get('resourceType');
        $this->validateIncludes($rawIncludes, $resourceType);
        if (null === $rawIncludes) {
            $rawIncludes = [];
        }
        $this->fractal->parseIncludes($rawIncludes);

        // exclude those entities even if they are in the defaultIncludes of the transformer
        $this->fractal->parseExcludes($this->request->get('exclude', ''));

        // check if only specific fields were requested and (if so) parse them to access
        // them later
        $fields = $this->request->get(UrlParameter::FIELDS);
        if (null !== $fields) {
            $fields = $this->fieldsValidator->validateFormat($fields);
            $this->validateFieldsets($fields);
            $this->fractal->parseFieldsets($fields);
        }
    }

    /**
     * @param int $httpResponseStatusCode HTTP status code to use for the response
     */
    public function renderResource(ResourceAbstract $resource, int $httpResponseStatusCode = Response::HTTP_OK): APIResponse
    {
        $data = $this->fractal->createData($resource)->toArray();

        return $this->createResponse($data, $httpResponseStatusCode);
    }

    /**
     * @param int $httpResponseStatusCode HTTP status code to use for the response
     */
    public function renderEmpty(int $httpResponseStatusCode = Response::HTTP_OK): APIResponse
    {
        return $this->createResponse([], $httpResponseStatusCode);
    }

    /**
     * @param int $status
     */
    protected function createResponse(array $data, $status): APIResponse
    {
        // @improve T16794
        if (false === array_key_exists('included', $data)) {
            $data['included'] = [];
        }

        $data['links'] = ['self' => $this->request instanceof Request ? $this->request->getUri() : ''];

        $data['jsonapi'] = ['version' => '1.0'];

        return APIResponse::create($data, $status);
    }

    protected function createEmptyResponse(): EmptyResponse
    {
        return new EmptyResponse();
    }

    // @improve T16795

    /**
     * Return JsonAPi Error Object.
     *
     * Also add messages to message bag.
     */
    public function handleApiError(Throwable $exception = null): APIResponse
    {
        $status = Response::HTTP_BAD_REQUEST;
        $message = '';

        if (!$this->getGlobalConfig()->isProdMode()) {
            $this->getLogger()->error('API exception occurred', [$exception]);
        }

        try {
            switch (true) {
                case $exception instanceof ExcludeException:
                    $message = $exception->getMessage();
                    break;
                case $exception instanceof PropertyUpdateAccessException:
                    $status = Response::HTTP_FORBIDDEN;
                    break;
                case $exception instanceof ViolationsException:
                    /** @var ViolationsException $exception */
                    $violations = $exception->getViolations();

                    $this->messageBag->addViolations($violations);
                    break;
                case $exception instanceof SessionUnavailableException:
                case $exception instanceof AccessDeniedException:
                    $status = Response::HTTP_UNAUTHORIZED;
                    $message = 'error.api.session';
                    break;
                case $exception instanceof ResourceNotFoundException:
                    $status = Response::HTTP_NOT_FOUND;
                    break;
                case $exception instanceof PersistResourceException:
                    // Error message was already added.
                    break;
                case $exception instanceof ConcurrentEditionException:
                    $status = Response::HTTP_CONFLICT;
                    break;
                case $exception instanceof DuplicateInternIdException:
                    $status = Response::HTTP_BAD_REQUEST;
                    $message = 'error.unique.procedure.internid';
                    break;
                default:
                    $message = 'error.api.generic';
                    break;
            }

            // be careful not to expose any exception message as it may contain
            // sensitive data
            if (null !== $exception && '' !== $message) {
                $this->getMessageBag()->add('error', $message);
            }
        } catch (MessageBagException $exception) {
            $this->getLogger()->error('Failed to add error message to message bag');
        }

        // @improve T16796
        $data = [
            'errors' => [
                [
                    'status' => $status,
                    'title'  => $this->translator->trans($message),
                ],
            ],
        ];

        return $this->createResponse($data, $status);
    }

    /**
     * Send a single item as response.
     *
     * @param array|CoreEntity|ValueObject|User $data
     * @param int                               $httpResponseStatusCode HTTP status code to use for the response
     */
    protected function renderItem($data, string $transformerName, int $httpResponseStatusCode = Response::HTTP_OK): APIResponse
    {
        if (null === $data) {
            throw new \demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException('Will not render item based on null data.');
        }
        $item = $this->resourceService->makeItem($data, $transformerName);

        return $this->renderResource($item, $httpResponseStatusCode);
    }

    /**
     * @deprecated use {@link ApiResourceService::makeItemOfResource()} and call {@link APIController::renderResource()} instead
     */
    protected function renderItemOfResource($data, ResourceTypeInterface $resourceType, int $httpResponseStatusCode = Response::HTTP_OK): APIResponse
    {
        if (null === $data) {
            throw new \demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException('Will not render item based on null data.');
        }
        $item = new Item($data, $resourceType->getTransformer(), $resourceType::getName());

        return $this->renderResource($item, $httpResponseStatusCode);
    }

    /**
     * Send a collection of items as response.
     *
     * @param iterable|CoreEntity[]|ValueObject[] $data
     */
    protected function renderCollection($data, string $transformerName): APIResponse
    {
        $collection = $this->resourceService->makeCollection($data, $transformerName);

        return $this->renderResource($collection);
    }

    // @improve T16798
    // @improve T16797

    /**
     * Confirm entity deletion.
     *
     * @param int $status
     *
     * @return JsonResponse
     */
    protected function renderDelete($status = Response::HTTP_OK): Response
    {
        if (Response::HTTP_NO_CONTENT === $status) {
            return new Response(null, $status);
        }

        return $this->createResponse([], $status);
    }

    protected function renderSuccess($status = Response::HTTP_OK): Response
    {
        if (Response::HTTP_NO_CONTENT === $status) {
            return new Response(null, $status);
        }

        return $this->createResponse([], $status);
    }

    /**
     * @param int $status
     *
     * @throws MessageBagException
     */
    protected function renderError($status): APIResponse
    {
        $message = 'error.api.generic';

        if (Response::HTTP_NOT_FOUND === $status) {
            $message = 'error.api.notfound';
        }

        $this->getMessageBag()->add('error', $message);

        return $this->createResponse([], $status);
    }

    /**
     * @return resource|string
     */
    protected function getRequestBody()
    {
        return $this->request->getContent();
    }

    /**
     * @param string $path a dictionary path understood by `data_get()`
     *
     * @see data_get()
     *
     * @return mixed|null
     */
    protected function getRequestJson($path = null)
    {
        if (null === $this->requestJson) {
            try {
                $this->requestJson = Json::decodeToArray($this->getRequestBody());
            } catch (InvalidArgumentException $e) {
                $this->logger->warning(
                    'Request did not contain valid json, but was expected to.',
                    $e->getTrace()
                );

                return null;
            }
        }

        return data_get($this->requestJson, $path);
    }

    /**
     * Its values must not be an array of properties even though Fractal supports it. This is
     * because this does not conform to the JSON:API and thus may not be supported by other
     * libraries, which may limit the options if Fractal is to be replaced by a different
     * library.
     *
     * @param array<non-empty-string, string> $fieldset
     *
     * @see https://jsonapi.org/format/#fetching-sparse-fieldsets
     */
    private function validateFieldsets(array $fieldset): void
    {
        foreach ($fieldset as $typeIdentifier => $propertiesString) {
            try {
                // Checking if the type exists and is a resource type implementation.
                $type = $this->resourceTypeProvider->requestType($typeIdentifier)
                    ->instanceOf(ResourceTypeInterface::class)
                    ->available(true)
                    ->getTypeInstance();
                $nonReadableProperties = $this->fieldsValidator->getNonReadableProperties($propertiesString, $type);
                if ([] !== $nonReadableProperties) {
                    $unknownPropertiesString = $this->messageFormatter->propertiesToString($nonReadableProperties);
                    $this->apiLogger->warning("The following requested fieldset properties are not readable in the resource type '$typeIdentifier': $unknownPropertiesString.");
                }
            } catch (TypeRetrievalAccessException $exception) {
                $this->apiLogger->warning("The key of the fields parameter MUST be an available resource type. Type '$typeIdentifier' not available for reading.", ['exception' => $exception]);
            }
        }
    }

    /**
     * Executes a superficial check for invalid includes.
     *
     * *Only the first path segment* of each include is checked. Also, it is only checked if a
     * property used in the include is defined as relationship in the resource type definition
     * *at all*.
     * This means the property may be defined but is still not available via the API due to being
     * reserved for internal use only or due to missing permissions.
     */
    protected function validateIncludes($rawIncludes, $resourceTypeName): void
    {
        if (null === $rawIncludes) {
            return;
        }

        $baseMessage = 'If the \'include\' parameter is present, its value must be a comma-separated '
            .'list of relationship paths as a single string. Eg. “include=comments,authors“';

        if (is_array($rawIncludes)) {
            // See https://jsonapi.org/format/#fetching-includes
            $message = $baseMessage.', not “include[]=comments&include[]=authors”. Request URL was: '
                .$this->request->getRequestUri();
            throw new BadRequestException($message);
        }

        if (!is_string($rawIncludes)) {
            $this->apiLogger->warning("$baseMessage, not the type ".gettype($rawIncludes).'.');

            return;
        }

        if ('' === $rawIncludes) {
            $this->apiLogger->warning("$baseMessage, not an empty string.");

            return;
        }

        // the existence of properties in a resource type can only be checked if we were able to
        // retrieve the accessed resource type from the request
        if (is_string($resourceTypeName)) {
            try {
                $this->resourceTypeProvider->requestType($resourceTypeName)
                    ->getTypeInstance();
            } catch (TypeRetrievalAccessException $exception) {
                // The accessed resource type is probably not a generic one, thus we can not
                // continue to validate the 'include' properties.
                return;
            }

            // if the type exists at all (see `getType` above) then it must be an
            // available, directly accessible resource type
            $type = $this->resourceTypeProvider->requestType($resourceTypeName)
                ->instanceOf(ResourceTypeInterface::class)
                ->available(true)
                ->getTypeInstance();
            if (!$type->isDirectlyAccessible()) {
                throw new InvalidArgumentException("The resource type '$resourceTypeName' is not directly accessible");
            }

            $includes = explode(',', $rawIncludes);
            array_map(function (string $include) use ($type): void {
                try {
                    $includePath = explode('.', $include);
                    $this->schemaPathProcessor->mapExternReadablePath($type, $includePath, false);
                } catch (PropertyAccessException $exception) {
                    $this->apiLogger->warning(
                        "The following include property path is not available in the resource type '{$type::getName()}': $include",
                        ['exception' => $exception]
                    );
                }
            }, $includes);
        }
    }
}
