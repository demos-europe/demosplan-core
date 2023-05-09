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

        /** @var Statement $testStatement2 */
        $testStatement2 = $this->getReference('testFixtureStatement');

        /** @var Procedure $testProcedure */
        $testProcedure = $this->getReference(LoadProcedureData::TESTPROCEDURE);
        $procedurePerson1 = new ProcedurePerson('Max Mustermann', $testProcedure);
        $procedurePerson1->addSimilarForeignStatement($testStatement);
        $manager->persist($procedurePerson1);
        $this->setReference('testProcedurePerson1', $procedurePerson1);

        /** @var Procedure $testProcedure */
        $testProcedure = $this->getReference(LoadProcedureData::TESTPROCEDURE);
        $procedurePerson2 = new ProcedurePerson('Malia Musterfrau', $testProcedure);
        $procedurePerson2->addSimilarForeignStatement($testStatement2);
        $manager->persist($procedurePerson2);
        $this->setReference('testProcedurePerson2', $procedurePerson2);

        /** @var Procedure $testProcedure */
        $testProcedure = $this->getReference(LoadProcedureData::TESTPROCEDURE);
        $procedurePerson3 = new ProcedurePerson('Oliver Groß', $testProcedure);
        $procedurePerson3->addSimilarForeignStatement($testStatement);
        $manager->persist($procedurePerson3);
        $this->setReference('testprocedurePerson3', $procedurePerson3);

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
