/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

const runLabelTests = (wrapper) => describe('ComponentWithLabel', () => {
  it('displays a label if label is defined', async () => {
    const label = 'someLabel'
    await wrapper.setProps({ label: label })
    const labelStub = wrapper.find('dp-label-stub')
    expect(labelStub.exists()).toBe(true)
    expect(labelStub.attributes('text')).toBe(label)
  })

  it('does not display a label if label is not defined', async () => {
    const label = ''
    await wrapper.setProps({ label: label })

    const labelStub = wrapper.find('dp-label-stub')
    expect(labelStub.exists()).toBe(false)
  })
})

// eslint-disable-next-line jest/no-export
export { runLabelTests }
