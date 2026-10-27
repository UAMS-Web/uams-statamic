// Coverage for which jobs make the runner resolve Node (UAMS-Web/uams-statamic-archive#2880).
//
// `node` is resolved lazily, so a job that runs a script through it but is missing
// from NODE_JOBS spawns `null` whenever `--jobs` selects it without another Node job.
// That is how `--jobs no-emoji` crashed: only `encoding` resolved Node. These read
// the job bodies from the source, so a new job that calls `node` fails here until it
// is listed, rather than on the first run that selects it alone.
import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { join, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import { ALL_JOBS, NODE_JOBS } from './local-ci.mjs'

const source = readFileSync(join(dirname(fileURLToPath(import.meta.url)), 'local-ci.mjs'), 'utf8')
const lines = source.split('\n')

/**
 * Each job's body, keyed by job name: the lines from its `'<job>': () => {` opener to
 * the next job's opener, or to the end of the job table for the last one.
 *
 * @returns {Map<string, string>} Job name to body text.
 */
function jobBodies() {
    const opener = /^ {8}'?([a-z][a-z-]*)'?: \(\) => \{$/
    const starts = lines
        .map((line, i) => [line.match(opener), i])
        .filter(([match]) => match !== null)
        .map(([match, i]) => [match[1], i])
    const bodies = new Map()

    starts.forEach(([name, start], n) => {
        const end = n + 1 < starts.length ? starts[n + 1][1] : start + 80
        bodies.set(name, lines.slice(start, end).join('\n'))
    })

    return bodies
}

describe('NODE_JOBS', () => {
    it('reads a body for every job, so an empty match cannot pass for coverage', () => {
        // The positive control for the test below: if the opener pattern stopped matching,
        // every job would look Node-free and the comparison would pass vacuously.
        expect([...jobBodies().keys()].sort()).toEqual([...ALL_JOBS].sort())
    })

    it('lists exactly the jobs whose bodies run something through node', () => {
        const usesNode = [...jobBodies()]
            .filter(([, body]) => /\b(run|capture|captureResult)\(node\b/.test(body))
            .map(([name]) => name)

        expect(usesNode.sort()).toEqual([...NODE_JOBS].sort())
    })

    it('resolves node whenever the job list includes any of them', () => {
        // The resolution must key on NODE_JOBS, not on one job's name, or the list above
        // documents a coverage the runner does not have.
        const condition = lines.findIndex((line) => line.includes('if (jobs.some((job) => NODE_JOBS.includes(job))) {'))

        expect(condition).toBeGreaterThan(-1)
        expect(lines[condition + 1]).toContain('node = resolveTool({')
        expect(source.match(/node = resolveTool\(\{/g)).toHaveLength(1)
    })
})
