/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 *  DPWizard
 *  Attach a tour-like experience to a complex form
 *
 *  @deprecated swap with more appropriate onboarding pattern? "tour", "whats new"...?
 */
import { hasOwnProp } from '@demos-europe/demosplan-ui'

export default function DpWizard () {
  // Gets jquery from window

  const $wizardButton = $('.o-wizard__trigger')
  const $form = $('form[name="configForm"]')
  const $wizardElements = $('.o-wizard__additional-elements')

  if ($wizardButton.length) {
    ({
      state: false,
      length: 0,

      $items: $('.o-wizard'),
      $actions: {
        prev: $wizardElements.find('.o-wizard__btn--prev'),
        next: $form.find('.o-wizard__btn--next'),
        done: $wizardElements.find('.o-wizard__btn--done'),
        close: $wizardElements.find('.o-wizard__close')
      },
      $menu: null,
      topics: [],
      done: [],

      step: 1,

      init: function () {
        const $items = this.$items
        const len = this.length = $items.length
        const topics = this.topics
        const done = this.done
        let i = 0
        let $item

        for (; i < len; i++) {
          $item = $items.eq(i)
          topics.push($item.data('wizard-topic'))
          if ($item.find('[data-wizard-cb]').prop('checked')) done.push(i)
        }
        $items.eq(0).find('legend').click()

        this.buildMenu()

        $wizardButton.on('click', $.proxy(this.toggle, this))
        $('[data-wizard-cb]').on('change', $.proxy(this.toggleStepState, this))
      },

      toggle: function () {
        const state = this.state = !this.state

        this.registerHandlers(state)

        if (state) {
          this.show()
        } else {
          this.hide()
        }
      },

      show: function () {
        const idx = this.step - 1
        const $items = this.$items
        const $currentItem = $items.eq(idx)

        $form.addClass('o-wizard-mode')

        $items.find('legend').removeClass('is-active-toggle')
        $items.find('.o-wizard__content').removeClass('is-active')

        $currentItem.find('legend').addClass('is-active-toggle')
        this.showElement($currentItem.find('.o-wizard__btn--next'))
        console.log($currentItem.find('.o-wizard__btn--next'))
        this.showElement($wizardElements)
        $currentItem
          .addClass('o-wizard--active')
          .find('.o-wizard__content')
          .append(
            $wizardElements.attr('aria-hidden', false)
          )
          .addClass('is-active')

        //  Vue Components that need to init on visible elements may listen to this
        const wizardShow = new CustomEvent('wizard:show', { data: $currentItem.attr('data-wizard-topic') })
        document.dispatchEvent(wizardShow)

        this.$menu.find('li').removeClass('active').eq(idx).addClass('active')

        this.toggleButtons()
        return this
      },

      showElement: function ($elements) {
        const elements = $elements.toArray()
        elements.forEach((element) => {
          element.style.display = 'block'
        })
      },

      hide: function () {
        $form.removeClass('o-wizard-mode')
        this.$items.removeClass('o-wizard--active')
        this.$items.find('.o-wizard__content').removeClass('is-active')
        $wizardElements.attr('aria-hidden', true)
        this.hideElement($wizardElements)
        this.hideElement($form.find('.o-wizard__btn--next'))
        return this
      },

      hideElement: function ($elements) {
        const elements = $elements.toArray()
        elements.forEach((element) => {
          element.style.display = 'none'
        })
      },

      buildMenu: function () {
        const listFragment = $(document.createDocumentFragment())
        const $menu = this.$menu = $wizardElements.find('.o-wizard__menu-list')
        const topics = this.topics
        const done = this.done
        const len = this.length
        let i = 0
        let listElement; let topic; let textNode

        for (; i < len; i++) {
          topic = topics[i]
          listElement = $('<li class="o-wizard__menu-item" data-wizard-step="' + (i + 1) + '" data-wizard-topic="' + topic + '"></li>')
          textNode = document.createTextNode(topic)
          listFragment.append(
            listElement
              .append('<i class="fa fa-check-circle"></i>')
              .append(textNode)
          )
          if (!i) listElement.addClass('active')
          if (done.indexOf(i) !== -1) {
            listElement.addClass('finished')
          }
        }
        $menu.append(listFragment)
        return this
      },

      toggleStepState: function (e) {
        const idx = this.step - 1
        const $cb = $(e.target)
        const $parentItem = $cb.parents('.o-wizard')
        const done = $cb.prop('checked')
        const $menuItem = this.$menu.find('[data-wizard-topic="' + $parentItem.data('wizard-topic') + '"]')

        if (done) {
          $menuItem.addClass('finished')
          $parentItem.attr('data-wizard-finished', true)

          this.done.push(idx)
        } else {
          $menuItem.removeClass('finished')
          $parentItem.attr('data-wizard-finished', null)

          this.done.splice(this.done.indexOf(idx), 1)
        }
        return this
      },

      toggleButtons: function () {
        const $nextBtn = this.$actions.next
        const $doneBtn = this.$actions.done

        if (this.step === this.length) {
          this.showElement($doneBtn)
          this.hideElement($nextBtn)
        } else {
          this.showElement($nextBtn)
          this.hideElement($doneBtn)
        }
        return this
      },

      move: function (dir) {
        if (dir === -1) {
          if (this.step === 1) return this
          this.step--
        } else {
          if (this.step + dir > this.length) return this
          this.step++
          this.save()
        }
        return this.hide().show()
      },

      moveTo: function (e) {
        this.step = $(e.currentTarget).data('wizard-step') * 1
        return this.hide().show()
      },

      save: function () {
        const url = Routing.generate('DemosPlan_procedure_edit_ajax', {
          procedure: $form.data('procedure')
        })

        $.post(url, $form.serialize())
          .always(function (xhr) {
            $form.find('button, input[type="submit"]').attr('disabled', false)
            if (hasOwnProp(xhr, 'meta') && hasOwnProp(xhr.meta, 'messages')) {
              for (const type in xhr.meta.messages) {
                for (const message in xhr.meta.messages[type]) {
                  dplan.notify.notify(type, xhr.meta.messages[type][message])
                }
              }
              return true
            }
          })
      },

      registerHandlers: function (on) {
        if (on) {
          this.$actions.prev.on('click', $.proxy(this.move, this, -1))
          this.$actions.next.on('click', $.proxy(this.move, this, 1))
          this.$actions.done.on('click', $.proxy(this.toggle, this))
          this.$actions.close.on('click', $.proxy(this.toggle, this))
          this.$menu.on('click', 'li', $.proxy(this.moveTo, this))
        } else {
          this.$actions.prev.off('click')
          this.$actions.next.off('click')
          this.$actions.done.off('click')
          this.$actions.close.off('click')
          this.$menu.off('click')
        }
      }
    }).init()
  }
}
