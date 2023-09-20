/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_import.html.twig
 */

import AdministrationImport from '@DpJs/components/procedure/AdministrationImport/AdministrationImport'
import { initialize } from '@DpJs/InitVue'

const components = { AdministrationImport }
const stores = {}
const apiStores = ['AnnotatedStatementPdf']

initialize(components, stores, apiStores)
