/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entry point for development_release_story_threadentry_edit.html.twig
 */

import { DpEditor, DpUploadFiles } from '@demos-europe/demosplan-ui/src'
import { initialize } from '@DpJs/InitVue'

const components = {
  DpEditor,
  DpUploadFiles
}

initialize(components)
