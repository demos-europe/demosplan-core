/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
import DpBoilerPlate from '@DpJs/components/statement/DpBoilerPlate'
import DpBoilerPlateModal from '@DpJs/components/statement/DpBoilerPlateModal'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

describe('DpBoilerPlate', () => {
  it('emits the boilerplate text and id when a boilerplate is selected', () => {
    const wrapper = shallowMountWithGlobalMocks(DpBoilerPlate, {
      props: {
        boilerPlates: [],
      },
    })

    wrapper.vm.addToTextArea({ id: 'boilerplate-id', text: '<p>Some text</p>', title: 'Some title' })

    expect(wrapper.emitted('boilerplateText:added')).toEqual([['<p>Some text</p>', 'boilerplate-id']])
  })
})

describe('DpBoilerPlateModal', () => {
  const createContext = () => ({
    boilerplateIdToBeAdded: '',
    textToBeAdded: '',
    $emit: jest.fn(),
    resetAndClose: jest.fn(),
  })

  it('stores text and id of the selected boilerplate', () => {
    const context = createContext()

    DpBoilerPlateModal.methods.addBoilerplateText.call(context, '<p>Some text</p>', 'boilerplate-id')

    expect(context.textToBeAdded).toBe('<p>Some text</p>')
    expect(context.boilerplateIdToBeAdded).toBe('boilerplate-id')
  })

  it('falls back to an empty id when none is given', () => {
    const context = createContext()

    DpBoilerPlateModal.methods.addBoilerplateText.call(context, '<p>Some text</p>', undefined)

    expect(context.boilerplateIdToBeAdded).toBe('')
  })

  it('emits insert with text and id on insert', () => {
    const context = createContext()

    context.textToBeAdded = '<p>Some text</p>'
    context.boilerplateIdToBeAdded = 'boilerplate-id'

    DpBoilerPlateModal.methods.insertBoilerPlate.call(context)

    expect(context.$emit).toHaveBeenCalledWith('insert', '<p>Some text</p>', 'boilerplate-id')
    expect(context.resetAndClose).toHaveBeenCalled()
  })
})
