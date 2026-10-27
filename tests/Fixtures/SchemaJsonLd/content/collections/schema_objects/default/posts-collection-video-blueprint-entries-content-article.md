---
id: 291a8cc1-8658-42ad-a821-40c48d0819ad
blueprint: schema_object
title: 'Posts Collection — Video Blueprint Entries Content (Article)'
schema:
  type:
    - Article
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
    articleBody:
      mode: inherit
      value: {  }
    articleSection:
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
    backstory:
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
      mode: inherit
      value: {  }
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
      mode: custom
      value:
        -
          value:
            code: |-
              {{ schema_thing_from_relationship
                  mode="direct"
                  current_field="segments"
                  current_field_replicator="true"
                  schema_object="6c7f84aa-846f-4960-9ca1-dd47fdaccbcb"
              }}
            mode: html
          _id: 2Ylg3T6_7odWVhJrH5pue
          type: antlers
          enabled: true
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
    learningResourceType:
      mode: inherit
      value: {  }
    license:
      mode: inherit
      value: {  }
    locationCreated:
      mode: inherit
      value: {  }
    mainEntity:
      mode: inherit
      value: {  }
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
    mentions:
      mode: inherit
      value: {  }
    name:
      mode: inherit
      value: {  }
    offers:
      mode: inherit
      value: {  }
    pageEnd:
      mode: inherit
      value: {  }
    pageStart:
      mode: inherit
      value: {  }
    pagination:
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
    releasedEvent:
      mode: inherit
      value: {  }
    review:
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
updated_at: 1774988249
---
