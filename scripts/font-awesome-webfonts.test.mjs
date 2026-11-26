import { describe, it, expect } from 'vitest'
import { existsSync, readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

// cspell:ignore webfonts
// #1546: the theme stylesheet inlines the package's `all` and `v4-shims` CSS, and Vite
// emits the fonts that CSS points at. This checks that the installed package still
// ships each font file its CSS names, so a package update that moves or renames one
// is caught here rather than as icons drawn in a fallback font.

const repoRoot = join(dirname(fileURLToPath(import.meta.url)), '..')
const packageDir = join(repoRoot, 'node_modules', '@fortawesome', 'fontawesome-free')

/** The `url(...)` targets in one of the package's stylesheets. */
const fontUrls = (sheet) => [...readFileSync(join(packageDir, 'css', sheet), 'utf8').matchAll(/url\("?([^")]+)"?\)/g)].map((match) => match[1])

describe('Font Awesome web fonts', () => {
    it('names the four font files the site relies on', () => {
        expect([...new Set(fontUrls('all.css'))].sort()).toEqual([
            '../webfonts/fa-brands-400.woff2',
            '../webfonts/fa-regular-400.woff2',
            '../webfonts/fa-solid-900.woff2',
            '../webfonts/fa-v4compatibility.woff2',
        ])
    })

    it('ships every font file the inlined stylesheets point at', () => {
        const urls = [...new Set([...fontUrls('all.css'), ...fontUrls('v4-shims.css')])]

        expect(urls.length).toBeGreaterThan(0)
        for (const url of urls) {
            expect(existsSync(join(packageDir, 'css', url)), url).toBe(true)
        }
    })
})
