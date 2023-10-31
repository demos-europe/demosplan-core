<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\Faq;
use demosplan\DemosPlanCoreBundle\Entity\FaqCategory;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadFaqData extends TestFixture implements DependentFixtureInterface
{
    final public const FAQ_GUEST = 'testFaqGuest';
    final public const FAQ_PLANNER = 'testFaqPlanner';
    final public const FAQ_PLANNER_BB = 'testFaqPlannerBB';

    public function load(ObjectManager $manager): void
    {
        /** @var Customer $customer */
        $customer = $this->getReference('testCustomer');

        $faqCategory1 = new FaqCategory();
        $faqCategory1->setTitle('Faq Kategorie Nummer 1');
        $faqCategory1->setType('oeb_bauleitplanung');
        $faqCategory1->setCustomer($customer);
        $faqCategory1->setCreateDate(new DateTime());
        $faqCategory1->setModifyDate(new DateTime());

        $manager->persist($faqCategory1);

        $faqCategory2 = new FaqCategory();
        $faqCategory2->setTitle('Faq Kategorie2');
        $faqCategory2->setType('technische_voraussetzung');
        $faqCategory2->setCustomer($customer);
        $faqCategory2->setCreateDate(new DateTime());
        $faqCategory2->setModifyDate(new DateTime());

        $manager->persist($faqCategory2);

        $faqCategory3 = new FaqCategory();
        $faqCategory3->setTitle('Faq Kategorie3');
        $faqCategory3->setType('bedienung');
        $faqCategory3->setCustomer($this->getReference('testCustomerBrandenburg'));
        $faqCategory3->setCreateDate(new DateTime());
        $faqCategory3->setModifyDate(new DateTime());

        $manager->persist($faqCategory3);

        $this->setReference('testCategoryFaq4', $faqCategory1);
        $this->setReference('testCategoryFaq5', $faqCategory2);
        $this->setReference('testCategoryFaq6', $faqCategory3);

        $faqGuest = new Faq();
        $faqGuest->setTitle('Häufige Frage Nummer 1');
        $faqGuest->setText('Ich bin eine häufige Frage.');
        $faqGuest->setEnabled(true);
        $faqGuest->setCreateDate(new DateTime());
        $faqGuest->setModifyDate(new DateTime());
        $faqGuest->setRoles([$this->getReference('testRoleGuest')]);
        $faqGuest->setCategory($faqCategory1);

        $manager->persist($faqGuest);

        $faqPlanner = new Faq();
        $faqPlanner->setTitle('Häufige Frage Nummer 3');
        $faqPlanner->setText('Ich bin eine dritte häufige Frage.');
        $faqPlanner->setEnabled(true);
        $faqPlanner->setCreateDate(new DateTime());
        $faqPlanner->setModifyDate(new DateTime());
        $faqPlanner->setRoles([$this->getReference('testRoleFP')]);
        $faqPlanner->setCategory($faqCategory2);

        $manager->persist($faqPlanner);

        $faqPlannerBB = new Faq();
        $faqPlannerBB->setTitle('Häufige Frage BB');
        $faqPlannerBB->setText('Ich bin eine häufige Frage in Brandenburg.');
        $faqPlannerBB->setEnabled(true);
        $faqPlannerBB->setCreateDate(new DateTime());
        $faqPlannerBB->setModifyDate(new DateTime());
        $faqPlannerBB->setRoles([$this->getReference('testRoleFP')]);
        $faqPlannerBB->setCategory($faqCategory3);

        $manager->persist($faqPlannerBB);

        $manager->flush();
        $this->setReference(self::FAQ_GUEST, $faqGuest);
        $this->setReference(self::FAQ_PLANNER, $faqPlanner);
        $this->setReference(self::FAQ_PLANNER_BB, $faqPlannerBB);
    }

    public function getDependencies()
    {
        return [
            LoadCustomerData::class,
        ];
    }
}
