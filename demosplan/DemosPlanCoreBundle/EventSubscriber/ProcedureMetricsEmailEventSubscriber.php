<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\PostNewProcedureCreatedEventInterface;
use demosplan\DemosPlanCoreBundle\Event\Procedure\PostNewProcedureCreatedEvent;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\OrgaNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureRepository;
use Doctrine\Common\Collections\Criteria;
use Exception;

class ProcedureMetricsEmailEventSubscriber extends BaseEventSubscriber
{
    private const USED_TEMPLATE = 'dm_schlussmitteilung';
    private const RECEIVER_MAIL_ADDRESS = 'support@demos-deutschland.de';
    private const LOKALE = 'de_DE';
    private const MAIL_SCOPE = 'extern';
    private const DATE_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private readonly CustomerService $customerService,
        private readonly CurrentUserService $currentUser,
        private readonly MailService $mailService,
        private readonly GlobalConfigInterface $globalConfig,
        private readonly ProcedureRepository $procedureRepository,
        private array $mailVars = ['mailsubject' => '', 'mailbody' => ''],
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PostNewProcedureCreatedEventInterface::class => 'onProcedureCreatedAction',
        ];
    }

    public function onProcedureCreatedAction(PostNewProcedureCreatedEvent $event): void
    {
        if (!$this->currentUser->hasPermission('feature_send_procedure_metrics_support_mail')) {
            return;
        }
        $this->getLogger()->info(
            'Caught PostNewProcedureCreatedEvent: preparing to send an email containing procedure metrics'
        );
        $this->handleMailAction($event);
    }

    private function handleMailAction(PostNewProcedureCreatedEvent $event): void
    {
        $procedure = $event->getProcedure();

        $from = $this->globalConfig->getEmailSystem();
        try {
            $allProceduresOfOrgaInCustomer = $this->getAllProceduresOfOrgaInCustomer();
            $this->mailVars['mailsubject'] = $this->getSubject($allProceduresOfOrgaInCustomer);
            $this->mailVars['mailbody'] = $this->getBody($procedure, $allProceduresOfOrgaInCustomer);

            $this->getLogger()->info(
                sprintf(
                    'try to send mail with:\nSubject: %s\nBody: %s\n',
                    $this->mailVars['mailsubject'],
                    $this->mailVars['mailbody']
                )
            );
            $this->mailService->sendMail(
                self::USED_TEMPLATE,
                self::LOKALE,
                self::RECEIVER_MAIL_ADDRESS,
                $from,
                '',
                '',
                self::MAIL_SCOPE,
                $this->mailVars
            );
        } catch (CustomerNotFoundException $e) {
            $this->getLogger()->error(
                'Failed sending procedureMetrics mail. Not able to fetch the current customer.',
                [$e]
            );
        } catch (OrgaNotFoundException $e) {
            $this->getLogger()->error(
                'Failed sending procedureMetrics mail. Not able to fetch the current organisation.',
                [$e]
            );
        } catch (Exception $e) {
            $this->getLogger()->error(
                'Failed sending procedureMetrics mail.',
                [$e]
            );
        }
    }

    /**
     * @throws CustomerNotFoundException
     * @throws OrgaNotFoundException
     */
    private function getAllProceduresOfOrgaInCustomer(): array
    {
        $customer = $this->customerService->getCurrentCustomer();
        $orga = $this->currentUser->getUser()->getOrga();
        if (false === $orga instanceof OrgaInterface) {
            throw new OrgaNotFoundException('No Orga found for current user');
        }

        return $this->procedureRepository->findBy(
            ['orga' => $orga->getId(), 'customer' => $customer->getId(), 'deleted' => false],
            ['createdDate' => Criteria::DESC]
        );
    }

    private function getSubject(array $allProceduresOfOrgaInCustomer): string
    {
        $customerName = $this->customerService->getCurrentCustomer()->getName();
        $orgaName = $this->currentUser->getUser()->getOrga()?->getName();
        $amount = (string) count($allProceduresOfOrgaInCustomer);

        return sprintf(
            'Die Organisation %s hat soeben das %s. Verfahren im Mandanten %s angelegt:',
            $orgaName,
            $amount,
            $customerName
        );
    }

    private function getBody(ProcedureInterface $procedure, array $allProceduresOfOrgaInCustomer): string
    {
        $customer = $this->customerService->getCurrentCustomer();
        $orga = $this->currentUser->getUser()->getOrga();

        $body = sprintf(
            "Die Organisation: \"%s\" hat für den Mandanten: \"%s\" ein neues Verfahren Namens: \"%s\" angelegt.\r\n",
            $orga->getName(),
            $customer->getName(),
            $procedure->getName()
        );
        $body .= sprintf(
            "Die Laufzeit des Verfahrens: Start - %s    Schluss - %s\r\n",
            $procedure->getStartDate()->format(self::DATE_FORMAT),
            $procedure->getEndDate()->format(self::DATE_FORMAT)
        );
        $body .= sprintf(
            "Diese Organisation hat insgesamt %s Verfahren im Mandanten %s angelegt.\r\n\r\n",
            (string) count($allProceduresOfOrgaInCustomer),
            $customer->getName()
        );
        $body .= sprintf(
            "Liste aller Verfahren der Orga %s im Mandanten %s:\r\n\r\n",
            $orga->getName(),
            $customer->getName()
        );
        /** @var ProcedureInterface $orgaProcedure */
        foreach ($allProceduresOfOrgaInCustomer as $orgaProcedure) {
            $body .= sprintf(
                "Name: %s, Start: %s, Schluss: %s\r\n",
                $orgaProcedure->getName(),
                $orgaProcedure->getStartDate()->format(self::DATE_FORMAT),
                $orgaProcedure->getEndDate()->format(self::DATE_FORMAT)
            );
        }

        return $body;
    }
}
