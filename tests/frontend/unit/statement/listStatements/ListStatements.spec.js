import { describe, expect, it, vi } from 'vitest'
import { flushPromises, shallowMount } from '@vue/test-utils'
import { createStore } from 'vuex'
import ListStatements from '@DpJs/components/statement/listStatements/ListStatements.vue'
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

  global.hasPermission = () => hasPermissionReturnValue

  return { wrapper: shallowMount(ListStatements, {
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

describe('ListStatements VersionHistorySlidebar integration', () => {
  it('showHistory mutation sets entity and opens slidebar', () => {
    const { wrapper, store } = mountComponent(true)

    wrapper.vm.showHistory({ externId: 'EXT-123', id: 'stmt-123', type: 'statement' })

    expect(store.state.VersionHistorySlidebar.isOpen).toBe(true)
    expect(store.state.VersionHistorySlidebar.entity).toEqual({
      externId: 'EXT-123',
      id: 'stmt-123',
      type: 'statement',
    })
    expect(store.state.VersionHistorySlidebar.reloadKey).toBe(1)

    wrapper.unmount()
  })

  it('setIsOpen(false) closes the slidebar without clearing entity', () => {
    const { wrapper, store } = mountComponent(true)

    wrapper.vm.showHistory({ externId: 'EXT-123', id: 'stmt-123', type: 'statement' })
    expect(store.state.VersionHistorySlidebar.isOpen).toBe(true)

    wrapper.vm.setIsOpen(false)
    expect(store.state.VersionHistorySlidebar.isOpen).toBe(false)
    expect(store.state.VersionHistorySlidebar.entity).toEqual({
      externId: 'EXT-123',
      id: 'stmt-123',
      type: 'statement',
    })

    wrapper.unmount()
  })

  it('reopening the same entity increments reloadKey', () => {
    const { wrapper, store } = mountComponent(true)

    wrapper.vm.showHistory({ externId: 'EXT-123', id: 'stmt-123', type: 'statement' })
    expect(store.state.VersionHistorySlidebar.reloadKey).toBe(1)

    wrapper.vm.setIsOpen(false)
    wrapper.vm.showHistory({ externId: 'EXT-123', id: 'stmt-123', type: 'statement' })
    expect(store.state.VersionHistorySlidebar.reloadKey).toBe(2)

    wrapper.unmount()
  })

  it('passes correct props to version-history component', async () => {
    const { wrapper } = mountComponent(true)

    wrapper.vm.showHistory({ externId: 'EXT-456', id: 'stmt-456', type: 'statement' })
    await flushPromises()

    expect(wrapper.vm.isSlidebarOpen).toBe(true)
    expect(wrapper.vm.versionHistoryEntity).toEqual({
      externId: 'EXT-456',
      id: 'stmt-456',
      type: 'statement',
    })
    expect(wrapper.vm.versionHistoryReloadKey).toBe(1)

    wrapper.unmount()
  })

  it('exposes showHistory and setIsOpen mutations', () => {
    const { wrapper } = mountComponent(true)

    expect(typeof wrapper.vm.showHistory).toBe('function')
    expect(typeof wrapper.vm.setIsOpen).toBe('function')

    wrapper.unmount()
  })

  it('switching entities updates entity and increments reloadKey', () => {
    const { wrapper, store } = mountComponent(true)

    wrapper.vm.showHistory({ externId: 'EXT-123', id: 'stmt-123', type: 'statement' })
    expect(store.state.VersionHistorySlidebar.entity.id).toBe('stmt-123')
    expect(store.state.VersionHistorySlidebar.reloadKey).toBe(1)

    wrapper.vm.showHistory({ externId: 'EXT-456', id: 'stmt-456', type: 'statement' })
    expect(store.state.VersionHistorySlidebar.entity.id).toBe('stmt-456')
    expect(store.state.VersionHistorySlidebar.reloadKey).toBe(2)

    wrapper.unmount()
  })
})
