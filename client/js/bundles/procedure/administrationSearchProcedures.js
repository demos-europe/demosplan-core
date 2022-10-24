/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_search_procedures.html.twig
 */
import DpSearchProcedures from '@DemosPlanProcedureBundle/components/DpSearchProcedures'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = { DpSearchProcedures }

initialize(components)
