<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\Normalizer;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use demosplan\DemosPlanCoreBundle\Utilities\Json;
use demosplan\plugins\workflow\SegmentsManager\Logic\Segment\SegmentedStatementService;
use Symfony\Component\HttpFoundation\Session\Session;
use Tests\Base\FunctionalTestCase;

class SegmentedStatementServiceTest extends FunctionalTestCase
{
    /**
     * @var SegmentedStatementService
     */
    protected $sut;

    /**
     * @var Session
     */
    protected $mockSession;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::$container->get(SegmentedStatementService::class);

        $this->mockSession = $this->setUpMockSession();
    }

    protected function setUpMockSession(string $userReferenceName = LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY): Session
    {
        $session = parent::setUpMockSession($userReferenceName);
        $permissions['feature_statement_assignment']['enabled'] = false;
        $permissions['feature_statement_cluster']['enabled'] = false;
        $permissions['feature_statement_content_changes_save']['enabled'] = true;
        $session->set('permissions', $permissions);

        return $session;
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Session\Session
     */
    protected function getMockSession()
    {
        return $this->mockSession;
    }

    /**
     * @throws \Exception
     */
    public function testTokenVerification(): void
    {
        self::markSkippedForCIIntervention();

        // poor mans mockup
        $statement = new Statement();
        $statement->setId('77ead7b9-b351-4b6c-87d9-216e0f526cd0');
        // rich mans mockup
        $mock = $this->getMockBuilder(GlobalConfig::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mock
            ->method('getAiServiceSalt')
            ->willReturn('%eNMkOwCWO0yBft#krI!6JWjhQznmih^');
        $this->sut->setGlobalConfig($mock);

        $tokenIsValid = $this->sut->isAiRequestTokenValid($statement, '40b7f387416ec82b9da4270d444cf8adfef124bc1e244670736b7dfe948d6d7b');
        self::assertTrue($tokenIsValid);
    }

    public function testTokenGeneration(): void
    {
        $salt = '%eNMkOwCWO0yBft#krI!6JWjhQznmih^';
        $statementId = '77ead7b9-b351-4b6c-87d9-216e0f526cd0';
        $actualHash = hash('sha256', $salt.$statementId);
        $expectedHash = '40b7f387416ec82b9da4270d444cf8adfef124bc1e244670736b7dfe948d6d7b';
        self::assertSame($expectedHash, $actualHash);
    }

    /**
     * @throws \Exception
     */
    public function testUpdateStatement(): void
    {
        self::markSkippedForCIIntervention();

        $salt = 'abc';
        $mock = $this->getMockBuilder(GlobalConfig::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mock
            ->method('getAiServiceSalt')
            ->willReturn($salt);

        $this->sut->setGlobalConfig($mock);

        $statement = $this->getStatementReference('testStatement');
        $statementId = $statement->getId();
        $statementText = $statement->getText();
        // because the statement ID may change for each test we need to manually generate the token
        $sha256 = hash('sha256', $salt.$statementId);
        $data = "{
	\"data\": {
		\"attributes\": {\"text\": \"$statementText\"},
		\"relationships\":{
			\"draftSegments\": { \"data\": [
				{\"id\": \"80d99aa5-a2d2-4429-8102-acfbc8d83255\", \"type\": \"DraftSegment\"},
				{\"id\": \"dc18b4a2-df3b-4663-9151-059385533c39\", \"type\": \"DraftSegment\"},
				{\"id\": \"9bde7739-5fa8-48fa-b50a-3178fe5bd1b4\", \"type\": \"DraftSegment\"},
				{\"id\": \"277b1795-46ec-4150-8393-54ac59b01574\", \"type\": \"DraftSegment\"},
				{\"id\": \"22e05369-3aa9-4992-807e-0b5fdbf22535\", \"type\": \"DraftSegment\"},
				{\"id\": \"a842b7a5-601a-4a4b-8eb0-d63bc3caecfd\", \"type\": \"DraftSegment\"},
				{\"id\": \"2ba0d8ae-9ed3-4470-8b94-76b3ac898540\", \"type\": \"DraftSegment\"},
				{\"id\": \"e540e5e6-0524-4c8c-80ae-a59d7cc6dedd\", \"type\": \"DraftSegment\"},
				{\"id\": \"f8e84952-de44-4735-8fd2-2f4ed50d8a9f\", \"type\": \"DraftSegment\"},
				{\"id\": \"798f9269-d4f4-4f3b-a7c0-c1827854d3a3\", \"type\": \"DraftSegment\"}
			]},
			\"tags\": { \"data\": [
				{\"id\": \"04c283b6-12e6-4d6c-b7ae-83daaaccc129\", \"type\": \"Tag\"},
				{\"id\": \"07c877c0-2d71-4d89-8883-2e1d49a1ccc2\", \"type\": \"Tag\"},
				{\"id\": \"aa308a1a-f5fa-4af7-8483-7be267f34363\", \"type\": \"Tag\"},
				{\"id\": \"ea51271a-d5ea-4fec-9c63-0f33368803b8\", \"type\": \"Tag\"},
				{\"id\": \"08f676b7-165b-4e10-aa26-cf259dc92288\", \"type\": \"Tag\"},
				{\"id\": \"29e519b5-2a1d-4fcc-a901-67b9e4945203\", \"type\": \"Tag\"},
				{\"id\": \"959fc896-bd1e-4048-966a-1b4d661d23c8\", \"type\": \"Tag\"},
				{\"id\": \"d84342fe-40a2-4abf-a308-353e4695e0ad\", \"type\": \"Tag\"},
				{\"id\": \"25d2225f-7308-4ff1-9d41-d4d82e466a2b\", \"type\": \"Tag\"},
				{\"id\": \"c34e6fa3-42cd-4d44-a2fe-9205114f682e\", \"type\": \"Tag\"},
				{\"id\": \"2719058a-f524-4285-b532-4f8e78854432\", \"type\": \"Tag\"},
				{\"id\": \"46f7f636-c461-4eab-8981-a248e5b5edc0\", \"type\": \"Tag\"},
				{\"id\": \"e6c7ff47-b415-44a2-8867-ac1b023f8a3e\", \"type\": \"Tag\"},
				{\"id\": \"2e490313-ea6c-4568-98aa-04a71851af45\", \"type\": \"Tag\"},
				{\"id\": \"4bbca289-7e6f-441e-89b7-81a7e1787f06\", \"type\": \"Tag\"}
			]},
			\"statement\": {\"data\": {\"id\": \"$statementId\", \"type\": \"Statement\"}}
		},
		\"type\": \"SegmentedStatement\"
	},
	\"meta\": {
	    \"token\": \"$sha256\"
	},
	\"included\": [
		{
			\"attributes\": {
				\"position\": {\"start\": 0, \"stop\": 147},
				\"score\": null,
				\"tag\": {
					\"04c283b6-12e6-4d6c-b7ae-83daaaccc129\": 0.85728
				}
			},
			\"id\": \"80d99aa5-a2d2-4429-8102-acfbc8d83255\",
			\"type\": \"DraftSegment\"
		},
		{
			\"attributes\": {
				\"position\": {\"start\": 147, \"stop\": 296},
				\"score\": null,
				\"tag\": {
					\"07c877c0-2d71-4d89-8883-2e1d49a1ccc2\": 0.46477,
					\"aa308a1a-f5fa-4af7-8483-7be267f34363\": 0.33505,
					\"ea51271a-d5ea-4fec-9c63-0f33368803b8\": 0.23691
				}
			},
			\"id\": \"dc18b4a2-df3b-4663-9151-059385533c39\",
			\"type\": \"DraftSegment\"
		},
		{
			\"attributes\": {
				\"position\": {\"start\": 296, \"stop\": 418},
				\"score\": null,
				\"tag\": {
					\"ea51271a-d5ea-4fec-9c63-0f33368803b8\": 0.56474
				}
			},
			\"id\": \"9bde7739-5fa8-48fa-b50a-3178fe5bd1b4\",
			\"type\": \"DraftSegment\"
		},
		{
			\"attributes\": {
				\"position\": {\"start\": 419, \"stop\": 1325},
				\"score\": null,
				\"tag\": {
					\"04c283b6-12e6-4d6c-b7ae-83daaaccc129\": 0.30748,
					\"08f676b7-165b-4e10-aa26-cf259dc92288\": 0.30927,
					\"aa308a1a-f5fa-4af7-8483-7be267f34363\": 0.94929
				}
			},
			\"id\": \"277b1795-46ec-4150-8393-54ac59b01574\",
			\"type\": \"DraftSegment\"
		},
		{
			\"attributes\": {
				\"position\": {\"start\": 1326, \"stop\": 1806},
				\"score\": null,
				\"tag\": {
					\"04c283b6-12e6-4d6c-b7ae-83daaaccc129\": 0.31342,
					\"25d2225f-7308-4ff1-9d41-d4d82e466a2b\": 0.66245,
					\"29e519b5-2a1d-4fcc-a901-67b9e4945203\": 0.84943,
					\"959fc896-bd1e-4048-966a-1b4d661d23c8\": 0.20986,
					\"d84342fe-40a2-4abf-a308-353e4695e0ad\": 0.21255
				}
			},
			\"id\": \"22e05369-3aa9-4992-807e-0b5fdbf22535\",
			\"type\": \"DraftSegment\"
		},
		{
			\"attributes\": {
				\"position\": {\"start\": 1806, \"stop\": 1910},
				\"score\": null,
				\"tag\": {
					\"08f676b7-165b-4e10-aa26-cf259dc92288\": 0.86881
				}
			},
			\"id\": \"a842b7a5-601a-4a4b-8eb0-d63bc3caecfd\",
			\"type\": \"DraftSegment\"
		},
		{
			\"attributes\": {
				\"position\": {\"start\": 1911, \"stop\": 2024},
				\"score\": null,
				\"tag\": {
					\"08f676b7-165b-4e10-aa26-cf259dc92288\": 0.25511,
					\"ea51271a-d5ea-4fec-9c63-0f33368803b8\": 0.31034
				}
			},
			\"id\": \"2ba0d8ae-9ed3-4470-8b94-76b3ac898540\",
			\"type\": \"DraftSegment\"
		},
		{
			\"attributes\": {
				\"position\": {\"start\": 2025, \"stop\": 2114},
				\"score\": null,
				\"tag\": {
					\"c34e6fa3-42cd-4d44-a2fe-9205114f682e\": 0.62086
				}
			},
			\"id\": \"e540e5e6-0524-4c8c-80ae-a59d7cc6dedd\",
			\"type\": \"DraftSegment\"
		},
		{
			\"attributes\": {
				\"position\": {\"start\": 2114, \"stop\": 2439},
				\"score\": null,
				\"tag\": {
					\"2719058a-f524-4285-b532-4f8e78854432\": 0.23908,
					\"46f7f636-c461-4eab-8981-a248e5b5edc0\": 0.83949,
					\"e6c7ff47-b415-44a2-8867-ac1b023f8a3e\": 0.30131
				}
			},
			\"id\": \"f8e84952-de44-4735-8fd2-2f4ed50d8a9f\",
			\"type\": \"DraftSegment\"
		},
		{
			\"attributes\": {
				\"position\": {\"start\": 2439, \"stop\": 2780},
				\"score\": null,
				\"tag\": {
					\"04c283b6-12e6-4d6c-b7ae-83daaaccc129\": 0.22282,
					\"2e490313-ea6c-4568-98aa-04a71851af45\": 0.2098,
					\"4bbca289-7e6f-441e-89b7-81a7e1787f06\": 0.2579
				}
			},
			\"id\": \"798f9269-d4f4-4f3b-a7c0-c1827854d3a3\",
			\"type\": \"DraftSegment\"
		},
		{\"attributes\": {\"title\": \"Verkehr\"}, \"id\": \"04c283b6-12e6-4d6c-b7ae-83daaaccc129\", \"type\": \"Tag\"},
		{\"attributes\": {\"title\": \"Altlasten\"}, \"id\": \"07c877c0-2d71-4d89-8883-2e1d49a1ccc2\", \"type\": \"Tag\"},
		{\"attributes\": {\"title\": \"Immissionen\"}, \"id\": \"aa308a1a-f5fa-4af7-8483-7be267f34363\", \"type\": \"Tag\"},
		{\"attributes\": {\"title\": \"unknown (skip)\"}, \"id\": \"ea51271a-d5ea-4fec-9c63-0f33368803b8\", \"type\": \"Tag\"},
		{\"attributes\": {\"title\": \"Wohnungsbau\"}, \"id\": \"08f676b7-165b-4e10-aa26-cf259dc92288\", \"type\": \"Tag\"},
		{\"attributes\": {\"title\": \"Artenschutz\"}, \"id\": \"29e519b5-2a1d-4fcc-a901-67b9e4945203\", \"type\": \"Tag\"},
		{\"attributes\": {\"title\": \"Erhaltung\"}, \"id\": \"959fc896-bd1e-4048-966a-1b4d661d23c8\", \"type\": \"Tag\"},
		{\"attributes\": {\"title\": \"Grünfläche\"}, \"id\": \"d84342fe-40a2-4abf-a308-353e4695e0ad\", \"type\": \"Tag\"},
		{\"attributes\": {\"title\": \"Schutzgut Pflanzen\"}, \"id\": \"25d2225f-7308-4ff1-9d41-d4d82e466a2b\", \"type\": \"Tag\"},
		{\"attributes\": {\"title\": \"Gewerbe\"}, \"id\": \"c34e6fa3-42cd-4d44-a2fe-9205114f682e\", \"type\": \"Tag\"},
		{\"attributes\": {\"title\": \"Oberflächengewässer\"}, \"id\": \"2719058a-f524-4285-b532-4f8e78854432\", \"type\": \"Tag\"},
		{\"attributes\": {\"title\": \"Wasserwirtschaft\"}, \"id\": \"46f7f636-c461-4eab-8981-a248e5b5edc0\", \"type\": \"Tag\"},
		{\"attributes\": {\"title\": \"without context\"}, \"id\": \"e6c7ff47-b415-44a2-8867-ac1b023f8a3e\", \"type\": \"Tag\"},
		{\"attributes\": {\"title\": \"Inhaltslos\"}, \"id\": \"2e490313-ea6c-4568-98aa-04a71851af45\", \"type\": \"Tag\"},
		{\"attributes\": {\"title\": \"negativ\"}, \"id\": \"4bbca289-7e6f-441e-89b7-81a7e1787f06\", \"type\": \"Tag\"}
	]
}";
        $normalizer = new Normalizer();
        $topLevel = $normalizer->normalize($data);

        $this->sut->updateStatement($topLevel);
        $actualJson = $statement->getDraftsListJson();
        // extract random id from actualJson
        $actualJsonArray = Json::decodeToArray($actualJson);
        $expectedJson = '{"data":{"id":"'.$actualJsonArray['data']['id'].'","type":"SegmentedStatement","attributes":{"statementId":"'.$statement->getId().'","procedureId":"'.$statement->getProcedureId().'","textualReference":"Ich bin der Text f\u00fcr das Statement","segments":[{"id":"80d99aa5-a2d2-4429-8102-acfbc8d83255","charStart":0,"charEnd":147,"tags":[{"id":"04c283b6-12e6-4d6c-b7ae-83daaaccc129","tagName":"Verkehr","tagScore":"0.85728"}]},{"id":"dc18b4a2-df3b-4663-9151-059385533c39","charStart":147,"charEnd":296,"tags":[{"id":"07c877c0-2d71-4d89-8883-2e1d49a1ccc2","tagName":"Altlasten","tagScore":"0.46477"},{"id":"aa308a1a-f5fa-4af7-8483-7be267f34363","tagName":"Immissionen","tagScore":"0.33505"},{"id":"ea51271a-d5ea-4fec-9c63-0f33368803b8","tagName":"unknown (skip)","tagScore":"0.23691"}]},{"id":"9bde7739-5fa8-48fa-b50a-3178fe5bd1b4","charStart":296,"charEnd":418,"tags":[{"id":"ea51271a-d5ea-4fec-9c63-0f33368803b8","tagName":"unknown (skip)","tagScore":"0.56474"}]},{"id":"277b1795-46ec-4150-8393-54ac59b01574","charStart":419,"charEnd":1325,"tags":[{"id":"04c283b6-12e6-4d6c-b7ae-83daaaccc129","tagName":"Verkehr","tagScore":"0.30748"},{"id":"08f676b7-165b-4e10-aa26-cf259dc92288","tagName":"Wohnungsbau","tagScore":"0.30927"},{"id":"aa308a1a-f5fa-4af7-8483-7be267f34363","tagName":"Immissionen","tagScore":"0.94929"}]},{"id":"22e05369-3aa9-4992-807e-0b5fdbf22535","charStart":1326,"charEnd":1806,"tags":[{"id":"04c283b6-12e6-4d6c-b7ae-83daaaccc129","tagName":"Verkehr","tagScore":"0.31342"},{"id":"25d2225f-7308-4ff1-9d41-d4d82e466a2b","tagName":"Schutzgut Pflanzen","tagScore":"0.66245"},{"id":"29e519b5-2a1d-4fcc-a901-67b9e4945203","tagName":"Artenschutz","tagScore":"0.84943"},{"id":"959fc896-bd1e-4048-966a-1b4d661d23c8","tagName":"Erhaltung","tagScore":"0.20986"},{"id":"d84342fe-40a2-4abf-a308-353e4695e0ad","tagName":"Gr\u00fcnfl\u00e4che","tagScore":"0.21255"}]},{"id":"a842b7a5-601a-4a4b-8eb0-d63bc3caecfd","charStart":1806,"charEnd":1910,"tags":[{"id":"08f676b7-165b-4e10-aa26-cf259dc92288","tagName":"Wohnungsbau","tagScore":"0.86881"}]},{"id":"2ba0d8ae-9ed3-4470-8b94-76b3ac898540","charStart":1911,"charEnd":2024,"tags":[{"id":"08f676b7-165b-4e10-aa26-cf259dc92288","tagName":"Wohnungsbau","tagScore":"0.25511"},{"id":"ea51271a-d5ea-4fec-9c63-0f33368803b8","tagName":"unknown (skip)","tagScore":"0.31034"}]},{"id":"e540e5e6-0524-4c8c-80ae-a59d7cc6dedd","charStart":2025,"charEnd":2114,"tags":[{"id":"c34e6fa3-42cd-4d44-a2fe-9205114f682e","tagName":"Gewerbe","tagScore":"0.62086"}]},{"id":"f8e84952-de44-4735-8fd2-2f4ed50d8a9f","charStart":2114,"charEnd":2439,"tags":[{"id":"2719058a-f524-4285-b532-4f8e78854432","tagName":"Oberfl\u00e4chengew\u00e4sser","tagScore":"0.23908"},{"id":"46f7f636-c461-4eab-8981-a248e5b5edc0","tagName":"Wasserwirtschaft","tagScore":"0.83949"},{"id":"e6c7ff47-b415-44a2-8867-ac1b023f8a3e","tagName":"without context","tagScore":"0.30131"}]},{"id":"798f9269-d4f4-4f3b-a7c0-c1827854d3a3","charStart":2439,"charEnd":2780,"tags":[{"id":"04c283b6-12e6-4d6c-b7ae-83daaaccc129","tagName":"Verkehr","tagScore":"0.22282"},{"id":"2e490313-ea6c-4568-98aa-04a71851af45","tagName":"Inhaltslos","tagScore":"0.2098"},{"id":"4bbca289-7e6f-441e-89b7-81a7e1787f06","tagName":"negativ","tagScore":"0.2579"}]}]}}}';
        self::assertSame($expectedJson, $actualJson);
    }
}
