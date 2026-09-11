/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { DpInput, prefixClassMixin } from '@demos-europe/demosplan-ui'
import { mapMutations, mapState } from 'vuex'

export default {
  components: {
    DpInput
  },

  mixins: [prefixClassMixin],

  props: {
    draftStatementId: {
      type: String,
      required: false,
      default: ''
    },

    disabled: {
      type: Boolean,
      required: false,
      default: false
    },

    required: {
      type: Boolean,
      required: false,
      default: true
    }
  },

  computed: {
    ...mapState('publicStatement', ['statement'])
  },
  methods: {
    ...mapMutations('publicStatement', ['updateStatement']),

    setStatementData (data) {
      this.updateStatement({ r_ident: this.draftStatementId, ...data })
    },

    /**
     * Native radio inputs cannot be unchecked by clicking them again. This handler is bound via
     * @click (which fires before the browser applies the checked state) so that clicking an
     * already-checked location radio deselects it instead of being a no-op.
     */
    toggleLocationSelection (event, additionalReset = {}) {
      if (event.target.checked) {
        event.target.checked = false
        this.setStatementData({ r_location: '', location_is_set: '', ...additionalReset })
      }
    }
  }
}
