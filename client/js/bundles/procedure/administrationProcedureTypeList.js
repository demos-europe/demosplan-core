/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_procedure_type_list.html.twig
 */

import { DpCard } from '@demos-europe/demosplan-ui/src'
import { initialize } from '@DpJs/InitVue'

const components = { DpCard }
const stores = {}
const apiStores = []

initialize(components, stores, apiStores)
