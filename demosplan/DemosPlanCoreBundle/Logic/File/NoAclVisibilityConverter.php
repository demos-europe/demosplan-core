<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\File;

use League\Flysystem\AwsS3V3\VisibilityConverter;
use League\Flysystem\Visibility;

/**
 * Visibility converter for IONOS S3-compatible storage.
 *
 * IONOS Cloud S3 requires ACLs to be set (unlike AWS S3 which supports disabling ACLs).
 * This converter always sets files to 'private' ACL for security.
 * Access control should be managed through bucket policies and IAM policies in addition to ACLs.
 */
class NoAclVisibilityConverter implements VisibilityConverter
{
    public function visibilityToAcl(string $visibility): string
    {
        // IONOS requires a valid ACL - always use 'private' for security
        return 'private';
    }

    public function aclToVisibility(array $grants): string
    {
        // Since we don't use ACLs, always return private
        return Visibility::PRIVATE;
    }

    public function defaultForDirectories(): string
    {
        return Visibility::PRIVATE;
    }
}
