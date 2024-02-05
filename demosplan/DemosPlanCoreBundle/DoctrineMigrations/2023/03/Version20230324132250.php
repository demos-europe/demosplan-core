<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Application\Migrations;

use demosplan\DemosPlanCoreBundle\Entity\User\AiApiUser;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Faker\Provider\Uuid;

class Version20230324132250 extends AbstractMigration
{
    private const PW_HASH = 'sha512';
    private const FLAGS = 'a:7:{s:10:"newsletter";b:0;s:16:"access_confirmed";b:1;s:16:"profileCompleted";b:1;s:7:"noPiwik";b:0;s:17:"forumNotification";b:0;s:7:"newUser";b:0;s:24:"assignedTaskNotification";b:1;}';

    public function getDescription(): string
    {
        return 'refs T31926: Insert the AiApiUser in the database.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $this->addSql('SET foreign_key_checks = 0;');
        $aiApiUserId = Uuid::uuid();
        // ensure that field is nullable as this is not the case in all projects
        $this->addSql('ALTER TABLE _user MODIFY alternative_login_password CHAR(255) NULL');
        $this->addSql('INSERT INTO _user SET
              _u_id = :user_id,
              _u_dm_id = NULL,
              _u_gender = NULL,
              _u_title = NULL,
              _u_firstname = NULL,
              _u_lastname = NULL,
              _u_email = :login,
              _u_login = :login,
              _u_password = :password,
              alternative_login_password = NULL,
              _u_salt = NULL,
              _u_language = NULL,
              _u_created_date = NOW(),
              _u_modified_date = NOW(),
              _u_deleted = 0,
              _u_gw_id = NULL,
              flags = :flags,
              last_login = NULL,
              twin_user_id = NULL,
              provided_by_identity_provider = 0;',
            [
                'user_id'  => $aiApiUserId,
                'login'    => AiApiUser::AI_API_USER_LOGIN,
                'password' => $this->generateNewRandomPassword(),
                'flags'    => self::FLAGS,
            ]
        );
        $allCustomerIds = $this->getAllCustomerIds();
        $roleId = $this->getRoleId();

        foreach ($allCustomerIds as $customerId) {
            $this->setRelationRoleUserCustomer($aiApiUserId, $roleId, $customerId['_c_id']);
        }

        $this->addSql('INSERT INTO _orga_users_doctrine SET
                      _o_id = :orgaId,
                      _u_id = :userId;',
            [
                'orgaId' => AiApiUser::ANONYMOUS_USER_ORGA_ID,
                'userId' => $aiApiUserId,
            ]
        );

        $this->addSql('INSERT INTO _department_users_doctrine SET
                           _d_id = :departmentId,
                           _u_id = :userId;',
            [
                'departmentId' => AiApiUser::ANONYMOUS_USER_DEPARTMENT_ID,
                'userId'       => $aiApiUserId,
            ]
        );
        $this->addSql('SET foreign_key_checks = 1;');
    }

    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $aiApiUserId = $this->getAiApiUserId();
        $this->addSql('SET foreign_key_checks = 0;');
        $this->addSql('DELETE FROM _user WHERE _u_id = ?;', [$aiApiUserId]);
        $this->addSql('DELETE FROM relation_role_user_customer WHERE user = ?;', [$aiApiUserId]);
        $this->addSql('DELETE FROM _orga_users_doctrine WHERE _u_id = ?;', [$aiApiUserId]);
        $this->addSql('DELETE FROM _department_users_doctrine WHERE _u_id = ?;', [$aiApiUserId]);
        $this->addSql('SET foreign_key_checks = 1;');
    }

    private function setRelationRoleUserCustomer($userId, $roleId, $customerId)
    {
        $this->addSql('INSERT INTO relation_role_user_customer SET
                        id = UUID(),
                        user = :userId,
                        role = :roleId,
                        customer = :customerId;',
            [
                'userId'     => $userId,
                'roleId'     => $roleId,
                'customerId' => $customerId,
            ]
        );
    }

    private function getAiApiUserId()
    {
        return $this->connection->fetchOne('SELECT _u_id FROM _user
             WHERE _u_login = :userLogin;', ['userLogin' => AiApiUser::AI_API_USER_LOGIN]);
    }

    private function getRoleId()
    {
        return $this->connection->fetchOne('SELECT _r_id FROM _role WHERE _r_code = "RAICOM";');
    }

    private function getAllCustomerIds()
    {
        return $this->connection->fetchAllAssociative('SELECT _c_id FROM customer;');
    }

    private function abortIfNotMysql(): void
    {
        $this->abortIf(
            'mysql' !== $this->connection->getDatabasePlatform()->getName(),
            "Migration can only be executed safely on 'mysql'."
        );
    }

    /**
     * Generate a new random password.
     *
     * WARNING: This is not a hashed password but the plain text variant
     *
     * This method should be used in places where we need to inform the user
     * about their new password, e.g. when recovering from a password loss.
     *
     * @throws Exception
     */
    private function generateNewRandomPassword(): string
    {
        return substr(hash(self::PW_HASH, random_bytes(500)), 0, 10);
    }
}
