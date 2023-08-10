<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Twig\Extension;

use Carbon\Carbon;
use DateTime;
use Exception;
use Twig\TwigFilter;

class DateExtension extends ExtensionBase
{
    /* (non-PHPdoc)
     * @see AbstractExtension::getFilters()
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('dplanDate', $this->dateFilter(...)),
            new TwigFilter('isoDate', $this->dateIsoFilter(...)),
            new TwigFilter(
                'dplanDateAnnotated',
                $this->annotatingDateFilter(...),
                ['is_safe' => ['html']]
            ),
        ];
    }

    /**
     * Datumsformatierung im Template.
     *
     * @param int|DateTime $timestamp
     * @param string       $format
     *
     * @return string
     */
    public function dateFilter($timestamp, $format = 'd.m.Y')
    {
        $dateResult = '';
        $timestamp = $this->parseInputToTimestamp($timestamp);
        if ('' === $timestamp) {
            return $dateResult;
        }

        try {
            if (is_numeric($timestamp)) {
                $dateResult = date($format, $timestamp);
            }
        } catch (Exception) {
        }

        return $dateResult;
    }

    /**
     * Can be used as `myDate|isoDate` to format a {@link DateTime} object or unix timestamp to an ISO 8601 string.
     *
     * @param DateTime|int $timestamp object or unix timestamp
     */
    public function dateIsoFilter($timestamp)
    {
        return $this->dateFilter($timestamp, DATE_ATOM);
    }

    /**
     * like dplanDate, but with extra spicy <time>-tag wrapping for assistive technologies.
     *
     * @param int|DateTime $timestamp
     * @param string       $format
     *
     * @return string
     */
    public function annotatingDateFilter($timestamp, $format = 'd.m.Y')
    {
        $formattedDate = $this->dateFilter($timestamp, $format);
        $timestamp = $this->parseInputToTimestamp($timestamp);

        if (is_numeric($timestamp)) {
            $isoDate = Carbon::createFromTimestamp(intval($timestamp));
        } else {
            $isoDate = Carbon::parse($timestamp);
        }

        $isoDate = $isoDate->toIso8601String();

        return sprintf('<time datetime="%s">%s</time>', $isoDate, $formattedDate);
    }

    /**
     * Parse arbitrary input to return an timestamp if possible.
     *
     * @param string|int|DateTime|null $timestamp
     *
     * @return bool|int|string
     */
    private function parseInputToTimestamp($timestamp)
    {
        if (null == $timestamp) {
            return '';
        }

        if ('' == $timestamp) {
            return '';
        }

        // Wenn der Timestamp doch ein DateTime-Objekt ist
        if ($timestamp instanceof DateTime) {
            if (0 > $timestamp->getTimestamp()) {
                return '';
            }
            $timestamp = $timestamp->getTimestamp();
        }

        // Versuche ein mysqldatum umzuwandeln
        $date = new DateTime();
        $mysqldate = $date->createFromFormat('Y-m-d\TH:i:sP', $timestamp);
        if ($mysqldate instanceof DateTime) {
            $timestamp = $mysqldate->getTimestamp();
        }

        // Wurde ein Timestamp mit Millisekunden übergeben?
        if (10 < strlen($timestamp)) {
            // schneide die Millisekunden weg
            $timestamp = substr($timestamp, 0, 10);
        }

        return $timestamp;
    }
}
