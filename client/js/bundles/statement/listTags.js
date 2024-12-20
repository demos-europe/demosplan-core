/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for list_tags.html.twig
 */

import { DpContextualHelp, DpUploadFiles } from '@demos-europe/demosplan-ui'
import AnimateById from '@DpJs/lib/shared/AnimateById'
import { initialize } from '@DpJs/InitVue'
import TagsList from '@DpJs/components/tags/TagsList'

const components = {
  DpContextualHelp,
  DpUploadFiles,
  TagsList
}

initialize(components).then(() => {
  AnimateById()
})
