<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Faq;

use DemosEurope\DemosplanAddon\Contracts\Entities\FaqCategoryInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\FaqInterface;
use demosplan\DemosPlanCoreBundle\Entity\Faq;
use demosplan\DemosPlanCoreBundle\Entity\FaqCategory;
use demosplan\DemosPlanCoreBundle\Entity\PlatformFaq;
use demosplan\DemosPlanCoreBundle\Entity\PlatformFaqCategory;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\ManualListSorter;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerHandler;
use demosplan\DemosPlanCoreBundle\Repository\FaqCategoryRepository;
use demosplan\DemosPlanCoreBundle\Repository\FaqRepository;
use demosplan\DemosPlanCoreBundle\Repository\PlatformFaqRepository;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use Exception;
use UnexpectedValueException;

class FaqService extends CoreService
{
    public function __construct(
        private readonly CustomerHandler $customerHandler,
        private readonly DqlConditionFactory $conditionFactory,
        private readonly FaqCategoryRepository $faqCategoryRepository,
        private readonly FaqRepository $faqRepository,
        private readonly ManualListSorter $manualListSorter,
        private readonly PlatformFaqRepository $platformFaqRepository,
        private readonly SortMethodFactory $sortMethodFactory
    ) {
    }

    /**
     * This is what the public should see when visiting the FAQ section.
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateFaqCategory(FaqCategory $faqCategory): FaqCategory
    {
        return $this->faqCategoryRepository->updateCategory($faqCategory);
    }

    public function deleteFaqCategory(FaqCategory $faqCategory): bool
    {
        return $this->faqCategoryRepository->deleteFaqCategory($faqCategory);
    }

    public function deleteFaq(Faq $faq): void
    {
        $this->faqRepository->deleteFaq($faq);
    }

    /**
     * Returns all categories.
     *
     * @return FaqCategory[]
     *
     * @throws CustomerNotFoundException
     */
    public function getFaqCategoriesOfCurrentCustomer(): array
    {
        $currentCustomer = $this->customerHandler->getCurrentCustomer();

        return $this->faqCategoryRepository->getFaqCategoriesByCustomer($currentCustomer);
    }

    /**
     * Get all platform-faq-categories sorted alphabetically by title.
     *
     * @return PlatformFaqCategory[]
     *
     * @throws UnexpectedValueException
     */
    public function getPlatformFaqCategories(): array
    {
        return $this->faqCategoryRepository->getCustomerIndependentPlatformFaqCategories();
    }

    /**
     * Return specific category of customer.
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getFaqCategory(string $id, Customer $customer): ?FaqCategory
    {
        return $this->faqCategoryRepository->getFaqCategory($id, $customer);
    }

    /**
     * Get FAQs of a given category.
     */
    public function getEnabledAndDisabledFaqList(FaqCategory $faqCategory): array
    {
        return $this->faqRepository->findBy([
            'faqCategory' => $faqCategory,
        ], [
            'title' => Criteria::ASC,
        ]);
    }

    /**
     * Get enabled FAQs of a given category.
     * takes the User-roles into account.
     *
     * @return array<int, FaqInterface>
     */
    public function getEnabledFaqList(FaqCategoryInterface $faqCategory, User $user): array
    {
        $roles = $user->isPublicUser() ? [Role::GUEST] : $user->getRoles();
        $categoryName = 'faqCategory';
        $repository = $this->faqRepository;

        if ($faqCategory instanceof PlatformFaqCategory) {
            $categoryName = 'platformFaqCategory';
            $repository = $this->platformFaqRepository;
        }
        $conditions = [
            $this->conditionFactory->propertyHasValue(1, ['enabled']),
            $this->conditionFactory->propertyHasValue($faqCategory, [$categoryName]),
            $this->conditionFactory->propertyHasAnyOfValues($roles, ['roles', 'code']),
        ];
        $sortMethod = $this->sortMethodFactory->propertyAscending(['title']);

        return $repository->getEntities($conditions, [$sortMethod]);
    }

    /**
     * Get all enabled FAQs of a given category ragardless of user-roles.
     *
     * @return array<int, FaqInterface>
     */
    public function getAllEnabledFaqForCategoryRegardlessOfUserRoles(FaqCategoryInterface $faqCategory): array
    {
        $categoryName = 'faqCategory';
        $className = Faq::class;

        if ($faqCategory instanceof PlatformFaqCategory) {
            $categoryName = 'platformFaqCategory';
            $className = PlatformFaq::class;
        }
        $conditions = [
            $this->conditionFactory->propertyHasValue(1, ['enabled']),
            $this->conditionFactory->propertyHasValue($faqCategory, [$categoryName]),
        ];
        $sortMethod = $this->sortMethodFactory->propertyAscending(['title']);

        return $this->entityFetcher->listEntitiesUnrestricted($className, $conditions, [$sortMethod]);
    }

    /**
     * Speichert die manuelle Listensortierung.
     *
     * @param string $type    Der Bezug unter dem die manuelle Sortierung gespeichert wurde. z.B. orga:{ident} oder user:{ident} / ident = ID ohne Klammer
     * @param string $context
     * @param string $sortIds
     *                        (Komma separierte Liste) / leer zum löschen
     *
     * @throws Exception
     */ // function setManualSort($ident, $context, $sortIds)

    public function setManualSortForGlobalContent($context, $sortIds, $type): bool
    {
        $sortIds = str_replace(' ', '', $sortIds);
        $data = [
            'ident'     => 'global',
            'context'   => $context,
            'namespace' => 'faq',
            'procedure' => 'global',
            'sortIdent' => $sortIds,
        ];

        return $this->manualListSorter->setManualSort($data['context'], $data);
    }

    /**
     * @param array<int, FaqInterface> $faqs
     *
     * @return array<int, FaqInterface>
     */
    public function orderFaqsByManualSortList(array $faqs, FaqCategoryInterface $faqCategory): array
    {
        // required for legacy reasons, since the method used can only operate with arrays
        $input = [];
        foreach ($faqs as $faq) {
            $input[] = [
                'faq' => $faq,
                'id'  => $faq->getId(),
            ];
        }
        $manualSortScope = '';
        $nameSpace = '';
        if ($faqs instanceof Faq) {
            $manualSortScope = 'faq:category:'.$faqCategory->getId();
            $nameSpace = 'faq';
        }
        if ($faqs instanceof PlatformFaq) {
            $manualSortScope = 'platformFaq:category:'.$faqCategory->getId();
            $nameSpace = 'faq';
        }
        $sorted = $this->manualListSorter->orderByManualListSort(
            $manualSortScope,
            'global',
            $nameSpace,
            $input,
            'id'
        );

        $output = [];
        foreach ($sorted['list'] as $item) {
            $output[] = $item['faq'];
        }

        return $output;
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateFaq(Faq $faq): Faq
    {
        return $this->faqRepository->updateFaq($faq);
    }

    public function getFaq(string $id): ?Faq
    {
        return $this->faqRepository->find($id);
    }

    public function findFaqCategoryByType(string $type): FaqCategory
    {
        $criteria = [];
        $currentCustomer = $this->customerHandler->getCurrentCustomer();
        $criteria['customer'] = $currentCustomer->getId();
        $criteria['type'] = $type;

        return $this->faqCategoryRepository->findOneBy($criteria);
    }
}
