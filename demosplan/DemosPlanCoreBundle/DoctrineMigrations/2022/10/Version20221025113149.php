<?php declare(strict_types = 1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class Version20221025113149 extends AbstractMigration
{
    /** @var ValidatorInterface */
    private $validator;

    public function getDescription(): string
    {
        return 'refs T29704: migrate and remove invalid Orga::url values to make validation work';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $this->setValidator();

        /** @var list<array{id: non-empty-string, url: string}> $invalidUrlOrgas */
        $invalidUrlOrgas = $this->connection->fetchAllAssociative("SELECT _o_id as id, _o_url as url FROM _orga WHERE _o_url IS NOT NULL AND _o_url <> ''");
        foreach ($invalidUrlOrgas as $invalidUrlOrga) {
            // do nothing if already valid URL
            $url = $invalidUrlOrga['url'];
            if ($this->isValidUrl($url)) {
                continue;
            }

            // try to fix the url and save it if successful and null it if not
            $id = $invalidUrlOrga['id'];
            $url = "https://$url";
            if ($this->isValidUrl($url)) {
                $this->addSql('UPDATE _orga SET _o_url = ? WHERE _o_id = ?', [$url, $id]);
                continue;
            }
            $this->addSql('UPDATE _orga SET _o_url = NULL WHERE _o_id = ?', [$id]);
        }

        // null all empty strings in any case
        $this->addSql("UPDATE _orga SET _o_url = NULL WHERE _o_url = ''");
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->throwIrreversibleMigrationException('Changed and deleted values can\'t be restored.');
    }

    /**
     * @throws Exception
     */
    private function abortIfNotMysql(): void
    {
        $this->abortIf(
            'mysql' !== $this->connection->getDatabasePlatform()->getName(),
            "Migration can only be executed safely on 'mysql'."
        );
    }

    /**
     * @param mixed $url
     */
    private function isValidUrl($url): bool
    {
        $violations = $this->validator->validate($url, new Url());

        return 0 === $violations->count();
    }

    /**
     * @return void
     */
    public function setValidator()
    {
        $this->validator = Validation::createValidator();
    }
}
