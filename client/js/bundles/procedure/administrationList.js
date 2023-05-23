/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entry point for administration_list.html.twig
 */

import AdministrationProceduresList from '@DpJs/components/procedure/admin/AdministrationProceduresList'
import { initialize } from '@DpJs/InitVue'

const components = {
  AdministrationProceduresList
}

initialize(components)
