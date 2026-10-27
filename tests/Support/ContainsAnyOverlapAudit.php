<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Fieldtypes\SchemaType;
use App\Services\CredentialRegistry\CtdlClassHierarchyBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Statamic\Fields\Field;
use Statamic\Fields\Fieldtype;
use Symfony\Component\Yaml\Yaml;

/**
 * Finds `contains_any` field conditions that over-match because a candidate is part of an option
 * the rule does not list (UAMS-Web/uams-statamic-archive#2891).
 *
 * Against a single string, `contains_any` is a substring test in Statamic's Control Panel
 * validator and in the server-side evaluator, so `contains_any date` also matches
 * `datetime-local`. `includes_any` (statamic/cms 6.35.0) is the membership test such a rule
 * means. This reads every rule, resolves the field it targets, and compares each candidate with
 * that field's full option list rather than with the values the rules happen to name.
 *
 * A rule is classified by what its target can hold:
 *  - `option-list`: a single-value `select`, `radio` or `button_group` with static options, or a
 *    single-string path into a `schema_type` or `ctdl_class` value, whose options are read from
 *    the fieldtype. These can over-match, and are checked for overlaps;
 *  - `fixed-value`: a `hidden` field, compared with its one default value;
 *  - `list-valued`: `checkboxes`, a `select` with `multiple`, or a `schema_type`'s `selections`,
 *    where `contains_any` is already membership;
 *  - `no-static-options`: a target whose options are not in its config, such as an `entries`
 *    field or a path into a value this audit does not model;
 *  - `unresolved`: no field with that handle is in the rule's scope.
 *
 * @phpstan-type Finding array{source: string, field: string, key: string, target: string, candidates: list<string>, type: ?string, status: string, options: list<string>, overlaps: list<string>}
 * @phpstan-type FieldMap array<string, array<string, mixed>>
 */
final class ContainsAnyOverlapAudit
{
    /**
     * Statamic's condition keys. Each takes a map of field path to rule.
     *
     * @var list<string>
     */
    private const array CONDITION_KEYS = ['if', 'if_any', 'show_when', 'show_when_any', 'unless', 'unless_any', 'hide_when', 'hide_when_any'];

    /**
     * Fieldtypes whose stored value is one string chosen from a static option list.
     *
     * @var list<string>
     */
    private const array SINGLE_VALUE_TYPES = ['select', 'radio', 'button_group'];

    /**
     * Raw fieldset contents keyed by handle, for imports and `fieldset.field` references.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $fieldsets = [];

    /**
     * @var list<Finding>
     */
    private array $findings = [];

    /**
     * @param  string  $fieldsetDirectory  Directory holding the fieldset YAML that imports and references resolve against.
     */
    public function __construct(string $fieldsetDirectory)
    {
        foreach ($this->yamlFiles($fieldsetDirectory) as $relative => $path) {
            $handle = str_replace('/', '.', substr($relative, 0, -5));
            $this->fieldsets[$handle] = self::mapOf(Yaml::parseFile($path));
        }
    }

    /**
     * Audit every blueprint and fieldset YAML file under the given directories.
     *
     * @param  array<string, string>  $directories  Label (used as the source prefix) => directory.
     */
    public function auditYamlDirectories(array $directories): self
    {
        foreach ($directories as $label => $directory) {
            foreach ($this->yamlFiles($directory) as $relative => $path) {
                $this->auditContents(self::mapOf(Yaml::parseFile($path)), $label.'/'.$relative);
            }
        }

        return $this;
    }

    /**
     * Audit one blueprint's or fieldset's parsed contents.
     *
     * @param  array<string, mixed>  $contents
     */
    public function auditContents(array $contents, string $source): self
    {
        $items = [];

        foreach (self::arrayOf($contents['tabs'] ?? null) as $tab) {
            foreach (self::arrayOf(self::mapOf($tab)['sections'] ?? null) as $section) {
                $items = [...$items, ...array_values(self::arrayOf(self::mapOf($section)['fields'] ?? null))];
            }
        }

        foreach (self::arrayOf($contents['sections'] ?? null) as $section) {
            $items = [...$items, ...array_values(self::arrayOf(self::mapOf($section)['fields'] ?? null))];
        }

        $items = [...$items, ...array_values(self::arrayOf($contents['fields'] ?? null))];

        $this->auditScope($items, $source, '', []);

        return $this;
    }

    /**
     * Audit a list of field items as one sibling scope, such as a fieldtype's config fields.
     *
     * @param  array<int|string, mixed>  $items  `handle`/`field` items, or a map of handle to config.
     */
    public function auditItems(array $items, string $source): self
    {
        $this->auditScope($items, $source, '', []);

        return $this;
    }

    /**
     * Audit the config fields of each concrete fieldtype class in a directory.
     *
     * @param  string  $directory  Directory of fieldtype classes.
     * @param  string  $namespace  Namespace those classes live in.
     */
    public function auditFieldtypeClasses(string $directory, string $namespace): self
    {
        foreach (glob(rtrim($directory, '/').'/*.php') ?: [] as $path) {
            $class = $namespace.'\\'.basename($path, '.php');

            if (! class_exists($class) || ! is_subclass_of($class, Fieldtype::class)) {
                continue;
            }

            if ((new \ReflectionClass($class))->isAbstract()) {
                continue;
            }

            $fieldtype = app($class);

            if (! $fieldtype instanceof Fieldtype) {
                continue;
            }

            $items = $fieldtype->configFields()->items();

            $this->auditItems(self::arrayOf($items instanceof Collection ? $items->all() : $items), $class);
        }

        return $this;
    }

    /**
     * @return list<Finding>
     */
    public function findings(): array
    {
        return $this->findings;
    }

    /**
     * The findings whose candidates are part of an option the rule does not list.
     *
     * @return list<Finding>
     */
    public function overlaps(): array
    {
        return array_values(array_filter($this->findings, static fn (array $finding): bool => $finding['overlaps'] !== []));
    }

    /**
     * Each `candidate -> option` pair where the candidate is part of an option the rule does not list.
     *
     * @param  list<string>  $candidates
     * @param  list<string>  $options
     * @return list<string>
     */
    public static function overlapsFor(array $candidates, array $options): array
    {
        $overlaps = [];

        foreach ($candidates as $candidate) {
            foreach ($options as $option) {
                if ($option !== $candidate && ! in_array($option, $candidates, true) && str_contains($option, $candidate)) {
                    $overlaps[] = $candidate.' -> '.$option;
                }
            }
        }

        return $overlaps;
    }

    /**
     * Audit one sibling scope, then every group and set nested in it.
     *
     * @param  array<int|string, mixed>  $items
     * @param  list<FieldMap>  $ancestors  Enclosing scopes, outermost (the root) first.
     */
    private function auditScope(array $items, string $source, string $pathPrefix, array $ancestors): void
    {
        $owned = [];
        $chain = [...$ancestors, $this->normalize($items, $owned)];

        foreach ($chain[array_key_last($chain)] as $handle => $config) {
            // Only fields written in this source are audited here; imported ones are audited in
            // their own fieldset, so each rule is counted once.
            if (! isset($owned[$handle])) {
                continue;
            }

            $path = $pathPrefix.$handle;
            $this->auditConditions($config, $source, $path, $chain);

            if (is_array($config['fields'] ?? null)) {
                $this->auditScope($config['fields'], $source, $path.'.', $chain);
            }

            foreach ($this->sets($config) as $setHandle => $setFields) {
                $this->auditScope($setFields, $source, $path.'.'.$setHandle.'.', $chain);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  non-empty-list<FieldMap>  $chain  Scopes from the root to the field's own.
     */
    private function auditConditions(array $config, string $source, string $path, array $chain): void
    {
        foreach (self::CONDITION_KEYS as $key) {
            foreach (self::arrayOf($config[$key] ?? null) as $target => $rules) {
                if (! is_string($target)) {
                    continue;
                }

                foreach (is_array($rules) ? $rules : [$rules] as $rule) {
                    if (! is_string($rule) || preg_match('/^\s*contains_any\s+(.+)$/s', $rule, $match) !== 1) {
                        continue;
                    }

                    $candidates = array_values(array_filter(
                        array_map(trim(...), explode(',', $match[1])),
                        static fn (string $candidate): bool => $candidate !== ''
                    ));

                    $this->findings[] = $this->assess($source, $path, $key, $target, $candidates, $chain);
                }
            }
        }
    }

    /**
     * @param  list<string>  $candidates
     * @param  non-empty-list<FieldMap>  $chain
     * @return Finding
     */
    private function assess(string $source, string $path, string $key, string $target, array $candidates, array $chain): array
    {
        $finding = [
            'source' => $source,
            'field' => $path,
            'key' => $key,
            'target' => $target,
            'candidates' => $candidates,
            'type' => null,
            'status' => 'unresolved',
            'options' => [],
            'overlaps' => [],
        ];

        $resolved = $this->resolve($target, $chain);

        if ($resolved === null) {
            return $finding;
        }

        [$config, $rest] = $resolved;
        $type = is_string($config['type'] ?? null) ? $config['type'] : null;
        $finding['type'] = $type;

        if ($rest !== '' || in_array($type, ['schema_type', 'ctdl_class'], true)) {
            [$finding['status'], $finding['options']] = $this->valuePathOptions($config, $rest);
            $finding['overlaps'] = $finding['status'] === 'option-list' ? self::overlapsFor($candidates, $finding['options']) : [];

            return $finding;
        }

        $default = self::stringOf($config['default'] ?? null);

        if ($type === 'hidden' && $default !== null) {
            $finding['status'] = 'fixed-value';
            $finding['options'] = [$default];
            $finding['overlaps'] = self::overlapsFor($candidates, $finding['options']);

            return $finding;
        }

        if ($type === 'checkboxes' || (in_array($type, ['select', 'combobox'], true) && ! empty($config['multiple']))) {
            $finding['status'] = 'list-valued';

            return $finding;
        }

        $options = $this->options($config['options'] ?? null);

        if (! in_array($type, self::SINGLE_VALUE_TYPES, true) || $options === []) {
            $finding['status'] = 'no-static-options';

            return $finding;
        }

        $finding['status'] = 'option-list';
        $finding['options'] = $options;
        $finding['overlaps'] = self::overlapsFor($candidates, $options);

        return $finding;
    }

    /**
     * The options for a path into a fieldtype's stored value, read from the fieldtype itself.
     *
     * @param  array<string, mixed>  $config
     * @return array{0: string, 1: list<string>} Status, and the options when the status is `option-list`.
     */
    private function valuePathOptions(array $config, string $rest): array
    {
        $type = $config['type'] ?? null;

        if ($type === 'schema_type') {
            if ($rest === 'selections') {
                return ['list-valued', []];
            }

            $fieldtype = app(SchemaType::class);
            $fieldtype->setField(new Field('schema_type', $config));
            $preload = $fieldtype->preload();

            // `value` in chained mode, or the whole value in single mode, is one type name.
            return match ($rest) {
                'value' => ['option-list', self::stringsOf($preload['subtree_type_names'])],
                '' => ['option-list', self::stringsOf($preload['single_select_values'])],
                default => ['no-static-options', []],
            };
        }

        // `ceterms_type` holds the root class; each `ceterms_<parent>_type` holds one child of
        // `<parent>`, named in snake case.
        if ($type === 'ctdl_class' && $rest === 'ceterms_type') {
            return ['option-list', self::stringsOf($config['ceterms_type_filter'] ?? null)];
        }

        if ($type === 'ctdl_class' && preg_match('/^ceterms_(\w+)_type$/', $rest, $match) === 1) {
            $children = CtdlClassHierarchyBuilder::preload()['children_by_shorthand']['ceterms:'.Str::studly($match[1])] ?? [];

            return ['option-list', $this->options($children)];
        }

        return ['no-static-options', []];
    }

    /**
     * Resolve a condition's field path to the target field's config, and the rest of the path
     * when it continues into that field's value rather than into a group's subfields.
     *
     * @param  non-empty-list<FieldMap>  $chain
     * @return ?array{0: array<string, mixed>, 1: string}
     */
    private function resolve(string $target, array $chain): ?array
    {
        if (preg_match('/^\$?root\./', $target) === 1) {
            $chain = [$chain[0]];
            $target = (string) preg_replace('/^\$?root\./', '', $target);
        }

        while (preg_match('/^\$?parent\./', $target) === 1) {
            if (count($chain) < 2) {
                return null;
            }

            array_pop($chain);
            $target = (string) preg_replace('/^\$?parent\./', '', $target);
        }

        $segments = explode('.', $target);
        $config = $chain[array_key_last($chain)][array_shift($segments)] ?? null;

        while ($segments !== [] && $config !== null && is_array($config['fields'] ?? null)) {
            $ignored = [];
            $config = $this->normalize($config['fields'], $ignored)[array_shift($segments)] ?? null;
        }

        return $config === null ? null : [$config, implode('.', $segments)];
    }

    /**
     * Normalize field items to a map of handle to config, expanding imports and references.
     *
     * @param  array<int|string, mixed>  $items
     * @param  array<string, true>  $owned  Filled with the handles written inline in these items.
     * @return FieldMap
     */
    private function normalize(array $items, array &$owned): array
    {
        $fields = [];

        foreach ($items as $key => $raw) {
            $item = self::mapOf($raw);
            $import = self::stringOf($item['import'] ?? null);

            if ($import !== null) {
                $prefix = self::stringOf($item['prefix'] ?? null) ?? '';
                $ignored = [];

                foreach ($this->normalize(self::arrayOf($this->fieldsets[$import]['fields'] ?? null), $ignored) as $handle => $config) {
                    $fields[$prefix.$handle] = $config;
                }

                continue;
            }

            $handle = self::stringOf($item['handle'] ?? null);

            if ($handle !== null && isset($item['field'])) {
                if (is_array($item['field'])) {
                    $fields[$handle] = self::mapOf($item['field']);
                    $owned[$handle] = true;

                    continue;
                }

                $fields[$handle] = array_merge(
                    $this->referenced(self::stringOf($item['field']) ?? ''),
                    self::mapOf($item['config'] ?? null)
                );

                // A referenced field's conditions are evaluated against THIS scope's siblings,
                // whose options the consumer may have overridden, so they are audited here as
                // well as in the fieldset that defines them.
                if (array_intersect(self::CONDITION_KEYS, array_keys($fields[$handle])) !== []) {
                    $owned[$handle] = true;
                }

                continue;
            }

            if (is_string($key) && isset($item['type'])) {
                $fields[$key] = $item;
                $owned[$key] = true;
            }
        }

        return $fields;
    }

    /**
     * The config of a `fieldset.field` reference, or an empty config when it does not resolve.
     *
     * @return array<string, mixed>
     */
    private function referenced(string $reference): array
    {
        $dot = strrpos($reference, '.');

        if ($dot === false) {
            return [];
        }

        $ignored = [];
        $fieldset = $this->fieldsets[substr($reference, 0, $dot)] ?? [];

        return $this->normalize(self::arrayOf($fieldset['fields'] ?? null), $ignored)[substr($reference, $dot + 1)] ?? [];
    }

    /**
     * A replicator's or Bard's sets, flattened across set groups.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, array<int|string, mixed>>
     */
    private function sets(array $config): array
    {
        $sets = [];

        foreach (self::arrayOf($config['sets'] ?? null) as $handle => $raw) {
            $set = self::mapOf($raw);

            if (is_array($set['sets'] ?? null)) {
                foreach ($set['sets'] as $innerHandle => $inner) {
                    $innerFields = self::mapOf($inner)['fields'] ?? null;

                    if (is_array($innerFields)) {
                        $sets[$handle.'.'.$innerHandle] = $innerFields;
                    }
                }

                continue;
            }

            if (is_array($set['fields'] ?? null)) {
                $sets[(string) $handle] = $set['fields'];
            }
        }

        return $sets;
    }

    /**
     * Option keys, from a list of strings, a list of `key`/`value` pairs, or a key-to-label map.
     *
     * @return list<string>
     */
    private function options(mixed $options): array
    {
        $options = self::arrayOf($options);
        $keys = [];

        foreach ($options as $key => $option) {
            if (is_array($option)) {
                $optionKey = self::stringOf($option['key'] ?? null);

                if ($optionKey !== null) {
                    $keys[] = $optionKey;
                }

                continue;
            }

            $value = array_is_list($options) ? self::stringOf($option) : (string) $key;

            if ($value !== null) {
                $keys[] = $value;
            }
        }

        return $keys;
    }

    /**
     * @return array<string, string> Path relative to the directory => absolute path.
     */
    private function yamlFiles(string $directory): array
    {
        $files = [];
        $directory = rtrim($directory, '/');

        if (! is_dir($directory)) {
            return $files;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->getExtension() === 'yaml') {
                // Keyed with forward slashes on every platform, so a nested file's source
                // reads the same on Windows as on macOS.
                $files[str_replace('\\', '/', substr($file->getPathname(), strlen($directory) + 1))] = $file->getPathname();
            }
        }

        ksort($files);

        return $files;
    }

    /**
     * @return array<int|string, mixed>
     */
    private static function arrayOf(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * The string-keyed entries of an array, which is every entry of a parsed YAML map.
     *
     * @return array<string, mixed>
     */
    private static function mapOf(mixed $value): array
    {
        $map = [];

        foreach (self::arrayOf($value) as $key => $item) {
            if (is_string($key)) {
                $map[$key] = $item;
            }
        }

        return $map;
    }

    private static function stringOf(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @return list<string>
     */
    private static function stringsOf(mixed $value): array
    {
        return array_values(array_filter(
            array_map(self::stringOf(...), self::arrayOf($value)),
            static fn (?string $item): bool => $item !== null
        ));
    }
}
