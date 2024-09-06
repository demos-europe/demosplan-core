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

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Tests\Base\AbstractApiTest;

class DemosPlanExternalLinksTest extends AbstractApiTest
{
    public function testExternalLinks(): void
    {
        $parameterBag = $this->getContainer()->get(ParameterBagInterface::class);
        $options = $parameterBag->add(['externalLinks' => [
            'DiPlan Portal' => 'https://test-portal.demos-europe.eu/',
            'DiPlanCockpit' => 'http://{customer}.cockpit-demos-europe.de/',
        ]]);



        $this->client->request('GET', '/informationen');

        // Validate a successful response and some content
        self::assertResponseIsSuccessful();
        $content = $this->client->getResponse()->getContent();
        //self::assertSelectorTextContains('h1', 'registrieren', $this->client->getResponse()->getContent());
    }

    protected function getServerParameters(): array
    {
        return [];
    }
}
