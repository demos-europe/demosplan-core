/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * Tests if attributes are defined as expected based on the boolean props they are bound to (e.g. required)
 * @param wrapper {Object} Object containing the mounted component
 * @param formControl {Object} element as returned by wrapper.find()
 * @param attr html element attribute and vue component prop
 * @return {*}
 */
const runBooleanAttrTests = (wrapper, formControl, attr) => describe('ComponentWithBooleanAttrs', () => {
  it(`is ${attr} if ${attr} is set to true`, async () => {
    await wrapper.setProps({ [attr]: true })
    expect(formControl.attributes(attr)).toBeDefined()
  })

  it(`is not ${attr} if ${attr} is set to false`, async () => {
    await wrapper.setProps({ [attr]: false })
    expect(formControl.attributes(attr)).toBeUndefined()
  })
})

/**
 * Tests if attributes with string values are defined as expected based on the string props they are bound to (e.g. placeholder)
 * @param wrapper {Object} Object containing the mounted component
 * @param formControl {Object} element as returned by wrapper.find()
 * @param prop {String} Vue component prop
 * @param val {String} value of the vue component prop
 * @param attr {String} html element attribute, define only if it differs from the prop
 * @return {*}
 */
const runStringAttrTests = (wrapper, formControl, prop, val, attr) => describe('ComponentWithStringAttrs', () => {
  if (attr) {
    it(`has a ${attr} attribute with corresponding value if ${prop} is defined`, async () => {
      await wrapper.setProps({ [prop]: val })
      expect(formControl.attributes(attr)).toBeDefined()
      expect(formControl.attributes(attr)).toEqual(val)
    })

    it(`does not have a ${attr} attribute if ${prop} is not defined`, async () => {
      await wrapper.setProps({ [prop]: '' })
      expect(formControl.attributes(attr)).toBeUndefined()
    })
  } else {
    it(`has a ${prop} attribute with corresponding value if ${prop} is defined`, async () => {
      await wrapper.setProps({ [prop]: val })
      expect(formControl.attributes(prop)).toBeDefined()
      expect(formControl.attributes(prop)).toEqual(val)
    })

    it(`does not have a ${prop} attribute if ${prop} is not defined`, async () => {
      await wrapper.setProps({ [prop]: '' })
      expect(formControl.attributes(prop)).toBeUndefined()
    })
  }
})

// eslint-disable-next-line jest/no-export
export { runBooleanAttrTests, runStringAttrTests }
