#!/usr/bin/env node
// Fails when the synced `worktrees` rule's `git submodule update --init` lines
// name a different set of submodules from `SUBMODULES` in
// scripts/worktree-slots.mjs (UAMS-Web/uams-statamic-archive#3167).
//
// The rule described four submodules after UAMS-Web/uams-statamic-archive#2860 added `resources/forms` as the
// fifth, and its provisioning recipe stood slots up without it. The rule is
// synced from UAMS-Web/uams-claude-skills, whose gate cannot read this
// repository's script, so the comparison lives here, beside the list it reads.
// SUBMODULES is imported, never copied, so the check cannot agree with a stale
// list of its own.
//
// USAGE
//     node scripts/check-worktrees-rule-submodules.mjs
//
// EXIT CODES
//     0  every init line names exactly SUBMODULES
//     1  an init line names a different set
//     2  the rule could not be read, or carries no init line to compare
//
// scripts/check-worktrees-rule-submodules.test.mjs runs this in the js job.

import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import process from 'node:process';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { SUBMODULES } from './worktree-slots.mjs';

export const RULE_PATH = '.claude/rules/worktrees.md';

const INIT_LINE = /^\s*git submodule update --init\s+(.*)$/;

/**
 * Compare every `git submodule update --init` line in the rule with the submodules
 * the script declares.
 *
 * @param  {string}   text        The rule's contents.
 * @param  {string[]} submodules  The submodules the script declares.
 * @return {{code: number, lines: string[]}}
 */
export function checkWorktreesRule(text, submodules = SUBMODULES) {
    // Each init line, with its line number and the paths it names. A trailing
    // `# comment` is not a path.
    const inits = text.split(/\r?\n/).flatMap((line, index) => {
        const match = line.match(INIT_LINE);

        return match ? [{ number: index + 1, paths: match[1].replace(/\s+#.*$/, '').trim().split(/\s+/).filter(Boolean) }] : [];
    });

    if (inits.length === 0) {
        return { code: 2, lines: [`${RULE_PATH} carries no \`git submodule update --init\` line, so there is nothing to compare.`] };
    }

    const problems = inits.flatMap(({ number, paths }) => {
        const missing = submodules.filter((path) => !paths.includes(path));
        const extra = paths.filter((path) => !submodules.includes(path));

        return [
            ...(missing.length > 0 ? [`${RULE_PATH}:${number} omits ${missing.join(', ')}.`] : []),
            ...(extra.length > 0 ? [`${RULE_PATH}:${number} names ${extra.join(', ')}, which SUBMODULES does not declare.`] : []),
        ];
    });

    if (problems.length > 0) {
        return {
            code: 1,
            lines: [
                ...problems,
                `SUBMODULES in scripts/worktree-slots.mjs: ${submodules.join(', ')}.`,
                `${RULE_PATH} is synced from UAMS-Web/uams-claude-skills: fix it there, not in this copy, or change SUBMODULES if the script is what is wrong.`,
            ],
        };
    }

    return { code: 0, lines: [`${inits.length} init line(s) in ${RULE_PATH} name exactly SUBMODULES: ${submodules.join(', ')}.`] };
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
    const root = join(dirname(fileURLToPath(import.meta.url)), '..');
    let result;

    try {
        result = checkWorktreesRule(readFileSync(join(root, RULE_PATH), 'utf8'));
    } catch (error) {
        result = { code: 2, lines: [`Could not read ${RULE_PATH}: ${error.message}`] };
    }

    const out = result.code === 0 ? console.log : console.error;

    for (const line of result.lines) {
        out(line);
    }

    process.exit(result.code);
}
