/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { formatDate } from '@demos-europe/demosplan-ui'

const Methods = {
  period () {
    const { internalStartDate, externalStartDate, internalEndDate, externalEndDate, daysLeft } = this.procedure
    let period = ''
    if (!!internalStartDate && !!externalStartDate && !!internalEndDate && !!externalEndDate) {
      period = formatDate(internalStartDate) +
                ' – ' +
                formatDate(internalEndDate) +
                ' / ' +
                formatDate(externalStartDate) +
                ' – ' +
                formatDate(externalEndDate)
    } else if (externalStartDate && externalEndDate) {
      period = formatDate(externalStartDate) +
                ' – ' +
                formatDate(externalEndDate)
    } else if (internalStartDate && internalEndDate) {
      period = formatDate(internalStartDate) +
                ' – ' +
                formatDate(internalEndDate)
    }
    if (daysLeft !== '') {
      if (daysLeft === Translator.trans('days.left.participation.finished')) {
        period = ' ' + daysLeft
      } else {
        period = ' ' + daysLeft + ' ' + period
      }
    }
    return period
  },

  procedureName () {
    const { name, externalName } = this.procedure
    if (!!name && !!externalName) {
      return `${name} / ${externalName}`
    } else if (externalName) {
      return externalName
    } else if (name) {
      return name
    }
  },

  phaseName () {
    const { internalPhaseTranslationKey, externalPhaseTranslationKey } = this.procedure
    if (!!internalPhaseTranslationKey && !!externalPhaseTranslationKey) {
      return `${Translator.trans(internalPhaseTranslationKey)} / ${Translator.trans(externalPhaseTranslationKey)}`
    } else if (externalPhaseTranslationKey) {
      return Translator.trans(externalPhaseTranslationKey)
    } else if (internalPhaseTranslationKey) {
      return Translator.trans(internalPhaseTranslationKey)
    }
  }
}

export default Methods
