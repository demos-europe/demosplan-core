/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

export default {
  methods: {
    capitalizeFirstLetter (str) {
      if (typeof str !== 'string') return

      return str.charAt(0).toUpperCase() + str.slice(1)
    }
  }
}
