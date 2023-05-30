<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Logic\ApiRequest;

use function data_get;

use DemosEurope\DemosplanAddon\Contracts\ApiRequest\Normalizer;
use Tests\Base\UnitTestCase;

class NormalizerTest extends UnitTestCase
{
    /**
     * @var Normalizer
     */
    protected $sut;

    public function setUp(): void
    {
        $this->sut = new Normalizer();
    }

    protected function tearDown(): void
    {
        unset($this->sut);
    }

    public function testNormalizesNoIncludesSingleItem()
    {
        $json = <<<JSON
{"meta":[],"data":{"type":"authors","id":"2","attributes":{"name":"testststst","birthplace":"Malaysia","date_of_birth":"1996-01-29","date_of_death":"1983-06-19"},"relationships":{"photos":{"data":[]},"books":{"data":[{"type":"books","id":"3"},{"type":"books","id":"30"}]}},"links":{"self":"\/authors\/2"}}}
JSON;

        $normalized = $this->sut->normalize($json);

        static::assertSame('2', $normalized['authors'][2]['id']);
    }

    public function testNormalizesNoIncludesList()
    {
        $json = <<<JSON
{"meta":{"page":1,"resources_per_page":5,"total_resources":6},"data":[{"type":"authors","id":"2","attributes":{"name":"testststst","birthplace":"Malaysia","date_of_birth":"1996-01-29","date_of_death":"1983-06-19"},"relationships":{"photos":{"data":[]},"books":{"data":[{"type":"books","id":"3"},{"type":"books","id":"30"}]}},"links":{"self":"\/authors\/2"}},{"type":"authors","id":"3","attributes":{"name":"okoko","birthplace":"El Salvador","date_of_birth":"1988-04-30","date_of_death":"1970-02-12"},"relationships":{"photos":{"data":[{"type":"photos","id":"3"}]},"books":{"data":[{"type":"books","id":"13"}]}},"links":{"self":"\/authors\/3"}},{"type":"authors","id":"4","attributes":{"name":"Prof. Antonina Paucek","birthplace":"Thailand","date_of_birth":"2010-09-30","date_of_death":"1997-07-05"},"relationships":{"photos":{"data":[{"type":"photos","id":"4"},{"type":"photos","id":"5"}]},"books":{"data":[{"type":"books","id":"26"}]}},"links":{"self":"\/authors\/4"}},{"type":"authors","id":"5","attributes":{"name":"Sierra Prosacco","birthplace":"Zambia","date_of_birth":"1992-07-30","date_of_death":"1999-04-21"},"relationships":{"photos":{"data":[]},"books":{"data":[{"type":"books","id":"16"}]}},"links":{"self":"\/authors\/5"}},{"type":"authors","id":"7","attributes":{"name":"Miss Noemy Paucek DVM","birthplace":"Senegal","date_of_birth":"1990-04-02","date_of_death":"1988-10-14"},"relationships":{"photos":{"data":[{"type":"photos","id":"7"},{"type":"photos","id":"8"}]},"books":{"data":[{"type":"books","id":"4"},{"type":"books","id":"21"}]}},"links":{"self":"\/authors\/7"}}]}
JSON;

        $normalized = $this->sut->normalize($json);

        static::assertCount(5, $normalized['authors']);
        static::assertSame('Sierra Prosacco', data_get($normalized, 'authors.5.attributes.name'));
    }

    public function testNormalizesItemWithInclude()
    {
        $json = <<<JSON
{"meta":[],"data":{"type":"authors","id":"5","attributes":{"name":"Sierra Prosacco","birthplace":"Zambia","date_of_birth":"1992-07-30","date_of_death":"1999-04-21"},"relationships":{"photos":{"data":[]},"books":{"data":[{"type":"books","id":"16"}]}},"links":{"self":"\/authors\/5"}},"included":[{"type":"books","id":"16","attributes":{"title":"Schamberger-Okuneva","date_published":"2007-01-01","isbn":4294967295}}]}
JSON;

        $normalized = $this->sut->normalize($json);

        static::assertSame('Zambia', data_get($normalized, 'authors.5.attributes.birthplace'));
        static::assertSame('Schamberger-Okuneva', data_get($normalized, 'books.16.attributes.title'));
    }

    public function testNormalizesListWithInclude()
    {
        $json = <<<JSON
{"data":[{"type":"authors","id":"2","attributes":{"name":"testststst","birthplace":"Malaysia","date_of_birth":"1996-01-29","date_of_death":"1983-06-19"},"relationships":{"photos":{"data":[]},"books":{"data":[{"type":"books","id":"3"},{"type":"books","id":"30"}]}},"links":{"self":"\/authors\/2"}},{"type":"authors","id":"3","attributes":{"name":"okoko","birthplace":"El Salvador","date_of_birth":"1988-04-30","date_of_death":"1970-02-12"},"relationships":{"photos":{"data":[{"type":"photos","id":"3"}]},"books":{"data":[{"type":"books","id":"13"}]}},"links":{"self":"\/authors\/3"}},{"type":"authors","id":"4","attributes":{"name":"Prof. Antonina Paucek","birthplace":"Thailand","date_of_birth":"2010-09-30","date_of_death":"1997-07-05"},"relationships":{"photos":{"data":[{"type":"photos","id":"4"},{"type":"photos","id":"5"}]},"books":{"data":[{"type":"books","id":"26"}]}},"links":{"self":"\/authors\/4"}},{"type":"authors","id":"5","attributes":{"name":"Sierra Prosacco","birthplace":"Zambia","date_of_birth":"1992-07-30","date_of_death":"1999-04-21"},"relationships":{"photos":{"data":[]},"books":{"data":[{"type":"books","id":"16"}]}},"links":{"self":"\/authors\/5"}},{"type":"authors","id":"7","attributes":{"name":"Miss Noemy Paucek DVM","birthplace":"Senegal","date_of_birth":"1990-04-02","date_of_death":"1988-10-14"},"relationships":{"photos":{"data":[{"type":"photos","id":"7"},{"type":"photos","id":"8"}]},"books":{"data":[{"type":"books","id":"4"},{"type":"books","id":"21"}]}},"links":{"self":"\/authors\/7"}}],"included":[{"type":"books","id":"3","attributes":{"title":"Wehner-Dare","date_published":"1970-04-19","isbn":4294967295}},{"type":"books","id":"30","attributes":{"title":"Wisoky Group","date_published":"2007-12-22","isbn":4294967295}},{"type":"photos","id":"3","attributes":{"title":"Photo 469","uri":"https:\/\/lorempixel.com\/400\/300\/abstract\/Faker\/?97816"}},{"type":"books","id":"13","attributes":{"title":"Lind, Koepp and Carter","date_published":"1988-04-04","isbn":2382936517}},{"type":"photos","id":"4","attributes":{"title":"Photo 737","uri":"https:\/\/lorempixel.com\/400\/300\/abstract\/Faker\/?93087"}},{"type":"photos","id":"5","attributes":{"title":"Photo 214","uri":"https:\/\/lorempixel.com\/400\/300\/abstract\/Faker\/?71089"}},{"type":"books","id":"26","attributes":{"title":"Cartwright-Tillman","date_published":"1990-08-25","isbn":900551658}},{"type":"books","id":"16","attributes":{"title":"Schamberger-Okuneva","date_published":"2007-01-01","isbn":4294967295}},{"type":"photos","id":"7","attributes":{"title":"Photo 723","uri":"https:\/\/lorempixel.com\/400\/300\/abstract\/Faker\/?93091"}},{"type":"photos","id":"8","attributes":{"title":"Photo 737","uri":"https:\/\/lorempixel.com\/400\/300\/abstract\/Faker\/?90267"}},{"type":"books","id":"4","attributes":{"title":"Runte LLC","date_published":"1994-10-28","isbn":4294967295}},{"type":"books","id":"21","attributes":{"title":"Muller-Haag","date_published":"1994-07-21","isbn":4294967295}}]}
JSON;

        $normalized = $this->sut->normalize($json);

        static::assertSame('Zambia', data_get($normalized, 'authors.5.attributes.birthplace'));
        static::assertSame('Muller-Haag', data_get($normalized, 'books.21.attributes.title'));
        static::assertCount(5, $normalized['photos']);
    }

    public function testRelationshipAccessViaResourceObjectMagic()
    {
        $json = <<<JSON
{"data":[{"type":"authors","id":"2","attributes":{"name":"testststst","birthplace":"Malaysia","date_of_birth":"1996-01-29","date_of_death":"1983-06-19"},"relationships":{"photos":{"data":[]},"books":{"data":[{"type":"books","id":"3"},{"type":"books","id":"30"}]}},"links":{"self":"\/authors\/2"}},{"type":"authors","id":"3","attributes":{"name":"okoko","birthplace":"El Salvador","date_of_birth":"1988-04-30","date_of_death":"1970-02-12"},"relationships":{"photos":{"data":[{"type":"photos","id":"3"}]},"books":{"data":[{"type":"books","id":"13"}]}},"links":{"self":"\/authors\/3"}},{"type":"authors","id":"4","attributes":{"name":"Prof. Antonina Paucek","birthplace":"Thailand","date_of_birth":"2010-09-30","date_of_death":"1997-07-05"},"relationships":{"photos":{"data":[{"type":"photos","id":"4"},{"type":"photos","id":"5"}]},"books":{"data":[{"type":"books","id":"26"}]}},"links":{"self":"\/authors\/4"}},{"type":"authors","id":"5","attributes":{"name":"Sierra Prosacco","birthplace":"Zambia","date_of_birth":"1992-07-30","date_of_death":"1999-04-21"},"relationships":{"photos":{"data":[]},"books":{"data":[{"type":"books","id":"16"}]}},"links":{"self":"\/authors\/5"}},{"type":"authors","id":"7","attributes":{"name":"Miss Noemy Paucek DVM","birthplace":"Senegal","date_of_birth":"1990-04-02","date_of_death":"1988-10-14"},"relationships":{"photos":{"data":[{"type":"photos","id":"7"},{"type":"photos","id":"8"}]},"books":{"data":[{"type":"books","id":"4"},{"type":"books","id":"21"}]}},"links":{"self":"\/authors\/7"}}],"included":[{"type":"books","id":"3","attributes":{"title":"Wehner-Dare","date_published":"1970-04-19","isbn":4294967295}},{"type":"books","id":"30","attributes":{"title":"Wisoky Group","date_published":"2007-12-22","isbn":4294967295}},{"type":"photos","id":"3","attributes":{"title":"Photo 469","uri":"https:\/\/lorempixel.com\/400\/300\/abstract\/Faker\/?97816"}},{"type":"books","id":"13","attributes":{"title":"Lind, Koepp and Carter","date_published":"1988-04-04","isbn":2382936517}},{"type":"photos","id":"4","attributes":{"title":"Photo 737","uri":"https:\/\/lorempixel.com\/400\/300\/abstract\/Faker\/?93087"}},{"type":"photos","id":"5","attributes":{"title":"Photo 214","uri":"https:\/\/lorempixel.com\/400\/300\/abstract\/Faker\/?71089"}},{"type":"books","id":"26","attributes":{"title":"Cartwright-Tillman","date_published":"1990-08-25","isbn":900551658}},{"type":"books","id":"16","attributes":{"title":"Schamberger-Okuneva","date_published":"2007-01-01","isbn":4294967295}},{"type":"photos","id":"7","attributes":{"title":"Photo 723","uri":"https:\/\/lorempixel.com\/400\/300\/abstract\/Faker\/?93091"}},{"type":"photos","id":"8","attributes":{"title":"Photo 737","uri":"https:\/\/lorempixel.com\/400\/300\/abstract\/Faker\/?90267"}},{"type":"books","id":"4","attributes":{"title":"Runte LLC","date_published":"1994-10-28","isbn":4294967295}},{"type":"books","id":"21","attributes":{"title":"Muller-Haag","date_published":"1994-07-21","isbn":4294967295}}]}
JSON;

        $normalized = $this->sut->normalize($json);

        $authors = $normalized['authors'];
        $books = $authors[2]['books'];

        static::assertCount(2, $books);
        static::assertSame('Wehner-Dare', $books[3]['title']);
    }

    public function testRelationshipStubGeneration()
    {
        $json = <<<JSON
{
  "data": {
    "type": "statementBulkEdit",
    "id": "73830656-3e48-11e4-a6a8-005056aef004",
    "relationships": {
      "assignee": {
        "data": { "type": "user", "id": "73830656-3e48-11e4-a6a8-005056ae0004" }
      },
      "statements": {
        "data": [
          { "type": "statement", "id": "08a4e167-286f-11e9-bb53-782bcb0d78b1" },
          { "type": "statement", "id": "8cad3d97-1fe4-11e9-b9cd-782bcb0d78b1" }
        ]
      }
    }
  }
}
JSON;
        $normalized = $this->sut->normalize($json);

        static::assertCount(2, $normalized['statement']);

        static::assertSame($normalized['statement'], $normalized['statementBulkEdit']['73830656-3e48-11e4-a6a8-005056aef004']['statements']);
    }
}
