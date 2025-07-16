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

import { DpEditor, dpValidate, hasAnyPermissions, hasPermission } from '@demos-europe/demosplan-ui'
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

const include = ['currentSlug']

if (hasPermission('feature_orga_branding_edit')) {
  include.push('branding')
}

if (hasAnyPermissions(['area_organisations', 'feature_organisation_user_list'])) {
  include.push('statusInCustomers')
}

const apiStores = ['Customer', 'Orga']
const presetStoreModules = {
  Orga: [{
    name: 'Pending',
    defaultQuery: {
      sort: 'name',
      filter: {
        registerStatus: {
          condition: {
            path: 'statusInCustomers.status',
            value: 'Pending'
          }
        }
      },
      include: include.join(),
      group: 'Pending'
    }
  }]
}

initialize(components, stores, apiStores, presetStoreModules).then(() => {
  UrlPreview()
  dpValidate()
})
