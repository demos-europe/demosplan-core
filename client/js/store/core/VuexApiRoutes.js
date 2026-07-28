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
      'id',
    ],
  },
  {
    module: 'Orga',
    action: 'create',
    url: '/1.0/organisation',
  },
  {
    module: 'Orga',
    action: 'delete',
    url: '/1.0/organisation/{id}',
    parameters: [
      'id',
    ],
  },
  {
    module: 'role',
    action: 'list',
    url: '/1.0/role',
  },
  {
    module: 'report',
    action: 'list',
    url: '/1.0/reports/{procedureId}/{group}',
    parameters: [
      'procedureId',
      'group',
    ],
  },
  {
    module: 'User',
    action: 'update',
    url: '/1.0/user/{id}',
    parameters: [
      'id',
    ],
  },
  // WARNING: When using api 2.0 route, AdministratableUserResourceType must be used
  {
    module: 'User',
    action: 'list',
    url: '/1.0/user',
  },
  {
    module: 'User',
    action: 'create',
    url: '/1.0/user',
  },
  {
    module: 'User',
    action: 'delete',
    url: '/1.0/user/{id}',
    parameters: [
      'id',
    ],
  },
  {
    module: 'Faq',
    action: 'delete',
    url: '/1.0/faq/{id}',
    parameters: [
      'id',
    ],
  },
  {
    module: 'Faq',
    action: 'update',
    url: '/1.0/faq/{id}',
    parameters: [
      'id',
    ],
  },
  {
    module: 'FaqCategory',
    action: 'list',
    url: '/1.0/FaqCategory',
  },
]

/*
 * Resources migrated to ApiPlatform
 * Actions not listed keep their /2.0 route
 */
const api3_0Modules = {
  Place: [
    'list',
    'get',
  ],
  AssignableUser: [
    'list',
  ],
}

const crudActions = [
  'list',
  'get',
  'create',
  'update',
  'delete',
]

const itemActions = new Set([
  'get',
  'update',
  'delete',
])

/*
 * Build route with correct version prefix (2.0 or 3.0)
 */
const buildRoute = (version, module, action) => {
  const isItemAction = itemActions.has(action)

  return {
    module,
    action,
    url: `/${version}/${module}${isItemAction ? '/{id}' : ''}`,
    parameters: isItemAction ? ['id'] : [],
  }
}

const generateApi2_0Routes = (apiModules) => apiModules
  .flatMap(typeName => crudActions.map(action => buildRoute('2.0', typeName, action)))

const generateApi3_0Routes = () => Object.entries(api3_0Modules)
  .flatMap(([typeName, actions]) => actions.map(action => buildRoute('3.0', typeName, action)))

/*
 * Resolve the path for a module + action, applying the same precedence as initStore():
 * explicit 1.0 and generated 3.0 routes win over the generic 2.0 one.
 */
const resolveApiRoute = (module, action) => {
  const override = [...api1_0Routes, ...generateApi3_0Routes()]
    .find(route => route.module === module && route.action === action)

  return override ? override.url : buildRoute('2.0', module, action).url
}

/*
 * Full URL for components calling dpApi directly, so they resolve to the same API
 * version as the store does. Keeps the version in api3_0Modules, not in call sites.
 */
const apiUrl = (module, action = 'list', id = null) => {
  const path = resolveApiRoute(module, action)

  if (path.includes('{id}') && !id) {
    throw new Error(`apiUrl: action "${action}" on "${module}" requires an id`)
  }

  // Encode like Routing.generate() does
  return `${Routing.getBaseUrl()}/api${path.replace('{id}', encodeURIComponent(id))}`
}

export {
  api1_0Routes,
  apiUrl,
  generateApi2_0Routes,
  generateApi3_0Routes,
}
