<?php

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\ProdData;

use demosplan\DemosPlanCoreBundle\Entity\PlatformFaq;
use demosplan\DemosPlanCoreBundle\Entity\PlatformFaqCategory;
use Doctrine\Persistence\ObjectManager;

class LoadPlatformFaqData
{
    public function load(ObjectManager $manager)
    {
        $faqs = [];
        $roles = [];
        $firstFaqCategory = new PlatformFaqCategory();
        $firstFaqCategory->setTitle('Technische Voraussetzungen');
        $firstFaqCategory->setType('technische_voraussetzung');

        $firstFaq = new PlatformFaq();
        $firstFaq->setTitle('Was ist DiPlanBeteiligung?');
        $firstFaq->setCategory($firstFaqCategory);
        $firstFaq->setEnabled(true);
        $firstFaq->setRoles($roles);
        $firstFaq->setText('DiPlanBeteiligung ist ein Service, der Ihnen die digitale Beteiligung an Planungen, Unternehmen oder als Mitarbeiter*in einer Behörde bzw. Abhängig von Ihrer Rolle stehen Ihnen unterschiedliche Möglichkeiten zur Verfügung, um Ihre Stellungnahmen einzubringen.');

        $secondFaq = new PlatformFaq();
        $secondFaq->setTitle('Was ist DiPlanBeteiligung?');
        $secondFaq->setCategory($firstFaqCategory);
        $secondFaq->setEnabled(true);
        $secondFaq->setRoles($roles);
        $secondFaq->setText('DiPlanBeteiligung ist ein Service, der Ihnen die digitale Beteiligung an Planungen, Unternehmen oder als Mitarbeiter*in einer Behörde bzw. Abhängig von Ihrer Rolle stehen Ihnen unterschiedliche Möglichkeiten zur Verfügung, um Ihre Stellungnahmen einzubringen.');

        $faqs [] = [$firstFaq, $secondFaq];

    }

}
