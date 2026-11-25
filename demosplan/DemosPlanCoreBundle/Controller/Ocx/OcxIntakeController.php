<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Ocx;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Ocx\OcxDelivery;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\OcxProblemException;
use demosplan\DemosPlanCoreBundle\Logic\Ocx\OcxDeliveryService;
use demosplan\DemosPlanCoreBundle\Logic\Ocx\OcxDeliveryStatusMapper;
use demosplan\DemosPlanCoreBundle\Logic\Ocx\OcxPayloadValidator;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Security\Authentication\Authenticator\OcxApiTokenAuthenticator;
use demosplan\DemosPlanCoreBundle\Security\Ocx\OcxRequestContext;
use demosplan\DemosPlanCoreBundle\Types\OcxDeliveryStatus;
use JsonException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

use function in_array;

/**
 * Receiving side of the OCX intake API, see `openapi/ocx-intake-0.1.yaml` in `demos-europe/ocx`.
 * Authentication happens in the `ocx` firewall.
 */
#[Route(path: '/ocx/v0', requirements: ['deliveryId' => '[0-9a-fA-F-]{36}', 'attachmentId' => '[0-9a-fA-F-]{36}', 'part' => '[1-9]\d{0,5}'])]
class OcxIntakeController extends AbstractController
{
    private const POLL_INTERVAL_SECONDS = 5;

    public function __construct(
        private readonly CurrentUserService $currentUser,
        private readonly OcxDeliveryService $deliveryService,
        private readonly OcxDeliveryStatusMapper $statusMapper,
        private readonly LoggerInterface $logger,
        private readonly OcxPayloadValidator $payloadValidator,
        private readonly PermissionsInterface $permissions,
        private readonly RateLimiterFactory $ocxIntakeLimiter,
        private readonly RequestStack $requestStack,
        #[Autowire('%ocx_max_body_size%')]
        private readonly int $maxBodySize,
    ) {
    }

    #[Route(path: '/capabilities', name: 'ocx_capabilities', methods: ['GET'])]
    public function capabilities(): Response
    {
        return $this->handle(fn (): Response => $this->jsonBody($this->payloadValidator->getCapabilities(), Response::HTTP_OK));
    }

    #[Route(path: '/procedures/{procedureId}/deliveries', name: 'ocx_delivery_open', methods: ['POST'])]
    public function open(Request $request, string $procedureId): Response
    {
        return $this->handle(function () use ($request, $procedureId): Response {
            [$delivery, $created] = $this->deliveryService->open(
                $procedureId,
                $this->getApiUser(),
                $this->getApiCustomerId(),
                (string) $request->headers->get('Idempotency-Key', ''),
                $this->readBody($request, $this->maxBodySize)
            );
            $response = $this->status($delivery, $created ? Response::HTTP_CREATED : Response::HTTP_OK);
            if ($created) {
                $response->headers->set('Location', $this->generateUrl('ocx_delivery_get', ['deliveryId' => $delivery->getId()]));
            }

            return $response;
        });
    }

    #[Route(path: '/deliveries/{deliveryId}', name: 'ocx_delivery_get', methods: ['GET'])]
    public function get(string $deliveryId): Response
    {
        return $this->handle(function () use ($deliveryId): Response {
            $delivery = $this->deliveryService->findAccessibleDelivery($deliveryId, $this->getApiUser(), $this->getApiCustomerId());
            $response = $this->status($delivery, Response::HTTP_OK);
            if (in_array($delivery->getStatus(), [OcxDeliveryStatus::QUEUED, OcxDeliveryStatus::PROCESSING], true)) {
                $response->headers->set('Retry-After', (string) self::POLL_INTERVAL_SECONDS);
            }

            return $response;
        });
    }

    #[Route(path: '/deliveries/{deliveryId}', name: 'ocx_delivery_abort', methods: ['DELETE'])]
    public function abort(string $deliveryId): Response
    {
        return $this->handle(function () use ($deliveryId): Response {
            $this->deliveryService->abort($this->deliveryService->findAccessibleDelivery($deliveryId, $this->getApiUser(), $this->getApiCustomerId()));

            return new Response(null, Response::HTTP_NO_CONTENT);
        });
    }

    #[Route(path: '/deliveries/{deliveryId}/attachments/{attachmentId}', name: 'ocx_delivery_attachment', methods: ['PUT'])]
    public function uploadAttachment(Request $request, string $deliveryId, string $attachmentId): Response
    {
        return $this->handle(function () use ($request, $deliveryId, $attachmentId): Response {
            $delivery = $this->deliveryService->findAccessibleDelivery($deliveryId, $this->getApiUser(), $this->getApiCustomerId());
            $stored = $this->deliveryService->storeAttachment(
                $delivery,
                $attachmentId,
                $request->headers->get('Content-Digest'),
                $request->getContent(true),
                $request->headers->has('Content-Length') ? (int) $request->headers->get('Content-Length') : null
            );

            return $this->status($delivery, $stored ? Response::HTTP_CREATED : Response::HTTP_OK);
        });
    }

    #[Route(path: '/deliveries/{deliveryId}/entries/{part}', name: 'ocx_delivery_entries', methods: ['PUT'])]
    public function uploadEntries(Request $request, string $deliveryId, int $part): Response
    {
        return $this->handle(function () use ($request, $deliveryId, $part): Response {
            $delivery = $this->deliveryService->findAccessibleDelivery($deliveryId, $this->getApiUser(), $this->getApiCustomerId());
            $stored = $this->deliveryService->storeEntriesPart(
                $delivery,
                $part,
                $request->headers->get('Content-Digest'),
                $this->readBody($request, $this->maxBodySize)
            );

            return $this->status($delivery, $stored ? Response::HTTP_CREATED : Response::HTTP_OK);
        });
    }

    #[Route(path: '/deliveries/{deliveryId}/complete', name: 'ocx_delivery_complete', methods: ['POST'])]
    public function complete(string $deliveryId): Response
    {
        return $this->handle(function () use ($deliveryId): Response {
            $delivery = $this->deliveryService->findAccessibleDelivery($deliveryId, $this->getApiUser(), $this->getApiCustomerId());
            $queued = $this->deliveryService->complete($delivery);

            return $this->status($delivery, $queued ? Response::HTTP_ACCEPTED : Response::HTTP_OK);
        });
    }

    /**
     * @param callable(): Response $action
     */
    private function handle(callable $action): Response
    {
        try {
            $limit = $this->ocxIntakeLimiter->create($this->getApiUser()->getId())->consume();
            if (!$limit->isAccepted()) {
                $response = (new OcxProblemException(Response::HTTP_TOO_MANY_REQUESTS))->toResponse();
                $response->headers->set('Retry-After', (string) max(1, $limit->getRetryAfter()->getTimestamp() - time()));

                return $response;
            }
            if (!$this->permissions->hasPermission(OcxRequestContext::FEATURE_STATEMENT)
                && !$this->permissions->hasPermission(OcxRequestContext::FEATURE_RESPONSE)
            ) {
                throw OcxProblemException::notFound();
            }

            return $action();
        } catch (OcxProblemException $e) {
            return $e->toResponse();
        } catch (Throwable $e) {
            $this->logger->error('OCX request failed', ['exception' => $e]);

            return (new OcxProblemException(Response::HTTP_INTERNAL_SERVER_ERROR))->toResponse();
        }
    }

    /**
     * Reads at most one byte beyond the limit, so an oversized body never ends up in memory as a whole.
     *
     * @throws OcxProblemException
     */
    private function readBody(Request $request, int $limit): string
    {
        $length = $request->headers->get('Content-Length');
        if (null !== $length && (int) $length > $limit) {
            throw OcxProblemException::tooLarge($limit);
        }
        $body = (string) stream_get_contents($request->getContent(true), $limit + 1);
        if (strlen($body) > $limit) {
            throw OcxProblemException::tooLarge($limit);
        }

        return $body;
    }

    /**
     * The customer the API token was issued for, set by {@see OcxApiTokenAuthenticator}.
     *
     * @throws OcxProblemException
     */
    private function getApiCustomerId(): string
    {
        $customerId = $this->requestStack->getCurrentRequest()?->attributes->get(OcxApiTokenAuthenticator::CUSTOMER_ID_ATTRIBUTE);
        if (!is_string($customerId) || '' === $customerId) {
            throw OcxProblemException::notFound();
        }

        return $customerId;
    }

    /**
     * @throws OcxProblemException
     */
    private function getApiUser(): User
    {
        $user = $this->currentUser->getUser();
        if (!$user->isLoggedIn()) {
            throw OcxProblemException::notFound();
        }

        return $user;
    }

    /**
     * @throws JsonException
     */
    private function status(OcxDelivery $delivery, int $status): Response
    {
        return $this->jsonBody($this->statusMapper->toStatus($delivery), $status);
    }

    /**
     * A plain response on purpose: {@see \demosplan\DemosPlanCoreBundle\EventListener\DemosPlanResponseEventSubscriber}
     * adds `meta.messages` to every JsonResponse, which the OCX schemas do not allow.
     *
     * @param array<string, mixed> $data
     *
     * @throws JsonException
     */
    private function jsonBody(array $data, int $status): Response
    {
        return new Response(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $status,
            ['Content-Type' => 'application/json']
        );
    }
}
