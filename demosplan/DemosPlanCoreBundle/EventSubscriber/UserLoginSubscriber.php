<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\User\FunctionalUser;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\Repository\AccountDeletionTrackingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\JWTUserToken;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class UserLoginSubscriber extends BaseEventSubscriber
{
    public function __construct(
        private readonly UserService $userService,
        private readonly AccountDeletionTrackingRepository $trackingRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function onLogin(LoginSuccessEvent $event): void
    {
        $token = $event->getAuthenticatedToken();
        if ($token instanceof JWTUserToken) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof User || $user instanceof FunctionalUser) {
            return;
        }

        $user->setLastLogin(new DateTime());
        $this->userService->updateUserObject($user);
        $this->removeAccountDeletionTracking($user);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLogin',
        ];
    }

    /**
     * Resets the inactivity-based account-deletion workflow when the user logs in.
     * Removes any existing tracking row so the cron starts fresh on the next stale
     * inactivity period.
     */
    private function removeAccountDeletionTracking(User $user): void
    {
        $tracking = $this->trackingRepository->findOneByUser($user);
        if (null === $tracking) {
            return;
        }

        $this->entityManager->remove($tracking);
        $this->entityManager->flush();
    }
}
