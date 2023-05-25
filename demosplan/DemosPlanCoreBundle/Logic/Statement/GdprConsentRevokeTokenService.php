<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\GdprConsentRevokeToken;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\GdprConsentRevokeTokenAlreadyUsedException;
use demosplan\DemosPlanCoreBundle\Exception\GdprConsentRevokeTokenNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Exception\StatementAlreadyConnectedToGdprConsentRevokeTokenException;
use demosplan\DemosPlanCoreBundle\Exception\StatementNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\EmailAddressService;
use demosplan\DemosPlanCoreBundle\Logic\TokenFactory;
use demosplan\DemosPlanCoreBundle\Repository\GdprConsentRevokeTokenRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;

class GdprConsentRevokeTokenService extends CoreService
{
    /** @var StatementService */
    protected $statementService;

    /** @var EmailAddressService */
    protected $emailAddressService;

    /** @var PermissionsInterface */
    protected $permissions;

    /**
     * @var StatementAnonymizeService
     */
    private $statementAnonymizeService;

    /**
     * @var TokenFactory
     */
    private $tokenFactory;
    /**
     * @var GdprConsentRevokeTokenRepository
     */
    private $gdprConsentRevokeTokenRepository;

    public function __construct(
        EmailAddressService $emailAddressService,
        GdprConsentRevokeTokenRepository $gdprConsentRevokeTokenRepository,
        PermissionsInterface $permissions,
        StatementAnonymizeService $statementAnonymizeService,
        TokenFactory $tokenFactory
    ) {
        $this->emailAddressService = $emailAddressService;
        $this->gdprConsentRevokeTokenRepository = $gdprConsentRevokeTokenRepository;
        $this->permissions = $permissions;
        $this->statementAnonymizeService = $statementAnonymizeService;
        $this->tokenFactory = $tokenFactory;
    }

    /**
     * @throws InvalidDataException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws GdprConsentRevokeTokenAlreadyUsedException
     * @throws GdprConsentRevokeTokenNotFoundException
     * @throws CustomerNotFoundException
     * @throws UserNotFoundException
     */
    public function revokeConsentByTokenIdAndEmailAddress(string $tokenValue, string $inputEmailAddress): void
    {
        $gdprConsentRevokeToken = $this->gdprConsentRevokeTokenRepository->getGdprConsentRevokeTokenByTokenValue($tokenValue);
        $tokenEmailAddress = $gdprConsentRevokeToken->getEmailAddress();

        if (null === $tokenEmailAddress) {
            throw GdprConsentRevokeTokenAlreadyUsedException::createFromTokenValue($tokenValue);
        }

        if ($tokenEmailAddress->getFullAddress() !== $inputEmailAddress) {
            throw GdprConsentRevokeTokenNotFoundException::createFromTokenValueAndEmailAddress($tokenValue, $inputEmailAddress);
        }

        /** @var Statement $statement */
        foreach ($gdprConsentRevokeToken->getStatements() as $statement) {
            assert($statement->isOriginal());
            $this->statementAnonymizeService->anonymizeUserDataOfStatement(
                $statement,
                true,
                true,
                User::ANONYMOUS_USER_ID,
                true
            );
        }

        $this->gdprConsentRevokeTokenRepository->updateAsUsed($gdprConsentRevokeToken);
    }

    /**
     * @throws StatementAlreadyConnectedToGdprConsentRevokeTokenException
     * @throws StatementNotFoundException
     * @throws Exception
     */
    public function createAndFlushTokenObject(Statement $originalStatement, string $fullEmailAddress): GdprConsentRevokeToken
    {
        $emailAddress = $this->getEmailAddressService()->getOrCreateEmailAddress($fullEmailAddress);

        $hashValue = $emailAddress->getFullAddress();
        $tokenValue = $this->tokenFactory->createSaltedToken($hashValue);

        return $this->gdprConsentRevokeTokenRepository->createAndFlushTokenObject($originalStatement, $emailAddress, $tokenValue);
    }

    /**
     * @return GdprConsentRevokeToken|null
     *
     * @throws StatementAlreadyConnectedToGdprConsentRevokeTokenException
     * @throws StatementNotFoundException
     */
    public function maybeCreateGdprConsentRevokeToken(string $fullEmailAddress, Statement $originalStatement)
    {
        if (true !== $this->permissions->hasPermission('feature_gdpr_consent_revoke_by_token')) {
            return null;
        }

        return $this->createAndFlushTokenObject($originalStatement, $fullEmailAddress);
    }

    protected function getEmailAddressService(): EmailAddressService
    {
        return $this->emailAddressService;
    }
}
