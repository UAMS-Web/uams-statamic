<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use Statamic\Fields\Field;
use Statamic\Fields\Fieldtype;

/**
 * Blueprint-driven text plus IPA phonetic pair, using the same CP control as
 * the PersonName fieldtype subfields.
 */
class TextPhoneticPair extends Fieldtype
{
    /**
     * Display title in the fieldtype picker.
     *
     * @var string
     */
    protected static $title = 'Text & Phonetic Pair';

    /**
     * The name of the Vue component to use for this fieldtype.
     *
     * @var string
     */
    protected $component = 'text_phonetic_pair';

    /**
     * @var bool
     */
    protected $selectable = true;

    /**
     * @var bool
     */
    protected $selectableInForms = true;

    /**
     * @var array<int, string>
     */
    protected $keywords = [
        'text',
        'phonetic',
        'ipa',
        'pair',
    ];

    /**
     * @var array<int, string>
     */
    protected $categories = [
        'text',
    ];

    /**
     * The icon for this fieldtype.
     *
     * @return string The icon name
     */
    #[\Override]
    public function icon(): string
    {
        return 'text-formatting';
    }

    /**
     * Blueprint editor: labels, layout, and validation toggles (mirrors the
     * partial component props and {@see PersonName} patterns).
     *
     * @return array<int, array<string, mixed>>
     */
    #[\Override]
    protected function configFieldItems(): array
    {
        /** @var array<int, array<string, mixed>> $parent */
        $parent = parent::configFieldItems();

        $custom = [
            [
                'display' => __('statamic::fieldtypes.text_phonetic_pair.config.section_heading'),
                'fields' => [
                    'text_label' => [
                        'type' => 'text',
                        'display' => __('statamic::fieldtypes.text_phonetic_pair.config.text_label_display'),
                        'instructions' => __(
                            'statamic::fieldtypes.text_phonetic_pair.config.text_label_instructions'
                        ),
                        'default' => '',
                    ],
                    'phonetic_label' => [
                        'type' => 'text',
                        'display' => __('statamic::fieldtypes.text_phonetic_pair.config.phonetic_label_display'),
                        'instructions' => __(
                            'statamic::fieldtypes.text_phonetic_pair.config.phonetic_label_instructions'
                        ),
                        'default' => '',
                    ],
                    'quote_straight_on_text' => [
                        'type' => 'toggle',
                        'display' => __('statamic::fieldtypes.text_phonetic_pair.config.quote_straight_on_text_display'),
                        'instructions' => __(
                            'statamic::fieldtypes.text_phonetic_pair.config.quote_straight_on_text_instructions'
                        ),
                        'default' => false,
                    ],
                    'split_inputs' => [
                        'type' => 'toggle',
                        'display' => __('statamic::fieldtypes.text_phonetic_pair.config.split_inputs_display'),
                        'instructions' => __(
                            'statamic::fieldtypes.text_phonetic_pair.config.split_inputs_instructions'
                        ),
                        'default' => true,
                    ],
                    'border' => [
                        'type' => 'toggle',
                        'display' => __('statamic::fieldtypes.text_phonetic_pair.config.border_display'),
                        'instructions' => __(
                            'statamic::fieldtypes.text_phonetic_pair.config.border_instructions'
                        ),
                        'default' => true,
                    ],
                    'body_class' => [
                        'type' => 'text',
                        'display' => __('statamic::fieldtypes.text_phonetic_pair.config.body_class_display'),
                        'instructions' => __(
                            'statamic::fieldtypes.text_phonetic_pair.config.body_class_instructions'
                        ),
                        'default' => 'space-y-6',
                    ],
                    'require_text' => [
                        'type' => 'toggle',
                        'display' => __('statamic::fieldtypes.text_phonetic_pair.config.require_text_display'),
                        'instructions' => __(
                            'statamic::fieldtypes.text_phonetic_pair.config.require_text_instructions'
                        ),
                        'default' => true,
                    ],
                    'require_phonetic' => [
                        'type' => 'toggle',
                        'display' => __('statamic::fieldtypes.text_phonetic_pair.config.require_phonetic_display'),
                        'instructions' => __(
                            'statamic::fieldtypes.text_phonetic_pair.config.require_phonetic_instructions'
                        ),
                        'default' => false,
                    ],
                    'include_phonetic_instructions' => [
                        'type' => 'toggle',
                        'display' => __(
                            'statamic::fieldtypes.text_phonetic_pair.config.include_phonetic_instructions_display'
                        ),
                        'instructions' => __(
                            'statamic::fieldtypes.text_phonetic_pair.config.include_phonetic_instructions_instructions'
                        ),
                        'default' => true,
                    ],
                ],
            ],
        ];

        return \array_merge(
            $parent,
            $custom
        );
    }

    /**
     * @return array<int, string>
     */
    #[\Override]
    public function rules(): array
    {
        return ['array'];
    }

    /**
     * Human-readable names for nested validation keys.
     *
     * @return array<string, string>
     */
    #[\Override]
    public function extraValidationAttributes(): array
    {
        $field = $this->field();

        if (! $field instanceof Field) {
            return [];
        }

        /** @var string $prefix */
        $prefix = $field->handle();

        return [
            $prefix.'.text' => $this->textPhoneticPairFieldtypeFieldLabel('text'),
            $prefix.'.phonetic_text' => $this->textPhoneticPairFieldtypeFieldLabel('phonetic_text'),
        ];
    }

    /**
     * Translated CP / validation label for a `text_phonetic_pair.field.{key}` entry.
     *
     * @param  string  $langKey  Key under `text_phonetic_pair.field` in fieldtypes.php
     */
    private function textPhoneticPairFieldtypeFieldLabel(string $langKey): string
    {
        return __('statamic::fieldtypes.text_phonetic_pair.field.'.$langKey);
    }

    /**
     * Nested validation rules for `text` and `phonetic_text`.
     *
     * @return array<string, array<int, string>>
     */
    #[\Override]
    public function extraRules(): array
    {
        $field = $this->field();

        if (! $field instanceof Field) {
            return [];
        }

        $config = $field->config();
        /** @var string $prefix */
        $prefix = $field->handle();
        $validate = $config['validate'] ?? [];
        $validateHasRequired = \is_array($validate) && \in_array('required', $validate, true);
        $required = ! empty($config['required']) || $validateHasRequired;
        $requireText = ! empty($config['require_text']);
        $requirePhonetic = ! empty($config['require_phonetic']);
        $mustText = $required || $requireText;

        $dot = static fn (string $path): string => $prefix.'.'.$path;

        $rules = [];

        $rules[$dot('text')] = $mustText
            ? ['required', 'string', 'name']
            : ['nullable', 'string', 'name'];

        $rules[$dot('phonetic_text')] = $requirePhonetic
            ? ['required', 'string', 'international_phonetic_alphabet']
            : ['nullable', 'string', 'international_phonetic_alphabet'];

        unset(
            $field,
            $config,
            $validate,
            $validateHasRequired,
            $required,
            $requireText,
            $requirePhonetic,
            $mustText,
            $prefix,
            $dot
        );

        return $rules;
    }

    /**
     * Default value for new entries.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function defaultValue(): array
    {
        return $this->emptyPair();
    }

    /**
     * @return array{text: string, phonetic_text: string}
     */
    private function emptyPair(): array
    {
        return [
            'text' => '',
            'phonetic_text' => '',
        ];
    }

    /**
     * Normalize for the publish form.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function preProcess(mixed $data): array
    {
        return $this->normalizeStored($data);
    }

    /**
     * Persist normalized pair.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function process(mixed $data): array
    {
        return $this->normalizeStored($data);
    }

    /**
     * Ensure validation receives a complete pair shape.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function preProcessValidatable(mixed $value): array
    {
        $base = $this->emptyPair();

        if (! \is_array($value)) {
            return $base;
        }

        /** @var array<string, mixed> $merged */
        $merged = \array_replace_recursive(
            $base,
            $value
        );

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeStored(mixed $data): array
    {
        if (! \is_array($data)) {
            return $this->emptyPair();
        }

        /** @var array<string, mixed> $pair */
        $pair = $data;

        return $this->normalizePair($pair);
    }

    /**
     * @param  array<string, mixed>  $pair
     * @return array{text: string, phonetic_text: string}
     */
    private function normalizePair(array $pair): array
    {
        $text = isset($pair['text']) && \is_string($pair['text']) ? $pair['text'] : '';
        $pho = isset($pair['phonetic_text']) && \is_string($pair['phonetic_text'])
            ? $pair['phonetic_text']
            : '';

        unset($pair);

        return [
            'text' => $text,
            'phonetic_text' => $pho,
        ];
    }
}
