/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { mapGetters } from 'vuex'

export default {
  props: {
    group: {
      type: Object,
      required: false,
      default: () => ({})
    },

    parentId: {
      type: String,
      required: false,
      default: ''
    }
  },

  computed: {
    ...mapGetters('Statement', [
      'getToc'
    ]),

    depth () {
      return this.group.level
    }
  }
}
