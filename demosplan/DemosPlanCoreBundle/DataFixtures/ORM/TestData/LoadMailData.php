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
use demosplan\DemosPlanCoreBundle\Entity\MailSend;
use demosplan\DemosPlanCoreBundle\Entity\MailTemplate;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadMailData extends TestFixture
{
    public function load(ObjectManager $manager): void
    {
        $mailTemplate = new MailTemplate();
        $mailTemplate->setLabel('test_template');
        $mailTemplate->setLanguage('de_DE');
        $mailTemplate->setTitle('Testtemplate Title ${mailtitle}');
        $mailTemplate->setContent('${mailbody}');

        $manager->persist($mailTemplate);

        $mailTemplate2 = new MailTemplate();
        $mailTemplate2->setLabel('dm_toebeinladung');
        $mailTemplate2->setLanguage('de_DE');
        $mailTemplate2->setTitle('${mailsubject}');
        $mailTemplate2->setContent('${mailbody}');

        $manager->persist($mailTemplate2);

        $mailTemplate3 = new MailTemplate();
        $mailTemplate3->setLabel('dm_subscription');
        $mailTemplate3->setLanguage('de_DE');
        $mailTemplate3->setTitle('${mailsubject}');
        $mailTemplate3->setContent('${mailbody}');

        $manager->persist($mailTemplate3);

        $mailTemplate4 = new MailTemplate();
        $mailTemplate4->setLabel('orga_registration_request_confirmation');
        $mailTemplate4->setLanguage('de_DE');
        $mailTemplate4->setTitle('Registrierung als ${orga_type} für das Bundesland ${customer} auf bauleitplanung-online.de');
        $mailTemplate4->setContent('Sehr geehrte/r \${firstname} \${lastname},\r\n\r\nSie haben für Ihre Organisation, \${orga_name}, eine Freischaltung als \${orga_type} für das Bundesland \${customer} beantragt. Ihre Anfrage wird dort durch die zuständige Stelle geprüft.\r\n\r\nEine Freischaltung Ihrer Organisation sollte innerhalb der nächsten Tage erfolgen. Sollten Sie weitere Fragen haben, wenden Sie sich bitte an den Support (support@bauleitplanung-online.de).\r\n\r\nViele Grüße,\r\n\r\nIhr Team von bauleitplanung-online.de\r\n');

        $manager->persist($mailTemplate4);

        $simpleMail = new MailSend();
        $simpleMail->setTo('sendto@simplemail.org');
        $simpleMail->setFrom('sentfrom@simplemail.org');
        $simpleMail->setTitle('Mailtitle Simple Mail');
        $simpleMail->setContent('Content of Simple Mail');
        $simpleMail->setTemplate($mailTemplate->getLabel());

        $manager->persist($simpleMail);

        $mailSend = new MailSend();
        $mailSend->setTo('sendto@mail.org');
        $mailSend->setFrom('sentfrom@mail.org');
        $mailSend->setCc('sendcc@mail.org, sendcc2@mail.org');
        $mailSend->setTitle('Mailtitle');
        $mailSend->setContent('Content of Mail');
        $mailSend->setScope('extern');
        $mailSend->setStatus('new');
        $mailSend->setTemplate($mailTemplate->getLabel());

        $manager->persist($mailSend);

        $sentMail = new MailSend();
        $sentMail->setTo('sendto@sentmail.org');
        $sentMail->setFrom('sentfrom@sentmail.org');
        $sentMail->setTitle('Mailtitle sent Mail');
        $sentMail->setContent('Content of sent Mail');
        $sentMail->setStatus('wait');
        $sentMail->setSendDate(new DateTime());
        $sentMail->setTemplate($mailTemplate->getLabel());

        $manager->persist($sentMail);

        $manager->flush();
        $this->setReference('testMailSend', $mailSend);
        $this->setReference('testMailTemplate', $mailTemplate);
    }
}
