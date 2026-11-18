import { describe, expect, it, vi } from 'vitest'
import { createStore } from 'vuex'
import { DpSlidebar } from '@demos-europe/demosplan-ui'
import ListStatements from '@DpJs/components/statement/listStatements/ListStatements.vue'
import { shallowMount } from '@vue/test-utils'
import VersionHistory from '@DpJs/components/statement/statement/VersionHistory.vue'
import VersionHistorySlidebarStore from '@DpJs/store/statement/VersionHistorySlidebar'

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
    ['shows element, document and chapter', extendedStatement, 'Able / Gesamtstellungnahme / 1.1 Kapitel Wind'],
    ['falls back to element title when no document', {
      attributes: { elementTitle: 'Able', paragraphTitle: '1.1 Foo' },
    }, 'Able / 1.1 Foo'],
    ['renders element and document without chapter', statementWithoutParagraph, 'Beta / Teilfortschreibung'],
    ['renders dash when no assignment at all', statementWithoutTitle, '–'],
  ])('%s', (name, statement, expected) => {
    expect(ListStatements.methods.planningDocumentLabel(statement)).toBe(expected)
  })
})

const mockStatement = {
  id: 'stmt-123',
  type: 'Statement',
  attributes: {
    externId: 'EXT-123',
    text: 'Test statement text',
    submitDate: '2024-01-01',
    submitName: 'Test User',
  },
  relationships: {
    assignee: { data: { id: '' } },
    orga: { data: { id: 'orga-1' } },
  },
  hasRelationship (name) {
    return !!this.relationships[name]
  },
}

/**
 * Without an argument, the permissions currently set on global.hasPermission are kept
 */
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
          items: { 'stmt-123': mockStatement },
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
      VersionHistorySlidebar: VersionHistorySlidebarStore,
    },
  })

  if (hasPermissionReturnValue !== undefined) {
    global.hasPermission = () => hasPermissionReturnValue
  }

  return { wrapper: shallowMount(ListStatements, {
    props: {
      currentUserId: 'user-id',
      procedureId: 'procedure-id',
    },
    global: {
      plugins: [store],
      stubs: {
        DpDataTable: false,
        DpFlyout: false,
        DpSlidebar: false,
        DpTableRow: false,
      },
      mocks: {
        $route: { name: 'x' },
        $router: { replace: vi.fn(), push: vi.fn() },
        CleanHtml: true,
        hasPermission: permission => global.hasPermission(permission),
        lscache: { get: vi.fn(), set: vi.fn(), remove: vi.fn() },
      },
    },
  }), store }
}

describe('ListStatements search whitelist & sortOptions permission gating', () => {
  it('always contains planDocument in the search whitelist', () => {
    const { wrapper } = mountComponent(true)
    const searchFields = wrapper.vm.searchFields

    expect(searchFields).toContain('planDocument')
    wrapper.unmount()
  })

  it('hides planning-document sort options when field_procedure_elements is off', () => {
    const { wrapper } = mountComponent(false)
    const values = wrapper.vm.sortOptions.map(option => option.value)

    expect(values).not.toContain('elementTitle,documentTitle,paragraphTitle')
    expect(values).not.toContain('-elementTitle,-documentTitle,-paragraphTitle')
    wrapper.unmount()
  })

  it('exposes planning-document sort options when field_procedure_elements is on', () => {
    global.hasPermission = permission => permission === 'field_procedure_elements'
    const { wrapper } = mountComponent(true)

    const values = wrapper.vm.sortOptions.map(option => option.value)

    expect(values).toContain('elementTitle,documentTitle,paragraphTitle')
    expect(values).toContain('-elementTitle,-documentTitle,-paragraphTitle')
    wrapper.unmount()
  })

  it('includes planning-document attributes in the statement fields list', () => {
    const { wrapper } = mountComponent(false)
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

describe('ListStatements version history integration', () => {
  const historyButton = wrapper => wrapper.find('[data-cy="listStatements:statementVersionHistory"]')

  it('shows the history menu item only with feature_statement_content_changes_view', () => {
    global.hasPermission = permission => permission !== 'feature_statement_content_changes_view'
    const { wrapper: wrapperWithout } = mountComponent()

    expect(historyButton(wrapperWithout).exists()).toBe(false)
    wrapperWithout.unmount()

    global.hasPermission = permission => permission === 'feature_statement_content_changes_view'
    const { wrapper: wrapperWith } = mountComponent()

    expect(historyButton(wrapperWith).exists()).toBe(true)
    wrapperWith.unmount()
  })

  it('opens the slidebar for the clicked statement', async () => {
    const { wrapper, store } = mountComponent(true)

    await historyButton(wrapper).trigger('click')

    expect(store.state.VersionHistorySlidebar.isOpen).toBe(true)
    expect(store.state.VersionHistorySlidebar.entity).toEqual({
      externId: 'EXT-123',
      id: 'stmt-123',
      type: 'statement',
    })
    expect(wrapper.findComponent(DpSlidebar).props('open')).toBe(true)
    wrapper.unmount()
  })

  it('passes the shown statement to the version history', async () => {
    const { wrapper } = mountComponent(true)

    await historyButton(wrapper).trigger('click')

    expect(wrapper.findComponent(VersionHistory).props()).toMatchObject({
      entityId: 'stmt-123',
      entityType: 'statement',
      externId: 'EXT-123',
      procedureId: 'procedure-id',
      reloadKey: 1,
    })
    wrapper.unmount()
  })

  it('closes the slidebar when the slidebar emits close', async () => {
    const { wrapper, store } = mountComponent(true)

    await historyButton(wrapper).trigger('click')
    await wrapper.findComponent(DpSlidebar).vm.$emit('close')

    expect(store.state.VersionHistorySlidebar.isOpen).toBe(false)
    wrapper.unmount()
  })
})
