import { describe, expect, it } from 'vitest';
import { reconcileNoneExclusivity } from './schemaNoneExclusivity.js';

const NONE = '__none__';

describe('reconcileNoneExclusivity (schema_type None mutual exclusivity, #966)', () => {
    it('drops None when a real value is added while None was active', () => {
        // prev=[None], emit=[None, Real] -> [Real]
        expect(reconcileNoneExclusivity([NONE], [NONE, 'Airline'], NONE)).toEqual(['Airline']);
    });

    it('collapses to None when None is added to existing real selections', () => {
        // prev=[Real], emit=[Real, None] -> [None]
        expect(reconcileNoneExclusivity(['Airline'], ['Airline', NONE], NONE)).toEqual([NONE]);
    });

    it('collapses to None when None is added to a previously empty selection', () => {
        // prev=[], emit=[None, Real] -> [None]
        expect(reconcileNoneExclusivity([], [NONE, 'Airline'], NONE)).toEqual([NONE]);
    });

    it('leaves a None-free multi-selection untouched', () => {
        expect(reconcileNoneExclusivity(['Airline'], ['Airline', 'EducationalOrganization'], NONE)).toEqual([
            'Airline',
            'EducationalOrganization',
        ]);
    });

    it('passes an empty selection straight through', () => {
        expect(reconcileNoneExclusivity([NONE], [], NONE)).toEqual([]);
    });

    it('passes a lone None through unchanged', () => {
        expect(reconcileNoneExclusivity([], [NONE], NONE)).toEqual([NONE]);
    });

    it('collapses duplicate sentinels to a single None', () => {
        // prev without None, emit=[None, None] -> [None]
        expect(reconcileNoneExclusivity([], [NONE, NONE], NONE)).toEqual([NONE]);
    });

    it('coerces a non-array incoming value to an empty selection', () => {
        expect(reconcileNoneExclusivity([], null, NONE)).toEqual([]);
    });
});
