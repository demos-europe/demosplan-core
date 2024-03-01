<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementVote;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadStatementVoteData extends TestFixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $testStatement1 = $this->getReference('testStatement1');
        $testStatement2 = $this->getReference('testStatement2');
        $testStatement3 = $this->getReference('testStatementOtherOrga');
        $testStatement4 = $this->getReference('testStatementAssigned10');
        /** @var User $testUser2 */
        $testUser2 = $this->getReference('testUser2');
        /** @var User $testUser3 */
        $testUser3 = $this->getReference(LoadUserData::TEST_USER_CITIZEN);

        $statementVote1 = new StatementVote();
        $statementVote1->setStatement($testStatement1);
        $statementVote1->setUser($testUser2);
        $statementVote1->setFirstName($testUser2->getFirstname());
        $statementVote1->setLastName($testUser2->getLastname());
        $statementVote1->setActive(true);
        $statementVote1->setDeleted(false);
        $statementVote1->setManual(false);

        $manager->persist($statementVote1);
        $this->setReference('testStatementVote1', $statementVote1);

        $statementVote2 = new StatementVote();
        $statementVote2->setStatement($testStatement2);
        $statementVote2->setUser($testUser2);
        $statementVote2->setFirstName($testUser2->getFirstname());
        $statementVote2->setLastName($testUser2->getLastname());
        $statementVote2->setActive(true);
        $statementVote2->setDeleted(false);
        $statementVote2->setManual(false);

        $manager->persist($statementVote2);
        $this->setReference('testStatementVote2', $statementVote2);

        $statementVote3 = new StatementVote();
        $statementVote3->setStatement($testStatement1);
        $statementVote3->setUser($testUser3);
        $statementVote3->setFirstName($testUser3->getFirstname());
        $statementVote3->setLastName($testUser3->getLastname());
        $statementVote3->setActive(true);
        $statementVote3->setDeleted(false);
        $statementVote3->setManual(false);

        $manager->persist($statementVote3);
        $this->setReference('testStatementVote3', $statementVote3);

        $statementVote4 = new StatementVote();
        $statementVote4->setStatement($testStatement3);
        $statementVote4->setUser($testUser2);
        $statementVote4->setFirstName($testUser2->getFirstname());
        $statementVote4->setLastName($testUser2->getLastname());
        $statementVote4->setActive(false);
        $statementVote4->setDeleted(false);
        $statementVote4->setManual(false);

        $manager->persist($statementVote4);
        $this->setReference('testStatementVote4', $statementVote4);

        $statementVote5 = new StatementVote();
        $statementVote5->setStatement($testStatement4);
        $statementVote5->setUser($testUser2);
        $statementVote5->setFirstName($testUser2->getFirstname());
        $statementVote5->setLastName($testUser2->getLastname());
        $statementVote5->setActive(false);
        $statementVote5->setDeleted(true);
        $statementVote5->setManual(false);

        $manager->persist($statementVote5);
        $this->setReference('testStatementVote5', $statementVote5);

        $manager->flush();
    }

    public function getDependencies()
    {
        return [
            LoadStatementData::class,
        ];
    }
}
