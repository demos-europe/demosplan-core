<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Faker\Provider\Uuid;

class Version20230531150204 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refs T31885: fill platform_faq, platform_faq_role, platform_faq_category';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();
        /**
         * categories FAQ ID
         */
        $categories = [
            'Allgemeines' => Uuid::uuid(),
            'Die Planungsdokumente' => Uuid::uuid(),
            'Beteiligung' => Uuid::uuid(),
            'Datenschutz' => Uuid::uuid(),
            'Informationen für Verfahrensträger' => Uuid::uuid(),
            'Informationen für Planungsbüros' => Uuid::uuid(),
            'Informationen für Träger öffentlicher Belange' => Uuid::uuid(),
            'Registrierung / Login' => Uuid::uuid(),
            'Technische Fragen' => Uuid::uuid(),
            'Technische Voraussetzungen' => Uuid::uuid()
        ];

            $roleIds = [
                'FP' => [
                    'RMOPSD' => $this->connection->fetchOne('SELECT _r_id FROM _role WHERE _r_code = "RMOPSD";'),
                    'RMOPSA' => $this->connection->fetchOne('SELECT _r_id FROM _role WHERE _r_code = "RMOPSA";'),
                    'RMOPFB' => $this->connection->fetchOne('SELECT _r_id FROM _role WHERE _r_code = "RMOPFB";'),
                    'RMOPPO' => $this->connection->fetchOne('SELECT _r_id FROM _role WHERE _r_code = "RMOPPO";')
                ],
                'Institutions' => [
                    'RPSOCO' => $this->connection->fetchOne('SELECT _r_id FROM _role WHERE _r_code = "RPSOCO";'),
                    'RPSODE' => $this->connection->fetchOne('SELECT _r_id FROM _role WHERE _r_code = "RPSODE";')
                ],
                'public' => [
                    'RGUEST' => $this->connection->fetchOne('SELECT _r_id FROM _role WHERE _r_code = "RGUEST";'),
                    'RCITIZ' => $this->connection->fetchOne('SELECT _r_id FROM _role WHERE _r_code = "RCITIZ";')
                ]
            ];

        /**
         * FAQ ID
         */
         $faqs = [
             'Was ist DiPlanBeteiligung' => Uuid::uuid(),
             'Welcher Vorgang wird in DiPlanBeteiligung abgebildet' => Uuid::uuid(),
             'Welche Vorteile bietet DiPlanBeteiligung' => Uuid::uuid(),
             'Was sind Behörden und sonstige Träger öffentlicher Belange (TöB)' => Uuid::uuid(),
             'Welche Vorteile bietet DiPlanBeteiligung für Träger öffentlicher Belange (TöB)' => Uuid::uuid(),
             'Welche Vorteile bietet DiPlanBeteiligung für Verfahrensträger' => Uuid::uuid(),
             'Was ist DiPlanBeteiligung?Was ist DiPlanBeteiligung' => Uuid::uuid(),
             'Wo erhalte ich Unterstützung bei der Benutzung von DiPlanBeteiligung1' => Uuid::uuid(),
             'Wo erhalte ich Unterstützung bei der Benutzung von DiPlanBeteiligung2' => Uuid::uuid(),
             'Welche Planungsdokumente gibt es normalerweise' => Uuid::uuid(),
             'Was ist ein Flächennutzungsplan' => Uuid::uuid(),
             'Was ist ein Bebauungsplan' => Uuid::uuid(),
             'Kann die Öffentlichkeitsbeteiligung über DiPlanBeteiligung erfolgen' => Uuid::uuid(),
             'Wie nutze ich DiPlanBeteiligung' => Uuid::uuid(),
             'Woran liegt es, dass ein Planungsverfahren in meiner Region bei DiPlanBeteiligung nicht zu finden ist' => Uuid::uuid(),
             'Wie kann ich meine Stellungnahme abgeben' => Uuid::uuid(),
             'Kann ich meine Stellungnahme auch anonym abgeben' => Uuid::uuid(),
             'Wieso soll ich meinen Namen und meine Anschrift bei der Stellungnahme angeben' => Uuid::uuid(),
             'Was geschieht mit meiner Stellungnahme' => Uuid::uuid(),
             'Für wen sind eingehende Stellungnahmen einsehbar' => Uuid::uuid(),
             'Wie lange bleiben die Verfahren in DiPlanBeteiligung erreichbar - Können Verfahren gelöscht werden1' => Uuid::uuid(),
             'Wie lange bleiben die Verfahren in DiPlanBeteiligung erreichbar - Können Verfahren gelöscht werden2' => Uuid::uuid(),
             'Wie dokumentiere ich als TöB die Beteiligung1' => Uuid::uuid(),
             'Wird eine beteiligte Institution automatisch informiert, wenn ein neues Verfahren ansteht' => Uuid::uuid(),
             'Wo finde ich ergänzende Datenschutzhinweise zur Abgabe von Stellungnahmen' => Uuid::uuid(),
             'Darf ich in meiner Stellungnahme andere Personen benennen' => Uuid::uuid(),
             'Wird meine Stellungnahme durch unterstützende Planungsbüros bearbeitet' => Uuid::uuid(),
             'Welche Rollen gibt es1' => Uuid::uuid(),
             'Welchen Funktionsumfang erhält der Verfahrensträger' => Uuid::uuid(),
             'Wie kann ein Planungsbüro mit dem Fachverfahren arbeiten' => Uuid::uuid(),
             'Wozu gibt es Blaupausen' => Uuid::uuid(),
             'Wie erfolgt die Einbindung von Karten in DiPlanBeteiligung' => Uuid::uuid(),
             'Kann ich DiPlanBeteiligung nutzen, wenn ich selbst keine WMS erstellen kann' => Uuid::uuid(),
             'Was steckt hinter dem Bereich "Originalstellungnahmen“, der von der Abwägungstabelle aus zugänglich ist' => Uuid::uuid(),
             'Wie und an wen wird die Information über das Abwägungsergebnis einer Stellungnahme versendet' => Uuid::uuid(),
             'Was ist das Verfahrensprotokoll' => Uuid::uuid(),
             'Wie wird über DiPlanBeteiligung die rechtssichere Dokumentation der Online-Beteiligung in der Verfahrensakte zu einem Beteilungsverfahren gewährleistet' => Uuid::uuid(),
             'Wie funktionieren Doppel-Rollen' => Uuid::uuid(),
             'Kann ich als Planungsbüro für verschiedene Verfahrensträger tätig werden' => Uuid::uuid(),
             'Welche Aufgaben kann ein Planungsbüro in einem Beteiligungsverfahren übernehmen' => Uuid::uuid(),
             'Welche Rollen gibt es2' => Uuid::uuid(),
             'Was muss ich beachten, wenn es in meiner Organisation keine TöB-Sachbearbeitung gibt' => Uuid::uuid(),
             'Wie dokumentiere ich als TöB die Beteiligung2' => Uuid::uuid(),
             'Ihr Handbuch für die Bedienung von DiPlanBeteiligung' => Uuid::uuid(),
             'Welche Vorteile habe ich, wenn ich mich als Bürger registriere' => Uuid::uuid(),
             'Wie registriere ich mich als Bürger*in' => Uuid::uuid(),
             'Wie registriere ich mich als TöB' => Uuid::uuid(),
             'Wie ändere ich meine Daten' => Uuid::uuid(),
             'Was unternehme ich, wenn ich meinen Zugang vergessen habe' => Uuid::uuid(),
             'Wie lade ich die Planungsdokumente herunter' => Uuid::uuid(),
             'Was kann ich tun, wenn die Grundkarte nicht angezeigt wird' => Uuid::uuid(),
             'Benötigt DiPlanBeteiligung eine bestimmte Infrastruktur' => Uuid::uuid(),
             'Welche Browser kann ich bei der Anwendung von DiPlanBeteiligung einsetzen' => Uuid::uuid(),
             'Kann ich DiPlanBeteiligung nutzen, wenn kein GIS-System zur Verfügung steht' => Uuid::uuid(),
             'Kann das regionale GIS-System eingesetzt werden' => Uuid::uuid(),
             'Was kostet DiPlanBeteiligung für BürgerInnen und TöB' => Uuid::uuid()
         ];

            $this->addSql('INSERT INTO platform_faq_category(id, title, create_date, modify_date, type) VALUES (
                                          ?,
                                          "Allgemeines",
                                          Now(),
                                          Now(),
                                          "system"
                                )
    ',[$categories['Allgemeines']]);

            $this->addSql('INSERT INTO platform_faq_category(id, title, create_date, modify_date, type) VALUES (
                                          ?,
                                          "Die Planungsdokumente",
                                          Now(),
                                          Now(),
                                          "system"
                                )
    ',[$categories['Die Planungsdokumente']]);

        $this->addSql('INSERT INTO platform_faq_category(id, title, create_date, modify_date, type) VALUES (
                                          ?,
                                          "Beteiligung",
                                          Now(),
                                          Now(),
                                          "system"
                                )
    ',[$categories['Beteiligung']]);

        $this->addSql('INSERT INTO platform_faq_category(id, title, create_date, modify_date, type) VALUES (
                                          ?,
                                          "Datenschutz",
                                          Now(),
                                          Now(),
                                          "system"
                                )
    ',[$categories['Datenschutz']]);

        $this->addSql('INSERT INTO platform_faq_category(id, title, create_date, modify_date, type) VALUES (
                                          ?,
                                          "Informationen für Verfahrensträger",
                                          Now(),
                                          Now(),
                                          "system"
                                )
    ',[$categories['Informationen für Verfahrensträger']]);

        $this->addSql('INSERT INTO platform_faq_category(id, title, create_date, modify_date, type) VALUES (
                                          ?,
                                          "Informationen für Planungsbüros",
                                          Now(),
                                          Now(),
                                          "system"
                                )
    ',[$categories['Informationen für Planungsbüros']]);

        $this->addSql('INSERT INTO platform_faq_category(id, title, create_date, modify_date, type) VALUES (
                                          ?,
                                          "Informationen für Träger öffentlicher Belange",
                                          Now(),
                                          Now(),
                                          "system"
                                )
    ',[$categories['Informationen für Träger öffentlicher Belange']]);

        $this->addSql('INSERT INTO platform_faq_category(id, title, create_date, modify_date, type) VALUES (
                                          ?,
                                          "Registrierung / Login",
                                          Now(),
                                          Now(),
                                          "system"
                                )
    ',[$categories['Registrierung / Login']]);

        $this->addSql('INSERT INTO platform_faq_category(id, title, create_date, modify_date, type) VALUES (
                                          ?,
                                          "Technische Fragen",
                                          Now(),
                                          Now(),
                                          "system"
                                )
    ',[$categories['Technische Fragen']]);

        $this->addSql('INSERT INTO platform_faq_category(id, title, create_date, modify_date, type) VALUES (
                                          ?,
                                          "Technische Voraussetzungen",
                                          Now(),
                                          Now(),
                                          "system"
                                )
    ',[$categories['Technische Voraussetzungen']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Was kostet DiPlanBeteiligung für BürgerInnen und TöB?",
                                        "<p>Bürgerinnen und TöB können DiPlanBeteiligung kostenfrei nutzen.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
', [$faqs['Was kostet DiPlanBeteiligung für BürgerInnen und TöB'] ,$categories['Allgemeines']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Was ist DiPlanBeteiligung?",
                                        "<p>DiPlanBeteiligung ist ein Service, der Ihnen die digitale Beteiligung an Planungen, insbesondere im Bauwesen (aktuell Bauleitplanung, später Landesplanung und Planfeststellung), einfach und effizient ermöglicht. Sie nehmen entweder als Bürger*in, Unternehmen oder als Mitarbeiter*in einer Behörde bzw. eines Träger öffentlicher Belange (TöB) an diesem Verfahren teil. Abhängig von Ihrer Rolle stehen Ihnen unterschiedliche Möglichkeiten zur Verfügung, um Ihre Stellungnahmen einzubringen.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
', [$faqs['Was ist DiPlanBeteiligung'] ,$categories['Allgemeines']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Welcher Vorgang wird in DiPlanBeteiligung abgebildet?",
                                        "<p>Die Plattform stellt alle notwendigen Informationen zu den Beteiligungsverfahren zur Verfügung. Es können Einwendungen bzw. Stellungnahmen erstellt und eingereicht werden.<br>DiPlanBeteiligung unterstützt Verfahrensträger bei der Durchführung der Beteiligung von Behörden, Trägern öffentlicher und der Öffentlichkeit.<br>Zu Beginn werden durch den Verfahrensträger Planungsdokumente eingestellt, die von den Beteiligten angesehen und heruntergeladen werden können. Die Beteiligten verfassen ihre Stellungnahmen direkt über DiPlanBeteiligung und reichen sie beim Verfahrensträger ein.<br>Im Ergebnis erhält der Verfahrensträger eine Abwägungstabelle mit den eingegangenen Stellungnahmen. Diese kann dann durch die Abwägungsvorschläge ergänzt werden und dann als Vorlage für die Gremiensitzung genutzt werden.<br>Bei Bedarf kann der Verfahrensträger die Einreichenden über das Ergebnis ihrer fachlichen Bewertung informieren. Das geht natürlich nur, wenn von den Einreichenden eine kontaktfähige E-Mail- oder Postadresse mit angegeben wurde.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
', [$faqs['Welcher Vorgang wird in DiPlanBeteiligung abgebildet'] ,$categories['Allgemeines']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Welche Vorteile bietet DiPlanBeteiligung?",
                                        "<p>DiPlanBeteiligung stellt den komplexen Beteiligungsprozess in einer klaren Struktur dar und ermöglicht damit eine intuitive Bedienbarkeit. Die Anwendung kann aus verschiedenen Browsern, mit verschiedenen Endgeräten jederzeit und von jedem Standort mit Internetzugang genutzt werden.<br>Früher konnten Planungsdokumente nur in den Ämtern eingesehen werden. Mit DiPlanBeteiligung geht das jetzt ganz bequem von zuhause aus – unabhängig von den Öffnungszeiten der Verwaltung. In einer Karte können Sie sich schnell einen Überblick über die Planverfahren Ihrer Kommune machen und Planungsdokumente ganz einfach digital ansehen. Ihre Stellungnahme müssen Sie nicht mehr in Papierform abgeben, sondern Sie können diese direkt online äußern.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
', [$faqs['Welche Vorteile bietet DiPlanBeteiligung'] ,$categories['Allgemeines']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Was sind Behörden und sonstige Träger öffentlicher Belange (TöB)?",
                                        "<p>Behörden und sonstigen Träger öffentlicher Belange (TöB) sind Organisationen, Unternehmen oder Behörden, die öffentliche Aufgaben wahrnehmen. Berührt ein geplantes Bauvorhaben Ihre Zuständigkeit, werden Sie beteiligt. Häufig betrifft dies die Natur- und Denkmalschutzbehörden. Aber auch Strom- und Gasversorgungsunternehmen gehören dazu, wenn es darum geht, die Energieversorgung zu gewährleisten. In einigen Fällen werden auch Nachbargemeinden beteiligt, um zu prüfen, ob die Planung Auswirkungen auf das eigene Gemeindegebiet hat.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
', [$faqs['Was sind Behörden und sonstige Träger öffentlicher Belange (TöB)'] ,$categories['Allgemeines']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Welche Vorteile bietet DiPlanBeteiligung für Träger öffentlicher Belange (TöB)?",
                                        "<p>DiPlanBeteiligung stellt den komplexen Beteiligungsprozess in einer klaren Struktur dar und ermöglicht damit eine intuitive Bedienbarkeit. Die Anwendung kann aus verschiedenen Browsern, mit verschiedenen Endgeräten jederzeit und von jedem Standort mit Internetzugang genutzt werden.<br>Die Planungsdokumente stehen online zur Verfügung, werden übersichtlich dargestellt und lassen sich zusammen bearbeiten. Das spart Zeit, Speicherplatz und Papier. Ihre Stellungnahme können Sie ganz einfach digital abgeben und dabei direkt Zuodnungen zu Planungsdokumenten sowie Einzeichnungen in der Karte vornehmen. Kartenansichten aus Ihrem GIS-System können ebenfalls eingebunden werden.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
', [$faqs['Welche Vorteile bietet DiPlanBeteiligung für Träger öffentlicher Belange (TöB)'] ,$categories['Allgemeines']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Welche Vorteile bietet DiPlanBeteiligung für Verfahrensträger?",
                                        "<p>Der gesamte Prozess der Behördenbeteiligung kann medienbruchfrei vollständig elektronisch erfolgen. Einwände und Behördenbeteiligungen gehen digital ein. Stellungnahmen können direkt mit Einzeichnungen in der Kartenansicht verknüpft und absatzgenau den Planungsdokumenten zugeordnet werden. Analog eingehende Stellungnahmen können manuell  nachgetragen werden. So entsteht ein digitaler Überblick für alle eingereichten Stellungnahmen. Das erhöht die Effizienz und beschleunigt den Bearbeitungsprozess. Die abgegebenen Stellungnahmen fließen direkt in ein Abschlussdokument, das die Grundlage für Ihre Sitzungsvorlage darstellt. Zudem werden Zeit, Papier und Kosten eingespart.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
', [$faqs['Welche Vorteile bietet DiPlanBeteiligung für Verfahrensträger'] ,$categories['Allgemeines']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Was ist DiPlanBeteiligung?Was ist DiPlanBeteiligung?",
                                        "<p>DiPlanBeteiligung ist ein Service, der Ihnen die digitale Beteiligung an Planungen, insbesondere im Bauwesen (aktuell Bauleitplanung, später Landesplanung und Planfeststellung), einfach und effizient ermöglicht. Sie nehmen entweder als Bürger*in, Unternehmen oder als Mitarbeiter*in einer Behörde bzw. eines Träger öffentlicher Belange (TöB) an diesem Verfahren teil. Abhängig von Ihrer Rolle stehen Ihnen unterschiedliche Möglichkeiten zur Verfügung, um Ihre Stellungnahmen einzubringen.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
', [$faqs['Was ist DiPlanBeteiligung?Was ist DiPlanBeteiligung'] ,$categories['Allgemeines']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Wo erhalte ich Unterstützung bei der Benutzung von DiPlanBeteiligung?",
                                        "<p>Bei fachliche Fragen zu dem konkreten Beteiligungsverfahren wenden Sie sich direkt an den Verfahrensträger. Den Ansprechpartner finden Sie auf der Verfahrensseite von DiPlanBeteiligung oder auf der Website der Verwaltung.<br>Viele Informationen erhalten Sie auch hier in den weiteren FAQs.<br>Wenn Sie darüber hinaus Fragen zur Bedienung der Plattform haben, wenden Sie sich gerne telefonisch an den Support:<br>Telefon: <muss noch festgelegt werden></p>",
                                        1,
                                        Now(),
                                        Now()
                            )
', [$faqs['Wo erhalte ich Unterstützung bei der Benutzung von DiPlanBeteiligung1'] ,$categories['Allgemeines']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Wo erhalte ich Unterstützung bei der Benutzung von DiPlanBeteiligung?",
                                        "<p>Bei fachliche Fragen zu dem konkreten Beteiligungsverfahren wenden Sie sich direkt an den Verfahrensträger. Den Ansprechpartner finden Sie auf der Verfahrensseite von DiPlanBeteiligung oder auf der Website der Verwaltung.<br>Viele Informationen erhalten Sie auch hier in den weiteren FAQs und in den PDF-Dokumentationen <Link><br>Wenn Sie darüber hinaus Fragen zur Bedienung der Plattform haben, wenden Sie sich gerne telefonisch an den Support:<br>Telefon: <muss noch festgelegt werden></p>",
                                        1,
                                        Now(),
                                        Now()
                            )
', [$faqs['Wo erhalte ich Unterstützung bei der Benutzung von DiPlanBeteiligung2'] ,$categories['Allgemeines']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Welche Planungsdokumente gibt es normalerweise?",
                                        "<p>Ein Bauleitplan besteht in der Regel aus einer Kartendarstellung, auf dem die geplante Nutzung und die überplanten Flächen erkennbar sind, einer textlichen Beschreibung der zulässigen Nutzungen (\\"Textliche Festsetzungen\\") und einer schriftlichen Begründung. Alle diese Planungsdokumente finden Sie übersichtlich auf DiPlanBeteiligung. Weitere Analysen, Prüfungen und Studien können hinzugefügt werden.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
', [$faqs['Welche Planungsdokumente gibt es normalerweise'] ,$categories['Die Planungsdokumente']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Was ist ein Flächennutzungsplan?",
                                        "<p>Ein Flächennutzungsplan (FNP oder F-Plan) umfasst immer das gesamte Gebiet der Kommune und dient als (städteplanerischer) Überblick. Änderungen können in Teilbereichen vorgenommen werden. Ein Flächennutzungsplan veranschaulicht, wie bestimmte Bereiche und Gebiete genutzt werden sollen und zueinanderstehen. Er ist nicht flächenscharf. Entsprechend des Gegenstromprinzips dürfen räumlich übergeordnete Belange dem kommunalen Flächennutzungsplan nicht widersprechen.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
', [$faqs['Was ist ein Flächennutzungsplan'] ,$categories['Die Planungsdokumente']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Was ist ein Bebauungsplan?",
                                        "<p>Ein Bebauungsplan (B-Plan) betrifft einen bestimmten Bereich, in dem gebaut werden soll. Er kann detailliert zeigen, wie an welcher Stelle gebaut werden darf. Anwohner*innen können zum Beispiel genau erkennen, ob ein Bebauungsplan an ihr Grundstück grenzt oder sich in der Nähe befindet. Er muss sich inhaltlich aus dem F-Plan ableiten.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
', [$faqs['Was ist ein Bebauungsplan'] ,$categories['Die Planungsdokumente']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Kann die Öffentlichkeitsbeteiligung über DiPlanBeteiligung erfolgen?",
                                        "<p>Ja, es können sowohl Träger öffentlicher Belange als auch die Öffentlichkeit beteiligt werden.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
', [$faqs['Kann die Öffentlichkeitsbeteiligung über DiPlanBeteiligung erfolgen'] ,$categories['Beteiligung']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Wie nutze ich DiPlanBeteiligung?",
                                        "<p>Auf der Startseite haben Sie die Möglichkeit Verfahren, die bald in Beteiligung gehen, laufende Verfahren oder bereits abgeschlossene Verfahren zu finden. Wenn Sie ein Verfahren gefunden haben, das Sie besonders interessiert, können Sie sich die jeweiligen Verfahrensseiten anschauen.<br>Informieren Sie sich und entscheiden Sie, ob Sie selbst eine Stellungnahme schreiben und einreichen möchten. Die Möglichkeit dazu haben Sie auf den Verfahrensseiten, sofern der Verfahrensträger dies eingeschaltet hat.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
', [$faqs['Wie nutze ich DiPlanBeteiligung'] ,$categories['Beteiligung']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Woran liegt es, dass ein Planungsverfahren in meiner Region bei DiPlanBeteiligung nicht zu finden ist?",
                                        "<p>Eine Online-Beteiligungslösung muss nicht verpflichtend genutzt werden. Es kann also sein, dass ein Verfahren läuft, in dem die Öffentlichkeit beteiligt wird, es bei DiPlanBeteiligung aber nicht eingestellt wurde.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
', [$faqs['Woran liegt es, dass ein Planungsverfahren in meiner Region bei DiPlanBeteiligung nicht zu finden ist'] ,$categories['Beteiligung']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Wie kann ich meine Stellungnahme abgeben?",
                                        "<p>Sie finden auf der Verfahrensseite links neben der Karte die Schaltfläche \\"Reden Sie mit!\\".<br>Wenn Sie diese Schaltfläche klicken, öffnet sich ein Dialogfeld zur Abgabe einer Stellungnahme. Sie können Ihre Stellungnahme nun direkt in das Textfeld eingeben oder aus einem anderen Dokument hineinkopieren. Aus Gründen des Datenschutzes dürfen Sie keine anderen Personen namentlich nennen oder beschreiben. Bitte bestätigen Sie durch Setzen des entsprechenden Häkchens, dass Sie dies beachtet haben.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
', [$faqs['Wie kann ich meine Stellungnahme abgeben'] ,$categories['Beteiligung']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Kann ich meine Stellungnahme auch anonym abgeben?",
                                        "<p>Sie haben die Möglichkeit, Ihre Stellungnahme zu personalisieren oder anonym abzugeben. Durch Auswahl des entsprechenden Feldes und der Angabe Ihrer E-Mail-Adresse werden Sie informiert, sobald Sie die Auswertung Ihrer Stellungnahme online einsehen können. Außerdem erhalten Sie per Mail die zu Ihrer Stellungnahme gehörende Identifikationsnummer (ID), mit der Sie nach Abschluss des Verfahrens die Bewertung Ihrer Stellungnahme in einer Auswertungstabelle wiederfinden können.<br>Sie können ihre Stellungnahme jedoch auch anonym ohne Angabe einer E-Mail Adresse abgegeben. Dann erhalten Sie natürlich keine Informationen per Mail.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
', [$faqs['Kann ich meine Stellungnahme auch anonym abgeben'] ,$categories['Beteiligung']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Wieso soll ich meinen Namen und meine Anschrift bei der Stellungnahme angeben?",
                                        "<p>Der Verfahrensträger ist verpflichtet, alle fristgerecht eingehenden Stellungnahmen zu prüfen und fachlich zu bewerten. Diese Aufgabe übernehmen Fachleute, die entweder beim Verfahrensträger arbeiten oder bei einem externen Planungsbüro. Diese Fachleute werden Planer genannt. Bei der fachlichen Bewertung wird entschieden, ob eine Stellungnahme berücksichtigt wird. Möglicherweise muss sogar die Planung angepasst werden. Ein entscheidendes Kriterium bei der Bewertung einer Stellungnahme ist, ob der/die Einreichende tatsächlich persönlich von der Planung betroffen ist.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
', [$faqs['Wieso soll ich meinen Namen und meine Anschrift bei der Stellungnahme angeben'] ,$categories['Beteiligung']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Was geschieht mit meiner Stellungnahme?",
                                        "<p>Der Verfahrensträger ist verpflichtet, alle fristgerecht eingehenden Stellungnahmen zu prüfen und fachlich zu bewerten. Diese Aufgabe übernehmen Fachleute, die entweder beim Verfahrensträger arbeiten oder bei einem externen Planungsbüro. Diese Fachleute werden Planer genannt. Bei der fachlichen Bewertung wird entschieden, ob eine Stellungnahme berücksichtigt wird. Möglicherweise muss sogar die Planung angepasst werden.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
', [$faqs['Was geschieht mit meiner Stellungnahme'] ,$categories['Beteiligung']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Für wen sind eingehende Stellungnahmen einsehbar?",
                                        "<p>Es gibt eine technische Trennung der Sichtbarkeit von Stellungnahmen nach Verfahrensträgern. Zusätzlich können durch den Verfahrensträger weitere Personen berechtigt werden, die Stellungnahmen einzusehen (z.B. Planungsbüros).</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
', [$faqs['Für wen sind eingehende Stellungnahmen einsehbar'] , $categories['Beteiligung']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Wie lange bleiben die Verfahren in DiPlanBeteiligung erreichbar? Können Verfahren gelöscht werden? ",
                                        "<p>DiPlanBeteiligung unterstützt bei der Durchführung der Beteiligungsphase und ist kein Archivsystem. Anwender*innen sollten daher alle notwendigen Dokumente und Informationen (Planunterlagen, Stellungnahmen, angehängte Dokumente, Karten) abspeichern und gemäß den Anweisungen der jeweiligen Organisation archivieren.<br>Das Verfahren wird durch den Verfahrensträger verwaltet. Der Verfahrensträger bestimmt wie lange ein Verfahren in DiPlanBeteiligung angezeigt wird.<br>Sobald der Plan rechtskräftig ist, kann das Verfahren in DiPlanBeteiligung gelöscht werden, da der Verfahrensstand im System dann entbehrlich ist.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
', [$faqs['Wie lange bleiben die Verfahren in DiPlanBeteiligung erreichbar - Können Verfahren gelöscht werden1'] , $categories['Beteiligung']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Wie lange bleiben die Verfahren in DiPlanBeteiligung erreichbar? Können Verfahren gelöscht werden? ",
                                        "<p>DiPlanBeteiligung unterstützt bei der Durchführung der Beteiligungsphase und ist kein Archivsystem. Anwender*innen sollten daher alle notwendigen Dokumente und Informationen (Planunterlagen, Stellungnahmen, angehängte Dokumente, Karten) abspeichern und gemäß den Anweisungen der jeweiligen Organisation archivieren. <br>Das Verfahren wird durch den Verfahrensträger verwaltet. Der Verfahrensträger bestimmt wie lange ein Verfahren in DiPlanBeteiligung angezeigt wird. <br>Verfahrensträger sollten neben den in DiPlanBeteiligung hochgeladenen Planunterlagen in jedem Fall die Abwägungstabelle sowie die Originalstellungnahmen und an Stellungnahmen angehängte Dokumente abspeichern und archivieren. <br>Sobald alle Unterlagen in der jeweiligen Organisation dokumentiert sind und der Plan rechtskräftig ist, kann das Verfahren in DiPlanBeteiligung gelöscht werden, da der Verfahrensstand im System dann entbehrlich ist.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
', [$faqs['Wie lange bleiben die Verfahren in DiPlanBeteiligung erreichbar - Können Verfahren gelöscht werden2'] , $categories['Beteiligung']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Wie dokumentiere ich als TöB die Beteiligung?",
                                        "<p>Alle Planungsdokumente (Kartenmaterial, Begründung, Verordnung, ggf. weitere Dokumente, wie z. B. Umweltbericht) werden durch den Verfahrensträger im PDF-Format bereitgestellt und können von den beteiligten TöB heruntergeladen und lokal abgespeichert werden. Es wird empfohlen, pro Beteiligung lokal einen Datei-Ordner anzulegen und alle PDF-Dokumente dort abzuspeichern. Die verfassten Stellungnahmen können als Liste in PDF-Form erzeugt und exportiert werden.<br>Hinweis: DiPlanBeteiligung dient lediglich zur Durchführung der Beteiligung, nicht der Dokumentation und der Archivierung.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
', [$faqs['Wie dokumentiere ich als TöB die Beteiligung1'] , $categories['Beteiligung']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Wird eine beteiligte Institution automatisch informiert, wenn ein neues Verfahren ansteht?",
                                        "<p>Nein. Der Verfahrensträger hat die Möglichkeit aus dem Verfahren heraus die ausgewählten TöBs einzuladen. Eine Einladungs-Mail kann (und sollte) in DiPlanBeteiligung erstellt und verschickt werden. Die E-Mail wird an die unter „Daten der Organisation“ eingegebene Adresse verschickt. Jede Organisation ist dafür verantwortlich, eine erreichbare E-Mail Adresse in den „Daten der Organisation“ einzutragen und diese zu pflegen.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
', [$faqs['Wird eine beteiligte Institution automatisch informiert, wenn ein neues Verfahren ansteht'] , $categories['Beteiligung']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Wo finde ich ergänzende Datenschutzhinweise zur Abgabe von Stellungnahmen?",
                                        "<p>Ergänzende Datenschutzhinweise zur Abgabe von Stellungnahmen im Rahmen der aktuell in Beteiligung befindlichen Planungsverfahren finden Sie in der Regel bei den Planungsdokumenten oder im Bereich Datenschutz <Link></p>",
                                        1,
                                        Now(),
                                        Now()
                            )
', [$faqs['Wo finde ich ergänzende Datenschutzhinweise zur Abgabe von Stellungnahmen'] , $categories['Datenschutz']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Darf ich in meiner Stellungnahme andere Personen benennen?",
                                        "<p>Aus Gründen des Datenschutzes dürfen Sie in Ihrer Stellungnahme keine anderen Personen namentlich benennen oder beschreiben. Sie bestätigen durch das Setzen des entsprechenden Häkchens im Einreichungsformular, dass Sie dies beachtet haben.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
',[$faqs['Darf ich in meiner Stellungnahme andere Personen benennen'] , $categories['Datenschutz']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Wird meine Stellungnahme durch unterstützende Planungsbüros bearbeitet?",
                                        "<p>Informationen über die Mitarbeit von unterstützenden Planungsbüros erhalten Sie in den Datenschutzhinweisen bzw. den ergänzenden Datenschutzhinweisen.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
',[$faqs['Wird meine Stellungnahme durch unterstützende Planungsbüros bearbeitet'] , $categories['Datenschutz']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Welche Rollen gibt es?",
                                        "<p>Fachplaner-Admin<ul><li>-pflegt die „Daten der Organisation“</li><li>-konfiguriert die Blaupause</li><li>-erstellt und betreut Beteiligungsverfahren</li><li>-erstellt und dokumentiert die Abwägungstabelle</li><li>Fachplaner-Sachbearbeiter</li><li>-betreut Beteiligungsverfahren</li><li>-erstellt und dokumentiert die Abwägungstabelle</li><li>Planungsbüro-Sachbearbeiter</li><li>-pflegt die „Daten der Organisation“</li><li>-betreut Beteiligungsverfahren</li><li>-erstellt und dokumentiert die Abwägungstabelle</li></ul></p>",
                                        1,
                                        Now(),
                                        Now()
                            )
',[$faqs['Welche Rollen gibt es1'] , $categories['Informationen für Verfahrensträger']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Welchen Funktionsumfang erhält der Verfahrensträger?",
                                        "<p><ul><li>-Online-Bereitstellung der Planunterlagen zur Einsicht für alle eingeladenen Beteiligten (Behörden/ sonstige Träger öffentlicher Belange)</li><li>-Versand der Einladungs-E-Mail an die ausgewählten Behörden/Träger öffentlicher Belange</li><li>-automatischer Eingang der abgegebenen Stellungnahmen</li><li>-Online-Weiterbearbeitung der Tabelle: Ergänzung der Abwägungsempfehlung</li><li>-Ergänzungsmöglichkeiten für Stellungnahmen, die im klassischen Verfahren eingegangen sind</li><li>-Möglichkeit zur Erstellung der Abwägungstabelle im docx und pdf Format</li><li>-Kostenreduktion für Personal und Material</li></ul></p>",
                                        1,
                                        Now(),
                                        Now()
                            )
',[$faqs['Welchen Funktionsumfang erhält der Verfahrensträger'] , $categories['Informationen für Verfahrensträger']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Wie kann ein Planungsbüro mit dem Fachverfahren arbeiten?",
                                        "<p>Auch Planungsbüros können im Auftrag von Verfahrensträgern mit DiPlanBeteiligung arbeiten. Die Registrierung erfolgt über den Verfahrensträger. Sobald das Planungsbüro registriert ist und auf der Plattform die \\"Daten der Organisation\\" vervollständigt hat, können Verfahrensträger/Fachplaner*innen das Planungsbüro in den \\"Allgemeinen Einstellungen\\" in einem Verfahren zuweisen.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
',[$faqs['Wie kann ein Planungsbüro mit dem Fachverfahren arbeiten'] , $categories['Informationen für Verfahrensträger']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Wozu gibt es Blaupausen?",
                                        "<p>Blaupausen sind Vorlagen für neue Verfahren. Das bedeutet, Sie können Konfigurationen (z. B. Benennungen von Ansprechpartner*innen, wiederkehrende Schlagworte, GIS-Einstellungen, etc.) in einer Blaupause vornehmen, die dann in neue Verfahren übertragen werden. Dem Verfahrensträger stehen mehrere Blaupausen zur Verfügung. Dies ist z. B. dann hilfreich, wenn ein Verfahrensträger für mehrere Beteiligungen spezifische Verfahrenskonfigurationen benötigt. Bei der Erstellung eines neuen Verfahrens werden die Einstellungen einer ausgewählten Blaupause in das Verfahren übernommen.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
',[$faqs['Wozu gibt es Blaupausen'] , $categories['Informationen für Verfahrensträger']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Wie erfolgt die Einbindung von Karten in DiPlanBeteiligung?",
                                        "<p>Eine Karte wird über einen OGC-konformen \\"Web Map Service\\" (WMS) in die interaktive Karte eingebunden. Dieser Web-Dienst ermöglicht die Anzeige einer georeferenzierten Planzeichnung in einem Web-Browser (z.B. Mozilla Firefox). So werden Grundkarten (Base Layer) und sie überlagernde Pläne (Overlays) dargestellt. Zu den Overlays gehören weitere Informations- und Visualisierungslayer, z. B. von bestimmten Abwägungsgebieten. In den GIS-Einstellungen legen die Fachplaner*innen des Verfahrensträgers einzelne WMS  als Ebenen (Layer) in der Karte an. Hier werden Server-URL, Ausgabeformat, Bezugssystem („EPSG“) und die abfragbaren Layer des WMS benötigt. Außerdem muss der Startausschnitt der Karte über die „GIS Einstellungen“ so gesetzt werden, dass der oder die Benutzer*in den Plan beim Aufruf derselben in einer passenden Ansicht erhält.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
',[$faqs['Wie erfolgt die Einbindung von Karten in DiPlanBeteiligung'] , $categories['Informationen für Verfahrensträger']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Kann ich DiPlanBeteiligung nutzen, wenn ich selbst keine WMS erstellen kann?",
                                        "<p>Ja. Oftmals arbeiten Verfahrensträger bereits mit GIS-Dienstleistern zusammen. Sollte dies bei Ihnen der Fall sein, können Sie diese mit der Erzeugung der WMS beauftragen.<br>Alternativ kann ein Planungsbüro mit der Erstellung des WMS beauftragt werden.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
',[$faqs['Kann ich DiPlanBeteiligung nutzen, wenn ich selbst keine WMS erstellen kann'] , $categories['Informationen für Verfahrensträger']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Was steckt hinter dem Bereich \\"Originalstellungnahmen\\“, der von der Abwägungstabelle aus zugänglich ist?",
                                        "<p>In der Abwägungstabelle können Stellungnahmen verändert (oder sogar gelöscht) werden. So können Sie z. B. Ansprachefloskeln aus den Stellungnahmetexten entfernen, um die Abwägungstabelle übersichtlich zu halten. In den Originalstellungnahmen wird jederzeit eine vollständige, unveränderbare Liste aller eingegangenen Stellungnahmen vorgehalten. Aus dieser Liste können Sie die Originale duplizieren und in die Abwägungstabelle kopieren. Die Originalstellungnahmen können nicht gelöscht werden.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
',[$faqs['Was steckt hinter dem Bereich "Originalstellungnahmen“, der von der Abwägungstabelle aus zugänglich ist'] , $categories['Informationen für Verfahrensträger']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Wie und an wen wird die Information über das Abwägungsergebnis einer Stellungnahme versendet?",
                                        "<p>Die Information über das Abwägungsergebnis ein oder mehrerer Stellungnahmen wird durch den Verfahrensträger versendet. In DiPlanBeteiligung erfolgt das für alle Einreichenden, die eine E-Mail-Adresse angegeben haben aus der Anwendung heraus. Für TöB geht die Mitteilung immer sowohl an die einreichende Koordination, als auch an die in den „Daten der Organisation“ hinterlegte E-Mail-Adresse. Bei TöB erhält der oder die Verfasser*in der Stellungnahme die Mitteilung nicht. Es sei denn  die Person ist gleichzeitig einreichende Koordination.<br>Zudem kann die Abwägungstabelle mit den Abwägungsergebnissen in DiPlanBeteiligung zum Download eingestellt werden.<br>Die Abwägungstabelle ist ein öffentliches Papier und kann auch in Gänze allen Trägern per Mail verschickt werden.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
',[$faqs['Wie und an wen wird die Information über das Abwägungsergebnis einer Stellungnahme versendet'] , $categories['Informationen für Verfahrensträger']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Was ist das Verfahrensprotokoll?",
                                        "<p>Das Verfahrensprotokoll speichert einige charakteristische Änderungen und Schritte in einem Beteiligungsverfahren und stellt sie dem Verfahrensträger übersichtlich dar.<br>Momentan werden abgebildet: Namensänderungen, Phasenänderungen und eingegangene Stellungnahmen sowie deren Auslöser (d.h. Nutzer*in) und Zeitpunkte.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
',[$faqs['Was ist das Verfahrensprotokoll'] , $categories['Informationen für Verfahrensträger']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Wie wird über DiPlanBeteiligung die rechtssichere Dokumentation der Online-Beteiligung in der Verfahrensakte zu einem Beteilungsverfahren gewährleistet?",
                                        "<p>Die Verfahren können inklusive aller eingestellten Dokumente, der Abwägungstabelle, dem Verfahrensprotokoll und weiteren Verfahrensinhalten exportiert werden. Dadurch kann der Verfahrensträger selbst die gewünschte Form der Dokumentation und Aufbewahrung sicherstellen.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
',[$faqs['Wie wird über DiPlanBeteiligung die rechtssichere Dokumentation der Online-Beteiligung in der Verfahrensakte zu einem Beteilungsverfahren gewährleistet'] , $categories['Informationen für Verfahrensträger']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Wie funktionieren Doppel-Rollen?",
                                        "<p>Verfahrensträger können nicht nur als Fachplaner*innen auftreten, sondern auch als TöB. Die Rollenkombinationen sind dabei beliebig möglich, solange jeweils nur eine Funktion der jeweiligen Seite zugewiesen wird, z. B. Fachplanung-Administration und Institutions-Sachbearbeitung.<br>Achtung: Eine Doppelrolle Institutions-Koordination und Institutions-Sachbearbeitung gibt es nicht. Die Kombination reduziert die verfügbaren Funktionen für Nutzende. Die Rolle Institutions-Koordination verfügt zudem über alle Rechte der Institutions-Sachbearbeitung.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
',[$faqs['Wie funktionieren Doppel-Rollen'] , $categories['Informationen für Verfahrensträger']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Kann ich als Planungsbüro für verschiedene Verfahrensträger tätig werden?",
                                        "<p>Ja. Sobald Sie die einmalige Registrierung durchgeführt haben, können Ihnen von allen in DiPlanBeteiligung aktiven Verfahrensträgern Verfahren zugeteilt werden.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
',[$faqs['Kann ich als Planungsbüro für verschiedene Verfahrensträger tätig werden'] , $categories['Informationen für Planungsbüros']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Welche Aufgaben kann ein Planungsbüro in einem Beteiligungsverfahren übernehmen?",
                                        "<p>Abgesehen von der Einrichtung von Verfahren, kann das Planungsbüro alle (oder Teile) der oben beschriebene Schritte übernehmen:<ul><li>-Online-Bereitstellung der Planunterlagen zur Einsicht für alle eingeladenen Beteiligten (Behörden/ sonstige Träger öffentlicher Belange)</li><li>-Versand der Einladungs-E-Mail an die ausgewählten Behörden/Träger öffentlicher Belange</li><li>-automatischer Eingang der abgegebenen Stellungnahmen</li><li>-Online-Weiterbearbeitung der Tabelle: Ergänzung der Abwägungsempfehlung</li><li>-Ergänzungsmöglichkeiten für Stellungnahmen, die im klassischen Verfahren eingegangen sind</li><li>-Möglichkeit zur Erstellung der Abwägungstabelle im docx und pdf Format</li><li>-Lediglich das Anlegen eines Verfahrens muss durch den Verfahrensträger erfolgen.</li></ul></p>",
                                        1,
                                        Now(),
                                        Now()
                            )
',[$faqs['Welche Aufgaben kann ein Planungsbüro in einem Beteiligungsverfahren übernehmen'] , $categories['Informationen für Planungsbüros']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Welche Rollen gibt es?",
                                        "<p><b>TöB-Koordinator</b><ul><li>-pflegt die „Daten der Organisation“</li><li>-schreibt Stellungnahmen</li><li>-koordiniert Stellungnahmen der TöB-Sachbearbeiter</li><li>-reicht Stellungnahmen an den Verfahrensträger ein</li></ul><br><b>TöB-Sachbearbeiter</b><ul><li>-schreibt Stellungnahmen</li><li>-gibt Stellungnahmen an den TöB-Koordinator frei</li></ul></p>",
                                        1,
                                        Now(),
                                        Now()
                            )
',[$faqs['Welche Rollen gibt es2'] , $categories['Informationen für Träger öffentlicher Belange']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Was muss ich beachten, wenn es in meiner Organisation keine TöB-Sachbearbeitung gibt?",
                                        "<p>Wenn Sie in Ihrer Organisation die einzige Person sind, die Stellungnahmen zu einem Verfahren verfasst und an den Verfahrensträger einreicht, können Sie den verkürzten Einreichungsprozess nutzen: Unter \\"Meine Daten\\" können Sie einen Haken setzen (siehe Stellungnahmeabgabeprozess -> Auswahl \\"Verkürzter Stellungnahmeprozess\\" statt<br>\\"Standard-Stellungnahmeprozess\\"), dadurch fällt der Schritt \\"An Institutions-Koordination freigeben\\" weg und Sie können Ihre Stellungnahmeentwürfe direkt einreichen.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
',[$faqs['Was muss ich beachten, wenn es in meiner Organisation keine TöB-Sachbearbeitung gibt'] , $categories['Informationen für Träger öffentlicher Belange']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Wie dokumentiere ich als TöB die Beteiligung?",
                                        "<p>Alle Planungsdokumente (Planzeichnung, Begründung, Verordnung, ggf. weitere Dokumente, wie z. B. Untersuchung) werden durch den Verfahrensträger im PDF-Format in DiPlanBeteiligung bereitgestellt und können von den beteiligten TöB heruntergeladen und lokal abgespeichert werden. Es wird empfohlen pro Beteiligung lokal einen Datei-Ordner anzulegen und alle PDF-Dokumente dort abzuspeichern.<br>Die verfassten Stellungnahmen können als Liste in PDF-Form in DiPlanBeteiligung erzeugt werden und lokal abgespeichert werden. Das System bietet auch den Export des Gesamtverfahrens in einem Zip-Ordner an.<br>Hinweis: DiPlanBeteiligung dient lediglich zur Durchführung der Beteiligung, nicht der Dokumentation und der Archivierung.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
',[$faqs['Wie dokumentiere ich als TöB die Beteiligung2'] , $categories['Informationen für Träger öffentlicher Belange']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Ihr Handbuch für die Bedienung von DiPlanBeteiligung",
                                        "<p>Für viele Fragen zur Bedienung von DiPlanBeteiligung, hilft ein Blick in das folgende Handbuch, das wir für Sie zum Download bereitstellen -> Datei <Handbuch für Institutionen als PDF></p>",
                                        1,
                                        Now(),
                                        Now()
                            )
',[$faqs['Ihr Handbuch für die Bedienung von DiPlanBeteiligung'] , $categories['Informationen für Träger öffentlicher Belange']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Welche Vorteile habe ich, wenn ich mich als Bürger registriere?",
                                        "<p>Anders als unregistierte Bürger*innen, können Sie mit der Registrierung als Bürger*in Ihre Stellungnahme als Entwurf speichern und zu einem späteren Zeitpunkt fortführen. Nach Einreichen Ihrer Stellungnahme(n) können Sie sich jederzeit einen einfachen Überblick über Ihre bisher eingereichten Stellungnahmen verschaffen.<br>Grundsätzlich ist die Beteiligung als Bürger aber auch ohne Registrierung möglich.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
',[$faqs['Welche Vorteile habe ich, wenn ich mich als Bürger registriere'] , $categories['Registrierung / Login']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Wie registriere ich mich als Bürger*in?",
                                        "<p>TBA. Ggf. Anleitung zur Registrierung als PDF bereitstellen</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
',[$faqs['Wie registriere ich mich als Bürger*in'] , $categories['Registrierung / Login']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Wie registriere ich mich als TöB?",
                                        "<p>TBA. Ggf. Anleitung zur Registrierung als PDF bereitstellen</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
',[$faqs['Wie registriere ich mich als TöB'] , $categories['Registrierung / Login']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Wie ändere ich meine Daten?",
                                        "<p>Über den IDP</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
',[$faqs['Wie ändere ich meine Daten'] , $categories['Registrierung / Login']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Was unternehme ich, wenn ich meinen Zugang vergessen habe?",
                                        "<p>TBA</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
',[$faqs['Was unternehme ich, wenn ich meinen Zugang vergessen habe'] , $categories['Registrierung / Login']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Wie lade ich die Planungsdokumente herunter?",
                                        "<p>Auf der Übersichtsseite der Planungsdokumente sind diverse Unterlagen wie Textteil, Karte und Umweltbericht abgelegt.<br>Zu jedem Dokument finden Sie eine kurze Beschreibung, einen Link zum Öffnen als PDF sowie ggf. einen Button zum Öffnen eines absatzbezogenen Dokuments im Browser, der insbesondere zur Abgabe der Stellungnahme pro Kapitel dient, oder einen Button zur Abgabe einer Gesamtstellungnahme zum jeweiligen Dokument.<br>Den Link zum PDF-Dokument erkennen Sie an dem Herunterladen-Button und an dem farblich markierten Text. Wenn Sie mit der linken Maustaste auf diesen Textbereich klicken, öffnet sich das Dokument in einem zusätzlich Browserfenster.<br>Sollte es Probleme bei der Anzeige geben, können Sie das Dokument direkt auf Ihrem PC speichern. Klicken Sie dazu auf \\"Herunterladen\\" und speichern Sie auf diesem Wege die benötigten Dokumente. Diese können Sie anschließend direkt vom gewählten Speicherort auf Ihrem PC öffnen.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
',[$faqs['Wie lade ich die Planungsdokumente herunter'] , $categories['Technische Fragen']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Was kann ich tun, wenn die Grundkarte nicht angezeigt wird?",
                                        "<p>Zum Anzeigen der Grundkarte ist es notwendig, dass Sie die Verwendung von Cookies zulassen.Nach erfolgreicher Aktivierung der Cookies und „Aktualisieren“ (F5) der Webseite sollte die Grundkarte bei Ihnen angezeigt werden.<br>Am Beispiel des von Mozilla Firefox und Google Chrome erklären wir Ihnen, wie Sie dies tun:<br><b>Cookies in Firefox aktivieren</b><ul><li>Schritt 1: Öffnen Sie das Firefox-Menü, indem Sie auf die drei horizontalen Linien rechts oben klicken.</li><li>Über die drei horizontalen Linien gelangt man in das Einstellungsmenü von Firefox.</li><li>Schritt 2: Wählen Sie den Eintrag „Einstellungen“, der zusätzlich durch das Zahnrad-Symbol gekennzeichnet ist. Sie gelangen zunächst ins Menü für die allgemeine Konfiguration von Firefox. Klickt man auf das Zahnrad-Symbol, gelangt man zu den Einstellungen.</li><li>Schritt 3: Es öffnet sich ein weiterer Tab mit den Einstellungen. Klicken Sie auf „Datenschutz & Sicherheit“.</li><li>Schritt 4: Unter „Chronik“ können Sie anschließend alle Cookies aktivieren, indem Sie die Option „Firefox wird eine Chronik anlegen“ wählen. Sollten Sie sich alternativ für eine Chronik nach benutzerdefinierten Einstellungen entscheiden, müssen Sie ein Häkchen bei „Cookies von Websites akzeptieren“ setzen. Wählt man die Option „Firefox wird eine Chronik anlegen“, lässt der Firefox-Browser automatisch sämtliche Cookies zu.</li></ul><br><b>Cookies im Chrome-Browser aktivieren</b><ul><li>Bei Chrome funktioniert das Aktivieren der Cookies recht ähnlich wie bei Firefox, nur die Menübezeichnung sieht etwas anders aus.</li><li>Schritt 1: Öffnen Sie die Einstellungen Ihres Webbrowsers über das Drei-Punkte-Symbol und den Menüpunkt „Einstellungen“.</li><li>Schritt 2: Scrollen Sie hinunter, um zu den erweiterten Einstellungen („Erweitert“) zu gelangen.</li><li>Schritt 3: Unter dem Punkt „Sicherheit und Datenschutz“ klicken Sie auf den Eintrag „Inhaltseinstellungen“.</li><li>Schritt 4: Wählen Sie „Cookies“ aus und verschieben den Regler bei „Websites dürfen Cookiedaten speichern und lesen“ nach rechts. Wenn Sie zusätzlich die Option „Lokale Daten nach Schließen des Browsers löschen“ aktivieren, werden alle Cookies nur so lange gespeichert, wie der Browser läuft.</li><li>Haben Sie Websites grundsätzlich erlaubt, Cookiedaten zu speichern und zu lesen, können Sie dennoch die Blockierung von Drittanbieter-Cookies aktivieren.</li></ul><p>",
                                        1,
                                        Now(),
                                        Now()
                            )
',[$faqs['Was kann ich tun, wenn die Grundkarte nicht angezeigt wird'] , $categories['Technische Fragen']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Benötigt DiPlanBeteiligung eine bestimmte Infrastruktur?",
                                        "<p>Nein. Nutzer benötigen lediglich einen Internetzugang.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
',[$faqs['Benötigt DiPlanBeteiligung eine bestimmte Infrastruktur'] , $categories['Technische Voraussetzungen']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Welche Browser kann ich bei der Anwendung von DiPlanBeteiligung einsetzen?",
                                        "<p>Folgende Internet-Browser werden für die Nutzung von DiPlanBeteiligung unterstützt:<ul><li>- Microsoft Edge in der aktuellsten Version, sowie den beiden vorangegangenen Major Versionen</li><li>- Firefox in der aktuellsten Version, sowie der vorangegangenen Major Version</li><li>- alle auf Chromium basierenden Browser wie z.Bsp. Google Chrome</li></ul><br>Bei der Nutzung anderer als den oben genannten Browsern stehen Ihnen ggf. nicht alle Funktionen von DiPlanBeteiligung zur Verfügung.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
',[$faqs['Welche Browser kann ich bei der Anwendung von DiPlanBeteiligung einsetzen'] , $categories['Technische Voraussetzungen']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Kann ich DiPlanBeteiligung nutzen, wenn kein GIS-System zur Verfügung steht?",
                                        "<p>Ja. Um in einem Verfahren eine Karte darzustellen, wird ein WMS (Web Map Service) benötigt, der in DiPlanBeteiligung eingepflegt wird. Um diesen WMS zu erhalten, kann ein GIS-System verwendet werden.<br>Steht Ihnen kein GIS-System zur Verfügung, können sie beispielsweise ein Planungsbüro mit der Erstellung des WMS beauftragen.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
',[$faqs['Kann ich DiPlanBeteiligung nutzen, wenn kein GIS-System zur Verfügung steht'] , $categories['Technische Voraussetzungen']]);

        $this->addSql('INSERT INTO platform_faq(id, platform_faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        ?,
                                        ?,
                                        "Kann das regionale GIS-System eingesetzt werden?",
                                        "<p>Der in DiPlanBeteiligung integrierte Kartenclient stellt Ihre Karten mittels OGC konformen WebMapServices (WMS) dar. Sofern der Verfahrensträger Zugriff auf einen Geodatenserver besitzt, kann der WMS hierüber erzeugt werden.</p>",
                                        1,
                                        Now(),
                                        Now()
                            )
',[$faqs['Kann das regionale GIS-System eingesetzt werden'] , $categories['Technische Voraussetzungen']]);


        foreach ($roleIds as $roleCategories) {
            foreach ($roleCategories as $roleCode => $roleId) {
                $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                [$roleId, $faqs['Was ist DiPlanBeteiligung']]
                );
            }
        }

        foreach ($roleIds as $roleCategories) {
            foreach ($roleCategories as $roleCode => $roleId) {
                $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                    [$roleId, $faqs['Welcher Vorgang wird in DiPlanBeteiligung abgebildet']]
                );
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories === 'public') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Welche Vorteile bietet DiPlanBeteiligung']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories === 'public') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Was sind Behörden und sonstige Träger öffentlicher Belange (TöB)']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories !== 'public') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Was sind Behörden und sonstige Träger öffentlicher Belange (TöB)']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories !== 'public') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Welche Vorteile bietet DiPlanBeteiligung für Verfahrensträger']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories !== 'FP') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Was kostet DiPlanBeteiligung für BürgerInnen und TöB']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories === 'public') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Wo erhalte ich Unterstützung bei der Benutzung von DiPlanBeteiligung1']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories !== 'public') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Wo erhalte ich Unterstützung bei der Benutzung von DiPlanBeteiligung2']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories === 'public') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Welche Planungsdokumente gibt es normalerweise']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories === 'public') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Was ist ein Flächennutzungsplan']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories === 'public') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Was ist ein Bebauungsplan']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories === 'FP') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Kann die Öffentlichkeitsbeteiligung über DiPlanBeteiligung erfolgen']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories !== 'FP') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Wie nutze ich DiPlanBeteiligung']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            foreach ($roleIdsList as $roleCode => $roleId) {
                $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                    [$roleId, $faqs['Woran liegt es, dass ein Planungsverfahren in meiner Region bei DiPlanBeteiligung nicht zu finden ist']]
                );
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories !== 'FP') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Wie kann ich meine Stellungnahme abgeben']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories === 'public') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Kann ich meine Stellungnahme auch anonym abgeben']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories === 'public') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Wieso soll ich meinen Namen und meine Anschrift bei der Stellungnahme angeben']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories !== 'FP') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Was geschieht mit meiner Stellungnahme']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            foreach ($roleIdsList as $roleCode => $roleId) {
                $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                    [$roleId, $faqs['Für wen sind eingehende Stellungnahmen einsehbar']]
                );
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories !== 'FP') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Wie lange bleiben die Verfahren in DiPlanBeteiligung erreichbar - Können Verfahren gelöscht werden1']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories === 'FP') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Wie lange bleiben die Verfahren in DiPlanBeteiligung erreichbar - Können Verfahren gelöscht werden2']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories !== 'public') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Wie dokumentiere ich als TöB die Beteiligung1']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories !== 'public') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Wird eine beteiligte Institution automatisch informiert, wenn ein neues Verfahren ansteht']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            foreach ($roleIdsList as $roleCode => $roleId) {
                $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                    [$roleId, $faqs['Wo finde ich ergänzende Datenschutzhinweise zur Abgabe von Stellungnahmen']]
                );
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            foreach ($roleIdsList as $roleCode => $roleId) {
                $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                    [$roleId, $faqs['Darf ich in meiner Stellungnahme andere Personen benennen']]
                );
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            foreach ($roleIdsList as $roleCode => $roleId) {
                $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                    [$roleId, $faqs['Wird meine Stellungnahme durch unterstützende Planungsbüros bearbeitet']]
                );
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories === 'FP') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Welche Rollen gibt es1']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories === 'FP') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Welchen Funktionsumfang erhält der Verfahrensträger']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories === 'FP') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Wie kann ein Planungsbüro mit dem Fachverfahren arbeiten']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories === 'FP') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Wozu gibt es Blaupausen']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories === 'FP') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Wie erfolgt die Einbindung von Karten in DiPlanBeteiligung']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories === 'FP') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Kann ich DiPlanBeteiligung nutzen, wenn ich selbst keine WMS erstellen kann']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories === 'FP') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Was steckt hinter dem Bereich "Originalstellungnahmen“, der von der Abwägungstabelle aus zugänglich ist']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories === 'FP') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Wie und an wen wird die Information über das Abwägungsergebnis einer Stellungnahme versendet']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories === 'FP') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Was ist das Verfahrensprotokoll']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories === 'FP') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Wie wird über DiPlanBeteiligung die rechtssichere Dokumentation der Online-Beteiligung in der Verfahrensakte zu einem Beteilungsverfahren gewährleistet']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories === 'FP') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Wie funktionieren Doppel-Rollen']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories === 'FP') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Kann ich als Planungsbüro für verschiedene Verfahrensträger tätig werden']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories === 'Institutions') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Welche Rollen gibt es2']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories === 'Institutions') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Was muss ich beachten, wenn es in meiner Organisation keine TöB-Sachbearbeitung gibt']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories === 'Institutions') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Wie dokumentiere ich als TöB die Beteiligung2']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories === 'Institutions') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Ihr Handbuch für die Bedienung von DiPlanBeteiligung']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories === 'public') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Welche Vorteile habe ich, wenn ich mich als Bürger registriere']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories === 'public') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Wie registriere ich mich als Bürger*in']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories !== 'FP') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Wie registriere ich mich als TöB']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            foreach ($roleIdsList as $roleCode => $roleId) {
                $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                    [$roleId, $faqs['Wie ändere ich meine Daten']]
                );
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            foreach ($roleIdsList as $roleCode => $roleId) {
                $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                    [$roleId, $faqs['Was unternehme ich, wenn ich meinen Zugang vergessen habe']]
                );
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            foreach ($roleIdsList as $roleCode => $roleId) {
                $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                    [$roleId, $faqs['Wie lade ich die Planungsdokumente herunter']]
                );
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            foreach ($roleIdsList as $roleCode => $roleId) {
                $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                    [$roleId, $faqs['Was kann ich tun, wenn die Grundkarte nicht angezeigt wird']]
                );
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            foreach ($roleIdsList as $roleCode => $roleId) {
                $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                    [$roleId, $faqs['Benötigt DiPlanBeteiligung eine bestimmte Infrastruktur']]
                );
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            foreach ($roleIdsList as $roleCode => $roleId) {
                $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                    [$roleId, $faqs['Welche Browser kann ich bei der Anwendung von DiPlanBeteiligung einsetzen']]
                );
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories === 'FP') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Kann ich DiPlanBeteiligung nutzen, wenn kein GIS-System zur Verfügung steht']]
                    );
                }
            }
        }

        foreach ($roleIds as $roleCategories => $roleIdsList) {
            if ($roleCategories === 'FP') {
                foreach ($roleIdsList as $roleCode => $roleId) {
                    $this->addSql('INSERT INTO platform_faq_role (role_id, platformFaq_id) VALUES (?, ?)',
                        [$roleId, $faqs['Kann das regionale GIS-System eingesetzt werden']]
                    );
                }
            }
        }

    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('SET foreign_key_checks = 0');
        $this->addSql('TRUNCATE TABLE platform_faq_role;');
        $this->addSql('TRUNCATE TABLE platform_faq_category;');
        $this->addSql('TRUNCATE TABLE platform_faq;');
        $this->addSql('SET foreign_key_checks = 1');

    }

    /**
     * @throws Exception
     */
    private function abortIfNotMysql(): void
    {
        $this->abortIf(
            'mysql' !== $this->connection->getDatabasePlatform()->getName(),
            "Migration can only be executed safely on 'mysql'."
        );
    }
}
