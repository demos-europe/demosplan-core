<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Data;

use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Logic\Permission\AccessControlService;
use demosplan\DemosPlanCoreBundle\Repository\OrgaTypeRepository;
use demosplan\DemosPlanCoreBundle\Repository\RoleRepository;
use EFrane\ConsoleAdditions\Batch\Batch;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;


class GenerateTestInstanceCommand extends CoreCommand
{

    protected static $defaultName = 'dplan:data:generate-test-instance';
    protected static $defaultDescription = 'Creates a new instance filled with fake data for testing purposes.';


    public function __construct(
        private readonly AccessControlService $accessControlPermissionService,
        private readonly OrgaTypeRepository $orgaTypeRepository,
        ParameterBagInterface $parameterBag,
        private readonly RoleRepository $roleRepository,
        ?string $name = null
    ) {
        parent::__construct($parameterBag, $name);
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Batch::create($this->getApplication(), $output)
            ->addShell(['bin/console', 'dplan:container:init', '--override-database'])
            ->run();

        $customer = CustomerFactory::createOne([
            'name' => 'test',
            'subdomain' => 'test',
        ]);
        $orga = OrgaFactory::createOne([
            'name' => 'TestOrga',
        ]);
        $orgaTyoe = $this->orgaTypeRepository->findOneBy(['name' => OrgaTypeInterface::MUNICIPALITY]);
        $orga->addCustomerAndOrgaType($customer->_real(), $orgaTyoe);
        $users = UserFactory::createMany(10);

        $fpa = $this->roleRepository->findOneBy(
            ['code' => RoleInterface::PLANNING_AGENCY_ADMIN]
        );
        // any idea how to do this better?
        foreach($users as $user) {
            $user->setDplanroles([$fpa], $customer->_real());
            $user->setOrga($orga->_real());
            $orga->addUser($user->_real());
            $user->_save();
        }
        $orga->_save();

        // enable permission to create new procedures
        $this->accessControlPermissionService->enablePermissionCustomerOrgaRole(
            AccessControlService::CREATE_PROCEDURES_PERMISSION,
            $customer->_real(),
            $fpa
        );
        $procedure = ProcedureFactory::createOne([
            'orga' => $orga,
            'customer' => $customer,
        ]);
        $procedure->setAuthorizedUsers(collect($users)->map(fn($user) => $user->_real())->toArray());

        $procedure->_save();

        try {

            $output->writeln(
                "Testinstance with data was successfully created.",
                OutputInterface::VERBOSITY_NORMAL
            );

            return Command::SUCCESS;
        } catch (Exception $e) {
            // Print Exception
            $output->writeln(
                'Something went wrong during Testinstance creation: '.$e->getMessage(),
                OutputInterface::VERBOSITY_NORMAL
            );

            return Command::FAILURE;
        }
    }
}
