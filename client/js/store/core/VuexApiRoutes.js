/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

export const VuexApiRoutes = [
  {
    module: 'Branding',
    action: 'list',
    url: '/2.0/Branding'
  },
  {
    module: 'Branding',
    action: 'update',
    url: '/2.0/Branding/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'Customer',
    action: 'create',
    url: '/2.0/Customer'
  },
  {
    module: 'Customer',
    action: 'list',
    url: '/2.0/Customer'
  },
  {
    module: 'Customer',
    action: 'delete',
    url: '/2.0/Customer/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'Customer',
    action: 'update',
    url: '/2.0/Customer/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'CustomerContact',
    action: 'create',
    url: '/2.0/CustomerContact'
  },
  {
    module: 'CustomerContact',
    action: 'delete',
    url: '/2.0/CustomerContact/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'CustomerContact',
    action: 'list',
    url: '/2.0/CustomerContact'
  },
  {
    module: 'CustomerContact',
    action: 'update',
    url: '/2.0/CustomerContact/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'CustomerLoginSupportContact',
    action: 'create',
    url: '/2.0/CustomerLoginSupportContact'
  },
  {
    module: 'CustomerLoginSupportContact',
    action: 'delete',
    url: '/2.0/CustomerLoginSupportContact/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'CustomerLoginSupportContact',
    action: 'list',
    url: '/2.0/CustomerLoginSupportContact'
  },
  {
    module: 'CustomerLoginSupportContact',
    action: 'update',
    url: '/2.0/CustomerLoginSupportContact/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'Report',
    action: 'list',
    url: '/1.0/reports/{procedureId}/{group}',
    parameters: [
      'procedureId',
      'group'
    ]
  },
  {
    module: 'User',
    action: 'list',
    url: '/1.0/user/'
  },
  {
    module: 'User',
    action: 'get',
    url: '/1.0/user/{userId}',
    parameters: [
      'userId'
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
    module: 'Department',
    action: 'list',
    url: '/2.0/Department'
  },
  {
    module: 'AssignableUser',
    action: 'list',
    url: '/2.0/AssignableUser'
  },
  {
    module: 'Orga',
    action: 'list',
    url: '/2.0/Orga'
  },
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
    module: 'Role',
    action: 'list',
    url: '/1.0/role/'
  },
  {
    module: 'FaqCategory',
    action: 'list',
    url: '/1.0/faq-category/'
  },
  {
    module: 'Faq',
    action: 'list',
    url: '/1.0/faq/'
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
    module: 'InvitableToeb',
    action: 'list',
    url: '/2.0/InvitableToeb'
  },
  {
    module: 'InvitableInstitution',
    action: 'list',
    url: '/2.0/InvitableInstitution'
  },
  {
    module: 'InvitableInstitution',
    action: 'update',
    url: '/2.0/InvitableInstitution/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'Place',
    action: 'list',
    url: '/2.0/Place'
  },
  {
    module: 'SegmentComment',
    action: 'list',
    url: '/2.0/SegmentComment'
  },
  {
    module: 'StatementSegment',
    action: 'list',
    url: '/2.0/StatementSegment'
  },
  {
    module: 'StatementSegment',
    action: 'update',
    url: '/2.0/StatementSegment/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'Statement',
    action: 'list',
    url: '/2.0/Statement'
  },
  {
    module: 'Statement',
    action: 'get',
    url: '/2.0/Statement/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'Statement',
    action: 'update',
    url: '/2.0/Statement/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'Statement',
    action: 'delete',
    url: '/2.0/Statement/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'StatementAttachment',
    action: 'create',
    url: '/2.0/StatementAttachment'
  },
  {
    module: 'Tag',
    action: 'list',
    url: '/2.0/Tag'
  },
  {
    module: 'InstitutionTag',
    action: 'create',
    url: '/2.0/InstitutionTag'
  },
  {
    module: 'InstitutionTag',
    action: 'list',
    url: '/2.0/InstitutionTag'
  },
  {
    module: 'InstitutionTag',
    action: 'update',
    url: '/2.0/InstitutionTag/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'InstitutionTag',
    action: 'delete',
    url: '/2.0/InstitutionTag/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'TagTopic',
    action: 'list',
    url: '/2.0/TagTopic'
  },
  {
    module: 'Elements',
    action: 'list',
    url: '/2.0/Elements'
  },
  {
    module: 'Elements',
    action: 'update',
    url: '/2.0/Elements/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'Elements',
    action: 'delete',
    url: '/2.0/Elements/{id}',
    parameters: [
      'id'
    ]
  }
]
