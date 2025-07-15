<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Entity\MailSend;
use demosplan\DemosPlanCoreBundle\Repository\MailRepository;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use Exception;
use Symfony\Component\Filesystem\Filesystem;

class BounceChecker extends CoreService
{
    public function __construct(private readonly GlobalConfigInterface $globalConfig, private readonly MailRepository $mailRepository, private readonly MailService $mailService)
    {
    }

    /**
     * Check if there are email bounces and react accordingly.
     *
     * This method can be used for overrides in project specific mail services
     *
     * @return int notificationEmailsSent
     */
    public function checkEmailBounces()
    {
        $config = $this->globalConfig;
        if (!$config->isEmailDataportBounceSystem()) {
            return 0;
        }

        $notificationEmailsSent = 0;
        // gibt es neue Bounces? Falls ja, ist das Bouncefile vorhanden
        $bounceFile = $config->getEmailBouncefilePath().'/'.$config->getEmailBouncefileFile();
        if (!is_file($bounceFile)) {
            // Keine neuen Bounces
            $this->logger->debug('Kein neues Bouncemailfile gefunden. Path '.DemosPlanTools::varExport($bounceFile, true));

            return $notificationEmailsSent;
        }
        $this->logger->info('Neues Bouncemailfile gefunden. Path '.DemosPlanTools::varExport($bounceFile, true));

        // uses local file, no need for flysystem
        $content = file_get_contents($bounceFile);
        $this->logger->debug(
            'Bouncefilecontent '.print_r($content, true)
        ); // print_r, damit die Formatierungen erhalten bleiben
        $bounces = preg_split('/From (\S*)\s*(.{24})(.*)/', $content, -1, PREG_SPLIT_NO_EMPTY);
        $this->logger->debug('Einzelne Bounces:  '.print_r($bounces, true));
        foreach ($bounces as $bounce) {
            $this->logger->info('Gebouncte Mail. Path '.DemosPlanTools::varExport($bounce, true));
            // Wenn die Mail nur an einen Empfänger geht, muss nicht der Body kompliziert geparst werden

            // MailSend EntityId
            preg_match(
                '/Return-Path: <'.$config->getEmailBouncePrefix().'\-(\\d+)@'.$config->getEmailBounceDomain().'>/',
                (string) $bounce,
                $mailEntityId
            );
            // wenn keine MailSend EntityId gefunden werden kann, gib gleich auf
            if (!isset($mailEntityId[1])) {
                $this->logger->error('Could not find MailSend EntityId '.DemosPlanTools::varExport($bounce, true));
                continue;
            }
            $mailEntity = $this->mailRepository->get($mailEntityId[1]);

            // wenn keine MailSend Entity gefunden werden kann, gib gleich auf
            if (null === $mailEntity) {
                $this->logger->error(
                    'Could not find MailSend Entity for EntityId '.DemosPlanTools::varExport($mailEntityId[1], true)
                );
                continue;
            }
            $mailTo = 0 < strlen($mailEntity->getTo()) ? explode(',', $mailEntity->getTo()) : [];
            $mailCc = 0 < strlen($mailEntity->getCc()) ? explode(',', $mailEntity->getCc()) : [];
            $mailBcc = 0 < strlen($mailEntity->getBcc()) ? explode(',', $mailEntity->getBcc()) : [];

            // Gibt es nur einen Empänger? Dann wissen wir, welche Email gebounced ist
            if (1 === (count($mailTo) + count($mailCc) + count($mailBcc))) {
                $this->logger->info(
                    'Only one recipient found for bounced mail '.DemosPlanTools::varExport($mailEntityId[1], true)
                );
                $this->sendBounceNotification($mailEntity->getFrom(), $mailEntity->getTo(), $mailEntity->getTitle());
                ++$notificationEmailsSent;
                continue;
            }

            // Wenn die Mail an mehr als eine Person versendet wurde, versuche die Mail aus dem Body zu bekommen
            preg_match('/boundary=\"(.*)\"/', (string) $bounce, $boundary);
            // wenn keine Boundary gefunden werden kann, kann die Nachricht nicht gelesen werden
            if (!isset($boundary[1])) {
                $this->logger->error('Could not find Bouncemail Boundary '.DemosPlanTools::varExport($bounce, true));
                continue;
            }
            // ungewöhnlicher Delimiter, weil / in den Boundaries vorkommen kann
            $mailParts = preg_split('$--'.preg_quote($boundary[1]).'\s$', (string) $bounce, -1, PREG_SPLIT_NO_EMPTY);
            // wenn keine Mailteile zu der Boundary gefunden werden können, kann die gebouncte Mail nicht erkannt werden
            if (!isset($mailParts[1])) {
                $this->logger->error(
                    'Could not find Content for Boundary '.DemosPlanTools::varExport($boundary[1], true).' in Bouncemail '.var_export(
                        $bounce,
                        true
                    )
                );
                continue;
            }
            // existierendes Emailpattern aus dem DSL übernommen, um identische Funktionalität zu gewährleisten
            // ebenso die Logik, dass die erste gefundene Email die gebouncte Mail ist
            preg_match('/[-0-9a-zA-Z.+_]+@[-0-9a-zA-Z.+_]+\.[a-zA-Z]{2,4}/', $mailParts[1], $contentEmails);
            // wenn keine Mail in dem Abschnitt gefunden wurde, konnte die gebouncte Mail nicht erkannt werden
            if (!isset($contentEmails[0])) {
                $this->logger->error(
                    'Could not find Email in boundary content '.DemosPlanTools::varExport(
                        $mailParts[1],
                        true
                    ).' in Bouncemail '.DemosPlanTools::varExport($bounce, true)
                );
                continue;
            }
            // die schuldige Email konnte ermittelt werden
            $this->logger->info(
                'Found bounced Email '.DemosPlanTools::varExport($contentEmails[0], true).' Sending NotificationEmail'
            );
            $this->sendBounceNotification($mailEntity->getFrom(), $contentEmails[0], $mailEntity->getTitle());
            ++$notificationEmailsSent;
        }

        // Delete Bouncefile after processing
        // local file only, no need for flysystem
        $fs = new Filesystem();
        try {
            $fs->remove($bounceFile);
        } catch (Exception $e) {
            $this->logger->error('Could not delete Bouncemailfile ', [$e]);
        }

        return $notificationEmailsSent;
    }

    /**
     * Verschicke die Benachrichtigungsemail, dass es einen Bounce gab.
     *
     * @param string $to
     * @param string $bouncedAddress
     * @param string $subjectBouncedMail
     */
    protected function sendBounceNotification($to, $bouncedAddress, $subjectBouncedMail)
    {
        $vars = [];
        try {
            $from = $this->globalConfig->getEmailSystem();
            $vars['mailsubject'] = 'Email konnte nicht zugestellt werden';
            $vars['mailbody'] = "Guten Tag
Der Versand einer E-Mail ist gescheitert.\r\nDabei ist folgende ungültige E-Mail-Adresse identifiziert worden:\r\n
E-Mail: $bouncedAddress
Betreff: $subjectBouncedMail


Hinweis: Diese E-Mail wurde automatisch erstellt.
";

            // Verschicke die Benachrichtigungsemail
            $this->mailService->sendMail(
                'dm_toebeinladung',
                'de_DE',
                $to,
                $from,
                '',
                '',
                'extern',
                $vars
            );
        } catch (Exception $e) {
            $this->logger->error('Fehler beim Versand der Bouncebenachrichtigung: ', [$e]);
        }
    }
}
