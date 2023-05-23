/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_convert_annotated_pdf.html.twig
 */

import DpConvertAnnotatedPdf from '@DpJs/components/procedure/imageAnnotator/DpConvertAnnotatedPdf'
import { initialize } from '@DpJs/InitVue'

const components = { DpConvertAnnotatedPdf }
const stores = {}
const apiStores = []

initialize(components, stores, apiStores)
