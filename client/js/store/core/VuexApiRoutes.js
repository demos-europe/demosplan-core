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
    module: 'branding',
    action: 'list',
    url: '/2.0/Branding'
  },
  {
    module: 'branding',
    action: 'update',
    url: '/2.0/Branding/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'customer',
    action: 'create',
    url: '/2.0/Customer'
  },
  {
    module: 'customer',
    action: 'list',
    url: '/2.0/Customer'
  },
  {
    module: 'customer',
    action: 'delete',
    url: '/2.0/Customer/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'customer',
    action: 'update',
    url: '/2.0/Customer/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'customerContact',
    action: 'create',
    url: '/2.0/CustomerContact'
  },
  {
    module: 'customerContact',
    action: 'delete',
    url: '/2.0/CustomerContact/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'customerContact',
    action: 'list',
    url: '/2.0/CustomerContact'
  },
  {
    module: 'customerContact',
    action: 'update',
    url: '/2.0/CustomerContact/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'customerLoginSupportContact',
    action: 'create',
    url: '/2.0/CustomerLoginSupportContact'
  },
  {
    module: 'customerLoginSupportContact',
    action: 'delete',
    url: '/2.0/CustomerLoginSupportContact/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'customerLoginSupportContact',
    action: 'list',
    url: '/2.0/CustomerLoginSupportContact'
  },
  {
    module: 'customerLoginSupportContact',
    action: 'update',
    url: '/2.0/CustomerLoginSupportContact/{id}',
    parameters: [
      'id'
    ]
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
    module: 'user',
    action: 'list',
    url: '/1.0/user/'
  },
  {
    module: 'user',
    action: 'get',
    url: '/1.0/user/{userId}',
    parameters: [
      'userId'
    ]
  },
  {
    module: 'user',
    action: 'update',
    url: '/1.0/user/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'user',
    action: 'create',
    url: '/1.0/user/'
  },
  {
    module: 'user',
    action: 'delete',
    url: '/1.0/user/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'department',
    action: 'list',
    url: '/2.0/Department'
  },
  {
    module: 'assignableUser',
    action: 'list',
    url: '/2.0/AssignableUser'
  },
  {
    module: 'orga',
    action: 'list',
    url: '/2.0/Orga'
  },
  {
    module: 'orga',
    action: 'update',
    url: '/1.0/organisation/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'orga',
    action: 'create',
    url: '/1.0/organisation/'
  },
  {
    module: 'orga',
    action: 'delete',
    url: '/1.0/organisation/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'role',
    action: 'list',
    url: '/1.0/role/'
  },
  {
    module: 'faqCategory',
    action: 'list',
    url: '/1.0/faq-category/'
  },
  {
    module: 'faq',
    action: 'list',
    url: '/1.0/faq/'
  },
  {
    module: 'faq',
    action: 'delete',
    url: '/1.0/faq/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'faq',
    action: 'update',
    url: '/1.0/faq/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'invitableToeb',
    action: 'list',
    url: '/2.0/InvitableToeb'
  },
  {
    module: 'invitableInstitution',
    action: 'list',
    url: '/2.0/InvitableInstitution'
  },
  {
    module: 'invitableInstitution',
    action: 'update',
    url: '/2.0/InvitableInstitution/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'place',
    action: 'list',
    url: '/2.0/Place'
  },
  {
    module: 'segmentComment',
    action: 'list',
    url: '/2.0/SegmentComment'
  },
  {
    module: 'statementSegment',
    action: 'list',
    url: '/2.0/StatementSegment'
  },
  {
    module: 'statementSegment',
    action: 'update',
    url: '/2.0/StatementSegment/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'statement',
    action: 'list',
    url: '/2.0/Statement'
  },
  {
    module: 'statement',
    action: 'get',
    url: '/2.0/Statement/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'statement',
    action: 'update',
    url: '/2.0/Statement/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'statement',
    action: 'delete',
    url: '/2.0/Statement/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'statementAttachment',
    action: 'create',
    url: '/2.0/StatementAttachment'
  },
  {
    module: 'tag',
    action: 'list',
    url: '/2.0/Tag'
  },
  {
    module: 'institutionTag',
    action: 'create',
    url: '/2.0/InstitutionTag'
  },
  {
    module: 'institutionTag',
    action: 'list',
    url: '/2.0/InstitutionTag'
  },
  {
    module: 'institutionTag',
    action: 'update',
    url: '/2.0/InstitutionTag/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'institutionTag',
    action: 'delete',
    url: '/2.0/InstitutionTag/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'tagTopic',
    action: 'list',
    url: '/2.0/TagTopic'
  },
  {
    module: 'elements',
    action: 'list',
    url: '/2.0/Elements'
  },
  {
    module: 'elements',
    action: 'update',
    url: '/2.0/Elements/{id}',
    parameters: [
      'id'
    ]
  },
  {
    module: 'elements',
    action: 'delete',
    url: '/2.0/Elements/{id}',
    parameters: [
      'id'
    ]
  }
]
