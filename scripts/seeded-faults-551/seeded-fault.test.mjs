import { it, expect } from 'vitest'

// Seeded fault for #551: this test fails on purpose, so the js job must fail on every platform.
it('fails on purpose (#551 seeded fault)', () => {
    expect(1).toBe(2)
})
