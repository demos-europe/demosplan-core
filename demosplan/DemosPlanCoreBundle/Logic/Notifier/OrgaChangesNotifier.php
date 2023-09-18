<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Notifier;

use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class OrgaChangesNotifier
{
    public function __construct(private readonly Environment $twig, private readonly MailService $mailService, private readonly OrgaService $orgaService, private readonly RouterInterface $router, private readonly TranslatorInterface $translator, private readonly UserService $userService)
    {
    }

    public function notifyNewOrgaAdminOfRegistration(
        string $userEmail,
        array $orgaTypeNames,
        string $customerName,
        string $userFirstName,
        string $userLastName,
        string $orgaName
    ): void {
        $orgaTypeLabels = $this->orgaService->transformOrgaTypeNamesToLabels($orgaTypeNames);

        // Send email
        $this->mailService->sendMail(
            'orga_registration_request_confirmation',
            'de_DE',
            $userEmail,
            '',
            '',
            '',
            'extern',
            ['orga_type' => implode(', ', $orgaTypeLabels), 'customer' => $customerName, 'firstname' => $userFirstName, 'lastname' => $userLastName, 'orga_name' => $orgaName]
        );
    }

    public function notifyDeciderOfOrgaRegistration(string $orgaName): void
    {
        $vars = [];
        $url = $this->router->generate('DemosPlan_orga_list', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $newOrgaMailBody = $this->twig
            ->load('@DemosPlanCore/DemosPlanCore/email/notify_new_orga_checker.html.twig')
            ->renderBlock(
                'body_plain',
                [
                    'newOrgaName' => $orgaName,
                    'url'         => $url,
                ]
            );

        // send Emails to customer master users
        $customerMasterUser = $this->userService->getUsersOfRole(Role::CUSTOMER_MASTER_USER);
        $toAddresses = collect($customerMasterUser)->transform(static fn(User $user) => $user->getEmail())->unique();
        $vars['mailsubject'] = $this->translator->trans('email.subject.orga.new');
        $vars['mailbody'] = $newOrgaMailBody;

        foreach ($toAddresses as $to) {
            $this->mailService->sendMail(
                'dm_stellungnahme',
                'de_DE',
                $to,
                '',
                '',
                '',
                'extern',
                $vars
            );
        }
    }
}
