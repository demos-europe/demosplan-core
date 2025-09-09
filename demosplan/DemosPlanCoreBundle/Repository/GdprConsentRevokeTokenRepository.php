<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\EmailAddress;
use demosplan\DemosPlanCoreBundle\Entity\Statement\GdprConsentRevokeToken;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\GdprConsentRevokeTokenNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\StatementAlreadyConnectedToGdprConsentRevokeTokenException;
use demosplan\DemosPlanCoreBundle\Exception\StatementNotFoundException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;

/**
 * @template-extends CoreRepository<GdprConsentRevokeToken>
 */
class GdprConsentRevokeTokenRepository extends CoreRepository
{
    /**
     * @throws GdprConsentRevokeTokenNotFoundException
     */
    public function getGdprConsentRevokeTokenByTokenValue(string $gdprConsentRevokeTokenValue): GdprConsentRevokeToken
    {
        $gdprConsentRevokeToken = $this->findOneBy(['token' => $gdprConsentRevokeTokenValue]);
        if (!$gdprConsentRevokeToken instanceof GdprConsentRevokeToken) {
            throw GdprConsentRevokeTokenNotFoundException::createFromTokenValue($gdprConsentRevokeTokenValue);
        }

        return $gdprConsentRevokeToken;
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateAsUsed(GdprConsentRevokeToken $gdprConsentRevokeToken)
    {
        assert(null !== $gdprConsentRevokeToken->getEmailAddress());
        $em = $this->getEntityManager();
        $gdprConsentRevokeToken->setEmailAddress(null);
        $gdprConsentRevokeToken->setStatements(new ArrayCollection());
        $em->persist($gdprConsentRevokeToken);
        $em->flush();
    }

    /**
     * @throws NonUniqueResultException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws StatementAlreadyConnectedToGdprConsentRevokeTokenException
     * @throws StatementNotFoundException
     */
    public function createAndFlushTokenObject(Statement $originalStatement, EmailAddress $emailAddress, string $tokenValue): GdprConsentRevokeToken
    {
        $entityManager = $this->getEntityManager();

        // check if provided statement ID is usable
        if (!$originalStatement->isOriginal()) {
            throw StatementNotFoundException::createFromNonOriginalId($originalStatement->getId());
        }
        if ($this->hasStatementIdGdprConsentRevokeToken($originalStatement->getId())) {
            throw StatementAlreadyConnectedToGdprConsentRevokeTokenException::createFromStatementId($originalStatement->getId());
        }

        // create new token
        $gdprConsentRevokeToken = new GdprConsentRevokeToken();
        $gdprConsentRevokeToken->setEmailAddress($emailAddress);
        $gdprConsentRevokeToken->setStatements(new ArrayCollection([$originalStatement]));
        $gdprConsentRevokeToken->setToken($tokenValue);

        $entityManager->persist($gdprConsentRevokeToken);
        $entityManager->flush();

        return $gdprConsentRevokeToken;
    }

    /**
     * Connect the $statementWithoutToken to the token in the $tokenSourceStatement and persist the
     * token.
     *
     * If $tokenSourceStatement does not have a token then no write operation happens.
     * If $statementWithoutToken is already linked to the same token as $tokenSourceStatement no
     * write operation happens.
     *
     * @return bool True if both statements are now connected to the same token. False otherwise
     *              (eg. because $sourceStatement was not connected to a token).
     *
     * @throws NonUniqueResultException
     * @throws ORMException
     * @throws StatementAlreadyConnectedToGdprConsentRevokeTokenException if $statementWithoutToken
     *                                                                    already has a token
     */
    public function maybeConnectStatementToTokenInOtherStatementAndPersist(Statement $statementWithoutToken, Statement $tokenSourceStatement): bool
    {
        // First check if there is anything that can or should be done.
        $statementWithoutTokenId = $statementWithoutToken->getId();

        try {
            $gdprConsentRevokeToken = $this->getGdprConsentRevokeTokenByStatementId(
                $tokenSourceStatement->getId()
            );
            $statements = $gdprConsentRevokeToken->getStatements();
            if ($statements->contains($statementWithoutToken)) {
                return true;
            }
        } catch (GdprConsentRevokeTokenNotFoundException) {
            // If the $sourceStatement does not have a token we do not need to do anything
            return false;
        }
        if (null !== $statementWithoutTokenId && $this->hasStatementIdGdprConsentRevokeToken($statementWithoutTokenId)) {
            throw StatementAlreadyConnectedToGdprConsentRevokeTokenException::createFromStatementId($statementWithoutTokenId);
        }

        // Then actually do what was requested.
        $statements->add($statementWithoutToken);
        $gdprConsentRevokeToken->setStatements($statements);
        $this->getEntityManager()->persist($gdprConsentRevokeToken);

        return true;
    }

    /**
     * @throws NonUniqueResultException
     * @throws GdprConsentRevokeTokenNotFoundException
     */
    protected function getGdprConsentRevokeTokenByStatementId(string $statementId): GdprConsentRevokeToken
    {
        try {
            $entityManager = $this->getEntityManager();
            $queryBuilder = $entityManager->createQueryBuilder();
            $queryBuilder->select('t');
            $queryBuilder->from(GdprConsentRevokeToken::class, 't');
            $queryBuilder->join('t.statements', 's');
            $queryBuilder->where('s.id = :statementId');
            $queryBuilder->setParameter('statementId', $statementId);

            return $queryBuilder->getQuery()->getSingleResult();
        } catch (NoResultException) {
            throw GdprConsentRevokeTokenNotFoundException::createFromStatementId($statementId);
        }
    }

    /**
     * @throws NonUniqueResultException
     */
    protected function hasStatementIdGdprConsentRevokeToken(string $statementId): bool
    {
        try {
            $this->getGdprConsentRevokeTokenByStatementId($statementId);

            return true;
        } catch (GdprConsentRevokeTokenNotFoundException) {
            return false;
        }
    }
}
