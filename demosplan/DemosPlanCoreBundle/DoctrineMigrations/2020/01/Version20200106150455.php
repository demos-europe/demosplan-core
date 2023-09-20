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

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20200106150455 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create initial database schema';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _address (_a_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _a_code VARCHAR(10) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _a_street VARCHAR(100) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _a_street_1 VARCHAR(100) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _a_postalcode VARCHAR(10) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _a_city VARCHAR(100) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _a_region VARCHAR(45) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _a_state VARCHAR(65) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _a_postofficebox VARCHAR(10) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _a_phone VARCHAR(30) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _a_fax VARCHAR(30) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _a_email VARCHAR(364) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _a_url VARCHAR(364) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _a_created_date DATETIME NOT NULL, _a_modified_date DATETIME NOT NULL, _a_deleted TINYINT(1) DEFAULT 0 NOT NULL, house_number VARCHAR(255) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, PRIMARY KEY(_a_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _autosave (_as_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _as_content LONGTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _as_user_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _as_formular_id VARCHAR(1024) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _as_create_date DATETIME NOT NULL, INDEX fk__user_ufk_1 (_as_user_id), PRIMARY KEY(_as_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _category (_c_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _c_name VARCHAR(50) CHARACTER SET utf8 DEFAULT \'custom_category\' NOT NULL COLLATE `utf8_unicode_ci` COMMENT \'Has no function for custom categories\', _c_title VARCHAR(255) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, _c_description TEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _c_picture VARCHAR(128) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, _c_picture_title VARCHAR(255) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, _c_enabled TINYINT(1) DEFAULT 1 NOT NULL, _c_deleted TINYINT(1) DEFAULT 0 NOT NULL, _c_create_date DATETIME NOT NULL, _c_modify_date DATETIME NOT NULL, _c_delete_date DATETIME NOT NULL, custom TINYINT(1) DEFAULT 1 NOT NULL COMMENT \'Determines if this entry was created by a user or is predefined.\', PRIMARY KEY(_c_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _county (_c_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _c_name VARCHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, PRIMARY KEY(_c_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _department (_d_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _d_name VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _d_code VARCHAR(128) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _d_created_date DATETIME NOT NULL, _d_modified_date DATETIME NOT NULL, _d_deleted TINYINT(1) DEFAULT 0 NOT NULL, _d_gw_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, UNIQUE INDEX _d_gw_id (_d_gw_id), PRIMARY KEY(_d_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _department_addresses_doctrine (_d_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _a_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_7B8358BF5125D371 (_d_id), INDEX IDX_7B8358BF66FB2343 (_a_id), PRIMARY KEY(_d_id, _a_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _department_users_doctrine (_d_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _u_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_AA982FA5125D371 (_d_id), INDEX IDX_AA982FAB980E38B (_u_id), PRIMARY KEY(_d_id, _u_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _draft_statement (_ds_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _p_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ds_paragraph_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _ds_document_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _ds_element_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _o_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _d_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _u_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ds_number INT DEFAULT 0 NOT NULL, _ds_title VARCHAR(4000) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ds_text MEDIUMTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ds_polygon TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ds_file CHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ds_map_file CHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _o_name VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _d_name VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _u_name VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _u_street VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _u_postal_code VARCHAR(6) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _u_city VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _u_email VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ds_feedback VARCHAR(10) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ds_extern_id CHAR(25) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ds_rejected_reason VARCHAR(4000) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ds_negative_statement TINYINT(1) NOT NULL, _ds_submited TINYINT(1) NOT NULL, _ds_released TINYINT(1) NOT NULL, _ds_show_all TINYINT(1) DEFAULT 0 NOT NULL, _ds_deleted TINYINT(1) NOT NULL, _ds_rejected TINYINT(1) NOT NULL, _ds_public_allowed TINYINT(1) DEFAULT 0 NOT NULL, _ds_public_use_name TINYINT(1) DEFAULT 0 NOT NULL, _ds_public_draft_statement VARCHAR(20) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ds_phase VARCHAR(50) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ds_created_date DATETIME NOT NULL, _ds_deleted_date DATETIME NOT NULL, _ds_last_modified_date DATETIME NOT NULL, _ds_submited_date DATETIME NOT NULL, _ds_released_date DATETIME NOT NULL, _ds_rejected_date DATETIME NOT NULL, _ds_represents VARCHAR(256) CHARACTER SET utf8 DEFAULT \'\' COLLATE `utf8_unicode_ci`, _ds_misc_data LONGTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci` COMMENT \'(DC2Type:array)\', house_number VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, u_feedback TINYINT(1) NOT NULL, anonymous TINYINT(1) DEFAULT 0 NOT NULL, INDEX IDX_5D77C03C2A698ED0 (_ds_element_id), INDEX IDX_5D77C03C5125D371 (_d_id), INDEX IDX_5D77C03C2A3B2F41 (_ds_paragraph_id), INDEX IDX_5D77C03C86245470 (_o_id), INDEX IDX_5D77C03CB980E38B (_u_id), INDEX IDX_5D77C03C8E5E13B9 (_p_id), INDEX IDX_5D77C03C79DA3896 (_ds_document_id), PRIMARY KEY(_ds_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _draft_statement_versions (_dsv_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ds_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _p_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ds_paragraph_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _ds_document_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _ds_element_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _o_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _d_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _u_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ds_number INT DEFAULT 0 NOT NULL, _ds_title VARCHAR(4000) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ds_text MEDIUMTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ds_polygon TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ds_file CHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ds_map_file CHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _o_name VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _d_name VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _u_name VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _u_street VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _u_postal_code VARCHAR(6) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _u_city VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _u_email VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ds_feedback VARCHAR(10) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ds_extern_id CHAR(25) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ds_rejected_reason VARCHAR(4000) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ds_negative_statement TINYINT(1) NOT NULL, _ds_submited TINYINT(1) NOT NULL, _ds_released TINYINT(1) NOT NULL, _ds_show_all TINYINT(1) DEFAULT 0 NOT NULL, _ds_deleted TINYINT(1) NOT NULL, _ds_rejected TINYINT(1) NOT NULL, _ds_public_allowed TINYINT(1) DEFAULT 0 NOT NULL, _ds_public_use_name TINYINT(1) DEFAULT 0 NOT NULL, _ds_public_draft_statement VARCHAR(20) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ds_phase VARCHAR(50) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ds_version_date DATETIME NOT NULL, _ds_created_date DATETIME NOT NULL, _ds_last_modified_date DATETIME NOT NULL, _ds_deleted_date DATETIME NOT NULL, _ds_submited_date DATETIME NOT NULL, _ds_released_date DATETIME NOT NULL, _ds_rejected_date DATETIME NOT NULL, INDEX IDX_1C6084CF2A698ED0 (_ds_element_id), INDEX IDX_1C6084CF5125D371 (_d_id), INDEX _ds_id (_ds_id), INDEX IDX_1C6084CF2A3B2F41 (_ds_paragraph_id), INDEX IDX_1C6084CF86245470 (_o_id), INDEX IDX_1C6084CFB980E38B (_u_id), INDEX IDX_1C6084CF8E5E13B9 (_p_id), INDEX IDX_1C6084CF79DA3896 (_ds_document_id), PRIMARY KEY(_dsv_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _elements (_e_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _e_p_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _p_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _e_category CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _e_title VARCHAR(256) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _e_icon VARCHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _e_text TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _e_order INT NOT NULL, _e_enabled TINYINT(1) DEFAULT 1 NOT NULL, _e_deleted TINYINT(1) DEFAULT 0 NOT NULL, _e_create_date DATETIME NOT NULL, _e_modify_date DATETIME NOT NULL, _e_delete_date DATETIME NOT NULL, _e_file VARCHAR(256) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, _e_designated_switch_date DATETIME DEFAULT NULL, _e_icon_title VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci` COMMENT \'Content of title-tag for icon\', permission VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci` COMMENT \'Needed permission, to get this element.\', INDEX IDX_6A8A423D8E5E13B9 (_p_id), INDEX IDX_6A8A423D58B43C6D (_e_p_id), PRIMARY KEY(_e_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _elements_orga_doctrine (_o_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _e_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_B97ACF3CE999B414 (_e_id), INDEX IDX_B97ACF3C86245470 (_o_id), PRIMARY KEY(_e_id, _o_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _files (_f_ident CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci` COMMENT \'This id is used in filestrings to reference to the file entity\', procedure_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _f_hash CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci` COMMENT \'This hash is used as filename\', _f_name VARCHAR(256) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _f_description TEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _f_path VARCHAR(256) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _f_filename VARCHAR(256) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _f_tags TEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _f_author VARCHAR(64) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _f_application VARCHAR(64) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _f_mimetype VARCHAR(256) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _f_created DATETIME NOT NULL, _f_modified DATETIME NOT NULL, _f_valid_until DATETIME NOT NULL, _f_deleted TINYINT(1) DEFAULT 0 NOT NULL, _f_stat_down INT DEFAULT 0 NOT NULL, _f_infected TINYINT(1) DEFAULT 0 NOT NULL, _f_last_v_scan DATETIME NOT NULL, _f_blocked TINYINT(1) DEFAULT 1 NOT NULL, _f_id INT DEFAULT NULL, size BIGINT DEFAULT NULL, INDEX IDX_8C0DACC51624BCD2 (procedure_id), PRIMARY KEY(_f_ident)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _forum_entries (_fe_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _f_thread_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _u_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _fe_user_roles VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _fe_text MEDIUMTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _fe_initial_entry TINYINT(1) DEFAULT 0 NOT NULL, _fe_create_date DATETIME NOT NULL, _fe_modified_date DATETIME NOT NULL, INDEX IDX_7364BE9AB980E38B (_u_id), INDEX IDX_7364BE9AC1DDD61B (_f_thread_id), PRIMARY KEY(_fe_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _forum_entry_files (_fef_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _fef_entry_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _fef_create_date DATETIME NOT NULL, _fef_modified_date DATETIME NOT NULL, _fef_deleted TINYINT(1) DEFAULT 0 NOT NULL, _fef_blocked TINYINT(1) DEFAULT 0 NOT NULL, _fef_order SMALLINT UNSIGNED NOT NULL, _fef_string VARCHAR(2048) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _fef_hash CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX _fef_entry_id__fef_order (_fef_entry_id, _fef_order), INDEX IDX_48BE16BD69643BEF (_fef_entry_id), PRIMARY KEY(_fef_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _forum_threads (_ft_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ft_url VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _ft_closed TINYINT(1) DEFAULT 0 NOT NULL, _ft_closing_reason VARCHAR(1024) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _ft_create_date DATETIME NOT NULL, _ft_progression TINYINT(1) DEFAULT 0 NOT NULL, _ft_modified_date DATETIME NOT NULL, INDEX fk__forum_topic_tfk_1 (_ft_id), UNIQUE INDEX UNIQ_311230822576F20 (_ft_url), PRIMARY KEY(_ft_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _gis (_g_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _g_pcsh_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, category_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _p_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _g_global_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _g_name VARCHAR(256) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _g_type VARCHAR(64) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _g_url VARCHAR(4096) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _g_layers VARCHAR(4096) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _g_legend VARCHAR(512) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, _g_opacity INT DEFAULT 100 NOT NULL, _g_bplan TINYINT(1) DEFAULT 0 NOT NULL, _g_xplan TINYINT(1) DEFAULT 0 NOT NULL, _g_print TINYINT(1) DEFAULT 0 NOT NULL, _g_scope1 TINYINT(1) DEFAULT 0 NOT NULL, _g_scope TINYINT(1) DEFAULT 0 NOT NULL, default_visibility TINYINT(1) DEFAULT 0 NOT NULL, enabled TINYINT(1) DEFAULT 1 NOT NULL, _g_deleted TINYINT(1) DEFAULT 0 NOT NULL, _g_order INT DEFAULT 0 NOT NULL, _g_create_date DATETIME NOT NULL, _g_modify_date DATETIME NOT NULL, _g_delete_date DATETIME NOT NULL, _g_servicetype VARCHAR(12) CHARACTER SET utf8 DEFAULT \'wms\' NOT NULL COLLATE `utf8_unicode_ci`, _g_cabilities TEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _g_tile_matrix_set VARCHAR(256) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, tree_order INT DEFAULT 0 NOT NULL, user_toggle_visibility TINYINT(1) DEFAULT 1 NOT NULL, visibility_group_id VARCHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _g_is_minimap TINYINT(1) DEFAULT 0 NOT NULL, _projection_label VARCHAR(255) CHARACTER SET utf8 DEFAULT \'EPSG:3857\' NOT NULL COLLATE `utf8_unicode_ci`, _projection_value VARCHAR(255) CHARACTER SET utf8 DEFAULT \'+proj=merc +a=6378137 +b=6378137 +lat_ts=0.0 +lon_0=0.0 +x_0=0.0 +y_0=0 +k=1.0 +units=m +nadgrids=@null +wktext  +no_defs\' NOT NULL COLLATE `utf8_unicode_ci`, layer_version VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_8281ED2512469DE2 (category_id), UNIQUE INDEX UNIQ_8281ED25A4794CBA (_g_pcsh_id), INDEX _g_global_id (_g_global_id), PRIMARY KEY(_g_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _mail_attachment (_ma_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ma_ms_id INT NOT NULL, _ma_filename VARCHAR(256) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _ma_delete_on_sent TINYINT(1) DEFAULT 0 NOT NULL, INDEX IDX_D047D4AB513D72BC (_ma_ms_id), PRIMARY KEY(_ma_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _mail_send (_ms_id INT AUTO_INCREMENT NOT NULL, _ms_id_ref INT DEFAULT NULL, _ms_mt_template VARCHAR(50) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ms_to TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ms_from VARCHAR(4096) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ms_cc VARCHAR(10000) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, _ms_bcc VARCHAR(5) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, _ms_title VARCHAR(1024) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, _ms_content TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ms_created_date DATETIME NOT NULL, _ms_send_date DATETIME NOT NULL, _ms_scope CHAR(6) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ms_status VARCHAR(10) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, _ms_context VARCHAR(256) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, _ms_context2 VARCHAR(256) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, _ms_send_attempt INT DEFAULT 0 NOT NULL, _ms_last_status_date DATETIME NOT NULL, _ms_error_code VARCHAR(255) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, _ms_error_message TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, PRIMARY KEY(_ms_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _mail_templates (_mt_id INT AUTO_INCREMENT NOT NULL, _mt_label VARCHAR(50) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _mt_language CHAR(6) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _mt_title VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _mt_content TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, PRIMARY KEY(_mt_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _manual_list_sort (_mls_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _p_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _mls_context VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _mls_namespace VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _mls_idents TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, PRIMARY KEY(_mls_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _master_toeb (_mt_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _o_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _d_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _mt_gateway_group VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _mt_orga_name VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _mt_department_name VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _mt_sign VARCHAR(50) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _mt_email VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _mt_cc_email VARCHAR(4096) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _mt_contact_person VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _mt_memo TEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _mt_comment TEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _mt_registered TINYINT(1) DEFAULT 0, _mt_district_hh_mitte SMALLINT DEFAULT 0 NOT NULL, _mt_district_eimsbuettel SMALLINT DEFAULT 0 NOT NULL, _mt_district_altona SMALLINT DEFAULT 0 NOT NULL, _mt_district_hh_nord SMALLINT DEFAULT 0 NOT NULL, _mt_district_wandsbek SMALLINT DEFAULT 0 NOT NULL, _mt_district_bergedorf SMALLINT DEFAULT 0 NOT NULL, _mt_district_harburg SMALLINT DEFAULT 0 NOT NULL, _mt_district_bsu SMALLINT DEFAULT 0 NOT NULL, _mt_document_rough_agreement TINYINT(1) DEFAULT 0 NOT NULL, _mt_document_agreement TINYINT(1) DEFAULT 0 NOT NULL, _mt_document_notice TINYINT(1) DEFAULT 0 NOT NULL, _mt_document_assessment TINYINT(1) DEFAULT 0 NOT NULL, _mt_created_date DATETIME NOT NULL, _mt_modified_date DATETIME NOT NULL, INDEX IDX_48AC64CB5125D371 (_d_id), UNIQUE INDEX UNIQ_48AC64CB86245470 (_o_id), PRIMARY KEY(_mt_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _master_toeb_versions (_mtv_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _mt_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _o_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _d_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _mt_gateway_group VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _mt_orga_name VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _mt_department_name VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _mt_sign VARCHAR(50) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _mt_email VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _mt_cc_email VARCHAR(4096) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _mt_contact_person VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _mt_memo TEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _mt_comment TEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _mt_registered TINYINT(1) DEFAULT 0 NOT NULL, _mt_district_hh_mitte TINYINT(1) DEFAULT 0 NOT NULL, _mt_district_eimsbuettel TINYINT(1) DEFAULT 0 NOT NULL, _mt_district_altona TINYINT(1) DEFAULT 0 NOT NULL, _mt_district_hh_nord TINYINT(1) DEFAULT 0 NOT NULL, _mt_district_wandsbek TINYINT(1) DEFAULT 0 NOT NULL, _mt_district_bergedorf TINYINT(1) DEFAULT 0 NOT NULL, _mt_district_harburg TINYINT(1) DEFAULT 0 NOT NULL, _mt_district_bsu TINYINT(1) DEFAULT 0 NOT NULL, _mt_document_rough_agreement TINYINT(1) DEFAULT 0 NOT NULL, _mt_document_agreement TINYINT(1) DEFAULT 0 NOT NULL, _mt_document_notice TINYINT(1) DEFAULT 0 NOT NULL, _mt_document_assessment TINYINT(1) DEFAULT 0 NOT NULL, _mt_created_date DATETIME NOT NULL, _mt_modified_date DATETIME NOT NULL, _mtv_version_date DATETIME NOT NULL, INDEX IDX_3A2F077D86245470 (_o_id), INDEX IDX_3A2F077D5125D371 (_d_id), INDEX IDX_3A2F077D50E58394 (_mt_id), PRIMARY KEY(_mtv_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _municipality (_m_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _m_name CHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, official_municipality_key CHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, UNIQUE INDEX official_municipality_key (official_municipality_key), PRIMARY KEY(_m_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _news (_n_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _p_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _n_title VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _n_description TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _n_text TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _n_picture VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _n_picture_title VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _n_pdf VARCHAR(255) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, _n_pdf_title VARCHAR(255) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, _n_enabled TINYINT(1) NOT NULL, _n_deleted TINYINT(1) NOT NULL, _n_create_date DATETIME NOT NULL, _n_modify_date DATETIME NOT NULL, _n_delete_date DATETIME NOT NULL, designated_switch_date DATETIME DEFAULT NULL, determined_to_switch TINYINT(1) NOT NULL, designated_state TINYINT(1) DEFAULT NULL, PRIMARY KEY(_n_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _news_roles (_n_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _r_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_AFCA59E13E983315 (_n_id), INDEX IDX_AFCA59E12457DB32 (_r_id), PRIMARY KEY(_n_id, _r_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _orga (_o_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, current_slug_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, branding_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _o_name VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _o_gateway_name VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _o_code VARCHAR(128) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _o_created_date DATETIME NOT NULL, _o_modified_date DATETIME NOT NULL, _o_cc_email2 VARCHAR(4096) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _o_url VARCHAR(364) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _o_deleted TINYINT(1) DEFAULT 0 NOT NULL, _o_showname TINYINT(1) DEFAULT 0 NOT NULL, _o_showlist TINYINT(1) DEFAULT 0 NOT NULL, _o_gw_id VARCHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _o_competence TEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _o_email2 VARCHAR(364) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _o_contact_person VARCHAR(256) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _o_paper_copy INT UNSIGNED DEFAULT NULL, _o_paper_copy_spec VARCHAR(4096) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _o_email_reviewer_admin VARCHAR(4096) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, data_protection TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, imprint MEDIUMTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, UNIQUE INDEX _o_gw_id (_o_gw_id), UNIQUE INDEX UNIQ_585858C29B14E34B (current_slug_id), UNIQUE INDEX UNIQ_585858C2560BC00E (branding_id), PRIMARY KEY(_o_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _orga_addresses_doctrine (_o_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _a_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_9DE5B2B386245470 (_o_id), INDEX IDX_9DE5B2B366FB2343 (_a_id), PRIMARY KEY(_o_id, _a_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _orga_departments_doctrine (_o_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _d_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_2DDC262386245470 (_o_id), INDEX IDX_2DDC26235125D371 (_d_id), PRIMARY KEY(_o_id, _d_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _orga_type (_ot_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ot_name CHAR(6) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ot_label VARCHAR(45) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, PRIMARY KEY(_ot_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _orga_users_doctrine (_o_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _u_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_78B76CDE86245470 (_o_id), INDEX IDX_78B76CDEB980E38B (_u_id), PRIMARY KEY(_o_id, _u_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _para_doc (_pd_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _p_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _e_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _pd_parent_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _pd_category CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _pd_title TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _pd_text MEDIUMTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _pd_order INT NOT NULL, _pd_visible INT NOT NULL, _pd_deleted TINYINT(1) DEFAULT 0 NOT NULL, _pd_create_date DATETIME NOT NULL, _pd_modify_date DATETIME NOT NULL, _pd_delete_date DATETIME NOT NULL, _pd_lockreason VARCHAR(300) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_C2A5857D39A5C393 (_pd_parent_id), INDEX IDX_C2A5857D8E5E13B9 (_p_id), INDEX IDX_C2A5857DE999B414 (_e_id), PRIMARY KEY(_pd_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _para_doc_version (_pdv_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _pd_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _p_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _e_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _pd_category VARCHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _pd_title TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _pd_text MEDIUMTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _pd_order INT NOT NULL, _pd_visible TINYINT(1) NOT NULL, _pd_deleted TINYINT(1) DEFAULT 0 NOT NULL, _pdv_version_date DATETIME NOT NULL, _pd_create_date DATETIME NOT NULL, _pd_modify_date DATETIME NOT NULL, _pd_delete_date DATETIME NOT NULL, INDEX IDX_FC2B484C8E5E13B9 (_p_id), INDEX IDX_FC2B484C988C8738 (_pd_id), INDEX IDX_FC2B484CE999B414 (_e_id), PRIMARY KEY(_pdv_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _platform_content (_pc_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _pc_type VARCHAR(60) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, _pc_title VARCHAR(255) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, _pc_description TEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _pc_text TEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _pc_picture VARCHAR(255) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, _pc_picture_title VARCHAR(255) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, _pc_pdf VARCHAR(255) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, _pc_pdf_title VARCHAR(255) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, _pc_enabled TINYINT(1) DEFAULT 0 NOT NULL, _pc_deleted TINYINT(1) DEFAULT 0 NOT NULL, _pc_create_date DATETIME NOT NULL, _pc_modify_date DATETIME NOT NULL, _pc_delete_date DATETIME NOT NULL, PRIMARY KEY(_pc_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _platform_content_categories (_pc_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _c_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_13635DFB55BBF81 (_pc_id), INDEX IDX_13635DFBCCF2EBC8 (_c_id), PRIMARY KEY(_pc_id, _c_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _platform_content_roles (_pc_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _r_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_FDE96BF955BBF81 (_pc_id), INDEX IDX_FDE96BF92457DB32 (_r_id), PRIMARY KEY(_pc_id, _r_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _platform_context_sensitive_help (_pcsh_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _pcsh_key VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _pcsh_text TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _pcsh_created DATETIME NOT NULL, _pcsh_modified DATETIME NOT NULL, PRIMARY KEY(_pcsh_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _predefined_texts (_pt_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _p_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, group_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _pt_title VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _pt_text LONGTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _pt_create_date DATETIME NOT NULL, _pt_modify_date DATETIME NOT NULL, INDEX IDX_810C1D0FFE54D947 (group_id), INDEX IDX_810C1D0F8E5E13B9 (_p_id), PRIMARY KEY(_pt_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'Tabelle für Textbausteine\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _predefined_texts_category (ptc_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _p_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, ptc_title VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, ptc_text LONGTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, ptc_create_date DATETIME NOT NULL, ptc_modify_date DATETIME NOT NULL, INDEX IDX_2C71A838E5E13B9 (_p_id), PRIMARY KEY(ptc_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _priority_area (_pa_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _pa_key CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _pa_type CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, UNIQUE INDEX key_idx (_pa_key), PRIMARY KEY(_pa_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _procedure (_p_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _o_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, current_slug_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, customer CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, procedure_type_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, statement_form_definition_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, procedure_ui_definition_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, procedure_behavior_definition_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, maillane_connection_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _p_name TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _p_short_url VARCHAR(256) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, _o_name VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _p_desc TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _p_phase VARCHAR(50) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _p_step VARCHAR(25) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, _p_logo CHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _p_extern_id CHAR(25) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _p_plis_id CHAR(36) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, _p_closed TINYINT(1) DEFAULT 0 NOT NULL, _p_deleted TINYINT(1) DEFAULT 0 NOT NULL, _p_master INT NOT NULL, _p_external_name TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _p_external_desc TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _p_public_participation TINYINT(1) DEFAULT 0 NOT NULL, _p_public_participation_phase VARCHAR(20) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _p_public_participation_step VARCHAR(25) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, _p_public_participation_start DATETIME NOT NULL, _p_public_participation_end DATETIME NOT NULL, _p_public_participation_contact VARCHAR(2048) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _p_location_name VARCHAR(1024) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _p_location_postcode VARCHAR(5) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, _p_municipal_code VARCHAR(10) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _p_ars VARCHAR(12) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci` COMMENT \'Amtlicher Regionalschluessel\', _p_created_date DATETIME NOT NULL, _p_start_date DATETIME NOT NULL, _p_end_date DATETIME NOT NULL, _p_closed_date DATETIME NOT NULL, _p_deleted_date DATETIME NOT NULL, _p_public_participation_publication_enabled TINYINT(1) DEFAULT 1 NOT NULL, agency_main_email_address VARCHAR(364) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci` COMMENT \'main email address of the agency (organization) assigned to this procedure\', master_template TINYINT(1) NOT NULL, extern_id VARCHAR(50) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, UNIQUE INDEX UNIQ_D1A01D02E68E097B (procedure_ui_definition_id), UNIQUE INDEX UNIQ_D1A01D02AC0C069A (maillane_connection_id), INDEX IDX_D1A01D029404667A (procedure_type_id), UNIQUE INDEX UNIQ_D1A01D0281398E09 (customer), UNIQUE INDEX UNIQ_D1A01D02A0D2BC07 (procedure_behavior_definition_id), INDEX IDX_D1A01D0286245470 (_o_id), UNIQUE INDEX UNIQ_D1A01D029B14E34B (current_slug_id), UNIQUE INDEX UNIQ_D1A01D027BF2A129 (statement_form_definition_id), PRIMARY KEY(_p_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _procedure_orga_doctrine (_p_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _o_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_E46E17648E5E13B9 (_p_id), INDEX IDX_E46E176486245470 (_o_id), PRIMARY KEY(_p_id, _o_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _procedure_settings (_ps_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _p_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, designated_phase_change_user_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, designated_public_phase_change_user_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _ps_map_extent VARCHAR(2048) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ps_start_scale VARCHAR(2048) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ps_available_scale VARCHAR(2048) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ps_bounding_box VARCHAR(2048) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ps_information_url VARCHAR(2048) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ps_default_layer VARCHAR(2048) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ps_territory TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ps_coordinate VARCHAR(2048) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ps_plan_enable TINYINT(1) DEFAULT 0 NOT NULL, _ps_plan_text TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ps_plan_pdf VARCHAR(256) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ps_plan_para1_pdf VARCHAR(256) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ps_plan_para2_pdf VARCHAR(256) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ps_plan_draw_text TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ps_plan_draw_pdf VARCHAR(256) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ps_email_title VARCHAR(2048) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ps_email_text TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ps_email_cc TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ps_links LONGTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ps_pictogram VARCHAR(256) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _ps_designated_phase VARCHAR(50) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _ps_designated_public_phase VARCHAR(50) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _ps_designated_public_switch_date DATETIME DEFAULT NULL, _ps_designated_switch_date DATETIME DEFAULT NULL, _ps_designated_end_date DATETIME DEFAULT NULL, _ps_designated_public_end_date DATETIME DEFAULT NULL, _ps_send_mails_to_counties TINYINT(1) DEFAULT 0 NOT NULL, planning_area VARCHAR(255) CHARACTER SET utf8 DEFAULT \'all\' NOT NULL COLLATE `utf8_unicode_ci`, scales VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, legal_notice VARCHAR(255) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, copyright VARCHAR(255) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, map_hint VARCHAR(2000) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_9C04F53DF3EE7A28 (designated_public_phase_change_user_id), INDEX _procedure_settings_ibfk_1 (_p_id), UNIQUE INDEX UNIQ_9C04F53D8E5E13B9 (_p_id), INDEX IDX_9C04F53DCBD82728 (designated_phase_change_user_id), PRIMARY KEY(_ps_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _procedure_subscriptions (_psu_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _u_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _u_email VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _psu_postalcode VARCHAR(5) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _psu_city VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _psu_distance INT NOT NULL, _psu_deleted TINYINT(1) DEFAULT 0 NOT NULL, _psu_created_date DATETIME NOT NULL, _psu_modified_date DATETIME NOT NULL, _psu_deleted_date DATETIME NOT NULL, INDEX IDX_F454B45B980E38B (_u_id), UNIQUE INDEX _psu_id (_psu_id), PRIMARY KEY(_psu_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'Tabelle für Verfahrenabonnements \' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _progression_releases (_pr_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _pr_description TEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _pr_title VARCHAR(1024) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _pr_phase VARCHAR(128) CHARACTER SET utf8 DEFAULT \'configuration\' NOT NULL COLLATE `utf8_unicode_ci`, _pr_start_date DATETIME DEFAULT NULL, _pr_end_date DATETIME DEFAULT NULL, _pr_create_date DATETIME NOT NULL, _pr_modified_date DATETIME NOT NULL, PRIMARY KEY(_pr_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _progression_userstories (_pu_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _pu_release_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _pu_thread_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _pu_online_votes SMALLINT UNSIGNED DEFAULT 0 NOT NULL, _pu_offline_votes SMALLINT UNSIGNED DEFAULT 0 NOT NULL, _pu_description TEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _pu_title VARCHAR(1024) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _pu_create_date DATETIME NOT NULL, _pu_modified_date DATETIME NOT NULL, INDEX IDX_CA6D6268C63752F1 (_pu_thread_id), INDEX IDX_CA6D62681FDFBF25 (_pu_release_id), PRIMARY KEY(_pu_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _progression_userstory_votes (_puv_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _puv_orga_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _puv_user_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _puv_userstroy_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _puv_number_of_votes SMALLINT UNSIGNED DEFAULT 1 NOT NULL, _puv_create_date DATETIME NOT NULL, _puv_modified_date DATETIME NOT NULL, INDEX IDX_D4838E32EEC548E3 (_puv_user_id), INDEX IDX_D4838E32DE5BF3D7 (_puv_orga_id), INDEX IDX_D4838E324965C830 (_puv_userstroy_id), PRIMARY KEY(_puv_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _report_entries (_re_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _c_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _re_category CHAR(100) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _re_group CHAR(100) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _re_level CHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _u_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _u_name CHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _s_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _re_identifier_type VARCHAR(50) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _re_identifier CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _re_message_mime_type VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _re_message MEDIUMTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _re_incoming MEDIUMTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _re_created_date DATETIME NOT NULL, INDEX IDX_9118934A2A23665C (_re_group), INDEX IDX_9118934AB59F1685 (_re_identifier), INDEX IDX_9118934AB272B5A3 (_re_identifier_type), INDEX IDX_9118934ACCF2EBC8 (_c_id), INDEX IDX_9118934A6A3F6F1B (_re_category), PRIMARY KEY(_re_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _role (_r_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _r_code CHAR(6) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _r_group_code CHAR(6) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _r_group_name VARCHAR(60) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, PRIMARY KEY(_r_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _settings (_s_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _s_procedure_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _s_user_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _s_orga_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _s_key VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _s_content TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _s_create_date DATETIME NOT NULL, _s_modified_date DATETIME NOT NULL, INDEX _s_key (_s_key), INDEX IDX_CB85E5A5D4BFA722 (_s_user_id), INDEX IDX_CB85E5A5D8C72A2F (_s_procedure_id), INDEX IDX_CB85E5A5E4211C16 (_s_orga_id), PRIMARY KEY(_s_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _single_doc (_sd_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _p_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _e_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _sd_category VARCHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _sd_order INT NOT NULL, _sd_title VARCHAR(256) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _sd_text TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _sd_symbol VARCHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _sd_document VARCHAR(256) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _sd_statement_enabled TINYINT(1) DEFAULT 0 NOT NULL, _sd_visible TINYINT(1) DEFAULT 1 NOT NULL, _sd_deleted TINYINT(1) DEFAULT 0 NOT NULL, _sd_create_date DATETIME NOT NULL, _sd_modify_date DATETIME NOT NULL, _sd_delete_date DATETIME NOT NULL, INDEX IDX_22855D16E999B414 (_e_id), INDEX IDX_22855D168E5E13B9 (_p_id), PRIMARY KEY(_sd_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _single_doc_version (_sdv_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _sd_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _p_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _e_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _sd_category CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _sd_order INT NOT NULL, _sd_title VARCHAR(256) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, _sd_text TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _sd_symbol VARCHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _sd_document VARCHAR(256) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _sd_statement_enabled TINYINT(1) DEFAULT 0 NOT NULL, _sd_visible TINYINT(1) DEFAULT 1 NOT NULL, _sd_deleted TINYINT(1) DEFAULT 0 NOT NULL, _sdv_version_date DATETIME NOT NULL, _sd_create_date DATETIME NOT NULL, _sd_modify_date DATETIME NOT NULL, _sd_delete_date DATETIME NOT NULL, INDEX IDX_C6FE7B6A8E5E13B9 (_p_id), INDEX IDX_C6FE7B6ADF2CFDE8 (_sd_id), INDEX IDX_C6FE7B6AE999B414 (_e_id), PRIMARY KEY(_sdv_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _statement (_st_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _st_p_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _st_o_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _u_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _o_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _p_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _st_paragraph_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _st_document_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _st_element_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _ds_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, assignee CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, head_statement_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, placeholder_statement_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, moved_statement_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, bthg_kompass_answer_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, place_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _st_priority CHAR(10) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _st_extern_id CHAR(25) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _st_phase VARCHAR(50) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _st_status CHAR(50) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _st_created_date DATETIME NOT NULL, _st_modified_date DATETIME NOT NULL, _st_send_date DATETIME NOT NULL, _st_sent_assessment_date DATETIME NOT NULL, _st_submit_date DATETIME NOT NULL, _st_deleted_date DATETIME NOT NULL, _st_deleted TINYINT(1) DEFAULT 0 NOT NULL, _st_negativ_statement TINYINT(1) DEFAULT 0 NOT NULL, _st_sent_assessment TINYINT(1) DEFAULT 0 NOT NULL, _st_public_use_name TINYINT(1) DEFAULT 0 NOT NULL, _st_public_verified VARCHAR(30) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _st_public_statement VARCHAR(20) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _st_to_send_per_mail TINYINT(1) DEFAULT 0 NOT NULL, _st_title VARCHAR(4096) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _st_text MEDIUMTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _st_recommendation MEDIUMTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _st_memo TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _st_feedback VARCHAR(10) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _st_reason_paragraph TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _st_planning_document VARCHAR(4096) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _st_file CHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _st_map_file CHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _st_polygon TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _st_county_notified TINYINT(1) DEFAULT 0 NOT NULL, _st_represents VARCHAR(256) CHARACTER SET utf8 DEFAULT \'\' COLLATE `utf8_unicode_ci`, _st_representation_check TINYINT(1) DEFAULT 0, _st_intern_id CHAR(35) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci` COMMENT \'manuelle Eingangsnummer\', _st_vote_pla CHAR(16) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _st_vote_stk CHAR(16) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _st_submit_type VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, manual TINYINT(1) DEFAULT 0 NOT NULL, number_of_anonym_votes INT UNSIGNED DEFAULT 0 NOT NULL, cluster_statement TINYINT(1) DEFAULT 0 NOT NULL, name VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, drafts_info_json MEDIUMTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, segment_statement_fk CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, entity_type VARCHAR(255) CHARACTER SET utf8 DEFAULT \'Statement\' NOT NULL COLLATE `utf8_unicode_ci`, segmentation_pi_retries SMALLINT DEFAULT 0 NOT NULL, pi_segments_proposal_resource_url VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, replied TINYINT(1) DEFAULT 0 NOT NULL, order_in_procedure INT DEFAULT NULL, anonymous TINYINT(1) DEFAULT 0 NOT NULL, INDEX IDX_8D47F06B993552ED (placeholder_statement_id), INDEX IDX_8D47F06BE411180F (_st_o_id), INDEX IDX_8D47F06BC96FC090 (bthg_kompass_answer_id), INDEX IDX_8D47F06B86245470 (_o_id), INDEX IDX_8D47F06B909515D4 (_st_paragraph_id), INDEX IDX_8D47F06B9785C7DD (_st_element_id), INDEX IDX_8D47F06B7C9DFC0C (assignee), INDEX IDX_8D47F06BEC6B5FC6 (_st_p_id), INDEX IDX_8D47F06B7F901FFA (moved_statement_id), INDEX IDX_8D47F06BB980E38B (_u_id), INDEX IDX_8D47F06BDA6A219 (place_id), INDEX IDX_8D47F06B8E5E13B9 (_p_id), INDEX IDX_8D47F06B7D6A862 (_st_document_id), INDEX IDX_8D47F06BC022D95C (_ds_id), UNIQUE INDEX internId_procedure (_st_intern_id, _p_id), INDEX IDX_8D47F06BA005B5F5 (head_statement_id), PRIMARY KEY(_st_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _statement_attribute (_sta_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _sta_st_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _sta_ds_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _sta_type VARCHAR(50) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _sta_value VARCHAR(1024) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_C28E323D1680EA67 (_sta_ds_id), INDEX IDX_C28E323D5997994C (_sta_st_id), PRIMARY KEY(_sta_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _statement_county (_st_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _c_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_C812F31F8F35AA77 (_st_id), INDEX IDX_C812F31FCCF2EBC8 (_c_id), PRIMARY KEY(_st_id, _c_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _statement_fragment_county (sf_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _c_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_8A990654FF1DD9FF (sf_id), INDEX IDX_8A990654CCF2EBC8 (_c_id), PRIMARY KEY(sf_id, _c_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _statement_fragment_municipality (sf_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _m_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_719C4CA3FF1DD9FF (sf_id), INDEX IDX_719C4CA32C2D9CFB (_m_id), PRIMARY KEY(sf_id, _m_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _statement_fragment_priority_area (sf_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _pa_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_6531A333FF1DD9FF (sf_id), INDEX IDX_6531A333AF52770A (_pa_id), PRIMARY KEY(sf_id, _pa_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _statement_meta (_stm_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _st_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _stm_author_name VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _stm_submit_name VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _stm_submit_u_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _stm_orga_name VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _stm_orga_department_name VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _stm_orga_case_worker_name VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _stm_orga_street VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _stm_orga_postalcode VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _stm_orga_city VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _stm_orga_email VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _stm_authored_date DATETIME DEFAULT NULL COMMENT \'T441: Store the date on which manual statements have been (allegedly) submitted\', _stm_submit_o_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _stm_misc_data LONGTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci` COMMENT \'(DC2Type:array)\', house_number VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, author_feedback TINYINT(1) NOT NULL, UNIQUE INDEX UNIQ_19AB49968F35AA77 (_st_id), PRIMARY KEY(_stm_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _statement_municipality (_st_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _m_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_DD9101B58F35AA77 (_st_id), INDEX IDX_DD9101B52C2D9CFB (_m_id), PRIMARY KEY(_st_id, _m_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _statement_priority_area (_st_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _pa_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_91491B2F8F35AA77 (_st_id), INDEX IDX_91491B2FAF52770A (_pa_id), PRIMARY KEY(_st_id, _pa_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _statement_tag (_st_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _t_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_3B308BF48F35AA77 (_st_id), INDEX IDX_3B308BF413C84EE (_t_id), PRIMARY KEY(_st_id, _t_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _statement_version_fields (_sv_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _st_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _u_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _u_name VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _s_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _sv_name CHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _sv_type CHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _sv_value TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _sv_created_date DATETIME NOT NULL, INDEX _st_id (_st_id), PRIMARY KEY(_sv_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _statement_votes (_stv_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _st_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _u_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _u_firstname VARCHAR(128) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, _u_lastname VARCHAR(128) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, _st_v_active TINYINT(1) DEFAULT 0 NOT NULL, _st_v_deleted TINYINT(1) DEFAULT 0 NOT NULL, _st_v_created_date DATETIME NOT NULL, _st_v_modified_date DATETIME NOT NULL, _st_v_deleted_date DATETIME NOT NULL, manual TINYINT(1) DEFAULT 0 NOT NULL, created_by_citizen TINYINT(1) DEFAULT 0 NOT NULL, organisation_name VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, department_name VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, user_name VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, user_mail VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, user_postcode VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, user_city VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_1E9AD1C0B980E38B (_u_id), INDEX IDX_1E9AD1C08F35AA77 (_st_id), PRIMARY KEY(_stv_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _tag (_t_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _tt_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _pt_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _t_title VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _t_create_date DATETIME NOT NULL, INDEX IDX_4EE2AE79C895D0A7 (_pt_id), INDEX IDX_4EE2AE793D157667 (_tt_id), UNIQUE INDEX tag_unique_title (_tt_id, _t_title), PRIMARY KEY(_t_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _tag_topic (_tt_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _p_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _tt_title VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _tt_create_date DATETIME NOT NULL, INDEX IDX_F23066008E5E13B9 (_p_id), UNIQUE INDEX tag_topic_unique_title (_p_id, _tt_title), PRIMARY KEY(_tt_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _user (_u_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, twin_user_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _u_dm_id INT DEFAULT NULL, _u_gender CHAR(6) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _u_title VARCHAR(45) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _u_firstname VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _u_lastname VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _u_email VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _u_login CHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _u_password CHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _u_language CHAR(6) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _u_created_date DATETIME NOT NULL, _u_modified_date DATETIME NOT NULL, _u_deleted TINYINT(1) DEFAULT 0 NOT NULL, _u_gw_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _u_salt CHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, flags LONGTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci` COMMENT \'(DC2Type:array)\', alternative_login_password CHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, last_login DATETIME DEFAULT NULL, UNIQUE INDEX _u_login (_u_login), UNIQUE INDEX _u_gw_id (_u_gw_id), UNIQUE INDEX UNIQ_D0B6A65284D7A399 (twin_user_id), PRIMARY KEY(_u_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE _user_address_doctrine (_u_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _a_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_5B4D8468B980E38B (_u_id), INDEX IDX_5B4D846866FB2343 (_a_id), PRIMARY KEY(_u_id, _a_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE address_book_entry (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, organisation_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, name VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, email_address VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, created_date DATETIME NOT NULL, modified_date DATETIME NOT NULL, INDEX IDX_5D7E00979E6B1585 (organisation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE annotated_statement_pdf (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _procedure CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _statement CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, file CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci` COMMENT \'This id is used in filestrings to reference to the file entity\', reviewer_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, statement_text MEDIUMTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, status LONGTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, pi_resource_url VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, submitter_json VARCHAR(1024) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, created DATETIME NOT NULL, reviewed_date DATETIME DEFAULT NULL, box_recognition_pi_retries SMALLINT DEFAULT 0 NOT NULL, text_recognition_pi_retries SMALLINT DEFAULT 0 NOT NULL, INDEX IDX_FC86557570574616 (reviewer_id), UNIQUE INDEX UNIQ_FC8655758D47F06B (_statement), UNIQUE INDEX UNIQ_FC8655758C9F3610 (file), INDEX IDX_FC865575D1A01D02 (_procedure), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE annotated_statement_pdf_page (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, annotated_statement_pdf CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, url VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, width INT UNSIGNED NOT NULL, height INT UNSIGNED NOT NULL, geo_json MEDIUMTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, confirmed TINYINT(1) DEFAULT 0 NOT NULL, page_order INT UNSIGNED DEFAULT 0 NOT NULL, INDEX IDX_D97F9F9766C927ED (annotated_statement_pdf), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE boilerplate_group (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, procedure_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, title VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, create_date DATETIME NOT NULL, INDEX IDX_491B305F1624BCD2 (procedure_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE branding (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, logo CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci` COMMENT \'This id is used in filestrings to reference to the file entity\', cssvars LONGTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, UNIQUE INDEX UNIQ_B25B8E57E48E9A13 (logo), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE bthg_kompass_answer (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, title VARCHAR(255) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci` COMMENT \'Title of the Paragraph which includes the answer of the Statement.\', breadcrumb_trail VARCHAR(255) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci` COMMENT \'Title, of the Paragraph and all parent paragraphs, which includes the answer of the Statement.\', url VARCHAR(255) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci` COMMENT \'Url which is filled automatically, but lead to external source.\', creation_date DATETIME DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE consultation_token (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, statement_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, original_statement_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, sent_email_id INT DEFAULT NULL, manually_created TINYINT(1) DEFAULT 0 NOT NULL, note VARCHAR(1024) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, token VARCHAR(8) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, creation_date DATETIME NOT NULL, modification_date DATETIME NOT NULL, UNIQUE INDEX UNIQ_24AD43152540450B (sent_email_id), UNIQUE INDEX unique_consultation_token (token), UNIQUE INDEX UNIQ_24AD43155FD0706D (original_statement_id), UNIQUE INDEX UNIQ_24AD4315849CB65B (statement_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE customer (_c_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _procedure CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, branding_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _c_name VARCHAR(50) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _c_subdomain VARCHAR(50) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, imprint TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, data_protection MEDIUMTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, terms_of_use LONGTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, map_attribution TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, base_layer_url VARCHAR(4096) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, base_layer_layers VARCHAR(4096) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, xplanning TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, accessibility_explanation LONGTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, sign_language_overview_description LONGTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, simple_language_overview_description LONGTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, UNIQUE INDEX UNIQ_81398E09D1A01D02 (_procedure), UNIQUE INDEX _c_name (_c_name), UNIQUE INDEX UNIQ_81398E09560BC00E (branding_id), PRIMARY KEY(_c_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE customer_county (cc_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, customer_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, county_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, e_mail_address TINYTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_E927237485E73F45 (county_id), INDEX IDX_E92723749395C3F3 (customer_id), UNIQUE INDEX customer_county_unique_context (customer_id, county_id), PRIMARY KEY(cc_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE draft_statement_file (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, draft_statement_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, file_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, create_date DATETIME NOT NULL, INDEX IDX_204C2E73EE6DAD86 (draft_statement_id), UNIQUE INDEX UNIQ_204C2E7393CB796C (file_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE email_address (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, full_address VARCHAR(254) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, UNIQUE INDEX UNIQ_B08E074E5E6736ED (full_address), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE entity_content_change (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, created DATETIME NOT NULL, modified DATETIME NOT NULL, user_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, entity_type VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, entity_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, entity_field VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, pre_update MEDIUMTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, post_update MEDIUMTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, content_change MEDIUMTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, user_name VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE entity_sync_link (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, class VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, source_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, target_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, UNIQUE INDEX unique_target (class, target_id), UNIQUE INDEX unique_source (class, source_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE export_fields_configuration (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, procedure_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, creation_date DATETIME NOT NULL, modification_date DATETIME NOT NULL, creation_date_exportable TINYINT(1) DEFAULT 1 NOT NULL, procedure_name_exportable TINYINT(1) DEFAULT 1 NOT NULL, procedure_phase_exportable TINYINT(1) DEFAULT 1 NOT NULL, institution_exportable TINYINT(1) DEFAULT 1 NOT NULL, public_participation_exportable TINYINT(1) DEFAULT 1 NOT NULL, orga_name_exportable TINYINT(1) DEFAULT 1 NOT NULL, department_name_exportable TINYINT(1) DEFAULT 1 NOT NULL, show_in_public_area_exportable TINYINT(1) DEFAULT 1 NOT NULL, document_exportable TINYINT(1) DEFAULT 1 NOT NULL, paragraph_exportable TINYINT(1) DEFAULT 1 NOT NULL, attachments_exportable TINYINT(1) DEFAULT 1 NOT NULL, priority_exportable TINYINT(1) DEFAULT 1 NOT NULL, email_exportable TINYINT(1) DEFAULT 1 NOT NULL, phone_number_exportable TINYINT(1) DEFAULT 1 NOT NULL, street_exportable TINYINT(1) DEFAULT 1 NOT NULL, street_number_exportable TINYINT(1) DEFAULT 1 NOT NULL, postal_code_exportable TINYINT(1) DEFAULT 1 NOT NULL, city_exportable TINYINT(1) DEFAULT 1 NOT NULL, institution_or_citizen_exportable TINYINT(1) DEFAULT 1 NOT NULL, id_exportable TINYINT(1) DEFAULT 1 NOT NULL, user_state_exportable TINYINT(1) DEFAULT 1 NOT NULL, user_group_exportable TINYINT(1) DEFAULT 1 NOT NULL, user_organisation_exportable TINYINT(1) DEFAULT 1 NOT NULL, user_position_exportable TINYINT(1) DEFAULT 1 NOT NULL, submitter_name_exportable TINYINT(1) DEFAULT 1 NOT NULL, files_exportable TINYINT(1) DEFAULT 1 NOT NULL, votes_num_exportable TINYINT(1) DEFAULT 1 NOT NULL, statement_name_exportable TINYINT(1) DEFAULT 1 NOT NULL, INDEX IDX_A2A26A421624BCD2 (procedure_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE faq (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, faq_category_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, title VARCHAR(255) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, text LONGTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, enabled TINYINT(1) DEFAULT 0 NOT NULL, create_date DATETIME NOT NULL, modify_date DATETIME NOT NULL, INDEX IDX_E8FF75CCF689B0DB (faq_category_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE faq_category (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, customer_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, title VARCHAR(255) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, create_date DATETIME NOT NULL, modify_date DATETIME NOT NULL, type VARCHAR(50) CHARACTER SET utf8 DEFAULT \'custom_category\' NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_FAEEE0D69395C3F3 (customer_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE faq_role (faq_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, role_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_5277E20C81BEC8C2 (faq_id), INDEX IDX_5277E20CD60322AC (role_id), PRIMARY KEY(faq_id, role_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE file_container (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, file_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci` COMMENT \'This id is used in filestrings to reference to the file entity\', entity_class CHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, orderNum SMALLINT UNSIGNED NOT NULL, file_string VARCHAR(2048) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, entity_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, entity_field VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, create_date DATETIME NOT NULL, modify_date DATETIME NOT NULL, public_allowed TINYINT(1) DEFAULT 1 NOT NULL COMMENT \'Is the file visible in this statement for other users than Fachplaner\', INDEX IDX_49AD536D81257D5D41BF2C66 (entity_id, entity_class), INDEX IDX_49AD536D93CB796C (file_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE gdpr_consent (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, statement_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, consentee_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, consent_received TINYINT(1) DEFAULT 0 NOT NULL, consent_received_date DATETIME DEFAULT NULL, consent_revoked TINYINT(1) DEFAULT 0 NOT NULL, consent_revoked_date DATETIME DEFAULT NULL, INDEX IDX_9D76FE9DF62EE424 (consentee_id), UNIQUE INDEX UNIQ_9D76FE9D849CB65B (statement_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE gdpr_consent_revoke_token (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, email_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, token CHAR(12) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_5C0C4C86A832C1C9 (email_id), UNIQUE INDEX UNIQ_5C0C4C865F37A13B (token), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE gdpr_consent_revoke_token_statements (token_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, statement_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, UNIQUE INDEX UNIQ_3E8C3D44849CB65B (statement_id), INDEX IDX_3E8C3D4441DEE7B9 (token_id), PRIMARY KEY(token_id, statement_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE geodb_short_table (id VARCHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, loc_id INT NOT NULL, postcode VARCHAR(256) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, city VARCHAR(256) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, municipal_code VARCHAR(10) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, state VARCHAR(256) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, lat DOUBLE PRECISION NOT NULL, lon DOUBLE PRECISION NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE gis_layer_category (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, procedure_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, parent_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, name VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, create_date DATETIME NOT NULL, modify_date DATETIME NOT NULL, tree_order INT DEFAULT 0 NOT NULL, visible TINYINT(1) DEFAULT 1 NOT NULL, layer_with_children_hidden TINYINT(1) DEFAULT 0 NOT NULL COMMENT \'Hides all children for the category and displays the category as layer instead.\', INDEX IDX_8E067F84727ACA70 (parent_id), INDEX IDX_8E067F841624BCD2 (procedure_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE hashed_query (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, procedure_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, hash VARCHAR(12) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, created DATETIME NOT NULL, modified DATETIME NOT NULL, stored_query LONGTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci` COMMENT \'(DC2Type:dplan.stored_query)\', INDEX IDX_1C0A40E1624BCD2 (procedure_id), UNIQUE INDEX UNIQ_1C0A40ED1B862B8 (hash), INDEX hash_idx (hash), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE institution_mail (_tm_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _o_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _p_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _p_phase VARCHAR(50) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _tm_created_date DATETIME NOT NULL, INDEX IDX_5982732086245470 (_o_id), INDEX IDX_598273208E5E13B9 (_p_id), PRIMARY KEY(_tm_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE location (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, postcode VARCHAR(20) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, name VARCHAR(256) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, municipal_code VARCHAR(9) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, ars VARCHAR(12) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, lat DOUBLE PRECISION DEFAULT NULL, lon DOUBLE PRECISION DEFAULT NULL, INDEX municipalCode (municipal_code), INDEX postcode (postcode), INDEX ars (ars), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE maillane_allowed_sender_email_address (maillane_connection_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, email_address_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_776BECC159045DAA (email_address_id), INDEX IDX_776BECC1AC0C069A (maillane_connection_id), PRIMARY KEY(maillane_connection_id, email_address_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE maillane_connection (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, maillane_account_id VARCHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, recipient_email_address VARCHAR(254) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, UNIQUE INDEX UNIQ_71C04D1DE2466BE6 (recipient_email_address), UNIQUE INDEX UNIQ_71C04D1DC35C61C1 (maillane_account_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE notification_receiver (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, procedure_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, `label` VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, email VARCHAR(128) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_68A8B4331624BCD2 (procedure_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE orga_slug (o_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, s_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_5857EC6DB01246B (o_id), INDEX IDX_5857EC6C1CECC4C (s_id), PRIMARY KEY(o_id, s_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE original_statement_anonymization (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, statement_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, created_by_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, created DATETIME NOT NULL, attachments_deleted TINYINT(1) DEFAULT 0 NOT NULL, text_version_history_deleted TINYINT(1) DEFAULT 0 NOT NULL, text_passages_anonymized TINYINT(1) DEFAULT 0 NOT NULL, submitter_and_author_meta_data_anonymized TINYINT(1) DEFAULT 0 NOT NULL, INDEX IDX_CEA667F2B03A8386 (created_by_id), INDEX IDX_CEA667F2849CB65B (statement_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE plugin_flood (fid CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, event VARCHAR(128) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, identifier VARCHAR(256) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, created DATETIME NOT NULL, expires DATETIME NOT NULL, PRIMARY KEY(fid)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE predefined_texts_categories (_ptc_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _pt_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_843996AFFF124921 (_ptc_id), INDEX IDX_843996AFC895D0A7 (_pt_id), PRIMARY KEY(_ptc_id, _pt_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE procedure_agency_extra_email_address (procedure_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, email_address_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_44C1F5A1624BCD2 (procedure_id), INDEX IDX_44C1F5A59045DAA (email_address_id), PRIMARY KEY(procedure_id, email_address_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE procedure_behavior_definition (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, procedure_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, procedure_type_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, creation_date DATETIME NOT NULL, modification_date DATETIME NOT NULL, has_priority_area TINYINT(1) DEFAULT 0 NOT NULL, allowed_to_enable_map TINYINT(1) DEFAULT 1 NOT NULL, participation_guest_only TINYINT(1) DEFAULT 0 NOT NULL, UNIQUE INDEX UNIQ_BB1C28D39404667A (procedure_type_id), UNIQUE INDEX UNIQ_BB1C28D31624BCD2 (procedure_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE procedure_category (procedure_category_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, procedure_category_name VARCHAR(4096) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, procedure_category_slug VARCHAR(4096) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, PRIMARY KEY(procedure_category_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE procedure_couple_token (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, source_procedure_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, target_procedure_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, token CHAR(12) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, UNIQUE INDEX UNIQ_7CDE194DC0CE606A (source_procedure_id), UNIQUE INDEX UNIQ_7CDE194D5F37A13B (token), UNIQUE INDEX UNIQ_7CDE194DF6766F83 (target_procedure_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE procedure_orga_datainput (_p_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _o_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_B64BA7828E5E13B9 (_p_id), INDEX IDX_B64BA78286245470 (_o_id), PRIMARY KEY(_p_id, _o_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE procedure_person (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, procedure_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, full_name LONGTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, street_name LONGTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, street_number LONGTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, city LONGTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, postal_code LONGTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, email_address LONGTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_A23602471624BCD2 (procedure_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE procedure_planningoffices (_p_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _o_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_B15193C98E5E13B9 (_p_id), INDEX IDX_B15193C986245470 (_o_id), PRIMARY KEY(_p_id, _o_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE procedure_procedure_category_doctrine (procedure_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, procedure_category_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_723A420C1624BCD2 (procedure_id), INDEX IDX_723A420C39886249 (procedure_category_id), PRIMARY KEY(procedure_id, procedure_category_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE procedure_proposal (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, user CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, name VARCHAR(4096) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, description LONGTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, additional_explanation LONGTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, coordinate VARCHAR(2048) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, created_date DATETIME NOT NULL, modified_date DATETIME NOT NULL, status VARCHAR(255) CHARACTER SET utf8 DEFAULT \'new\' NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_666286448D93D649 (user), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE procedure_settings_allowed_segment_procedures (procedure_settings__ps_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, procedure__p_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_1B094DEF87CD6105 (procedure_settings__ps_id), INDEX IDX_1B094DEF6D25ACF3 (procedure__p_id), PRIMARY KEY(procedure_settings__ps_id, procedure__p_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE procedure_slug (p_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, s_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_8029C3EAD37B63A2 (p_id), INDEX IDX_8029C3EAC1CECC4C (s_id), PRIMARY KEY(p_id, s_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE procedure_type (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, statement_form_definition_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, procedure_ui_definition_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, procedure_behavior_definition_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, name CHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, description LONGTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, creation_date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, modification_date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, UNIQUE INDEX UNIQ_946A0FA1A0D2BC07 (procedure_behavior_definition_id), UNIQUE INDEX UNIQ_946A0FA1E68E097B (procedure_ui_definition_id), UNIQUE INDEX UNIQ_946A0FA17BF2A129 (statement_form_definition_id), UNIQUE INDEX UNIQ_946A0FA15E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE procedure_ui_definition (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, procedure_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, procedure_type_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, map_hint_default LONGTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, statement_form_hint_statement LONGTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, statement_form_hint_personal_data LONGTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, statement_form_hint_recheck LONGTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, creation_date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, modification_date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, statement_public_submit_confirmation_text VARCHAR(500) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, UNIQUE INDEX UNIQ_1D5910A19404667A (procedure_type_id), UNIQUE INDEX UNIQ_1D5910A11624BCD2 (procedure_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE procedure_user (procedure_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, user_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_95278EC11624BCD2 (procedure_id), INDEX IDX_95278EC1A76ED395 (user_id), PRIMARY KEY(procedure_id, user_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE procedureproposal_file_doctrine (file CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci` COMMENT \'This id is used in filestrings to reference to the file entity\', procedureProposal CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_BF2652876393FDB0 (procedureProposal), INDEX IDX_BF2652878C9F3610 (file), PRIMARY KEY(procedureProposal, file)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE relation_customer_orga_orga_type (_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _o_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _c_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _ot_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, status VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_315820482A25D0F4 (_ot_id), INDEX IDX_31582048CCF2EBC8 (_c_id), UNIQUE INDEX o_c_ot_unique (_o_id, _c_id, _ot_id), INDEX IDX_3158204886245470 (_o_id), PRIMARY KEY(_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE relation_role_user_customer (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, user CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, role CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, customer CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_E3B0097481398E09 (customer), INDEX IDX_E3B0097457698A6A (role), INDEX IDX_E3B009748D93D649 (user), UNIQUE INDEX role_customer_user_unique_constraint (role, customer, user), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE search_index_task (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, entity VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, entity_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, created DATETIME NOT NULL, processing TINYINT(1) NOT NULL, user_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_214833BCB23DB7B8 (created), INDEX IDX_214833BCE284468 (entity), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE segment_comment (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, segment_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, submitter_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, place_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, creation_date DATETIME NOT NULL, text LONGTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_CB0D138EDA6A219 (place_id), INDEX IDX_CB0D138EDB296AAD (segment_id), INDEX IDX_CB0D138E919E5513 (submitter_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE sessions (sess_id VARBINARY(128) NOT NULL, sess_data MEDIUMBLOB NOT NULL, sess_lifetime INT UNSIGNED NOT NULL, sess_time INT UNSIGNED NOT NULL, INDEX sessions_sess_lifetime_idx (sess_lifetime), PRIMARY KEY(sess_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_bin` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE sign_language_overview_video (customer_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, video_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, UNIQUE INDEX UNIQ_96771E2629C1004E (video_id), INDEX IDX_96771E269395C3F3 (customer_id), PRIMARY KEY(customer_id, video_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE similar_statement_submitter (statement_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, submitter_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_AD3EF3B3849CB65B (statement_id), INDEX IDX_AD3EF3B3919E5513 (submitter_id), PRIMARY KEY(statement_id, submitter_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE slug (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, name VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, UNIQUE INDEX slug_unique (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE statement_attachment (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, file_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci` COMMENT \'This id is used in filestrings to reference to the file entity\', statement_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, type VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_B6A1C4D693CB796C (file_id), INDEX IDX_B6A1C4D6849CB65B (statement_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE statement_field_definition (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, statement_form_definition_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, name VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, enabled TINYINT(1) NOT NULL, required TINYINT(1) DEFAULT 1 NOT NULL, creation_date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, modification_date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, order_number SMALLINT DEFAULT 0 NOT NULL, UNIQUE INDEX UNIQ_B5E8B93E7BF2A129551F0F81 (statement_form_definition_id, order_number), INDEX IDX_B5E8B93E7BF2A129 (statement_form_definition_id), UNIQUE INDEX UNIQ_B5E8B93E7BF2A1295E237E06 (statement_form_definition_id, name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE statement_form_definition (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, procedure_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, procedure_type_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, creation_date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, modification_date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, UNIQUE INDEX UNIQ_511ECAA99404667A (procedure_type_id), UNIQUE INDEX UNIQ_511ECAA91624BCD2 (procedure_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE statement_fragment (sf_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, statement_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _p_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, _d_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, assignee CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, modified_by_u_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, modified_by_d_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, last_claimed CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, element_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, paragraph_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _archived_d_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, document_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, display_id INT UNSIGNED NOT NULL, sf_text MEDIUMTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, created_date DATETIME NOT NULL, modified_date DATETIME NOT NULL, sf_consideration_advice LONGTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, sf_vote LONGTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, sf_vote_advice LONGTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, sf_consideration LONGTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, sf_archived_orga_name LONGTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, sf_archived_department_name LONGTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, sf_archived_vote_user_name LONGTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, status VARCHAR(255) CHARACTER SET utf8 DEFAULT \'fragment.status.new\' COLLATE `utf8_unicode_ci`, assigned_to_fb_date DATETIME DEFAULT NULL, sort_index INT DEFAULT -1 NOT NULL, INDEX IDX_47745080CF065881 (modified_by_u_id), INDEX IDX_47745080CEAEFECB (last_claimed), INDEX IDX_477450808B50597F (paragraph_id), INDEX IDX_477450808E5E13B9 (_p_id), INDEX IDX_47745080F7250B2B (_archived_d_id), INDEX IDX_4774508027A3687B (modified_by_d_id), INDEX IDX_477450801F1F2A24 (element_id), UNIQUE INDEX statement_fragment_unique_sort_index (statement_id, sort_index), INDEX IDX_47745080C33F7837 (document_id), INDEX IDX_47745080849CB65B (statement_id), INDEX IDX_477450805125D371 (_d_id), INDEX IDX_477450807C9DFC0C (assignee), PRIMARY KEY(sf_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE statement_fragment_tag (sf_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, tag_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_6CEC5E33FF1DD9FF (sf_id), INDEX IDX_6CEC5E33BAD26311 (tag_id), PRIMARY KEY(sf_id, tag_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE statement_fragment_version (sfv_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, statement_fragment_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _p_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, sfv_modified_by_u_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, sfv_modified_by_d_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, paragraph_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, document_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, display_id INT UNSIGNED NOT NULL, sfv_text LONGTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, sfv_vote_advice LONGTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, sfv_vote LONGTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, created_date DATETIME NOT NULL, sfv_consideration_advice LONGTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, sfv_consideration LONGTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, sfv_archived_orga_name LONGTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, sfv_archived_department_name LONGTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, sfv_archived_vote_user_name LONGTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, sfv_statement_fragment_version_tag LONGTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, sfv_department_name LONGTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, sfv_orga_name LONGTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, sfv_county_name LONGTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, sfv_priority_area_name LONGTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, sfv_municipality_name LONGTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, sfv_element_title TEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, sfv_element_category TEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_C1DA955D1977D8EF (sfv_modified_by_d_id), INDEX IDX_C1DA955DC33F7837 (document_id), INDEX IDX_C1DA955D8E5E13B9 (_p_id), INDEX IDX_C1DA955D8B50597F (paragraph_id), INDEX IDX_C1DA955D6AA73686 (statement_fragment_id), INDEX IDX_C1DA955DF1D2E815 (sfv_modified_by_u_id), PRIMARY KEY(sfv_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE statement_import_email (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, procedure_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, user_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, creation_date DATETIME NOT NULL, subject LONGTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, body LONGTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, raw_email_text LONGTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, html_text_content LONGTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, from_address VARCHAR(512) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_B9926577A76ED395 (user_id), INDEX IDX_B99265771624BCD2 (procedure_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE statement_import_email_attachments (statement_import_email_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, file_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci` COMMENT \'This id is used in filestrings to reference to the file entity\', UNIQUE INDEX UNIQ_49B1343B93CB796C (file_id), INDEX IDX_49B1343B3174D6D6 (statement_import_email_id), PRIMARY KEY(statement_import_email_id, file_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE statement_import_email_original_statements (statement_import_email_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, original_statement_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, UNIQUE INDEX UNIQ_AF3741B25FD0706D (original_statement_id), INDEX IDX_AF3741B23174D6D6 (statement_import_email_id), PRIMARY KEY(statement_import_email_id, original_statement_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE statement_import_email_processed_attachments (statement_import_email_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, annotated_statement_pdf_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, UNIQUE INDEX UNIQ_8D51E2FFFF064ED4 (annotated_statement_pdf_id), INDEX IDX_8D51E2FF3174D6D6 (statement_import_email_id), PRIMARY KEY(statement_import_email_id, annotated_statement_pdf_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE statement_likes (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, st_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _u_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, _st_v_created_date DATETIME NOT NULL, INDEX IDX_7B00029EB980E38B (_u_id), INDEX IDX_7B00029E50D46EB (st_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE survey (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, p_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, title TINYTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, description TEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, start_date DATE NOT NULL, end_date DATE NOT NULL, status VARCHAR(50) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_AD5F9BFCD37B63A2 (p_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE survey_vote (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, survey_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, user_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, is_agreed TINYINT(1) NOT NULL, text TEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, created_date DATE NOT NULL, text_review VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_9CF985AFA76ED395 (user_id), INDEX IDX_9CF985AFB3FE509D (survey_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE user_filter_set (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, user_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, filter_set_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, procedure_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, name VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_A6A762E21624BCD2 (procedure_id), INDEX IDX_A6A762E2A76ED395 (user_id), INDEX IDX_A6A762E23DD05366 (filter_set_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE video (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, uploader_id CHAR(36) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, customer_context_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, file_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci` COMMENT \'This id is used in filestrings to reference to the file entity\', title VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, description LONGTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, creation_date DATETIME NOT NULL, modification_date DATETIME NOT NULL, INDEX IDX_7CC7DA2C16678C77 (uploader_id), UNIQUE INDEX UNIQ_7CC7DA2C93CB796C (file_id), INDEX IDX_7CC7DA2C96C9C54D (customer_context_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE workflow_place (id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, procedure_id CHAR(36) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, name VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, sort_index INT UNSIGNED DEFAULT 0 NOT NULL, description VARCHAR(255) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_C55325F91624BCD2 (procedure_id), UNIQUE INDEX unique_workflow_place_name (name, procedure_id), UNIQUE INDEX unique_workflow_place_sort_index (sort_index, procedure_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _address');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _autosave');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _category');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _county');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _department');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _department_addresses_doctrine');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _department_users_doctrine');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _draft_statement');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _draft_statement_versions');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _elements');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _elements_orga_doctrine');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _files');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _forum_entries');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _forum_entry_files');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _forum_threads');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _gis');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _mail_attachment');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _mail_send');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _mail_templates');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _manual_list_sort');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _master_toeb');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _master_toeb_versions');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _municipality');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _news');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _news_roles');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _orga');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _orga_addresses_doctrine');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _orga_departments_doctrine');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _orga_type');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _orga_users_doctrine');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _para_doc');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _para_doc_version');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _platform_content');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _platform_content_categories');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _platform_content_roles');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _platform_context_sensitive_help');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _predefined_texts');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _predefined_texts_category');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _priority_area');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _procedure');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _procedure_orga_doctrine');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _procedure_settings');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _procedure_subscriptions');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _progression_releases');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _progression_userstories');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _progression_userstory_votes');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _report_entries');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _role');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _settings');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _single_doc');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _single_doc_version');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _statement');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _statement_attribute');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _statement_county');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _statement_fragment_county');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _statement_fragment_municipality');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _statement_fragment_priority_area');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _statement_meta');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _statement_municipality');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _statement_priority_area');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _statement_tag');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _statement_version_fields');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _statement_votes');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _tag');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _tag_topic');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _user');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE _user_address_doctrine');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE address_book_entry');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE annotated_statement_pdf');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE annotated_statement_pdf_page');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE boilerplate_group');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE branding');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE bthg_kompass_answer');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE consultation_token');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE customer');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE customer_county');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE draft_statement_file');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE email_address');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE entity_content_change');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE entity_sync_link');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE export_fields_configuration');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE faq');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE faq_category');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE faq_role');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE file_container');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE gdpr_consent');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE gdpr_consent_revoke_token');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE gdpr_consent_revoke_token_statements');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE geodb_short_table');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE gis_layer_category');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE hashed_query');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE institution_mail');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE location');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE maillane_allowed_sender_email_address');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE maillane_connection');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE notification_receiver');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE orga_slug');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE original_statement_anonymization');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE plugin_flood');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE predefined_texts_categories');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE procedure_agency_extra_email_address');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE procedure_behavior_definition');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE procedure_category');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE procedure_couple_token');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE procedure_orga_datainput');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE procedure_person');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE procedure_planningoffices');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE procedure_procedure_category_doctrine');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE procedure_proposal');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE procedure_settings_allowed_segment_procedures');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE procedure_slug');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE procedure_type');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE procedure_ui_definition');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE procedure_user');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE procedureproposal_file_doctrine');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE relation_customer_orga_orga_type');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE relation_role_user_customer');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE search_index_task');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE segment_comment');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE sessions');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE sign_language_overview_video');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE similar_statement_submitter');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE slug');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE statement_attachment');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE statement_field_definition');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE statement_form_definition');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE statement_fragment');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE statement_fragment_tag');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE statement_fragment_version');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE statement_import_email');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE statement_import_email_attachments');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE statement_import_email_original_statements');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE statement_import_email_processed_attachments');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE statement_likes');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE survey');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE survey_vote');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE user_filter_set');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE video');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE workflow_place');
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
}
