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
use DemosEurope\DemosplanAddon\Contracts\Handler\FaqHandlerInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\Category;
use demosplan\DemosPlanCoreBundle\Entity\Faq;
use demosplan\DemosPlanCoreBundle\Entity\FaqCategory;
use demosplan\DemosPlanCoreBundle\Entity\PlatformFaqCategory;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\FaqNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\ContentService;
use demosplan\DemosPlanCoreBundle\Logic\CoreHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;
use demosplan\DemosPlanCoreBundle\Repository\RoleRepository;
use demosplan\DemosPlanCoreBundle\ResourceTypes\FaqResourceType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;
use Illuminate\Support\Collection;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use UnexpectedValueException;

class FaqHandler extends CoreHandler implements FaqHandlerInterface
{
    /**
     * @var ContentService
     */
    protected $contentService;

    public function __construct(
        MessageBagInterface $messageBag,
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $entityManager,
        private readonly FaqResourceType $faqResourceType,
        private readonly FaqService $faqService,
        private readonly CustomerHandler $customerHandler,
        private readonly RoleHandler $roleHandler,
        private readonly RoleRepository $roleRepository,
        ContentService $contentService,
        private readonly ValidatorInterface $validator
    ) {
        parent::__construct($messageBag);
        $this->contentService = $contentService;
    }

    /**
     * Get all (enabled and disabled) faqs of a category.
     *
     * @return array<int, FaqInterface>
     */
    public function getEnabledAndDisabledFaqList(FaqCategory $faqCategory): array
    {
        return $this->faqService->getEnabledAndDisabledFaqList($faqCategory);
    }

    /**
     * Gets enabled faqs of a category.
     * takes user-roles into account.
     *
     * @return array<int, FaqInterface>
     */
    public function getEnabledFaqList(FaqCategoryInterface $faqCategory, User $user): array
    {
        return $this->faqService->getEnabledFaqList($faqCategory, $user);
    }

    /**
     * Get all enabled faqs of a category regardless of user role restrictions.
     *
     * @return array<int, FaqInterface>
     */
    public function getAllEnabledFaqsRegardlessOfUserRoleRestrictions(FaqCategoryInterface $faqCategory): array
    {
        return $this->faqService->getAllEnabledFaqForCategoryRegardlessOfUserRoles($faqCategory);
    }

    /**
     * @param string $categoryId - Identify the category to delete
     *
     * @throws CustomerNotFoundException
     * @throws MessageBagException
     */
    public function getFaqCategory($categoryId): ?FaqCategory
    {
        $currentCustomer = $this->customerHandler->getCurrentCustomer();

        $category = $this->getFaqService()->getFaqCategory($categoryId, $currentCustomer);

        if (is_null($category)) {
            $this->logger->warning('Category with ID: '.$categoryId.' not found.');
            $this->getMessageBag()->add('warning', 'category.not.found');
        }

        return $category;
    }

    /**
     * @throws CustomerNotFoundException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateFaqCategory(FaqCategory $faqCategory): FaqCategory
    {
        $this->ensureThatFaqCategoryBelongsToCurrentCustomer($faqCategory);

        return $this->getFaqService()->updateFaqCategory($faqCategory);
    }

    /**
     * User may only view (and Admins may only view and edit) FAQ categories of the current customer.
     *
     * @throws CustomerNotFoundException
     * @throws AccessDeniedException
     */
    private function ensureThatFaqCategoryBelongsToCurrentCustomer(FaqCategory $faqCategory): void
    {
        $currentCustomer = $this->customerHandler->getCurrentCustomer();
        if ($faqCategory->getCustomer() !== $currentCustomer) {
            $message = 'FaqCategory "%s" with the title "%s" does not belong '.
                'to current customer ("%s") and may thus not be edited.';

            throw new AccessDeniedException(sprintf($message, $faqCategory->getId(), $faqCategory->getTitle(), $currentCustomer->getName()));
        }
    }

    /**
     * Creates a new Category and checks for mandatory fields.
     *
     * @throws Exception
     */
    public function createFaqCategory(array $data): FaqCategory
    {
        try {
            if (!array_key_exists('r_category_title', $data) || '' === trim((string) $data['r_category_title'])) {
                throw new UnexpectedValueException('FaqCategory title field missing or left blank.');
            }
            $data['r_category_title'] = trim((string) $data['r_category_title']);

            $faqCategory = new FaqCategory();
            $faqCategory->setTitle($data['r_category_title']);
            $faqCategory->setCustomer($this->customerHandler->getCurrentCustomer());
            // We need to identify categories created by some users
            $faqCategory->setType('custom_category');

            $this->faqService->updateFaqCategory($faqCategory);

            return $faqCategory;
        } catch (UnexpectedValueException $e) {
            $this->getMessageBag()->add('error', 'error.no.title.given');
            throw $e;
        } catch (Exception $e) {
            $this->logger->warning('Fehler beim Anlegen einer Kategorie: ', [$e]);
            throw $e;
        }
    }

    /**
     * Add or update Faq.
     *
     * @param array $data
     *
     * @throws CustomerNotFoundException
     * @throws MessageBagException
     */
    public function addOrUpdateFaq($data, ?Faq $faq = null): ?Faq
    {
        // improve:
        // Sanitize and validate fields
        $mandatoryErrors = false;
        if (!array_key_exists('r_enable', $data) || '' === trim((string) $data['r_enable'])) {
            $mandatoryErrors = true;
            $this->getMessageBag()->add(
                'warning',
                'error.mandatoryfield',
                ['name' => $this->translator->trans('status')]
            );
        }
        if (!array_key_exists('r_group_code', $data)) {
            $mandatoryErrors = true;
            $this->getMessageBag()->add(
                'warning',
                'error.mandatoryfield',
                ['name' => $this->translator->trans('visible')]
            );
        }
        if (!array_key_exists('r_title', $data) || '' === trim((string) $data['r_title'])) {
            $mandatoryErrors = true;
            $this->getMessageBag()->add(
                'warning',
                'error.mandatoryfield',
                ['name' => $this->translator->trans('heading')]
            );
        }
        if (!array_key_exists('r_text', $data) || '' === trim((string) $data['r_text'])) {
            $mandatoryErrors = true;
            $this->getMessageBag()->add(
                'warning',
                'error.mandatoryfield',
                ['name' => $this->translator->trans('text')]
            );
        }
        if (!array_key_exists('r_category_id', $data)) {
            $mandatoryErrors = true;
            $this->getMessageBag()->add(
                'confirm', 'error.mandatoryfield',
                ['name' => $this->translator->trans('category')]
            );
        }

        if (true === $mandatoryErrors) {
            return null;
        }
        if (255 < strlen((string) $data['r_title'])) {
            $data['r_title'] = substr((string) $data['r_title'], 0, 255);
            $this->getMessageBag()->add('warning', 'warning.faq.title.tooLong');
        }

        // write fields into object
        if (!$faq instanceof Faq) {
            $faq = new Faq();
        }
        $faq->setTitle($data['r_title']);
        $faq->setText($data['r_text']);
        if ('1' == $data['r_enable']) {
            $faq->setEnabled(true);
        } else {
            $faq->setEnabled(false);
        }
        $roles = $this->roleHandler->getUserRolesByGroupCodes($data['r_group_code']);
        $faq->setRoles($roles);
        $currentCustomer = $this->customerHandler->getCurrentCustomer();
        $category = $this->faqService->getFaqCategory($data['r_category_id'], $currentCustomer);
        if ($category instanceof FaqCategory) {
            $faq->setCategory($category);
        }

        return $this->faqService->updateFaq($faq);
    }

    public function getFaq(string $id): ?Faq
    {
        return $this->faqService->getFaq($id);
    }

    /**
     * update Faq.
     */
    public function updateFAQ(Faq $faq): Faq
    {
        return $this->faqService->updateFaq($faq);
    }

    /**
     * Returns all categories.
     *
     * @return FaqCategory[]
     *
     * @throws CustomerNotFoundException
     */
    public function getAllCategoriesOfCurrentCustomer(): array
    {
        return $this->getFaqService()->getFaqCategoriesOfCurrentCustomer();
    }

    /**
     * Returns all categories.
     */
    public function deleteFaq(Faq $faq): void
    {
        $this->getFaqService()->deleteFaq($faq);
    }

    /**
     * Get filtered faq-categories sorted alphabetically by title.
     *
     * @param string[] $categoryTypeNamesToInclude
     *
     * @return Collection<Category> Collection of Category entities
     *
     * @throws CustomerNotFoundException
     */
    public function getCustomFaqCategoriesByNamesOrCustom(array $categoryTypeNamesToInclude): Collection
    {
        $allFaqCategories = collect($this->getAllCategoriesOfCurrentCustomer());

        // filter: custom categories only
        return $allFaqCategories->filter(
            static fn (FaqCategory $faqCategory) => in_array($faqCategory->getType(), $categoryTypeNamesToInclude, true) || $faqCategory->isCustom()
        );
    }

    /**
     * Get all platform-faq-categories sorted alphabetically by title.
     *
     * @return Collection<PlatformFaqCategory>
     *
     * @throws UnexpectedValueException
     */
    public function getPlatformFaqCategories(): Collection
    {
        return collect($this->faqService->getPlatformFaqCategories());
    }

    /**
     * Get all faqs and sort by category into array.
     *
     * @param Collection $categories a collection of {@link Category categories}
     *
     * @return array<string, array{id: string, label: string, faqlist: list<FaqInterface>}>
     */
    public function convertIntoTwigFormat(Collection $categories, User $user): array
    {
        // get all faqs and sort by category into array:
        $convertedResult = [];
        foreach ($categories as $category) {
            $faqList = $this->getEnabledFaqList($category, $user);

            $faqList = $this->orderFaqsByManualSortList($faqList, $category);
            foreach ($faqList as $faq) {
                $categoryId = $faq->getCategory()->getId();
                $categoryTitle = $faq->getCategory()->getTitle();

                $convertedResult[$categoryId]['id'] = $categoryId;
                $convertedResult[$categoryId]['label'] = $categoryTitle;
                $convertedResult[$categoryId]['faqlist'][] = $faq;
            }
        }

        return $convertedResult;
    }

    /**
     * Delete the related category of the given Id if there are no related content.
     *
     * @param FaqCategory $faqCategory Identify the category to delete
     *
     * @return bool - false if unsuccessfully deleted, otherwise true
     *
     * @throws Exception
     */
    public function deleteFaqCategory(FaqCategory $faqCategory): bool
    {
        $successfullyDeleted = false;
        try {
            $categoryTitle = $faqCategory->getTitle();

            if (0 !== count($this->faqService->getEnabledAndDisabledFaqList($faqCategory))) {
                $this->getMessageBag()->add(
                    'warning',
                    'category.delete.deny.because.of.related.content',
                    ['title' => $categoryTitle]
                );

                return false;
            }

            $successfullyDeleted = $this->faqService->deleteFaqCategory($faqCategory);
            if (false === $successfullyDeleted) {
                $this->getMessageBag()->add(
                    'warning',
                    'category.delete.unsuccessful',
                    ['title' => $categoryTitle]
                );
            } else {
                $this->getMessageBag()->add(
                    'confirm',
                    'category.delete.successful',
                    ['title' => $categoryTitle]
                );
            }
        } catch (Exception $e) {
            $this->logger->warning('Error on delete FaqCategory: ', [$e]);
        }

        return $successfullyDeleted;
    }

    /**
     * @throws FaqNotFoundException
     */
    public function deleteFaqById(string $faqId): void
    {
        $faq = $this->getFaq($faqId);
        if (null === $faq) {
            throw FaqNotFoundException::createFromId($faqId);
        }
        $this->deleteFaq($faq);
    }

    public function findFaqCategoryByType(string $typeName): FaqCategory
    {
        return $this->getFaqService()->findFaqCategoryByType($typeName);
    }

    protected function getFaqService(): FaqService
    {
        return $this->faqService;
    }

    // todo please update and use this method

    /**
     * @param string $title
     *
     * @return bool
     */
    public function isCategoryTitleUnique($title)
    {
        // check for already existing category by
        $category = $this->entityManager
            ->getRepository(Category::class)
            ->findOneBy(['title' => $title]);

        return false === is_null($category);
    }

    /**
     * Saves the manual sort order of FAQ items.
     *
     * @param string $categoryId
     * @param string $sortIds    comma separated list of FAQ item IDs or an empty string to trigger deletion
     *
     * @throws Exception
     */
    public function setManualSort($categoryId, $sortIds): bool
    {
        if ('' == $sortIds) {
            return false;
        }

        if ('delete' === $sortIds) {
            $sortIds = '';
        }
        $context = 'faq:category:'.$categoryId;

        return $this->faqService->setManualSortForGlobalContent($context, $sortIds, 'faq');
    }

    /**
     * @param array<int, FaqInterface> $faqs
     *
     * @return array<int, FaqInterface>
     */
    public function orderFaqsByManualSortList(array $faqs, FaqCategoryInterface $faqCategory): array
    {
        return $this->faqService->orderFaqsByManualSortList($faqs, $faqCategory);
    }
}
