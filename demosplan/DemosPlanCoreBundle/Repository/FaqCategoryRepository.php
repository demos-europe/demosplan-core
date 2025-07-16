<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use DemosEurope\DemosplanAddon\Logic\ApiRequest\FluentRepository;
use demosplan\DemosPlanCoreBundle\Entity\FaqCategory;
use demosplan\DemosPlanCoreBundle\Entity\PlatformFaqCategory;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;
use UnexpectedValueException;

/**
 * @template-extends FluentRepository<FaqCategory>
 */
class FaqCategoryRepository extends FluentRepository
{
    /**
     * Get FaqCategory by id of a specific customer.
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getFaqCategory(string $id, Customer $customer): ?FaqCategory
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('faqCategory')
            ->from(FaqCategory::class, 'faqCategory')
            ->where('faqCategory.id = :id')
            ->andWhere('faqCategory.customer = :customer')
            ->setParameter('id', $id)
            ->setParameter('customer', $customer);

        return $query->getQuery()->getSingleResult();
    }

    /**
     * Get all FaqCategories of a specific customer.
     *
     * @return FaqCategory[]
     */
    public function getFaqCategoriesByCustomer(Customer $customer): array
    {
        try {
            $query = $this->getEntityManager()
                ->createQueryBuilder()
                ->select('faqCategory')
                ->from(FaqCategory::class, 'faqCategory')
                ->where('faqCategory.customer = :customer')
                ->setParameter('customer', $customer)
                ->orderBy('faqCategory.title');

            return $query->getQuery()->getResult();
        } catch (NoResultException $e) {
            $this->logger->error('Get FaqCategories failed.', [$e]);

            return [];
        }
    }

    /**
     * Get all static platformFaqCategories - same for all customers.
     *
     * @return PlatformFaqCategory[]
     *
     * @throws UnexpectedValueException
     */
    public function getCustomerIndependentPlatformFaqCategories(): array
    {
        return $this->getEntityManager()->getRepository(PlatformFaqCategory::class)->findBy([], ['title' => 'ASC']);
    }

    /**
     * Update or save FaqCategory.
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateCategory(FaqCategory $faqCategory): FaqCategory
    {
        $em = $this->getEntityManager();
        $em->persist($faqCategory);
        $em->flush();

        return $faqCategory;
    }

    /**
     * Delete a specific category if there are no related FAQs.
     *
     * @return bool Value is "false" if unsuccessfully deleted, otherwise "true"
     */
    public function deleteFaqCategory(FaqCategory $faqCategory): bool
    {
        try {
            $this->getEntityManager()->remove($faqCategory);
            $this->getEntityManager()->flush();
        } catch (Exception $e) {
            $this->logger->error('Delete FaqCategory failed', [$e]);

            return false;
        }

        return true;
    }
}
