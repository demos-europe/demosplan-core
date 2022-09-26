/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for list_orgas.html.twig
 */

import DpCreateItem from '../components/DpCreateItem'
import DpOrganisationList from '@DemosPlanUserBundle/components/DpOrganisationList'
import DpTiptap from '@DpJs/components/core/DpTiptap'
import dpValidate from '@DpJs/lib/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import UrlPreview from '../lib/UrlPreview'

const stores = {}
const components = {
  DpCreateItem,
  DpOrganisationList,
  DpTiptap
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
