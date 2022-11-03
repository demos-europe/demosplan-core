/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { hasAllPermissions, hasAnyPermissions, hasPermission } from 'demosplan-utils'

window.dplan = {
  permissions: {
    myPermission: true,
    notMyPermission: false,
    someOtherPremission: true,
    andIcanAccessEverything: true,
    youShallNotPass: false
  }
}

describe.each([
  { permission: 'myPermission', result: true },
  { permission: undefined, result: false },
  { permission: null, result: false },
  { permission: 'notIntheList', result: false },
  { permission: ['myPermission'], result: true },
  { permission: ['myPermission', 'someOtherPremission'], result: false }
])('hasPermission - check access rights for one permission', ({ permission, result }) => {
  test(`returns ${result} for testcase with prop ${permission}`, () => {
    expect(hasPermission(permission)).toBe(result)
  })
})

describe.each([
  { permission: ['myPermission', 'notHere', 'andNothingHere'], result: true },
  { permission: ['myPermission', 'andIcanAccessEverything', 'someOtherPremission'], result: true },
  { permission: ['youShallNotPass', 'notMyPermission'], result: false }
])('hasPermission - check any access rights', ({ permission, result }) => {
  test(`returns '${result}' for testcase with prop '${permission}'`, () => {
    expect(hasAnyPermissions(permission)).toBe(result)
  })
})

describe.each([
  { permission: null, result: 'error' },
  { permission: undefined, result: 'error' }
])('hasPermission - check any access rights', ({ permission, result }) => {
  test(`returns 'Error' for testcase with '${permission}'`, () => {
    expect(() => hasAnyPermissions(permission)).toThrow('Typeof "permissions" is not an Array')
  })
})

describe.each([
  { permission: ['myPermission', 'notHere', 'andNothingHere'], result: false },
  { permission: ['myPermission', 'andIcanAccessEverything', 'someOtherPremission'], result: true },
  { permission: ['youShallNotPass', 'notMyPermission'], result: false }
])('hasPermission - check all access rights', ({ permission, result }) => {
  test(`returns '${result}' for testcase with prop '${permission}'`, () => {
    expect(hasAllPermissions(permission)).toBe(result)
  })
})

describe.each([
  { permission: null, result: 'error' },
  { permission: undefined, result: 'error' }
])('hasPermission - check all access rights', ({ permission, result }) => {
  test(`returns 'Error' for testcase with '${permission}'`, () => {
    expect(() => hasAllPermissions(permission)).toThrow('Typeof "permissions" is not an Array')
  })
})
