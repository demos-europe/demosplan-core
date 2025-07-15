<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\User;

use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Illuminate\Support\Collection;

/**
 * dplan:users:list.
 *
 * List users with their roles
 */
class UserListCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:user:list';
    protected static $defaultDescription = 'List users with roles';

    public function __construct(ParameterBagInterface $parameterBag, private readonly UserService $userService, string $name = null)
    {
        parent::__construct($parameterBag, $name);
    }

    public function configure(): void
    {
        $this->addOption('html', null, InputOption::VALUE_NONE, 'Export data as html table');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $userList = $this->userService->getAllActiveUsers();

        $data = collect($userList)
            ->map(
                function (User $user) {
                    $roles = collect($user->getDplanroles())->map(
                        fn (Role $role) => $role->getName()
                    )->implode(',');

                    return [
                        'name'  => $user->getFullname(),
                        'orga'  => $user->getOrgaName(),
                        'login' => $user->getLogin(),
                        'roles' => $roles,
                    ];
                }
            )
            ->sortBy('orga')
            ->groupBy('orga')
            ->sortBy('roles')
            ->flatten(1);

        $headers = [
            'Name',
            'Organisation',
            'Login',
            'Rollen',
        ];

        if ($input->getOption('html')) {
            $this->outputDataAsHTMLTable($output, $headers, $data);
        } else {
            $this->outputDataAsTextTable($output, $headers, $data);
        }

        return Command::SUCCESS;
    }

    public function outputDataAsHTMLTable(OutputInterface $output, array $headers, Collection $data)
    {
        $headerHTML = "<tr>\n".collect($headers)->map(
            fn ($header) => "<th>{$header}</th>"
        )->implode("\n").'</tr>';

        $contentHTML = '';
        $data->each(
            function ($user) use (&$contentHTML) {
                $contentHTML .= "<tr>\n".collect($user)->map(
                    function ($field) {
                        $field = nl2br($field);

                        return "<td>{$field}</td>";
                    }
                )->implode("\n").'</tr>';
            }
        );

        $output->write("<table>\n$headerHTML\n$contentHTML</table>");
    }

    public function outputDataAsTextTable(OutputInterface $output, array $headers, Collection $data)
    {
        $table = new Table($output);
        $table->setHeaders($headers);

        $data->each(
            function (array $user) use ($table) {
                $table->addRow($user);
                $table->addRow(['', '', '']);
            }
        );

        $table->render();
    }
}
