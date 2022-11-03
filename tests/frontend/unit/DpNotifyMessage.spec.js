/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import DpNotifyMessage from '@DpJs/components/core/notify/DpNotifyMessage'
import { mount } from '@vue/test-utils'

describe('DpNotifyMessage', () => {
  it('is named DpNotifyMessage', () => {
    expect(DpNotifyMessage.hasOwnProperty('name')).toBe(true)
    expect(DpNotifyMessage.name).toBe('DpNotifyMessage')
  })

  it('chooses the message class based on it\'s type', () => {
    const validCombinations = [
      {
        type: 'confirm',
        clazz: 'c-notify__message--confirm'
      },
      {
        type: 'info',
        clazz: 'c-notify__message--info'
      },
      {
        type: 'warning',
        clazz: 'c-notify__message--warning'
      }
    ]

    for (const val of validCombinations) {
      const wrapper = mount(DpNotifyMessage, {
        propsData: {
          message: {
            type: val.type
          }
        }
      })

      expect(wrapper.vm.messageClass).toBe(val.clazz)
    }
  })

  it('always renders with closemark', () => {
    let wrapper = mount(DpNotifyMessage, {
      propsData: {
        message: {
          type: 'error'
        }
      }
    })

    expect(wrapper.html()).toMatchSnapshot()
    let closer = wrapper.find('i.c-notify__closer')
    expect(closer.element.tagName).toStrictEqual('I')

    wrapper = mount(DpNotifyMessage, {
      propsData: {
        message: {
          type: 'confirm'
        }
      }
    })

    expect(wrapper.html()).toMatchSnapshot()
    closer = wrapper.find('i.c-notify__closer')
    expect(closer.element.tagName).toStrictEqual('I')
  })

  it('renders the message text', () => {
    const wrapper = mount(DpNotifyMessage, {
      propsData: {
        message: {
          type: 'confirm',
          text: 'MessageText'
        }
      }
    })

    expect(wrapper.find('.cf > .u-ml').text()).toBe('MessageText')
  })

  it('renders a link if link attributes are given', () => {
    const wrapper = mount(DpNotifyMessage, {
      propsData: {
        message: {
          type: 'confirm',
          text: 'MessageText',
          linkUrl: 'about:blank',
          linkText: 'LinkText'
        }
      }
    })

    expect(wrapper.find('.cf > .u-ml').text()).toBe('MessageText\n      \n        LinkText')
  })

  it('emits dp-notify-remove with it\'s message as payload once clicked', (done) => {
    const message = {
      type: 'confirm',
      text: 'MessageText'
    }

    const wrapper = mount(DpNotifyMessage, {
      propsData: {
        message: message
      }
    })

    wrapper.vm.$on('dp-notify-remove', (payload) => {
      expect(payload).toBe(message)
      done()
    })

    wrapper.find('.c-notify__closer').trigger('click')
  })

  it('emits dp-notify-remove after hide timeout idle', (done) => {
    const message = {
      type: 'confirm',
      text: 'MessageText'
    }

    const wrapper = mount(DpNotifyMessage, {
      propsData: {
        message: message,
        hideTimer: 25 // Make this timeout reasonably short to keep the test time low
      }
    })

    wrapper.vm.$on('dp-notify-remove', (payload) => {
      expect(payload).toBe(message)
      done()
    })
  })
})
