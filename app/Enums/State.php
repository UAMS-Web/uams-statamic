<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\EnumGeneration\EnumDataUtils;
use Attribute;

/**
 * Attribute for storing state information on enum cases.
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class StateInfo
{
    public string $fullName;

    public string $apAbbreviation;

    public string $type;

    public string $fipsCode;

    public string $iso3166_2;

    public ?string $baseName;

    public ?string $geoId;

    public ?string $nationalStandardCode;

    public ?string $division;

    public ?string $region;

    public ?string $functionalStatusCode;

    public ?string $mafTigerFeatureClassCode;

    public ?string $mafTigerObjectIdentifier;

    public ?string $legalStatisticalAreaDescriptionCode;

    public ?string $areaLand;

    public ?string $areaWater;

    public ?string $arcGisObjectIdentifier;

    /** @var array{0:string,1:string}|array<int,string> */
    public array $centroidGeo;

    /** @var array{0:string,1:string}|array<int,string> */
    public array $internalPointGeo;

    public function __construct(
        string $fullName,
        string $apAbbreviation,
        string $type,
        string $fipsCode,
        string $iso3166_2,
        ?string $baseName = null,
        ?string $geoId = null,
        ?string $nationalStandardCode = null,
        ?string $division = null,
        ?string $region = null,
        ?string $functionalStatusCode = null,
        ?string $mafTigerFeatureClassCode = null,
        ?string $mafTigerObjectIdentifier = null,
        ?string $legalStatisticalAreaDescriptionCode = null,
        ?string $areaLand = null,
        ?string $areaWater = null,
        ?string $arcGisObjectIdentifier = null,
        array $centroidGeo = [],
        array $internalPointGeo = [],
    ) {
        $this->fullName = $fullName;
        $this->apAbbreviation = $apAbbreviation;
        $this->type = $type;
        $this->fipsCode = $fipsCode;
        $this->iso3166_2 = $iso3166_2;

        $this->baseName = $baseName;
        $this->geoId = $geoId;
        $this->nationalStandardCode = $nationalStandardCode;
        $this->division = $division;
        $this->region = $region;
        $this->functionalStatusCode = $functionalStatusCode;
        $this->mafTigerFeatureClassCode = $mafTigerFeatureClassCode;
        $this->mafTigerObjectIdentifier = $mafTigerObjectIdentifier;
        $this->legalStatisticalAreaDescriptionCode = $legalStatisticalAreaDescriptionCode;
        $this->areaLand = $areaLand;
        $this->areaWater = $areaWater;
        $this->arcGisObjectIdentifier = $arcGisObjectIdentifier;
        $this->centroidGeo = $centroidGeo;
        $this->internalPointGeo = $internalPointGeo;
    }
}

/**
 * US State/territory enum with comprehensive geographic and administrative data.
 *
 * This enum is intended for use wherever a standardized list of U.S. states,
 * territories, and districts is required, such as form dropdowns, validation,
 * or formatting for display.
 *
 * This enum provides two ways to access attribute data:
 *
 *   1. **Getter Methods** (Recommended for common use):
 *      - `fullName()`, `apAbbreviation()`, `type()`, `fipsCode()`, `iso3166_2()`
 *      - These methods use cached data for optimal performance
 *      - Only include the most commonly used attributes
 *      - Add new getter methods whenever an attribute becomes frequently used
 *
 *   2. **Direct Reflection** (For advanced/rare use cases):
 *      - Access any attribute directly via PHP's reflection API
 *      - Example: `$reflection = new \ReflectionEnumUnitCase(State::class, 'CA');`
 *      - Then:
 *        `$info = $reflection->getAttributes(StateInfo::class)[0]->newInstance();`
 *      - Provides access to ALL attributes: baseName, geoId,
 *        nationalStandardCode, etc.
 *
 * **Adding New Getter Methods:**
 *
 * When an attribute becomes commonly used, add a getter method following this
 * pattern:
 *   ```php
 *   public function attributeName(): string
 *   {
 *       $data = self::stateData();
 *       return EnumDataUtils::getFieldValue($data, $this->value, 'attributeName', 'state');
 *   }
 *   ```
 *
 * Also update the `stateData()` method to include the new attribute in the
 * returned array.
 *
 * Each case's string value represents the USPS abbreviation
 * (e.g., 'AL' for Alabama). The case name is a human-readable identifier for the
 * state.
 *
 * Each case includes:
 *   - fullName // NAME; The full name of the state (e.g., Alabama = 'Alabama').
 *   - apAbbreviation // The AP style abbreviation (e.g., Alabama = 'Ala.').
 *   - type // The type: 'state', 'territory', or 'district'
 *     (e.g., Alabama = 'state').
 *   - fipsCode // STATE; Two-digit FIPS state code (e.g., Alabama = '01').
 *   - iso3166_2 // ISO 3166-2 subdivision code (e.g., Alabama = 'US-AL').
 *   - baseName // BASENAME; State name without any legal/statistical suffix
 *     (often same as fullName for states; e.g., Alabama = 'Alabama').
 *   - geoId // GEOID; Fully qualified state GEOID (usually 2-digit FIPS as
 *     a string for states; e.g., Alabama = '01').
 *   - nationalStandardCode // STATENS; GNIS/ANSI Names ID for the state
 *     (persistent identifier string).
 *   - division // DIVISION; U.S. Census Division code (1–9).
 *       - 1: New England
 *       - 2: Middle Atlantic
 *       - 3: East North Central
 *       - 4: West North Central
 *       - 5: South Atlantic
 *       - 6: East South Central
 *       - 7: West South Central
 *       - 8: Mountain
 *       - 9: Pacific
 *   - region // REGION; U.S. Census Region code (1–4).
 *       - 1: Northeast
 *       - 2: Midwest
 *       - 3: South
 *       - 4: West
 *   - functionalStatusCode // FUNCSTAT; Functional status code of the area
 *       - A: Active entity (federally recognized entities only)
 *           - American Indian/Alaska Native/Native Hawaiian Area
 *       - More at
 *         https://www.census.gov/library/reference/code-lists/functional-status-codes.html
 *         (https://archive.is/fUEqx)
 *   - mafTigerFeatureClassCode // MTFCC; MAF/TIGER Feature Class Code. Identifies
 *     the feature type.
 *       - G4000: State or Equivalent Feature
 *       - More at
 *         https://www.census.gov/library/reference/code-lists/mt-feature-class-codes.html
 *         (https://archive.is/tUsrx)
 *   - mafTigerObjectIdentifier // OID; MAF/TIGER Object Identifier; Permanent
 *     TIGER-derived identifier string for the feature (distinct from the GIS
 *     layer's `OBJECTID`)
 *   - legalStatisticalAreaDescriptionCode // LSADC; Legal/Statistical Area
 *     Description code for the suffix type.
 *       - 00: (no prefix or suffix)
 *           - American Indian Area
 *           - Congressional District
 *           - Consolidated City
 *           - County or Equivalent Feature
 *           - County Subdivision
 *           - Economic Census Place
 *           - Elementary School District
 *           - Incorporated Place
 *           - Local/Tribal Legislative District
 *           - Military Installation
 *           - Nation
 *           - Secondary School District
 *           - Special School Administrative Area
 *           - Special-Purpose District
 *           - State Legislative District (Lower Chamber)
 *           - State Legislative District (Upper Chamber)
 *           - State or Equivalent Feature
 *           - Tribal Subdivision
 *           - Unified School District
 *           - Voting District
 *       - More at
 *         https://www.census.gov/library/reference/code-lists/legal-status-codes.html
 *         (https://archive.is/1rR4s)
 *   - areaLand // AREALAND; Land area in square meters (integer)
 *   - areaWater // AREAWATER; Water area in square meters (integer)
 *   - arcGisObjectIdentifier // OBJECTID; ArcGIS-managed numeric object ID for
 *     the layer (distinct from the MAF/TIGER `OID`)
 *   - centroidGeo[<lat>, <lon>] // CENTLAT & CENTLON; Feature centroid latitude
 *     and longitude in decimal degrees (string with sign).
 *   - internalPointGeo[<lat>, <lon>] // INTPTLAT & INTPTLON; Internal point
 *     latitude and longitude in decimal degrees (string with sign).
 *
 * Note on geometry shapes:
 *   - You can fetch polygon geometries for states from the API, but they are not
 *     embedded in the enum. To include geometry in API responses, add:
 *     returnGeometry=true&outSR=4326
 *     (outSR=4326 requests WGS84 lon/lat coordinates.)
 *
 * Data Source:
 *   - TIGERweb/State_County (MapServer), States (or statistically equivalent
 *     entities):
 *       - Layer 0 (January 1, 2025 vintage):
 *         https://tigerweb.geo.census.gov/arcgis/rest/services/TIGERweb/State_County/MapServer/0/query?where=1%3D1&outFields=STUSAB,NAME,BASENAME,STATE,GEOID,STATENS,DIVISION,REGION,FUNCSTAT,MTFCC,OID,LSADC,AREALAND,AREAWATER,OBJECTID,CENTLAT,CENTLON,INTPTLAT,INTPTLON&returnGeometry=false&f=json
 *
 * This list of cases was generated on 2025-08-11 at 19:35:50 UTC
 * Total states: 56
 */
enum State: string
{
    #[StateInfo(fullName: 'Alaska', apAbbreviation: 'Alaska', type: 'state', fipsCode: '02', iso3166_2: 'US-AK', baseName: 'Alaska', geoId: '02', nationalStandardCode: '01785533', division: '9', region: '4', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '2749045059742', legalStatisticalAreaDescriptionCode: '00', areaLand: '1479893380150', areaWater: '244326118163', arcGisObjectIdentifier: '3', centroidGeo: ['+63.4136458', '-152.8788722'], internalPointGeo: ['+63.3473560', '-152.8397334'])]
    case AK = 'AK';

    #[StateInfo(fullName: 'Alabama', apAbbreviation: 'Ala.', type: 'state', fipsCode: '01', iso3166_2: 'US-AL', baseName: 'Alabama', geoId: '01', nationalStandardCode: '01779775', division: '6', region: '3', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '2749065038753', legalStatisticalAreaDescriptionCode: '00', areaLand: '131186429591', areaWater: '4580729056', arcGisObjectIdentifier: '14', centroidGeo: ['+32.7436844', '-086.8467928'], internalPointGeo: ['+32.7395785', '-086.8434469'])]
    case AL = 'AL';

    #[StateInfo(fullName: 'Arkansas', apAbbreviation: 'Ark.', type: 'state', fipsCode: '05', iso3166_2: 'US-AR', baseName: 'Arkansas', geoId: '05', nationalStandardCode: '00068085', division: '7', region: '3', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '2749045148374', legalStatisticalAreaDescriptionCode: '00', areaLand: '134658642291', areaWater: '3122591253', arcGisObjectIdentifier: '42', centroidGeo: ['+34.8941718', '-092.4418676'], internalPointGeo: ['+34.8955256', '-092.4446262'])]
    case AR = 'AR';

    #[StateInfo(fullName: 'American Samoa', apAbbreviation: 'American Samoa', type: 'territory', fipsCode: '60', iso3166_2: 'US-AS', baseName: 'American Samoa', geoId: '60', nationalStandardCode: '01802701', division: '0', region: '9', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '27490459971956', legalStatisticalAreaDescriptionCode: '00', areaLand: '197759070', areaWater: '1307243751', arcGisObjectIdentifier: '4', centroidGeo: ['-13.9638307', '-170.0822667'], internalPointGeo: ['-14.2668475', '-170.6671854'])]
    case AS = 'AS';

    #[StateInfo(fullName: 'Arizona', apAbbreviation: 'Ariz.', type: 'state', fipsCode: '04', iso3166_2: 'US-AZ', baseName: 'Arizona', geoId: '04', nationalStandardCode: '01779777', division: '8', region: '4', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '274901053753069', legalStatisticalAreaDescriptionCode: '00', areaLand: '294366238828', areaWater: '853868947', arcGisObjectIdentifier: '24', centroidGeo: ['+34.2750988', '-111.6605311'], internalPointGeo: ['+34.2039362', '-111.6063449'])]
    case AZ = 'AZ';

    #[StateInfo(fullName: 'California', apAbbreviation: 'Calif.', type: 'state', fipsCode: '06', iso3166_2: 'US-CA', baseName: 'California', geoId: '06', nationalStandardCode: '01779778', division: '9', region: '4', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '2749018475066', legalStatisticalAreaDescriptionCode: '00', areaLand: '403676564152', areaWater: '20288505023', arcGisObjectIdentifier: '38', centroidGeo: ['+37.1547616', '-119.5277735'], internalPointGeo: ['+37.1551773', '-119.5434183'])]
    case CA = 'CA';

    #[StateInfo(fullName: 'Colorado', apAbbreviation: 'Colo.', type: 'state', fipsCode: '08', iso3166_2: 'US-CO', baseName: 'Colorado', geoId: '08', nationalStandardCode: '01779779', division: '8', region: '4', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '2749086215995', legalStatisticalAreaDescriptionCode: '00', areaLand: '268419398775', areaWater: '1185110804', arcGisObjectIdentifier: '50', centroidGeo: ['+38.9976180', '-105.5478280'], internalPointGeo: ['+38.9937669', '-105.5087122'])]
    case CO = 'CO';

    #[StateInfo(fullName: 'Connecticut', apAbbreviation: 'Conn.', type: 'state', fipsCode: '09', iso3166_2: 'US-CT', baseName: 'Connecticut', geoId: '09', nationalStandardCode: '01779780', division: '1', region: '1', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '27490331955805', legalStatisticalAreaDescriptionCode: '00', areaLand: '12542101087', areaWater: '1816013585', arcGisObjectIdentifier: '6', centroidGeo: ['+41.5751437', '-072.7393081'], internalPointGeo: ['+41.5798637', '-072.7466572'])]
    case CT = 'CT';

    #[StateInfo(fullName: 'District of Columbia', apAbbreviation: 'D.C.', type: 'district', fipsCode: '11', iso3166_2: 'US-DC', baseName: 'District of Columbia', geoId: '11', nationalStandardCode: '01702382', division: '5', region: '3', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '27490331294090', legalStatisticalAreaDescriptionCode: '00', areaLand: '158318961', areaWater: '18706999', arcGisObjectIdentifier: '22', centroidGeo: ['+38.9047586', '-077.0162860'], internalPointGeo: ['+38.9042429', '-077.0165243'])]
    case DC = 'DC';

    #[StateInfo(fullName: 'Delaware', apAbbreviation: 'Del.', type: 'state', fipsCode: '10', iso3166_2: 'US-DE', baseName: 'Delaware', geoId: '10', nationalStandardCode: '01779781', division: '5', region: '3', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '27490331618368', legalStatisticalAreaDescriptionCode: '00', areaLand: '5046707808', areaWater: '1399203452', arcGisObjectIdentifier: '7', centroidGeo: ['+38.9932570', '-075.4514104'], internalPointGeo: ['+38.9985661', '-075.4416440'])]
    case DE = 'DE';

    #[StateInfo(fullName: 'Florida', apAbbreviation: 'Fla.', type: 'state', fipsCode: '12', iso3166_2: 'US-FL', baseName: 'Florida', geoId: '12', nationalStandardCode: '00294478', division: '5', region: '3', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '2749046365780', legalStatisticalAreaDescriptionCode: '00', areaLand: '138973813223', areaWater: '45960479273', arcGisObjectIdentifier: '31', centroidGeo: ['+28.4062029', '-082.5022125'], internalPointGeo: ['+28.3989775', '-082.5143005'])]
    case FL = 'FL';

    #[StateInfo(fullName: 'Georgia', apAbbreviation: 'Ga.', type: 'state', fipsCode: '13', iso3166_2: 'US-GA', baseName: 'Georgia', geoId: '13', nationalStandardCode: '01705317', division: '5', region: '3', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '2749048077737', legalStatisticalAreaDescriptionCode: '00', areaLand: '149487720450', areaWater: '4417264043', arcGisObjectIdentifier: '13', centroidGeo: ['+32.6279417', '-083.4165286'], internalPointGeo: ['+32.6295789', '-083.4235109'])]
    case GA = 'GA';

    #[StateInfo(fullName: 'Guam', apAbbreviation: 'Guam', type: 'territory', fipsCode: '66', iso3166_2: 'US-GU', baseName: 'Guam', geoId: '66', nationalStandardCode: '01802705', division: '0', region: '9', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '27490748116647', legalStatisticalAreaDescriptionCode: '00', areaLand: '543555846', areaWater: '934337452', arcGisObjectIdentifier: '55', centroidGeo: ['+13.4427156', '+144.7693797'], internalPointGeo: ['+13.4382885', '+144.7729494'])]
    case GU = 'GU';

    #[StateInfo(fullName: 'Hawaii', apAbbreviation: 'Hawaii', type: 'state', fipsCode: '15', iso3166_2: 'US-HI', baseName: 'Hawaii', geoId: '15', nationalStandardCode: '01779782', division: '9', region: '4', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '2749047648128', legalStatisticalAreaDescriptionCode: '00', areaLand: '16634436568', areaWater: '11777362694', arcGisObjectIdentifier: '26', centroidGeo: ['+21.0508739', '-157.9936887'], internalPointGeo: ['+19.8281671', '-155.4950421'])]
    case HI = 'HI';

    #[StateInfo(fullName: 'Iowa', apAbbreviation: 'Iowa', type: 'state', fipsCode: '19', iso3166_2: 'US-IA', baseName: 'Iowa', geoId: '19', nationalStandardCode: '01779785', division: '4', region: '2', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '2749058169487', legalStatisticalAreaDescriptionCode: '00', areaLand: '144660389024', areaWater: '1085298995', arcGisObjectIdentifier: '23', centroidGeo: ['+42.0753617', '-093.4958521'], internalPointGeo: ['+42.0700227', '-093.4933492'])]
    case IA = 'IA';

    #[StateInfo(fullName: 'Idaho', apAbbreviation: 'Idaho', type: 'state', fipsCode: '16', iso3166_2: 'US-ID', baseName: 'Idaho', geoId: '16', nationalStandardCode: '01779783', division: '8', region: '4', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '27490130780184', legalStatisticalAreaDescriptionCode: '00', areaLand: '214050683201', areaWater: '2390817981', arcGisObjectIdentifier: '36', centroidGeo: ['+44.3524056', '-114.6136830'], internalPointGeo: ['+44.3484220', '-114.5588540'])]
    case ID = 'ID';

    #[StateInfo(fullName: 'Illinois', apAbbreviation: 'Ill.', type: 'state', fipsCode: '17', iso3166_2: 'US-IL', baseName: 'Illinois', geoId: '17', nationalStandardCode: '01779784', division: '3', region: '2', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '27490100360203', legalStatisticalAreaDescriptionCode: '00', areaLand: '143779367883', areaWater: '6215353468', arcGisObjectIdentifier: '32', centroidGeo: ['+40.1006068', '-089.1500794'], internalPointGeo: ['+40.1028754', '-089.1526108'])]
    case IL = 'IL';

    #[StateInfo(fullName: 'Indiana', apAbbreviation: 'Ind.', type: 'state', fipsCode: '18', iso3166_2: 'US-IN', baseName: 'Indiana', geoId: '18', nationalStandardCode: '00448508', division: '3', region: '2', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '2749051705222', legalStatisticalAreaDescriptionCode: '00', areaLand: '92786782808', areaWater: '1543815569', arcGisObjectIdentifier: '47', centroidGeo: ['+39.9059401', '-086.2872768'], internalPointGeo: ['+39.9013136', '-086.2919129'])]
    case IN = 'IN';

    #[StateInfo(fullName: 'Kansas', apAbbreviation: 'Kan.', type: 'state', fipsCode: '20', iso3166_2: 'US-KS', baseName: 'Kansas', geoId: '20', nationalStandardCode: '00481813', division: '4', region: '2', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '2749051425496', legalStatisticalAreaDescriptionCode: '00', areaLand: '211754288230', areaWater: '1345197050', arcGisObjectIdentifier: '46', centroidGeo: ['+38.4938959', '-098.3786217'], internalPointGeo: ['+38.4985464', '-098.3834298'])]
    case KS = 'KS';

    #[StateInfo(fullName: 'Kentucky', apAbbreviation: 'Ky.', type: 'state', fipsCode: '21', iso3166_2: 'US-KY', baseName: 'Kentucky', geoId: '21', nationalStandardCode: '01779786', division: '6', region: '3', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '27490100360204', legalStatisticalAreaDescriptionCode: '00', areaLand: '102267794436', areaWater: '2383097595', arcGisObjectIdentifier: '28', centroidGeo: ['+37.5351657', '-085.3018147'], internalPointGeo: ['+37.5336844', '-085.2929801'])]
    case KY = 'KY';

    #[StateInfo(fullName: 'Louisiana', apAbbreviation: 'La.', type: 'state', fipsCode: '22', iso3166_2: 'US-LA', baseName: 'Louisiana', geoId: '22', nationalStandardCode: '01629543', division: '7', region: '3', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '2749052402301', legalStatisticalAreaDescriptionCode: '00', areaLand: '111927154861', areaWater: '23724485446', arcGisObjectIdentifier: '53', centroidGeo: ['+30.9025928', '-091.7982299'], internalPointGeo: ['+30.7083190', '-091.6046207'])]
    case LA = 'LA';

    #[StateInfo(fullName: 'Massachusetts', apAbbreviation: 'Mass.', type: 'state', fipsCode: '25', iso3166_2: 'US-MA', baseName: 'Massachusetts', geoId: '25', nationalStandardCode: '00606926', division: '1', region: '1', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '27490203627104', legalStatisticalAreaDescriptionCode: '00', areaLand: '20204475228', areaWater: '7130575380', arcGisObjectIdentifier: '20', centroidGeo: ['+42.1618490', '-071.4975238'], internalPointGeo: ['+42.1565196', '-071.4895915'])]
    case MA = 'MA';

    #[StateInfo(fullName: 'Maryland', apAbbreviation: 'Md.', type: 'state', fipsCode: '24', iso3166_2: 'US-MD', baseName: 'Maryland', geoId: '24', nationalStandardCode: '01714934', division: '5', region: '3', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '27490140608205', legalStatisticalAreaDescriptionCode: '00', areaLand: '25151298706', areaWater: '6979771758', arcGisObjectIdentifier: '34', centroidGeo: ['+38.9463607', '-076.6789664'], internalPointGeo: ['+38.9466584', '-076.6744939'])]
    case MD = 'MD';

    #[StateInfo(fullName: 'Maine', apAbbreviation: 'Maine', type: 'state', fipsCode: '23', iso3166_2: 'US-ME', baseName: 'Maine', geoId: '23', nationalStandardCode: '01779787', division: '1', region: '1', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '27490100936543', legalStatisticalAreaDescriptionCode: '00', areaLand: '79888899089', areaWater: '11744562992', arcGisObjectIdentifier: '56', centroidGeo: ['+45.2610965', '-069.2136650'], internalPointGeo: ['+45.4092843', '-068.6666160'])]
    case ME = 'ME';

    #[StateInfo(fullName: 'Michigan', apAbbreviation: 'Mich.', type: 'state', fipsCode: '26', iso3166_2: 'US-MI', baseName: 'Michigan', geoId: '26', nationalStandardCode: '01779789', division: '3', region: '2', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '2749048217992', legalStatisticalAreaDescriptionCode: '00', areaLand: '146625947203', areaWater: '103860207077', arcGisObjectIdentifier: '41', centroidGeo: ['+44.8382368', '-085.6650623'], internalPointGeo: ['+44.8441768', '-085.6604907'])]
    case MI = 'MI';

    #[StateInfo(fullName: 'Minnesota', apAbbreviation: 'Minn.', type: 'state', fipsCode: '27', iso3166_2: 'US-MN', baseName: 'Minnesota', geoId: '27', nationalStandardCode: '00662849', division: '4', region: '2', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '27490186175787', legalStatisticalAreaDescriptionCode: '00', areaLand: '206245435043', areaWater: '18936592419', arcGisObjectIdentifier: '33', centroidGeo: ['+46.3167397', '-094.1982323'], internalPointGeo: ['+46.3159573', '-094.1996043'])]
    case MN = 'MN';

    #[StateInfo(fullName: 'Missouri', apAbbreviation: 'Mo.', type: 'state', fipsCode: '29', iso3166_2: 'US-MO', baseName: 'Missouri', geoId: '29', nationalStandardCode: '01779791', division: '4', region: '2', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '2749046934061', legalStatisticalAreaDescriptionCode: '00', areaLand: '178052192662', areaWater: '2487934091', arcGisObjectIdentifier: '44', centroidGeo: ['+38.3569837', '-092.4580520'], internalPointGeo: ['+38.3507508', '-092.4567820'])]
    case MO = 'MO';

    #[StateInfo(fullName: 'Commonwealth of the Northern Mariana Islands', apAbbreviation: 'Northern Mariana Islands', type: 'territory', fipsCode: '69', iso3166_2: 'US-MP', baseName: 'Commonwealth of the Northern Mariana Islands', geoId: '69', nationalStandardCode: '01779809', division: '0', region: '9', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '27490460013836', legalStatisticalAreaDescriptionCode: '00', areaLand: '472292520', areaWater: '4644252457', arcGisObjectIdentifier: '54', centroidGeo: ['+16.7974379', '+145.5968710'], internalPointGeo: ['+15.0010865', '+145.6181702'])]
    case MP = 'MP';

    #[StateInfo(fullName: 'Mississippi', apAbbreviation: 'Miss.', type: 'state', fipsCode: '28', iso3166_2: 'US-MS', baseName: 'Mississippi', geoId: '28', nationalStandardCode: '01779790', division: '6', region: '3', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '2749046721299', legalStatisticalAreaDescriptionCode: '00', areaLand: '121534611855', areaWater: '3914024462', arcGisObjectIdentifier: '43', centroidGeo: ['+32.6972986', '-089.6552478'], internalPointGeo: ['+32.6864714', '-089.6561377'])]
    case MS = 'MS';

    #[StateInfo(fullName: 'Montana', apAbbreviation: 'Mont.', type: 'state', fipsCode: '30', iso3166_2: 'US-MT', baseName: 'Montana', geoId: '30', nationalStandardCode: '00767982', division: '8', region: '4', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '2749047339960', legalStatisticalAreaDescriptionCode: '00', areaLand: '376973522036', areaWater: '3866841435', arcGisObjectIdentifier: '45', centroidGeo: ['+47.0526547', '-109.6328844'], internalPointGeo: ['+47.0511770', '-109.6348174'])]
    case MT = 'MT';

    #[StateInfo(fullName: 'North Carolina', apAbbreviation: 'N.C.', type: 'state', fipsCode: '37', iso3166_2: 'US-NC', baseName: 'North Carolina', geoId: '37', nationalStandardCode: '01027616', division: '5', region: '3', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '2749018629826', legalStatisticalAreaDescriptionCode: '00', areaLand: '125935547374', areaWater: '13453873497', arcGisObjectIdentifier: '9', centroidGeo: ['+35.5401756', '-079.1299320'], internalPointGeo: ['+35.5397100', '-079.1308636'])]
    case NC = 'NC';

    #[StateInfo(fullName: 'North Dakota', apAbbreviation: 'N.D.', type: 'state', fipsCode: '38', iso3166_2: 'US-ND', baseName: 'North Dakota', geoId: '38', nationalStandardCode: '01779797', division: '4', region: '2', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '2749051615269', legalStatisticalAreaDescriptionCode: '00', areaLand: '178694402328', areaWater: '4414688315', arcGisObjectIdentifier: '27', centroidGeo: ['+47.4504657', '-100.4660576'], internalPointGeo: ['+47.4421740', '-100.4608258'])]
    case ND = 'ND';

    #[StateInfo(fullName: 'Nebraska', apAbbreviation: 'Neb.', type: 'state', fipsCode: '31', iso3166_2: 'US-NE', baseName: 'Nebraska', geoId: '31', nationalStandardCode: '01779792', division: '4', region: '2', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '2749072488932', legalStatisticalAreaDescriptionCode: '00', areaLand: '198950563271', areaWater: '1378346850', arcGisObjectIdentifier: '12', centroidGeo: ['+41.5381095', '-099.7949779'], internalPointGeo: ['+41.5433050', '-099.8118641'])]
    case NE = 'NE';

    #[StateInfo(fullName: 'New Hampshire', apAbbreviation: 'N.H.', type: 'state', fipsCode: '33', iso3166_2: 'US-NH', baseName: 'New Hampshire', geoId: '33', nationalStandardCode: '01779794', division: '1', region: '1', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '27490227215319', legalStatisticalAreaDescriptionCode: '00', areaLand: '23190733491', areaWater: '1025349624', arcGisObjectIdentifier: '37', centroidGeo: ['+43.6740328', '-071.5738496'], internalPointGeo: ['+43.6727945', '-071.5841886'])]
    case NH = 'NH';

    #[StateInfo(fullName: 'New Jersey', apAbbreviation: 'N.J.', type: 'state', fipsCode: '34', iso3166_2: 'US-NJ', baseName: 'New Jersey', geoId: '34', nationalStandardCode: '01779795', division: '2', region: '1', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '27490286138606', legalStatisticalAreaDescriptionCode: '00', areaLand: '19049443028', areaWater: '3533161265', arcGisObjectIdentifier: '39', centroidGeo: ['+40.1048602', '-074.6583832'], internalPointGeo: ['+40.1072744', '-074.6652012'])]
    case NJ = 'NJ';

    #[StateInfo(fullName: 'New Mexico', apAbbreviation: 'N.M.', type: 'state', fipsCode: '35', iso3166_2: 'US-NM', baseName: 'New Mexico', geoId: '35', nationalStandardCode: '00897535', division: '8', region: '4', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '27490100936841', legalStatisticalAreaDescriptionCode: '00', areaLand: '314198529853', areaWater: '726526491', arcGisObjectIdentifier: '8', centroidGeo: ['+34.4075784', '-106.1126597'], internalPointGeo: ['+34.4346844', '-106.1316180'])]
    case NM = 'NM';

    #[StateInfo(fullName: 'Nevada', apAbbreviation: 'Nev.', type: 'state', fipsCode: '32', iso3166_2: 'US-NV', baseName: 'Nevada', geoId: '32', nationalStandardCode: '01779793', division: '8', region: '4', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '27490286467734', legalStatisticalAreaDescriptionCode: '00', areaLand: '284537074263', areaWater: '1839852286', arcGisObjectIdentifier: '2', centroidGeo: ['+39.3292318', '-116.6313463'], internalPointGeo: ['+39.3310928', '-116.6151469'])]
    case NV = 'NV';

    #[StateInfo(fullName: 'New York', apAbbreviation: 'N.Y.', type: 'state', fipsCode: '36', iso3166_2: 'US-NY', baseName: 'New York', geoId: '36', nationalStandardCode: '01779796', division: '2', region: '1', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '27490100833860', legalStatisticalAreaDescriptionCode: '00', areaLand: '122049344560', areaWater: '19256566831', arcGisObjectIdentifier: '1', centroidGeo: ['+42.9196122', '-075.5941454'], internalPointGeo: ['+42.9133974', '-075.5962723'])]
    case NY = 'NY';

    #[StateInfo(fullName: 'Ohio', apAbbreviation: 'Ohio', type: 'state', fipsCode: '39', iso3166_2: 'US-OH', baseName: 'Ohio', geoId: '39', nationalStandardCode: '01085497', division: '3', region: '2', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '2749046634335', legalStatisticalAreaDescriptionCode: '00', areaLand: '105824499476', areaWater: '10273857263', arcGisObjectIdentifier: '16', centroidGeo: ['+40.4097282', '-082.7168529'], internalPointGeo: ['+40.4149297', '-082.7119975'])]
    case OH = 'OH';

    #[StateInfo(fullName: 'Oklahoma', apAbbreviation: 'Okla.', type: 'state', fipsCode: '40', iso3166_2: 'US-OK', baseName: 'Oklahoma', geoId: '40', nationalStandardCode: '01102857', division: '7', region: '3', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '2749092328581', legalStatisticalAreaDescriptionCode: '00', areaLand: '177664943367', areaWater: '3372936372', arcGisObjectIdentifier: '17', centroidGeo: ['+35.5885699', '-097.4947176'], internalPointGeo: ['+35.5900815', '-097.4867789'])]
    case OK = 'OK';

    #[StateInfo(fullName: 'Oregon', apAbbreviation: 'Ore.', type: 'state', fipsCode: '41', iso3166_2: 'US-OR', baseName: 'Oregon', geoId: '41', nationalStandardCode: '01155107', division: '9', region: '4', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '27490206508673', legalStatisticalAreaDescriptionCode: '00', areaLand: '248630742196', areaWater: '6168664862', arcGisObjectIdentifier: '11', centroidGeo: ['+43.9396011', '-120.6109268'], internalPointGeo: ['+43.9717125', '-120.6229578'])]
    case OR = 'OR';

    #[StateInfo(fullName: 'Pennsylvania', apAbbreviation: 'Pa.', type: 'state', fipsCode: '42', iso3166_2: 'US-PA', baseName: 'Pennsylvania', geoId: '42', nationalStandardCode: '01779798', division: '2', region: '1', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '27490163788605', legalStatisticalAreaDescriptionCode: '00', areaLand: '115882017582', areaWater: '3397072484', arcGisObjectIdentifier: '51', centroidGeo: ['+40.9011363', '-077.8369449'], internalPointGeo: ['+40.9046042', '-077.8275233'])]
    case PA = 'PA';

    #[StateInfo(fullName: 'Puerto Rico', apAbbreviation: 'Puerto Rico', type: 'territory', fipsCode: '72', iso3166_2: 'US-PR', baseName: 'Puerto Rico', geoId: '72', nationalStandardCode: '01779808', division: '0', region: '9', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '2749082215471', legalStatisticalAreaDescriptionCode: '00', areaLand: '8869703179', areaWater: '4921575413', arcGisObjectIdentifier: '48', centroidGeo: ['+18.2164715', '-066.4147614'], internalPointGeo: ['+18.2176480', '-066.4107992'])]
    case PR = 'PR';

    #[StateInfo(fullName: 'Rhode Island', apAbbreviation: 'R.I.', type: 'state', fipsCode: '44', iso3166_2: 'US-RI', baseName: 'Rhode Island', geoId: '44', nationalStandardCode: '01219835', division: '1', region: '1', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '27490224494057', legalStatisticalAreaDescriptionCode: '00', areaLand: '2677770297', areaWater: '1323680040', arcGisObjectIdentifier: '35', centroidGeo: ['+41.5940253', '-071.5247212'], internalPointGeo: ['+41.5964850', '-071.5264901'])]
    case RI = 'RI';

    #[StateInfo(fullName: 'South Carolina', apAbbreviation: 'S.C.', type: 'state', fipsCode: '45', iso3166_2: 'US-SC', baseName: 'South Carolina', geoId: '45', nationalStandardCode: '01779799', division: '5', region: '3', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '27490140244538', legalStatisticalAreaDescriptionCode: '00', areaLand: '77865991519', areaWater: '5074452542', arcGisObjectIdentifier: '40', centroidGeo: ['+33.8758222', '-080.8583129'], internalPointGeo: ['+33.8741776', '-080.8542639'])]
    case SC = 'SC';

    #[StateInfo(fullName: 'South Dakota', apAbbreviation: 'S.D.', type: 'state', fipsCode: '46', iso3166_2: 'US-SD', baseName: 'South Dakota', geoId: '46', nationalStandardCode: '01785534', division: '4', region: '2', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '2749092876144', legalStatisticalAreaDescriptionCode: '00', areaLand: '196341986509', areaWater: '3387247814', arcGisObjectIdentifier: '49', centroidGeo: ['+44.4445705', '-100.2270262'], internalPointGeo: ['+44.4467957', '-100.2381762'])]
    case SD = 'SD';

    #[StateInfo(fullName: 'Tennessee', apAbbreviation: 'Tenn.', type: 'state', fipsCode: '47', iso3166_2: 'US-TN', baseName: 'Tennessee', geoId: '47', nationalStandardCode: '01325873', division: '6', region: '3', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '2749051118086', legalStatisticalAreaDescriptionCode: '00', areaLand: '106771660995', areaWater: '2344243062', arcGisObjectIdentifier: '18', centroidGeo: ['+35.8582468', '-086.3491254'], internalPointGeo: ['+35.8584600', '-086.3496339'])]
    case TN = 'TN';

    #[StateInfo(fullName: 'Texas', apAbbreviation: 'Texas', type: 'state', fipsCode: '48', iso3166_2: 'US-TX', baseName: 'Texas', geoId: '48', nationalStandardCode: '01779801', division: '7', region: '3', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '2749098452115', legalStatisticalAreaDescriptionCode: '00', areaLand: '676660157338', areaWater: '19008164905', arcGisObjectIdentifier: '25', centroidGeo: ['+31.4276892', '-099.2890539'], internalPointGeo: ['+31.4347032', '-099.2818238'])]
    case TX = 'TX';

    #[StateInfo(fullName: 'Utah', apAbbreviation: 'Utah', type: 'state', fipsCode: '49', iso3166_2: 'US-UT', baseName: 'Utah', geoId: '49', nationalStandardCode: '01455989', division: '8', region: '4', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '27490187824724', legalStatisticalAreaDescriptionCode: '00', areaLand: '213921693698', areaWater: '5963352640', arcGisObjectIdentifier: '15', centroidGeo: ['+39.3063102', '-111.6703458'], internalPointGeo: ['+39.3349925', '-111.6563326'])]
    case UT = 'UT';

    #[StateInfo(fullName: 'Virginia', apAbbreviation: 'Va.', type: 'state', fipsCode: '51', iso3166_2: 'US-VA', baseName: 'Virginia', geoId: '51', nationalStandardCode: '01779803', division: '5', region: '3', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '2749099610787', legalStatisticalAreaDescriptionCode: '00', areaLand: '102256455003', areaWater: '8529795937', arcGisObjectIdentifier: '21', centroidGeo: ['+37.5182631', '-078.6759174'], internalPointGeo: ['+37.5222512', '-078.6681938'])]
    case VA = 'VA';

    #[StateInfo(fullName: 'United States Virgin Islands', apAbbreviation: 'U.S. Virgin Islands', type: 'territory', fipsCode: '78', iso3166_2: 'US-VI', baseName: 'United States Virgin Islands', geoId: '78', nationalStandardCode: '01802710', division: '0', region: '9', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '27490228303490', legalStatisticalAreaDescriptionCode: '00', areaLand: '348021915', areaWater: '1550236186', arcGisObjectIdentifier: '29', centroidGeo: ['+18.0593788', '-064.8387617'], internalPointGeo: ['+18.3392359', '-064.9500433'])]
    case VI = 'VI';

    #[StateInfo(fullName: 'Vermont', apAbbreviation: 'Vt.', type: 'state', fipsCode: '50', iso3166_2: 'US-VT', baseName: 'Vermont', geoId: '50', nationalStandardCode: '01779802', division: '1', region: '1', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '27490227024193', legalStatisticalAreaDescriptionCode: '00', areaLand: '23872664356', areaWater: '1030573104', arcGisObjectIdentifier: '5', centroidGeo: ['+44.0693363', '-072.6660619'], internalPointGeo: ['+44.0589536', '-072.6710173'])]
    case VT = 'VT';

    #[StateInfo(fullName: 'Washington', apAbbreviation: 'Wash.', type: 'state', fipsCode: '53', iso3166_2: 'US-WA', baseName: 'Washington', geoId: '53', nationalStandardCode: '01779804', division: '9', region: '4', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '27490225755057', legalStatisticalAreaDescriptionCode: '00', areaLand: '172121688057', areaWater: '12545987222', arcGisObjectIdentifier: '52', centroidGeo: ['+47.4148774', '-120.5954939'], internalPointGeo: ['+47.4073238', '-120.5757999'])]
    case WA = 'WA';

    #[StateInfo(fullName: 'Wisconsin', apAbbreviation: 'Wis.', type: 'state', fipsCode: '55', iso3166_2: 'US-WI', baseName: 'Wisconsin', geoId: '55', nationalStandardCode: '01779806', division: '3', region: '2', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '2749096104710', legalStatisticalAreaDescriptionCode: '00', areaLand: '140295112685', areaWater: '29340578971', arcGisObjectIdentifier: '10', centroidGeo: ['+44.6273136', '-089.7097854'], internalPointGeo: ['+44.6309071', '-089.7093916'])]
    case WI = 'WI';

    #[StateInfo(fullName: 'West Virginia', apAbbreviation: 'W.Va.', type: 'state', fipsCode: '54', iso3166_2: 'US-WV', baseName: 'West Virginia', geoId: '54', nationalStandardCode: '01779805', division: '5', region: '3', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '27490156252396', legalStatisticalAreaDescriptionCode: '00', areaLand: '62266655298', areaWater: '488773392', arcGisObjectIdentifier: '30', centroidGeo: ['+38.6411869', '-080.6234537'], internalPointGeo: ['+38.6472854', '-080.6183274'])]
    case WV = 'WV';

    #[StateInfo(fullName: 'Wyoming', apAbbreviation: 'Wyo.', type: 'state', fipsCode: '56', iso3166_2: 'US-WY', baseName: 'Wyoming', geoId: '56', nationalStandardCode: '01779807', division: '8', region: '4', functionalStatusCode: 'A', mafTigerFeatureClassCode: 'G4000', mafTigerObjectIdentifier: '27490227778497', legalStatisticalAreaDescriptionCode: '00', areaLand: '251458188895', areaWater: '1868027102', arcGisObjectIdentifier: '19', centroidGeo: ['+42.9960999', '-107.5514568'], internalPointGeo: ['+42.9896591', '-107.5443922'])]
    case WY = 'WY';

    /**
     * Lazily initializes and returns the state data map.
     *
     * @return array<string, array{fullName: string, apAbbreviation: string, type: string, fipsCode: string, iso3166_2: string}>
     */
    private static function stateData(): array
    {
        // Create a closure to extract commonly used attributes from StateInfo
        $dataExtractor = fn ($info, $caseValue) => [
            'fullName' => $info->fullName,
            'apAbbreviation' => $info->apAbbreviation,
            'type' => $info->type,
            'fipsCode' => $info->fipsCode,
            'iso3166_2' => $info->iso3166_2,
        ];

        // Build the lazy data map using EnumDataUtils to cache state data
        $result = EnumDataUtils::buildLazyDataMap(
            self::class,
            StateInfo::class,
            $dataExtractor
        );

        // Clean up variables to free up memory
        unset(
            $dataExtractor
        );

        // Return the cached data map for use by getter methods
        return $result;
    }

    /**
     * Get the full name of the state.
     *
     * @return string The full name
     */
    public function fullName(): string
    {
        // Resolve from cached state data
        $data = self::stateData();

        // Throw if state is not found
        return EnumDataUtils::getFieldValue(
            $data,
            $this->value,
            'fullName',
            'state'
        );
    }

    /**
     * Get the AP style abbreviation of the state.
     *
     * @return string The AP abbreviation
     */
    public function apAbbreviation(): string
    {
        // Resolve from cached state data
        $data = self::stateData();

        // Throw if state is not found
        return EnumDataUtils::getFieldValue(
            $data,
            $this->value,
            'apAbbreviation',
            'state'
        );
    }

    /**
     * Get the type of the state (state, territory, or district).
     *
     * @return string The type
     */
    public function type(): string
    {
        // Resolve from cached state data
        $data = self::stateData();

        // Throw if state is not found
        return EnumDataUtils::getFieldValue(
            $data,
            $this->value,
            'type',
            'state'
        );
    }

    /**
     * Get the FIPS state code.
     *
     * @return string The FIPS state code (two-digit string)
     */
    public function fipsCode(): string
    {
        // Resolve from cached state data
        $data = self::stateData();

        // Throw if state is not found
        return EnumDataUtils::getFieldValue(
            $data,
            $this->value,
            'fipsCode',
            'state'
        );
    }

    /**
     * Get the ISO 3166-2 subdivision code.
     *
     * @return string The ISO 3166-2 code (e.g., 'US-CA' for California)
     */
    public function iso3166_2(): string
    {
        // Resolve from cached state data
        $data = self::stateData();

        // Throw if state is not found
        return EnumDataUtils::getFieldValue(
            $data,
            $this->value,
            'iso3166_2',
            'state'
        );
    }

    /**
     * Get the postal code of the state.
     *
     * @return string The postal code
     */
    public function postal(): string
    {
        // Return underlying enum case value
        return $this->value;
    }
}
