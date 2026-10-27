import { describe, it, expect } from 'vitest'
import { rewriteBare, rewriteLinks } from './repoint-archive-references.mjs'

// Coverage for the archive re-pointing script (#3383)

const HISTORY = { max: 3410, open: new Set([2661]) }

/**
 * Rewrite one line and return the reasons its bare references were left.
 *
 * @param {string} line  the line to rewrite
 * @param {object} [kind]  the line's file kind, as `rewriteBare` takes it
 * @returns {{line: string, rewritten: number, why: string[]}} the result and the reasons
 */
function bare(line, kind) {
    const left = []
    const result = rewriteBare(line, HISTORY, left, kind)

    return { ...result, why: left.map((l) => l.why) }
}

describe('rewriteLinks', () => {
    it('moves history links and qualified references to the archive', () => {
        const counts = {}
        const out = rewriteLinks(
            [
                'https://github.com/UAMS-Web/uams-statamic/pull/3396',
                'https://github.com/UAMS-Web/uams-statamic/issues/5#issuecomment-1',
                'https://github.com/UAMS-Web/uams-statamic/commit/abc123',
                'https://github.com/UAMS-Web/uams-statamic/blob/3383-branch/README.md',
                'UAMS-Web/uams-statamic#849 and uams-statamic#12',
            ].join('\n'),
            counts,
        )

        expect(out).toBe([
            'https://github.com/UAMS-Web/uams-statamic-archive/pull/3396',
            'https://github.com/UAMS-Web/uams-statamic-archive/issues/5#issuecomment-1',
            'https://github.com/UAMS-Web/uams-statamic-archive/commit/abc123',
            'https://github.com/UAMS-Web/uams-statamic-archive/blob/3383-branch/README.md',
            'UAMS-Web/uams-statamic-archive#849 and uams-statamic-archive#12',
        ].join('\n'))
        expect(counts).toEqual({ url: 3, url_ref: 1, qualified: 1, short: 1 })
    })

    it('leaves links that follow the repository, and other repositories', () => {
        const kept = [
            'https://github.com/UAMS-Web/uams-statamic',
            'https://github.com/UAMS-Web/uams-statamic/blob/main/README.md',
            'https://github.com/UAMS-Web/uams-statamic/blob/<branch>/path/to/file.php',
            'https://github.com/UAMS-Web/uams-statamic/tree/main/app',
            'UAMS-Web/uams-statamic-content#82',
            'UAMS-Web/uams-statamic-archive#4',
            'uams-web/uams-statamic',
        ].join('\n')

        expect(rewriteLinks(kept)).toBe(kept)
    })
})

describe('rewriteBare', () => {
    it('qualifies a bare reference to this repository\'s history', () => {
        expect(bare('see #464 and (#624/#628); also #1959;')).toMatchObject({
            line: 'see UAMS-Web/uams-statamic-archive#464 and (UAMS-Web/uams-statamic-archive#624/UAMS-Web/uams-statamic-archive#628); '
                + 'also UAMS-Web/uams-statamic-archive#1959;',
            rewritten: 4,
        })
    })

    it('leaves a reference, with its reason, when it may not be this repository\'s', () => {
        const cases = [
            ['an open issue moves: #2661', 'open issue, moves in #2661'],
            ['above the range: #3999', 'above this repository\'s numbers'],
            ['link text: [#808](https://example.test/808)', 'link text'],
            ['UAMS-Web/wordpress-importer#215 and #216', 'line names another repository'],
            ['upstream `statamic/cms#15546` beside #1693', 'line names another repository'],
            ['the importer\'s wordpress-importer#443 beside #500', 'line names another repository'],
        ]
        for (const [line, why] of cases) {
            expect(bare(line), line).toEqual({ line, rewritten: 0, why: [why] })
        }
    })

    it('does not count this repository\'s own qualified references as another repository', () => {
        expect(bare('UAMS-Web/uams-statamic#12, uams-statamic-archive#13 and #14').line)
            .toBe('UAMS-Web/uams-statamic#12, uams-statamic-archive#13 and UAMS-Web/uams-statamic-archive#14')
    })

    it('rewrites beside a product or package name that is not a citation', () => {
        expect(bare('the Find-a-Doc API and statamic/cms v6 (#1043)').rewritten).toBe(1)
    })

    it('leaves numeric character references, words and anchors', () => {
        for (const line of ['Jerry&amp;#039;s', 'Jerry&#039;s', 'The ABC&amp;#39;s', 'abc#123', 'see [the part](#12)']) {
            expect(bare(line), line).toMatchObject({ line, rewritten: 0 })
        }
    })

    it('leaves a CSS color, named property or not', () => {
        expect(bare('<path fill="#000"/>')).toMatchObject({ rewritten: 0, why: ['CSS color'] })
        expect(bare('color: #222;')).toMatchObject({ rewritten: 0, why: ['CSS color'] })
        for (const line of ['$black: #000;', 'box-shadow: 0px 2px 5px #000;']) {
            expect(bare(line, { style: true }), line).toMatchObject({ rewritten: 0, why: ['CSS color'] })
            expect(bare(line).rewritten, line).toBe(1)
        }
        expect(bare('// Lite-YouTube facade (#224)', { style: true }).rewritten).toBe(1)
    })

    it('leaves a code string that is data, whole, and rewrites a comment', () => {
        const code = { prose: false }
        for (const line of [
            "expect(json).toBe('#42-duns')",
            "$id = $base.' #42-duns';",
            "'#946 residency_years content' => ['residency_years', 'content'],",
            'Land first: ${repo}#71 (#72)',
            '"#1068/#1069 mount bump"',
        ]) {
            const result = bare(line, code)
            expect(result, line).toMatchObject({ line, rewritten: 0 })
            expect(new Set(result.why), line).toEqual(new Set(['string data']))
        }
        expect(bare("throw new Error('see (#830) for why')", code).rewritten).toBe(1)
        expect(bare("    // `#2143` requires it, and '#2144' too", code).rewritten).toBe(2)
    })

    it('leaves every line of a test but its comments', () => {
        const test = { prose: false, test: true }
        expect(bare("it('coerces each row (#675)', function () {", test)).toMatchObject({ rewritten: 0, why: ['string data'] })
        expect(bare(' * Pins the fix from #675.', test).rewritten).toBe(1)
        expect(bare(' | Scheduled refresh (#3178)', test).rewritten).toBe(1)
    })

    it('treats a prose file as prose whatever its strings look like', () => {
        expect(bare("the `'#42'` case, and #43-style ranges", { prose: true }).rewritten).toBe(2)
    })
})
