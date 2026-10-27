<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use Statamic\Fields\Fieldtype;

/**
 * Subsection fieldtype.
 *
 * Separates logical blocks of content within a Panel Card.
 *
 * Statamic v6 deprecated the Section fieldtype. The fieldtype is still available,
 * but will be removed in Statamic 7. This fieldtype serves as a replacement for
 * the Section fieldtype.
 */
class Subsection extends Fieldtype
{
    /**
     * The display title of the fieldtype in the Control Panel.
     *
     * @var string The display title of the fieldtype in the Control Panel.
     */
    protected static $title = 'Subsection';

    /**
     * Fieldtype Keywords
     *
     * @var array<string> Search keywords used to find this fieldtype in the Control Panel
     */
    protected $keywords = [
        'divider',
        'header',
        'heading',
        'section',
        'sub-section',
        'subsection',
        'subtitle',
        'title',
    ];

    /**
     * The categories this fieldtype belongs to in the Statamic fieldtype selector.
     *
     * @var array<string> Categories this fieldtype belongs to in the Statamic fieldtype selector.
     */
    protected $categories = [
        'special',
    ];

    /**
     * Indicates whether the fieldtype's value can be localized.
     *
     * @var bool Indicates whether the fieldtype's value can be localized
     */
    protected $localizable = false;

    /**
     * Indicates whether the fieldtype's value can be validated.
     *
     * @var bool Indicates whether the fieldtype's value can be validated
     */
    protected $validatable = false;

    /**
     * Indicates whether the fieldtype can have a default value.
     *
     * @var bool Indicates whether the fieldtype can have a default value
     */
    protected $defaultable = false;

    /**
     * The name of the Vue component to use for this fieldtype.
     *
     * @var string The name of the Vue component to use for this fieldtype
     */
    protected $component = 'subsection';

    /**
     * The icon for this fieldtype.
     *
     * @return string The icon name
     */
    #[\Override]
    public function icon(): string
    {
        return 'fieldtype-section';
    }
}
