/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { convertSize } from 'demosplan-utils/lib/FileInfo'
const de = () => {
  return {
    strings: {
      /*
       * Text to show on the droppable area.
       * `%{browse}` is replaced with a link that opens the system file selection dialog.
       */
      dropHereOr: Translator.trans('form.button.upload.pdf', {
        browse: '{browse}',
        maxUploadSize: convertSize('GB', window.dplan.settings.maxUploadSize)
      }),
      failedToUpload: Translator.trans('form.button.upload.failed', {
        file: '{file}'
      }),
      // This string is clickable and opens the system file selection dialog.
      browse: Translator.trans('form.button.upload.search')
    }
  }
}

export { de }
