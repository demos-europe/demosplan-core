/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for list_statements.html.twig
 */

import { initialize } from '@DemosPlanCoreBundle/InitVue'
import ListStatements from '@DemosPlanStatementBundle/components/listStatements/ListStatements'

const components = {
  ListStatements
}
const apiStores = ['assignableUser', 'statement']

initialize(components, {}, apiStores)
