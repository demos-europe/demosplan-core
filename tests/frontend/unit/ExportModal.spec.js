import { afterEach, beforeEach, describe, expect, it } from '@jest/globals'
import { enableAutoUnmount } from '@vue/test-utils'
import ExportModal from '@DpJs/components/statement/assessmentTable/ExportModal'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

describe('ExportModal', () => {
  const props = {
    options: {
      docx: {
        _defaults: {
          anonymous: false,
          exportType: 'statementsOnly',
          sortType: 'default',
          template: 'condensed'
        },
        anonymize: true,
        buttonLabel: 'export.docx',
        buttonLabelSingle: 'export.trigger.docx',
        exportTypes: true,
        obscure: true,
        tabLabel: 'export.docx',
        templates: {
          condensed: {
            name: 'export.compact'
          },
          landscape: {
            explanation: 'explanation.export.docx',
            name: 'export.landscape'
          },
          portrait: false
        }
      },
      pdf: {
        _defaults: {
          anonymous: false,
          exportType: 'statementsOnly',
          sortType: 'false',
          template: 'condensed'
        },
        anonymize: true,
        buttonLabel: 'export.pdf',
        buttonLabelSingle: 'export.trigger.pdf',
        exportTypes: true,
        newPagePerStn: true,
        obscure: true,
        tabLabel: 'export.pdf',
        templates: {
          condensed: {
            name: 'export.compact'
          },
          landscape: {
            name: 'export.landscape'
          },
          portrait: {
            name: 'export.portrait'
          }
        }

      },
      xlsx: {
        _defaults: {
          anonymous: false,
          exportType: 'topicsAndTags',
          sortType: 'false',
          template: 'compact'
        },
        anonymize: false,
        buttonLabel: 'export.xlsx',
        buttonLabelSingle: 'export.trigger.xlsx',
        exportTypes: true,
        obscure: false,
        tabLabel: 'export.xlsx'
      },
      zip: {
        _defaults: {
          anonymous: false,
          exportType: 'statementsWithAttachments'
        },
        buttonLabel: 'export.zip',
        tabLabel: 'export.zip',
        templates: {
          condensed: {
            name: 'export.compact'
          },
          landscape: {
            name: 'export.landscape'
          },
          portrait: {
            name: 'export.portrait'
          }
        }
      }
    },
    procedureId: '1',
    view: 'assessment_table'
  }

  let wrapper

  beforeEach(() => {
    wrapper = shallowMountWithGlobalMocks(
      ExportModal,
      {
        props,
        global: {
          renderStubDefaultSlot: true,
          stubs: {
            'dp-modal': {
              template: '<div><slot /></div>',
              methods: {
                toggle: jest.fn()
              }
            }
          }
        }
      })
  })

  enableAutoUnmount(afterEach)

  it('displays each optGroup as tab', () => {
    const tabButtons = wrapper.findAll('button')
    const optGroups = Object.keys(props.options)
    const buttonTexts = tabButtons.map(button => button.text())

    optGroups.forEach(key => {
      expect(buttonTexts).toContain(props.options[key].tabLabel)
    })
  })

  it('switches to the correct tab content when a tab button is clicked', async () => {
    const tabButtons = wrapper.findAll('button')
    const index = 1
    await tabButtons.at(index).trigger('click')
    const dataCy = tabButtons.at(index).attributes('data-cy')
    const id = dataCy.split('.').pop()
    const matchingTabContent = wrapper.find(`#${id}`)

    expect(getComputedStyle(matchingTabContent.element).display).toBe('block')
  })

  it('triggers a "submit" event when the submit button is clicked', async () => {
    wrapper.vm.submit = jest.fn()
    wrapper.vm.handleSubmit()

    const event = wrapper.emitted('submit')

    expect(wrapper.emitted()).toHaveProperty('submit')
    expect(event).toHaveLength(1)
  })

  describe('ExportModal: pdf export', () => {
    it('displays "anonymized" checkbox in the pdf tab if pdf.anonymize or pdf.obscure option is set to true', async () => {
      const tabButtons = wrapper.findAll('button')
      const index = 1
      await tabButtons.at(index).trigger('click')
      const checkboxStub = wrapper.find('[datacy="exportModal:pdfAnonymous"]')

      expect(checkboxStub.exists()).toBe(true)
    })

    it('does not display "anonymized" checkbox in the pdf tab if pdf.anonymize and pdf.obscure options are set to false', async () => {
      await wrapper.setProps({
        options: {
          ...props.options,
          pdf: {
            ...props.options.pdf,
            anonymize: false,
            obscure: false
          }
        }
      })

      const checkboxStub = wrapper.find('[datacy="exportModal:pdfAnonymous"]')
      expect(checkboxStub.exists()).toBe(false)
    })

    it('displays "new page per statement" checkbox in the pdf tab in the original statements view if "newPagePerStn" option is set to true', async () => {
      await wrapper.setProps({
        view: 'original_statements'
      })

      const checkboxStub = wrapper.find('[datacy="exportModal:newPagePerStn"]')
      expect(checkboxStub.exists()).toBe(true)
    })

    it('does not display "new page per statement" checkbox in the pdf tab if "newPagePerStn" option is set to true, but view is "assessment_table"', async () => {
      await wrapper.setProps({
        view: 'assessment_table'
      })

      const checkboxStub = wrapper.find('[datacy="exportModal:newPagePerStn"]')
      expect(checkboxStub.exists()).toBe(false)
    })

    it('does not display "new page per statement" checkbox in the pdf tab in the original statements view if "newPagePerStn" option is set to false', async () => {
      await wrapper.setProps({
        view: 'original_statements',
        options: {
          ...props.options,
          pdf: {
            ...props.options.pdf,
            newPagePerStn: false
          }
        }
      })

      const checkboxStub = wrapper.find('[datacy="exportModal:newPagePerStn"]')
      expect(checkboxStub.exists()).toBe(false)
    })

    it('displays radio buttons for selecting a format in the pdf tab if pdf.templates option is true', () => {
      const radioButtons = wrapper.findAll('[datacy^="exportModal:pdfTemplate"]')
      expect(radioButtons.length).toBeGreaterThan(0)
    })

    it('does not display radio buttons for selecting a format in the pdf tab if pdf.templates option is false', async () => {
      await wrapper.setProps({
        options: {
          ...props.options,
          pdf: {
            ...props.options.pdf,
            templates: false
          }
        }
      })

      const radioButtons = wrapper.findAll('[datacy^="exportModal:pdfTemplate"]')
      expect(radioButtons.length).toBe(0)
    })

    it('displays radio buttons for selecting the data to be exported if pdf.exportTypes option exists, the selected pdf template is "condensed", and the view is "assessment_table"', async () => {
      await wrapper.setProps({
        view: 'assessment_table'
      })

      const radioButtons = wrapper.findAll('[datacy^="exportModal:pdfExportType"]')
      expect(radioButtons.length).toBeGreaterThan(0)
    })

    it('displays an explanation if pdf.anonymize and pdf.obscure options are false and pdf.exportTypes and pdf.templates options are false', async () => {
      await wrapper.setProps({
        options: {
          ...props.options,
          pdf: {
            ...props.options.pdf,
            anonymize: false,
            exportTypes: false,
            obscure: false,
            templates: false
          }
        }
      })
      const explanationText = 'explanation.export.anonymous'

      expect(wrapper.html()).toContain(explanationText)
    })

    it('displays a message that pdf fragment export is deactivated if the view mode is not "view_mode_default"', async () => {
      await wrapper.setProps({
        viewMode: 'view_mode_tag'
      })
      const message = 'explanation.export.disabled.viewMode'
      const pdfDiv = wrapper.find('#pdf')

      expect(pdfDiv.html()).toContain(message)
    })

    it('does not display a message that pdf fragment export is deactivated if the view mode is "view_mode_default"', () => {
      const message = 'explanation.export.disabled.viewMode'
      const pdfDiv = wrapper.find('#pdf')

      expect(pdfDiv.html()).not.toContain(message)
    })
  })

  describe('ExportModal: docx export', () => {
    it('displays "anonymized" checkbox in the docx tab if docx.anonymize or docx.obscure option is set to true', async () => {
      const checkboxStub = wrapper.find('[datacy="exportModal:docxObscure"]')

      expect(checkboxStub.exists()).toBe(true)
    })

    it('does not display "anonymized" checkbox in the docx tab if docx.anonymize and docx.obscure options are set to false', async () => {
      await wrapper.setProps({
        options: {
          ...props.options,
          docx: {
            ...props.options.docx,
            anonymize: false,
            obscure: false
          }
        }
      })

      const checkboxStub = wrapper.find('[datacy="exportModal:docxObscure"]')
      expect(checkboxStub.exists()).toBe(false)
    })

    it('displays radio buttons for selecting a format in the docx tab if docx.templates option is true', () => {
      const radioButtons = wrapper.findAll('[datacy^="exportModal:docxTemplate"]')
      expect(radioButtons.length).toBeGreaterThan(0)
    })

    it('displays radio buttons for selecting the data to be exported if docx.exportTypes option exists, the selected docx template is "condensed", and the view is "assessment_table"', async () => {
      await wrapper.setProps({
        view: 'assessment_table'
      })

      const radioButtons = wrapper.findAll('[datacy^="exportModal:docxExportType"]')
      expect(radioButtons.length).toBeGreaterThan(0)
    })

    it('displays radio buttons for selecting the structuring of the docx export if docx.exportTypes option is true, the selected docx template is "condensed", and the view is "assessment_table"', async () => {
      await wrapper.setProps({
        view: 'assessment_table'
      })
      const radioButtonStubs = wrapper.findAll('[datacy^="exportModal:docxSortType"]')

      expect(radioButtonStubs.length).toBeGreaterThan(0)
    })

    it('displays an explanation if docx.anonymize and docx.obscure options are false and docx.exportTypes and docx.templates options are false', async () => {
      await wrapper.setProps({
        options: {
          ...props.options,
          docx: {
            ...props.options.docx,
            anonymize: false,
            exportTypes: false,
            obscure: false,
            templates: false
          }
        }
      })
      const explanationText = 'explanation.export.anonymous'

      expect(wrapper.html()).toContain(explanationText)
    })

    it('displays a message that docx fragment export is deactivated if the view mode is not "view_mode_default"', async () => {
      await wrapper.setProps({
        viewMode: 'view_mode_tag'
      })
      const message = 'explanation.export.disabled.viewMode'
      const docxDiv = wrapper.find('#docx')

      expect(docxDiv.html()).toContain(message)
    })

    it('does not display a message that docx fragment export is deactivated if the view mode is "view_mode_default"', () => {
      const message = 'explanation.export.disabled.viewMode'
      const docxDiv = wrapper.find('#docx')

      expect(docxDiv.html()).not.toContain(message)
    })
  })

  describe('ExportModal: xlsx export', () => {
    it('displays "anonymized" checkbox in the xlsx tab if xlsx.anonymize or xlsx.obscure option is set to true', async () => {
      await wrapper.setProps({
        options: {
          ...props.options,
          xlsx: {
            ...props.options.xlsx,
            anonymize: true
          }
        }
      })
      const checkboxStub = wrapper.find('[datacy="exportModal:xlsxAnonymous"]')

      expect(checkboxStub.exists()).toBe(true)
    })

    it('does not display "anonymized" checkbox in the xlsx tab if xlsx.anonymize and xlsx.obscure options are set to false', () => {
      const checkboxStub = wrapper.find('[datacy="exportModal:xlsxAnonymous"]')

      expect(checkboxStub.exists()).toBe(false)
    })

    // Fails because 'feature_admin_assessmenttable_export_statement_generic_xlsx' permission is always evaluated as true
    it.skip('displays two radio buttons for selecting the data to be exported if xlsx.exportTypes is true', () => {
      const radioButtonStubs = wrapper.findAll('[datacy^="exportModal:xlsxExportType"]')

      expect(radioButtonStubs.length).toBe(2)
    })

    it('displays a third radio button for selecting that statements will be exported if the permission "feature_admin_assessmenttable_export_statement_generic_xlsx" is true', () => {
      global.features = {
        feature_admin_assessmenttable_export_statement_generic_xlsx: true
      }
      const radioButtonStubs = wrapper.findAll('[datacy^="exportModal:xlsxExportType"]')

      expect(radioButtonStubs.length).toBe(3)
    })

    it('does not display radio buttons for selecting the data to be exported if xlsx.exportTypes is false', async () => {
      await wrapper.setProps({
        options: {
          ...props.options,
          xlsx: {
            ...props.options.xlsx,
            exportTypes: false
          }
        }
      })

      const radioButtonStubs = wrapper.findAll('[datacy^="exportModal:xlsxExportType"]')

      expect(radioButtonStubs.length).toBe(0)
    })
  })

  describe('ExportModal: zip export', () => {
    it('displays radio buttons for selecting a format in the zip tab if zip.templates is true', () => {
      const radioButtonStubs = wrapper.findAll('[datacy^="exportModal:zipTemplate"]')

      expect(radioButtonStubs.length).toBeGreaterThan(0)
    })

    it('does not display radio buttons for selecting a format in the zip tab if zip.templates is false', async () => {
      await wrapper.setProps({
        options: {
          ...props.options,
          zip: {
            ...props.options.zip,
            templates: false
          }
        }
      })
      const radioButtonStubs = wrapper.findAll('[datacy^="exportModal:zipTemplate"]')

      expect(radioButtonStubs.length).toBe(0)
    })
  })
})
