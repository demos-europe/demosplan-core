/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is a generic entrypoint used in all templates where no additional entrypoint specific javascript is needed.
 * See genericBundleValidate.js for a variant including form validation.
 *
 * Never put any additional javascript in here that is not needed in all places where genericBundle is used. If you
 * need additional javascript, the entrypoint should have it's own bundle.
 */

import { initialize } from '@DpJs/InitVue'

initialize()
