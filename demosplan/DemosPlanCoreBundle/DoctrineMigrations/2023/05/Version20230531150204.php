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

            $categories = [
                'Allgemeines' => Uuid::uuid(),
                'Die Planungsdokumente' => Uuid::uuid(),
                'Beteiligung'=> Uuid::uuid(),
                'Datenschutz'=> Uuid::uuid(),
                'Informationen für Verfahrensträger'=> Uuid::uuid(),
                'Informationen für Planungsbüros'=> Uuid::uuid(),
                'Informationen für Träger öffentlicher Belange'=> Uuid::uuid(),
                'Registrierung / Login'=> Uuid::uuid(),
                'Technische Fragen'=> Uuid::uuid(),
                'Technische Voraussetzungen'=> Uuid::uuid()
            ];

            $id = Uuid::uuid();
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
    '[$categories['Die Planungsdokumente']]);
        $this->addSql('INSERT INTO platform_faq_category(id, title, create_date, modify_date, type) VALUES (
                                          ?,
                                          "Beteiligung",
                                          Now(),
                                          Now(),
                                          "system"
                                )
    '[$categories['Beteiligung']]);
        $this->addSql('INSERT INTO platform_faq_category(id, title, create_date, modify_date, type) VALUES (
                                          ?,
                                          "Datenschutz",
                                          Now(),
                                          Now(),
                                          "system"
                                )
    '[$categories['Datenschutz']]);
        $this->addSql('INSERT INTO platform_faq_category(id, title, create_date, modify_date, type) VALUES (
                                          ?,
                                          "Informationen für Verfahrensträger",
                                          Now(),
                                          Now(),
                                          "system"
                                )
    '[$categories['Informationen für Verfahrensträger']]);
        $this->addSql('INSERT INTO platform_faq_category(id, title, create_date, modify_date, type) VALUES (
                                          ?,
                                          "Informationen für Planungsbüros",
                                          Now(),
                                          Now(),
                                          "system"
                                )
    '[$categories['Informationen für Planungsbüros']]);
        $this->addSql('INSERT INTO platform_faq_category(id, title, create_date, modify_date, type) VALUES (
                                          ?,
                                          "Informationen für Träger öffentlicher Belange",
                                          Now(),
                                          Now(),
                                          "system"
                                )
    '[$categories['Informationen für Träger öffentlicher Belange']]);
        $this->addSql('INSERT INTO platform_faq_category(id, title, create_date, modify_date, type) VALUES (
                                          ?,
                                          "Registrierung / Login",
                                          Now(),
                                          Now(),
                                          "system"
                                )
    '[$categories['Registrierung / Login']]);
        $this->addSql('INSERT INTO platform_faq_category(id, title, create_date, modify_date, type) VALUES (
                                          ?,
                                          "Technische Fragen",
                                          Now(),
                                          Now(),
                                          "system"
                                )
    '[$categories['Technische Fragen']]);
        $this->addSql('INSERT INTO platform_faq_category(id, title, create_date, modify_date, type) VALUES (
                                          ?,
                                          "Technische Voraussetzungen",
                                          Now(),
                                          Now(),
                                          "system"
                                )
    '[$categories['Technische Voraussetzungen']]);


        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Was ist DiPlanBeteiligung?",
                                        "DiPlanBeteiligung ist ein Service, der Ihnen die digitale Beteiligung an Planungen, insbesondere im Bauwesen (aktuell Bauleitplanung, später Landesplanung und Planfeststellung), einfach und effizient ermöglicht. Sie nehmen entweder als Bürger*in, Unternehmen oder als Mitarbeiter*in einer Behörde bzw. eines Träger öffentlicher Belange (TöB) an diesem Verfahren teil. Abhängig von Ihrer Rolle stehen Ihnen unterschiedliche Möglichkeiten zur Verfügung, um Ihre Stellungnahmen einzubringen.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Allgemeines']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Welcher Vorgang wird in DiPlanBeteiligung abgebildet?",
                                        "Die Plattform stellt alle notwendigen Informationen zu den Beteiligungsverfahren zur Verfügung. Es können Einwendungen bzw. Stellungnahmen erstellt und eingereicht werden.
DiPlanBeteiligung unterstützt Verfahrensträger bei der Durchführung der Beteiligung von Behörden, Trägern öffentlicher und der Öffentlichkeit.
Zu Beginn werden durch den Verfahrensträger Planungsdokumente eingestellt, die von den Beteiligten angesehen und heruntergeladen werden können. Die Beteiligten verfassen ihre Stellungnahmen direkt über DiPlanBeteiligung und reichen sie beim Verfahrensträger ein.
Im Ergebnis erhält der Verfahrensträger eine Abwägungstabelle mit den eingegangenen Stellungnahmen. Diese kann dann durch die Abwägungsvorschläge ergänzt werden und dann als Vorlage für die Gremiensitzung genutzt werden.
Bei Bedarf kann der Verfahrensträger die Einreichenden über das Ergebnis ihrer fachlichen Bewertung informieren. Das geht natürlich nur, wenn von den Einreichenden eine kontaktfähige E-Mail- oder Postadresse mit angegeben wurde.
",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Allgemeines']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Welche Vorteile bietet DiPlanBeteiligung?",
                                        "DiPlanBeteiligung stellt den komplexen Beteiligungsprozess in einer klaren Struktur dar und ermöglicht damit eine intuitive Bedienbarkeit. Die Anwendung kann aus verschiedenen Browsern, mit verschiedenen Endgeräten jederzeit und von jedem Standort mit Internetzugang genutzt werden.
Früher konnten Planungsdokumente nur in den Ämtern eingesehen werden. Mit DiPlanBeteiligung geht das jetzt ganz bequem von zuhause aus – unabhängig von den Öffnungszeiten der Verwaltung. In einer Karte können Sie sich schnell einen Überblick über die Planverfahren Ihrer Kommune machen und Planungsdokumente ganz einfach digital ansehen. Ihre Stellungnahme müssen Sie nicht mehr in Papierform abgeben, sondern Sie können diese direkt online äußern.
",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Allgemeines']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Was sind Behörden und sonstige Träger öffentlicher Belange (TöB)?",
                                        "Behörden und sonstigen Träger öffentlicher Belange (TöB) sind Organisationen, Unternehmen oder Behörden, die öffentliche Aufgaben wahrnehmen. Berührt ein geplantes Bauvorhaben Ihre Zuständigkeit, werden Sie beteiligt. Häufig betrifft dies die Natur- und Denkmalschutzbehörden. Aber auch Strom- und Gasversorgungsunternehmen gehören dazu, wenn es darum geht, die Energieversorgung zu gewährleisten. In einigen Fällen werden auch Nachbargemeinden beteiligt, um zu prüfen, ob die Planung Auswirkungen auf das eigene Gemeindegebiet hat.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Allgemeines']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Welche Vorteile bietet DiPlanBeteiligung für Träger öffentlicher Belange (TöB)?",
                                        "DiPlanBeteiligung stellt den komplexen Beteiligungsprozess in einer klaren Struktur dar und ermöglicht damit eine intuitive Bedienbarkeit. Die Anwendung kann aus verschiedenen Browsern, mit verschiedenen Endgeräten jederzeit und von jedem Standort mit Internetzugang genutzt werden.
Die Planungsdokumente stehen online zur Verfügung, werden übersichtlich dargestellt und lassen sich zusammen bearbeiten. Das spart Zeit, Speicherplatz und Papier. Ihre Stellungnahme können Sie ganz einfach digital abgeben und dabei direkt Zuodnungen zu Planungsdokumenten sowie Einzeichnungen in der Karte vornehmen. Kartenansichten aus Ihrem GIS-System können ebenfalls eingebunden werden.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Allgemeines']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Welche Vorteile bietet DiPlanBeteiligung für Verfahrensträger?",
                                        "Der gesamte Prozess der Behördenbeteiligung kann medienbruchfrei vollständig elektronisch erfolgen. Einwände und Behördenbeteiligungen gehen digital ein. Stellungnahmen können direkt mit Einzeichnungen in der Kartenansicht verknüpft und absatzgenau den Planungsdokumenten zugeordnet werden. Analog eingehende Stellungnahmen können manuell  nachgetragen werden. So entsteht ein digitaler Überblick für alle eingereichten Stellungnahmen. Das erhöht die Effizienz und beschleunigt den Bearbeitungsprozess. Die abgegebenen Stellungnahmen fließen direkt in ein Abschlussdokument, das die Grundlage für Ihre Sitzungsvorlage darstellt. Zudem werden Zeit, Papier und Kosten eingespart.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Allgemeines']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Was ist DiPlanBeteiligung?Was ist DiPlanBeteiligung?",
                                        "DiPlanBeteiligung ist ein Service, der Ihnen die digitale Beteiligung an Planungen, insbesondere im Bauwesen (aktuell Bauleitplanung, später Landesplanung und Planfeststellung), einfach und effizient ermöglicht. Sie nehmen entweder als Bürger*in, Unternehmen oder als Mitarbeiter*in einer Behörde bzw. eines Träger öffentlicher Belange (TöB) an diesem Verfahren teil. Abhängig von Ihrer Rolle stehen Ihnen unterschiedliche Möglichkeiten zur Verfügung, um Ihre Stellungnahmen einzubringen.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Allgemeines']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Wo erhalte ich Unterstützung bei der Benutzung von DiPlanBeteiligung?",
                                        "Bei fachliche Fragen zu dem konkreten Beteiligungsverfahren wenden Sie sich direkt an den Verfahrensträger. Den Ansprechpartner finden Sie auf der Verfahrensseite von DiPlanBeteiligung oder auf der Website der Verwaltung.
Viele Informationen erhalten Sie auch hier in den weiteren FAQs.
Wenn Sie darüber hinaus Fragen zur Bedienung der Plattform haben, wenden Sie sich gerne telefonisch an den Support:
Telefon: <muss noch festgelegt werden>",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Allgemeines']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Wo erhalte ich Unterstützung bei der Benutzung von DiPlanBeteiligung?",
                                        "Bei fachliche Fragen zu dem konkreten Beteiligungsverfahren wenden Sie sich direkt an den Verfahrensträger. Den Ansprechpartner finden Sie auf der Verfahrensseite von DiPlanBeteiligung oder auf der Website der Verwaltung.
Viele Informationen erhalten Sie auch hier in den weiteren FAQs und in den PDF-Dokumentationen <Link>
Wenn Sie darüber hinaus Fragen zur Bedienung der Plattform haben, wenden Sie sich gerne telefonisch an den Support:
Telefon: <muss noch festgelegt werden>",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Allgemeines']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Welche Planungsdokumente gibt es normalerweise?",
                                        "Ein Bauleitplan besteht in der Regel aus einer Kartendarstellung, auf dem die geplante Nutzung und die überplanten Flächen erkennbar sind, einer textlichen Beschreibung der zulässigen Nutzungen (\\"Textliche Festsetzungen\\") und einer schriftlichen Begründung. Alle diese Planungsdokumente finden Sie übersichtlich auf DiPlanBeteiligung. Weitere Analysen, Prüfungen und Studien können hinzugefügt werden",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Die Planungsdokumente']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Was ist ein Flächennutzungsplan?",
                                        "Ein Flächennutzungsplan (FNP oder F-Plan) umfasst immer das gesamte Gebiet der Kommune und dient als (städteplanerischer) Überblick. Änderungen können in Teilbereichen vorgenommen werden. Ein Flächennutzungsplan veranschaulicht, wie bestimmte Bereiche und Gebiete genutzt werden sollen und zueinanderstehen. Er ist nicht flächenscharf. Entsprechend des Gegenstromprinzips dürfen räumlich übergeordnete Belange dem kommunalen Flächennutzungsplan nicht widersprechen.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Die Planungsdokumente']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Was ist ein Bebauungsplan?",
                                        "Ein Bebauungsplan (B-Plan) betrifft einen bestimmten Bereich, in dem gebaut werden soll. Er kann detailliert zeigen, wie an welcher Stelle gebaut werden darf. Anwohner*innen können zum Beispiel genau erkennen, ob ein Bebauungsplan an ihr Grundstück grenzt oder sich in der Nähe befindet. Er muss sich inhaltlich aus dem F-Plan ableiten.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Die Planungsdokumente']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Was ist ein Bebauungsplan?",
                                        "Ein Bebauungsplan (B-Plan) betrifft einen bestimmten Bereich, in dem gebaut werden soll. Er kann detailliert zeigen, wie an welcher Stelle gebaut werden darf. Anwohner*innen können zum Beispiel genau erkennen, ob ein Bebauungsplan an ihr Grundstück grenzt oder sich in der Nähe befindet. Er muss sich inhaltlich aus dem F-Plan ableiten.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Beteiligung']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Kann die Öffentlichkeitsbeteiligung über DiPlanBeteiligung erfolgen?",
                                        "Ja, es können sowohl Träger öffentlicher Belange als auch die Öffentlichkeit beteiligt werden.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Beteiligung']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Wie nutze ich DiPlanBeteiligung?",
                                        "Auf der Startseite haben Sie die Möglichkeit Verfahren, die bald in Beteiligung gehen, laufende Verfahren oder bereits abgeschlossene Verfahren zu finden. Wenn Sie ein Verfahren gefunden haben, das Sie besonders interessiert, können Sie sich die jeweiligen Verfahrensseiten anschauen.
Informieren Sie sich und entscheiden Sie, ob Sie selbst eine Stellungnahme schreiben und einreichen möchten. Die Möglichkeit dazu haben Sie auf den Verfahrensseiten, sofern der Verfahrensträger dies eingeschaltet hat.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Beteiligung']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Woran liegt es, dass ein Planungsverfahren in meiner Region bei DiPlanBeteiligung nicht zu finden ist?",
                                        "Eine Online-Beteiligungslösung muss nicht verpflichtend genutzt werden. Es kann also sein, dass ein Verfahren läuft, in dem die Öffentlichkeit beteiligt wird, es bei DiPlanBeteiligung aber nicht eingestellt wurde. ",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Beteiligung']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Wie kann ich meine Stellungnahme abgeben?",
                                        "Sie finden auf der Verfahrensseite links neben der Karte die Schaltfläche \\"Reden Sie mit!\\".
Wenn Sie diese Schaltfläche klicken, öffnet sich ein Dialogfeld zur Abgabe einer Stellungnahme. Sie können Ihre Stellungnahme nun direkt in das Textfeld eingeben oder aus einem anderen Dokument hineinkopieren. Aus Gründen des Datenschutzes dürfen Sie keine anderen Personen namentlich nennen oder beschreiben. Bitte bestätigen Sie durch Setzen des entsprechenden Häkchens, dass Sie dies beachtet haben.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Beteiligung']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Kann ich meine Stellungnahme auch anonym abgeben?",
                                        "Sie haben die Möglichkeit, Ihre Stellungnahme zu personalisieren oder anonym abzugeben. Durch Auswahl des entsprechenden Feldes und der Angabe Ihrer E-Mail-Adresse werden Sie informiert, sobald Sie die Auswertung Ihrer Stellungnahme online einsehen können. Außerdem erhalten Sie per Mail die zu Ihrer Stellungnahme gehörende Identifikationsnummer (ID), mit der Sie nach Abschluss des Verfahrens die Bewertung Ihrer Stellungnahme in einer Auswertungstabelle wiederfinden können.
Sie können ihre Stellungnahme jedoch auch anonym ohne Angabe einer E-Mail Adresse abgegeben. Dann erhalten Sie natürlich keine Informationen per Mail.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Beteiligung']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Wieso soll ich meinen Namen und meine Anschrift bei der Stellungnahme angeben?",
                                        "Der Verfahrensträger ist verpflichtet, alle fristgerecht eingehenden Stellungnahmen zu prüfen und fachlich zu bewerten. Diese Aufgabe übernehmen Fachleute, die entweder beim Verfahrensträger arbeiten oder bei einem externen Planungsbüro. Diese Fachleute werden Planer genannt. Bei der fachlichen Bewertung wird entschieden, ob eine Stellungnahme berücksichtigt wird. Möglicherweise muss sogar die Planung angepasst werden. Ein entscheidendes Kriterium bei der Bewertung einer Stellungnahme ist, ob der/die Einreichende tatsächlich persönlich von der Planung betroffen ist.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Beteiligung']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Was geschieht mit meiner Stellungnahme?",
                                        "Der Verfahrensträger ist verpflichtet, alle fristgerecht eingehenden Stellungnahmen zu prüfen und fachlich zu bewerten. Diese Aufgabe übernehmen Fachleute, die entweder beim Verfahrensträger arbeiten oder bei einem externen Planungsbüro. Diese Fachleute werden Planer genannt. Bei der fachlichen Bewertung wird entschieden, ob eine Stellungnahme berücksichtigt wird. Möglicherweise muss sogar die Planung angepasst werden. ",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Beteiligung']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Für wen sind eingehende Stellungnahmen einsehbar?",
                                        "Es gibt eine technische Trennung der Sichtbarkeit von Stellungnahmen nach Verfahrensträgern. Zusätzlich können durch den Verfahrensträger weitere Personen berechtigt werden, die Stellungnahmen einzusehen (z.B. Planungsbüros).",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Beteiligung']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Wie lange bleiben die Verfahren in DiPlanBeteiligung erreichbar? Können Verfahren gelöscht werden? ",
                                        "DiPlanBeteiligung unterstützt bei der Durchführung der Beteiligungsphase und ist kein Archivsystem. Anwender*innen sollten daher alle notwendigen Dokumente und Informationen (Planunterlagen, Stellungnahmen, angehängte Dokumente, Karten) abspeichern und gemäß den Anweisungen der jeweiligen Organisation archivieren.
Das Verfahren wird durch den Verfahrensträger verwaltet. Der Verfahrensträger bestimmt wie lange ein Verfahren in DiPlanBeteiligung angezeigt wird.
Sobald der Plan rechtskräftig ist, kann das Verfahren in DiPlanBeteiligung gelöscht werden, da der Verfahrensstand im System dann entbehrlich ist.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Beteiligung']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Wie lange bleiben die Verfahren in DiPlanBeteiligung erreichbar? Können Verfahren gelöscht werden? ",
                                        "DiPlanBeteiligung unterstützt bei der Durchführung der Beteiligungsphase und ist kein Archivsystem. Anwender*innen sollten daher alle notwendigen Dokumente und Informationen (Planunterlagen, Stellungnahmen, angehängte Dokumente, Karten) abspeichern und gemäß den Anweisungen der jeweiligen Organisation archivieren.
Das Verfahren wird durch den Verfahrensträger verwaltet. Der Verfahrensträger bestimmt wie lange ein Verfahren in DiPlanBeteiligung angezeigt wird.
Verfahrensträger sollten neben den in DiPlanBeteiligung hochgeladenen Planunterlagen in jedem Fall die Abwägungstabelle sowie die Originalstellungnahmen und an Stellungnahmen angehängte Dokumente abspeichern und archivieren.
Sobald alle Unterlagen in der jeweiligen Organisation dokumentiert sind und der Plan rechtskräftig ist, kann das Verfahren in DiPlanBeteiligung gelöscht werden, da der Verfahrensstand im System dann entbehrlich ist.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Beteiligung']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Wie dokumentiere ich als TöB die Beteiligung?",
                                        "Alle Planungsdokumente (Kartenmaterial, Begründung, Verordnung, ggf. weitere Dokumente, wie z. B. Umweltbericht) werden durch den Verfahrensträger im PDF-Format bereitgestellt und können von den beteiligten TöB heruntergeladen und lokal abgespeichert werden. Es wird empfohlen, pro Beteiligung lokal einen Datei-Ordner anzulegen und alle PDF-Dokumente dort abzuspeichern. Die verfassten Stellungnahmen können als Liste in PDF-Form erzeugt und exportiert werden.
Hinweis: DiPlanBeteiligung dient lediglich zur Durchführung der Beteiligung, nicht der Dokumentation und der Archivierung.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Beteiligung']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Wird eine beteiligte Institution automatisch informiert, wenn ein neues Verfahren ansteht?",
                                        "Nein. Der Verfahrensträger hat die Möglichkeit aus dem Verfahren heraus die ausgewählten TöBs einzuladen. Eine Einladungs-Mail kann (und sollte) in DiPlanBeteiligung erstellt und verschickt werden. Die E-Mail wird an die unter „Daten der Organisation“ eingegebene Adresse verschickt. Jede Organisation ist dafür verantwortlich, eine erreichbare E-Mail Adresse in den „Daten der Organisation“ einzutragen und diese zu pflegen.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Beteiligung']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Wo finde ich ergänzende Datenschutzhinweise zur Abgabe von Stellungnahmen?",
                                        "Ergänzende Datenschutzhinweise zur Abgabe von Stellungnahmen im Rahmen der aktuell in Beteiligung befindlichen Planungsverfahren finden Sie in der Regel bei den Planungsdokumenten oder im Bereich Datenschutz <Link>",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Datenschutz']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Darf ich in meiner Stellungnahme andere Personen benennen?",
                                        "Aus Gründen des Datenschutzes dürfen Sie in Ihrer Stellungnahme keine anderen Personen namentlich benennen oder beschreiben. Sie bestätigen durch das Setzen des entsprechenden Häkchens im Einreichungsformular, dass Sie dies beachtet haben.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Datenschutz']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Wird meine Stellungnahme durch unterstützende Planungsbüros bearbeitet?",
                                        "Informationen über die Mitarbeit von unterstützenden Planungsbüros erhalten Sie in den Datenschutzhinweisen bzw. den ergänzenden Datenschutzhinweisen.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Datenschutz']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Welche Rollen gibt es?",
                                        "Fachplaner-Admin
-pflegt die „Daten der Organisation“
-konfiguriert die Blaupause
-erstellt und betreut Beteiligungsverfahren
-erstellt und dokumentiert die Abwägungstabelle
Fachplaner-Sachbearbeiter
-betreut Beteiligungsverfahren
-erstellt und dokumentiert die Abwägungstabelle
Planungsbüro-Sachbearbeiter
-pflegt die „Daten der Organisation“
-betreut Beteiligungsverfahren
-erstellt und dokumentiert die Abwägungstabelle",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Informationen für Verfahrensträger']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Welchen Funktionsumfang erhält der Verfahrensträger?",
                                        "-Online-Bereitstellung der Planunterlagen zur Einsicht für alle eingeladenen Beteiligten (Behörden/ sonstige Träger öffentlicher Belange)
-Versand der Einladungs-E-Mail an die ausgewählten Behörden/Träger öffentlicher Belange
-automatischer Eingang der abgegebenen Stellungnahmen
-Online-Weiterbearbeitung der Tabelle: Ergänzung der Abwägungsempfehlung
-Ergänzungsmöglichkeiten für Stellungnahmen, die im klassischen Verfahren eingegangen sind
-Möglichkeit zur Erstellung der Abwägungstabelle im docx und pdf Format
-Kostenreduktion für Personal und Material",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Informationen für Verfahrensträger']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Wie kann ein Planungsbüro mit dem Fachverfahren arbeiten?",
                                        "Auch Planungsbüros können im Auftrag von Verfahrensträgern mit DiPlanBeteiligung arbeiten. Die Registrierung erfolgt über den Verfahrensträger. Sobald das Planungsbüro registriert ist und auf der Plattform die \\"Daten der Organisation\\" vervollständigt hat, können Verfahrensträger/Fachplaner*innen das Planungsbüro in den \\"Allgemeinen Einstellungen\\" in einem Verfahren zuweisen.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Informationen für Verfahrensträger']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Wozu gibt es Blaupausen?",
                                        "Blaupausen sind Vorlagen für neue Verfahren. Das bedeutet, Sie können Konfigurationen (z. B. Benennungen von Ansprechpartner*innen, wiederkehrende Schlagworte, GIS-Einstellungen, etc.) in einer Blaupause vornehmen, die dann in neue Verfahren übertragen werden. Dem Verfahrensträger stehen mehrere Blaupausen zur Verfügung. Dies ist z. B. dann hilfreich, wenn ein Verfahrensträger für mehrere Beteiligungen spezifische Verfahrenskonfigurationen benötigt. Bei der Erstellung eines neuen Verfahrens werden die Einstellungen einer ausgewählten Blaupause in das Verfahren übernommen.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Informationen für Verfahrensträger']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Wie erfolgt die Einbindung von Karten in DiPlanBeteiligung?",
                                        "Eine Karte wird über einen OGC-konformen \\"Web Map Service\\" (WMS) in die interaktive Karte eingebunden. Dieser Web-Dienst ermöglicht die Anzeige einer georeferenzierten Planzeichnung in einem Web-Browser (z.B. Mozilla Firefox). So werden Grundkarten (Base Layer) und sie überlagernde Pläne (Overlays) dargestellt. Zu den Overlays gehören weitere Informations- und Visualisierungslayer, z. B. von bestimmten Abwägungsgebieten. In den GIS-Einstellungen legen die Fachplaner*innen des Verfahrensträgers einzelne WMS  als Ebenen (Layer) in der Karte an. Hier werden Server-URL, Ausgabeformat, Bezugssystem („EPSG“) und die abfragbaren Layer des WMS benötigt. Außerdem muss der Startausschnitt der Karte über die „GIS Einstellungen“ so gesetzt werden, dass der oder die Benutzer*in den Plan beim Aufruf derselben in einer passenden Ansicht erhält.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Informationen für Verfahrensträger']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Kann ich DiPlanBeteiligung nutzen, wenn ich selbst keine WMS erstellen kann?",
                                        "Ja. Oftmals arbeiten Verfahrensträger bereits mit GIS-Dienstleistern zusammen. Sollte dies bei Ihnen der Fall sein, können Sie diese mit der Erzeugung der WMS beauftragen.
Alternativ kann ein Planungsbüro mit der Erstellung des WMS beauftragt werden.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Informationen für Verfahrensträger']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Was steckt hinter dem Bereich \\"Originalstellungnahmen\\“, der von der Abwägungstabelle aus zugänglich ist?",
                                        "In der Abwägungstabelle können Stellungnahmen verändert (oder sogar gelöscht) werden. So können Sie z. B. Ansprachefloskeln aus den Stellungnahmetexten entfernen, um die Abwägungstabelle übersichtlich zu halten. In den Originalstellungnahmen wird jederzeit eine vollständige, unveränderbare Liste aller eingegangenen Stellungnahmen vorgehalten. Aus dieser Liste können Sie die Originale duplizieren und in die Abwägungstabelle kopieren. Die Originalstellungnahmen können nicht gelöscht werden.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Informationen für Verfahrensträger']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Wie und an wen wird die Information über das Abwägungsergebnis einer Stellungnahme versendet?",
                                        "Die Information über das Abwägungsergebnis ein oder mehrerer Stellungnahmen wird durch den Verfahrensträger versendet. In DiPlanBeteiligung erfolgt das für alle Einreichenden, die eine E-Mail-Adresse angegeben haben aus der Anwendung heraus. Für TöB geht die Mitteilung immer sowohl an die einreichende Koordination, als auch an die in den „Daten der Organisation“ hinterlegte E-Mail-Adresse. Bei TöB erhält der oder die Verfasser*in der Stellungnahme die Mitteilung nicht. Es sei denn  die Person ist gleichzeitig einreichende Koordination.
Zudem kann die Abwägungstabelle mit den Abwägungsergebnissen in DiPlanBeteiligung zum Download eingestellt werden.
Die Abwägungstabelle ist ein öffentliches Papier und kann auch in Gänze allen Trägern per Mail verschickt werden.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Informationen für Verfahrensträger']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Was ist das Verfahrensprotokoll?",
                                        "Das Verfahrensprotokoll speichert einige charakteristische Änderungen und Schritte in einem Beteiligungsverfahren und stellt sie dem Verfahrensträger übersichtlich dar.
Momentan werden abgebildet: Namensänderungen, Phasenänderungen und eingegangene Stellungnahmen sowie deren Auslöser (d.h. Nutzer*in) und Zeitpunkte.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Informationen für Verfahrensträger']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Wie wird über DiPlanBeteiligung die rechtssichere Dokumentation der Online-Beteiligung in der Verfahrensakte zu einem Beteilungsverfahren gewährleistet?",
                                        "Die Verfahren können inklusive aller eingestellten Dokumente, der Abwägungstabelle, dem Verfahrensprotokoll und weiteren Verfahrensinhalten exportiert werden. Dadurch kann der Verfahrensträger selbst die gewünschte Form der Dokumentation und Aufbewahrung sicherstellen.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Informationen für Verfahrensträger']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Wie funktionieren Doppel-Rollen?",
                                        "Verfahrensträger können nicht nur als Fachplaner*innen auftreten, sondern auch als TöB. Die Rollenkombinationen sind dabei beliebig möglich, solange jeweils nur eine Funktion der jeweiligen Seite zugewiesen wird, z. B. Fachplanung-Administration und Institutions-Sachbearbeitung.
Achtung: Eine Doppelrolle Institutions-Koordination und Institutions-Sachbearbeitung gibt es nicht. Die Kombination reduziert die verfügbaren Funktionen für Nutzende. Die Rolle Institutions-Koordination verfügt zudem über alle Rechte der Institutions-Sachbearbeitung.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Informationen für Verfahrensträger']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Kann ich als Planungsbüro für verschiedene Verfahrensträger tätig werden?",
                                        "Ja. Sobald Sie die einmalige Registrierung durchgeführt haben, können Ihnen von allen in DiPlanBeteiligung aktiven Verfahrensträgern Verfahren zugeteilt werden.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Informationen für Planungsbüros']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Welche Aufgaben kann ein Planungsbüro in einem Beteiligungsverfahren übernehmen?",
                                        "Abgesehen von der Einrichtung von Verfahren, kann das Planungsbüro alle (oder Teile) der oben beschriebene Schritte übernehmen:
-Online-Bereitstellung der Planunterlagen zur Einsicht für alle eingeladenen Beteiligten (Behörden/ sonstige Träger öffentlicher Belange)
-Versand der Einladungs-E-Mail an die ausgewählten Behörden/Träger öffentlicher Belange
-automatischer Eingang der abgegebenen Stellungnahmen
-Online-Weiterbearbeitung der Tabelle: Ergänzung der Abwägungsempfehlung
-Ergänzungsmöglichkeiten für Stellungnahmen, die im klassischen Verfahren eingegangen sind
-Möglichkeit zur Erstellung der Abwägungstabelle im docx und pdf Format
-Lediglich das Anlegen eines Verfahrens muss durch den Verfahrensträger erfolgen.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Informationen für Planungsbüros']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Welche Rollen gibt es?",
                                        "TöB-Koordinator
-pflegt die „Daten der Organisation“
-schreibt Stellungnahmen
-koordiniert Stellungnahmen der TöB-Sachbearbeiter
-reicht Stellungnahmen an den Verfahrensträger ein
TöB-Sachbearbeiter
-schreibt Stellungnahmen
-gibt Stellungnahmen an den TöB-Koordinator frei",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Informationen für Träger öffentlicher Belange']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Was muss ich beachten, wenn es in meiner Organisation keine TöB-Sachbearbeitung gibt?",
                                        "Wenn Sie in Ihrer Organisation die einzige Person sind, die Stellungnahmen zu einem Verfahren verfasst und an den Verfahrensträger einreicht, können Sie den verkürzten Einreichungsprozess nutzen: Unter \\"Meine Daten\\" können Sie einen Haken setzen (siehe Stellungnahmeabgabeprozess -> Auswahl \\"Verkürzter Stellungnahmeprozess\\" statt
\\"Standard-Stellungnahmeprozess\\"), dadurch fällt der Schritt \\"An Institutions-Koordination freigeben\\" weg und Sie können Ihre Stellungnahmeentwürfe direkt einreichen.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Informationen für Träger öffentlicher Belange']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Wie dokumentiere ich als TöB die Beteiligung?",
                                        "Alle Planungsdokumente (Planzeichnung, Begründung, Verordnung, ggf. weitere Dokumente, wie z. B. Untersuchung) werden durch den Verfahrensträger im PDF-Format in DiPlanBeteiligung bereitgestellt und können von den beteiligten TöB heruntergeladen und lokal abgespeichert werden. Es wird empfohlen pro Beteiligung lokal einen Datei-Ordner anzulegen und alle PDF-Dokumente dort abzuspeichern.
Die verfassten Stellungnahmen können als Liste in PDF-Form in DiPlanBeteiligung erzeugt werden und lokal abgespeichert werden. Das System bietet auch den Export des Gesamtverfahrens in einem Zip-Ordner an.
Hinweis: DiPlanBeteiligung dient lediglich zur Durchführung der Beteiligung, nicht der Dokumentation und der Archivierung.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Informationen für Träger öffentlicher Belange']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Ihr Handbuch für die Bedienung von DiPlanBeteiligung",
                                        "Für viele Fragen zur Bedienung von DiPlanBeteiligung, hilft ein Blick in das folgende Handbuch, das wir für Sie zum Download bereitstellen Datei (Handbuch für Institutionen als PDF)",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Informationen für Träger öffentlicher Belange']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Welche Vorteile habe ich, wenn ich mich als Bürger registriere?",
                                        "Anders als unregistierte Bürger*innen, können Sie mit der Registrierung als Bürger*in Ihre Stellungnahme als Entwurf speichern und zu einem späteren Zeitpunkt fortführen. Nach Einreichen Ihrer Stellungnahme(n) können Sie sich jederzeit einen einfachen Überblick über Ihre bisher eingereichten Stellungnahmen verschaffen.
Grundsätzlich ist die Beteiligung als Bürger aber auch ohne Registrierung möglich.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Registrierung / Login']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Wie registriere ich mich als Bürger*in?",
                                        "TBA. Ggf. Anleitung zur Registrierung als PDF bereitstellen",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Registrierung / Login']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Wie registriere ich mich als TöB?",
                                        "TBA. Ggf. Anleitung zur Registrierung als PDF bereitstellen",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Registrierung / Login']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Wie ändere ich meine Daten?",
                                        "Über den IDP",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Registrierung / Login']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Was unternehme ich, wenn ich meinen Zugang vergessen habe?",
                                        "TBA",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Registrierung / Login']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Wie lade ich die Planungsdokumente herunter?",
                                        "Auf der Übersichtsseite der Planungsdokumente sind diverse Unterlagen wie Textteil, Karte und Umweltbericht abgelegt.
Zu jedem Dokument finden Sie eine kurze Beschreibung, einen Link zum Öffnen als PDF sowie ggf. einen Button zum Öffnen eines absatzbezogenen Dokuments im Browser, der insbesondere zur Abgabe der Stellungnahme pro Kapitel dient, oder einen Button zur Abgabe einer Gesamtstellungnahme zum jeweiligen Dokument.
Den Link zum PDF-Dokument erkennen Sie an dem Herunterladen-Button und an dem farblich markierten Text. Wenn Sie mit der linken Maustaste auf diesen Textbereich klicken, öffnet sich das Dokument in einem zusätzlich Browserfenster.
Sollte es Probleme bei der Anzeige geben, können Sie das Dokument direkt auf Ihrem PC speichern. Klicken Sie dazu auf \\"Herunterladen\\" und speichern Sie auf diesem Wege die benötigten Dokumente. Diese können Sie anschließend direkt vom gewählten Speicherort auf Ihrem PC öffnen.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Technische Fragen']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Was kann ich tun, wenn die Grundkarte nicht angezeigt wird?",
                                        "Zum Anzeigen der Grundkarte ist es notwendig, dass Sie die Verwendung von Cookies zulassen.Nach erfolgreicher Aktivierung der Cookies und „Aktualisieren“ (F5) der Webseite sollte die Grundkarte bei Ihnen angezeigt werden.
Am Beispiel des von Mozilla Firefox und Google Chrome erklären wir Ihnen, wie Sie dies tun:
Cookies in Firefox aktivieren
Schritt 1: Öffnen Sie das Firefox-Menü, indem Sie auf die drei horizontalen Linien rechts oben klicken.
Über die drei horizontalen Linien gelangt man in das Einstellungsmenü von Firefox.
Schritt 2: Wählen Sie den Eintrag „Einstellungen“, der zusätzlich durch das Zahnrad-Symbol gekennzeichnet ist. Sie gelangen zunächst ins Menü für die allgemeine Konfiguration von Firefox. Klickt man auf das Zahnrad-Symbol, gelangt man zu den Einstellungen.
Schritt 3: Es öffnet sich ein weiterer Tab mit den Einstellungen. Klicken Sie auf „Datenschutz & Sicherheit“.
Schritt 4: Unter „Chronik“ können Sie anschließend alle Cookies aktivieren, indem Sie die Option „Firefox wird eine Chronik anlegen“ wählen. Sollten Sie sich alternativ für eine Chronik nach benutzerdefinierten Einstellungen entscheiden, müssen Sie ein Häkchen bei „Cookies von Websites akzeptieren“ setzen. Wählt man die Option „Firefox wird eine Chronik anlegen“, lässt der Firefox-Browser automatisch sämtliche Cookies zu.

Cookies im Chrome-Browser aktivieren
Bei Chrome funktioniert das Aktivieren der Cookies recht ähnlich wie bei Firefox, nur die Menübezeichnung sieht etwas anders aus.
Schritt 1: Öffnen Sie die Einstellungen Ihres Webbrowsers über das Drei-Punkte-Symbol und den Menüpunkt „Einstellungen“.
Schritt 2: Scrollen Sie hinunter, um zu den erweiterten Einstellungen („Erweitert“) zu gelangen.
Schritt 3: Unter dem Punkt „Sicherheit und Datenschutz“ klicken Sie auf den Eintrag „Inhaltseinstellungen“.
Schritt 4: Wählen Sie „Cookies“ aus und verschieben den Regler bei „Websites dürfen Cookiedaten speichern und lesen“ nach rechts. Wenn Sie zusätzlich die Option „Lokale Daten nach Schließen des Browsers löschen“ aktivieren, werden alle Cookies nur so lange gespeichert, wie der Browser läuft.

Haben Sie Websites grundsätzlich erlaubt, Cookiedaten zu speichern und zu lesen, können Sie dennoch die Blockierung von Drittanbieter-Cookies aktivieren.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Technische Fragen']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Benötigt DiPlanBeteiligung eine bestimmte Infrastruktur?",
                                        "Nein. Nutzer benötigen lediglich einen Internetzugang.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Technische Voraussetzungen']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Welche Browser kann ich bei der Anwendung von DiPlanBeteiligung einsetzen?",
                                        "Folgende Internet-Browser werden für die Nutzung von DiPlanBeteiligung unterstützt:
- Microsoft Edge in der aktuellsten Version, sowie den beiden vorangegangenen Major Versionen
- Firefox in der aktuellsten Version, sowie der vorangegangenen Major Version
- alle auf Chromium basierenden Browser wie z.Bsp. Google Chrome
Bei der Nutzung anderer als den oben genannten Browsern stehen Ihnen ggf. nicht alle Funktionen von DiPlanBeteiligung zur Verfügung.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Technische Voraussetzungen']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Kann ich DiPlanBeteiligung nutzen, wenn kein GIS-System zur Verfügung steht?",
                                        "Ja. Um in einem Verfahren eine Karte darzustellen, wird ein WMS (Web Map Service) benötigt, der in DiPlanBeteiligung eingepflegt wird. Um diesen WMS zu erhalten, kann ein GIS-System verwendet werden.
Steht Ihnen kein GIS-System zur Verfügung, können sie beispielsweise ein Planungsbüro mit der Erstellung des WMS beauftragen.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Technische Voraussetzungen']]);
        $this->addSql('INSERT INTO platform_faq(id, faq_category_id, title, text, enabled, create_date, modify_date) VALUES (
                                        Uuid(),
                                        ?,
                                        "Kann das regionale GIS-System eingesetzt werden?",
                                        "Der in DiPlanBeteiligung integrierte Kartenclient stellt Ihre Karten mittels OGC konformen WebMapServices (WMS) dar. Sofern der Verfahrensträger Zugriff auf einen Geodatenserver besitzt, kann der WMS hierüber erzeugt werden.",
                                        1,
                                        Now(),
                                        Now()
                            )
'[$categories['Technische Voraussetzungen']]);
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();


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
