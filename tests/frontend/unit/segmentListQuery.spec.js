/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import {
  buildSegmentExportUrl,
  buildSegmentListQuery,
  DEFAULT_SEGMENT_ORDER,
} from '@DpJs/components/procedure/SegmentsList/segmentListQuery'

describe('segmentListQuery', () => {
  describe('buildSegmentListQuery', () => {
    const base = { filter: {}, procedureId: 'procedure-1', searchTerm: '', searchFields: [] }

    it('adds the procedure', () => {
      expect(buildSegmentListQuery(base)).toEqual({ 'parentStatementOfSegment.procedure.id': 'procedure-1' })
    })

    it('keeps the filter parameters, including custom fields', () => {
      const filter = { 'tags.id': ['t1'], customField: { f1: ['o1', 'o2'] } }

      expect(buildSegmentListQuery({ ...base, filter })).toMatchObject(filter)
    })

    it('adds the search only when there is a search term', () => {
      expect(buildSegmentListQuery(base)).not.toHaveProperty('search')
      expect(buildSegmentListQuery({ ...base, searchTerm: 'noise' }).search).toEqual({ value: 'noise' })
    })

    it('adds the fields to search only when some are selected', () => {
      const query = buildSegmentListQuery({ ...base, searchTerm: 'noise', searchFields: ['text'] })

      expect(query.search).toEqual({ value: 'noise', fieldsToSearch: ['text'] })
    })
  })

  describe('buildSegmentExportUrl', () => {
    const url = 'https://dplan.test/api/3.0/StatementSegment'
    const decode = (exportUrl) => decodeURIComponent(exportUrl)

    it('adds the format as suffix', () => {
      expect(buildSegmentExportUrl({ url, format: 'xlsx', params: { columns: 'externId' } }))
        .toMatch(/^https:\/\/dplan\.test\/api\/3\.0\/StatementSegment\/export\.xlsx\?/)
    })

    it('writes arrays like the list request does', () => {
      const exportUrl = buildSegmentExportUrl({ url, format: 'csv', params: { 'tags.id': ['t1', 't2'] } })

      expect(decode(exportUrl)).toContain('tags.id[]=t1&tags.id[]=t2')
    })

    it('writes nested parameters', () => {
      const exportUrl = buildSegmentExportUrl({
        url,
        format: 'csv',
        params: {
          customField: { f1: ['o1', 'o2'] },
          order: DEFAULT_SEGMENT_ORDER,
          search: { value: 'noise' },
        },
      })

      expect(decode(exportUrl)).toContain('customField[f1][]=o1&customField[f1][]=o2')
      expect(decode(exportUrl)).toContain('order[parentStatementOfSegment.submit]=asc')
      expect(decode(exportUrl)).toContain('search[value]=noise')
    })

    it('keeps a false value, like "not assigned"', () => {
      const exportUrl = buildSegmentExportUrl({ url, format: 'csv', params: { 'exists[assignee]': false } })

      expect(decode(exportUrl)).toContain('exists[assignee]=false')
    })
  })

  it('has the order the export used before', () => {
    expect(Object.keys(DEFAULT_SEGMENT_ORDER)).toEqual([
      'parentStatementOfSegment.submit',
      'parentStatementOfSegment.externId',
      'orderInProcedure',
    ])
  })
})
