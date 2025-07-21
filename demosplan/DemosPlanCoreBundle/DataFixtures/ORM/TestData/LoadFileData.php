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
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\FileContainer;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadFileData extends TestFixture implements DependentFixtureInterface
{
    final public const PDF_TEST_FILE = 'testFile';

    public function load(ObjectManager $manager): void
    {
        $file1 = new File();
        $file1->setHash('df055eb7-5405-425b-9e21-7faa63f69a70');
        $file1->setDescription('session: ee39542b87bc4d0cab7700646d250028');
        $file1->setName('7025_283_Testfile.pdf');
        $file1->setPath(DemosPlanPath::getTestPath('test/files'));
        $file1->setFilename('testdokument.pdf');
        $file1->setTags(',,Demos Test');
        $file1->setAuthor('0a8ca8ee-ce50-432b-a376-935d4fd5aacb');
        $file1->setApplication('FI');
        $file1->setMimetype('application/x-pdf');
        $file1->setCreated(new DateTime());
        $file1->setModified(new DateTime());
        $file1->setValidUntil(new DateTime());
        $file1->setDeleted(0);
        $file1->setStatDown(0);
        $file1->setInfected(0);
        $file1->setLastVScan(new DateTime());
        $file1->setBlocked(0);
        $file1->setSize(5_000_000); // 5MB

        $manager->persist($file1);
        $this->setReference(self::PDF_TEST_FILE, $file1);

        // write File to test existance
        // local file only, no need for flysystem
        $fs = new Filesystem();

        // without try catch, should throw exception if not successful
        $fs->dumpFile($file1->getFilePathWithHash(), 'file1');

        $file2 = new File();
        $file2->setHash('ab055eb7-5405-425b-9e21-7faa63f69a70');
        $file2->setDescription('session: e37d0cdd56084c1dbfb73eb3ffaf272c');
        $file2->setName('Testfile_Forum.pdf');
        $file2->setPath(DemosPlanPath::getTemporaryPath('test/files'));
        $file2->setFilename('testfileforum.pdf');
        $file2->setTags(',,Demos Test');
        $file2->setAuthor('558ca8ee-ce50-432b-a376-935d4fd5aacb');
        $file2->setApplication('FI');
        $file2->setMimetype('application/x-pdf');
        $file2->setCreated(new DateTime());
        $file2->setModified(new DateTime());
        $file2->setValidUntil(new DateTime());
        $file2->setDeleted(0);
        $file2->setStatDown(0);
        $file2->setInfected(0);
        $file2->setLastVScan(new DateTime());
        $file2->setBlocked(0);
        $file2->setSize(5_000_000); // 5MB

        $manager->persist($file2);
        $this->setReference('testFile2', $file2);

        $statementFileContainer1 = new FileContainer();
        $statementFileContainer1->setEntityClass(Statement::class);
        // use static Id, real usecase should be tested in statementService Test
        $statementFileContainer1->setEntityId('statementId');
        $statementFileContainer1->setEntityField('file');
        $statementFileContainer1->setFile($file1);
        $statementFileContainer1->setFileString('fileName:Hash:12534:image/png');

        $statementFileContainer2 = new FileContainer();
        $statementFileContainer2->setEntityClass(Statement::class);
        // use static Id, real usecase should be tested in statementService Test
        $statementFileContainer2->setEntityId('statementId');
        $statementFileContainer2->setEntityField('file');
        $statementFileContainer2->setFile($file2);
        $statementFileContainer2->setFileString('fileName2:Hash2:212534:image/png');

        $manager->persist($statementFileContainer1);
        $manager->persist($statementFileContainer2);
        $manager->flush();

        $this->setReference('testFileContainer', $statementFileContainer1);
        $this->setReference('testFileContainer2', $statementFileContainer2);

        $file3 = new File();
        $file3->setHash('9210abeaf8a628e8fb92c41f30e5544f');
        $file3->setName('export1.pdf');
        $file3->setPath(DemosPlanPath::getTemporaryPath('test/files'));
        $file3->setFilename('export1.pdf');
        $file3->setMimetype('application/x-pdf');
        $file3->setCreated(new DateTime());
        $file3->setModified(new DateTime());
        $file3->setValidUntil(new DateTime());
        $file3->setDeleted(0);
        $file3->setStatDown(0);
        $file3->setInfected(0);
        $file3->setLastVScan(new DateTime());
        $file3->setBlocked(0);
        $file3->setSize(5_000_000); // 5MB

        $manager->persist($file3);
        $this->setReference('testFile3', $file3);

        $file4 = new File();
        $file4->setHash('c9079e2d46700c5a17ff87b1148fd4ba');
        $file4->setName('export2.pdf');
        $file4->setPath(DemosPlanPath::getTemporaryPath('test/files'));
        $file4->setFilename('export2.pdf');
        $file4->setMimetype('application/x-pdf');
        $file4->setCreated(new DateTime());
        $file4->setModified(new DateTime());
        $file4->setValidUntil(new DateTime());
        $file4->setDeleted(0);
        $file4->setStatDown(0);
        $file4->setInfected(0);
        $file4->setLastVScan(new DateTime());
        $file4->setBlocked(0);
        $file4->setSize(5_000_000); // 5MB

        $manager->persist($file4);
        $this->setReference('testFile4', $file4);

        $file5 = new File();
        $file5->setHash('f6274f23da4da9132dbc1cab93b92c18');
        $file5->setName('export3.pdf');
        $file5->setPath(DemosPlanPath::getTemporaryPath('test/files'));
        $file5->setFilename('export3.pdf');
        $file5->setMimetype('application/x-pdf');
        $file5->setCreated(new DateTime());
        $file5->setModified(new DateTime());
        $file5->setValidUntil(new DateTime());
        $file5->setDeleted(0);
        $file5->setStatDown(0);
        $file5->setInfected(0);
        $file5->setLastVScan(new DateTime());
        $file5->setBlocked(0);
        $file5->setSize(5_000_000); // 5MB

        $manager->persist($file5);
        $this->setReference('testFile5', $file5);

        $file6 = new File();
        $file6->setHash('7bcf05c6ca68e74f247c3c32d61e41ab');
        $file6->setName('export4.pdf');
        $file6->setPath(DemosPlanPath::getTemporaryPath('test/files'));
        $file6->setFilename('export4.pdf');
        $file6->setMimetype('application/x-pdf');
        $file6->setCreated(new DateTime());
        $file6->setModified(new DateTime());
        $file6->setValidUntil(new DateTime());
        $file6->setDeleted(0);
        $file6->setStatDown(0);
        $file6->setInfected(0);
        $file6->setLastVScan(new DateTime());
        $file6->setBlocked(0);
        $file6->setSize(5_000_000); // 5MB

        $manager->persist($file6);
        $this->setReference('testFile6', $file6);

        $file7 = new File();
        $file7->setHash('7cd6adce4e9063bb7fcb6c4c07c91448');
        $file7->setName('export5.pdf');
        $file7->setPath(DemosPlanPath::getTemporaryPath('test/files'));
        $file7->setFilename('export5.pdf');
        $file7->setMimetype('application/x-pdf');
        $file7->setCreated(new DateTime());
        $file7->setModified(new DateTime());
        $file7->setValidUntil(new DateTime());
        $file7->setDeleted(0);
        $file7->setStatDown(0);
        $file7->setInfected(0);
        $file7->setLastVScan(new DateTime());
        $file7->setBlocked(0);
        $file7->setSize(5_000_000); // 5MB

        $manager->persist($file7);
        $this->setReference('testFile7', $file7);

        $file8 = new File();
        $file8->setHash('e1e7f809d7eea51dfa383899cae3a3bb');
        $file8->setName('export6.pdf');
        $file8->setPath(DemosPlanPath::getTemporaryPath('test/files'));
        $file8->setFilename('export6.pdf');
        $file8->setMimetype('application/x-pdf');
        $file8->setCreated(new DateTime());
        $file8->setModified(new DateTime());
        $file8->setValidUntil(new DateTime());
        $file8->setDeleted(0);
        $file8->setStatDown(0);
        $file8->setInfected(0);
        $file8->setLastVScan(new DateTime());
        $file8->setBlocked(0);
        $file8->setSize(5_000_000); // 5MB

        $manager->persist($file8);
        $this->setReference('testFile8', $file8);

        $file9 = new File();
        $file9->setHash('dde7f809d7eea51dfa383899cae3a3bb');
        $file9->setName('export9.pdf');
        $file9->setPath(DemosPlanPath::getTemporaryPath('test/files'));
        $file9->setFilename('export9.pdf');
        $file9->setMimetype('application/x-pdf');
        $file9->setCreated(new DateTime());
        $file9->setModified(new DateTime());
        $file9->setValidUntil(new DateTime());
        $file9->setDeleted(0);
        $file9->setStatDown(0);
        $file9->setInfected(0);
        $file9->setLastVScan(new DateTime());
        $file9->setBlocked(0);
        $file9->setSize(5_000_000); // 5MB

        $manager->persist($file9);
        $this->setReference('testFile9', $file9);

        $statementsAsXlsx = new File();
        $statementsAsXlsx->setHash('dde7f809d7eea51123456799cae3a3bb2');
        $statementsAsXlsx->setName('Vorschlag_Stellungnahmen-Import.xlsx');
        $statementsAsXlsx->setPath(DemosPlanPath::getRootPath('tests/backend/core/Statement/Functional/res'));
        $statementsAsXlsx->setFilename('dde7f809d7eea51123456799cae3a3bb2');
        $statementsAsXlsx->setMimetype('xlsx');
        $statementsAsXlsx->setCreated(new DateTime());
        $statementsAsXlsx->setModified(new DateTime());
        $statementsAsXlsx->setValidUntil(new DateTime());
        $statementsAsXlsx->setDeleted(0);
        $statementsAsXlsx->setStatDown(0);
        $statementsAsXlsx->setInfected(0);
        $statementsAsXlsx->setLastVScan(new DateTime());
        $statementsAsXlsx->setBlocked(0);

        $manager->persist($statementsAsXlsx);
        $this->setReference('statements_as_xlsx', $statementsAsXlsx);

        $statementsAsXlsxWithMinimalData = new File();
        $statementsAsXlsxWithMinimalData->setHash('dde7f809d7eea51123456799cae3a3bb_minimal_data');
        $statementsAsXlsxWithMinimalData->setName('Vorschlag_Stellungnahmen-Import.xlsx');
        $statementsAsXlsxWithMinimalData->setPath(DemosPlanPath::getRootPath('tests/backend/core/Statement/Functional/res'));
        $statementsAsXlsxWithMinimalData->setFilename('dde7f809d7eea51123456799cae3a3bb_minimal_data');
        $statementsAsXlsxWithMinimalData->setMimetype('xlsx');
        $statementsAsXlsxWithMinimalData->setCreated(new DateTime());
        $statementsAsXlsxWithMinimalData->setModified(new DateTime());
        $statementsAsXlsxWithMinimalData->setValidUntil(new DateTime());
        $statementsAsXlsxWithMinimalData->setDeleted(0);
        $statementsAsXlsxWithMinimalData->setStatDown(0);
        $statementsAsXlsxWithMinimalData->setInfected(0);
        $statementsAsXlsxWithMinimalData->setLastVScan(new DateTime());
        $statementsAsXlsxWithMinimalData->setBlocked(0);

        $manager->persist($statementsAsXlsxWithMinimalData);
        $this->setReference('statements_as_xlsx_minimal_data', $statementsAsXlsxWithMinimalData);

        $statementsAsXlsxWithWrongWorksheetName = new File();
        $statementsAsXlsxWithWrongWorksheetName->setHash('dde7f809d7eea51123456799cae3a3bb_wrong_worksheetName');
        $statementsAsXlsxWithWrongWorksheetName->setName('Vorschlag_Stellungnahmen-Import.xlsx');
        $statementsAsXlsxWithWrongWorksheetName->setPath(DemosPlanPath::getRootPath('tests/backend/core/Statement/Functional/res'));
        $statementsAsXlsxWithWrongWorksheetName->setFilename('dde7f809d7eea51123456799cae3a3bb_wrong_worksheetName');
        $statementsAsXlsxWithWrongWorksheetName->setMimetype('xlsx');
        $statementsAsXlsxWithWrongWorksheetName->setCreated(new DateTime());
        $statementsAsXlsxWithWrongWorksheetName->setModified(new DateTime());
        $statementsAsXlsxWithWrongWorksheetName->setValidUntil(new DateTime());
        $statementsAsXlsxWithWrongWorksheetName->setDeleted(0);
        $statementsAsXlsxWithWrongWorksheetName->setStatDown(0);
        $statementsAsXlsxWithWrongWorksheetName->setInfected(0);
        $statementsAsXlsxWithWrongWorksheetName->setLastVScan(new DateTime());
        $statementsAsXlsxWithWrongWorksheetName->setBlocked(0);

        $manager->persist($statementsAsXlsxWithWrongWorksheetName);
        $this->setReference('statements_as_xlsx_wrong_worksheetName', $statementsAsXlsxWithWrongWorksheetName);

        $corruptStatementsAsXlsx = new File();
        $corruptStatementsAsXlsx->setHash('dde7f809d7eea5112_corrupted.xlsx');
        $corruptStatementsAsXlsx->setName('Vorschlag_Stellungnahmen-Import_wrong_worksheetName.xlsx');
        $corruptStatementsAsXlsx->setPath(DemosPlanPath::getRootPath('tests/backend/core/Statement/Functional/res'));
        $corruptStatementsAsXlsx->setFilename('dde7f809d7eea51123456799cae3a3bb_corrupted.xlsx');
        $corruptStatementsAsXlsx->setMimetype('xlsx');
        $corruptStatementsAsXlsx->setCreated(new DateTime());
        $corruptStatementsAsXlsx->setModified(new DateTime());
        $corruptStatementsAsXlsx->setValidUntil(new DateTime());
        $corruptStatementsAsXlsx->setDeleted(0);
        $corruptStatementsAsXlsx->setStatDown(0);
        $corruptStatementsAsXlsx->setInfected(0);
        $corruptStatementsAsXlsx->setLastVScan(new DateTime());
        $corruptStatementsAsXlsx->setBlocked(0);

        $manager->persist($corruptStatementsAsXlsx);
        $this->setReference('statements_as_xlsx_including_an_error', $corruptStatementsAsXlsx);

        $file10 = new File();
        $file10->setHash('df055eb7-5405-425b-9e21-7faa63f67777');
        $file10->setDescription('session: ee3954dfsdffd0cab7700646d250028');
        $file10->setName('7025_283_Testfile2.pdf');
        $file10->setPath(DemosPlanPath::getTestPath('test/files'));
        $file10->setFilename('20131112_OSBA_Leitfaden_zur_Datensicherheit.pdf');
        $file10->setTags('Demos Test');
        $file10->setAuthor('0a8ca8ee-ce50-432b-a376-935d4fd5aacb');
        $file10->setApplication('FI');
        $file10->setMimetype('application/x-pdf');
        $file10->setCreated(new DateTime());
        $file10->setModified(new DateTime());
        $file10->setValidUntil(new DateTime());
        $file10->setDeleted(0);
        $file10->setStatDown(0);
        $file10->setInfected(0);
        $file10->setLastVScan(new DateTime());
        $file10->setBlocked(0);
        $file10->setSize(5_000_000); // 5MB
        $file10->setProcedure($this->getReference('masterBlaupause'));

        $manager->persist($file10);

        $this->setReference('testdokument2', $file10);

        // without try catch, should throw exception if not successful
        $fs->dumpFile($file10->getFilePathWithHash(), 'file10');

        $manager->flush();
    }

    public function getDependencies()
    {
        return [
            LoadProcedureData::class,
        ];
    }
}
