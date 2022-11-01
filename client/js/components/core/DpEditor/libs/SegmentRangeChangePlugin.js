/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { Plugin } from 'prosemirror-state'

export default new Plugin({
/*
 *  State: {
 *  init () {
 *    return {}
 *  },
 *  apply (tr, set) {
 *    console.log(set)
 *  }
 *  },
 */
  appendTransaction (transactions) {
    // Console.log(transactions)
  }
/*
 *  View () {
 *  return {
 *    update (editorView, prevState) {
 *      debugger
 *    }
 *  }
 *  }
 */
})
