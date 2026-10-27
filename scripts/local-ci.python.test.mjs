import { describe, it, expect } from 'vitest'
import { pickPython3 } from './local-ci.mjs'

// UAMS-Web/uams-statamic-archive#2850. The encoding job runs the release-notes generator suite, so the runner has to find a
// Python 3. A name on PATH is not proof of one: `python` can be a Python 2, and a stub can
// resolve and still not run. These cases pin that the choice is made on the version a candidate
// REPORTS, not on whether it was found.

const reports = (answers) => (candidate) => answers[candidate] ?? ''

describe('pickPython3', () => {
    it('takes the first candidate that reports major version 3', () => {
        expect(pickPython3(['/a/python3', '/b/python'], reports({ '/a/python3': '3', '/b/python': '3' })))
            .toBe('/a/python3')
    })

    it('passes over a Python 2 to reach a later Python 3', () => {
        expect(pickPython3(['/a/python', '/b/python3'], reports({ '/a/python': '2', '/b/python3': '3' })))
            .toBe('/b/python3')
    })

    it('passes over a candidate that cannot answer at all', () => {
        // A stub that resolves on PATH but prints nothing, or a spawn that throws, reports ''.
        expect(pickPython3(['/stub/python3', '/real/python'], reports({ '/real/python': '3' })))
            .toBe('/real/python')
    })

    it('skips the empty entries an unset override or a missing PATH name leaves', () => {
        const asked = []
        const majorOf = (candidate) => {
            asked.push(candidate)

            return '3'
        }

        expect(pickPython3([undefined, null, '', '/c/python3'], majorOf)).toBe('/c/python3')
        expect(asked).toEqual(['/c/python3'])
    })

    it('returns null when nothing reports 3, so the caller refuses instead of running nothing', () => {
        expect(pickPython3(['/a/python', '/b/python3'], reports({ '/a/python': '2' }))).toBeNull()
        expect(pickPython3([], reports({}))).toBeNull()
    })
})
