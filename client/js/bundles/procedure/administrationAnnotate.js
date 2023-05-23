/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_annotate.html.twig
 */

import DpImageAnnotator from '@DpJs/components/procedure/imageAnnotator/DpImageAnnotator'
import { initialize } from '@DpJs/InitVue'

const components = { DpImageAnnotator }
const stores = {}
const apiStores = []

initialize(components, stores, apiStores)
