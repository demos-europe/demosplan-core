<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Types;

use MyCLabs\Enum\Enum;

/**
 * Class UserFlagKeyType.
 *
 * @psalm-immutable
 */
final class UserFlagKey extends Enum
{
    public const ASSIGNED_TASK_NOTIFICATION = 'assignedTaskNotification';
    public const DRAFT_STATEMENT_SUBMISSION_REMINDER_ENABLED = 'draftStatementSubmissionReminderEnabled';
    public const NO_USER_TRACKING = 'noPiwik';
    public const SUBSCRIBED_TO_NEWSLETTER = 'newsletter';
    public const WANTS_FORUM_NOTIFICATIONS = 'forumNotification';
    public const IS_NEW_USER = 'newUser';
    public const PROFILE_COMPLETED = 'profileCompleted';
    public const ACCESS_CONFIRMED = 'access_confirmed';
    public const INVITED = 'invited';
}
