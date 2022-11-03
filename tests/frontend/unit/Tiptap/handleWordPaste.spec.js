/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { test300, test365, testMso } from '../__mocks__/wordContentSnippets.mock'
import { handleWordPaste } from '@DpJs/lib/TiptapPlugins/handleWordPaste'

describe.each([testMso, test365, test300])('handleWordPaste - a util to handle pasting lists from word to tiptap', snippet => {
  test('convert word clipboard to readable and semantic correct html (for lists)', () => {
    expect(handleWordPaste(snippet)).toMatchSnapshot()
  })
})
