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

class ProcedureMetricsEmailEventSubscriber extends BaseEventSubscriber
{
    private const USED_TEMPLATE = 'dm_schlussmitteilung';
    private const RECEIVER_MAIL_ADDRESS = 'support@demos-deutschland.de';
    private const LOKALE = 'de_DE';
    private const MAIL_SCOPE = 'extern';

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
        $procedure = $event->getProcedure();

        $from = $this->globalConfig->getEmailSystem();
        $this->mailVars['mailsubject'] = $this->getSubject($procedure);
        $this->mailVars['mailbody'] = $this->getBody($procedure);
    }

    /**
     * @throws CustomerNotFoundException
     */
    private function getSubject(ProcedureInterface $procedure): string
    {
        $customerName = $this->customerService->getCurrentCustomer()->getName();
        $orgaName = $this->currentUser->getUser()->getOrga()?->getName();

        return "Die Organisation: \"$orgaName\" hat für den Mandanten: \"$customerName\" ein neues Verfahren angelegt.";
    }

    /**
     * @throws CustomerNotFoundException
     * @throws OrgaNotFoundException
     */
    private function getBody(ProcedureInterface $procedure): string
    {
        $customer = $this->customerService->getCurrentCustomer();
        $orga = $this->currentUser->getUser()->getOrga();
        if (false === $orga instanceof OrgaInterface) {
            throw new OrgaNotFoundException('No Orga found for current user');
        }
        $procedureName = $procedure->getName();

        $allProceduresOfOrgaInCustomer = $this->procedureRepository->getAllProceduresOfOrgaInCustomer($orga, $customer);

        return sprintf(
            'Die Organisation: "%s" hat für den Mandanten: "%s" ein neues Verfahren Namens: %s angelegt.',
            $orga->getName(),
            $customer->getName(),
            $procedure->getName()
        );
    }
}
