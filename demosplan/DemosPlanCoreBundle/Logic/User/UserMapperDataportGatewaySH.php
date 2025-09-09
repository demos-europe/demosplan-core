<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\User;

use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\DuplicateGwIdException;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\ValueObject\Credentials;
use Symfony\Component\Yaml\Parser;

class UserMapperDataportGatewaySH extends UserMapperDataportGateway
{
    protected function loadRoleMapping()
    {
        $yaml = new Parser();
        // Lädt das Rollenmapping
        $this->roles = collect(
            $yaml->parse(
                // uses local file, no need for flysystem
                file_get_contents(DemosPlanPath::getRootPath('demosplan/DemosPlanCoreBundle/Logic/User').'/UserMapperDataportGatewaySH.yml')
            )
        );
    }

    public function getValidUser(Credentials $credentials): ?User
    {
        $this->logger->info('getValidUser with', [self::class]);
        $request = $this->getRequest();
        $token = trim($credentials->getToken());
        $this->logger->debug('Incoming Token', [$token]);

        $this->salt = $this->globalConfig->getSalt();

        // add possibility to test with local "redirects"
        if ($request->query->has('TokenTest')) {
            $token = trim((string) $request->get('TokenTest', null));
        }

        // gateway variables might be stored in session when verifying Orga/Departmentchange
        $this->data = $request->getSession()->get('unknownChange_gatewayData', null);
        $userChangedDepartment = $request->request->has('ChangedDepartment');
        $userUpdatedDepartment = $request->request->has('UpdatedDepartment');

        // -> Frage das Gateway mit dem Token an, also if gatewayData is saved but user does not come from Orga Verify page
        if (is_null($this->data) || !($userChangedDepartment || $userUpdatedDepartment)) {
            $this->logger->info('Call Gateway for Token', [$token]);
            $aResult = $this->authenticateByService($token);
            if (false === $aResult) {
                return null;
            }

            // GatewayXML parsen
            $this->data = $this->parseXML($aResult);
            if (false === $this->data) {
                return null;
            }
        }

        // Firmennutzer aus dem Gateway
        if ('3' == $this->data['modeID']) {
            // Nutzer prüfen
            $user = $this->createOrUpdateUser();

            // user claimed department changed
            if ($userChangedDepartment) {
                $this->logger->info('User claimed department has changed');
                $user = $this->cleanUserOrgaDepartment($user);
            }

            // if user changed Orga delete Orga and Department
            if ($user->getOrga() instanceof Orga && $user->getOrga()->getGwId() != $this->data['user']['COMPANYID']) {
                $this->logger->info('Orga has changed, clean user Orga and Department');
                $user = $this->cleanUserOrgaDepartment($user);
            }

            // if user already has orga, it needs to be updated eventually with current data from gateway
            if ($user->getOrga() instanceof Orga) {
                $userOrga = $user->getOrga();
                $departmentNameIs = $user->getDepartment() instanceof Department ? $user->getDepartment()->getName() : '';
                $departmentNameExpected = $this->getDepartmentNameFromGwData();
                $departmentNameChanged = $departmentNameExpected !== $departmentNameIs;

                // organame and Department has NOT changed, check other data to be updated
                if (!$departmentNameChanged || $userChangedDepartment || $userUpdatedDepartment) {
                    $this->logger->info('Orga gatewayId has NOT changed, check for updates');
                    try {
                        $this->updateOrga($userOrga);
                    } catch (DuplicateGwIdException) {
                        // when orga to change to exists and user claimed that orgadata has changed
                        // move user to existing orga.
                        $this->logger->warning('Clean User Orga/Departmentdata as update would lead to duplicate gwId at Orga: ', [$userOrga->getId()]);
                        $user = $this->cleanUserOrgaDepartment($user);
                    }
                } else {
                    $this->logger->info('Could not decide whether user changed Orga. Send to verification page');

                    // organame and/or Department HAS changed. We cannot decide whether user changed orga and/or department
                    // or Orga and/or department has been renamed
                    $this->saveTempUserVerificationData($request, $userOrga->getName(), $departmentNameIs, $departmentNameExpected);

                    if ($departmentNameChanged) {
                        // set verification route
                        $this->verificationRoute = 'DemosPlan_user_verify_department_switch_or_update';

                        return $user;
                    }
                }
            }

            // if user does not have orga yet, add her or create new orga
            $orga = $this->createOrgaIfNeededAndAddUser($user);

            // Update Department
            if ($user->getDepartment() instanceof Department) {
                $this->logger->info('ToebUser has department', [$user->getDepartment()->getName()]);
                $userDepartment = $user->getDepartment();
                $departmentName = $this->getDepartmentNameFromGwData();

                // check that department to be renamed to does not already exist.
                // in that case user has switched Departments in Government gateway and should change department
                /** @var Department[] $departments */
                $departments = $this->userService->getDepartmentByFields(['gwId' => md5($this->data['user']['COMPANYID'].$departmentName)]);

                // add user to existing department
                if (1 === count($departments) && $departments[0] instanceof Department) {
                    $this->logger->info('Add user to existing department', [$departments[0]->getName()]);

                    $this->userService->updateUser(
                        $user->getId(),
                        ['departmentId' => $departments[0]->getId()]
                    );
                } else {
                    // Department data has changed in Government gateway, update department
                    $this->logger->info('Update department');
                    $this->updateDepartment($userDepartment, $departmentName);
                }
            } else {
                // Auf Department prüfen
                $departmentName = $this->getDepartmentNameFromGwData();
                /** @var Department[] $departments */
                $departments = $this->userService->getDepartmentByFields(['gwId' => md5($this->data['user']['COMPANYID'].$departmentName)]);
                if (empty($departments)) {
                    // Anlegen eines Departments
                    $this->logger->info('Create new department');
                    $department = $this->createDepartment($departmentName, $orga->getId());
                    $this->userService->departmentAddUser($department->getId(), $user);
                } else {
                    // Nutzer zur vorhandene Department hinzufügen
                    $this->logger->info('Add user to existing department');
                    $this->userService->departmentAddUser($departments[0]->getId(), $user);
                }
            }

            return $user;
        }

        // Bürger
        if ('1' == $this->data['modeID']) {
            return $this->handleCitizen();
        }

        // default: User konnte nicht authentifiziert werden
        return null;
    }
}
