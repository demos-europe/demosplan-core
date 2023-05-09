<?php
declare(strict_types=1);


namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;


use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePerson;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class LoadProcedurePersonData extends TestFixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        /** @var Statement $testStatement */
        $testStatement = $this->getReference('testStatement');

        /** @var Procedure $testProcedure */
        $testProcedure = $this->getReference(LoadProcedureData::TESTPROCEDURE);
        $procedurePerson1 = new ProcedurePerson('Max Mustermann', $testProcedure);
        $procedurePerson1->addSimilarForeignStatement($testStatement);
        $manager->persist($procedurePerson1);
        $this->setReference('testProcedurePerson1', $procedurePerson1);

        $manager->flush();
    }

    public function getDependencies()
    {
        return [
            LoadProcedureData::class,
            LoadStatementData::class,
        ];
    }
}
