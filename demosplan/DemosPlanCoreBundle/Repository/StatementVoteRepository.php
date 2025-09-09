<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementVote;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\ORMException;
use Exception;
use InvalidArgumentException;

/**
 * @template-extends CoreRepository<StatementVote>
 */
class StatementVoteRepository extends CoreRepository implements ArrayInterface, ObjectInterface
{
    /**
     * Get Entity by Id.
     *
     * @param string $entityId
     *
     * @return StatementVote
     */
    public function get($entityId)
    {
        return $this->findOneBy(['id' => $entityId]);
    }

    /**
     * Add Entity to database.
     *
     * @return StatementVote
     *
     * @throws Exception
     */
    public function add(array $data)
    {
        try {
            if (array_key_exists('statement', $data)) {
                $vote = new StatementVote();
                $vote = $this->generateObjectValues($vote, $data);

                return $this->addObject($vote);
            } else {
                throw new InvalidArgumentException('Trying to add a StatementVote without related Statement.');
            }
        } catch (Exception $e) {
            $this->logger->warning('Create StatementVote failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Add Entityobject to database.
     *
     * @param object $statementVote
     *
     * @return StatementVote
     *
     * @throws Exception
     */
    public function addObject($statementVote)
    {
        try {
            $manager = $this->getEntityManager();
            $manager->persist($statementVote);
            $manager->flush();

            return $this->get($statementVote);
        } catch (Exception $e) {
            $this->logger->warning('Add StatementVoteObject failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Update Entity.
     *
     * @param string $entityId
     *
     * @return StatementVote
     *
     * @throws Exception
     */
    public function update($entityId, array $data)
    {
        try {
            $vote = $this->get($entityId);
            $vote = $this->generateObjectValues($vote, $data);

            return $this->updateObject($vote);
        } catch (Exception $e) {
            $this->logger->warning(
                'Update StatementVote failed.', [$e]);
            throw $e;
        }
    }

    /**
     * Update Object.
     *
     * @param StatementVote $statementVote
     *
     * @return StatementVote
     *
     * @throws Exception
     */
    public function updateObject($statementVote)
    {
        try {
            $em = $this->getEntityManager();
            $em->persist($statementVote);
            $em->flush();

            return $statementVote;
        } catch (Exception $e) {
            $this->logger->warning('Update StatementVote failed. Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Delete Entity.
     *
     * @param StatementVote $toDelete
     *
     * @return bool
     *
     * @throws EntityNotFoundException
     */
    public function delete($toDelete)
    {
        if (is_null($toDelete)) {
            $this->logger->warning('Delete statementVote failed: Given ID not found.');
            throw new EntityNotFoundException('Delete statementVote failed: Given ID not found.');
        }
        try {
            $this->getEntityManager()->remove($toDelete);
            $this->getEntityManager()->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->error('Delete statementVote failed: ', [$e]);
        }

        return false;
    }

    /**
     * Set Objectvalues by array
     * Set "@param" according to specific entity to get autocompletion.
     *
     * @param StatementVote|null $entity
     *
     * @return StatementVote
     *
     * @throws ORMException
     */
    public function generateObjectValues($entity, array $data)
    {
        if (is_null($entity)) {
            $entity = new StatementVote();
        }

        $user = null;
        if (array_key_exists('user', $data)) {
            $user = $data['user'] instanceof User ? $data['user'] : $this->getEntityManager()->getReference(User::class, $data['user']);
        }

        if (!$data['statement'] instanceof Statement) {
            $data['statement'] = $this->getEntityManager()->getReference(Statement::class, $data['statement']);
        }

        $entity->setStatement($data['statement']);
        $firstName = array_key_exists('firstName', $data) ? $data['firstName'] : '';
        $lastName = array_key_exists('lastName', $data) ? $data['lastName'] : '';
        $fullUserName = $firstName.''.$lastName;

        // set username from user object in case of given user object, else set from giving data.
        if ($user instanceof User) {
            $entity->setUser($user);
            $firstName = $user->getFirstname();
            $lastName = $user->getLastname();
            $fullUserName = $user->getFullname();
        }

        $entity->setLastName($firstName);
        $entity->setFirstName($lastName);
        $entity->setUserName($fullUserName);

        if (array_key_exists('author_name', $data)) {
            $entity->setUserName($data['author_name']);
            $entity->setLastName($data['author_name']);
        }

        if (array_key_exists('manual', $data)) {
            $entity->setManual($data['manual']);
        }

        if (array_key_exists('active', $data)) {
            $entity->setActive($data['active']);
        }

        if (array_key_exists('deleted', $data)) {
            $entity->setDeleted($data['deleted']);
        }

        if ($user instanceof User) {
            $entity->setOrganisationName($user->getOrgaName());
        } elseif (array_key_exists('orga_name', $data)) {
            $entity->setOrganisationName($data['orga_name']);
        }

        if ($user instanceof User && null !== $user->getDepartment()) {
            $entity->setDepartmentName($user->getDepartment()->getName());
        } elseif (array_key_exists('orga_department_name', $data)) {
            $entity->setDepartmentName($data['orga_department_name']);
        }

        if ($user instanceof User) {
            $entity->setUserMail($user->getEmail());
        } elseif (array_key_exists('email', $data)) {
            $entity->setUserMail($data['email']);
        }

        if ($user instanceof User) {
            $entity->setUserPostcode($user->getPostalcode());
        } elseif (array_key_exists('postalcode', $data)) {
            $entity->setUserPostcode($data['postalcode']);
        }

        if ($user instanceof User) {
            $entity->setUserCity($user->getCity());
        } elseif (array_key_exists('orga_city', $data)) {
            $entity->setUserCity($data['orga_city']);
        }

        if ($user instanceof User) {
            $createdByCitizen = User::ANONYMOUS_USER_ORGA_ID === $user->getOrga()->getId();
            $entity->setCreatedByCitizen($createdByCitizen);
        } elseif (array_key_exists('role', $data)) {
            $entity->setCreatedByCitizen('0' === $data['role']);
        }

        return $entity;
    }

    /**
     * Returns all Votes of the given user.
     *
     * @param string $userId
     * @param bool   $deleted
     * @param bool   $active
     *
     * @return array
     *
     * @throws Exception
     */
    public function getByUserId($userId, $deleted = false, $active = true)
    {
        try {
            return $this->findBy(['user' => $userId, 'deleted' => $deleted, 'active' => $active]);
        } catch (Exception $e) {
            $this->logger->warning('Get Statementvotes failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Overrides all user data of the StatementVotes of the given user.
     *
     * @param string $userId - Identifies the User, whose StatementVotes will be cleared
     *
     * @return bool - false if Exception occurs, otherwise true
     *
     * @throws Exception
     */
    public function clearByUserId($userId)
    {
        try {
            $manager = $this->getEntityManager();
            /** @var StatementVote[] $votes */
            $votes = $this->findBy(['user' => $userId]);

            foreach ($votes as $vote) {
                $vote->setUser(null);
                $vote->setFirstName('');
                $vote->setLastName('');
                $vote->setOrganisationName(null);
                $vote->setDepartmentName(null);
                $vote->setUserName(null);
                $vote->setUserMail(null);
                $vote->setUserPostcode(null);
                $vote->setUserCity(null);
                $vote->setDeletedDate(new DateTime());
                $manager->persist($vote);
            }
            $manager->flush();
        } catch (Exception $e) {
            $this->logger->warning('Clear StatementVotes by userId failed Message: ', [$e]);
            throw $e;
        }

        return true;
    }

    /**
     * @param CoreEntity $entity
     */
    public function deleteObject($entity): never
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }
}
