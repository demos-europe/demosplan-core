<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace backend\integration;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\EventListener\SetHttpTestPermissionsListener;
use demosplan\DemosPlanCoreBundle\Logic\Customer\CustomerDeleter;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use Tests\Base\AbstractApiTest;

class DemosGlobalContentTest extends AbstractApiTest
{
    public function testUserRegister(): void
    {
        $this->enablePermissions(['area_admin_globalnews']);
        $user = $this->loginTestUser();
        $currentUserService = $this->getContainer()->get(CurrentUserService::class);
        $currentUserService->setUser($user);

        $this->client->setServerParameter('TEST_USER', 'TEST_USER');
       // $response = $this->sendRequest('/news/verwalten','GET', $user, null);
        $this->client->request('GET', '/news/verwalten');
        $response = $this->client->getResponse();
        $content = $response->getContent();

        // Step 1: Extract the JSON string using a regular expression
        preg_match('/:init-list="JSON\.parse\(\'(.*?)\'\)"/', $content, $matches);

        if (isset($matches[1])) {
            // Step 2: Remove backslashes
            $jsonString = stripslashes($matches[1]);

            // Step 3: Convert Unicode escape sequences to UTF-8 characters
            $regex = '/u([0-9a-fA-F]{4})/';
            $callback = function ($matches) {
                return mb_convert_encoding(pack('H*', $matches[1]), 'UTF-8', 'UCS-2BE');
            };

            $jsonString = preg_replace_callback($regex, $callback, $jsonString);
            // Step 1: Remove invalid escape sequences
            $jsonString = str_replace(['\ü', '\ä'], ['ü', 'ä'], $jsonString);
            // Step 4: Decode the JSON string
            $jsonData = json_decode($jsonString, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                // Successfully parsed JSON data
                print_r($jsonData);
            } else {
                // Handle JSON parsing error
                echo 'JSON parsing error: ' . json_last_error_msg();
            }
        } else {
            // Handle case where JSON string is not found
            echo 'JSON string not found in the HTML content';
        }

        // Validate a successful response and some content
        self::assertResponseIsSuccessful();
        //self::assertSelectorTextContains('h1', 'registrieren', $this->client->getResponse()->getContent());
    }

    protected function getServerParameters(): array
    {
        return [];
    }
}
