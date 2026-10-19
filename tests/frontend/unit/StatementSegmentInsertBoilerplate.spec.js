/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
import StatementSegment from '@DpJs/components/procedure/StatementSegmentsList/StatementSegment'

describe('StatementSegment.insertBoilerplateText', () => {
  let handleInsertText

  const createContext = () => ({
    procedureId: 'procedure-id',
    segment: { id: 'segment-id' },
    canLinkBoilerplate: true,
  })

  beforeEach(() => {
    handleInsertText = vi.fn()
    globalThis.Translator = { trans: vi.fn(key => key) }
    globalThis.dplan = { notify: { error: vi.fn() } }
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('inserts the text as a linked node when permitted', () => {
    const insertBoilerplate = vi.fn(() => true)

    StatementSegment.methods.insertBoilerplateText.call(createContext(), '<p>Text</p>', 'boilerplate-id', insertBoilerplate, handleInsertText)

    expect(insertBoilerplate).toHaveBeenCalledWith('boilerplate-id', '<p>Text</p>')
    expect(handleInsertText).not.toHaveBeenCalled()
  })

  it('falls back to plain text without the permission', () => {
    const context = { ...createContext(), canLinkBoilerplate: false }
    const insertBoilerplate = vi.fn(() => true)

    StatementSegment.methods.insertBoilerplateText.call(context, '<p>Text</p>', 'boilerplate-id', insertBoilerplate, handleInsertText)

    expect(insertBoilerplate).not.toHaveBeenCalled()
    expect(handleInsertText).toHaveBeenCalledWith('<p>Text</p>')
  })

  it('falls back to plain text without a boilerplate id', () => {
    const insertBoilerplate = vi.fn(() => true)

    StatementSegment.methods.insertBoilerplateText.call(createContext(), '<p>Text</p>', '', insertBoilerplate, handleInsertText)

    expect(insertBoilerplate).not.toHaveBeenCalled()
    expect(handleInsertText).toHaveBeenCalledWith('<p>Text</p>')
  })

  it('notifies when the boilerplate is already linked', () => {
    const insertBoilerplate = vi.fn(() => false)

    StatementSegment.methods.insertBoilerplateText.call(createContext(), '<p>Text</p>', 'boilerplate-id', insertBoilerplate, handleInsertText)

    expect(handleInsertText).not.toHaveBeenCalled()
    expect(globalThis.dplan.notify.error).toHaveBeenCalledWith('boilerplate.link.exists')
  })
})
