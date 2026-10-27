// Coverage for the `ipv6-address` ignore pattern in cspell.json (UAMS-Web/uams-statamic-archive#3271).
//
// Without the pattern, an IPv6 literal fails `spell` on whichever 16-bit groups
// are all letters (`fffa`, `fefe`), and those characters are the address, so they
// cannot be respelled. These tests run the real cspell over stdin with the
// repository's own config, so they fail if the pattern is removed, renamed out of
// `ignoreRegExpList`, or widened until it hides ordinary code.

import { describe, expect, it } from 'vitest'
import { spawnSync } from 'node:child_process'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..')

/**
 * Spell-check PHP source with the repository config and return the unknown words.
 *
 * @param {string} source
 * @return {{status: ?number, words: string[], output: string}}
 */
function unknownWords(source) {
    const result = spawnSync(
        process.execPath,
        [
            join(ROOT, 'node_modules', 'cspell', 'bin.mjs'),
            '--no-progress',
            '--no-summary',
            '--config',
            join(ROOT, 'cspell.json'),
            '--language-id',
            'php',
            'stdin://probe.php',
        ],
        { cwd: ROOT, encoding: 'utf8', input: source }
    )
    const output = `${result.stdout ?? ''}${result.stderr ?? ''}`
    const words = [...output.matchAll(/Unknown word \(([^)]+)\)/g)].map((match) => match[1])

    return { status: result.status, words, output }
}

describe('the ipv6-address cspell pattern', () => {
    it('does not flag the letter-only groups of an address literal', () => {
        const { status, words, output } = unknownWords([
            '<?php',
            "$teredo = '2001:100:808:808::f5ff:fffa';",
            "$bare = '2001:0:808:808:0:f7f7:3f57:fefe';",
            "$bracketed = '[2001:db8::fefe]:443';",
            "$zoned = 'fe80::abcd:fffa%eth0';",
            '',
        ].join('\n'))

        expect(words, output).toEqual([])
        expect(status, output).toBe(0)
    })

    it('still flags a misspelling in a static call and in prose', () => {
        // Bounds the pattern on the other side: `Class::method` must stay checked,
        // so the pattern cannot have grown into a blanket double-colon ignore.
        // `faec` is all hex letters, so only the guard against a preceding word
        // character keeps `::faec` from reading as an address.
        const { status, words } = unknownWords([
            '<?php',
            'Foo::metodd();',
            'Bar::faec();',
            '// this sentense has a tpyo',
            '',
        ].join('\n'))

        expect(words).toEqual(['metodd', 'faec', 'sentense', 'tpyo'])
        expect(status).toBe(1)
    })

    it('still flags a letter group that is not inside an address', () => {
        // A lone group, or one joined to a word by a single colon, is not an
        // address, so it must reach the dictionary. A run of groups that ends in a
        // word is not one either, so neither end may touch a word character.
        const { words } = unknownWords([
            '<?php',
            "$lone = 'fffa';",
            "$keyed = 'key:wrongg:fefe';",
            "$tail = 'fffa:fefe:wrongg';",
            '',
        ].join('\n'))

        expect(words).toEqual(['fffa', 'wrongg', 'fefe', 'fffa', 'fefe', 'wrongg'])
    })
})
