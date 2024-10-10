<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="layout--flush u-n-mt-0_25">
    <div class="layout__item u-4-of-12 u-pv-0_25 o-hellip">
      {{ faqItem.attributes.title }}
    </div><!--
 --><div class="layout__item u-4-of-12  u-pt-0_125">
      <dp-multiselect
        v-if="availableGroupOptions.length > 1"
        :allow-empty="false"
        :custom-label="option =>`${option.title}`"
        data-cy="selectedGroups"
        multiple
        :options="availableGroupOptions"
        track-by="id"
        :value="selectedGroups"
        @input="selectGroups">
        <template v-slot:option="{ props }">
          <span>{{ props.option.title }}</span>
        </template>
        <template v-slot:tag="{ props }">
          <span class="multiselect__tag">
            {{ props.option.title }}
            <i
              aria-hidden="true"
              class="multiselect__tag-icon"
              tabindex="1"
              @click="props.remove(props.option)" />
          </span>
        </template>
      </dp-multiselect>
    </div><!--
 --><div class="layout__item u-2-of-12 text-center u-pv-0_25">
      <dp-toggle
        v-model="itemEnabled"
        class="u-mt-0_125"
        data-cy="enabledFaqItem" />
    </div><!--
 --><div class="layout__item u-2-of-12 text-center py-1">
      <div class="flex flex-col sm:flex-row justify-center">
        <a
          class="btn--blank o-link--default"
          :href="Routing.generate('DemosPlan_faq_administration_faq_edit', {faqID: this.faqItem.id})"
          :aria-label="Translator.trans('item.edit')"
          data-cy="editFaqItem">
          <i
            class="fa fa-pencil"
            aria-hidden="true" />
        </a>
        <button
          type="button"
          @click="deleteFaqItem"
          data-cy="deleteFaqItem"
          :aria-label="Translator.trans('item.delete')"
          class="btn--blank o-link--default sm:ml-2">
          <i
            class="fa fa-trash"
            aria-hidden="true" />
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { DpMultiselect, DpToggle, hasOwnProp } from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'

export default {
  name: 'DpFaqItem',

  components: {
    DpMultiselect,
    DpToggle
  },

  props: {
    availableGroupOptions: {
      type: Array,
      required: true
    },

    faqItem: {
      type: Object,
      required: true
    },

    parentId: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      /**
       * This fifo-queue performs all API request in order. If a request should be performed an API request has to be pushed
       * to the end of the queue.
       * The request MUST be set in a reactive way.
       * The request MUST be a function returning a Promise.
       * example
       * let request = () => Promise.resolve(true)
       * this.queue.push(request)
       * see the queue-watcher for implementation details
       */
      queue: []
    }
  },

  computed: {
    ...mapState('Faq', {
      faqItems: 'items'
    }),
    ...mapState('FaqCategory', {
      faqCategories: 'items'
    }),

    currentParentItem () {
      return this.faqCategories[this.parentId]
    },

    itemEnabled: {
      get () {
        return this.faqItems[this.faqItem.id].attributes.enabled
      },

      set (val) {
        if (val !== this.faqItem.attributes.enabled) {
          const faqCpy = JSON.parse(JSON.stringify(this.faqItem))
          faqCpy.attributes.enabled = val

          this.updateFaq({ ...faqCpy, id: faqCpy.id })
          const saveAction = () => {
            return this.saveFaq(this.faqItem.id).then(() => {
              dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
            }).catch(() => {
              dplan.notify.error(Translator.trans('error.changes.not.saved'))
            })
          }

          this.queue.push(saveAction)
        }
      }
    },

    selectedGroups () {
      return this.availableGroupOptions.filter(group => this.visibilities[group.id])
    },

    visibilities () {
      const faq = this.faqItem.attributes

      /**
       * If there is only one role group to activate within the project,
       * users may control visibility by using the "status" toggle.
       * The value for visibility for that role group is saved as true,
       * in case another role group is activated later on within the project.
       */
      if (this.availableGroupOptions.length === 1) {
        faq[this.availableGroupOptions[0].id] = true
      }

      return {
        fpVisible: faq.fpVisible,
        invitableInstitutionVisible: faq.invitableInstitutionVisible,
        publicVisible: faq.publicVisible
      }
    }
  },

  watch: {
    queue: {
      handler (newQueue) {
        if (newQueue.length) {
          newQueue[0]()
            .finally(() => {
              newQueue.shift()
              this.queue = newQueue
            })
        }
      },
      deep: true
    }
  },

  methods: {
    ...mapActions('Faq', {
      deleteFaq: 'delete',
      saveFaq: 'save'
    }),
    ...mapMutations('Faq', {
      updateFaq: 'setItem'
    }),
    ...mapMutations('FaqCategory', {
      updateCategory: 'setItem'
    }),

    selectGroups (val) {
      const selectedGroups = val.reduce((acc, group) => {
        return {
          ...acc,
          ...{ [group.id]: true }
        }
      }, {})
      let newSelection = {}
      this.availableGroupOptions.forEach(group => {
        newSelection = selectedGroups[group.id] ? newSelection : { ...newSelection, ...{ [group.id]: false } }
      })

      newSelection = { ...newSelection, ...selectedGroups }

      const faqCpy = JSON.parse(JSON.stringify(this.faqItem))
      faqCpy.attributes = { ...faqCpy.attributes, ...newSelection }

      /**
       * Weirdly the input event seems to be fired on initial load of the vue multiselect.
       * Therefore, a check is implemented which confirms if the item has changed at all.
       * If the item hasn't changed, the update is not triggered.
       * @type {boolean}
       */
      const hasChangedAttributes = Object.entries(faqCpy.attributes).filter(([key, value]) => {
        return this.faqItem.attributes[key] !== value
      }).length !== 0
      if (hasChangedAttributes === true) {
        this.updateFaq({ ...faqCpy, id: faqCpy.id })
        const saveAction = () => {
          return this.saveFaq(this.faqItem.id).then(() => {
            dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
          }).catch(() => {
            dplan.notify.error(Translator.trans('error.changes.not.saved'))
          })
        }

        this.queue.push(saveAction)
      }
    },

    deleteFaqItem () {
      if (dpconfirm(Translator.trans('check.item.delete'))) {
        const categoryCpy = JSON.parse(JSON.stringify(this.currentParentItem))

        categoryCpy.relationships.faq.data = categoryCpy.relationships.faq.data.filter(item => item.id !== this.faqItem.id)

        this.updateCategory({ ...categoryCpy, id: categoryCpy.id })

        const deleteAction = () => {
          return this.deleteFaq(this.faqItem.id).then(() => {
            dplan.notify.notify('confirm', Translator.trans('confirm.faq.deleted'))
          })
        }

        this.queue.push(deleteAction())
      }
    }
  }
}
</script>
