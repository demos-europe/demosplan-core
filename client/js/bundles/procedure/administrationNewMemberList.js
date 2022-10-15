/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_new_member_list.html.twig
 */
import DpAddOrganisationList from '@DemosPlanProcedureBundle/components/admin/DpAddOrganisationList'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = {
  DpAddOrganisationList
}

initialize(components, {}, ['invitableToeb'])
