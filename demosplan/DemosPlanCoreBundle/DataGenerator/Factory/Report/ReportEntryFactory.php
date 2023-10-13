<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Report;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Repository\ReportRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<ReportEntry>
 *
 * @method        ReportEntry|Proxy                create(array|callable $attributes = [])
 * @method static ReportEntry|Proxy                createOne(array $attributes = [])
 * @method static ReportEntry|Proxy                find(object|array|mixed $criteria)
 * @method static ReportEntry|Proxy                findOrCreate(array $attributes)
 * @method static ReportEntry|Proxy                first(string $sortedField = 'id')
 * @method static ReportEntry|Proxy                last(string $sortedField = 'id')
 * @method static ReportEntry|Proxy                random(array $attributes = [])
 * @method static ReportEntry|Proxy                randomOrCreate(array $attributes = [])
 * @method static ReportRepository|RepositoryProxy repository()
 * @method static ReportEntry[]|Proxy[]            all()
 * @method static ReportEntry[]|Proxy[]            createMany(int $number, array|callable $attributes = [])
 * @method static ReportEntry[]|Proxy[]            createSequence(iterable|callable $sequence)
 * @method static ReportEntry[]|Proxy[]            findBy(array $attributes)
 * @method static ReportEntry[]|Proxy[]            randomRange(int $min, int $max, array $attributes = [])
 * @method static ReportEntry[]|Proxy[]            randomSet(int $number, array $attributes = [])
 */
final class ReportEntryFactory extends ModelFactory
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function getDefaults(): array
    {
        return [
            'category'       => 'add',
            'customer'       => CustomerFactory::new(),
            'group'          => 'statement',
            'identifier'     => self::faker()->uuid(),
            'identifierType' => 'generated',
            'incoming'       => '',
            'level'          => 'INFO',
            'message'        => '{"ident":"07fb6bbd-3bb3-11eb-83ff-b026282c0641","id":"07fb6bbd-3bb3-11eb-83ff-b026282c0641","parent":null,"parentId":null,"children":[],"original":null,"originalId":null,"priority":"","externId":"M3","internId":null,"user":{"__initializer__":null,"__cloner__":null,"__isInitialized__":true},"uId":"73830656-3e48-11e4-a6a8-005056ae0004","uName":"","organisation":{"id":"cdec5e4b-3f06-11e4-a6a8-005056ae0004","name":"Bürger"},"oId":"cdec5e4b-3f06-11e4-a6a8-005056ae0004","oName":"Bürger","dName":"anonym","procedure":{"id":"b3f2cf2a-3bad-11eb-83ff-b026282c0641","name":"Test_11_12_2020_III","phase":"analysis","publicParticipationPhase":"analysis"},"pId":"b3f2cf2a-3bad-11eb-83ff-b026282c0641","represents":"","representationCheck":0,"phase":"analysis","status":"new","created":1607692568000,"modified":1607692568000,"send":134168000,"sentAssessmentDate":134168000,"submit":1607692568,"deletedDate":134168000,"deleted":false,"negativeStatement":false,"sentAssessment":false,"publicUseName":false,"publicVerified":"no_check_permission_disabled","anonymizations":[],"publicStatement":"external","toSendPerMail":false,"title":"","text":"<strong>Test document PDF<\/strong><br\/><p>Test document PDF Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nulla est purus, ultrices in porttitor in, accumsan non quam. Nam consectetur porttitor rhoncus. Curabitur eu est et leo feugiat auctor vel quis lorem. Ut et ligula dolor, sit amet consequat lorem. Aliquam porta eros sed velit imperdiet egestas. Maecenas tempus eros ut diam ullamcorper id dictum libero tempor. Donec quis augue quis magna condimentum lobortis. Quisque imperdiet ipsum vel magna viverra rutrum. Cras viverra molestie urna, vitae vestibulum turpis varius id. Vestibulum mollis, arcu iaculis bibendum varius, velit sapien blandit metus, ac posuere lorem nulla ac dolor. Maecenas urna elit, tincidunt in dapibus nec, vehicula eu dui. Duis lacinia fringilla massa. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Ut consequat ultricies est, non rhoncus mauris congue porta. Vivamus viverra suscipit felis eget condimentum. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Integer bibendum sagittis ligula, non faucibus nulla volutpat vitae. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. In aliquet quam et velit bibendum accumsan. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Vestibulum vitae ipsum nec arcu semper adipiscing at ac lacus. Praesent id pellentesque orci. Morbi congue viverra nisl nec rhoncus. Integer mattis, ipsum a tincidunt commodo, lacus arcu elementum elit, at mollis eros ante ac risus. In volutpat, ante at pretium ultricies, velit magna suscipit enim, aliquet blandit massa orci nec lorem. Nulla facilisi. Duis eu vehicula arcu. Nulla facilisi. Maecenas pellentesque volutpat felis, quis tristique ligula luctus vel. Sed nec mi eros. Integer augue enim, sollicitudin ullamcorper mattis eget, aliquam in est. Morbi sollicitudin libero nec augue dignissim ut consectetur dui volutpat. Nulla facilisi. Mauris egestas vestibulum neque cursus tincidunt. Donec sit amet pulvinar orci. Quisque volutpat pharetra tincidunt. Fusce sapien arcu, molestie eget varius egestas, faucibus ac urna. Sed at nisi in velit egestas aliquam ut a felis. Aenean malesuada iaculis nisl, ut tempor lacus egestas consequat. Nam nibh lectus, gravida sed egestas ut, feugiat quis dolor. Donec eu leo enim, non laoreet ante. Morbi dictum tempor vulputate. Phasellus ultricies risus vel augue sagittis euismod. Vivamus tincidunt placerat nisi in aliquam. Cras quis mi ac nunc pretium aliquam. Aenean elementum erat ac metus commodo rhoncus. Aliquam nulla augue, porta non sagittis quis, accumsan vitae sem. Phasellus id lectus tortor, eget pulvinar augue. Etiam eget velit ac purus fringilla blandit. Donec odio odio, sagittis sed iaculis sed, consectetur eget sem. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Maecenas accumsan velit vel turpis rutrum in sodales diam placerat. Quisque luctus ullamcorper velit sit amet lobortis. Etiam ligula felis, vulputate quis rhoncus nec, fermentum eget odio. Vivamus vel ipsum ac augue sodales mollis euismod nec tellus. Fusce et augue rutrum nunc semper vehicula vel semper nisl. Nam laoreet euismod quam at varius. Sed aliquet auctor nibh. Curabitur malesuada fermentum lacus vel accumsan. Duis ornare scelerisque nulla, ac pulvinar ligula tempus sit amet. In placerat nulla ac ante scelerisque posuere. Phasellus at ante felis. Sed hendrerit risus a metus posuere rutrum. Phasellus eu augue dui. Proin in vestibulum ipsum. Aenean accumsan mollis sapien, ut eleifend sem blandit at. Vivamus luctus mi eget lorem lobortis pharetra. Phasellus at tortor quam, a volutpat purus. Etiam sollicitudin arcu vel elit bibendum et imperdiet risus tincidunt. Etiam elit velit, posuere ut pulvinar ac, condimentum eget justo. Fusce a erat velit. Vivamus imperdiet ultrices orci in hendrerit.<\/p>","textShort":"<strong>Test document PDF<\/strong><br><p>Test document PDF Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nulla est purus, ultrices in porttitor in, accumsan non quam. Nam consectetur porttitor rhoncus. Curabitur eu est et leo feugiat auctor vel quis lorem. Ut et ligula dolor, sit amet consequat lorem. Aliquam porta eros sed velit imperdiet egestas. Maecenas tempus eros ut diam ullamcorper id dictum libero tempor. Donec quis augue quis magna condimentum lobortis. Quisque imperdiet ipsum vel magna viverra<\/p>","recommendation":"","recommendationShort":"","memo":"","feedback":"email","reasonParagraph":"","reasonTitle":"","planningDocument":"","file":"","mapFile":"","countyNotified":false,"paragraph":null,"paragraphId":null,"paragraphTitle":"","paragraphOrder":null,"paragraphParentId":null,"paragraphParentTitle":"","documentParentTitle":"","documentHash":null,"elementId":"b462b85d-3bad-11eb-83ff-b026282c0641","elementTitle":"Gesamtstellungnahme","element":{"ident":"b462b85d-3bad-11eb-83ff-b026282c0641","id":"b462b85d-3bad-11eb-83ff-b026282c0641","elementParentId":null,"parent":null,"pId":"b3f2cf2a-3bad-11eb-83ff-b026282c0641","procedure":[],"category":"statement","title":"Gesamtstellungnahme","icon":"","iconTitle":"","text":"Hier können Sie eine Stellungnahme zum gesamten Verfahren abgeben","file":"","order":1,"enabled":false,"deleted":false,"documents":[],"children":[],"organisations":[],"designatedSwitchDate":null,"permission":null,"organisation":[],"createdate":1584441593000,"modifydate":1584441593000,"deletedate":1584441593000},"polygon":"","draftStatement":null,"draftStatementId":null,"meta":{"ident":"07fb920b-3bb3-11eb-83ff-b026282c0641","id":"07fb920b-3bb3-11eb-83ff-b026282c0641","statement":[],"statementId":"07fb6bbd-3bb3-11eb-83ff-b026282c0641","authorName":"","submitUId":"73830656-3e48-11e4-a6a8-005056ae0004","submitName":"","orgaName":"Bürger","orgaDepartmentName":"anonym","caseWorkerName":"","orgaStreet":"","houseNumber":"","orgaPostalCode":"","orgaCity":"","orgaEmail":"","authoredDate":0,"submitOrgaId":"9a3ae3ff-683b-11ea-bea8-782bcb0d78b1","miscData":{"submitterRole":"citizen"}},"version":[],"statementAttributes":[],"votes":[],"numberOfAnonymVotes":0,"likes":[],"tags":[],"counties":[],"priorityAreas":[],"municipalities":[],"fragments":[],"fragmentsFilteredCount":0,"voteStk":null,"votePla":null,"gdprConsent":[],"submitType":"unknown","files":[],"assignee":null,"headStatement":null,"cluster":[],"manual":true,"clusterStatement":false,"placeholderStatement":null,"movedStatement":null,"name":"","template":false,"replied":false,"draftsListJson":"","segmentsOfStatement":[],"submitterAndAuthorMetaDataAnonymized":false,"textPassagesAnonymized":false,"attachmentsDeleted":false,"attachments":[],"segmentationPiRetries":0,"piSegmentsProposalResourceUrl":null,"bthgKompassAnswer":null,"createdByToeb":false,"createdByCitizen":false,"submitterEmailAddress":"","votesNum":0,"categories":[]}',
            'mimeType'       => '',
            'sessionId'      => '',
            'userId'         => self::faker()->uuid(),
            'userName'       => self::faker()->name(),
        ];
    }

    protected function initialize(): self
    {
        return $this;
    }

    protected static function getClass(): string
    {
        return ReportEntry::class;
    }
}
