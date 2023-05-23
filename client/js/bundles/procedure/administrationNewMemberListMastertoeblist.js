/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_new_member_list_mastertoeblist.html.twig
 */
import DpMasterToebList from '@DpJs/components/user/DpMasterToebList'
import { initialize } from '@DpJs/InitVue'

const components = {
  DpMasterToebList
}

initialize(components)
