<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;

class EditorService extends CoreService
{
    final public const EXISTING_FIELD_FILTER = '*';
    final public const KEINE_ZUORDNUNG = 'keinezuordnung';
    final public const EMPTY_FIELD = 'no_value';

    final public const OBSCURE_TAG_OPEN = '<dp-obscure>';
    final public const OBSCURE_TAG_CLOSE = '</dp-obscure>';
    final public const EDITOR_ALTERNATIVE_TEXT_TAG_OPEN = ' {';
    final public const EDITOR_ALTERNATIVE_TEXT_TAG_OPEN_INACCURATE = '{'; // this is a wrong but likely input by the user which we still accept
    final public const EDITOR_ALTERNATIVE_TEXT_TAG_CLOSE = '}';
    final public const HTML_ALTERNATIVE_TEXT_TAG_OPEN = '&alt="';
    final public const HTML_ALTERNATIVE_TEXT_TAG_CLOSE = '"';
    final public const HTML_ALTERNATIVE_TEXT_PLACEHOLDER = 'Hier können Sie Ihren alternativen Text einfügen.';
    final public const EDITOR_IMAGE_PLACEHOLDER = '[An dieser Stelle wird ihr Bild angezeigt]';

    // Note that changing the following two values alone is not sufficient, please find and replace as well.
    final public const IMAGE_ID_OPENING_TAG = '<!-- #Image-';
    final public const IMAGE_ID_CLOSING_TAG = '-->';

    public function __construct(private readonly MessageBagInterface $messageBag)
    {
    }

    /**
     * Adds placeholder for images, is part of preparing DB data to Editor output.
     */
    public function addImagePlaceholdersToStringFromDatabase(string $text): string
    {
        // prevents accidential doubles
        if (strpos($text, (string) $this::EDITOR_IMAGE_PLACEHOLDER)) {
            return $text;
        }

        $imageComments = explode($this::IMAGE_ID_OPENING_TAG, $text);

        if (0 < count($imageComments)) {
            $text = implode($this::EDITOR_IMAGE_PLACEHOLDER.$this::IMAGE_ID_OPENING_TAG, $imageComments);
        }

        return $text;
    }

    /**
     * Checks if there are one or more alternative text tag in data from the editor.
     * Alt text placeholders are also counted as valid.
     */
    public function alternativeTextExistsInStringFromEditor(string $text): bool
    {
        return is_int(strpos($text, (string) $this::EDITOR_ALTERNATIVE_TEXT_TAG_OPEN_INACCURATE));
    }

    /**
     * Extracts alt text from editor text.
     * Requires that there is exactly one alternative text in the string.
     */
    public function extractAlternativeTextFromEditorText(string $text): string
    {
        $alternativeTextPositions = $this->getAlternativeTextPositionsArrayFromEditorTag($text);

        return substr($text, $alternativeTextPositions['start'], $alternativeTextPositions['length']);
    }

    /**
     * Returns parameters of the editor's alternative text tag.
     *
     * @return array|null three integers with the keys "start", "end", "length"
     */
    public function getAlternativeTextPositionsArrayFromEditorTag(string $text)
    {
        // validation: return null if no alternative text attached (user has deleted it)
        if (!strpos($text, (string) $this::EDITOR_ALTERNATIVE_TEXT_TAG_OPEN_INACCURATE)) {
            return null;
        }

        $startPosition = strpos($text, (string) $this::EDITOR_ALTERNATIVE_TEXT_TAG_OPEN_INACCURATE) + strlen((string) $this::EDITOR_ALTERNATIVE_TEXT_TAG_OPEN_INACCURATE);
        $endPosition = strpos($text, (string) $this::EDITOR_ALTERNATIVE_TEXT_TAG_CLOSE);

        $length = $endPosition - $startPosition;

        $output = [
            'start'  => $startPosition,
            'end'    => $endPosition,
            'length' => $length,
        ];

        if (is_numeric($startPosition) && is_numeric($endPosition) && $length > 0) {
            return $output;
        }

        return null;
    }

    /**
     * Returns parameters of the html alternative text tag.
     *
     * @return array|null three integers with the keys "start", "end", "length"
     */
    public function getAlternativeTextPositionsArrayFromHtmlTag(string $text)
    {
        // return null in case of text without image
        if (!str_contains($text, (string) $this::HTML_ALTERNATIVE_TEXT_TAG_OPEN)) {
            return null;
        }

        $startPosition = strpos($text, (string) $this::HTML_ALTERNATIVE_TEXT_TAG_OPEN) +
            strlen((string) $this::HTML_ALTERNATIVE_TEXT_TAG_OPEN);
        $endPosition = strpos($text, (string) $this::HTML_ALTERNATIVE_TEXT_TAG_CLOSE, $startPosition);
        $length = $endPosition - $startPosition;

        $output = [
            'start'  => $startPosition,
            'end'    => $endPosition,
            'length' => $length,
        ];
        if (is_numeric($startPosition) && is_numeric($endPosition) && $length > 0) {
            return $output;
        }

        return null;
    }

    /**
     * Will obscure tagged parts of given string, unless the $executeObscuring is given as false.
     * In both cases the tags in the given string will be removed!
     *
     * @param string $string           string to handle
     * @param bool   $executeObscuring determines if the given string will be obscured or not
     *
     * @return string - string without obscure tags. Will be obscured if $executeObscuring was given as true,
     *                otherwise the result will not be obscured.
     */
    public function handleObscureTags($string, $executeObscuring = true)
    {
        return $executeObscuring ? $this->obscureString($string) : $this->removeObscureTags($string);
    }

    /**
     * Checks if the given string includes the defined OBSCURE_TAG_OPEN AND OBSCURE_TAG_CLOSE.
     *
     * @param string $haystack - String in which will be searched for the defined tags
     *
     * @return bool - true if, opening AND closing obscure tag was found in text, otherwise false
     */
    public function hasObscuredText(string $haystack): bool
    {
        $obscureStart = $this::OBSCURE_TAG_OPEN;
        $obscureEnd = $this::OBSCURE_TAG_CLOSE;
        $openingTagFound = strpos($haystack, (string) $obscureStart);
        $closingTagFound = strpos($haystack, (string) $obscureEnd);

        return $openingTagFound && $closingTagFound;
    }

    /**
     * Will replace all characters covered by obscureTags, with "█", except of spaces.
     *
     * Obscure given String
     *
     * @param string $string
     *
     * @return string
     */
    public function obscureString($string)
    {
        $result = '';
        $offset = 0;
        $depth = 0;
        $inLength = strlen($string);
        $openTagLength = strlen((string) $this::OBSCURE_TAG_OPEN);
        $closeTagLength = strlen((string) $this::OBSCURE_TAG_CLOSE);

        while ($offset < $inLength) {
            if ('<' == $string[$offset]) {
                if (substr($string, $offset, $openTagLength) == $this::OBSCURE_TAG_OPEN) {
                    ++$depth;
                    $offset += $openTagLength;
                    continue;
                } elseif (substr($string, $offset, $closeTagLength) == $this::OBSCURE_TAG_CLOSE) {
                    --$depth;
                    $offset += $closeTagLength;
                    continue;
                }
            }
            if ($depth > 0) {
                $result .= ' ' == $string[$offset] ? ' ' : '█';
            } else {
                $result .= $string[$offset];
            }
            ++$offset;
        }

        return $result;
    }

    /**
     * Remove $this::OBSCURE_TAG_OPEN and $this::OBSCURE_TAG_CLOSE tags from the given string.
     *
     * @param string $string string to clean
     *
     * @return string - cleaned string
     *
     * T6679: remove own obscure tags for conversion
     * to avoid deleting text (& issues in general) on conversion to word
     */
    public function removeObscureTags($string)
    {
        $halfCleanedString = str_replace($this::OBSCURE_TAG_OPEN, '', $string);

        return str_replace($this::OBSCURE_TAG_CLOSE, '', $halfCleanedString);
    }

    /**
     * Handles the replacing of the Editor's alternative text placeholder to store in DB.
     *
     * @return mixed|string
     *
     * @throws MessageBagException
     */
    public function replaceAlternativeTextPlaceholderByHTMLTag(string $text)
    {
        $textImageElementArray = $this->separateTextFromEditorIntoImageElements($text);

        for ($i = 0, $iMax = count($textImageElementArray); $i < $iMax; ++$i) {
            if ($this->alternativeTextExistsInStringFromEditor($textImageElementArray[$i])) {
                $customAlternativeText = $this->extractAlternativeTextFromEditorText($textImageElementArray[$i]);
                $textImageElementArray[$i] = $this->removeEditorAlternativeTextPlaceholder($textImageElementArray[$i], $customAlternativeText);

                // remove unwanted characters
                if (str_contains($customAlternativeText, '&amp')) {
                    $customAlternativeText = str_replace('&amp', '', $customAlternativeText);
                    $this->messageBag->add('warning', 'warning.char.removed', ['character' => '&']);
                }
                if (str_contains($customAlternativeText, '"')) {
                    $customAlternativeText = str_replace('"', '\'', $customAlternativeText);
                    $this->messageBag->add('warning', 'warning.char.replaced.by', ['character' => '"', 'replacement' => '\'']);
                }

                // stored alternative text: $customAlternativeText
                $this->messageBag->add('confirm', 'confirm.alternative.text', ['alternativeText' => $customAlternativeText]);

                // Don't store placeholders in the database
                if ($customAlternativeText !== $this::HTML_ALTERNATIVE_TEXT_PLACEHOLDER) {
                    $textImageElementArray[$i] = $this->setAlternativeTextHTMLCommentTag($textImageElementArray[$i], $customAlternativeText);
                }
            }
        }

        return implode('', $textImageElementArray);
    }

    /**
     * Remove alternative text incl. placeholder to avoid store placeholder in DB.
     */
    public function removeEditorAlternativeTextPlaceholder(string $text, string $alternativeText): string
    {
        return str_replace(
            [
                // correct editor alt text opening tag
                $this::EDITOR_ALTERNATIVE_TEXT_TAG_OPEN.$alternativeText.$this::EDITOR_ALTERNATIVE_TEXT_TAG_CLOSE,
                // incorrect editor alt text opening tag
                $this::EDITOR_ALTERNATIVE_TEXT_TAG_OPEN_INACCURATE.$alternativeText.$this::EDITOR_ALTERNATIVE_TEXT_TAG_CLOSE,
            ],
            '',
            $text
        );
    }

    public function replaceHtmlAltTextTagByAlternativeTextPlaceholder(string $text): string
    {
        $imageComments = explode($this::IMAGE_ID_OPENING_TAG, $text);

        if (0 < count($imageComments)) {
            foreach ($imageComments as $imageComment) {
                // if image existing
                if (strpos($imageComment, (string) $this::IMAGE_ID_CLOSING_TAG)) {
                    $imageId = substr($imageComment, 0, 36);

                    // get alt text
                    $altTextParameters = $this->getAlternativeTextPositionsArrayFromHtmlTag($imageComment);
                    $pureAltText = $this::HTML_ALTERNATIVE_TEXT_PLACEHOLDER;
                    if (is_array($altTextParameters)) {
                        $pureAltText = substr($imageComment, $altTextParameters['start'], $altTextParameters['length']);
                    }

                    // add alt text editor tag, so that users see it in editor
                    $altTagEditorTag = $this::EDITOR_ALTERNATIVE_TEXT_TAG_OPEN.$pureAltText.$this::EDITOR_ALTERNATIVE_TEXT_TAG_CLOSE;
                    $text = str_replace(
                        $this::IMAGE_ID_OPENING_TAG.$imageId,
                        $altTagEditorTag.$this::IMAGE_ID_OPENING_TAG.$imageId,
                        $text
                    );

                    // Remove alt text tag in html comment. It is being reset every time the user submits text.
                    $altHtmlTag = $this::HTML_ALTERNATIVE_TEXT_TAG_OPEN.$pureAltText.$this::HTML_ALTERNATIVE_TEXT_TAG_CLOSE;
                    $text = str_replace(
                        $altHtmlTag,
                        '',
                        $text
                    );
                }
            }
        }

        return $text;
    }

    /**
     * Turns a string of texts and images into string array in which each element ends with an image.
     *
     * @param string $text Must include at least one image!
     */
    public function separateTextFromEditorIntoImageElements(string $text): array
    {
        $textArray = explode($this::IMAGE_ID_CLOSING_TAG, $text);

        foreach ($textArray as $i => $iValue) {
            if (str_contains($iValue, (string) $this::IMAGE_ID_OPENING_TAG)) {
                $textArray[$i] .= $this::IMAGE_ID_CLOSING_TAG;
            }
        }

        return $textArray;
    }

    /**
     * Add $alternativeText to given $text
     * This method can only handle exactly one image tag.
     */
    public function setAlternativeTextHTMLCommentTag(string $text, string $alternativeText): string
    {
        // Prevents that the dashes used in the alt closing tag are recognized as part of the comment content.
        // Is reversed below.
        $temporaryHtmlAltTextClosingTag = '>'.$this::IMAGE_ID_CLOSING_TAG;
        $text = str_replace($this::IMAGE_ID_CLOSING_TAG, $temporaryHtmlAltTextClosingTag, $text);

        preg_match(
            '|'.$this::IMAGE_ID_OPENING_TAG.'([a-z0-9&=\-]*)|',
            $text,
            $imageMatch
        );

        if (2 !== count($imageMatch)) { // Keep in mind that preg_match finds n+1 elements for n matched patterns, so 2 ~ 1 matches.
            throw new InvalidArgumentException('Given string has to contain exactly one image tag (pattern: |'.$this::IMAGE_ID_OPENING_TAG.'([a-z0-9&=\-]*)|). Contains '.(count($imageMatch) - 1).': '.$text);
        }

        $replacement = $imageMatch[0].
            $this::HTML_ALTERNATIVE_TEXT_TAG_OPEN.$alternativeText.$this::HTML_ALTERNATIVE_TEXT_TAG_CLOSE;

        return str_replace(
            [$imageMatch[0], $temporaryHtmlAltTextClosingTag],
            // Resets the html comment closing tag to it's state, see beginning of method above.
            [$replacement, $this::IMAGE_ID_CLOSING_TAG],
            $text
        );
    }
}
