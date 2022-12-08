<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanStatementBundle\Logic;

use Carbon\Carbon;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Exception;
use demosplan\DemosPlanCoreBundle\Entity\Statement\GdprConsentRevokeToken;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\EmailAddressService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementAnonymizeService;
use demosplan\DemosPlanCoreBundle\Logic\TokenFactory;
use demosplan\DemosPlanStatementBundle\Exception\GdprConsentRevokeTokenAlreadyUsedException;
use demosplan\DemosPlanStatementBundle\Exception\GdprConsentRevokeTokenNotFoundException;
use demosplan\DemosPlanStatementBundle\Exception\InvalidDataException;
use demosplan\DemosPlanStatementBundle\Exception\StatementAlreadyConnectedToGdprConsentRevokeTokenException;
use demosplan\DemosPlanStatementBundle\Exception\StatementNotFoundException;
use demosplan\DemosPlanStatementBundle\Repository\GdprConsentRevokeTokenRepository;
use demosplan\DemosPlanUserBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanUserBundle\Exception\UserNotFoundException;
use function hash;

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
