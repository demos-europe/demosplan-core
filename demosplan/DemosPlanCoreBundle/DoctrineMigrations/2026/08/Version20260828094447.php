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

/**
 * DPLAN-18271: retroactively converts legacy boilerplate_usage rows into the new
 * <dp-boilerplate boilerplate-id="…"></dp-boilerplate> reference tag form, or severs the
 * relation outright when that conversion cannot be done safely.
 *
 * Before this ticket, linking a boilerplate to a segment/statement (boilerplate_usage)
 * copied the boilerplate's text verbatim into the recommendation as plain content — no
 * tag, no reference. Under this ticket's tag-based reconciliation, the next normal save
 * of such a row would silently drop the boilerplate_usage relation (zero tags found in
 * plain text -> nothing to preserve). Rather than leave that to happen implicitly and
 * unpredictably on whatever a future edit does, this migration resolves every row now:
 *
 * - If the boilerplate's *current* text still appears byte-for-byte, exactly once, in the
 *   recommendation: cut that exact substring out and replace it with the reference tag at
 *   the same position. What is rendered does not change (getRecommendation() substitutes
 *   the tag right back to that same text) — only the storage form does, and the relation
 *   survives.
 * - The same applies with the boilerplate text's own outer <p>/</p> wrapper stripped, when
 *   the boilerplate text is a single paragraph. Verified against a real, freshly-created
 *   test case (2026-08-28): inserting a boilerplate mid-paragraph into a segment's
 *   recommendation makes the editor merge it into the surrounding paragraph, dropping the
 *   boilerplate's own <p>/</p> wrapper — a structural editor-serialization behavior, not a
 *   content guess. The wrapped form is tried first (the strongest match); the unwrapped
 *   form is only a fallback.
 * - Otherwise, the relation is deleted outright and the recommendation text itself is left
 *   completely untouched. This covers: the text not appearing verbatim at all (content has
 *   drifted since the relation was created — inserting a tag would silently swap in
 *   whatever the boilerplate says *now*, changing already-reviewed submitted content); the
 *   text appearing more than once (not a realistic production case — a boilerplate's text
 *   occurring twice in one recommendation has no legitimate reason to happen, so any
 *   occurrence is treated as test data rather than something to guess at); or the
 *   boilerplate's text being empty.
 *
 * Idempotent: a row already containing this boilerplate's tag is skipped entirely, so
 * running this migration twice (or on a database where some rows were already migrated by
 * hand) is safe.
 */
class Version20260828094447 extends AbstractMigration
{
    private const TAG_TEMPLATE = '<dp-boilerplate boilerplate-id="%s"></dp-boilerplate>';

    public function getDescription(): string
    {
        return 'refs DPLAN-18271: convert legacy boilerplate_usage rows to reference-tag form where the boilerplate text still matches verbatim, sever the relation otherwise';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $rows = $this->connection->fetchAllAssociative(
            'SELECT bu.id AS usage_id, bu.boilerplate_id AS boilerplate_id, s._st_id AS statement_id,
                    pt._pt_text AS boilerplate_text, s._st_recommendation AS recommendation
             FROM boilerplate_usage bu
             INNER JOIN _predefined_texts pt ON pt._pt_id = bu.boilerplate_id
             INNER JOIN _statement s ON s._st_id = bu.segment_id'
        );

        $convertedCount = 0;
        $severedCount = 0;

        foreach ($rows as $row) {
            $boilerplateText = (string) $row['boilerplate_text'];
            $recommendation = (string) $row['recommendation'];
            $tag = sprintf(self::TAG_TEMPLATE, $row['boilerplate_id']);

            if (str_contains($recommendation, $tag)) {
                // Already migrated (or already tag-based) - nothing to do.
                continue;
            }

            $match = $this->findUnambiguousMatch($recommendation, $boilerplateText);

            if (null === $match) {
                // No verbatim match (with or without the boilerplate's own paragraph
                // wrapper), or the text occurs more than once (not a realistic production
                // case - treated as test data rather than guessed at). Sever the relation
                // instead of leaving it to be silently dropped on some future, unrelated
                // save.
                $this->addSql('DELETE FROM boilerplate_usage WHERE id = ?', [$row['usage_id']]);
                ++$severedCount;
                continue;
            }

            $newRecommendation = substr_replace($recommendation, $tag, $match['position'], $match['length']);
            $this->addSql(
                'UPDATE _statement SET _st_recommendation = ? WHERE _st_id = ?',
                [$newRecommendation, $row['statement_id']]
            );
            ++$convertedCount;
        }

        $this->write("Converted $convertedCount legacy boilerplate_usage row(s) to reference-tag form; severed $severedCount unmatched/ambiguous relation(s).");
    }

    /**
     * Only reverses the tag->plain-text conversions. Severed relations (rows deleted in
     * up() because the boilerplate text no longer matched verbatim, or matched more than
     * once) cannot be reconstructed — the same inherent limitation any migration rollback
     * has for deleted data. Also assumes the boilerplate's text has not changed since
     * up() ran. Always restores the boilerplate's wrapped text (the strongest form), even
     * if the unwrapped variant was what originally matched — the visible content is the
     * same either way once substituted, only the raw storage form differs.
     *
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $rows = $this->connection->fetchAllAssociative(
            'SELECT bu.boilerplate_id AS boilerplate_id, s._st_id AS statement_id,
                    pt._pt_text AS boilerplate_text, s._st_recommendation AS recommendation
             FROM boilerplate_usage bu
             INNER JOIN _predefined_texts pt ON pt._pt_id = bu.boilerplate_id
             INNER JOIN _statement s ON s._st_id = bu.segment_id'
        );

        foreach ($rows as $row) {
            $tag = sprintf(self::TAG_TEMPLATE, $row['boilerplate_id']);
            $recommendation = (string) $row['recommendation'];
            $position = strpos($recommendation, $tag);
            if (false === $position) {
                continue;
            }

            $newRecommendation = substr_replace($recommendation, (string) $row['boilerplate_text'], $position, strlen($tag));
            $this->addSql(
                'UPDATE _statement SET _st_recommendation = ? WHERE _st_id = ?',
                [$newRecommendation, $row['statement_id']]
            );
        }
    }

    /**
     * Tries the boilerplate's text as-is first, then — if it is a single paragraph — with
     * its own outer <p>/</p> wrapper stripped (see class docblock for why that second form
     * is a verified structural editor behavior, not a guess). Returns null if neither form
     * matches, or if a form matches more than once (ambiguous — the whole match attempt is
     * abandoned rather than falling through to a looser candidate).
     *
     * @return array{position: int, length: int}|null
     */
    private function findUnambiguousMatch(string $recommendation, string $boilerplateText): ?array
    {
        foreach ($this->candidateTexts($boilerplateText) as $candidate) {
            if ('' === $candidate) {
                continue;
            }

            $firstPosition = strpos($recommendation, $candidate);
            if (false === $firstPosition) {
                continue;
            }

            $secondPosition = strpos($recommendation, $candidate, $firstPosition + 1);
            if (false !== $secondPosition) {
                return null;
            }

            return ['position' => $firstPosition, 'length' => strlen($candidate)];
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function candidateTexts(string $boilerplateText): array
    {
        $candidates = [$boilerplateText];

        $isSingleWrappedParagraph = str_starts_with($boilerplateText, '<p>')
            && str_ends_with($boilerplateText, '</p>')
            && 1 === substr_count($boilerplateText, '<p>')
            && 1 === substr_count($boilerplateText, '</p>');
        if ($isSingleWrappedParagraph) {
            $candidates[] = substr($boilerplateText, 3, -4);
        }

        return $candidates;
    }

    /**
     * @throws Exception
     */
    private function abortIfNotMysql(): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on 'mysql'."
        );
    }
}
