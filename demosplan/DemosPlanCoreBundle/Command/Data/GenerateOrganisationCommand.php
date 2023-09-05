<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Data;

use demosplan\DemosPlanCoreBundle\Entity\Slug;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\MasterToeb;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerHandler;
use demosplan\DemosPlanCoreBundle\Repository\OrgaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Exception;
use Faker\Factory;
use Faker\Generator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class GenerateOrganisationCommand extends DataProviderCommand
{
    protected static $defaultName = 'dplan:data:generate:organisation';
    protected static $defaultDescription = 'Generate a (number of) Organisation(s)';
    /**
     * @var ManagerRegistry
     */
    protected $registry;
    /**
     * @var Customer
     */
    protected $customer;
    /**
     * @var Generator
     */
    protected $faker;
    /**
     * @var OrgaType|null
     */
    protected $orgaType;
    /**
     * @var ObjectManager
     */
    protected $em;

    protected function configure(): void
    {
        $this->addOption(
            'type',
            't',
            InputOption::VALUE_OPTIONAL,
            'Type of organisation. One of toeb, planningagency, planner',
            'toeb'
        );

        $this->addOption(
            'with-mastertoeb',
            'm',
            InputOption::VALUE_NONE,
            'Should MasterToeb Entries be generated for each Orga?'
        );

        $this->addArgument(
            'amount',
            InputArgument::OPTIONAL,
            'The amount of organisations to be generated.',
            1
        );
    }

    /**
     * @throws CustomerNotFoundException
     */
    public function __construct(
        ManagerRegistry $registry,
        private readonly CustomerHandler $customerHandler,
        ParameterBagInterface $parameterBag,
        string $name = null
    ) {
        parent::__construct($parameterBag, $name);
        $this->em = $registry->getManager();
        $this->faker = Factory::create('de_DE');
    }

    /**
     * @throws NonUniqueResultException
     */
    protected function handle(): int
    {
        $withMastertoeb = $this->getOption('with-mastertoeb');

        $orgaType = $this->getOption('type');
        $orgaTypeMap = [
            'toeb'           => OrgaType::PUBLIC_AGENCY,
            'planningagency' => OrgaType::PLANNING_AGENCY,
            'planner'        => OrgaType::MUNICIPALITY,
        ];
        /** @var OrgaRepository $orgaRepos */
        $orgaRepos = $this->em->getRepository(Orga::class);
        $this->orgaType = $orgaRepos->getOrgaTypeByName($orgaTypeMap[$orgaType]);

        $amount = $this->getArgument('amount');

        try {
            $progressBar = $this->createGeneratorProgressBar($amount);
            $progressBar->setMessage('Generating orgas...');

            for ($i = 0; $i < $amount; ++$i) {
                $this->createOrga($withMastertoeb);
                $progressBar->advance();
            }
            $this->em->flush();
            $progressBar->finish();
        } catch (Exception $e) {
            $this->error($e);

            return 2;
        }

        return 0;
    }

    /**
     * @param bool $withMastertoeb
     */
    private function createOrga($withMastertoeb = false)
    {
        $department = new Department();
        $department->setName(Department::DEFAULT_DEPARTMENT_NAME);
        $this->em->persist($department);
        $faker = $this->faker;

        $name = $faker->company;
        $slug = new Slug($faker->uuid);

        $orga = new Orga();
        $orga->setName($name)
            ->setShowlist(true)
            ->setEmail2($faker->email)
            ->addDepartment($department);
        $orga->setSlugs(new ArrayCollection([$slug]));
        $orga->setCurrentSlug($slug);
        $orgaStatusInCustomer = new OrgaStatusInCustomer();
        $orgaStatusInCustomer->setOrga($orga);
        $orgaStatusInCustomer->setCustomer($this->customerHandler->getCurrentCustomer());
        $orgaStatusInCustomer->setOrgaType($this->orgaType);
        $orgaStatusInCustomer->setStatus(OrgaStatusInCustomer::STATUS_ACCEPTED);

        $orga->addStatusInCustomer($orgaStatusInCustomer);

        if ($withMastertoeb) {
            $masterToeb = new MasterToeb();
            $masterToeb->setOrgaName($orga->getName())
                ->setDepartmentName($department->getName())
                ->setOrga($orga)
                ->setDepartment($department)
                ->setDistrictAltona($this->faker->numberBetween(0, 2))
                ->setDistrictBergedorf($this->faker->numberBetween(0, 2))
                ->setDistrictEimsbuettel($this->faker->numberBetween(0, 2))
                ->setDistrictHarburg($this->faker->numberBetween(0, 2))
                ->setDistrictHHMitte($this->faker->numberBetween(0, 2))
                ->setDistrictHHNord($this->faker->numberBetween(0, 2))
                ->setDistrictWandsbek($this->faker->numberBetween(0, 2))
                ->setDocumentAgreement($this->faker->numberBetween(0, 1))
                ->setDocumentRoughAgreement($this->faker->numberBetween(0, 1))
                ->setDocumentNotice($this->faker->numberBetween(0, 1))
                ->setDocumentAssessment($this->faker->numberBetween(0, 1))
                ->setDistrictBsu($this->faker->numberBetween(0, 2));
            $this->em->persist($masterToeb);
        }

        $this->em->persist($orga);
    }
}
