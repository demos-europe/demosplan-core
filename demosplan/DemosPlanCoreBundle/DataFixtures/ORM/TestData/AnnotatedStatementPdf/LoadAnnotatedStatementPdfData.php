<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\AnnotatedStatementPdf;

use Carbon\Carbon;
use DateTime;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadFileData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadStatementData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\TestFixture;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\AnnotatedStatementPdf\AnnotatedStatementPdf;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Exception;

class LoadAnnotatedStatementPdfData extends TestFixture implements DependentFixtureInterface
{
    /**
     * {@inheritdoc}
     *
     * @throws Exception
     */
    public function load(ObjectManager $manager): void
    {
        /** @var Procedure $testProcedure */
        $testProcedure = $this->getReference(LoadProcedureData::TESTPROCEDURE);
        /** @var File $file1 */
        $file1 = $this->getReference('testFile');
        /** @var Statement $statement1 */
        $statement1 = $this->getReference('testStatement');

        /** @var AnnotatedStatementPdf $annotatedStatementPdf1 */
        $annotatedStatementPdf1 = new AnnotatedStatementPdf();
        $annotatedStatementPdf1->setProcedure($testProcedure);
        $annotatedStatementPdf1->setFile($file1);
        $annotatedStatementPdf1->setStatement($statement1);
        $annotatedStatementPdf1->setCreated(new DateTime());
        $annotatedStatementPdf1->setStatementText('Lorem ipsum dolor sit amet');
        $annotatedStatementPdf1 = $this->setAsPendingBlocked($annotatedStatementPdf1);

        $manager->persist($annotatedStatementPdf1);
        $this->setReference('pendingBlockedAnnotatedStatementPdf1', $annotatedStatementPdf1);

        /** @var File $file2 */
        $file2 = $this->getReference('testFile2');
        /** @var Statement $statement2 */
        $statement2 = $this->getReference('testStatement1');
        /** @var AnnotatedStatementPdf $annotatedStatementPdf2 */
        $annotatedStatementPdf2 = new AnnotatedStatementPdf();
        $annotatedStatementPdf2->setProcedure($testProcedure);
        $annotatedStatementPdf2->setFile($file2);
        $annotatedStatementPdf2->setStatement($statement2);
        $annotatedStatementPdf2->setStatementText('Lorem ipsum dolor sit amet');
        $annotatedStatementPdf2 = $this->setAsNotPendingBlocked($annotatedStatementPdf2);

        $manager->persist($annotatedStatementPdf2);
        $this->setReference('notPendingBlockedAnnotatedStatementPdf', $annotatedStatementPdf2);

        /** @var File $file3 */
        $file3 = $this->getReference('testFile3');
        /** @var Statement $statement3 */
        $statement3 = $this->getReference('testFixtureStatement');
        /** @var AnnotatedStatementPdf $annotatedStatementPdf3 */
        $annotatedStatementPdf3 = new AnnotatedStatementPdf();
        $annotatedStatementPdf3->setProcedure($testProcedure);
        $annotatedStatementPdf3->setFile($file3);
        $annotatedStatementPdf3->setStatement($statement3);
        $annotatedStatementPdf3->setStatementText('Lorem ipsum dolor sit amet');
        $annotatedStatementPdf3 = $this->setAsReviewedBlocked($annotatedStatementPdf3);

        $manager->persist($annotatedStatementPdf3);
        $this->setReference('reviewedBlockedAnnotatedStatementPdf', $annotatedStatementPdf3);

        /** @var File $file4 */
        $file4 = $this->getReference('testFile4');
        /** @var Statement $statement4 */
        $statement4 = $this->getReference('testStatement2');
        /** @var AnnotatedStatementPdf $annotatedStatementPdf4 */
        $annotatedStatementPdf4 = new AnnotatedStatementPdf();
        $annotatedStatementPdf4->setProcedure($testProcedure);
        $annotatedStatementPdf4->setFile($file4);
        $annotatedStatementPdf4->setStatement($statement4);
        $annotatedStatementPdf4->setStatementText('Lorem ipsum dolor sit amet');
        $annotatedStatementPdf4 = $this->setAsPendingBlocked($annotatedStatementPdf4);

        $manager->persist($annotatedStatementPdf4);
        $this->setReference('annotatedStatementPdf4', $annotatedStatementPdf4);

        /** @var File $file5 */
        $file5 = $this->getReference('testFile5');
        /** @var Statement $statement5 */
        $statement5 = $this->getReference('childTestStatement2');
        /** @var AnnotatedStatementPdf $annotatedStatementPdf5 */
        $annotatedStatementPdf5 = new AnnotatedStatementPdf();
        $annotatedStatementPdf5->setProcedure($testProcedure);
        $annotatedStatementPdf5->setFile($file5);
        $annotatedStatementPdf5->setStatement($statement5);
        $annotatedStatementPdf5->setStatementText('Lorem ipsum dolor sit amet');
        $annotatedStatementPdf5 = $this->setAsPendingBlocked($annotatedStatementPdf5);

        $manager->persist($annotatedStatementPdf5);
        $this->setReference('annotatedStatementPdf5', $annotatedStatementPdf5);

        /** @var File $file6 */
        $file6 = $this->getReference('testFile6');
        /** @var Statement $statement6 */
        $statement6 = $this->getReference('testStatementOtherOrga');
        /** @var AnnotatedStatementPdf $annotatedStatementPdf6 */
        $annotatedStatementPdf6 = new AnnotatedStatementPdf();
        $annotatedStatementPdf6->setProcedure($testProcedure);
        $annotatedStatementPdf6->setFile($file6);
        $annotatedStatementPdf6->setStatement($statement6);
        $annotatedStatementPdf6->setStatementText('Lorem ipsum dolor sit amet');
        $annotatedStatementPdf6 = $this->setAsPendingBlocked($annotatedStatementPdf6);

        $manager->persist($annotatedStatementPdf6);
        $this->setReference('annotatedStatementPdf6', $annotatedStatementPdf6);

        /** @var File $file7 */
        $file7 = $this->getReference('testFile7');
        /** @var Statement $statement7 */
        $statement7 = $this->getReference('testStatementNotOriginal');
        /** @var AnnotatedStatementPdf $annotatedStatementPdf7 */
        $annotatedStatementPdf7 = new AnnotatedStatementPdf();
        $annotatedStatementPdf7->setProcedure($testProcedure);
        $annotatedStatementPdf7->setFile($file7);
        $annotatedStatementPdf7->setStatement($statement7);
        $annotatedStatementPdf7->setStatementText('Lorem ipsum dolor sit amet');
        $annotatedStatementPdf7 = $this->setAsPendingBlocked($annotatedStatementPdf7);

        $manager->persist($annotatedStatementPdf7);
        $this->setReference('annotatedStatementPdf7', $annotatedStatementPdf7);

        /** @var File $file8 */
        $file8 = $this->getReference('testFile8');
        /** @var Statement $statement8 */
        $statement8 = $this->getReference('testStatementAssigned6');
        /** @var AnnotatedStatementPdf $annotatedStatementPdf8 */
        $annotatedStatementPdf8 = new AnnotatedStatementPdf();
        $annotatedStatementPdf8->setProcedure($testProcedure);
        $annotatedStatementPdf8->setFile($file8);
        $annotatedStatementPdf8->setStatement($statement8);
        $annotatedStatementPdf8->setStatementText('Lorem ipsum dolor sit amet');
        $annotatedStatementPdf8 = $this->setAsPendingBlocked($annotatedStatementPdf8);

        $manager->persist($annotatedStatementPdf8);
        $this->setReference('annotatedStatementPdf8', $annotatedStatementPdf8);

        /** @var File $file9 */
        $file9 = $this->getReference('testFile9');
        /** @var AnnotatedStatementPdf $annotatedStatementPdf9 */
        $annotatedStatementPdf9 = new AnnotatedStatementPdf();
        $annotatedStatementPdf9->setProcedure($testProcedure);
        $annotatedStatementPdf9->setFile($file9);
        $annotatedStatementPdf9->setStatementText('Lorem ipsum dolor sit amet');
        $annotatedStatementPdf9->setStatus(AnnotatedStatementPdf::CONVERTED);

        $manager->persist($annotatedStatementPdf9);
        $this->setReference('annotatedStatementPdf9', $annotatedStatementPdf9);

        $manager->flush();
    }

    /**
     * @throws Exception
     */
    private function setAsPendingBlocked(
        AnnotatedStatementPdf $annotatedStatementPdf
    ): AnnotatedStatementPdf {
        $createdDate = Carbon::now()->addSeconds(
            - 2 * $this->getContainer()->getParameter('pipeline.max.waiting.seconds')
        )->toDateTime();
        $annotatedStatementPdf->setCreated($createdDate);
        $annotatedStatementPdf->setStatus(AnnotatedStatementPdf::PENDING);

        return $annotatedStatementPdf;
    }

    /**
     * @throws Exception
     */
    private function setAsNotPendingBlocked(
        AnnotatedStatementPdf $annotatedStatementPdf
    ): AnnotatedStatementPdf {
        $validDate = Carbon::now()->addSeconds(
            - (int)($this->getContainer()->getParameter('pipeline.max.waiting.seconds') / 2)
        )->toDateTime();
        $annotatedStatementPdf->setCreated($validDate);
        $annotatedStatementPdf->setStatus(AnnotatedStatementPdf::PENDING);

        return $annotatedStatementPdf;
    }

    /**
     * @throws Exception
     */
    private function setAsReviewedBlocked(
        AnnotatedStatementPdf $annotatedStatementPdf
    ): AnnotatedStatementPdf {
        $reviewedDate = Carbon::now()->addSeconds(
            - 2 * $this->getContainer()->getParameter('pipeline.max.waiting.seconds')
        )->toDateTime();
        $annotatedStatementPdf->setReviewedDate($reviewedDate);
        $annotatedStatementPdf->setStatus(AnnotatedStatementPdf::REVIEWED);

        return $annotatedStatementPdf;
    }

    public function getDependencies()
    {
        return [
            LoadFileData::class,
            LoadProcedureData::class,
            LoadStatementData::class,
        ];
    }
}
