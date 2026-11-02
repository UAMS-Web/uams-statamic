<?php

declare(strict_types=1);

use App\Services\EntryValidation\CustomConditionEvaluator;
use PHPUnit\Framework\Assert;

// The server-side ports of the editor's custom field conditions (#508). The cases
// follow the JavaScript tests beside each function in resources/js/conditions/.

it('evaluates each custom condition the way its JavaScript does', function (string $name, array $params, mixed $target, array $values, bool $shows): void {
    // Check each row carries the shapes evaluate() takes: string params, field-handle keys
    $paramList = [];

    foreach ($params as $param) {
        Assert::assertIsString($param);
        $paramList[] = $param;
    }

    $fieldValues = [];

    foreach ($values as $handle => $value) {
        Assert::assertIsString($handle);
        $fieldValues[$handle] = $value;
    }

    expect((new CustomConditionEvaluator)->evaluate($name, $paramList, $target, $fieldValues))->toBe($shows);
})->with([
    // articleVisible
    'articleVisible on an original article' => ['articleVisible', [], null, ['syndication_query' => false], true],
    'articleVisible before syndication is answered' => ['articleVisible', [], null, ['syndication_query' => null], false],
    'articleVisible syndicated with no source' => ['articleVisible', [], null, ['syndication_query' => true], false],
    'articleVisible syndicated from NCI' => ['articleVisible', [], null, ['syndication_query' => true, 'syndication_source' => 'nci'], false],
    'articleVisible syndicated from another source' => ['articleVisible', [], null, ['syndication_query' => true, 'syndication_source' => 'other'], true],
    'articleVisible on a string false' => ['articleVisible', [], null, ['syndication_query' => 'false'], true],
    'articleVisible on a numeric false' => ['articleVisible', [], null, ['syndication_query' => 0], true],

    // isNotEmpty, with JavaScript truthiness
    'isNotEmpty on text' => ['isNotEmpty', [], 'Kirk', [], true],
    'isNotEmpty on the string zero' => ['isNotEmpty', [], '0', [], true],
    'isNotEmpty on an empty list, truthy in JavaScript' => ['isNotEmpty', [], [], [], true],
    'isNotEmpty on null' => ['isNotEmpty', [], null, [], false],
    'isNotEmpty on an empty string' => ['isNotEmpty', [], '', [], false],
    'isNotEmpty on false' => ['isNotEmpty', [], false, [], false],
    'isNotEmpty on zero' => ['isNotEmpty', [], 0, [], false],
    'isNotEmpty on a float zero' => ['isNotEmpty', [], 0.0, [], false],

    // notContainsAny
    'notContainsAny with no match' => ['notContainsAny', ['Occupation', 'WorkRole'], 'Credential', [], true],
    'notContainsAny with a match' => ['notContainsAny', ['Occupation', 'WorkRole'], 'WorkRole', [], false],
    'notContainsAny in a list' => ['notContainsAny', ['a', 'b'], ['c', 'b'], [], false],
    'notContainsAny with no params' => ['notContainsAny', [], 'anything', [], false],

    // notSyndicated
    'notSyndicated once answered no' => ['notSyndicated', [], null, ['syndication_query' => false], true],
    'notSyndicated before an answer' => ['notSyndicated', [], null, ['syndication_query' => null], false],
    'notSyndicated with no value at all' => ['notSyndicated', [], null, [], false],
    'notSyndicated on a syndicated article' => ['notSyndicated', [], null, ['syndication_query' => true], false],
    'notSyndicated on a string false' => ['notSyndicated', [], null, ['syndication_query' => 'false'], true],

    // parentOverride
    'parentOverride address on a top-level location' => ['parentOverride', ['address'], null, ['parent' => null], true],
    'parentOverride address on a child that inherits' => ['parentOverride', ['address'], null, ['parent' => 'parent-1'], false],
    'parentOverride address on a child that overrides' => ['parentOverride', ['address'], null, ['parent' => 'parent-1', 'parent_address_override_query' => true], true],
    'parentOverride parking on a child that overrides parking' => ['parentOverride', ['parking'], null, ['parent' => 'parent-1', 'parent_parking_override_query' => true], true],
    'parentOverride on a parent stored as a list' => ['parentOverride', ['address'], null, ['parent' => ['parent-1']], false],
    'parentOverride on an empty parent list' => ['parentOverride', ['address'], null, ['parent' => []], true],
    'parentOverride on an unknown role' => ['parentOverride', ['nonsense'], null, ['parent' => 'parent-1', 'parent_address_override_query' => true], false],
    'parentOverride facility on a top-level location' => ['parentOverride', ['facility'], null, ['parent' => null], true],
    'parentOverride street outside a facility' => ['parentOverride', ['street'], null, ['parent' => null, 'facility_query' => false], true],
    'parentOverride street inside a facility' => ['parentOverride', ['street'], null, ['parent' => null, 'facility_query' => true], false],
    'parentOverride street before the containment answer' => ['parentOverride', ['street'], null, ['parent' => null], false],
    'parentOverride unit inside a facility' => ['parentOverride', ['unit'], null, ['parent' => null, 'facility_query' => true], true],
    'parentOverride unit outside a facility' => ['parentOverride', ['unit'], null, ['parent' => null, 'facility_query' => false], false],
    'parentOverride map on a child overriding the map' => ['parentOverride', ['map'], null, ['parent' => 'p', 'parent_address_override_query' => 'true', 'parent_address_override_map_query' => 1], true],
    'parentOverride map on a child not overriding the map' => ['parentOverride', ['map'], null, ['parent' => 'p', 'parent_address_override_query' => 1, 'parent_address_override_map_query' => 'false'], false],
    'parentOverride unit on a unit-only child' => ['parentOverride', ['unit'], null, ['parent' => 'p', 'parent_address_override_query' => true, 'parent_address_override_unit_query' => true], true],
    'parentOverride street on a child owning everything inside a facility' => ['parentOverride', ['street'], null, ['parent' => 'p', 'parent_address_override_query' => true, 'parent_address_override_facility_query' => true, 'parent_address_override_street_query' => true, 'facility_query' => true], false],

    // parkingVisible
    'parkingVisible facilityInherit inside a picked facility' => ['parkingVisible', ['facilityInherit'], null, ['facility_query' => true, 'facility' => ['f-1']], true],
    'parkingVisible facilityInherit with no facility picked' => ['parkingVisible', ['facilityInherit'], null, ['facility_query' => true, 'facility' => []], false],
    'parkingVisible facility when inheriting' => ['parkingVisible', ['facility'], null, ['facility_query' => true, 'facility' => ['f-1']], false],
    'parkingVisible facility when declining to inherit' => ['parkingVisible', ['facility'], null, ['facility_query' => true, 'facility' => ['f-1'], 'parking_facility_inherit_query' => false], true],
    'parkingVisible inheritToggle standalone with parking' => ['parkingVisible', ['inheritToggle'], null, ['facility_query' => false, 'parking_query' => true], true],
    'parkingVisible inheritToggle standalone without parking' => ['parkingVisible', ['inheritToggle'], null, ['facility_query' => false, 'parking_query' => false], false],
    'parkingVisible instructions when inherited' => ['parkingVisible', ['instructions'], null, ['facility_query' => true, 'facility' => ['f-1']], false],
    'parkingVisible instructions when not inherited' => ['parkingVisible', ['instructions'], null, ['facility_query' => true, 'facility' => ['f-1'], 'parking_instructions_inherit_query' => false], true],
    'parkingVisible directions when not inherited' => ['parkingVisible', ['directions'], null, ['facility_query' => true, 'facility' => ['f-1'], 'directions_from_parking_inherit_query' => false], true],
    'parkingVisible on a child overriding parking' => ['parkingVisible', ['inheritToggle'], null, ['parent' => 'p', 'parent_parking_override_query' => true], true],
    'parkingVisible on a child inheriting parking' => ['parkingVisible', ['facility'], null, ['parent' => 'p'], false],
    'parkingVisible on an unknown role' => ['parkingVisible', ['nonsense'], null, ['facility_query' => false, 'parking_query' => true], false],

    // selectionCount
    'selectionCount below the limit' => ['selectionCount', ['lt', '6'], ['a', 'b'], [], true],
    'selectionCount at the limit' => ['selectionCount', ['lt', '6'], ['a', 'b', 'c', 'd', 'e', 'f'], [], false],
    'selectionCount on nothing selected' => ['selectionCount', ['lt', '1'], null, [], true],
    'selectionCount counts a single value as one' => ['selectionCount', ['equals', '1'], 'only', [], true],
    'selectionCount gt' => ['selectionCount', ['gt', '1'], ['a', 'b'], [], true],
    'selectionCount gte' => ['selectionCount', ['gte', '3'], ['a', 'b'], [], false],
    'selectionCount lte' => ['selectionCount', ['lte', '2'], ['a', 'b'], [], true],
    'selectionCount neq' => ['selectionCount', ['neq', '2'], ['a', 'b'], [], false],
    'selectionCount between' => ['selectionCount', ['between', '1', '3'], ['a', 'b'], [], true],
    'selectionCount outside between' => ['selectionCount', ['between', '3', '5'], ['a', 'b'], [], false],
    'selectionCount against a sibling list' => ['selectionCount', ['lte', 'others'], ['a', 'b'], ['others' => ['x', 'y']], true],
    'selectionCount against a sibling number string' => ['selectionCount', ['gt', 'max'], ['a', 'b'], ['max' => '3'], false],
    'selectionCount against a blank sibling, zero in JavaScript' => ['selectionCount', ['gt', 'max'], ['a'], ['max' => ''], true],
    'selectionCount against a missing operand' => ['selectionCount', ['lt', 'missing'], ['a'], [], false],
    'selectionCount neq a missing operand, like NaN' => ['selectionCount', ['neq', 'missing'], ['a'], [], true],
    'selectionCount with no params' => ['selectionCount', [], ['a'], [], false],
    'selectionCount with an unknown operator' => ['selectionCount', ['near', '2'], ['a'], [], false],
]);

it('returns null for a custom condition it does not port', function (): void {
    expect((new CustomConditionEvaluator)->evaluate('noSuchCondition', [], 'x', []))->toBeNull();
});
