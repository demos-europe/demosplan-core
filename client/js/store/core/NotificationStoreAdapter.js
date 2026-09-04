/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

export default class NotificationStoreAdapter {
  constructor (store) {
    this.store = store

    /*
     * Action handlers (e.g. "undo this unlink") live here, not in the Vuex store: functions
     * are not serializable state. Keyed by a counter of our own rather than the message's
     * `uid` — that one is assigned inside the `Notify/add` mutation, after this method has
     * already built the message object.
     */
    this.actionHandlers = new Map()
    this.nextActionId = 1
  }

  notify (type, text, linkUrl = '', linkText = '') {
    let message = { type }

    if (typeof text === 'object') {
      message.linkUrl = text.linkUrl || null
      message.linkText = text.linkText || null
      message.text = text.message
      message.persist = text.persist || false
      message.actionText = text.actionText || null
      message.hideTimer = text.hideTimer

      if (text.onAction) {
        message.actionId = this.nextActionId++
        this.actionHandlers.set(message.actionId, text.onAction)
      }
    } else {
      message = { type, text, linkUrl, linkText, persist: false }
    }

    this.store.commit('Notify/add', message)
  }

  /**
   * Invoked by NotifyContainer when the user clicks a message's action button.
   *
   * @param {number} actionId
   */
  runAction (actionId) {
    this.actionHandlers.get(actionId)?.()
    this.actionHandlers.delete(actionId)
  }

  remove (notification) {
    if (notification.actionId) {
      this.actionHandlers.delete(notification.actionId)
    }

    this.store.commit('Notify/remove', notification)
  }

  info (...args) {
    this.notify.apply(this, ['info'].concat(args))
  }

  confirm (...args) {
    this.notify.apply(this, ['confirm'].concat(args))
  }

  warning (...args) {
    this.notify.apply(this, ['warning'].concat(args))
  }

  error (...args) {
    this.notify.apply(this, ['error'].concat(args))
  }
}
