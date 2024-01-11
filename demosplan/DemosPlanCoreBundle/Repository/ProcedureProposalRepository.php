<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureProposal;
use demosplan\DemosPlanCoreBundle\Exception\ProcedureProposalNotFound;
use Doctrine\Common\Collections\ArrayCollection;
use Exception;
use InvalidArgumentException;
use RuntimeException;

/**
 * @template-extends CoreRepository<ProcedureProposal>
 */
class ProcedureProposalRepository extends CoreRepository
{
    /**
     * Get Entity by Id.
     *
     * @param string $procedureProposalId
     *
     * @return ProcedureProposal never null
     *
     * @throws ProcedureProposalNotFound
     */
    public function getProcedureProposal($procedureProposalId): ProcedureProposal
    {
        $procedureProposal = $this->findOneBy(['id' => $procedureProposalId]);
        if (!$procedureProposal instanceof ProcedureProposal) {
            throw ProcedureProposalNotFound::createFromId($procedureProposalId);
        }

        return $procedureProposal;
    }

    public function addObject(ProcedureProposal $procedureProposal): ProcedureProposal
    {
        try {
            if (null === $procedureProposal->getName()) {
                throw new InvalidArgumentException('Trying to add a ProcedureProposal without name');
            }

            $em = $this->getEntityManager();
            $procedureProposal->setAdditionalExplanation($this->sanitize(
                $procedureProposal->getAdditionalExplanation()
            ));
            $em->persist($procedureProposal);
            $em->flush();
        } catch (Exception $e) {
            $this->getLogger()->error('Add ProcedureProposal failed: ', [$e]);
            throw new RuntimeException('Could not add ProcedureProposal.', 0, $e);
        }

        return $procedureProposal;
    }

    /**
     * Delete Entity.
     */
    public function delete(ProcedureProposal $procedureProposal): bool
    {
        try {
            $this->getEntityManager()->remove($procedureProposal);
            $this->getEntityManager()->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->error('Delete ProcedureProposal failed: ', [$e]);
        }

        return false;
    }

    /**
     * Set object values by array.
     */
    public function generateObjectValues(ProcedureProposal $procedureProposal, array $data): ProcedureProposal
    {
        if (array_key_exists('name', $data)) {
            $procedureProposal->setName($data['name']);
        }

        if (array_key_exists('description', $data)) {
            $procedureProposal->setDescription($data['description']);
        }

        if (array_key_exists('coordinate', $data)) {
            $procedureProposal->setCoordinate($data['coordinate']);
        }

        if (array_key_exists('additionalExplanation', $data)) {
            $procedureProposal->setAdditionalExplanation($data['additionalExplanation']);
        }

        if (array_key_exists('user', $data)) {
            $procedureProposal->setUser($data['user']);
        }

        if (array_key_exists('status', $data)) {
            $procedureProposal->setStatus($data['status']);
        }

        if (array_key_exists('uploadedFiles', $data)) {
            $procedureProposal->setFiles(new ArrayCollection($data['uploadedFiles']));
        }

        return $procedureProposal;
    }

    /**
     * Update Entity.
     *
     * @return ProcedureProposal|false
     */
    public function updateObject(ProcedureProposal $procedureProposal)
    {
        try {
            $em = $this->getEntityManager();
            $procedureProposal->setAdditionalExplanation($this->sanitize(
                $procedureProposal->getAdditionalExplanation(),
                [$this->obscureTag]
            ));
            $em->persist($procedureProposal);
            $em->flush();
        } catch (Exception $e) {
            $this->getLogger()->error('Update ProcedureProposal failed: ', [$e]);

            return false;
        }

        return $procedureProposal;
    }

    public function getAllOrderedByDate(): array
    {
        $builder = $this->getEntityManager()->createQueryBuilder();

        $query = $builder
            ->select('proposal')
            ->from(ProcedureProposal::class, 'proposal')
            ->orderBy('proposal.createdDate', 'DESC')
            ->getQuery();

        return $query->getResult();
    }
}
