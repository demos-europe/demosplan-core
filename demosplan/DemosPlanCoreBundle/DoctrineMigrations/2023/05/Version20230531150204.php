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
