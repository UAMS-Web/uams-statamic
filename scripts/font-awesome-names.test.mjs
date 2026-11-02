import { describe, it, expect } from 'vitest'
import { readFileSync, readdirSync } from 'node:fs'
import { dirname, extname, join, relative } from 'node:path'
import { fileURLToPath } from 'node:url'

// UAMS-Web/uams-statamic#347: a stylesheet could name an icon the installed package
// does not ship, and nothing said so: a misspelled `:hover` selector sat in the source
// matching no element. This resolves every `fa-` name in resources/ against the package's own
// metadata, three ways, because the source still uses names from older versions.

const repoRoot = join(dirname(fileURLToPath(import.meta.url)), '..')
const metadataDir = join(repoRoot, 'node_modules', '@fortawesome', 'fontawesome-free', 'metadata')

// Text only, since a font or an image can hold these bytes by chance
const SCANNED_EXTENSIONS = new Set(['.css', '.html', '.js', '.md', '.php', '.scss', '.vue', '.yaml', '.yml'])

// The `fa-` classes that are not icon names: styles, sizes, animations, transforms,
// layout utilities and the SVG build's own runtime marker.
const NON_ICON_CLASSES = new Set([
    'fa-brands', 'fa-classic', 'fa-regular', 'fa-solid',
    'fa-2xs', 'fa-xs', 'fa-sm', 'fa-lg', 'fa-xl', 'fa-2xl',
    'fa-1x', 'fa-2x', 'fa-3x', 'fa-4x', 'fa-5x', 'fa-6x', 'fa-7x', 'fa-8x', 'fa-9x', 'fa-10x',
    'fa-beat', 'fa-beat-fade', 'fa-bounce', 'fa-fade', 'fa-flip', 'fa-pulse', 'fa-shake', 'fa-spin', 'fa-spin-pulse', 'fa-spin-reverse',
    'fa-flip-both', 'fa-flip-horizontal', 'fa-flip-vertical', 'fa-rotate-90', 'fa-rotate-180', 'fa-rotate-270', 'fa-rotate-by',
    'fa-border', 'fa-fw', 'fa-inverse', 'fa-li', 'fa-pull-end', 'fa-pull-left', 'fa-pull-right', 'fa-pull-start', 'fa-stack', 'fa-stack-1x', 'fa-stack-2x', 'fa-ul', 'fa-width-auto', 'fa-width-fixed',
    'fa-layers', 'fa-layers-counter', 'fa-layers-text',
    'fa-i2svg',
])

/** The package's icon names, its aliases of them, and its Font Awesome 4 shims. */
const loadIndex = () => {
    const families = JSON.parse(readFileSync(join(metadataDir, 'icon-families.json'), 'utf8'))
    const shims = readFileSync(join(metadataDir, 'shims.yml'), 'utf8')

    return {
        icons: new Set(Object.keys(families)),
        aliases: new Set(Object.values(families).flatMap((icon) => icon.aliases?.names ?? [])),
        // Top-level keys only: an entry's `name:` and `prefix:` lines are indented
        shims: new Set([...shims.matchAll(/^'?([a-z0-9-]+)'?:/gm)].map((match) => match[1])),
    }
}

/** How a class resolves as an icon: 'icon', 'shim', 'alias', or null when it does not. */
const resolution = (name, { icons, aliases, shims }) => {
    const icon = name.replace(/^fa-/, '')

    if (icons.has(icon)) return 'icon'
    if (shims.has(icon)) return 'shim'
    if (aliases.has(icon)) return 'alias'

    return null
}

/**
 * The `fa-` classes in a text. The lookbehind leaves out a longer word that ends in
 * `fa-`, a `--fa-` custom property and a `$fa-` variable, none of which name an icon.
 */
const classesIn = (text) => [...text.matchAll(/(?<![\w$-])fa-[a-z0-9]+(?:-[a-z0-9]+)*/g)].map((match) => match[0])

/** Every scanned file under resources/, relative to the repository root. */
const scannedFiles = () => readdirSync(join(repoRoot, 'resources'), { recursive: true, withFileTypes: true })
    .filter((entry) => entry.isFile() && SCANNED_EXTENSIONS.has(extname(entry.name)))
    .map((entry) => relative(repoRoot, join(entry.parentPath, entry.name)))

describe('Font Awesome names in resources/ exist in the installed package (#347)', () => {
    const index = loadIndex()

    it('resolves a name each of the three ways, and refuses one that resolves none of them', () => {
        expect(resolution('fa-whatsapp', index)).toBe('icon')
        expect(resolution('fa-area-chart', index)).toBe('shim')
        expect(resolution('fa-file-alt', index)).toBe('alias')

        // Pro only, and invented: a pass cannot come from a lookup that accepts anything
        expect(resolution('fa-abacus', index)).toBeNull()
        expect(resolution('fa-no-such-icon', index)).toBeNull()
    })

    it('reads a class but not a longer word, a custom property or a variable', () => {
        expect(classesIn('<i class="fa-solid fa-house"></i> .fa-whatsapp:hover "fa-file-alt"'))
            .toEqual(['fa-solid', 'fa-house', 'fa-whatsapp', 'fa-file-alt'])
        expect(classesIn('sofa-bed --fa-width: 1; $fa-size: 2; fa-')).toEqual([])
    })

    it('finds every name in resources/ in the package', () => {
        const files = scannedFiles()
        const unresolved = []
        const found = new Set()

        for (const file of files) {
            for (const name of classesIn(readFileSync(join(repoRoot, file), 'utf8'))) {
                found.add(name)

                if (!NON_ICON_CLASSES.has(name) && resolution(name, index) === null) {
                    unresolved.push(`${file}: ${name}`)
                }
            }
        }

        // A scan that read nothing passes the last line, so show that it read the source
        expect(files.length).toBeGreaterThan(500)
        expect(found).toContain('fa-whatsapp')
        expect(found).toContain('fa-file-alt')
        expect(unresolved).toEqual([])
    })

    // The icons come from the package's SVG and JS build, which reads no Sass, so a
    // `$fa-` variable here configures nothing while reading as a working setting.
    it('sets no Font Awesome Sass variable in resources/', () => {
        const pattern = /\$fa-[a-z0-9-]+/

        expect(pattern.test('$fa-size: 2;')).toBe(true)
        expect(scannedFiles().filter((file) => pattern.test(readFileSync(join(repoRoot, file), 'utf8')))).toEqual([])
    })
})
