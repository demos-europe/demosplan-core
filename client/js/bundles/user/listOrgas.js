/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for list_orgas.html.twig
 */

import { DpEditor, dpValidate } from '@demos-europe/demosplan-ui/src'
import DpCreateItem from '@DpJs/components/user/DpCreateItem'
import DpOrganisationList from '@DpJs/components/user/DpOrganisationList/DpOrganisationList'
import { initialize } from '@DpJs/InitVue'
import UrlPreview from '@DpJs/lib/shared/UrlPreview'

const stores = {}
const components = {
  DpCreateItem,
  DpOrganisationList,
  DpEditor
}

const apiStores = ['orga', 'customer']
const presetStoreModules = {
  orga: [{
    name: 'pending',
    defaultQuery: {
      sort: 'name',
      filter: {
        registerStatus: {
          condition: {
            path: 'statusInCustomers.status',
            value: 'pending'
          }
        }
      },
      include: ['branding', 'currentSlug', 'customers', 'statusInCustomers'].join(),
      group: 'pending'
    }
  }]
}

initialize(components, stores, apiStores, presetStoreModules).then(() => {
  UrlPreview()
  dpValidate()
})
