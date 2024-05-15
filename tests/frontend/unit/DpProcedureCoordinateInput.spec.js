/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import DpProcedureCoordinateInput from '@DpJs/components/procedure/basicSettings/DpProcedureCoordinateInput'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

describe('DpProcedureCoordinateInput', () => {
  it('button should be enabled with valid input from props', () => {
    const instance = shallowMountWithGlobalMocks(DpProcedureCoordinateInput, {})
    expect(instance.html()).toEqual(expect.not.stringContaining('class="btn--disabled"'))
  })
})
