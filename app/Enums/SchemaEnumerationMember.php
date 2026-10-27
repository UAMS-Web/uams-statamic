<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Attributes\SchemaEnumerationMemberInfo;

/**
 * Schema.org enumeration member values from schema.org JSON-LD vocabulary.
 *
 * Each case is an enumeration member (e.g. AlgorithmicMediaDigitalSource) with
 * metadata via SchemaEnumerationMemberInfo (type, rdfs_comment).
 *
 * Generated from schema.org on 2026-05-20 using
 * app/Services/EnumGeneration/Schema/SchemaTypeEnumGenerator.php.
 *
 * @see https://schema.org/docs/developers.html
 */
enum SchemaEnumerationMember: string
{
    #[SchemaEnumerationMemberInfo(type: ['PhysicalExam'], rdfs_comment: 'Abdomen clinical examination.')]
    case Abdomen = 'Abdomen';
    #[SchemaEnumerationMemberInfo(type: ['PriceComponentTypeEnumeration'], rdfs_comment: 'Represents the activation fee part of the total price for an offered product, for example a cellphone contract.')]
    case ActivationFee = 'ActivationFee';
    #[SchemaEnumerationMemberInfo(type: ['ActionStatusType'], rdfs_comment: 'An in-progress action (e.g., while watching the movie, or driving to a location).')]
    case ActiveActionStatus = 'ActiveActionStatus';
    #[SchemaEnumerationMemberInfo(type: ['MedicalStudyStatus'], rdfs_comment: 'Active, but not recruiting new participants.')]
    case ActiveNotRecruiting = 'ActiveNotRecruiting';
    #[SchemaEnumerationMemberInfo(type: ['PhysicalActivityCategory'], rdfs_comment: 'Physical activity of relatively low intensity that depends primarily on the aerobic energy-generating process; during activity, the aerobic metabolism uses oxygen to adequately meet energy demands during exercise.')]
    case AerobicActivity = 'AerobicActivity';
    #[SchemaEnumerationMemberInfo(type: ['MusicAlbumReleaseType'], rdfs_comment: 'AlbumRelease.')]
    case AlbumRelease = 'AlbumRelease';
    #[SchemaEnumerationMemberInfo(type: ['AdultOrientedEnumeration'], rdfs_comment: 'Item contains alcohol or promotes alcohol consumption.')]
    case AlcoholConsideration = 'AlcoholConsideration';
    #[SchemaEnumerationMemberInfo(type: ['IPTCDigitalSourceEnumeration'], rdfs_comment: 'Content coded as \'<a href="https://cv.iptc.org/newscodes/digitalsourcetype/algorithmicMedia">algorithmic media</a>\' using the IPTC <a href="https://cv.iptc.org/newscodes/digitalsourcetype/">digital source type</a> vocabulary.')]
    case AlgorithmicMediaDigitalSource = 'AlgorithmicMediaDigitalSource';
    #[SchemaEnumerationMemberInfo(type: ['IPTCDigitalSourceEnumeration'], rdfs_comment: 'Content coded as \'<a href="https://cv.iptc.org/newscodes/digitalsourcetype/algorithmicallyEnhanced">algorithmically enhanced</a>\' using the IPTC <a href="https://cv.iptc.org/newscodes/digitalsourcetype/">digital source type</a> vocabulary.')]
    case AlgorithmicallyEnhancedDigitalSource = 'AlgorithmicallyEnhancedDigitalSource';
    #[SchemaEnumerationMemberInfo(type: ['DriveWheelConfigurationValue'], rdfs_comment: 'All-wheel Drive is a transmission layout where the engine drives all four wheels.')]
    case AllWheelDriveConfiguration = 'AllWheelDriveConfiguration';
    #[SchemaEnumerationMemberInfo(type: ['HealthAspectEnumeration'], rdfs_comment: 'Content about the allergy-related aspects of a health topic.')]
    case AllergiesHealthAspect = 'AllergiesHealthAspect';
    #[SchemaEnumerationMemberInfo(type: ['PhysicalActivityCategory'], rdfs_comment: 'Physical activity that is of high-intensity which utilizes the anaerobic metabolism of the body.')]
    case AnaerobicActivity = 'AnaerobicActivity';
    #[SchemaEnumerationMemberInfo(type: ['DigitalPlatformEnumeration'], rdfs_comment: 'Represents the broad notion of Android-based operating systems.')]
    case AndroidPlatform = 'AndroidPlatform';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'A specific branch of medical science that pertains to study of anesthetics and their application.')]
    case Anesthesia = 'Anesthesia';
    #[SchemaEnumerationMemberInfo(type: ['PhysicalExam'], rdfs_comment: 'Appearance assessment with clinical examination.')]
    case Appearance = 'Appearance';
    #[SchemaEnumerationMemberInfo(type: ['BookFormatType'], rdfs_comment: 'Book format: Audiobook. This is an enumerated value for use with the bookFormat property. There is also a type \'Audiobook\' in the bib extension which includes Audiobook specific properties.')]
    case AudiobookFormat = 'AudiobookFormat';
    #[SchemaEnumerationMemberInfo(type: ['LegalValueLevel'], rdfs_comment: 'Indicates that the publisher gives some special status to the publication of the document. ("The Queens Printer" version of a UK Act of Parliament, or the PDF version of a Directive published by the EU Office of Publications). Something "Authoritative" is considered to be also <a href="https://schema.org/OfficialLegalValue" target="_blank" rel="noopener noreferrer">OfficialLegalValue</a>".')]
    case AuthoritativeLegalValue = 'AuthoritativeLegalValue';
    #[SchemaEnumerationMemberInfo(type: ['MedicineSystem'], rdfs_comment: 'A system of medicine that originated in India over thousands of years and that focuses on integrating and balancing the body, mind, and spirit.')]
    case Ayurvedic = 'Ayurvedic';
    #[SchemaEnumerationMemberInfo(type: ['ItemAvailability'], rdfs_comment: 'Indicates that the item is available on back order.')]
    case BackOrder = 'BackOrder';
    #[SchemaEnumerationMemberInfo(type: ['InfectiousAgentClass'], rdfs_comment: 'Pathogenic bacteria that cause bacterial infection.')]
    case Bacteria = 'Bacteria';
    #[SchemaEnumerationMemberInfo(type: ['PhysicalActivityCategory'], rdfs_comment: 'Physical activity that is engaged to help maintain posture and balance.')]
    case Balance = 'Balance';
    #[SchemaEnumerationMemberInfo(type: ['GovernmentBenefitsType'], rdfs_comment: 'BasicIncome: this is a benefit for basic income.')]
    case BasicIncome = 'BasicIncome';
    #[SchemaEnumerationMemberInfo(type: ['HealthAspectEnumeration'], rdfs_comment: 'Content about the benefits and advantages of usage or utilization of topic.')]
    case BenefitsHealthAspect = 'BenefitsHealthAspect';
    #[SchemaEnumerationMemberInfo(type: ['BodyMeasurementTypeEnumeration'], rdfs_comment: 'Arm length (measured between arms/shoulder line intersection and the prominent wrist bone). Used, for example, to fit shirts.')]
    case BodyMeasurementArm = 'BodyMeasurementArm';
    #[SchemaEnumerationMemberInfo(type: ['BodyMeasurementTypeEnumeration'], rdfs_comment: 'Maximum girth of bust. Used, for example, to fit women\'s suits.')]
    case BodyMeasurementBust = 'BodyMeasurementBust';
    #[SchemaEnumerationMemberInfo(type: ['BodyMeasurementTypeEnumeration'], rdfs_comment: 'Maximum girth of chest. Used, for example, to fit men\'s suits.')]
    case BodyMeasurementChest = 'BodyMeasurementChest';
    #[SchemaEnumerationMemberInfo(type: ['BodyMeasurementTypeEnumeration'], rdfs_comment: 'Foot length (measured between end of the most prominent toe and the most prominent part of the heel). Used, for example, to measure socks.')]
    case BodyMeasurementFoot = 'BodyMeasurementFoot';
    #[SchemaEnumerationMemberInfo(type: ['BodyMeasurementTypeEnumeration'], rdfs_comment: 'Maximum hand girth (measured over the knuckles of the open right hand excluding thumb, fingers together). Used, for example, to fit gloves.')]
    case BodyMeasurementHand = 'BodyMeasurementHand';
    #[SchemaEnumerationMemberInfo(type: ['BodyMeasurementTypeEnumeration'], rdfs_comment: 'Maximum girth of head above the ears. Used, for example, to fit hats.')]
    case BodyMeasurementHead = 'BodyMeasurementHead';
    #[SchemaEnumerationMemberInfo(type: ['BodyMeasurementTypeEnumeration'], rdfs_comment: 'Body height (measured between crown of head and soles of feet). Used, for example, to fit jackets.')]
    case BodyMeasurementHeight = 'BodyMeasurementHeight';
    #[SchemaEnumerationMemberInfo(type: ['BodyMeasurementTypeEnumeration'], rdfs_comment: 'Girth of hips (measured around the buttocks). Used, for example, to fit skirts.')]
    case BodyMeasurementHips = 'BodyMeasurementHips';
    #[SchemaEnumerationMemberInfo(type: ['BodyMeasurementTypeEnumeration'], rdfs_comment: 'Inside leg (measured between crotch and soles of feet). Used, for example, to fit pants.')]
    case BodyMeasurementInsideLeg = 'BodyMeasurementInsideLeg';
    #[SchemaEnumerationMemberInfo(type: ['BodyMeasurementTypeEnumeration'], rdfs_comment: 'Girth of neck. Used, for example, to fit shirts.')]
    case BodyMeasurementNeck = 'BodyMeasurementNeck';
    #[SchemaEnumerationMemberInfo(type: ['BodyMeasurementTypeEnumeration'], rdfs_comment: 'Girth of body just below the bust. Used, for example, to fit women\'s swimwear.')]
    case BodyMeasurementUnderbust = 'BodyMeasurementUnderbust';
    #[SchemaEnumerationMemberInfo(type: ['BodyMeasurementTypeEnumeration'], rdfs_comment: 'Girth of natural waistline (between hip bones and lower ribs). Used, for example, to fit pants.')]
    case BodyMeasurementWaist = 'BodyMeasurementWaist';
    #[SchemaEnumerationMemberInfo(type: ['BodyMeasurementTypeEnumeration'], rdfs_comment: 'Body weight. Used, for example, to measure pantyhose.')]
    case BodyMeasurementWeight = 'BodyMeasurementWeight';
    #[SchemaEnumerationMemberInfo(type: ['MusicAlbumReleaseType'], rdfs_comment: 'BroadcastRelease.')]
    case BroadcastRelease = 'BroadcastRelease';
    #[SchemaEnumerationMemberInfo(type: ['GovernmentBenefitsType'], rdfs_comment: 'BusinessSupport: this is a benefit for supporting businesses.')]
    case BusinessSupport = 'BusinessSupport';
    #[SchemaEnumerationMemberInfo(type: ['PaymentMethodType'], rdfs_comment: 'Payment in advance by bank transfer, equivalent to <code>http://purl.org/goodrelations/v1#ByBankTransferInAdvance</code>.')]
    case ByBankTransferInAdvance = 'ByBankTransferInAdvance';
    #[SchemaEnumerationMemberInfo(type: ['PaymentMethodType'], rdfs_comment: 'Payment by invoice, typically after the goods were delivered, equivalent to <code>http://purl.org/goodrelations/v1#ByInvoice</code>.')]
    case ByInvoice = 'ByInvoice';
    #[SchemaEnumerationMemberInfo(type: ['MusicReleaseFormatType'], rdfs_comment: 'CDFormat.')]
    case CDFormat = 'CDFormat';
    #[SchemaEnumerationMemberInfo(type: ['PaymentMethodType'], rdfs_comment: 'Cash on Delivery (COD) payment, equivalent to <code>http://purl.org/goodrelations/v1#COD</code>.')]
    case COD = 'COD';
    #[SchemaEnumerationMemberInfo(type: ['MedicalImagingTechnique'], rdfs_comment: 'X-ray computed tomography imaging.')]
    case CT = 'CT';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'A specific branch of medical science that pertains to diagnosis and treatment of disorders of heart and vasculature.')]
    case Cardiovascular = 'Cardiovascular';
    #[SchemaEnumerationMemberInfo(type: ['PhysicalExam'], rdfs_comment: 'Cardiovascular system assessment with clinical examination.')]
    case CardiovascularExam = 'CardiovascularExam';
    #[SchemaEnumerationMemberInfo(type: ['MedicalObservationalStudyDesign'], rdfs_comment: 'A case series (also known as a clinical series) is a medical research study that tracks patients with a known exposure given similar treatment or examines their medical records for exposure and outcome. A case series can be retrospective or prospective and usually involves a smaller number of patients than the more powerful case-control studies or randomized controlled trials. Case series may be consecutive or non-consecutive, depending on whether all cases presenting to the reporting authors over a period of time were included, or only a selection.')]
    case CaseSeries = 'CaseSeries';
    #[SchemaEnumerationMemberInfo(type: ['PaymentMethodType'], rdfs_comment: 'Payment using cash, on premises, equivalent to <code>http://purl.org/goodrelations/v1#Cash</code>.')]
    case Cash = 'Cash';
    #[SchemaEnumerationMemberInfo(type: ['MusicReleaseFormatType'], rdfs_comment: 'CassetteFormat.')]
    case CassetteFormat = 'CassetteFormat';
    #[SchemaEnumerationMemberInfo(type: ['HealthAspectEnumeration'], rdfs_comment: 'Information about the causes and main actions that gave rise to the topic.')]
    case CausesHealthAspect = 'CausesHealthAspect';
    #[SchemaEnumerationMemberInfo(type: ['CertificationStatusEnumeration'], rdfs_comment: 'Specifies that a certification is active.')]
    case CertificationActive = 'CertificationActive';
    #[SchemaEnumerationMemberInfo(type: ['CertificationStatusEnumeration'], rdfs_comment: 'Specifies that a certification is inactive (no longer in effect).')]
    case CertificationInactive = 'CertificationInactive';
    #[SchemaEnumerationMemberInfo(type: ['UKNonprofitType'], rdfs_comment: 'CharitableIncorporatedOrganization: Non-profit type referring to a Charitable Incorporated Organization (UK).')]
    case CharitableIncorporatedOrganization = 'CharitableIncorporatedOrganization';
    #[SchemaEnumerationMemberInfo(type: ['PaymentMethodType'], rdfs_comment: 'Payment in advance by sending a check, equivalent to <code>http://purl.org/goodrelations/v1#CheckInAdvance</code>.')]
    case CheckInAdvance = 'CheckInAdvance';
    #[SchemaEnumerationMemberInfo(type: ['MedicineSystem'], rdfs_comment: 'A system of medicine focused on the relationship between the body\'s structure, mainly the spine, and its functioning.')]
    case Chiropractic = 'Chiropractic';
    #[SchemaEnumerationMemberInfo(type: ['PriceComponentTypeEnumeration'], rdfs_comment: 'Represents the cleaning fee part of the total price for an offered product, for example a vacation rental.')]
    case CleaningFee = 'CleaningFee';
    #[SchemaEnumerationMemberInfo(type: ['MedicalAudienceType'], rdfs_comment: 'Medical clinicians, including practicing physicians and other medical professionals involved in clinical practice.')]
    case Clinician = 'Clinician';
    #[SchemaEnumerationMemberInfo(type: ['GamePlayMode'], rdfs_comment: 'Play mode: CoOp. Co-operative games, where you play on the same team with friends.')]
    case CoOp = 'CoOp';
    #[SchemaEnumerationMemberInfo(type: ['MedicalObservationalStudyDesign'], rdfs_comment: 'Also known as a panel study. A cohort study is a form of longitudinal study used in medicine and social science. It is one type of study design and should be compared with a cross-sectional study.  A cohort is a group of people who share a common characteristic or experience within a defined period (e.g., are born, leave school, lose their job, are exposed to a drug or a vaccine, etc.). The comparison group may be the general population from which the cohort is drawn, or it may be another cohort of persons thought to have had little or no exposure to the substance under investigation, but otherwise similar. Alternatively, subgroups within the cohort may be compared with each other.')]
    case CohortStudy = 'CohortStudy';
    #[SchemaEnumerationMemberInfo(type: ['DigitalDocumentPermissionType'], rdfs_comment: 'Permission to add comments to the document.')]
    case CommentPermission = 'CommentPermission';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'A field of public health focusing on improving health characteristics of a defined population in relation with their geographical or environment areas.')]
    case CommunityHealth = 'CommunityHealth';
    #[SchemaEnumerationMemberInfo(type: ['MusicAlbumProductionType'], rdfs_comment: 'CompilationAlbum.')]
    case CompilationAlbum = 'CompilationAlbum';
    #[SchemaEnumerationMemberInfo(type: ['MedicalStudyStatus'], rdfs_comment: 'Completed.')]
    case Completed = 'Completed';
    #[SchemaEnumerationMemberInfo(type: ['ActionStatusType'], rdfs_comment: 'An action that has already taken place.')]
    case CompletedActionStatus = 'CompletedActionStatus';
    #[SchemaEnumerationMemberInfo(type: ['IPTCDigitalSourceEnumeration'], rdfs_comment: 'Content coded as \'<a href="https://cv.iptc.org/newscodes/digitalsourcetype/compositeCapture">composite capture</a>\' using the IPTC <a href="https://cv.iptc.org/newscodes/digitalsourcetype/">digital source type</a> vocabulary.')]
    case CompositeCaptureDigitalSource = 'CompositeCaptureDigitalSource';
    #[SchemaEnumerationMemberInfo(type: ['IPTCDigitalSourceEnumeration'], rdfs_comment: 'Content coded as \'<a href="https://cv.iptc.org/newscodes/digitalsourcetype/algorithmicMedia">algorithmic media</a>\' using the IPTC <a href="https://cv.iptc.org/newscodes/digitalsourcetype/">digital source type</a> vocabulary.')]
    case CompositeDigitalSource = 'CompositeDigitalSource';
    #[SchemaEnumerationMemberInfo(type: ['IPTCDigitalSourceEnumeration'], rdfs_comment: 'Content coded as \'<a href="https://cv.iptc.org/newscodes/digitalsourcetype/compositeSynthetic">composite synthetic</a>\' using the IPTC <a href="https://cv.iptc.org/newscodes/digitalsourcetype/">digital source type</a> vocabulary.')]
    case CompositeSyntheticDigitalSource = 'CompositeSyntheticDigitalSource';
    #[SchemaEnumerationMemberInfo(type: ['IPTCDigitalSourceEnumeration'], rdfs_comment: 'Content coded as \'<a href="https://cv.iptc.org/newscodes/digitalsourcetype/compositeWithTrainedAlgorithmicMedia">composite with trained algorithmic media</a>\' using the IPTC <a href="https://cv.iptc.org/newscodes/digitalsourcetype/">digital source type</a> vocabulary.')]
    case CompositeWithTrainedAlgorithmicMediaDigitalSource = 'CompositeWithTrainedAlgorithmicMediaDigitalSource';
    #[SchemaEnumerationMemberInfo(type: ['HealthAspectEnumeration'], rdfs_comment: 'Content about contagion mechanisms and contagiousness information over the topic.')]
    case ContagiousnessHealthAspect = 'ContagiousnessHealthAspect';
    #[SchemaEnumerationMemberInfo(type: ['MedicalObservationalStudyDesign'], rdfs_comment: 'Studies carried out on pre-existing data (usually from \'snapshot\' surveys), such as that collected by the Census Bureau. Sometimes called Prevalence Studies.')]
    case CrossSectional = 'CrossSectional';
    #[SchemaEnumerationMemberInfo(type: ['DENonprofitType'], rdfs_comment: 'DECooperativeCharity: Non-profit type referring to a charitable Cooperative (German Genossenschaft) that has been recognized by the tax authorities as a charitable non-profit.')]
    case DECooperativeCharity = 'DECooperativeCharity';
    #[SchemaEnumerationMemberInfo(type: ['DENonprofitType'], rdfs_comment: 'DEFoundationCharity: Non-profit type referring to a charitable Foundation (German Stiftung) that has been recognized by the tax authorities as a charitable non-profit.')]
    case DEFoundationCharity = 'DEFoundationCharity';
    #[SchemaEnumerationMemberInfo(type: ['DENonprofitType'], rdfs_comment: 'DEJointStockCompanyCharity: Non-profit type referring to a charitable Joint Stock Company (German gAG gemeinnützige Aktiengesellschaft) that has been recognized by the tax authorities as a charitable non-profit.')]
    case DEJointStockCompanyCharity = 'DEJointStockCompanyCharity';
    #[SchemaEnumerationMemberInfo(type: ['DENonprofitType'], rdfs_comment: 'DELimitedLiabilityCharity: Non-profit type referring to a charitable Common Stock Company (German gGmbH or gemeinnützige Gesellschaft mit beschränkter Haftung) that has been recognized by the tax authorities as a charitable non-profit.')]
    case DELimitedLiabilityCharity = 'DELimitedLiabilityCharity';
    #[SchemaEnumerationMemberInfo(type: ['DENonprofitType'], rdfs_comment: 'DENotRegisteredAssociationCharity: Non-profit type referring to a charitable association that is not registered with the German association register (gemeinnütziger Verein) and has been recognized by the tax authorities as a charitable non-profit.')]
    case DENotRegisteredAssociationCharity = 'DENotRegisteredAssociationCharity';
    #[SchemaEnumerationMemberInfo(type: ['DENonprofitType'], rdfs_comment: 'DEPublicCharity: Non-profit type referring to a charitable government owned juridical person (German Körperschaft öffentlichen Rechts) that has been recognized by the tax authorities as a charitable non-profit.')]
    case DEPublicCharity = 'DEPublicCharity';
    #[SchemaEnumerationMemberInfo(type: ['DENonprofitType'], rdfs_comment: 'DERegisteredAssociationCharity: Non-profit type referring to a charitable association that is registered with the German association register (german gemeinnütziger e.v. or gemeinnütziger eingetragener Verein) and has been recognized by the tax authorities as a charitable non-profit.')]
    case DERegisteredAssociationCharity = 'DERegisteredAssociationCharity';
    #[SchemaEnumerationMemberInfo(type: ['MusicAlbumProductionType'], rdfs_comment: 'DJMixAlbum.')]
    case DJMixAlbum = 'DJMixAlbum';
    #[SchemaEnumerationMemberInfo(type: ['MusicReleaseFormatType'], rdfs_comment: 'DVDFormat.')]
    case DVDFormat = 'DVDFormat';
    #[SchemaEnumerationMemberInfo(type: ['OfferItemCondition'], rdfs_comment: 'Indicates that the item is damaged.')]
    case DamagedCondition = 'DamagedCondition';
    #[SchemaEnumerationMemberInfo(type: ['AdultOrientedEnumeration'], rdfs_comment: 'The item is dangerous and requires careful handling and/or special training of the user. See also the <a href="https://unece.org/DAM/trans/danger/publi/unrec/rev17/English/02EREv17_Part2.pdf" target="_blank" rel="noopener noreferrer">UN Model Classification</a> defining the 9 classes of dangerous goods such as explosives, gases, flammables, and more.')]
    case DangerousGoodConsideration = 'DangerousGoodConsideration';
    #[SchemaEnumerationMemberInfo(type: ['IPTCDigitalSourceEnumeration'], rdfs_comment: 'Content coded as \'<a href="https://cv.iptc.org/newscodes/digitalsourcetype/dataDrivenMedia">data driven media</a>\' using the IPTC <a href="https://cv.iptc.org/newscodes/digitalsourcetype/">digital source type</a> vocabulary.')]
    case DataDrivenMediaDigitalSource = 'DataDrivenMediaDigitalSource';
    #[SchemaEnumerationMemberInfo(type: ['MediaManipulationRatingEnumeration'], rdfs_comment: 'Content coded \'missing context\' in a <a href="https://schema.org/MediaReview" target="_blank" rel="noopener noreferrer">MediaReview</a>, considered in the context of how it was published or shared.

For a <a href="https://schema.org/VideoObject" target="_blank" rel="noopener noreferrer">VideoObject</a> to be \'missing context\': Presenting unaltered video in an inaccurate manner that misrepresents the footage. For example, using incorrect dates or locations, altering the transcript or sharing brief clips from a longer video to mislead viewers. (A video rated \'original\' can also be missing context.)

For an <a href="https://schema.org/ImageObject" target="_blank" rel="noopener noreferrer">ImageObject</a> to be \'missing context\': Presenting unaltered images in an inaccurate manner to misrepresent the image and mislead the viewer. For example, a common tactic is using an unaltered image but saying it came from a different time or place. (An image rated \'original\' can also be missing context.)

For an <a href="https://schema.org/ImageObject" target="_blank" rel="noopener noreferrer">ImageObject</a> with embedded text to be \'missing context\': An unaltered image presented in an inaccurate manner to misrepresent the image and mislead the viewer. For example, a common tactic is using an unaltered image but saying it came from a different time or place. (An \'original\' image with inaccurate text would generally fall in this category.)

For an <a href="https://schema.org/AudioObject" target="_blank" rel="noopener noreferrer">AudioObject</a> to be \'missing context\': Unaltered audio presented in an inaccurate manner that misrepresents it. For example, using incorrect dates or locations, or sharing brief clips from a longer recording to mislead viewers. (Audio rated “original” can also be missing context.)')]
    case DecontextualizedContent = 'DecontextualizedContent';
    #[SchemaEnumerationMemberInfo(type: ['LegalValueLevel'], rdfs_comment: 'Indicates a document for which the text is conclusively what the law says and is legally binding. (e.g. The digitally signed version of an Official Journal.)
  Something "Definitive" is considered to be also <a href="https://schema.org/AuthoritativeLegalValue" target="_blank" rel="noopener noreferrer">AuthoritativeLegalValue</a>.')]
    case DefinitiveLegalValue = 'DefinitiveLegalValue';
    #[SchemaEnumerationMemberInfo(type: ['MusicAlbumProductionType'], rdfs_comment: 'DemoAlbum.')]
    case DemoAlbum = 'DemoAlbum';
    #[SchemaEnumerationMemberInfo(type: ['GameAvailabilityEnumeration'], rdfs_comment: 'Indicates demo game availability, i.e. a somehow limited demonstration of the full game.')]
    case DemoGameAvailability = 'DemoGameAvailability';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'A branch of medicine that is involved in the dental care.')]
    case Dentistry = 'Dentistry';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'Something relating to or practicing dermatology.')]
    case Dermatologic = 'Dermatologic';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'A specific branch of medical science that pertains to diagnosis and treatment of disorders of skin.')]
    case Dermatology = 'Dermatology';
    #[SchemaEnumerationMemberInfo(type: ['DigitalPlatformEnumeration'], rdfs_comment: 'Represents the broad notion of \'desktop\' browsers as a Web Platform.')]
    case DesktopWebPlatform = 'DesktopWebPlatform';
    #[SchemaEnumerationMemberInfo(type: ['RestrictedDiet'], rdfs_comment: 'A diet appropriate for people with diabetes.')]
    case DiabeticDiet = 'DiabeticDiet';
    #[SchemaEnumerationMemberInfo(type: ['MedicalDevicePurpose'], rdfs_comment: 'A medical device used for diagnostic purposes.')]
    case Diagnostic = 'Diagnostic';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'Dietetics and nutrition as a medical specialty.')]
    case DietNutrition = 'DietNutrition';
    #[SchemaEnumerationMemberInfo(type: ['IPTCDigitalSourceEnumeration'], rdfs_comment: 'Content coded as \'<a href="https://cv.iptc.org/newscodes/digitalsourcetype/digitalArt">digital art</a>\' using the IPTC <a href="https://cv.iptc.org/newscodes/digitalsourcetype/">digital source type</a> vocabulary.')]
    case DigitalArtDigitalSource = 'DigitalArtDigitalSource';
    #[SchemaEnumerationMemberInfo(type: ['MusicReleaseFormatType'], rdfs_comment: 'DigitalAudioTapeFormat.')]
    case DigitalAudioTapeFormat = 'DigitalAudioTapeFormat';
    #[SchemaEnumerationMemberInfo(type: ['IPTCDigitalSourceEnumeration'], rdfs_comment: 'Content coded as \'<a href="https://cv.iptc.org/newscodes/digitalsourcetype/digitalCapture">digital capture</a></a>\' using the IPTC <a href="https://cv.iptc.org/newscodes/digitalsourcetype/">digital source type</a> vocabulary.')]
    case DigitalCaptureDigitalSource = 'DigitalCaptureDigitalSource';
    #[SchemaEnumerationMemberInfo(type: ['MusicReleaseFormatType'], rdfs_comment: 'DigitalFormat.')]
    case DigitalFormat = 'DigitalFormat';
    #[SchemaEnumerationMemberInfo(type: ['PaymentMethodType'], rdfs_comment: 'Payment in advance by direct debit from the bank, equivalent to <code>http://purl.org/goodrelations/v1#DirectDebit</code>.')]
    case DirectDebit = 'DirectDebit';
    #[SchemaEnumerationMemberInfo(type: ['GovernmentBenefitsType'], rdfs_comment: 'DisabilitySupport: this is a benefit for disability support.')]
    case DisabilitySupport = 'DisabilitySupport';
    #[SchemaEnumerationMemberInfo(type: ['ItemAvailability'], rdfs_comment: 'Indicates that the item has been discontinued.')]
    case Discontinued = 'Discontinued';
    #[SchemaEnumerationMemberInfo(type: ['PriceComponentTypeEnumeration'], rdfs_comment: 'Represents the distance fee (e.g., price per km or mile) part of the total price for an offered product, for example a car rental.')]
    case DistanceFee = 'DistanceFee';
    #[SchemaEnumerationMemberInfo(type: ['MedicalTrialDesign'], rdfs_comment: 'A trial design in which neither the researcher nor the patient knows the details of the treatment the patient was randomly assigned to.')]
    case DoubleBlindedTrial = 'DoubleBlindedTrial';
    #[SchemaEnumerationMemberInfo(type: ['PriceComponentTypeEnumeration'], rdfs_comment: 'Represents the downpayment (up-front payment) price component of the total price for an offered product that has additional installment payments.')]
    case Downpayment = 'Downpayment';
    #[SchemaEnumerationMemberInfo(type: ['CarUsageType'], rdfs_comment: 'Indicates the usage of the vehicle for driving school.')]
    case DrivingSchoolVehicleUsage = 'DrivingSchoolVehicleUsage';
    #[SchemaEnumerationMemberInfo(type: ['BookFormatType'], rdfs_comment: 'Book format: Ebook.')]
    case EBook = 'EBook';
    #[SchemaEnumerationMemberInfo(type: ['MusicAlbumReleaseType'], rdfs_comment: 'EPRelease.')]
    case EPRelease = 'EPRelease';
    #[SchemaEnumerationMemberInfo(type: ['EUEnergyEfficiencyEnumeration'], rdfs_comment: 'Represents EU Energy Efficiency Class A as defined in EU energy labeling regulations.')]
    case EUEnergyEfficiencyCategoryA = 'EUEnergyEfficiencyCategoryA';
    #[SchemaEnumerationMemberInfo(type: ['EUEnergyEfficiencyEnumeration'], rdfs_comment: 'Represents EU Energy Efficiency Class A+ as defined in EU energy labeling regulations.')]
    case EUEnergyEfficiencyCategoryA1Plus = 'EUEnergyEfficiencyCategoryA1Plus';
    #[SchemaEnumerationMemberInfo(type: ['EUEnergyEfficiencyEnumeration'], rdfs_comment: 'Represents EU Energy Efficiency Class A++ as defined in EU energy labeling regulations.')]
    case EUEnergyEfficiencyCategoryA2Plus = 'EUEnergyEfficiencyCategoryA2Plus';
    #[SchemaEnumerationMemberInfo(type: ['EUEnergyEfficiencyEnumeration'], rdfs_comment: 'Represents EU Energy Efficiency Class A+++ as defined in EU energy labeling regulations.')]
    case EUEnergyEfficiencyCategoryA3Plus = 'EUEnergyEfficiencyCategoryA3Plus';
    #[SchemaEnumerationMemberInfo(type: ['EUEnergyEfficiencyEnumeration'], rdfs_comment: 'Represents EU Energy Efficiency Class B as defined in EU energy labeling regulations.')]
    case EUEnergyEfficiencyCategoryB = 'EUEnergyEfficiencyCategoryB';
    #[SchemaEnumerationMemberInfo(type: ['EUEnergyEfficiencyEnumeration'], rdfs_comment: 'Represents EU Energy Efficiency Class C as defined in EU energy labeling regulations.')]
    case EUEnergyEfficiencyCategoryC = 'EUEnergyEfficiencyCategoryC';
    #[SchemaEnumerationMemberInfo(type: ['EUEnergyEfficiencyEnumeration'], rdfs_comment: 'Represents EU Energy Efficiency Class D as defined in EU energy labeling regulations.')]
    case EUEnergyEfficiencyCategoryD = 'EUEnergyEfficiencyCategoryD';
    #[SchemaEnumerationMemberInfo(type: ['EUEnergyEfficiencyEnumeration'], rdfs_comment: 'Represents EU Energy Efficiency Class E as defined in EU energy labeling regulations.')]
    case EUEnergyEfficiencyCategoryE = 'EUEnergyEfficiencyCategoryE';
    #[SchemaEnumerationMemberInfo(type: ['EUEnergyEfficiencyEnumeration'], rdfs_comment: 'Represents EU Energy Efficiency Class F as defined in EU energy labeling regulations.')]
    case EUEnergyEfficiencyCategoryF = 'EUEnergyEfficiencyCategoryF';
    #[SchemaEnumerationMemberInfo(type: ['EUEnergyEfficiencyEnumeration'], rdfs_comment: 'Represents EU Energy Efficiency Class G as defined in EU energy labeling regulations.')]
    case EUEnergyEfficiencyCategoryG = 'EUEnergyEfficiencyCategoryG';
    #[SchemaEnumerationMemberInfo(type: ['PhysicalExam'], rdfs_comment: 'Ear function assessment with clinical examination.')]
    case Ear = 'Ear';
    #[SchemaEnumerationMemberInfo(type: ['MediaManipulationRatingEnumeration'], rdfs_comment: 'Content coded \'edited or cropped content\' in a <a href="https://schema.org/MediaReview" target="_blank" rel="noopener noreferrer">MediaReview</a>, considered in the context of how it was published or shared.

For a <a href="https://schema.org/VideoObject" target="_blank" rel="noopener noreferrer">VideoObject</a> to be \'edited or cropped content\': The video has been edited or rearranged. This category applies to time edits, including editing multiple videos together to alter the story being told or editing out large portions from a video.

For an <a href="https://schema.org/ImageObject" target="_blank" rel="noopener noreferrer">ImageObject</a> to be \'edited or cropped content\': Presenting a part of an image from a larger whole to mislead the viewer.

For an <a href="https://schema.org/ImageObject" target="_blank" rel="noopener noreferrer">ImageObject</a> with embedded text to be \'edited or cropped content\': Presenting a part of an image from a larger whole to mislead the viewer.

For an <a href="https://schema.org/AudioObject" target="_blank" rel="noopener noreferrer">AudioObject</a> to be \'edited or cropped content\': The audio has been edited or rearranged. This category applies to time edits, including editing multiple audio clips together to alter the story being told or editing out large portions from the recording.')]
    case EditedOrCroppedContent = 'EditedOrCroppedContent';
    #[SchemaEnumerationMemberInfo(type: ['HealthAspectEnumeration'], rdfs_comment: 'Content about the effectiveness-related aspects of a health topic.')]
    case EffectivenessHealthAspect = 'EffectivenessHealthAspect';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'A specific branch of medical science that deals with the evaluation and initial treatment of medical conditions caused by trauma or sudden illness.')]
    case Emergency = 'Emergency';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'A specific branch of medical science that pertains to diagnosis and treatment of disorders of endocrine glands and their secretions.')]
    case Endocrine = 'Endocrine';
    #[SchemaEnumerationMemberInfo(type: ['EnergyStarEnergyEfficiencyEnumeration'], rdfs_comment: 'Represents EnergyStar certification.')]
    case EnergyStarCertified = 'EnergyStarCertified';
    #[SchemaEnumerationMemberInfo(type: ['MedicalStudyStatus'], rdfs_comment: 'Enrolling participants by invitation only.')]
    case EnrollingByInvitation = 'EnrollingByInvitation';
    #[SchemaEnumerationMemberInfo(type: ['EventStatusType'], rdfs_comment: 'The event has been cancelled. If the event has multiple startDate values, all are assumed to be cancelled. Either startDate or previousStartDate may be used to specify the event\'s cancelled date(s).')]
    case EventCancelled = 'EventCancelled';
    #[SchemaEnumerationMemberInfo(type: ['EventStatusType'], rdfs_comment: 'Indicates that the event was changed to allow online participation. See <a href="https://schema.org/eventAttendanceMode" target="_blank" rel="noopener noreferrer">eventAttendanceMode</a> for specifics of whether it is now fully or partially online.')]
    case EventMovedOnline = 'EventMovedOnline';
    #[SchemaEnumerationMemberInfo(type: ['EventStatusType'], rdfs_comment: 'The event has been postponed and no new date has been set. The event\'s previousStartDate should be set.')]
    case EventPostponed = 'EventPostponed';
    #[SchemaEnumerationMemberInfo(type: ['EventStatusType'], rdfs_comment: 'The event has been rescheduled. The event\'s previousStartDate should be set to the old date and the startDate should be set to the event\'s new date. (If the event has been rescheduled multiple times, the previousStartDate property may be repeated.)')]
    case EventRescheduled = 'EventRescheduled';
    #[SchemaEnumerationMemberInfo(type: ['EventStatusType'], rdfs_comment: 'The event is taking place or has taken place on the startDate as scheduled. Use of this value is optional, as it is assumed by default.')]
    case EventScheduled = 'EventScheduled';
    #[SchemaEnumerationMemberInfo(type: ['MedicalEvidenceLevel'], rdfs_comment: 'Data derived from multiple randomized clinical trials or meta-analyses.')]
    case EvidenceLevelA = 'EvidenceLevelA';
    #[SchemaEnumerationMemberInfo(type: ['MedicalEvidenceLevel'], rdfs_comment: 'Data derived from a single randomized trial, or nonrandomized studies.')]
    case EvidenceLevelB = 'EvidenceLevelB';
    #[SchemaEnumerationMemberInfo(type: ['MedicalEvidenceLevel'], rdfs_comment: 'Only consensus opinion of experts, case studies, or standard-of-care.')]
    case EvidenceLevelC = 'EvidenceLevelC';
    #[SchemaEnumerationMemberInfo(type: ['MeasurementMethodEnum'], rdfs_comment: 'An example <a href="https://schema.org/MeasurementMethodEnum" target="_blank" rel="noopener noreferrer">MeasurementMethodEnum</a> (to remove when real enums are added).')]
    case ExampleMeasurementMethodEnum = 'ExampleMeasurementMethodEnum';
    #[SchemaEnumerationMemberInfo(type: ['RefundTypeEnumeration'], rdfs_comment: 'Specifies that a refund can be done as an exchange for the same product.')]
    case ExchangeRefund = 'ExchangeRefund';
    #[SchemaEnumerationMemberInfo(type: ['PhysicalExam'], rdfs_comment: 'Eye or ophthalmological function assessment with clinical examination.')]
    case Eye = 'Eye';
    #[SchemaEnumerationMemberInfo(type: ['DrugPregnancyCategory'], rdfs_comment: 'A designation by the US FDA signifying that adequate and well-controlled studies have failed to demonstrate a risk to the fetus in the first trimester of pregnancy (and there is no evidence of risk in later trimesters).')]
    case FDAcategoryA = 'FDAcategoryA';
    #[SchemaEnumerationMemberInfo(type: ['DrugPregnancyCategory'], rdfs_comment: 'A designation by the US FDA signifying that animal reproduction studies have failed to demonstrate a risk to the fetus and there are no adequate and well-controlled studies in pregnant women.')]
    case FDAcategoryB = 'FDAcategoryB';
    #[SchemaEnumerationMemberInfo(type: ['DrugPregnancyCategory'], rdfs_comment: 'A designation by the US FDA signifying that animal reproduction studies have shown an adverse effect on the fetus and there are no adequate and well-controlled studies in humans, but potential benefits may warrant use of the drug in pregnant women despite potential risks.')]
    case FDAcategoryC = 'FDAcategoryC';
    #[SchemaEnumerationMemberInfo(type: ['DrugPregnancyCategory'], rdfs_comment: 'A designation by the US FDA signifying that there is positive evidence of human fetal risk based on adverse reaction data from investigational or marketing experience or studies in humans, but potential benefits may warrant use of the drug in pregnant women despite potential risks.')]
    case FDAcategoryD = 'FDAcategoryD';
    #[SchemaEnumerationMemberInfo(type: ['DrugPregnancyCategory'], rdfs_comment: 'A designation by the US FDA signifying that studies in animals or humans have demonstrated fetal abnormalities and/or there is positive evidence of human fetal risk based on adverse reaction data from investigational or marketing experience, and the risks involved in use of the drug in pregnant women clearly outweigh potential benefits.')]
    case FDAcategoryX = 'FDAcategoryX';
    #[SchemaEnumerationMemberInfo(type: ['DrugPregnancyCategory'], rdfs_comment: 'A designation that the drug in question has not been assigned a pregnancy category designation by the US FDA.')]
    case FDAnotEvaluated = 'FDAnotEvaluated';
    #[SchemaEnumerationMemberInfo(type: ['ActionStatusType'], rdfs_comment: 'An action that failed to complete. The action\'s error property and the HTTP return code contain more information about the failure.')]
    case FailedActionStatus = 'FailedActionStatus';
    #[SchemaEnumerationMemberInfo(type: ['GenderType'], rdfs_comment: 'The female gender.')]
    case Female = 'Female';
    #[SchemaEnumerationMemberInfo(type: ['PhysicalActivityCategory'], rdfs_comment: 'Physical activity that is engaged in to improve joint and muscle flexibility.')]
    case Flexibility = 'Flexibility';
    #[SchemaEnumerationMemberInfo(type: ['DriveWheelConfigurationValue'], rdfs_comment: 'Four-wheel drive is a transmission layout where the engine primarily drives two wheels with a part-time four-wheel drive capability.')]
    case FourWheelDriveConfiguration = 'FourWheelDriveConfiguration';
    #[SchemaEnumerationMemberInfo(type: ['ReturnFeesEnumeration'], rdfs_comment: 'Specifies that product returns are free of charge for the customer.')]
    case FreeReturn = 'FreeReturn';
    #[SchemaEnumerationMemberInfo(type: ['DayOfWeek'], rdfs_comment: 'The day of the week between Thursday and Saturday.')]
    case Friday = 'Friday';
    #[SchemaEnumerationMemberInfo(type: ['DriveWheelConfigurationValue'], rdfs_comment: 'Front-wheel drive is a transmission layout where the engine drives the front wheels.')]
    case FrontWheelDriveConfiguration = 'FrontWheelDriveConfiguration';
    #[SchemaEnumerationMemberInfo(type: ['FulfillmentTypeEnumeration'], rdfs_comment: 'Fulfillment to a collection point location.')]
    case FulfillmentTypeCollectionPoint = 'FulfillmentTypeCollectionPoint';
    #[SchemaEnumerationMemberInfo(type: ['FulfillmentTypeEnumeration'], rdfs_comment: 'Fulfillment to a customer selected address.')]
    case FulfillmentTypeDelivery = 'FulfillmentTypeDelivery';
    #[SchemaEnumerationMemberInfo(type: ['FulfillmentTypeEnumeration'], rdfs_comment: 'Fulfillment through pick-up drop-off locations.')]
    case FulfillmentTypePickupDropoff = 'FulfillmentTypePickupDropoff';
    #[SchemaEnumerationMemberInfo(type: ['FulfillmentTypeEnumeration'], rdfs_comment: 'Fulfillment through pick-up in a store.')]
    case FulfillmentTypePickupInStore = 'FulfillmentTypePickupInStore';
    #[SchemaEnumerationMemberInfo(type: ['FulfillmentTypeEnumeration'], rdfs_comment: 'Fulfillment to a customer selected address after scheduling with the customer.')]
    case FulfillmentTypeScheduledDelivery = 'FulfillmentTypeScheduledDelivery';
    #[SchemaEnumerationMemberInfo(type: ['GameAvailabilityEnumeration'], rdfs_comment: 'Indicates full game availability.')]
    case FullGameAvailability = 'FullGameAvailability';
    #[SchemaEnumerationMemberInfo(type: ['RefundTypeEnumeration'], rdfs_comment: 'Specifies that a refund can be done in the full amount the customer paid for the product.')]
    case FullRefund = 'FullRefund';
    #[SchemaEnumerationMemberInfo(type: ['InfectiousAgentClass'], rdfs_comment: 'Pathogenic fungus.')]
    case Fungus = 'Fungus';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'A specific branch of medical science that pertains to diagnosis and treatment of disorders of digestive system.')]
    case Gastroenterologic = 'Gastroenterologic';
    #[SchemaEnumerationMemberInfo(type: ['DigitalPlatformEnumeration'], rdfs_comment: 'Represents the generic notion of the Web Platform. More specific codes include <a href="https://schema.org/MobileWebPlatform" target="_blank" rel="noopener noreferrer">MobileWebPlatform</a> and <a href="https://schema.org/DesktopWebPlatform" target="_blank" rel="noopener noreferrer">DesktopWebPlatform</a>, as an incomplete list.')]
    case GenericWebPlatform = 'GenericWebPlatform';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'A specific branch of medical science that pertains to hereditary transmission and the variation of inherited characteristics and disorders.')]
    case Genetic = 'Genetic';
    #[SchemaEnumerationMemberInfo(type: ['PhysicalExam'], rdfs_comment: 'Genitourinary system function assessment with clinical examination.')]
    case Genitourinary = 'Genitourinary';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'A specific branch of medical science that is concerned with the diagnosis and treatment of diseases, debilities and provision of care to the aged.')]
    case Geriatric = 'Geriatric';
    #[SchemaEnumerationMemberInfo(type: ['HealthAspectEnumeration'], rdfs_comment: 'Content that discusses practical and policy aspects for getting access to specific kinds of healthcare (e.g. distribution mechanisms for vaccines).')]
    case GettingAccessHealthAspect = 'GettingAccessHealthAspect';
    #[SchemaEnumerationMemberInfo(type: ['RestrictedDiet'], rdfs_comment: 'A diet exclusive of gluten.')]
    case GlutenFreeDiet = 'GlutenFreeDiet';
    #[SchemaEnumerationMemberInfo(type: ['BookFormatType'], rdfs_comment: 'This type is deprecated: GraphicNovel does not fit the BookFormatType enumeration, as it can appear in multiple formats (e.g., Hardcover, eBook). It is not mutually exclusive and therefore deprecated. Use standard BookFormatType values instead in combination with the SequentialArt.

Book format: GraphicNovel. May represent a bound collection of ComicIssue instances.')]
    case GraphicNovel = 'GraphicNovel';
    #[SchemaEnumerationMemberInfo(type: ['BoardingPolicyType'], rdfs_comment: 'The airline boards by groups based on check-in time, priority, etc.')]
    case GroupBoardingPolicy = 'GroupBoardingPolicy';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'A specific branch of medical science that pertains to the health care of women, particularly in the diagnosis and treatment of disorders affecting the female reproductive system.')]
    case Gynecologic = 'Gynecologic';
    #[SchemaEnumerationMemberInfo(type: ['RestrictedDiet'], rdfs_comment: 'A diet conforming to Islamic dietary practices.')]
    case HalalDiet = 'HalalDiet';
    #[SchemaEnumerationMemberInfo(type: ['BookFormatType'], rdfs_comment: 'A durable, archival-quality book featuring a rigid protective shell made of heavy board wrapped in cloth or paper, designed to withstand heavy use and preservation on a shelf.')]
    case Hardcover = 'Hardcover';
    #[SchemaEnumerationMemberInfo(type: ['PhysicalExam'], rdfs_comment: 'Head assessment with clinical examination.')]
    case Head = 'Head';
    #[SchemaEnumerationMemberInfo(type: ['GovernmentBenefitsType'], rdfs_comment: 'HealthCare: this is a benefit for health care.')]
    case HealthCare = 'HealthCare';
    #[SchemaEnumerationMemberInfo(type: ['AdultOrientedEnumeration'], rdfs_comment: 'Item is a pharmaceutical (e.g., a prescription or OTC drug) or a restricted medical device.')]
    case HealthcareConsideration = 'HealthcareConsideration';
    #[SchemaEnumerationMemberInfo(type: ['ContactPointOption'], rdfs_comment: 'Uses devices to support users with hearing impairments.')]
    case HearingImpairedSupported = 'HearingImpairedSupported';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'A specific branch of medical science that pertains to diagnosis and treatment of disorders of blood and blood producing organs.')]
    case Hematologic = 'Hematologic';
    #[SchemaEnumerationMemberInfo(type: ['RestrictedDiet'], rdfs_comment: 'A diet conforming to Hindu dietary practices, in particular, beef-free.')]
    case HinduDiet = 'HinduDiet';
    #[SchemaEnumerationMemberInfo(type: ['MedicineSystem'], rdfs_comment: 'A system of medicine based on the principle that a disease can be cured by a substance that produces similar symptoms in healthy people.')]
    case Homeopathic = 'Homeopathic';
    #[SchemaEnumerationMemberInfo(type: ['HealthAspectEnumeration'], rdfs_comment: 'Content that discusses and explains how a particular health-related topic works, e.g. in terms of mechanisms and underlying science.')]
    case HowItWorksHealthAspect = 'HowItWorksHealthAspect';
    #[SchemaEnumerationMemberInfo(type: ['HealthAspectEnumeration'], rdfs_comment: 'Information about how or where to find a topic. Also may contain location data that can be used for where to look for help if the topic is observed.')]
    case HowOrWhereHealthAspect = 'HowOrWhereHealthAspect';
    #[SchemaEnumerationMemberInfo(type: ['DigitalPlatformEnumeration'], rdfs_comment: 'Represents the broad notion of iOS-based operating systems.')]
    case IOSPlatform = 'IOSPlatform';
    #[SchemaEnumerationMemberInfo(type: ['ITNonprofitType'], rdfs_comment: 'ITCooperativeCharity: Non-profit type referring to Cooperatives with charitable missions (Ital. Cooperativa Sociale) according to Italian Law 112 of 2017.')]
    case ITCooperativeCharity = 'ITCooperativeCharity';
    #[SchemaEnumerationMemberInfo(type: ['ITNonprofitType'], rdfs_comment: 'ITMutualAidCharity: Non-profit type referring to associations providing financial aid to people need (Ital. Società di mutuo soccorso or SOMS) according to Italian Law 3818 of 1886.')]
    case ITMutualAidCharity = 'ITMutualAidCharity';
    #[SchemaEnumerationMemberInfo(type: ['ITNonprofitType'], rdfs_comment: 'ITSocialCompanyCharity: Non-profit type referring to companies with charitable missions (Ital. Imprese Sociali or IS) according to Italian Law 112 of 2017.')]
    case ITSocialCompanyCharity = 'ITSocialCompanyCharity';
    #[SchemaEnumerationMemberInfo(type: ['ITNonprofitType'], rdfs_comment: 'ITSocialPromotionCharity: Non-profit type referring to associations promoting charitable causes (Ital. Associazioni di promozione sociale or APS) according to Italian Law 383 of 2000.')]
    case ITSocialPromotionCharity = 'ITSocialPromotionCharity';
    #[SchemaEnumerationMemberInfo(type: ['ITNonprofitType'], rdfs_comment: 'ITSportCompanyCharity: Non-profit type referring to Companies that organize sports activities for the public or inscribed members (Ital. Società Sportiva Dilettantistica or SSD) according to Italian Law 289 of 2002.')]
    case ITSportCompanyCharity = 'ITSportCompanyCharity';
    #[SchemaEnumerationMemberInfo(type: ['ITNonprofitType'], rdfs_comment: 'ITVolunteerAssociationCharity: Non-profit type referring to associations organising charitable volunteer activities (Ital. Organizzazioni di volontariato or ODV) according to Italian Law 266 of 1991.')]
    case ITVolunteerAssociationCharity = 'ITVolunteerAssociationCharity';
    #[SchemaEnumerationMemberInfo(type: ['LegalForceStatus'], rdfs_comment: 'Indicates that a legislation is in force.')]
    case InForce = 'InForce';
    #[SchemaEnumerationMemberInfo(type: ['ItemAvailability'], rdfs_comment: 'Indicates that the item is in stock.')]
    case InStock = 'InStock';
    #[SchemaEnumerationMemberInfo(type: ['ItemAvailability'], rdfs_comment: 'Indicates that the item is available only at physical locations.')]
    case InStoreOnly = 'InStoreOnly';
    #[SchemaEnumerationMemberInfo(type: ['PaymentMethodType'], rdfs_comment: 'Payment in advance in some form of shop or kiosk for goods purchased online.')]
    case InStorePrepay = 'InStorePrepay';
    #[SchemaEnumerationMemberInfo(type: ['IncentiveQualifiedExpenseType'], rdfs_comment: 'This incentive applies to goods only.')]
    case IncentiveQualifiedExpenseTypeGoodsOnly = 'IncentiveQualifiedExpenseTypeGoodsOnly';
    #[SchemaEnumerationMemberInfo(type: ['IncentiveQualifiedExpenseType'], rdfs_comment: 'This incentive can apply to either goods or services (or both).')]
    case IncentiveQualifiedExpenseTypeGoodsOrServices = 'IncentiveQualifiedExpenseTypeGoodsOrServices';
    #[SchemaEnumerationMemberInfo(type: ['IncentiveQualifiedExpenseType'], rdfs_comment: 'This incentive applies to services only.')]
    case IncentiveQualifiedExpenseTypeServicesOnly = 'IncentiveQualifiedExpenseTypeServicesOnly';
    #[SchemaEnumerationMemberInfo(type: ['IncentiveQualifiedExpenseType'], rdfs_comment: 'This incentive applies to utility bills.')]
    case IncentiveQualifiedExpenseTypeUtilityBill = 'IncentiveQualifiedExpenseTypeUtilityBill';
    #[SchemaEnumerationMemberInfo(type: ['IncentiveStatus'], rdfs_comment: 'This incentive is currently active.')]
    case IncentiveStatusActive = 'IncentiveStatusActive';
    #[SchemaEnumerationMemberInfo(type: ['IncentiveStatus'], rdfs_comment: 'This incentive is currently being developed, and may become active/retired in the future.')]
    case IncentiveStatusInDevelopment = 'IncentiveStatusInDevelopment';
    #[SchemaEnumerationMemberInfo(type: ['IncentiveStatus'], rdfs_comment: 'This incentive is currently active, but may not be accepting new applicants (e.g. max number of redemptions reached for a year)')]
    case IncentiveStatusOnHold = 'IncentiveStatusOnHold';
    #[SchemaEnumerationMemberInfo(type: ['IncentiveStatus'], rdfs_comment: 'This incentive is not longer available.')]
    case IncentiveStatusRetired = 'IncentiveStatusRetired';
    #[SchemaEnumerationMemberInfo(type: ['IncentiveType'], rdfs_comment: 'An incentive where the recipient can receive additional funding for the purchase/lease of the good/service, which must be paid back.')]
    case IncentiveTypeLoan = 'IncentiveTypeLoan';
    #[SchemaEnumerationMemberInfo(type: ['IncentiveType'], rdfs_comment: 'An incentive that reduces the purchase/lease cost of the good/service in question.')]
    case IncentiveTypeRebateOrSubsidy = 'IncentiveTypeRebateOrSubsidy';
    #[SchemaEnumerationMemberInfo(type: ['IncentiveType'], rdfs_comment: 'An incentive that directly reduces the amount of tax owed by the recipient.')]
    case IncentiveTypeTaxCredit = 'IncentiveTypeTaxCredit';
    #[SchemaEnumerationMemberInfo(type: ['IncentiveType'], rdfs_comment: 'An incentive that reduces the recipient\'s amount of taxable income.')]
    case IncentiveTypeTaxDeduction = 'IncentiveTypeTaxDeduction';
    #[SchemaEnumerationMemberInfo(type: ['IncentiveType'], rdfs_comment: 'An incentive that reduces/exempts the recipient from taxation applicable to the incentivized good/service (e.g. luxury taxes, registration taxes, circulation tax).')]
    case IncentiveTypeTaxWaiver = 'IncentiveTypeTaxWaiver';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'Something in medical science that pertains to infectious diseases, i.e. caused by bacterial, viral, fungal or parasitic infections.')]
    case Infectious = 'Infectious';
    #[SchemaEnumerationMemberInfo(type: ['HealthAspectEnumeration'], rdfs_comment: 'Content discussing ingredients-related aspects of a health topic.')]
    case IngredientsHealthAspect = 'IngredientsHealthAspect';
    #[SchemaEnumerationMemberInfo(type: ['PriceComponentTypeEnumeration'], rdfs_comment: 'Represents the installment pricing component of the total price for an offered product.')]
    case Installment = 'Installment';
    #[SchemaEnumerationMemberInfo(type: ['MedicalTrialDesign'], rdfs_comment: 'An international trial.')]
    case InternationalTrial = 'InternationalTrial';
    #[SchemaEnumerationMemberInfo(type: ['PriceTypeEnumeration'], rdfs_comment: 'Represents the invoice price of an offered product.')]
    case InvoicePrice = 'InvoicePrice';
    #[SchemaEnumerationMemberInfo(type: ['ItemListOrderType'], rdfs_comment: 'An ItemList ordered with lower values listed first.')]
    case ItemListOrderAscending = 'ItemListOrderAscending';
    #[SchemaEnumerationMemberInfo(type: ['ItemListOrderType'], rdfs_comment: 'An ItemList ordered with higher values listed first.')]
    case ItemListOrderDescending = 'ItemListOrderDescending';
    #[SchemaEnumerationMemberInfo(type: ['ItemListOrderType'], rdfs_comment: 'An ItemList ordered with no explicit order.')]
    case ItemListUnordered = 'ItemListUnordered';
    #[SchemaEnumerationMemberInfo(type: ['ReturnMethodEnumeration'], rdfs_comment: 'Specifies that the consumer can keep the product, even when receiving a refund or store credit.')]
    case KeepProduct = 'KeepProduct';
    #[SchemaEnumerationMemberInfo(type: ['RestrictedDiet'], rdfs_comment: 'A diet conforming to Jewish dietary practices.')]
    case KosherDiet = 'KosherDiet';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'A medical science pertaining to chemical, hematological, immunologic, microscopic, or bacteriological diagnostic analyses or research.')]
    case LaboratoryScience = 'LaboratoryScience';
    #[SchemaEnumerationMemberInfo(type: ['MusicReleaseFormatType'], rdfs_comment: 'LaserDiscFormat.')]
    case LaserDiscFormat = 'LaserDiscFormat';
    #[SchemaEnumerationMemberInfo(type: ['SteeringPositionValue'], rdfs_comment: 'The steering position is on the left side of the vehicle (viewed from the main direction of driving).')]
    case LeftHandDriving = 'LeftHandDriving';
    #[SchemaEnumerationMemberInfo(type: ['PhysicalActivityCategory'], rdfs_comment: 'Any physical activity engaged in for recreational purposes. Examples may include ballroom dancing, roller skating, canoeing, fishing, etc.')]
    case LeisureTimeActivity = 'LeisureTimeActivity';
    #[SchemaEnumerationMemberInfo(type: ['ItemAvailability'], rdfs_comment: 'Indicates that the item has limited availability.')]
    case LimitedAvailability = 'LimitedAvailability';
    #[SchemaEnumerationMemberInfo(type: ['UKNonprofitType'], rdfs_comment: 'LimitedByGuaranteeCharity: Non-profit type referring to a charitable company that is limited by guarantee (UK).')]
    case LimitedByGuaranteeCharity = 'LimitedByGuaranteeCharity';
    #[SchemaEnumerationMemberInfo(type: ['PriceTypeEnumeration'], rdfs_comment: 'Represents the list price of an offered product. Typically the same as the <a href="https://schema.org/MSRP" target="_blank" rel="noopener noreferrer">MSRP</a>.')]
    case ListPrice = 'ListPrice';
    #[SchemaEnumerationMemberInfo(type: ['MusicAlbumProductionType'], rdfs_comment: 'LiveAlbum.')]
    case LiveAlbum = 'LiveAlbum';
    #[SchemaEnumerationMemberInfo(type: ['HealthAspectEnumeration'], rdfs_comment: 'Information about coping or life related to the topic.')]
    case LivingWithHealthAspect = 'LivingWithHealthAspect';
    #[SchemaEnumerationMemberInfo(type: ['DeliveryMethod'], rdfs_comment: 'A DeliveryMethod in which an item is made available via locker.')]
    case LockerDelivery = 'LockerDelivery';
    #[SchemaEnumerationMemberInfo(type: ['MedicalObservationalStudyDesign'], rdfs_comment: 'Unlike cross-sectional studies, longitudinal studies track the same people, and therefore the differences observed in those people are less likely to be the result of cultural differences across generations. Longitudinal studies are also used in medicine to uncover predictors of certain diseases.')]
    case Longitudinal = 'Longitudinal';
    #[SchemaEnumerationMemberInfo(type: ['RestrictedDiet'], rdfs_comment: 'A diet focused on reduced calorie intake.')]
    case LowCalorieDiet = 'LowCalorieDiet';
    #[SchemaEnumerationMemberInfo(type: ['RestrictedDiet'], rdfs_comment: 'A diet focused on reduced fat and cholesterol intake.')]
    case LowFatDiet = 'LowFatDiet';
    #[SchemaEnumerationMemberInfo(type: ['RestrictedDiet'], rdfs_comment: 'A diet appropriate for people with lactose intolerance.')]
    case LowLactoseDiet = 'LowLactoseDiet';
    #[SchemaEnumerationMemberInfo(type: ['RestrictedDiet'], rdfs_comment: 'A diet focused on reduced sodium intake.')]
    case LowSaltDiet = 'LowSaltDiet';
    #[SchemaEnumerationMemberInfo(type: ['PhysicalExam'], rdfs_comment: 'Lung and respiratory system clinical examination.')]
    case Lung = 'Lung';
    #[SchemaEnumerationMemberInfo(type: ['MedicalImagingTechnique'], rdfs_comment: 'Magnetic resonance imaging.')]
    case MRI = 'MRI';
    #[SchemaEnumerationMemberInfo(type: ['PriceTypeEnumeration'], rdfs_comment: 'Represents the manufacturer suggested retail price ("MSRP") of an offered product.')]
    case MSRP = 'MSRP';
    #[SchemaEnumerationMemberInfo(type: ['ItemAvailability'], rdfs_comment: 'Indicates that the item is made to order (custom made).')]
    case MadeToOrder = 'MadeToOrder';
    #[SchemaEnumerationMemberInfo(type: ['GenderType'], rdfs_comment: 'The male gender.')]
    case Male = 'Male';
    #[SchemaEnumerationMemberInfo(type: ['HealthAspectEnumeration'], rdfs_comment: 'Related topics may be treated by a Topic.')]
    case MayTreatHealthAspect = 'MayTreatHealthAspect';
    #[SchemaEnumerationMemberInfo(type: ['MedicalAudienceType'], rdfs_comment: 'Medical researchers.')]
    case MedicalResearcher = 'MedicalResearcher';
    #[SchemaEnumerationMemberInfo(type: ['MerchantReturnEnumeration'], rdfs_comment: 'Specifies that there is a finite window for product returns.')]
    case MerchantReturnFiniteReturnWindow = 'MerchantReturnFiniteReturnWindow';
    #[SchemaEnumerationMemberInfo(type: ['MerchantReturnEnumeration'], rdfs_comment: 'Specifies that product returns are not permitted.')]
    case MerchantReturnNotPermitted = 'MerchantReturnNotPermitted';
    #[SchemaEnumerationMemberInfo(type: ['MerchantReturnEnumeration'], rdfs_comment: 'Specifies that there is an unlimited window for product returns.')]
    case MerchantReturnUnlimitedWindow = 'MerchantReturnUnlimitedWindow';
    #[SchemaEnumerationMemberInfo(type: ['MerchantReturnEnumeration'], rdfs_comment: 'Specifies that a product return policy is not provided.')]
    case MerchantReturnUnspecified = 'MerchantReturnUnspecified';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'A nurse-like health profession that deals with pregnancy, childbirth, and the postpartum period (including care of the newborn), besides sexual and reproductive health of women throughout their lives.')]
    case Midwifery = 'Midwifery';
    #[SchemaEnumerationMemberInfo(type: ['PriceTypeEnumeration'], rdfs_comment: 'Represents the minimum advertised price ("MAP") (as dictated by the manufacturer) of an offered product.')]
    case MinimumAdvertisedPrice = 'MinimumAdvertisedPrice';
    #[SchemaEnumerationMemberInfo(type: ['IPTCDigitalSourceEnumeration'], rdfs_comment: 'Content coded as \'<a href="https://cv.iptc.org/newscodes/digitalsourcetype/minorHumanEdits">minor human edits</a>\' using the IPTC <a href="https://cv.iptc.org/newscodes/digitalsourcetype/">digital source type</a> vocabulary.')]
    case MinorHumanEditsDigitalSource = 'MinorHumanEditsDigitalSource';
    #[SchemaEnumerationMemberInfo(type: ['HealthAspectEnumeration'], rdfs_comment: 'Content about common misconceptions and myths that are related to a topic.')]
    case MisconceptionsHealthAspect = 'MisconceptionsHealthAspect';
    #[SchemaEnumerationMemberInfo(type: ['EventAttendanceModeEnumeration'], rdfs_comment: 'MixedEventAttendanceMode - an event that is conducted as a combination of both offline and online modes.')]
    case MixedEventAttendanceMode = 'MixedEventAttendanceMode';
    #[SchemaEnumerationMemberInfo(type: ['MusicAlbumProductionType'], rdfs_comment: 'MixtapeAlbum.')]
    case MixtapeAlbum = 'MixtapeAlbum';
    #[SchemaEnumerationMemberInfo(type: ['DigitalPlatformEnumeration'], rdfs_comment: 'Represents the broad notion of \'mobile\' browsers as a Web Platform.')]
    case MobileWebPlatform = 'MobileWebPlatform';
    #[SchemaEnumerationMemberInfo(type: ['DayOfWeek'], rdfs_comment: 'The day of the week between Sunday and Tuesday.')]
    case Monday = 'Monday';
    #[SchemaEnumerationMemberInfo(type: ['MedicalTrialDesign'], rdfs_comment: 'A trial that takes place at multiple centers.')]
    case MultiCenterTrial = 'MultiCenterTrial';
    #[SchemaEnumerationMemberInfo(type: ['IPTCDigitalSourceEnumeration'], rdfs_comment: 'Content coded as \'<a href="https://cv.iptc.org/newscodes/digitalsourcetype/algorithmicMedia">algorithmic media</a>\' using the IPTC <a href="https://cv.iptc.org/newscodes/digitalsourcetype/">digital source type</a> vocabulary.')]
    case MultiFrameComputationalCaptureDigitalSource = 'MultiFrameComputationalCaptureDigitalSource';
    #[SchemaEnumerationMemberInfo(type: ['GamePlayMode'], rdfs_comment: 'Play mode: MultiPlayer. Requiring or allowing multiple human players to play simultaneously.')]
    case MultiPlayer = 'MultiPlayer';
    #[SchemaEnumerationMemberInfo(type: ['InfectiousAgentClass'], rdfs_comment: 'Multicellular parasite that causes an infection.')]
    case MulticellularParasite = 'MulticellularParasite';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'A specific branch of medical science that pertains to diagnosis and treatment of disorders of muscles, ligaments and skeletal system.')]
    case Musculoskeletal = 'Musculoskeletal';
    #[SchemaEnumerationMemberInfo(type: ['PhysicalExam'], rdfs_comment: 'Musculoskeletal system clinical examination.')]
    case MusculoskeletalExam = 'MusculoskeletalExam';
    #[SchemaEnumerationMemberInfo(type: ['AdultOrientedEnumeration'], rdfs_comment: 'Item is a narcotic as defined by the <a href="https://www.incb.org/incb/en/narcotic-drugs/Yellowlist/yellow-list.html" target="_blank" rel="noopener noreferrer">1961 UN convention</a>, for example marijuana or heroin.')]
    case NarcoticConsideration = 'NarcoticConsideration';
    #[SchemaEnumerationMemberInfo(type: ['PhysicalExam'], rdfs_comment: 'Neck assessment with clinical examination.')]
    case Neck = 'Neck';
    #[SchemaEnumerationMemberInfo(type: ['IPTCDigitalSourceEnumeration'], rdfs_comment: 'Content coded as \'<a href="https://cv.iptc.org/newscodes/digitalsourcetype/negativeFilm">negative film</a></a>\' using the IPTC <a href="https://cv.iptc.org/newscodes/digitalsourcetype/">digital source type</a> vocabulary.')]
    case NegativeFilmDigitalSource = 'NegativeFilmDigitalSource';
    #[SchemaEnumerationMemberInfo(type: ['PhysicalExam'], rdfs_comment: 'Neurological system clinical examination.')]
    case Neuro = 'Neuro';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'A specific branch of medical science that studies the nerves and nervous system and its respective disease states.')]
    case Neurologic = 'Neurologic';
    #[SchemaEnumerationMemberInfo(type: ['OfferItemCondition'], rdfs_comment: 'Indicates that the item is new.')]
    case NewCondition = 'NewCondition';
    #[SchemaEnumerationMemberInfo(type: ['MedicalProcedureType'], rdfs_comment: 'A type of medical procedure that involves noninvasive techniques.')]
    case NoninvasiveProcedure = 'NoninvasiveProcedure';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501a: Non-profit type referring to Farmers’ Cooperative Associations.')]
    case Nonprofit501a = 'Nonprofit501a';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501c1: Non-profit type referring to Corporations Organized Under Act of Congress, including Federal Credit Unions and National Farm Loan Associations.')]
    case Nonprofit501c1 = 'Nonprofit501c1';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501c10: Non-profit type referring to Domestic Fraternal Societies and Associations.')]
    case Nonprofit501c10 = 'Nonprofit501c10';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501c11: Non-profit type referring to Teachers\' Retirement Fund Associations.')]
    case Nonprofit501c11 = 'Nonprofit501c11';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501c12: Non-profit type referring to Benevolent Life Insurance Associations, Mutual Ditch or Irrigation Companies, Mutual or Cooperative Telephone Companies.')]
    case Nonprofit501c12 = 'Nonprofit501c12';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501c13: Non-profit type referring to Cemetery Companies.')]
    case Nonprofit501c13 = 'Nonprofit501c13';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501c14: Non-profit type referring to State-Chartered Credit Unions, Mutual Reserve Funds.')]
    case Nonprofit501c14 = 'Nonprofit501c14';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501c15: Non-profit type referring to Mutual Insurance Companies or Associations.')]
    case Nonprofit501c15 = 'Nonprofit501c15';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501c16: Non-profit type referring to Cooperative Organizations to Finance Crop Operations.')]
    case Nonprofit501c16 = 'Nonprofit501c16';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501c17: Non-profit type referring to Supplemental Unemployment Benefit Trusts.')]
    case Nonprofit501c17 = 'Nonprofit501c17';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501c18: Non-profit type referring to Employee Funded Pension Trust (created before 25 June 1959).')]
    case Nonprofit501c18 = 'Nonprofit501c18';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501c19: Non-profit type referring to Post or Organization of Past or Present Members of the Armed Forces.')]
    case Nonprofit501c19 = 'Nonprofit501c19';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501c2: Non-profit type referring to Title-holding Corporations for Exempt Organizations.')]
    case Nonprofit501c2 = 'Nonprofit501c2';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501c20: Non-profit type referring to Group Legal Services Plan Organizations.')]
    case Nonprofit501c20 = 'Nonprofit501c20';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501c21: Non-profit type referring to Black Lung Benefit Trusts.')]
    case Nonprofit501c21 = 'Nonprofit501c21';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501c22: Non-profit type referring to Withdrawal Liability Payment Funds.')]
    case Nonprofit501c22 = 'Nonprofit501c22';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501c23: Non-profit type referring to Veterans Organizations.')]
    case Nonprofit501c23 = 'Nonprofit501c23';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501c24: Non-profit type referring to Section 4049 ERISA Trusts.')]
    case Nonprofit501c24 = 'Nonprofit501c24';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501c25: Non-profit type referring to Real Property Title-Holding Corporations or Trusts with Multiple Parents.')]
    case Nonprofit501c25 = 'Nonprofit501c25';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501c26: Non-profit type referring to State-Sponsored Organizations Providing Health Coverage for High-Risk Individuals.')]
    case Nonprofit501c26 = 'Nonprofit501c26';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501c27: Non-profit type referring to State-Sponsored Workers\' Compensation Reinsurance Organizations.')]
    case Nonprofit501c27 = 'Nonprofit501c27';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501c28: Non-profit type referring to National Railroad Retirement Investment Trusts.')]
    case Nonprofit501c28 = 'Nonprofit501c28';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501c3: Non-profit type referring to Religious, Educational, Charitable, Scientific, Literary, Testing for Public Safety, Fostering National or International Amateur Sports Competition, or Prevention of Cruelty to Children or Animals Organizations.')]
    case Nonprofit501c3 = 'Nonprofit501c3';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501c4: Non-profit type referring to Civic Leagues, Social Welfare Organizations, and Local Associations of Employees.')]
    case Nonprofit501c4 = 'Nonprofit501c4';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501c5: Non-profit type referring to Labor, Agricultural and Horticultural Organizations.')]
    case Nonprofit501c5 = 'Nonprofit501c5';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501c6: Non-profit type referring to Business Leagues, Chambers of Commerce, Real Estate Boards.')]
    case Nonprofit501c6 = 'Nonprofit501c6';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501c7: Non-profit type referring to Social and Recreational Clubs.')]
    case Nonprofit501c7 = 'Nonprofit501c7';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501c8: Non-profit type referring to Fraternal Beneficiary Societies and Associations.')]
    case Nonprofit501c8 = 'Nonprofit501c8';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501c9: Non-profit type referring to Voluntary Employee Beneficiary Associations.')]
    case Nonprofit501c9 = 'Nonprofit501c9';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501d: Non-profit type referring to Religious and Apostolic Associations.')]
    case Nonprofit501d = 'Nonprofit501d';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501e: Non-profit type referring to Cooperative Hospital Service Organizations.')]
    case Nonprofit501e = 'Nonprofit501e';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501f: Non-profit type referring to Cooperative Service Organizations.')]
    case Nonprofit501f = 'Nonprofit501f';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501k: Non-profit type referring to Child Care Organizations.')]
    case Nonprofit501k = 'Nonprofit501k';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501n: Non-profit type referring to Charitable Risk Pools.')]
    case Nonprofit501n = 'Nonprofit501n';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit501q: Non-profit type referring to Credit Counseling Organizations.')]
    case Nonprofit501q = 'Nonprofit501q';
    #[SchemaEnumerationMemberInfo(type: ['USNonprofitType'], rdfs_comment: 'Nonprofit527: Non-profit type referring to political organizations.')]
    case Nonprofit527 = 'Nonprofit527';
    #[SchemaEnumerationMemberInfo(type: ['NLNonprofitType'], rdfs_comment: 'NonprofitANBI: Non-profit type referring to a Public Benefit Organization (NL).')]
    case NonprofitANBI = 'NonprofitANBI';
    #[SchemaEnumerationMemberInfo(type: ['NLNonprofitType'], rdfs_comment: 'NonprofitSBBI: Non-profit type referring to a Social Interest Promoting Institution (NL).')]
    case NonprofitSBBI = 'NonprofitSBBI';
    #[SchemaEnumerationMemberInfo(type: ['PhysicalExam'], rdfs_comment: 'Nose function assessment with clinical examination.')]
    case Nose = 'Nose';
    #[SchemaEnumerationMemberInfo(type: ['LegalForceStatus'], rdfs_comment: 'Indicates that a legislation is currently not in force.')]
    case NotInForce = 'NotInForce';
    #[SchemaEnumerationMemberInfo(type: ['MedicalStudyStatus'], rdfs_comment: 'Not yet recruiting.')]
    case NotYetRecruiting = 'NotYetRecruiting';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'A health profession of a person formally educated and trained in the care of the sick or infirm person.')]
    case Nursing = 'Nursing';
    #[SchemaEnumerationMemberInfo(type: ['DrugPrescriptionStatus'], rdfs_comment: 'The character of a medical substance, typically a medicine, of being available over the counter or not.')]
    case OTC = 'OTC';
    #[SchemaEnumerationMemberInfo(type: ['MedicalObservationalStudyDesign'], rdfs_comment: 'An observational study design.')]
    case Observational = 'Observational';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'A specific branch of medical science that specializes in the care of women during the prenatal and postnatal care and with the delivery of the child.')]
    case Obstetric = 'Obstetric';
    #[SchemaEnumerationMemberInfo(type: ['PhysicalActivityCategory'], rdfs_comment: 'Any physical activity engaged in for job-related purposes. Examples may include waiting tables, maid service, carrying a mailbag, picking fruits or vegetables, construction work, etc.')]
    case OccupationalActivity = 'OccupationalActivity';
    #[SchemaEnumerationMemberInfo(type: ['LegalValueLevel'], rdfs_comment: 'All the documents published by an official publisher should have at least the legal value level "OfficialLegalValue". This indicates that the document was published by an organisation with the public task of making it available (e.g. a consolidated version of a EU directive published by the EU Office of Publications).')]
    case OfficialLegalValue = 'OfficialLegalValue';
    #[SchemaEnumerationMemberInfo(type: ['EventAttendanceModeEnumeration'], rdfs_comment: 'OfflineEventAttendanceMode - an event that is primarily conducted offline.')]
    case OfflineEventAttendanceMode = 'OfflineEventAttendanceMode';
    #[SchemaEnumerationMemberInfo(type: ['GameServerStatus'], rdfs_comment: 'Game server status: OfflinePermanently. Server is offline and not available.')]
    case OfflinePermanently = 'OfflinePermanently';
    #[SchemaEnumerationMemberInfo(type: ['GameServerStatus'], rdfs_comment: 'Game server status: OfflineTemporarily. Server is offline now but it can be online soon.')]
    case OfflineTemporarily = 'OfflineTemporarily';
    #[SchemaEnumerationMemberInfo(type: ['DeliveryMethod'], rdfs_comment: 'A DeliveryMethod in which an item is collected on site, e.g. in a store or at a box office.')]
    case OnSitePickup = 'OnSitePickup';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'A specific branch of medical science that deals with benign and malignant tumors, including the study of their development, diagnosis, treatment and prevention.')]
    case Oncologic = 'Oncologic';
    #[SchemaEnumerationMemberInfo(type: ['GovernmentBenefitsType'], rdfs_comment: 'OneTimePayments: this is a benefit for one-time payments for individuals.')]
    case OneTimePayments = 'OneTimePayments';
    #[SchemaEnumerationMemberInfo(type: ['GameServerStatus'], rdfs_comment: 'Game server status: Online. Server is available.')]
    case Online = 'Online';
    #[SchemaEnumerationMemberInfo(type: ['EventAttendanceModeEnumeration'], rdfs_comment: 'OnlineEventAttendanceMode - an event that is primarily conducted online.')]
    case OnlineEventAttendanceMode = 'OnlineEventAttendanceMode';
    #[SchemaEnumerationMemberInfo(type: ['GameServerStatus'], rdfs_comment: 'Game server status: OnlineFull. Server is online but unavailable. The maximum number of players has reached.')]
    case OnlineFull = 'OnlineFull';
    #[SchemaEnumerationMemberInfo(type: ['ItemAvailability'], rdfs_comment: 'Indicates that the item is available only online.')]
    case OnlineOnly = 'OnlineOnly';
    #[SchemaEnumerationMemberInfo(type: ['MedicalTrialDesign'], rdfs_comment: 'A trial design in which the researcher knows the full details of the treatment, and so does the patient.')]
    case OpenTrial = 'OpenTrial';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'The science or practice of testing visual acuity and prescribing corrective lenses.')]
    case Optometric = 'Optometric';
    #[SchemaEnumerationMemberInfo(type: ['OrderStatus'], rdfs_comment: 'OrderStatus representing cancellation of an order.')]
    case OrderCancelled = 'OrderCancelled';
    #[SchemaEnumerationMemberInfo(type: ['OrderStatus'], rdfs_comment: 'OrderStatus representing successful delivery of an order.')]
    case OrderDelivered = 'OrderDelivered';
    #[SchemaEnumerationMemberInfo(type: ['OrderStatus'], rdfs_comment: 'OrderStatus representing that an order is in transit.')]
    case OrderInTransit = 'OrderInTransit';
    #[SchemaEnumerationMemberInfo(type: ['OrderStatus'], rdfs_comment: 'OrderStatus representing that payment is due on an order.')]
    case OrderPaymentDue = 'OrderPaymentDue';
    #[SchemaEnumerationMemberInfo(type: ['OrderStatus'], rdfs_comment: 'OrderStatus representing availability of an order for pickup.')]
    case OrderPickupAvailable = 'OrderPickupAvailable';
    #[SchemaEnumerationMemberInfo(type: ['OrderStatus'], rdfs_comment: 'OrderStatus representing that there is a problem with the order.')]
    case OrderProblem = 'OrderProblem';
    #[SchemaEnumerationMemberInfo(type: ['OrderStatus'], rdfs_comment: 'OrderStatus representing that an order is being processed.')]
    case OrderProcessing = 'OrderProcessing';
    #[SchemaEnumerationMemberInfo(type: ['OrderStatus'], rdfs_comment: 'OrderStatus representing that an order has been returned.')]
    case OrderReturned = 'OrderReturned';
    #[SchemaEnumerationMemberInfo(type: ['MediaManipulationRatingEnumeration'], rdfs_comment: 'Content coded \'as original media content\' in a <a href="https://schema.org/MediaReview" target="_blank" rel="noopener noreferrer">MediaReview</a>, considered in the context of how it was published or shared.

For a <a href="https://schema.org/VideoObject" target="_blank" rel="noopener noreferrer">VideoObject</a> to be \'original\': No evidence the footage has been misleadingly altered or manipulated, though it may contain false or misleading claims.

For an <a href="https://schema.org/ImageObject" target="_blank" rel="noopener noreferrer">ImageObject</a> to be \'original\': No evidence the image has been misleadingly altered or manipulated, though it may still contain false or misleading claims.

For an <a href="https://schema.org/ImageObject" target="_blank" rel="noopener noreferrer">ImageObject</a> with embedded text to be \'original\': No evidence the image has been misleadingly altered or manipulated, though it may still contain false or misleading claims.

For an <a href="https://schema.org/AudioObject" target="_blank" rel="noopener noreferrer">AudioObject</a> to be \'original\': No evidence the audio has been misleadingly altered or manipulated, though it may contain false or misleading claims.')]
    case OriginalMediaContent = 'OriginalMediaContent';
    #[SchemaEnumerationMemberInfo(type: ['ReturnFeesEnumeration'], rdfs_comment: 'Specifies that the customer must pay the original shipping costs when returning a product.')]
    case OriginalShippingFees = 'OriginalShippingFees';
    #[SchemaEnumerationMemberInfo(type: ['MedicineSystem'], rdfs_comment: 'A system of medicine focused on promoting the body\'s innate ability to heal itself.')]
    case Osteopathic = 'Osteopathic';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'A specific branch of medical science that is concerned with the ear, nose and throat and their respective disease states.')]
    case Otolaryngologic = 'Otolaryngologic';
    #[SchemaEnumerationMemberInfo(type: ['ItemAvailability'], rdfs_comment: 'Indicates that the item is out of stock.')]
    case OutOfStock = 'OutOfStock';
    #[SchemaEnumerationMemberInfo(type: ['HealthAspectEnumeration'], rdfs_comment: 'Overview of the content. Contains a summarized view of the topic with the most relevant information for an introduction.')]
    case OverviewHealthAspect = 'OverviewHealthAspect';
    #[SchemaEnumerationMemberInfo(type: ['MedicalImagingTechnique'], rdfs_comment: 'Positron emission tomography imaging.')]
    case PET = 'PET';
    #[SchemaEnumerationMemberInfo(type: ['GovernmentBenefitsType'], rdfs_comment: 'PaidLeave: this is a benefit for paid leave.')]
    case PaidLeave = 'PaidLeave';
    #[SchemaEnumerationMemberInfo(type: ['BookFormatType'], rdfs_comment: 'A small, unbound or stapled booklet consisting of few pages with a flexible paper cover, designed for the economical distribution of focused information on a single subject.')]
    case Pamphlet = 'Pamphlet';
    #[SchemaEnumerationMemberInfo(type: ['BookFormatType'], rdfs_comment: 'A flexible, lightweight book bound with a thick paper or cardstock cover and glued spine, prioritizing portability and affordability over long-term durability.')]
    case Paperback = 'Paperback';
    #[SchemaEnumerationMemberInfo(type: ['DeliveryMethod'], rdfs_comment: 'A private parcel service as the delivery mode available for a certain offer.\\n\\nCommonly used values:\\n\\n* http://purl.org/goodrelations/v1#DHL\\n* http://purl.org/goodrelations/v1#FederalExpress\\n* http://purl.org/goodrelations/v1#UPS')]
    case ParcelService = 'ParcelService';
    #[SchemaEnumerationMemberInfo(type: ['GovernmentBenefitsType'], rdfs_comment: 'ParentalSupport: this is a benefit for parental support.')]
    case ParentalSupport = 'ParentalSupport';
    #[SchemaEnumerationMemberInfo(type: ['MapCategoryType'], rdfs_comment: 'A parking map.')]
    case ParkingMap = 'ParkingMap';
    #[SchemaEnumerationMemberInfo(type: ['LegalForceStatus'], rdfs_comment: 'Indicates that parts of the legislation are in force, and parts are not.')]
    case PartiallyInForce = 'PartiallyInForce';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'A specific branch of medical science that is concerned with the study of the cause, origin and nature of a disease state, including its consequences as a result of manifestation of the disease. In clinical care, the term is used to designate a branch of medicine using laboratory tests to diagnose and determine the prognostic significance of illness.')]
    case Pathology = 'Pathology';
    #[SchemaEnumerationMemberInfo(type: ['HealthAspectEnumeration'], rdfs_comment: 'Content about the real life experience of patients or people that have lived a similar experience about the topic. May be forums, topics, Q-and-A and related material.')]
    case PatientExperienceHealthAspect = 'PatientExperienceHealthAspect';
    #[SchemaEnumerationMemberInfo(type: ['PaymentStatusType'], rdfs_comment: 'An automatic payment system is in place and will be used.')]
    case PaymentAutomaticallyApplied = 'PaymentAutomaticallyApplied';
    #[SchemaEnumerationMemberInfo(type: ['PaymentStatusType'], rdfs_comment: 'The payment has been received and processed.')]
    case PaymentComplete = 'PaymentComplete';
    #[SchemaEnumerationMemberInfo(type: ['PaymentStatusType'], rdfs_comment: 'The payee received the payment, but it was declined for some reason.')]
    case PaymentDeclined = 'PaymentDeclined';
    #[SchemaEnumerationMemberInfo(type: ['PaymentStatusType'], rdfs_comment: 'The payment is due, but still within an acceptable time to be received.')]
    case PaymentDue = 'PaymentDue';
    #[SchemaEnumerationMemberInfo(type: ['PaymentStatusType'], rdfs_comment: 'The payment is due and considered late.')]
    case PaymentPastDue = 'PaymentPastDue';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'A specific branch of medical science that specializes in the care of infants, children and adolescents.')]
    case Pediatric = 'Pediatric';
    #[SchemaEnumerationMemberInfo(type: ['MedicalProcedureType'], rdfs_comment: 'A type of medical procedure that involves percutaneous techniques, where access to organs or tissue is achieved via needle-puncture of the skin. For example, catheter-based procedures like stent delivery.')]
    case PercutaneousProcedure = 'PercutaneousProcedure';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'The practice or art and science of preparing and dispensing drugs and medicines.')]
    case PharmacySpecialty = 'PharmacySpecialty';
    #[SchemaEnumerationMemberInfo(type: ['PaymentMethodType'], rdfs_comment: 'Payment by billing via the phone carrier.')]
    case PhoneCarrierPayment = 'PhoneCarrierPayment';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'The practice of treatment of disease, injury, or deformity by physical methods such as massage, heat treatment, and exercise rather than by drugs or surgery.')]
    case Physiotherapy = 'Physiotherapy';
    #[SchemaEnumerationMemberInfo(type: ['MedicalTrialDesign'], rdfs_comment: 'A placebo-controlled trial design.')]
    case PlaceboControlledTrial = 'PlaceboControlledTrial';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'A specific branch of medical science that pertains to therapeutic or cosmetic repair or re-formation of missing, injured or malformed tissues or body parts by manual and instrumental means.')]
    case PlasticSurgery = 'PlasticSurgery';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'Podiatry is the care of the human foot, especially the diagnosis and treatment of foot disorders.')]
    case Podiatric = 'Podiatric';
    #[SchemaEnumerationMemberInfo(type: ['IPTCDigitalSourceEnumeration'], rdfs_comment: 'Content coded as \'<a href="https://cv.iptc.org/newscodes/digitalsourcetype/positiveFilm">positive film</a>\' using the IPTC <a href="https://cv.iptc.org/newscodes/digitalsourcetype/">digital source type</a> vocabulary.')]
    case PositiveFilmDigitalSource = 'PositiveFilmDigitalSource';
    #[SchemaEnumerationMemberInfo(type: ['ActionStatusType'], rdfs_comment: 'A description of an action that is supported.')]
    case PotentialActionStatus = 'PotentialActionStatus';
    #[SchemaEnumerationMemberInfo(type: ['ItemAvailability'], rdfs_comment: 'Indicates that the item is available for pre-order.')]
    case PreOrder = 'PreOrder';
    #[SchemaEnumerationMemberInfo(type: ['ItemAvailability'], rdfs_comment: 'Indicates that the item is available for ordering and delivery before general availability.')]
    case PreSale = 'PreSale';
    #[SchemaEnumerationMemberInfo(type: ['HealthAspectEnumeration'], rdfs_comment: 'Content discussing pregnancy-related aspects of a health topic.')]
    case PregnancyHealthAspect = 'PregnancyHealthAspect';
    #[SchemaEnumerationMemberInfo(type: ['DrugPrescriptionStatus'], rdfs_comment: 'Available by prescription only.')]
    case PrescriptionOnly = 'PrescriptionOnly';
    #[SchemaEnumerationMemberInfo(type: ['HealthAspectEnumeration'], rdfs_comment: 'Information about actions or measures that can be taken to avoid getting the topic or reaching a critical situation related to the topic.')]
    case PreventionHealthAspect = 'PreventionHealthAspect';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'The medical care by a physician, or other health-care professional, who is the patient\'s first contact with the health-care system and who may recommend a specialist if necessary.')]
    case PrimaryCare = 'PrimaryCare';
    #[SchemaEnumerationMemberInfo(type: ['IPTCDigitalSourceEnumeration'], rdfs_comment: 'Content coded as \'<a href="https://cv.iptc.org/newscodes/digitalsourcetype/print">print</a>\' using the IPTC <a href="https://cv.iptc.org/newscodes/digitalsourcetype/">digital source type</a> vocabulary.')]
    case PrintDigitalSource = 'PrintDigitalSource';
    #[SchemaEnumerationMemberInfo(type: ['InfectiousAgentClass'], rdfs_comment: 'A prion is an infectious agent composed of protein in a misfolded form.')]
    case Prion = 'Prion';
    #[SchemaEnumerationMemberInfo(type: ['HealthAspectEnumeration'], rdfs_comment: 'Typical progression and happenings of life course of the topic.')]
    case PrognosisHealthAspect = 'PrognosisHealthAspect';
    #[SchemaEnumerationMemberInfo(type: ['InfectiousAgentClass'], rdfs_comment: 'Single-celled organism that causes an infection.')]
    case Protozoa = 'Protozoa';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'A specific branch of medical science that is concerned with the study, treatment, and prevention of mental illness, using both medical and psychological therapies.')]
    case Psychiatric = 'Psychiatric';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'Branch of medicine that pertains to the health services to improve and protect community health, especially epidemiology, sanitation, immunization, and preventive medicine.')]
    case PublicHealth = 'PublicHealth';
    #[SchemaEnumerationMemberInfo(type: ['DayOfWeek'], rdfs_comment: 'This stands for any day that is a public holiday; it is a placeholder for all official public holidays in some particular location. While not technically a "day of the week", it can be used with <a href="https://schema.org/OpeningHoursSpecification" target="_blank" rel="noopener noreferrer">OpeningHoursSpecification</a>. In the context of an opening hours specification it can be used to indicate opening hours on public holidays, overriding general opening hours for the day of the week on which a public holiday occurs.')]
    case PublicHolidays = 'PublicHolidays';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'A specific branch of medical science that pertains to the study of the respiratory system and its respective disease states.')]
    case Pulmonary = 'Pulmonary';
    #[SchemaEnumerationMemberInfo(type: ['PurchaseType'], rdfs_comment: 'This is a lease of an item.')]
    case PurchaseTypeLease = 'PurchaseTypeLease';
    #[SchemaEnumerationMemberInfo(type: ['PurchaseType'], rdfs_comment: 'This is a purchase of a new item.')]
    case PurchaseTypeNewPurchase = 'PurchaseTypeNewPurchase';
    #[SchemaEnumerationMemberInfo(type: ['PurchaseType'], rdfs_comment: 'This is a trade-in for an item.')]
    case PurchaseTypeTradeIn = 'PurchaseTypeTradeIn';
    #[SchemaEnumerationMemberInfo(type: ['PurchaseType'], rdfs_comment: 'This is a purchase of a used item.')]
    case PurchaseTypeUsedPurchase = 'PurchaseTypeUsedPurchase';
    #[SchemaEnumerationMemberInfo(type: ['MedicalImagingTechnique', 'MedicalSpecialty'], rdfs_comment: 'Radiography is an imaging technique that uses electromagnetic radiation other than visible light, especially X-rays, to view the internal structure of a non-uniformly composed and opaque object such as the human body.')]
    case Radiography = 'Radiography';
    #[SchemaEnumerationMemberInfo(type: ['MedicalTrialDesign'], rdfs_comment: 'A randomized trial design.')]
    case RandomizedTrial = 'RandomizedTrial';
    #[SchemaEnumerationMemberInfo(type: ['DigitalDocumentPermissionType'], rdfs_comment: 'Permission to read or view the document.')]
    case ReadPermission = 'ReadPermission';
    #[SchemaEnumerationMemberInfo(type: ['DriveWheelConfigurationValue'], rdfs_comment: 'Real-wheel drive is a transmission layout where the engine drives the rear wheels.')]
    case RearWheelDriveConfiguration = 'RearWheelDriveConfiguration';
    #[SchemaEnumerationMemberInfo(type: ['MedicalStudyStatus'], rdfs_comment: 'Recruiting participants.')]
    case Recruiting = 'Recruiting';
    #[SchemaEnumerationMemberInfo(type: ['AdultOrientedEnumeration'], rdfs_comment: 'A general code for cases where relevance to children is reduced, e.g. adult education, mortgages, retirement-related products, etc.')]
    case ReducedRelevanceForChildrenConsideration = 'ReducedRelevanceForChildrenConsideration';
    #[SchemaEnumerationMemberInfo(type: ['OfferItemCondition'], rdfs_comment: 'Indicates that the item is refurbished.')]
    case RefurbishedCondition = 'RefurbishedCondition';
    #[SchemaEnumerationMemberInfo(type: ['MedicalObservationalStudyDesign'], rdfs_comment: 'A registry-based study design.')]
    case Registry = 'Registry';
    #[SchemaEnumerationMemberInfo(type: ['PriceTypeEnumeration'], rdfs_comment: 'Represents the regular price of an offered product. This is usually the advertised price before a temporary sale. Once the sale period ends the advertised price will go back to the regular price.')]
    case RegularPrice = 'RegularPrice';
    #[SchemaEnumerationMemberInfo(type: ['DrugCostCategory'], rdfs_comment: 'The drug\'s cost represents the maximum reimbursement paid by an insurer for the drug.')]
    case ReimbursementCap = 'ReimbursementCap';
    #[SchemaEnumerationMemberInfo(type: ['HealthAspectEnumeration'], rdfs_comment: 'Other prominent or relevant topics tied to the main topic.')]
    case RelatedTopicsHealthAspect = 'RelatedTopicsHealthAspect';
    #[SchemaEnumerationMemberInfo(type: ['MusicAlbumProductionType'], rdfs_comment: 'RemixAlbum.')]
    case RemixAlbum = 'RemixAlbum';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'A specific branch of medical science that pertains to the study of the kidneys and its respective disease states.')]
    case Renal = 'Renal';
    #[SchemaEnumerationMemberInfo(type: ['CarUsageType'], rdfs_comment: 'Indicates the usage of the vehicle as a rental car.')]
    case RentalVehicleUsage = 'RentalVehicleUsage';
    #[SchemaEnumerationMemberInfo(type: ['ReservationStatusType'], rdfs_comment: 'The status for a previously confirmed reservation that is now cancelled.')]
    case ReservationCancelled = 'ReservationCancelled';
    #[SchemaEnumerationMemberInfo(type: ['ReservationStatusType'], rdfs_comment: 'The status of a confirmed reservation.')]
    case ReservationConfirmed = 'ReservationConfirmed';
    #[SchemaEnumerationMemberInfo(type: ['ReservationStatusType'], rdfs_comment: 'The status of a reservation on hold pending an update like credit card number or flight changes.')]
    case ReservationHold = 'ReservationHold';
    #[SchemaEnumerationMemberInfo(type: ['ReservationStatusType'], rdfs_comment: 'The status of a reservation when a request has been sent, but not confirmed.')]
    case ReservationPending = 'ReservationPending';
    #[SchemaEnumerationMemberInfo(type: ['ItemAvailability'], rdfs_comment: 'Indicates that the item is reserved and therefore not available.')]
    case Reserved = 'Reserved';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'The therapy that is concerned with the maintenance or improvement of respiratory function (as in patients with pulmonary disease).')]
    case RespiratoryTherapy = 'RespiratoryTherapy';
    #[SchemaEnumerationMemberInfo(type: ['ReturnFeesEnumeration'], rdfs_comment: 'Specifies that the customer must pay a restocking fee when returning a product.')]
    case RestockingFees = 'RestockingFees';
    #[SchemaEnumerationMemberInfo(type: ['MedicalStudyStatus'], rdfs_comment: 'Results are available.')]
    case ResultsAvailable = 'ResultsAvailable';
    #[SchemaEnumerationMemberInfo(type: ['MedicalStudyStatus'], rdfs_comment: 'Results are not available.')]
    case ResultsNotAvailable = 'ResultsNotAvailable';
    #[SchemaEnumerationMemberInfo(type: ['DrugCostCategory'], rdfs_comment: 'The drug\'s cost represents the retail cost of the drug.')]
    case Retail = 'Retail';
    #[SchemaEnumerationMemberInfo(type: ['ReturnMethodEnumeration'], rdfs_comment: 'Specifies that product returns must be made at a kiosk.')]
    case ReturnAtKiosk = 'ReturnAtKiosk';
    #[SchemaEnumerationMemberInfo(type: ['ReturnMethodEnumeration'], rdfs_comment: 'Specifies that product returns must be done by mail.')]
    case ReturnByMail = 'ReturnByMail';
    #[SchemaEnumerationMemberInfo(type: ['ReturnFeesEnumeration'], rdfs_comment: 'Specifies that product returns must be paid for, and are the responsibility of, the customer.')]
    case ReturnFeesCustomerResponsibility = 'ReturnFeesCustomerResponsibility';
    #[SchemaEnumerationMemberInfo(type: ['ReturnMethodEnumeration'], rdfs_comment: 'Specifies that product returns must be made in a store.')]
    case ReturnInStore = 'ReturnInStore';
    #[SchemaEnumerationMemberInfo(type: ['ReturnLabelSourceEnumeration'], rdfs_comment: 'Indicated that creating a return label is the responsibility of the customer.')]
    case ReturnLabelCustomerResponsibility = 'ReturnLabelCustomerResponsibility';
    #[SchemaEnumerationMemberInfo(type: ['ReturnLabelSourceEnumeration'], rdfs_comment: 'Indicated that a return label must be downloaded and printed by the customer.')]
    case ReturnLabelDownloadAndPrint = 'ReturnLabelDownloadAndPrint';
    #[SchemaEnumerationMemberInfo(type: ['ReturnLabelSourceEnumeration'], rdfs_comment: 'Specifies that a return label will be provided by the seller in the shipping box.')]
    case ReturnLabelInBox = 'ReturnLabelInBox';
    #[SchemaEnumerationMemberInfo(type: ['ReturnFeesEnumeration'], rdfs_comment: 'Specifies that the customer must pay the return shipping costs when returning a product.')]
    case ReturnShippingFees = 'ReturnShippingFees';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'A specific branch of medical science that deals with the study and treatment of rheumatic, autoimmune or joint diseases.')]
    case Rheumatologic = 'Rheumatologic';
    #[SchemaEnumerationMemberInfo(type: ['SteeringPositionValue'], rdfs_comment: 'The steering position is on the right side of the vehicle (viewed from the main direction of driving).')]
    case RightHandDriving = 'RightHandDriving';
    #[SchemaEnumerationMemberInfo(type: ['HealthAspectEnumeration'], rdfs_comment: 'Information about the risk factors and possible complications that may follow a topic.')]
    case RisksOrComplicationsHealthAspect = 'RisksOrComplicationsHealthAspect';
    #[SchemaEnumerationMemberInfo(type: ['RsvpResponseType'], rdfs_comment: 'The invitee may or may not attend.')]
    case RsvpResponseMaybe = 'RsvpResponseMaybe';
    #[SchemaEnumerationMemberInfo(type: ['RsvpResponseType'], rdfs_comment: 'The invitee will not attend.')]
    case RsvpResponseNo = 'RsvpResponseNo';
    #[SchemaEnumerationMemberInfo(type: ['RsvpResponseType'], rdfs_comment: 'The invitee will attend.')]
    case RsvpResponseYes = 'RsvpResponseYes';
    #[SchemaEnumerationMemberInfo(type: ['PriceTypeEnumeration'], rdfs_comment: 'Represents the suggested retail price ("SRP") of an offered product.')]
    case SRP = 'SRP';
    #[SchemaEnumerationMemberInfo(type: ['HealthAspectEnumeration'], rdfs_comment: 'Content about the safety-related aspects of a health topic.')]
    case SafetyHealthAspect = 'SafetyHealthAspect';
    #[SchemaEnumerationMemberInfo(type: ['PriceTypeEnumeration'], rdfs_comment: 'Represents a sale price (usually active for a limited period) of an offered product.')]
    case SalePrice = 'SalePrice';
    #[SchemaEnumerationMemberInfo(type: ['MediaManipulationRatingEnumeration'], rdfs_comment: 'Content coded \'satire or parody content\' in a <a href="https://schema.org/MediaReview" target="_blank" rel="noopener noreferrer">MediaReview</a>, considered in the context of how it was published or shared.

For a <a href="https://schema.org/VideoObject" target="_blank" rel="noopener noreferrer">VideoObject</a> to be \'satire or parody content\': A video that was created as political or humorous commentary and is presented in that context. (Reshares of satire/parody content that do not include relevant context are more likely to fall under the “missing context” rating.)

For an <a href="https://schema.org/ImageObject" target="_blank" rel="noopener noreferrer">ImageObject</a> to be \'satire or parody content\': An image that was created as political or humorous commentary and is presented in that context. (Reshares of satire/parody content that do not include relevant context are more likely to fall under the “missing context” rating.)

For an <a href="https://schema.org/ImageObject" target="_blank" rel="noopener noreferrer">ImageObject</a> with embedded text to be \'satire or parody content\': An image that was created as political or humorous commentary and is presented in that context. (Reshares of satire/parody content that do not include relevant context are more likely to fall under the “missing context” rating.)

For an <a href="https://schema.org/AudioObject" target="_blank" rel="noopener noreferrer">AudioObject</a> to be \'satire or parody content\': Audio that was created as political or humorous commentary and is presented in that context. (Reshares of satire/parody content that do not include relevant context are more likely to fall under the “missing context” rating.)')]
    case SatireOrParodyContent = 'SatireOrParodyContent';
    #[SchemaEnumerationMemberInfo(type: ['DayOfWeek'], rdfs_comment: 'The day of the week between Friday and Sunday.')]
    case Saturday = 'Saturday';
    #[SchemaEnumerationMemberInfo(type: ['IPTCDigitalSourceEnumeration'], rdfs_comment: 'Content coded as \'<a href="https://cv.iptc.org/newscodes/digitalsourcetype/algorithmicMedia">algorithmic media</a>\' using the IPTC <a href="https://cv.iptc.org/newscodes/digitalsourcetype/">digital source type</a> vocabulary.')]
    case ScreenCaptureDigitalSource = 'ScreenCaptureDigitalSource';
    #[SchemaEnumerationMemberInfo(type: ['HealthAspectEnumeration'], rdfs_comment: 'Content about how to screen or further filter a topic.')]
    case ScreeningHealthAspect = 'ScreeningHealthAspect';
    #[SchemaEnumerationMemberInfo(type: ['MapCategoryType'], rdfs_comment: 'A seating map.')]
    case SeatingMap = 'SeatingMap';
    #[SchemaEnumerationMemberInfo(type: ['HealthAspectEnumeration'], rdfs_comment: 'Information about questions that may be asked, when to see a professional, measures before seeing a doctor or content about the first consultation.')]
    case SeeDoctorHealthAspect = 'SeeDoctorHealthAspect';
    #[SchemaEnumerationMemberInfo(type: ['HealthAspectEnumeration'], rdfs_comment: 'Self care actions or measures that can be taken to sooth, health or avoid a topic. This may be carried at home and can be carried/managed by the person itself.')]
    case SelfCareHealthAspect = 'SelfCareHealthAspect';
    #[SchemaEnumerationMemberInfo(type: ['AdultOrientedEnumeration'], rdfs_comment: 'The item contains sexually oriented content such as nudity, suggestive or explicit material, or related online services, or is intended to enhance sexual activity. Examples: Erotic videos or magazine, sexual enhancement devices, sex toys.')]
    case SexualContentConsideration = 'SexualContentConsideration';
    #[SchemaEnumerationMemberInfo(type: ['HealthAspectEnumeration'], rdfs_comment: 'Side effects that can be observed from the usage of the topic.')]
    case SideEffectsHealthAspect = 'SideEffectsHealthAspect';
    #[SchemaEnumerationMemberInfo(type: ['MedicalTrialDesign'], rdfs_comment: 'A trial design in which the researcher knows which treatment the patient was randomly assigned to but the patient does not.')]
    case SingleBlindedTrial = 'SingleBlindedTrial';
    #[SchemaEnumerationMemberInfo(type: ['MedicalTrialDesign'], rdfs_comment: 'A trial that takes place at a single center.')]
    case SingleCenterTrial = 'SingleCenterTrial';
    #[SchemaEnumerationMemberInfo(type: ['GamePlayMode'], rdfs_comment: 'Play mode: SinglePlayer. Which is played by a lone player.')]
    case SinglePlayer = 'SinglePlayer';
    #[SchemaEnumerationMemberInfo(type: ['MusicAlbumReleaseType'], rdfs_comment: 'SingleRelease.')]
    case SingleRelease = 'SingleRelease';
    #[SchemaEnumerationMemberInfo(type: ['SizeSystemEnumeration'], rdfs_comment: 'Imperial size system.')]
    case SizeSystemImperial = 'SizeSystemImperial';
    #[SchemaEnumerationMemberInfo(type: ['SizeSystemEnumeration'], rdfs_comment: 'Metric size system.')]
    case SizeSystemMetric = 'SizeSystemMetric';
    #[SchemaEnumerationMemberInfo(type: ['PhysicalExam'], rdfs_comment: 'Skin assessment with clinical examination.')]
    case Skin = 'Skin';
    #[SchemaEnumerationMemberInfo(type: ['ItemAvailability'], rdfs_comment: 'Indicates that the item has sold out.')]
    case SoldOut = 'SoldOut';
    #[SchemaEnumerationMemberInfo(type: ['MusicAlbumProductionType'], rdfs_comment: 'SoundtrackAlbum.')]
    case SoundtrackAlbum = 'SoundtrackAlbum';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'The scientific study and treatment of defects, disorders, and malfunctions of speech and voice, as stuttering, lisping, or lalling, and of language disturbances, as aphasia or delayed language acquisition.')]
    case SpeechPathology = 'SpeechPathology';
    #[SchemaEnumerationMemberInfo(type: ['MusicAlbumProductionType'], rdfs_comment: 'SpokenWordAlbum.')]
    case SpokenWordAlbum = 'SpokenWordAlbum';
    #[SchemaEnumerationMemberInfo(type: ['MediaManipulationRatingEnumeration'], rdfs_comment: 'Content coded \'staged content\' in a <a href="https://schema.org/MediaReview" target="_blank" rel="noopener noreferrer">MediaReview</a>, considered in the context of how it was published or shared.

For a <a href="https://schema.org/VideoObject" target="_blank" rel="noopener noreferrer">VideoObject</a> to be \'staged content\': A video that has been created using actors or similarly contrived.

For an <a href="https://schema.org/ImageObject" target="_blank" rel="noopener noreferrer">ImageObject</a> to be \'staged content\': An image that was created using actors or similarly contrived, such as a screenshot of a fake tweet.

For an <a href="https://schema.org/ImageObject" target="_blank" rel="noopener noreferrer">ImageObject</a> with embedded text to be \'staged content\': An image that was created using actors or similarly contrived, such as a screenshot of a fake tweet.

For an <a href="https://schema.org/AudioObject" target="_blank" rel="noopener noreferrer">AudioObject</a> to be \'staged content\': Audio that has been created using actors or similarly contrived.')]
    case StagedContent = 'StagedContent';
    #[SchemaEnumerationMemberInfo(type: ['HealthAspectEnumeration'], rdfs_comment: 'Stages that can be observed from a topic.')]
    case StagesHealthAspect = 'StagesHealthAspect';
    #[SchemaEnumerationMemberInfo(type: ['RefundTypeEnumeration'], rdfs_comment: 'Specifies that the customer receives a store credit as refund when returning a product.')]
    case StoreCreditRefund = 'StoreCreditRefund';
    #[SchemaEnumerationMemberInfo(type: ['PhysicalActivityCategory'], rdfs_comment: 'Physical activity that is engaged in to improve muscle and bone strength. Also referred to as resistance training.')]
    case StrengthTraining = 'StrengthTraining';
    #[SchemaEnumerationMemberInfo(type: ['PriceTypeEnumeration'], rdfs_comment: 'Represents the strikethrough price (the previous advertised price) of an offered product.')]
    case StrikethroughPrice = 'StrikethroughPrice';
    #[SchemaEnumerationMemberInfo(type: ['MusicAlbumProductionType'], rdfs_comment: 'StudioAlbum.')]
    case StudioAlbum = 'StudioAlbum';
    #[SchemaEnumerationMemberInfo(type: ['PriceComponentTypeEnumeration'], rdfs_comment: 'Represents the subscription pricing component of the total price for an offered product.')]
    case Subscription = 'Subscription';
    #[SchemaEnumerationMemberInfo(type: ['DayOfWeek'], rdfs_comment: 'The day of the week between Saturday and Monday.')]
    case Sunday = 'Sunday';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'A specific branch of medical science that pertains to treating diseases, injuries and deformities by manual and instrumental means.')]
    case Surgical = 'Surgical';
    #[SchemaEnumerationMemberInfo(type: ['MedicalStudyStatus'], rdfs_comment: 'Suspended.')]
    case Suspended = 'Suspended';
    #[SchemaEnumerationMemberInfo(type: ['HealthAspectEnumeration'], rdfs_comment: 'Symptoms or related symptoms of a Topic.')]
    case SymptomsHealthAspect = 'SymptomsHealthAspect';
    #[SchemaEnumerationMemberInfo(type: ['CarUsageType'], rdfs_comment: 'Indicates the usage of the car as a taxi.')]
    case TaxiVehicleUsage = 'TaxiVehicleUsage';
    #[SchemaEnumerationMemberInfo(type: ['MedicalStudyStatus'], rdfs_comment: 'Terminated.')]
    case Terminated = 'Terminated';
    #[SchemaEnumerationMemberInfo(type: ['MedicalDevicePurpose'], rdfs_comment: 'A medical device used for therapeutic purposes.')]
    case Therapeutic = 'Therapeutic';
    #[SchemaEnumerationMemberInfo(type: ['PhysicalExam'], rdfs_comment: 'Throat assessment with  clinical examination.')]
    case Throat = 'Throat';
    #[SchemaEnumerationMemberInfo(type: ['DayOfWeek'], rdfs_comment: 'The day of the week between Wednesday and Friday.')]
    case Thursday = 'Thursday';
    #[SchemaEnumerationMemberInfo(type: ['TierBenefitEnumeration'], rdfs_comment: 'Benefit of the tier is earning of loyalty points.')]
    case TierBenefitLoyaltyPoints = 'TierBenefitLoyaltyPoints';
    #[SchemaEnumerationMemberInfo(type: ['TierBenefitEnumeration'], rdfs_comment: 'Benefit of the tier is a members-only price.')]
    case TierBenefitLoyaltyPrice = 'TierBenefitLoyaltyPrice';
    #[SchemaEnumerationMemberInfo(type: ['TierBenefitEnumeration'], rdfs_comment: 'Benefit of the tier is members-only returns, for example free unlimited returns.')]
    case TierBenefitLoyaltyReturns = 'TierBenefitLoyaltyReturns';
    #[SchemaEnumerationMemberInfo(type: ['TierBenefitEnumeration'], rdfs_comment: 'Benefit of the tier is a members-only shipping price or speed (for example free shipping or 1-day shipping).')]
    case TierBenefitLoyaltyShipping = 'TierBenefitLoyaltyShipping';
    #[SchemaEnumerationMemberInfo(type: ['AdultOrientedEnumeration'], rdfs_comment: 'Item contains tobacco and/or nicotine, for example cigars, cigarettes, chewing tobacco, e-cigarettes, or hookahs.')]
    case TobaccoNicotineConsideration = 'TobaccoNicotineConsideration';
    #[SchemaEnumerationMemberInfo(type: ['ContactPointOption'], rdfs_comment: 'The associated telephone number is toll free.')]
    case TollFree = 'TollFree';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'A specific branch of medical science that is concerned with poisons, their nature, effects and detection and involved in the treatment of poisoning.')]
    case Toxicologic = 'Toxicologic';
    #[SchemaEnumerationMemberInfo(type: ['MedicineSystem'], rdfs_comment: 'A system of medicine based on common theoretical concepts that originated in China and evolved over thousands of years, that uses herbs, acupuncture, exercise, massage, dietary therapy, and other methods to treat a wide range of conditions.')]
    case TraditionalChinese = 'TraditionalChinese';
    #[SchemaEnumerationMemberInfo(type: ['IPTCDigitalSourceEnumeration'], rdfs_comment: 'Content coded as \'<a href="https://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia">trained algorithmic media</a>\' using the IPTC <a href="https://cv.iptc.org/newscodes/digitalsourcetype/">digital source type</a> vocabulary.')]
    case TrainedAlgorithmicMediaDigitalSource = 'TrainedAlgorithmicMediaDigitalSource';
    #[SchemaEnumerationMemberInfo(type: ['MediaManipulationRatingEnumeration'], rdfs_comment: 'Content coded \'transformed content\' in a <a href="https://schema.org/MediaReview" target="_blank" rel="noopener noreferrer">MediaReview</a>, considered in the context of how it was published or shared.

For a <a href="https://schema.org/VideoObject" target="_blank" rel="noopener noreferrer">VideoObject</a> to be \'transformed content\':  or all of the video has been manipulated to transform the footage itself. This category includes using tools like the Adobe Suite to change the speed of the video, add or remove visual elements or dub audio. Deepfakes are also a subset of transformation.

For an <a href="https://schema.org/ImageObject" target="_blank" rel="noopener noreferrer">ImageObject</a> to be \'transformed content\': Adding or deleting visual elements to give the image a different meaning with the intention to mislead.

For an <a href="https://schema.org/ImageObject" target="_blank" rel="noopener noreferrer">ImageObject</a> with embedded text to be \'transformed content\': Adding or deleting visual elements to give the image a different meaning with the intention to mislead.

For an <a href="https://schema.org/AudioObject" target="_blank" rel="noopener noreferrer">AudioObject</a> to be \'transformed content\': Part or all of the audio has been manipulated to alter the words or sounds, or the audio has been synthetically generated, such as to create a sound-alike voice.')]
    case TransformedContent = 'TransformedContent';
    #[SchemaEnumerationMemberInfo(type: ['MapCategoryType'], rdfs_comment: 'A transit map.')]
    case TransitMap = 'TransitMap';
    #[SchemaEnumerationMemberInfo(type: ['HealthAspectEnumeration'], rdfs_comment: 'Treatments or related therapies for a Topic.')]
    case TreatmentsHealthAspect = 'TreatmentsHealthAspect';
    #[SchemaEnumerationMemberInfo(type: ['MedicalTrialDesign'], rdfs_comment: 'A trial design in which neither the researcher, the person administering the therapy nor the patient knows the details of the treatment the patient was randomly assigned to.')]
    case TripleBlindedTrial = 'TripleBlindedTrial';
    #[SchemaEnumerationMemberInfo(type: ['DayOfWeek'], rdfs_comment: 'The day of the week between Monday and Wednesday.')]
    case Tuesday = 'Tuesday';
    #[SchemaEnumerationMemberInfo(type: ['HealthAspectEnumeration'], rdfs_comment: 'Categorization and other types related to a topic.')]
    case TypesHealthAspect = 'TypesHealthAspect';
    #[SchemaEnumerationMemberInfo(type: ['UKNonprofitType'], rdfs_comment: 'UKTrust: Non-profit type referring to a UK trust.')]
    case UKTrust = 'UKTrust';
    #[SchemaEnumerationMemberInfo(type: ['MedicalImagingTechnique'], rdfs_comment: 'Ultrasound imaging.')]
    case Ultrasound = 'Ultrasound';
    #[SchemaEnumerationMemberInfo(type: ['AdultOrientedEnumeration'], rdfs_comment: 'The item is suitable only for adults, without indicating why. Due to widespread use of "adult" as a euphemism for "sexual", many such items are likely suited also for the SexualContentConsideration code.')]
    case UnclassifiedAdultConsideration = 'UnclassifiedAdultConsideration';
    #[SchemaEnumerationMemberInfo(type: ['GovernmentBenefitsType'], rdfs_comment: 'UnemploymentSupport: this is a benefit for unemployment support.')]
    case UnemploymentSupport = 'UnemploymentSupport';
    #[SchemaEnumerationMemberInfo(type: ['UKNonprofitType'], rdfs_comment: 'UnincorporatedAssociationCharity: Non-profit type referring to a charitable company that is not incorporated (UK).')]
    case UnincorporatedAssociationCharity = 'UnincorporatedAssociationCharity';
    #[SchemaEnumerationMemberInfo(type: ['LegalValueLevel'], rdfs_comment: 'Indicates that a document has no particular or special standing (e.g. a republication of a law by a private publisher).')]
    case UnofficialLegalValue = 'UnofficialLegalValue';
    #[SchemaEnumerationMemberInfo(type: ['MedicalSpecialty'], rdfs_comment: 'A specific branch of medical science that is concerned with the diagnosis and treatment of diseases pertaining to the urinary tract and the urogenital system.')]
    case Urologic = 'Urologic';
    #[SchemaEnumerationMemberInfo(type: ['HealthAspectEnumeration'], rdfs_comment: 'Content about how, when, frequency and dosage of a topic.')]
    case UsageOrScheduleHealthAspect = 'UsageOrScheduleHealthAspect';
    #[SchemaEnumerationMemberInfo(type: ['OfferItemCondition'], rdfs_comment: 'Indicates that the item is used.')]
    case UsedCondition = 'UsedCondition';
    #[SchemaEnumerationMemberInfo(type: ['RestrictedDiet'], rdfs_comment: 'A diet exclusive of all animal products.')]
    case VeganDiet = 'VeganDiet';
    #[SchemaEnumerationMemberInfo(type: ['RestrictedDiet'], rdfs_comment: 'A diet exclusive of animal meat.')]
    case VegetarianDiet = 'VegetarianDiet';
    #[SchemaEnumerationMemberInfo(type: ['MapCategoryType'], rdfs_comment: 'A venue map (e.g. for malls, auditoriums, museums, etc.).')]
    case VenueMap = 'VenueMap';
    #[SchemaEnumerationMemberInfo(type: ['MusicReleaseFormatType'], rdfs_comment: 'VinylFormat.')]
    case VinylFormat = 'VinylFormat';
    #[SchemaEnumerationMemberInfo(type: ['AdultOrientedEnumeration'], rdfs_comment: 'Item shows or promotes violence.')]
    case ViolenceConsideration = 'ViolenceConsideration';
    #[SchemaEnumerationMemberInfo(type: ['IPTCDigitalSourceEnumeration'], rdfs_comment: 'Content coded as \'<a href="https://cv.iptc.org/newscodes/digitalsourcetype/virtualRecording">virtual recording</a>\' using the IPTC <a href="https://cv.iptc.org/newscodes/digitalsourcetype/">digital source type</a> vocabulary.')]
    case VirtualRecordingDigitalSource = 'VirtualRecordingDigitalSource';
    #[SchemaEnumerationMemberInfo(type: ['InfectiousAgentClass'], rdfs_comment: 'Pathogenic virus that causes viral infection.')]
    case Virus = 'Virus';
    #[SchemaEnumerationMemberInfo(type: ['AdultOrientedEnumeration'], rdfs_comment: 'The item is intended to induce bodily harm, for example guns, mace, combat knives, brass knuckles, nail or other bombs, and spears.')]
    case WeaponConsideration = 'WeaponConsideration';
    #[SchemaEnumerationMemberInfo(type: ['WearableMeasurementTypeEnumeration'], rdfs_comment: 'Measurement of the back section, for example of a jacket.')]
    case WearableMeasurementBack = 'WearableMeasurementBack';
    #[SchemaEnumerationMemberInfo(type: ['WearableMeasurementTypeEnumeration'], rdfs_comment: 'Measurement of the chest/bust section, for example of a suit.')]
    case WearableMeasurementChestOrBust = 'WearableMeasurementChestOrBust';
    #[SchemaEnumerationMemberInfo(type: ['WearableMeasurementTypeEnumeration'], rdfs_comment: 'Measurement of the collar, for example of a shirt.')]
    case WearableMeasurementCollar = 'WearableMeasurementCollar';
    #[SchemaEnumerationMemberInfo(type: ['WearableMeasurementTypeEnumeration'], rdfs_comment: 'Measurement of the cup, for example of a bra.')]
    case WearableMeasurementCup = 'WearableMeasurementCup';
    #[SchemaEnumerationMemberInfo(type: ['WearableMeasurementTypeEnumeration'], rdfs_comment: 'Measurement of the height, for example the heel height of a shoe.')]
    case WearableMeasurementHeight = 'WearableMeasurementHeight';
    #[SchemaEnumerationMemberInfo(type: ['WearableMeasurementTypeEnumeration'], rdfs_comment: 'Measurement of the hip section, for example of a skirt.')]
    case WearableMeasurementHips = 'WearableMeasurementHips';
    #[SchemaEnumerationMemberInfo(type: ['WearableMeasurementTypeEnumeration'], rdfs_comment: 'Measurement of the inseam, for example of pants.')]
    case WearableMeasurementInseam = 'WearableMeasurementInseam';
    #[SchemaEnumerationMemberInfo(type: ['WearableMeasurementTypeEnumeration'], rdfs_comment: 'Represents the length, for example of a dress.')]
    case WearableMeasurementLength = 'WearableMeasurementLength';
    #[SchemaEnumerationMemberInfo(type: ['WearableMeasurementTypeEnumeration'], rdfs_comment: 'Measurement of the outside leg, for example of pants.')]
    case WearableMeasurementOutsideLeg = 'WearableMeasurementOutsideLeg';
    #[SchemaEnumerationMemberInfo(type: ['WearableMeasurementTypeEnumeration'], rdfs_comment: 'Measurement of the sleeve length, for example of a shirt.')]
    case WearableMeasurementSleeve = 'WearableMeasurementSleeve';
    #[SchemaEnumerationMemberInfo(type: ['WearableMeasurementTypeEnumeration'], rdfs_comment: 'Measurement of the waist section, for example of pants.')]
    case WearableMeasurementWaist = 'WearableMeasurementWaist';
    #[SchemaEnumerationMemberInfo(type: ['WearableMeasurementTypeEnumeration'], rdfs_comment: 'Measurement of the width, for example of shoes.')]
    case WearableMeasurementWidth = 'WearableMeasurementWidth';
    #[SchemaEnumerationMemberInfo(type: ['WearableSizeGroupEnumeration'], rdfs_comment: 'Size group "Big" for wearables.')]
    case WearableSizeGroupBig = 'WearableSizeGroupBig';
    #[SchemaEnumerationMemberInfo(type: ['WearableSizeGroupEnumeration'], rdfs_comment: 'Size group "Boys" for wearables.')]
    case WearableSizeGroupBoys = 'WearableSizeGroupBoys';
    #[SchemaEnumerationMemberInfo(type: ['WearableSizeGroupEnumeration'], rdfs_comment: 'Size group "Extra Short" for wearables.')]
    case WearableSizeGroupExtraShort = 'WearableSizeGroupExtraShort';
    #[SchemaEnumerationMemberInfo(type: ['WearableSizeGroupEnumeration'], rdfs_comment: 'Size group "Extra Tall" for wearables.')]
    case WearableSizeGroupExtraTall = 'WearableSizeGroupExtraTall';
    #[SchemaEnumerationMemberInfo(type: ['WearableSizeGroupEnumeration'], rdfs_comment: 'Size group "Girls" for wearables.')]
    case WearableSizeGroupGirls = 'WearableSizeGroupGirls';
    #[SchemaEnumerationMemberInfo(type: ['WearableSizeGroupEnumeration'], rdfs_comment: 'Size group "Husky" (or "Stocky") for wearables.')]
    case WearableSizeGroupHusky = 'WearableSizeGroupHusky';
    #[SchemaEnumerationMemberInfo(type: ['WearableSizeGroupEnumeration'], rdfs_comment: 'Size group "Infants" for wearables.')]
    case WearableSizeGroupInfants = 'WearableSizeGroupInfants';
    #[SchemaEnumerationMemberInfo(type: ['WearableSizeGroupEnumeration'], rdfs_comment: 'Size group "Juniors" for wearables.')]
    case WearableSizeGroupJuniors = 'WearableSizeGroupJuniors';
    #[SchemaEnumerationMemberInfo(type: ['WearableSizeGroupEnumeration'], rdfs_comment: 'Size group "Maternity" for wearables.')]
    case WearableSizeGroupMaternity = 'WearableSizeGroupMaternity';
    #[SchemaEnumerationMemberInfo(type: ['WearableSizeGroupEnumeration'], rdfs_comment: 'Size group "Mens" for wearables.')]
    case WearableSizeGroupMens = 'WearableSizeGroupMens';
    #[SchemaEnumerationMemberInfo(type: ['WearableSizeGroupEnumeration'], rdfs_comment: 'Size group "Misses" (also known as "Missy") for wearables.')]
    case WearableSizeGroupMisses = 'WearableSizeGroupMisses';
    #[SchemaEnumerationMemberInfo(type: ['WearableSizeGroupEnumeration'], rdfs_comment: 'Size group "Petite" for wearables.')]
    case WearableSizeGroupPetite = 'WearableSizeGroupPetite';
    #[SchemaEnumerationMemberInfo(type: ['WearableSizeGroupEnumeration'], rdfs_comment: 'Size group "Plus" for wearables.')]
    case WearableSizeGroupPlus = 'WearableSizeGroupPlus';
    #[SchemaEnumerationMemberInfo(type: ['WearableSizeGroupEnumeration'], rdfs_comment: 'Size group "Regular" for wearables.')]
    case WearableSizeGroupRegular = 'WearableSizeGroupRegular';
    #[SchemaEnumerationMemberInfo(type: ['WearableSizeGroupEnumeration'], rdfs_comment: 'Size group "Short" for wearables.')]
    case WearableSizeGroupShort = 'WearableSizeGroupShort';
    #[SchemaEnumerationMemberInfo(type: ['WearableSizeGroupEnumeration'], rdfs_comment: 'Size group "Tall" for wearables.')]
    case WearableSizeGroupTall = 'WearableSizeGroupTall';
    #[SchemaEnumerationMemberInfo(type: ['WearableSizeGroupEnumeration'], rdfs_comment: 'Size group "Womens" for wearables.')]
    case WearableSizeGroupWomens = 'WearableSizeGroupWomens';
    #[SchemaEnumerationMemberInfo(type: ['WearableSizeSystemEnumeration'], rdfs_comment: 'Australian size system for wearables.')]
    case WearableSizeSystemAU = 'WearableSizeSystemAU';
    #[SchemaEnumerationMemberInfo(type: ['WearableSizeSystemEnumeration'], rdfs_comment: 'Brazilian size system for wearables.')]
    case WearableSizeSystemBR = 'WearableSizeSystemBR';
    #[SchemaEnumerationMemberInfo(type: ['WearableSizeSystemEnumeration'], rdfs_comment: 'Chinese size system for wearables.')]
    case WearableSizeSystemCN = 'WearableSizeSystemCN';
    #[SchemaEnumerationMemberInfo(type: ['WearableSizeSystemEnumeration'], rdfs_comment: 'Continental size system for wearables.')]
    case WearableSizeSystemContinental = 'WearableSizeSystemContinental';
    #[SchemaEnumerationMemberInfo(type: ['WearableSizeSystemEnumeration'], rdfs_comment: 'German size system for wearables.')]
    case WearableSizeSystemDE = 'WearableSizeSystemDE';
    #[SchemaEnumerationMemberInfo(type: ['WearableSizeSystemEnumeration'], rdfs_comment: 'EN 13402 (joint European standard for size labelling of clothes).')]
    case WearableSizeSystemEN13402 = 'WearableSizeSystemEN13402';
    #[SchemaEnumerationMemberInfo(type: ['WearableSizeSystemEnumeration'], rdfs_comment: 'European size system for wearables.')]
    case WearableSizeSystemEurope = 'WearableSizeSystemEurope';
    #[SchemaEnumerationMemberInfo(type: ['WearableSizeSystemEnumeration'], rdfs_comment: 'French size system for wearables.')]
    case WearableSizeSystemFR = 'WearableSizeSystemFR';
    #[SchemaEnumerationMemberInfo(type: ['WearableSizeSystemEnumeration'], rdfs_comment: 'GS1 (formerly NRF) size system for wearables.')]
    case WearableSizeSystemGS1 = 'WearableSizeSystemGS1';
    #[SchemaEnumerationMemberInfo(type: ['WearableSizeSystemEnumeration'], rdfs_comment: 'Italian size system for wearables.')]
    case WearableSizeSystemIT = 'WearableSizeSystemIT';
    #[SchemaEnumerationMemberInfo(type: ['WearableSizeSystemEnumeration'], rdfs_comment: 'Japanese size system for wearables.')]
    case WearableSizeSystemJP = 'WearableSizeSystemJP';
    #[SchemaEnumerationMemberInfo(type: ['WearableSizeSystemEnumeration'], rdfs_comment: 'Mexican size system for wearables.')]
    case WearableSizeSystemMX = 'WearableSizeSystemMX';
    #[SchemaEnumerationMemberInfo(type: ['WearableSizeSystemEnumeration'], rdfs_comment: 'United Kingdom size system for wearables.')]
    case WearableSizeSystemUK = 'WearableSizeSystemUK';
    #[SchemaEnumerationMemberInfo(type: ['WearableSizeSystemEnumeration'], rdfs_comment: 'United States size system for wearables.')]
    case WearableSizeSystemUS = 'WearableSizeSystemUS';
    #[SchemaEnumerationMemberInfo(type: ['DayOfWeek'], rdfs_comment: 'The day of the week between Tuesday and Thursday.')]
    case Wednesday = 'Wednesday';
    #[SchemaEnumerationMemberInfo(type: ['MedicineSystem'], rdfs_comment: 'The conventional Western system of medicine, that aims to apply the best available evidence gained from the scientific method to clinical decision making. Also known as conventional or Western medicine.')]
    case WesternConventional = 'WesternConventional';
    #[SchemaEnumerationMemberInfo(type: ['DrugCostCategory'], rdfs_comment: 'The drug\'s cost represents the wholesale acquisition cost of the drug.')]
    case Wholesale = 'Wholesale';
    #[SchemaEnumerationMemberInfo(type: ['MedicalStudyStatus'], rdfs_comment: 'Withdrawn.')]
    case Withdrawn = 'Withdrawn';
    #[SchemaEnumerationMemberInfo(type: ['DigitalDocumentPermissionType'], rdfs_comment: 'Permission to write or edit the document.')]
    case WritePermission = 'WritePermission';
    #[SchemaEnumerationMemberInfo(type: ['MedicalImagingTechnique'], rdfs_comment: 'X-ray imaging.')]
    case XRay = 'XRay';
    #[SchemaEnumerationMemberInfo(type: ['BoardingPolicyType'], rdfs_comment: 'The airline boards by zones of the plane.')]
    case ZoneBoardingPolicy = 'ZoneBoardingPolicy';

    /**
     * Enumeration member string values valid for a Schema.org enumeration type
     * (matches {@see SchemaEnumerationMemberInfo::$type} on each case).
     *
     * @return list<string>
     */
    public static function memberNamesForEnumerationType(string $schemaEnumerationTypeName): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            if (in_array($schemaEnumerationTypeName, $case->type(), true)) {
                $out[] = $case->value;
            }
        }

        sort($out);

        return array_values(array_unique($out));
    }

    /**
     * SchemaEnumerationMemberInfo attribute for this case (read via reflection).
     */
    public function info(): ?SchemaEnumerationMemberInfo
    {
        $ref = (new \ReflectionEnum(self::class))->getCase($this->name);
        $attrs = $ref->getAttributes(SchemaEnumerationMemberInfo::class);

        return isset($attrs[0]) ? $attrs[0]->newInstance() : null;
    }

    /**
     * Enumeration type name(s) for which this value is a member.
     *
     * @return list<string>
     */
    public function type(): array
    {
        return $this->info()?->type ?? [];
    }

    /**
     * Member description (rdfs:comment) as HTML with links.
     */
    public function description(): ?string
    {
        return $this->info()?->rdfs_comment;
    }
}
