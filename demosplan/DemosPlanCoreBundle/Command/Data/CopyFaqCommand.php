<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Data;

use DemosEurope\DemosplanAddon\Contracts\Entities\FaqCategoryInterface;
use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Command\Helpers\Helpers;
use demosplan\DemosPlanCoreBundle\Entity\Faq;
use demosplan\DemosPlanCoreBundle\Entity\FaqCategory;
use demosplan\DemosPlanCoreBundle\Repository\FaqCategoryRepository;
use demosplan\DemosPlanCoreBundle\Repository\FaqRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class CopyFaqCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:data:copy-faq';
    protected static $defaultDescription = 'Copies the FAQ from one customer to another.';

    protected QuestionHelper $helper;

    public function __construct(
        private readonly Helpers $helpers,
        private readonly FaqCategoryRepository $faqCategoryRepository,
        private readonly FaqRepository $faqRepository,
        private readonly EntityManagerInterface $entityManager,
        ParameterBagInterface $parameterBag,
        ?string $name = null
    ) {
        parent::__construct($parameterBag, $name);
        $this->helper = new QuestionHelper();
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, $output);
        $output->writeln('Copying FAQ from one customer to another.');
        $output->writeln('Choose customer to copy from');
        $fromCustomer = $this->helpers->askCustomer($input, $output);
        $output->writeln('Choose customer to copy to');
        $toCustomer = $this->helpers->askCustomer($input, $output);

        try {
            $faqCategories = collect($this->faqCategoryRepository->getFaqCategoriesByCustomer($fromCustomer));
            // only "valid" faq categories should be copied
            $faqCategories = $faqCategories->filter(
                static fn (FaqCategory $faqCategory) => in_array($faqCategory->getType(), FaqCategoryInterface::FAQ_CATEGORY_TYPES_MANDATORY, true)
                    || $faqCategory->isCustom()
            );

            $categoryCount = 0;
            $faqCount = 0;
            foreach ($faqCategories as $faqCategory) {
                ++$categoryCount;
                $copiedFaqCategory = new FaqCategory();
                $copiedFaqCategory->setCustomer($toCustomer);
                $copiedFaqCategory->setTitle($faqCategory->getTitle());
                $copiedFaqCategory->setType($faqCategory->getType());
                $this->entityManager->persist($copiedFaqCategory);

                $faqs = $this->faqRepository->findBy(['faqCategory' => $faqCategory]);
                foreach ($faqs as $faq) {
                    ++$faqCount;
                    $copiedFaq = new Faq();
                    $copiedFaq->setCategory($copiedFaqCategory);
                    $copiedFaq->setEnabled($faq->getEnabled());
                    $copiedFaq->setRoles($faq->getRoles()->toArray());
                    $copiedFaq->setText($faq->getText());
                    $copiedFaq->setTitle($faq->getTitle());
                    $this->entityManager->persist($copiedFaq);
                }
            }

            $this->entityManager->flush();

            $output->info('Copied '.$categoryCount.' FAQ categories with '.$faqCount.' FAQs.');
            $timeSaved = ceil(($categoryCount * 30 + $faqCount * 45) / 60);
            $output->success("Faq where successfully copied. Saved approx $timeSaved minutes and lots of nerves.");

            return Command::SUCCESS;
        } catch (Exception $e) {
            // Print Exception
            $output->error('Something went wrong during faq copy: '.$e->getMessage());
        }

        return Command::FAILURE;
    }
}
