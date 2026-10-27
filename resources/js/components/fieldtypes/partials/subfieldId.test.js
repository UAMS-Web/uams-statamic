import { describe, expect, it } from 'vitest';
import { subfieldId } from './subfieldId.js';

describe('subfieldId (publish container in subfield ids, #327)', () => {
    it('builds on the id Statamic gives the fieldtype', () => {
        expect(subfieldId('field_base_address', 'address', 'zip_code')).toBe('field_base_address__zip_code');
    });

    it('gives the same fieldtype different ids in two publish containers', () => {
        const inBase = subfieldId('field_base_address', 'address', 'zip_code');
        const inStack = subfieldId('field_stack_1_address', 'address', 'zip_code');

        expect(inBase).not.toBe(inStack);
    });

    it('keeps the field path, so a nested fieldtype differs from a top-level one', () => {
        expect(subfieldId('field_base_locations_0_address', 'address', 'zip_code')).toBe(
            'field_base_locations_0_address__zip_code',
        );
    });

    it('never equals the id Statamic gives a sibling field named after the subfield', () => {
        // An `address` field `postal` beside a text field `postal_city`.
        expect(subfieldId('field_base_postal', 'postal', 'city')).not.toBe('field_base_postal_city');
    });

    it('replaces dots so the id is usable as a selector', () => {
        expect(subfieldId('field_base_a.b', 'b', 'c.d')).toBe('field_base_a_b__c_d');
    });

    it('falls back to the parent handle without a framework id', () => {
        expect(subfieldId(undefined, 'address', 'zip_code')).toBe('field_address__zip_code');
        expect(subfieldId('', 'address', 'zip_code')).toBe('field_address__zip_code');
        expect(subfieldId(null, 'address', 'zip_code')).toBe('field_address__zip_code');
    });
});
