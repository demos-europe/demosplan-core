/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entry point for list_subscriptions.html.twig
 */

import { initialize } from '@DpJs/InitVue'
import ListSubscriptions from '@DpJs/components/procedure/listSubscriptions/ListSubscriptions'

const components = { ListSubscriptions }

initialize(components)
