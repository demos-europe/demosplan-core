<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

class HtmlSanitizerService
{
    public function escapeDisallowedTags(string $inputString): string
    {
        $allowedTags = '<!DOCTYPE><a href><a></a><abbr></abbr><address></address><area><article></article><aside></aside><audio></audio><b></b><base><bdi></bdi><bdo></bdo><blockquote></blockquote><body></body><br><button></button><canvas></canvas><caption></caption><cite></cite><code></code><col><colgroup></colgroup><data></data><datalist></datalist><dd></dd><del></del><details></details><dfn></dfn><dialog></dialog><div></div><dl></dl><dt></dt><em></em><embed><fieldset></fieldset><figcaption></figcaption><figure></figure><footer></footer><form></form><h1></h1><h2></h2><h3></h3><h4></h4><h5></h5><h6></h6><head></head><header></header><hr><html></html><i></i><iframe></iframe><img><input><ins></ins><kbd></kbd><label></label><legend></legend><li></li><link><main></main><map></map><mark></mark><meta><meter></meter><nav></nav><noscript></noscript><object></object><ol></ol><optgroup></optgroup><option></option><output></output><p></p><param><picture></picture><pre></pre><progress></progress><q></q><rp></rp><rt></rt><ruby></ruby><s></s><samp></samp><script></script><section></section><select></select><small></small><source><span></span><strong></strong><style></style><sub></sub><summary></summary><sup></sup><table></table><tbody></tbody><td></td><template></template><textarea></textarea><tfoot></tfoot><th></th><thead></thead><time></time><title></title><tr></tr><track><u></u><ul></ul><var></var><video></video><wbr>';

        $inputString = htmlspecialchars($inputString, ENT_NOQUOTES, 'UTF-8');

        // Convert the $allowed_tags string to an array of original HTML tags
        $allowedTagsArray = explode('><', trim($allowedTags, '<>'));
        $allowedTagsArray = array_map(fn ($tag) => '<'.$tag.'>', $allowedTagsArray);

        // Create a map of encoded tags to decoded tags
        $encodedToDecodedMap = [];
        foreach ($allowedTagsArray as $tag) {
            $encodedTag = htmlspecialchars($tag);
            $encodedToDecodedMap[$encodedTag] = $tag;
        }

        // Decode allowed tags in the input string
        $decodedString = strtr($inputString, $encodedToDecodedMap);

        $tagsWithAttributeDrivenContent = ['a href', 'img'];

        // Decode Tags with Attribute Driven Content in the input string
        foreach ($tagsWithAttributeDrivenContent as $str) {
            // Create a pattern to find the specific HTML entities before and after the targeted strings
            $pattern = '/&lt;('.preg_quote($str, '/').'[^&]*)&gt;/';

            // Replace using a callback to conditionally replace the entities
            $decodedString = preg_replace_callback($pattern,
                static fn ($matches) => '<'.$matches[1].'>', (string) $decodedString
            );
        }

        return $decodedString;
    }
}
