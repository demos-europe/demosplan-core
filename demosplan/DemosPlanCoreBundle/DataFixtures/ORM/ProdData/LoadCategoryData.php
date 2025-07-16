<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\ProdData;

use demosplan\DemosPlanCoreBundle\Entity\Category;
use Doctrine\Persistence\ObjectManager;

class LoadCategoryData extends ProdFixture
{
    public function load(ObjectManager $manager): void
    {
        $categories = [
            [ // row #0
                '_c_id'            => '14f12642-8681-11e8-810c-6798c205d312',
                '_c_name'          => 'custom_category',
                '_c_title'         => 'Test_13_07_18',
                '_c_description'   => null,
                '_c_picture'       => '',
                '_c_picture_title' => '',
                '_c_enabled'       => 1,
                '_c_deleted'       => 0,
                '_c_create_date'   => '2018-07-13 11:42:40',
                '_c_modify_date'   => '2018-07-13 11:42:40',
                '_c_delete_date'   => '2018-07-13 11:42:40',
                'custom'           => 1,
            ],
            [ // row #1
                '_c_id'            => '25846712-803f-11e8-bb4d-782bcb0d78b1',
                '_c_name'          => 'custom_category',
                '_c_title'         => 'Neue Kategorie4',
                '_c_description'   => null,
                '_c_picture'       => '',
                '_c_picture_title' => '',
                '_c_enabled'       => 1,
                '_c_deleted'       => 0,
                '_c_create_date'   => '2018-07-05 12:35:33',
                '_c_modify_date'   => '2018-07-05 13:20:03',
                '_c_delete_date'   => '2018-07-05 12:35:33',
                'custom'           => 1,
            ],
            [ // row #2
                '_c_id'            => '3ec12ceb-e671-11e3-930e-005056ae0004',
                '_c_name'          => 'system',
                '_c_title'         => 'Über Bauleitplanung online',
                '_c_description'   => null,
                '_c_picture'       => '',
                '_c_picture_title' => '',
                '_c_enabled'       => 1,
                '_c_deleted'       => 0,
                '_c_create_date'   => '2014-05-28 16:06:20',
                '_c_modify_date'   => '1970-01-01 02:01:01',
                '_c_delete_date'   => '2015-07-21 16:35:58',
                'custom'           => 0,
            ],
            [ // row #3
                '_c_id'            => '61a25c3d-e671-11e3-930e-005056ae0004',
                '_c_name'          => 'bedienung',
                '_c_title'         => 'Bedienung',
                '_c_description'   => null,
                '_c_picture'       => '',
                '_c_picture_title' => '',
                '_c_enabled'       => 1,
                '_c_deleted'       => 0,
                '_c_create_date'   => '2014-05-28 16:07:19',
                '_c_modify_date'   => '1970-01-01 02:01:01',
                '_c_delete_date'   => '1970-01-01 02:01:01',
                'custom'           => 0,
            ],
            [ // row #4
                '_c_id'            => '68289954-8045-11e8-bb4d-782bcb0d78b1',
                '_c_name'          => 'custom_category',
                '_c_title'         => 'Neue Kategorie',
                '_c_description'   => null,
                '_c_picture'       => '',
                '_c_picture_title' => '',
                '_c_enabled'       => 1,
                '_c_deleted'       => 0,
                '_c_create_date'   => '2018-07-05 13:20:22',
                '_c_modify_date'   => '2018-07-05 13:20:22',
                '_c_delete_date'   => '2018-07-05 13:20:22',
                'custom'           => 1,
            ],
            [ // row #6
                '_c_id'            => '910C9F2E-306A-4D6D-B147-E67CEDAE33C6',
                '_c_name'          => 'press',
                '_c_title'         => 'Pressebericht',
                '_c_description'   => null,
                '_c_picture'       => '',
                '_c_picture_title' => '',
                '_c_enabled'       => 1,
                '_c_deleted'       => 0,
                '_c_create_date'   => '2015-11-12 12:38:33',
                '_c_modify_date'   => '1970-01-01 02:01:01',
                '_c_delete_date'   => '1970-01-01 02:01:01',
                'custom'           => 0,
            ],
            [ // row #7
                '_c_id'            => 'a4bc60d8-e670-11e3-930e-005056ae0004',
                '_c_name'          => 'technische_voraussetzung',
                '_c_title'         => 'Technische Voraussetzungen',
                '_c_description'   => null,
                '_c_picture'       => '',
                '_c_picture_title' => '',
                '_c_enabled'       => 1,
                '_c_deleted'       => 0,
                '_c_create_date'   => '2014-05-28 16:02:02',
                '_c_modify_date'   => '1970-01-01 02:01:01',
                '_c_delete_date'   => '2015-07-21 14:28:21',
                'custom'           => 0,
            ],
            [ // row #8
                '_c_id'            => 'BC0CBFAD-F67D-43DA-96FD-FED0566A8CA9',
                '_c_name'          => 'news',
                '_c_title'         => 'News',
                '_c_description'   => null,
                '_c_picture'       => '',
                '_c_picture_title' => '',
                '_c_enabled'       => 1,
                '_c_deleted'       => 0,
                '_c_create_date'   => '2015-11-12 14:23:11',
                '_c_modify_date'   => '1970-01-01 02:01:01',
                '_c_delete_date'   => '1970-01-01 02:01:01',
                'custom'           => 0,
            ],
            [ // row #9
                '_c_id'            => 'dc60a343-9a4a-11e4-9b36-005056ae0004',
                '_c_name'          => 'oeb_bauleitplanung',
                '_c_title'         => 'Was ist Bauleitplanung?',
                '_c_description'   => null,
                '_c_picture'       => '',
                '_c_picture_title' => '',
                '_c_enabled'       => 1,
                '_c_deleted'       => 0,
                '_c_create_date'   => '2015-01-12 12:05:04',
                '_c_modify_date'   => '1970-01-01 02:01:01',
                '_c_delete_date'   => '2015-07-22 09:55:54',
                'custom'           => 0,
            ],
            [ // row #10
                '_c_id'            => 'dc60bf24-9a4a-11e4-9b36-005056ae0004',
                '_c_name'          => 'oeb_bob',
                '_c_title'         => 'So funktioniert Bauleitplanung online',
                '_c_description'   => null,
                '_c_picture'       => '',
                '_c_picture_title' => '',
                '_c_enabled'       => 1,
                '_c_deleted'       => 0,
                '_c_create_date'   => '2015-01-12 12:05:04',
                '_c_modify_date'   => '1970-01-01 02:01:01',
                '_c_delete_date'   => '2015-07-22 10:02:37',
                'custom'           => 0,
            ],
        ];

        foreach ($categories as $categoryDefinition) {
            $category = new Category();
            $category->setName($categoryDefinition['_c_name']);
            $category->setTitle($categoryDefinition['_c_title']);
            $category->setDescription($categoryDefinition['_c_description']);
            $category->setPicture($categoryDefinition['_c_picture']);
            $category->setPicTitle($categoryDefinition['_c_picture_title']);
            $category->setEnabled($categoryDefinition['_c_enabled']);
            $category->setDeleted($categoryDefinition['_c_deleted']);
            $category->setCustom($categoryDefinition['custom']);
            $manager->persist($category);
        }

        $manager->flush();
    }
}
