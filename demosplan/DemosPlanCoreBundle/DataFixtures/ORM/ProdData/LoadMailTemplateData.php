<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\ProdData;

use demosplan\DemosPlanCoreBundle\Entity\MailTemplate;
use Doctrine\Persistence\ObjectManager;

class LoadMailTemplateData extends ProdFixture
{
    public function load(ObjectManager $manager): void
    {
        // array was exported with HeidiSQL and modified afterwards
        $mailTemplates = [
            [ // row #0
                'label'    => 'register',
                'language' => 'de_DE',
                'title'    => 'BOB-SH Registrierung',
                'content'  => "Hallo \${firstname} \${lastname},\r\n\r\num Ihre Registrierung abzuschließen, klicken Sie bitte auf den nachfolgenden Link.\r\nHiermit bestätigen Sie die angegebene E-Mail Adresse.\r\n\r\n\${link}\r\n\r\nMit freundlichen Grüßen\r\nIhr BOB-SH Team",
            ],
            [ // row #1
                'label'    => 'password_recover',
                'language' => 'de_DE',
                'title'    => 'BOB-SH Zugangsdaten',
                'content'  => "Hallo,\r\nmit einem Klick auf den folgenden Link können Sie Ihr Passwort ändern.\r\n\r\n\${link}\r\n\r\nBitte beachten Sie, dass der Link nur einmalig verwendet werden kann.\r\n\r\nMit freundlichen Grüßen\r\nIhr BOB-SH Team\r\n",
            ],
            [ // row #2
                'label'    => 'event',
                'language' => 'de_DE',
                'title'    => 'BOB-SH Veranstaltung',
                'content'  => "Hallo,\r\n\r\nSie möchten sich zu folgender Veranstaltung anmelden:\r\n \r\nTitel: \${event_title}\r\nOrt: \${event_place}\r\n\r\nBeginn: \${event_start}\r\nEnde:  \${event_end}\r\n\r\nBitte bestätigen Sie Ihre Teilnahme mit einem Klick auf nachfolgendem Link:\r\n\${link}\r\n\r\nMit freundlichen Grüßen\r\nIhr BOB-SH Team\r\n",
            ],
            [ // row #3
                'label'    => 'recommendation',
                'language' => 'de_DE',
                'title'    => 'Empfehlung',
                'content'  => "Hallo,\r\n\${firstname} \${lastname} möchte Sie auf die Online-Beteiligung in der Bauleitplanung aufmerksam machen und sendet Ihnen den nachfolgenden Link zur offiziellen Webpräsenz.\r\n\r\n\${link}\r\n\r\n\"Bauleitplanung Online-Beteiligung für Schleswig-Holstein\" (BOB-SH) ermöglicht mit der Lösung DEMOS-Plan für alle Gemeinden in Schleswig-Holstein, den Beteiligungsprozess in der Bauleitplanung für alle Mitwirkenden einfacher und effizienter zu gestalten.\r\n\r\n\${message}\r\n\r\nMit freundlichen Grüßen\r\nIhr BOB-SH Team",
            ],
            [ // row #4
                'label'    => 'error_occurred',
                'language' => 'de_DE',
                'title'    => 'Fehlermeldung',
                'content'  => "Hallo,\r\nfolgender Fehler ist eingetreten:\r\n\r\n\${errortext} \r\n\r\nMit freundlichen Grüßen\r\nIhr BOB-SH Team",
            ],
            [ // row #5
                'label'    => 'dm_schlussmitteilung',
                'language' => 'de_DE',
                'title'    => '${mailsubject}',
                'content'  => '${mailbody}',
            ],
            [ // row #6
                'label'    => 'dm_toebeinladung',
                'language' => 'de_DE',
                'title'    => '${mailsubject}',
                'content'  => '${mailbody}',
            ],
            [ // row #7
                'label'    => 'dm_stellungnahme',
                'language' => 'de_DE',
                'title'    => '${mailsubject}',
                'content'  => '${mailbody}',
            ],
            [ // row #8
                'label'    => 'notify_newsletter_changes',
                'language' => 'de_DE',
                'title'    => 'An-/Abmeldung zum Newsletter',
                'content'  => "Hallo,\r\n\r\nder Nutzer \r\n\r\nAnrede: \${gender}\r\nName: \${firstname} \${lastname} \r\nOrganisation: \${organisation}\r\nE-Mail: \${email} \r\n\r\nmöchte den Newsletter \${action}. \r\n\r\nMit freundlichen Grüßen\r\nIhr BOB-SH Team",
            ],
            [ // row #9
                'label'    => 'platform_contact',
                'language' => 'de_DE',
                'title'    => '${subject} (Kontaktformular)',
                'content'  => "Absender: \r\n\t\${gender} \${firstname} \${lastname},\r\n \t\${organisation}, \${email},  \r\n\tTel.: \${phone}, \r\n\tAdresse: \${address},\r\n\r\nsendet Ihnen folgende Nachricht:\r\n\tBetreff: \${subject}\r\n\tNachricht: \${message}",
            ],
            [ // row #10
                'label'    => 'dm_subscription',
                'language' => 'de_DE',
                'title'    => '${mailsubject}',
                'content'  => '${mailbody}',
            ],
            [ // row #11
                'label'    => 'dm_forum_notification',
                'language' => 'de_DE',
                'title'    => '${mailsubject}',
                'content'  => '${mailbody}',
            ],
            [ // row #12
                'label'    => 'dm_county_notification',
                'language' => 'de_DE',
                'title'    => 'Beitrag von ${orga}',
                'content'  => '${mailbody}',
            ],
            [ // row #13
                'label'    => 'orga_registration_request_confirmation',
                'language' => 'de_DE',
                'title'    => 'Registrierung als ${orga_type} für das Bundesland ${customer} auf bauleitplanung-online.de',
                'content'  => "Sehr geehrte/r \${firstname} \${lastname},\r\n\r\nSie haben für Ihre Organisation, \${orga_name}, eine Freischaltung als \${orga_type} für das Bundesland \${customer} beantragt. Ihre Anfrage wird dort durch die zuständige Stelle geprüft.\r\n\r\nEine Freischaltung Ihrer Organisation sollte innerhalb der nächsten Tage erfolgen. Sollten Sie weitere Fragen haben, wenden Sie sich bitte an den Support (support@bauleitplanung-online.de).\r\n\r\nViele Grüße,\r\n\r\nIhr Team von bauleitplanung-online.de\r\n",
            ],
            [ // row #14
                'label'    => 'orga_registration_accepted',
                'language' => 'de_DE',
                'title'    => 'Freischaltung als ${orga_type} für das Bundesland ${customer} auf bauleitplanung-online.de',
                'content'  => "Sehr geehrte/r \${firstname} \${lastname},\r\n\r\nSie haben vor kurzer Zeit eine Freischaltung Ihrer Organisation, \${orga_name}, als \${orga_type} für das Bundesland \${customer} beantragt. Ihre Anfrage wurde durch die dort zuständige Stelle geprüft. Ihre Organisation ist nun freigeschaltet.\r\n\r\nIn einer separaten E-Mail haben Sie die Zugangsdaten für Ihren Nutzeraccount erhalten. Loggen Sie sich mit diesen Daten ein. Sie können nun weitere Nutzer*innen für Ihre Organisation anlegen.\r\n\r\nViele Grüße,\r\n\r\nIhr Team von bauleitplanung-online.de\r\n",
            ],
            [ // row #15
                'label'    => 'orga_registration_rejected',
                'language' => 'de_DE',
                'title'    => 'Abgelehnte Freischaltung als ${orga_type} für das Bundesland ${customer} auf bauleitplanung-online.de',
                'content'  => "Sehr geehrte/r \${firstname} \${lastname},\r\n\r\nSie haben vor kurzer Zeit eine Freischaltung Ihrer Organisation, \${orga_name}, als \${orga_type} für das Bundesland \${customer} beantragt. Ihre Anfrage wurde durch die dort zuständige Stelle geprüft und abgelehnt.\r\n\r\nSollten Sie vermuten, dass hier ein Fehler vorliegt, wenden Sie sich bitte an den Support (support@bauleitplanung-online.de).\r\n\r\nViele Grüße,\r\n\r\nIhr Team von bauleitplanung-online.de\r\n",
            ],
        ];

        foreach ($mailTemplates as $mailTemplateData) {
            $mailTemplate = new MailTemplate();
            $mailTemplate->setContent($mailTemplateData['content']);
            $mailTemplate->setTitle($mailTemplateData['title']);
            $mailTemplate->setLabel($mailTemplateData['label']);
            $mailTemplate->setLanguage($mailTemplateData['language']);

            $manager->persist($mailTemplate);
            $this->setReference('mailTemplate_'.$mailTemplate->getLabel(), $mailTemplate);
        }

        $manager->flush();
    }
}
