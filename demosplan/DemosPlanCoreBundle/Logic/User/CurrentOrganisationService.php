<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\User;

use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Repository\OrgaRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service to manage the current organisation context for multi-responsibility users.
 *
 * Multi-responsibility users can belong to multiple organisations but only operate
 * in one organisation context per session. This service manages that context by:
 * - Storing the current organisation ID in the session
 * - Initializing the transient currentOrganisation property on the User entity
 * - Providing helpers for organisation lookup and creation
 */
class CurrentOrganisationService
{
    public const SESSION_KEY = 'dplan_current_organisation_id';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly OrgaRepository $orgaRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Get the current session-selected organisation for a user.
     * Falls back to the first organisation in the user's collection if no session selection exists.
     */
    public function getCurrentOrganisation(UserInterface $user): ?OrgaInterface
    {
        // First check if user has a transient current organisation set
        if ($user instanceof User && null !== $user->getCurrentOrganisation()) {
            return $user->getCurrentOrganisation();
        }

        // Try to get from session
        $session = $this->requestStack->getSession();
        $orgaId = $session->get(self::SESSION_KEY);

        if (null !== $orgaId) {
            $orga = $this->orgaRepository->find($orgaId);
            if (null !== $orga && $this->userBelongsToOrganisation($user, $orga)) {
                return $orga;
            }

            // Clear stale organisation ID from session if it is invalid for the current user
            $session->remove(self::SESSION_KEY);
        }

        // Fallback to first organisation in collection
        if ($user instanceof User) {
            $organisations = $user->getOrganisations();
            if ($organisations->count() > 0) {
                return $organisations->first();
            }
        }

        return null;
    }

    /**
     * Set the current organisation for a user's session.
     * Also sets the transient property on the User entity.
     *
     * @throws InvalidArgumentException if the user does not belong to the organisation
     */
    public function setCurrentOrganisation(UserInterface $user, OrgaInterface $organisation): void
    {
        if (!$this->userBelongsToOrganisation($user, $organisation)) {
            throw new InvalidArgumentException(sprintf('User %s does not belong to organisation %s', $user->getId(), $organisation->getId()));
        }

        // Store in session
        $session = $this->requestStack->getSession();
        $session->set(self::SESSION_KEY, $organisation->getId());

        // Set transient property on user
        if ($user instanceof User) {
            $user->setCurrentOrganisation($organisation);
        }
    }

    /**
     * Clear the current organisation selection from the session.
     */
    public function clearCurrentOrganisation(): void
    {
        $session = $this->requestStack->getSession();
        $session->remove(self::SESSION_KEY);
    }

    /**
     * Initialize the transient currentOrganisation property on a User entity from session.
     * Called by CurrentOrganisationListener on each request.
     */
    public function initializeCurrentOrganisation(User $user): void
    {
        if (null !== $user->getCurrentOrganisation()) {
            return;
        }

        $session = $this->requestStack->getSession();
        $orgaId = $session->get(self::SESSION_KEY);

        if (null !== $orgaId) {
            $orga = $this->orgaRepository->find($orgaId);
            if (null !== $orga && $this->userBelongsToOrganisation($user, $orga)) {
                $user->setCurrentOrganisation($orga);
            }
        }
    }

    /**
     * Get all organisations this user belongs to.
     *
     * @return Collection<int, OrgaInterface>
     */
    public function getAvailableOrganisations(UserInterface $user): Collection
    {
        if ($user instanceof User) {
            return $user->getOrganisations();
        }

        return new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Check if user belongs to multiple organisations (multi-responsibility user).
     */
    public function hasMultipleOrganisations(UserInterface $user): bool
    {
        if ($user instanceof User) {
            return $user->hasMultipleOrganisations();
        }

        return false;
    }

    /**
     * Check if user needs to select an organisation (has multiple, none selected in session).
     */
    public function requiresOrganisationSelection(UserInterface $user): bool
    {
        if (!$this->hasMultipleOrganisations($user)) {
            return false;
        }

        $session = $this->requestStack->getSession();

        return !$session->has(self::SESSION_KEY);
    }

    /**
     * Find an organisation by its gwId (gateway identifier).
     */
    public function findOrganisationByGwId(string $gwId): ?OrgaInterface
    {
        return $this->orgaRepository->findOneBy(['gwId' => $gwId]);
    }

    /**
     * Find an existing organisation by gwId or create a new one.
     * Uses the gwId as the organisation name if no name is provided.
     *
     * WARNING: By default this method flushes immediately after creating a new organisation.
     * This is intentional for authentication flows where the org must exist before proceeding.
     * Set $flush to false if calling within an existing transaction to avoid breaking
     * transaction boundaries - the caller must then manage flushing.
     *
     * @param string      $gwId  The gateway identifier (stored as gwId on the organisation)
     * @param string|null $name  The organisation name (defaults to gwId if null)
     * @param bool        $flush Whether to flush immediately (default: true for standalone use)
     */
    public function findOrCreateOrganisation(string $gwId, ?string $name = null, bool $flush = true): OrgaInterface
    {
        $orga = $this->findOrganisationByGwId($gwId);

        if (null !== $orga) {
            return $orga;
        }

        // Create new organisation with gwId
        $orga = new Orga();
        $orga->setGwId($gwId);
        $orga->setName($name ?? $gwId);

        $this->entityManager->persist($orga);

        if ($flush) {
            $this->entityManager->flush();
        }

        return $orga;
    }

    /**
     * Check if a user belongs to a specific organisation.
     * Compares by ID to handle cases where org objects are different instances.
     */
    private function userBelongsToOrganisation(UserInterface $user, OrgaInterface $organisation): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        $targetId = $organisation->getId();
        foreach ($user->getOrganisations() as $userOrga) {
            if ($userOrga->getId() === $targetId) {
                return true;
            }
        }

        return false;
    }
}
