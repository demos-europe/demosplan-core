export default class NotificationStoreAdapter {
  constructor (store) {
    this.store = store
  }

  notify (type, text, linkUrl = '', linkText = '') {
    let message = { type }
    if (typeof text === 'object') {
      message.linkUrl = text.linkUrl || null
      message.linkText = text.linkText || null
      message.text = text.message
    } else {
      message = { type, text, linkUrl, linkText }
    }
    this.store.commit('notify/add', message)
  }

  remove (notification) {
    this.store.commit('notify/remove', notification)
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
