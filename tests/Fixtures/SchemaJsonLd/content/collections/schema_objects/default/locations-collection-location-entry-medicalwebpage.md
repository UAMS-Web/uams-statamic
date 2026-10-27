---
id: 3524c376-d801-4097-b7b9-e64db0849512
blueprint: schema_object
title: 'Locations Collection — Location Entry (MedicalWebPage)'
schema:
  type:
    - MedicalWebPage
  properties:
    about:
      mode: inherit
      value: {  }
    abstract:
      mode: inherit
      value: {  }
    accessMode:
      mode: inherit
      value: {  }
    accessModeSufficient:
      mode: inherit
      value: {  }
    accessibilityAPI:
      mode: inherit
      value: {  }
    accessibilityControl:
      mode: inherit
      value: {  }
    accessibilityFeature:
      mode: inherit
      value: {  }
    accessibilityHazard:
      mode: inherit
      value: {  }
    accessibilitySummary:
      mode: inherit
      value: {  }
    accountablePerson:
      mode: inherit
      value: {  }
    acquireLicensePage:
      mode: inherit
      value: {  }
    additionalType:
      mode: inherit
      value: {  }
    aggregateRating:
      mode: inherit
      value: {  }
    alternateName:
      mode: inherit
      value: {  }
    alternativeHeadline:
      mode: inherit
      value: {  }
    archivedAt:
      mode: inherit
      value: {  }
    assesses:
      mode: inherit
      value: {  }
    associatedMedia:
      mode: inherit
      value: {  }
    audience:
      mode: inherit
      value: {  }
    audio:
      mode: inherit
      value: {  }
    author:
      mode: inherit
      value: {  }
    award:
      mode: inherit
      value: {  }
    breadcrumb:
      mode: inherit
      value: {  }
    character:
      mode: inherit
      value: {  }
    citation:
      mode: inherit
      value: {  }
    comment:
      mode: inherit
      value: {  }
    commentCount:
      mode: inherit
      value: {  }
    conditionsOfAccess:
      mode: inherit
      value: {  }
    contentLocation:
      mode: inherit
      value: {  }
    contentRating:
      mode: inherit
      value: {  }
    contentReferenceTime:
      mode: inherit
      value: {  }
    contributor:
      mode: inherit
      value: {  }
    copyrightHolder:
      mode: inherit
      value: {  }
    copyrightNotice:
      mode: inherit
      value: {  }
    copyrightYear:
      mode: inherit
      value: {  }
    correction:
      mode: inherit
      value: {  }
    countryOfOrigin:
      mode: inherit
      value: {  }
    creativeWorkStatus:
      mode: inherit
      value: {  }
    creator:
      mode: inherit
      value: {  }
    creditText:
      mode: inherit
      value: {  }
    dateCreated:
      mode: inherit
      value: {  }
    dateModified:
      mode: inherit
      value: {  }
    datePublished:
      mode: inherit
      value: {  }
    description:
      mode: custom
      value:
        -
          value:
            code: '{{ about | strip_tags | sanitize }}'
            mode: html
          _id: locwp3524description
          type: antlers
          enabled: true
    digitalSourceType:
      mode: inherit
      value: {  }
    disambiguatingDescription:
      mode: inherit
      value: {  }
    discussionUrl:
      mode: inherit
      value: {  }
    editEIDR:
      mode: inherit
      value: {  }
    editor:
      mode: inherit
      value: {  }
    educationalAlignment:
      mode: inherit
      value: {  }
    educationalLevel:
      mode: inherit
      value: {  }
    educationalUse:
      mode: inherit
      value: {  }
    encoding:
      mode: inherit
      value: {  }
    encodingFormat:
      mode: inherit
      value: {  }
    exampleOfWork:
      mode: inherit
      value: {  }
    expires:
      mode: inherit
      value: {  }
    funder:
      mode: inherit
      value: {  }
    funding:
      mode: inherit
      value: {  }
    genre:
      mode: inherit
      value: {  }
    hasPart:
      mode: inherit
      value: {  }
    headline:
      mode: inherit
      value: {  }
    identifier:
      mode: inherit
      value: {  }
    image:
      mode: inherit
      value: {  }
    inLanguage:
      mode: inherit
      value: {  }
    interactionStatistic:
      mode: inherit
      value: {  }
    interactivityType:
      mode: inherit
      value: {  }
    interpretedAsClaim:
      mode: inherit
      value: {  }
    isAccessibleForFree:
      mode: inherit
      value: {  }
    isBasedOn:
      mode: inherit
      value: {  }
    isFamilyFriendly:
      mode: inherit
      value: {  }
    isPartOf:
      mode: inherit
      value: {  }
    keywords:
      mode: inherit
      value: {  }
    lastReviewed:
      mode: inherit
      value: {  }
    learningResourceType:
      mode: inherit
      value: {  }
    license:
      mode: inherit
      value: {  }
    locationCreated:
      mode: inherit
      value: {  }
    mainContentOfPage:
      mode: inherit
      value: {  }
    mainEntity:
      mode: custom
      value:
        -
          value:
            code: '{{ get_schema_slot slot="main_content" }}'
            mode: html
          _id: locwp3524mainentity
          type: antlers
          enabled: true
    mainEntityOfPage:
      mode: inherit
      value: {  }
    maintainer:
      mode: inherit
      value: {  }
    material:
      mode: inherit
      value: {  }
    materialExtent:
      mode: inherit
      value: {  }
    medicalAudience:
      mode: inherit
      value: {  }
    mentions:
      mode: inherit
      value: {  }
    name:
      mode: inherit
      value: {  }
    offers:
      mode: inherit
      value: {  }
    pattern:
      mode: inherit
      value: {  }
    position:
      mode: inherit
      value: {  }
    potentialAction:
      mode: inherit
      value: {  }
    primaryImageOfPage:
      mode: custom
      value:
        -
          value:
            code: '{{ schema_thing_from_relationship mode="direct" current_field="featured_image" schema_object="0ec21000-e498-4d08-94ee-8c98d151c48c" }}'
            mode: html
          _id: locwp3524primaryimg
          type: antlers
          enabled: true
    producer:
      mode: inherit
      value: {  }
    provider:
      mode: inherit
      value: {  }
    publication:
      mode: inherit
      value: {  }
    publisher:
      mode: inherit
      value: {  }
    publisherImprint:
      mode: inherit
      value: {  }
    publishingPrinciples:
      mode: inherit
      value: {  }
    recordedAt:
      mode: inherit
      value: {  }
    relatedLink:
      mode: inherit
      value: {  }
    releasedEvent:
      mode: inherit
      value: {  }
    review:
      mode: inherit
      value: {  }
    reviewedBy:
      mode: inherit
      value: {  }
    sameAs:
      mode: inherit
      value: {  }
    schemaVersion:
      mode: inherit
      value: {  }
    sdDatePublished:
      mode: inherit
      value: {  }
    sdLicense:
      mode: inherit
      value: {  }
    sdPublisher:
      mode: inherit
      value: {  }
    significantLink:
      mode: inherit
      value: {  }
    size:
      mode: inherit
      value: {  }
    sourceOrganization:
      mode: inherit
      value: {  }
    spatial:
      mode: inherit
      value: {  }
    spatialCoverage:
      mode: inherit
      value: {  }
    speakable:
      mode: inherit
      value: {  }
    specialty:
      mode: inherit
      value: {  }
    sponsor:
      mode: inherit
      value: {  }
    subjectOf:
      mode: inherit
      value: {  }
    teaches:
      mode: inherit
      value: {  }
    temporal:
      mode: inherit
      value: {  }
    temporalCoverage:
      mode: inherit
      value: {  }
    text:
      mode: inherit
      value: {  }
    thumbnail:
      mode: inherit
      value: {  }
    thumbnailUrl:
      mode: inherit
      value: {  }
    timeRequired:
      mode: inherit
      value: {  }
    translationOfWork:
      mode: inherit
      value: {  }
    translator:
      mode: inherit
      value: {  }
    typicalAgeRange:
      mode: inherit
      value: {  }
    url:
      mode: inherit
      value: {  }
    usageInfo:
      mode: inherit
      value: {  }
    version:
      mode: inherit
      value: {  }
    video:
      mode: inherit
      value: {  }
    wordCount:
      mode: inherit
      value: {  }
    workExample:
      mode: inherit
      value: {  }
    workTranslation:
      mode: inherit
      value: {  }
updated_by: cd0a8902-c35f-474a-89f5-d360ea4ca6ae
updated_at: 1773435137
---
