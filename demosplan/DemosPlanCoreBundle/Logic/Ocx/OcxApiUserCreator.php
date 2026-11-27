<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Ocx;

use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;

/**
 * Creates the technical user an OCX API token acts as. It belongs to the organisation whose
 * procedures it may deliver into, has no role and a random password nobody knows; its rights
 * come from the OCX request context only.
 */
readonly class OcxApiUserCreator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private OcxApiTokenService $tokenService,
    ) {
    }

    /**
     * @return array{User, string} the user and a new API token for it
     *
     * @throws Exception
     */
    public function create(Orga $orga, ?Customer $customer, string $tokenName): array
    {
        $customer = $this->tokenService->resolveCustomer($orga, $customer);
        $department = $orga->getDepartments()->first();
        if (!$department instanceof Department) {
            throw new InvalidArgumentException('The organisation has no department.');
        }

        // all or nothing: a user without its token (e.g. after a failing search index) is useless
        return $this->entityManager->wrapInTransaction(function () use ($orga, $customer, $department, $tokenName): array {
            $login = 'ocx-api-'.bin2hex(random_bytes(6)).'@ocx.invalid';
            $user = $this->userRepository->add([
                'firstname'    => 'OCX',
                'lastname'     => 'API',
                'email'        => $login,
                'login'        => $login,
                'password'     => hash('sha512', random_bytes(32)),
                'customer'     => $customer,
                'organisation' => $orga,
                'department'   => $department,
            ]);
            // nobody will fill in the welcome page that otherwise intercepts every request
            $user->setProfileCompleted(true);
            $user->setAccessConfirmed(true);
            $user->setNewUser(false);
            $this->entityManager->flush();

            return [$user, $this->tokenService->issue($user, $customer, $tokenName)];
        });
    }
}
