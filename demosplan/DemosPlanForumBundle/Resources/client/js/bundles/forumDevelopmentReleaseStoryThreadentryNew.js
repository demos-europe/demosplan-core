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

import DpTiptap from '@DemosPlanCoreBundle/components/DpTiptap'
import DpUploadFiles from '@DemosPlanCoreBundle/components/DpUpload/DpUploadFiles'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = {
  DpTiptap,
  DpUploadFiles
}

initialize(components)
