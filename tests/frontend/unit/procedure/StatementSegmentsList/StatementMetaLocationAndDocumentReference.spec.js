/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
import { createStore } from 'vuex'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'
import StatementMetaLocationAndDocumentReference from '@DpJs/components/procedure/StatementSegmentsList/StatementMeta/StatementMetaLocationAndDocumentReference'
import { vi } from 'vitest'

const mount = ({ initialElementId = '', initialParagraphId = '', initialDocumentId = '' } = {}) => {
  const store = createStore({
    modules: {
      ElementsDetails: {
        namespaced: true,
        state: { items: {} },
        actions: { list: vi.fn(() => Promise.resolve()) },
      },
    },
  })

  const wrapper = shallowMountWithGlobalMocks(StatementMetaLocationAndDocumentReference, {
    props: {
      statement: { id: 'statement-1', attributes: {}, relationships: {} },
      procedureId: 'procedure-1',
      initiallySelectedElementId: initialElementId,
      initiallySelectedParagraphId: initialParagraphId,
      initiallySelectedDocumentId: initialDocumentId,
    },
    global: {
      plugins: [store],
      stubs: {
        DpMapModal: true, // Lazy-loaded component
      },
    },
  })

  return wrapper
}

describe('StatementMetaLocationAndDocumentReference save() payload shape', () => {
  it('serializes the elements relationship as a to-one linkage object, not an array', () => {
    /*
     * The Statement resource exposes `elements` as a to-one relationship
     * (`Statement::element` is a ManyToOne). Sending `data: [{...}]` gets the
     * PATCH rejected by the EDT validator with a 400 "Unexpected field(s) in the
     * context of relationship references". DPLAN-18403 regression guard.
     */
    const wrapper = mount()

    wrapper.setData({ selectedElementId: 'element-1' })

    wrapper.vm.save()

    const emitted = wrapper.emitted('save')

    expect(emitted).toHaveLength(1)

    const [{ relationships }] = emitted[0]

    expect(relationships.elements).toEqual({
      data: { type: 'ElementsDetails', id: 'element-1' },
    })
    expect(Array.isArray(relationships.elements.data)).toBe(false)
  })

  it('omits the elements relationship entirely when no element is selected', () => {
    const wrapper = mount()

    wrapper.setData({ selectedElementId: '' })

    wrapper.vm.save()

    const [{ relationships }] = wrapper.emitted('save')[0]

    expect(relationships.elements).toBeUndefined()
  })

  it('sends paragraphParentId as an attribute when the paragraph changed', () => {
    const wrapper = mount({ initialElementId: 'element-1', initialParagraphId: 'old-para' })

    wrapper.setData({
      selectedElementId: 'element-1',
      selectedParagraphId: 'new-para',
    })

    wrapper.vm.save()

    const [{ attributes, relationships }] = wrapper.emitted('save')[0]

    expect(attributes.paragraphParentId).toBe('new-para')
    expect(relationships.elements).toBeDefined() // Element still present (not cleared by paragraph change)
  })

  it('sends the document relationship as null when the selection was cleared', () => {
    const wrapper = mount({
      initialElementId: 'element-1',
      initialDocumentId: 'doc-1',
    })

    wrapper.setData({
      selectedElementId: 'element-1',
      selectedDocumentId: '',
    })

    wrapper.vm.save()

    const [{ relationships }] = wrapper.emitted('save')[0]

    expect(relationships.document).toEqual({ data: null })
  })
})
