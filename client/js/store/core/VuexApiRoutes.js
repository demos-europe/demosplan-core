/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/*
 * Hardcoded API 1.0 Routes
 */
const api1_0Routes = [
  {
    module: 'Orga',
    action: 'update',
    url: '/1.0/organisation/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'Orga',
    action: 'create',
    url: '/1.0/organisation/'
  },
  {
    module: 'Orga',
    action: 'delete',
    url: '/1.0/organisation/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'role',
    action: 'list',
    url: '/1.0/role'
  },
  {
    module: 'report',
    action: 'list',
    url: '/1.0/reports/{procedureId}/{group}',
    parameters: [
      'procedureId',
      'group'
    ]
  },
  {
    module: 'User',
    action: 'update',
    url: '/1.0/user/{id}',
    parameters: [
      'id'
    ]
  },
  // WARNING: When using api 2.0 route, AdministratableUserResourceType must be used
  {
    module: 'User',
    action: 'list',
    url: '/1.0/user/'
  },
  {
    module: 'User',
    action: 'create',
    url: '/1.0/user/'
  },
  {
    module: 'User',
    action: 'delete',
    url: '/1.0/user/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'Faq',
    action: 'delete',
    url: '/1.0/faq/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'Faq',
    action: 'update',
    url: '/1.0/faq/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'FaqCategory',
    action: 'list',
    url: '/1.0/FaqCategory'
  }
]

const generateApi2_0Routes = (apiModules) => {
  const routes = []

  apiModules.forEach(typeName => {
    routes.push({
      module: typeName,
      action: 'list',
      url: `/2.0/${typeName}`
    })

    routes.push({
      module: typeName,
      action: 'get',
      url: `/2.0/${typeName}/{id}`,
      parameters: [
        'id'
      ]
    })

    routes.push({
      module: typeName,
      action: 'update',
      url: `/2.0/${typeName}/{id}`,
      parameters: [
        'id'
      ]
    })

    routes.push({
      module: typeName,
      action: 'create',
      url: `/2.0/${typeName}`
    })

    routes.push({
      module: typeName,
      action: 'delete',
      url: `/2.0/${typeName}/{id}`,
      parameters: [
        'id'
      ]
    })
  })

  return routes
}

export { api1_0Routes, generateApi2_0Routes }
