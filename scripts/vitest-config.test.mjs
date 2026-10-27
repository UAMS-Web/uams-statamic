import { describe, it, expect } from 'vitest'
import config from '../vitest.config.js'

// UAMS-Web/uams-statamic-archive#3100: the forking suites need more than vitest's 5 second test and 10 second hook
// defaults. Removing either setting silently restores the default, so this pins both.
describe('vitest.config.js', () => {
    it('sets an explicit test timeout above the 5 second default', () => {
        expect(config.test.testTimeout).toBe(30_000)
    })

    it('sets an explicit hook timeout above the 10 second default', () => {
        expect(config.test.hookTimeout).toBe(60_000)
    })
})
