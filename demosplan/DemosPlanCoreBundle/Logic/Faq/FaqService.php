<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Faq;

use demosplan\DemosPlanCoreBundle\Entity\Faq;
use demosplan\DemosPlanCoreBundle\Entity\FaqCategory;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\EntityFetcher;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\ManualListSorter;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerHandler;
use demosplan\DemosPlanCoreBundle\Repository\FaqCategoryRepository;
use demosplan\DemosPlanCoreBundle\Repository\FaqRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use EDT\ConditionFactory\ConditionFactoryInterface;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use EDT\Querying\Contracts\SortMethodFactoryInterface;
use Exception;

class FaqService extends CoreService
{
    /**
     * @var CustomerHandler
     */
    private $customerHandler;

    /**
     * @var ConditionFactoryInterface
     */
    private $conditionFactory;

    /**
     * @var SortMethodFactoryInterface
     */
    private $sortMethodFactory;

    /**
     * @var EntityFetcher
     */
    private $entityFetcher;
    /**
     * @var ManualListSorter
     */
    private $manualListSorter;
    /**
     * @var FaqCategoryRepository
     */
    private $faqCategoryRepository;
    /**
     * @var FaqRepository
     */
    private $faqRepository;

    public function __construct(
        CustomerHandler $customerHandler,
        DqlConditionFactory $conditionFactory,
        EntityFetcher $entityFetcher,
        FaqCategoryRepository $faqCategoryRepository,
        FaqRepository $faqRepository,
        ManualListSorter $manualListSorter,
        SortMethodFactory $sortMethodFactory
    ) {
        $this->conditionFactory = $conditionFactory;
        $this->customerHandler = $customerHandler;
        $this->entityFetcher = $entityFetcher;
        $this->faqCategoryRepository = $faqCategoryRepository;
        $this->faqRepository = $faqRepository;
        $this->manualListSorter = $manualListSorter;
        $this->sortMethodFactory = $sortMethodFactory;
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
        $condition = $this->conditionFactory->propertyHasValue($faqCategory, ['faqCategory']);
        $sortMethod = $this->sortMethodFactory->propertyAscending(['title']);

        return $this->entityFetcher->listEntitiesUnrestricted(Faq::class, [$condition], [$sortMethod]);
    }

    /**
     * Get enabled FAQs of a given category.
     *
     * @return array<int, Faq>
     */
    public function getEnabledFaqList(FaqCategory $faqCategory, User $user): array
    {
        $roles = $user->isPublicUser() ? [Role::GUEST] : $user->getRoles();
        $conditions = [
            $this->conditionFactory->propertyHasValue(1, ['enabled']),
            $this->conditionFactory->propertyHasValue($faqCategory, ['faqCategory']),
            $this->conditionFactory->propertyHasAnyOfValues($roles, ['roles', 'code']),
        ];
        $sortMethod = $this->sortMethodFactory->propertyAscending(['title']);

        return $this->entityFetcher->listEntitiesUnrestricted(Faq::class, $conditions, [$sortMethod]);
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
     * @param array<int, Faq> $faqs
     *
     * @return array<int, Faq>
     */
    public function orderFaqsByManualSortList(array $faqs, FaqCategory $faqCategory): array
    {
        // required for legacy reasons, since the method used can only operate with arrays
        $input = [];
        foreach ($faqs as $faq) {
            $input[] = [
                'faq' => $faq,
                'id'  => $faq->getId(),
            ];
        }

        $sorted = $this->manualListSorter->orderByManualListSort(
            'faq:category:'.$faqCategory->getId(),
            'global',
            'faq',
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
        $currentCustomer = $this->customerHandler->getCurrentCustomer();
        $criteria['customer'] = $currentCustomer->getId();
        $criteria['type'] = $type;

        return $this->faqCategoryRepository->findOneBy($criteria);
    }
}
