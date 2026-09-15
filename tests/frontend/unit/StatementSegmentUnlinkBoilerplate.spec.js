/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
import StatementSegment from '@DpJs/components/procedure/StatementSegmentsList/StatementSegment'

describe('StatementSegment.handleUnlinkRequest', () => {
  let unlinkBoilerplate
  let undo
  let notifyConfirm

  const createContext = (getBoilerplateTitle = vi.fn(() => 'Mein Textbaustein')) => {
    unlinkBoilerplate = vi.fn()
    undo = vi.fn()

    return {
      getBoilerplateTitle,
      pendingUnlink: null,
      $refs: {
        unlinkBoilerplateDialog: { open: vi.fn() },
        editor: { unlinkBoilerplate, undo },
      },
    }
  }

  beforeEach(() => {
    globalThis.Translator = { trans: vi.fn((key, params) => params?.title ? `${key}:${params.title}` : key) }
    notifyConfirm = vi.fn()
    globalThis.dplan = { notify: { confirm: notifyConfirm } }
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('dissolves the boilerplate and shows an undo notification when confirmed', async () => {
    const context = createContext()

    context.$refs.unlinkBoilerplateDialog.open.mockResolvedValue(true)

    await StatementSegment.methods.handleUnlinkRequest.call(context, { boilerplateId: 'boilerplate-1', pos: 42 })

    expect(unlinkBoilerplate).toHaveBeenCalledWith(42)
    expect(notifyConfirm).toHaveBeenCalledWith({
      message: 'boilerplate.link.dissolved:Mein Textbaustein',
      actionText: 'undo',
      hideTimer: 15000,
      onAction: expect.any(Function),
    })
  })

  it('does nothing when the confirmation dialog is cancelled', async () => {
    const context = createContext()

    context.$refs.unlinkBoilerplateDialog.open.mockResolvedValue(false)

    await StatementSegment.methods.handleUnlinkRequest.call(context, { boilerplateId: 'boilerplate-1', pos: 42 })

    expect(unlinkBoilerplate).not.toHaveBeenCalled()
    expect(notifyConfirm).not.toHaveBeenCalled()
  })

  it('undoes the dissolution when the notification\'s action is triggered', async () => {
    const context = createContext()

    context.$refs.unlinkBoilerplateDialog.open.mockResolvedValue(true)

    await StatementSegment.methods.handleUnlinkRequest.call(context, { boilerplateId: 'boilerplate-1', pos: 42 })

    notifyConfirm.mock.calls[0][0].onAction()

    expect(undo).toHaveBeenCalled()
  })

  it('falls back to a generic title when the boilerplate has no resolvable title', async () => {
    const context = createContext(vi.fn(() => ''))

    context.$refs.unlinkBoilerplateDialog.open.mockResolvedValue(true)

    await StatementSegment.methods.handleUnlinkRequest.call(context, { boilerplateId: 'boilerplate-1', pos: 42 })

    expect(notifyConfirm).toHaveBeenCalledWith(expect.objectContaining({
      message: 'boilerplate.link.dissolved:boilerplate',
    }))
  })
})
