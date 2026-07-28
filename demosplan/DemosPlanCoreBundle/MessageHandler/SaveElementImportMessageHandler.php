<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\MessageHandler;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ElementImportJob;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Document\DocumentHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Message\SaveElementImportMessage;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

/**
 * Turns an already extracted Planunterlagen import into documents in the background, so the user is
 * not held in a request for the tens of minutes this takes on a large archive — and so it does not
 * occupy one of the few PHP-FPM workers while doing it.
 *
 * The existing synchronous {@link DocumentHandler::saveElementsFromImport()} is reused unchanged;
 * this handler only re-establishes the acting user and permissions it would normally get from the
 * HTTP request, and hands it the job so progress lands on the row the browser polls.
 */
#[AsMessageHandler]
class SaveElementImportMessageHandler
{
    public function __construct(
        private readonly CurrentUserService $currentUserService,
        private readonly DocumentHandler $documentHandler,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly PermissionsInterface $permissions,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function __invoke(SaveElementImportMessage $message): void
    {
        $job = $this->entityManager->find(ElementImportJob::class, $message->getJobId());
        if (!$job instanceof ElementImportJob) {
            $this->logger->error('Element import job not found', ['jobId' => $message->getJobId()]);

            return;
        }

        $job->setStatus(ElementImportJob::STATUS_PROCESSING);
        $job->setModifiedDate(new DateTime());
        $this->entityManager->flush();

        $requestPushed = false;
        try {
            // The importer reaches for the session to hold its running counters; provide an empty
            // request so it does not fail outside an HTTP context.
            $this->pushSyntheticRequest();
            $requestPushed = true;

            $this->establishContext($message);

            $errorReport = $this->documentHandler->saveElementsFromImport(
                $message->getRequestPost(),
                $job->getImportList() ?? [],
                $message->getProcedureId(),
                DemosPlanPath::getTemporaryPath($message->getUserId().'/'.$message->getProcedureId()),
                $job
            );

            if ([] !== $errorReport) {
                // Individual files that could not be imported do not fail the job: the import
                // continues past them by design, so they are reported rather than thrown.
                $job->setErrorMessage(implode("\n", $errorReport));
            }

            $job->setStatus(ElementImportJob::STATUS_COMPLETED);
        } catch (Throwable $e) {
            $this->logger->error(
                'Asynchronous element import failed',
                ['jobId' => $message->getJobId(), 'exception' => $e]
            );
            $job->setStatus(ElementImportJob::STATUS_FAILED);
            $job->setErrorMessage($e->getMessage());
        } finally {
            if ($requestPushed) {
                $this->requestStack->pop();
            }
            $job->setModifiedDate(new DateTime());
            $this->entityManager->flush();
        }
    }

    /**
     * Re-establish the acting user and their permissions outside an HTTP request, so the permission
     * checks inside the importer see the user who started the job rather than nobody.
     */
    private function establishContext(SaveElementImportMessage $message): void
    {
        $user = $this->entityManager->find(User::class, $message->getUserId());
        if (!$user instanceof User) {
            throw new RuntimeException('Element import job user not found: '.$message->getUserId());
        }

        $this->currentUserService->setUser($user);
        $this->permissions->initPermissions($user);
    }

    private function pushSyntheticRequest(): void
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $this->requestStack->push($request);
    }
}
