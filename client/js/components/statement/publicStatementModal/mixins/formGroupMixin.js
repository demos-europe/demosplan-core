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
    ...mapState('PublicStatement', ['statement'])
  },
  methods: {
    ...mapMutations('PublicStatement', ['updateStatement']),

    setStatementData (data) {
      this.updateStatement({ r_ident: this.draftStatementId, ...data })
    }
  }
}
