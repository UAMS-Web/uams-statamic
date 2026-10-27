import { describe, it, expect } from 'vitest';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { RULE_PATH, checkWorktreesRule } from './check-worktrees-rule-submodules.mjs';
import { SUBMODULES } from './worktree-slots.mjs';

// UAMS-Web/uams-statamic-archive#3167: the synced worktrees rule's init lines must name exactly the submodules
// scripts/worktree-slots.mjs declares. This runs the check against the rule as
// committed, so a submodule added to the script and not to the rule fails the js job.

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const rule = readFileSync(join(root, RULE_PATH), 'utf8');

describe('the worktrees rule names the submodules worktree-slots.mjs declares (#3167)', () => {
    it('passes the rule as committed, comparing every init line', () => {
        const result = checkWorktreesRule(rule);

        expect(result.code).toBe(0);
        expect(result.lines[0]).toMatch(/^2 init line\(s\)/);
    });

    it('compares against the SUBMODULES the script exports, all five of them', () => {
        expect(SUBMODULES).toEqual(['content', 'users', 'resources/forms', 'public/assets', 'app/private/assets']);
    });

    it('fails a copy of the rule with one submodule removed, and says where to fix it', () => {
        // The positive control: a rule known to be wrong has to be refused.
        const stale = rule.replace(/(git submodule update --init .*?) resources\/forms/, '$1');
        expect(stale).not.toBe(rule);

        const result = checkWorktreesRule(stale);
        const text = result.lines.join('\n');

        expect(result.code).toBe(1);
        expect(text).toContain('omits resources/forms.');
        expect(text).toContain('synced from UAMS-Web/uams-claude-skills: fix it there');
    });

    it('fails when the script declares a submodule the rule does not name', () => {
        const result = checkWorktreesRule(rule, [...SUBMODULES, 'resources/new']);

        expect(result.code).toBe(1);
        expect(result.lines.join('\n')).toContain('omits resources/new.');
    });

    it('fails when a line names a submodule SUBMODULES does not declare', () => {
        const result = checkWorktreesRule('git submodule update --init content users extra\n', ['content', 'users']);

        expect(result.code).toBe(1);
        expect(result.lines[0]).toBe(`${RULE_PATH}:1 names extra, which SUBMODULES does not declare.`);
    });

    it('cannot run against a rule with no init line', () => {
        expect(checkWorktreesRule('no recipe here\n').code).toBe(2);
    });
});
