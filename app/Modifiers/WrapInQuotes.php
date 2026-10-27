<?php

declare(strict_types=1);

namespace App\Modifiers;

use Statamic\Fields\Value;
use Statamic\Modifiers\Modifier;

/**
 * Antlers modifier `{{ quote_text | wrap_in_quotes }}`: the value wrapped in curly
 * double quotes, “ and ”, for the side-by-side block's Blockquote with Citation
 * layout (UAMS-Web/uams-statamic-archive#2757).
 *
 * Editors do not type the quotation marks, but some will. So any straight or curly,
 * single or double marks at the start and end of the value are removed before it is
 * wrapped. Marks inside the text are kept, so an apostrophe ("didn't") or a quote
 * within the quote survives.
 */
class WrapInQuotes extends Modifier
{
    /**
     * The marks removed from the ends of the value: " ' “ ” ‘ ’.
     */
    private const string END_MARKS = '"\'“”‘’';

    /**
     * Return the value without its end quotation marks, wrapped in “ and ”.
     *
     * @param  mixed  $value  The quote text.
     * @param  array<int, string>  $params  Unused.
     * @param  array<string, mixed>  $context  Unused.
     */
    public function index($value, array $params, array $context): string
    {
        if ($value instanceof Value) {
            $value = $value->value();
        }

        // Cast scalars only: the end-strip regex below already eats surrounding
        // whitespace, so a local trim here would be observationally identical.
        $text = is_scalar($value) ? (string) $value : '';
        $marks = preg_quote(self::END_MARKS, '/');
        $stripped = preg_replace(
            "/^[{$marks}\\s]+|[{$marks}\\s]+$/u",
            '',
            $text
        );
        // preg_replace returns string|null; the '' arm is only for the null
        // error path, which this pattern does not hit under test.
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        $text = is_string($stripped) ? $stripped : '';

        if ($text === '') {
            return '';
        }

        return "“{$text}”";
    }
}
