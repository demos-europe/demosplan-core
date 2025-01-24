<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\CustomFactory;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\DataProviderException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidUserDataException;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\DraftStatementService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use Illuminate\Support\Collection;

class StatementFactory extends FactoryBase
{
    final public const RANDOM_ORGANISATION = '__random__570cdd6f65e5f';
    final public const PUBLIC_USERS_ONLY = 'PUBLIC';

    /**
     * @var User
     */
    protected $user;
    /**
     * @var Orga|string
     */
    protected $orga = '';
    /**
     * @var Procedure
     */
    protected $procedure = '';

    /**
     * Maximum amount of characters of a statement.
     *
     * @var int
     */
    protected $maxChars = 1400;
    /**
     * @var ProcedureService|object|null
     */
    protected $procedureService;
    /**
     * @var OrgaService|object|null
     */
    protected $orgaService;

    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly DraftStatementService $draftService,
        ManagerRegistry $registry,
        OrgaService $orgaService,
        PermissionsInterface $permissions,
        ProcedureService $procedureService,
        private readonly UserService $userService,
    ) {
        $this->orgaService = $orgaService;
        $this->procedureService = $procedureService;

        parent::__construct($registry, $permissions);
    }

    /**
     * Generate Statements.
     *
     * @param int $batchSize number of inserts before the entity manager is cleared
     *
     * @return Collection Will return collection of generated statement ids
     */
    public function make(int $amount = 1, int $batchSize = 10): Collection
    {
        return collect(range(1, $amount))->map(
            function ($offset) use ($batchSize) {
                $statement = $this->makeStatement();

                $this->clearEntityManager($offset, $batchSize);

                call_user_func($this->getProgressCallback(), $offset, 'not yet implemented');

                return $statement->getId();
            }
        );
    }

    /**
     * @return mixed
     *
     * @throws DataProviderException
     */
    protected function makeStatement(): Statement
    {
        $draftStatement = $this->makeDraftStatement();

        try {
            $submittedStatement = $this->draftService->submitDraftStatement(
                [$draftStatement['ident']],
                $this->user,
                null,
                true,
                false
            )[0];
        } catch (Exception $e) {
            throw new DataProviderException("Generating statement from draft {$draftStatement['id']} failed.", 0, $e);
        }

        return $submittedStatement;
    }

    /**
     * @return array
     *
     * @throws DataProviderException
     */
    protected function makeDraftStatement()
    {
        try {
            $baseData = $this->makeStatementData();

            return $this->draftService->addDraftStatement($baseData);
        } catch (Exception $e) {
            throw new DataProviderException('Generating draft statement failed. '.$e, 0, $e);
        }
    }

    /**
     * @throws DataProviderException
     * @throws InvalidUserDataException
     */
    protected function makeStatementData(): array
    {
        if ($this->procedure instanceof Procedure) {
            $procedure = $this->procedure;
        } else {
            $procedures = collect($this->procedureService->getProcedureFullList('', false));
            /* @var Procedure $procedure */
            $procedure = $procedures->random();
        }

        if ($this->orga instanceof Orga) {
            $organisation = $this->orga;
        } else {
            $allOrgas = collect($this->orgaService->getOrganisations());

            // Only use valid Orgas with departments
            $orgas = $allOrgas->filter(
                static fn (Orga $value) => $value->getDepartments()->count() > 0
            );

            $organisation = $orgas->random();
        }

        /* @var $departments Collection */
        $departments = collect($organisation->getDepartments()->toArray());

        if (0 === $departments->count()) {
            throw new DataProviderException("Unable to fetch a Department for Organisation {$organisation->getId()}");
        }

        $department = $departments->random();

        $baseData = [
            'pId'                  => $procedure->getId(),
            'oId'                  => $organisation->getId(),
            'dId'                  => $department->getId(),
            'publicDraftStatement' => DraftStatement::EXTERNAL,
            'text'                 => '<p>'.$this->faker->realText($this->faker->numberBetween(140, $this->maxChars)).'</p>',
        ];

        return $this->determineUserInfo($baseData);
    }

    /**
     * There three different ways of setting the user info on a generated statement.
     *
     * 1) If a concrete user was configured and exists, they will be chosen
     * 2) Otherwise, randomization will choose between
     *   a) Existing internal users with access rights to the project
     *   b) Existing public users
     *   c) Newly created public users
     *   d) Anonymous public users => "Bürger"
     *
     * @param array $baseData
     *
     * @throws InvalidUserDataException on invalid user value
     */
    protected function determineUserInfo($baseData)
    {
        // NOTE: Currently, only anonymous and named public users are being chosen
        // TODO (SG): Modify this to do everything the method comment says this does

        if ($this->user instanceof User) {
            $baseData['uId'] = $this->user->getId();

            return $baseData;
        }

        if (self::PUBLIC_USERS_ONLY !== $this->user) {
            // TODO: this needs to be reorganized to fetch / create users
            throw new InvalidUserDataException();
        }

        $baseData['anonym'] = true;

        $chanceOfGettingTrue = 20;
        if (!$this->faker->boolean($chanceOfGettingTrue)) {
            $baseData['useName'] = true;

            $baseData['uName'] = $this->faker->name;
            $baseData['userEmail'] = $this->faker->companyEmail;
            // use this and chanceOfGettingTrue=0 to create kinda random email addresses for submitted statements
            $baseData['userStreet'] = $this->faker->streetName.' '.$this->faker->numberBetween(0, 277);
            $baseData['userPostalCode'] = $this->faker->postcode;
            $baseData['userCity'] = $this->faker->city;
        }

        return $baseData;
    }

    /**
     * @param array $options
     *
     * @throws DataProviderException
     */
    public function configure(...$options): void
    {
        parent::configure(...$options);

        $this->permissions->enablePermissions(
            [
                'feature_admin_element_invitable_institution_or_public_authorisations',
                'feature_admin_element_public_access',
            ]
        );

        $this->permissions->disablePermissions(
            [
                'feature_statement_assignment',
            ]
        );

        $this->parseOptions($options[0]);

        if ($this->user instanceof User) {
            $this->currentUser->setUser($this->user);
        }
    }

    /**
     * @throws DataProviderException
     */
    protected function parseOptions(array $data)
    {
        if (array_key_exists('user', $data) && !self::PUBLIC_USERS_ONLY === $data['user']) {
            $this->fetchUser($data['user']);
        }

        if (array_key_exists('organisation', $data)
            && self::RANDOM_ORGANISATION !== $data['organisation']) {
            $this->fetchOrga($data['organisation']);
        }

        if (array_key_exists('procedure', $data)) {
            $this->fetchProcedure($data['procedure']);
        }

        if (array_key_exists('maxChars', $data)) {
            $this->maxChars = $data['maxChars'];
        }
    }

    /**
     * @throws DataProviderException
     */
    protected function fetchUser(?string $userLogin): void
    {
        try {
            $user = $this->userService->getUserByFields(['login' => $userLogin]);
        } catch (Exception $e) {
            throw new DataProviderException($e);
        }

        if (1 === count($user) && $user[0] instanceof User) {
            $this->user = $user[0];
        } else {
            throw new DataProviderException("Could not fetch user for login {$userLogin}");
        }
    }

    /**
     * @throws DataProviderException
     */
    protected function fetchOrga(?string $orgaId): void
    {
        try {
            $this->orga = $this->orgaService->getOrgaByFields(['name' => $orgaId]);
        } catch (Exception $e) {
            throw new DataProviderException($e);
        }
    }

    /**
     * @throws DataProviderException
     */
    protected function fetchProcedure(?string $procedureId): void
    {
        try {
            $this->procedure = $this->procedureService->getProcedure($procedureId);
        } catch (Exception $e) {
            throw new DataProviderException($e);
        }
    }
}
