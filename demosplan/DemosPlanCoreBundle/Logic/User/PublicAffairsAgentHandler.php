<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\User;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\PublicAffairsAgentNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\CoreHandler;
use Exception;

class PublicAffairsAgentHandler extends CoreHandler
{
    protected function getOrgaHandler(): OrgaHandler
    {
        return $this->orgaHandler;
    }

    public function __construct(private readonly OrgaHandler $orgaHandler, MessageBagInterface $messageBag)
    {
        parent::__construct($messageBag);
    }

    /**
     * @param list<array{type: non-empty-string, id: non-empty-string}> $resourceLinkage
     *
     * @return Orga[]
     *
     * @throws InvalidArgumentException            thrown if at least one item in the given
     *                                             linkage object is not of type 'publicAffairsAgent'
     * @throws PublicAffairsAgentNotFoundException thrown if at least one PublicAffairsAgent with the given ID was not
     *                                             found
     * @throws Exception
     */
    public function getFromResourceLinkage(array $resourceLinkage): array
    {
        return array_map(
            fn (array $resourceIdentifierObject): Orga => $this->getFromResourceIdentifierObject($resourceIdentifierObject['id'], $resourceIdentifierObject['type']),
            $resourceLinkage
        );
    }

    /**
     * @param non-empty-string $id
     * @param non-empty-string $actualType
     *
     * @return Orga PublicAffairsAgent
     *
     * @throws InvalidArgumentException            Thrown if the given ResourceIdentifierObject is not of type 'publicAffairsAgent'
     * @throws PublicAffairsAgentNotFoundException thrown if no PublicAffairsAgent with the given ID was found
     * @throws Exception
     */
    public function getFromResourceIdentifierObject(string $id, string $actualType): Orga
    {
        $expectedType = 'publicAffairsAgent';
        if ($expectedType !== $actualType) {
            throw new InvalidArgumentException("expected '$expectedType' for all resourceIdentifierObjects, got '$actualType'");
        }

        return $this->getFromId($id);
    }

    /**
     * @return Orga With the type 'OPSORG' (Toeb/PublicAgency/PublicAffairsAgent)
     *
     * @throws PublicAffairsAgentNotFoundException thrown if no PublicAffairsAgent with the given ID was found
     * @throws Exception
     */
    public function getFromId(string $publicAffairsAgentId): Orga
    {
        $publicAffairsAgent = $this->getOrgaHandler()->getOrga($publicAffairsAgentId);

        if (!$publicAffairsAgent instanceof Orga || !$this->isPublicAffairsAgent($publicAffairsAgent)) {
            throw PublicAffairsAgentNotFoundException::createFromId($publicAffairsAgentId);
        }

        return $publicAffairsAgent;
    }

    /**
     * Note: do not use Orga::getTypes to check if an Orga is a PublicAffairsAgent. Instead check if any User has the GPSORG
     * role. Orga::getTypes really does not matter at all, the truth is stored in the group roles of the users.
     */
    public function isPublicAffairsAgent(Orga $orga): bool
    {
        // ArrayCollection::contains may return false due to lazy loading,
        // using collect we retrieve all elements before using
        // laravels Collection::contains.
        return collect($orga->getAllUsers())->contains(
            static fn (User $user) => $user->isPublicAgency()
        );
    }
}
