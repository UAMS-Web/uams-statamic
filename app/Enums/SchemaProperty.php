<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Attributes\SchemaPropertyInfo;

/**
 * Schema.org properties from schema.org JSON-LD vocabulary.
 *
 * Each case carries metadata via the SchemaPropertyInfo attribute (rdfs_comment,
 * schema_domain_includes, range_includes). Descriptions are type-agnostic.
 *
 * range_includes: declared schema:rangeIncludes plus OWL-implied Role, Text, and URL when the
 * property is not limited to datatype-only primitives (Boolean, Date, DateTime, Number, Float,
 * Integer, Time)—see app/Services/EnumGeneration/Schema/schema-jsonld-normalization.md and
 * schema.org software/util/sdoowl.py DEFAULTRANGES.
 *
 * Generated from schema.org on 2026-05-20 using
 * app/Services/EnumGeneration/Schema/SchemaTypeEnumGenerator.php.
 *
 * @see https://schema.org/docs/developers.html
 * @see https://schema.org/docs/datamodel.html
 */
enum SchemaProperty: string
{
    #[SchemaPropertyInfo(rdfs_comment: 'The subject matter of an object.', schema_domain_includes: ['Certification', 'CommunicateAction', 'CreativeWork', 'DefinedTerm', 'DefinedTermSet', 'Event'], range_includes: ['Role', 'Text', 'Thing', 'URL'], superseded_by: null)]
    case about = 'about';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates whether the book is an abridged edition.', schema_domain_includes: ['Book'], range_includes: ['Boolean'], superseded_by: null)]
    case abridged = 'abridged';
    #[SchemaPropertyInfo(rdfs_comment: 'An abstract is a short description that summarizes a <a href="https://schema.org/CreativeWork" target="_blank" rel="noopener noreferrer">CreativeWork</a>.', schema_domain_includes: ['CreativeWork'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case Propertyabstract = 'abstract';
    #[SchemaPropertyInfo(rdfs_comment: 'The time needed to accelerate the vehicle from a given start velocity to a given target velocity.\\n\\nTypical unit code(s): SEC for seconds\\n\\n* Note: There are unfortunately no standard unit codes for seconds/0..100 km/h or seconds/0..60 mph. Simply use "SEC" for seconds and indicate the velocities in the <a href="https://schema.org/name" target="_blank" rel="noopener noreferrer">name</a> of the <a href="https://schema.org/QuantitativeValue" target="_blank" rel="noopener noreferrer">QuantitativeValue</a>, or use <a href="https://schema.org/valueReference" target="_blank" rel="noopener noreferrer">valueReference</a> with a <a href="https://schema.org/QuantitativeValue" target="_blank" rel="noopener noreferrer">QuantitativeValue</a> of 0..60 mph or 0..100 km/h to specify the reference speeds.', schema_domain_includes: ['Vehicle'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case accelerationTime = 'accelerationTime';
    #[SchemaPropertyInfo(rdfs_comment: 'The answer(s) that has been accepted as best, typically on a Question/Answer site. Sites vary in their selection mechanisms, e.g. drawing on community opinion and/or the view of the Question author.', schema_domain_includes: ['Question'], range_includes: ['Answer', 'ItemList', 'Role', 'Text', 'URL'], superseded_by: null)]
    case acceptedAnswer = 'acceptedAnswer';
    #[SchemaPropertyInfo(rdfs_comment: 'The offer(s) -- e.g., product, quantity and price combinations -- included in the order.', schema_domain_includes: ['Order'], range_includes: ['Offer', 'Role', 'Text', 'URL'], superseded_by: null)]
    case acceptedOffer = 'acceptedOffer';
    #[SchemaPropertyInfo(rdfs_comment: 'The payment method(s) that are accepted in general by an organization, or for some specific demand or offer.', schema_domain_includes: ['Demand', 'Offer', 'Organization'], range_includes: ['LoanOrCredit', 'PaymentMethod', 'Role', 'Text', 'URL'], superseded_by: null)]
    case acceptedPaymentMethod = 'acceptedPaymentMethod';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates whether a FoodEstablishment accepts reservations. Values can be Boolean, an URL at which reservations can be made or (for backwards compatibility) the strings ```Yes``` or ```No```.', schema_domain_includes: ['FoodEstablishment'], range_includes: ['Boolean', 'Role', 'Text', 'URL'], superseded_by: null)]
    case acceptsReservations = 'acceptsReservations';
    #[SchemaPropertyInfo(rdfs_comment: 'Password, PIN, or access code needed for delivery (e.g. from a locker).', schema_domain_includes: ['DeliveryEvent'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case accessCode = 'accessCode';
    #[SchemaPropertyInfo(rdfs_comment: 'The human sensory perceptual system or cognitive faculty through which a person may process or perceive the intellectual content of a resource, not including any adaptations of the content (e.g., text alternatives for images). Values should be drawn from the <a href="https://www.w3.org/2021/a11y-discov-vocab/latest/#accessMode-vocabulary" target="_blank" rel="noopener noreferrer">approved vocabulary</a>.', schema_domain_includes: ['CreativeWork'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case accessMode = 'accessMode';
    #[SchemaPropertyInfo(rdfs_comment: 'A list of single or combined access modes that are sufficient to understand all the intellectual content of a resource, including any adaptations. Values should be drawn from the <a href="https://www.w3.org/2021/a11y-discov-vocab/latest/#accessModeSufficient-vocabulary" target="_blank" rel="noopener noreferrer">approved vocabulary</a>.', schema_domain_includes: ['CreativeWork'], range_includes: ['ItemList', 'Role', 'Text', 'URL'], superseded_by: null)]
    case accessModeSufficient = 'accessModeSufficient';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates that the resource is compatible with the referenced accessibility API. Values should be drawn from the <a href="https://www.w3.org/2021/a11y-discov-vocab/latest/#accessibilityAPI-vocabulary" target="_blank" rel="noopener noreferrer">approved vocabulary</a>.', schema_domain_includes: ['CreativeWork'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case accessibilityAPI = 'accessibilityAPI';
    #[SchemaPropertyInfo(rdfs_comment: 'Identifies input methods that are sufficient to fully control the described resource. Values should be drawn from the <a href="https://www.w3.org/2021/a11y-discov-vocab/latest/#accessibilityControl-vocabulary" target="_blank" rel="noopener noreferrer">approved vocabulary</a>.', schema_domain_includes: ['CreativeWork'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case accessibilityControl = 'accessibilityControl';
    #[SchemaPropertyInfo(rdfs_comment: 'Content features of the resource, such as accessible media, alternatives and supported enhancements for accessibility. Values should be drawn from the <a href="https://www.w3.org/2021/a11y-discov-vocab/latest/#accessibilityFeature-vocabulary" target="_blank" rel="noopener noreferrer">approved vocabulary</a>.', schema_domain_includes: ['CreativeWork'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case accessibilityFeature = 'accessibilityFeature';
    #[SchemaPropertyInfo(rdfs_comment: 'A characteristic of the described resource that is physiologically dangerous to some users. Related to WCAG 2.0 guideline 2.3. Values should be drawn from the <a href="https://www.w3.org/2021/a11y-discov-vocab/latest/#accessibilityHazard-vocabulary" target="_blank" rel="noopener noreferrer">approved vocabulary</a>.', schema_domain_includes: ['CreativeWork'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case accessibilityHazard = 'accessibilityHazard';
    #[SchemaPropertyInfo(rdfs_comment: 'A human-readable summary of specific accessibility features or deficiencies, consistent with the other accessibility metadata but expressing subtleties such as "short descriptions are present but long descriptions will be needed for non-visual users" or "short descriptions are present and no long descriptions are needed".', schema_domain_includes: ['CreativeWork'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case accessibilitySummary = 'accessibilitySummary';
    #[SchemaPropertyInfo(rdfs_comment: 'Category of an <a href="https://schema.org/Accommodation" target="_blank" rel="noopener noreferrer">Accommodation</a>, following real estate conventions, e.g. RESO (see <a href="https://ddwiki.reso.org/display/DDW17/PropertySubType+Field" target="_blank" rel="noopener noreferrer">PropertySubType</a>, and <a href="https://ddwiki.reso.org/display/DDW17/PropertyType+Field" target="_blank" rel="noopener noreferrer">PropertyType</a> fields  for suggested values).', schema_domain_includes: ['Accommodation'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case accommodationCategory = 'accommodationCategory';
    #[SchemaPropertyInfo(rdfs_comment: 'A floorplan of some <a href="https://schema.org/Accommodation" target="_blank" rel="noopener noreferrer">Accommodation</a>.', schema_domain_includes: ['Accommodation', 'Residence'], range_includes: ['FloorPlan', 'Role', 'Text', 'URL'], superseded_by: null)]
    case accommodationFloorPlan = 'accommodationFloorPlan';
    #[SchemaPropertyInfo(rdfs_comment: 'The identifier for the account the payment will be applied to.', schema_domain_includes: ['Invoice'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case accountId = 'accountId';
    #[SchemaPropertyInfo(rdfs_comment: 'A minimum amount that has to be paid in every month.', schema_domain_includes: ['BankAccount'], range_includes: ['MonetaryAmount', 'Role', 'Text', 'URL'], superseded_by: null)]
    case accountMinimumInflow = 'accountMinimumInflow';
    #[SchemaPropertyInfo(rdfs_comment: 'An overdraft is an extension of credit from a lending institution when an account reaches zero. An overdraft allows the individual to continue withdrawing money even if the account has no funds in it. Basically the bank allows people to borrow a set amount of money.', schema_domain_includes: ['BankAccount'], range_includes: ['MonetaryAmount', 'Role', 'Text', 'URL'], superseded_by: null)]
    case accountOverdraftLimit = 'accountOverdraftLimit';
    #[SchemaPropertyInfo(rdfs_comment: 'Specifies the Person that is legally accountable for the CreativeWork.', schema_domain_includes: ['CreativeWork'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case accountablePerson = 'accountablePerson';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates a page documenting how licenses can be purchased or otherwise acquired, for the current item.', schema_domain_includes: ['CreativeWork'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case acquireLicensePage = 'acquireLicensePage';
    #[SchemaPropertyInfo(rdfs_comment: 'The organization or person from which the product was acquired.', schema_domain_includes: ['OwnershipInfo'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case acquiredFrom = 'acquiredFrom';
    #[SchemaPropertyInfo(rdfs_comment: 'The ACRISS Car Classification Code is a code used by many car rental companies, for classifying vehicles. ACRISS stands for Association of Car Rental Industry Systems and Standards.', schema_domain_includes: ['BusOrCoach', 'Car'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case acrissCode = 'acrissCode';
    #[SchemaPropertyInfo(rdfs_comment: 'A set of requirements that must be fulfilled in order to perform an Action. If more than one value is specified, fulfilling one set of requirements will allow the Action to be performed.', schema_domain_includes: ['ConsumeAction'], range_includes: ['ActionAccessSpecification', 'Role', 'Text', 'URL'], superseded_by: null)]
    case actionAccessibilityRequirement = 'actionAccessibilityRequirement';
    #[SchemaPropertyInfo(rdfs_comment: 'An application that can complete the request.', schema_domain_includes: ['EntryPoint'], range_includes: ['Role', 'SoftwareApplication', 'Text', 'URL'], superseded_by: null)]
    case actionApplication = 'actionApplication';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of object. The options subject to this action.', schema_domain_includes: ['ChooseAction'], range_includes: ['Role', 'Text', 'Thing', 'URL'], superseded_by: null)]
    case actionOption = 'actionOption';
    #[SchemaPropertyInfo(rdfs_comment: 'The high level platform(s) where the Action can be performed for the given URL. To specify a specific application or operating system instance, use actionApplication.', schema_domain_includes: ['EntryPoint'], range_includes: ['DigitalPlatformEnumeration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case actionPlatform = 'actionPlatform';
    #[SchemaPropertyInfo(rdfs_comment: 'Description of the process by which the action was performed.', schema_domain_includes: ['Action'], range_includes: ['HowTo', 'Role', 'Text', 'URL'], superseded_by: null)]
    case actionProcess = 'actionProcess';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates the current disposition of the Action.', schema_domain_includes: ['Action'], range_includes: ['ActionStatusType', 'Role', 'Text', 'URL'], superseded_by: null)]
    case actionStatus = 'actionStatus';
    #[SchemaPropertyInfo(rdfs_comment: 'For a <a href="https://schema.org/NewsMediaOrganization" target="_blank" rel="noopener noreferrer">NewsMediaOrganization</a> or other news-related <a href="https://schema.org/Organization" target="_blank" rel="noopener noreferrer">Organization</a>, a statement about public engagement activities (for news media, the newsroom’s), including involving the public - digitally or otherwise -- in coverage decisions, reporting and activities after publication.', schema_domain_includes: ['NewsMediaOrganization', 'Organization'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case actionableFeedbackPolicy = 'actionableFeedbackPolicy';
    #[SchemaPropertyInfo(rdfs_comment: 'An active ingredient, typically chemical compounds and/or biologic substances.', schema_domain_includes: ['DietarySupplement', 'Drug', 'DrugStrength', 'Substance'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case activeIngredient = 'activeIngredient';
    #[SchemaPropertyInfo(rdfs_comment: 'Length of time to engage in the activity.', schema_domain_includes: ['ExercisePlan'], range_includes: ['Duration', 'QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case activityDuration = 'activityDuration';
    #[SchemaPropertyInfo(rdfs_comment: 'How often one should engage in the activity.', schema_domain_includes: ['ExercisePlan'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case activityFrequency = 'activityFrequency';
    #[SchemaPropertyInfo(rdfs_comment: 'An actor (individual or a group), e.g. in TV, radio, movie, video games etc., or in an event. Actors can be associated with individual items or with a series, episode, clip.', schema_domain_includes: ['Clip', 'CreativeWorkSeason', 'Episode', 'Event', 'Movie', 'MovieSeries', 'PodcastSeries', 'RadioSeries', 'TVSeries', 'VideoGame', 'VideoGameSeries', 'VideoObject'], range_includes: ['PerformingGroup', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case actor = 'actor';
    #[SchemaPropertyInfo(rdfs_comment: 'An actor, e.g. in TV, radio, movie, video games etc. Actors can be associated with individual items or with a series, episode, clip.', schema_domain_includes: ['Clip', 'Episode', 'Movie', 'MovieSeries', 'RadioSeries', 'TVSeries', 'VideoGame', 'VideoGameSeries', 'VideoObject'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: 'actor')]
    case actors = 'actors';
    #[SchemaPropertyInfo(rdfs_comment: 'An additional offer that can only be obtained in combination with the first base offer (e.g. supplements and extensions that are available for a surcharge).', schema_domain_includes: ['Offer'], range_includes: ['Offer', 'Role', 'Text', 'URL'], superseded_by: null)]
    case addOn = 'addOn';
    #[SchemaPropertyInfo(rdfs_comment: 'An additional name for a Person, can be used for a middle name.', schema_domain_includes: ['Person'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case additionalName = 'additionalName';
    #[SchemaPropertyInfo(rdfs_comment: 'If responding yes, the number of guests who will attend in addition to the invitee.', schema_domain_includes: ['RsvpAction'], range_includes: ['Number'], superseded_by: null)]
    case additionalNumberOfGuests = 'additionalNumberOfGuests';
    #[SchemaPropertyInfo(rdfs_comment: 'A property-value pair representing an additional characteristic of the entity, e.g. a product feature or another characteristic for which there is no matching property in schema.org.\\n\\nNote: Publishers should be aware that applications designed to use specific schema.org properties (e.g. https://schema.org/width, https://schema.org/color, https://schema.org/gtin13, ...) will typically expect such data to be provided using those properties, rather than using the generic property/value mechanism.', schema_domain_includes: ['MerchantReturnPolicy', 'Offer', 'Place', 'Product', 'QualitativeValue', 'QuantitativeValue'], range_includes: ['PropertyValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case additionalProperty = 'additionalProperty';
    #[SchemaPropertyInfo(rdfs_comment: 'An additional type for the item, typically used for adding more specific types from external vocabularies in microdata syntax. This is a relationship between something and a class that the thing is in. Typically the value is a URI-identified RDF class, and in this case corresponds to the
    use of rdf:type in RDF. Text values can be used sparingly, for cases where useful information can be added without their being an appropriate schema to reference. In the case of text values, the class label should follow the schema.org <a href="https://schema.org/docs/styleguide.html">style guide</a>.', schema_domain_includes: ['Thing'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case additionalType = 'additionalType';
    #[SchemaPropertyInfo(rdfs_comment: 'Any additional component of the exercise prescription that may need to be articulated to the patient. This may include the order of exercises, the number of repetitions of movement, quantitative distance, progressions over time, etc.', schema_domain_includes: ['ExercisePlan'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case additionalVariable = 'additionalVariable';
    #[SchemaPropertyInfo(rdfs_comment: 'Physical address of the item.', schema_domain_includes: ['GeoCoordinates', 'GeoShape', 'Organization', 'Person', 'Place'], range_includes: ['PostalAddress', 'Role', 'Text', 'URL'], superseded_by: null)]
    case address = 'address';
    #[SchemaPropertyInfo(rdfs_comment: 'The country. Recommended to be in 2-letter <a href="http://en.wikipedia.org/wiki/ISO_3166-1" target="_blank" rel="noopener noreferrer">ISO 3166-1 alpha-2</a> format, for example "US". For backward compatibility, a 3-letter <a href="https://en.wikipedia.org/wiki/ISO_3166-1_alpha-3" target="_blank" rel="noopener noreferrer">ISO 3166-1 alpha-3</a> country code such as "SGP" or a full country name such as "Singapore" can also be used.', schema_domain_includes: ['DefinedRegion', 'GeoCoordinates', 'GeoShape', 'PostalAddress'], range_includes: ['Country', 'Role', 'Text', 'URL'], superseded_by: null)]
    case addressCountry = 'addressCountry';
    #[SchemaPropertyInfo(rdfs_comment: 'The locality in which the street address is, and which is in the region. For example, Mountain View.', schema_domain_includes: ['PostalAddress'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case addressLocality = 'addressLocality';
    #[SchemaPropertyInfo(rdfs_comment: 'The region in which the locality is, and which is in the country. For example, California or another appropriate first-level <a href="https://en.wikipedia.org/wiki/List_of_administrative_divisions_by_country" target="_blank" rel="noopener noreferrer">Administrative division</a> such as the Province in Italy or Region in Germany.', schema_domain_includes: ['DefinedRegion', 'PostalAddress'], range_includes: ['AdministrativeArea', 'Role', 'Text', 'URL'], superseded_by: null)]
    case addressRegion = 'addressRegion';
    #[SchemaPropertyInfo(rdfs_comment: 'A route by which this drug may be administered, e.g. \'oral\'.', schema_domain_includes: ['Drug'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case administrationRoute = 'administrationRoute';
    #[SchemaPropertyInfo(rdfs_comment: 'The amount of time that is required between accepting the offer and the actual usage of the resource or service.', schema_domain_includes: ['Demand', 'Offer'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case advanceBookingRequirement = 'advanceBookingRequirement';
    #[SchemaPropertyInfo(rdfs_comment: 'A possible complication and/or side effect of this therapy. If it is known that an adverse outcome is serious (resulting in death, disability, or permanent damage; requiring hospitalization; or otherwise life-threatening or requiring immediate medical attention), tag it as a seriousAdverseOutcome instead.', schema_domain_includes: ['MedicalDevice', 'TherapeuticProcedure'], range_includes: ['MedicalEntity', 'Role', 'Text', 'URL'], superseded_by: null)]
    case adverseOutcome = 'adverseOutcome';
    #[SchemaPropertyInfo(rdfs_comment: 'Drugs that affect the test\'s results.', schema_domain_includes: ['MedicalTest'], range_includes: ['Drug', 'Role', 'Text', 'URL'], superseded_by: null)]
    case affectedBy = 'affectedBy';
    #[SchemaPropertyInfo(rdfs_comment: 'An organization that this person is affiliated with. For example, a school/university, a club, or a team.', schema_domain_includes: ['Person', 'Role'], range_includes: ['Organization', 'Role', 'Text', 'URL'], superseded_by: null)]
    case affiliation = 'affiliation';
    #[SchemaPropertyInfo(rdfs_comment: 'A media object representing the circumstances after performing this direction.', schema_domain_includes: ['HowToDirection'], range_includes: ['MediaObject', 'Role', 'Text', 'URL'], superseded_by: null)]
    case afterMedia = 'afterMedia';
    #[SchemaPropertyInfo(rdfs_comment: 'The direct performer or driver of the action (animate or inanimate). E.g. *John* wrote a book.', schema_domain_includes: ['Action'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case agent = 'agent';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of completed interactions for this entity, in a particular role (the \'agent\'), in a particular action (indicated in the statistic), and in a particular context (i.e. interactionService).', schema_domain_includes: ['Organization', 'Person'], range_includes: ['InteractionCounter', 'Role', 'Text', 'URL'], superseded_by: null)]
    case agentInteractionStatistic = 'agentInteractionStatistic';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates a prototype of the elements in the list that is used to hold aggregate information (ratings, offers, etc.).', schema_domain_includes: ['ItemList'], range_includes: ['Role', 'Text', 'Thing', 'URL'], superseded_by: null)]
    case aggregateElement = 'aggregateElement';
    #[SchemaPropertyInfo(rdfs_comment: 'The overall rating, based on a collection of reviews or ratings, of the item.', schema_domain_includes: ['Brand', 'CreativeWork', 'Event', 'Offer', 'Organization', 'Place', 'Product', 'Service'], range_includes: ['AggregateRating', 'Role', 'Text', 'URL'], superseded_by: null)]
    case aggregateRating = 'aggregateRating';
    #[SchemaPropertyInfo(rdfs_comment: 'The kind of aircraft (e.g., "Boeing 747").', schema_domain_includes: ['Flight'], range_includes: ['Role', 'Text', 'URL', 'Vehicle'], superseded_by: null)]
    case aircraft = 'aircraft';
    #[SchemaPropertyInfo(rdfs_comment: 'A music album.', schema_domain_includes: ['MusicGroup'], range_includes: ['MusicAlbum', 'Role', 'Text', 'URL'], superseded_by: null)]
    case album = 'album';
    #[SchemaPropertyInfo(rdfs_comment: 'Classification of the album by its type of content: soundtrack, live album, studio album, etc.', schema_domain_includes: ['MusicAlbum'], range_includes: ['MusicAlbumProductionType', 'Role', 'Text', 'URL'], superseded_by: null)]
    case albumProductionType = 'albumProductionType';
    #[SchemaPropertyInfo(rdfs_comment: 'A release of this album.', schema_domain_includes: ['MusicAlbum'], range_includes: ['MusicRelease', 'Role', 'Text', 'URL'], superseded_by: null)]
    case albumRelease = 'albumRelease';
    #[SchemaPropertyInfo(rdfs_comment: 'The kind of release which this album is: single, EP or album.', schema_domain_includes: ['MusicAlbum'], range_includes: ['MusicAlbumReleaseType', 'Role', 'Text', 'URL'], superseded_by: null)]
    case albumReleaseType = 'albumReleaseType';
    #[SchemaPropertyInfo(rdfs_comment: 'A collection of music albums.', schema_domain_includes: ['MusicGroup'], range_includes: ['MusicAlbum', 'Role', 'Text', 'URL'], superseded_by: 'album')]
    case albums = 'albums';
    #[SchemaPropertyInfo(rdfs_comment: 'Any precaution, guidance, contraindication, etc. related to consumption of alcohol while taking this drug.', schema_domain_includes: ['Drug'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case alcoholWarning = 'alcoholWarning';
    #[SchemaPropertyInfo(rdfs_comment: 'The algorithm or rules to follow to compute the score.', schema_domain_includes: ['MedicalRiskScore'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case algorithm = 'algorithm';
    #[SchemaPropertyInfo(rdfs_comment: 'A category of alignment between the learning resource and the framework node. Recommended values include: \'requires\', \'textComplexity\', \'readingLevel\', and \'educationalSubject\'.', schema_domain_includes: ['AlignmentObject'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case alignmentType = 'alignmentType';
    #[SchemaPropertyInfo(rdfs_comment: 'An alias for the item.', schema_domain_includes: ['Thing'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case alternateName = 'alternateName';
    #[SchemaPropertyInfo(rdfs_comment: 'A secondary title of the CreativeWork.', schema_domain_includes: ['CreativeWork'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case alternativeHeadline = 'alternativeHeadline';
    #[SchemaPropertyInfo(rdfs_comment: 'Another gene which is a variation of this one.', schema_domain_includes: ['Gene'], range_includes: ['Gene', 'Role', 'Text', 'URL'], superseded_by: null)]
    case alternativeOf = 'alternativeOf';
    #[SchemaPropertyInfo(rdfs_comment: 'Alumni of an organization.', schema_domain_includes: ['EducationalOrganization', 'Organization'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case alumni = 'alumni';
    #[SchemaPropertyInfo(rdfs_comment: 'An organization that the person is an alumni of.', schema_domain_includes: ['Person', 'Role'], range_includes: ['EducationalOrganization', 'Organization', 'Role', 'Text', 'URL'], superseded_by: null)]
    case alumniOf = 'alumniOf';
    #[SchemaPropertyInfo(rdfs_comment: 'An amenity feature (e.g. a characteristic or service) of the Accommodation. This generic property does not make a statement about whether the feature is included in an offer for the main accommodation or available at extra costs.', schema_domain_includes: ['Accommodation', 'FloorPlan', 'LodgingBusiness', 'Place'], range_includes: ['LocationFeatureSpecification', 'Role', 'Text', 'URL'], superseded_by: null)]
    case amenityFeature = 'amenityFeature';
    #[SchemaPropertyInfo(rdfs_comment: 'The amount of money.', schema_domain_includes: ['DatedMoneySpecification', 'InvestmentOrDeposit', 'LoanOrCredit', 'MonetaryGrant', 'MoneyTransfer'], range_includes: ['MonetaryAmount', 'Number', 'Role', 'Text', 'URL'], superseded_by: null)]
    case amount = 'amount';
    #[SchemaPropertyInfo(rdfs_comment: 'The quantity of the goods included in the offer.', schema_domain_includes: ['TypeAndQuantityNode'], range_includes: ['Number'], superseded_by: null)]
    case amountOfThisGood = 'amountOfThisGood';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates a specific <a href="https://schema.org/CivicStructure" target="_blank" rel="noopener noreferrer">CivicStructure</a> or <a href="https://schema.org/LocalBusiness" target="_blank" rel="noopener noreferrer">LocalBusiness</a> associated with the SpecialAnnouncement. For example, a specific testing facility or business with special opening hours. For a larger geographic region like a quarantine of an entire region, use <a href="https://schema.org/spatialCoverage" target="_blank" rel="noopener noreferrer">spatialCoverage</a>.', schema_domain_includes: ['SpecialAnnouncement'], range_includes: ['CivicStructure', 'LocalBusiness', 'Role', 'Text', 'URL'], superseded_by: null)]
    case announcementLocation = 'announcementLocation';
    #[SchemaPropertyInfo(rdfs_comment: 'The annual rate that is charged for borrowing (or made by investing), expressed as a single percentage number that represents the actual yearly cost of funds over the term of a loan. This includes any fees or additional costs associated with the transaction.', schema_domain_includes: ['FinancialProduct'], range_includes: ['Number', 'QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case annualPercentageRate = 'annualPercentageRate';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of answers this question has received.', schema_domain_includes: ['Question'], range_includes: ['Integer'], superseded_by: null)]
    case answerCount = 'answerCount';
    #[SchemaPropertyInfo(rdfs_comment: 'A step-by-step or full explanation about Answer. Can outline how this Answer was achieved or contain more broad clarification or statement about it.', schema_domain_includes: ['Answer'], range_includes: ['Comment', 'Role', 'Text', 'URL', 'WebContent'], superseded_by: null)]
    case answerExplanation = 'answerExplanation';
    #[SchemaPropertyInfo(rdfs_comment: 'The muscle whose action counteracts the specified muscle.', schema_domain_includes: ['Muscle'], range_includes: ['Muscle', 'Role', 'Text', 'URL'], superseded_by: null)]
    case antagonist = 'antagonist';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates an occurrence of a <a href="https://schema.org/Claim" target="_blank" rel="noopener noreferrer">Claim</a> in some <a href="https://schema.org/CreativeWork" target="_blank" rel="noopener noreferrer">CreativeWork</a>.', schema_domain_includes: ['Claim'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case appearance = 'appearance';
    #[SchemaPropertyInfo(rdfs_comment: 'A country where a particular merchant return policy applies to, for example the two-letter ISO 3166-1 alpha-2 country code.', schema_domain_includes: ['MerchantReturnPolicy'], range_includes: ['Country', 'Role', 'Text', 'URL'], superseded_by: null)]
    case applicableCountry = 'applicableCountry';
    #[SchemaPropertyInfo(rdfs_comment: 'The location in which the status applies.', schema_domain_includes: ['DrugCost', 'DrugLegalStatus'], range_includes: ['AdministrativeArea', 'Role', 'Text', 'URL'], superseded_by: null)]
    case applicableLocation = 'applicableLocation';
    #[SchemaPropertyInfo(rdfs_comment: 'The location(s) applicants can apply from. This is usually used for telecommuting jobs where the applicant does not need to be in a physical office. Note: This should not be used for citizenship or work visa requirements.', schema_domain_includes: ['JobPosting'], range_includes: ['AdministrativeArea', 'Role', 'Text', 'URL'], superseded_by: null)]
    case applicantLocationRequirements = 'applicantLocationRequirements';
    #[SchemaPropertyInfo(rdfs_comment: 'An application that can complete the request.', schema_domain_includes: ['EntryPoint'], range_includes: ['Role', 'SoftwareApplication', 'Text', 'URL'], superseded_by: 'actionApplication')]
    case application = 'application';
    #[SchemaPropertyInfo(rdfs_comment: 'Type of software application, e.g. \'Game, Multimedia\'.', schema_domain_includes: ['SoftwareApplication'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case applicationCategory = 'applicationCategory';
    #[SchemaPropertyInfo(rdfs_comment: 'Contact details for further information relevant to this job posting.', schema_domain_includes: ['JobPosting'], range_includes: ['ContactPoint', 'Role', 'Text', 'URL'], superseded_by: null)]
    case applicationContact = 'applicationContact';
    #[SchemaPropertyInfo(rdfs_comment: 'The date on which the program stops collecting applications for the next enrollment cycle. Flexible application deadlines (for example, a program with rolling admissions) can be described in a textual string, rather than as a DateTime.', schema_domain_includes: ['EducationalOccupationalProgram'], range_includes: ['Date', 'Role', 'Text', 'URL'], superseded_by: null)]
    case applicationDeadline = 'applicationDeadline';
    #[SchemaPropertyInfo(rdfs_comment: 'The date at which the program begins collecting applications for the next enrollment cycle.', schema_domain_includes: ['EducationalOccupationalProgram'], range_includes: ['Date'], superseded_by: null)]
    case applicationStartDate = 'applicationStartDate';
    #[SchemaPropertyInfo(rdfs_comment: 'Subcategory of the application, e.g. \'Arcade Game\'.', schema_domain_includes: ['SoftwareApplication'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case applicationSubCategory = 'applicationSubCategory';
    #[SchemaPropertyInfo(rdfs_comment: 'The name of the application suite to which the application belongs (e.g. Excel belongs to Office).', schema_domain_includes: ['SoftwareApplication'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case applicationSuite = 'applicationSuite';
    #[SchemaPropertyInfo(rdfs_comment: 'The delivery method(s) to which the delivery charge or payment charge specification applies.', schema_domain_includes: ['DeliveryChargeSpecification', 'PaymentChargeSpecification'], range_includes: ['DeliveryMethod', 'Role', 'Text', 'URL'], superseded_by: null)]
    case appliesToDeliveryMethod = 'appliesToDeliveryMethod';
    #[SchemaPropertyInfo(rdfs_comment: 'The payment method(s) to which the payment charge specification applies.', schema_domain_includes: ['PaymentChargeSpecification'], range_includes: ['PaymentMethod', 'Role', 'Text', 'URL'], superseded_by: null)]
    case appliesToPaymentMethod = 'appliesToPaymentMethod';
    #[SchemaPropertyInfo(rdfs_comment: 'en', schema_domain_includes: ['ArchiveOrganization'], range_includes: ['ArchiveComponent', 'Role', 'Text', 'URL'], superseded_by: null)]
    case archiveHeld = 'archiveHeld';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates a page or other link involved in archival of a <a href="https://schema.org/CreativeWork" target="_blank" rel="noopener noreferrer">CreativeWork</a>. In the case of <a href="https://schema.org/MediaReview" target="_blank" rel="noopener noreferrer">MediaReview</a>, the items in a <a href="https://schema.org/MediaReviewItem" target="_blank" rel="noopener noreferrer">MediaReviewItem</a> may often become inaccessible, but be archived by archival, journalistic, activist, or law enforcement organizations. In such cases, the referenced page may not directly publish the content.', schema_domain_includes: ['CreativeWork'], range_includes: ['Role', 'Text', 'URL', 'WebPage'], superseded_by: null)]
    case archivedAt = 'archivedAt';
    #[SchemaPropertyInfo(rdfs_comment: 'The area within which users can expect to reach the broadcast service.', schema_domain_includes: ['BroadcastService'], range_includes: ['Place', 'Role', 'Text', 'URL'], superseded_by: 'serviceArea')]
    case area = 'area';
    #[SchemaPropertyInfo(rdfs_comment: 'The geographic area where a service or offered item is provided.', schema_domain_includes: ['ContactPoint', 'DeliveryChargeSpecification', 'Demand', 'FinancialIncentive', 'Offer', 'Organization', 'Service'], range_includes: ['AdministrativeArea', 'GeoShape', 'Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case areaServed = 'areaServed';
    #[SchemaPropertyInfo(rdfs_comment: 'The airport where the flight terminates.', schema_domain_includes: ['Flight'], range_includes: ['Airport', 'Role', 'Text', 'URL'], superseded_by: null)]
    case arrivalAirport = 'arrivalAirport';
    #[SchemaPropertyInfo(rdfs_comment: 'The terminal or port from which the boat arrives.', schema_domain_includes: ['BoatTrip'], range_includes: ['BoatTerminal', 'Role', 'Text', 'URL'], superseded_by: null)]
    case arrivalBoatTerminal = 'arrivalBoatTerminal';
    #[SchemaPropertyInfo(rdfs_comment: 'The stop or station from which the bus arrives.', schema_domain_includes: ['BusTrip'], range_includes: ['BusStation', 'BusStop', 'Role', 'Text', 'URL'], superseded_by: null)]
    case arrivalBusStop = 'arrivalBusStop';
    #[SchemaPropertyInfo(rdfs_comment: 'Identifier of the flight\'s arrival gate.', schema_domain_includes: ['Flight'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case arrivalGate = 'arrivalGate';
    #[SchemaPropertyInfo(rdfs_comment: 'The platform where the train arrives.', schema_domain_includes: ['TrainTrip'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case arrivalPlatform = 'arrivalPlatform';
    #[SchemaPropertyInfo(rdfs_comment: 'The station where the train trip ends.', schema_domain_includes: ['TrainTrip'], range_includes: ['Role', 'Text', 'TrainStation', 'URL'], superseded_by: null)]
    case arrivalStation = 'arrivalStation';
    #[SchemaPropertyInfo(rdfs_comment: 'Identifier of the flight\'s arrival terminal.', schema_domain_includes: ['Flight'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case arrivalTerminal = 'arrivalTerminal';
    #[SchemaPropertyInfo(rdfs_comment: 'The expected arrival time.', schema_domain_includes: ['Trip'], range_includes: ['DateTime', 'Time'], superseded_by: null)]
    case arrivalTime = 'arrivalTime';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of copies when multiple copies of a piece of artwork are produced - e.g. for a limited edition of 20 prints, \'artEdition\' refers to the total number of copies (in this example "20").', schema_domain_includes: ['VisualArtwork'], range_includes: ['Integer', 'Role', 'Text', 'URL'], superseded_by: null)]
    case artEdition = 'artEdition';
    #[SchemaPropertyInfo(rdfs_comment: 'The material used. (E.g. Oil, Watercolour, Acrylic, Linoprint, Marble, Cyanotype, Digital, Lithograph, DryPoint, Intaglio, Pastel, Woodcut, Pencil, Mixed Media, etc.)', schema_domain_includes: ['VisualArtwork'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case artMedium = 'artMedium';
    #[SchemaPropertyInfo(rdfs_comment: 'The branches that comprise the arterial structure.', schema_domain_includes: ['Artery'], range_includes: ['AnatomicalStructure', 'Role', 'Text', 'URL'], superseded_by: null)]
    case arterialBranch = 'arterialBranch';
    #[SchemaPropertyInfo(rdfs_comment: 'e.g. Painting, Drawing, Sculpture, Print, Photograph, Assemblage, Collage, etc.', schema_domain_includes: ['VisualArtwork'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case artform = 'artform';
    #[SchemaPropertyInfo(rdfs_comment: 'The actual body of the article.', schema_domain_includes: ['Article'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case articleBody = 'articleBody';
    #[SchemaPropertyInfo(rdfs_comment: 'Articles may belong to one or more \'sections\' in a magazine or newspaper, such as Sports, Lifestyle, etc.', schema_domain_includes: ['Article'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case articleSection = 'articleSection';
    #[SchemaPropertyInfo(rdfs_comment: 'The primary artist for a work
    	in a medium other than pencils or digital line art--for example, if the
    	primary artwork is done in watercolors or digital paints.', schema_domain_includes: ['ComicIssue', 'ComicStory', 'VisualArtwork'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case artist = 'artist';
    #[SchemaPropertyInfo(rdfs_comment: 'The supporting materials for the artwork, e.g. Canvas, Paper, Wood, Board, etc.', schema_domain_includes: ['VisualArtwork'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case artworkSurface = 'artworkSurface';
    #[SchemaPropertyInfo(rdfs_comment: 'An Amazon Standard Identification Number (ASIN) is a 10-character alphanumeric unique identifier assigned by Amazon.com and its partners for product identification within the Amazon organization (summary from <a href="https://en.wikipedia.org/wiki/Amazon_Standard_Identification_Number" target="_blank" rel="noopener noreferrer">Wikipedia</a>\'s article).

Note also that this is a definition for how to include ASINs in Schema.org data, and not a definition of ASINs in general - see documentation from Amazon for authoritative details.
ASINs are most commonly encoded as text strings, but the [asin] property supports URL/URI as potential values too.', schema_domain_includes: ['Demand', 'Offer', 'Product'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case asin = 'asin';
    #[SchemaPropertyInfo(rdfs_comment: 'An aspect of medical practice that is considered on the page, such as \'diagnosis\', \'treatment\', \'causes\', \'prognosis\', \'etiology\', \'epidemiology\', etc.', schema_domain_includes: ['MedicalWebPage'], range_includes: ['Role', 'Text', 'URL'], superseded_by: 'mainContentOfPage')]
    case aspect = 'aspect';
    #[SchemaPropertyInfo(rdfs_comment: 'Library file name, e.g., mscorlib.dll, system.web.dll.', schema_domain_includes: ['APIReference'], range_includes: ['Role', 'Text', 'URL'], superseded_by: 'executableLibraryName')]
    case assembly = 'assembly';
    #[SchemaPropertyInfo(rdfs_comment: 'Associated product/technology version. E.g., .NET Framework 4.5.', schema_domain_includes: ['APIReference'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case assemblyVersion = 'assemblyVersion';
    #[SchemaPropertyInfo(rdfs_comment: 'The item being described is intended to assess the competency or learning outcome defined by the referenced term.', schema_domain_includes: ['CreativeWork', 'EducationEvent', 'LearningResource'], range_includes: ['DefinedTerm', 'Role', 'Text', 'URL'], superseded_by: null)]
    case assesses = 'assesses';
    #[SchemaPropertyInfo(rdfs_comment: 'The anatomy of the underlying organ system or structures associated with this entity.', schema_domain_includes: ['MedicalCondition', 'PhysicalActivity'], range_includes: ['AnatomicalStructure', 'AnatomicalSystem', 'Role', 'SuperficialAnatomy', 'Text', 'URL'], superseded_by: null)]
    case associatedAnatomy = 'associatedAnatomy';
    #[SchemaPropertyInfo(rdfs_comment: 'A NewsArticle associated with the Media Object.', schema_domain_includes: ['MediaObject'], range_includes: ['NewsArticle', 'Role', 'Text', 'URL'], superseded_by: null)]
    case associatedArticle = 'associatedArticle';
    #[SchemaPropertyInfo(rdfs_comment: 'An associated <a href="https://schema.org/ClaimReview" target="_blank" rel="noopener noreferrer">ClaimReview</a>, related by specific common content, topic or claim. The expectation is that this property would be most typically used in cases where a single activity is conducting both claim reviews and media reviews, in which case <a href="https://schema.org/relatedMediaReview" target="_blank" rel="noopener noreferrer">relatedMediaReview</a> would commonly be used on a <a href="https://schema.org/ClaimReview" target="_blank" rel="noopener noreferrer">ClaimReview</a>, while <a href="https://schema.org/associatedClaimReview" target="_blank" rel="noopener noreferrer">associatedClaimReview</a> would be used on <a href="https://schema.org/MediaReview" target="_blank" rel="noopener noreferrer">MediaReview</a>.', schema_domain_includes: ['Review'], range_includes: ['Review', 'Role', 'Text', 'URL'], superseded_by: null)]
    case associatedClaimReview = 'associatedClaimReview';
    #[SchemaPropertyInfo(rdfs_comment: 'Disease associated to this BioChemEntity. Such disease can be a MedicalCondition or a URL. If you want to add an evidence supporting the association, please use PropertyValue.', schema_domain_includes: ['BioChemEntity'], range_includes: ['MedicalCondition', 'PropertyValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case associatedDisease = 'associatedDisease';
    #[SchemaPropertyInfo(rdfs_comment: 'A media object that encodes this CreativeWork. This property is a synonym for encoding.', schema_domain_includes: ['CreativeWork', 'HyperToc', 'HyperTocEntry'], range_includes: ['MediaObject', 'Role', 'Text', 'URL'], superseded_by: null)]
    case associatedMedia = 'associatedMedia';
    #[SchemaPropertyInfo(rdfs_comment: 'An associated <a href="https://schema.org/MediaReview" target="_blank" rel="noopener noreferrer">MediaReview</a>, related by specific common content, topic or claim. The expectation is that this property would be most typically used in cases where a single activity is conducting both claim reviews and media reviews, in which case <a href="https://schema.org/relatedMediaReview" target="_blank" rel="noopener noreferrer">relatedMediaReview</a> would commonly be used on a <a href="https://schema.org/ClaimReview" target="_blank" rel="noopener noreferrer">ClaimReview</a>, while <a href="https://schema.org/associatedClaimReview" target="_blank" rel="noopener noreferrer">associatedClaimReview</a> would be used on <a href="https://schema.org/MediaReview" target="_blank" rel="noopener noreferrer">MediaReview</a>.', schema_domain_includes: ['Review'], range_includes: ['Review', 'Role', 'Text', 'URL'], superseded_by: null)]
    case associatedMediaReview = 'associatedMediaReview';
    #[SchemaPropertyInfo(rdfs_comment: 'If applicable, a description of the pathophysiology associated with the anatomical system, including potential abnormal changes in the mechanical, physical, and biochemical functions of the system.', schema_domain_includes: ['AnatomicalStructure', 'AnatomicalSystem', 'SuperficialAnatomy'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case associatedPathophysiology = 'associatedPathophysiology';
    #[SchemaPropertyInfo(rdfs_comment: 'An associated <a href="https://schema.org/Review" target="_blank" rel="noopener noreferrer">Review</a>.', schema_domain_includes: ['Review'], range_includes: ['Review', 'Role', 'Text', 'URL'], superseded_by: null)]
    case associatedReview = 'associatedReview';
    #[SchemaPropertyInfo(rdfs_comment: 'A person that acts as performing member of a sports team; a player as opposed to a coach.', schema_domain_includes: ['SportsTeam'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case athlete = 'athlete';
    #[SchemaPropertyInfo(rdfs_comment: 'A person or organization attending the event.', schema_domain_includes: ['Event', 'Role'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case attendee = 'attendee';
    #[SchemaPropertyInfo(rdfs_comment: 'A person attending the event.', schema_domain_includes: ['Event', 'Role'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: 'attendee')]
    case attendees = 'attendees';
    #[SchemaPropertyInfo(rdfs_comment: 'An intended audience, i.e. a group for whom something was created.', schema_domain_includes: ['CreativeWork', 'Event', 'LodgingBusiness', 'PlayAction', 'Product', 'Service'], range_includes: ['Audience', 'Role', 'Text', 'URL'], superseded_by: null)]
    case audience = 'audience';
    #[SchemaPropertyInfo(rdfs_comment: 'The target group associated with a given audience (e.g. veterans, car owners, musicians, etc.).', schema_domain_includes: ['Audience'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case audienceType = 'audienceType';
    #[SchemaPropertyInfo(rdfs_comment: 'An embedded audio object.', schema_domain_includes: ['CreativeWork'], range_includes: ['AudioObject', 'Clip', 'MusicRecording', 'Role', 'Text', 'URL'], superseded_by: null)]
    case audio = 'audio';
    #[SchemaPropertyInfo(rdfs_comment: 'Date when a certification was last audited. See also  <a href="https://www.gs1.org/voc/certificationAuditDate" target="_blank" rel="noopener noreferrer">gs1:certificationAuditDate</a>.', schema_domain_includes: ['Certification'], range_includes: ['Date', 'DateTime'], superseded_by: null)]
    case auditDate = 'auditDate';
    #[SchemaPropertyInfo(rdfs_comment: 'The Organization responsible for authenticating the user\'s subscription. For example, many media apps require a cable/satellite provider to authenticate your subscription before playing media.', schema_domain_includes: ['MediaSubscription'], range_includes: ['Organization', 'Role', 'Text', 'URL'], superseded_by: null)]
    case authenticator = 'authenticator';
    #[SchemaPropertyInfo(rdfs_comment: 'The author of this content or rating. Please note that author is special in that HTML 5 provides a special mechanism for indicating authorship via the rel tag. That is equivalent to this and may be used interchangeably.', schema_domain_includes: ['CreativeWork', 'Rating', 'Role'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case author = 'author';
    #[SchemaPropertyInfo(rdfs_comment: 'The availability of this item—for example In stock, Out of stock, Pre-order, etc.', schema_domain_includes: ['Demand', 'Offer'], range_includes: ['ItemAvailability', 'Role', 'Text', 'URL'], superseded_by: null)]
    case availability = 'availability';
    #[SchemaPropertyInfo(rdfs_comment: 'The end of the availability of the product or service included in the offer.', schema_domain_includes: ['ActionAccessSpecification', 'Demand', 'Offer'], range_includes: ['Date', 'DateTime', 'Time'], superseded_by: null)]
    case availabilityEnds = 'availabilityEnds';
    #[SchemaPropertyInfo(rdfs_comment: 'The beginning of the availability of the product or service included in the offer.', schema_domain_includes: ['ActionAccessSpecification', 'Demand', 'Offer'], range_includes: ['Date', 'DateTime', 'Time'], superseded_by: null)]
    case availabilityStarts = 'availabilityStarts';
    #[SchemaPropertyInfo(rdfs_comment: 'The place(s) from which the offer can be obtained (e.g. store locations).', schema_domain_includes: ['Demand', 'Offer'], range_includes: ['Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case availableAtOrFrom = 'availableAtOrFrom';
    #[SchemaPropertyInfo(rdfs_comment: 'A means of accessing the service (e.g. a phone bank, a web site, a location, etc.).', schema_domain_includes: ['Service'], range_includes: ['Role', 'ServiceChannel', 'Text', 'URL'], superseded_by: null)]
    case availableChannel = 'availableChannel';
    #[SchemaPropertyInfo(rdfs_comment: 'The delivery method(s) available for this offer.', schema_domain_includes: ['Demand', 'Offer'], range_includes: ['DeliveryMethod', 'Role', 'Text', 'URL'], superseded_by: null)]
    case availableDeliveryMethod = 'availableDeliveryMethod';
    #[SchemaPropertyInfo(rdfs_comment: 'When the item is available for pickup from the store, locker, etc.', schema_domain_includes: ['DeliveryEvent'], range_includes: ['DateTime'], superseded_by: null)]
    case availableFrom = 'availableFrom';
    #[SchemaPropertyInfo(rdfs_comment: 'The location in which the strength is available.', schema_domain_includes: ['DrugStrength'], range_includes: ['AdministrativeArea', 'Role', 'Text', 'URL'], superseded_by: null)]
    case availableIn = 'availableIn';
    #[SchemaPropertyInfo(rdfs_comment: 'A language someone may use with or at the item, service or place. Please use one of the language codes from the <a href="http://tools.ietf.org/html/bcp47" target="_blank" rel="noopener noreferrer">IETF BCP 47 standard</a>. See also <a href="https://schema.org/inLanguage" target="_blank" rel="noopener noreferrer">inLanguage</a>.', schema_domain_includes: ['ContactPoint', 'Course', 'LodgingBusiness', 'ServiceChannel', 'TouristAttraction'], range_includes: ['Language', 'Role', 'Text', 'URL'], superseded_by: null)]
    case availableLanguage = 'availableLanguage';
    #[SchemaPropertyInfo(rdfs_comment: 'Device required to run the application. Used in cases where a specific make/model is required to run the application.', schema_domain_includes: ['SoftwareApplication'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case availableOnDevice = 'availableOnDevice';
    #[SchemaPropertyInfo(rdfs_comment: 'A medical service available from this provider.', schema_domain_includes: ['Hospital', 'MedicalClinic', 'Physician'], range_includes: ['MedicalProcedure', 'MedicalTest', 'MedicalTherapy', 'Role', 'Text', 'URL'], superseded_by: null)]
    case availableService = 'availableService';
    #[SchemaPropertyInfo(rdfs_comment: 'An available dosage strength for the drug.', schema_domain_includes: ['Drug'], range_includes: ['DrugStrength', 'Role', 'Text', 'URL'], superseded_by: null)]
    case availableStrength = 'availableStrength';
    #[SchemaPropertyInfo(rdfs_comment: 'A diagnostic test or procedure offered by this lab.', schema_domain_includes: ['DiagnosticLab'], range_includes: ['MedicalTest', 'Role', 'Text', 'URL'], superseded_by: null)]
    case availableTest = 'availableTest';
    #[SchemaPropertyInfo(rdfs_comment: 'After this date, the item will no longer be available for pickup.', schema_domain_includes: ['DeliveryEvent'], range_includes: ['DateTime'], superseded_by: null)]
    case availableThrough = 'availableThrough';
    #[SchemaPropertyInfo(rdfs_comment: 'An award won by or for this item.', schema_domain_includes: ['CreativeWork', 'Organization', 'Person', 'Product', 'Service'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case award = 'award';
    #[SchemaPropertyInfo(rdfs_comment: 'Awards won by or for this item.', schema_domain_includes: ['CreativeWork', 'Organization', 'Person', 'Product'], range_includes: ['Role', 'Text', 'URL'], superseded_by: 'award')]
    case awards = 'awards';
    #[SchemaPropertyInfo(rdfs_comment: 'The away team in a sports event.', schema_domain_includes: ['SportsEvent'], range_includes: ['Person', 'Role', 'SportsTeam', 'Text', 'URL'], superseded_by: null)]
    case awayTeam = 'awayTeam';
    #[SchemaPropertyInfo(rdfs_comment: 'For an <a href="https://schema.org/Article" target="_blank" rel="noopener noreferrer">Article</a>, typically a <a href="https://schema.org/NewsArticle" target="_blank" rel="noopener noreferrer">NewsArticle</a>, the backstory property provides a textual summary giving a brief explanation of why and how an article was created. In a journalistic setting this could include information about reporting process, methods, interviews, data sources, etc.', schema_domain_includes: ['Article'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case backstory = 'backstory';
    #[SchemaPropertyInfo(rdfs_comment: 'The type of a bank account.', schema_domain_includes: ['BankAccount'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case bankAccountType = 'bankAccountType';
    #[SchemaPropertyInfo(rdfs_comment: 'The base salary of the job or of an employee in an EmployeeRole.', schema_domain_includes: ['EmployeeRole', 'JobPosting'], range_includes: ['MonetaryAmount', 'Number', 'PriceSpecification', 'Role', 'Text', 'URL'], superseded_by: null)]
    case baseSalary = 'baseSalary';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of recipient. The recipient blind copied on a message.', schema_domain_includes: ['Message'], range_includes: ['ContactPoint', 'Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case bccRecipient = 'bccRecipient';
    #[SchemaPropertyInfo(rdfs_comment: 'The type of bed or beds included in the accommodation. For the single case of just one bed of a certain type, you use bed directly with a text.
      If you want to indicate the quantity of a certain kind of bed, use an instance of BedDetails. For more detailed information, use the amenityFeature property.', schema_domain_includes: ['Accommodation', 'HotelRoom', 'Suite'], range_includes: ['BedDetails', 'BedType', 'Role', 'Text', 'URL'], superseded_by: null)]
    case bed = 'bed';
    #[SchemaPropertyInfo(rdfs_comment: 'A media object representing the circumstances before performing this direction.', schema_domain_includes: ['HowToDirection'], range_includes: ['MediaObject', 'Role', 'Text', 'URL'], superseded_by: null)]
    case beforeMedia = 'beforeMedia';
    #[SchemaPropertyInfo(rdfs_comment: 'A bank or bank’s branch, financial institution or international financial institution operating the beneficiary’s bank account or releasing funds for the beneficiary.', schema_domain_includes: ['MoneyTransfer'], range_includes: ['BankOrCreditUnion', 'Role', 'Text', 'URL'], superseded_by: null)]
    case beneficiaryBank = 'beneficiaryBank';
    #[SchemaPropertyInfo(rdfs_comment: 'Description of benefits associated with the job.', schema_domain_includes: ['JobPosting'], range_includes: ['Role', 'Text', 'URL'], superseded_by: 'jobBenefits')]
    case benefits = 'benefits';
    #[SchemaPropertyInfo(rdfs_comment: 'The URL that goes directly to the summary of benefits and coverage for the specific standard plan or plan variation.', schema_domain_includes: ['HealthInsurancePlan'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case benefitsSummaryUrl = 'benefitsSummaryUrl';
    #[SchemaPropertyInfo(rdfs_comment: 'The highest value allowed in this rating system.', schema_domain_includes: ['Rating'], range_includes: ['Number', 'Role', 'Text', 'URL'], superseded_by: null)]
    case bestRating = 'bestRating';
    #[SchemaPropertyInfo(rdfs_comment: 'The billing address for the order.', schema_domain_includes: ['Order'], range_includes: ['PostalAddress', 'Role', 'Text', 'URL'], superseded_by: null)]
    case billingAddress = 'billingAddress';
    #[SchemaPropertyInfo(rdfs_comment: 'Specifies for how long this price (or price component) will be billed. Can be used, for example, to model the contractual duration of a subscription or payment plan. Type can be either a Duration or a Number (in which case the unit of measurement, for example month, is specified by the unitCode property).', schema_domain_includes: ['UnitPriceSpecification'], range_includes: ['Duration', 'Number', 'QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case billingDuration = 'billingDuration';
    #[SchemaPropertyInfo(rdfs_comment: 'This property specifies the minimal quantity and rounding increment that will be the basis for the billing. The unit of measurement is specified by the unitCode property.', schema_domain_includes: ['UnitPriceSpecification'], range_includes: ['Number'], superseded_by: null)]
    case billingIncrement = 'billingIncrement';
    #[SchemaPropertyInfo(rdfs_comment: 'The time interval used to compute the invoice.', schema_domain_includes: ['Invoice'], range_includes: ['Duration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case billingPeriod = 'billingPeriod';
    #[SchemaPropertyInfo(rdfs_comment: 'Specifies after how much time this price (or price component) becomes valid and billing starts. Can be used, for example, to model a price increase after the first year of a subscription. The unit of measurement is specified by the unitCode property.', schema_domain_includes: ['UnitPriceSpecification'], range_includes: ['Number'], superseded_by: null)]
    case billingStart = 'billingStart';
    #[SchemaPropertyInfo(rdfs_comment: 'A BioChemEntity that is known to interact with this item.', schema_domain_includes: ['BioChemEntity'], range_includes: ['BioChemEntity', 'Role', 'Text', 'URL'], superseded_by: null)]
    case bioChemInteraction = 'bioChemInteraction';
    #[SchemaPropertyInfo(rdfs_comment: 'A similar BioChemEntity, e.g., obtained by fingerprint similarity algorithms.', schema_domain_includes: ['BioChemEntity'], range_includes: ['BioChemEntity', 'Role', 'Text', 'URL'], superseded_by: null)]
    case bioChemSimilarity = 'bioChemSimilarity';
    #[SchemaPropertyInfo(rdfs_comment: 'A role played by the BioChemEntity within a biological context.', schema_domain_includes: ['BioChemEntity'], range_includes: ['DefinedTerm', 'Role', 'Text', 'URL'], superseded_by: null)]
    case biologicalRole = 'biologicalRole';
    #[SchemaPropertyInfo(rdfs_comment: 'The biomechanical properties of the bone.', schema_domain_includes: ['Joint'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case biomechnicalClass = 'biomechnicalClass';
    #[SchemaPropertyInfo(rdfs_comment: 'Date of birth.', schema_domain_includes: ['Person'], range_includes: ['Date'], superseded_by: null)]
    case birthDate = 'birthDate';
    #[SchemaPropertyInfo(rdfs_comment: 'The place where the person was born.', schema_domain_includes: ['Person'], range_includes: ['Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case birthPlace = 'birthPlace';
    #[SchemaPropertyInfo(rdfs_comment: 'The bitrate of the media object.', schema_domain_includes: ['MediaObject'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case bitrate = 'bitrate';
    #[SchemaPropertyInfo(rdfs_comment: 'A posting that is part of this blog.', schema_domain_includes: ['Blog'], range_includes: ['BlogPosting', 'Role', 'Text', 'URL'], superseded_by: null)]
    case blogPost = 'blogPost';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates a post that is part of a <a href="https://schema.org/Blog" target="_blank" rel="noopener noreferrer">Blog</a>. Note that historically, what we term a "Blog" was once known as a "weblog", and that what we term a "BlogPosting" is now often colloquially referred to as a "blog".', schema_domain_includes: ['Blog'], range_includes: ['BlogPosting', 'Role', 'Text', 'URL'], superseded_by: 'blogPost')]
    case blogPosts = 'blogPosts';
    #[SchemaPropertyInfo(rdfs_comment: 'The blood vessel that carries blood from the heart to the muscle.', schema_domain_includes: ['Muscle'], range_includes: ['Role', 'Text', 'URL', 'Vessel'], superseded_by: null)]
    case bloodSupply = 'bloodSupply';
    #[SchemaPropertyInfo(rdfs_comment: 'The airline-specific indicator of boarding order / preference.', schema_domain_includes: ['FlightReservation'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case boardingGroup = 'boardingGroup';
    #[SchemaPropertyInfo(rdfs_comment: 'The type of boarding policy used by the airline (e.g. zone-based or group-based).', schema_domain_includes: ['Airline', 'Flight'], range_includes: ['BoardingPolicyType', 'Role', 'Text', 'URL'], superseded_by: null)]
    case boardingPolicy = 'boardingPolicy';
    #[SchemaPropertyInfo(rdfs_comment: 'Location in the body of the anatomical structure.', schema_domain_includes: ['AnatomicalStructure', 'MedicalProcedure'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case bodyLocation = 'bodyLocation';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates the design and body style of the vehicle (e.g. station wagon, hatchback, etc.).', schema_domain_includes: ['Vehicle'], range_includes: ['QualitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case bodyType = 'bodyType';
    #[SchemaPropertyInfo(rdfs_comment: 'The edition of the book.', schema_domain_includes: ['Book'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case bookEdition = 'bookEdition';
    #[SchemaPropertyInfo(rdfs_comment: 'The format of the book.', schema_domain_includes: ['Book'], range_includes: ['BookFormatType', 'Role', 'Text', 'URL'], superseded_by: null)]
    case bookFormat = 'bookFormat';
    #[SchemaPropertyInfo(rdfs_comment: '\'bookingAgent\' is an out-dated term indicating a \'broker\' that serves as a booking agent.', schema_domain_includes: ['Reservation'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: 'broker')]
    case bookingAgent = 'bookingAgent';
    #[SchemaPropertyInfo(rdfs_comment: 'The date and time the reservation was booked.', schema_domain_includes: ['Reservation'], range_includes: ['DateTime'], superseded_by: null)]
    case bookingTime = 'bookingTime';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of participant. The person that borrows the object being lent.', schema_domain_includes: ['LendAction'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case borrower = 'borrower';
    #[SchemaPropertyInfo(rdfs_comment: 'A box is the area enclosed by the rectangle formed by two points. The first point is the lower corner, the second point is the upper corner. A box is expressed as two points separated by a space character.', schema_domain_includes: ['GeoShape'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case box = 'box';
    #[SchemaPropertyInfo(rdfs_comment: 'The branches that delineate from the nerve bundle. Not to be confused with <a href="https://schema.org/branchOf" target="_blank" rel="noopener noreferrer">branchOf</a>.', schema_domain_includes: ['Nerve'], range_includes: ['AnatomicalStructure', 'Role', 'Text', 'URL'], superseded_by: 'arterialBranch')]
    case branch = 'branch';
    #[SchemaPropertyInfo(rdfs_comment: 'A short textual code (also called "store code") that uniquely identifies a place of business. The code is typically assigned by the parentOrganization and used in structured URLs.\\n\\nFor example, in the URL http://www.starbucks.co.uk/store-locator/etc/detail/3047 the code "3047" is a branchCode for a particular branch.', schema_domain_includes: ['Place'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case branchCode = 'branchCode';
    #[SchemaPropertyInfo(rdfs_comment: 'The larger organization that this local business is a branch of, if any. Not to be confused with (anatomical) <a href="https://schema.org/branch" target="_blank" rel="noopener noreferrer">branch</a>.', schema_domain_includes: ['LocalBusiness'], range_includes: ['Organization', 'Role', 'Text', 'URL'], superseded_by: 'parentOrganization')]
    case branchOf = 'branchOf';
    #[SchemaPropertyInfo(rdfs_comment: 'The brand(s) associated with a product or service, or the brand(s) maintained by an organization or business person.', schema_domain_includes: ['Organization', 'Person', 'Product', 'Service'], range_includes: ['Brand', 'Organization', 'Role', 'Text', 'URL'], superseded_by: null)]
    case brand = 'brand';
    #[SchemaPropertyInfo(rdfs_comment: 'A set of links that can help a user understand and navigate a website hierarchy.', schema_domain_includes: ['WebPage'], range_includes: ['BreadcrumbList', 'Role', 'Text', 'URL'], superseded_by: null)]
    case breadcrumb = 'breadcrumb';
    #[SchemaPropertyInfo(rdfs_comment: 'Any precaution, guidance, contraindication, etc. related to this drug\'s use by breastfeeding mothers.', schema_domain_includes: ['Drug'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case breastfeedingWarning = 'breastfeedingWarning';
    #[SchemaPropertyInfo(rdfs_comment: 'The media network(s) whose content is broadcast on this station.', schema_domain_includes: ['BroadcastService'], range_includes: ['Organization', 'Role', 'Text', 'URL'], superseded_by: null)]
    case broadcastAffiliateOf = 'broadcastAffiliateOf';
    #[SchemaPropertyInfo(rdfs_comment: 'The unique address by which the BroadcastService can be identified in a provider lineup. In US, this is typically a number.', schema_domain_includes: ['BroadcastChannel'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case broadcastChannelId = 'broadcastChannelId';
    #[SchemaPropertyInfo(rdfs_comment: 'The name displayed in the channel guide. For many US affiliates, it is the network name.', schema_domain_includes: ['BroadcastService'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case broadcastDisplayName = 'broadcastDisplayName';
    #[SchemaPropertyInfo(rdfs_comment: 'The frequency used for over-the-air broadcasts. Numeric values or simple ranges, e.g. 87-99. In addition a shortcut idiom is supported for frequencies of AM and FM radio channels, e.g. "87 FM".', schema_domain_includes: ['BroadcastChannel', 'BroadcastService'], range_includes: ['BroadcastFrequencySpecification', 'Role', 'Text', 'URL'], superseded_by: null)]
    case broadcastFrequency = 'broadcastFrequency';
    #[SchemaPropertyInfo(rdfs_comment: 'The frequency in MHz for a particular broadcast.', schema_domain_includes: ['BroadcastFrequencySpecification'], range_includes: ['Number', 'QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case broadcastFrequencyValue = 'broadcastFrequencyValue';
    #[SchemaPropertyInfo(rdfs_comment: 'The event being broadcast such as a sporting event or awards ceremony.', schema_domain_includes: ['BroadcastEvent'], range_includes: ['Event', 'Role', 'Text', 'URL'], superseded_by: null)]
    case broadcastOfEvent = 'broadcastOfEvent';
    #[SchemaPropertyInfo(rdfs_comment: 'The type of service required to have access to the channel (e.g. Standard or Premium).', schema_domain_includes: ['BroadcastChannel'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case broadcastServiceTier = 'broadcastServiceTier';
    #[SchemaPropertyInfo(rdfs_comment: 'The modulation (e.g. FM, AM, etc) used by a particular broadcast service.', schema_domain_includes: ['BroadcastFrequencySpecification'], range_includes: ['QualitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case broadcastSignalModulation = 'broadcastSignalModulation';
    #[SchemaPropertyInfo(rdfs_comment: 'The subchannel used for the broadcast.', schema_domain_includes: ['BroadcastFrequencySpecification'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case broadcastSubChannel = 'broadcastSubChannel';
    #[SchemaPropertyInfo(rdfs_comment: 'The timezone in <a href="http://en.wikipedia.org/wiki/ISO_8601" target="_blank" rel="noopener noreferrer">ISO 8601 format</a> for which the service bases its broadcasts.', schema_domain_includes: ['BroadcastService'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case broadcastTimezone = 'broadcastTimezone';
    #[SchemaPropertyInfo(rdfs_comment: 'The organization owning or operating the broadcast service.', schema_domain_includes: ['BroadcastService'], range_includes: ['Organization', 'Role', 'Text', 'URL'], superseded_by: null)]
    case broadcaster = 'broadcaster';
    #[SchemaPropertyInfo(rdfs_comment: 'An entity that arranges for an exchange between a buyer and a seller.  In most cases a broker never acquires or releases ownership of a product or service involved in an exchange.  If it is not clear whether an entity is a broker, seller, or buyer, the latter two terms are preferred.', schema_domain_includes: ['Invoice', 'Order', 'Reservation', 'Service'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case broker = 'broker';
    #[SchemaPropertyInfo(rdfs_comment: 'Specifies browser requirements in human-readable text. For example, \'requires HTML5 support\'.', schema_domain_includes: ['WebApplication'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case browserRequirements = 'browserRequirements';
    #[SchemaPropertyInfo(rdfs_comment: 'The name of the bus (e.g. Bolt Express).', schema_domain_includes: ['BusTrip'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case busName = 'busName';
    #[SchemaPropertyInfo(rdfs_comment: 'The unique identifier for the bus.', schema_domain_includes: ['BusTrip'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case busNumber = 'busNumber';
    #[SchemaPropertyInfo(rdfs_comment: 'Days of the week when the merchant typically operates, indicated via opening hours markup.', schema_domain_includes: ['ServicePeriod', 'ShippingDeliveryTime'], range_includes: ['DayOfWeek', 'OpeningHoursSpecification', 'Role', 'Text', 'URL'], superseded_by: null)]
    case businessDays = 'businessDays';
    #[SchemaPropertyInfo(rdfs_comment: 'The business function (e.g. sell, lease, repair, dispose) of the offer or component of a bundle (TypeAndQuantityNode). The default is http://purl.org/goodrelations/v1#Sell.', schema_domain_includes: ['Demand', 'Offer', 'TypeAndQuantityNode'], range_includes: ['BusinessFunction', 'Role', 'Text', 'URL'], superseded_by: null)]
    case businessFunction = 'businessFunction';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of participant. The participant/person/organization that bought the object.', schema_domain_includes: ['SellAction'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case buyer = 'buyer';
    #[SchemaPropertyInfo(rdfs_comment: 'The artist that performed this album or recording.', schema_domain_includes: ['MusicAlbum', 'MusicRecording'], range_includes: ['MusicGroup', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case byArtist = 'byArtist';
    #[SchemaPropertyInfo(rdfs_comment: 'Defines the day(s) of the week on which a recurring <a href="https://schema.org/Event" target="_blank" rel="noopener noreferrer">Event</a> takes place. May be specified using either <a href="https://schema.org/DayOfWeek" target="_blank" rel="noopener noreferrer">DayOfWeek</a>, or alternatively <a href="https://schema.org/Text" target="_blank" rel="noopener noreferrer">Text</a> conforming to iCal\'s syntax for byDay recurrence rules.', schema_domain_includes: ['Schedule'], range_includes: ['DayOfWeek', 'Role', 'Text', 'URL'], superseded_by: null)]
    case byDay = 'byDay';
    #[SchemaPropertyInfo(rdfs_comment: 'Defines the month(s) of the year on which a recurring <a href="https://schema.org/Event" target="_blank" rel="noopener noreferrer">Event</a> takes place. Specified as an <a href="https://schema.org/Integer" target="_blank" rel="noopener noreferrer">Integer</a> between 1-12. January is 1.', schema_domain_includes: ['Schedule'], range_includes: ['Integer'], superseded_by: null)]
    case byMonth = 'byMonth';
    #[SchemaPropertyInfo(rdfs_comment: 'Defines the day(s) of the month on which a recurring <a href="https://schema.org/Event" target="_blank" rel="noopener noreferrer">Event</a> takes place. Specified as an <a href="https://schema.org/Integer" target="_blank" rel="noopener noreferrer">Integer</a> between 1-31.', schema_domain_includes: ['Schedule'], range_includes: ['Integer'], superseded_by: null)]
    case byMonthDay = 'byMonthDay';
    #[SchemaPropertyInfo(rdfs_comment: 'Defines the week(s) of the month on which a recurring Event takes place. Specified as an Integer between 1-5. For clarity, byMonthWeek is best used in conjunction with byDay to indicate concepts like the first and third Mondays of a month.', schema_domain_includes: ['Schedule'], range_includes: ['Integer'], superseded_by: null)]
    case byMonthWeek = 'byMonthWeek';
    #[SchemaPropertyInfo(rdfs_comment: 'A <a href="https://en.wikipedia.org/wiki/Call_sign" target="_blank" rel="noopener noreferrer">callsign</a>, as used in broadcasting and radio communications to identify people, radio and TV stations, or vehicles.', schema_domain_includes: ['BroadcastService', 'Person', 'Vehicle'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case callSign = 'callSign';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of calories.', schema_domain_includes: ['NutritionInformation'], range_includes: ['Energy', 'Role', 'Text', 'URL'], superseded_by: null)]
    case calories = 'calories';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of object. The candidate subject of this action.', schema_domain_includes: ['VoteAction'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case candidate = 'candidate';
    #[SchemaPropertyInfo(rdfs_comment: 'The caption for this object. For downloadable machine formats (closed caption, subtitles etc.) use MediaObject and indicate the <a href="https://schema.org/encodingFormat" target="_blank" rel="noopener noreferrer">encodingFormat</a>.', schema_domain_includes: ['AudioObject', 'ImageObject', 'VideoObject'], range_includes: ['MediaObject', 'Role', 'Text', 'URL'], superseded_by: null)]
    case caption = 'caption';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of grams of carbohydrates.', schema_domain_includes: ['NutritionInformation'], range_includes: ['Mass', 'Role', 'Text', 'URL'], superseded_by: null)]
    case carbohydrateContent = 'carbohydrateContent';
    #[SchemaPropertyInfo(rdfs_comment: 'The available volume for cargo or luggage. For automobiles, this is usually the trunk volume.\\n\\nTypical unit code(s): LTR for liters, FTQ for cubic foot/feet\\n\\nNote: You can use <a href="https://schema.org/minValue" target="_blank" rel="noopener noreferrer">minValue</a> and <a href="https://schema.org/maxValue" target="_blank" rel="noopener noreferrer">maxValue</a> to indicate ranges.', schema_domain_includes: ['Vehicle'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case cargoVolume = 'cargoVolume';
    #[SchemaPropertyInfo(rdfs_comment: '\'carrier\' is an out-dated term indicating the \'provider\' for parcel delivery and flights.', schema_domain_includes: ['Flight', 'ParcelDelivery'], range_includes: ['Organization', 'Role', 'Text', 'URL'], superseded_by: 'provider')]
    case carrier = 'carrier';
    #[SchemaPropertyInfo(rdfs_comment: 'Specifies specific carrier(s) requirements for the application (e.g. an application may only work on a specific carrier network).', schema_domain_includes: ['MobileApplication'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case carrierRequirements = 'carrierRequirements';
    #[SchemaPropertyInfo(rdfs_comment: 'A cardholder benefit that pays the cardholder a small percentage of their net expenditures.', schema_domain_includes: ['PaymentCard'], range_includes: ['Boolean', 'Number'], superseded_by: null)]
    case cashBack = 'cashBack';
    #[SchemaPropertyInfo(rdfs_comment: 'A data catalog which contains this dataset.', schema_domain_includes: ['Dataset'], range_includes: ['DataCatalog', 'Role', 'Text', 'URL'], superseded_by: 'includedInDataCatalog')]
    case catalog = 'catalog';
    #[SchemaPropertyInfo(rdfs_comment: 'The catalog number for the release.', schema_domain_includes: ['MusicRelease'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case catalogNumber = 'catalogNumber';
    #[SchemaPropertyInfo(rdfs_comment: 'A category for the item. Greater signs or slashes can be used to informally indicate a category hierarchy.', schema_domain_includes: ['ActionAccessSpecification', 'Guide', 'Invoice', 'Offer', 'PhysicalActivity', 'Product', 'Recommendation', 'Service', 'SpecialAnnouncement'], range_includes: ['CategoryCode', 'PhysicalActivityCategory', 'Role', 'Text', 'Thing', 'URL'], superseded_by: null)]
    case category = 'category';
    #[SchemaPropertyInfo(rdfs_comment: 'The cause of a medical condition.', schema_domain_includes: ['MedicalCondition'], range_includes: ['MedicalCause', 'Role', 'Text', 'URL'], superseded_by: null)]
    case cause = 'cause';
    #[SchemaPropertyInfo(rdfs_comment: 'The condition, complication, symptom, sign, etc. caused.', schema_domain_includes: ['MedicalCause'], range_includes: ['MedicalEntity', 'Role', 'Text', 'URL'], superseded_by: null)]
    case causeOf = 'causeOf';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of recipient. The recipient copied on a message.', schema_domain_includes: ['Message'], range_includes: ['ContactPoint', 'Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case ccRecipient = 'ccRecipient';
    #[SchemaPropertyInfo(rdfs_comment: 'Identifier of a certification instance (as registered with an independent certification body). Typically this identifier can be used to consult and verify the certification instance. See also <a href="https://www.gs1.org/voc/certificationIdentification" target="_blank" rel="noopener noreferrer">gs1:certificationIdentification</a>.', schema_domain_includes: ['Certification'], range_includes: ['DefinedTerm', 'Role', 'Text', 'URL'], superseded_by: null)]
    case certificationIdentification = 'certificationIdentification';
    #[SchemaPropertyInfo(rdfs_comment: 'Rating of a certification instance (as defined by an independent certification body). Typically this rating can be used to rate the level to which the requirements of the certification instance are fulfilled. See also <a href="https://www.gs1.org/voc/certificationValue" target="_blank" rel="noopener noreferrer">gs1:certificationValue</a>.', schema_domain_includes: ['Certification'], range_includes: ['Rating', 'Role', 'Text', 'URL'], superseded_by: null)]
    case certificationRating = 'certificationRating';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates the current status of a certification: active or inactive. See also  <a href="https://www.gs1.org/voc/certificationStatus" target="_blank" rel="noopener noreferrer">gs1:certificationStatus</a>.', schema_domain_includes: ['Certification'], range_includes: ['CertificationStatusEnumeration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case certificationStatus = 'certificationStatus';
    #[SchemaPropertyInfo(rdfs_comment: 'Fictional person connected with a creative work.', schema_domain_includes: ['CreativeWork'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case character = 'character';
    #[SchemaPropertyInfo(rdfs_comment: 'A piece of data that represents a particular aspect of a fictional character (skill, power, character points, advantage, disadvantage).', schema_domain_includes: ['Game', 'VideoGameSeries'], range_includes: ['Role', 'Text', 'Thing', 'URL'], superseded_by: null)]
    case characterAttribute = 'characterAttribute';
    #[SchemaPropertyInfo(rdfs_comment: 'The name of a character played in some acting or performing role, i.e. in a PerformanceRole.', schema_domain_includes: ['PerformanceRole'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case characterName = 'characterName';
    #[SchemaPropertyInfo(rdfs_comment: 'Cheat codes to the game.', schema_domain_includes: ['VideoGame', 'VideoGameSeries'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case cheatCode = 'cheatCode';
    #[SchemaPropertyInfo(rdfs_comment: 'The earliest someone may check into a lodging establishment.', schema_domain_includes: ['LodgingBusiness', 'LodgingReservation'], range_includes: ['DateTime', 'Time'], superseded_by: null)]
    case checkinTime = 'checkinTime';
    #[SchemaPropertyInfo(rdfs_comment: 'A URL template (RFC 6570) for a checkout page for an offer. This approach allows merchants to specify a URL for online checkout of the offered product, by interpolating parameters such as the logged in user ID, product ID, quantity, discount code etc. Parameter naming and standardization are not specified here.', schema_domain_includes: ['Offer'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case checkoutPageURLTemplate = 'checkoutPageURLTemplate';
    #[SchemaPropertyInfo(rdfs_comment: 'The latest someone may check out of a lodging establishment.', schema_domain_includes: ['LodgingBusiness', 'LodgingReservation'], range_includes: ['DateTime', 'Time'], superseded_by: null)]
    case checkoutTime = 'checkoutTime';
    #[SchemaPropertyInfo(rdfs_comment: 'The chemical composition describes the identity and relative ratio of the chemical elements that make up the substance.', schema_domain_includes: ['ChemicalSubstance'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case chemicalComposition = 'chemicalComposition';
    #[SchemaPropertyInfo(rdfs_comment: 'A role played by the BioChemEntity within a chemical context.', schema_domain_includes: ['ChemicalSubstance', 'MolecularEntity'], range_includes: ['DefinedTerm', 'Role', 'Text', 'URL'], superseded_by: null)]
    case chemicalRole = 'chemicalRole';
    #[SchemaPropertyInfo(rdfs_comment: 'Maximal age of the child.', schema_domain_includes: ['ParentAudience'], range_includes: ['Number'], superseded_by: null)]
    case childMaxAge = 'childMaxAge';
    #[SchemaPropertyInfo(rdfs_comment: 'Minimal age of the child.', schema_domain_includes: ['ParentAudience'], range_includes: ['Number'], superseded_by: null)]
    case childMinAge = 'childMinAge';
    #[SchemaPropertyInfo(rdfs_comment: 'Closest child taxa of the taxon in question.', schema_domain_includes: ['Taxon'], range_includes: ['Role', 'Taxon', 'Text', 'URL'], superseded_by: null)]
    case childTaxon = 'childTaxon';
    #[SchemaPropertyInfo(rdfs_comment: 'A child of the person.', schema_domain_includes: ['Person'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case children = 'children';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of milligrams of cholesterol.', schema_domain_includes: ['NutritionInformation'], range_includes: ['Mass', 'Role', 'Text', 'URL'], superseded_by: null)]
    case cholesterolContent = 'cholesterolContent';
    #[SchemaPropertyInfo(rdfs_comment: 'A circle is the circular region of a specified radius centered at a specified latitude and longitude. A circle is expressed as a pair followed by a radius in meters.', schema_domain_includes: ['GeoShape'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case circle = 'circle';
    #[SchemaPropertyInfo(rdfs_comment: 'A citation or reference to another creative work, such as another publication, web page, scholarly article, etc.', schema_domain_includes: ['CreativeWork'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case citation = 'citation';
    #[SchemaPropertyInfo(rdfs_comment: 'For a <a href="https://schema.org/Claim" target="_blank" rel="noopener noreferrer">Claim</a> interpreted from <a href="https://schema.org/MediaObject" target="_blank" rel="noopener noreferrer">MediaObject</a> content, the <a href="https://schema.org/interpretedAsClaim" target="_blank" rel="noopener noreferrer">interpretedAsClaim</a> property can be used to indicate a claim contained, implied or refined from the content of a <a href="https://schema.org/MediaObject" target="_blank" rel="noopener noreferrer">MediaObject</a>.', schema_domain_includes: ['Claim'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case claimInterpreter = 'claimInterpreter';
    #[SchemaPropertyInfo(rdfs_comment: 'A short summary of the specific claims reviewed in a ClaimReview.', schema_domain_includes: ['ClaimReview'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case claimReviewed = 'claimReviewed';
    #[SchemaPropertyInfo(rdfs_comment: 'Description of the absorption and elimination of drugs, including their concentration (pharmacokinetics, pK) and biological effects (pharmacodynamics, pD).', schema_domain_includes: ['Drug'], range_includes: ['Role', 'Text', 'URL'], superseded_by: 'clinicalPharmacology')]
    case clincalPharmacology = 'clincalPharmacology';
    #[SchemaPropertyInfo(rdfs_comment: 'Description of the absorption and elimination of drugs, including their concentration (pharmacokinetics, pK) and biological effects (pharmacodynamics, pD).', schema_domain_includes: ['Drug'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case clinicalPharmacology = 'clinicalPharmacology';
    #[SchemaPropertyInfo(rdfs_comment: 'Position of the clip within an ordered group of clips.', schema_domain_includes: ['Clip'], range_includes: ['Integer', 'Role', 'Text', 'URL'], superseded_by: null)]
    case clipNumber = 'clipNumber';
    #[SchemaPropertyInfo(rdfs_comment: 'The closing hour of the place or service on the given day(s) of the week.', schema_domain_includes: ['OpeningHoursSpecification'], range_includes: ['Time'], superseded_by: null)]
    case closes = 'closes';
    #[SchemaPropertyInfo(rdfs_comment: 'A person that acts in a coaching role for a sports team.', schema_domain_includes: ['SportsTeam'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case coach = 'coach';
    #[SchemaPropertyInfo(rdfs_comment: 'A medical code for the entity, taken from a controlled vocabulary or ontology such as ICD-9, DiseasesDB, MeSH, SNOMED-CT, RxNorm, etc.', schema_domain_includes: ['MedicalEntity'], range_includes: ['MedicalCode', 'Role', 'Text', 'URL'], superseded_by: null)]
    case code = 'code';
    #[SchemaPropertyInfo(rdfs_comment: 'Link to the repository where the un-compiled, human readable code and related code is located (SVN, GitHub, CodePlex).', schema_domain_includes: ['SoftwareSourceCode'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case codeRepository = 'codeRepository';
    #[SchemaPropertyInfo(rdfs_comment: 'What type of code sample: full (compile ready) solution, code snippet, inline code, scripts, template.', schema_domain_includes: ['SoftwareSourceCode'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case codeSampleType = 'codeSampleType';
    #[SchemaPropertyInfo(rdfs_comment: 'A short textual code that uniquely identifies the value.', schema_domain_includes: ['CategoryCode', 'MedicalCode'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case codeValue = 'codeValue';
    #[SchemaPropertyInfo(rdfs_comment: 'The coding system, e.g. \'ICD-10\'.', schema_domain_includes: ['MedicalCode'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case codingSystem = 'codingSystem';
    #[SchemaPropertyInfo(rdfs_comment: 'A colleague of the person.', schema_domain_includes: ['Person'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case colleague = 'colleague';
    #[SchemaPropertyInfo(rdfs_comment: 'A colleague of the person.', schema_domain_includes: ['Person'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: 'colleague')]
    case colleagues = 'colleagues';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of object. The collection target of the action.', schema_domain_includes: ['UpdateAction'], range_includes: ['Role', 'Text', 'Thing', 'URL'], superseded_by: 'targetCollection')]
    case collection = 'collection';
    #[SchemaPropertyInfo(rdfs_comment: 'en', schema_domain_includes: ['Collection'], range_includes: ['Integer'], superseded_by: null)]
    case collectionSize = 'collectionSize';
    #[SchemaPropertyInfo(rdfs_comment: 'The color of the product.', schema_domain_includes: ['Product'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case color = 'color';
    #[SchemaPropertyInfo(rdfs_comment: 'A color swatch image, visualizing the color of a <a href="https://schema.org/Product" target="_blank" rel="noopener noreferrer">Product</a>. Should match the textual description specified in the <a href="https://schema.org/color" target="_blank" rel="noopener noreferrer">color</a> property. This can be a URL or a fully described ImageObject.', schema_domain_includes: ['Product'], range_includes: ['ImageObject', 'Role', 'Text', 'URL'], superseded_by: null)]
    case colorSwatch = 'colorSwatch';
    #[SchemaPropertyInfo(rdfs_comment: 'The individual who adds color to inked drawings.', schema_domain_includes: ['ComicIssue', 'ComicStory', 'VisualArtwork'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case colorist = 'colorist';
    #[SchemaPropertyInfo(rdfs_comment: 'Comments, typically from users.', schema_domain_includes: ['CreativeWork', 'RsvpAction'], range_includes: ['Comment', 'Role', 'Text', 'URL'], superseded_by: null)]
    case comment = 'comment';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of comments this CreativeWork (e.g. Article, Question or Answer) has received. This is most applicable to works published in Web sites with commenting system; additional comments may exist elsewhere.', schema_domain_includes: ['CreativeWork'], range_includes: ['Integer'], superseded_by: null)]
    case commentCount = 'commentCount';
    #[SchemaPropertyInfo(rdfs_comment: 'The text of the UserComment.', schema_domain_includes: ['UserComments'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case commentText = 'commentText';
    #[SchemaPropertyInfo(rdfs_comment: 'The time at which the UserComment was made.', schema_domain_includes: ['UserComments'], range_includes: ['Date', 'DateTime'], superseded_by: null)]
    case commentTime = 'commentTime';
    #[SchemaPropertyInfo(rdfs_comment: 'The official registration information of a business including the organization that issued it such as Company House or Chamber of Commerce in form of a Certification.', schema_domain_includes: ['Organization'], range_includes: ['Certification', 'Role', 'Text', 'URL'], superseded_by: null)]
    case companyRegistration = 'companyRegistration';
    #[SchemaPropertyInfo(rdfs_comment: 'Knowledge, skill, ability or personal attribute that must be demonstrated by a person or other entity in order to do something such as earn an Educational Occupational Credential or understand a LearningResource.', schema_domain_includes: ['EducationalOccupationalCredential', 'LearningResource'], range_includes: ['DefinedTerm', 'Role', 'Text', 'URL'], superseded_by: null)]
    case competencyRequired = 'competencyRequired';
    #[SchemaPropertyInfo(rdfs_comment: 'A competitor in a sports event.', schema_domain_includes: ['SportsEvent'], range_includes: ['Person', 'Role', 'SportsTeam', 'Text', 'URL'], superseded_by: null)]
    case competitor = 'competitor';
    #[SchemaPropertyInfo(rdfs_comment: 'The person or organization who wrote a composition, or who is the composer of a work performed at some event.', schema_domain_includes: ['Event', 'MusicComposition'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case composer = 'composer';
    #[SchemaPropertyInfo(rdfs_comment: 'Specifying something physically contained by something else. Typically used here for the underlying anatomical structures, such as organs, that comprise the anatomical system.', schema_domain_includes: ['AnatomicalSystem'], range_includes: ['AnatomicalStructure', 'AnatomicalSystem', 'Role', 'Text', 'URL'], superseded_by: null)]
    case comprisedOf = 'comprisedOf';
    #[SchemaPropertyInfo(rdfs_comment: 'Conditions that affect the availability of, or method(s) of access to, an item. Typically used for real world items such as an <a href="https://schema.org/ArchiveComponent" target="_blank" rel="noopener noreferrer">ArchiveComponent</a> held by an <a href="https://schema.org/ArchiveOrganization" target="_blank" rel="noopener noreferrer">ArchiveOrganization</a>. This property is not suitable for use as a general Web access control mechanism. It is expressed only in natural language.\\n\\nFor example "Available by appointment from the Reading Room" or "Accessible only from logged-in accounts ".', schema_domain_includes: ['CreativeWork'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case conditionsOfAccess = 'conditionsOfAccess';
    #[SchemaPropertyInfo(rdfs_comment: 'A number that confirms the given order or payment has been received.', schema_domain_includes: ['Invoice', 'Order'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case confirmationNumber = 'confirmationNumber';
    #[SchemaPropertyInfo(rdfs_comment: 'Other anatomical structures to which this structure is connected.', schema_domain_includes: ['AnatomicalStructure'], range_includes: ['AnatomicalStructure', 'Role', 'Text', 'URL'], superseded_by: null)]
    case connectedTo = 'connectedTo';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates a property used as a constraint. For example, in the definition of a <a href="https://schema.org/StatisticalVariable" target="_blank" rel="noopener noreferrer">StatisticalVariable</a>. The value is a property, either from within Schema.org or from other compatible (e.g. RDF) systems such as DataCommons.org or Wikidata.org.', schema_domain_includes: ['ConstraintNode'], range_includes: ['Property', 'Role', 'Text', 'URL'], superseded_by: null)]
    case constraintProperty = 'constraintProperty';
    #[SchemaPropertyInfo(rdfs_comment: 'An option available on this contact point (e.g. a toll-free number or support for hearing-impaired callers).', schema_domain_includes: ['ContactPoint'], range_includes: ['ContactPointOption', 'Role', 'Text', 'URL'], superseded_by: null)]
    case contactOption = 'contactOption';
    #[SchemaPropertyInfo(rdfs_comment: 'A contact point for a person or organization.', schema_domain_includes: ['HealthInsurancePlan', 'Organization', 'Person'], range_includes: ['ContactPoint', 'Role', 'Text', 'URL'], superseded_by: null)]
    case contactPoint = 'contactPoint';
    #[SchemaPropertyInfo(rdfs_comment: 'A contact point for a person or organization.', schema_domain_includes: ['Organization', 'Person'], range_includes: ['ContactPoint', 'Role', 'Text', 'URL'], superseded_by: 'contactPoint')]
    case contactPoints = 'contactPoints';
    #[SchemaPropertyInfo(rdfs_comment: 'A person or organization can have different contact points, for different purposes. For example, a sales contact point, a PR contact point and so on. This property is used to specify the kind of contact point.', schema_domain_includes: ['ContactPoint'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case contactType = 'contactType';
    #[SchemaPropertyInfo(rdfs_comment: 'A secure method for consumers to purchase products or services via debit, credit or smartcards by using RFID or NFC technology.', schema_domain_includes: ['PaymentCard'], range_includes: ['Boolean'], superseded_by: null)]
    case contactlessPayment = 'contactlessPayment';
    #[SchemaPropertyInfo(rdfs_comment: 'The basic containment relation between a place and one that contains it.', schema_domain_includes: ['Place'], range_includes: ['Place', 'Role', 'Text', 'URL'], superseded_by: 'containedInPlace')]
    case containedIn = 'containedIn';
    #[SchemaPropertyInfo(rdfs_comment: 'The basic containment relation between a place and one that contains it.', schema_domain_includes: ['Place'], range_includes: ['Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case containedInPlace = 'containedInPlace';
    #[SchemaPropertyInfo(rdfs_comment: 'The basic containment relation between a place and another that it contains.', schema_domain_includes: ['Place'], range_includes: ['Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case containsPlace = 'containsPlace';
    #[SchemaPropertyInfo(rdfs_comment: 'A season that is part of the media series.', schema_domain_includes: ['RadioSeries', 'TVSeries', 'VideoGameSeries'], range_includes: ['CreativeWorkSeason', 'Role', 'Text', 'URL'], superseded_by: null)]
    case containsSeason = 'containsSeason';
    #[SchemaPropertyInfo(rdfs_comment: 'The location depicted or described in the content. For example, the location in a photograph or painting.', schema_domain_includes: ['CreativeWork'], range_includes: ['Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case contentLocation = 'contentLocation';
    #[SchemaPropertyInfo(rdfs_comment: 'Official rating of a piece of content—for example, \'MPAA PG-13\'.', schema_domain_includes: ['CreativeWork'], range_includes: ['Rating', 'Role', 'Text', 'URL'], superseded_by: null)]
    case contentRating = 'contentRating';
    #[SchemaPropertyInfo(rdfs_comment: 'The specific time described by a creative work, for works (e.g. articles, video objects etc.) that emphasise a particular moment within an Event.', schema_domain_includes: ['CreativeWork'], range_includes: ['DateTime'], superseded_by: null)]
    case contentReferenceTime = 'contentReferenceTime';
    #[SchemaPropertyInfo(rdfs_comment: 'File size in (mega/kilo)bytes.', schema_domain_includes: ['MediaObject'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case contentSize = 'contentSize';
    #[SchemaPropertyInfo(rdfs_comment: 'The supported content type(s) for an EntryPoint response.', schema_domain_includes: ['EntryPoint'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case contentType = 'contentType';
    #[SchemaPropertyInfo(rdfs_comment: 'Actual bytes of the media object, for example the image file or video file.', schema_domain_includes: ['MediaObject'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case contentUrl = 'contentUrl';
    #[SchemaPropertyInfo(rdfs_comment: 'A contraindication for this therapy.', schema_domain_includes: ['MedicalDevice', 'MedicalTherapy'], range_includes: ['MedicalContraindication', 'Role', 'Text', 'URL'], superseded_by: null)]
    case contraindication = 'contraindication';
    #[SchemaPropertyInfo(rdfs_comment: 'A secondary contributor to the CreativeWork or Event.', schema_domain_includes: ['CreativeWork', 'Event', 'Role'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case contributor = 'contributor';
    #[SchemaPropertyInfo(rdfs_comment: 'The time it takes to actually cook the dish, in <a href="http://en.wikipedia.org/wiki/ISO_8601" target="_blank" rel="noopener noreferrer">ISO 8601 duration format</a>.', schema_domain_includes: ['Recipe'], range_includes: ['Duration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case cookTime = 'cookTime';
    #[SchemaPropertyInfo(rdfs_comment: 'The method of cooking, such as Frying, Steaming, ...', schema_domain_includes: ['Recipe'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case cookingMethod = 'cookingMethod';
    #[SchemaPropertyInfo(rdfs_comment: 'The party holding the legal copyright to the CreativeWork.', schema_domain_includes: ['CreativeWork'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case copyrightHolder = 'copyrightHolder';
    #[SchemaPropertyInfo(rdfs_comment: 'Text of a notice appropriate for describing the copyright aspects of this Creative Work, ideally indicating the owner of the copyright for the Work.', schema_domain_includes: ['CreativeWork'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case copyrightNotice = 'copyrightNotice';
    #[SchemaPropertyInfo(rdfs_comment: 'The year during which the claimed copyright for the CreativeWork was first asserted.', schema_domain_includes: ['CreativeWork'], range_includes: ['Number'], superseded_by: null)]
    case copyrightYear = 'copyrightYear';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates a correction to a <a href="https://schema.org/CreativeWork" target="_blank" rel="noopener noreferrer">CreativeWork</a>, either via a <a href="https://schema.org/CorrectionComment" target="_blank" rel="noopener noreferrer">CorrectionComment</a>, textually or in another document.', schema_domain_includes: ['CreativeWork'], range_includes: ['CorrectionComment', 'Role', 'Text', 'URL'], superseded_by: null)]
    case correction = 'correction';
    #[SchemaPropertyInfo(rdfs_comment: 'For an <a href="https://schema.org/Organization" target="_blank" rel="noopener noreferrer">Organization</a> (e.g. <a href="https://schema.org/NewsMediaOrganization" target="_blank" rel="noopener noreferrer">NewsMediaOrganization</a>), a statement describing (in news media, the newsroom’s) disclosure and correction policy for errors.', schema_domain_includes: ['NewsMediaOrganization', 'Organization'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case correctionsPolicy = 'correctionsPolicy';
    #[SchemaPropertyInfo(rdfs_comment: 'The category of cost, such as wholesale, retail, reimbursement cap, etc.', schema_domain_includes: ['DrugCost'], range_includes: ['DrugCostCategory', 'Role', 'Text', 'URL'], superseded_by: null)]
    case costCategory = 'costCategory';
    #[SchemaPropertyInfo(rdfs_comment: 'The currency (in 3-letter) of the drug cost. See: http://en.wikipedia.org/wiki/ISO_4217.', schema_domain_includes: ['DrugCost'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case costCurrency = 'costCurrency';
    #[SchemaPropertyInfo(rdfs_comment: 'Additional details to capture the origin of the cost data. For example, \'Medicare Part B\'.', schema_domain_includes: ['DrugCost'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case costOrigin = 'costOrigin';
    #[SchemaPropertyInfo(rdfs_comment: 'The cost per unit of the drug.', schema_domain_includes: ['DrugCost'], range_includes: ['Number', 'QualitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case costPerUnit = 'costPerUnit';
    #[SchemaPropertyInfo(rdfs_comment: 'Countries for which the application is not supported. You can also provide the two-letter ISO 3166-1 alpha-2 country code.', schema_domain_includes: ['SoftwareApplication'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case countriesNotSupported = 'countriesNotSupported';
    #[SchemaPropertyInfo(rdfs_comment: 'Countries for which the application is supported. You can also provide the two-letter ISO 3166-1 alpha-2 country code.', schema_domain_includes: ['SoftwareApplication'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case countriesSupported = 'countriesSupported';
    #[SchemaPropertyInfo(rdfs_comment: 'The place where the product was assembled.', schema_domain_includes: ['Product'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case countryOfAssembly = 'countryOfAssembly';
    #[SchemaPropertyInfo(rdfs_comment: 'The place where the item (typically <a href="https://schema.org/Product" target="_blank" rel="noopener noreferrer">Product</a>) was last processed and tested before importation.', schema_domain_includes: ['Product'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case countryOfLastProcessing = 'countryOfLastProcessing';
    #[SchemaPropertyInfo(rdfs_comment: 'The country of origin of something, including products as well as creative  works such as movie and TV content.

In the case of TV and movie, this would be the country of the principle offices of the production company or individual responsible for the movie. For other kinds of <a href="https://schema.org/CreativeWork" target="_blank" rel="noopener noreferrer">CreativeWork</a> it is difficult to provide fully general guidance, and properties such as <a href="https://schema.org/contentLocation" target="_blank" rel="noopener noreferrer">contentLocation</a> and <a href="https://schema.org/locationCreated" target="_blank" rel="noopener noreferrer">locationCreated</a> may be more applicable.

In the case of products, the country of origin of the product. The exact interpretation of this may vary by context and product type, and cannot be fully enumerated here.', schema_domain_includes: ['CreativeWork', 'Movie', 'Product', 'TVEpisode', 'TVSeason', 'TVSeries'], range_includes: ['Country', 'Role', 'Text', 'URL'], superseded_by: null)]
    case countryOfOrigin = 'countryOfOrigin';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of location. The course where this action was taken.', schema_domain_includes: ['ExerciseAction'], range_includes: ['Place', 'Role', 'Text', 'URL'], superseded_by: 'exerciseCourse')]
    case course = 'course';
    #[SchemaPropertyInfo(rdfs_comment: 'The identifier for the <a href="https://schema.org/Course" target="_blank" rel="noopener noreferrer">Course</a> used by the course <a href="https://schema.org/provider" target="_blank" rel="noopener noreferrer">provider</a> (e.g. CS101 or 6.001).', schema_domain_includes: ['Course'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case courseCode = 'courseCode';
    #[SchemaPropertyInfo(rdfs_comment: 'The medium or means of delivery of the course instance or the mode of study, either as a text label (e.g. "online", "onsite" or "blended"; "synchronous" or "asynchronous"; "full-time" or "part-time") or as a URL reference to a term from a controlled vocabulary (e.g. https://ceds.ed.gov/element/001311#Asynchronous).', schema_domain_includes: ['CourseInstance'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case courseMode = 'courseMode';
    #[SchemaPropertyInfo(rdfs_comment: 'Requirements for taking the Course. May be completion of another <a href="https://schema.org/Course" target="_blank" rel="noopener noreferrer">Course</a> or a textual description like "permission of instructor". Requirements may be a pre-requisite competency, referenced using <a href="https://schema.org/AlignmentObject" target="_blank" rel="noopener noreferrer">AlignmentObject</a>.', schema_domain_includes: ['Course'], range_includes: ['AlignmentObject', 'Course', 'Role', 'Text', 'URL'], superseded_by: null)]
    case coursePrerequisites = 'coursePrerequisites';
    #[SchemaPropertyInfo(rdfs_comment: 'Represents the length and pace of a course, expressed as a <a href="https://schema.org/Schedule" target="_blank" rel="noopener noreferrer">Schedule</a>.', schema_domain_includes: ['CourseInstance'], range_includes: ['Role', 'Schedule', 'Text', 'URL'], superseded_by: null)]
    case courseSchedule = 'courseSchedule';
    #[SchemaPropertyInfo(rdfs_comment: 'The amount of work expected of students taking the course, often provided as a figure per week or per month, and may be broken down by type. For example, "2 hours of lectures, 1 hour of lab work and 3 hours of independent study per week".', schema_domain_includes: ['CourseInstance'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case courseWorkload = 'courseWorkload';
    #[SchemaPropertyInfo(rdfs_comment: 'The time when the live blog will stop covering the Event. Note that coverage may continue after the Event concludes.', schema_domain_includes: ['LiveBlogPosting'], range_includes: ['DateTime'], superseded_by: null)]
    case coverageEndTime = 'coverageEndTime';
    #[SchemaPropertyInfo(rdfs_comment: 'The time when the live blog will begin covering the Event. Note that coverage may begin before the Event\'s start time. The LiveBlogPosting may also be created before coverage begins.', schema_domain_includes: ['LiveBlogPosting'], range_includes: ['DateTime'], superseded_by: null)]
    case coverageStartTime = 'coverageStartTime';
    #[SchemaPropertyInfo(rdfs_comment: 'The status of a creative work in terms of its stage in a lifecycle. Example terms include Incomplete, Draft, Published, Obsolete. Some organizations define a set of terms for the stages of their publication lifecycle.', schema_domain_includes: ['CreativeWork'], range_includes: ['DefinedTerm', 'Role', 'Text', 'URL'], superseded_by: null)]
    case creativeWorkStatus = 'creativeWorkStatus';
    #[SchemaPropertyInfo(rdfs_comment: 'The creator/author of this CreativeWork. This is the same as the Author property for CreativeWork.', schema_domain_includes: ['CreativeWork', 'Role', 'UserComments'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case creator = 'creator';
    #[SchemaPropertyInfo(rdfs_comment: 'The category or type of credential being described, for example "degree”, “certificate”, “badge”, or more specific term.', schema_domain_includes: ['Credential'], range_includes: ['DefinedTerm', 'Role', 'Text', 'URL'], superseded_by: null)]
    case credentialCategory = 'credentialCategory';
    #[SchemaPropertyInfo(rdfs_comment: 'Text that can be used to credit person(s) and/or organization(s) associated with a published Creative Work.', schema_domain_includes: ['CreativeWork'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case creditText = 'creditText';
    #[SchemaPropertyInfo(rdfs_comment: 'The group the release is credited to if different than the byArtist. For example, Red and Blue is credited to "Stefani Germanotta Band", but by Lady Gaga.', schema_domain_includes: ['MusicRelease', 'Role'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case creditedTo = 'creditedTo';
    #[SchemaPropertyInfo(rdfs_comment: 'A CSS selector, e.g. of a <a href="https://schema.org/SpeakableSpecification" target="_blank" rel="noopener noreferrer">SpeakableSpecification</a> or <a href="https://schema.org/WebPageElement" target="_blank" rel="noopener noreferrer">WebPageElement</a>. In the latter case, multiple matches within a page can constitute a single conceptual "Web page element".', schema_domain_includes: ['SpeakableSpecification', 'WebPageElement'], range_includes: ['CssSelectorType', 'Role', 'Text', 'URL'], superseded_by: null)]
    case cssSelector = 'cssSelector';
    #[SchemaPropertyInfo(rdfs_comment: 'The currency accepted.\\n\\nUse standard formats: <a href="http://en.wikipedia.org/wiki/ISO_4217" target="_blank" rel="noopener noreferrer">ISO 4217 currency format</a>, e.g. "USD"; <a href="https://en.wikipedia.org/wiki/List_of_cryptocurrencies" target="_blank" rel="noopener noreferrer">Ticker symbol</a> for cryptocurrencies, e.g. "BTC"; well known names for <a href="https://en.wikipedia.org/wiki/Local_exchange_trading_system" target="_blank" rel="noopener noreferrer">Local Exchange Trading Systems</a> (LETS) and other currency types, e.g. "Ithaca HOUR".', schema_domain_includes: ['LocalBusiness'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case currenciesAccepted = 'currenciesAccepted';
    #[SchemaPropertyInfo(rdfs_comment: 'The currency in which the monetary amount is expressed.\\n\\nUse standard formats: <a href="http://en.wikipedia.org/wiki/ISO_4217" target="_blank" rel="noopener noreferrer">ISO 4217 currency format</a>, e.g. "USD"; <a href="https://en.wikipedia.org/wiki/List_of_cryptocurrencies" target="_blank" rel="noopener noreferrer">Ticker symbol</a> for cryptocurrencies, e.g. "BTC"; well known names for <a href="https://en.wikipedia.org/wiki/Local_exchange_trading_system" target="_blank" rel="noopener noreferrer">Local Exchange Trading Systems</a> (LETS) and other currency types, e.g. "Ithaca HOUR".', schema_domain_includes: ['DatedMoneySpecification', 'ExchangeRateSpecification', 'LoanOrCredit', 'MonetaryAmount', 'MonetaryAmountDistribution'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case currency = 'currency';
    #[SchemaPropertyInfo(rdfs_comment: 'The current price of a currency.', schema_domain_includes: ['ExchangeRateSpecification'], range_includes: ['Role', 'Text', 'URL', 'UnitPriceSpecification'], superseded_by: null)]
    case currentExchangeRate = 'currentExchangeRate';
    #[SchemaPropertyInfo(rdfs_comment: 'Party placing the order or paying the invoice.', schema_domain_includes: ['Invoice', 'Order'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case customer = 'customer';
    #[SchemaPropertyInfo(rdfs_comment: 'The type of return fees if the product is returned due to customer remorse.', schema_domain_includes: ['MerchantReturnPolicy'], range_includes: ['ReturnFeesEnumeration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case customerRemorseReturnFees = 'customerRemorseReturnFees';
    #[SchemaPropertyInfo(rdfs_comment: 'The method (from an enumeration) by which the customer obtains a return shipping label for a product returned due to customer remorse.', schema_domain_includes: ['MerchantReturnPolicy'], range_includes: ['ReturnLabelSourceEnumeration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case customerRemorseReturnLabelSource = 'customerRemorseReturnLabelSource';
    #[SchemaPropertyInfo(rdfs_comment: 'The amount of shipping costs if a product is returned due to customer remorse. Applicable when property <a href="https://schema.org/customerRemorseReturnFees" target="_blank" rel="noopener noreferrer">customerRemorseReturnFees</a> equals <a href="https://schema.org/ReturnShippingFees" target="_blank" rel="noopener noreferrer">ReturnShippingFees</a>.', schema_domain_includes: ['MerchantReturnPolicy'], range_includes: ['MonetaryAmount', 'Role', 'Text', 'URL'], superseded_by: null)]
    case customerRemorseReturnShippingFeesAmount = 'customerRemorseReturnShippingFeesAmount';
    #[SchemaPropertyInfo(rdfs_comment: 'Order cutoff time allows merchants to describe the time after which they will no longer process orders received on that day. For orders processed after cutoff time, one day gets added to the delivery time estimate. This property is expected to be most typically used via the <a href="https://schema.org/ShippingRateSettings" target="_blank" rel="noopener noreferrer">ShippingRateSettings</a> publication pattern. The time is indicated using the ISO-8601 Time format, e.g. "23:30:00-05:00" would represent 6:30 pm Eastern Standard Time (EST) which is 5 hours behind Coordinated Universal Time (UTC).', schema_domain_includes: ['ServicePeriod', 'ShippingDeliveryTime'], range_includes: ['Time'], superseded_by: null)]
    case cutoffTime = 'cutoffTime';
    #[SchemaPropertyInfo(rdfs_comment: 'collectiondate - Date for which patient counts are reported.', schema_domain_includes: ['CDCPMDRecord'], range_includes: ['DateTime', 'Role', 'Text', 'URL'], superseded_by: null)]
    case cvdCollectionDate = 'cvdCollectionDate';
    #[SchemaPropertyInfo(rdfs_comment: 'Name of the County of the NHSN facility that this data record applies to. Use <a href="https://schema.org/cvdFacilityId" target="_blank" rel="noopener noreferrer">cvdFacilityId</a> to identify the facility. To provide other details, <a href="https://schema.org/healthcareReportingData" target="_blank" rel="noopener noreferrer">healthcareReportingData</a> can be used on a <a href="https://schema.org/Hospital" target="_blank" rel="noopener noreferrer">Hospital</a> entry.', schema_domain_includes: ['CDCPMDRecord'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case cvdFacilityCounty = 'cvdFacilityCounty';
    #[SchemaPropertyInfo(rdfs_comment: 'Identifier of the NHSN facility that this data record applies to. Use <a href="https://schema.org/cvdFacilityCounty" target="_blank" rel="noopener noreferrer">cvdFacilityCounty</a> to indicate the county. To provide other details, <a href="https://schema.org/healthcareReportingData" target="_blank" rel="noopener noreferrer">healthcareReportingData</a> can be used on a <a href="https://schema.org/Hospital" target="_blank" rel="noopener noreferrer">Hospital</a> entry.', schema_domain_includes: ['CDCPMDRecord'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case cvdFacilityId = 'cvdFacilityId';
    #[SchemaPropertyInfo(rdfs_comment: 'numbeds - HOSPITAL INPATIENT BEDS: Inpatient beds, including all staffed, licensed, and overflow (surge) beds used for inpatients.', schema_domain_includes: ['CDCPMDRecord'], range_includes: ['Number'], superseded_by: null)]
    case cvdNumBeds = 'cvdNumBeds';
    #[SchemaPropertyInfo(rdfs_comment: 'numbedsocc - HOSPITAL INPATIENT BED OCCUPANCY: Total number of staffed inpatient beds that are occupied.', schema_domain_includes: ['CDCPMDRecord'], range_includes: ['Number'], superseded_by: null)]
    case cvdNumBedsOcc = 'cvdNumBedsOcc';
    #[SchemaPropertyInfo(rdfs_comment: 'numc19died - DEATHS: Patients with suspected or confirmed COVID-19 who died in the hospital, ED, or any overflow location.', schema_domain_includes: ['CDCPMDRecord'], range_includes: ['Number'], superseded_by: null)]
    case cvdNumC19Died = 'cvdNumC19Died';
    #[SchemaPropertyInfo(rdfs_comment: 'numc19hopats - HOSPITAL ONSET: Patients hospitalized in an NHSN inpatient care location with onset of suspected or confirmed COVID-19 14 or more days after hospitalization.', schema_domain_includes: ['CDCPMDRecord'], range_includes: ['Number'], superseded_by: null)]
    case cvdNumC19HOPats = 'cvdNumC19HOPats';
    #[SchemaPropertyInfo(rdfs_comment: 'numc19hosppats - HOSPITALIZED: Patients currently hospitalized in an inpatient care location who have suspected or confirmed COVID-19.', schema_domain_includes: ['CDCPMDRecord'], range_includes: ['Number'], superseded_by: null)]
    case cvdNumC19HospPats = 'cvdNumC19HospPats';
    #[SchemaPropertyInfo(rdfs_comment: 'numc19mechventpats - HOSPITALIZED and VENTILATED: Patients hospitalized in an NHSN inpatient care location who have suspected or confirmed COVID-19 and are on a mechanical ventilator.', schema_domain_includes: ['CDCPMDRecord'], range_includes: ['Number'], superseded_by: null)]
    case cvdNumC19MechVentPats = 'cvdNumC19MechVentPats';
    #[SchemaPropertyInfo(rdfs_comment: 'numc19ofmechventpats - ED/OVERFLOW and VENTILATED: Patients with suspected or confirmed COVID-19 who are in the ED or any overflow location awaiting an inpatient bed and on a mechanical ventilator.', schema_domain_includes: ['CDCPMDRecord'], range_includes: ['Number'], superseded_by: null)]
    case cvdNumC19OFMechVentPats = 'cvdNumC19OFMechVentPats';
    #[SchemaPropertyInfo(rdfs_comment: 'numc19overflowpats - ED/OVERFLOW: Patients with suspected or confirmed COVID-19 who are in the ED or any overflow location awaiting an inpatient bed.', schema_domain_includes: ['CDCPMDRecord'], range_includes: ['Number'], superseded_by: null)]
    case cvdNumC19OverflowPats = 'cvdNumC19OverflowPats';
    #[SchemaPropertyInfo(rdfs_comment: 'numicubeds - ICU BEDS: Total number of staffed inpatient intensive care unit (ICU) beds.', schema_domain_includes: ['CDCPMDRecord'], range_includes: ['Number'], superseded_by: null)]
    case cvdNumICUBeds = 'cvdNumICUBeds';
    #[SchemaPropertyInfo(rdfs_comment: 'numicubedsocc - ICU BED OCCUPANCY: Total number of staffed inpatient ICU beds that are occupied.', schema_domain_includes: ['CDCPMDRecord'], range_includes: ['Number'], superseded_by: null)]
    case cvdNumICUBedsOcc = 'cvdNumICUBedsOcc';
    #[SchemaPropertyInfo(rdfs_comment: 'numtotbeds - ALL HOSPITAL BEDS: Total number of all inpatient and outpatient beds, including all staffed, ICU, licensed, and overflow (surge) beds used for inpatients or outpatients.', schema_domain_includes: ['CDCPMDRecord'], range_includes: ['Number'], superseded_by: null)]
    case cvdNumTotBeds = 'cvdNumTotBeds';
    #[SchemaPropertyInfo(rdfs_comment: 'numvent - MECHANICAL VENTILATORS: Total number of ventilators available.', schema_domain_includes: ['CDCPMDRecord'], range_includes: ['Number'], superseded_by: null)]
    case cvdNumVent = 'cvdNumVent';
    #[SchemaPropertyInfo(rdfs_comment: 'numventuse - MECHANICAL VENTILATORS IN USE: Total number of ventilators in use.', schema_domain_includes: ['CDCPMDRecord'], range_includes: ['Number'], superseded_by: null)]
    case cvdNumVentUse = 'cvdNumVentUse';
    #[SchemaPropertyInfo(rdfs_comment: 'Data associated with the event, like for instance a log message.', schema_domain_includes: ['InstantaneousEvent'], range_includes: ['Role', 'Text', 'Thing', 'URL'], superseded_by: null)]
    case data = 'data';
    #[SchemaPropertyInfo(rdfs_comment: 'An item within a data feed. Data feeds may have many elements.', schema_domain_includes: ['DataFeed'], range_includes: ['DataFeedItem', 'Role', 'Text', 'Thing', 'URL'], superseded_by: null)]
    case dataFeedElement = 'dataFeedElement';
    #[SchemaPropertyInfo(rdfs_comment: 'A dataset contained in this catalog.', schema_domain_includes: ['DataCatalog'], range_includes: ['Dataset', 'Role', 'Text', 'URL'], superseded_by: null)]
    case dataset = 'dataset';
    #[SchemaPropertyInfo(rdfs_comment: 'The range of temporal applicability of a dataset, e.g. for a 2011 census dataset, the year 2011 (in ISO 8601 time interval format).', schema_domain_includes: ['Dataset'], range_includes: ['DateTime'], superseded_by: 'temporalCoverage')]
    case datasetTimeInterval = 'datasetTimeInterval';
    #[SchemaPropertyInfo(rdfs_comment: 'The date on which the CreativeWork was created or the item was added to a DataFeed.', schema_domain_includes: ['CreativeWork', 'DataFeedItem'], range_includes: ['Date', 'DateTime'], superseded_by: null)]
    case dateCreated = 'dateCreated';
    #[SchemaPropertyInfo(rdfs_comment: 'The datetime the item was removed from the DataFeed.', schema_domain_includes: ['DataFeedItem'], range_includes: ['Date', 'DateTime'], superseded_by: null)]
    case dateDeleted = 'dateDeleted';
    #[SchemaPropertyInfo(rdfs_comment: 'The date the ticket was issued.', schema_domain_includes: ['Ticket'], range_includes: ['Date', 'DateTime'], superseded_by: null)]
    case dateIssued = 'dateIssued';
    #[SchemaPropertyInfo(rdfs_comment: 'The date on which the CreativeWork was most recently modified or when the item\'s entry was modified within a DataFeed.', schema_domain_includes: ['CreativeWork', 'DataFeedItem'], range_includes: ['Date', 'DateTime'], superseded_by: null)]
    case dateModified = 'dateModified';
    #[SchemaPropertyInfo(rdfs_comment: 'Publication date of an online listing.', schema_domain_includes: ['CDCPMDRecord', 'JobPosting', 'RealEstateListing', 'SpecialAnnouncement'], range_includes: ['Date', 'DateTime'], superseded_by: null)]
    case datePosted = 'datePosted';
    #[SchemaPropertyInfo(rdfs_comment: 'Date of first publication or broadcast. For example the date a <a href="https://schema.org/CreativeWork" target="_blank" rel="noopener noreferrer">CreativeWork</a> was broadcast or a <a href="https://schema.org/Certification" target="_blank" rel="noopener noreferrer">Certification</a> was issued.', schema_domain_includes: ['Certification', 'CreativeWork'], range_includes: ['Date', 'DateTime'], superseded_by: null)]
    case datePublished = 'datePublished';
    #[SchemaPropertyInfo(rdfs_comment: 'The date/time at which the message has been read by the recipient if a single recipient exists.', schema_domain_includes: ['Message'], range_includes: ['Date', 'DateTime'], superseded_by: null)]
    case dateRead = 'dateRead';
    #[SchemaPropertyInfo(rdfs_comment: 'The date/time the message was received if a single recipient exists.', schema_domain_includes: ['Message'], range_includes: ['DateTime'], superseded_by: null)]
    case dateReceived = 'dateReceived';
    #[SchemaPropertyInfo(rdfs_comment: 'The date/time at which the message was sent.', schema_domain_includes: ['Message'], range_includes: ['DateTime'], superseded_by: null)]
    case dateSent = 'dateSent';
    #[SchemaPropertyInfo(rdfs_comment: 'The date of the first registration of the vehicle with the respective public authorities.', schema_domain_includes: ['Vehicle'], range_includes: ['Date'], superseded_by: null)]
    case dateVehicleFirstRegistered = 'dateVehicleFirstRegistered';
    #[SchemaPropertyInfo(rdfs_comment: 'A <a href="https://en.wikipedia.org/wiki/Dateline" target="_blank" rel="noopener noreferrer">dateline</a> is a brief piece of text included in news articles that describes where and when the story was written or filed though the date is often omitted. Sometimes only a placename is provided.

Structured representations of dateline-related information can also be expressed more explicitly using <a href="https://schema.org/locationCreated" target="_blank" rel="noopener noreferrer">locationCreated</a> (which represents where a work was created, e.g. where a news report was written).  For location depicted or described in the content, use <a href="https://schema.org/contentLocation" target="_blank" rel="noopener noreferrer">contentLocation</a>.

Dateline summaries are oriented more towards human readers than towards automated processing, and can vary substantially. Some examples: "BEIRUT, Lebanon, June 2.", "Paris, France", "December 19, 2017 11:43AM Reporting from Washington", "Beijing/Moscow", "QUEZON CITY, Philippines".', schema_domain_includes: ['NewsArticle'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case dateline = 'dateline';
    #[SchemaPropertyInfo(rdfs_comment: 'The day of the week for which these opening hours are valid.', schema_domain_includes: ['EducationalOccupationalProgram', 'OpeningHoursSpecification'], range_includes: ['DayOfWeek', 'Role', 'Text', 'URL'], superseded_by: null)]
    case dayOfWeek = 'dayOfWeek';
    #[SchemaPropertyInfo(rdfs_comment: 'Date of death.', schema_domain_includes: ['Person'], range_includes: ['Date'], superseded_by: null)]
    case deathDate = 'deathDate';
    #[SchemaPropertyInfo(rdfs_comment: 'The place where the person died.', schema_domain_includes: ['Person'], range_includes: ['Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case deathPlace = 'deathPlace';
    #[SchemaPropertyInfo(rdfs_comment: 'The default value of the input.  For properties that expect a literal, the default is a literal value, for properties that expect an object, it\'s an ID reference to one of the current values.', schema_domain_includes: ['PropertyValueSpecification'], range_includes: ['Role', 'Text', 'Thing', 'URL'], superseded_by: null)]
    case defaultValue = 'defaultValue';
    #[SchemaPropertyInfo(rdfs_comment: 'Destination address.', schema_domain_includes: ['ParcelDelivery'], range_includes: ['PostalAddress', 'Role', 'Text', 'URL'], superseded_by: null)]
    case deliveryAddress = 'deliveryAddress';
    #[SchemaPropertyInfo(rdfs_comment: 'The typical delay between the receipt of the order and the goods either leaving the warehouse or being prepared for pickup, in case the delivery method is on site pickup.', schema_domain_includes: ['Demand', 'Offer'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case deliveryLeadTime = 'deliveryLeadTime';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of instrument. The method of delivery.', schema_domain_includes: ['OrderAction', 'ReceiveAction', 'SendAction', 'TrackAction'], range_includes: ['DeliveryMethod', 'Role', 'Text', 'URL'], superseded_by: null)]
    case deliveryMethod = 'deliveryMethod';
    #[SchemaPropertyInfo(rdfs_comment: 'New entry added as the package passes through each leg of its journey (from shipment to final delivery).', schema_domain_includes: ['ParcelDelivery'], range_includes: ['DeliveryEvent', 'Role', 'Text', 'URL'], superseded_by: null)]
    case deliveryStatus = 'deliveryStatus';
    #[SchemaPropertyInfo(rdfs_comment: 'The total delay between the receipt of the order and the goods reaching the final customer.', schema_domain_includes: ['DeliveryTimeSettings', 'OfferShippingDetails'], range_includes: ['Role', 'ShippingDeliveryTime', 'Text', 'URL'], superseded_by: null)]
    case deliveryTime = 'deliveryTime';
    #[SchemaPropertyInfo(rdfs_comment: 'A relationship between an organization and a department of that organization, also described as an organization (allowing different urls, logos, opening hours). For example: a store with a pharmacy, or a bakery with a cafe.', schema_domain_includes: ['Organization'], range_includes: ['Organization', 'Role', 'Text', 'URL'], superseded_by: null)]
    case department = 'department';
    #[SchemaPropertyInfo(rdfs_comment: 'The airport where the flight originates.', schema_domain_includes: ['Flight'], range_includes: ['Airport', 'Role', 'Text', 'URL'], superseded_by: null)]
    case departureAirport = 'departureAirport';
    #[SchemaPropertyInfo(rdfs_comment: 'The terminal or port from which the boat departs.', schema_domain_includes: ['BoatTrip'], range_includes: ['BoatTerminal', 'Role', 'Text', 'URL'], superseded_by: null)]
    case departureBoatTerminal = 'departureBoatTerminal';
    #[SchemaPropertyInfo(rdfs_comment: 'The stop or station from which the bus departs.', schema_domain_includes: ['BusTrip'], range_includes: ['BusStation', 'BusStop', 'Role', 'Text', 'URL'], superseded_by: null)]
    case departureBusStop = 'departureBusStop';
    #[SchemaPropertyInfo(rdfs_comment: 'Identifier of the flight\'s departure gate.', schema_domain_includes: ['Flight'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case departureGate = 'departureGate';
    #[SchemaPropertyInfo(rdfs_comment: 'The platform from which the train departs.', schema_domain_includes: ['TrainTrip'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case departurePlatform = 'departurePlatform';
    #[SchemaPropertyInfo(rdfs_comment: 'The station from which the train departs.', schema_domain_includes: ['TrainTrip'], range_includes: ['Role', 'Text', 'TrainStation', 'URL'], superseded_by: null)]
    case departureStation = 'departureStation';
    #[SchemaPropertyInfo(rdfs_comment: 'Identifier of the flight\'s departure terminal.', schema_domain_includes: ['Flight'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case departureTerminal = 'departureTerminal';
    #[SchemaPropertyInfo(rdfs_comment: 'The expected departure time.', schema_domain_includes: ['Trip'], range_includes: ['DateTime', 'Time'], superseded_by: null)]
    case departureTime = 'departureTime';
    #[SchemaPropertyInfo(rdfs_comment: 'Prerequisites needed to fulfill steps in article.', schema_domain_includes: ['TechArticle'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case dependencies = 'dependencies';
    #[SchemaPropertyInfo(rdfs_comment: 'The depth of the item.', schema_domain_includes: ['OfferShippingDetails', 'Product', 'ShippingConditions', 'VisualArtwork'], range_includes: ['Distance', 'QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case depth = 'depth';
    #[SchemaPropertyInfo(rdfs_comment: 'A description of the item.', schema_domain_includes: ['Thing'], range_includes: ['Role', 'Text', 'TextObject', 'URL'], superseded_by: null)]
    case description = 'description';
    #[SchemaPropertyInfo(rdfs_comment: 'Device required to run the application. Used in cases where a specific make/model is required to run the application.', schema_domain_includes: ['SoftwareApplication'], range_includes: ['Role', 'Text', 'URL'], superseded_by: 'availableOnDevice')]
    case device = 'device';
    #[SchemaPropertyInfo(rdfs_comment: 'One or more alternative conditions considered in the differential diagnosis process as output of a diagnosis process.', schema_domain_includes: ['DDxElement', 'Patient'], range_includes: ['MedicalCondition', 'Role', 'Text', 'URL'], superseded_by: null)]
    case diagnosis = 'diagnosis';
    #[SchemaPropertyInfo(rdfs_comment: 'An image containing a diagram that illustrates the structure and/or its component substructures and/or connections with other structures.', schema_domain_includes: ['AnatomicalStructure'], range_includes: ['ImageObject', 'Role', 'Text', 'URL'], superseded_by: null)]
    case diagram = 'diagram';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of instrument. The diet used in this action.', schema_domain_includes: ['ExerciseAction'], range_includes: ['Diet', 'Role', 'Text', 'URL'], superseded_by: null)]
    case diet = 'diet';
    #[SchemaPropertyInfo(rdfs_comment: 'Nutritional information specific to the dietary plan. May include dietary recommendations on what foods to avoid, what foods to consume, and specific alterations/deviations from the USDA or other regulatory body\'s approved dietary guidelines.', schema_domain_includes: ['Diet'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case dietFeatures = 'dietFeatures';
    #[SchemaPropertyInfo(rdfs_comment: 'One of a set of differential diagnoses for the condition. Specifically, a closely-related or competing diagnosis typically considered later in the cognitive process whereby this medical condition is distinguished from others most likely responsible for a similar collection of signs and symptoms to reach the most parsimonious diagnosis or diagnoses in a patient.', schema_domain_includes: ['MedicalCondition'], range_includes: ['DDxElement', 'Role', 'Text', 'URL'], superseded_by: null)]
    case differentialDiagnosis = 'differentialDiagnosis';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates an IPTCDigitalSourceEnumeration code indicating the nature of the digital source(s) for some <a href="https://schema.org/CreativeWork" target="_blank" rel="noopener noreferrer">CreativeWork</a>.', schema_domain_includes: ['CreativeWork'], range_includes: ['IPTCDigitalSourceEnumeration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case digitalSourceType = 'digitalSourceType';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates whether an <a href="https://schema.org/url" target="_blank" rel="noopener noreferrer">url</a> that is associated with a <a href="https://schema.org/JobPosting" target="_blank" rel="noopener noreferrer">JobPosting</a> enables direct application for the job, via the posting website. A job posting is considered to have directApply of <a href="https://schema.org/True" target="_blank" rel="noopener noreferrer">True</a> if an application process for the specified job can be directly initiated via the url(s) given (noting that e.g. multiple internet domains might nevertheless be involved at an implementation level). A value of <a href="https://schema.org/False" target="_blank" rel="noopener noreferrer">False</a> is appropriate if there is no clear path to applying directly online for the specified job, navigating directly from the JobPosting url(s) supplied.', schema_domain_includes: ['JobPosting'], range_includes: ['Boolean'], superseded_by: null)]
    case directApply = 'directApply';
    #[SchemaPropertyInfo(rdfs_comment: 'A director of e.g. TV, radio, movie, video gaming etc. content, or of an event. Directors can be associated with individual items or with a series, episode, clip.', schema_domain_includes: ['Clip', 'CreativeWorkSeason', 'Episode', 'Event', 'Movie', 'MovieSeries', 'RadioSeries', 'TVSeries', 'VideoGame', 'VideoGameSeries', 'VideoObject'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case director = 'director';
    #[SchemaPropertyInfo(rdfs_comment: 'A director of e.g. TV, radio, movie, video games etc. content. Directors can be associated with individual items or with a series, episode, clip.', schema_domain_includes: ['Clip', 'Episode', 'Movie', 'MovieSeries', 'RadioSeries', 'TVSeries', 'VideoGame', 'VideoGameSeries', 'VideoObject'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: 'director')]
    case directors = 'directors';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of description. A short description of the item used to disambiguate from other, similar items. Information from other properties (in particular, name) may be necessary for the description to be useful for disambiguation.', schema_domain_includes: ['Thing'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case disambiguatingDescription = 'disambiguatingDescription';
    #[SchemaPropertyInfo(rdfs_comment: 'Any discount applied (to an Order).', schema_domain_includes: ['Order'], range_includes: ['Number', 'Role', 'Text', 'URL'], superseded_by: null)]
    case discount = 'discount';
    #[SchemaPropertyInfo(rdfs_comment: 'Code used to redeem a discount.', schema_domain_includes: ['Order'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case discountCode = 'discountCode';
    #[SchemaPropertyInfo(rdfs_comment: 'The currency of the discount.\\n\\nUse standard formats: <a href="http://en.wikipedia.org/wiki/ISO_4217" target="_blank" rel="noopener noreferrer">ISO 4217 currency format</a>, e.g. "USD"; <a href="https://en.wikipedia.org/wiki/List_of_cryptocurrencies" target="_blank" rel="noopener noreferrer">Ticker symbol</a> for cryptocurrencies, e.g. "BTC"; well known names for <a href="https://en.wikipedia.org/wiki/Local_exchange_trading_system" target="_blank" rel="noopener noreferrer">Local Exchange Trading Systems</a> (LETS) and other currency types, e.g. "Ithaca HOUR".', schema_domain_includes: ['Order'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case discountCurrency = 'discountCurrency';
    #[SchemaPropertyInfo(rdfs_comment: 'Specifies the CreativeWork associated with the UserComment.', schema_domain_includes: ['UserComments'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case discusses = 'discusses';
    #[SchemaPropertyInfo(rdfs_comment: 'A link to the page containing the comments of the CreativeWork.', schema_domain_includes: ['CreativeWork'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case discussionUrl = 'discussionUrl';
    #[SchemaPropertyInfo(rdfs_comment: 'Information about disease prevention.', schema_domain_includes: ['SpecialAnnouncement'], range_includes: ['Role', 'Text', 'URL', 'WebContent'], superseded_by: null)]
    case diseasePreventionInfo = 'diseasePreventionInfo';
    #[SchemaPropertyInfo(rdfs_comment: 'Statistical information about the spread of a disease, either as <a href="https://schema.org/WebContent" target="_blank" rel="noopener noreferrer">WebContent</a>, or
  described directly as a <a href="https://schema.org/Dataset" target="_blank" rel="noopener noreferrer">Dataset</a>, or the specific <a href="https://schema.org/Observation" target="_blank" rel="noopener noreferrer">Observation</a>s in the dataset. When a <a href="https://schema.org/WebContent" target="_blank" rel="noopener noreferrer">WebContent</a> URL is
  provided, the page indicated might also contain more such markup.', schema_domain_includes: ['SpecialAnnouncement'], range_includes: ['Dataset', 'Observation', 'Role', 'Text', 'URL', 'WebContent'], superseded_by: null)]
    case diseaseSpreadStatistics = 'diseaseSpreadStatistics';
    #[SchemaPropertyInfo(rdfs_comment: 'The location at which an item can be viewed or experienced in-person.', schema_domain_includes: ['CreativeWork', 'Product'], range_includes: ['Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case displayLocation = 'displayLocation';
    #[SchemaPropertyInfo(rdfs_comment: 'The date that this organization was dissolved.', schema_domain_includes: ['Organization'], range_includes: ['Date'], superseded_by: null)]
    case dissolutionDate = 'dissolutionDate';
    #[SchemaPropertyInfo(rdfs_comment: 'The distance travelled, e.g. exercising or travelling.', schema_domain_includes: ['ExerciseAction', 'TravelAction'], range_includes: ['Distance', 'Role', 'Text', 'URL'], superseded_by: null)]
    case distance = 'distance';
    #[SchemaPropertyInfo(rdfs_comment: 'One of a set of signs and symptoms that can be used to distinguish this diagnosis from others in the differential diagnosis.', schema_domain_includes: ['DDxElement'], range_includes: ['MedicalSignOrSymptom', 'Role', 'Text', 'URL'], superseded_by: null)]
    case distinguishingSign = 'distinguishingSign';
    #[SchemaPropertyInfo(rdfs_comment: 'A downloadable form of this dataset, at a specific location, in a specific format. This property can be repeated if different variations are available. There is no expectation that different downloadable distributions must contain exactly equivalent information (see also <a href="https://www.w3.org/TR/vocab-dcat-3/#Class:Distribution" target="_blank" rel="noopener noreferrer">DCAT</a> on this point). Different distributions might include or exclude different subsets of the entire dataset, for example.', schema_domain_includes: ['Dataset'], range_includes: ['DataDownload', 'Role', 'Text', 'URL'], superseded_by: null)]
    case distribution = 'distribution';
    #[SchemaPropertyInfo(rdfs_comment: 'Statement on diversity policy by an <a href="https://schema.org/Organization" target="_blank" rel="noopener noreferrer">Organization</a> e.g. a <a href="https://schema.org/NewsMediaOrganization" target="_blank" rel="noopener noreferrer">NewsMediaOrganization</a>. For a <a href="https://schema.org/NewsMediaOrganization" target="_blank" rel="noopener noreferrer">NewsMediaOrganization</a>, a statement describing the newsroom’s diversity policy on both staffing and sources, typically providing staffing data.', schema_domain_includes: ['NewsMediaOrganization', 'Organization'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case diversityPolicy = 'diversityPolicy';
    #[SchemaPropertyInfo(rdfs_comment: 'For an <a href="https://schema.org/Organization" target="_blank" rel="noopener noreferrer">Organization</a> (often but not necessarily a <a href="https://schema.org/NewsMediaOrganization" target="_blank" rel="noopener noreferrer">NewsMediaOrganization</a>), a report on staffing diversity issues. In a news context this might be for example ASNE or RTDNA (US) reports, or self-reported.', schema_domain_includes: ['NewsMediaOrganization', 'Organization'], range_includes: ['Article', 'Role', 'Text', 'URL'], superseded_by: null)]
    case diversityStaffingReport = 'diversityStaffingReport';
    #[SchemaPropertyInfo(rdfs_comment: 'Further documentation describing the Web API in more detail.', schema_domain_includes: ['WebAPI'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case documentation = 'documentation';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates when shipping to a particular <a href="https://schema.org/shippingDestination" target="_blank" rel="noopener noreferrer">shippingDestination</a> is not available.', schema_domain_includes: ['OfferShippingDetails', 'ShippingConditions', 'ShippingRateSettings'], range_includes: ['Boolean'], superseded_by: null)]
    case doesNotShip = 'doesNotShip';
    #[SchemaPropertyInfo(rdfs_comment: 'Relates a property to a class that is (one of) the type(s) the property is expected to be used on.', schema_domain_includes: ['Property'], range_includes: ['Class', 'Role', 'Text', 'URL'], superseded_by: null)]
    case domainIncludes = 'domainIncludes';
    #[SchemaPropertyInfo(rdfs_comment: 'Whether borrower is a resident of the jurisdiction where the property is located.', schema_domain_includes: ['MortgageLoan'], range_includes: ['Boolean'], superseded_by: null)]
    case domiciledMortgage = 'domiciledMortgage';
    #[SchemaPropertyInfo(rdfs_comment: 'The time admission will commence.', schema_domain_includes: ['Event'], range_includes: ['DateTime', 'Time'], superseded_by: null)]
    case doorTime = 'doorTime';
    #[SchemaPropertyInfo(rdfs_comment: 'A dosage form in which this drug/supplement is available, e.g. \'tablet\', \'suspension\', \'injection\'.', schema_domain_includes: ['Drug'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case dosageForm = 'dosageForm';
    #[SchemaPropertyInfo(rdfs_comment: 'A dosing schedule for the drug for a given population, either observed, recommended, or maximum dose based on the type used.', schema_domain_includes: ['Drug', 'TherapeuticProcedure'], range_includes: ['DoseSchedule', 'Role', 'Text', 'URL'], superseded_by: null)]
    case doseSchedule = 'doseSchedule';
    #[SchemaPropertyInfo(rdfs_comment: 'The unit of the dose, e.g. \'mg\'.', schema_domain_includes: ['DoseSchedule'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case doseUnit = 'doseUnit';
    #[SchemaPropertyInfo(rdfs_comment: 'The value of the dose, e.g. 500.', schema_domain_includes: ['DoseSchedule'], range_includes: ['Number', 'QualitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case doseValue = 'doseValue';
    #[SchemaPropertyInfo(rdfs_comment: 'a type of payment made in cash during the onset of the purchase of an expensive good/service. The payment typically represents only a percentage of the full purchase price.', schema_domain_includes: ['RepaymentSpecification'], range_includes: ['MonetaryAmount', 'Number', 'Role', 'Text', 'URL'], superseded_by: null)]
    case downPayment = 'downPayment';
    #[SchemaPropertyInfo(rdfs_comment: 'If the file can be downloaded, URL to download the binary.', schema_domain_includes: ['SoftwareApplication'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case downloadUrl = 'downloadUrl';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of downvotes this question, answer or comment has received from the community.', schema_domain_includes: ['Comment'], range_includes: ['Integer'], superseded_by: null)]
    case downvoteCount = 'downvoteCount';
    #[SchemaPropertyInfo(rdfs_comment: 'The vasculature that the vein drains into.', schema_domain_includes: ['Vein'], range_includes: ['Role', 'Text', 'URL', 'Vessel'], superseded_by: null)]
    case drainsTo = 'drainsTo';
    #[SchemaPropertyInfo(rdfs_comment: 'The drive wheel configuration, i.e. which roadwheels will receive torque from the vehicle\'s engine via the drivetrain.', schema_domain_includes: ['Vehicle'], range_includes: ['DriveWheelConfigurationValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case driveWheelConfiguration = 'driveWheelConfiguration';
    #[SchemaPropertyInfo(rdfs_comment: 'Where a rental car can be dropped off.', schema_domain_includes: ['RentalCarReservation'], range_includes: ['Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case dropoffLocation = 'dropoffLocation';
    #[SchemaPropertyInfo(rdfs_comment: 'When a rental car can be dropped off.', schema_domain_includes: ['RentalCarReservation'], range_includes: ['DateTime'], superseded_by: null)]
    case dropoffTime = 'dropoffTime';
    #[SchemaPropertyInfo(rdfs_comment: 'Specifying a drug or medicine used in a medication procedure.', schema_domain_includes: ['DrugClass', 'MedicalCondition', 'Patient', 'TherapeuticProcedure'], range_includes: ['Drug', 'Role', 'Text', 'URL'], superseded_by: null)]
    case drug = 'drug';
    #[SchemaPropertyInfo(rdfs_comment: 'The class of drug this belongs to (e.g., statins).', schema_domain_includes: ['Drug'], range_includes: ['DrugClass', 'Role', 'Text', 'URL'], superseded_by: null)]
    case drugClass = 'drugClass';
    #[SchemaPropertyInfo(rdfs_comment: 'The unit in which the drug is measured, e.g. \'5 mg tablet\'.', schema_domain_includes: ['Drug', 'DrugCost'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case drugUnit = 'drugUnit';
    #[SchemaPropertyInfo(rdfs_comment: 'The Dun & Bradstreet DUNS number for identifying an organization or business person.', schema_domain_includes: ['Organization', 'Person'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case duns = 'duns';
    #[SchemaPropertyInfo(rdfs_comment: 'A therapy that duplicates or overlaps this one.', schema_domain_includes: ['MedicalTherapy'], range_includes: ['MedicalTherapy', 'Role', 'Text', 'URL'], superseded_by: null)]
    case duplicateTherapy = 'duplicateTherapy';
    #[SchemaPropertyInfo(rdfs_comment: 'The duration of the item (movie, audio recording, event, etc.) in <a href="http://en.wikipedia.org/wiki/ISO_8601" target="_blank" rel="noopener noreferrer">ISO 8601 duration format</a>.', schema_domain_includes: ['Audiobook', 'Episode', 'Event', 'MediaObject', 'Movie', 'MusicRecording', 'MusicRelease', 'QuantitativeValueDistribution', 'Schedule', 'ServicePeriod'], range_includes: ['Duration', 'QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case duration = 'duration';
    #[SchemaPropertyInfo(rdfs_comment: 'The duration of the warranty promise. Common unitCode values are ANN for year, MON for months, or DAY for days.', schema_domain_includes: ['WarrantyPromise'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case durationOfWarranty = 'durationOfWarranty';
    #[SchemaPropertyInfo(rdfs_comment: 'A media object representing the circumstances while performing this direction.', schema_domain_includes: ['HowToDirection'], range_includes: ['MediaObject', 'Role', 'Text', 'URL'], superseded_by: null)]
    case duringMedia = 'duringMedia';
    #[SchemaPropertyInfo(rdfs_comment: 'The amount to be paid as a penalty in the event of early payment of the loan.', schema_domain_includes: ['RepaymentSpecification'], range_includes: ['MonetaryAmount', 'Role', 'Text', 'URL'], superseded_by: null)]
    case earlyPrepaymentPenalty = 'earlyPrepaymentPenalty';
    #[SchemaPropertyInfo(rdfs_comment: 'An <a href="https://eidr.org/" target="_blank" rel="noopener noreferrer">EIDR</a> (Entertainment Identifier Registry) <a href="https://schema.org/identifier" target="_blank" rel="noopener noreferrer">identifier</a> representing a specific edit / edition for a work of film or television.

For example, the motion picture known as "Ghostbusters" whose <a href="https://schema.org/titleEIDR" target="_blank" rel="noopener noreferrer">titleEIDR</a> is "10.5240/7EC7-228A-510A-053E-CBB8-J" has several edits, e.g. "10.5240/1F2A-E1C5-680A-14C6-E76B-I" and "10.5240/8A35-3BEE-6497-5D12-9E4F-3".

Since schema.org types like <a href="https://schema.org/Movie" target="_blank" rel="noopener noreferrer">Movie</a> and <a href="https://schema.org/TVEpisode" target="_blank" rel="noopener noreferrer">TVEpisode</a> can be used for both works and their multiple expressions, it is possible to use <a href="https://schema.org/titleEIDR" target="_blank" rel="noopener noreferrer">titleEIDR</a> alone (for a general description), or alongside <a href="https://schema.org/editEIDR" target="_blank" rel="noopener noreferrer">editEIDR</a> for a more edit-specific description.', schema_domain_includes: ['CreativeWork'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case editEIDR = 'editEIDR';
    #[SchemaPropertyInfo(rdfs_comment: 'Specifies the Person who edited the CreativeWork.', schema_domain_includes: ['CreativeWork'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case editor = 'editor';
    #[SchemaPropertyInfo(rdfs_comment: 'For questions that are part of learning resources (e.g. Quiz), eduQuestionType indicates the format of question being given. Example: "Multiple choice", "Open ended", "Flashcard".', schema_domain_includes: ['Question', 'SolveMathAction'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case eduQuestionType = 'eduQuestionType';
    #[SchemaPropertyInfo(rdfs_comment: 'Educational background needed for the position or Occupation.', schema_domain_includes: ['JobPosting', 'Occupation'], range_includes: ['EducationalOccupationalCredential', 'Role', 'Text', 'URL'], superseded_by: null)]
    case educationRequirements = 'educationRequirements';
    #[SchemaPropertyInfo(rdfs_comment: 'An alignment to an established educational framework.

This property should not be used where the nature of the alignment can be described using a simple property, for example to express that a resource <a href="https://schema.org/teaches" target="_blank" rel="noopener noreferrer">teaches</a> or <a href="https://schema.org/assesses" target="_blank" rel="noopener noreferrer">assesses</a> a competency.', schema_domain_includes: ['CreativeWork', 'LearningResource'], range_includes: ['AlignmentObject', 'Role', 'Text', 'URL'], superseded_by: null)]
    case educationalAlignment = 'educationalAlignment';
    #[SchemaPropertyInfo(rdfs_comment: 'A description of the qualification, award, certificate, diploma or other educational credential awarded as a consequence of successful completion of this course or program.', schema_domain_includes: ['Course', 'EducationalOccupationalProgram'], range_includes: ['EducationalOccupationalCredential', 'Role', 'Text', 'URL'], superseded_by: null)]
    case educationalCredentialAwarded = 'educationalCredentialAwarded';
    #[SchemaPropertyInfo(rdfs_comment: 'The framework to which the resource being described is aligned.', schema_domain_includes: ['AlignmentObject'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case educationalFramework = 'educationalFramework';
    #[SchemaPropertyInfo(rdfs_comment: 'The level in terms of progression through an educational or training context. Examples of educational levels include \'beginner\', \'intermediate\' or \'advanced\', and formal sets of level indicators.', schema_domain_includes: ['CreativeWork', 'EducationEvent', 'EducationalOccupationalCredential', 'LearningResource'], range_includes: ['DefinedTerm', 'Role', 'Text', 'URL'], superseded_by: null)]
    case educationalLevel = 'educationalLevel';
    #[SchemaPropertyInfo(rdfs_comment: 'Similar to courseMode, the medium or means of delivery of the program as a whole. The value may either be a text label (e.g. "online", "onsite" or "blended"; "synchronous" or "asynchronous"; "full-time" or "part-time") or a URL reference to a term from a controlled vocabulary (e.g. https://ceds.ed.gov/element/001311#Asynchronous ).', schema_domain_includes: ['EducationalOccupationalProgram'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case educationalProgramMode = 'educationalProgramMode';
    #[SchemaPropertyInfo(rdfs_comment: 'An educationalRole of an EducationalAudience.', schema_domain_includes: ['EducationalAudience'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case educationalRole = 'educationalRole';
    #[SchemaPropertyInfo(rdfs_comment: 'The purpose of a work in the context of education; for example, \'assignment\', \'group work\'.', schema_domain_includes: ['CreativeWork', 'LearningResource'], range_includes: ['DefinedTerm', 'Role', 'Text', 'URL'], superseded_by: null)]
    case educationalUse = 'educationalUse';
    #[SchemaPropertyInfo(rdfs_comment: 'The elevation of a location (<a href="https://en.wikipedia.org/wiki/World_Geodetic_System" target="_blank" rel="noopener noreferrer">WGS 84</a>). Values may be of the form \'NUMBER UNIT\\_OF\\_MEASUREMENT\' (e.g., \'1,000 m\', \'3,200 ft\') while numbers alone should be assumed to be a value in meters.', schema_domain_includes: ['GeoCoordinates', 'GeoShape'], range_includes: ['Number', 'Role', 'Text', 'URL'], superseded_by: null)]
    case elevation = 'elevation';
    #[SchemaPropertyInfo(rdfs_comment: 'The legal requirements such as citizenship, visa and other documentation required for an applicant to this job.', schema_domain_includes: ['JobPosting'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case eligibilityToWorkRequirement = 'eligibilityToWorkRequirement';
    #[SchemaPropertyInfo(rdfs_comment: 'The type(s) of customers for which the given offer is valid.', schema_domain_includes: ['Demand', 'Offer'], range_includes: ['BusinessEntityType', 'Role', 'Text', 'URL'], superseded_by: null)]
    case eligibleCustomerType = 'eligibleCustomerType';
    #[SchemaPropertyInfo(rdfs_comment: 'The duration for which the given offer is valid.', schema_domain_includes: ['Demand', 'Offer'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case eligibleDuration = 'eligibleDuration';
    #[SchemaPropertyInfo(rdfs_comment: 'The interval and unit of measurement of ordering quantities for which the offer or price specification is valid. This allows e.g. specifying that a certain freight charge is valid only for a certain quantity.', schema_domain_includes: ['Demand', 'Offer', 'PriceSpecification'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case eligibleQuantity = 'eligibleQuantity';
    #[SchemaPropertyInfo(rdfs_comment: 'The ISO 3166-1 (ISO 3166-1 alpha-2) or ISO 3166-2 code, the place, or the GeoShape for the geo-political region(s) for which the offer or delivery charge specification is valid.\\n\\nSee also <a href="https://schema.org/ineligibleRegion" target="_blank" rel="noopener noreferrer">ineligibleRegion</a>.', schema_domain_includes: ['ActionAccessSpecification', 'DeliveryChargeSpecification', 'Demand', 'Offer'], range_includes: ['GeoShape', 'Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case eligibleRegion = 'eligibleRegion';
    #[SchemaPropertyInfo(rdfs_comment: 'The transaction volume, in a monetary unit, for which the offer or price specification is valid, e.g. for indicating a minimal purchasing volume, to express free shipping above a certain order volume, or to limit the acceptance of credit cards to purchases to a certain minimal amount.', schema_domain_includes: ['Demand', 'Offer', 'PriceSpecification'], range_includes: ['PriceSpecification', 'Role', 'Text', 'URL'], superseded_by: null)]
    case eligibleTransactionVolume = 'eligibleTransactionVolume';
    #[SchemaPropertyInfo(rdfs_comment: 'The supplier of the incentivized item/service for which the incentive is valid for such as a utility company, merchant, or contractor.', schema_domain_includes: ['FinancialIncentive'], range_includes: ['Organization', 'Role', 'Text', 'URL'], superseded_by: null)]
    case eligibleWithSupplier = 'eligibleWithSupplier';
    #[SchemaPropertyInfo(rdfs_comment: 'Email address.', schema_domain_includes: ['ContactPoint', 'Organization', 'Person'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case email = 'email';
    #[SchemaPropertyInfo(rdfs_comment: 'A URL pointing to a player for a specific video. In general, this is the information in the ```src``` element of an ```embed``` tag and should not be the same as the content of the ```loc``` tag.', schema_domain_includes: ['MediaObject'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case embedUrl = 'embedUrl';
    #[SchemaPropertyInfo(rdfs_comment: 'Represents textual captioning from a <a href="https://schema.org/MediaObject" target="_blank" rel="noopener noreferrer">MediaObject</a>, e.g. text of a \'meme\'.', schema_domain_includes: ['AudioObject', 'ImageObject', 'VideoObject'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case embeddedTextCaption = 'embeddedTextCaption';
    #[SchemaPropertyInfo(rdfs_comment: 'The CO2 emissions in g/km. When used in combination with a QuantitativeValue, put "g/km" into the unitText property of that value, since there is no UN/CEFACT Common Code for "g/km".', schema_domain_includes: ['Vehicle'], range_includes: ['Number'], superseded_by: null)]
    case emissionsCO2 = 'emissionsCO2';
    #[SchemaPropertyInfo(rdfs_comment: 'Someone working for this organization.', schema_domain_includes: ['Organization'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case employee = 'employee';
    #[SchemaPropertyInfo(rdfs_comment: 'People working for this organization.', schema_domain_includes: ['Organization'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: 'employee')]
    case employees = 'employees';
    #[SchemaPropertyInfo(rdfs_comment: 'A description of the employer, career opportunities and work environment for this position.', schema_domain_includes: ['JobPosting'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case employerOverview = 'employerOverview';
    #[SchemaPropertyInfo(rdfs_comment: 'Type of employment (e.g. full-time, part-time, contract, temporary, seasonal, internship).', schema_domain_includes: ['JobPosting'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case employmentType = 'employmentType';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates the department, unit and/or facility where the employee reports and/or in which the job is to be performed.', schema_domain_includes: ['JobPosting'], range_includes: ['Organization', 'Role', 'Text', 'URL'], superseded_by: null)]
    case employmentUnit = 'employmentUnit';
    #[SchemaPropertyInfo(rdfs_comment: 'Another BioChemEntity encoded by this one.', schema_domain_includes: ['Gene'], range_includes: ['BioChemEntity', 'Role', 'Text', 'URL'], superseded_by: null)]
    case encodesBioChemEntity = 'encodesBioChemEntity';
    #[SchemaPropertyInfo(rdfs_comment: 'The CreativeWork encoded by this media object.', schema_domain_includes: ['MediaObject'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case encodesCreativeWork = 'encodesCreativeWork';
    #[SchemaPropertyInfo(rdfs_comment: 'A media object that encodes this CreativeWork. This property is a synonym for associatedMedia.', schema_domain_includes: ['CreativeWork'], range_includes: ['MediaObject', 'Role', 'Text', 'URL'], superseded_by: null)]
    case encoding = 'encoding';
    #[SchemaPropertyInfo(rdfs_comment: 'Media type typically expressed using a MIME format (see <a href="http://www.iana.org/assignments/media-types/media-types.xhtml" target="_blank" rel="noopener noreferrer">IANA site</a> and <a href="https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/MIME_types" target="_blank" rel="noopener noreferrer">MDN reference</a>), e.g. application/zip for a SoftwareApplication binary, audio/mpeg for .mp3 etc.

In cases where a <a href="https://schema.org/CreativeWork" target="_blank" rel="noopener noreferrer">CreativeWork</a> has several media type representations, <a href="https://schema.org/encoding" target="_blank" rel="noopener noreferrer">encoding</a> can be used to indicate each <a href="https://schema.org/MediaObject" target="_blank" rel="noopener noreferrer">MediaObject</a> alongside particular <a href="https://schema.org/encodingFormat" target="_blank" rel="noopener noreferrer">encodingFormat</a> information.

Unregistered or niche encoding and file formats can be indicated instead via the most appropriate URL, e.g. defining Web page or a Wikipedia/Wikidata entry.', schema_domain_includes: ['CreativeWork', 'MediaObject'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case encodingFormat = 'encodingFormat';
    #[SchemaPropertyInfo(rdfs_comment: 'The supported encoding type(s) for an EntryPoint request.', schema_domain_includes: ['EntryPoint'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case encodingType = 'encodingType';
    #[SchemaPropertyInfo(rdfs_comment: 'A media object that encodes this CreativeWork.', schema_domain_includes: ['CreativeWork'], range_includes: ['MediaObject', 'Role', 'Text', 'URL'], superseded_by: 'encoding')]
    case encodings = 'encodings';
    #[SchemaPropertyInfo(rdfs_comment: 'The end date and time of the item (in <a href="http://en.wikipedia.org/wiki/ISO_8601" target="_blank" rel="noopener noreferrer">ISO 8601 date format</a>).', schema_domain_includes: ['CreativeWorkSeason', 'CreativeWorkSeries', 'DatedMoneySpecification', 'EducationalOccupationalProgram', 'Event', 'MerchantReturnPolicySeasonalOverride', 'Role', 'Schedule'], range_includes: ['Date', 'DateTime'], superseded_by: null)]
    case endDate = 'endDate';
    #[SchemaPropertyInfo(rdfs_comment: 'The end time of the clip expressed as the number of seconds from the beginning of the work.', schema_domain_includes: ['Clip'], range_includes: ['HyperTocEntry', 'Number', 'Role', 'Text', 'URL'], superseded_by: null)]
    case endOffset = 'endOffset';
    #[SchemaPropertyInfo(rdfs_comment: 'The endTime of something. For a reserved event or service (e.g. FoodEstablishmentReservation), the time that it is expected to end. For actions that span a period of time, when the action was performed. E.g. John wrote a book from January to *December*. For media, including audio and video, it\'s the time offset of the end of a clip within a larger file.\\n\\nNote that Event uses startDate/endDate instead of startTime/endTime, even when describing dates with times. This situation may be clarified in future revisions.', schema_domain_includes: ['Action', 'FoodEstablishmentReservation', 'InteractionCounter', 'MediaObject', 'Schedule'], range_includes: ['DateTime', 'Time'], superseded_by: null)]
    case endTime = 'endTime';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of participant. The person/organization being supported.', schema_domain_includes: ['EndorseAction'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case endorsee = 'endorsee';
    #[SchemaPropertyInfo(rdfs_comment: 'People or organizations that endorse the plan.', schema_domain_includes: ['Diet'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case endorsers = 'endorsers';
    #[SchemaPropertyInfo(rdfs_comment: 'Specifies the most energy efficient class on the regulated EU energy consumption scale for the product category a product belongs to. For example, energy consumption for televisions placed on the market after January 1, 2020 is scaled from D to A+++.', schema_domain_includes: ['EnergyConsumptionDetails'], range_includes: ['EUEnergyEfficiencyEnumeration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case energyEfficiencyScaleMax = 'energyEfficiencyScaleMax';
    #[SchemaPropertyInfo(rdfs_comment: 'Specifies the least energy efficient class on the regulated EU energy consumption scale for the product category a product belongs to. For example, energy consumption for televisions placed on the market after January 1, 2020 is scaled from D to A+++.', schema_domain_includes: ['EnergyConsumptionDetails'], range_includes: ['EUEnergyEfficiencyEnumeration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case energyEfficiencyScaleMin = 'energyEfficiencyScaleMin';
    #[SchemaPropertyInfo(rdfs_comment: 'The volume swept by all of the pistons inside the cylinders of an internal combustion engine in a single movement. \\n\\nTypical unit code(s): CMQ for cubic centimeter, LTR for liters, INQ for cubic inches\\n* Note 1: You can link to information about how the given value has been determined using the <a href="https://schema.org/valueReference" target="_blank" rel="noopener noreferrer">valueReference</a> property.\\n* Note 2: You can use <a href="https://schema.org/minValue" target="_blank" rel="noopener noreferrer">minValue</a> and <a href="https://schema.org/maxValue" target="_blank" rel="noopener noreferrer">maxValue</a> to indicate ranges.', schema_domain_includes: ['EngineSpecification'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case engineDisplacement = 'engineDisplacement';
    #[SchemaPropertyInfo(rdfs_comment: 'The power of the vehicle\'s engine.
    Typical unit code(s): KWT for kilowatt, BHP for brake horsepower, N12 for metric horsepower (PS, with 1 PS = 735,49875 W)\\n\\n* Note 1: There are many different ways of measuring an engine\'s power. For an overview, see  <a href="http://en.wikipedia.org/wiki/Horsepower#Engine_power_test_codes" target="_blank" rel="noopener noreferrer">http://en.wikipedia.org/wiki/Horsepower#Engine\\_power\\_test\\_codes</a>.\\n* Note 2: You can link to information about how the given value has been determined using the <a href="https://schema.org/valueReference" target="_blank" rel="noopener noreferrer">valueReference</a> property.\\n* Note 3: You can use <a href="https://schema.org/minValue" target="_blank" rel="noopener noreferrer">minValue</a> and <a href="https://schema.org/maxValue" target="_blank" rel="noopener noreferrer">maxValue</a> to indicate ranges.', schema_domain_includes: ['EngineSpecification'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case enginePower = 'enginePower';
    #[SchemaPropertyInfo(rdfs_comment: 'The type of engine or engines powering the vehicle.', schema_domain_includes: ['EngineSpecification'], range_includes: ['QualitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case engineType = 'engineType';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of location. The entertainment business where the action occurred.', schema_domain_includes: ['PerformAction'], range_includes: ['EntertainmentBusiness', 'Role', 'Text', 'URL'], superseded_by: null)]
    case entertainmentBusiness = 'entertainmentBusiness';
    #[SchemaPropertyInfo(rdfs_comment: 'The characteristics of associated patients, such as age, gender, race etc.', schema_domain_includes: ['MedicalCondition', 'PhysicalActivity'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case epidemiology = 'epidemiology';
    #[SchemaPropertyInfo(rdfs_comment: 'An episode of a TV, radio or game media within a series or season.', schema_domain_includes: ['CreativeWorkSeason', 'RadioSeries', 'TVSeries', 'VideoGameSeries'], range_includes: ['Episode', 'Role', 'Text', 'URL'], superseded_by: null)]
    case episode = 'episode';
    #[SchemaPropertyInfo(rdfs_comment: 'Position of the episode within an ordered group of episodes.', schema_domain_includes: ['Episode'], range_includes: ['Integer', 'Role', 'Text', 'URL'], superseded_by: null)]
    case episodeNumber = 'episodeNumber';
    #[SchemaPropertyInfo(rdfs_comment: 'An episode of a TV/radio series or season.', schema_domain_includes: ['CreativeWorkSeason', 'RadioSeries', 'TVSeries', 'VideoGameSeries'], range_includes: ['Episode', 'Role', 'Text', 'URL'], superseded_by: 'episode')]
    case episodes = 'episodes';
    #[SchemaPropertyInfo(rdfs_comment: 'This ordering relation for qualitative values indicates that the subject is equal to the object.', schema_domain_includes: ['QualitativeValue'], range_includes: ['QualitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case equal = 'equal';
    #[SchemaPropertyInfo(rdfs_comment: 'For failed actions, more information on the cause of the failure. Consider using the Error type.', schema_domain_includes: ['Action'], range_includes: ['Role', 'Text', 'Thing', 'URL'], superseded_by: null)]
    case error = 'error';
    #[SchemaPropertyInfo(rdfs_comment: 'Application or platform dependant error code.', schema_domain_includes: ['Error'], range_includes: ['DefinedTerm', 'Integer', 'Role', 'StatusEnumeration', 'Text', 'URL'], superseded_by: null)]
    case errorCode = 'errorCode';
    #[SchemaPropertyInfo(rdfs_comment: 'The estimated cost of the supply or supplies consumed when performing instructions.', schema_domain_includes: ['HowTo', 'HowToSupply'], range_includes: ['MonetaryAmount', 'Role', 'Text', 'URL'], superseded_by: null)]
    case estimatedCost = 'estimatedCost';
    #[SchemaPropertyInfo(rdfs_comment: 'The estimated time the flight will take.', schema_domain_includes: ['Flight'], range_includes: ['Duration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case estimatedFlightDuration = 'estimatedFlightDuration';
    #[SchemaPropertyInfo(rdfs_comment: 'An estimated salary for a job posting or occupation, based on a variety of variables including, but not limited to industry, job title, and location. Estimated salaries  are often computed by outside organizations rather than the hiring organization, who may not have committed to the estimated value.', schema_domain_includes: ['JobPosting', 'Occupation'], range_includes: ['MonetaryAmount', 'MonetaryAmountDistribution', 'Number', 'Role', 'Text', 'URL'], superseded_by: null)]
    case estimatedSalary = 'estimatedSalary';
    #[SchemaPropertyInfo(rdfs_comment: 'The condition, complication, or symptom whose risk is being estimated.', schema_domain_includes: ['MedicalRiskEstimator'], range_includes: ['MedicalEntity', 'Role', 'Text', 'URL'], superseded_by: null)]
    case estimatesRiskOf = 'estimatesRiskOf';
    #[SchemaPropertyInfo(rdfs_comment: 'Statement about ethics policy, e.g. of a <a href="https://schema.org/NewsMediaOrganization" target="_blank" rel="noopener noreferrer">NewsMediaOrganization</a> regarding journalistic and publishing practices, or of a <a href="https://schema.org/Restaurant" target="_blank" rel="noopener noreferrer">Restaurant</a>, a page describing food source policies. In the case of a <a href="https://schema.org/NewsMediaOrganization" target="_blank" rel="noopener noreferrer">NewsMediaOrganization</a>, an ethicsPolicy is typically a statement describing the personal, organizational, and corporate standards of behavior expected by the organization.', schema_domain_includes: ['NewsMediaOrganization', 'Organization'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case ethicsPolicy = 'ethicsPolicy';
    #[SchemaPropertyInfo(rdfs_comment: 'Upcoming or past event associated with this place, organization, or action.', schema_domain_includes: ['InformAction', 'InviteAction', 'JoinAction', 'LeaveAction', 'Organization', 'Place', 'PlayAction'], range_includes: ['Event', 'Role', 'Text', 'URL'], superseded_by: null)]
    case event = 'event';
    #[SchemaPropertyInfo(rdfs_comment: 'The eventAttendanceMode of an event indicates whether it occurs online, offline, or a mix.', schema_domain_includes: ['Event'], range_includes: ['EventAttendanceModeEnumeration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case eventAttendanceMode = 'eventAttendanceMode';
    #[SchemaPropertyInfo(rdfs_comment: 'Associates an <a href="https://schema.org/Event" target="_blank" rel="noopener noreferrer">Event</a> with a <a href="https://schema.org/Schedule" target="_blank" rel="noopener noreferrer">Schedule</a>. There are circumstances where it is preferable to share a schedule for a series of
      repeating events rather than data on the individual events themselves. For example, a website or application might prefer to publish a schedule for a weekly
      gym class rather than provide data on every event. A schedule could be processed by applications to add forthcoming events to a calendar. An <a href="https://schema.org/Event" target="_blank" rel="noopener noreferrer">Event</a> that
      is associated with a <a href="https://schema.org/Schedule" target="_blank" rel="noopener noreferrer">Schedule</a> using this property should not have <a href="https://schema.org/startDate" target="_blank" rel="noopener noreferrer">startDate</a> or <a href="https://schema.org/endDate" target="_blank" rel="noopener noreferrer">endDate</a> properties. These are instead defined within the associated
      <a href="https://schema.org/Schedule" target="_blank" rel="noopener noreferrer">Schedule</a>, this avoids any ambiguity for clients using the data. The property might have repeated values to specify different schedules, e.g. for different months
      or seasons.', schema_domain_includes: ['Event'], range_includes: ['Role', 'Schedule', 'Text', 'URL'], superseded_by: null)]
    case eventSchedule = 'eventSchedule';
    #[SchemaPropertyInfo(rdfs_comment: 'An eventStatus of an event represents its status; particularly useful when an event is cancelled or rescheduled.', schema_domain_includes: ['Event'], range_includes: ['EventStatusType', 'Role', 'Text', 'URL'], superseded_by: null)]
    case eventStatus = 'eventStatus';
    #[SchemaPropertyInfo(rdfs_comment: 'Upcoming or past events associated with this place or organization.', schema_domain_includes: ['Organization', 'Place'], range_includes: ['Event', 'Role', 'Text', 'URL'], superseded_by: 'event')]
    case events = 'events';
    #[SchemaPropertyInfo(rdfs_comment: 'Strength of evidence of the data used to formulate the guideline (enumerated).', schema_domain_includes: ['MedicalGuideline'], range_includes: ['MedicalEvidenceLevel', 'Role', 'Text', 'URL'], superseded_by: null)]
    case evidenceLevel = 'evidenceLevel';
    #[SchemaPropertyInfo(rdfs_comment: 'Source of the data used to formulate the guidance, e.g. RCT, consensus opinion, etc.', schema_domain_includes: ['MedicalGuideline'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case evidenceOrigin = 'evidenceOrigin';
    #[SchemaPropertyInfo(rdfs_comment: 'A creative work that this work is an example/instance/realization/derivation of.', schema_domain_includes: ['CreativeWork'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case exampleOfWork = 'exampleOfWork';
    #[SchemaPropertyInfo(rdfs_comment: 'Defines a <a href="https://schema.org/Date" target="_blank" rel="noopener noreferrer">Date</a> or <a href="https://schema.org/DateTime" target="_blank" rel="noopener noreferrer">DateTime</a> during which a scheduled <a href="https://schema.org/Event" target="_blank" rel="noopener noreferrer">Event</a> will not take place. The property allows exceptions to
      a <a href="https://schema.org/Schedule" target="_blank" rel="noopener noreferrer">Schedule</a> to be specified. If an exception is specified as a <a href="https://schema.org/DateTime" target="_blank" rel="noopener noreferrer">DateTime</a> then only the event that would have started at that specific date and time
      should be excluded from the schedule. If an exception is specified as a <a href="https://schema.org/Date" target="_blank" rel="noopener noreferrer">Date</a> then any event that is scheduled for that 24 hour period should be
      excluded from the schedule. This allows a whole day to be excluded from the schedule without having to itemise every scheduled event.', schema_domain_includes: ['Schedule'], range_includes: ['Date', 'DateTime'], superseded_by: null)]
    case exceptDate = 'exceptDate';
    #[SchemaPropertyInfo(rdfs_comment: 'The difference between the price at which a broker or other intermediary buys and sells foreign currency.', schema_domain_includes: ['ExchangeRateSpecification'], range_includes: ['MonetaryAmount', 'Number', 'Role', 'Text', 'URL'], superseded_by: null)]
    case exchangeRateSpread = 'exchangeRateSpread';
    #[SchemaPropertyInfo(rdfs_comment: 'Library file name, e.g., mscorlib.dll, system.web.dll.', schema_domain_includes: ['APIReference'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case executableLibraryName = 'executableLibraryName';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of location. The course where this action was taken.', schema_domain_includes: ['ExerciseAction'], range_includes: ['Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case exerciseCourse = 'exerciseCourse';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of instrument. The exercise plan used on this action.', schema_domain_includes: ['ExerciseAction'], range_includes: ['ExercisePlan', 'Role', 'Text', 'URL'], superseded_by: null)]
    case exercisePlan = 'exercisePlan';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of instrument. The diet used in this action.', schema_domain_includes: ['ExerciseAction'], range_includes: ['Diet', 'Role', 'Text', 'URL'], superseded_by: null)]
    case exerciseRelatedDiet = 'exerciseRelatedDiet';
    #[SchemaPropertyInfo(rdfs_comment: 'Type(s) of exercise or activity, such as strength training, flexibility training, aerobics, cardiac rehabilitation, etc.', schema_domain_includes: ['ExerciseAction', 'ExercisePlan'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case exerciseType = 'exerciseType';
    #[SchemaPropertyInfo(rdfs_comment: 'exif data for this object.', schema_domain_includes: ['ImageObject'], range_includes: ['PropertyValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case exifData = 'exifData';
    #[SchemaPropertyInfo(rdfs_comment: 'The earliest date the package may arrive.', schema_domain_includes: ['ParcelDelivery'], range_includes: ['Date', 'DateTime'], superseded_by: null)]
    case expectedArrivalFrom = 'expectedArrivalFrom';
    #[SchemaPropertyInfo(rdfs_comment: 'The latest date the package may arrive.', schema_domain_includes: ['ParcelDelivery'], range_includes: ['Date', 'DateTime'], superseded_by: null)]
    case expectedArrivalUntil = 'expectedArrivalUntil';
    #[SchemaPropertyInfo(rdfs_comment: 'The likely outcome in either the short term or long term of the medical condition.', schema_domain_includes: ['MedicalCondition'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case expectedPrognosis = 'expectedPrognosis';
    #[SchemaPropertyInfo(rdfs_comment: 'An Offer which must be accepted before the user can perform the Action. For example, the user may need to buy a movie before being able to watch it.', schema_domain_includes: ['ActionAccessSpecification', 'ConsumeAction', 'MediaSubscription'], range_includes: ['Offer', 'Role', 'Text', 'URL'], superseded_by: null)]
    case expectsAcceptanceOf = 'expectsAcceptanceOf';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates whether a <a href="https://schema.org/JobPosting" target="_blank" rel="noopener noreferrer">JobPosting</a> will accept experience (as indicated by <a href="https://schema.org/OccupationalExperienceRequirements" target="_blank" rel="noopener noreferrer">OccupationalExperienceRequirements</a>) in place of its formal educational qualifications (as indicated by <a href="https://schema.org/educationRequirements" target="_blank" rel="noopener noreferrer">educationRequirements</a>). If true, indicates that satisfying one of these requirements is sufficient.', schema_domain_includes: ['JobPosting'], range_includes: ['Boolean'], superseded_by: null)]
    case experienceInPlaceOfEducation = 'experienceInPlaceOfEducation';
    #[SchemaPropertyInfo(rdfs_comment: 'Description of skills and experience needed for the position or Occupation.', schema_domain_includes: ['JobPosting', 'Occupation'], range_includes: ['OccupationalExperienceRequirements', 'Role', 'Text', 'URL'], superseded_by: null)]
    case experienceRequirements = 'experienceRequirements';
    #[SchemaPropertyInfo(rdfs_comment: 'Medical expert advice related to the plan.', schema_domain_includes: ['Diet'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case expertConsiderations = 'expertConsiderations';
    #[SchemaPropertyInfo(rdfs_comment: 'Date the content expires and is no longer useful or available. For example a <a href="https://schema.org/VideoObject" target="_blank" rel="noopener noreferrer">VideoObject</a> or <a href="https://schema.org/NewsArticle" target="_blank" rel="noopener noreferrer">NewsArticle</a> whose availability or relevance is time-limited, a <a href="https://schema.org/ClaimReview" target="_blank" rel="noopener noreferrer">ClaimReview</a> fact check whose publisher wants to indicate that it may no longer be relevant (or helpful to highlight) after some date, or a <a href="https://schema.org/Certification" target="_blank" rel="noopener noreferrer">Certification</a> the validity has expired.', schema_domain_includes: ['Certification', 'CreativeWork'], range_includes: ['Date', 'DateTime'], superseded_by: null)]
    case expires = 'expires';
    #[SchemaPropertyInfo(rdfs_comment: 'Tissue, organ, biological sample, etc in which activity of this gene has been observed experimentally. For example brain, digestive system.', schema_domain_includes: ['Gene'], range_includes: ['AnatomicalStructure', 'AnatomicalSystem', 'BioChemEntity', 'DefinedTerm', 'Role', 'Text', 'URL'], superseded_by: null)]
    case expressedIn = 'expressedIn';
    #[SchemaPropertyInfo(rdfs_comment: 'An address extension such as an apartment number, C/O or alternative name.', schema_domain_includes: ['PostalAddress'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case extendedAddress = 'extendedAddress';
    #[SchemaPropertyInfo(rdfs_comment: 'Family name. In the U.S., the last name of a Person.', schema_domain_includes: ['Person'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case familyName = 'familyName';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of grams of fat.', schema_domain_includes: ['NutritionInformation'], range_includes: ['Mass', 'Role', 'Text', 'URL'], superseded_by: null)]
    case fatContent = 'fatContent';
    #[SchemaPropertyInfo(rdfs_comment: 'The fax number.', schema_domain_includes: ['ContactPoint', 'Organization', 'Person', 'Place'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case faxNumber = 'faxNumber';
    #[SchemaPropertyInfo(rdfs_comment: 'Features or modules provided by this application (and possibly required by other applications).', schema_domain_includes: ['SoftwareApplication'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case featureList = 'featureList';
    #[SchemaPropertyInfo(rdfs_comment: 'Description of fees, commissions, and other terms applied either to a class of financial product, or by a financial service organization.', schema_domain_includes: ['FinancialProduct', 'FinancialService'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case feesAndCommissionsSpecification = 'feesAndCommissionsSpecification';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of grams of fiber.', schema_domain_includes: ['NutritionInformation'], range_includes: ['Mass', 'Role', 'Text', 'URL'], superseded_by: null)]
    case fiberContent = 'fiberContent';
    #[SchemaPropertyInfo(rdfs_comment: 'Media type, typically MIME format (see <a href="http://www.iana.org/assignments/media-types/media-types.xhtml" target="_blank" rel="noopener noreferrer">IANA site</a>) of the content, e.g. application/zip of a SoftwareApplication binary. In cases where a CreativeWork has several media type representations, \'encoding\' can be used to indicate each MediaObject alongside particular fileFormat information. Unregistered or niche file formats can be indicated instead via the most appropriate URL, e.g. defining Web page or a Wikipedia entry.', schema_domain_includes: ['CreativeWork'], range_includes: ['Role', 'Text', 'URL'], superseded_by: 'encodingFormat')]
    case fileFormat = 'fileFormat';
    #[SchemaPropertyInfo(rdfs_comment: 'Size of the application / package (e.g. 18MB). In the absence of a unit (MB, KB etc.), KB will be assumed.', schema_domain_includes: ['SoftwareApplication'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case fileSize = 'fileSize';
    #[SchemaPropertyInfo(rdfs_comment: 'A financial aid type or program which students may use to pay for tuition or fees associated with the program.', schema_domain_includes: ['Course', 'EducationalOccupationalProgram'], range_includes: ['DefinedTerm', 'Role', 'Text', 'URL'], superseded_by: null)]
    case financialAidEligible = 'financialAidEligible';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates the first known occurrence of a <a href="https://schema.org/Claim" target="_blank" rel="noopener noreferrer">Claim</a> in some <a href="https://schema.org/CreativeWork" target="_blank" rel="noopener noreferrer">CreativeWork</a>.', schema_domain_includes: ['Claim'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case firstAppearance = 'firstAppearance';
    #[SchemaPropertyInfo(rdfs_comment: 'The date and place the work was first performed.', schema_domain_includes: ['MusicComposition'], range_includes: ['Event', 'Role', 'Text', 'URL'], superseded_by: null)]
    case firstPerformance = 'firstPerformance';
    #[SchemaPropertyInfo(rdfs_comment: 'The distance of the flight.', schema_domain_includes: ['Flight'], range_includes: ['Distance', 'Role', 'Text', 'URL'], superseded_by: null)]
    case flightDistance = 'flightDistance';
    #[SchemaPropertyInfo(rdfs_comment: 'The unique identifier for a flight including the airline IATA code. For example, if describing United flight 110, where the IATA code for United is \'UA\', the flightNumber is \'UA110\'.', schema_domain_includes: ['Flight'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case flightNumber = 'flightNumber';
    #[SchemaPropertyInfo(rdfs_comment: 'The floor level for an <a href="https://schema.org/Accommodation" target="_blank" rel="noopener noreferrer">Accommodation</a> in a multi-storey building. Since counting
  systems <a href="https://en.wikipedia.org/wiki/Storey#Consecutive_number_floor_designations" target="_blank" rel="noopener noreferrer">vary internationally</a>, the local system should be used where possible.', schema_domain_includes: ['Accommodation', 'LocalBusiness', 'Residence'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case floorLevel = 'floorLevel';
    #[SchemaPropertyInfo(rdfs_comment: 'A floor limit is the amount of money above which credit card transactions must be authorized.', schema_domain_includes: ['PaymentCard'], range_includes: ['MonetaryAmount', 'Role', 'Text', 'URL'], superseded_by: null)]
    case floorLimit = 'floorLimit';
    #[SchemaPropertyInfo(rdfs_comment: 'The size of the accommodation, e.g. in square meter or squarefoot.
Typical unit code(s): MTK for square meter, FTK for square foot, or YDK for square yard.', schema_domain_includes: ['Accommodation', 'FloorPlan'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case floorSize = 'floorSize';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of object. The person or organization being followed.', schema_domain_includes: ['FollowAction'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case followee = 'followee';
    #[SchemaPropertyInfo(rdfs_comment: 'The most generic uni-directional social relation.', schema_domain_includes: ['Person'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case follows = 'follows';
    #[SchemaPropertyInfo(rdfs_comment: 'Typical or recommended followup care after the procedure is performed.', schema_domain_includes: ['MedicalProcedure'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case followup = 'followup';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of location. The specific food establishment where the action occurred.', schema_domain_includes: ['CookAction'], range_includes: ['FoodEstablishment', 'Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case foodEstablishment = 'foodEstablishment';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of location. The specific food event where the action occurred.', schema_domain_includes: ['CookAction'], range_includes: ['FoodEvent', 'Role', 'Text', 'URL'], superseded_by: null)]
    case foodEvent = 'foodEvent';
    #[SchemaPropertyInfo(rdfs_comment: 'Any precaution, guidance, contraindication, etc. related to consumption of specific foods while taking this drug.', schema_domain_includes: ['Drug'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case foodWarning = 'foodWarning';
    #[SchemaPropertyInfo(rdfs_comment: 'A person or organization who founded this organization.', schema_domain_includes: ['Organization', 'Role'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case founder = 'founder';
    #[SchemaPropertyInfo(rdfs_comment: 'A person who founded this organization.', schema_domain_includes: ['Organization'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: 'founder')]
    case founders = 'founders';
    #[SchemaPropertyInfo(rdfs_comment: 'The date that this organization was founded.', schema_domain_includes: ['Organization'], range_includes: ['Date'], superseded_by: null)]
    case foundingDate = 'foundingDate';
    #[SchemaPropertyInfo(rdfs_comment: 'The place where the Organization was founded.', schema_domain_includes: ['Organization'], range_includes: ['Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case foundingLocation = 'foundingLocation';
    #[SchemaPropertyInfo(rdfs_comment: 'A flag to signal that the item, event, or place is accessible for free.', schema_domain_includes: ['PublicationEvent'], range_includes: ['Boolean'], superseded_by: 'isAccessibleForFree')]
    case free = 'free';
    #[SchemaPropertyInfo(rdfs_comment: 'A monetary value above (or at) which the shipping rate becomes free. Intended to be used via an <a href="https://schema.org/OfferShippingDetails" target="_blank" rel="noopener noreferrer">OfferShippingDetails</a> with <a href="https://schema.org/shippingSettingsLink" target="_blank" rel="noopener noreferrer">shippingSettingsLink</a> matching this <a href="https://schema.org/ShippingRateSettings" target="_blank" rel="noopener noreferrer">ShippingRateSettings</a>.', schema_domain_includes: ['ShippingRateSettings'], range_includes: ['DeliveryChargeSpecification', 'MonetaryAmount', 'Role', 'Text', 'URL'], superseded_by: null)]
    case freeShippingThreshold = 'freeShippingThreshold';
    #[SchemaPropertyInfo(rdfs_comment: 'How often the dose is taken, e.g. \'daily\'.', schema_domain_includes: ['DoseSchedule'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case frequency = 'frequency';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of location. The original location of the object or the agent before the action.', schema_domain_includes: ['ExerciseAction', 'MoveAction', 'TransferAction'], range_includes: ['Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case fromLocation = 'fromLocation';
    #[SchemaPropertyInfo(rdfs_comment: 'The capacity of the fuel tank or in the case of electric cars, the battery. If there are multiple components for storage, this should indicate the total of all storage of the same type.\\n\\nTypical unit code(s): LTR for liters, GLL of US gallons, GLI for UK / imperial gallons, AMH for ampere-hours (for electrical vehicles).', schema_domain_includes: ['Vehicle'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case fuelCapacity = 'fuelCapacity';
    #[SchemaPropertyInfo(rdfs_comment: 'The amount of fuel consumed for traveling a particular distance or temporal duration with the given vehicle (e.g. liters per 100 km).\\n\\n* Note 1: There are unfortunately no standard unit codes for liters per 100 km.  Use <a href="https://schema.org/unitText" target="_blank" rel="noopener noreferrer">unitText</a> to indicate the unit of measurement, e.g. L/100 km.\\n* Note 2: There are two ways of indicating the fuel consumption, <a href="https://schema.org/fuelConsumption" target="_blank" rel="noopener noreferrer">fuelConsumption</a> (e.g. 8 liters per 100 km) and <a href="https://schema.org/fuelEfficiency" target="_blank" rel="noopener noreferrer">fuelEfficiency</a> (e.g. 30 miles per gallon). They are reciprocal.\\n* Note 3: Often, the absolute value is useful only when related to driving speed ("at 80 km/h") or usage pattern ("city traffic"). You can use <a href="https://schema.org/valueReference" target="_blank" rel="noopener noreferrer">valueReference</a> to link the value for the fuel consumption to another value.', schema_domain_includes: ['Vehicle'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case fuelConsumption = 'fuelConsumption';
    #[SchemaPropertyInfo(rdfs_comment: 'The distance traveled per unit of fuel used; most commonly miles per gallon (mpg) or kilometers per liter (km/L).\\n\\n* Note 1: There are unfortunately no standard unit codes for miles per gallon or kilometers per liter. Use <a href="https://schema.org/unitText" target="_blank" rel="noopener noreferrer">unitText</a> to indicate the unit of measurement, e.g. mpg or km/L.\\n* Note 2: There are two ways of indicating the fuel consumption, <a href="https://schema.org/fuelConsumption" target="_blank" rel="noopener noreferrer">fuelConsumption</a> (e.g. 8 liters per 100 km) and <a href="https://schema.org/fuelEfficiency" target="_blank" rel="noopener noreferrer">fuelEfficiency</a> (e.g. 30 miles per gallon). They are reciprocal.\\n* Note 3: Often, the absolute value is useful only when related to driving speed ("at 80 km/h") or usage pattern ("city traffic"). You can use <a href="https://schema.org/valueReference" target="_blank" rel="noopener noreferrer">valueReference</a> to link the value for the fuel economy to another value.', schema_domain_includes: ['Vehicle'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case fuelEfficiency = 'fuelEfficiency';
    #[SchemaPropertyInfo(rdfs_comment: 'The type of fuel suitable for the engine or engines of the vehicle. If the vehicle has only one engine, this property can be attached directly to the vehicle.', schema_domain_includes: ['EngineSpecification', 'Vehicle'], range_includes: ['QualitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case fuelType = 'fuelType';
    #[SchemaPropertyInfo(rdfs_comment: 'Type of fulfillment applicable to the <a href="https://schema.org/ShippingService" target="_blank" rel="noopener noreferrer">ShippingService</a>.', schema_domain_includes: ['ShippingService'], range_includes: ['FulfillmentTypeEnumeration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case fulfillmentType = 'fulfillmentType';
    #[SchemaPropertyInfo(rdfs_comment: 'The degree of mobility the joint allows.', schema_domain_includes: ['Joint'], range_includes: ['MedicalEntity', 'Role', 'Text', 'URL'], superseded_by: null)]
    case functionalClass = 'functionalClass';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates something directly or indirectly funded or sponsored through a <a href="https://schema.org/Grant" target="_blank" rel="noopener noreferrer">Grant</a>. See also <a href="https://schema.org/ownershipFundingInfo" target="_blank" rel="noopener noreferrer">ownershipFundingInfo</a>.', schema_domain_includes: ['Grant'], range_includes: ['BioChemEntity', 'CreativeWork', 'Event', 'MedicalEntity', 'Organization', 'Person', 'Product', 'Role', 'Text', 'URL'], superseded_by: null)]
    case fundedItem = 'fundedItem';
    #[SchemaPropertyInfo(rdfs_comment: 'A person or organization that supports (sponsors) something through some kind of financial contribution.', schema_domain_includes: ['CreativeWork', 'Event', 'Grant', 'MonetaryGrant', 'Organization', 'Person'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case funder = 'funder';
    #[SchemaPropertyInfo(rdfs_comment: 'A <a href="https://schema.org/Grant" target="_blank" rel="noopener noreferrer">Grant</a> that directly or indirectly provide funding or sponsorship for this item. See also <a href="https://schema.org/ownershipFundingInfo" target="_blank" rel="noopener noreferrer">ownershipFundingInfo</a>.', schema_domain_includes: ['BioChemEntity', 'CreativeWork', 'Event', 'MedicalEntity', 'Organization', 'Person', 'Product'], range_includes: ['Grant', 'Role', 'Text', 'URL'], superseded_by: null)]
    case funding = 'funding';
    #[SchemaPropertyInfo(rdfs_comment: 'Video game which is played on this server.', schema_domain_includes: ['GameServer'], range_includes: ['Role', 'Text', 'URL', 'VideoGame'], superseded_by: null)]
    case game = 'game';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates the availability type of the game content associated with this action, such as whether it is a full version or a demo.', schema_domain_includes: ['PlayGameAction'], range_includes: ['GameAvailabilityEnumeration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case gameAvailabilityType = 'gameAvailabilityType';
    #[SchemaPropertyInfo(rdfs_comment: 'The edition of a video game.', schema_domain_includes: ['VideoGame'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case gameEdition = 'gameEdition';
    #[SchemaPropertyInfo(rdfs_comment: 'An item is an object within the game world that can be collected by a player or, occasionally, a non-player character.', schema_domain_includes: ['Game', 'VideoGameSeries'], range_includes: ['Role', 'Text', 'Thing', 'URL'], superseded_by: null)]
    case gameItem = 'gameItem';
    #[SchemaPropertyInfo(rdfs_comment: 'Real or fictional location of the game (or part of game).', schema_domain_includes: ['Game', 'VideoGameSeries'], range_includes: ['Place', 'PostalAddress', 'Role', 'Text', 'URL'], superseded_by: null)]
    case gameLocation = 'gameLocation';
    #[SchemaPropertyInfo(rdfs_comment: 'The electronic systems used to play <a href="http://en.wikipedia.org/wiki/Category:Video_game_platforms">video games</a>.', schema_domain_includes: ['VideoGame', 'VideoGameSeries'], range_includes: ['Role', 'Text', 'Thing', 'URL'], superseded_by: null)]
    case gamePlatform = 'gamePlatform';
    #[SchemaPropertyInfo(rdfs_comment: 'The server on which  it is possible to play the game.', schema_domain_includes: ['VideoGame'], range_includes: ['GameServer', 'Role', 'Text', 'URL'], superseded_by: null)]
    case gameServer = 'gameServer';
    #[SchemaPropertyInfo(rdfs_comment: 'Links to tips, tactics, etc.', schema_domain_includes: ['VideoGame'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case gameTip = 'gameTip';
    #[SchemaPropertyInfo(rdfs_comment: 'Gender of something, typically a <a href="https://schema.org/Person" target="_blank" rel="noopener noreferrer">Person</a>, but possibly also fictional characters, animals, etc. While https://schema.org/Male and https://schema.org/Female may be used, text strings are also acceptable for people who are not a binary gender. The <a href="https://schema.org/gender" target="_blank" rel="noopener noreferrer">gender</a> property can also be used in an extended sense to cover e.g. the gender of sports teams. As with the gender of individuals, we do not try to enumerate all possibilities. A mixed-gender <a href="https://schema.org/SportsTeam" target="_blank" rel="noopener noreferrer">SportsTeam</a> can be indicated with a text value of "Mixed".', schema_domain_includes: ['Person', 'SportsTeam'], range_includes: ['GenderType', 'Role', 'Text', 'URL'], superseded_by: null)]
    case gender = 'gender';
    #[SchemaPropertyInfo(rdfs_comment: 'Genre of the creative work, broadcast channel or group.', schema_domain_includes: ['BroadcastChannel', 'CreativeWork', 'MusicGroup'], range_includes: ['DefinedTerm', 'Role', 'Text', 'URL'], superseded_by: null)]
    case genre = 'genre';
    #[SchemaPropertyInfo(rdfs_comment: 'The geo coordinates of the place.', schema_domain_includes: ['Place'], range_includes: ['GeoCoordinates', 'GeoShape', 'Role', 'Text', 'URL'], superseded_by: null)]
    case geo = 'geo';
    #[SchemaPropertyInfo(rdfs_comment: 'Represents a relationship between two geometries (or the places they represent), relating a containing geometry to a contained geometry. "a contains b iff no points of b lie in the exterior of a, and at least one point of the interior of b lies in the interior of a". As defined in <a href="https://en.wikipedia.org/wiki/DE-9IM" target="_blank" rel="noopener noreferrer">DE-9IM</a>.', schema_domain_includes: ['GeospatialGeometry', 'Place'], range_includes: ['GeospatialGeometry', 'Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case geoContains = 'geoContains';
    #[SchemaPropertyInfo(rdfs_comment: 'Represents a relationship between two geometries (or the places they represent), relating a geometry to another that covers it. As defined in <a href="https://en.wikipedia.org/wiki/DE-9IM" target="_blank" rel="noopener noreferrer">DE-9IM</a>.', schema_domain_includes: ['GeospatialGeometry', 'Place'], range_includes: ['GeospatialGeometry', 'Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case geoCoveredBy = 'geoCoveredBy';
    #[SchemaPropertyInfo(rdfs_comment: 'Represents a relationship between two geometries (or the places they represent), relating a covering geometry to a covered geometry. "Every point of b is a point of (the interior or boundary of) a". As defined in <a href="https://en.wikipedia.org/wiki/DE-9IM" target="_blank" rel="noopener noreferrer">DE-9IM</a>.', schema_domain_includes: ['GeospatialGeometry', 'Place'], range_includes: ['GeospatialGeometry', 'Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case geoCovers = 'geoCovers';
    #[SchemaPropertyInfo(rdfs_comment: 'Represents a relationship between two geometries (or the places they represent), relating a geometry to another that crosses it: "a crosses b: they have some but not all interior points in common, and the dimension of the intersection is less than that of at least one of them". As defined in <a href="https://en.wikipedia.org/wiki/DE-9IM" target="_blank" rel="noopener noreferrer">DE-9IM</a>.', schema_domain_includes: ['GeospatialGeometry', 'Place'], range_includes: ['GeospatialGeometry', 'Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case geoCrosses = 'geoCrosses';
    #[SchemaPropertyInfo(rdfs_comment: 'Represents spatial relations in which two geometries (or the places they represent) are topologically disjoint: "they have no point in common. They form a set of disconnected geometries." (A symmetric relationship, as defined in <a href="https://en.wikipedia.org/wiki/DE-9IM" target="_blank" rel="noopener noreferrer">DE-9IM</a>.)', schema_domain_includes: ['GeospatialGeometry', 'Place'], range_includes: ['GeospatialGeometry', 'Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case geoDisjoint = 'geoDisjoint';
    #[SchemaPropertyInfo(rdfs_comment: 'Represents spatial relations in which two geometries (or the places they represent) are topologically equal, as defined in <a href="https://en.wikipedia.org/wiki/DE-9IM" target="_blank" rel="noopener noreferrer">DE-9IM</a>. "Two geometries are topologically equal if their interiors intersect and no part of the interior or boundary of one geometry intersects the exterior of the other" (a symmetric relationship).', schema_domain_includes: ['GeospatialGeometry', 'Place'], range_includes: ['GeospatialGeometry', 'Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case geoEquals = 'geoEquals';
    #[SchemaPropertyInfo(rdfs_comment: 'Represents spatial relations in which two geometries (or the places they represent) have at least one point in common. As defined in <a href="https://en.wikipedia.org/wiki/DE-9IM" target="_blank" rel="noopener noreferrer">DE-9IM</a>.', schema_domain_includes: ['GeospatialGeometry', 'Place'], range_includes: ['GeospatialGeometry', 'Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case geoIntersects = 'geoIntersects';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates the GeoCoordinates at the centre of a GeoShape, e.g. GeoCircle.', schema_domain_includes: ['GeoCircle'], range_includes: ['GeoCoordinates', 'Role', 'Text', 'URL'], superseded_by: null)]
    case geoMidpoint = 'geoMidpoint';
    #[SchemaPropertyInfo(rdfs_comment: 'Represents a relationship between two geometries (or the places they represent), relating a geometry to another that geospatially overlaps it, i.e. they have some but not all points in common. As defined in <a href="https://en.wikipedia.org/wiki/DE-9IM" target="_blank" rel="noopener noreferrer">DE-9IM</a>.', schema_domain_includes: ['GeospatialGeometry', 'Place'], range_includes: ['GeospatialGeometry', 'Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case geoOverlaps = 'geoOverlaps';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates the approximate radius of a GeoCircle (metres unless indicated otherwise via Distance notation).', schema_domain_includes: ['GeoCircle'], range_includes: ['Distance', 'Number', 'Role', 'Text', 'URL'], superseded_by: null)]
    case geoRadius = 'geoRadius';
    #[SchemaPropertyInfo(rdfs_comment: 'Represents spatial relations in which two geometries (or the places they represent) touch: "they have at least one boundary point in common, but no interior points." (A symmetric relationship, as defined in <a href="https://en.wikipedia.org/wiki/DE-9IM" target="_blank" rel="noopener noreferrer">DE-9IM</a>.)', schema_domain_includes: ['GeospatialGeometry', 'Place'], range_includes: ['GeospatialGeometry', 'Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case geoTouches = 'geoTouches';
    #[SchemaPropertyInfo(rdfs_comment: 'Represents a relationship between two geometries (or the places they represent), relating a geometry to one that contains it, i.e. it is inside (i.e. within) its interior. As defined in <a href="https://en.wikipedia.org/wiki/DE-9IM" target="_blank" rel="noopener noreferrer">DE-9IM</a>.', schema_domain_includes: ['GeospatialGeometry', 'Place'], range_includes: ['GeospatialGeometry', 'Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case geoWithin = 'geoWithin';
    #[SchemaPropertyInfo(rdfs_comment: 'The geographic area associated with the audience.', schema_domain_includes: ['Audience'], range_includes: ['AdministrativeArea', 'Role', 'Text', 'URL'], superseded_by: null)]
    case geographicArea = 'geographicArea';
    #[SchemaPropertyInfo(rdfs_comment: 'Information about getting tested (for a <a href="https://schema.org/MedicalCondition" target="_blank" rel="noopener noreferrer">MedicalCondition</a>), e.g. in the context of a pandemic.', schema_domain_includes: ['SpecialAnnouncement'], range_includes: ['Role', 'Text', 'URL', 'WebContent'], superseded_by: null)]
    case gettingTestedInfo = 'gettingTestedInfo';
    #[SchemaPropertyInfo(rdfs_comment: 'Given name. In the U.S., the first name of a Person.', schema_domain_includes: ['Person'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case givenName = 'givenName';
    #[SchemaPropertyInfo(rdfs_comment: 'The <a href="http://www.gs1.org/gln" target="_blank" rel="noopener noreferrer">Global Location Number</a> (GLN, sometimes also referred to as International Location Number or ILN) of the respective organization, person, or place. The GLN is a 13-digit number used to identify parties and physical locations.', schema_domain_includes: ['Organization', 'Person', 'Place'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case globalLocationNumber = 'globalLocationNumber';
    #[SchemaPropertyInfo(rdfs_comment: 'governmentBenefitsInfo provides information about government benefits associated with a SpecialAnnouncement.', schema_domain_includes: ['SpecialAnnouncement'], range_includes: ['GovernmentService', 'Role', 'Text', 'URL'], superseded_by: null)]
    case governmentBenefitsInfo = 'governmentBenefitsInfo';
    #[SchemaPropertyInfo(rdfs_comment: 'The period of time after any due date that the borrower has to fulfil its obligations before a default (failure to pay) is deemed to have occurred.', schema_domain_includes: ['LoanOrCredit'], range_includes: ['Duration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case gracePeriod = 'gracePeriod';
    #[SchemaPropertyInfo(rdfs_comment: 'The person, organization, contact point, or audience that has been granted this permission.', schema_domain_includes: ['DigitalDocumentPermission'], range_includes: ['Audience', 'ContactPoint', 'Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case grantee = 'grantee';
    #[SchemaPropertyInfo(rdfs_comment: 'This ordering relation for qualitative values indicates that the subject is greater than the object.', schema_domain_includes: ['QualitativeValue'], range_includes: ['QualitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case greater = 'greater';
    #[SchemaPropertyInfo(rdfs_comment: 'This ordering relation for qualitative values indicates that the subject is greater than or equal to the object.', schema_domain_includes: ['QualitativeValue'], range_includes: ['QualitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case greaterOrEqual = 'greaterOrEqual';
    #[SchemaPropertyInfo(rdfs_comment: 'A Global Trade Item Number (<a href="https://www.gs1.org/standards/id-keys/gtin" target="_blank" rel="noopener noreferrer">GTIN</a>). GTINs identify trade items, including products and services, using numeric identification codes.

A correct <a href="https://schema.org/gtin" target="_blank" rel="noopener noreferrer">gtin</a> value should be a valid GTIN, which means that it should be an all-numeric string of either 8, 12, 13 or 14 digits, or a "GS1 Digital Link" URL based on such a string. The numeric component should also have a <a href="https://www.gs1.org/services/check-digit-calculator" target="_blank" rel="noopener noreferrer">valid GS1 check digit</a> and meet the other rules for valid GTINs. See also <a href="http://www.gs1.org/barcodes/technical/idkeys/gtin" target="_blank" rel="noopener noreferrer">GS1\'s GTIN Summary</a> and <a href="https://en.wikipedia.org/wiki/Global_Trade_Item_Number" target="_blank" rel="noopener noreferrer">Wikipedia</a> for more details. Left-padding of the gtin values is not required or encouraged. The <a href="https://schema.org/gtin" target="_blank" rel="noopener noreferrer">gtin</a> property generalizes the earlier <a href="https://schema.org/gtin8" target="_blank" rel="noopener noreferrer">gtin8</a>, <a href="https://schema.org/gtin12" target="_blank" rel="noopener noreferrer">gtin12</a>, <a href="https://schema.org/gtin13" target="_blank" rel="noopener noreferrer">gtin13</a>, and <a href="https://schema.org/gtin14" target="_blank" rel="noopener noreferrer">gtin14</a> properties.

The GS1 <a href="https://www.gs1.org/standards/Digital-Link/" target="_blank" rel="noopener noreferrer">digital link specifications</a> expresses GTINs as URLs (URIs, IRIs, etc.).
Digital Links should be populated into the <a href="https://schema.org/hasGS1DigitalLink" target="_blank" rel="noopener noreferrer">hasGS1DigitalLink</a> attribute.

Note also that this is a definition for how to include GTINs in Schema.org data, and not a definition of GTINs in general - see the GS1 documentation for authoritative details.', schema_domain_includes: ['Demand', 'Offer', 'Product'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case gtin = 'gtin';
    #[SchemaPropertyInfo(rdfs_comment: 'The GTIN-12 code of the product, or the product to which the offer refers. The GTIN-12 is the 12-digit GS1 Identification Key composed of a U.P.C. Company Prefix, Item Reference, and Check Digit used to identify trade items. See <a href="http://www.gs1.org/barcodes/technical/idkeys/gtin" target="_blank" rel="noopener noreferrer">GS1 GTIN Summary</a> for more details.', schema_domain_includes: ['Demand', 'Offer', 'Product'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case gtin12 = 'gtin12';
    #[SchemaPropertyInfo(rdfs_comment: 'The GTIN-13 code of the product, or the product to which the offer refers. This is equivalent to 13-digit ISBN codes and EAN UCC-13. Former 12-digit UPC codes can be converted into a GTIN-13 code by simply adding a preceding zero. See <a href="http://www.gs1.org/barcodes/technical/idkeys/gtin" target="_blank" rel="noopener noreferrer">GS1 GTIN Summary</a> for more details.', schema_domain_includes: ['Demand', 'Offer', 'Product'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case gtin13 = 'gtin13';
    #[SchemaPropertyInfo(rdfs_comment: 'The GTIN-14 code of the product, or the product to which the offer refers. See <a href="http://www.gs1.org/barcodes/technical/idkeys/gtin" target="_blank" rel="noopener noreferrer">GS1 GTIN Summary</a> for more details.', schema_domain_includes: ['Demand', 'Offer', 'Product'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case gtin14 = 'gtin14';
    #[SchemaPropertyInfo(rdfs_comment: 'The GTIN-8 code of the product, or the product to which the offer refers. This code is also known as EAN/UCC-8 or 8-digit EAN. See <a href="http://www.gs1.org/barcodes/technical/idkeys/gtin" target="_blank" rel="noopener noreferrer">GS1 GTIN Summary</a> for more details.', schema_domain_includes: ['Demand', 'Offer', 'Product'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case gtin8 = 'gtin8';
    #[SchemaPropertyInfo(rdfs_comment: 'A medical guideline related to this entity.', schema_domain_includes: ['MedicalEntity'], range_includes: ['MedicalGuideline', 'Role', 'Text', 'URL'], superseded_by: null)]
    case guideline = 'guideline';
    #[SchemaPropertyInfo(rdfs_comment: 'Date on which this guideline\'s recommendation was made.', schema_domain_includes: ['MedicalGuideline'], range_includes: ['Date'], superseded_by: null)]
    case guidelineDate = 'guidelineDate';
    #[SchemaPropertyInfo(rdfs_comment: 'The medical conditions, treatments, etc. that are the subject of the guideline.', schema_domain_includes: ['MedicalGuideline'], range_includes: ['MedicalEntity', 'Role', 'Text', 'URL'], superseded_by: null)]
    case guidelineSubject = 'guidelineSubject';
    #[SchemaPropertyInfo(rdfs_comment: 'The typical delay between the receipt of the order and the goods either leaving the warehouse or being prepared for pickup, in case the delivery method is on site pickup.

In the context of <a href="https://schema.org/ShippingDeliveryTime" target="_blank" rel="noopener noreferrer">ShippingDeliveryTime</a>, Typical properties: minValue, maxValue, unitCode (d for DAY).  This is by common convention assumed to mean business days (if a unitCode is used, coded as "d"), i.e. only counting days when the business normally operates.

In the context of <a href="https://schema.org/ShippingService" target="_blank" rel="noopener noreferrer">ShippingService</a>, use the <a href="https://schema.org/ServicePeriod" target="_blank" rel="noopener noreferrer">ServicePeriod</a> format, that contains the same information in a structured form, with cut-off time, business days and duration.', schema_domain_includes: ['ShippingDeliveryTime', 'ShippingService'], range_includes: ['QuantitativeValue', 'Role', 'ServicePeriod', 'Text', 'URL'], superseded_by: null)]
    case handlingTime = 'handlingTime';
    #[SchemaPropertyInfo(rdfs_comment: 'Used to tag an item to be intended or suitable for consumption or use by adults only.', schema_domain_includes: ['Offer', 'Product'], range_includes: ['AdultOrientedEnumeration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hasAdultConsideration = 'hasAdultConsideration';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates a BioChemEntity that (in some sense) has this BioChemEntity as a part.', schema_domain_includes: ['BioChemEntity'], range_includes: ['BioChemEntity', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hasBioChemEntityPart = 'hasBioChemEntityPart';
    #[SchemaPropertyInfo(rdfs_comment: 'A symbolic representation of a BioChemEntity. For example, a nucleotide sequence of a Gene or an amino acid sequence of a Protein.', schema_domain_includes: ['Gene', 'Protein'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case hasBioPolymerSequence = 'hasBioPolymerSequence';
    #[SchemaPropertyInfo(rdfs_comment: 'A broadcast channel of a broadcast service.', schema_domain_includes: ['BroadcastService'], range_includes: ['BroadcastChannel', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hasBroadcastChannel = 'hasBroadcastChannel';
    #[SchemaPropertyInfo(rdfs_comment: 'A Category code contained in this code set.', schema_domain_includes: ['CategoryCodeSet'], range_includes: ['CategoryCode', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hasCategoryCode = 'hasCategoryCode';
    #[SchemaPropertyInfo(rdfs_comment: 'Certification information about a product, organization, service, place, or person.', schema_domain_includes: ['Organization', 'Person', 'Place', 'Product', 'Service'], range_includes: ['Certification', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hasCertification = 'hasCertification';
    #[SchemaPropertyInfo(rdfs_comment: 'A course or class that is one of the learning opportunities that constitute an educational / occupational program. No information is implied about whether the course is mandatory or optional; no guarantee is implied about whether the course will be available to everyone on the program.', schema_domain_includes: ['EducationalOccupationalProgram'], range_includes: ['Course', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hasCourse = 'hasCourse';
    #[SchemaPropertyInfo(rdfs_comment: 'An offering of the course at a specific time and place or through specific media or mode of study or to a specific section of students.', schema_domain_includes: ['Course'], range_includes: ['CourseInstance', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hasCourseInstance = 'hasCourseInstance';
    #[SchemaPropertyInfo(rdfs_comment: 'A credential awarded to the Person or Organization.', schema_domain_includes: ['Organization', 'Person'], range_includes: ['Credential', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hasCredential = 'hasCredential';
    #[SchemaPropertyInfo(rdfs_comment: 'A Defined Term contained in this term set.', schema_domain_includes: ['DefinedTermSet', 'Taxon'], range_includes: ['DefinedTerm', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hasDefinedTerm = 'hasDefinedTerm';
    #[SchemaPropertyInfo(rdfs_comment: 'Method used for delivery or shipping.', schema_domain_includes: ['DeliveryEvent', 'ParcelDelivery'], range_includes: ['DeliveryMethod', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hasDeliveryMethod = 'hasDeliveryMethod';
    #[SchemaPropertyInfo(rdfs_comment: 'A permission related to the access to this document (e.g. permission to read or write an electronic document). For a public document, specify a grantee with an Audience with audienceType equal to "public".', schema_domain_includes: ['DigitalDocument'], range_includes: ['DigitalDocumentPermission', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hasDigitalDocumentPermission = 'hasDigitalDocumentPermission';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates whether some facility (e.g. <a href="https://schema.org/FoodEstablishment" target="_blank" rel="noopener noreferrer">FoodEstablishment</a>, <a href="https://schema.org/CovidTestingFacility" target="_blank" rel="noopener noreferrer">CovidTestingFacility</a>) offers a service that can be used by driving through in a car. In the case of <a href="https://schema.org/CovidTestingFacility" target="_blank" rel="noopener noreferrer">CovidTestingFacility</a> such facilities could potentially help with social distancing from other potentially-infected users.', schema_domain_includes: ['Place'], range_includes: ['Boolean'], superseded_by: null)]
    case hasDriveThroughService = 'hasDriveThroughService';
    #[SchemaPropertyInfo(rdfs_comment: 'Defines the energy efficiency Category (also known as "class" or "rating") for a product according to an international energy efficiency standard.', schema_domain_includes: ['Product'], range_includes: ['EnergyConsumptionDetails', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hasEnergyConsumptionDetails = 'hasEnergyConsumptionDetails';
    #[SchemaPropertyInfo(rdfs_comment: 'Defines the energy efficiency Category (which could be either a rating out of range of values or a yes/no certification) for a product according to an international energy efficiency standard.', schema_domain_includes: ['EnergyConsumptionDetails'], range_includes: ['EnergyEfficiencyEnumeration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hasEnergyEfficiencyCategory = 'hasEnergyEfficiencyCategory';
    #[SchemaPropertyInfo(rdfs_comment: 'The <a href="https://www.gs1.org/standards/gs1-digital-link">GS1 digital link</a> associated with the object. This URL should conform to the particular requirements of digital links. The link should only contain the Application Identifiers (AIs) that are relevant for the entity being annotated, for instance a [[Product]] or an [[Organization]], and for the correct granularity. In particular, for products:<ul><li>A Digital Link that contains a serial number (AI <code>21</code>) should only be present on instances of [[IndividualProduct]]</li><li>A Digital Link that contains a lot number (AI <code>10</code>) should be annotated as [[SomeProducts]] if only products from that lot are sold, or [[IndividualProduct]] if there is only a specific product.</li><li>A Digital Link that contains a global model number (AI <code>8013</code>) should be attached to a [[Product]] or a [[ProductModel]].</li></ul> Other item types should be adapted similarly.', schema_domain_includes: ['Offer', 'Organization', 'Place', 'Product'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case hasGS1DigitalLink = 'hasGS1DigitalLink';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates the aspect or aspects specifically addressed in some <a href="https://schema.org/HealthTopicContent" target="_blank" rel="noopener noreferrer">HealthTopicContent</a>. For example, that the content is an overview, or that it talks about treatment, self-care, treatments or their side-effects.', schema_domain_includes: ['HealthTopicContent'], range_includes: ['HealthAspectEnumeration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hasHealthAspect = 'hasHealthAspect';
    #[SchemaPropertyInfo(rdfs_comment: 'A URL to a map of the place.', schema_domain_includes: ['Place'], range_includes: ['Map', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hasMap = 'hasMap';
    #[SchemaPropertyInfo(rdfs_comment: 'A measurement of an item, For example, the inseam of pants, the wheel size of a bicycle, the gauge of a screw, or the carbon footprint measured for certification by an authority. Usually an exact measurement, but can also be a range of measurements for adjustable products, for example belts and ski bindings.', schema_domain_includes: ['Certification', 'Offer', 'Product', 'SizeSpecification'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hasMeasurement = 'hasMeasurement';
    #[SchemaPropertyInfo(rdfs_comment: 'MemberProgram offered by an Organization, for example an eCommerce merchant or an airline.', schema_domain_includes: ['Organization'], range_includes: ['MemberProgram', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hasMemberProgram = 'hasMemberProgram';
    #[SchemaPropertyInfo(rdfs_comment: 'Either the actual menu as a structured representation, as text, or a URL of the menu.', schema_domain_includes: ['FoodEstablishment'], range_includes: ['Menu', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hasMenu = 'hasMenu';
    #[SchemaPropertyInfo(rdfs_comment: 'A food or drink item contained in a menu or menu section.', schema_domain_includes: ['Menu', 'MenuSection'], range_includes: ['MenuItem', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hasMenuItem = 'hasMenuItem';
    #[SchemaPropertyInfo(rdfs_comment: 'A subgrouping of the menu (by dishes, course, serving time period, etc.).', schema_domain_includes: ['Menu', 'MenuSection'], range_includes: ['MenuSection', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hasMenuSection = 'hasMenuSection';
    #[SchemaPropertyInfo(rdfs_comment: 'Specifies a MerchantReturnPolicy that may be applicable.', schema_domain_includes: ['Offer', 'Organization', 'Product'], range_includes: ['MerchantReturnPolicy', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hasMerchantReturnPolicy = 'hasMerchantReturnPolicy';
    #[SchemaPropertyInfo(rdfs_comment: 'Molecular function performed by this BioChemEntity; please use PropertyValue if you want to include any evidence.', schema_domain_includes: ['BioChemEntity'], range_includes: ['DefinedTerm', 'PropertyValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hasMolecularFunction = 'hasMolecularFunction';
    #[SchemaPropertyInfo(rdfs_comment: 'The Person\'s occupation. For past professions, use Role for expressing dates.', schema_domain_includes: ['Person'], range_includes: ['Occupation', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hasOccupation = 'hasOccupation';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates an OfferCatalog listing for this Organization, Person, or Service.', schema_domain_includes: ['Organization', 'Person', 'Service'], range_includes: ['OfferCatalog', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hasOfferCatalog = 'hasOfferCatalog';
    #[SchemaPropertyInfo(rdfs_comment: 'Points-of-Sales operated by the organization or person.', schema_domain_includes: ['Organization', 'Person'], range_includes: ['Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hasPOS = 'hasPOS';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates an item or CreativeWork that is part of this item, or CreativeWork (in some sense).', schema_domain_includes: ['CreativeWork'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hasPart = 'hasPart';
    #[SchemaPropertyInfo(rdfs_comment: 'An offer to participate in the event, for example, Call for Proposals, Call for Speakers, or Call for Performers.', schema_domain_includes: ['Event'], range_includes: ['Offer', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hasParticipationOffer = 'hasParticipationOffer';
    #[SchemaPropertyInfo(rdfs_comment: 'A common representation such as a protein sequence or chemical structure for this entity. For images use schema.org/image.', schema_domain_includes: ['BioChemEntity'], range_includes: ['PropertyValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hasRepresentation = 'hasRepresentation';
    #[SchemaPropertyInfo(rdfs_comment: 'Specification of a shipping service offered by the organization.', schema_domain_includes: ['OfferShippingDetails', 'Organization'], range_includes: ['Role', 'ShippingService', 'Text', 'URL'], superseded_by: null)]
    case hasShippingService = 'hasShippingService';
    #[SchemaPropertyInfo(rdfs_comment: 'An offer to sponsor the event, for example, Sponsorship Prospectus, Sponsorship Opportunities, or Sponsor Packages.', schema_domain_includes: ['Event'], range_includes: ['Offer', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hasSponsorshipOffer = 'hasSponsorshipOffer';
    #[SchemaPropertyInfo(rdfs_comment: 'An eCommerce store part of an online marketplace.', schema_domain_includes: ['OnlineMarketplace'], range_includes: ['OnlineStore', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hasStore = 'hasStore';
    #[SchemaPropertyInfo(rdfs_comment: 'A member benefit for a particular tier of a loyalty program.', schema_domain_includes: ['MemberProgramTier'], range_includes: ['Role', 'Text', 'TierBenefitEnumeration', 'URL'], superseded_by: null)]
    case hasTierBenefit = 'hasTierBenefit';
    #[SchemaPropertyInfo(rdfs_comment: 'A requirement for a user to join a membership tier, for example: a CreditCard if the tier requires sign up for a credit card, A UnitPriceSpecification if the user is required to pay a (periodic) fee, or a MonetaryAmount if the user needs to spend a minimum amount to join the tier. If a tier is free to join then this property does not need to be specified.', schema_domain_includes: ['MemberProgramTier'], range_includes: ['CreditCard', 'MonetaryAmount', 'Role', 'Text', 'URL', 'UnitPriceSpecification'], superseded_by: null)]
    case hasTierRequirement = 'hasTierRequirement';
    #[SchemaPropertyInfo(rdfs_comment: 'The tiers of a member program.', schema_domain_includes: ['MemberProgram'], range_includes: ['MemberProgramTier', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hasTiers = 'hasTiers';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates a <a href="https://schema.org/Product" target="_blank" rel="noopener noreferrer">Product</a> that is a member of this <a href="https://schema.org/ProductGroup" target="_blank" rel="noopener noreferrer">ProductGroup</a> (or <a href="https://schema.org/ProductModel" target="_blank" rel="noopener noreferrer">ProductModel</a>).', schema_domain_includes: ['ProductGroup'], range_includes: ['Product', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hasVariant = 'hasVariant';
    #[SchemaPropertyInfo(rdfs_comment: 'Headline of the article.', schema_domain_includes: ['CreativeWork'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case headline = 'headline';
    #[SchemaPropertyInfo(rdfs_comment: 'Specifying the health condition(s) of a patient, medical study, or other target audience.', schema_domain_includes: ['MedicalStudy', 'Patient', 'PeopleAudience'], range_includes: ['MedicalCondition', 'Role', 'Text', 'URL'], superseded_by: null)]
    case healthCondition = 'healthCondition';
    #[SchemaPropertyInfo(rdfs_comment: 'Whether the coinsurance applies before or after deductible, etc. TODO: Is this a closed set?', schema_domain_includes: ['HealthPlanCostSharingSpecification'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case healthPlanCoinsuranceOption = 'healthPlanCoinsuranceOption';
    #[SchemaPropertyInfo(rdfs_comment: 'The rate of coinsurance expressed as a number between 0.0 and 1.0.', schema_domain_includes: ['HealthPlanCostSharingSpecification'], range_includes: ['Number'], superseded_by: null)]
    case healthPlanCoinsuranceRate = 'healthPlanCoinsuranceRate';
    #[SchemaPropertyInfo(rdfs_comment: 'The copay amount.', schema_domain_includes: ['HealthPlanCostSharingSpecification'], range_includes: ['PriceSpecification', 'Role', 'Text', 'URL'], superseded_by: null)]
    case healthPlanCopay = 'healthPlanCopay';
    #[SchemaPropertyInfo(rdfs_comment: 'Whether the copay is before or after deductible, etc. TODO: Is this a closed set?', schema_domain_includes: ['HealthPlanCostSharingSpecification'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case healthPlanCopayOption = 'healthPlanCopayOption';
    #[SchemaPropertyInfo(rdfs_comment: 'The costs to the patient for services under this network or formulary.', schema_domain_includes: ['HealthPlanFormulary', 'HealthPlanNetwork'], range_includes: ['Boolean', 'HealthPlanCostSharingSpecification', 'Role', 'Text', 'URL'], superseded_by: null)]
    case healthPlanCostSharing = 'healthPlanCostSharing';
    #[SchemaPropertyInfo(rdfs_comment: 'TODO.', schema_domain_includes: ['HealthInsurancePlan'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case healthPlanDrugOption = 'healthPlanDrugOption';
    #[SchemaPropertyInfo(rdfs_comment: 'The tier(s) of drugs offered by this formulary or insurance plan.', schema_domain_includes: ['HealthInsurancePlan', 'HealthPlanFormulary'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case healthPlanDrugTier = 'healthPlanDrugTier';
    #[SchemaPropertyInfo(rdfs_comment: 'The 14-character, HIOS-generated Plan ID number. (Plan IDs must be unique, even across different markets.)', schema_domain_includes: ['HealthInsurancePlan'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case healthPlanId = 'healthPlanId';
    #[SchemaPropertyInfo(rdfs_comment: 'The URL that goes directly to the plan brochure for the specific standard plan or plan variation.', schema_domain_includes: ['HealthInsurancePlan'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case healthPlanMarketingUrl = 'healthPlanMarketingUrl';
    #[SchemaPropertyInfo(rdfs_comment: 'Name or unique ID of network. (Networks are often reused across different insurance plans.)', schema_domain_includes: ['HealthPlanNetwork', 'MedicalOrganization'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case healthPlanNetworkId = 'healthPlanNetworkId';
    #[SchemaPropertyInfo(rdfs_comment: 'The tier(s) for this network.', schema_domain_includes: ['HealthPlanNetwork'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case healthPlanNetworkTier = 'healthPlanNetworkTier';
    #[SchemaPropertyInfo(rdfs_comment: 'The category or type of pharmacy associated with this cost sharing.', schema_domain_includes: ['HealthPlanCostSharingSpecification'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case healthPlanPharmacyCategory = 'healthPlanPharmacyCategory';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates data describing a hospital, e.g. a CDC <a href="https://schema.org/CDCPMDRecord" target="_blank" rel="noopener noreferrer">CDCPMDRecord</a> or as some kind of <a href="https://schema.org/Dataset" target="_blank" rel="noopener noreferrer">Dataset</a>.', schema_domain_includes: ['Hospital'], range_includes: ['CDCPMDRecord', 'Dataset', 'Role', 'Text', 'URL'], superseded_by: null)]
    case healthcareReportingData = 'healthcareReportingData';
    #[SchemaPropertyInfo(rdfs_comment: 'The height of the item.', schema_domain_includes: ['MediaObject', 'OfferShippingDetails', 'Person', 'Product', 'ShippingConditions', 'VisualArtwork'], range_includes: ['Distance', 'QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case height = 'height';
    #[SchemaPropertyInfo(rdfs_comment: 'The highest price of all offers available.\\n\\nUsage guidelines:\\n\\n* Use values from 0123456789 (Unicode \'DIGIT ZERO\' (U+0030) to \'DIGIT NINE\' (U+0039)) rather than superficially similar Unicode symbols.\\n* Use \'.\' (Unicode \'FULL STOP\' (U+002E)) rather than \',\' to indicate a decimal point. Avoid using these symbols as a readability separator.', schema_domain_includes: ['AggregateOffer'], range_includes: ['Number', 'Role', 'Text', 'URL'], superseded_by: null)]
    case highPrice = 'highPrice';
    #[SchemaPropertyInfo(rdfs_comment: 'Organization or Person offering the job position.', schema_domain_includes: ['JobPosting'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hiringOrganization = 'hiringOrganization';
    #[SchemaPropertyInfo(rdfs_comment: 'en', schema_domain_includes: ['ArchiveComponent'], range_includes: ['ArchiveOrganization', 'Role', 'Text', 'URL'], superseded_by: null)]
    case holdingArchive = 'holdingArchive';
    #[SchemaPropertyInfo(rdfs_comment: 'A contact location for a person\'s residence.', schema_domain_includes: ['Person'], range_includes: ['ContactPoint', 'Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case homeLocation = 'homeLocation';
    #[SchemaPropertyInfo(rdfs_comment: 'The home team in a sports event.', schema_domain_includes: ['SportsEvent'], range_includes: ['Person', 'Role', 'SportsTeam', 'Text', 'URL'], superseded_by: null)]
    case homeTeam = 'homeTeam';
    #[SchemaPropertyInfo(rdfs_comment: 'An honorific prefix preceding a Person\'s name such as Dr/Mrs/Mr.', schema_domain_includes: ['Person'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case honorificPrefix = 'honorificPrefix';
    #[SchemaPropertyInfo(rdfs_comment: 'An honorific suffix following a Person\'s name such as M.D./PhD/MSCSW.', schema_domain_includes: ['Person'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case honorificSuffix = 'honorificSuffix';
    #[SchemaPropertyInfo(rdfs_comment: 'A hospital with which the physician or office is affiliated.', schema_domain_includes: ['Physician'], range_includes: ['Hospital', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hospitalAffiliation = 'hospitalAffiliation';
    #[SchemaPropertyInfo(rdfs_comment: 'The Organization (airline, travelers\' club, retailer, etc.) the membership is made with or which offers the  MemberProgram.', schema_domain_includes: ['MemberProgram', 'ProgramMembership'], range_includes: ['Organization', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hostingOrganization = 'hostingOrganization';
    #[SchemaPropertyInfo(rdfs_comment: 'The hours during which this service or contact is available.', schema_domain_includes: ['ContactPoint', 'LocationFeatureSpecification', 'Service'], range_includes: ['OpeningHoursSpecification', 'Role', 'Text', 'URL'], superseded_by: null)]
    case hoursAvailable = 'hoursAvailable';
    #[SchemaPropertyInfo(rdfs_comment: 'How the procedure is performed.', schema_domain_includes: ['MedicalProcedure'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case howPerformed = 'howPerformed';
    #[SchemaPropertyInfo(rdfs_comment: 'An HTTP method that specifies the appropriate HTTP method for a request to an HTTP EntryPoint. Values are capitalized strings as used in HTTP.', schema_domain_includes: ['EntryPoint'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case httpMethod = 'httpMethod';
    #[SchemaPropertyInfo(rdfs_comment: 'IATA identifier for an airline or airport.', schema_domain_includes: ['Airline', 'Airport'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case iataCode = 'iataCode';
    #[SchemaPropertyInfo(rdfs_comment: 'ICAO identifier for an airport.', schema_domain_includes: ['Airport'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case icaoCode = 'icaoCode';
    #[SchemaPropertyInfo(rdfs_comment: 'The identifier property represents any kind of identifier for any kind of <a href="https://schema.org/Thing" target="_blank" rel="noopener noreferrer">Thing</a>, such as ISBNs, GTIN codes, UUIDs etc. Schema.org provides dedicated properties for representing many of these, either as textual strings or as URL (URI) links. See <a href="https://schema.org/docs/datamodel.html#identifierBg" target="_blank" rel="noopener noreferrer">background notes</a> for more details.', schema_domain_includes: ['Thing'], range_includes: ['PropertyValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case identifier = 'identifier';
    #[SchemaPropertyInfo(rdfs_comment: 'A physical examination that can identify this sign.', schema_domain_includes: ['MedicalSign'], range_includes: ['PhysicalExam', 'Role', 'Text', 'URL'], superseded_by: null)]
    case identifyingExam = 'identifyingExam';
    #[SchemaPropertyInfo(rdfs_comment: 'A diagnostic test that can identify this sign.', schema_domain_includes: ['MedicalSign'], range_includes: ['MedicalTest', 'Role', 'Text', 'URL'], superseded_by: null)]
    case identifyingTest = 'identifyingTest';
    #[SchemaPropertyInfo(rdfs_comment: 'The illustrator of the book.', schema_domain_includes: ['Book'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case illustrator = 'illustrator';
    #[SchemaPropertyInfo(rdfs_comment: 'An image of the item. This can be a <a href="https://schema.org/URL" target="_blank" rel="noopener noreferrer">URL</a> or a fully described <a href="https://schema.org/ImageObject" target="_blank" rel="noopener noreferrer">ImageObject</a>.', schema_domain_includes: ['Thing'], range_includes: ['ImageObject', 'Role', 'Text', 'URL'], superseded_by: null)]
    case image = 'image';
    #[SchemaPropertyInfo(rdfs_comment: 'Imaging technique used.', schema_domain_includes: ['ImagingTest'], range_includes: ['MedicalImagingTechnique', 'Role', 'Text', 'URL'], superseded_by: null)]
    case imagingTechnique = 'imagingTechnique';
    #[SchemaPropertyInfo(rdfs_comment: 'The album to which this recording belongs.', schema_domain_includes: ['MusicRecording'], range_includes: ['MusicAlbum', 'Role', 'Text', 'URL'], superseded_by: null)]
    case inAlbum = 'inAlbum';
    #[SchemaPropertyInfo(rdfs_comment: 'The CableOrSatelliteService offering the channel.', schema_domain_includes: ['BroadcastChannel'], range_includes: ['CableOrSatelliteService', 'Role', 'Text', 'URL'], superseded_by: null)]
    case inBroadcastLineup = 'inBroadcastLineup';
    #[SchemaPropertyInfo(rdfs_comment: 'Non-proprietary identifier for molecular entity that can be used in printed and electronic data sources thus enabling easier linking of diverse data compilations.', schema_domain_includes: ['MolecularEntity'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case inChI = 'inChI';
    #[SchemaPropertyInfo(rdfs_comment: 'InChIKey is a hashed version of the full InChI (using the SHA-256 algorithm).', schema_domain_includes: ['MolecularEntity'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case inChIKey = 'inChIKey';
    #[SchemaPropertyInfo(rdfs_comment: 'A <a href="https://schema.org/CategoryCodeSet" target="_blank" rel="noopener noreferrer">CategoryCodeSet</a> that contains this category code.', schema_domain_includes: ['CategoryCode'], range_includes: ['CategoryCodeSet', 'Role', 'Text', 'URL'], superseded_by: null)]
    case inCodeSet = 'inCodeSet';
    #[SchemaPropertyInfo(rdfs_comment: 'A <a href="https://schema.org/DefinedTermSet" target="_blank" rel="noopener noreferrer">DefinedTermSet</a> that contains this term.', schema_domain_includes: ['DefinedTerm'], range_includes: ['DefinedTermSet', 'Role', 'Text', 'URL'], superseded_by: null)]
    case inDefinedTermSet = 'inDefinedTermSet';
    #[SchemaPropertyInfo(rdfs_comment: 'The language of the content or performance or used in an action. Please use one of the language codes from the <a href="http://tools.ietf.org/html/bcp47" target="_blank" rel="noopener noreferrer">IETF BCP 47 standard</a>. See also <a href="https://schema.org/availableLanguage" target="_blank" rel="noopener noreferrer">availableLanguage</a>.', schema_domain_includes: ['BroadcastService', 'CommunicateAction', 'CreativeWork', 'Event', 'LinkRole', 'PronounceableText', 'WriteAction'], range_includes: ['Language', 'Role', 'Text', 'URL'], superseded_by: null)]
    case inLanguage = 'inLanguage';
    #[SchemaPropertyInfo(rdfs_comment: 'The playlist to which this recording belongs.', schema_domain_includes: ['MusicRecording'], range_includes: ['MusicPlaylist', 'Role', 'Text', 'URL'], superseded_by: null)]
    case inPlaylist = 'inPlaylist';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates the <a href="https://schema.org/productGroupID" target="_blank" rel="noopener noreferrer">productGroupID</a> for a <a href="https://schema.org/ProductGroup" target="_blank" rel="noopener noreferrer">ProductGroup</a> that this product <a href="https://schema.org/isVariantOf" target="_blank" rel="noopener noreferrer">isVariantOf</a>.', schema_domain_includes: ['Product'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case inProductGroupWithID = 'inProductGroupWithID';
    #[SchemaPropertyInfo(rdfs_comment: 'Are in-store returns offered? (For more advanced return methods use the <a href="https://schema.org/returnMethod" target="_blank" rel="noopener noreferrer">returnMethod</a> property.)', schema_domain_includes: ['MerchantReturnPolicy'], range_includes: ['Boolean'], superseded_by: null)]
    case inStoreReturnsOffered = 'inStoreReturnsOffered';
    #[SchemaPropertyInfo(rdfs_comment: 'Qualification, candidature, degree, application that Thesis supports.', schema_domain_includes: ['Thesis'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case inSupportOf = 'inSupportOf';
    #[SchemaPropertyInfo(rdfs_comment: 'Describes the amount that can be redeemed from this incentive.
    
<p><a href="https://schema.org/QuantitativeValue" target="_blank" rel="noopener noreferrer">QuantitativeValue</a>: Use this for incentives based on price (either raw amount or percentage-based). For a raw amount example, "You can claim $2,500 - $7,500 from the total cost of installation" would be represented as the following:</p>
    {
        "@type": "QuantitativeValue",
        “minValue”: 2500,
        “maxValue”: 7500,
        "unitCode": "USD"
    }
<p><a href="https://schema.org/QuantitativeValue" target="_blank" rel="noopener noreferrer">QuantitativeValue</a> can also be used for percentage amounts. In such cases, value is used to represent the incentive’s percentage, while maxValue represents a limit (if one exists) to that incentive. The unitCode should be \'P1\' and the unitText should be \'%\', while valueReference should be used for holding the currency type. For example, "You can claim up to 30% of the total cost of installation, up to a maximum of $7,500" would be:</p>
    {
        "@type": "QuantitativeValue",
        "value": 30,
        "unitCode": "P1",
        "unitText": "%",
        “maxValue”: 7500,
        “valueReference”: “USD”
    }
<p><a href="https://schema.org/UnitPriceSpecification" target="_blank" rel="noopener noreferrer">UnitPriceSpecification</a>: Use this for incentives that are based on amounts rather than price. For example, a net metering rebate that pays $10/kWh, up to $1,000:</p>
    {
        "@type": "UnitPriceSpecification",
        "price": 10,
        "priceCurrency": "USD",
        "referenceQuantity": 1,
        "unitCode": "DO3",
        "unitText": "kw/h",
        "maxPrice": 1000,
        "description": "$10 / kwh up to $1000"
    }
<p><a href="https://schema.org/LoanOrCredit" target="_blank" rel="noopener noreferrer">LoanOrCredit</a>: Use for incentives that are loan based. For example, a loan of $4,000 - $50,000 with a repayment term of 10 years, interest free would look like:</p>
    {
        "@type": "LoanOrCredit",
        "loanTerm": {
                "@type":"QuantitativeValue",
                "value":"10",
                "unitCode": "ANN"
            },
        "amount":[
            {
                "@type": "QuantitativeValue",
                "Name":"fixed interest rate",
                "value":"0",
            },
        ],
        "amount":[
            {
                "@type": "MonetaryAmount",
                "Name":"min loan amount",
                "value":"4000",
                "currency":"CAD"
            },
            {
                "@type": "MonetaryAmount",
                "Name":"max loan amount",
                "value":"50000",
                "currency":"CAD"
            }
        ],
    }

In summary: <ul><li>Use <a href="https://schema.org/QuantitativeValue" target="_blank" rel="noopener noreferrer">QuantitativeValue</a> for absolute/percentage-based incentives applied on the price of a good/service.</li>
<li>Use <a href="https://schema.org/UnitPriceSpecification" target="_blank" rel="noopener noreferrer">UnitPriceSpecification</a> for incentives based on a per-unit basis (e.g. net metering).</li>
<li>Use <a href="https://schema.org/LoanOrCredit" target="_blank" rel="noopener noreferrer">LoanOrCredit</a> for loans/credits.</li>
</ul>.', schema_domain_includes: ['FinancialIncentive'], range_includes: ['LoanOrCredit', 'QuantitativeValue', 'Role', 'Text', 'URL', 'UnitPriceSpecification'], superseded_by: null)]
    case incentiveAmount = 'incentiveAmount';
    #[SchemaPropertyInfo(rdfs_comment: 'Description of bonus and commission compensation aspects of the job.', schema_domain_includes: ['JobPosting'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case incentiveCompensation = 'incentiveCompensation';
    #[SchemaPropertyInfo(rdfs_comment: 'The status of the incentive (active, on hold, retired, etc.).', schema_domain_includes: ['FinancialIncentive'], range_includes: ['IncentiveStatus', 'Role', 'Text', 'URL'], superseded_by: null)]
    case incentiveStatus = 'incentiveStatus';
    #[SchemaPropertyInfo(rdfs_comment: 'The type of incentive offered (tax credit/rebate, tax deduction, tax waiver, subsidies, etc.).', schema_domain_includes: ['FinancialIncentive'], range_includes: ['IncentiveType', 'Role', 'Text', 'URL'], superseded_by: null)]
    case incentiveType = 'incentiveType';
    #[SchemaPropertyInfo(rdfs_comment: 'Description of bonus and commission compensation aspects of the job.', schema_domain_includes: ['JobPosting'], range_includes: ['Role', 'Text', 'URL'], superseded_by: 'incentiveCompensation')]
    case incentives = 'incentives';
    #[SchemaPropertyInfo(rdfs_comment: 'The type or specific product(s) and/or service(s) being incentivized.
<p>DefinedTermSets are used for product and service categories such as the United Nations Standard Products and Services Code:</p>
    {
        "@type": "DefinedTerm",
        "inDefinedTermSet": "https://www.unspsc.org/",
        "termCode": "261315XX",
        "name": "Photovoltaic module"
    }

<p>For a specific product or service, use the Product type:</p>
    {
        "@type": "Product",
        "name": "Kenmore White 17" Microwave",
    }
For multiple different incentivized items, use multiple <a href="https://schema.org/DefinedTerm" target="_blank" rel="noopener noreferrer">DefinedTerm</a> or <a href="https://schema.org/Product" target="_blank" rel="noopener noreferrer">Product</a>.', schema_domain_includes: ['FinancialIncentive'], range_includes: ['DefinedTerm', 'Product', 'Role', 'Text', 'URL'], superseded_by: null)]
    case incentivizedItem = 'incentivizedItem';
    #[SchemaPropertyInfo(rdfs_comment: 'Smaller compositions included in this work (e.g. a movement in a symphony).', schema_domain_includes: ['MusicComposition'], range_includes: ['MusicComposition', 'Role', 'Text', 'URL'], superseded_by: null)]
    case includedComposition = 'includedComposition';
    #[SchemaPropertyInfo(rdfs_comment: 'A data catalog which contains this dataset (this property was previously \'catalog\', preferred name is now \'includedInDataCatalog\').', schema_domain_includes: ['Dataset'], range_includes: ['DataCatalog', 'Role', 'Text', 'URL'], superseded_by: 'includedInDataCatalog')]
    case includedDataCatalog = 'includedDataCatalog';
    #[SchemaPropertyInfo(rdfs_comment: 'A data catalog which contains this dataset.', schema_domain_includes: ['Dataset'], range_includes: ['DataCatalog', 'Role', 'Text', 'URL'], superseded_by: null)]
    case includedInDataCatalog = 'includedInDataCatalog';
    #[SchemaPropertyInfo(rdfs_comment: 'The insurance plans that cover this drug.', schema_domain_includes: ['Drug'], range_includes: ['HealthInsurancePlan', 'Role', 'Text', 'URL'], superseded_by: null)]
    case includedInHealthInsurancePlan = 'includedInHealthInsurancePlan';
    #[SchemaPropertyInfo(rdfs_comment: 'A modifiable or non-modifiable risk factor included in the calculation, e.g. age, coexisting condition.', schema_domain_includes: ['MedicalRiskEstimator'], range_includes: ['MedicalRiskFactor', 'Role', 'Text', 'URL'], superseded_by: null)]
    case includedRiskFactor = 'includedRiskFactor';
    #[SchemaPropertyInfo(rdfs_comment: 'Attraction located at destination.', schema_domain_includes: ['TouristDestination'], range_includes: ['Role', 'Text', 'TouristAttraction', 'URL'], superseded_by: null)]
    case includesAttraction = 'includesAttraction';
    #[SchemaPropertyInfo(rdfs_comment: 'Formularies covered by this plan.', schema_domain_includes: ['HealthInsurancePlan'], range_includes: ['HealthPlanFormulary', 'Role', 'Text', 'URL'], superseded_by: null)]
    case includesHealthPlanFormulary = 'includesHealthPlanFormulary';
    #[SchemaPropertyInfo(rdfs_comment: 'Networks covered by this plan.', schema_domain_includes: ['HealthInsurancePlan'], range_includes: ['HealthPlanNetwork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case includesHealthPlanNetwork = 'includesHealthPlanNetwork';
    #[SchemaPropertyInfo(rdfs_comment: 'This links to a node or nodes indicating the exact quantity of the products included in  an <a href="https://schema.org/Offer" target="_blank" rel="noopener noreferrer">Offer</a> or <a href="https://schema.org/ProductCollection" target="_blank" rel="noopener noreferrer">ProductCollection</a>.', schema_domain_includes: ['Demand', 'Offer', 'ProductCollection'], range_includes: ['Role', 'Text', 'TypeAndQuantityNode', 'URL'], superseded_by: null)]
    case includesObject = 'includesObject';
    #[SchemaPropertyInfo(rdfs_comment: 'Optional. Income limit for which the incentive is applicable for.
    
<p>If MonetaryAmount is specified, this should be based on annualized income (e.g. if an incentive is limited to those making <$114,000 annually):</p>
    {
        "@type": "MonetaryAmount",
        "maxValue": 114000,
        "currency": "USD",
    }

Use Text for incentives that are limited based on other criteria, for example if an incentive is only available to recipients making 120% of the median poverty income in their area.', schema_domain_includes: ['FinancialIncentive'], range_includes: ['MonetaryAmount', 'Role', 'Text', 'URL'], superseded_by: null)]
    case incomeLimit = 'incomeLimit';
    #[SchemaPropertyInfo(rdfs_comment: 'The condition, complication, etc. influenced by this factor.', schema_domain_includes: ['MedicalRiskFactor'], range_includes: ['MedicalEntity', 'Role', 'Text', 'URL'], superseded_by: null)]
    case increasesRiskOf = 'increasesRiskOf';
    #[SchemaPropertyInfo(rdfs_comment: 'The industry associated with the job position.', schema_domain_includes: ['JobPosting'], range_includes: ['DefinedTerm', 'Role', 'Text', 'URL'], superseded_by: null)]
    case industry = 'industry';
    #[SchemaPropertyInfo(rdfs_comment: 'The ISO 3166-1 (ISO 3166-1 alpha-2) or ISO 3166-2 code, the place, or the GeoShape for the geo-political region(s) for which the offer or delivery charge specification is not valid, e.g. a region where the transaction is not allowed.\\n\\nSee also <a href="https://schema.org/eligibleRegion" target="_blank" rel="noopener noreferrer">eligibleRegion</a>.', schema_domain_includes: ['ActionAccessSpecification', 'DeliveryChargeSpecification', 'Demand', 'MediaObject', 'Offer'], range_includes: ['GeoShape', 'Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case ineligibleRegion = 'ineligibleRegion';
    #[SchemaPropertyInfo(rdfs_comment: 'The actual infectious agent, such as a specific bacterium.', schema_domain_includes: ['InfectiousDisease'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case infectiousAgent = 'infectiousAgent';
    #[SchemaPropertyInfo(rdfs_comment: 'The class of infectious agent (bacteria, prion, etc.) that causes the disease.', schema_domain_includes: ['InfectiousDisease'], range_includes: ['InfectiousAgentClass', 'Role', 'Text', 'URL'], superseded_by: null)]
    case infectiousAgentClass = 'infectiousAgentClass';
    #[SchemaPropertyInfo(rdfs_comment: 'A single ingredient used in the recipe, e.g. sugar, flour or garlic.', schema_domain_includes: ['Recipe'], range_includes: ['Role', 'Text', 'URL'], superseded_by: 'recipeIngredient')]
    case ingredients = 'ingredients';
    #[SchemaPropertyInfo(rdfs_comment: 'The individual who traces over the pencil drawings in ink after pencils are complete.', schema_domain_includes: ['ComicIssue', 'ComicStory', 'VisualArtwork'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case inker = 'inker';
    #[SchemaPropertyInfo(rdfs_comment: 'The place of attachment of a muscle, or what the muscle moves.', schema_domain_includes: ['Muscle'], range_includes: ['AnatomicalStructure', 'Role', 'Text', 'URL'], superseded_by: null)]
    case insertion = 'insertion';
    #[SchemaPropertyInfo(rdfs_comment: 'URL at which the app may be installed, if different from the URL of the item.', schema_domain_includes: ['SoftwareApplication'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case installUrl = 'installUrl';
    #[SchemaPropertyInfo(rdfs_comment: 'A person assigned to instruct or provide instructional assistance for the <a href="https://schema.org/CourseInstance" target="_blank" rel="noopener noreferrer">CourseInstance</a>.', schema_domain_includes: ['CourseInstance'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case instructor = 'instructor';
    #[SchemaPropertyInfo(rdfs_comment: 'The object that helped the agent perform the action. E.g. John wrote a book with *a pen*.', schema_domain_includes: ['Action'], range_includes: ['Role', 'Text', 'Thing', 'URL'], superseded_by: null)]
    case instrument = 'instrument';
    #[SchemaPropertyInfo(rdfs_comment: 'Quantitative measure gauging the degree of force involved in the exercise, for example, heartbeats per minute. May include the velocity of the movement.', schema_domain_includes: ['ExercisePlan'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case intensity = 'intensity';
    #[SchemaPropertyInfo(rdfs_comment: 'Another drug that is known to interact with this drug in a way that impacts the effect of this drug or causes a risk to the patient. Note: disease interactions are typically captured as contraindications.', schema_domain_includes: ['Drug'], range_includes: ['Drug', 'Role', 'Text', 'URL'], superseded_by: null)]
    case interactingDrug = 'interactingDrug';
    #[SchemaPropertyInfo(rdfs_comment: 'This property is deprecated, alongside the UserInteraction types on which it depended.', schema_domain_includes: [], range_includes: [], superseded_by: 'interactionStatistic')]
    case interactionCount = 'interactionCount';
    #[SchemaPropertyInfo(rdfs_comment: 'The WebSite or SoftwareApplication where the interactions took place.', schema_domain_includes: ['InteractionCounter'], range_includes: ['Role', 'SoftwareApplication', 'Text', 'URL', 'WebSite'], superseded_by: null)]
    case interactionService = 'interactionService';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of interactions for the CreativeWork using the WebSite or SoftwareApplication. The most specific child type of InteractionCounter should be used.', schema_domain_includes: ['CreativeWork', 'Organization', 'Person'], range_includes: ['InteractionCounter', 'Role', 'Text', 'URL'], superseded_by: null)]
    case interactionStatistic = 'interactionStatistic';
    #[SchemaPropertyInfo(rdfs_comment: 'The Action representing the type of interaction. For up votes, +1s, etc. use <a href="https://schema.org/LikeAction" target="_blank" rel="noopener noreferrer">LikeAction</a>. For down votes use <a href="https://schema.org/DislikeAction" target="_blank" rel="noopener noreferrer">DislikeAction</a>. Otherwise, use the most specific Action.', schema_domain_includes: ['InteractionCounter'], range_includes: ['Action', 'Role', 'Text', 'URL'], superseded_by: null)]
    case interactionType = 'interactionType';
    #[SchemaPropertyInfo(rdfs_comment: 'The predominant mode of learning supported by the learning resource. Acceptable values are \'active\', \'expositive\', or \'mixed\'.', schema_domain_includes: ['CreativeWork'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case interactivityType = 'interactivityType';
    #[SchemaPropertyInfo(rdfs_comment: 'The interest rate, charged or paid, applicable to the financial product. Note: This is different from the calculated annualPercentageRate.', schema_domain_includes: ['FinancialProduct'], range_includes: ['Number', 'QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case interestRate = 'interestRate';
    #[SchemaPropertyInfo(rdfs_comment: 'Used to indicate a specific claim contained, implied, translated or refined from the content of a <a href="https://schema.org/MediaObject" target="_blank" rel="noopener noreferrer">MediaObject</a> or other <a href="https://schema.org/CreativeWork" target="_blank" rel="noopener noreferrer">CreativeWork</a>. The interpreting party can be indicated using <a href="https://schema.org/claimInterpreter" target="_blank" rel="noopener noreferrer">claimInterpreter</a>.', schema_domain_includes: ['CreativeWork', 'MediaObject'], range_includes: ['Claim', 'Role', 'Text', 'URL'], superseded_by: null)]
    case interpretedAsClaim = 'interpretedAsClaim';
    #[SchemaPropertyInfo(rdfs_comment: 'The current approximate inventory level for the item or items.', schema_domain_includes: ['Demand', 'Offer', 'SomeProducts'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case inventoryLevel = 'inventoryLevel';
    #[SchemaPropertyInfo(rdfs_comment: 'Relates a property to a property that is its inverse. Inverse properties relate the same pairs of items to each other, but in reversed direction. For example, the \'alumni\' and \'alumniOf\' properties are inverseOf each other. Some properties don\'t have explicit inverses; in these situations RDFa and JSON-LD syntax for reverse properties can be used.', schema_domain_includes: ['Property'], range_includes: ['Property', 'Role', 'Text', 'URL'], superseded_by: null)]
    case inverseOf = 'inverseOf';
    #[SchemaPropertyInfo(rdfs_comment: 'Whether the provider is accepting new patients.', schema_domain_includes: ['MedicalOrganization'], range_includes: ['Boolean'], superseded_by: null)]
    case isAcceptingNewPatients = 'isAcceptingNewPatients';
    #[SchemaPropertyInfo(rdfs_comment: 'A flag to signal that the item, event, or place is accessible for free.', schema_domain_includes: ['CreativeWork', 'Event', 'Place'], range_includes: ['Boolean'], superseded_by: null)]
    case isAccessibleForFree = 'isAccessibleForFree';
    #[SchemaPropertyInfo(rdfs_comment: 'A pointer to another product (or multiple products) for which this product is an accessory or spare part.', schema_domain_includes: ['Product'], range_includes: ['Product', 'Role', 'Text', 'URL'], superseded_by: null)]
    case isAccessoryOrSparePartFor = 'isAccessoryOrSparePartFor';
    #[SchemaPropertyInfo(rdfs_comment: 'True if the drug is available in a generic form (regardless of name).', schema_domain_includes: ['Drug'], range_includes: ['Boolean'], superseded_by: null)]
    case isAvailableGenerically = 'isAvailableGenerically';
    #[SchemaPropertyInfo(rdfs_comment: 'A resource from which this work is derived or from which it is a modification or adaptation.', schema_domain_includes: ['CreativeWork'], range_includes: ['CreativeWork', 'Product', 'Role', 'Text', 'URL'], superseded_by: null)]
    case isBasedOn = 'isBasedOn';
    #[SchemaPropertyInfo(rdfs_comment: 'A resource that was used in the creation of this resource. This term can be repeated for multiple sources. For example, http://example.com/great-multiplication-intro.html.', schema_domain_includes: ['CreativeWork'], range_includes: ['CreativeWork', 'Product', 'Role', 'Text', 'URL'], superseded_by: 'isBasedOn')]
    case isBasedOnUrl = 'isBasedOnUrl';
    #[SchemaPropertyInfo(rdfs_comment: 'A pointer to another product (or multiple products) for which this product is a consumable.', schema_domain_includes: ['Product'], range_includes: ['Product', 'Role', 'Text', 'URL'], superseded_by: null)]
    case isConsumableFor = 'isConsumableFor';
    #[SchemaPropertyInfo(rdfs_comment: 'Another BioChemEntity encoding by this one.', schema_domain_includes: ['BioChemEntity'], range_includes: ['Gene', 'Role', 'Text', 'URL'], superseded_by: null)]
    case isEncodedByBioChemEntity = 'isEncodedByBioChemEntity';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates whether this content is family friendly.', schema_domain_includes: ['CreativeWork', 'Offer', 'Product'], range_includes: ['Boolean'], superseded_by: null)]
    case isFamilyFriendly = 'isFamilyFriendly';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates whether the offer was accepted as a gift for someone other than the buyer.', schema_domain_includes: ['Order'], range_includes: ['Boolean'], superseded_by: null)]
    case isGift = 'isGift';
    #[SchemaPropertyInfo(rdfs_comment: 'Biological process this BioChemEntity is involved in; please use PropertyValue if you want to include any evidence.', schema_domain_includes: ['BioChemEntity'], range_includes: ['DefinedTerm', 'PropertyValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case isInvolvedInBiologicalProcess = 'isInvolvedInBiologicalProcess';
    #[SchemaPropertyInfo(rdfs_comment: 'True if the broadcast is of a live event.', schema_domain_includes: ['BroadcastEvent'], range_includes: ['Boolean'], superseded_by: null)]
    case isLiveBroadcast = 'isLiveBroadcast';
    #[SchemaPropertyInfo(rdfs_comment: 'Subcellular location where this BioChemEntity is located; please use PropertyValue if you want to include any evidence.', schema_domain_includes: ['BioChemEntity'], range_includes: ['DefinedTerm', 'PropertyValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case isLocatedInSubcellularLocation = 'isLocatedInSubcellularLocation';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates an item or CreativeWork that this item, or CreativeWork (in some sense), is part of.', schema_domain_includes: ['CreativeWork'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case isPartOf = 'isPartOf';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates a BioChemEntity that is (in some sense) a part of this BioChemEntity.', schema_domain_includes: ['BioChemEntity'], range_includes: ['BioChemEntity', 'Role', 'Text', 'URL'], superseded_by: null)]
    case isPartOfBioChemEntity = 'isPartOfBioChemEntity';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates some accommodation that this floor plan describes.', schema_domain_includes: ['FloorPlan'], range_includes: ['Accommodation', 'Role', 'Text', 'URL'], superseded_by: null)]
    case isPlanForApartment = 'isPlanForApartment';
    #[SchemaPropertyInfo(rdfs_comment: 'True if this item\'s name is a proprietary/brand name (vs. generic name).', schema_domain_includes: ['DietarySupplement', 'Drug'], range_includes: ['Boolean'], superseded_by: null)]
    case isProprietary = 'isProprietary';
    #[SchemaPropertyInfo(rdfs_comment: 'A pointer to another, somehow related product (or multiple products).', schema_domain_includes: ['Product', 'Service'], range_includes: ['Product', 'Role', 'Service', 'Text', 'URL'], superseded_by: null)]
    case isRelatedTo = 'isRelatedTo';
    #[SchemaPropertyInfo(rdfs_comment: 'Whether the 3DModel allows resizing. For example, room layout applications often do not allow 3DModel elements to be resized to reflect reality.', schema_domain_includes: ['3DModel'], range_includes: ['Boolean'], superseded_by: null)]
    case isResizable = 'isResizable';
    #[SchemaPropertyInfo(rdfs_comment: 'A pointer to another, functionally similar product (or multiple products).', schema_domain_includes: ['Product', 'Service'], range_includes: ['Product', 'Role', 'Service', 'Text', 'URL'], superseded_by: null)]
    case isSimilarTo = 'isSimilarTo';
    #[SchemaPropertyInfo(rdfs_comment: 'The eCommerce marketplace this online store is on.', schema_domain_includes: ['OnlineStore'], range_includes: ['OnlineMarketplace', 'Role', 'Text', 'URL'], superseded_by: null)]
    case isStoreOn = 'isStoreOn';
    #[SchemaPropertyInfo(rdfs_comment: 'The member program this tier is a part of.', schema_domain_includes: ['MemberProgramTier'], range_includes: ['MemberProgram', 'Role', 'Text', 'URL'], superseded_by: null)]
    case isTierOf = 'isTierOf';
    #[SchemaPropertyInfo(rdfs_comment: 'This can be marked \'true\' to indicate that some published <a href="https://schema.org/DeliveryTimeSettings" target="_blank" rel="noopener noreferrer">DeliveryTimeSettings</a> or <a href="https://schema.org/ShippingRateSettings" target="_blank" rel="noopener noreferrer">ShippingRateSettings</a> are intended to apply to all <a href="https://schema.org/OfferShippingDetails" target="_blank" rel="noopener noreferrer">OfferShippingDetails</a> published by the same merchant, when referenced by a <a href="https://schema.org/shippingSettingsLink" target="_blank" rel="noopener noreferrer">shippingSettingsLink</a> in those settings. It is not meaningful to use a \'true\' value for this property alongside a transitTimeLabel (for <a href="https://schema.org/DeliveryTimeSettings" target="_blank" rel="noopener noreferrer">DeliveryTimeSettings</a>) or shippingLabel (for <a href="https://schema.org/ShippingRateSettings" target="_blank" rel="noopener noreferrer">ShippingRateSettings</a>), since this property is for use with unlabelled settings.', schema_domain_includes: ['DeliveryTimeSettings', 'ShippingRateSettings'], range_includes: ['Boolean'], superseded_by: null)]
    case isUnlabelledFallback = 'isUnlabelledFallback';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates the kind of product that this is a variant of. In the case of <a href="https://schema.org/ProductModel" target="_blank" rel="noopener noreferrer">ProductModel</a>, this is a pointer (from a ProductModel) to a base product from which this product is a variant. It is safe to infer that the variant inherits all product features from the base model, unless defined locally. This is not transitive. In the case of a <a href="https://schema.org/ProductGroup" target="_blank" rel="noopener noreferrer">ProductGroup</a>, the group description also serves as a template, representing a set of Products that vary on explicitly defined, specific dimensions only (so it defines both a set of variants, as well as which values distinguish amongst those variants). When used with <a href="https://schema.org/ProductGroup" target="_blank" rel="noopener noreferrer">ProductGroup</a>, this property can apply to any <a href="https://schema.org/Product" target="_blank" rel="noopener noreferrer">Product</a> included in the group.', schema_domain_includes: ['Product', 'ProductModel'], range_includes: ['ProductGroup', 'ProductModel', 'Role', 'Text', 'URL'], superseded_by: null)]
    case isVariantOf = 'isVariantOf';
    #[SchemaPropertyInfo(rdfs_comment: 'The ISBN of the book.', schema_domain_includes: ['Book'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case isbn = 'isbn';
    #[SchemaPropertyInfo(rdfs_comment: 'The International Standard of Industrial Classification of All Economic Activities (ISIC), Revision 4 code for a particular organization, business person, or place.', schema_domain_includes: ['Organization', 'Person', 'Place'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case isicV4 = 'isicV4';
    #[SchemaPropertyInfo(rdfs_comment: 'An organization identifier as defined in <a href="https://en.wikipedia.org/wiki/ISO/IEC_6523" target="_blank" rel="noopener noreferrer">ISO 6523(-1)</a>. The identifier should be in the `XXXX:YYYYYY:ZZZ` or `XXXX:YYYYYY`format. Where `XXXX` is a 4 digit _ICD_ (International Code Designator), `YYYYYY` is an _OID_ (Organization Identifier) with all formatting characters (dots, dashes, spaces) removed with a maximal length of 35 characters, and `ZZZ` is an optional OPI (Organization Part Identifier) with a maximum length of 35 characters. The various components (ICD, OID, OPI) are joined with a colon character (ASCII `0x3a`). Note that many existing organization identifiers defined as attributes like <a href="https://schema.org/leiCode" target="_blank" rel="noopener noreferrer">leiCode</a> (`0199`), <a href="https://schema.org/duns" target="_blank" rel="noopener noreferrer">duns</a> (`0060`) or <a href="https://schema.org/globalLocationNumber" target="_blank" rel="noopener noreferrer">GLN</a> (`0088`) can be expressed using ISO-6523. If possible, ISO-6523 codes should be preferred to populating <a href="https://schema.org/vatID" target="_blank" rel="noopener noreferrer">vatID</a> or <a href="https://schema.org/taxID" target="_blank" rel="noopener noreferrer">taxID</a>, as ISO identifiers are less ambiguous.', schema_domain_includes: ['Organization'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case iso6523Code = 'iso6523Code';
    #[SchemaPropertyInfo(rdfs_comment: 'The International Standard Recording Code for the recording.', schema_domain_includes: ['MusicRecording'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case isrcCode = 'isrcCode';
    #[SchemaPropertyInfo(rdfs_comment: 'The International Standard Serial Number (ISSN) that identifies this serial publication. You can repeat this property to identify different formats of, or the linking ISSN (ISSN-L) for, this serial publication.', schema_domain_includes: ['Blog', 'CreativeWorkSeries', 'Dataset', 'WebSite'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case issn = 'issn';
    #[SchemaPropertyInfo(rdfs_comment: 'Identifies the issue of publication; for example, "iii" or "2".', schema_domain_includes: ['PublicationIssue'], range_includes: ['Integer', 'Role', 'Text', 'URL'], superseded_by: null)]
    case issueNumber = 'issueNumber';
    #[SchemaPropertyInfo(rdfs_comment: 'The organization issuing the item, for example a <a href="https://schema.org/Permit" target="_blank" rel="noopener noreferrer">Permit</a>, <a href="https://schema.org/Ticket" target="_blank" rel="noopener noreferrer">Ticket</a>, or <a href="https://schema.org/Certification" target="_blank" rel="noopener noreferrer">Certification</a>.', schema_domain_includes: ['Certification', 'Permit', 'Ticket'], range_includes: ['Organization', 'Role', 'Text', 'URL'], superseded_by: null)]
    case issuedBy = 'issuedBy';
    #[SchemaPropertyInfo(rdfs_comment: 'The service through which the permit was granted.', schema_domain_includes: ['Permit'], range_includes: ['Role', 'Service', 'Text', 'URL'], superseded_by: null)]
    case issuedThrough = 'issuedThrough';
    #[SchemaPropertyInfo(rdfs_comment: 'The International Standard Musical Work Code for the composition.', schema_domain_includes: ['MusicComposition'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case iswcCode = 'iswcCode';
    #[SchemaPropertyInfo(rdfs_comment: 'An entity represented by an entry in a list or data feed (e.g. an \'artist\' in a list of \'artists\').', schema_domain_includes: ['DataFeedItem', 'ListItem'], range_includes: ['Role', 'Text', 'Thing', 'URL'], superseded_by: null)]
    case item = 'item';
    #[SchemaPropertyInfo(rdfs_comment: 'A predefined value from OfferItemCondition specifying the condition of the product or service, or the products or services included in the offer. Also used for product return policies to specify the condition of products accepted for returns.', schema_domain_includes: ['Demand', 'MerchantReturnPolicy', 'Offer', 'Product'], range_includes: ['OfferItemCondition', 'Role', 'Text', 'URL'], superseded_by: null)]
    case itemCondition = 'itemCondition';
    #[SchemaPropertyInfo(rdfs_comment: 'The type of return fees for returns of defect products.', schema_domain_includes: ['MerchantReturnPolicy'], range_includes: ['ReturnFeesEnumeration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case itemDefectReturnFees = 'itemDefectReturnFees';
    #[SchemaPropertyInfo(rdfs_comment: 'The method (from an enumeration) by which the customer obtains a return shipping label for a defect product.', schema_domain_includes: ['MerchantReturnPolicy'], range_includes: ['ReturnLabelSourceEnumeration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case itemDefectReturnLabelSource = 'itemDefectReturnLabelSource';
    #[SchemaPropertyInfo(rdfs_comment: 'Amount of shipping costs for defect product returns. Applicable when property <a href="https://schema.org/itemDefectReturnFees" target="_blank" rel="noopener noreferrer">itemDefectReturnFees</a> equals <a href="https://schema.org/ReturnShippingFees" target="_blank" rel="noopener noreferrer">ReturnShippingFees</a>.', schema_domain_includes: ['MerchantReturnPolicy'], range_includes: ['MonetaryAmount', 'Role', 'Text', 'URL'], superseded_by: null)]
    case itemDefectReturnShippingFeesAmount = 'itemDefectReturnShippingFeesAmount';
    #[SchemaPropertyInfo(rdfs_comment: 'For itemListElement values, you can use simple strings (e.g. "Peter", "Paul", "Mary"), existing entities, or use ListItem.\\n\\nText values are best if the elements in the list are plain strings. Existing entities are best for a simple, unordered list of existing things in your data. ListItem is used with ordered lists when you want to provide additional context about the element in that list or when the same item might be in different places in different lists.\\n\\nNote: The order of elements in your mark-up is not sufficient for indicating the order or elements.  Use ListItem with a \'position\' property in such cases.', schema_domain_includes: ['ItemList'], range_includes: ['ListItem', 'Role', 'Text', 'Thing', 'URL'], superseded_by: null)]
    case itemListElement = 'itemListElement';
    #[SchemaPropertyInfo(rdfs_comment: 'Type of ordering (e.g. Ascending, Descending, Unordered).', schema_domain_includes: ['ItemList'], range_includes: ['ItemListOrderType', 'Role', 'Text', 'URL'], superseded_by: null)]
    case itemListOrder = 'itemListOrder';
    #[SchemaPropertyInfo(rdfs_comment: 'en', schema_domain_includes: ['ArchiveComponent'], range_includes: ['Place', 'PostalAddress', 'Role', 'Text', 'URL'], superseded_by: null)]
    case itemLocation = 'itemLocation';
    #[SchemaPropertyInfo(rdfs_comment: 'An item being offered (or demanded). The transactional nature of the offer or demand is documented using <a href="https://schema.org/businessFunction" target="_blank" rel="noopener noreferrer">businessFunction</a>, e.g. sell, lease etc. While several common expected types are listed explicitly in this definition, others can be used. Using a second type, such as Product or a subtype of Product, can clarify the nature of the offer.', schema_domain_includes: ['Demand', 'Offer'], range_includes: ['AggregateOffer', 'CreativeWork', 'Event', 'MenuItem', 'Product', 'Role', 'Service', 'Text', 'Trip', 'URL'], superseded_by: null)]
    case itemOffered = 'itemOffered';
    #[SchemaPropertyInfo(rdfs_comment: 'The item that is being reviewed/rated.', schema_domain_includes: ['AggregateRating', 'Review'], range_includes: ['Role', 'Text', 'Thing', 'URL'], superseded_by: null)]
    case itemReviewed = 'itemReviewed';
    #[SchemaPropertyInfo(rdfs_comment: 'Item(s) being shipped.', schema_domain_includes: ['ParcelDelivery'], range_includes: ['Product', 'Role', 'Text', 'URL'], superseded_by: null)]
    case itemShipped = 'itemShipped';
    #[SchemaPropertyInfo(rdfs_comment: 'Destination(s) ( <a href="https://schema.org/Place" target="_blank" rel="noopener noreferrer">Place</a> ) that make up a trip. For a trip where destination order is important use <a href="https://schema.org/ItemList" target="_blank" rel="noopener noreferrer">ItemList</a> to specify that order (see examples).', schema_domain_includes: ['Trip'], range_includes: ['ItemList', 'Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case itinerary = 'itinerary';
    #[SchemaPropertyInfo(rdfs_comment: 'Systematic method of naming chemical compounds as recommended by the International Union of Pure and Applied Chemistry (IUPAC).', schema_domain_includes: ['MolecularEntity'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case iupacName = 'iupacName';
    #[SchemaPropertyInfo(rdfs_comment: 'Description of benefits associated with the job.', schema_domain_includes: ['JobPosting'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case jobBenefits = 'jobBenefits';
    #[SchemaPropertyInfo(rdfs_comment: 'The expected duration of an employment offer as advertised by the employer. Relevant for job postings that have a clearly defined period in mind such as seasonal work, substitutes for maternal leave or any other temporary employment.', schema_domain_includes: ['JobPosting'], range_includes: ['Duration', 'QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case jobDuration = 'jobDuration';
    #[SchemaPropertyInfo(rdfs_comment: 'An indicator as to whether a position is available for an immediate start.', schema_domain_includes: ['JobPosting'], range_includes: ['Boolean'], superseded_by: null)]
    case jobImmediateStart = 'jobImmediateStart';
    #[SchemaPropertyInfo(rdfs_comment: 'A (typically single) geographic location associated with the job position.', schema_domain_includes: ['JobPosting'], range_includes: ['Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case jobLocation = 'jobLocation';
    #[SchemaPropertyInfo(rdfs_comment: 'A description of the job location (e.g. TELECOMMUTE for telecommute jobs).', schema_domain_includes: ['JobPosting'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case jobLocationType = 'jobLocationType';
    #[SchemaPropertyInfo(rdfs_comment: 'The date on which a successful applicant for this job would be expected to start work. Choose a specific date in the future or use the jobImmediateStart property to indicate the position is to be filled as soon as possible.', schema_domain_includes: ['JobPosting'], range_includes: ['Date', 'Role', 'Text', 'URL'], superseded_by: null)]
    case jobStartDate = 'jobStartDate';
    #[SchemaPropertyInfo(rdfs_comment: 'The job title of the person (for example, Financial Manager).', schema_domain_includes: ['Person'], range_includes: ['DefinedTerm', 'Role', 'Text', 'URL'], superseded_by: null)]
    case jobTitle = 'jobTitle';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates a legal jurisdiction, e.g. of some legislation, or where some government service is based.', schema_domain_includes: ['GovernmentService', 'Legislation'], range_includes: ['AdministrativeArea', 'Role', 'Text', 'URL'], superseded_by: null)]
    case jurisdiction = 'jurisdiction';
    #[SchemaPropertyInfo(rdfs_comment: 'Keywords or tags used to describe some item. Multiple textual entries in a keywords list are typically delimited by commas, or by repeating the property.', schema_domain_includes: ['CreativeWork', 'Event', 'Organization', 'Place', 'Product'], range_includes: ['DefinedTerm', 'Role', 'Text', 'URL'], superseded_by: null)]
    case keywords = 'keywords';
    #[SchemaPropertyInfo(rdfs_comment: 'A textual description of known damages, both repaired and unrepaired.', schema_domain_includes: ['Vehicle'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case knownVehicleDamages = 'knownVehicleDamages';
    #[SchemaPropertyInfo(rdfs_comment: 'The most generic bi-directional social/work relation.', schema_domain_includes: ['Person'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case knows = 'knows';
    #[SchemaPropertyInfo(rdfs_comment: 'Of a <a href="https://schema.org/Person" target="_blank" rel="noopener noreferrer">Person</a>, and less typically of an <a href="https://schema.org/Organization" target="_blank" rel="noopener noreferrer">Organization</a>, to indicate a topic that is known about - suggesting possible expertise but not implying it. We do not distinguish skill levels here, or relate this to educational content, events, objectives or <a href="https://schema.org/JobPosting" target="_blank" rel="noopener noreferrer">JobPosting</a> descriptions.', schema_domain_includes: ['Organization', 'Person'], range_includes: ['Role', 'Text', 'Thing', 'URL'], superseded_by: null)]
    case knowsAbout = 'knowsAbout';
    #[SchemaPropertyInfo(rdfs_comment: 'Of a <a href="https://schema.org/Person" target="_blank" rel="noopener noreferrer">Person</a>, and less typically of an <a href="https://schema.org/Organization" target="_blank" rel="noopener noreferrer">Organization</a>, to indicate a known language. We do not distinguish skill levels or reading/writing/speaking/signing here. Use language codes from the <a href="http://tools.ietf.org/html/bcp47" target="_blank" rel="noopener noreferrer">IETF BCP 47 standard</a>.', schema_domain_includes: ['Organization', 'Person'], range_includes: ['Language', 'Role', 'Text', 'URL'], superseded_by: null)]
    case knowsLanguage = 'knowsLanguage';
    #[SchemaPropertyInfo(rdfs_comment: 'Link to the drug\'s label details.', schema_domain_includes: ['Drug'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case labelDetails = 'labelDetails';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of participant. The owner of the real estate property.', schema_domain_includes: ['RentAction'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case landlord = 'landlord';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of instrument. The language used on this action.', schema_domain_includes: ['CommunicateAction', 'WriteAction'], range_includes: ['Language', 'Role', 'Text', 'URL'], superseded_by: 'inLanguage')]
    case language = 'language';
    #[SchemaPropertyInfo(rdfs_comment: 'Date on which the content on this web page was last reviewed for accuracy and/or completeness.', schema_domain_includes: ['WebPage'], range_includes: ['Date'], superseded_by: null)]
    case lastReviewed = 'lastReviewed';
    #[SchemaPropertyInfo(rdfs_comment: 'The latitude of a location. For example ```37.42242``` (<a href="https://en.wikipedia.org/wiki/World_Geodetic_System" target="_blank" rel="noopener noreferrer">WGS 84</a>).', schema_domain_includes: ['GeoCoordinates', 'Place'], range_includes: ['Number', 'Role', 'Text', 'URL'], superseded_by: null)]
    case latitude = 'latitude';
    #[SchemaPropertyInfo(rdfs_comment: 'A schematic image showing the floorplan layout.', schema_domain_includes: ['FloorPlan'], range_includes: ['ImageObject', 'Role', 'Text', 'URL'], superseded_by: null)]
    case layoutImage = 'layoutImage';
    #[SchemaPropertyInfo(rdfs_comment: 'The predominant type or kind characterizing the learning resource. For example, \'presentation\', \'handout\'.', schema_domain_includes: ['CreativeWork', 'LearningResource'], range_includes: ['DefinedTerm', 'Role', 'Text', 'URL'], superseded_by: null)]
    case learningResourceType = 'learningResourceType';
    #[SchemaPropertyInfo(rdfs_comment: 'Length of the lease for some <a href="https://schema.org/Accommodation" target="_blank" rel="noopener noreferrer">Accommodation</a>, either particular to some <a href="https://schema.org/Offer" target="_blank" rel="noopener noreferrer">Offer</a> or in some cases intrinsic to the property.', schema_domain_includes: ['Accommodation', 'Offer', 'RealEstateListing'], range_includes: ['Duration', 'QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case leaseLength = 'leaseLength';
    #[SchemaPropertyInfo(rdfs_comment: 'The legal address of an organization which acts as the officially registered address used for legal and tax purposes. The legal address can be different from the place of operations of a business and other addresses can be part of an organization.', schema_domain_includes: ['Organization'], range_includes: ['PostalAddress', 'Role', 'Text', 'URL'], superseded_by: null)]
    case legalAddress = 'legalAddress';
    #[SchemaPropertyInfo(rdfs_comment: 'The official name of the organization, e.g. the registered company name.', schema_domain_includes: ['Organization'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case legalName = 'legalName';
    #[SchemaPropertyInfo(rdfs_comment: 'One or multiple persons who represent this organization legally such as CEO or sole administrator.', schema_domain_includes: ['Organization'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case legalRepresentative = 'legalRepresentative';
    #[SchemaPropertyInfo(rdfs_comment: 'The drug or supplement\'s legal status, including any controlled substance schedules that apply.', schema_domain_includes: ['DietarySupplement', 'Drug', 'MedicalEntity'], range_includes: ['DrugLegalStatus', 'MedicalEnumeration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case legalStatus = 'legalStatus';
    #[SchemaPropertyInfo(rdfs_comment: 'Another legislation that this legislation amends, introducing legal changes.', schema_domain_includes: ['Legislation'], range_includes: ['Legislation', 'Role', 'Text', 'URL'], superseded_by: null)]
    case legislationAmends = 'legislationAmends';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates that this legislation (or part of a legislation) somehow transfers another legislation in a different legislative context. This is an informative link, and it has no legal value. For legally-binding links of transposition, use the <a href="/legislationTransposes">legislationTransposes</a> property. For example an informative consolidated law of a European Union\'s member state "applies" the consolidated version of the European Directive implemented in it.', schema_domain_includes: ['Legislation'], range_includes: ['Legislation', 'Role', 'Text', 'URL'], superseded_by: null)]
    case legislationApplies = 'legislationApplies';
    #[SchemaPropertyInfo(rdfs_comment: 'Another legislation that this legislation changes. This encompasses the notions of amendment, replacement, correction, repeal, or other types of change. This may be a direct change (textual or non-textual amendment) or a consequential or indirect change. The property is to be used to express the existence of a change relationship between two acts rather than the existence of a consolidated version of the text that shows the result of the change. For consolidation relationships, use the <a href="/legislationConsolidates">legislationConsolidates</a> property.', schema_domain_includes: ['Legislation'], range_includes: ['Legislation', 'Role', 'Text', 'URL'], superseded_by: null)]
    case legislationChanges = 'legislationChanges';
    #[SchemaPropertyInfo(rdfs_comment: 'Another legislation that this one sets into force.', schema_domain_includes: ['Legislation'], range_includes: ['Legislation', 'Role', 'Text', 'URL'], superseded_by: null)]
    case legislationCommences = 'legislationCommences';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates another legislation taken into account in this consolidated legislation (which is usually the product of an editorial process that revises the legislation). This property should be used multiple times to refer to both the original version or the previous consolidated version, and to the legislations making the change.', schema_domain_includes: ['Legislation'], range_includes: ['Legislation', 'Role', 'Text', 'URL'], superseded_by: null)]
    case legislationConsolidates = 'legislationConsolidates';
    #[SchemaPropertyInfo(rdfs_comment: 'Another legislation in which this one introduces textual changes, like correction of spelling mistakes, with no legal impact (for modifications that have legal impact, use <a href="/legislationAmends">legislationAmends</a>).', schema_domain_includes: ['Legislation'], range_includes: ['Legislation', 'Role', 'Text', 'URL'], superseded_by: null)]
    case legislationCorrects = 'legislationCorrects';
    #[SchemaPropertyInfo(rdfs_comment: 'The person or organization that countersigned the legislation. Depending on the legal context, a countersignature can indicate that the signed authority undertakes to assume responsibility for texts emanating from a person who is inviolable and irresponsible, (for example a King, Grand Duc or President), or that the authority is in charge of the implementation of the text.', schema_domain_includes: ['Legislation'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case legislationCountersignedBy = 'legislationCountersignedBy';
    #[SchemaPropertyInfo(rdfs_comment: 'The date of adoption or signature of the legislation. This is the date at which the text is officially aknowledged to be a legislation, even though it might not even be published or in force.', schema_domain_includes: ['Legislation'], range_includes: ['Date'], superseded_by: null)]
    case legislationDate = 'legislationDate';
    #[SchemaPropertyInfo(rdfs_comment: 'The date at which the Legislation becomes applicable. This can sometimes be distinct from the date of entry into force : a text may come in force today, and state it will become applicable in 3 months.', schema_domain_includes: ['Legislation'], range_includes: ['Date'], superseded_by: null)]
    case legislationDateOfApplicability = 'legislationDateOfApplicability';
    #[SchemaPropertyInfo(rdfs_comment: 'The point-in-time at which the provided description of the legislation is valid (e.g.: when looking at the law on the 2016-04-07 (= dateVersion), I get the consolidation of 2015-04-12 of the "National Insurance Contributions Act 2015")', schema_domain_includes: ['Legislation'], range_includes: ['Date'], superseded_by: null)]
    case legislationDateVersion = 'legislationDateVersion';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates that this Legislation ensures the implementation of another Legislation, for example by modifying national legislations so that they do not contradict to an EU regulation or decision. This implies a legal meaning. Transpositions of EU Directive should be captured with <a href="/legislationTransposes">legislationTransposes</a>.', schema_domain_includes: ['Legislation'], range_includes: ['Legislation', 'Role', 'Text', 'URL'], superseded_by: null)]
    case legislationEnsuresImplementationOf = 'legislationEnsuresImplementationOf';
    #[SchemaPropertyInfo(rdfs_comment: 'An identifier for the legislation. This can be either a string-based identifier, like the CELEX at EU level or the NOR in France, or a web-based, URL/URI identifier, like an ELI (European Legislation Identifier) or an URN-Lex.', schema_domain_includes: ['Legislation'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case legislationIdentifier = 'legislationIdentifier';
    #[SchemaPropertyInfo(rdfs_comment: 'The jurisdiction from which the legislation originates.', schema_domain_includes: ['Legislation'], range_includes: ['AdministrativeArea', 'Role', 'Text', 'URL'], superseded_by: null)]
    case legislationJurisdiction = 'legislationJurisdiction';
    #[SchemaPropertyInfo(rdfs_comment: 'Whether the legislation is currently in force, not in force, or partially in force.', schema_domain_includes: ['Legislation'], range_includes: ['LegalForceStatus', 'Role', 'Text', 'URL'], superseded_by: null)]
    case legislationLegalForce = 'legislationLegalForce';
    #[SchemaPropertyInfo(rdfs_comment: 'The legal value of this legislation file. The same legislation can be written in multiple files with different legal values. Typically a digitally signed PDF have a "stronger" legal value than the HTML file of the same act.', schema_domain_includes: ['LegislationObject'], range_includes: ['LegalValueLevel', 'Role', 'Text', 'URL'], superseded_by: null)]
    case legislationLegalValue = 'legislationLegalValue';
    #[SchemaPropertyInfo(rdfs_comment: 'The person or organization that originally passed or made the law: typically parliament (for primary legislation) or government (for secondary legislation). This indicates the "legal author" of the law, as opposed to its physical author.', schema_domain_includes: ['Legislation'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case legislationPassedBy = 'legislationPassedBy';
    #[SchemaPropertyInfo(rdfs_comment: 'Another legislation that this legislation repeals (cancels, abrogates).', schema_domain_includes: ['Legislation'], range_includes: ['Legislation', 'Role', 'Text', 'URL'], superseded_by: null)]
    case legislationRepeals = 'legislationRepeals';
    #[SchemaPropertyInfo(rdfs_comment: 'An individual or organization that has some kind of responsibility for the legislation. Typically the ministry who is/was in charge of elaborating the legislation, or the adressee for potential questions about the legislation once it is published.', schema_domain_includes: ['Legislation'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case legislationResponsible = 'legislationResponsible';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates that this legislation (or part of legislation) fulfills the objectives set by another legislation, by passing appropriate implementation measures. Typically, some legislations of European Union\'s member states or regions transpose European Directives. This indicates a legally binding link between the 2 legislations.', schema_domain_includes: ['Legislation'], range_includes: ['Legislation', 'Role', 'Text', 'URL'], superseded_by: null)]
    case legislationTransposes = 'legislationTransposes';
    #[SchemaPropertyInfo(rdfs_comment: 'The type of the legislation. Examples of values are "law", "act", "directive", "decree", "regulation", "statutory instrument", "loi organique", "règlement grand-ducal", etc., depending on the country.', schema_domain_includes: ['Legislation'], range_includes: ['CategoryCode', 'Role', 'Text', 'URL'], superseded_by: null)]
    case legislationType = 'legislationType';
    #[SchemaPropertyInfo(rdfs_comment: 'An organization identifier that uniquely identifies a legal entity as defined in ISO 17442.', schema_domain_includes: ['Organization'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case leiCode = 'leiCode';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of participant. The person that lends the object being borrowed.', schema_domain_includes: ['BorrowAction'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case lender = 'lender';
    #[SchemaPropertyInfo(rdfs_comment: 'This ordering relation for qualitative values indicates that the subject is lesser than the object.', schema_domain_includes: ['QualitativeValue'], range_includes: ['QualitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case lesser = 'lesser';
    #[SchemaPropertyInfo(rdfs_comment: 'This ordering relation for qualitative values indicates that the subject is lesser than or equal to the object.', schema_domain_includes: ['QualitativeValue'], range_includes: ['QualitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case lesserOrEqual = 'lesserOrEqual';
    #[SchemaPropertyInfo(rdfs_comment: 'The individual who adds lettering, including speech balloons and sound effects, to artwork.', schema_domain_includes: ['ComicIssue', 'ComicStory', 'VisualArtwork'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case letterer = 'letterer';
    #[SchemaPropertyInfo(rdfs_comment: 'A license document that applies to this content, typically indicated by URL.', schema_domain_includes: ['CreativeWork'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case license = 'license';
    #[SchemaPropertyInfo(rdfs_comment: 'A life event like baptism, communions, Bar Mitzvahs, Aqiqah, Namakarana, Miyamairi, burial, ....', schema_domain_includes: ['Person'], range_includes: ['Event', 'Role', 'Text', 'URL'], superseded_by: null)]
    case lifeEvent = 'lifeEvent';
    #[SchemaPropertyInfo(rdfs_comment: 'A line is a point-to-point path consisting of two or more points. A line is expressed as a series of two or more point objects separated by space.', schema_domain_includes: ['GeoShape'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case line = 'line';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates the relationship type of a Web link.', schema_domain_includes: ['LinkRole'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case linkRelationship = 'linkRelationship';
    #[SchemaPropertyInfo(rdfs_comment: 'An update to the LiveBlog.', schema_domain_includes: ['LiveBlogPosting'], range_includes: ['BlogPosting', 'Role', 'Text', 'URL'], superseded_by: null)]
    case liveBlogUpdate = 'liveBlogUpdate';
    #[SchemaPropertyInfo(rdfs_comment: 'Amount of mortgage mandate that can be converted into a proper mortgage at a later stage.', schema_domain_includes: ['MortgageLoan'], range_includes: ['MonetaryAmount', 'Role', 'Text', 'URL'], superseded_by: null)]
    case loanMortgageMandateAmount = 'loanMortgageMandateAmount';
    #[SchemaPropertyInfo(rdfs_comment: 'The amount of money to pay in a single payment.', schema_domain_includes: ['RepaymentSpecification'], range_includes: ['MonetaryAmount', 'Role', 'Text', 'URL'], superseded_by: null)]
    case loanPaymentAmount = 'loanPaymentAmount';
    #[SchemaPropertyInfo(rdfs_comment: 'Frequency of payments due, i.e. number of months between payments. This is defined as a frequency, i.e. the reciprocal of a period of time.', schema_domain_includes: ['RepaymentSpecification'], range_includes: ['Number'], superseded_by: null)]
    case loanPaymentFrequency = 'loanPaymentFrequency';
    #[SchemaPropertyInfo(rdfs_comment: 'A form of paying back money previously borrowed from a lender. Repayment usually takes the form of periodic payments that normally include part principal plus interest in each payment.', schema_domain_includes: ['LoanOrCredit'], range_includes: ['RepaymentSpecification', 'Role', 'Text', 'URL'], superseded_by: null)]
    case loanRepaymentForm = 'loanRepaymentForm';
    #[SchemaPropertyInfo(rdfs_comment: 'The duration of the loan or credit agreement.', schema_domain_includes: ['LoanOrCredit'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case loanTerm = 'loanTerm';
    #[SchemaPropertyInfo(rdfs_comment: 'The type of a loan or credit.', schema_domain_includes: ['LoanOrCredit'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case loanType = 'loanType';
    #[SchemaPropertyInfo(rdfs_comment: 'The location of, for example, where an event is happening, where an organization is located, or where an action takes place.', schema_domain_includes: ['Action', 'Event', 'InteractionCounter', 'Organization'], range_includes: ['Place', 'PostalAddress', 'Role', 'Text', 'URL', 'VirtualLocation'], superseded_by: null)]
    case location = 'location';
    #[SchemaPropertyInfo(rdfs_comment: 'The location where the CreativeWork was created, which may not be the same as the location depicted in the CreativeWork.', schema_domain_includes: ['CreativeWork'], range_includes: ['Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case locationCreated = 'locationCreated';
    #[SchemaPropertyInfo(rdfs_comment: 'A full description of the lodging unit.', schema_domain_includes: ['LodgingReservation'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case lodgingUnitDescription = 'lodgingUnitDescription';
    #[SchemaPropertyInfo(rdfs_comment: 'Textual description of the unit type (including suite vs. room, size of bed, etc.).', schema_domain_includes: ['LodgingReservation'], range_includes: ['QualitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case lodgingUnitType = 'lodgingUnitType';
    #[SchemaPropertyInfo(rdfs_comment: 'An associated logo.', schema_domain_includes: ['Brand', 'Certification', 'Organization', 'Place', 'Product', 'Service'], range_includes: ['ImageObject', 'Role', 'Text', 'URL'], superseded_by: null)]
    case logo = 'logo';
    #[SchemaPropertyInfo(rdfs_comment: 'The longitude of a location. For example ```-122.08585``` (<a href="https://en.wikipedia.org/wiki/World_Geodetic_System" target="_blank" rel="noopener noreferrer">WGS 84</a>).', schema_domain_includes: ['GeoCoordinates', 'Place'], range_includes: ['Number', 'Role', 'Text', 'URL'], superseded_by: null)]
    case longitude = 'longitude';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of participant. The loser of the action.', schema_domain_includes: ['WinAction'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case loser = 'loser';
    #[SchemaPropertyInfo(rdfs_comment: 'The lowest price of all offers available.\\n\\nUsage guidelines:\\n\\n* Use values from 0123456789 (Unicode \'DIGIT ZERO\' (U+0030) to \'DIGIT NINE\' (U+0039)) rather than superficially similar Unicode symbols.\\n* Use \'.\' (Unicode \'FULL STOP\' (U+002E)) rather than \',\' to indicate a decimal point. Avoid using these symbols as a readability separator.', schema_domain_includes: ['AggregateOffer'], range_includes: ['Number', 'Role', 'Text', 'URL'], superseded_by: null)]
    case lowPrice = 'lowPrice';
    #[SchemaPropertyInfo(rdfs_comment: 'The person who wrote the words.', schema_domain_includes: ['MusicComposition'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case lyricist = 'lyricist';
    #[SchemaPropertyInfo(rdfs_comment: 'The words in the song.', schema_domain_includes: ['MusicComposition'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case lyrics = 'lyrics';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates if this web page element is the main subject of the page.', schema_domain_includes: ['WebPage'], range_includes: ['Role', 'Text', 'URL', 'WebPageElement'], superseded_by: null)]
    case mainContentOfPage = 'mainContentOfPage';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates the primary entity described in some page or other CreativeWork.', schema_domain_includes: ['CreativeWork'], range_includes: ['Role', 'Text', 'Thing', 'URL'], superseded_by: null)]
    case mainEntity = 'mainEntity';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates a page (or other CreativeWork) for which this thing is the main entity being described. See <a href="https://schema.org/docs/datamodel.html#mainEntityBackground" target="_blank" rel="noopener noreferrer">background notes</a> for details.', schema_domain_includes: ['Thing'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case mainEntityOfPage = 'mainEntityOfPage';
    #[SchemaPropertyInfo(rdfs_comment: 'A maintainer of a <a href="https://schema.org/Dataset" target="_blank" rel="noopener noreferrer">Dataset</a>, software package (<a href="https://schema.org/SoftwareApplication" target="_blank" rel="noopener noreferrer">SoftwareApplication</a>), or other <a href="https://schema.org/Project" target="_blank" rel="noopener noreferrer">Project</a>. A maintainer is a <a href="https://schema.org/Person" target="_blank" rel="noopener noreferrer">Person</a> or <a href="https://schema.org/Organization" target="_blank" rel="noopener noreferrer">Organization</a> that manages contributions to, and/or publication of, some (typically complex) artifact. It is common for distributions of software and data to be based on "upstream" sources. When <a href="https://schema.org/maintainer" target="_blank" rel="noopener noreferrer">maintainer</a> is applied to a specific version of something e.g. a particular version or packaging of a <a href="https://schema.org/Dataset" target="_blank" rel="noopener noreferrer">Dataset</a>, it is always  possible that the upstream source has a different maintainer. The <a href="https://schema.org/isBasedOn" target="_blank" rel="noopener noreferrer">isBasedOn</a> property can be used to indicate such relationships between datasets to make the different maintenance roles clear. Similarly in the case of software, a package may have dedicated maintainers working on integration into software distributions such as Ubuntu, as well as upstream maintainers of the underlying work.', schema_domain_includes: ['CreativeWork'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case maintainer = 'maintainer';
    #[SchemaPropertyInfo(rdfs_comment: 'A pointer to products or services offered by the organization or person.', schema_domain_includes: ['Organization', 'Person'], range_includes: ['Offer', 'Role', 'Text', 'URL'], superseded_by: null)]
    case makesOffer = 'makesOffer';
    #[SchemaPropertyInfo(rdfs_comment: 'The manufacturer of the product.', schema_domain_includes: ['Product'], range_includes: ['Organization', 'Role', 'Text', 'URL'], superseded_by: null)]
    case manufacturer = 'manufacturer';
    #[SchemaPropertyInfo(rdfs_comment: 'A URL to a map of the place.', schema_domain_includes: ['Place'], range_includes: ['Role', 'Text', 'URL'], superseded_by: 'hasMap')]
    case map = 'map';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates the kind of Map, from the MapCategoryType Enumeration.', schema_domain_includes: ['Map'], range_includes: ['MapCategoryType', 'Role', 'Text', 'URL'], superseded_by: null)]
    case mapType = 'mapType';
    #[SchemaPropertyInfo(rdfs_comment: 'A URL to a map of the place.', schema_domain_includes: ['Place'], range_includes: ['Role', 'Text', 'URL'], superseded_by: 'hasMap')]
    case maps = 'maps';
    #[SchemaPropertyInfo(rdfs_comment: 'A <a href="https://schema.org/marginOfError" target="_blank" rel="noopener noreferrer">marginOfError</a> for an <a href="https://schema.org/Observation" target="_blank" rel="noopener noreferrer">Observation</a>.', schema_domain_includes: ['Observation'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case marginOfError = 'marginOfError';
    #[SchemaPropertyInfo(rdfs_comment: 'For a <a href="https://schema.org/NewsMediaOrganization" target="_blank" rel="noopener noreferrer">NewsMediaOrganization</a>, a link to the masthead page or a page listing top editorial management.', schema_domain_includes: ['NewsMediaOrganization'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case masthead = 'masthead';
    #[SchemaPropertyInfo(rdfs_comment: 'A material that something is made from, e.g. leather, wool, cotton, paper.', schema_domain_includes: ['CreativeWork', 'Product'], range_includes: ['Product', 'Role', 'Text', 'URL'], superseded_by: null)]
    case material = 'material';
    #[SchemaPropertyInfo(rdfs_comment: 'en', schema_domain_includes: ['CreativeWork'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case materialExtent = 'materialExtent';
    #[SchemaPropertyInfo(rdfs_comment: 'A mathematical expression (e.g. \'x^2-3x=0\') that may be solved for a specific variable, simplified, or transformed. This can take many formats, e.g. LaTeX, Ascii-Math, or math as you would write with a keyboard.', schema_domain_includes: ['MathSolver'], range_includes: ['Role', 'SolveMathAction', 'Text', 'URL'], superseded_by: null)]
    case mathExpression = 'mathExpression';
    #[SchemaPropertyInfo(rdfs_comment: 'The highest price if the price is a range.', schema_domain_includes: ['PriceSpecification'], range_includes: ['Number'], superseded_by: null)]
    case maxPrice = 'maxPrice';
    #[SchemaPropertyInfo(rdfs_comment: 'The upper value of some characteristic or property.', schema_domain_includes: ['MonetaryAmount', 'PropertyValue', 'PropertyValueSpecification', 'QuantitativeValue'], range_includes: ['Number'], superseded_by: null)]
    case maxValue = 'maxValue';
    #[SchemaPropertyInfo(rdfs_comment: 'The total number of individuals that may attend an event or venue.', schema_domain_includes: ['Event', 'Place'], range_includes: ['Integer'], superseded_by: null)]
    case maximumAttendeeCapacity = 'maximumAttendeeCapacity';
    #[SchemaPropertyInfo(rdfs_comment: 'The maximum number of students who may be enrolled in the program.', schema_domain_includes: ['EducationalOccupationalProgram'], range_includes: ['Integer'], superseded_by: null)]
    case maximumEnrollment = 'maximumEnrollment';
    #[SchemaPropertyInfo(rdfs_comment: 'Recommended intake of this supplement for a given population as defined by a specific recommending authority.', schema_domain_includes: ['DietarySupplement', 'Drug', 'DrugStrength', 'Substance'], range_includes: ['MaximumDoseSchedule', 'Role', 'Text', 'URL'], superseded_by: null)]
    case maximumIntake = 'maximumIntake';
    #[SchemaPropertyInfo(rdfs_comment: 'The maximum physical attendee capacity of an <a href="https://schema.org/Event" target="_blank" rel="noopener noreferrer">Event</a> whose <a href="https://schema.org/eventAttendanceMode" target="_blank" rel="noopener noreferrer">eventAttendanceMode</a> is <a href="https://schema.org/OfflineEventAttendanceMode" target="_blank" rel="noopener noreferrer">OfflineEventAttendanceMode</a> (or the offline aspects, in the case of a <a href="https://schema.org/MixedEventAttendanceMode" target="_blank" rel="noopener noreferrer">MixedEventAttendanceMode</a>).', schema_domain_includes: ['Event'], range_includes: ['Integer'], superseded_by: null)]
    case maximumPhysicalAttendeeCapacity = 'maximumPhysicalAttendeeCapacity';
    #[SchemaPropertyInfo(rdfs_comment: 'The maximum virtual attendee capacity of an <a href="https://schema.org/Event" target="_blank" rel="noopener noreferrer">Event</a> whose <a href="https://schema.org/eventAttendanceMode" target="_blank" rel="noopener noreferrer">eventAttendanceMode</a> is <a href="https://schema.org/OnlineEventAttendanceMode" target="_blank" rel="noopener noreferrer">OnlineEventAttendanceMode</a> (or the online aspects, in the case of a <a href="https://schema.org/MixedEventAttendanceMode" target="_blank" rel="noopener noreferrer">MixedEventAttendanceMode</a>).', schema_domain_includes: ['Event'], range_includes: ['Integer'], superseded_by: null)]
    case maximumVirtualAttendeeCapacity = 'maximumVirtualAttendeeCapacity';
    #[SchemaPropertyInfo(rdfs_comment: 'Description of the meals that will be provided or available for purchase.', schema_domain_includes: ['Flight'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case mealService = 'mealService';
    #[SchemaPropertyInfo(rdfs_comment: 'The measuredProperty of an <a href="https://schema.org/Observation" target="_blank" rel="noopener noreferrer">Observation</a>, typically via its <a href="https://schema.org/StatisticalVariable" target="_blank" rel="noopener noreferrer">StatisticalVariable</a>. There are various kinds of applicable <a href="https://schema.org/Property" target="_blank" rel="noopener noreferrer">Property</a>: a schema.org property, a property from other RDF-compatible systems, e.g. W3C RDF Data Cube, Data Commons, Wikidata, or schema.org extensions such as <a href="https://www.gs1.org/voc/?show=properties" target="_blank" rel="noopener noreferrer">GS1\'s</a>.', schema_domain_includes: ['Observation', 'StatisticalVariable'], range_includes: ['Property', 'Role', 'Text', 'URL'], superseded_by: null)]
    case measuredProperty = 'measuredProperty';
    #[SchemaPropertyInfo(rdfs_comment: 'Identifies the denominator variable when an observation represents a ratio or percentage.', schema_domain_includes: ['Observation', 'StatisticalVariable'], range_includes: ['Role', 'StatisticalVariable', 'Text', 'URL'], superseded_by: null)]
    case measurementDenominator = 'measurementDenominator';
    #[SchemaPropertyInfo(rdfs_comment: 'A subproperty of <a href="https://schema.org/measurementTechnique" target="_blank" rel="noopener noreferrer">measurementTechnique</a> that can be used for specifying specific methods, in particular via <a href="https://schema.org/MeasurementMethodEnum" target="_blank" rel="noopener noreferrer">MeasurementMethodEnum</a>.', schema_domain_includes: ['DataCatalog', 'DataDownload', 'Dataset', 'Observation', 'PropertyValue', 'StatisticalVariable'], range_includes: ['DefinedTerm', 'MeasurementMethodEnum', 'Role', 'Text', 'URL'], superseded_by: null)]
    case measurementMethod = 'measurementMethod';
    #[SchemaPropertyInfo(rdfs_comment: 'Provides additional qualification to an observation. For example, a GDP observation measures the Nominal value.', schema_domain_includes: ['Observation', 'StatisticalVariable'], range_includes: ['Enumeration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case measurementQualifier = 'measurementQualifier';
    #[SchemaPropertyInfo(rdfs_comment: 'A technique, method or technology used in an <a href="https://schema.org/Observation" target="_blank" rel="noopener noreferrer">Observation</a>, <a href="https://schema.org/StatisticalVariable" target="_blank" rel="noopener noreferrer">StatisticalVariable</a> or <a href="https://schema.org/Dataset" target="_blank" rel="noopener noreferrer">Dataset</a> (or <a href="https://schema.org/DataDownload" target="_blank" rel="noopener noreferrer">DataDownload</a>, <a href="https://schema.org/DataCatalog" target="_blank" rel="noopener noreferrer">DataCatalog</a>), corresponding to the method used for measuring the corresponding variable(s) (for datasets, described using <a href="https://schema.org/variableMeasured" target="_blank" rel="noopener noreferrer">variableMeasured</a>; for <a href="https://schema.org/Observation" target="_blank" rel="noopener noreferrer">Observation</a>, a <a href="https://schema.org/StatisticalVariable" target="_blank" rel="noopener noreferrer">StatisticalVariable</a>). Often but not necessarily each <a href="https://schema.org/variableMeasured" target="_blank" rel="noopener noreferrer">variableMeasured</a> will have an explicit representation as (or mapping to) an property such as those defined in Schema.org, or other RDF vocabularies and "knowledge graphs". In that case the subproperty of <a href="https://schema.org/variableMeasured" target="_blank" rel="noopener noreferrer">variableMeasured</a> called <a href="https://schema.org/measuredProperty" target="_blank" rel="noopener noreferrer">measuredProperty</a> is applicable.
    
The <a href="https://schema.org/measurementTechnique" target="_blank" rel="noopener noreferrer">measurementTechnique</a> property helps when extra clarification is needed about how a <a href="https://schema.org/measuredProperty" target="_blank" rel="noopener noreferrer">measuredProperty</a> was measured. This is oriented towards scientific and scholarly dataset publication but may have broader applicability; it is not intended as a full representation of measurement, but can often serve as a high level summary for dataset discovery. 

For example, if <a href="https://schema.org/variableMeasured" target="_blank" rel="noopener noreferrer">variableMeasured</a> is: molecule concentration, <a href="https://schema.org/measurementTechnique" target="_blank" rel="noopener noreferrer">measurementTechnique</a> could be: "mass spectrometry" or "nmr spectroscopy" or "colorimetry" or "immunofluorescence". If the <a href="https://schema.org/variableMeasured" target="_blank" rel="noopener noreferrer">variableMeasured</a> is "depression rating", the <a href="https://schema.org/measurementTechnique" target="_blank" rel="noopener noreferrer">measurementTechnique</a> could be "Zung Scale" or "HAM-D" or "Beck Depression Inventory". 

If there are several <a href="https://schema.org/variableMeasured" target="_blank" rel="noopener noreferrer">variableMeasured</a> properties recorded for some given data object, use a <a href="https://schema.org/PropertyValue" target="_blank" rel="noopener noreferrer">PropertyValue</a> for each <a href="https://schema.org/variableMeasured" target="_blank" rel="noopener noreferrer">variableMeasured</a> and attach the corresponding <a href="https://schema.org/measurementTechnique" target="_blank" rel="noopener noreferrer">measurementTechnique</a>. The value can also be from an enumeration, organized as a <a href="https://schema.org/MeasurementMethodEnum" target="_blank" rel="noopener noreferrer">MeasurementMethodEnum</a>.', schema_domain_includes: ['DataCatalog', 'DataDownload', 'Dataset', 'Observation', 'PropertyValue', 'StatisticalVariable'], range_includes: ['DefinedTerm', 'MeasurementMethodEnum', 'Role', 'Text', 'URL'], superseded_by: null)]
    case measurementTechnique = 'measurementTechnique';
    #[SchemaPropertyInfo(rdfs_comment: 'The specific biochemical interaction through which this drug or supplement produces its pharmacological effect.', schema_domain_includes: ['DietarySupplement', 'Drug'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case mechanismOfAction = 'mechanismOfAction';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates a MediaManipulationRatingEnumeration classification of a media object (in the context of how it was published or shared).', schema_domain_includes: ['MediaReview'], range_includes: ['MediaManipulationRatingEnumeration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case mediaAuthenticityCategory = 'mediaAuthenticityCategory';
    #[SchemaPropertyInfo(rdfs_comment: 'In the context of a <a href="https://schema.org/MediaReview" target="_blank" rel="noopener noreferrer">MediaReview</a>, indicates specific media item(s) that are grouped using a <a href="https://schema.org/MediaReviewItem" target="_blank" rel="noopener noreferrer">MediaReviewItem</a>.', schema_domain_includes: ['MediaReviewItem'], range_includes: ['MediaObject', 'Role', 'Text', 'URL'], superseded_by: null)]
    case mediaItemAppearance = 'mediaItemAppearance';
    #[SchemaPropertyInfo(rdfs_comment: 'The median value.', schema_domain_includes: ['QuantitativeValueDistribution'], range_includes: ['Number'], superseded_by: null)]
    case median = 'median';
    #[SchemaPropertyInfo(rdfs_comment: 'Medical audience for page.', schema_domain_includes: ['MedicalWebPage'], range_includes: ['MedicalAudience', 'MedicalAudienceType', 'Role', 'Text', 'URL'], superseded_by: null)]
    case medicalAudience = 'medicalAudience';
    #[SchemaPropertyInfo(rdfs_comment: 'A medical specialty of the provider.', schema_domain_includes: ['Hospital', 'MedicalClinic', 'MedicalOrganization', 'Physician'], range_includes: ['MedicalSpecialty', 'Role', 'Text', 'URL'], superseded_by: null)]
    case medicalSpecialty = 'medicalSpecialty';
    #[SchemaPropertyInfo(rdfs_comment: 'The system of medicine that includes this MedicalEntity, for example \'evidence-based\', \'homeopathic\', \'chiropractic\', etc.', schema_domain_includes: ['MedicalEntity'], range_includes: ['MedicineSystem', 'Role', 'Text', 'URL'], superseded_by: null)]
    case medicineSystem = 'medicineSystem';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates that the vehicle meets the respective emission standard.', schema_domain_includes: ['Vehicle'], range_includes: ['QualitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case meetsEmissionStandard = 'meetsEmissionStandard';
    #[SchemaPropertyInfo(rdfs_comment: 'A member of an Organization or a ProgramMembership. Organizations can be members of organizations; ProgramMembership is typically for individuals.', schema_domain_includes: ['Organization', 'ProgramMembership', 'Role'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case member = 'member';
    #[SchemaPropertyInfo(rdfs_comment: 'An Organization (or ProgramMembership) to which this Person or Organization belongs.', schema_domain_includes: ['Organization', 'Person', 'Role'], range_includes: ['MemberProgramTier', 'Organization', 'ProgramMembership', 'Role', 'Text', 'URL'], superseded_by: null)]
    case memberOf = 'memberOf';
    #[SchemaPropertyInfo(rdfs_comment: 'A member of this organization.', schema_domain_includes: ['Organization', 'ProgramMembership', 'Role'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: 'member')]
    case members = 'members';
    #[SchemaPropertyInfo(rdfs_comment: 'A unique identifier for the membership.', schema_domain_includes: ['ProgramMembership'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case membershipNumber = 'membershipNumber';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of membership points earned by the member. If necessary, the unitText can be used to express the units the points are issued in. (E.g. stars, miles, etc.)', schema_domain_includes: ['MemberProgramTier', 'PriceSpecification', 'ProgramMembership'], range_includes: ['Number', 'QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case membershipPointsEarned = 'membershipPointsEarned';
    #[SchemaPropertyInfo(rdfs_comment: 'Minimum memory requirements.', schema_domain_includes: ['SoftwareApplication'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case memoryRequirements = 'memoryRequirements';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates that the CreativeWork contains a reference to, but is not necessarily about a concept.', schema_domain_includes: ['CreativeWork'], range_includes: ['Role', 'Text', 'Thing', 'URL'], superseded_by: null)]
    case mentions = 'mentions';
    #[SchemaPropertyInfo(rdfs_comment: 'Either the actual menu as a structured representation, as text, or a URL of the menu.', schema_domain_includes: ['FoodEstablishment'], range_includes: ['Menu', 'Role', 'Text', 'URL'], superseded_by: 'hasMenu')]
    case menu = 'menu';
    #[SchemaPropertyInfo(rdfs_comment: 'Additional menu item(s) such as a side dish of salad or side order of fries that can be added to this menu item. Additionally it can be a menu section containing allowed add-on menu items for this menu item.', schema_domain_includes: ['MenuItem'], range_includes: ['MenuItem', 'MenuSection', 'Role', 'Text', 'URL'], superseded_by: null)]
    case menuAddOn = 'menuAddOn';
    #[SchemaPropertyInfo(rdfs_comment: '\'merchant\' is an out-dated term for \'seller\'.', schema_domain_includes: ['Order'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: 'seller')]
    case merchant = 'merchant';
    #[SchemaPropertyInfo(rdfs_comment: 'Specifies either a fixed return date or the number of days (from the delivery date) that a product can be returned. Used when the <a href="https://schema.org/returnPolicyCategory" target="_blank" rel="noopener noreferrer">returnPolicyCategory</a> property is specified as <a href="https://schema.org/MerchantReturnFiniteReturnWindow" target="_blank" rel="noopener noreferrer">MerchantReturnFiniteReturnWindow</a>.', schema_domain_includes: ['MerchantReturnPolicy', 'MerchantReturnPolicySeasonalOverride'], range_includes: ['Date', 'DateTime', 'Integer'], superseded_by: null)]
    case merchantReturnDays = 'merchantReturnDays';
    #[SchemaPropertyInfo(rdfs_comment: 'Specifies a Web page or service by URL, for product returns.', schema_domain_includes: ['MerchantReturnPolicy'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case merchantReturnLink = 'merchantReturnLink';
    #[SchemaPropertyInfo(rdfs_comment: 'A CreativeWork attached to the message.', schema_domain_includes: ['Message'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case messageAttachment = 'messageAttachment';
    #[SchemaPropertyInfo(rdfs_comment: 'The total distance travelled by the particular vehicle since its initial production, as read from its odometer.\\n\\nTypical unit code(s): KMT for kilometers, SMI for statute miles.', schema_domain_includes: ['Vehicle'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case mileageFromOdometer = 'mileageFromOdometer';
    #[SchemaPropertyInfo(rdfs_comment: 'The lowest price if the price is a range.', schema_domain_includes: ['PriceSpecification'], range_includes: ['Number'], superseded_by: null)]
    case minPrice = 'minPrice';
    #[SchemaPropertyInfo(rdfs_comment: 'The lower value of some characteristic or property.', schema_domain_includes: ['MonetaryAmount', 'PropertyValue', 'PropertyValueSpecification', 'QuantitativeValue'], range_includes: ['Number'], superseded_by: null)]
    case minValue = 'minValue';
    #[SchemaPropertyInfo(rdfs_comment: 'The minimum payment required at this time.', schema_domain_includes: ['Invoice'], range_includes: ['MonetaryAmount', 'PriceSpecification', 'Role', 'Text', 'URL'], superseded_by: null)]
    case minimumPaymentDue = 'minimumPaymentDue';
    #[SchemaPropertyInfo(rdfs_comment: 'For a <a href="https://schema.org/NewsMediaOrganization" target="_blank" rel="noopener noreferrer">NewsMediaOrganization</a>, a statement on coverage priorities, including any public agenda or stance on issues.', schema_domain_includes: ['NewsMediaOrganization'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case missionCoveragePrioritiesPolicy = 'missionCoveragePrioritiesPolicy';
    #[SchemaPropertyInfo(rdfs_comment: 'The <a href="https://schema.org/mobileUrl" target="_blank" rel="noopener noreferrer">mobileUrl</a> property is provided for specific situations in which data consumers need to determine whether one of several provided URLs is a dedicated \'mobile site\'.

To discourage over-use, and reflecting intial usecases, the property is expected only on <a href="https://schema.org/Product" target="_blank" rel="noopener noreferrer">Product</a> and <a href="https://schema.org/Offer" target="_blank" rel="noopener noreferrer">Offer</a>, rather than <a href="https://schema.org/Thing" target="_blank" rel="noopener noreferrer">Thing</a>. The general trend in web technology is towards <a href="https://en.wikipedia.org/wiki/Responsive_web_design" target="_blank" rel="noopener noreferrer">responsive design</a> in which content can be flexibly adapted to a wide range of browsing environments. Pages and sites referenced with the long-established <a href="https://schema.org/url" target="_blank" rel="noopener noreferrer">url</a> property should ideally also be usable on a wide variety of devices, including mobile phones. In most cases, it would be pointless and counter productive to attempt to update all <a href="https://schema.org/url" target="_blank" rel="noopener noreferrer">url</a> markup to use <a href="https://schema.org/mobileUrl" target="_blank" rel="noopener noreferrer">mobileUrl</a> for more mobile-oriented pages. The property is intended for the case when items (primarily <a href="https://schema.org/Product" target="_blank" rel="noopener noreferrer">Product</a> and <a href="https://schema.org/Offer" target="_blank" rel="noopener noreferrer">Offer</a>) have extra URLs hosted on an additional "mobile site" alongside the main one. It should not be taken as an endorsement of this publication style.', schema_domain_includes: ['Offer', 'Product'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case mobileUrl = 'mobileUrl';
    #[SchemaPropertyInfo(rdfs_comment: 'The model of the product. Use with the URL of a ProductModel or a textual representation of the model identifier. The URL of the ProductModel can be from an external source. It is recommended to additionally provide strong product identifiers via the gtin8/gtin13/gtin14 and mpn properties.', schema_domain_includes: ['Product'], range_includes: ['ProductModel', 'Role', 'Text', 'URL'], superseded_by: null)]
    case model = 'model';
    #[SchemaPropertyInfo(rdfs_comment: 'The release date of a vehicle model (often used to differentiate versions of the same make and model).', schema_domain_includes: ['Vehicle'], range_includes: ['Date'], superseded_by: null)]
    case modelDate = 'modelDate';
    #[SchemaPropertyInfo(rdfs_comment: 'The date and time the reservation was modified.', schema_domain_includes: ['Reservation'], range_includes: ['DateTime'], superseded_by: null)]
    case modifiedTime = 'modifiedTime';
    #[SchemaPropertyInfo(rdfs_comment: 'The empirical formula is the simplest whole number ratio of all the atoms in a molecule.', schema_domain_includes: ['MolecularEntity'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case molecularFormula = 'molecularFormula';
    #[SchemaPropertyInfo(rdfs_comment: 'This is the molecular weight of the entity being described, not of the parent. Units should be included in the form \'<Number> <unit>\', for example \'12 amu\' or as \'<QuantitativeValue>.', schema_domain_includes: ['MolecularEntity'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case molecularWeight = 'molecularWeight';
    #[SchemaPropertyInfo(rdfs_comment: 'The monoisotopic mass is the sum of the masses of the atoms in a molecule using the unbound, ground-state, rest mass of the principal (most abundant) isotope for each element instead of the isotopic average mass. Please include the units in the form \'<Number> <unit>\', for example \'770.230488 g/mol\' or as \'<QuantitativeValue>.', schema_domain_includes: ['MolecularEntity'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case monoisotopicMolecularWeight = 'monoisotopicMolecularWeight';
    #[SchemaPropertyInfo(rdfs_comment: 'The minimum payment is the lowest amount of money that one is required to pay on a credit card statement each month.', schema_domain_includes: ['PaymentCard'], range_includes: ['MonetaryAmount', 'Number', 'Role', 'Text', 'URL'], superseded_by: null)]
    case monthlyMinimumRepaymentAmount = 'monthlyMinimumRepaymentAmount';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates the minimal number of months of experience required for a position.', schema_domain_includes: ['OccupationalExperienceRequirements'], range_includes: ['Number'], superseded_by: null)]
    case monthsOfExperience = 'monthsOfExperience';
    #[SchemaPropertyInfo(rdfs_comment: 'The Manufacturer Part Number (MPN) of the product, or the product to which the offer refers.', schema_domain_includes: ['Demand', 'Offer', 'Product'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case mpn = 'mpn';
    #[SchemaPropertyInfo(rdfs_comment: 'Whether multiple values are allowed for the property.  Default is false.', schema_domain_includes: ['PropertyValueSpecification'], range_includes: ['Boolean'], superseded_by: null)]
    case multipleValues = 'multipleValues';
    #[SchemaPropertyInfo(rdfs_comment: 'The movement the muscle generates.', schema_domain_includes: ['Muscle'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case muscleAction = 'muscleAction';
    #[SchemaPropertyInfo(rdfs_comment: 'An arrangement derived from the composition.', schema_domain_includes: ['MusicComposition'], range_includes: ['MusicComposition', 'Role', 'Text', 'URL'], superseded_by: null)]
    case musicArrangement = 'musicArrangement';
    #[SchemaPropertyInfo(rdfs_comment: 'The composer of the soundtrack.', schema_domain_includes: ['Clip', 'Episode', 'Movie', 'MovieSeries', 'RadioSeries', 'TVSeries', 'VideoGame', 'VideoGameSeries', 'VideoObject'], range_includes: ['MusicGroup', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case musicBy = 'musicBy';
    #[SchemaPropertyInfo(rdfs_comment: 'The type of composition (e.g. overture, sonata, symphony, etc.).', schema_domain_includes: ['MusicComposition'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case musicCompositionForm = 'musicCompositionForm';
    #[SchemaPropertyInfo(rdfs_comment: 'A member of a music group—for example, John, Paul, George, or Ringo.', schema_domain_includes: ['MusicGroup'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: 'member')]
    case musicGroupMember = 'musicGroupMember';
    #[SchemaPropertyInfo(rdfs_comment: 'Format of this release (the type of recording media used, i.e. compact disc, digital media, LP, etc.).', schema_domain_includes: ['MusicRelease'], range_includes: ['MusicReleaseFormatType', 'Role', 'Text', 'URL'], superseded_by: null)]
    case musicReleaseFormat = 'musicReleaseFormat';
    #[SchemaPropertyInfo(rdfs_comment: 'The key, mode, or scale this composition uses.', schema_domain_includes: ['MusicComposition'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case musicalKey = 'musicalKey';
    #[SchemaPropertyInfo(rdfs_comment: 'The North American Industry Classification System (NAICS) code for a particular organization or business person.', schema_domain_includes: ['Organization', 'Person'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case naics = 'naics';
    #[SchemaPropertyInfo(rdfs_comment: 'The name of the item.', schema_domain_includes: ['Thing'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case name = 'name';
    #[SchemaPropertyInfo(rdfs_comment: 'A position played, performed or filled by a person or organization, as part of an organization. For example, an athlete in a SportsTeam might play in the position named \'Quarterback\'.', schema_domain_includes: ['Role'], range_includes: ['Role', 'Text', 'URL'], superseded_by: 'roleName')]
    case namedPosition = 'namedPosition';
    #[SchemaPropertyInfo(rdfs_comment: 'Nationality of the person.', schema_domain_includes: ['Person'], range_includes: ['Country', 'Role', 'Text', 'URL'], superseded_by: null)]
    case nationality = 'nationality';
    #[SchemaPropertyInfo(rdfs_comment: 'The expected progression of the condition if it is not treated and allowed to progress naturally.', schema_domain_includes: ['MedicalCondition'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case naturalProgression = 'naturalProgression';
    #[SchemaPropertyInfo(rdfs_comment: 'Provides negative considerations regarding something, most typically in pro/con lists for reviews (alongside <a href="https://schema.org/positiveNotes" target="_blank" rel="noopener noreferrer">positiveNotes</a>). For symmetry 

In the case of a <a href="https://schema.org/Review" target="_blank" rel="noopener noreferrer">Review</a>, the property describes the <a href="https://schema.org/itemReviewed" target="_blank" rel="noopener noreferrer">itemReviewed</a> from the perspective of the review; in the case of a <a href="https://schema.org/Product" target="_blank" rel="noopener noreferrer">Product</a>, the product itself is being described. Since product descriptions 
tend to emphasise positive claims, it may be relatively unusual to find <a href="https://schema.org/negativeNotes" target="_blank" rel="noopener noreferrer">negativeNotes</a> used in this way. Nevertheless for the sake of symmetry, <a href="https://schema.org/negativeNotes" target="_blank" rel="noopener noreferrer">negativeNotes</a> can be used on <a href="https://schema.org/Product" target="_blank" rel="noopener noreferrer">Product</a>.

The property values can be expressed either as unstructured text (repeated as necessary), or if ordered, as a list (in which case the most negative is at the beginning of the list).', schema_domain_includes: ['Product', 'Review'], range_includes: ['ItemList', 'ListItem', 'Role', 'Text', 'URL', 'WebContent'], superseded_by: null)]
    case negativeNotes = 'negativeNotes';
    #[SchemaPropertyInfo(rdfs_comment: 'The underlying innervation associated with the muscle.', schema_domain_includes: ['Muscle'], range_includes: ['Nerve', 'Role', 'Text', 'URL'], superseded_by: null)]
    case nerve = 'nerve';
    #[SchemaPropertyInfo(rdfs_comment: 'The neurological pathway extension that involves muscle control.', schema_domain_includes: ['Nerve'], range_includes: ['Muscle', 'Role', 'Text', 'URL'], superseded_by: null)]
    case nerveMotor = 'nerveMotor';
    #[SchemaPropertyInfo(rdfs_comment: 'The total financial value of the person as calculated by subtracting the total value of liabilities from the total value of assets.', schema_domain_includes: ['Person'], range_includes: ['MonetaryAmount', 'PriceSpecification', 'Role', 'Text', 'URL'], superseded_by: null)]
    case netWorth = 'netWorth';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates a page with news updates and guidelines. This could often be (but is not required to be) the main page containing <a href="https://schema.org/SpecialAnnouncement" target="_blank" rel="noopener noreferrer">SpecialAnnouncement</a> markup on a site.', schema_domain_includes: ['SpecialAnnouncement'], range_includes: ['Role', 'Text', 'URL', 'WebContent'], superseded_by: null)]
    case newsUpdatesAndGuidelines = 'newsUpdatesAndGuidelines';
    #[SchemaPropertyInfo(rdfs_comment: 'A link to the ListItem that follows the current one.', schema_domain_includes: ['ListItem'], range_includes: ['ListItem', 'Role', 'Text', 'URL'], superseded_by: null)]
    case nextItem = 'nextItem';
    #[SchemaPropertyInfo(rdfs_comment: 'For a <a href="https://schema.org/NewsMediaOrganization" target="_blank" rel="noopener noreferrer">NewsMediaOrganization</a> or other news-related <a href="https://schema.org/Organization" target="_blank" rel="noopener noreferrer">Organization</a>, a statement explaining when authors of articles are not named in bylines.', schema_domain_includes: ['NewsMediaOrganization'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case noBylinesPolicy = 'noBylinesPolicy';
    #[SchemaPropertyInfo(rdfs_comment: 'This ordering relation for qualitative values indicates that the subject is not equal to the object.', schema_domain_includes: ['QualitativeValue'], range_includes: ['QualitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case nonEqual = 'nonEqual';
    #[SchemaPropertyInfo(rdfs_comment: 'The generic name of this drug or supplement.', schema_domain_includes: ['DietarySupplement', 'Drug'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case nonProprietaryName = 'nonProprietaryName';
    #[SchemaPropertyInfo(rdfs_comment: 'nonprofitStatus indicates the legal status of a non-profit organization in its primary place of business.', schema_domain_includes: ['Organization'], range_includes: ['NonprofitType', 'Role', 'Text', 'URL'], superseded_by: null)]
    case nonprofitStatus = 'nonprofitStatus';
    #[SchemaPropertyInfo(rdfs_comment: 'Range of acceptable values for a typical patient, when applicable.', schema_domain_includes: ['MedicalTest'], range_includes: ['MedicalEnumeration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case normalRange = 'normalRange';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates the <a href="https://en.wikipedia.org/wiki/NATO_Stock_Number" target="_blank" rel="noopener noreferrer">NATO stock number</a> (nsn) of a <a href="https://schema.org/Product" target="_blank" rel="noopener noreferrer">Product</a>.', schema_domain_includes: ['Product'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case nsn = 'nsn';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of adults staying in the unit.', schema_domain_includes: ['LodgingReservation'], range_includes: ['Integer', 'QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case numAdults = 'numAdults';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of children staying in the unit.', schema_domain_includes: ['LodgingReservation'], range_includes: ['Integer', 'QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case numChildren = 'numChildren';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates the number of constraints property values defined for a particular <a href="https://schema.org/ConstraintNode" target="_blank" rel="noopener noreferrer">ConstraintNode</a> such as <a href="https://schema.org/StatisticalVariable" target="_blank" rel="noopener noreferrer">StatisticalVariable</a>. This helps applications understand if they have access to a sufficiently complete description of a <a href="https://schema.org/StatisticalVariable" target="_blank" rel="noopener noreferrer">StatisticalVariable</a> or other construct that is defined using properties on template-style nodes.', schema_domain_includes: ['ConstraintNode'], range_includes: ['Integer'], superseded_by: null)]
    case numConstraints = 'numConstraints';
    #[SchemaPropertyInfo(rdfs_comment: 'Limits the number of items being shipped for which these conditions apply.', schema_domain_includes: ['ShippingConditions'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case numItems = 'numItems';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of tracks in this album or playlist.', schema_domain_includes: ['MusicPlaylist'], range_includes: ['Integer'], superseded_by: null)]
    case numTracks = 'numTracks';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates the total (available plus unavailable) number of accommodation units in an <a href="https://schema.org/ApartmentComplex" target="_blank" rel="noopener noreferrer">ApartmentComplex</a>, or the number of accommodation units for a specific <a href="https://schema.org/FloorPlan" target="_blank" rel="noopener noreferrer">FloorPlan</a> (within its specific <a href="https://schema.org/ApartmentComplex" target="_blank" rel="noopener noreferrer">ApartmentComplex</a>). See also <a href="https://schema.org/numberOfAvailableAccommodationUnits" target="_blank" rel="noopener noreferrer">numberOfAvailableAccommodationUnits</a>.', schema_domain_includes: ['ApartmentComplex', 'FloorPlan'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case numberOfAccommodationUnits = 'numberOfAccommodationUnits';
    #[SchemaPropertyInfo(rdfs_comment: 'The number or type of airbags in the vehicle.', schema_domain_includes: ['Vehicle'], range_includes: ['Number', 'Role', 'Text', 'URL'], superseded_by: null)]
    case numberOfAirbags = 'numberOfAirbags';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates the number of available accommodation units in an <a href="https://schema.org/ApartmentComplex" target="_blank" rel="noopener noreferrer">ApartmentComplex</a>, or the number of accommodation units for a specific <a href="https://schema.org/FloorPlan" target="_blank" rel="noopener noreferrer">FloorPlan</a> (within its specific <a href="https://schema.org/ApartmentComplex" target="_blank" rel="noopener noreferrer">ApartmentComplex</a>). See also <a href="https://schema.org/numberOfAccommodationUnits" target="_blank" rel="noopener noreferrer">numberOfAccommodationUnits</a>.', schema_domain_includes: ['ApartmentComplex', 'FloorPlan'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case numberOfAvailableAccommodationUnits = 'numberOfAvailableAccommodationUnits';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of axles.\\n\\nTypical unit code(s): C62.', schema_domain_includes: ['Vehicle'], range_includes: ['Number', 'QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case numberOfAxles = 'numberOfAxles';
    #[SchemaPropertyInfo(rdfs_comment: 'The total integer number of bathrooms in some <a href="https://schema.org/Accommodation" target="_blank" rel="noopener noreferrer">Accommodation</a>, following real estate conventions as <a href="https://ddwiki.reso.org/display/DDW17/BathroomsTotalInteger+Field" target="_blank" rel="noopener noreferrer">documented in RESO</a>: "The simple sum of the number of bathrooms. For example for a property with two Full Bathrooms and one Half Bathroom, the Bathrooms Total Integer will be 3.". See also <a href="https://schema.org/numberOfRooms" target="_blank" rel="noopener noreferrer">numberOfRooms</a>.', schema_domain_includes: ['Accommodation', 'FloorPlan'], range_includes: ['Integer'], superseded_by: null)]
    case numberOfBathroomsTotal = 'numberOfBathroomsTotal';
    #[SchemaPropertyInfo(rdfs_comment: 'The total integer number of bedrooms in a some <a href="https://schema.org/Accommodation" target="_blank" rel="noopener noreferrer">Accommodation</a>, <a href="https://schema.org/ApartmentComplex" target="_blank" rel="noopener noreferrer">ApartmentComplex</a> or <a href="https://schema.org/FloorPlan" target="_blank" rel="noopener noreferrer">FloorPlan</a>.', schema_domain_includes: ['Accommodation', 'ApartmentComplex', 'FloorPlan'], range_includes: ['Number', 'QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case numberOfBedrooms = 'numberOfBedrooms';
    #[SchemaPropertyInfo(rdfs_comment: 'The quantity of the given bed type available in the HotelRoom, Suite, House, or Apartment.', schema_domain_includes: ['BedDetails'], range_includes: ['Number'], superseded_by: null)]
    case numberOfBeds = 'numberOfBeds';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of credits or units awarded by a Course or required to complete an EducationalOccupationalProgram.', schema_domain_includes: ['Course', 'EducationalOccupationalProgram'], range_includes: ['Integer', 'Role', 'StructuredValue', 'Text', 'URL'], superseded_by: null)]
    case numberOfCredits = 'numberOfCredits';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of doors.\\n\\nTypical unit code(s): C62.', schema_domain_includes: ['Vehicle'], range_includes: ['Number', 'QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case numberOfDoors = 'numberOfDoors';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of employees in an organization, e.g. business.', schema_domain_includes: ['BusinessAudience', 'Organization'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case numberOfEmployees = 'numberOfEmployees';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of episodes in this season or series.', schema_domain_includes: ['CreativeWorkSeason', 'RadioSeries', 'TVSeries', 'VideoGameSeries'], range_includes: ['Integer'], superseded_by: null)]
    case numberOfEpisodes = 'numberOfEpisodes';
    #[SchemaPropertyInfo(rdfs_comment: 'The total number of forward gears available for the transmission system of the vehicle.\\n\\nTypical unit code(s): C62.', schema_domain_includes: ['Vehicle'], range_includes: ['Number', 'QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case numberOfForwardGears = 'numberOfForwardGears';
    #[SchemaPropertyInfo(rdfs_comment: 'Number of full bathrooms - The total number of full and ¾ bathrooms in an <a href="https://schema.org/Accommodation" target="_blank" rel="noopener noreferrer">Accommodation</a>. This corresponds to the <a href="https://ddwiki.reso.org/display/DDW17/BathroomsFull+Field" target="_blank" rel="noopener noreferrer">BathroomsFull field in RESO</a>.', schema_domain_includes: ['Accommodation', 'FloorPlan'], range_includes: ['Number'], superseded_by: null)]
    case numberOfFullBathrooms = 'numberOfFullBathrooms';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of items in an ItemList. Note that some descriptions might not fully describe all items in a list (e.g., multi-page pagination); in such cases, the numberOfItems would be for the entire list.', schema_domain_includes: ['ItemList'], range_includes: ['Integer'], superseded_by: null)]
    case numberOfItems = 'numberOfItems';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of payments contractually required at origination to repay the loan. For monthly paying loans this is the number of months from the contractual first payment date to the maturity date.', schema_domain_includes: ['RepaymentSpecification'], range_includes: ['Number'], superseded_by: null)]
    case numberOfLoanPayments = 'numberOfLoanPayments';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of pages in the book.', schema_domain_includes: ['Book'], range_includes: ['Integer'], superseded_by: null)]
    case numberOfPages = 'numberOfPages';
    #[SchemaPropertyInfo(rdfs_comment: 'Number of partial bathrooms - The total number of half and ¼ bathrooms in an <a href="https://schema.org/Accommodation" target="_blank" rel="noopener noreferrer">Accommodation</a>. This corresponds to the <a href="https://ddwiki.reso.org/display/DDW17/BathroomsPartial+Field" target="_blank" rel="noopener noreferrer">BathroomsPartial field in RESO</a>.', schema_domain_includes: ['Accommodation', 'FloorPlan'], range_includes: ['Number'], superseded_by: null)]
    case numberOfPartialBathrooms = 'numberOfPartialBathrooms';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicate how many people can play this game (minimum, maximum, or range).', schema_domain_includes: ['Game', 'VideoGameSeries'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case numberOfPlayers = 'numberOfPlayers';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of owners of the vehicle, including the current one.\\n\\nTypical unit code(s): C62.', schema_domain_includes: ['Vehicle'], range_includes: ['Number', 'QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case numberOfPreviousOwners = 'numberOfPreviousOwners';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of rooms (excluding bathrooms and closets) of the accommodation or lodging business.
Typical unit code(s): ROM for room or C62 for no unit. The type of room can be put in the unitText property of the QuantitativeValue.', schema_domain_includes: ['Accommodation', 'Apartment', 'FloorPlan', 'House', 'LodgingBusiness', 'SingleFamilyResidence', 'Suite'], range_includes: ['Number', 'QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case numberOfRooms = 'numberOfRooms';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of seasons in this series.', schema_domain_includes: ['RadioSeries', 'TVSeries', 'VideoGameSeries'], range_includes: ['Integer'], superseded_by: null)]
    case numberOfSeasons = 'numberOfSeasons';
    #[SchemaPropertyInfo(rdfs_comment: 'A number associated with a role in an organization, for example, the number on an athlete\'s jersey.', schema_domain_includes: ['OrganizationRole'], range_includes: ['Number'], superseded_by: null)]
    case numberedPosition = 'numberedPosition';
    #[SchemaPropertyInfo(rdfs_comment: 'Nutrition information about the recipe or menu item.', schema_domain_includes: ['MenuItem', 'Recipe'], range_includes: ['NutritionInformation', 'Role', 'Text', 'URL'], superseded_by: null)]
    case nutrition = 'nutrition';
    #[SchemaPropertyInfo(rdfs_comment: 'The object upon which the action is carried out, whose state is kept intact or changed. Also known as the semantic roles patient, affected or undergoer (which change their state) or theme (which doesn\'t). E.g. John read *a book*.', schema_domain_includes: ['Action'], range_includes: ['Role', 'Text', 'Thing', 'URL'], superseded_by: null)]
    case object = 'object';
    #[SchemaPropertyInfo(rdfs_comment: 'The <a href="https://schema.org/observationAbout" target="_blank" rel="noopener noreferrer">observationAbout</a> property identifies an entity, often a <a href="https://schema.org/Place" target="_blank" rel="noopener noreferrer">Place</a>, associated with an <a href="https://schema.org/Observation" target="_blank" rel="noopener noreferrer">Observation</a>.', schema_domain_includes: ['Observation'], range_includes: ['Place', 'Role', 'Text', 'Thing', 'URL'], superseded_by: null)]
    case observationAbout = 'observationAbout';
    #[SchemaPropertyInfo(rdfs_comment: 'The observationDate of an <a href="https://schema.org/Observation" target="_blank" rel="noopener noreferrer">Observation</a>.', schema_domain_includes: ['Observation'], range_includes: ['Date', 'DateTime'], superseded_by: null)]
    case observationDate = 'observationDate';
    #[SchemaPropertyInfo(rdfs_comment: 'The length of time an Observation took place over. The format follows `P[0-9]*[Y|M|D|h|m|s]`. For example, P1Y is Period 1 Year, P3M is Period 3 Months, P3h is Period 3 hours.', schema_domain_includes: ['Observation'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case observationPeriod = 'observationPeriod';
    #[SchemaPropertyInfo(rdfs_comment: 'The allowed total occupancy for the accommodation in persons (including infants etc). For individual accommodations, this is not necessarily the legal maximum but defines the permitted usage as per the contractual agreement (e.g. a double room used by a single person).
Typical unit code(s): C62 for person.', schema_domain_includes: ['Accommodation', 'Apartment', 'HotelRoom', 'SingleFamilyResidence', 'Suite'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case occupancy = 'occupancy';
    #[SchemaPropertyInfo(rdfs_comment: 'The region/country for which this occupational description is appropriate. Note that educational requirements and qualifications can vary between jurisdictions.', schema_domain_includes: ['Occupation'], range_includes: ['AdministrativeArea', 'Role', 'Text', 'URL'], superseded_by: null)]
    case occupationLocation = 'occupationLocation';
    #[SchemaPropertyInfo(rdfs_comment: 'A category describing the job, preferably using a term from a taxonomy such as <a href="http://www.onetcenter.org/taxonomy.html" target="_blank" rel="noopener noreferrer">BLS O*NET-SOC</a>, <a href="https://www.ilo.org/public/english/bureau/stat/isco/isco08/" target="_blank" rel="noopener noreferrer">ISCO-08</a> or similar, with the property repeated for each applicable value. Ideally the taxonomy should be identified, and both the textual label and formal code for the category should be provided.\\n
Note: for historical reasons, any textual label and formal code provided as a literal may be assumed to be from O*NET-SOC.', schema_domain_includes: ['EducationalOccupationalProgram', 'JobPosting', 'Occupation', 'Physician', 'WorkBasedProgram'], range_includes: ['CategoryCode', 'Role', 'Text', 'URL'], superseded_by: null)]
    case occupationalCategory = 'occupationalCategory';
    #[SchemaPropertyInfo(rdfs_comment: 'A description of the qualification, award, certificate, diploma or other occupational credential awarded as a consequence of successful completion of this course or program.', schema_domain_includes: ['Course', 'EducationalOccupationalProgram'], range_includes: ['EducationalOccupationalCredential', 'Role', 'Text', 'URL'], superseded_by: null)]
    case occupationalCredentialAwarded = 'occupationalCredentialAwarded';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of offers for the product.', schema_domain_includes: ['AggregateOffer'], range_includes: ['Integer'], superseded_by: null)]
    case offerCount = 'offerCount';
    #[SchemaPropertyInfo(rdfs_comment: 'A pointer to the organization or person making the offer.', schema_domain_includes: ['Offer'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case offeredBy = 'offeredBy';
    #[SchemaPropertyInfo(rdfs_comment: 'An offer to provide this item—for example, an offer to sell a product, rent the DVD of a movie, perform a service, or give away tickets to an event. Use <a href="https://schema.org/businessFunction" target="_blank" rel="noopener noreferrer">businessFunction</a> to indicate the kind of transaction offered, i.e. sell, lease, etc. This property can also be used to describe a <a href="https://schema.org/Demand" target="_blank" rel="noopener noreferrer">Demand</a>. While this property is listed as expected on a number of common types, it can be used in others. In that case, using a second type, such as Product or a subtype of Product, can clarify the nature of the offer.', schema_domain_includes: ['AggregateOffer', 'CreativeWork', 'EducationalOccupationalProgram', 'Event', 'MenuItem', 'Product', 'Service', 'Trip'], range_includes: ['Demand', 'Offer', 'Role', 'Text', 'URL'], superseded_by: null)]
    case offers = 'offers';
    #[SchemaPropertyInfo(rdfs_comment: 'Whether prescriptions can be delivered by mail.', schema_domain_includes: ['HealthPlanFormulary'], range_includes: ['Boolean'], superseded_by: null)]
    case offersPrescriptionByMail = 'offersPrescriptionByMail';
    #[SchemaPropertyInfo(rdfs_comment: 'The general opening hours for a business. Opening hours can be specified as a weekly time range, starting with days, then times per day. Multiple days can be listed with commas \',\' separating each day. Day or time ranges are specified using a hyphen \'-\'.\\n\\n* Days are specified using the following two-letter combinations: ```Mo```, ```Tu```, ```We```, ```Th```, ```Fr```, ```Sa```, ```Su```.\\n* Times are specified using 24:00 format. For example, 3pm is specified as ```15:00```, 10am as ```10:00```. \\n* Here is an example: <code><time itemprop="openingHours" datetime="Tu,Th 16:00-20:00">Tuesdays and Thursdays 4-8pm</time></code>.\\n* If a business is open 7 days a week, then it can be specified as <code><time itemprop="openingHours" datetime="Mo-Su">Monday through Sunday, all day</time></code>.', schema_domain_includes: ['CivicStructure', 'LocalBusiness'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case openingHours = 'openingHours';
    #[SchemaPropertyInfo(rdfs_comment: 'The opening hours of a certain place.', schema_domain_includes: ['Place'], range_includes: ['OpeningHoursSpecification', 'Role', 'Text', 'URL'], superseded_by: null)]
    case openingHoursSpecification = 'openingHoursSpecification';
    #[SchemaPropertyInfo(rdfs_comment: 'The opening hour of the place or service on the given day(s) of the week.', schema_domain_includes: ['OpeningHoursSpecification'], range_includes: ['Time'], superseded_by: null)]
    case opens = 'opens';
    #[SchemaPropertyInfo(rdfs_comment: 'Operating systems supported (Windows 7, OS X 10.6, Android 1.6).', schema_domain_includes: ['SoftwareApplication'], range_includes: ['OperatingSystem', 'Role', 'Text', 'URL'], superseded_by: null)]
    case operatingSystem = 'operatingSystem';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of participant. The opponent on this action.', schema_domain_includes: ['ExerciseAction'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case opponent = 'opponent';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of object. The options subject to this action.', schema_domain_includes: ['ChooseAction'], range_includes: ['Role', 'Text', 'Thing', 'URL'], superseded_by: 'actionOption')]
    case option = 'option';
    #[SchemaPropertyInfo(rdfs_comment: 'Date order was placed.', schema_domain_includes: ['Order'], range_includes: ['Date', 'DateTime'], superseded_by: null)]
    case orderDate = 'orderDate';
    #[SchemaPropertyInfo(rdfs_comment: 'The delivery of the parcel related to this order or order item.', schema_domain_includes: ['Order', 'OrderItem'], range_includes: ['ParcelDelivery', 'Role', 'Text', 'URL'], superseded_by: null)]
    case orderDelivery = 'orderDelivery';
    #[SchemaPropertyInfo(rdfs_comment: 'The identifier of the order item.', schema_domain_includes: ['OrderItem'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case orderItemNumber = 'orderItemNumber';
    #[SchemaPropertyInfo(rdfs_comment: 'The current status of the order item.', schema_domain_includes: ['OrderItem'], range_includes: ['OrderStatus', 'Role', 'Text', 'URL'], superseded_by: null)]
    case orderItemStatus = 'orderItemStatus';
    #[SchemaPropertyInfo(rdfs_comment: 'The identifier of the transaction.', schema_domain_includes: ['Order'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case orderNumber = 'orderNumber';
    #[SchemaPropertyInfo(rdfs_comment: 'Value representing the fraction of the value of the order that is charged as shipping cost. Example: 0.10 would mean shipping rate is 10% of the total order value.', schema_domain_includes: ['ShippingRateSettings'], range_includes: ['Number'], superseded_by: null)]
    case orderPercentage = 'orderPercentage';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of the item ordered. If the property is not set, assume the quantity is one.', schema_domain_includes: ['OrderItem'], range_includes: ['Number', 'QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case orderQuantity = 'orderQuantity';
    #[SchemaPropertyInfo(rdfs_comment: 'The current status of the order.', schema_domain_includes: ['Order'], range_includes: ['OrderStatus', 'Role', 'Text', 'URL'], superseded_by: null)]
    case orderStatus = 'orderStatus';
    #[SchemaPropertyInfo(rdfs_comment: 'Minimum and maximum order value for which these shipping conditions are valid.', schema_domain_includes: ['ShippingConditions'], range_includes: ['MonetaryAmount', 'Role', 'Text', 'URL'], superseded_by: null)]
    case orderValue = 'orderValue';
    #[SchemaPropertyInfo(rdfs_comment: 'The item ordered.', schema_domain_includes: ['Order', 'OrderItem'], range_includes: ['OrderItem', 'Product', 'Role', 'Service', 'Text', 'URL'], superseded_by: null)]
    case orderedItem = 'orderedItem';
    #[SchemaPropertyInfo(rdfs_comment: 'An organizer of an Event.', schema_domain_includes: ['Event', 'Role'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case organizer = 'organizer';
    #[SchemaPropertyInfo(rdfs_comment: 'Shipper\'s address.', schema_domain_includes: ['ParcelDelivery'], range_includes: ['PostalAddress', 'Role', 'Text', 'URL'], superseded_by: null)]
    case originAddress = 'originAddress';
    #[SchemaPropertyInfo(rdfs_comment: 'Describes, in a <a href="https://schema.org/MediaReview" target="_blank" rel="noopener noreferrer">MediaReview</a> when dealing with <a href="https://schema.org/DecontextualizedContent" target="_blank" rel="noopener noreferrer">DecontextualizedContent</a>, background information that can contribute to better interpretation of the <a href="https://schema.org/MediaObject" target="_blank" rel="noopener noreferrer">MediaObject</a>.', schema_domain_includes: ['MediaReview'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case originalMediaContextDescription = 'originalMediaContextDescription';
    #[SchemaPropertyInfo(rdfs_comment: 'Link to the page containing an original version of the content, or directly to an online copy of the original <a href="https://schema.org/MediaObject" target="_blank" rel="noopener noreferrer">MediaObject</a> content, e.g. video file.', schema_domain_includes: ['MediaReview'], range_includes: ['MediaObject', 'Role', 'Text', 'URL', 'WebPage'], superseded_by: null)]
    case originalMediaLink = 'originalMediaLink';
    #[SchemaPropertyInfo(rdfs_comment: 'The vasculature the lymphatic structure originates, or afferents, from.', schema_domain_includes: ['LymphaticVessel'], range_includes: ['Role', 'Text', 'URL', 'Vessel'], superseded_by: null)]
    case originatesFrom = 'originatesFrom';
    #[SchemaPropertyInfo(rdfs_comment: 'Any information related to overdose on a drug, including signs or symptoms, treatments, contact information for emergency response.', schema_domain_includes: ['Drug'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case overdosage = 'overdosage';
    #[SchemaPropertyInfo(rdfs_comment: 'The date and time of obtaining the product.', schema_domain_includes: ['OwnershipInfo'], range_includes: ['DateTime'], superseded_by: null)]
    case ownedFrom = 'ownedFrom';
    #[SchemaPropertyInfo(rdfs_comment: 'The date and time of giving up ownership on the product.', schema_domain_includes: ['OwnershipInfo'], range_includes: ['DateTime'], superseded_by: null)]
    case ownedThrough = 'ownedThrough';
    #[SchemaPropertyInfo(rdfs_comment: 'A person or organization who owns this Thing.', schema_domain_includes: ['Thing'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case owner = 'owner';
    #[SchemaPropertyInfo(rdfs_comment: 'For an <a href="https://schema.org/Organization" target="_blank" rel="noopener noreferrer">Organization</a> (often but not necessarily a <a href="https://schema.org/NewsMediaOrganization" target="_blank" rel="noopener noreferrer">NewsMediaOrganization</a>), a description of organizational ownership structure; funding and grants. In a news/media setting, this is with particular reference to editorial independence.   Note that the <a href="https://schema.org/funder" target="_blank" rel="noopener noreferrer">funder</a> is also available and can be used to make basic funder information machine-readable.', schema_domain_includes: ['NewsMediaOrganization', 'Organization'], range_includes: ['AboutPage', 'CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case ownershipFundingInfo = 'ownershipFundingInfo';
    #[SchemaPropertyInfo(rdfs_comment: 'Things owned by the organization or person.', schema_domain_includes: ['Organization', 'Person'], range_includes: ['Role', 'Text', 'Thing', 'URL'], superseded_by: null)]
    case owns = 'owns';
    #[SchemaPropertyInfo(rdfs_comment: 'The page on which the work ends; for example "138" or "xvi".', schema_domain_includes: ['Article', 'Chapter', 'PublicationIssue', 'PublicationVolume'], range_includes: ['Integer', 'Role', 'Text', 'URL'], superseded_by: null)]
    case pageEnd = 'pageEnd';
    #[SchemaPropertyInfo(rdfs_comment: 'The page on which the work starts; for example "135" or "xiii".', schema_domain_includes: ['Article', 'Chapter', 'PublicationIssue', 'PublicationVolume'], range_includes: ['Integer', 'Role', 'Text', 'URL'], superseded_by: null)]
    case pageStart = 'pageStart';
    #[SchemaPropertyInfo(rdfs_comment: 'Any description of pages that is not separated into pageStart and pageEnd; for example, "1-6, 9, 55" or "10-12, 46-49".', schema_domain_includes: ['Article', 'Chapter', 'PublicationIssue', 'PublicationVolume'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case pagination = 'pagination';
    #[SchemaPropertyInfo(rdfs_comment: 'A parent of this person.', schema_domain_includes: ['Person'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case parent = 'parent';
    #[SchemaPropertyInfo(rdfs_comment: 'The parent of a question, answer or item in general. Typically used for Q/A discussion threads e.g. a chain of comments with the first comment being an <a href="https://schema.org/Article" target="_blank" rel="noopener noreferrer">Article</a> or other <a href="https://schema.org/CreativeWork" target="_blank" rel="noopener noreferrer">CreativeWork</a>. See also <a href="https://schema.org/comment" target="_blank" rel="noopener noreferrer">comment</a> which points from something to a comment about it.', schema_domain_includes: ['Answer', 'Comment', 'Question'], range_includes: ['Comment', 'CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case parentItem = 'parentItem';
    #[SchemaPropertyInfo(rdfs_comment: 'The larger organization that this organization is a <a href="https://schema.org/subOrganization" target="_blank" rel="noopener noreferrer">subOrganization</a> of, if any.', schema_domain_includes: ['Organization'], range_includes: ['Organization', 'Role', 'Text', 'URL'], superseded_by: null)]
    case parentOrganization = 'parentOrganization';
    #[SchemaPropertyInfo(rdfs_comment: 'A broadcast service to which the broadcast service may belong to such as regional variations of a national channel.', schema_domain_includes: ['BroadcastService'], range_includes: ['BroadcastService', 'Role', 'Text', 'URL'], superseded_by: null)]
    case parentService = 'parentService';
    #[SchemaPropertyInfo(rdfs_comment: 'Closest parent taxon of the taxon in question.', schema_domain_includes: ['Taxon'], range_includes: ['Role', 'Taxon', 'Text', 'URL'], superseded_by: null)]
    case parentTaxon = 'parentTaxon';
    #[SchemaPropertyInfo(rdfs_comment: 'A parents of the person.', schema_domain_includes: ['Person'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: 'parent')]
    case parents = 'parents';
    #[SchemaPropertyInfo(rdfs_comment: 'The episode to which this clip belongs.', schema_domain_includes: ['Clip'], range_includes: ['Episode', 'Role', 'Text', 'URL'], superseded_by: null)]
    case partOfEpisode = 'partOfEpisode';
    #[SchemaPropertyInfo(rdfs_comment: 'The order is being paid as part of the referenced Invoice.', schema_domain_includes: ['Order'], range_includes: ['Invoice', 'Role', 'Text', 'URL'], superseded_by: null)]
    case partOfInvoice = 'partOfInvoice';
    #[SchemaPropertyInfo(rdfs_comment: 'The overall order the items in this delivery were included in.', schema_domain_includes: ['ParcelDelivery'], range_includes: ['Order', 'Role', 'Text', 'URL'], superseded_by: null)]
    case partOfOrder = 'partOfOrder';
    #[SchemaPropertyInfo(rdfs_comment: 'The season to which this episode belongs.', schema_domain_includes: ['Clip', 'Episode'], range_includes: ['CreativeWorkSeason', 'Role', 'Text', 'URL'], superseded_by: null)]
    case partOfSeason = 'partOfSeason';
    #[SchemaPropertyInfo(rdfs_comment: 'The series to which this episode or season belongs.', schema_domain_includes: ['Clip', 'CreativeWorkSeason', 'Episode'], range_includes: ['CreativeWorkSeries', 'Role', 'Text', 'URL'], superseded_by: null)]
    case partOfSeries = 'partOfSeries';
    #[SchemaPropertyInfo(rdfs_comment: 'The anatomical or organ system that this structure is part of.', schema_domain_includes: ['AnatomicalStructure'], range_includes: ['AnatomicalSystem', 'Role', 'Text', 'URL'], superseded_by: null)]
    case partOfSystem = 'partOfSystem';
    #[SchemaPropertyInfo(rdfs_comment: 'The TV series to which this episode or season belongs.', schema_domain_includes: ['TVClip', 'TVEpisode', 'TVSeason'], range_includes: ['Role', 'TVSeries', 'Text', 'URL'], superseded_by: 'partOfSeries')]
    case partOfTVSeries = 'partOfTVSeries';
    #[SchemaPropertyInfo(rdfs_comment: 'Identifies that this <a href="https://schema.org/Trip" target="_blank" rel="noopener noreferrer">Trip</a> is a subTrip of another Trip.  For example Day 1, Day 2, etc. of a multi-day trip.', schema_domain_includes: ['Trip'], range_includes: ['Role', 'Text', 'Trip', 'URL'], superseded_by: null)]
    case partOfTrip = 'partOfTrip';
    #[SchemaPropertyInfo(rdfs_comment: 'Other co-agents that participated in the action indirectly. E.g. John wrote a book with *Steve*.', schema_domain_includes: ['Action', 'Role'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case participant = 'participant';
    #[SchemaPropertyInfo(rdfs_comment: 'Number of people the reservation should accommodate.', schema_domain_includes: ['FoodEstablishmentReservation', 'TaxiReservation'], range_includes: ['Integer', 'QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case partySize = 'partySize';
    #[SchemaPropertyInfo(rdfs_comment: 'The priority status assigned to a passenger for security or boarding (e.g. FastTrack or Priority).', schema_domain_includes: ['FlightReservation'], range_includes: ['QualitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case passengerPriorityStatus = 'passengerPriorityStatus';
    #[SchemaPropertyInfo(rdfs_comment: 'The passenger\'s sequence number as assigned by the airline.', schema_domain_includes: ['FlightReservation'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case passengerSequenceNumber = 'passengerSequenceNumber';
    #[SchemaPropertyInfo(rdfs_comment: 'Changes in the normal mechanical, physical, and biochemical functions that are associated with this activity or condition.', schema_domain_includes: ['MedicalCondition', 'PhysicalActivity'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case pathophysiology = 'pathophysiology';
    #[SchemaPropertyInfo(rdfs_comment: 'A pattern that something has, for example \'polka dot\', \'striped\', \'Canadian flag\'. Values are typically expressed as text, although links to controlled value schemes are also supported.', schema_domain_includes: ['CreativeWork', 'Product'], range_includes: ['DefinedTerm', 'Role', 'Text', 'URL'], superseded_by: null)]
    case pattern = 'pattern';
    #[SchemaPropertyInfo(rdfs_comment: 'The permitted weight of passengers and cargo, EXCLUDING the weight of the empty vehicle.\\n\\nTypical unit code(s): KGM for kilogram, LBR for pound\\n\\n* Note 1: Many databases specify the permitted TOTAL weight instead, which is the sum of <a href="https://schema.org/weight" target="_blank" rel="noopener noreferrer">weight</a> and <a href="https://schema.org/payload" target="_blank" rel="noopener noreferrer">payload</a>\\n* Note 2: You can indicate additional information in the <a href="https://schema.org/name" target="_blank" rel="noopener noreferrer">name</a> of the <a href="https://schema.org/QuantitativeValue" target="_blank" rel="noopener noreferrer">QuantitativeValue</a> node.\\n* Note 3: You may also link to a <a href="https://schema.org/QualitativeValue" target="_blank" rel="noopener noreferrer">QualitativeValue</a> node that provides additional information using <a href="https://schema.org/valueReference" target="_blank" rel="noopener noreferrer">valueReference</a>.\\n* Note 4: Note that you can use <a href="https://schema.org/minValue" target="_blank" rel="noopener noreferrer">minValue</a> and <a href="https://schema.org/maxValue" target="_blank" rel="noopener noreferrer">maxValue</a> to indicate ranges.', schema_domain_includes: ['Vehicle'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case payload = 'payload';
    #[SchemaPropertyInfo(rdfs_comment: 'Cash, Credit Card, Cryptocurrency, Local Exchange Tradings System, etc.', schema_domain_includes: ['LocalBusiness'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case paymentAccepted = 'paymentAccepted';
    #[SchemaPropertyInfo(rdfs_comment: 'The date that payment is due.', schema_domain_includes: ['Invoice', 'Order'], range_includes: ['DateTime'], superseded_by: 'paymentDueDate')]
    case paymentDue = 'paymentDue';
    #[SchemaPropertyInfo(rdfs_comment: 'The date that payment is due.', schema_domain_includes: ['Invoice', 'Order'], range_includes: ['Date', 'DateTime'], superseded_by: null)]
    case paymentDueDate = 'paymentDueDate';
    #[SchemaPropertyInfo(rdfs_comment: 'The name of the credit card or other method of payment for the order.', schema_domain_includes: ['Invoice', 'Order'], range_includes: ['PaymentMethod', 'Role', 'Text', 'URL'], superseded_by: null)]
    case paymentMethod = 'paymentMethod';
    #[SchemaPropertyInfo(rdfs_comment: 'An identifier for the method of payment used (e.g. the last 4 digits of the credit card).', schema_domain_includes: ['Invoice', 'Order'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case paymentMethodId = 'paymentMethodId';
    #[SchemaPropertyInfo(rdfs_comment: 'The type of a payment method.', schema_domain_includes: ['PaymentMethod'], range_includes: ['PaymentMethodType', 'Role', 'Text', 'URL'], superseded_by: null)]
    case paymentMethodType = 'paymentMethodType';
    #[SchemaPropertyInfo(rdfs_comment: 'The status of payment; whether the invoice has been paid or not.', schema_domain_includes: ['Invoice'], range_includes: ['PaymentStatusType', 'Role', 'Text', 'URL'], superseded_by: null)]
    case paymentStatus = 'paymentStatus';
    #[SchemaPropertyInfo(rdfs_comment: 'The URL for sending a payment.', schema_domain_includes: ['Order'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case paymentUrl = 'paymentUrl';
    #[SchemaPropertyInfo(rdfs_comment: 'The individual who draws the primary narrative artwork.', schema_domain_includes: ['ComicIssue', 'ComicStory', 'VisualArtwork'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case penciler = 'penciler';
    #[SchemaPropertyInfo(rdfs_comment: 'The 10th percentile value.', schema_domain_includes: ['QuantitativeValueDistribution'], range_includes: ['Number'], superseded_by: null)]
    case percentile10 = 'percentile10';
    #[SchemaPropertyInfo(rdfs_comment: 'The 25th percentile value.', schema_domain_includes: ['QuantitativeValueDistribution'], range_includes: ['Number'], superseded_by: null)]
    case percentile25 = 'percentile25';
    #[SchemaPropertyInfo(rdfs_comment: 'The 75th percentile value.', schema_domain_includes: ['QuantitativeValueDistribution'], range_includes: ['Number'], superseded_by: null)]
    case percentile75 = 'percentile75';
    #[SchemaPropertyInfo(rdfs_comment: 'The 90th percentile value.', schema_domain_includes: ['QuantitativeValueDistribution'], range_includes: ['Number'], superseded_by: null)]
    case percentile90 = 'percentile90';
    #[SchemaPropertyInfo(rdfs_comment: 'The length of time it takes to perform instructions or a direction (not including time to prepare the supplies), in <a href="http://en.wikipedia.org/wiki/ISO_8601" target="_blank" rel="noopener noreferrer">ISO 8601 duration format</a>.', schema_domain_includes: ['HowTo', 'HowToDirection'], range_includes: ['Duration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case performTime = 'performTime';
    #[SchemaPropertyInfo(rdfs_comment: 'A performer at the event—for example, a presenter, musician, musical group or actor.', schema_domain_includes: ['Event', 'Role'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case performer = 'performer';
    #[SchemaPropertyInfo(rdfs_comment: 'Event that this person is a performer or participant in.', schema_domain_includes: ['Person'], range_includes: ['Event', 'Role', 'Text', 'URL'], superseded_by: null)]
    case performerIn = 'performerIn';
    #[SchemaPropertyInfo(rdfs_comment: 'The main performer or performers of the event—for example, a presenter, musician, or actor.', schema_domain_includes: ['Event', 'Role'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: 'performer')]
    case performers = 'performers';
    #[SchemaPropertyInfo(rdfs_comment: 'The type of permission granted the person, organization, or audience.', schema_domain_includes: ['DigitalDocumentPermission'], range_includes: ['DigitalDocumentPermissionType', 'Role', 'Text', 'URL'], superseded_by: null)]
    case permissionType = 'permissionType';
    #[SchemaPropertyInfo(rdfs_comment: 'Permission(s) required to run the app (for example, a mobile app may require full internet access or may run only on wifi).', schema_domain_includes: ['SoftwareApplication'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case permissions = 'permissions';
    #[SchemaPropertyInfo(rdfs_comment: 'The target audience for this permit.', schema_domain_includes: ['Permit'], range_includes: ['Audience', 'Role', 'Text', 'URL'], superseded_by: null)]
    case permitAudience = 'permitAudience';
    #[SchemaPropertyInfo(rdfs_comment: 'Indications regarding the permitted usage of the accommodation.', schema_domain_includes: ['Accommodation'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case permittedUsage = 'permittedUsage';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates whether pets are allowed to enter the accommodation or lodging business. More detailed information can be put in a text value.', schema_domain_includes: ['Accommodation', 'ApartmentComplex', 'FloorPlan', 'LodgingBusiness'], range_includes: ['Boolean', 'Role', 'Text', 'URL'], superseded_by: null)]
    case petsAllowed = 'petsAllowed';
    #[SchemaPropertyInfo(rdfs_comment: 'Representation of a text <a href="https://schema.org/textValue" target="_blank" rel="noopener noreferrer">textValue</a> using the specified <a href="https://schema.org/speechToTextMarkup" target="_blank" rel="noopener noreferrer">speechToTextMarkup</a>. For example the city name of Houston in IPA: /ˈhjuːstən/.', schema_domain_includes: ['PronounceableText'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case phoneticText = 'phoneticText';
    #[SchemaPropertyInfo(rdfs_comment: 'A photograph of this place.', schema_domain_includes: ['Place'], range_includes: ['ImageObject', 'Photograph', 'Role', 'Text', 'URL'], superseded_by: null)]
    case photo = 'photo';
    #[SchemaPropertyInfo(rdfs_comment: 'Photographs of this place.', schema_domain_includes: ['Place'], range_includes: ['ImageObject', 'Photograph', 'Role', 'Text', 'URL'], superseded_by: 'photo')]
    case photos = 'photos';
    #[SchemaPropertyInfo(rdfs_comment: 'A description of the types of physical activity associated with the job. Defined terms such as those in O*net may be used, but note that there is no way to specify the level of ability as well as its nature when using a defined term.', schema_domain_includes: ['JobPosting'], range_includes: ['DefinedTerm', 'Role', 'Text', 'URL'], superseded_by: null)]
    case physicalRequirement = 'physicalRequirement';
    #[SchemaPropertyInfo(rdfs_comment: 'Specific physiologic benefits associated to the plan.', schema_domain_includes: ['Diet'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case physiologicalBenefits = 'physiologicalBenefits';
    #[SchemaPropertyInfo(rdfs_comment: 'Where a taxi will pick up a passenger or a rental car can be picked up.', schema_domain_includes: ['RentalCarReservation', 'TaxiReservation'], range_includes: ['Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case pickupLocation = 'pickupLocation';
    #[SchemaPropertyInfo(rdfs_comment: 'When a taxi will pick up a passenger or a rental car can be picked up.', schema_domain_includes: ['RentalCarReservation', 'TaxiReservation'], range_includes: ['DateTime'], superseded_by: null)]
    case pickupTime = 'pickupTime';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates whether this game is multi-player, co-op or single-player.  The game can be marked as multi-player, co-op and single-player at the same time.', schema_domain_includes: ['VideoGame', 'VideoGameSeries'], range_includes: ['GamePlayMode', 'Role', 'Text', 'URL'], superseded_by: null)]
    case playMode = 'playMode';
    #[SchemaPropertyInfo(rdfs_comment: 'Player type required—for example, Flash or Silverlight.', schema_domain_includes: ['MediaObject'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case playerType = 'playerType';
    #[SchemaPropertyInfo(rdfs_comment: 'Number of players on the server.', schema_domain_includes: ['GameServer'], range_includes: ['Integer'], superseded_by: null)]
    case playersOnline = 'playersOnline';
    #[SchemaPropertyInfo(rdfs_comment: 'A polygon is the area enclosed by a point-to-point path for which the starting and ending points are the same. A polygon is expressed as a series of four or more space delimited points where the first and final points are identical.', schema_domain_includes: ['GeoShape'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case polygon = 'polygon';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates the populationType common to all members of a <a href="https://schema.org/StatisticalPopulation" target="_blank" rel="noopener noreferrer">StatisticalPopulation</a> or all cases within the scope of a <a href="https://schema.org/StatisticalVariable" target="_blank" rel="noopener noreferrer">StatisticalVariable</a>.', schema_domain_includes: ['StatisticalPopulation', 'StatisticalVariable'], range_includes: ['Class', 'Role', 'Text', 'URL'], superseded_by: null)]
    case populationType = 'populationType';
    #[SchemaPropertyInfo(rdfs_comment: 'The position of an item in a series or sequence of items.', schema_domain_includes: ['CreativeWork', 'ListItem'], range_includes: ['Integer', 'Role', 'Text', 'URL'], superseded_by: null)]
    case position = 'position';
    #[SchemaPropertyInfo(rdfs_comment: 'Provides positive considerations regarding something, for example product highlights or (alongside <a href="https://schema.org/negativeNotes" target="_blank" rel="noopener noreferrer">negativeNotes</a>) pro/con lists for reviews.

In the case of a <a href="https://schema.org/Review" target="_blank" rel="noopener noreferrer">Review</a>, the property describes the <a href="https://schema.org/itemReviewed" target="_blank" rel="noopener noreferrer">itemReviewed</a> from the perspective of the review; in the case of a <a href="https://schema.org/Product" target="_blank" rel="noopener noreferrer">Product</a>, the product itself is being described.

The property values can be expressed either as unstructured text (repeated as necessary), or if ordered, as a list (in which case the most positive is at the beginning of the list).', schema_domain_includes: ['Product', 'Review'], range_includes: ['ItemList', 'ListItem', 'Role', 'Text', 'URL', 'WebContent'], superseded_by: null)]
    case positiveNotes = 'positiveNotes';
    #[SchemaPropertyInfo(rdfs_comment: 'A possible unexpected and unfavorable evolution of a medical condition. Complications may include worsening of the signs or symptoms of the disease, extension of the condition to other organ systems, etc.', schema_domain_includes: ['MedicalCondition'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case possibleComplication = 'possibleComplication';
    #[SchemaPropertyInfo(rdfs_comment: 'A possible treatment to address this condition, sign or symptom.', schema_domain_includes: ['MedicalCondition', 'MedicalSignOrSymptom'], range_includes: ['Drug', 'DrugClass', 'LifestyleModification', 'MedicalTherapy', 'Role', 'Text', 'URL'], superseded_by: null)]
    case possibleTreatment = 'possibleTreatment';
    #[SchemaPropertyInfo(rdfs_comment: 'The post office box number for PO box addresses.', schema_domain_includes: ['PostalAddress'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case postOfficeBoxNumber = 'postOfficeBoxNumber';
    #[SchemaPropertyInfo(rdfs_comment: 'A description of the postoperative procedures, care, and/or followups for this device.', schema_domain_includes: ['MedicalDevice'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case postOp = 'postOp';
    #[SchemaPropertyInfo(rdfs_comment: 'The postal code. For example, 94043.', schema_domain_includes: ['DefinedRegion', 'GeoCoordinates', 'GeoShape', 'PostalAddress'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case postalCode = 'postalCode';
    #[SchemaPropertyInfo(rdfs_comment: 'First postal code in a range (included).', schema_domain_includes: ['PostalCodeRangeSpecification'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case postalCodeBegin = 'postalCodeBegin';
    #[SchemaPropertyInfo(rdfs_comment: 'Last postal code in the range (included). Needs to be after <a href="https://schema.org/postalCodeBegin" target="_blank" rel="noopener noreferrer">postalCodeBegin</a>.', schema_domain_includes: ['PostalCodeRangeSpecification'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case postalCodeEnd = 'postalCodeEnd';
    #[SchemaPropertyInfo(rdfs_comment: 'A defined range of postal codes indicated by a common textual prefix. Used for non-numeric systems such as UK.', schema_domain_includes: ['DefinedRegion'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case postalCodePrefix = 'postalCodePrefix';
    #[SchemaPropertyInfo(rdfs_comment: 'A defined range of postal codes.', schema_domain_includes: ['DefinedRegion'], range_includes: ['PostalCodeRangeSpecification', 'Role', 'Text', 'URL'], superseded_by: null)]
    case postalCodeRange = 'postalCodeRange';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates a potential Action, which describes an idealized action in which this thing would play an \'object\' role.', schema_domain_includes: ['Thing'], range_includes: ['Action', 'Role', 'Text', 'URL'], superseded_by: null)]
    case potentialAction = 'potentialAction';
    #[SchemaPropertyInfo(rdfs_comment: 'Intended use of the BioChemEntity by humans.', schema_domain_includes: ['ChemicalSubstance', 'MolecularEntity'], range_includes: ['DefinedTerm', 'Role', 'Text', 'URL'], superseded_by: null)]
    case potentialUse = 'potentialUse';
    #[SchemaPropertyInfo(rdfs_comment: 'A <a href="https://schema.org/MedicalOrganization" target="_blank" rel="noopener noreferrer">MedicalOrganization</a> where the <a href="https://schema.org/IndividualPhysician" target="_blank" rel="noopener noreferrer">IndividualPhysician</a> practices.', schema_domain_includes: ['IndividualPhysician'], range_includes: ['MedicalOrganization', 'Role', 'Text', 'URL'], superseded_by: null)]
    case practicesAt = 'practicesAt';
    #[SchemaPropertyInfo(rdfs_comment: 'A description of the workup, testing, and other preparations required before implanting this device.', schema_domain_includes: ['MedicalDevice'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case preOp = 'preOp';
    #[SchemaPropertyInfo(rdfs_comment: 'A pointer from a previous, often discontinued variant of the product to its newer variant.', schema_domain_includes: ['ProductModel'], range_includes: ['ProductModel', 'Role', 'Text', 'URL'], superseded_by: null)]
    case predecessorOf = 'predecessorOf';
    #[SchemaPropertyInfo(rdfs_comment: 'Pregnancy category of this drug.', schema_domain_includes: ['Drug'], range_includes: ['DrugPregnancyCategory', 'Role', 'Text', 'URL'], superseded_by: null)]
    case pregnancyCategory = 'pregnancyCategory';
    #[SchemaPropertyInfo(rdfs_comment: 'Any precaution, guidance, contraindication, etc. related to this drug\'s use during pregnancy.', schema_domain_includes: ['Drug'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case pregnancyWarning = 'pregnancyWarning';
    #[SchemaPropertyInfo(rdfs_comment: 'The length of time it takes to prepare the items to be used in instructions or a direction, in <a href="http://en.wikipedia.org/wiki/ISO_8601" target="_blank" rel="noopener noreferrer">ISO 8601 duration format</a>.', schema_domain_includes: ['HowTo', 'HowToDirection'], range_includes: ['Duration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case prepTime = 'prepTime';
    #[SchemaPropertyInfo(rdfs_comment: 'Typical preparation that a patient must undergo before having the procedure performed.', schema_domain_includes: ['MedicalProcedure'], range_includes: ['MedicalEntity', 'Role', 'Text', 'URL'], superseded_by: null)]
    case preparation = 'preparation';
    #[SchemaPropertyInfo(rdfs_comment: 'Link to prescribing information for the drug.', schema_domain_includes: ['Drug'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case prescribingInfo = 'prescribingInfo';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates the status of drug prescription, e.g. local catalogs classifications or whether the drug is available by prescription or over-the-counter, etc.', schema_domain_includes: ['Drug'], range_includes: ['DrugPrescriptionStatus', 'Role', 'Text', 'URL'], superseded_by: null)]
    case prescriptionStatus = 'prescriptionStatus';
    #[SchemaPropertyInfo(rdfs_comment: 'A link to the ListItem that precedes the current one.', schema_domain_includes: ['ListItem'], range_includes: ['ListItem', 'Role', 'Text', 'URL'], superseded_by: null)]
    case previousItem = 'previousItem';
    #[SchemaPropertyInfo(rdfs_comment: 'Used in conjunction with eventStatus for rescheduled or cancelled events. This property contains the previously scheduled start date. For rescheduled events, the startDate property should be used for the newly scheduled start date. In the (rare) case of an event that has been postponed and rescheduled multiple times, this field may be repeated.', schema_domain_includes: ['Event'], range_includes: ['Date', 'DateTime'], superseded_by: null)]
    case previousStartDate = 'previousStartDate';
    #[SchemaPropertyInfo(rdfs_comment: 'The offer price of a product, or of a price component when attached to PriceSpecification and its subtypes.\\n\\nUsage guidelines:\\n\\n* Use the <a href="https://schema.org/priceCurrency" target="_blank" rel="noopener noreferrer">priceCurrency</a> property (with standard formats: <a href="http://en.wikipedia.org/wiki/ISO_4217" target="_blank" rel="noopener noreferrer">ISO 4217 currency format</a>, e.g. "USD"; <a href="https://en.wikipedia.org/wiki/List_of_cryptocurrencies" target="_blank" rel="noopener noreferrer">Ticker symbol</a> for cryptocurrencies, e.g. "BTC"; well known names for <a href="https://en.wikipedia.org/wiki/Local_exchange_trading_system" target="_blank" rel="noopener noreferrer">Local Exchange Trading Systems</a> (LETS) and other currency types, e.g. "Ithaca HOUR") instead of including <a href="http://en.wikipedia.org/wiki/Dollar_sign#Currencies_that_use_the_dollar_or_peso_sign" target="_blank" rel="noopener noreferrer">ambiguous symbols</a> such as \'$\' in the value.\\n* Use \'.\' (Unicode \'FULL STOP\' (U+002E)) rather than \',\' to indicate a decimal point. Avoid using these symbols as a readability separator.\\n* Note that both <a href="http://www.w3.org/TR/xhtml-rdfa-primer/#using-the-content-attribute" target="_blank" rel="noopener noreferrer">RDFa</a> and Microdata syntax allow the use of a "content=" attribute for publishing simple machine-readable values alongside more human-friendly formatting.\\n* Use values from 0123456789 (Unicode \'DIGIT ZERO\' (U+0030) to \'DIGIT NINE\' (U+0039)) rather than superficially similar Unicode symbols.', schema_domain_includes: ['DonateAction', 'Offer', 'PriceSpecification', 'TradeAction'], range_includes: ['Number', 'Role', 'Text', 'URL'], superseded_by: null)]
    case price = 'price';
    #[SchemaPropertyInfo(rdfs_comment: 'This property links to all <a href="https://schema.org/UnitPriceSpecification" target="_blank" rel="noopener noreferrer">UnitPriceSpecification</a> nodes that apply in parallel for the <a href="https://schema.org/CompoundPriceSpecification" target="_blank" rel="noopener noreferrer">CompoundPriceSpecification</a> node.', schema_domain_includes: ['CompoundPriceSpecification'], range_includes: ['PriceSpecification', 'Role', 'Text', 'URL'], superseded_by: null)]
    case priceComponent = 'priceComponent';
    #[SchemaPropertyInfo(rdfs_comment: 'Identifies a price component (for example, a line item on an invoice), part of the total price for an offer.', schema_domain_includes: ['UnitPriceSpecification'], range_includes: ['PriceComponentTypeEnumeration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case priceComponentType = 'priceComponentType';
    #[SchemaPropertyInfo(rdfs_comment: 'The currency of the price, or a price component when attached to <a href="https://schema.org/PriceSpecification" target="_blank" rel="noopener noreferrer">PriceSpecification</a> and its subtypes.\\n\\nUse standard formats: <a href="http://en.wikipedia.org/wiki/ISO_4217" target="_blank" rel="noopener noreferrer">ISO 4217 currency format</a>, e.g. "USD"; <a href="https://en.wikipedia.org/wiki/List_of_cryptocurrencies" target="_blank" rel="noopener noreferrer">Ticker symbol</a> for cryptocurrencies, e.g. "BTC"; well known names for <a href="https://en.wikipedia.org/wiki/Local_exchange_trading_system" target="_blank" rel="noopener noreferrer">Local Exchange Trading Systems</a> (LETS) and other currency types, e.g. "Ithaca HOUR".', schema_domain_includes: ['DonateAction', 'Offer', 'PriceSpecification', 'Reservation', 'Ticket', 'TradeAction'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case priceCurrency = 'priceCurrency';
    #[SchemaPropertyInfo(rdfs_comment: 'The price range of the business, for example ```$$$```.', schema_domain_includes: ['LocalBusiness'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case priceRange = 'priceRange';
    #[SchemaPropertyInfo(rdfs_comment: 'One or more detailed price specifications, indicating the unit price and delivery or payment charges.', schema_domain_includes: ['Demand', 'DonateAction', 'Offer', 'TradeAction'], range_includes: ['PriceSpecification', 'Role', 'Text', 'URL'], superseded_by: null)]
    case priceSpecification = 'priceSpecification';
    #[SchemaPropertyInfo(rdfs_comment: 'Defines the type of a price specified for an offered product, for example a list price, a (temporary) sale price or a manufacturer suggested retail price. If multiple prices are specified for an offer the <a href="https://schema.org/priceType" target="_blank" rel="noopener noreferrer">priceType</a> property can be used to identify the type of each such specified price. The value of priceType can be specified as a value from enumeration PriceTypeEnumeration or, a UN/EDIFACT 5387 code, or as a free form text string for price types that are not already predefined in PriceTypeEnumeration.', schema_domain_includes: ['CompoundPriceSpecification', 'UnitPriceSpecification'], range_includes: ['PriceTypeEnumeration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case priceType = 'priceType';
    #[SchemaPropertyInfo(rdfs_comment: 'The date after which the price is no longer available.', schema_domain_includes: ['Offer'], range_includes: ['Date'], superseded_by: null)]
    case priceValidUntil = 'priceValidUntil';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates the main image on the page.', schema_domain_includes: ['WebPage'], range_includes: ['ImageObject', 'Role', 'Text', 'URL'], superseded_by: null)]
    case primaryImageOfPage = 'primaryImageOfPage';
    #[SchemaPropertyInfo(rdfs_comment: 'A preventative therapy used to prevent an initial occurrence of the medical condition, such as vaccination.', schema_domain_includes: ['MedicalCondition'], range_includes: ['MedicalTherapy', 'Role', 'Text', 'URL'], superseded_by: null)]
    case primaryPrevention = 'primaryPrevention';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of the column in which the NewsArticle appears in the print edition.', schema_domain_includes: ['NewsArticle'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case printColumn = 'printColumn';
    #[SchemaPropertyInfo(rdfs_comment: 'The edition of the print product in which the NewsArticle appears.', schema_domain_includes: ['NewsArticle'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case printEdition = 'printEdition';
    #[SchemaPropertyInfo(rdfs_comment: 'If this NewsArticle appears in print, this field indicates the name of the page on which the article is found. Please note that this field is intended for the exact page name (e.g. A5, B18).', schema_domain_includes: ['NewsArticle'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case printPage = 'printPage';
    #[SchemaPropertyInfo(rdfs_comment: 'If this NewsArticle appears in print, this field indicates the print section in which the article appeared.', schema_domain_includes: ['NewsArticle'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case printSection = 'printSection';
    #[SchemaPropertyInfo(rdfs_comment: 'A description of the procedure involved in setting up, using, and/or installing the device.', schema_domain_includes: ['MedicalDevice'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case procedure = 'procedure';
    #[SchemaPropertyInfo(rdfs_comment: 'The type of procedure, for example Surgical, Noninvasive, or Percutaneous.', schema_domain_includes: ['MedicalProcedure'], range_includes: ['MedicalProcedureType', 'Role', 'Text', 'URL'], superseded_by: null)]
    case procedureType = 'procedureType';
    #[SchemaPropertyInfo(rdfs_comment: 'Estimated processing time for the service using this channel.', schema_domain_includes: ['ServiceChannel'], range_includes: ['Duration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case processingTime = 'processingTime';
    #[SchemaPropertyInfo(rdfs_comment: 'Processor architecture required to run the application (e.g. IA64).', schema_domain_includes: ['SoftwareApplication'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case processorRequirements = 'processorRequirements';
    #[SchemaPropertyInfo(rdfs_comment: 'The person or organization who produced the work (e.g. music album, movie, TV/radio series etc.).', schema_domain_includes: ['CreativeWork', 'Role'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case producer = 'producer';
    #[SchemaPropertyInfo(rdfs_comment: 'The tangible thing generated by the service, e.g. a passport, permit, etc.', schema_domain_includes: ['Service'], range_includes: ['Role', 'Text', 'Thing', 'URL'], superseded_by: 'serviceOutput')]
    case produces = 'produces';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates a textual identifier for a ProductGroup.', schema_domain_includes: ['ProductGroup'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case productGroupID = 'productGroupID';
    #[SchemaPropertyInfo(rdfs_comment: 'The product identifier, such as ISBN. For example: ``` meta itemprop="productID" content="isbn:123-456-789" ```.', schema_domain_includes: ['Product'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case productID = 'productID';
    #[SchemaPropertyInfo(rdfs_comment: 'The product or service this support contact point is related to (such as product support for a particular product line). This can be a specific product or product line (e.g. "iPhone") or a general category of products or services (e.g. "smartphones").', schema_domain_includes: ['ContactPoint'], range_includes: ['Product', 'Role', 'Text', 'URL'], superseded_by: null)]
    case productSupported = 'productSupported';
    #[SchemaPropertyInfo(rdfs_comment: 'The production company or studio responsible for the item, e.g. series, video game, episode etc.', schema_domain_includes: ['CreativeWorkSeason', 'Episode', 'MediaObject', 'Movie', 'MovieSeries', 'RadioSeries', 'TVSeries', 'VideoGameSeries'], range_includes: ['Organization', 'Role', 'Text', 'URL'], superseded_by: null)]
    case productionCompany = 'productionCompany';
    #[SchemaPropertyInfo(rdfs_comment: 'The date of production of the item, e.g. vehicle.', schema_domain_includes: ['Product', 'Vehicle'], range_includes: ['Date'], superseded_by: null)]
    case productionDate = 'productionDate';
    #[SchemaPropertyInfo(rdfs_comment: 'Proficiency needed for this content; expected values: \'Beginner\', \'Expert\'.', schema_domain_includes: ['TechArticle'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case proficiencyLevel = 'proficiencyLevel';
    #[SchemaPropertyInfo(rdfs_comment: 'The <a href="https://schema.org/MemberProgram" target="_blank" rel="noopener noreferrer">MemberProgram</a> associated with a <a href="https://schema.org/ProgramMembership" target="_blank" rel="noopener noreferrer">ProgramMembership</a>.', schema_domain_includes: ['ProgramMembership'], range_includes: ['MemberProgram', 'Role', 'Text', 'URL'], superseded_by: null)]
    case program = 'program';
    #[SchemaPropertyInfo(rdfs_comment: 'Any membership in a frequent flyer, hotel loyalty program, etc. being applied to the reservation.', schema_domain_includes: ['Reservation'], range_includes: ['ProgramMembership', 'Role', 'Text', 'URL'], superseded_by: null)]
    case programMembershipUsed = 'programMembershipUsed';
    #[SchemaPropertyInfo(rdfs_comment: 'The program providing the membership. It is preferable to use <a href="https://schema.org/program" target="_blank" rel="noopener noreferrer">:program</a> instead.', schema_domain_includes: ['ProgramMembership'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case programName = 'programName';
    #[SchemaPropertyInfo(rdfs_comment: 'Prerequisites for enrolling in the program.', schema_domain_includes: ['EducationalOccupationalProgram'], range_includes: ['AlignmentObject', 'Course', 'EducationalOccupationalCredential', 'Role', 'Text', 'URL'], superseded_by: null)]
    case programPrerequisites = 'programPrerequisites';
    #[SchemaPropertyInfo(rdfs_comment: 'The type of educational or occupational program. For example, classroom, internship, alternance, etc.', schema_domain_includes: ['EducationalOccupationalProgram'], range_includes: ['DefinedTerm', 'Role', 'Text', 'URL'], superseded_by: null)]
    case programType = 'programType';
    #[SchemaPropertyInfo(rdfs_comment: 'The computer programming language.', schema_domain_includes: ['SoftwareSourceCode'], range_includes: ['ComputerLanguage', 'Role', 'Text', 'URL'], superseded_by: null)]
    case programmingLanguage = 'programmingLanguage';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates whether API is managed or unmanaged.', schema_domain_includes: ['APIReference'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case programmingModel = 'programmingModel';
    #[SchemaPropertyInfo(rdfs_comment: 'A short string listing or describing pronouns for a person. Typically the person concerned is the best authority as pronouns are a critical part of personal identity and expression. Publishers and consumers of this information are reminded to treat this data responsibly, take country-specific laws related to gender expression into account, and be wary of out-of-date data and drawing unwarranted inferences about the person being described.

In English, formulations such as "they/them", "she/her", and "he/him" are commonly used online and can also be used here. We do not intend to enumerate all possible micro-syntaxes in all languages. More structured and well-defined external values for pronouns can be referenced using the <a href="https://schema.org/StructuredValue" target="_blank" rel="noopener noreferrer">StructuredValue</a> or <a href="https://schema.org/DefinedTerm" target="_blank" rel="noopener noreferrer">DefinedTerm</a> values.', schema_domain_includes: ['Person'], range_includes: ['DefinedTerm', 'Role', 'StructuredValue', 'Text', 'URL'], superseded_by: null)]
    case pronouns = 'pronouns';
    #[SchemaPropertyInfo(rdfs_comment: 'A commonly used identifier for the characteristic represented by the property, e.g. a manufacturer or a standard code for a property. propertyID can be
(1) a prefixed string, mainly meant to be used with standards for product properties; (2) a site-specific, non-prefixed string (e.g. the primary key of the property or the vendor-specific ID of the property), or (3)
a URL indicating the type of the property, either pointing to an external vocabulary, or a Web resource that describes the property (e.g. a glossary entry).
Standards bodies should promote a standard prefix for the identifiers of properties from their standards.', schema_domain_includes: ['PropertyValue'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case propertyID = 'propertyID';
    #[SchemaPropertyInfo(rdfs_comment: 'Proprietary name given to the diet plan, typically by its originator or creator.', schema_domain_includes: ['DietarySupplement', 'Drug'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case proprietaryName = 'proprietaryName';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of grams of protein.', schema_domain_includes: ['NutritionInformation'], range_includes: ['Mass', 'Role', 'Text', 'URL'], superseded_by: null)]
    case proteinContent = 'proteinContent';
    #[SchemaPropertyInfo(rdfs_comment: 'The service provider, service operator, or service performer; the goods producer. Another party (a seller) may offer those services or goods on behalf of the provider. A provider may also serve as the seller.', schema_domain_includes: ['Action', 'CreativeWork', 'EducationalOccupationalProgram', 'FinancialIncentive', 'Invoice', 'ParcelDelivery', 'Reservation', 'Service', 'Trip'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case provider = 'provider';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates the mobility of a provided service (e.g. \'static\', \'dynamic\').', schema_domain_includes: ['Service'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case providerMobility = 'providerMobility';
    #[SchemaPropertyInfo(rdfs_comment: 'The BroadcastService offered on this channel.', schema_domain_includes: ['BroadcastChannel'], range_includes: ['BroadcastService', 'Role', 'Text', 'URL'], superseded_by: null)]
    case providesBroadcastService = 'providesBroadcastService';
    #[SchemaPropertyInfo(rdfs_comment: 'The service provided by this channel.', schema_domain_includes: ['ServiceChannel'], range_includes: ['Role', 'Service', 'Text', 'URL'], superseded_by: null)]
    case providesService = 'providesService';
    #[SchemaPropertyInfo(rdfs_comment: 'A flag to signal that the <a href="https://schema.org/Place" target="_blank" rel="noopener noreferrer">Place</a> is open to public visitors.  If this property is omitted there is no assumed default boolean value.', schema_domain_includes: ['Place'], range_includes: ['Boolean'], superseded_by: null)]
    case publicAccess = 'publicAccess';
    #[SchemaPropertyInfo(rdfs_comment: 'Information about public transport closures.', schema_domain_includes: ['SpecialAnnouncement'], range_includes: ['Role', 'Text', 'URL', 'WebContent'], superseded_by: null)]
    case publicTransportClosuresInfo = 'publicTransportClosuresInfo';
    #[SchemaPropertyInfo(rdfs_comment: 'A publication event associated with the item.', schema_domain_includes: ['CreativeWork'], range_includes: ['PublicationEvent', 'Role', 'Text', 'URL'], superseded_by: null)]
    case publication = 'publication';
    #[SchemaPropertyInfo(rdfs_comment: 'The type of the medical article, taken from the US NLM MeSH publication type catalog. See also <a href="http://www.nlm.nih.gov/mesh/pubtypes.html" target="_blank" rel="noopener noreferrer">MeSH documentation</a>.', schema_domain_includes: ['MedicalScholarlyArticle'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case publicationType = 'publicationType';
    #[SchemaPropertyInfo(rdfs_comment: 'An agent associated with the publication event.', schema_domain_includes: ['PublicationEvent'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case publishedBy = 'publishedBy';
    #[SchemaPropertyInfo(rdfs_comment: 'A broadcast service associated with the publication event.', schema_domain_includes: ['PublicationEvent'], range_includes: ['BroadcastService', 'Role', 'Text', 'URL'], superseded_by: null)]
    case publishedOn = 'publishedOn';
    #[SchemaPropertyInfo(rdfs_comment: 'The publisher of the article in question.', schema_domain_includes: ['CreativeWork', 'FinancialIncentive', 'Role'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case publisher = 'publisher';
    #[SchemaPropertyInfo(rdfs_comment: 'The publishing division which published the comic.', schema_domain_includes: ['CreativeWork'], range_includes: ['Organization', 'Role', 'Text', 'URL'], superseded_by: null)]
    case publisherImprint = 'publisherImprint';
    #[SchemaPropertyInfo(rdfs_comment: 'The publishingPrinciples property indicates (typically via <a href="https://schema.org/URL" target="_blank" rel="noopener noreferrer">URL</a>) a document describing the editorial principles of an <a href="https://schema.org/Organization" target="_blank" rel="noopener noreferrer">Organization</a> (or individual, e.g. a <a href="https://schema.org/Person" target="_blank" rel="noopener noreferrer">Person</a> writing a blog) that relate to their activities as a publisher, e.g. ethics or diversity policies. When applied to a <a href="https://schema.org/CreativeWork" target="_blank" rel="noopener noreferrer">CreativeWork</a> (e.g. <a href="https://schema.org/NewsArticle" target="_blank" rel="noopener noreferrer">NewsArticle</a>) the principles are those of the party primarily responsible for the creation of the <a href="https://schema.org/CreativeWork" target="_blank" rel="noopener noreferrer">CreativeWork</a>.

While such policies are most typically expressed in natural language, sometimes related information (e.g. indicating a <a href="https://schema.org/funder" target="_blank" rel="noopener noreferrer">funder</a>) can be expressed using schema.org terminology.', schema_domain_includes: ['CreativeWork', 'Organization', 'Person'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case publishingPrinciples = 'publishingPrinciples';
    #[SchemaPropertyInfo(rdfs_comment: 'The date the item, e.g. vehicle, was purchased by the current owner.', schema_domain_includes: ['Product', 'Vehicle'], range_includes: ['Date'], superseded_by: null)]
    case purchaseDate = 'purchaseDate';
    #[SchemaPropertyInfo(rdfs_comment: 'Optional. The maximum price the item can have and still qualify for this offer.', schema_domain_includes: ['FinancialIncentive'], range_includes: ['MonetaryAmount', 'Role', 'Text', 'URL'], superseded_by: null)]
    case purchasePriceLimit = 'purchasePriceLimit';
    #[SchemaPropertyInfo(rdfs_comment: 'Optional. The type of purchase the consumer must make in order to qualify for this incentive.', schema_domain_includes: ['FinancialIncentive'], range_includes: ['PurchaseType', 'Role', 'Text', 'URL'], superseded_by: null)]
    case purchaseType = 'purchaseType';
    #[SchemaPropertyInfo(rdfs_comment: 'Specific qualifications required for this role or Occupation.', schema_domain_includes: ['JobPosting', 'Occupation'], range_includes: ['Credential', 'Role', 'Text', 'URL'], superseded_by: null)]
    case qualifications = 'qualifications';
    #[SchemaPropertyInfo(rdfs_comment: 'Optional. The types of expenses that are covered by the incentive. For example some incentives are only for the goods (tangible items) but the services (labor) are excluded.', schema_domain_includes: ['FinancialIncentive'], range_includes: ['IncentiveQualifiedExpenseType', 'Role', 'Text', 'URL'], superseded_by: null)]
    case qualifiedExpense = 'qualifiedExpense';
    #[SchemaPropertyInfo(rdfs_comment: 'Guidelines about quarantine rules, e.g. in the context of a pandemic.', schema_domain_includes: ['SpecialAnnouncement'], range_includes: ['Role', 'Text', 'URL', 'WebContent'], superseded_by: null)]
    case quarantineGuidelines = 'quarantineGuidelines';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of instrument. The query used on this action.', schema_domain_includes: ['SearchAction'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case query = 'query';
    #[SchemaPropertyInfo(rdfs_comment: 'The task that a player-controlled character, or group of characters may complete in order to gain a reward.', schema_domain_includes: ['Game', 'VideoGameSeries'], range_includes: ['Role', 'Text', 'Thing', 'URL'], superseded_by: null)]
    case quest = 'quest';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of object. A question.', schema_domain_includes: ['AskAction'], range_includes: ['Question', 'Role', 'Text', 'URL'], superseded_by: null)]
    case question = 'question';
    #[SchemaPropertyInfo(rdfs_comment: 'Relates a property to a class that constitutes (one of) the expected type(s) for values of the property.', schema_domain_includes: ['Property'], range_includes: ['Class', 'Role', 'Text', 'URL'], superseded_by: null)]
    case rangeIncludes = 'rangeIncludes';
    #[SchemaPropertyInfo(rdfs_comment: 'The count of total number of ratings.', schema_domain_includes: ['AggregateRating'], range_includes: ['Integer'], superseded_by: null)]
    case ratingCount = 'ratingCount';
    #[SchemaPropertyInfo(rdfs_comment: 'A short explanation (e.g. one to two sentences) providing background context and other information that led to the conclusion expressed in the rating. This is particularly applicable to ratings associated with "fact check" markup using <a href="https://schema.org/ClaimReview" target="_blank" rel="noopener noreferrer">ClaimReview</a>.', schema_domain_includes: ['Rating'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case ratingExplanation = 'ratingExplanation';
    #[SchemaPropertyInfo(rdfs_comment: 'The rating for the content.\\n\\nUsage guidelines:\\n\\n* Use values from 0123456789 (Unicode \'DIGIT ZERO\' (U+0030) to \'DIGIT NINE\' (U+0039)) rather than superficially similar Unicode symbols.\\n* Use \'.\' (Unicode \'FULL STOP\' (U+002E)) rather than \',\' to indicate a decimal point. Avoid using these symbols as a readability separator.', schema_domain_includes: ['Rating'], range_includes: ['Number', 'Role', 'Text', 'URL'], superseded_by: null)]
    case ratingValue = 'ratingValue';
    #[SchemaPropertyInfo(rdfs_comment: 'A person who reads (performs) the audiobook.', schema_domain_includes: ['Audiobook'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case readBy = 'readBy';
    #[SchemaPropertyInfo(rdfs_comment: 'Whether or not a property is mutable.  Default is false. Specifying this for a property that also has a value makes it act similar to a "hidden" input in an HTML form.', schema_domain_includes: ['PropertyValueSpecification'], range_includes: ['Boolean'], superseded_by: null)]
    case readonlyValue = 'readonlyValue';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of participant. The real estate agent involved in the action.', schema_domain_includes: ['RentAction'], range_includes: ['RealEstateAgent', 'Role', 'Text', 'URL'], superseded_by: null)]
    case realEstateAgent = 'realEstateAgent';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of instrument. The recipe/instructions used to perform the action.', schema_domain_includes: ['CookAction'], range_includes: ['Recipe', 'Role', 'Text', 'URL'], superseded_by: null)]
    case recipe = 'recipe';
    #[SchemaPropertyInfo(rdfs_comment: 'The category of the recipe—for example, appetizer, entree, etc.', schema_domain_includes: ['Recipe'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case recipeCategory = 'recipeCategory';
    #[SchemaPropertyInfo(rdfs_comment: 'The cuisine of the recipe (for example, French or Ethiopian).', schema_domain_includes: ['Recipe'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case recipeCuisine = 'recipeCuisine';
    #[SchemaPropertyInfo(rdfs_comment: 'An ingredient or ordered list of ingredients and potentially quantities used in the recipe, e.g. 1 cup of sugar, flour or garlic.  The ingredients can be represented as free text or more structured values.', schema_domain_includes: ['Recipe'], range_includes: ['ItemList', 'PropertyValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case recipeIngredient = 'recipeIngredient';
    #[SchemaPropertyInfo(rdfs_comment: 'A step in making the recipe, in the form of a single item (document, video, etc.) or an ordered list with HowToStep and/or HowToSection items.', schema_domain_includes: ['Recipe'], range_includes: ['CreativeWork', 'ItemList', 'Role', 'Text', 'URL'], superseded_by: null)]
    case recipeInstructions = 'recipeInstructions';
    #[SchemaPropertyInfo(rdfs_comment: 'The quantity produced by the recipe (for example, number of people served, number of servings, etc).', schema_domain_includes: ['Recipe'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case recipeYield = 'recipeYield';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of participant. The participant who is at the receiving end of the action.', schema_domain_includes: ['AuthorizeAction', 'CommunicateAction', 'DonateAction', 'GiveAction', 'Message', 'PayAction', 'ReturnAction', 'SendAction', 'TipAction'], range_includes: ['Audience', 'ContactPoint', 'Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case recipient = 'recipient';
    #[SchemaPropertyInfo(rdfs_comment: 'An organization that acknowledges the validity, value or utility of a credential. Note: recognition may include a process of quality assurance or accreditation.', schema_domain_includes: ['Credential'], range_includes: ['Organization', 'Role', 'Text', 'URL'], superseded_by: null)]
    case recognizedBy = 'recognizedBy';
    #[SchemaPropertyInfo(rdfs_comment: 'If applicable, the organization that officially recognizes this entity as part of its endorsed system of medicine.', schema_domain_includes: ['MedicalEntity'], range_includes: ['Organization', 'Role', 'Text', 'URL'], superseded_by: null)]
    case recognizingAuthority = 'recognizingAuthority';
    #[SchemaPropertyInfo(rdfs_comment: 'Strength of the guideline\'s recommendation (e.g. \'class I\').', schema_domain_includes: ['MedicalGuidelineRecommendation'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case recommendationStrength = 'recommendationStrength';
    #[SchemaPropertyInfo(rdfs_comment: 'Recommended intake of this supplement for a given population as defined by a specific recommending authority.', schema_domain_includes: ['DietarySupplement'], range_includes: ['RecommendedDoseSchedule', 'Role', 'Text', 'URL'], superseded_by: null)]
    case recommendedIntake = 'recommendedIntake';
    #[SchemaPropertyInfo(rdfs_comment: 'The label that issued the release.', schema_domain_includes: ['MusicRelease'], range_includes: ['Organization', 'Role', 'Text', 'URL'], superseded_by: null)]
    case recordLabel = 'recordLabel';
    #[SchemaPropertyInfo(rdfs_comment: 'An audio recording of the work.', schema_domain_includes: ['MusicComposition'], range_includes: ['MusicRecording', 'Role', 'Text', 'URL'], superseded_by: null)]
    case recordedAs = 'recordedAs';
    #[SchemaPropertyInfo(rdfs_comment: 'The Event where the CreativeWork was recorded. The CreativeWork may capture all or part of the event.', schema_domain_includes: ['CreativeWork'], range_includes: ['Event', 'Role', 'Text', 'URL'], superseded_by: null)]
    case recordedAt = 'recordedAt';
    #[SchemaPropertyInfo(rdfs_comment: 'The CreativeWork that captured all or part of this Event.', schema_domain_includes: ['Event'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case recordedIn = 'recordedIn';
    #[SchemaPropertyInfo(rdfs_comment: 'The composition this track is a recording of.', schema_domain_includes: ['MusicRecording'], range_includes: ['MusicComposition', 'Role', 'Text', 'URL'], superseded_by: null)]
    case recordingOf = 'recordingOf';
    #[SchemaPropertyInfo(rdfs_comment: 'The only way you get the money back in the event of default is the security. Recourse is where you still have the opportunity to go back to the borrower for the rest of the money.', schema_domain_includes: ['LoanOrCredit'], range_includes: ['Boolean'], superseded_by: null)]
    case recourseLoan = 'recourseLoan';
    #[SchemaPropertyInfo(rdfs_comment: 'An official who watches a game or match closely to enforce the rules and arbitrate on matters arising from the play such as referees, umpires or judges. The name of the effective function can vary according to the sport.', schema_domain_includes: ['SportsEvent'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case referee = 'referee';
    #[SchemaPropertyInfo(rdfs_comment: 'The reference quantity for which a certain price applies, e.g. 1 EUR per 4 kWh of electricity. This property is a replacement for unitOfMeasurement for the advanced cases where the price does not relate to a standard unit.', schema_domain_includes: ['UnitPriceSpecification'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case referenceQuantity = 'referenceQuantity';
    #[SchemaPropertyInfo(rdfs_comment: 'The Order(s) related to this Invoice. One or more Orders may be combined into a single Invoice.', schema_domain_includes: ['Invoice'], range_includes: ['Order', 'Role', 'Text', 'URL'], superseded_by: null)]
    case referencesOrder = 'referencesOrder';
    #[SchemaPropertyInfo(rdfs_comment: 'A refund type, from an enumerated list.', schema_domain_includes: ['MerchantReturnPolicy', 'MerchantReturnPolicySeasonalOverride'], range_includes: ['RefundTypeEnumeration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case refundType = 'refundType';
    #[SchemaPropertyInfo(rdfs_comment: 'The anatomical or organ system drained by this vessel; generally refers to a specific part of an organ.', schema_domain_includes: ['LymphaticVessel', 'Vein'], range_includes: ['AnatomicalStructure', 'AnatomicalSystem', 'Role', 'Text', 'URL'], superseded_by: null)]
    case regionDrained = 'regionDrained';
    #[SchemaPropertyInfo(rdfs_comment: 'The regions where the media is allowed. If not specified, then it\'s assumed to be allowed everywhere. Specify the countries in <a href="http://en.wikipedia.org/wiki/ISO_3166" target="_blank" rel="noopener noreferrer">ISO 3166 format</a>.', schema_domain_includes: ['MediaObject'], range_includes: ['Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case regionsAllowed = 'regionsAllowed';
    #[SchemaPropertyInfo(rdfs_comment: 'Anatomical systems or structures that relate to the superficial anatomy.', schema_domain_includes: ['SuperficialAnatomy'], range_includes: ['AnatomicalStructure', 'AnatomicalSystem', 'Role', 'Text', 'URL'], superseded_by: null)]
    case relatedAnatomy = 'relatedAnatomy';
    #[SchemaPropertyInfo(rdfs_comment: 'A medical condition associated with this anatomy.', schema_domain_includes: ['AnatomicalStructure', 'AnatomicalSystem', 'SuperficialAnatomy'], range_includes: ['MedicalCondition', 'Role', 'Text', 'URL'], superseded_by: null)]
    case relatedCondition = 'relatedCondition';
    #[SchemaPropertyInfo(rdfs_comment: 'Any other drug related to this one, for example commonly-prescribed alternatives.', schema_domain_includes: ['Drug'], range_includes: ['Drug', 'Role', 'Text', 'URL'], superseded_by: null)]
    case relatedDrug = 'relatedDrug';
    #[SchemaPropertyInfo(rdfs_comment: 'A link related to this web page, for example to other related web pages.', schema_domain_includes: ['WebPage'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case relatedLink = 'relatedLink';
    #[SchemaPropertyInfo(rdfs_comment: 'Related anatomical structure(s) that are not part of the system but relate or connect to it, such as vascular bundles associated with an organ system.', schema_domain_includes: ['AnatomicalSystem'], range_includes: ['AnatomicalStructure', 'Role', 'Text', 'URL'], superseded_by: null)]
    case relatedStructure = 'relatedStructure';
    #[SchemaPropertyInfo(rdfs_comment: 'A medical therapy related to this anatomy.', schema_domain_includes: ['AnatomicalStructure', 'AnatomicalSystem', 'SuperficialAnatomy'], range_includes: ['MedicalTherapy', 'Role', 'Text', 'URL'], superseded_by: null)]
    case relatedTherapy = 'relatedTherapy';
    #[SchemaPropertyInfo(rdfs_comment: 'The most generic familial relation.', schema_domain_includes: ['Person'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case relatedTo = 'relatedTo';
    #[SchemaPropertyInfo(rdfs_comment: 'The release date of a product or product model. This can be used to distinguish the exact variant of a product.', schema_domain_includes: ['Product'], range_includes: ['Date'], superseded_by: null)]
    case releaseDate = 'releaseDate';
    #[SchemaPropertyInfo(rdfs_comment: 'Description of what changed in this version.', schema_domain_includes: ['SoftwareApplication'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case releaseNotes = 'releaseNotes';
    #[SchemaPropertyInfo(rdfs_comment: 'The album this is a release of.', schema_domain_includes: ['MusicRelease'], range_includes: ['MusicAlbum', 'Role', 'Text', 'URL'], superseded_by: null)]
    case releaseOf = 'releaseOf';
    #[SchemaPropertyInfo(rdfs_comment: 'The place and time the release was issued, expressed as a PublicationEvent.', schema_domain_includes: ['CreativeWork'], range_includes: ['PublicationEvent', 'Role', 'Text', 'URL'], superseded_by: null)]
    case releasedEvent = 'releasedEvent';
    #[SchemaPropertyInfo(rdfs_comment: 'The Occupation for the JobPosting.', schema_domain_includes: ['JobPosting'], range_includes: ['Occupation', 'Role', 'Text', 'URL'], superseded_by: null)]
    case relevantOccupation = 'relevantOccupation';
    #[SchemaPropertyInfo(rdfs_comment: 'If applicable, a medical specialty in which this entity is relevant.', schema_domain_includes: ['MedicalEntity'], range_includes: ['MedicalSpecialty', 'Role', 'Text', 'URL'], superseded_by: null)]
    case relevantSpecialty = 'relevantSpecialty';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of attendee places for an event that remain unallocated.', schema_domain_includes: ['Event'], range_includes: ['Integer'], superseded_by: null)]
    case remainingAttendeeCapacity = 'remainingAttendeeCapacity';
    #[SchemaPropertyInfo(rdfs_comment: 'Whether the terms for payment of interest can be renegotiated during the life of the loan.', schema_domain_includes: ['LoanOrCredit'], range_includes: ['Boolean'], superseded_by: null)]
    case renegotiableLoan = 'renegotiableLoan';
    #[SchemaPropertyInfo(rdfs_comment: 'Defines the number of times a recurring <a href="https://schema.org/Event" target="_blank" rel="noopener noreferrer">Event</a> will take place.', schema_domain_includes: ['Schedule'], range_includes: ['Integer'], superseded_by: null)]
    case repeatCount = 'repeatCount';
    #[SchemaPropertyInfo(rdfs_comment: 'Defines the frequency at which <a href="https://schema.org/Event" target="_blank" rel="noopener noreferrer">Event</a>s will occur according to a schedule <a href="https://schema.org/Schedule" target="_blank" rel="noopener noreferrer">Schedule</a>. The intervals between
      events should be defined as a <a href="https://schema.org/Duration" target="_blank" rel="noopener noreferrer">Duration</a> of time.', schema_domain_includes: ['Schedule'], range_includes: ['Duration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case repeatFrequency = 'repeatFrequency';
    #[SchemaPropertyInfo(rdfs_comment: 'Number of times one should repeat the activity.', schema_domain_includes: ['ExercisePlan'], range_includes: ['Number', 'QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case repetitions = 'repetitions';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of object. The object that is being replaced.', schema_domain_includes: ['ReplaceAction'], range_includes: ['Role', 'Text', 'Thing', 'URL'], superseded_by: null)]
    case replacee = 'replacee';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of object. The object that replaces.', schema_domain_includes: ['ReplaceAction'], range_includes: ['Role', 'Text', 'Thing', 'URL'], superseded_by: null)]
    case replacer = 'replacer';
    #[SchemaPropertyInfo(rdfs_comment: 'The URL at which a reply may be posted to the specified UserComment.', schema_domain_includes: ['UserComments'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case replyToUrl = 'replyToUrl';
    #[SchemaPropertyInfo(rdfs_comment: 'The number or other unique designator assigned to a Report by the publishing organization.', schema_domain_includes: ['Report'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case reportNumber = 'reportNumber';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates whether this image is representative of the content of the page.', schema_domain_includes: ['ImageObject'], range_includes: ['Boolean'], superseded_by: null)]
    case representativeOfPage = 'representativeOfPage';
    #[SchemaPropertyInfo(rdfs_comment: 'Assets required to secure loan or credit repayments. It may take form of third party pledge, goods, financial instruments (cash, securities, etc.)', schema_domain_includes: ['LoanOrCredit'], range_includes: ['Role', 'Text', 'Thing', 'URL'], superseded_by: null)]
    case requiredCollateral = 'requiredCollateral';
    #[SchemaPropertyInfo(rdfs_comment: 'Audiences defined by a person\'s gender.', schema_domain_includes: ['PeopleAudience'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case requiredGender = 'requiredGender';
    #[SchemaPropertyInfo(rdfs_comment: 'Audiences defined by a person\'s maximum age.', schema_domain_includes: ['PeopleAudience'], range_includes: ['Integer'], superseded_by: null)]
    case requiredMaxAge = 'requiredMaxAge';
    #[SchemaPropertyInfo(rdfs_comment: 'Audiences defined by a person\'s minimum age.', schema_domain_includes: ['PeopleAudience'], range_includes: ['Integer'], superseded_by: null)]
    case requiredMinAge = 'requiredMinAge';
    #[SchemaPropertyInfo(rdfs_comment: 'The required quantity of the item(s).', schema_domain_includes: ['HowToItem'], range_includes: ['Number', 'QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case requiredQuantity = 'requiredQuantity';
    #[SchemaPropertyInfo(rdfs_comment: 'Component dependency requirements for application. This includes runtime environments and shared libraries that are not included in the application distribution package, but required to run the application (examples: DirectX, Java or .NET runtime).', schema_domain_includes: ['SoftwareApplication'], range_includes: ['Role', 'Text', 'URL'], superseded_by: 'softwareRequirements')]
    case requirements = 'requirements';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates if use of the media require a subscription  (either paid or free). Allowed values are ```true``` or ```false``` (note that an earlier version had \'yes\', \'no\').', schema_domain_includes: ['ActionAccessSpecification', 'MediaObject'], range_includes: ['Boolean', 'MediaSubscription', 'Role', 'Text', 'URL'], superseded_by: null)]
    case requiresSubscription = 'requiresSubscription';
    #[SchemaPropertyInfo(rdfs_comment: 'The thing -- flight, event, restaurant, etc. being reserved.', schema_domain_includes: ['Reservation'], range_includes: ['Role', 'Text', 'Thing', 'URL'], superseded_by: null)]
    case reservationFor = 'reservationFor';
    #[SchemaPropertyInfo(rdfs_comment: 'A unique identifier for the reservation.', schema_domain_includes: ['Reservation'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case reservationId = 'reservationId';
    #[SchemaPropertyInfo(rdfs_comment: 'The current status of the reservation.', schema_domain_includes: ['Reservation'], range_includes: ['ReservationStatusType', 'Role', 'Text', 'URL'], superseded_by: null)]
    case reservationStatus = 'reservationStatus';
    #[SchemaPropertyInfo(rdfs_comment: 'A ticket associated with the reservation.', schema_domain_includes: ['Reservation'], range_includes: ['Role', 'Text', 'Ticket', 'URL'], superseded_by: null)]
    case reservedTicket = 'reservedTicket';
    #[SchemaPropertyInfo(rdfs_comment: 'Responsibilities associated with this role or Occupation.', schema_domain_includes: ['JobPosting', 'Occupation'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case responsibilities = 'responsibilities';
    #[SchemaPropertyInfo(rdfs_comment: 'How often one should break from the activity.', schema_domain_includes: ['ExercisePlan'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case restPeriods = 'restPeriods';
    #[SchemaPropertyInfo(rdfs_comment: 'Use <a href="https://schema.org/MonetaryAmount" target="_blank" rel="noopener noreferrer">MonetaryAmount</a> to specify a fixed restocking fee for product returns, or use <a href="https://schema.org/Number" target="_blank" rel="noopener noreferrer">Number</a> to specify a percentage of the product price paid by the customer.', schema_domain_includes: ['MerchantReturnPolicy', 'MerchantReturnPolicySeasonalOverride'], range_includes: ['MonetaryAmount', 'Number', 'Role', 'Text', 'URL'], superseded_by: null)]
    case restockingFee = 'restockingFee';
    #[SchemaPropertyInfo(rdfs_comment: 'The result produced in the action. E.g. John wrote *a book*.', schema_domain_includes: ['Action'], range_includes: ['Role', 'Text', 'Thing', 'URL'], superseded_by: null)]
    case result = 'result';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of result. The Comment created or sent as a result of this action.', schema_domain_includes: ['CommentAction', 'ReplyAction'], range_includes: ['Comment', 'Role', 'Text', 'URL'], superseded_by: null)]
    case resultComment = 'resultComment';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of result. The review that resulted in the performing of the action.', schema_domain_includes: ['ReviewAction'], range_includes: ['Review', 'Role', 'Text', 'URL'], superseded_by: null)]
    case resultReview = 'resultReview';
    #[SchemaPropertyInfo(rdfs_comment: 'The type of return fees for purchased products (for any return reason).', schema_domain_includes: ['MerchantReturnPolicy', 'MerchantReturnPolicySeasonalOverride'], range_includes: ['ReturnFeesEnumeration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case returnFees = 'returnFees';
    #[SchemaPropertyInfo(rdfs_comment: 'The method (from an enumeration) by which the customer obtains a return shipping label for a product returned for any reason.', schema_domain_includes: ['MerchantReturnPolicy'], range_includes: ['ReturnLabelSourceEnumeration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case returnLabelSource = 'returnLabelSource';
    #[SchemaPropertyInfo(rdfs_comment: 'The type of return method offered, specified from an enumeration.', schema_domain_includes: ['MerchantReturnPolicy', 'MerchantReturnPolicySeasonalOverride'], range_includes: ['ReturnMethodEnumeration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case returnMethod = 'returnMethod';
    #[SchemaPropertyInfo(rdfs_comment: 'Specifies an applicable return policy (from an enumeration).', schema_domain_includes: ['MerchantReturnPolicy', 'MerchantReturnPolicySeasonalOverride'], range_includes: ['MerchantReturnEnumeration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case returnPolicyCategory = 'returnPolicyCategory';
    #[SchemaPropertyInfo(rdfs_comment: 'The country where the product has to be sent to for returns, for example "Ireland" using the <a href="https://schema.org/name" target="_blank" rel="noopener noreferrer">name</a> property of <a href="https://schema.org/Country" target="_blank" rel="noopener noreferrer">Country</a>. You can also provide the two-letter <a href="http://en.wikipedia.org/wiki/ISO_3166-1" target="_blank" rel="noopener noreferrer">ISO 3166-1 alpha-2 country code</a>. Note that this can be different from the country where the product was originally shipped from or sent to.', schema_domain_includes: ['MerchantReturnPolicy'], range_includes: ['Country', 'Role', 'Text', 'URL'], superseded_by: null)]
    case returnPolicyCountry = 'returnPolicyCountry';
    #[SchemaPropertyInfo(rdfs_comment: 'Seasonal override of a return policy.', schema_domain_includes: ['MerchantReturnPolicy'], range_includes: ['MerchantReturnPolicySeasonalOverride', 'Role', 'Text', 'URL'], superseded_by: null)]
    case returnPolicySeasonalOverride = 'returnPolicySeasonalOverride';
    #[SchemaPropertyInfo(rdfs_comment: 'Amount of shipping costs for product returns (for any reason). Applicable when property <a href="https://schema.org/returnFees" target="_blank" rel="noopener noreferrer">returnFees</a> equals <a href="https://schema.org/ReturnShippingFees" target="_blank" rel="noopener noreferrer">ReturnShippingFees</a>.', schema_domain_includes: ['MerchantReturnPolicy', 'MerchantReturnPolicySeasonalOverride'], range_includes: ['MonetaryAmount', 'Role', 'Text', 'URL'], superseded_by: null)]
    case returnShippingFeesAmount = 'returnShippingFeesAmount';
    #[SchemaPropertyInfo(rdfs_comment: 'A review of the item.', schema_domain_includes: ['Brand', 'CreativeWork', 'Event', 'Offer', 'Organization', 'Place', 'Product', 'Service'], range_includes: ['Review', 'Role', 'Text', 'URL'], superseded_by: null)]
    case review = 'review';
    #[SchemaPropertyInfo(rdfs_comment: 'This Review or Rating is relevant to this part or facet of the itemReviewed.', schema_domain_includes: ['Guide', 'Rating', 'Review'], range_includes: ['Role', 'StructuredValue', 'Text', 'URL'], superseded_by: null)]
    case reviewAspect = 'reviewAspect';
    #[SchemaPropertyInfo(rdfs_comment: 'The actual body of the review.', schema_domain_includes: ['Review'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case reviewBody = 'reviewBody';
    #[SchemaPropertyInfo(rdfs_comment: 'The count of total number of reviews.', schema_domain_includes: ['AggregateRating'], range_includes: ['Integer'], superseded_by: null)]
    case reviewCount = 'reviewCount';
    #[SchemaPropertyInfo(rdfs_comment: 'The rating given in this review. Note that reviews can themselves be rated. The ```reviewRating``` applies to rating given by the review. The <a href="https://schema.org/aggregateRating" target="_blank" rel="noopener noreferrer">aggregateRating</a> property applies to the review itself, as a creative work.', schema_domain_includes: ['Review'], range_includes: ['Rating', 'Role', 'Text', 'URL'], superseded_by: null)]
    case reviewRating = 'reviewRating';
    #[SchemaPropertyInfo(rdfs_comment: 'People or organizations that have reviewed the content on this web page for accuracy and/or completeness.', schema_domain_includes: ['WebPage'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case reviewedBy = 'reviewedBy';
    #[SchemaPropertyInfo(rdfs_comment: 'Review of the item.', schema_domain_includes: ['CreativeWork', 'Offer', 'Organization', 'Place', 'Product'], range_includes: ['Review', 'Role', 'Text', 'URL'], superseded_by: 'review')]
    case reviews = 'reviews';
    #[SchemaPropertyInfo(rdfs_comment: 'A modifiable or non-modifiable factor that increases the risk of a patient contracting this condition, e.g. age,  coexisting condition.', schema_domain_includes: ['MedicalCondition'], range_includes: ['MedicalRiskFactor', 'Role', 'Text', 'URL'], superseded_by: null)]
    case riskFactor = 'riskFactor';
    #[SchemaPropertyInfo(rdfs_comment: 'Specific physiologic risks associated to the diet plan.', schema_domain_includes: ['Diet'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case risks = 'risks';
    #[SchemaPropertyInfo(rdfs_comment: 'A role played, performed or filled by a person or organization. For example, the team of creators for a comic book might fill the roles named \'inker\', \'penciller\', and \'letterer\'; or an athlete in a SportsTeam might play in the position named \'Quarterback\'.', schema_domain_includes: ['Role'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case roleName = 'roleName';
    #[SchemaPropertyInfo(rdfs_comment: 'The permitted total weight of cargo and installations (e.g. a roof rack) on top of the vehicle.\\n\\nTypical unit code(s): KGM for kilogram, LBR for pound\\n\\n* Note 1: You can indicate additional information in the <a href="https://schema.org/name" target="_blank" rel="noopener noreferrer">name</a> of the <a href="https://schema.org/QuantitativeValue" target="_blank" rel="noopener noreferrer">QuantitativeValue</a> node.\\n* Note 2: You may also link to a <a href="https://schema.org/QualitativeValue" target="_blank" rel="noopener noreferrer">QualitativeValue</a> node that provides additional information using <a href="https://schema.org/valueReference" target="_blank" rel="noopener noreferrer">valueReference</a>\\n* Note 3: Note that you can use <a href="https://schema.org/minValue" target="_blank" rel="noopener noreferrer">minValue</a> and <a href="https://schema.org/maxValue" target="_blank" rel="noopener noreferrer">maxValue</a> to indicate ranges.', schema_domain_includes: ['BusOrCoach', 'Car'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case roofLoad = 'roofLoad';
    #[SchemaPropertyInfo(rdfs_comment: 'The response (yes, no, maybe) to the RSVP.', schema_domain_includes: ['RsvpAction'], range_includes: ['Role', 'RsvpResponseType', 'Text', 'URL'], superseded_by: null)]
    case rsvpResponse = 'rsvpResponse';
    #[SchemaPropertyInfo(rdfs_comment: 'The vasculature the lymphatic structure runs, or efferents, to.', schema_domain_includes: ['LymphaticVessel'], range_includes: ['Role', 'Text', 'URL', 'Vessel'], superseded_by: null)]
    case runsTo = 'runsTo';
    #[SchemaPropertyInfo(rdfs_comment: 'Runtime platform or script interpreter dependencies (example: Java v1, Python 2.3, .NET Framework 3.0).', schema_domain_includes: ['SoftwareSourceCode'], range_includes: ['Role', 'Text', 'URL'], superseded_by: 'runtimePlatform')]
    case runtime = 'runtime';
    #[SchemaPropertyInfo(rdfs_comment: 'Runtime platform or script interpreter dependencies (example: Java v1, Python 2.3, .NET Framework 3.0).', schema_domain_includes: ['SoftwareApplication', 'SoftwareSourceCode'], range_includes: ['Role', 'RuntimePlatform', 'Text', 'URL'], superseded_by: null)]
    case runtimePlatform = 'runtimePlatform';
    #[SchemaPropertyInfo(rdfs_comment: 'The RxCUI drug identifier from RXNORM.', schema_domain_includes: ['Drug'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case rxcui = 'rxcui';
    #[SchemaPropertyInfo(rdfs_comment: 'Any potential safety concern associated with the supplement. May include interactions with other drugs and foods, pregnancy, breastfeeding, known adverse reactions, and documented efficacy of the supplement.', schema_domain_includes: ['DietarySupplement'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case safetyConsideration = 'safetyConsideration';
    #[SchemaPropertyInfo(rdfs_comment: 'The currency (coded using <a href="http://en.wikipedia.org/wiki/ISO_4217" target="_blank" rel="noopener noreferrer">ISO 4217</a>) used for the main salary information in this job posting or for this employee.', schema_domain_includes: ['EmployeeRole', 'JobPosting'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case salaryCurrency = 'salaryCurrency';
    #[SchemaPropertyInfo(rdfs_comment: 'The expected salary upon completing the training.', schema_domain_includes: ['EducationalOccupationalProgram'], range_includes: ['MonetaryAmountDistribution', 'Role', 'Text', 'URL'], superseded_by: null)]
    case salaryUponCompletion = 'salaryUponCompletion';
    #[SchemaPropertyInfo(rdfs_comment: 'URL of a reference Web page that unambiguously indicates the item\'s identity. E.g. the URL of the item\'s Wikipedia page, Wikidata entry, or official website.', schema_domain_includes: ['Thing'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case sameAs = 'sameAs';
    #[SchemaPropertyInfo(rdfs_comment: 'What type of code sample: full (compile ready) solution, code snippet, inline code, scripts, template.', schema_domain_includes: ['SoftwareSourceCode'], range_includes: ['Role', 'Text', 'URL'], superseded_by: 'codeSampleType')]
    case sampleType = 'sampleType';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of grams of saturated fat.', schema_domain_includes: ['NutritionInformation'], range_includes: ['Mass', 'Role', 'Text', 'URL'], superseded_by: null)]
    case saturatedFatContent = 'saturatedFatContent';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates the timezone for which the time(s) indicated in the <a href="https://schema.org/Schedule" target="_blank" rel="noopener noreferrer">Schedule</a> are given. The value provided should be among those listed in the IANA Time Zone Database.', schema_domain_includes: ['Schedule'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case scheduleTimezone = 'scheduleTimezone';
    #[SchemaPropertyInfo(rdfs_comment: 'The date the invoice is scheduled to be paid.', schema_domain_includes: ['Invoice'], range_includes: ['Date'], superseded_by: null)]
    case scheduledPaymentDate = 'scheduledPaymentDate';
    #[SchemaPropertyInfo(rdfs_comment: 'The time the object is scheduled to.', schema_domain_includes: ['PlanAction'], range_includes: ['Date', 'DateTime'], superseded_by: null)]
    case scheduledTime = 'scheduledTime';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates (by URL or string) a particular version of a schema used in some CreativeWork. This property was created primarily to
    indicate the use of a specific schema.org release, e.g. ```10.0``` as a simple string, or more explicitly via URL, ```https://schema.org/docs/releases.html#v10.0```. There may be situations in which other schemas might usefully be referenced this way, e.g. ```http://dublincore.org/specifications/dublin-core/dces/1999-07-02/``` but this has not been carefully explored in the community.', schema_domain_includes: ['CreativeWork'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case schemaVersion = 'schemaVersion';
    #[SchemaPropertyInfo(rdfs_comment: 'Information about school closures.', schema_domain_includes: ['SpecialAnnouncement'], range_includes: ['Role', 'Text', 'URL', 'WebContent'], superseded_by: null)]
    case schoolClosuresInfo = 'schoolClosuresInfo';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of screens in the movie theater.', schema_domain_includes: ['MovieTheater'], range_includes: ['Number'], superseded_by: null)]
    case screenCount = 'screenCount';
    #[SchemaPropertyInfo(rdfs_comment: 'A link to a screenshot image of the app.', schema_domain_includes: ['SoftwareApplication'], range_includes: ['ImageObject', 'Role', 'Text', 'URL'], superseded_by: null)]
    case screenshot = 'screenshot';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates the date on which the current structured data was generated / published. Typically used alongside <a href="https://schema.org/sdPublisher" target="_blank" rel="noopener noreferrer">sdPublisher</a>.', schema_domain_includes: ['CreativeWork'], range_includes: ['Date'], superseded_by: null)]
    case sdDatePublished = 'sdDatePublished';
    #[SchemaPropertyInfo(rdfs_comment: 'A license document that applies to this structured data, typically indicated by URL.', schema_domain_includes: ['CreativeWork'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case sdLicense = 'sdLicense';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates the party responsible for generating and publishing the current structured data markup, typically in cases where the structured data is derived automatically from existing published content but published on a different site. For example, student projects and open data initiatives often re-publish existing content with more explicitly structured metadata. The
<a href="https://schema.org/sdPublisher" target="_blank" rel="noopener noreferrer">sdPublisher</a> property helps make such practices more explicit.', schema_domain_includes: ['CreativeWork'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case sdPublisher = 'sdPublisher';
    #[SchemaPropertyInfo(rdfs_comment: 'A season in a media series.', schema_domain_includes: ['RadioSeries', 'TVSeries', 'VideoGameSeries'], range_includes: ['CreativeWorkSeason', 'Role', 'Text', 'URL'], superseded_by: 'containsSeason')]
    case season = 'season';
    #[SchemaPropertyInfo(rdfs_comment: 'Position of the season within an ordered group of seasons.', schema_domain_includes: ['CreativeWorkSeason'], range_includes: ['Integer', 'Role', 'Text', 'URL'], superseded_by: null)]
    case seasonNumber = 'seasonNumber';
    #[SchemaPropertyInfo(rdfs_comment: 'Limited period during which these shipping conditions apply.', schema_domain_includes: ['ShippingConditions'], range_includes: ['OpeningHoursSpecification', 'Role', 'Text', 'URL'], superseded_by: null)]
    case seasonalOverride = 'seasonalOverride';
    #[SchemaPropertyInfo(rdfs_comment: 'A season in a media series.', schema_domain_includes: ['RadioSeries', 'TVSeries', 'VideoGameSeries'], range_includes: ['CreativeWorkSeason', 'Role', 'Text', 'URL'], superseded_by: 'season')]
    case seasons = 'seasons';
    #[SchemaPropertyInfo(rdfs_comment: 'The location of the reserved seat (e.g., 27).', schema_domain_includes: ['Seat'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case seatNumber = 'seatNumber';
    #[SchemaPropertyInfo(rdfs_comment: 'The row location of the reserved seat (e.g., B).', schema_domain_includes: ['Seat'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case seatRow = 'seatRow';
    #[SchemaPropertyInfo(rdfs_comment: 'The section location of the reserved seat (e.g. Orchestra).', schema_domain_includes: ['Seat'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case seatSection = 'seatSection';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of persons that can be seated (e.g. in a vehicle), both in terms of the physical space available, and in terms of limitations set by law.\\n\\nTypical unit code(s): C62 for persons.', schema_domain_includes: ['Vehicle'], range_includes: ['Number', 'QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case seatingCapacity = 'seatingCapacity';
    #[SchemaPropertyInfo(rdfs_comment: 'The type/class of the seat.', schema_domain_includes: ['Seat'], range_includes: ['QualitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case seatingType = 'seatingType';
    #[SchemaPropertyInfo(rdfs_comment: 'A preventative therapy used to prevent reoccurrence of the medical condition after an initial episode of the condition.', schema_domain_includes: ['MedicalCondition'], range_includes: ['Drug', 'DrugClass', 'LifestyleModification', 'MedicalTherapy', 'Role', 'Text', 'URL'], superseded_by: null)]
    case secondaryPrevention = 'secondaryPrevention';
    #[SchemaPropertyInfo(rdfs_comment: 'A description of any security clearance requirements of the job.', schema_domain_includes: ['JobPosting'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case securityClearanceRequirement = 'securityClearanceRequirement';
    #[SchemaPropertyInfo(rdfs_comment: 'The type of security screening the passenger is subject to.', schema_domain_includes: ['FlightReservation'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case securityScreening = 'securityScreening';
    #[SchemaPropertyInfo(rdfs_comment: 'A pointer to products or services sought by the organization or person (demand).', schema_domain_includes: ['Organization', 'Person'], range_includes: ['Demand', 'Role', 'Text', 'URL'], superseded_by: null)]
    case seeks = 'seeks';
    #[SchemaPropertyInfo(rdfs_comment: 'An entity which offers (sells / leases / lends / loans) the services / goods.  A seller may also be a provider.', schema_domain_includes: ['BuyAction', 'Demand', 'Flight', 'Offer', 'Order'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case seller = 'seller';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of participant. The participant who is at the sending end of the action.', schema_domain_includes: ['Message', 'ReceiveAction'], range_includes: ['Audience', 'Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case sender = 'sender';
    #[SchemaPropertyInfo(rdfs_comment: 'A description of any sensory requirements and levels necessary to function on the job, including hearing and vision. Defined terms such as those in O*net may be used, but note that there is no way to specify the level of ability as well as its nature when using a defined term.', schema_domain_includes: ['JobPosting'], range_includes: ['DefinedTerm', 'Role', 'Text', 'URL'], superseded_by: null)]
    case sensoryRequirement = 'sensoryRequirement';
    #[SchemaPropertyInfo(rdfs_comment: 'The neurological pathway extension that inputs and sends information to the brain or spinal cord.', schema_domain_includes: ['Nerve'], range_includes: ['AnatomicalStructure', 'Role', 'SuperficialAnatomy', 'Text', 'URL'], superseded_by: null)]
    case sensoryUnit = 'sensoryUnit';
    #[SchemaPropertyInfo(rdfs_comment: 'The serial number or any alphanumeric identifier of a particular product. When attached to an offer, it is a shortcut for the serial number of the product included in the offer.', schema_domain_includes: ['Demand', 'IndividualProduct', 'Offer'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case serialNumber = 'serialNumber';
    #[SchemaPropertyInfo(rdfs_comment: 'A possible serious complication and/or serious side effect of this therapy. Serious adverse outcomes include those that are life-threatening; result in death, disability, or permanent damage; require hospitalization or prolong existing hospitalization; cause congenital anomalies or birth defects; or jeopardize the patient and may require medical or surgical intervention to prevent one of the outcomes in this definition.', schema_domain_includes: ['MedicalDevice', 'MedicalTherapy'], range_includes: ['MedicalEntity', 'Role', 'Text', 'URL'], superseded_by: null)]
    case seriousAdverseOutcome = 'seriousAdverseOutcome';
    #[SchemaPropertyInfo(rdfs_comment: 'Status of a game server.', schema_domain_includes: ['GameServer'], range_includes: ['GameServerStatus', 'Role', 'Text', 'URL'], superseded_by: null)]
    case serverStatus = 'serverStatus';
    #[SchemaPropertyInfo(rdfs_comment: 'The cuisine of the restaurant.', schema_domain_includes: ['FoodEstablishment'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case servesCuisine = 'servesCuisine';
    #[SchemaPropertyInfo(rdfs_comment: 'The geographic area where the service is provided.', schema_domain_includes: ['ContactPoint', 'Organization', 'Service'], range_includes: ['AdministrativeArea', 'GeoShape', 'Place', 'Role', 'Text', 'URL'], superseded_by: 'areaServed')]
    case serviceArea = 'serviceArea';
    #[SchemaPropertyInfo(rdfs_comment: 'The audience eligible for this service.', schema_domain_includes: ['Service'], range_includes: ['Audience', 'Role', 'Text', 'URL'], superseded_by: 'audience')]
    case serviceAudience = 'serviceAudience';
    #[SchemaPropertyInfo(rdfs_comment: 'The location (e.g. civic structure, local business, etc.) where a person can go to access the service.', schema_domain_includes: ['ServiceChannel'], range_includes: ['Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case serviceLocation = 'serviceLocation';
    #[SchemaPropertyInfo(rdfs_comment: 'The operating organization, if different from the provider.  This enables the representation of services that are provided by an organization, but operated by another organization like a subcontractor.', schema_domain_includes: ['GovernmentService'], range_includes: ['Organization', 'Role', 'Text', 'URL'], superseded_by: null)]
    case serviceOperator = 'serviceOperator';
    #[SchemaPropertyInfo(rdfs_comment: 'The tangible thing generated by the service, e.g. a passport, permit, etc.', schema_domain_includes: ['Service'], range_includes: ['Role', 'Text', 'Thing', 'URL'], superseded_by: null)]
    case serviceOutput = 'serviceOutput';
    #[SchemaPropertyInfo(rdfs_comment: 'The phone number to use to access the service.', schema_domain_includes: ['ServiceChannel'], range_includes: ['ContactPoint', 'Role', 'Text', 'URL'], superseded_by: null)]
    case servicePhone = 'servicePhone';
    #[SchemaPropertyInfo(rdfs_comment: 'The address for accessing the service by mail.', schema_domain_includes: ['ServiceChannel'], range_includes: ['PostalAddress', 'Role', 'Text', 'URL'], superseded_by: null)]
    case servicePostalAddress = 'servicePostalAddress';
    #[SchemaPropertyInfo(rdfs_comment: 'The number to access the service by text message.', schema_domain_includes: ['ServiceChannel'], range_includes: ['ContactPoint', 'Role', 'Text', 'URL'], superseded_by: null)]
    case serviceSmsNumber = 'serviceSmsNumber';
    #[SchemaPropertyInfo(rdfs_comment: 'The type of service being offered, e.g. veterans\' benefits, emergency relief, etc.', schema_domain_includes: ['Service'], range_includes: ['GovernmentBenefitsType', 'Role', 'Text', 'URL'], superseded_by: null)]
    case serviceType = 'serviceType';
    #[SchemaPropertyInfo(rdfs_comment: 'The website to access the service.', schema_domain_includes: ['ServiceChannel'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case serviceUrl = 'serviceUrl';
    #[SchemaPropertyInfo(rdfs_comment: 'The serving size, in terms of the number of volume or mass.', schema_domain_includes: ['NutritionInformation'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case servingSize = 'servingSize';
    #[SchemaPropertyInfo(rdfs_comment: 'The <a href="https://en.wikipedia.org/wiki/SHA-2" target="_blank" rel="noopener noreferrer">SHA-2</a> SHA256 hash of the content of the item. For example, a zero-length input has value \'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855\'.', schema_domain_includes: ['MediaObject'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case sha256 = 'sha256';
    #[SchemaPropertyInfo(rdfs_comment: 'A CreativeWork such as an image, video, or audio clip shared as part of this posting.', schema_domain_includes: ['Comment', 'SocialMediaPosting'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case sharedContent = 'sharedContent';
    #[SchemaPropertyInfo(rdfs_comment: 'The conditions (constraints, price) applicable to the <a href="https://schema.org/ShippingService" target="_blank" rel="noopener noreferrer">ShippingService</a>.', schema_domain_includes: ['ShippingService'], range_includes: ['Role', 'ShippingConditions', 'Text', 'URL'], superseded_by: null)]
    case shippingConditions = 'shippingConditions';
    #[SchemaPropertyInfo(rdfs_comment: 'indicates (possibly multiple) shipping destinations. These can be defined in several ways, e.g. postalCode ranges.', schema_domain_includes: ['DeliveryTimeSettings', 'OfferShippingDetails', 'ShippingConditions', 'ShippingRateSettings'], range_includes: ['DefinedRegion', 'Role', 'Text', 'URL'], superseded_by: null)]
    case shippingDestination = 'shippingDestination';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates information about the shipping policies and options associated with an <a href="https://schema.org/Offer" target="_blank" rel="noopener noreferrer">Offer</a>.', schema_domain_includes: ['Offer'], range_includes: ['OfferShippingDetails', 'Role', 'Text', 'URL'], superseded_by: null)]
    case shippingDetails = 'shippingDetails';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates the origin of a shipment, i.e. where it should be coming from.', schema_domain_includes: ['OfferShippingDetails', 'ShippingConditions'], range_includes: ['DefinedRegion', 'Role', 'Text', 'URL'], superseded_by: null)]
    case shippingOrigin = 'shippingOrigin';
    #[SchemaPropertyInfo(rdfs_comment: 'The shipping rate is the cost of shipping to the specified destination. Typically, the maxValue and currency values (of the <a href="https://schema.org/MonetaryAmount" target="_blank" rel="noopener noreferrer">MonetaryAmount</a>) are most appropriate.', schema_domain_includes: ['OfferShippingDetails', 'ShippingConditions', 'ShippingRateSettings'], range_includes: ['MonetaryAmount', 'Role', 'ShippingRateSettings', 'Text', 'URL'], superseded_by: null)]
    case shippingRate = 'shippingRate';
    #[SchemaPropertyInfo(rdfs_comment: 'A sibling of the person.', schema_domain_includes: ['Person'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case sibling = 'sibling';
    #[SchemaPropertyInfo(rdfs_comment: 'A sibling of the person.', schema_domain_includes: ['Person'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: 'sibling')]
    case siblings = 'siblings';
    #[SchemaPropertyInfo(rdfs_comment: 'A sign detected by the test.', schema_domain_includes: ['MedicalTest'], range_includes: ['MedicalSign', 'Role', 'Text', 'URL'], superseded_by: null)]
    case signDetected = 'signDetected';
    #[SchemaPropertyInfo(rdfs_comment: 'A sign or symptom of this condition. Signs are objective or physically observable manifestations of the medical condition while symptoms are the subjective experience of the medical condition.', schema_domain_includes: ['MedicalCondition'], range_includes: ['MedicalSignOrSymptom', 'Role', 'Text', 'URL'], superseded_by: null)]
    case signOrSymptom = 'signOrSymptom';
    #[SchemaPropertyInfo(rdfs_comment: 'The significance associated with the superficial anatomy; as an example, how characteristics of the superficial anatomy can suggest underlying medical conditions or courses of treatment.', schema_domain_includes: ['SuperficialAnatomy'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case significance = 'significance';
    #[SchemaPropertyInfo(rdfs_comment: 'One of the more significant URLs on the page. Typically, these are the non-navigation links that are clicked on the most.', schema_domain_includes: ['WebPage'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case significantLink = 'significantLink';
    #[SchemaPropertyInfo(rdfs_comment: 'The most significant URLs on the page. Typically, these are the non-navigation links that are clicked on the most.', schema_domain_includes: ['WebPage'], range_includes: ['Role', 'Text', 'URL'], superseded_by: 'significantLink')]
    case significantLinks = 'significantLinks';
    #[SchemaPropertyInfo(rdfs_comment: 'A standardized size of a product or creative work, specified either through a simple textual string (for example \'XL\', \'32Wx34L\'), a  QuantitativeValue with a unitCode, or a comprehensive and structured <a href="https://schema.org/SizeSpecification" target="_blank" rel="noopener noreferrer">SizeSpecification</a>; in other cases, the <a href="https://schema.org/width" target="_blank" rel="noopener noreferrer">width</a>, <a href="https://schema.org/height" target="_blank" rel="noopener noreferrer">height</a>, <a href="https://schema.org/depth" target="_blank" rel="noopener noreferrer">depth</a> and <a href="https://schema.org/weight" target="_blank" rel="noopener noreferrer">weight</a> properties may be more applicable.', schema_domain_includes: ['CreativeWork', 'Product'], range_includes: ['DefinedTerm', 'QuantitativeValue', 'Role', 'SizeSpecification', 'Text', 'URL'], superseded_by: null)]
    case size = 'size';
    #[SchemaPropertyInfo(rdfs_comment: 'The size group (also known as "size type") for a product\'s size. Size groups are common in the fashion industry to define size segments and suggested audiences for wearable products. Multiple values can be combined, for example "men\'s big and tall", "petite maternity" or "regular".', schema_domain_includes: ['SizeSpecification'], range_includes: ['Role', 'SizeGroupEnumeration', 'Text', 'URL'], superseded_by: null)]
    case sizeGroup = 'sizeGroup';
    #[SchemaPropertyInfo(rdfs_comment: 'The size system used to identify a product\'s size. Typically either a standard (for example, "GS1" or "ISO-EN13402"), country code (for example "US" or "JP"), or a measuring system (for example "Metric" or "Imperial").', schema_domain_includes: ['SizeSpecification'], range_includes: ['Role', 'SizeSystemEnumeration', 'Text', 'URL'], superseded_by: null)]
    case sizeSystem = 'sizeSystem';
    #[SchemaPropertyInfo(rdfs_comment: 'A statement of knowledge, skill, ability, task or any other assertion expressing a competency that is either claimed by a person, an organization or desired or required to fulfill a role or to work in an occupation.', schema_domain_includes: ['JobPosting', 'Occupation', 'Organization', 'Person'], range_includes: ['DefinedTerm', 'Role', 'Text', 'URL'], superseded_by: null)]
    case skills = 'skills';
    #[SchemaPropertyInfo(rdfs_comment: 'The Stock Keeping Unit (SKU), i.e. a merchant-specific identifier for a product or service, or the product to which the offer refers.', schema_domain_includes: ['Demand', 'Offer', 'Product'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case sku = 'sku';
    #[SchemaPropertyInfo(rdfs_comment: 'A slogan or motto associated with the item.', schema_domain_includes: ['Brand', 'Organization', 'Place', 'Product', 'Service'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case slogan = 'slogan';
    #[SchemaPropertyInfo(rdfs_comment: 'A specification in form of a line notation for describing the structure of chemical species using short ASCII strings.  Double bond stereochemistry \\ indicators may need to be escaped in the string in formats where the backslash is an escape character.', schema_domain_includes: ['MolecularEntity'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case smiles = 'smiles';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates whether it is allowed to smoke in the place, e.g. in the restaurant, hotel or hotel room.', schema_domain_includes: ['Place'], range_includes: ['Boolean'], superseded_by: null)]
    case smokingAllowed = 'smokingAllowed';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of milligrams of sodium.', schema_domain_includes: ['NutritionInformation'], range_includes: ['Mass', 'Role', 'Text', 'URL'], superseded_by: null)]
    case sodiumContent = 'sodiumContent';
    #[SchemaPropertyInfo(rdfs_comment: 'Additional content for a software application.', schema_domain_includes: ['SoftwareApplication'], range_includes: ['Role', 'SoftwareApplication', 'Text', 'URL'], superseded_by: null)]
    case softwareAddOn = 'softwareAddOn';
    #[SchemaPropertyInfo(rdfs_comment: 'Software application help.', schema_domain_includes: ['SoftwareApplication'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case softwareHelp = 'softwareHelp';
    #[SchemaPropertyInfo(rdfs_comment: 'Component dependency requirements for application. This includes runtime environments and shared libraries that are not included in the application distribution package, but required to run the application (examples: DirectX, Java or .NET runtime).', schema_domain_includes: ['SoftwareApplication'], range_includes: ['Role', 'SoftwareApplication', 'Text', 'URL'], superseded_by: null)]
    case softwareRequirements = 'softwareRequirements';
    #[SchemaPropertyInfo(rdfs_comment: 'Version of the software instance.', schema_domain_includes: ['SoftwareApplication'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case softwareVersion = 'softwareVersion';
    #[SchemaPropertyInfo(rdfs_comment: 'The source or cause of the event.', schema_domain_includes: ['InstantaneousEvent'], range_includes: ['Role', 'Text', 'Thing', 'URL'], superseded_by: null)]
    case source = 'source';
    #[SchemaPropertyInfo(rdfs_comment: 'The Organization on whose behalf the creator was working.', schema_domain_includes: ['CreativeWork'], range_includes: ['Organization', 'Role', 'Text', 'URL'], superseded_by: null)]
    case sourceOrganization = 'sourceOrganization';
    #[SchemaPropertyInfo(rdfs_comment: 'The neurological pathway that originates the neurons.', schema_domain_includes: ['Nerve'], range_includes: ['BrainStructure', 'Role', 'Text', 'URL'], superseded_by: null)]
    case sourcedFrom = 'sourcedFrom';
    #[SchemaPropertyInfo(rdfs_comment: 'The "spatial" property can be used in cases when more specific properties
(e.g. <a href="https://schema.org/locationCreated" target="_blank" rel="noopener noreferrer">locationCreated</a>, <a href="https://schema.org/spatialCoverage" target="_blank" rel="noopener noreferrer">spatialCoverage</a>, <a href="https://schema.org/contentLocation" target="_blank" rel="noopener noreferrer">contentLocation</a>) are not known to be appropriate.', schema_domain_includes: ['CreativeWork'], range_includes: ['Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case spatial = 'spatial';
    #[SchemaPropertyInfo(rdfs_comment: 'The spatialCoverage of a CreativeWork indicates the place(s) which are the focus of the content. It is a subproperty of
      contentLocation intended primarily for more technical and detailed materials. For example with a Dataset, it indicates
      areas that the dataset describes: a dataset of New York weather would have spatialCoverage which was the place: the state of New York.', schema_domain_includes: ['CreativeWork'], range_includes: ['Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case spatialCoverage = 'spatialCoverage';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates sections of a Web page that are particularly \'speakable\' in the sense of being highlighted as being especially appropriate for text-to-speech conversion. Other sections of a page may also be usefully spoken in particular circumstances; the \'speakable\' property serves to indicate the parts most likely to be generally useful for speech.

The *speakable* property can be repeated an arbitrary number of times, with three kinds of possible \'content-locator\' values:

1.) *id-value* URL references - uses *id-value* of an element in the page being annotated. The simplest use of *speakable* has (potentially relative) URL values, referencing identified sections of the document concerned.

2.) CSS Selectors - addresses content in the annotated page, e.g. via class attribute. Use the <a href="https://schema.org/cssSelector" target="_blank" rel="noopener noreferrer">cssSelector</a> property.

3.)  XPaths - addresses content via XPaths (assuming an XML view of the content). Use the <a href="https://schema.org/xpath" target="_blank" rel="noopener noreferrer">xpath</a> property.


For more sophisticated markup of speakable sections beyond simple ID references, either CSS selectors or XPath expressions to pick out document section(s) as speakable. For this
we define a supporting type, <a href="https://schema.org/SpeakableSpecification" target="_blank" rel="noopener noreferrer">SpeakableSpecification</a>  which is defined to be a possible value of the *speakable* property.', schema_domain_includes: ['Article', 'WebPage'], range_includes: ['Role', 'SpeakableSpecification', 'Text', 'URL'], superseded_by: null)]
    case speakable = 'speakable';
    #[SchemaPropertyInfo(rdfs_comment: 'Any special commitments associated with this job posting. Valid entries include VeteranCommit, MilitarySpouseCommit, etc.', schema_domain_includes: ['JobPosting'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case specialCommitments = 'specialCommitments';
    #[SchemaPropertyInfo(rdfs_comment: 'The special opening hours of a certain place.\\n\\nUse this to explicitly override general opening hours brought in scope by <a href="https://schema.org/openingHoursSpecification" target="_blank" rel="noopener noreferrer">openingHoursSpecification</a> or <a href="https://schema.org/openingHours" target="_blank" rel="noopener noreferrer">openingHours</a>.', schema_domain_includes: ['Place'], range_includes: ['OpeningHoursSpecification', 'Role', 'Text', 'URL'], superseded_by: null)]
    case specialOpeningHoursSpecification = 'specialOpeningHoursSpecification';
    #[SchemaPropertyInfo(rdfs_comment: 'One of the domain specialities to which this web page\'s content applies.', schema_domain_includes: ['WebPage'], range_includes: ['Role', 'Specialty', 'Text', 'URL'], superseded_by: null)]
    case specialty = 'specialty';
    #[SchemaPropertyInfo(rdfs_comment: 'Form of markup used. eg. <a href="https://www.w3.org/TR/speech-synthesis11" target="_blank" rel="noopener noreferrer">SSML</a> or <a href="https://www.wikidata.org/wiki/Property:P898" target="_blank" rel="noopener noreferrer">IPA</a>.', schema_domain_includes: ['PronounceableText'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case speechToTextMarkup = 'speechToTextMarkup';
    #[SchemaPropertyInfo(rdfs_comment: 'The speed range of the vehicle. If the vehicle is powered by an engine, the upper limit of the speed range (indicated by <a href="https://schema.org/maxValue" target="_blank" rel="noopener noreferrer">maxValue</a>) should be the maximum speed achievable under regular conditions.\\n\\nTypical unit code(s): KMH for km/h, HM for mile per hour (0.447 04 m/s), KNT for knot\\n\\n*Note 1: Use <a href="https://schema.org/minValue" target="_blank" rel="noopener noreferrer">minValue</a> and <a href="https://schema.org/maxValue" target="_blank" rel="noopener noreferrer">maxValue</a> to indicate the range. Typically, the minimal value is zero.\\n* Note 2: There are many different ways of measuring the speed range. You can link to information about how the given value has been determined using the <a href="https://schema.org/valueReference" target="_blank" rel="noopener noreferrer">valueReference</a> property.', schema_domain_includes: ['Vehicle'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case speed = 'speed';
    #[SchemaPropertyInfo(rdfs_comment: 'The (e.g. fictional) character, Person or Organization to whom the quotation is attributed within the containing CreativeWork.', schema_domain_includes: ['Quotation'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case spokenByCharacter = 'spokenByCharacter';
    #[SchemaPropertyInfo(rdfs_comment: 'A person or organization that supports a thing through a pledge, promise, or financial contribution. E.g. a sponsor of a Medical Study or a corporate sponsor of an event.', schema_domain_includes: ['CreativeWork', 'Event', 'Grant', 'MedicalStudy', 'Organization', 'Person'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case sponsor = 'sponsor';
    #[SchemaPropertyInfo(rdfs_comment: 'A type of sport (e.g. Baseball).', schema_domain_includes: ['SportsEvent', 'SportsOrganization'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case sport = 'sport';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of location. The sports activity location where this action occurred.', schema_domain_includes: ['ExerciseAction'], range_includes: ['Role', 'SportsActivityLocation', 'Text', 'URL'], superseded_by: null)]
    case sportsActivityLocation = 'sportsActivityLocation';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of location. The sports event where this action occurred.', schema_domain_includes: ['ExerciseAction'], range_includes: ['Role', 'SportsEvent', 'Text', 'URL'], superseded_by: null)]
    case sportsEvent = 'sportsEvent';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of participant. The sports team that participated on this action.', schema_domain_includes: ['ExerciseAction'], range_includes: ['Role', 'SportsTeam', 'Text', 'URL'], superseded_by: null)]
    case sportsTeam = 'sportsTeam';
    #[SchemaPropertyInfo(rdfs_comment: 'The person\'s spouse.', schema_domain_includes: ['Person'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case spouse = 'spouse';
    #[SchemaPropertyInfo(rdfs_comment: 'The stage of the condition, if applicable.', schema_domain_includes: ['MedicalCondition'], range_includes: ['MedicalConditionStage', 'Role', 'Text', 'URL'], superseded_by: null)]
    case stage = 'stage';
    #[SchemaPropertyInfo(rdfs_comment: 'The stage represented as a number, e.g. 3.', schema_domain_includes: ['MedicalConditionStage'], range_includes: ['Number'], superseded_by: null)]
    case stageAsNumber = 'stageAsNumber';
    #[SchemaPropertyInfo(rdfs_comment: 'An official rating for a lodging business or food establishment, e.g. from national associations or standards bodies. Use the author property to indicate the rating organization, e.g. as an Organization with name such as (e.g. HOTREC, DEHOGA, WHR, or Hotelstars).', schema_domain_includes: ['FoodEstablishment', 'LodgingBusiness'], range_includes: ['Rating', 'Role', 'Text', 'URL'], superseded_by: null)]
    case starRating = 'starRating';
    #[SchemaPropertyInfo(rdfs_comment: 'The start date and time of the item (in <a href="http://en.wikipedia.org/wiki/ISO_8601" target="_blank" rel="noopener noreferrer">ISO 8601 date format</a>).', schema_domain_includes: ['CreativeWorkSeason', 'CreativeWorkSeries', 'DatedMoneySpecification', 'EducationalOccupationalProgram', 'Event', 'MerchantReturnPolicySeasonalOverride', 'Role', 'Schedule'], range_includes: ['Date', 'DateTime'], superseded_by: null)]
    case startDate = 'startDate';
    #[SchemaPropertyInfo(rdfs_comment: 'The start time of the clip expressed as the number of seconds from the beginning of the work.', schema_domain_includes: ['Clip', 'SeekToAction'], range_includes: ['HyperTocEntry', 'Number', 'Role', 'Text', 'URL'], superseded_by: null)]
    case startOffset = 'startOffset';
    #[SchemaPropertyInfo(rdfs_comment: 'The startTime of something. For a reserved event or service (e.g. FoodEstablishmentReservation), the time that it is expected to start. For actions that span a period of time, when the action was performed. E.g. John wrote a book from *January* to December. For media, including audio and video, it\'s the time offset of the start of a clip within a larger file.\\n\\nNote that Event uses startDate/endDate instead of startTime/endTime, even when describing dates with times. This situation may be clarified in future revisions.', schema_domain_includes: ['Action', 'FoodEstablishmentReservation', 'InteractionCounter', 'MediaObject', 'Schedule'], range_includes: ['DateTime', 'Time'], superseded_by: null)]
    case startTime = 'startTime';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates the kind of statistic represented by a <a href="https://schema.org/StatisticalVariable" target="_blank" rel="noopener noreferrer">StatisticalVariable</a>, e.g. mean, count etc. The value of statType is a property, either from within Schema.org (e.g. <a href="https://schema.org/median" target="_blank" rel="noopener noreferrer">median</a>, <a href="https://schema.org/marginOfError" target="_blank" rel="noopener noreferrer">marginOfError</a>, <a href="https://schema.org/maxValue" target="_blank" rel="noopener noreferrer">maxValue</a>, <a href="https://schema.org/minValue" target="_blank" rel="noopener noreferrer">minValue</a>) or from other compatible (e.g. RDF) systems such as DataCommons.org or Wikidata.org.', schema_domain_includes: ['StatisticalVariable'], range_includes: ['Property', 'Role', 'Text', 'URL'], superseded_by: null)]
    case statType = 'statType';
    #[SchemaPropertyInfo(rdfs_comment: 'The status of the study (enumerated).', schema_domain_includes: ['MedicalCondition', 'MedicalProcedure', 'MedicalStudy'], range_includes: ['EventStatusType', 'MedicalStudyStatus', 'Role', 'Text', 'URL'], superseded_by: null)]
    case status = 'status';
    #[SchemaPropertyInfo(rdfs_comment: 'The position of the steering wheel or similar device (mostly for cars).', schema_domain_includes: ['Vehicle'], range_includes: ['Role', 'SteeringPositionValue', 'Text', 'URL'], superseded_by: null)]
    case steeringPosition = 'steeringPosition';
    #[SchemaPropertyInfo(rdfs_comment: 'A single step item (as HowToStep, text, document, video, etc.) or a HowToSection.', schema_domain_includes: ['HowTo'], range_includes: ['CreativeWork', 'HowToSection', 'HowToStep', 'Role', 'Text', 'URL'], superseded_by: null)]
    case step = 'step';
    #[SchemaPropertyInfo(rdfs_comment: 'The stepValue attribute indicates the granularity that is expected (and required) of the value in a PropertyValueSpecification.', schema_domain_includes: ['PropertyValueSpecification'], range_includes: ['Number'], superseded_by: null)]
    case stepValue = 'stepValue';
    #[SchemaPropertyInfo(rdfs_comment: 'A single step item (as HowToStep, text, document, video, etc.) or a HowToSection (originally misnamed \'steps\'; \'step\' is preferred).', schema_domain_includes: ['HowTo', 'HowToSection'], range_includes: ['CreativeWork', 'ItemList', 'Role', 'Text', 'URL'], superseded_by: 'step')]
    case steps = 'steps';
    #[SchemaPropertyInfo(rdfs_comment: 'Storage requirements (free space required).', schema_domain_includes: ['SoftwareApplication'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case storageRequirements = 'storageRequirements';
    #[SchemaPropertyInfo(rdfs_comment: 'The street address. For example, 1600 Amphitheatre Pkwy.', schema_domain_includes: ['PostalAddress'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case streetAddress = 'streetAddress';
    #[SchemaPropertyInfo(rdfs_comment: 'The units of an active ingredient\'s strength, e.g. mg.', schema_domain_includes: ['DrugStrength'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case strengthUnit = 'strengthUnit';
    #[SchemaPropertyInfo(rdfs_comment: 'The value of an active ingredient\'s strength, e.g. 325.', schema_domain_includes: ['DrugStrength'], range_includes: ['Number'], superseded_by: null)]
    case strengthValue = 'strengthValue';
    #[SchemaPropertyInfo(rdfs_comment: 'The name given to how bone physically connects to each other.', schema_domain_includes: ['Joint'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case structuralClass = 'structuralClass';
    #[SchemaPropertyInfo(rdfs_comment: 'A medical study or trial related to this entity.', schema_domain_includes: ['MedicalEntity'], range_includes: ['MedicalStudy', 'Role', 'Text', 'URL'], superseded_by: null)]
    case study = 'study';
    #[SchemaPropertyInfo(rdfs_comment: 'Specifics about the observational study design (enumerated).', schema_domain_includes: ['MedicalObservationalStudy'], range_includes: ['MedicalObservationalStudyDesign', 'Role', 'Text', 'URL'], superseded_by: null)]
    case studyDesign = 'studyDesign';
    #[SchemaPropertyInfo(rdfs_comment: 'The location in which the study is taking/took place.', schema_domain_includes: ['MedicalStudy'], range_includes: ['AdministrativeArea', 'Role', 'Text', 'URL'], superseded_by: null)]
    case studyLocation = 'studyLocation';
    #[SchemaPropertyInfo(rdfs_comment: 'A subject of the study, i.e. one of the medical conditions, therapies, devices, drugs, etc. investigated by the study.', schema_domain_includes: ['MedicalStudy'], range_includes: ['MedicalEntity', 'Role', 'Text', 'URL'], superseded_by: null)]
    case studySubject = 'studySubject';
    #[SchemaPropertyInfo(rdfs_comment: 'An Event that is part of this event. For example, a conference event includes many presentations, each of which is a subEvent of the conference.', schema_domain_includes: ['Event'], range_includes: ['Event', 'Role', 'Text', 'URL'], superseded_by: null)]
    case subEvent = 'subEvent';
    #[SchemaPropertyInfo(rdfs_comment: 'Events that are a part of this event. For example, a conference event includes many presentations, each subEvents of the conference.', schema_domain_includes: ['Event'], range_includes: ['Event', 'Role', 'Text', 'URL'], superseded_by: 'subEvent')]
    case subEvents = 'subEvents';
    #[SchemaPropertyInfo(rdfs_comment: 'A relationship between two organizations where the first includes the second, e.g., as a subsidiary. See also: the more specific \'department\' property.', schema_domain_includes: ['Organization'], range_includes: ['Organization', 'Role', 'Text', 'URL'], superseded_by: null)]
    case subOrganization = 'subOrganization';
    #[SchemaPropertyInfo(rdfs_comment: 'The individual reservations included in the package. Typically a repeated property.', schema_domain_includes: ['ReservationPackage'], range_includes: ['Reservation', 'Role', 'Text', 'URL'], superseded_by: null)]
    case subReservation = 'subReservation';
    #[SchemaPropertyInfo(rdfs_comment: 'The substage, e.g. \'a\' for Stage IIIa.', schema_domain_includes: ['MedicalConditionStage'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case subStageSuffix = 'subStageSuffix';
    #[SchemaPropertyInfo(rdfs_comment: 'Component (sub-)structure(s) that comprise this anatomical structure.', schema_domain_includes: ['AnatomicalStructure'], range_includes: ['AnatomicalStructure', 'Role', 'Text', 'URL'], superseded_by: null)]
    case subStructure = 'subStructure';
    #[SchemaPropertyInfo(rdfs_comment: 'A component test of the panel.', schema_domain_includes: ['MedicalTestPanel'], range_includes: ['MedicalTest', 'Role', 'Text', 'URL'], superseded_by: null)]
    case subTest = 'subTest';
    #[SchemaPropertyInfo(rdfs_comment: 'Identifies a <a href="https://schema.org/Trip" target="_blank" rel="noopener noreferrer">Trip</a> that is a subTrip of this Trip.  For example Day 1, Day 2, etc. of a multi-day trip.', schema_domain_includes: ['Trip'], range_includes: ['Role', 'Text', 'Trip', 'URL'], superseded_by: null)]
    case subTrip = 'subTrip';
    #[SchemaPropertyInfo(rdfs_comment: 'A CreativeWork or Event about this Thing.', schema_domain_includes: ['Thing'], range_includes: ['CreativeWork', 'Event', 'Role', 'Text', 'URL'], superseded_by: null)]
    case subjectOf = 'subjectOf';
    #[SchemaPropertyInfo(rdfs_comment: 'Languages in which subtitles/captions are available, in <a href="http://tools.ietf.org/html/bcp47" target="_blank" rel="noopener noreferrer">IETF BCP 47 standard format</a>.', schema_domain_includes: ['BroadcastEvent', 'Movie', 'ScreeningEvent', 'TVEpisode'], range_includes: ['Language', 'Role', 'Text', 'URL'], superseded_by: null)]
    case subtitleLanguage = 'subtitleLanguage';
    #[SchemaPropertyInfo(rdfs_comment: 'A pointer from a newer variant of a product  to its previous, often discontinued predecessor.', schema_domain_includes: ['ProductModel'], range_includes: ['ProductModel', 'Role', 'Text', 'URL'], superseded_by: null)]
    case successorOf = 'successorOf';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of grams of sugar.', schema_domain_includes: ['NutritionInformation'], range_includes: ['Mass', 'Role', 'Text', 'URL'], superseded_by: null)]
    case sugarContent = 'sugarContent';
    #[SchemaPropertyInfo(rdfs_comment: 'The age or age range for the intended audience or person, for example 3-12 months for infants, 1-5 years for toddlers.', schema_domain_includes: ['PeopleAudience', 'SizeSpecification'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case suggestedAge = 'suggestedAge';
    #[SchemaPropertyInfo(rdfs_comment: 'An answer (possibly one of several, possibly incorrect) to a Question, e.g. on a Question/Answer site.', schema_domain_includes: ['Question'], range_includes: ['Answer', 'ItemList', 'Role', 'Text', 'URL'], superseded_by: null)]
    case suggestedAnswer = 'suggestedAnswer';
    #[SchemaPropertyInfo(rdfs_comment: 'The suggested gender of the intended person or audience, for example "male", "female", or "unisex".', schema_domain_includes: ['PeopleAudience', 'SizeSpecification'], range_includes: ['GenderType', 'Role', 'Text', 'URL'], superseded_by: null)]
    case suggestedGender = 'suggestedGender';
    #[SchemaPropertyInfo(rdfs_comment: 'Maximum recommended age in years for the audience or user.', schema_domain_includes: ['PeopleAudience'], range_includes: ['Number'], superseded_by: null)]
    case suggestedMaxAge = 'suggestedMaxAge';
    #[SchemaPropertyInfo(rdfs_comment: 'A suggested range of body measurements for the intended audience or person, for example inseam between 32 and 34 inches or height between 170 and 190 cm. Typically found on a size chart for wearable products.', schema_domain_includes: ['PeopleAudience', 'SizeSpecification'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case suggestedMeasurement = 'suggestedMeasurement';
    #[SchemaPropertyInfo(rdfs_comment: 'Minimum recommended age in years for the audience or user.', schema_domain_includes: ['PeopleAudience'], range_includes: ['Number'], superseded_by: null)]
    case suggestedMinAge = 'suggestedMinAge';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates a dietary restriction or guideline for which this recipe or menu item is suitable, e.g. diabetic, halal etc.', schema_domain_includes: ['MenuItem', 'Recipe'], range_includes: ['Diet', 'RestrictedDiet', 'Role', 'Text', 'URL'], superseded_by: null)]
    case suitableForDiet = 'suitableForDiet';
    #[SchemaPropertyInfo(rdfs_comment: 'An event that this event is a part of. For example, a collection of individual music performances might each have a music festival as their superEvent.', schema_domain_includes: ['Event'], range_includes: ['Event', 'Role', 'Text', 'URL'], superseded_by: null)]
    case superEvent = 'superEvent';
    #[SchemaPropertyInfo(rdfs_comment: 'Relates a term (i.e. a property, class or enumeration) to one that supersedes it.', schema_domain_includes: ['Class', 'Enumeration', 'Property'], range_includes: ['Class', 'Enumeration', 'Property', 'Role', 'Text', 'URL'], superseded_by: null)]
    case supersededBy = 'supersededBy';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub-property of instrument. A supply consumed when performing instructions or a direction.', schema_domain_includes: ['HowTo', 'HowToDirection'], range_includes: ['HowToSupply', 'Role', 'Text', 'URL'], superseded_by: null)]
    case supply = 'supply';
    #[SchemaPropertyInfo(rdfs_comment: 'The area to which the artery supplies blood.', schema_domain_includes: ['Artery'], range_includes: ['AnatomicalStructure', 'Role', 'Text', 'URL'], superseded_by: null)]
    case supplyTo = 'supplyTo';
    #[SchemaPropertyInfo(rdfs_comment: 'Supporting data for a SoftwareApplication.', schema_domain_includes: ['SoftwareApplication'], range_includes: ['DataFeed', 'Role', 'Text', 'URL'], superseded_by: null)]
    case supportingData = 'supportingData';
    #[SchemaPropertyInfo(rdfs_comment: 'A material used as a surface in some artwork, e.g. Canvas, Paper, Wood, Board, etc.', schema_domain_includes: ['VisualArtwork'], range_includes: ['Role', 'Text', 'URL'], superseded_by: 'artworkSurface')]
    case surface = 'surface';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates (typically several) Syllabus entities that lay out what each section of the overall course will cover.', schema_domain_includes: ['Course'], range_includes: ['Role', 'Syllabus', 'Text', 'URL'], superseded_by: null)]
    case syllabusSections = 'syllabusSections';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates a target EntryPoint, or url, for an Action.', schema_domain_includes: ['Action'], range_includes: ['EntryPoint', 'Role', 'Text', 'URL'], superseded_by: null)]
    case target = 'target';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of object. The collection target of the action.', schema_domain_includes: ['UpdateAction'], range_includes: ['Role', 'Text', 'Thing', 'URL'], superseded_by: null)]
    case targetCollection = 'targetCollection';
    #[SchemaPropertyInfo(rdfs_comment: 'The description of a node in an established educational framework.', schema_domain_includes: ['AlignmentObject'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case targetDescription = 'targetDescription';
    #[SchemaPropertyInfo(rdfs_comment: 'The name of a node in an established educational framework.', schema_domain_includes: ['AlignmentObject'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case targetName = 'targetName';
    #[SchemaPropertyInfo(rdfs_comment: 'Type of app development: phone, Metro style, desktop, XBox, etc.', schema_domain_includes: ['APIReference'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case targetPlatform = 'targetPlatform';
    #[SchemaPropertyInfo(rdfs_comment: 'Characteristics of the population for which this is intended, or which typically uses it, e.g. \'adults\'.', schema_domain_includes: ['DietarySupplement', 'DoseSchedule'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case targetPopulation = 'targetPopulation';
    #[SchemaPropertyInfo(rdfs_comment: 'Target Operating System / Product to which the code applies.  If applies to several versions, just the product name can be used.', schema_domain_includes: ['SoftwareSourceCode'], range_includes: ['Role', 'SoftwareApplication', 'Text', 'URL'], superseded_by: null)]
    case targetProduct = 'targetProduct';
    #[SchemaPropertyInfo(rdfs_comment: 'The URL of a node in an established educational framework.', schema_domain_includes: ['AlignmentObject'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case targetUrl = 'targetUrl';
    #[SchemaPropertyInfo(rdfs_comment: 'The Tax / Fiscal ID of the organization or person, e.g. the TIN in the US or the CIF/NIF in Spain.', schema_domain_includes: ['Organization', 'Person'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case taxID = 'taxID';
    #[SchemaPropertyInfo(rdfs_comment: 'The taxonomic rank of this taxon given preferably as a URI from a controlled vocabulary – typically the ranks from TDWG TaxonRank ontology or equivalent Wikidata URIs.', schema_domain_includes: ['Taxon'], range_includes: ['PropertyValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case taxonRank = 'taxonRank';
    #[SchemaPropertyInfo(rdfs_comment: 'The taxonomic grouping of the organism that expresses, encodes, or in some way related to the BioChemEntity.', schema_domain_includes: ['BioChemEntity'], range_includes: ['DefinedTerm', 'Role', 'Taxon', 'Text', 'URL'], superseded_by: null)]
    case taxonomicRange = 'taxonomicRange';
    #[SchemaPropertyInfo(rdfs_comment: 'The item being described is intended to help a person learn the competency or learning outcome defined by the referenced term.', schema_domain_includes: ['CreativeWork', 'EducationEvent', 'LearningResource'], range_includes: ['DefinedTerm', 'Role', 'Text', 'URL'], superseded_by: null)]
    case teaches = 'teaches';
    #[SchemaPropertyInfo(rdfs_comment: 'The telephone number.', schema_domain_includes: ['ContactPoint', 'Organization', 'Person', 'Place'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case telephone = 'telephone';
    #[SchemaPropertyInfo(rdfs_comment: 'The "temporal" property can be used in cases where more specific properties
(e.g. <a href="https://schema.org/temporalCoverage" target="_blank" rel="noopener noreferrer">temporalCoverage</a>, <a href="https://schema.org/dateCreated" target="_blank" rel="noopener noreferrer">dateCreated</a>, <a href="https://schema.org/dateModified" target="_blank" rel="noopener noreferrer">dateModified</a>, <a href="https://schema.org/datePublished" target="_blank" rel="noopener noreferrer">datePublished</a>) are not known to be appropriate.', schema_domain_includes: ['CreativeWork'], range_includes: ['DateTime', 'Role', 'Text', 'URL'], superseded_by: null)]
    case temporal = 'temporal';
    #[SchemaPropertyInfo(rdfs_comment: 'The temporalCoverage of a CreativeWork indicates the period that the content applies to, i.e. that it describes, either as a DateTime or as a textual string indicating a time period in <a href="https://en.wikipedia.org/wiki/ISO_8601#Time_intervals" target="_blank" rel="noopener noreferrer">ISO 8601 time interval format</a>. In
      the case of a Dataset it will typically indicate the relevant time period in a precise notation (e.g. for a 2011 census dataset, the year 2011 would be written "2011/2012"). Other forms of content, e.g. ScholarlyArticle, Book, TVSeries or TVEpisode, may indicate their temporalCoverage in broader terms - textually or via well-known URL.
      Written works such as books may sometimes have precise temporal coverage too, e.g. a work set in 1939 - 1945 can be indicated in ISO 8601 interval format format via "1939/1945".

Open-ended date ranges can be written with ".." in place of the end date. For example, "2015-11/.." indicates a range beginning in November 2015 and with no specified final date. This is tentative and might be updated in future when ISO 8601 is officially updated.', schema_domain_includes: ['CreativeWork'], range_includes: ['DateTime', 'Role', 'Text', 'URL'], superseded_by: null)]
    case temporalCoverage = 'temporalCoverage';
    #[SchemaPropertyInfo(rdfs_comment: 'A code that identifies this <a href="https://schema.org/DefinedTerm" target="_blank" rel="noopener noreferrer">DefinedTerm</a> within a <a href="https://schema.org/DefinedTermSet" target="_blank" rel="noopener noreferrer">DefinedTermSet</a>.', schema_domain_includes: ['DefinedTerm'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case termCode = 'termCode';
    #[SchemaPropertyInfo(rdfs_comment: 'The amount of time in a term as defined by the institution. A term is a length of time where students take one or more classes. Semesters and quarters are common units for term.', schema_domain_includes: ['EducationalOccupationalProgram'], range_includes: ['Duration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case termDuration = 'termDuration';
    #[SchemaPropertyInfo(rdfs_comment: 'Human-readable terms of service documentation.', schema_domain_includes: ['Service'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case termsOfService = 'termsOfService';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of times terms of study are offered per year. Semesters and quarters are common units for term. For example, if the student can only take 2 semesters for the program in one year, then termsPerYear should be 2.', schema_domain_includes: ['EducationalOccupationalProgram'], range_includes: ['Number'], superseded_by: null)]
    case termsPerYear = 'termsPerYear';
    #[SchemaPropertyInfo(rdfs_comment: 'The textual content of this CreativeWork.', schema_domain_includes: ['CreativeWork'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case text = 'text';
    #[SchemaPropertyInfo(rdfs_comment: 'Text value being annotated.', schema_domain_includes: ['PronounceableText'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case textValue = 'textValue';
    #[SchemaPropertyInfo(rdfs_comment: 'Thumbnail image for an image or video.', schema_domain_includes: ['CreativeWork'], range_includes: ['ImageObject', 'Role', 'Text', 'URL'], superseded_by: null)]
    case thumbnail = 'thumbnail';
    #[SchemaPropertyInfo(rdfs_comment: 'A thumbnail image relevant to the Thing.', schema_domain_includes: ['CreativeWork'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case thumbnailUrl = 'thumbnailUrl';
    #[SchemaPropertyInfo(rdfs_comment: 'The exchange traded instrument associated with a Corporation object. The tickerSymbol is expressed as an exchange and an instrument name separated by a space character. For the exchange component of the tickerSymbol attribute, we recommend using the controlled vocabulary of Market Identifier Codes (MIC) specified in ISO 15022.', schema_domain_includes: ['Corporation'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case tickerSymbol = 'tickerSymbol';
    #[SchemaPropertyInfo(rdfs_comment: 'The unique identifier for the ticket.', schema_domain_includes: ['Ticket'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case ticketNumber = 'ticketNumber';
    #[SchemaPropertyInfo(rdfs_comment: 'Reference to an asset (e.g., Barcode, QR code image or PDF) usable for entrance.', schema_domain_includes: ['Ticket'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case ticketToken = 'ticketToken';
    #[SchemaPropertyInfo(rdfs_comment: 'The seat associated with the ticket.', schema_domain_includes: ['Ticket'], range_includes: ['Role', 'Seat', 'Text', 'URL'], superseded_by: null)]
    case ticketedSeat = 'ticketedSeat';
    #[SchemaPropertyInfo(rdfs_comment: 'The time of day the program normally runs. For example, "evenings".', schema_domain_includes: ['EducationalOccupationalProgram'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case timeOfDay = 'timeOfDay';
    #[SchemaPropertyInfo(rdfs_comment: 'Approximate or typical time it usually takes to work with or through the content of this work for the typical or target audience.', schema_domain_includes: ['CreativeWork'], range_includes: ['Duration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case timeRequired = 'timeRequired';
    #[SchemaPropertyInfo(rdfs_comment: 'The expected length of time to complete the program if attending full-time.', schema_domain_includes: ['EducationalOccupationalProgram'], range_includes: ['Duration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case timeToComplete = 'timeToComplete';
    #[SchemaPropertyInfo(rdfs_comment: 'The instant the event occured.', schema_domain_includes: ['InstantaneousEvent'], range_includes: ['DateTime'], superseded_by: null)]
    case timestamp = 'timestamp';
    #[SchemaPropertyInfo(rdfs_comment: 'The type of tissue sample required for the test.', schema_domain_includes: ['PathologyTest'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case tissueSample = 'tissueSample';
    #[SchemaPropertyInfo(rdfs_comment: 'The title of the job.', schema_domain_includes: ['JobPosting'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case title = 'title';
    #[SchemaPropertyInfo(rdfs_comment: 'An <a href="https://eidr.org/" target="_blank" rel="noopener noreferrer">EIDR</a> (Entertainment Identifier Registry) <a href="https://schema.org/identifier" target="_blank" rel="noopener noreferrer">identifier</a> representing at the most general/abstract level, a work of film or television.

For example, the motion picture known as "Ghostbusters" has a titleEIDR of  "10.5240/7EC7-228A-510A-053E-CBB8-J". This title (or work) may have several variants, which EIDR calls "edits". See <a href="https://schema.org/editEIDR" target="_blank" rel="noopener noreferrer">editEIDR</a>.

Since schema.org types like <a href="https://schema.org/Movie" target="_blank" rel="noopener noreferrer">Movie</a>, <a href="https://schema.org/TVEpisode" target="_blank" rel="noopener noreferrer">TVEpisode</a>, <a href="https://schema.org/TVSeason" target="_blank" rel="noopener noreferrer">TVSeason</a>, and <a href="https://schema.org/TVSeries" target="_blank" rel="noopener noreferrer">TVSeries</a> can be used for both works and their multiple expressions, it is possible to use <a href="https://schema.org/titleEIDR" target="_blank" rel="noopener noreferrer">titleEIDR</a> alone (for a general description), or alongside <a href="https://schema.org/editEIDR" target="_blank" rel="noopener noreferrer">editEIDR</a> for a more edit-specific description.', schema_domain_includes: ['Movie', 'TVEpisode', 'TVSeason', 'TVSeries'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case titleEIDR = 'titleEIDR';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of location. The final location of the object or the agent after the action.', schema_domain_includes: ['ExerciseAction', 'InsertAction', 'MoveAction', 'TransferAction'], range_includes: ['Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case toLocation = 'toLocation';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of recipient. The recipient who was directly sent the message.', schema_domain_includes: ['Message'], range_includes: ['Audience', 'ContactPoint', 'Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case toRecipient = 'toRecipient';
    #[SchemaPropertyInfo(rdfs_comment: 'A <a href="https://schema.org/HyperTocEntry" target="_blank" rel="noopener noreferrer">HyperTocEntry</a> can have a <a href="https://schema.org/tocContinuation" target="_blank" rel="noopener noreferrer">tocContinuation</a> indicated, which is another <a href="https://schema.org/HyperTocEntry" target="_blank" rel="noopener noreferrer">HyperTocEntry</a> that would be the default next item to play or render.', schema_domain_includes: ['HyperTocEntry'], range_includes: ['HyperTocEntry', 'Role', 'Text', 'URL'], superseded_by: null)]
    case tocContinuation = 'tocContinuation';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates a <a href="https://schema.org/HyperTocEntry" target="_blank" rel="noopener noreferrer">HyperTocEntry</a> in a <a href="https://schema.org/HyperToc" target="_blank" rel="noopener noreferrer">HyperToc</a>.', schema_domain_includes: ['HyperToc'], range_includes: ['HyperTocEntry', 'Role', 'Text', 'URL'], superseded_by: null)]
    case tocEntry = 'tocEntry';
    #[SchemaPropertyInfo(rdfs_comment: 'The permitted vertical load (TWR) of a trailer attached to the vehicle. Also referred to as Tongue Load Rating (TLR) or Vertical Load Rating (VLR).\\n\\nTypical unit code(s): KGM for kilogram, LBR for pound\\n\\n* Note 1: You can indicate additional information in the <a href="https://schema.org/name" target="_blank" rel="noopener noreferrer">name</a> of the <a href="https://schema.org/QuantitativeValue" target="_blank" rel="noopener noreferrer">QuantitativeValue</a> node.\\n* Note 2: You may also link to a <a href="https://schema.org/QualitativeValue" target="_blank" rel="noopener noreferrer">QualitativeValue</a> node that provides additional information using <a href="https://schema.org/valueReference" target="_blank" rel="noopener noreferrer">valueReference</a>.\\n* Note 3: Note that you can use <a href="https://schema.org/minValue" target="_blank" rel="noopener noreferrer">minValue</a> and <a href="https://schema.org/maxValue" target="_blank" rel="noopener noreferrer">maxValue</a> to indicate ranges.', schema_domain_includes: ['Vehicle'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case tongueWeight = 'tongueWeight';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of instrument. An object used (but not consumed) when performing instructions or a direction.', schema_domain_includes: ['HowTo', 'HowToDirection'], range_includes: ['HowToTool', 'Role', 'Text', 'URL'], superseded_by: null)]
    case tool = 'tool';
    #[SchemaPropertyInfo(rdfs_comment: 'The torque (turning force) of the vehicle\'s engine.\\n\\nTypical unit code(s): NU for newton metre (N m), F17 for pound-force per foot, or F48 for pound-force per inch\\n\\n* Note 1: You can link to information about how the given value has been determined (e.g. reference RPM) using the <a href="https://schema.org/valueReference" target="_blank" rel="noopener noreferrer">valueReference</a> property.\\n* Note 2: You can use <a href="https://schema.org/minValue" target="_blank" rel="noopener noreferrer">minValue</a> and <a href="https://schema.org/maxValue" target="_blank" rel="noopener noreferrer">maxValue</a> to indicate ranges.', schema_domain_includes: ['EngineSpecification'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case torque = 'torque';
    #[SchemaPropertyInfo(rdfs_comment: 'The total number of students that have enrolled in the history of the course.', schema_domain_includes: ['Course'], range_includes: ['Integer'], superseded_by: null)]
    case totalHistoricalEnrollment = 'totalHistoricalEnrollment';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of positions open for this job posting. Use a positive integer. Do not use if the number of positions is unclear or not known.', schema_domain_includes: ['JobPosting'], range_includes: ['Integer'], superseded_by: null)]
    case totalJobOpenings = 'totalJobOpenings';
    #[SchemaPropertyInfo(rdfs_comment: 'The total amount due.', schema_domain_includes: ['Invoice'], range_includes: ['MonetaryAmount', 'PriceSpecification', 'Role', 'Text', 'URL'], superseded_by: null)]
    case totalPaymentDue = 'totalPaymentDue';
    #[SchemaPropertyInfo(rdfs_comment: 'The total price for the reservation or ticket, including applicable taxes, shipping, etc.\\n\\nUsage guidelines:\\n\\n* Use values from 0123456789 (Unicode \'DIGIT ZERO\' (U+0030) to \'DIGIT NINE\' (U+0039)) rather than superficially similar Unicode symbols.\\n* Use \'.\' (Unicode \'FULL STOP\' (U+002E)) rather than \',\' to indicate a decimal point. Avoid using these symbols as a readability separator.', schema_domain_includes: ['Reservation', 'Ticket'], range_includes: ['Number', 'PriceSpecification', 'Role', 'Text', 'URL'], superseded_by: null)]
    case totalPrice = 'totalPrice';
    #[SchemaPropertyInfo(rdfs_comment: 'The total time required to perform instructions or a direction (including time to prepare the supplies), in <a href="http://en.wikipedia.org/wiki/ISO_8601" target="_blank" rel="noopener noreferrer">ISO 8601 duration format</a>.', schema_domain_includes: ['HowTo', 'HowToDirection'], range_includes: ['Duration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case totalTime = 'totalTime';
    #[SchemaPropertyInfo(rdfs_comment: 'A page providing information on how to book a tour of some <a href="https://schema.org/Place" target="_blank" rel="noopener noreferrer">Place</a>, such as an <a href="https://schema.org/Accommodation" target="_blank" rel="noopener noreferrer">Accommodation</a> or <a href="https://schema.org/ApartmentComplex" target="_blank" rel="noopener noreferrer">ApartmentComplex</a> in a real estate setting, as well as other kinds of tours as appropriate.', schema_domain_includes: ['Accommodation', 'ApartmentComplex', 'Place'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case tourBookingPage = 'tourBookingPage';
    #[SchemaPropertyInfo(rdfs_comment: 'Attraction suitable for type(s) of tourist. E.g. children, visitors from a particular country, etc.', schema_domain_includes: ['TouristAttraction', 'TouristDestination', 'TouristTrip'], range_includes: ['Audience', 'Role', 'Text', 'URL'], superseded_by: null)]
    case touristType = 'touristType';
    #[SchemaPropertyInfo(rdfs_comment: 'A music recording (track)—usually a single song. If an ItemList is given, the list should contain items of type MusicRecording.', schema_domain_includes: ['MusicGroup', 'MusicPlaylist'], range_includes: ['ItemList', 'MusicRecording', 'Role', 'Text', 'URL'], superseded_by: null)]
    case track = 'track';
    #[SchemaPropertyInfo(rdfs_comment: 'Shipper tracking number.', schema_domain_includes: ['ParcelDelivery'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case trackingNumber = 'trackingNumber';
    #[SchemaPropertyInfo(rdfs_comment: 'Tracking url for the parcel delivery.', schema_domain_includes: ['ParcelDelivery'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case trackingUrl = 'trackingUrl';
    #[SchemaPropertyInfo(rdfs_comment: 'A music recording (track)—usually a single song.', schema_domain_includes: ['MusicGroup', 'MusicPlaylist'], range_includes: ['MusicRecording', 'Role', 'Text', 'URL'], superseded_by: 'track')]
    case tracks = 'tracks';
    #[SchemaPropertyInfo(rdfs_comment: 'The trailer of a movie or TV/radio series, season, episode, etc.', schema_domain_includes: ['CreativeWorkSeason', 'Episode', 'Movie', 'MovieSeries', 'RadioSeries', 'TVSeries', 'VideoGame', 'VideoGameSeries'], range_includes: ['Role', 'Text', 'URL', 'VideoObject'], superseded_by: null)]
    case trailer = 'trailer';
    #[SchemaPropertyInfo(rdfs_comment: 'The permitted weight of a trailer attached to the vehicle.\\n\\nTypical unit code(s): KGM for kilogram, LBR for pound\\n* Note 1: You can indicate additional information in the <a href="https://schema.org/name" target="_blank" rel="noopener noreferrer">name</a> of the <a href="https://schema.org/QuantitativeValue" target="_blank" rel="noopener noreferrer">QuantitativeValue</a> node.\\n* Note 2: You may also link to a <a href="https://schema.org/QualitativeValue" target="_blank" rel="noopener noreferrer">QualitativeValue</a> node that provides additional information using <a href="https://schema.org/valueReference" target="_blank" rel="noopener noreferrer">valueReference</a>.\\n* Note 3: Note that you can use <a href="https://schema.org/minValue" target="_blank" rel="noopener noreferrer">minValue</a> and <a href="https://schema.org/maxValue" target="_blank" rel="noopener noreferrer">maxValue</a> to indicate ranges.', schema_domain_includes: ['Vehicle'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case trailerWeight = 'trailerWeight';
    #[SchemaPropertyInfo(rdfs_comment: 'The name of the train (e.g. The Orient Express).', schema_domain_includes: ['TrainTrip'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case trainName = 'trainName';
    #[SchemaPropertyInfo(rdfs_comment: 'The unique identifier for the train.', schema_domain_includes: ['TrainTrip'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case trainNumber = 'trainNumber';
    #[SchemaPropertyInfo(rdfs_comment: 'The estimated salary earned while in the program.', schema_domain_includes: ['EducationalOccupationalProgram', 'WorkBasedProgram'], range_includes: ['MonetaryAmountDistribution', 'Role', 'Text', 'URL'], superseded_by: null)]
    case trainingSalary = 'trainingSalary';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of grams of trans fat.', schema_domain_includes: ['NutritionInformation'], range_includes: ['Mass', 'Role', 'Text', 'URL'], superseded_by: null)]
    case transFatContent = 'transFatContent';
    #[SchemaPropertyInfo(rdfs_comment: 'If this MediaObject is an AudioObject or VideoObject, the transcript of that object.', schema_domain_includes: ['AudioObject', 'VideoObject'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case transcript = 'transcript';
    #[SchemaPropertyInfo(rdfs_comment: 'The typical delay the order has been sent for delivery and the goods reach the final customer.

  In the context of <a href="https://schema.org/ShippingDeliveryTime" target="_blank" rel="noopener noreferrer">ShippingDeliveryTime</a>, use the <a href="https://schema.org/QuantitativeValue" target="_blank" rel="noopener noreferrer">QuantitativeValue</a>. Typical properties: minValue, maxValue, unitCode (d for DAY).

  In the context of <a href="https://schema.org/ShippingConditions" target="_blank" rel="noopener noreferrer">ShippingConditions</a>, use the <a href="https://schema.org/ServicePeriod" target="_blank" rel="noopener noreferrer">ServicePeriod</a>. It has a duration (as a <a href="https://schema.org/QuantitativeValue" target="_blank" rel="noopener noreferrer">QuantitativeValue</a>) and also business days and a cut-off time.', schema_domain_includes: ['ShippingConditions', 'ShippingDeliveryTime'], range_includes: ['QuantitativeValue', 'Role', 'ServicePeriod', 'Text', 'URL'], superseded_by: null)]
    case transitTime = 'transitTime';
    #[SchemaPropertyInfo(rdfs_comment: 'The work that this work has been translated from. E.g. 物种起源 is a translationOf “On the Origin of Species”.', schema_domain_includes: ['CreativeWork'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case translationOfWork = 'translationOfWork';
    #[SchemaPropertyInfo(rdfs_comment: 'Organization or person who adapts a creative work to different languages, regional differences and technical requirements of a target market, or that translates during some event.', schema_domain_includes: ['CreativeWork', 'Event', 'Role'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case translator = 'translator';
    #[SchemaPropertyInfo(rdfs_comment: 'How the disease spreads, either as a route or vector, for example \'direct contact\', \'Aedes aegypti\', etc.', schema_domain_includes: ['InfectiousDisease'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case transmissionMethod = 'transmissionMethod';
    #[SchemaPropertyInfo(rdfs_comment: 'Information about travel bans, e.g. in the context of a pandemic.', schema_domain_includes: ['SpecialAnnouncement'], range_includes: ['Role', 'Text', 'URL', 'WebContent'], superseded_by: null)]
    case travelBans = 'travelBans';
    #[SchemaPropertyInfo(rdfs_comment: 'Specifics about the trial design (enumerated).', schema_domain_includes: ['MedicalTrial'], range_includes: ['MedicalTrialDesign', 'Role', 'Text', 'URL'], superseded_by: null)]
    case trialDesign = 'trialDesign';
    #[SchemaPropertyInfo(rdfs_comment: 'The anatomical or organ system that the vein flows into; a larger structure that the vein connects to.', schema_domain_includes: ['Vein'], range_includes: ['AnatomicalStructure', 'Role', 'Text', 'URL'], superseded_by: null)]
    case tributary = 'tributary';
    #[SchemaPropertyInfo(rdfs_comment: 'The location of origin of the trip, prior to any destination(s).', schema_domain_includes: ['Trip'], range_includes: ['Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case tripOrigin = 'tripOrigin';
    #[SchemaPropertyInfo(rdfs_comment: 'The type of bed to which the BedDetail refers, i.e. the type of bed available in the quantity indicated by quantity.', schema_domain_includes: ['BedDetails'], range_includes: ['BedType', 'Role', 'Text', 'URL'], superseded_by: null)]
    case typeOfBed = 'typeOfBed';
    #[SchemaPropertyInfo(rdfs_comment: 'The product that this structured value is referring to.', schema_domain_includes: ['OwnershipInfo', 'TypeAndQuantityNode'], range_includes: ['Product', 'Role', 'Service', 'Text', 'URL'], superseded_by: null)]
    case typeOfGood = 'typeOfGood';
    #[SchemaPropertyInfo(rdfs_comment: 'The typical expected age range, e.g. \'7-9\', \'11-\'.', schema_domain_includes: ['CreativeWork', 'Event'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case typicalAgeRange = 'typicalAgeRange';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of credits or units a full-time student would be expected to take in 1 term however \'term\' is defined by the institution.', schema_domain_includes: ['EducationalOccupationalProgram'], range_includes: ['Integer', 'Role', 'StructuredValue', 'Text', 'URL'], superseded_by: null)]
    case typicalCreditsPerTerm = 'typicalCreditsPerTerm';
    #[SchemaPropertyInfo(rdfs_comment: 'A medical test typically performed given this condition.', schema_domain_includes: ['MedicalCondition'], range_includes: ['MedicalTest', 'Role', 'Text', 'URL'], superseded_by: null)]
    case typicalTest = 'typicalTest';
    #[SchemaPropertyInfo(rdfs_comment: 'The person or organization the reservation or ticket is for.', schema_domain_includes: ['Reservation', 'Ticket'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case underName = 'underName';
    #[SchemaPropertyInfo(rdfs_comment: 'The unit of measurement given using the UN/CEFACT Common Code (3 characters) or a URL. Other codes than the UN/CEFACT Common Code may be used with a prefix followed by a colon.', schema_domain_includes: ['PropertyValue', 'QuantitativeValue', 'TypeAndQuantityNode', 'UnitPriceSpecification'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case unitCode = 'unitCode';
    #[SchemaPropertyInfo(rdfs_comment: 'A string or text indicating the unit of measurement. Useful if you cannot provide a standard unit code for
<a href=\'unitCode\'>unitCode</a>.', schema_domain_includes: ['PropertyValue', 'QuantitativeValue', 'TypeAndQuantityNode', 'UnitPriceSpecification'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case unitText = 'unitText';
    #[SchemaPropertyInfo(rdfs_comment: 'For an <a href="https://schema.org/Organization" target="_blank" rel="noopener noreferrer">Organization</a> (typically a <a href="https://schema.org/NewsMediaOrganization" target="_blank" rel="noopener noreferrer">NewsMediaOrganization</a>), a statement about policy on use of unnamed sources and the decision process required.', schema_domain_includes: ['NewsMediaOrganization', 'Organization'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case unnamedSourcesPolicy = 'unnamedSourcesPolicy';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of grams of unsaturated fat.', schema_domain_includes: ['NutritionInformation'], range_includes: ['Mass', 'Role', 'Text', 'URL'], superseded_by: null)]
    case unsaturatedFatContent = 'unsaturatedFatContent';
    #[SchemaPropertyInfo(rdfs_comment: 'Date (including time if available) when this media object was uploaded to this site.', schema_domain_includes: ['MediaObject'], range_includes: ['Date', 'DateTime'], superseded_by: null)]
    case uploadDate = 'uploadDate';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of upvotes this question, answer or comment has received from the community.', schema_domain_includes: ['Comment'], range_includes: ['Integer'], superseded_by: null)]
    case upvoteCount = 'upvoteCount';
    #[SchemaPropertyInfo(rdfs_comment: 'URL of the item.', schema_domain_includes: ['Thing'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case url = 'url';
    #[SchemaPropertyInfo(rdfs_comment: 'An url template (RFC6570) that will be used to construct the target of the execution of the action.', schema_domain_includes: ['EntryPoint'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case urlTemplate = 'urlTemplate';
    #[SchemaPropertyInfo(rdfs_comment: 'A <a href="https://en.wikipedia.org/wiki/National_Provider_Identifier">National Provider Identifier</a> (NPI) 
    is a unique 10-digit identification number issued to health care providers in the United States by the Centers for Medicare and Medicaid Services.', schema_domain_includes: ['Physician'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case usNPI = 'usNPI';
    #[SchemaPropertyInfo(rdfs_comment: 'The schema.org <a href="https://schema.org/usageInfo" target="_blank" rel="noopener noreferrer">usageInfo</a> property indicates further information about a <a href="https://schema.org/CreativeWork" target="_blank" rel="noopener noreferrer">CreativeWork</a>. This property is applicable both to works that are freely available and to those that require payment or other transactions. It can reference additional information, e.g. community expectations on preferred linking and citation conventions, as well as purchasing details. For something that can be commercially licensed, usageInfo can provide detailed, resource-specific information about licensing options.

This property can be used alongside the license property which indicates license(s) applicable to some piece of content. The usageInfo property can provide information about other licensing options, e.g. acquiring commercial usage rights for an image that is also available under non-commercial creative commons licenses.', schema_domain_includes: ['CreativeWork'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case usageInfo = 'usageInfo';
    #[SchemaPropertyInfo(rdfs_comment: 'A condition the test is used to diagnose.', schema_domain_includes: ['MedicalTest'], range_includes: ['MedicalCondition', 'Role', 'Text', 'URL'], superseded_by: null)]
    case usedToDiagnose = 'usedToDiagnose';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of interactions for the CreativeWork using the WebSite or SoftwareApplication.', schema_domain_includes: ['InteractionCounter'], range_includes: ['Integer'], superseded_by: null)]
    case userInteractionCount = 'userInteractionCount';
    #[SchemaPropertyInfo(rdfs_comment: 'Device used to perform the test.', schema_domain_includes: ['MedicalTest'], range_includes: ['MedicalDevice', 'Role', 'Text', 'URL'], superseded_by: null)]
    case usesDevice = 'usesDevice';
    #[SchemaPropertyInfo(rdfs_comment: 'The standard for interpreting the Plan ID. The preferred is "HIOS". See the Centers for Medicare & Medicaid Services for more details.', schema_domain_includes: ['HealthInsurancePlan'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case usesHealthPlanIdStandard = 'usesHealthPlanIdStandard';
    #[SchemaPropertyInfo(rdfs_comment: 'Text of an utterances (spoken words, lyrics etc.) that occurs at a certain section of a media object, represented as a <a href="https://schema.org/HyperTocEntry" target="_blank" rel="noopener noreferrer">HyperTocEntry</a>.', schema_domain_includes: ['HyperTocEntry'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case utterances = 'utterances';
    #[SchemaPropertyInfo(rdfs_comment: 'The duration of validity of a permit or similar thing.', schema_domain_includes: ['Credential', 'Permit'], range_includes: ['Duration', 'Role', 'Text', 'URL'], superseded_by: null)]
    case validFor = 'validFor';
    #[SchemaPropertyInfo(rdfs_comment: 'The membership program tier(s) an Offer (or a PriceSpecification, OfferShippingDetails, or MerchantReturnPolicy under an Offer) is valid for.', schema_domain_includes: ['MerchantReturnPolicy', 'Offer', 'OfferShippingDetails', 'PriceSpecification', 'ShippingService'], range_includes: ['MemberProgramTier', 'Role', 'Text', 'URL'], superseded_by: null)]
    case validForMemberTier = 'validForMemberTier';
    #[SchemaPropertyInfo(rdfs_comment: 'The date when the item becomes valid.', schema_domain_includes: ['Certification', 'Demand', 'FinancialIncentive', 'LocationFeatureSpecification', 'MonetaryAmount', 'Offer', 'OpeningHoursSpecification', 'Permit', 'PriceSpecification'], range_includes: ['Date', 'DateTime'], superseded_by: null)]
    case validFrom = 'validFrom';
    #[SchemaPropertyInfo(rdfs_comment: 'The geographic area where the item is valid. Applies for example to a <a href="https://schema.org/Permit" target="_blank" rel="noopener noreferrer">Permit</a>, a <a href="https://schema.org/Certification" target="_blank" rel="noopener noreferrer">Certification</a>, or an <a href="https://schema.org/EducationalOccupationalCredential" target="_blank" rel="noopener noreferrer">EducationalOccupationalCredential</a>.', schema_domain_includes: ['Certification', 'Credential', 'Permit'], range_includes: ['AdministrativeArea', 'Role', 'Text', 'URL'], superseded_by: null)]
    case validIn = 'validIn';
    #[SchemaPropertyInfo(rdfs_comment: 'The date after when the item is not valid. For example the end of an offer, salary period, or a period of opening hours.', schema_domain_includes: ['Demand', 'FinancialIncentive', 'JobPosting', 'LocationFeatureSpecification', 'MonetaryAmount', 'Offer', 'OpeningHoursSpecification', 'PriceSpecification'], range_includes: ['Date', 'DateTime'], superseded_by: null)]
    case validThrough = 'validThrough';
    #[SchemaPropertyInfo(rdfs_comment: 'The date when the item is no longer valid.', schema_domain_includes: ['Permit'], range_includes: ['Date'], superseded_by: null)]
    case validUntil = 'validUntil';
    #[SchemaPropertyInfo(rdfs_comment: 'The value of a <a href="https://schema.org/QuantitativeValue" target="_blank" rel="noopener noreferrer">QuantitativeValue</a> (including <a href="https://schema.org/Observation" target="_blank" rel="noopener noreferrer">Observation</a>) or property value node.\\n\\n* For <a href="https://schema.org/QuantitativeValue" target="_blank" rel="noopener noreferrer">QuantitativeValue</a> and <a href="https://schema.org/MonetaryAmount" target="_blank" rel="noopener noreferrer">MonetaryAmount</a>, the recommended type for values is \'Number\'.\\n* For <a href="https://schema.org/PropertyValue" target="_blank" rel="noopener noreferrer">PropertyValue</a>, it can be \'Text\', \'Number\', \'Boolean\', or \'StructuredValue\'.\\n* Use values from 0123456789 (Unicode \'DIGIT ZERO\' (U+0030) to \'DIGIT NINE\' (U+0039)) rather than superficially similar Unicode symbols.\\n* Use \'.\' (Unicode \'FULL STOP\' (U+002E)) rather than \',\' to indicate a decimal point. Avoid using these symbols as a readability separator.', schema_domain_includes: ['MonetaryAmount', 'PropertyValue', 'QuantitativeValue'], range_includes: ['Boolean', 'Number', 'Role', 'StructuredValue', 'Text', 'URL'], superseded_by: null)]
    case value = 'value';
    #[SchemaPropertyInfo(rdfs_comment: 'Specifies whether the applicable value-added tax (VAT) is included in the price specification or not.', schema_domain_includes: ['PriceSpecification'], range_includes: ['Boolean'], superseded_by: null)]
    case valueAddedTaxIncluded = 'valueAddedTaxIncluded';
    #[SchemaPropertyInfo(rdfs_comment: 'Specifies the allowed range for number of characters in a literal value.', schema_domain_includes: ['PropertyValueSpecification'], range_includes: ['Number'], superseded_by: null)]
    case valueMaxLength = 'valueMaxLength';
    #[SchemaPropertyInfo(rdfs_comment: 'Specifies the minimum allowed range for number of characters in a literal value.', schema_domain_includes: ['PropertyValueSpecification'], range_includes: ['Number'], superseded_by: null)]
    case valueMinLength = 'valueMinLength';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates the name of the PropertyValueSpecification to be used in URL templates and form encoding in a manner analogous to HTML\'s input@name.', schema_domain_includes: ['PropertyValueSpecification'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case valueName = 'valueName';
    #[SchemaPropertyInfo(rdfs_comment: 'Specifies a regular expression for testing literal values according to the HTML spec.', schema_domain_includes: ['PropertyValueSpecification'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case valuePattern = 'valuePattern';
    #[SchemaPropertyInfo(rdfs_comment: 'A secondary value that provides additional information on the original value, e.g. a reference temperature or a type of measurement.', schema_domain_includes: ['PropertyValue', 'QualitativeValue', 'QuantitativeValue'], range_includes: ['DefinedTerm', 'Enumeration', 'MeasurementTypeEnumeration', 'PropertyValue', 'QualitativeValue', 'QuantitativeValue', 'Role', 'StructuredValue', 'Text', 'URL'], superseded_by: null)]
    case valueReference = 'valueReference';
    #[SchemaPropertyInfo(rdfs_comment: 'Whether the property must be filled in to complete the action.  Default is false.', schema_domain_includes: ['PropertyValueSpecification'], range_includes: ['Boolean'], superseded_by: null)]
    case valueRequired = 'valueRequired';
    #[SchemaPropertyInfo(rdfs_comment: 'The variableMeasured property can indicate (repeated as necessary) the  variables that are measured in some dataset, either described as text or as pairs of identifier and description using PropertyValue, or more explicitly as a <a href="https://schema.org/StatisticalVariable" target="_blank" rel="noopener noreferrer">StatisticalVariable</a>.', schema_domain_includes: ['Dataset', 'Observation'], range_includes: ['Property', 'PropertyValue', 'Role', 'StatisticalVariable', 'Text', 'URL'], superseded_by: null)]
    case variableMeasured = 'variableMeasured';
    #[SchemaPropertyInfo(rdfs_comment: 'A description of the variant cover
    	for the issue, if the issue is a variant printing. For example, "Bryan Hitch
    	Variant Cover" or "2nd Printing Variant".', schema_domain_includes: ['ComicIssue'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case variantCover = 'variantCover';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates the property or properties by which the variants in a <a href="https://schema.org/ProductGroup" target="_blank" rel="noopener noreferrer">ProductGroup</a> vary, e.g. their size, color etc. Schema.org properties can be referenced by their short name e.g. "color"; terms defined elsewhere can be referenced with their URIs.', schema_domain_includes: ['ProductGroup'], range_includes: ['DefinedTerm', 'Role', 'Text', 'URL'], superseded_by: null)]
    case variesBy = 'variesBy';
    #[SchemaPropertyInfo(rdfs_comment: 'The value-added Tax ID of the organization or person with national prefix (for example IT123456789). Can also be described as <a href="https://schema.org/iso6523Code" target="_blank" rel="noopener noreferrer">iso6523Code</a> with proper prefix.', schema_domain_includes: ['Organization', 'Person'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case vatID = 'vatID';
    #[SchemaPropertyInfo(rdfs_comment: 'A short text indicating the configuration of the vehicle, e.g. \'5dr hatchback ST 2.5 MT 225 hp\' or \'limited edition\'.', schema_domain_includes: ['Vehicle'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case vehicleConfiguration = 'vehicleConfiguration';
    #[SchemaPropertyInfo(rdfs_comment: 'Information about the engine or engines of the vehicle.', schema_domain_includes: ['Vehicle'], range_includes: ['EngineSpecification', 'Role', 'Text', 'URL'], superseded_by: null)]
    case vehicleEngine = 'vehicleEngine';
    #[SchemaPropertyInfo(rdfs_comment: 'The Vehicle Identification Number (VIN) is a unique serial number used by the automotive industry to identify individual motor vehicles.', schema_domain_includes: ['Vehicle'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case vehicleIdentificationNumber = 'vehicleIdentificationNumber';
    #[SchemaPropertyInfo(rdfs_comment: 'The color or color combination of the interior of the vehicle.', schema_domain_includes: ['Vehicle'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case vehicleInteriorColor = 'vehicleInteriorColor';
    #[SchemaPropertyInfo(rdfs_comment: 'The type or material of the interior of the vehicle (e.g. synthetic fabric, leather, wood, etc.). While most interior types are characterized by the material used, an interior type can also be based on vehicle usage or target audience.', schema_domain_includes: ['Vehicle'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case vehicleInteriorType = 'vehicleInteriorType';
    #[SchemaPropertyInfo(rdfs_comment: 'The release date of a vehicle model (often used to differentiate versions of the same make and model).', schema_domain_includes: ['Vehicle'], range_includes: ['Date'], superseded_by: null)]
    case vehicleModelDate = 'vehicleModelDate';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of passengers that can be seated in the vehicle, both in terms of the physical space available, and in terms of limitations set by law.\\n\\nTypical unit code(s): C62 for persons.', schema_domain_includes: ['Vehicle'], range_includes: ['Number', 'QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case vehicleSeatingCapacity = 'vehicleSeatingCapacity';
    #[SchemaPropertyInfo(rdfs_comment: 'Indicates whether the vehicle has been used for special purposes, like commercial rental, driving school, or as a taxi. The legislation in many countries requires this information to be revealed when offering a car for sale.', schema_domain_includes: ['Vehicle'], range_includes: ['CarUsageType', 'Role', 'Text', 'URL'], superseded_by: null)]
    case vehicleSpecialUsage = 'vehicleSpecialUsage';
    #[SchemaPropertyInfo(rdfs_comment: 'The type of component used for transmitting the power from a rotating power source to the wheels or other relevant component(s) ("gearbox" for cars).', schema_domain_includes: ['Vehicle'], range_includes: ['QualitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case vehicleTransmission = 'vehicleTransmission';
    #[SchemaPropertyInfo(rdfs_comment: '\'vendor\' is an earlier term for \'seller\'.', schema_domain_includes: ['BuyAction'], range_includes: ['Organization', 'Person', 'Role', 'Text', 'URL'], superseded_by: 'seller')]
    case vendor = 'vendor';
    #[SchemaPropertyInfo(rdfs_comment: 'Disclosure about verification and fact-checking processes for a <a href="https://schema.org/NewsMediaOrganization" target="_blank" rel="noopener noreferrer">NewsMediaOrganization</a> or other fact-checking <a href="https://schema.org/Organization" target="_blank" rel="noopener noreferrer">Organization</a>.', schema_domain_includes: ['NewsMediaOrganization'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case verificationFactCheckingPolicy = 'verificationFactCheckingPolicy';
    #[SchemaPropertyInfo(rdfs_comment: 'The version of the CreativeWork embodied by a specified resource.', schema_domain_includes: ['CreativeWork'], range_includes: ['Number', 'Role', 'Text', 'URL'], superseded_by: null)]
    case version = 'version';
    #[SchemaPropertyInfo(rdfs_comment: 'An embedded video object.', schema_domain_includes: ['CreativeWork'], range_includes: ['Clip', 'Role', 'Text', 'URL', 'VideoObject'], superseded_by: null)]
    case video = 'video';
    #[SchemaPropertyInfo(rdfs_comment: 'The type of screening or video broadcast used (e.g. IMAX, 3D, SD, HD, etc.).', schema_domain_includes: ['BroadcastEvent', 'BroadcastService', 'ScreeningEvent'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case videoFormat = 'videoFormat';
    #[SchemaPropertyInfo(rdfs_comment: 'The frame size of the video.', schema_domain_includes: ['VideoObject'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case videoFrameSize = 'videoFrameSize';
    #[SchemaPropertyInfo(rdfs_comment: 'The quality of the video.', schema_domain_includes: ['VideoObject'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case videoQuality = 'videoQuality';
    #[SchemaPropertyInfo(rdfs_comment: 'Identifies the volume of publication or multi-part work; for example, "iii" or "2".', schema_domain_includes: ['PublicationVolume'], range_includes: ['Integer', 'Role', 'Text', 'URL'], superseded_by: null)]
    case volumeNumber = 'volumeNumber';
    #[SchemaPropertyInfo(rdfs_comment: 'Any FDA or other warnings about the drug (text or URL).', schema_domain_includes: ['Drug'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case warning = 'warning';
    #[SchemaPropertyInfo(rdfs_comment: 'The warranty promise(s) included in the offer.', schema_domain_includes: ['Demand', 'Offer'], range_includes: ['Role', 'Text', 'URL', 'WarrantyPromise'], superseded_by: null)]
    case warranty = 'warranty';
    #[SchemaPropertyInfo(rdfs_comment: 'The warranty promise(s) included in the offer.', schema_domain_includes: ['BuyAction', 'SellAction'], range_includes: ['Role', 'Text', 'URL', 'WarrantyPromise'], superseded_by: 'warranty')]
    case warrantyPromise = 'warrantyPromise';
    #[SchemaPropertyInfo(rdfs_comment: 'The scope of the warranty promise.', schema_domain_includes: ['WarrantyPromise'], range_includes: ['Role', 'Text', 'URL', 'WarrantyScope'], superseded_by: null)]
    case warrantyScope = 'warrantyScope';
    #[SchemaPropertyInfo(rdfs_comment: 'The time when a passenger can check into the flight online.', schema_domain_includes: ['Flight'], range_includes: ['DateTime'], superseded_by: null)]
    case webCheckinTime = 'webCheckinTime';
    #[SchemaPropertyInfo(rdfs_comment: 'The URL for a feed, e.g. associated with a podcast series, blog, or series of date-stamped updates. This is usually RSS or Atom.', schema_domain_includes: ['PodcastSeries', 'SpecialAnnouncement'], range_includes: ['DataFeed', 'Role', 'Text', 'URL'], superseded_by: null)]
    case webFeed = 'webFeed';
    #[SchemaPropertyInfo(rdfs_comment: 'The weight of the product or person.', schema_domain_includes: ['OfferShippingDetails', 'Person', 'Product', 'ShippingConditions', 'VisualArtwork'], range_includes: ['Mass', 'QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case weight = 'weight';
    #[SchemaPropertyInfo(rdfs_comment: 'Value representing the fraction of the weight that is used to compute the shipping price. Example: 0.10 and a shipping weight of 15kg would add $1.5 to the order price, where the $ is the currency of the order.', schema_domain_includes: ['ShippingRateSettings'], range_includes: ['Number'], superseded_by: null)]
    case weightPercentage = 'weightPercentage';
    #[SchemaPropertyInfo(rdfs_comment: 'The permitted total weight of the loaded vehicle, including passengers and cargo and the weight of the empty vehicle.\\n\\nTypical unit code(s): KGM for kilogram, LBR for pound\\n\\n* Note 1: You can indicate additional information in the <a href="https://schema.org/name" target="_blank" rel="noopener noreferrer">name</a> of the <a href="https://schema.org/QuantitativeValue" target="_blank" rel="noopener noreferrer">QuantitativeValue</a> node.\\n* Note 2: You may also link to a <a href="https://schema.org/QualitativeValue" target="_blank" rel="noopener noreferrer">QualitativeValue</a> node that provides additional information using <a href="https://schema.org/valueReference" target="_blank" rel="noopener noreferrer">valueReference</a>.\\n* Note 3: Note that you can use <a href="https://schema.org/minValue" target="_blank" rel="noopener noreferrer">minValue</a> and <a href="https://schema.org/maxValue" target="_blank" rel="noopener noreferrer">maxValue</a> to indicate ranges.', schema_domain_includes: ['Vehicle'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case weightTotal = 'weightTotal';
    #[SchemaPropertyInfo(rdfs_comment: 'The distance between the centers of the front and rear wheels.\\n\\nTypical unit code(s): CMT for centimeters, MTR for meters, INH for inches, FOT for foot/feet.', schema_domain_includes: ['Vehicle'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case wheelbase = 'wheelbase';
    #[SchemaPropertyInfo(rdfs_comment: 'The width of the item.', schema_domain_includes: ['MediaObject', 'OfferShippingDetails', 'Product', 'ShippingConditions', 'VisualArtwork'], range_includes: ['Distance', 'QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case width = 'width';
    #[SchemaPropertyInfo(rdfs_comment: 'A sub property of participant. The winner of the action.', schema_domain_includes: ['LoseAction'], range_includes: ['Person', 'Role', 'Text', 'URL'], superseded_by: null)]
    case winner = 'winner';
    #[SchemaPropertyInfo(rdfs_comment: 'The number of words in the text of the CreativeWork such as an Article, Book, etc.', schema_domain_includes: ['Article', 'CreativeWork'], range_includes: ['Integer'], superseded_by: null)]
    case wordCount = 'wordCount';
    #[SchemaPropertyInfo(rdfs_comment: 'Example/instance/realization/derivation of the concept of this creative work. E.g. the paperback edition, first edition, or e-book.', schema_domain_includes: ['CreativeWork'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case workExample = 'workExample';
    #[SchemaPropertyInfo(rdfs_comment: 'A work featured in some event, e.g. exhibited in an ExhibitionEvent.
       Specific subproperties are available for workPerformed (e.g. a play), or a workPresented (a Movie at a ScreeningEvent).', schema_domain_includes: ['Event'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case workFeatured = 'workFeatured';
    #[SchemaPropertyInfo(rdfs_comment: 'The typical working hours for this job (e.g. 1st shift, night shift, 8am-5pm).', schema_domain_includes: ['JobPosting'], range_includes: ['Role', 'Text', 'URL'], superseded_by: null)]
    case workHours = 'workHours';
    #[SchemaPropertyInfo(rdfs_comment: 'A contact location for a person\'s place of work.', schema_domain_includes: ['Person'], range_includes: ['ContactPoint', 'Place', 'Role', 'Text', 'URL'], superseded_by: null)]
    case workLocation = 'workLocation';
    #[SchemaPropertyInfo(rdfs_comment: 'A work performed in some event, for example a play performed in a TheaterEvent.', schema_domain_includes: ['Event'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case workPerformed = 'workPerformed';
    #[SchemaPropertyInfo(rdfs_comment: 'The movie presented during this event.', schema_domain_includes: ['ScreeningEvent'], range_includes: ['Movie', 'Role', 'Text', 'URL'], superseded_by: null)]
    case workPresented = 'workPresented';
    #[SchemaPropertyInfo(rdfs_comment: 'A work that is a translation of the content of this work. E.g. 西遊記 has an English workTranslation “Journey to the West”, a German workTranslation “Monkeys Pilgerfahrt” and a Vietnamese  translation Tây du ký bình khảo.', schema_domain_includes: ['CreativeWork'], range_includes: ['CreativeWork', 'Role', 'Text', 'URL'], superseded_by: null)]
    case workTranslation = 'workTranslation';
    #[SchemaPropertyInfo(rdfs_comment: 'Quantitative measure of the physiologic output of the exercise; also referred to as energy expenditure.', schema_domain_includes: ['ExercisePlan'], range_includes: ['Energy', 'QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case workload = 'workload';
    #[SchemaPropertyInfo(rdfs_comment: 'Organizations that the person works for.', schema_domain_includes: ['Person', 'Role'], range_includes: ['Organization', 'Role', 'Text', 'URL'], superseded_by: null)]
    case worksFor = 'worksFor';
    #[SchemaPropertyInfo(rdfs_comment: 'The lowest value allowed in this rating system.', schema_domain_includes: ['Rating'], range_includes: ['Number', 'Role', 'Text', 'URL'], superseded_by: null)]
    case worstRating = 'worstRating';
    #[SchemaPropertyInfo(rdfs_comment: 'An XPath, e.g. of a <a href="https://schema.org/SpeakableSpecification" target="_blank" rel="noopener noreferrer">SpeakableSpecification</a> or <a href="https://schema.org/WebPageElement" target="_blank" rel="noopener noreferrer">WebPageElement</a>. In the latter case, multiple matches within a page can constitute a single conceptual "Web page element".', schema_domain_includes: ['SpeakableSpecification', 'WebPageElement'], range_includes: ['Role', 'Text', 'URL', 'XPathType'], superseded_by: null)]
    case xpath = 'xpath';
    #[SchemaPropertyInfo(rdfs_comment: 'The year an <a href="https://schema.org/Accommodation" target="_blank" rel="noopener noreferrer">Accommodation</a> was constructed. This corresponds to the <a href="https://ddwiki.reso.org/display/DDW17/YearBuilt+Field" target="_blank" rel="noopener noreferrer">YearBuilt field in RESO</a>.', schema_domain_includes: ['Accommodation'], range_includes: ['Number'], superseded_by: null)]
    case yearBuilt = 'yearBuilt';
    #[SchemaPropertyInfo(rdfs_comment: 'The size of the business in annual revenue.', schema_domain_includes: ['BusinessAudience'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case yearlyRevenue = 'yearlyRevenue';
    #[SchemaPropertyInfo(rdfs_comment: 'The age of the business.', schema_domain_includes: ['BusinessAudience'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case yearsInOperation = 'yearsInOperation';
    #[SchemaPropertyInfo(rdfs_comment: 'The quantity that results by performing instructions. For example, a paper airplane, 10 personalized candles.', schema_domain_includes: ['HowTo'], range_includes: ['QuantitativeValue', 'Role', 'Text', 'URL'], superseded_by: null)]
    case Propertyyield = 'yield';

    /**
     * SchemaPropertyInfo attribute for this case (read via reflection).
     */
    public function info(): ?SchemaPropertyInfo
    {
        $ref = (new \ReflectionEnum(self::class))->getCase($this->name);
        $attrs = $ref->getAttributes(SchemaPropertyInfo::class);

        return isset($attrs[0]) ? $attrs[0]->newInstance() : null;
    }

    /**
     * Property description (rdfs:comment) as HTML with links.
     */
    public function description(): ?string
    {
        return $this->info()?->rdfs_comment;
    }

    /**
     * Type names this property applies to (schema:domainIncludes).
     *
     * @return list<string>
     */
    public function domainTypes(): array
    {
        return $this->info()?->schema_domain_includes ?? [];
    }

    /**
     * Type names for values: declared schema:rangeIncludes plus Schema.org OWL-implied types
     * (Role, Text, URL) when applicable—see generator and schema-jsonld-normalization.md.
     *
     * @return list<string>
     */
    public function rangeTypes(): array
    {
        return $this->info()?->range_includes ?? [];
    }

    /**
     * Local name of the property that supersedes this property (schema:supersededBy), or null.
     */
    public function supersededBy(): ?string
    {
        return $this->info()?->superseded_by;
    }

    /**
     * Human-readable label (the property name).
     */
    public function label(): string
    {
        return $this->value;
    }
}
