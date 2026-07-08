<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use DateTimeInterface;

/**
 * Single source of truth for the cross-field rule shared by the statement create and edit routes:
 * the authoredDate (Verfassungsdatum) must not be later than the submittedDate (Einreichungsdatum).
 *
 * Callers are responsible for parsing their own input (raw request params vs. statement array) and
 * for deciding whether the check applies at all (e.g. the edit route skips it when neither date
 * changed, so a Bestandsstatement with pre-existing invalid dates stays editable). The actual
 * comparison lives here so it cannot drift between the two routes.
 */
class StatementDateOrderValidator
{
    /**
     * Day-precise comparison. If either date is missing/unparseable there is nothing to compare,
     * so the order is considered valid.
     */
    public function isAuthoredAfterSubmitted(?DateTimeInterface $authored, ?DateTimeInterface $submitted): bool
    {
        if (!$authored instanceof DateTimeInterface || !$submitted instanceof DateTimeInterface) {
            return false;
        }

        return $authored->format('Ymd') > $submitted->format('Ymd');
    }
}
