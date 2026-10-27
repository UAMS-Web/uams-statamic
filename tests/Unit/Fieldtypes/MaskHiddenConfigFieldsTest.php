<?php

declare(strict_types=1);

use App\Fieldtypes\CssSelector;
use App\Fieldtypes\Currency;
use App\Fieldtypes\Floatval;
use App\Fieldtypes\Hidden;
use App\Fieldtypes\Integer;
use App\Fieldtypes\Phone;
use App\Fieldtypes\Time;
use App\Fieldtypes\Xpath;
use App\Fieldtypes\ZipCode;

/**
 * Mask-suppressing fieldtypes hide every `input_mask_*` config field so the
 * masking is driven by their own presets/defaults, not hand-edited in the CP.
 * When UAMS-Web/uams-statamic-archive#892 replaced the eval'd `input_mask_prepareChar` code field with the
 * declarative `input_mask_prepare_case` select, each fieldtype's hidden list
 * had to follow — otherwise the new select renders in the Configure-Field
 * modal (visibly so for the input_mask=true defaults like ZipCode/Currency).
 * This locks the rename across all nine so it can't silently regress.
 */
it('hides input_mask_prepare_case (and not the removed input_mask_prepareChar)', function (string $fieldtype) {
    $method = new ReflectionMethod($fieldtype, 'getHiddenConfigFields');
    $method->setAccessible(true);

    $hidden = $method->invoke(new $fieldtype);

    expect($hidden)
        ->toContain('input_mask_prepare_case')
        ->and($hidden)->not->toContain('input_mask_prepareChar');
})->with([
    Integer::class,
    ZipCode::class,
    Time::class,
    Hidden::class,
    Currency::class,
    CssSelector::class,
    Xpath::class,
    Floatval::class,
    Phone::class,
]);
