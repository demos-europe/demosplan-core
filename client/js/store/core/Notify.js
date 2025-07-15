/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

export default {
  namespaced: true,

  name: 'Notify',

  state: {
    messages: [],
    uid: 1
  },

  mutations: {
    /**
     * Add a message to the store
     *
     * @param {object} state The state of the notifications store
     * @param {object} message A message
     * @param {string} message.type Type of the message
     * @param {string} message.text Text of the message
     * @param {string} [message.linkUrl] Link url of the message
     * @param {string} [message.linkText] Link text of the message
     */
    add (state, message) {
      state.messages.push({
        type: message.type,
        text: message.text || '',
        linkUrl: message.linkUrl || '',
        linkText: message.linkText || '',
        uid: state.uid++
      })
    },

    /**
     * Remove a message
     *
     * @param {object} state
     * @param {object} messageToRemove
     * @param {int}    messageToRemove.uid The uid of the message
     * @param {string} [messageToRemove.type] Type of the message
     * @param {string} [messageToRemove.text] Text of the message
     * @param {string} [messageToRemove.linkUrl] Link url of the message
     * @param {string} [messageToRemove.linkText] Link text of the message
     */
    remove (state, messageToRemove) {
      state.messages = state.messages.filter((message) => message.uid !== messageToRemove.uid)
    }
  }
}
