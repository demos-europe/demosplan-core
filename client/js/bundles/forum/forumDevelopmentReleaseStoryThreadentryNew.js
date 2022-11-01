/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entry point for development_release_story_threadentry_new.html.twig
 */

import DpEditor from '@DpJs/components/core/DpEditor/DpEditor'
import DpUploadFiles from '@DpJs/components/core/DpUpload/DpUploadFiles'
import { initialize } from '@DpJs/InitVue'

const components = {
  DpEditor,
  DpUploadFiles
}

initialize(components)
