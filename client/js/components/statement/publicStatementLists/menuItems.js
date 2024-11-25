/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

function sleep (ms) {
  return new Promise(resolve => setTimeout(resolve, ms))
}

async function reject (event, statementId) {
  const message = prompt(Translator.trans('statement.rejected.define.reason'), '')
  if (message === null) {
    document.getElementById('reject_reason').value = ''
    document.getElementById('statement_reject').value = ''
  } else {
    document.getElementById('reject_reason').value = message
    document.getElementById('statement_reject').value = statementId
    document.getElementById('sortform').submit()
    // Some firefox versions abort submit post request when reloading page immediately. T24847
    await sleep(500)
    location.reload()
  }
}

const targets = {
  draft: 'DemosPlan_statement_list_draft',
  released: 'DemosPlan_statement_list_released',
  released_group: 'DemosPlan_statement_list_released_group',
  final_group: 'DemosPlan_statement_list_final_group'
}

const generateMenuItems = ({ fields, id, number, procedureId, target, isPublished }) => {
  const menuItems = {
    email: {
      name: 'email',
      type: 'link',
      attrs: {
        'data-cy': 'emailSendAs'
      },
      url: Routing.generate('DemosPlan_statement_send', { statementID: id, procedure: procedureId, target }),
      text: Translator.trans('email.send.as')
    },

    pdf: {
      name: 'pdf',
      type: 'button',
      attrs: {
        type: 'submit',
        'data-form-actions-pdf-single': id,
        'aria-label': `${Translator.trans('statement.download.pdf', { id: number })}`
      },
      text: Translator.trans('pdf.download')
    },

    edit: {
      name: 'edit',
      type: 'button',
      callback: (e, vue) => {
        vue.$emit('open-statement-modal-from-list', id)
      },

      attrs: {
        type: 'button',
        class: 'o-flyout-menu__item',
        'data-cy': 'statementEdit',
        'aria-label': Translator.trans('statement.edit.with.id', { id: number })
      },
      text: Translator.trans('edit')

    },

    delete: {
      name: 'delete',
      type: 'link',
      url: Routing.generate(targets[target], { statement_delete: id, procedure: procedureId }),
      attrs: {
        'data-form-actions-confirm': Translator.trans('check.statement.delete'),
        'aria-label': Translator.trans('statement.delete', { id: number }),
        'data-cy': 'deleteDraftedStatement'
      },
      text: Translator.trans('delete')
    },

    reject: {
      name: 'reject',
      type: 'button',
      callback: (e) => {
        reject(e, id)
      },
      text: Translator.trans('reject')
    },

    publish: {
      name: 'publish',
      type: 'link',
      url: Routing.generate((isPublished && 'DemosPlan_statement_unpublish') || 'DemosPlan_statement_publish', {
        statementID: id,
        procedure: procedureId
      }),
      attrs: {
        'data-cy': 'statementPublishUnpublish',
        'data-form-actions-confirm': Translator.trans((isPublished && 'check.statement.unpublish') || 'check.statement.publish')
      },
      text: Translator.trans((isPublished && 'statement.unpublish.invitable_institution') || 'statement.publish.invitable_institution')
    },

    versions: {
      name: 'versions',
      type: 'link',
      url: Routing.generate('DemosPlan_statement_versions', { statementID: id, procedure: procedureId }),
      attrs: {
        'data-cy': 'showVersionsStatement'
      },
      text: Translator.trans('versions')
    }
  }

  return fields.map(f => menuItems[f])
}

export { generateMenuItems }
