<?php

declare(strict_types=1);

namespace App\Providers;

use App\Rules\After;
use App\Rules\AfterOrEqual;
use App\Rules\Ahaid;
use App\Rules\AsciiIdentifier;
use App\Rules\AssetValidationSatisfied;
use App\Rules\AtomFeedUrl;
use App\Rules\Before;
use App\Rules\BeforeOrEqual;
use App\Rules\BoardCertificationTerm;
use App\Rules\BoxSharedStaticAudioUrl;
use App\Rules\BoxSharedStaticUrl;
use App\Rules\BoxSharedStaticVideoUrl;
use App\Rules\CageCode;
use App\Rules\CmsSpecialtyCode;
use App\Rules\CredentialTypeCertification;
use App\Rules\CssSelectorSyntax;
use App\Rules\DateEquals;
use App\Rules\DepInDeclaredList;
use App\Rules\DunsNumber;
use App\Rules\EntrySchemaTypeIn;
use App\Rules\EpicDepId;
use App\Rules\FeedUrl;
use App\Rules\FirstPartyUrl;
use App\Rules\GlobalLocationNumber;
use App\Rules\GoogleCid;
use App\Rules\GoogleScholarAuthorId;
use App\Rules\GoogleTagManagerContainerId;
use App\Rules\Gs1Modulo10;
use App\Rules\HealthCareProviderTaxonomyCode;
use App\Rules\IdentifierRegex;
use App\Rules\InternationalPhone;
use App\Rules\InternationalPhoneticAlphabet;
use App\Rules\IpedsId;
use App\Rules\Isco08Code;
use App\Rules\IsicRev4Code;
use App\Rules\Iso6523Code;
use App\Rules\Latitude;
use App\Rules\LatLng;
use App\Rules\LeadershipDevelopmentActivitiesTerm;
use App\Rules\LegalEntityIdentifier;
use App\Rules\Longitude;
use App\Rules\Luhn;
use App\Rules\NaicsCode;
use App\Rules\Name;
use App\Rules\NationalPhone;
use App\Rules\NotTopLevelOrganization;
use App\Rules\Npi;
use App\Rules\OnetSocOccupationCode;
use App\Rules\OpeId;
use App\Rules\Orcid;
use App\Rules\OtherCertificationTerm;
use App\Rules\Pmid;
use App\Rules\ProfessionalDevelopmentActivitiesTerm;
use App\Rules\PubmedAuthorId;
use App\Rules\ResearcherId;
use App\Rules\RssFeedUrl;
use App\Rules\RxCui;
use App\Rules\ScopusAuthorId;
use App\Rules\SmsShortCode;
use App\Rules\SpecialtyTaxonomyTermType;
use App\Rules\SubfieldsAll;
use App\Rules\SubfieldsAny;
use App\Rules\SubfieldsList;
use App\Rules\TaxpayerIdentificationNumber;
use App\Rules\TermsSubsetOf;
use App\Rules\TickerSymbol;
use App\Rules\TriProfileUrl;
use App\Rules\UamsVideoUrl;
use App\Rules\Uei;
use App\Rules\UniqueIdentifier;
use App\Rules\UniqueIdentifierList;
use App\Rules\VaStationId;
use App\Rules\VaStationSubstationId;
use App\Rules\VaSubstationId;
use App\Rules\VimeoUrl;
use App\Rules\XpathSyntax;
use App\Rules\YouTubeUrl;
use App\Rules\ZipCode;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;

/**
 * Service provider for custom validation rules with enhanced display name support.
 *
 * This provider extends Laravel's validation system with custom rules that
 * provide enhanced functionality, type-specific messages, and custom display
 * names for the Statamic Control Panel interface.
 *
 * ## Custom Display Names Feature
 *
 * This service provider includes a custom display names system that allows you to
 * define how validation rules appear in the Statamic Control Panel's "Rules"
 * field. Instead of using Laravel's default `Str::title()` conversion (which
 * produces "Youtube Url" from `youtube_url`), you can specify custom display
 * names like "YouTube URL" for proper capitalization.
 *
 * ## Rule Interface Support
 *
 * This service provider can register rules that implement either:
 *   - Laravel's `Rule` interface (Illuminate\Contracts\Validation\Rule)
 *   - Laravel's `ValidationRule` interface
 *     (Illuminate\Contracts\Validation\ValidationRule)
 *
 * Both interfaces can be registered for simple string syntax using a bridge
 * pattern.
 *
 * The difference:
 *   - `Rule` interface: Direct registration with passes()/message() methods
 *   - `ValidationRule` interface: Bridge pattern converts to Rule interface
 *     automatically
 *
 * ## Adding New Rules
 *
 * To add a new rule for simple string syntax:
 *   1. Ensure it implements either `Rule` or `ValidationRule` interface
 *   2. Add it to the `boot()` method using `registerCustomRule()`
 *   3. Add appropriate validation messages to the language file (for Rule
 *      interface)
 *   4. For ValidationRule interface, messages are provided via the `$fail`
 *      callback in the `validate()` method. Messages can be either inline
 *      strings or retrieved from translation files using `trans()`
 *      (recommended for maintainability).
 *        - When using translation files:
 *            - Add the message to the appropriate validation translation file
 *              (e.g., `lang/vendor/statamic/en/validation.php`) with the array
 *              key `{rule_name}` (e.g., `zip_code`).
 *            - Reference it using `trans('statamic::validation.{rule_name}')` in
 *              the rule class
 *
 * ## Custom Display Names Usage
 *
 * To define a custom display name for a validation rule:
 *   ```php
 *   $this->registerCustomRule('youtube_url', \App\Rules\YouTubeUrl::class, 'YouTube URL');
 *   $this->registerCustomRule('uams_video_url', \App\Rules\UamsVideoUrl::class, 'UAMS Video URL');
 *   ```
 *
 * This will display as "YouTube URL `youtube_url`" and
 * "UAMS Video URL `uams_video_url`" in the Statamic Control Panel Rules field
 * interface.
 *
 * ## Integration with Statamic Control Panel
 *
 * The custom display names are automatically integrated with the Statamic Control
 * Panel through the `CustomFieldComposer` and
 * `CustomFieldComposerServiceProvider`. The display names are stored in both
 * instance and static properties to avoid
 * circular dependency issues when accessed from view composers.
 */
class ValidationServiceProvider extends ServiceProvider
{
    /**
     * Custom display names for validation rules (instance storage)
     *
     * This array stores custom display names for validation rules that will be
     * used in the Statamic Control Panel interface. The keys are the rule names
     * (e.g., 'youtube_url') and the values are the display names
     * (e.g., 'YouTube URL').
     *
     * @var array<string, string>
     */
    protected array $customDisplayNames = [];

    /**
     * Static storage for custom display names to avoid circular dependencies
     *
     * This static property provides access to custom display names from other
     * classes (like CustomFieldComposer) without requiring instantiation of the
     * ValidationServiceProvider, which would cause circular dependency issues.
     *
     * @var array<string, string>
     */
    public static array $staticCustomDisplayNames = [];

    /**
     * Register services.
     */
    #[\Override]
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // ---
        // Date/time validation rules
        // ---

        $this->registerCustomRule(
            'after',
            After::class
        );
        $this->registerCustomRule(
            'after_or_equal',
            AfterOrEqual::class
        );
        $this->registerCustomRule(
            'before',
            Before::class
        );
        $this->registerCustomRule(
            'before_or_equal',
            BeforeOrEqual::class
        );
        $this->registerCustomRule(
            'date_equals',
            DateEquals::class
        );

        // ---
        // Phone validation rules
        // ---

        $this->registerCustomRule(
            'international_phone',
            InternationalPhone::class
        );
        $this->registerCustomRule(
            'national_phone',
            NationalPhone::class
        );

        // ---
        // Identifier and code validation rules
        // ---

        $this->registerCustomRule(
            'ahaid',
            Ahaid::class,
            'American Hospital Association (AHA) ID'
        );
        $this->registerCustomRule(
            'cage_code',
            CageCode::class,
            'Commercial and Government Entity (CAGE) Code'
        );
        $this->registerCustomRule(
            'cms_specialty_code',
            CmsSpecialtyCode::class,
            'Centers for Medicare & Medicaid Services (CMS) Specialty Code'
        );
        $this->registerCustomRule(
            'duns_number',
            DunsNumber::class,
            'Dun & Bradstreet Data Universal Numbering System (D-U-N-S) Number'
        );
        $this->registerCustomRule(
            'epic_dep_id',
            EpicDepId::class,
            'UAMS Health Epic Department (DEP) ID'
        );
        $this->registerCustomRule(
            'global_location_number',
            GlobalLocationNumber::class,
            'Global Location Number (GLN)'
        );
        $this->registerCustomRule(
            'google_business_profile_cid',
            GoogleCid::class,
            'Google CID'
        );
        $this->registerCustomRule(
            'google_scholar_author_id',
            GoogleScholarAuthorId::class,
            'Google Scholar Author ID'
        );
        $this->registerCustomRule(
            'google_tag_manager_container_id',
            GoogleTagManagerContainerId::class,
            'Google Tag Manager Container ID'
        );
        $this->registerCustomRule(
            'gs1_modulo_10',
            Gs1Modulo10::class,
            'GS1 Modulo 10'
        );
        $this->registerCustomRule(
            'health_care_provider_taxonomy_code',
            HealthCareProviderTaxonomyCode::class
        );
        $this->registerCustomRule(
            'isco_08_code',
            Isco08Code::class,
            'ISCO-08 Code'
        );
        $this->registerCustomRule(
            'ipeds_id',
            IpedsId::class,
            'Integrated Postsecondary Education Data System (IPEDS) ID'
        );
        $this->registerCustomRule(
            'isic_rev4_code',
            IsicRev4Code::class,
            'International Standard of Industrial Classification of All Economic Activities (ISIC), Revision 4 Code'
        );
        $this->registerCustomRule(
            'iso_6523_code',
            Iso6523Code::class,
            'ISO 6523 Code'
        );
        $this->registerCustomRule(
            'legal_entity_identifier',
            LegalEntityIdentifier::class,
            'Legal Entity Identifier (LEI)'
        );
        $this->registerCustomRule(
            'luhn',
            Luhn::class,
            'Luhn algorithm'
        );
        $this->registerCustomRule(
            'naics_code',
            NaicsCode::class,
            'North American Industry Classification System (NAICS) Code'
        );
        $this->registerCustomRule(
            'npi',
            Npi::class,
            'National Provider Identifier (NPI)'
        );
        $this->registerCustomRule(
            'onet_soc_occupation_code',
            OnetSocOccupationCode::class,
            'O*Net-SOC Occupation Code'
        );
        $this->registerCustomRule(
            'ope_id',
            OpeId::class,
            'Office of Postsecondary Education (OPE) ID'
        );
        $this->registerCustomRule(
            'orcid',
            Orcid::class,
            'Open Researcher and Contributor ID (ORCID)'
        );
        $this->registerCustomRule(
            'pmid',
            Pmid::class,
            'PubMed Identifier (PMID)'
        );
        $this->registerCustomRule(
            'pubmed_author_id',
            PubmedAuthorId::class,
            'PubMed Author Identifier (PMID)'
        );
        $this->registerCustomRule(
            'researcher_id',
            ResearcherId::class,
            'Web of Science ResearcherID'
        );
        $this->registerCustomRule(
            'rxcui',
            RxCui::class,
            'National Library of Medicine RxNorm Concept Unique Identifier (RxCUI)'
        );
        $this->registerCustomRule(
            'scopus_author_id',
            ScopusAuthorId::class,
            'Scopus Author ID'
        );
        $this->registerCustomRule(
            'sms_short_code',
            SmsShortCode::class,
            'SMS Short Code'
        );
        $this->registerCustomRule(
            'taxpayer_identification_number',
            TaxpayerIdentificationNumber::class,
            'U.S. Taxpayer Identification Numbers (TIN) / Employer Identification Numbers (EIN)'
        );
        $this->registerCustomRule(
            'ticker_symbol',
            TickerSymbol::class,
            'Stock Ticker Symbol'
        );
        $this->registerCustomRule(
            'uei',
            Uei::class,
            'Unique Entity ID (Federal/SAM.gov)'
        );
        $this->registerCustomRule(
            'va_station_id',
            VaStationId::class,
            'United States Department of Veterans Affairs (VA) Station Identifier (STA3N)'
        );
        $this->registerCustomRule(
            'va_station_substation_id',
            VaStationSubstationId::class,
            'United States Department of Veterans Affairs (VA) Station Identifier (STA3N) or Substation Identifier (STA6A)'
        );
        $this->registerCustomRule(
            'va_substation_id',
            VaSubstationId::class,
            'United States Department of Veterans Affairs (VA) Substation Identifier (STA6A)'
        );

        // ---
        // Geographic validation rules
        // ---

        $this->registerCustomRule(
            'latitude',
            Latitude::class
        );
        $this->registerCustomRule(
            'lat_lng',
            LatLng::class,
            'Latitude and Longitude'
        );
        $this->registerCustomRule(
            'longitude',
            Longitude::class
        );

        // ---
        // Name and text validation rules
        // ---

        $this->registerCustomRule(
            'name',
            Name::class
        );

        // ---
        // URL validation rules
        // ---

        $this->registerCustomRule(
            'atom_feed_url',
            AtomFeedUrl::class,
            'Atom Feed URL'
        );
        $this->registerCustomRule(
            'box_shared_static_audio_url',
            BoxSharedStaticAudioUrl::class,
            'Box.com shared static audio URL'
        );
        $this->registerCustomRule(
            'box_shared_static_url',
            BoxSharedStaticUrl::class,
            'Box.com shared static URL'
        );
        $this->registerCustomRule(
            'box_shared_static_video_url',
            BoxSharedStaticVideoUrl::class,
            'Box.com shared static video URL'
        );
        $this->registerCustomRule(
            'feed_url',
            FeedUrl::class,
            'Feed URL (Atom or RSS)'
        );
        $this->registerCustomRule(
            'rss_feed_url',
            RssFeedUrl::class,
            'RSS Feed URL'
        );
        $this->registerCustomRule(
            'tri_profile_url',
            TriProfileUrl::class,
            'UAMS Translational Research Institute (TRI) Profile URL'
        );
        $this->registerCustomRule(
            'first_party_url',
            FirstPartyUrl::class,
            'First-party URL (must be a UAMS-owned domain)',
            [],
            [
                0 => true,
            ]
        );
        $this->registerCustomRule(
            'non_first_party_url',
            FirstPartyUrl::class,
            'External URL (must not be a UAMS-owned domain)',
            [],
            [
                0 => false,
            ]
        );
        $this->registerCustomRule(
            'uams_video_url',
            UamsVideoUrl::class,
            'UAMS Video URL (YouTube, Vimeo, or Box)'
        );
        $this->registerCustomRule(
            'vimeo_url',
            VimeoUrl::class,
            'Vimeo URL'
        );
        $this->registerCustomRule(
            'youtube_url',
            YouTubeUrl::class,
            'YouTube URL'
        );

        // ---
        // Custom validation rules
        // ---

        $this->registerCustomRule(
            'ascii_identifier',
            AsciiIdentifier::class,
            'ASCII identifier'
        );
        $this->registerCustomRule(
            'identifier_regex',
            IdentifierRegex::class
        );
        $this->registerCustomRule(
            'subfields_all',
            SubfieldsAll::class,
            'Subfields (All)'
        );
        $this->registerCustomRule(
            'subfields_any',
            SubfieldsAny::class,
            'Subfields (Any)'
        );
        $this->registerCustomRule(
            'subfields_list',
            SubfieldsList::class,
            'Subfields (List)'
        );
        $this->registerCustomRule(
            'dep_in_declared_list',
            DepInDeclaredList::class,
            'DEP ID must be in the declared list',
            [
                0 => 'parent_field',
            ]
        );
        $this->registerCustomRule(
            'terms_subset_of',
            TermsSubsetOf::class,
            'Terms subset of field',
            [
                0 => 'parent_field',
            ]
        );
        $this->registerCustomRule(
            'unique_identifier',
            UniqueIdentifier::class,
            'Identifier must be unique'
        );
        $this->registerCustomRule(
            'unique_identifier_list',
            UniqueIdentifierList::class,
            'Identifiers must be unique'
        );
        $this->registerCustomRule(
            'zip_code',
            ZipCode::class,
            'U.S. ZIP Code'
        );
        $this->registerCustomRule(
            'xpath_syntax',
            XpathSyntax::class,
            'XPath Syntax'
        );
        $this->registerCustomRule(
            'css_selector_syntax',
            CssSelectorSyntax::class,
            'CSS Selector Syntax'
        );
        $this->registerCustomRule(
            'international_phonetic_alphabet',
            InternationalPhoneticAlphabet::class,
            'International Phonetic Alphabet (IPA)'
        );
        $this->registerCustomRule(
            'entry_schema_type_in',
            EntrySchemaTypeIn::class,
            'Entry schema type'
        );
        $this->registerCustomRule(
            'asset_validation_satisfied',
            AssetValidationSatisfied::class,
            'Asset validation satisfied'
        );
        $this->registerCustomRule(
            'board_certification_term',
            BoardCertificationTerm::class,
            'Certifications taxonomy term (board certifications scope)'
        );
        $this->registerCustomRule(
            'other_certification_term',
            OtherCertificationTerm::class,
            'Certifications taxonomy term (other certifications scope)'
        );
        $this->registerCustomRule(
            'specialty_taxonomy_term_type',
            SpecialtyTaxonomyTermType::class,
            'Specialty taxonomy term type (matches specialty_taxonomy_term_type scope)'
        );
        $this->registerCustomRule(
            'professional_development_activity_term',
            ProfessionalDevelopmentActivitiesTerm::class,
            'Professional and leadership development activity term (professional scope)'
        );
        $this->registerCustomRule(
            'leadership_development_activity_term',
            LeadershipDevelopmentActivitiesTerm::class,
            'Professional and leadership development activity term (leadership scope)'
        );
        $this->registerCustomRule(
            'credential_type_certification',
            CredentialTypeCertification::class,
            'Credential term (CTDL credential type Certification)'
        );
        $this->registerCustomRule(
            'not_top_level_organization',
            NotTopLevelOrganization::class,
            'Organization below the top of the organizations tree'
        );
    }

    /**
     * Register a custom validation rule with type-specific message support and
     * optional custom display names for the Statamic Control Panel.
     *
     * This method supports both Rule and ValidationRule interfaces:
     *   - Rule interface: Direct registration with passes()/message() methods
     *   - ValidationRule interface: Bridge pattern to convert to Rule interface
     *   - ValidatorAwareRule: When combined with ValidationRule, setValidator() is
     *     called so rules can use getDisplayableAttribute() (e.g. nested Statamic fields)
     *
     * ## Custom Display Names
     *
     * When a string is provided as the third parameter, it will be used as the
     * custom display name for the rule in the Statamic Control Panel. This allows
     * you to override the default title case conversion (e.g., "YouTube URL" instead
     * of "Youtube Url").
     *
     * ## Parameter Mapping
     *
     * When an array is provided as the third parameter, it will be used to map
     * parameter positions to named values for validation messages.
     *
     * ## Baked Constructor Arguments
     *
     * The optional `$constructorArguments` are passed to the rule class constructor
     * ahead of any validation-time parameters, baked in at registration time. This
     * lets one rule class back several aliases that differ only by a fixed
     * constructor flag — e.g. `first_party_url` and `non_first_party_url` both
     * register `App\Rules\FirstPartyUrl`, distinguished by a baked
     * `$mustBeFirstParty` boolean — without exposing the flag as a blueprint rule
     * parameter.
     *
     * @param  string  $ruleName  The name of the validation rule (e.g., 'youtube_url')
     * @param  class-string  $ruleClass  The fully qualified class name of the rule
     * @param  array<int, string>|string  $parameterMapOrDisplayName  Optional mapping of parameter positions to names, or display name string for
     *                                                                the Statamic Control Panel
     * @param  array<int, string>  $parameterMap  Optional mapping of parameter positions to names (when display name is provided
     *                                            as third parameter)
     * @param  array<int, mixed>  $constructorArguments  Optional fixed constructor arguments baked in at registration, prepended to
     *                                                   any validation-time parameters when instantiating the rule
     */
    protected function registerCustomRule(
        string $ruleName,
        string $ruleClass,
        array|string $parameterMapOrDisplayName = [],
        array $parameterMap = [],
        array $constructorArguments = []
    ): void {
        // ---
        // Handle display name parameter
        // ---

        // Check if third parameter is a string (display name)
        if (\is_string($parameterMapOrDisplayName)) {
            // Store the display name string
            $displayName = $parameterMapOrDisplayName;

            // Store display name in instance property
            $this->customDisplayNames[$ruleName] = $displayName;

            // Also store in static property for access from other classes
            self::$staticCustomDisplayNames[$ruleName] = $displayName;
        } else { // Otherwise
            // Use parameter as parameter map
            $parameterMap = $parameterMapOrDisplayName;
        }

        // Register the validation rule
        Validator::extend(
            $ruleName,
            function ($attribute, $value, array $parameters, $validator) use ($ruleClass, $parameterMap, $constructorArguments) {
                /** @var string $attribute */
                /** @var list<string> $parameters */
                /** @var \Illuminate\Validation\Validator $validator */

                // Map parameters if mapping is provided
                $mappedParameters = $this->mapParameters(
                    $parameters,
                    $parameterMap
                );

                // Create rule instance with baked constructor arguments prepended to
                // any validation-time parameters
                $rule = new $ruleClass(...$constructorArguments, ...$mappedParameters);

                // Check if rule implements ValidationRule interface
                if ($rule instanceof ValidationRule) {
                    // ---
                    // Bridge pattern: Convert ValidationRule to Rule interface
                    // ---

                    // Pass the validator when the rule resolves displayable attribute names
                    if ($rule instanceof ValidatorAwareRule) {
                        $rule->setValidator($validator);
                    }

                    // Initialize error flag
                    $hasErrors = false;

                    // Create fail callback closure for ValidationRule interface
                    $fail = function ($message) use ($attribute, $validator, &$hasErrors): void {
                        /** @var \Illuminate\Validation\Validator $validator */

                        // Add validation error to validator
                        $validator->errors()->add(
                            $attribute,
                            self::failMessageString($message)
                        );

                        // Set error flag to true
                        $hasErrors = true;
                    };

                    // Call validate method on rule with fail callback. The closure
                    // intentionally records the message and flips $hasErrors rather
                    // than returning the chainable PotentiallyTranslatedString that
                    // Laravel's interface declares; the ValidationRule implementations
                    // invoked here call $fail($message) without chaining.
                    $rule->validate(
                        $attribute,
                        $value,
                        $fail, // @phpstan-ignore argument.type
                    );

                    // ---
                    // Return true to prevent Laravel from invoking addFailure()/message
                    // replacement for this rule. ValidationRule errors are already added
                    // through the `$fail` callback above.
                    // ---
                    return true;
                }

                // Otherwise
                // ---
                // Standard Rule interface
                // ---
                /** @var Rule $rule */
                // Call passes method on rule
                $result = $rule->passes(
                    $attribute,
                    $value
                );

                // Return validation result
                return $result;
            }
        );

        // Register the message replacer (only for Rule interface, not ValidationRule)
        Validator::replacer(
            $ruleName,
            function ($message, $attribute, $rule, array $parameters) use ($ruleName, $parameterMap, $ruleClass) {
                /** @var string $attribute */
                /** @var list<string> $parameters */

                // ---
                // Check if this is a ValidationRule interface using reflection. If so, skip
                // message replacement. ValidationRule provides messages via the $fail callback in
                // validate(), which may use inline strings or translation files.
                // ---

                // Create reflection class instance to inspect rule class
                $reflection = new ReflectionClass($ruleClass);

                // Check if rule implements ValidationRule interface
                if ($reflection->implementsInterface(ValidationRule::class)) {
                    // Return original message as-is
                    return $message;
                }

                // Map parameters if mapping is provided
                $mappedParameters = $this->mapParameters(
                    $parameters,
                    $parameterMap
                );

                // Get the type parameter (usually the second parameter)
                $type = $mappedParameters[1] ?? 'default';

                // Use the language file message based on type, with fallback to generic message
                $messageKey = sprintf('validation.%s.%s', $ruleName, $type);

                // ---
                // Check if the specific type message exists, otherwise use the generic fallback
                // ---

                // Check if translation key doesn't exist (translation equals key)
                if (trans($messageKey) === $messageKey) {
                    // Use generic message key without type suffix
                    $messageKey = 'validation.'.$ruleName;
                }

                // ---
                // Ensure the final message key resolves to a string. Some rules define a
                // nested array (e.g. ['default' => ..., 'invalid' => ...]); in that case use
                // the `default` message to prevent array values from reaching formatter logic.
                // ---

                // Resolve translated message value for the current key
                $resolvedMessage = trans($messageKey);

                // Check if translation resolved to an array value
                if (\is_array($resolvedMessage)) {
                    // Resolve the default message key for nested translation arrays
                    $defaultMessageKey = $messageKey.'.default';

                    // Check if nested default key exists
                    if (trans($defaultMessageKey) !== $defaultMessageKey) {
                        // Update message key to nested default when available
                        $messageKey = $defaultMessageKey;
                    } else {
                        // Fall back to generic required-string message key
                        $messageKey = 'validation.required';
                    }
                }

                // Build replacement array dynamically based on message content
                $replacements = ['attribute' => $attribute];

                // Get the message template to detect what parameters it needs
                $messageTemplate = trans(
                    $messageKey,
                    [],
                    'en'
                );

                // ---
                // Extract parameter names from the message template (e.g., :date, :value, etc.)
                // ---

                // Extract parameter placeholders from message template using regex
                preg_match_all(
                    '/:(\w+)/',
                    $messageTemplate,
                    $matches
                );

                // Get matched parameter names from regex results
                $neededParams = $matches[1];

                // ---
                // Use mapped parameters if provided
                // ---

                // Check if parameter map is provided
                if ($parameterMap !== []) {
                    // Loop through mapped parameters
                    foreach ($mappedParameters as $index => $value) {
                        // Get parameter name from map
                        $paramName = $parameterMap[$index];

                        // Add parameter to replacements array
                        $replacements[$paramName] = $value;
                    }
                } else { // Otherwise
                    // ---
                    // Provide parameters based on what the message actually needs
                    // ---

                    // Loop through needed parameters from message template
                    foreach ($neededParams as $paramIndex => $paramName) {
                        // Check if parameter is not 'attribute' and exists in mapped parameters
                        if ($paramName !== 'attribute' && isset($mappedParameters[$paramIndex])) {
                            // Add parameter to replacements array
                            $replacements[$paramName] = $mappedParameters[$paramIndex];
                        }
                    }
                }

                // Return translated message with replacements
                return trans(
                    $messageKey,
                    $replacements
                );
            }
        );
    }

    /**
     * Coerce what a rule passed to its fail callback to a string.
     *
     * Laravel's interface says the message is a string, but the rules here pass what
     * `__()` returns, which is an array for a translation group. Scalars and Stringables
     * are cast, and anything else becomes an empty string.
     *
     * @param  mixed  $message  The message the rule passed
     */
    private static function failMessageString(mixed $message): string
    {
        if (is_string($message)) {
            return $message;
        }

        if (is_scalar($message) || $message instanceof \Stringable) {
            return (string) $message;
        }

        return '';
    }

    /**
     * Map validation rule parameters to named values for use in validation messages.
     *
     * This method takes the raw parameters array from Laravel's validation system
     * and maps them to named values based on the provided parameter map. This allows
     * for more descriptive validation messages that use meaningful parameter names
     * instead of positional indices.
     *
     * @param  list<string>  $parameters  The original parameters array from Laravel's validator
     * @param  array<int, string>  $parameterMap  Mapping of parameter positions to descriptive names
     * @return array<int, string|null> The mapped parameters array with named keys
     */
    protected function mapParameters(
        array $parameters,
        array $parameterMap
    ): array {
        // Check if no mapping is provided
        if ($parameterMap === []) {
            // Return parameters as-is
            return $parameters;
        }

        // ---
        // Map parameters based on the provided mapping
        // ---

        // Initialize mapped parameters array
        $mapped = [];

        // Loop through parameter map
        foreach (array_keys($parameterMap) as $position) {
            // Map parameter value from original parameters array
            $mapped[$position] = $parameters[$position] ?? null;
        }

        // Return mapped parameters array
        return $mapped;
    }

    /**
     * Get custom display names for validation rules (instance method)
     *
     * This method returns the custom display names stored in the instance property.
     * It's primarily used for internal access within the service provider.
     *
     * @return array<string, string> Array of rule names mapped to their display names
     */
    public function getCustomDisplayNames(): array
    {
        return $this->customDisplayNames;
    }

    /**
     * Get custom display names for validation rules (static method)
     *
     * This static method provides access to custom display names from other classes
     * without requiring instantiation of the ValidationServiceProvider. This is
     * used by the CustomFieldComposer to avoid circular dependency issues.
     *
     * @return array<string, string> Array of rule names mapped to their display names
     */
    public static function getStaticCustomDisplayNames(): array
    {
        return self::$staticCustomDisplayNames;
    }
}
