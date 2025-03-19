<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Report\Functional;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Logic\Report\ReportMessageConverter;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Base\FunctionalTestCase;

class ReportMessageConverterTest extends FunctionalTestCase
{
    /** @var TranslatorInterface */
    protected $translator;
    /** @var \Symfony\Component\Routing\Router */
    protected $router;

    /** @var ReportMessageConverter */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::$container->get(ReportMessageConverter::class);
        $this->translator = self::$container->get('translator.default');
        $this->router = self::$container->get('router');
    }

    public function testConvertProcedureAddMessage()
    {
        $reportEntry = new ReportEntry();
        $reportEntry->setGroup(ReportEntry::GROUP_PROCEDURE)
            ->setCategory(ReportEntry::CATEGORY_ADD)
            ->setMessage('{"ident":"fc344be9-b56d-11e6-a701-0050568a1238","id":"fc344be9-b56d-11e6-a701-0050568a1238","name":"Test-Blaupause","shortUrl":"","orgaName":"Bezirksamt Bergedorf","orgaId":"d43745af-89ea-4a51-942a-7bc5990e9b0b","orga":{},"desc":"","phase":"configuration","step":"","logo":"","externId":"","plisId":"","closed":false,"deleted":false,"master":true,"externalName":"Test-Blaupause","externalDesc":"","publicParticipation":false,"publicParticipationPhase":"configuration","publicParticipationStep":"","publicParticipationStartDate":1480339466000,"publicParticipationEndDate":1480339466000,"publicParticipationContact":"k.A.","publicParticipationPublicationEnabled":true,"locationName":"","locationPostCode":"","coordinate":"","municipalCode":"","createdDate":1480339466000,"startDate":1477962000000,"endDate":1484787600000,"closedDate":1480339466000,"deletedDate":1480339466000,"organisation":[],"organisationIds":[],"settings":{"ident":"27a95d51-db94-4461-be18-e089c9cb037f","id":"27a95d51-db94-4461-be18-e089c9cb037f","pId":"ae65efdb-8414-4deb-bc81-26efdfc9560b","mapExtent":"570689.647,5936867.855,570872.786,5937053.390","startScale":"","availableScale":"","boundingBox":"","informationUrl":"","defaultLayer":"","territory":"","coordinate":"","planEnable":false,"planText":"","planPDF":"","planPara1PDF":"","planPara2PDF":"","planDrawText":"","planDrawPDF":"","emailTitle":"Betreff","emailText":"Text","emailCc":"","assessmentOrder":"","assessmentOriginalOrder":"","links":"","procedure":{},"elementsZip":null,"pictogram":null},"tags":{},"topics":{},"elements":{},"planningOffices":[],"pictogram":null}');

        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals($this->translator->trans('procedure.created'), $message);
    }

    public function testConvertProcedureUpdateMessage()
    {
        self::markSkippedForCIIntervention();

        $reportEntry = new ReportEntry();
        $reportEntry->setGroup(ReportEntry::GROUP_PROCEDURE)
            ->setCategory(ReportEntry::CATEGORY_UPDATE)
            ->setMessage('{"oldName":"Wind - Regionalpläne 3 ","newName":"Landespläne Wind - Regionalplan 3 ","oldPublicName":"Pläne Wind - Regionalplan 3 "}');
        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals(
            $this->translator->trans('text.protocol.procedure.name.changed', [
                '%oldName%' => 'Wind - Regionalpläne 3 ', '%newName%' => 'Landespläne Wind - Regionalplan 3 ',
            ]),
            $message
        );

        $reportEntry->setMessage('{"oldName":"Fortschreibung des Landesentwicklungsplans Schleswig-Holstein 2010","newName":"_Fortschreibung des Landesentwicklungsplans Schleswig-Holstein 2010","oldPublicName":"Fortschreibung des Landesentwicklungsplans Schleswig-Holstein 2010","newPublicName":"_Fortschreibung des Landesentwicklungsplans Schleswig-Holstein 2010"}');
        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals($this->translator->trans('text.protocol.procedure.name.changed', [
            '%oldName%' => 'Fortschreibung des Landesentwicklungsplans Schleswig-Holstein 2010',
            '%newName%' => '_Fortschreibung des Landesentwicklungsplans Schleswig-Holstein 2010',
        ]).'<br />'.
            $this->translator->trans('text.protocol.procedure.public.name.changed', [
                '%oldName%' => 'Fortschreibung des Landesentwicklungsplans Schleswig-Holstein 2010',
                '%newName%' => '_Fortschreibung des Landesentwicklungsplans Schleswig-Holstein 2010',
            ]),
            $message
        );

        // test very old entry -> generic message
        $reportEntry->setMessage('{"ident":"dad9a71b-e646-4b45-bd9a-6f22feb61f37","name":"Gelenk","desc":"1.Beteiligung","orgaName":"Amt Nordwest","logo":"BOB-SH_Logo.jpg:b98f07c7cd3445a48cb25a1ee67ec952","externId":"","orgaId":"030528df-19d5-4fa5-857a-502251422bdc","deleted":false,"master":false,"createdDate":1391508525000,"startDate":1391554800000,"endDate":1393974000000,"closedDate":1376989266000,"settings":{"mapExtent":"576990.34,5949451.54,577667.67,5950118.29","startScale":"","availableScale":"","boundingBox":"441997.41,5923055.13,611330.65,6089742.54","informationUrl":"","defaultLayer":"","planEnable":false,"planText":"05.02.2014","planPDF":"Planzeichnung.pdf:f7f9d0304c614b9abb3090f0a137f55e","planPara1PDF":"Begruendung.pdf:6783ed44dbbd4865bff35bd0a900d942","planPara2PDF":"TextlicheFestsetzung.pdf:abb9e4493c344bb9b3eab211d7b0afbe","planDrawText":"","planDrawPDF":"Planzeichenerklärung.pdf:05e6b697f8184283b6886afc510b97dd","emailTitle":"Einladung zur Beteiligung  Amt Nordwest: Gelenk","emailText":"bitte geben sie ihre stellungnahme ab","assessmentOrder":"","assessmentOriginalOrder":""},"phase":"participation","closed":false,"organisation":["4201af7a-de35-460d-a408-5f3edc8553ff","9c2a287a-5a23-4910-865a-4e758248e4f1"],"psdOrgaId":""}');
        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals(
            $this->translator->trans('text.protocol.procedure.changed.generic'),
            $message
        );

        $reportEntry->setMessage('{"oldName":"vvvvv","newName":"PB 107","oldPublicName":"BP 107 Bereich Hauptstraße","oldPublicStartDate":1516323600,"newPublicStartDate":1533513600,"oldPublicEndDate":1516323600,"newPublicEndDate":1535587200}');
        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals('Verfahrensname geändert von vvvvv in PB 107<br />Öffentlichkeitsbeteiligung: Zeitraum geändert von 19.01.2018 - 19.01.2018 in 06.08.2018 - 30.08.2018',
            $message
        );

        $reportEntry->setMessage('{"ident":"7a0be49e-fcfe-11e7-9a68-0050568a1238","group":"procedure","category":"update","externalDesc":"k.A."}');
        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals('Öffentliche Verfahrensbeschreibung geändert auf: k.A.',
            $message
        );

        $reportEntry->setMessage('{"oldPublicName":"Verfahren 1 - überdachter Tunnel"}');
        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals(
            $this->translator->trans('text.protocol.procedure.changed.generic'),
            $message
        );

        $reportEntry->setMessage('{"oldPublicStartDate":1531180800,"newPublicStartDate":1533859200,"oldPublicEndDate":1531180800,"newPublicEndDate":1536537600}');
        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals('Öffentlichkeitsbeteiligung: Zeitraum geändert von 10.07.2018 - 10.07.2018 in 10.08.2018 - 10.09.2018',
            $message
        );

        $reportEntry->setMessage('{"oldAuthorizedUsers":"Sven Nordwind, ft-fpatbko-N Vorname ft-fpatbko-N Nachname, ft-fpsb-N Vorname ft-fpsb-N Nachname, Hannes Rudzik, Walter West, Horst Hohenfeld, Maya Hohenfeld, ft-fpa-N Nachname ft-fpa-N Vorname","newAuthorizedUsers":"Sven Nordwind, ft-fpatbko-N Vorname ft-fpatbko-N Nachname, ft-fpsb-N Vorname ft-fpsb-N Nachname, Hannes Rudzik, Selta Seewind, Walter West, Horst Hohenfeld, Maya Hohenfeld, Fachplaner Admin, ft-fpa-N Nachname ft-fpa-N Vorname"}');
        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals($this->translator->trans('text.protocol.procedure.authorized.user.changed', [
            '%oldAuthorizedUsers%' => 'Sven Nordwind, ft-fpatbko-N Vorname ft-fpatbko-N Nachname, ft-fpsb-N Vorname ft-fpsb-N Nachname, Hannes Rudzik, Walter West, Horst Hohenfeld, Maya Hohenfeld, ft-fpa-N Nachname ft-fpa-N Vorname',
            '%newAuthorizedUsers%' => 'Sven Nordwind, ft-fpatbko-N Vorname ft-fpatbko-N Nachname, ft-fpsb-N Vorname ft-fpsb-N Nachname, Hannes Rudzik, Selta Seewind, Walter West, Horst Hohenfeld, Maya Hohenfeld, Fachplaner Admin, ft-fpa-N Nachname ft-fpa-N Vorname',
        ]),
            $message
        );

        $reportEntry->setMessage('{"mapExtent":"566988.57,5943718.02,567894.77,5944505.16"}');
        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals($this->translator->trans('text.protocol.procedure.mapextent.changed', [
            '%mapExtent%' => '566988.57, 5943718.02, 567894.77, 5944505.16',
        ]),
            $message
        );

        $reportEntry->setMessage('{"mapExtent":""}');
        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals($this->translator->trans('text.protocol.procedure.mapextent.changed', [
            '%mapExtent%' => '',
        ]),
            $message
        );

        $reportEntry->setMessage('{"ident":"9d3edaac-b80b-4054-bce3-711dbb2ed923","group":"procedure","category":"update","mapExtent":"571417.34,5925168.01,572314.80,5925830.53"}');
        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals($this->translator->trans('text.protocol.procedure.mapextent.changed', [
            '%mapExtent%' => '571417.34, 5925168.01, 572314.80, 5925830.53',
        ]),
            $message
        );
        $reportEntry->setMessage('{"oldStartDate":1537263153,"newStartDate":1537228800,"oldEndDate":1537263153,"newEndDate":1537228800,"oldPublicStartDate":1537263153,"newPublicStartDate":1537228800,"oldPublicEndDate":1537263153,"newPublicEndDate":1537228800}');
        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals('TöB-Beteiligung: Zeitraum geändert von 18.09.2018 - 18.09.2018 in 18.09.2018 - 18.09.2018<br />Öffentlichkeitsbeteiligung: Zeitraum geändert von 18.09.2018 - 18.09.2018 in 18.09.2018 - 18.09.2018',
            $message
        );
    }

    public function testConvertProcedureChangePhasesMessage()
    {
        self::markTestSkipped('This test was skipped because of pre-existing errors. They are most likely easily fixable but prevent us from getting to a usable state of our CI.');

        $phaseConfiguration = $this->translator->trans('procedure.phases.internal.configuration');
        $phaseParticipation = $this->translator->trans('procedure.phases.internal.participation');
        $phaseEarlyParticipation = $this->translator->trans('procedure.phases.internal.earlyparticipation');
        $phaseEvaluation = $this->translator->trans('procedure.phases.internal.analysis');
        $phaseConfigurationExternal = $this->translator->trans('procedure.phases.external.configuration');
        $phaseEarlyParticipationExternal = $this->translator->trans('procedure.phases.external.earlyparticipation');
        $phaseParticipationExternal = $this->translator->trans('procedure.phases.external.participation');
        $phaseEvaluationExternal = $this->translator->trans('procedure.phases.external.evaluating');

        $pubAgencyParticipation = $this->translator->trans('invitable_institution.participation');

        // change phase from configuration to configuration as this is the only phase
        // which has the same name in any project. Otherwise the success of this test
        // would depend on phpunit project configuration file
        $reportEntry = new ReportEntry();
        $reportEntry->setGroup(ReportEntry::GROUP_PROCEDURE)
            ->setCategory(ReportEntry::CATEGORY_CHANGE_PHASES)
            ->setMessage('{"begruendung":true,"newPhase":"configuration","oldPhase":"configuration","verordnung":true,"verordnungPDF":"Verordnung.pdf:6f48b539-4f9d-48db-90be-cc257265eec5:54871:application/pdf","planText":"11.11.2015","elements":{"Untersuchungen":{"files":["Untersuchung:Untersuchung_Laerm.pdf:1b91a735-deee-4323-abb4-99c28a8df561:13794:application/pdf"]}},"planPDF":"Legende.pdf:fd4e93f5-30d2-4595-bb07-81143e24d4dc:119994:application/pdf"}');
        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals($pubAgencyParticipation.': "Verfahrensschritt" von "'.$phaseConfiguration.'" auf "'.$phaseConfiguration.'" verändert<br />Bei der Umstellung eingestellte Unterlagen:<br /><strong>Begründung</strong><br /><strong>Textliche Festsetzungen: "PDF"</strong><br /><strong>Planzeichenerklärung</strong><br /><strong>Untersuchungen</strong><br /><ul><li>Untersuchung (Untersuchung_Laerm.pdf)</li></ul>',
            $message
        );

        $reportEntry->setMessage('{"oldPhase":"earlyparticipation","newPhase":"evaluating","oldPublicPhase":"earlyparticipation","newPublicPhase":"evaluating","planPDF":"Surendorf 5.Änd B14 - B-Plan-Zeichenerklärung.pdf:9a609f5b-792e-11e8-a21a-0050568a354d:215689:application\/pdf","planGisName":"5. Änderung Bebauungsplan 14","planGisVisible":true}');
        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals($pubAgencyParticipation.': "Verfahrensschritt" von "'.$phaseEarlyParticipation.'" auf "'.$phaseEvaluation.'" verändert<br />Öffentlichkeitsbeteiligung: "Verfahrensschritt" von "'.$phaseEarlyParticipationExternal.'" auf "'.$phaseEvaluationExternal.'" verändert<br />Bei der Umstellung eingestellte Unterlagen:<br /><strong>Planzeichnungs-WMS "5. Änderung Bebauungsplan 14"</strong><br /><strong>Planzeichenerklärung</strong>',
            $message
        );

        $reportEntry->setMessage('{"begruendung":true,"verordnung":true,"verordnungPDF":"Verordnung.pdf:ecee67e6-c054-45e9-aa0c-b32c10964acc:54871:application/pdf","planText":"07.12.2015","newPublicPhase":"earlyparticipation","begruendungPDF":"Begruendung.pdf:40cee25e-de6f-4a83-a21d-c9ed3e5c2a09:80204:application/pdf","elements":{"Infoblatt, Scoping-Papier, nur Scoping-Protokoll":{"files":["GA Protukoll (20. Mai 2015`):Testdokument.pdf:81195fab-91d3-43b6-81d6-cc798471da7b:13794:application/pdf"],"access":["LGV-Betrieb Geodatenanwendung","BOP Eins Baumeister GmbH","BOP Interner TöB Schulung"]},"Ergänzende Unterlagen":{"files":["Gutachten 1a:Gutachten.pdf:cf061c95-0bce-40bd-9f04-7427c2c4dbb9:13794:application/pdf","LTU:Untersuchung_Laerm.pdf:467325e4-5c1d-4367-b3ed-12019a3cb8fc:13794:application/pdf"]}},"planPDF":"Legende.pdf:f15b1116-3043-407e-8fa1-2a52da1087fa:119994:application/pdf","oldPublicPhase":"configuration"}');
        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals('Öffentlichkeitsbeteiligung: "Verfahrensschritt" von "'.$phaseConfigurationExternal.'" auf "'.$phaseEarlyParticipationExternal.'" verändert<br />Bei der Umstellung eingestellte Unterlagen:<br /><strong>Begründung</strong><br /><strong>Begründung (PDF)</strong><br /><strong>Textliche Festsetzungen: "PDF"</strong><br /><strong>Planzeichenerklärung</strong><br /><strong>Infoblatt, Scoping-Papier, nur Scoping-Protokoll</strong><br />Zugriff für LGV-Betrieb Geodatenanwendung, BOP Eins Baumeister GmbH, BOP Interner TöB Schulung<br /><ul><li>GA Protukoll (20. Mai 2015`) (Testdokument.pdf)</li></ul><br /><strong>Ergänzende Unterlagen</strong><br /><ul><li>Gutachten 1a (Gutachten.pdf)</li><li>LTU (Untersuchung_Laerm.pdf)</li></ul>',
            $message
        );
        $reportEntry->setMessage('{"newPhase":"configuration","oldPhase":"internalphase1","verordnungPDF":"Verordnung.pdf:9d977f32-c8a4-47a4-bda0-0c89f0c6a825:54871:application/pdf","planText":"16.12.2015","newPublicPhase":"configuration","begruendungPDF":"Begruendung.pdf:592530ad-0579-473d-bcd2-24283f3517c6:80204:application/pdf","elements":{"Untersuchung":{"files":["Gutachten:Gutachten.pdf:6ee42968-43bd-4bd2-b8fe-21a3657f501c:13794:application/pdf"]}},"planPDF":"Legende.pdf:0a92191d-71eb-4111-ab07-77567367311b:119994:application/pdf","oldPublicPhase":"participation"}');
        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals($pubAgencyParticipation.': "Verfahrensschritt" von "internalphase1" auf "'.$phaseConfiguration.'" verändert<br />Öffentlichkeitsbeteiligung: "Verfahrensschritt" von "'.$phaseParticipationExternal.'" auf "'.$phaseConfigurationExternal.'" verändert<br />Bei der Umstellung eingestellte Unterlagen:<br /><strong>Begründung (PDF)</strong><br /><strong>Textliche Festsetzungen: "PDF"</strong><br /><strong>Planzeichenerklärung</strong><br /><strong>Untersuchung</strong><br /><ul><li>Gutachten (Gutachten.pdf)</li></ul>',
            $message
        );
        $reportEntry->setMessage('{"newPhase":"configuration","oldPhase":"configuration","verordnungPDF":"Verordnung.pdf:a6358608-35a4-424d-9617-855b9ce35511:54871:application/pdf","planText":"07.12.2015","newPublicPhase":"configuration","begruendungPDF":"Begruendung.pdf:1a611591-11e3-4672-8728-c69c588147d6:80204:application/pdf","elements":{"Untersuchung":{"files":["Lärmgutachten :Untersuchung_Laerm.pdf:bc5bb1fd-950e-49c3-82fb-dff1b1a01c81:13794:application/pdf"]},"Ergänzende Unterlagen":{"files":["Ergänzung BAUM Text:Testdokument.pdf:977a5e65-933a-4847-a53a-2eb46a2366a4:13794:application/pdf"],"access":["Bezirksamt Bergedorf","LGV-Betrieb Geodatenanwendung","Bezirksamt Hamburg-Nord"]}},"planPDF":"Legende.pdf:07c08aae-596e-44ee-af03-fc86dd944933:119994:application/pdf","oldPublicPhase":"earlyparticipation"}');
        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals($pubAgencyParticipation.': "Verfahrensschritt" von "'.$phaseConfiguration.'" auf "'.$phaseConfiguration.'" verändert<br />Öffentlichkeitsbeteiligung: "Verfahrensschritt" von "'.$phaseEarlyParticipationExternal.'" auf "'.$phaseConfigurationExternal.'" verändert<br />Bei der Umstellung eingestellte Unterlagen:<br /><strong>Begründung (PDF)</strong><br /><strong>Textliche Festsetzungen: "PDF"</strong><br /><strong>Planzeichenerklärung</strong><br /><strong>Untersuchung</strong><br /><ul><li>Lärmgutachten  (Untersuchung_Laerm.pdf)</li></ul><br /><strong>Ergänzende Unterlagen</strong><br />Zugriff für Bezirksamt Bergedorf, LGV-Betrieb Geodatenanwendung, Bezirksamt Hamburg-Nord<br /><ul><li>Ergänzung BAUM Text (Testdokument.pdf)</li></ul>',
            $message
        );

        $reportEntry->setMessage('{"newPhase":"configuration","oldPhase":"configuration","planGisName":"Bebauungspläne","verordnungPDF":"Verordnung.pdf:1f3a6302-130d-46cb-98f9-5b173f9d5f8f:54871:application/pdf","planText":"01.11.2015","planGisVisible":true,"newPublicPhase":"configuration","begruendungPDF":"Begruendung_docx.pdf:e3c14673-a908-488e-9719-12b29bb817d2:19058:application/pdf","elements":{"Infoblatt, Scoping-Papier, nur Scoping-Protokoll":{"files":["Unterlagen für das Einleitungsgespräch:Verordnung.pdf:e91aca0f-ad8d-4f15-9891-c21b423cc5a8:54871:application/pdf"],"access":["BOP Interner TöB Schulung","Bezirksamt Bergedorf"]},"Ergänzende Unterlagen":{"files":["Testdokument:Testdokument.pdf:5f9140dc-e608-44a7-b2bb-f974bc442c91:13794:application/pdf"]},"Verteiler und Einladung":{"files":["Verteiler und Einladung:Testdokument.doc:f853692f-8d1f-40ff-ab43-545dd244926d:21504:application/msword"]}},"planPDF":"Legende.pdf:8fb52c37-8fd7-4c65-ac41-d41def101a7f:119994:application/pdf","oldPublicPhase":"earlyparticipation"}');
        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals($pubAgencyParticipation.': "Verfahrensschritt" von "'.$phaseConfiguration.'" auf "'.$phaseConfiguration.'" verändert<br />Öffentlichkeitsbeteiligung: "Verfahrensschritt" von "'.$phaseEarlyParticipationExternal.'" auf "'.$phaseConfigurationExternal.'" verändert<br />Bei der Umstellung eingestellte Unterlagen:<br /><strong>Begründung (PDF)</strong><br /><strong>Textliche Festsetzungen: "PDF"</strong><br /><strong>Planzeichnungs-WMS "Bebauungspläne"</strong><br /><strong>Planzeichenerklärung</strong><br /><strong>Infoblatt, Scoping-Papier, nur Scoping-Protokoll</strong><br />Zugriff für BOP Interner TöB Schulung, Bezirksamt Bergedorf<br /><ul><li>Unterlagen für das Einleitungsgespräch (Verordnung.pdf)</li></ul><br /><strong>Ergänzende Unterlagen</strong><br /><ul><li>Testdokument (Testdokument.pdf)</li></ul><br /><strong>Verteiler und Einladung</strong><br /><ul><li>Verteiler und Einladung (Testdokument.doc)</li></ul>',
            $message
        );
        $reportEntry->setMessage('{"oldPhase":"internalphase2","newPhase":"participation","planText":"16.05.2019","elements":{"files":{"files":{"Begr":"Begruendung.pdf:aad81fab-c019-11e8-b87f-4f2df2384097:80204:application\/pdf"}},"Test_03.05.2019":{"files":{"Testmaterial für Zipfiles (Mittel)":"Testmaterial für Zipfiles (Mittel).zip:13335db8-6da0-11e9-bbbd-782bcb0d78b1:11812799:application\/zip"}}},"paragraph":true}');
        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals($pubAgencyParticipation.': "Verfahrensschritt" von "internalphase2" auf "'.$phaseParticipation.'" verändert<br />Bei der Umstellung eingestellte Unterlagen:<br /><strong>files</strong><br /><ul><li>Begr (Begruendung.pdf)</li></ul><br /><strong>Test_03.05.2019</strong><br /><ul><li>Testmaterial für Zipfiles (Mittel) (Testmaterial für Zipfiles (Mittel).zip)</li></ul>',
            $message
        );

        $reportEntry->setMessage('{"oldPhase":"earlyparticipation","newPhase":"participation","planPDF":"2015-03-27_BVWP_Strae_bersicht_Verfahren_in_Niedersachsen (1).pdf:9ebacaf0-78cb-11ea-ba80-0242ac16ff03:24036:application\/pdf","planDrawPDF":"Legende.pdf:61b7f989-78cb-11ea-ba80-0242ac16ff03:119994:application\/pdf","elements":{"Landschaftsplan-Änderung":{"files":{"test":"4ecb55d3_Theatrhythm-Final-Fantasy-Vivi-Artwork.png:14de0405-b495-11e5-9c91-005056ae0004:352234:image\/png"}}},"paragraphs":{"Textliche Festsetzungen":{"hasParagraphPdf":true},"Begründung":{"hasParagraphPdf":true,"hasParagraphs":true}}}');
        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals($pubAgencyParticipation.': "Verfahrensschritt" von "'.$phaseEarlyParticipation.'" auf "'.$phaseParticipation.'" verändert<br />Bei der Umstellung eingestellte Unterlagen:<br /><strong>Planzeichenerklärung</strong><br /><strong>Planzeichnung als PDF</strong><br /><strong>Landschaftsplan-Änderung</strong><br /><ul><li>test (4ecb55d3_Theatrhythm-Final-Fantasy-Vivi-Artwork.png)</li></ul><br /><strong>Textliche Festsetzungen</strong><br /><ul><li>Datei als PDF</li></ul><br /><strong>Begründung</strong><br /><ul><li>Datei als PDF</li><li>Absatzbezogenes Dokument</li></ul>',
            $message
        );
    }

    public function testConvertProcedureInvitationMessage()
    {
        self::markSkippedForCIIntervention();

        $phaseConfiguration = $this->translator->trans('procedure.phases.internal.configuration');
        $phaseParticipation = $this->translator->trans('procedure.phases.internal.participation');
        $reportEntry = new ReportEntry();
        $reportEntry->setGroup(ReportEntry::GROUP_PROCEDURE)
            ->setCategory(ReportEntry::CATEGORY_INVITATION)
            ->setMessage('{"recipients":[{"ident":"0a723651-2681-48d8-9cda-28d745e5abfb","nameLegal":"BOP Eins Baumeister GmbH","email2":"firmennutzer1.bop1@web.de"},{"ident":"1d6fbfe2-1d4c-43e5-8872-f329912dccd5","nameLegal":"BOP Externer TöB Schulung","email2":"firmennutzer1.bopschulung@web.de"},{"ident":"46951c9c-6e0f-4d42-8c92-b0104c48692a","nameLegal":"BOP Interner TöB Schulung","email2":"user727-01@training.dataport.de"},{"ident":"53f363cc-90a2-4e6e-b205-bab7a14d444c","nameLegal":"FHH-interner Test-TöB","email2":"Bezirke-IT-Verfahren@hamburg-nord.hamburg.de"},{"ident":"33ac09a6-bab1-4f75-b700-ae11885d82be","nameLegal":"LGV-Betrieb Geodatenanwendung","email2":"stefanie.buetefisch@gv.hamburg.de"}],"phase":"configuration","ident":"bd01b67d-d966-40fc-a4f9-40a1cb601a75"}');
        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals($phaseConfiguration.'<br />Verschickt an:<br />BOP Eins Baumeister GmbH<br />BOP Externer TöB Schulung<br />BOP Interner TöB Schulung<br />FHH-interner Test-TöB<br />LGV-Betrieb Geodatenanwendung',
            $message
        );

        $reportEntry->setMessage('{"recipients":[{"ident":"46951c9c-6e0f-4d42-8c92-b0104c48692a","nameLegal":"BOP Interner TöB Schulung","email2":"user727-01@training.dataport.de"}],"phase":"configuration","ident":"c47aa687-cec1-11e7-9c16-0050568a1238"}');
        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals($phaseConfiguration.'<br />Verschickt an:<br />BOP Interner TöB Schulung',
            $message
        );

        $reportEntry->setMessage('{"recipients":[{"ident":"030528df-19d5-4fa5-857a-502251422bdc","nameLegal":"Amt Nordwest","ccEmails":["-"]},{"ident":"3f7ae652-c711-418f-a86d-551da73a2db1","nameLegal":"DEMOS Hamburg","email2":"egal@ich.de"}],"phase":"participation","ident":"049980e9-33cd-4716-b050-77b05c2198c5"}');
        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals($phaseParticipation.'<br />Verschickt an:<br />DEMOS Hamburg',
            $message
        );

        $reportEntry->setMessage('{"recipients":[{"ident":"030528df-19d5-4fa5-857a-502251422bdc","nameLegal":"Amt Nordwest","email2":"","ccEmails":["-"]},{"ident":"3f7ae652-c711-418f-a86d-551da73a2db1","nameLegal":"DEMOS Hamburg","email2":"egal@ich.de"}],"phase":"participation","ident":"049980e9-33cd-4716-b050-77b05c2198c5"}');
        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals($phaseParticipation.'<br />Verschickt an:<br />DEMOS Hamburg',
            $message
        );

        $reportEntry->setMessage('{"recipients":[{"ident":"a659bdac-831b-4c14-823a-05c1d55ccc54","nameLegal":"Amt Flensmuenster-Land","email2":"lars.lud@flens.de"},{"ident":"030528df-19d5-4fa5-857a-502251422bdc","nameLegal":"Amt Nordwest","email2":"bob-sh20@demos-deutschland.de","ccEmails":["ich@hier.de", "ichauch@da.de"]},{"ident":"f62112f8-0985-4f3e-8881-48a9f165f7de","nameLegal":"DEMOS Gesellschaft für E-Partizipation mbH","email2":"rudzik@demos-international.com"}],"phase":"configuration","ident":"ae56e970-3265-11e8-8c0e-0050568a1238"}');
        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals($phaseConfiguration.'<br />Verschickt an:<br />Amt Flensmuenster-Land<br />Amt Nordwest<br />Kopie: ich@hier.de, ichauch@da.de<br />DEMOS Gesellschaft für E-Partizipation mbH',
            $message
        );
    }

    public function testConvertProcedureFinalMailMessage()
    {
        self::markSkippedForCIIntervention();

        $reportEntry = new ReportEntry();
        $reportEntry->setGroup(ReportEntry::GROUP_PROCEDURE)
            ->setCategory(ReportEntry::CATEGORY_FINAL_MAIL)
            ->setMessage('{"procedureId":"45752f51-f68a-11e5-b083-005056ae0004","ident":"45752f51-f68a-11e5-b083-005056ae0004","receiverCount":20,"mailSubject":"asdf","mailBody":"ganz viel Text und noch mehr Text ganz viel Text und noch mehr Text ganz viel Text und noch mehr Text ganz viel Text und noch mehr Text"}');
        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals('Eine E-Mail wurde an alle 20 Einreichenden versendet.<br />Betreff: asdf<br />Nachricht: ganz viel Text und noch mehr Text ganz viel Text und noch mehr Text ganz viel Text und noch mehr Text ganz viel Text und noch mehr Text',
            $message
        );
    }

    public function testConvertStatementFinalMailMessage()
    {
        $procedure = ProcedureFactory::createOne();
        $procedure->setShortUrl($procedure->getId());
        $procedure->_save();

        $statement = StatementFactory::createOne([
            'procedure' => $procedure,
        ]);

        $reportEntry = new ReportEntry();
        $reportEntry->setGroup(ReportEntry::GROUP_STATEMENT)
            ->setCategory(ReportEntry::CATEGORY_FINAL_MAIL)
            ->setMessage(
                '{"procedureId":"'.
                $procedure->getId().
                '","statementId":"'.
                $statement->getId().
                '","externId":"'.
                $statement->getExternId().
                '","ident":"'.
                $procedure->getId().'"}');
        $message = $this->sut->convertMessage($reportEntry);

        self::assertEquals($this->translator->trans('text.protocol.procedure.finalMail', [
            'url'      => $this->router->generate('dplan_assessmenttable_view_table', [
                'procedureId' => $procedure->getId(),
                '_fragment'   => $statement->getId(),
            ]),
            'externId' => $statement->getExternId(), ]),
            $message);
    }

    public function testConvertStatementAddMessage()
    {
        self::markSkippedForCIIntervention();

        $reportEntry = new ReportEntry();
        $reportEntry->setGroup(ReportEntry::GROUP_STATEMENT)
            ->setCategory(ReportEntry::CATEGORY_ADD)
            ->setMessage($this->loadTestJson('convertStatementAddMessage_a.json'));
        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals($this->translator->trans('confirm.statement.submitted', [
            '%externId%' => '1060',
            '%link%'     => $this->router->generate('dplan_assessmenttable_view_table', [
                'procedureId' => 'bdc57287-c623-498b-b3e6-9093fc5404dc',
                '_fragment'   => 'bccb8091-b9a6-4bca-af8f-d0e3e8f28293',
            ]), ]),
            $message);

        $reportEntry->setMessage($this->loadTestJson('convertStatementAddMessage_b.json'));

        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals($this->translator->trans('confirm.statement.submitted', [
            '%externId%' => '1005',
            '%link%'     => $this->router->generate('dplan_assessmenttable_view_table', [
                'procedureId' => '590c21de-c383-48b4-ac67-495d0b6bf6a3',
                '_fragment'   => '3071b412-e0e6-11e7-9a2d-0050568a1238',
            ]), ]),
            $message);

        $reportEntry->setMessage($this->loadTestJson('convertStatementAddMessage_c.json'));
        $message = $this->sut->convertMessage($reportEntry);

        // invalid string should be fixed
        self::assertEquals($this->translator->trans('confirm.statement.submitted', [
            '%externId%' => '1901',
            '%link%'     => $this->router->generate('dplan_assessmenttable_view_table', [
                'procedureId' => '7193eae7-387e-11e6-887d-005056ae0004',
                '_fragment'   => '00089dcf-798d-11e6-ba4b-005056ae0004',
            ]), ]),
            $message);
    }

    public function testConvertInvalidStatementAddMessage()
    {
        self::markSkippedForCIIntervention();

        $reportEntry = new ReportEntry();
        $reportEntry->setGroup(ReportEntry::GROUP_STATEMENT)
            ->setCategory(ReportEntry::CATEGORY_ADD)
            ->setMessage('{I am / Reallyy broken json');
        $message = $this->sut->convertMessage($reportEntry);

        self::assertEquals('', $message);
    }

    public function testConvertStatementCopyMessage()
    {
        self::markSkippedForCIIntervention();

        $reportEntry = new ReportEntry();
        $reportEntry->setGroup(ReportEntry::GROUP_STATEMENT)
            ->setCategory(ReportEntry::CATEGORY_COPY)
            ->setMessage($this->loadTestJson('convertStatementCopyMessage_a.json'));
        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals($this->translator->trans('confirm.statement.id.copied', [
            '%externId%' => 'M9877',
            '%link%'     => $this->router->generate('dplan_assessmenttable_view_table', [
                'procedureId' => '6c6c9b69-a08d-400a-a12d-1df0a34e92d2',
                '_fragment'   => 'ac133955-d790-4499-8247-b2ba89e63fa3',
            ]), ]),
            $message);

        $reportEntry->setMessage($this->loadTestJson('convertStatementCopyMessage_b.json'));

        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals($this->translator->trans('confirm.statement.id.copied', [
            '%externId%' => '1002',
            '%link%'     => $this->router->generate('dplan_assessmenttable_view_table', [
                'procedureId' => 'd879923f-1134-11e6-ab27-0050568a1238',
                '_fragment'   => 'de57985a-da92-11e7-9a2d-0050568a1238',
            ]), ]),
            $message);
    }

    public function testConvertStatementUpdateMessage()
    {
        self::markSkippedForCIIntervention();

        $reportEntry = new ReportEntry();
        $reportEntry->setGroup(ReportEntry::GROUP_STATEMENT)
            ->setCategory(ReportEntry::CATEGORY_UPDATE)
            ->setMessage($this->loadTestJson('convertStatementUpdateMessage_a.json'));
        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals($this->translator->trans('confirm.statement.id.updated', [
            '%externId%' => 'M9954',
            '%link%'     => $this->router->generate('dplan_assessmenttable_view_table', [
                'procedureId' => 'bed9b754-7fd6-11e6-ba4b-005056ae0004',
                '_fragment'   => '327fb08c-d900-11e8-b945-51361dec4aad',
            ]), ]),
            $message);

        $reportEntry->setMessage($this->loadTestJson('convertStatementUpdateMessage_b.json'));

        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals($this->translator->trans('confirm.statement.id.updated', [
            '%externId%' => '1000',
            '%link%'     => $this->router->generate('dplan_assessmenttable_view_table', [
                'procedureId' => 'b231551f-de2a-11e6-a808-0050568a04d7',
                '_fragment'   => '0b1a427b-e648-11e6-a808-0050568a04d7',
            ]), ]),
            $message);
        $reportEntry->setMessage($this->loadTestJson('convertStatementUpdateMessage_c.json'));

        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals($this->translator->trans('confirm.statement.id.updated', [
            '%externId%' => '1191',
            '%link%'     => $this->router->generate('dplan_assessmenttable_view_table', [
                'procedureId' => 'e9ec3189-a124-11e8-bc52-0050568a04d7',
                '_fragment'   => '988ea476-fea6-11e8-b6a2-0050569710bc',
            ]), ]),
            $message);
    }

    public function testConvertStatementDeleteMessage()
    {
        self::markSkippedForCIIntervention();

        $reportEntry = new ReportEntry();
        $reportEntry->setGroup(ReportEntry::GROUP_STATEMENT)
            ->setCategory(ReportEntry::CATEGORY_DELETE)
            ->setMessage($this->loadTestJson('convertStatementDeleteMessage_a.json'));
        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals($this->translator->trans('confirm.statement.id.deleted', [
            '%externId%' => 'M7987', ]),
            $message);
    }

    public function testConvertStatementMoveMessage()
    {
        $reportEntry = new ReportEntry();
        $reportEntry->setGroup(ReportEntry::GROUP_STATEMENT)
            ->setCategory(ReportEntry::CATEGORY_MOVE)
            ->setMessage('{"sourceProcedureId":"bed9b754-7fd6-11e6-ba4b-005056ae0004","sourceProcedureName":"1. Online-Beteiligung Landesplanung - Teilaufstellung Regionalplan I, Sachthema Windenergie","targetProcedureId":"45752f51-f68a-11e5-b083-005056ae0004","targetProcedureName":"Skatepark für Goldhamsters","movedStatementId":"d0c9c2ea-d934-11e8-b945-51361dec4aad","movedStatementExternId":"M10002","placeholderStatementId":"ecbf8983-1b21-11e9-b9cd-782bcb0d78b1","placeholderStatementExternId":"M9955"}');
        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals($this->translator->trans('protocol.statement.moved', [
            '%externId%'            => 'M9955',
            '%targetProcedureName%' => 'Skatepark für Goldhamsters',
            '%newExternId%'         => 'M10002',
        ]),
            $message);
    }

    public function testConvertRegisterInvitationMessage()
    {
        $reportEntry = new ReportEntry();
        $reportEntry->setGroup(ReportEntry::GROUP_PROCEDURE)
            ->setCategory(ReportEntry::CATEGORY_REGISTER_INVITATION)
            ->setMessage('{"recipients":["baerchen@schnupfen.de"],"ccAddresses":[""],"phase":"participation","ident":"80f97050-560f-11e9-99ea-782bcb0d78b1"}');
        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals('Verschickt an:<br />baerchen@schnupfen.de',
            $message);

        $reportEntry
            ->setMessage('{"recipients":["baerchen@schnupfen.de","claudia@testing.de"],"ccAddresses":["claudia@testing.de", "ccadded@test.de"],"phase":"participation","ident":"80f97050-560f-11e9-99ea-782bcb0d78b1"}');
        $message = $this->sut->convertMessage($reportEntry);
        self::assertEquals('Verschickt an:<br />baerchen@schnupfen.de, claudia@testing.de, ccadded@test.de', $message);
    }

    private function loadTestJson(string $filename): string
    {
        // uses local file, no need for flysystem
        $jsonString = file_get_contents("../Resources/$filename");
        if (false === $jsonString) {
            self::fail("failed to load test data from $filename");
        }

        return $jsonString;
    }
}
