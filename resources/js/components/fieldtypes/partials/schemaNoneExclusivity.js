/**
 * Reconcile a multi-select update so the "None applies" sentinel and real
 * schema-type selections stay mutually exclusive: whichever the editor just
 * added wins. A newly-added None collapses the selection to `[None]`; a real
 * value added while None was active drops None. Extracted from the schema_type
 * fieldtype's `onSingleModelUpdate` so the exclusivity rule is unit-testable
 * without mounting the Vue component (UAMS-Web/uams-statamic-archive#966).
 *
 * @param {string[]} prev       Previous selection array (the field's prior value)
 * @param {string[]} next       Incoming selection array from the combobox
 * @param {string}   noneValue  The reserved None sentinel value
 * @returns {string[]} The reconciled selection array
 */
export function reconcileNoneExclusivity(prev, next, noneValue) {
    const incoming = Array.isArray(next) ? next : [];

    // Nothing to reconcile unless the update both contains None and has more
    // than one entry (a lone [None] or a None-free list is already exclusive).
    if (incoming.length <= 1 || !incoming.includes(noneValue)) {
        return incoming;
    }

    const previous = Array.isArray(prev) ? prev : [];

    // If None was already selected, the editor just added a real value -> keep
    // the reals and drop None. Otherwise None was just added -> it wins.
    return previous.includes(noneValue)
        ? incoming.filter((value) => value !== noneValue)
        : [noneValue];
}
