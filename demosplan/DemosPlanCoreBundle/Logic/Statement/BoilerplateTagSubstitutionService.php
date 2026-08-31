<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use demosplan\DemosPlanCoreBundle\Repository\BoilerplateRepository;
use DOMDocumentFragment;
use DOMElement;
use DOMNode;
use Masterminds\HTML5;

/**
 * Parses and substitutes <dp-boilerplate boilerplate-id="…"></dp-boilerplate> reference
 * tags embedded in recommendation text (DPLAN-18271).
 *
 * The tag never carries content, only a reference to a {@see Boilerplate} id. Every
 * read path that is not the editor itself — exports, emails, version-history
 * snapshots, Elasticsearch, the JSON:API `recommendation` attribute, addons — must see
 * the boilerplate's *current* text instead of the tag. Only the editor's tag-form
 * accessor (`getRecommendationEmbedded()`) and the setter's reconciliation logic ever
 * see the raw tag.
 *
 * Uses {@see HTML5} (Masterminds\HTML5), not raw DOMDocument::loadHTML() — that method
 * mangles UTF-8 by default (`Grüßen` becomes `GrÃ¼Ãen`), which HTML5 does not. Same
 * reasoning already applied by {@see HTMLFragmentSlicer} for the same kind of work on
 * the same kind of content.
 *
 * The reference attribute is `boilerplate-id`, deliberately all-lowercase:
 * HTML5-spec-compliant parsers (this one included, same as every browser) lowercase
 * attribute names on parse, so an attribute name containing uppercase letters would
 * silently normalize to a different string than what was written. `boilerplate-id` has
 * no uppercase letters, so there is nothing for any parser to normalize.
 *
 * {@see DOMDocumentFragment} does not support getElementsByTagName() in PHP's DOM
 * extension, and DOMXPath queries scoped to a DOMDocumentFragment context node
 * reliably return zero matches (verified directly) — both traversal methods that would
 * normally be reached for first. Tags are found by walking the parsed fragment's node
 * tree directly instead.
 */
class BoilerplateTagSubstitutionService
{
    final public const TAG_NAME = 'dp-boilerplate';
    final public const ATTRIBUTE_NAME = 'boilerplate-id';

    /**
     * Per-instance cache of resolved boilerplate texts, keyed by id, for the
     * single-lookup path in {@see self::substitute()} (used whenever no pre-loaded
     * $boilerplateTextsById map is given). Safe because this service is a shared
     * (singleton-per-container) Symfony service: for a web request that means one cache
     * per request; for a long-running process (Elasticsearch populate, message workers)
     * it means one cache per run — matching the DPLAN-18271 plan's "per-request caching is
     * safe; long-running processes must cache per-run only" guidance. A boilerplate being
     * edited mid-run is an accepted, cheap staleness window, not a correctness issue: the
     * populate/reindex run reflects boilerplate content as of when it started.
     *
     * @var array<string, string|null>
     */
    private array $boilerplateTextCache = [];

    public function __construct(
        private readonly BoilerplateRepository $boilerplateRepository,
    ) {
    }

    /**
     * Returns the distinct boilerplate ids referenced by tags in $embeddedText, in the
     * order they first appear.
     *
     * @return string[]
     */
    public function extractBoilerplateIds(string $embeddedText): array
    {
        if (!$this->mayContainTag($embeddedText)) {
            return [];
        }

        $ids = [];
        foreach ($this->findTags($this->parseFragment($embeddedText)) as $tag) {
            $id = $tag->getAttribute(self::ATTRIBUTE_NAME);
            if ('' !== $id && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Replaces every <dp-boilerplate boilerplate-id="…"> tag in $embeddedText with the
     * current text of the boilerplate it references.
     *
     * A tag referencing a boilerplate that no longer exists substitutes to an empty
     * string. This should not occur during normal operation — deleting a boilerplate
     * materializes its content into every usage before the boilerplate row is removed
     * (see the DPLAN-18271 plan, "Boilerplate deletion") — the empty-string fallback
     * exists only for the residual concurrent-edit race described there, not as a
     * designed state.
     *
     * @param array<string, string>|null $boilerplateTextsById pre-loaded id => text map,
     *        for bulk callers avoiding N+1 (exports, Elasticsearch populate). When null,
     *        texts are loaded individually for the ids actually present in
     *        $embeddedText.
     */
    public function substitute(string $embeddedText, ?array $boilerplateTextsById = null): string
    {
        if (!$this->mayContainTag($embeddedText)) {
            return $embeddedText;
        }

        $html5 = new HTML5();
        $fragment = $this->parseFragment($embeddedText, $html5);

        foreach ($this->findTags($fragment) as $tag) {
            $id = $tag->getAttribute(self::ATTRIBUTE_NAME);
            $replacementText = $boilerplateTextsById[$id] ?? $this->resolveBoilerplateText($id) ?? '';

            $this->replaceTag($tag, $fragment, $html5, $replacementText);
        }

        return $html5->saveHTML($fragment);
    }

    private function resolveBoilerplateText(string $boilerplateId): ?string
    {
        if (!array_key_exists($boilerplateId, $this->boilerplateTextCache)) {
            $this->boilerplateTextCache[$boilerplateId] = $this->boilerplateRepository->find($boilerplateId)?->getText();
        }

        return $this->boilerplateTextCache[$boilerplateId];
    }

    /**
     * Replaces only the tag(s) referencing $boilerplateId with $replacementText, leaving
     * any other boilerplate's tag in $embeddedText untouched (DPLAN-18271, delete-time
     * materialization: a recommendation may reference several boilerplates, and deleting
     * one of them must not affect the others still live).
     */
    public function materializeBoilerplate(string $embeddedText, string $boilerplateId, string $replacementText): string
    {
        if (!$this->mayContainTag($embeddedText)) {
            return $embeddedText;
        }

        $html5 = new HTML5();
        $fragment = $this->parseFragment($embeddedText, $html5);

        foreach ($this->findTags($fragment) as $tag) {
            if ($boilerplateId !== $tag->getAttribute(self::ATTRIBUTE_NAME)) {
                continue;
            }

            $this->replaceTag($tag, $fragment, $html5, $replacementText);
        }

        return $html5->saveHTML($fragment);
    }

    private function replaceTag(DOMElement $tag, DOMDocumentFragment $fragment, HTML5 $html5, string $replacementText): void
    {
        if ('' === $replacementText) {
            $tag->parentNode->removeChild($tag);

            return;
        }

        $replacementFragment = $html5->loadHTMLFragment($replacementText);
        $importedFragment = $fragment->ownerDocument->importNode($replacementFragment, true);
        $tag->parentNode->replaceChild($importedFragment, $tag);
    }

    /**
     * Cheap pre-check so recommendations without any boilerplate tag skip parsing
     * entirely — the common case for most recommendations.
     */
    private function mayContainTag(string $embeddedText): bool
    {
        return str_contains($embeddedText, self::TAG_NAME);
    }

    private function parseFragment(string $embeddedText, ?HTML5 $html5 = null): DOMDocumentFragment
    {
        return ($html5 ?? new HTML5())->loadHTMLFragment($embeddedText);
    }

    /**
     * @return list<DOMElement>
     */
    private function findTags(DOMNode $node): array
    {
        $found = [];
        $this->collectTags($node, $found);

        return $found;
    }

    /**
     * @param list<DOMElement> $found
     */
    private function collectTags(DOMNode $node, array &$found): void
    {
        if ($node instanceof DOMElement && self::TAG_NAME === strtolower($node->nodeName)) {
            $found[] = $node;
        }

        foreach ($node->childNodes as $child) {
            $this->collectTags($child, $found);
        }
    }
}
