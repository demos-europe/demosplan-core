/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_import.html.twig
 */

import AdministrationImport from '@DemosPlanProcedureBundle/components/AdministrationImport/AdministrationImport'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = { AdministrationImport }
const stores = {}
const apiStores = ['AnnotatedStatementPdf']

initialize(components, stores, apiStores)
