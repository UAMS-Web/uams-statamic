<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\EnumGeneration\EnumDataUtils;
use Attribute;

/**
 * AP Stylebook month labels for `ApStyleGregorianMonth` enum cases.
 *
 * Holds running-text (`prose`) and tabular (`tabular`) forms per case.
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class ApStyleMonthInfo
{
    /**
     * @param  string  $prose  Running-text AP label (abbreviations use periods
     *                         where the Stylebook requires them)
     * @param  string  $tabular  Three-letter tabular form without periods
     */
    public function __construct(
        public string $prose,
        public string $tabular,
    ) {}
}

/**
 * Gregorian calendar months with AP Stylebook labels.
 *
 * Backing values are full English names (`January` … `December`) aligned with
 * Carbon `format('F')` in the default locale. Prose labels follow running-text
 * AP month rules; tabular labels follow the three-letter table (including
 * `Sep`, not `Sept.`). Use `runningTextLabel()` when prose must spell out the
 * month unless the formatted phrase includes a specific calendar day.
 */
enum ApStyleGregorianMonth: string
{
    /** January. */
    #[ApStyleMonthInfo(prose: 'Jan.', tabular: 'Jan')]
    case January = 'January';

    /** February. */
    #[ApStyleMonthInfo(prose: 'Feb.', tabular: 'Feb')]
    case February = 'February';

    /** March. */
    #[ApStyleMonthInfo(prose: 'March', tabular: 'Mar')]
    case March = 'March';

    /** April. */
    #[ApStyleMonthInfo(prose: 'April', tabular: 'Apr')]
    case April = 'April';

    /** May. */
    #[ApStyleMonthInfo(prose: 'May', tabular: 'May')]
    case May = 'May';

    /** June. */
    #[ApStyleMonthInfo(prose: 'June', tabular: 'Jun')]
    case June = 'June';

    /** July. */
    #[ApStyleMonthInfo(prose: 'July', tabular: 'Jul')]
    case July = 'July';

    /** August. */
    #[ApStyleMonthInfo(prose: 'Aug.', tabular: 'Aug')]
    case August = 'August';

    /** September. */
    #[ApStyleMonthInfo(prose: 'Sept.', tabular: 'Sep')]
    case September = 'September';

    /** October. */
    #[ApStyleMonthInfo(prose: 'Oct.', tabular: 'Oct')]
    case October = 'October';

    /** November. */
    #[ApStyleMonthInfo(prose: 'Nov.', tabular: 'Nov')]
    case November = 'November';

    /** December. */
    #[ApStyleMonthInfo(prose: 'Dec.', tabular: 'Dec')]
    case December = 'December';

    /**
     * Resolve the enum case for a Gregorian month number.
     *
     * @param  int  $month  Month index (1 = January through 12 = December)
     * @return self|null Case when in range, otherwise null
     */
    public static function fromMonthNumber(int $month): ?self
    {
        return match ($month) {
            1 => self::January,
            2 => self::February,
            3 => self::March,
            4 => self::April,
            5 => self::May,
            6 => self::June,
            7 => self::July,
            8 => self::August,
            9 => self::September,
            10 => self::October,
            11 => self::November,
            12 => self::December,
            default => null,
        };
    }

    /**
     * Lazily builds the cached prose/tabular map from case attributes.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function monthLabelData(): array
    {
        // Build row shape expected by EnumDataUtils getters
        $dataExtractor = fn (
            ApStyleMonthInfo $info,
            string $caseValue
        ): array => [
            'prose' => $info->prose,
            'tabular' => $info->tabular,
        ];

        // Reflect attributes once and cache keyed by backing month name
        $result = EnumDataUtils::buildLazyDataMap(
            self::class,
            ApStyleMonthInfo::class,
            $dataExtractor
        );

        unset($dataExtractor);

        return $result;
    }

    /**
     * Running-text AP month label for this case.
     *
     * @return string Prose label (e.g. `Jan.`, `March`, `Sept.`)
     */
    public function proseLabel(): string
    {
        // Read prose column from the cached attribute map
        $data = self::monthLabelData();

        return EnumDataUtils::getFieldValue(
            $data,
            $this->value,
            'prose',
            'AP Style Gregorian month'
        );
    }

    /**
     * Tabular-material AP month label for this case (no periods).
     *
     * @return string Three-letter tabular label (e.g. `Jan`, `Sep`)
     */
    public function tabularLabel(): string
    {
        // Read tabular column from the cached attribute map
        $data = self::monthLabelData();

        return EnumDataUtils::getFieldValue(
            $data,
            $this->value,
            'tabular',
            'AP Style Gregorian month'
        );
    }

    /**
     * Fully spelled English month name (running text when no day appears).
     *
     * @return string Full name matching the backing value (`January`, etc.)
     */
    public function spelledOutName(): string
    {
        // Reuse the backed full month name for AP month-alone and month-year
        return $this->value;
    }

    /**
     * Running-text label: abbreviated when a day appears, spelled out otherwise.
     *
     * @param  bool  $includesSpecificDay  True when output includes a calendar day number (AP abbreviates Jan., Feb.,
     *                                     Aug., Sept., Oct., Nov., Dec. only in that case)
     * @return string Prose label for the requested precision
     */
    public function runningTextLabel(bool $includesSpecificDay): string
    {
        // Prefer abbreviated prose only when the phrase includes a specific day
        return $includesSpecificDay
            ? $this->proseLabel()
            : $this->spelledOutName();
    }

    /**
     * Pick prose or tabular label based on material type.
     *
     * @param  bool  $tabularMaterial  True for tabular three-letter forms
     * @return string AP month string for the requested material type
     */
    public function labelForTabularMaterial(bool $tabularMaterial): string
    {
        // Route to tabular vs abbreviated prose (day-present convention)
        return $tabularMaterial
            ? $this->tabularLabel()
            : $this->proseLabel();
    }
}
