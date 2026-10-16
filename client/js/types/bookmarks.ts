/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

export interface FilterCondition {
  path: string
  value?: string
  operator?: string
}

export interface Filter {
  condition: FilterCondition
}

export interface ViewSettings {
  selectedColumns: string[]
  columnOrder?: string
  sorting?: string
}

export interface BookmarkAttributes {
  name: string
  queryHash: string
  filter: Record<string, Filter>
  searchPhrase?: string
  viewSettings: ViewSettings
}

export interface Bookmark {
  id: string
  type: 'Bookmark'
  attributes: BookmarkAttributes
}

export interface CreateBookmarkPayload {
  type: 'Bookmark'
  attributes: {
    name: string
    queryHash: string
  }
}

export interface UpdateBookmarkPayload {
  id: string
  name?: string
  queryHash?: string
}

export interface FilterLabelCache {
  [id: string]: string
}

export interface FilterChange {
  filterName: string
  oldValue: string
  newValue: string
}

export interface IndividualFilterChange {
  filterCategory: string
  filterValue: string
  action: 'added' | 'removed'
}

export interface GroupedFilterChange {
  category: string
  removed: string[]
  added: string[]
}

export interface DetailedChange {
  type: 'filter' | 'search' | 'sorting' | 'columns'
  label: string
  details?: FilterChange[]
  individualFilters?: IndividualFilterChange[]
  groupedFilterChanges?: GroupedFilterChange[]
  oldValue?: string
  newValue?: string
  added?: string[]
  removed?: string[]
}
