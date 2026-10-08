import { describe, expect, it, vi } from 'vitest'
import { createStore } from 'vuex'
import ListStatements from '@DpJs/components/statement/listStatements/ListStatements'
import { shallowMount } from '@vue/test-utils'

const extendedStatement = {
  attributes: {
    documentTitle: 'Gesamtstellungnahme',
    elementTitle: 'Able',
    paragraphTitle: '1.1 Kapitel Wind',
  },
}

const statementWithoutParagraph = {
  attributes: {
    documentTitle: 'Teilfortschreibung',
    elementTitle: 'Beta',
  },
}

const statementWithoutTitle = {
  attributes: {},
}

describe('ListStatements planning-document rendering', () => {
  it.each([
    ['shows document + chapter', extendedStatement, 'Gesamtstellungnahme – 1.1 Kapitel Wind'],
    ['falls back to element title when no document', {
      attributes: { elementTitle: 'Able', paragraphTitle: '1.1 Foo' },
    }, 'Able – 1.1 Foo'],
    ['renders title without paragraph when no chapter', statementWithoutParagraph, 'Teilfortschreibung'],
    ['renders dash when no assignment at all', statementWithoutTitle, '–'],
  ])('%s', (name, statement, expected) => {
    expect(ListStatements.methods.planningDocumentLabel(statement)).toBe(expected)
  })
})

function mountComponent (hasPermissionReturnValue) {
  const store = createStore({
    modules: {
      AssignableUser: {
        namespaced: true,
        state: () => ({ items: {} }),
        actions: {
          list: vi.fn(),
        },
      },
      Orga: {
        namespaced: true,
        state: () => ({ items: {} }),
      },
      Statement: {
        namespaced: true,
        state: () => ({
          items: {},
          currentPage: 1,
          totalFiles: 0,
          loading: false,
        }),
        actions: {
          list: vi.fn(),
          delete: vi.fn(),
          restoreFromInitial: vi.fn(),
        },
        mutations: {
          setItem: vi.fn(),
        },
      },
    },
  })

  global.hasPermission = () => hasPermissionReturnValue

  return shallowMount(ListStatements, {
    props: {
      currentUserId: 'user-id',
      procedureId: 'procedure-id',
    },
    global: {
      plugins: [store],
      mocks: {
        $route: { name: 'x' },
        $router: { replace: vi.fn(), push: vi.fn() },
        CleanHtml: true,
        lscache: { get: vi.fn(), set: vi.fn(), remove: vi.fn() },
      },
    },
  })
}

describe('ListStatements search whitelist & sortOptions permission gating', () => {
  it('always contains planDocument in the search whitelist', () => {
    const wrapper = mountComponent(true)
    const searchFields = wrapper.vm.searchFields
    expect(searchFields).toContain('planDocument')
    wrapper.unmount()
  })

  it('hides planning-document sort options when field_procedure_elements is off', () => {
    const wrapper = mountComponent(false)
    const values = wrapper.vm.sortOptions.map(option => option.value)
    expect(values).not.toContain('elementTitle,paragraphTitle')
    expect(values).not.toContain('-elementTitle,-paragraphTitle')
    wrapper.unmount()
  })

  it('exposes planning-document sort options when field_procedure_elements is on', () => {
    global.hasPermission = permission => permission === 'field_procedure_elements'
    const wrapper = mountComponent(true)

    const values = wrapper.vm.sortOptions.map(option => option.value)
    expect(values).toContain('elementTitle,paragraphTitle')
    expect(values).toContain('-elementTitle,-paragraphTitle')
    wrapper.unmount()
  })

  it('includes planning-document attributes in the statement fields list', () => {
    const wrapper = mountComponent(false)
    const fetchStatements = vi.fn().mockReturnValue(Promise.resolve({ meta: { pagination: { currentPage: 1, count: 0, perPage: 10, total: 0, totalPages: 0 } } }))
    wrapper.vm.fetchStatements = fetchStatements
    wrapper.vm.pagination = { currentPage: 1, perPage: 10 }
    wrapper.vm.setLocalStorage = vi.fn()
    wrapper.vm.setNumSelectableItems = vi.fn()
    wrapper.vm.updatePagination = vi.fn()
    wrapper.vm.fetchGroupMemberCounts = vi.fn()
    global.hasPermission = () => false
    wrapper.vm.searchFieldsSelected = null
    wrapper.vm.searchValue = ''

    wrapper.vm.getItemsByPage(1)

    expect(fetchStatements).toHaveBeenCalledTimes(1)
    const callArguments = fetchStatements.mock.calls[0][0]
    const fieldsCsv = callArguments.fields.Statement
    expect(fieldsCsv).toContain('elementTitle')
    expect(fieldsCsv).toContain('paragraphTitle')
    expect(fieldsCsv).toContain('documentTitle')

    wrapper.unmount()
  })
})
