// Guard `project-words.txt` against losing its sorted order (UAMS-Web/uams-statamic-archive#2284).
//
// UAMS-Web/uams-statamic-archive#2283 restored the file to fully sorted order after it had drifted twice, by
// two different routes, both silent:
//
//   - UAMS-Web/uams-statamic-archive#1922 sorted the WHOLE file, which put the four header comments into
//     alphabetical order and split one sentence across two non-adjacent lines.
//     It stayed scrambled for a month.
//   - Four entries were inserted between `Iñapari` and `İşaret`, and one a line
//     early, by the rule "insert before the first line that sorts higher" —
//     correct for a sorted file, self-propagating in an unsorted one.
//
// `cspell` reads every line regardless of order, so `npm run spell` reported 0
// issues throughout both. Nothing could have caught either.
//
// This lives beside the file rather than in `scripts/` as a check invoked from
// the runner, because `vitest.config.js` already collects `scripts/**/*.test.mjs`
// into the `js` job — so it gates with no wiring, and without touching
// `local-ci.mjs` or `ci.yml`, which a parked pull request holds.

import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const WORDS = resolve(dirname(fileURLToPath(import.meta.url)), '..', 'project-words.txt')

/**
 * Split the dictionary into its comment header and its word list.
 *
 * The header is the leading run of `#` lines. Everything after it is the sorted
 * region; blank lines are dropped from the comparison but their positions are
 * kept, so a reported line number matches what an editor shows.
 *
 * @return {{header: string[], words: {line: number, text: string}[], strays: number[]}}
 */
function readDictionary() {
    const lines = readFileSync(WORDS, 'utf8').split(/\r?\n/)

    const header = []
    let index = 0
    while (index < lines.length && lines[index].startsWith('#')) {
        header.push(lines[index])
        index += 1
    }

    const words = []
    const strays = []
    for (let i = index; i < lines.length; i += 1) {
        const text = lines[i]
        if (text.trim() === '') {
            continue
        }
        // A `#` line below the header is the UAMS-Web/uams-statamic-archive#1922 defect: a comment that has been
        // sorted into the word list, where it is both a bogus dictionary entry and
        // a sentence fragment separated from the rest of its sentence.
        if (text.startsWith('#')) {
            strays.push(i + 1)
            continue
        }
        words.push({ line: i + 1, text })
    }

    return { header, words, strays }
}

/** Codepoint order on the lowercased word — see the test below for why this one. */
const sortsBefore = (a, b) => {
    const left = a.toLowerCase()
    const right = b.toLowerCase()
    return left < right ? -1 : left > right ? 1 : 0
}

describe('project-words.txt', () => {
    it('has a header and a word list to check, so neither assertion can pass vacuously', () => {
        const { header, words } = readDictionary()

        // A guard over an empty parse is not a guard. If the file moves or the
        // header shape changes, this fails first and says so, rather than the
        // ordering check below reporting a sorted list of nothing.
        expect(header.length).toBeGreaterThan(0)
        expect(words.length).toBeGreaterThan(1000)
    })

    it('is sorted by codepoint order on the lowercased word', () => {
        const { words } = readDictionary()

        // The comparator is measured, not assumed. Against this file, codepoint
        // order on the lowercased string has ZERO breaks across 18,622 words,
        // while raw codepoint order has 1,220 and `localeCompare` has 217-218.
        // Getting this wrong would not fail loudly — it would flag hundreds of
        // correct lines and teach the next reader to distrust the check.
        //
        // The pair that discriminates: `izzi` < `Iñapari` < `İşaret`. Lowercased,
        // `İ` becomes `i` + U+0307, which sorts after `ñ` (U+00F1) — so this
        // ordering is only correct under this comparator.
        const breaks = []
        for (let i = 1; i < words.length; i += 1) {
            if (sortsBefore(words[i - 1].text, words[i].text) > 0) {
                breaks.push(
                    `line ${words[i].line}: ${JSON.stringify(words[i].text)} sorts before `
                    + `line ${words[i - 1].line}: ${JSON.stringify(words[i - 1].text)}`
                    + (words[i + 1] ? ` (next is ${JSON.stringify(words[i + 1].text)})` : '')
                )
            }
        }

        // Named neighbors rather than a bare count: an 18,000-line file gives a
        // reader no other way to find the pair, and "the file is unsorted" is not
        // an actionable message.
        expect(breaks).toEqual([])
    })

    it('keeps its comment block at the top, out of the sorted region', () => {
        const { strays } = readDictionary()

        // The half that would have caught UAMS-Web/uams-statamic-archive#1922. A check on the word list alone
        // passes while the header is scrambled, because a sorted header is still
        // a sorted list of lines — it is only wrong as a HEADER.
        expect(strays).toEqual([])
    })

    it('records the convention in the file itself, where an editor will meet it', () => {
        const { header } = readDictionary()
        const text = header.join('\n')

        // A guard nobody knows about produces a puzzling red. The header is the
        // one place a person adding a word is already looking.
        expect(text).toContain('sorted')
        expect(text).toContain('project-words-sorted.test.mjs')
    })
})
