<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use Statamic\Fieldtypes\Section as BaseSection;

/**
 * Application Section fieldtype; selectable in form blueprints.
 */
class Section extends BaseSection
{
    /**
     * Indicates whether the fieldtype is selectable in form blueprints.
     *
     * @var bool Indicates whether the fieldtype is selectable in form blueprints
     */
    protected $selectableInForms = true;
}
