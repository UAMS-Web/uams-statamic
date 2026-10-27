import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { join, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

// UAMS-Web/uams-statamic-archive#3106: routes/web.php registers each site's feed.xml only when
// `Collection::findByHandle('posts')` resolves as the route file loads. A route
// cache compiled against the cold Stache the chain leaves behind omits every feed
// route, so the pest job builds the Stache seed first and the route cache after
// it, in both CI surfaces. These pin that order.

const repoRoot = join(dirname(fileURLToPath(import.meta.url)), '..')
const runner = readFileSync(join(repoRoot, 'scripts', 'local-ci.mjs'), 'utf8')
const workflow = readFileSync(join(repoRoot, '.github', 'workflows', 'ci.yml'), 'utf8')

/** The offset of a fragment that must occur exactly once in the given text. */
const offsetIn = (text, file, fragment) => {
    const first = text.indexOf(fragment)

    expect(first, `missing from ${file}: ${fragment}`).toBeGreaterThanOrEqual(0)
    expect(text.indexOf(fragment, first + 1), `occurs more than once in ${file}: ${fragment}`).toBe(-1)

    return first
}

describe('the pest job builds the route cache after the Stache seed (#3106)', () => {
    it('runs test:seed-stache before route:cache in scripts/local-ci.mjs', () => {
        const at = (fragment) => offsetIn(runner, 'scripts/local-ci.mjs', fragment)

        const seedStep = at("step('JOB pest - build Stache seed (testing env)')")
        const seedRun = at("runPhp(['artisan', 'test:seed-stache']")
        const cacheStep = at("step('JOB pest - build route cache (testing env)')")
        const cacheRun = at("runPhp(['artisan', 'route:cache']")

        expect(seedRun).toBeGreaterThan(seedStep)
        expect(cacheStep).toBeGreaterThan(seedRun)
        expect(cacheRun).toBeGreaterThan(cacheStep)
    })

    it('runs the seed step before the route-cache step in ci.yml', () => {
        const at = (fragment) => offsetIn(workflow, '.github/workflows/ci.yml', fragment)

        const seed = at('- name: Build Stache seed (testing env)')
        const cache = at('- name: Build route cache (testing env)')

        expect(cache).toBeGreaterThan(seed)
    })
})
