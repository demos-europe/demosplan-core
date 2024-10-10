<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Services;

use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use DOMDocument;
use DOMException;
use DOMNode;
use DOMText;
use Masterminds\HTML5;
use RuntimeException;
use Illuminate\Support\Collection;

class HTMLFragmentSlicer
{
    final public const SLICE_AT_END_OF_STRING = -1;

    final public const SLICE_DEFAULT = 500; // slice at about 500 characters into a string by default

    /**
     * @var string
     */
    protected $originalFragment = '';

    /**
     * @var string
     */
    protected $shortenedFragment = '';

    /**
     * @var string
     */
    protected $remainingFragment = '';

    /**
     * initialized as the index of the input string's text at which the cut is supposed to happen,
     * subsequently adjusted to the index of the input string's text (read mb_strlen)at which the
     * cut actually happened.
     *
     * @var int
     */
    protected $sliceIndex = 0;

    /**
     * @var DOMDocument dom representation of the input string
     */
    private $dom;

    /**
     * @var Collection remainingList the list of remainder nodes
     */
    private $remainingList;

    private $currentSize = 0;
    private $reachedLimit = false;

    public function __construct()
    {
        $this->remainingList = new Collection();
    }

    /**
     * Returns the HTMLFragmentSlicer object. Please be aware
     * of the performance implications, as the result is not
     * cached here. Please use {@link HTMLFragmentSlicer::getShortened()}
     * if you only need the shortened text instead.
     *
     * @param string $htmlFragment
     * @param int    $sliceIndex
     *
     * @return HTMLFragmentSlicer
     */
    public static function slice(
        $htmlFragment,
        $sliceIndex = self::SLICE_DEFAULT
    ) {
        $slicer = new self();

        return $slicer
            ->setOriginalFragment($htmlFragment)
            ->setSliceIndex($sliceIndex)
            ->execute();
    }

    /**
     * Cached shorthand method for shortened text.
     *
     * @param string $htmlFragment
     * @param int    $sliceIndex
     */
    public static function getShortened($htmlFragment, $sliceIndex = self::SLICE_DEFAULT): string
    {
        $textHash = md5($htmlFragment);
        $cacheKey = 'htmlslicer_'.$textHash;
        if (DemosPlanTools::cacheExists($cacheKey)) {
            return DemosPlanTools::cacheGet($cacheKey);
        }

        $slicer = self::slice($htmlFragment, $sliceIndex);

        $shortened = $slicer->getShortenedFragment();

        // cache entry for 180 Days
        DemosPlanTools::cacheAdd($cacheKey, $shortened, 15_552_000);

        return $shortened;
    }

    public function execute()
    {
        // Edge Case 1: input is shorter than slice index
        if ($this->strlen($this->originalFragment) <= $this->sliceIndex) {
            $this->setShortenedFragment($this->originalFragment);
            $this->setSliceIndex(0);

            return $this;
        }

        // Edge Case 2: slice index is end of string
        if (self::SLICE_AT_END_OF_STRING == $this->sliceIndex) {
            $this->setShortenedFragment('');
            $this->setRemainingFragment($this->originalFragment);

            return $this;
        }

        // Edge Case 3: first "word" (like an URL) is longer than slice max size
        $stripped = trim(strip_tags($this->getOriginalFragment()));
        preg_match('/\S*/', $stripped, $matches);
        if (is_array($matches) && isset($matches[0]) && mb_strlen($matches[0]) > $this->getSliceIndex()) {
            $this->setShortenedFragment('[...]');
            $this->setRemainingFragment($this->originalFragment);

            return $this;
        }

        $parser = new HTML5();
        $this->dom = $parser->loadHTMLFragment($this->originalFragment);

        try {
            $this->walk($this->dom);

            $this->setSliceIndex($this->currentSize);

            $this->remainingList
                ->filter(
                    fn ($nodeCheck) => is_a($nodeCheck, DOMNode::class)
                )
                ->each(
                    function (DOMNode $node) use ($parser) {
                        $this->remainingFragment .= $parser->saveHTML($node);

                        if (!is_null($node->parentNode)) {
                            $node->parentNode->removeChild($node);
                        } else {
                            try {
                                $this->dom->removeChild($node);
                            } catch (DOMException $e) {
                                if ('Not Found Error' !== $e->getMessage()) {
                                    throw $e;
                                }
                            }
                        }
                    }
                );
        } catch (RuntimeException) {
            // TODO: create proper exception?
            // TODO: Log failure with reasonable context (original fragment?) to debug

            $this->setShortenedFragment($this->getOriginalFragment());
            $this->setRemainingFragment('');
        }

        $this->setShortenedFragment($parser->saveHTML($this->dom));

        return $this;
    }

    /**
     * @return bool|int
     */
    protected function strlen($str)
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen((string) $str);
        }

        return strlen((string) $str);
    }

    /**
     * @param string $haystack
     * @param string $needle
     * @param int    $offset
     *
     * @return bool|int
     */
    protected function strpos($haystack, $needle, $offset = 0)
    {
        if (function_exists('mb_strpos')) {
            return mb_strpos($haystack, $needle, $offset);
        }

        return strpos($haystack, $needle, $offset);
    }

    protected function walk(DOMNode $node)
    {
        if ($this->reachedLimit) {
            $this->remainingList->push($node);
        } else {
            if ($node instanceof DOMText) {
                $nodeSize = $this->strlen($node->nodeValue);
                $this->currentSize += $nodeSize;

                if ($this->currentSize > $this->sliceIndex) {
                    $characterDifference = $this->currentSize - $this->sliceIndex - 1;

                    $limitInNode = $nodeSize - $characterDifference;
                    $actualOffset = $this->strpos(wordwrap(trim($node->nodeValue), $limitInNode, "\n", false), "\n");

                    $node->splitText($actualOffset);
                    $this->remainingList->push($node->nextSibling);

                    $this->reachedLimit = true;
                }
            }

            if ($node->hasChildNodes()) {
                foreach ($node->childNodes as $childNode) {
                    $this->walk($childNode);
                }
            }
        }
    }

    public function toArray($except = [])
    {
        return collect(
            [
                'original'   => $this->getOriginalFragment(),
                'shortened'  => $this->getShortenedFragment(),
                'remainder'  => $this->getRemainingFragment(),
                'sliceIndex' => $this->getSliceIndex(),
            ]
        )->except($except)
            ->toArray();
    }

    /**
     * @return string
     */
    public function getShortenedFragment()
    {
        return $this->shortenedFragment;
    }

    /**
     * @param string $shortenedFragment
     *
     * @return HTMLFragmentSlicer
     */
    protected function setShortenedFragment($shortenedFragment)
    {
        $this->shortenedFragment = $shortenedFragment;

        return $this;
    }

    /**
     * @return string
     */
    public function getRemainingFragment()
    {
        return $this->remainingFragment;
    }

    /**
     * @param string $remainingFragment
     *
     * @return HTMLFragmentSlicer
     */
    protected function setRemainingFragment($remainingFragment)
    {
        $this->remainingFragment = $remainingFragment;

        return $this;
    }

    /**
     * @return int
     */
    public function getSliceIndex()
    {
        return $this->sliceIndex;
    }

    /**
     * @param int $sliceIndex
     *
     * @return HTMLFragmentSlicer
     *
     * @throws InvalidArgumentException
     */
    public function setSliceIndex($sliceIndex)
    {
        if (!is_int($sliceIndex)) {
            throw new InvalidArgumentException('Expected integer, got '.gettype($sliceIndex));
        }

        $this->sliceIndex = $sliceIndex;

        return $this;
    }

    /**
     * @return string
     */
    public function getOriginalFragment()
    {
        return $this->originalFragment;
    }

    /**
     * @param string $originalFragment
     *
     * @return HTMLFragmentSlicer
     *
     * @throws InvalidArgumentException
     */
    public function setOriginalFragment($originalFragment)
    {
        if (!is_string($originalFragment)) {
            throw new InvalidArgumentException('Expected string, got '.gettype($originalFragment));
        }

        $this->originalFragment = $originalFragment;

        return $this;
    }
}
