---
id: 4f2a0926-99d6-44e9-94c6-13c721b0d49c
blueprint: schema_object
title: 'AudioObject Type Base'
schema:
  type:
    - AudioObject
  properties:
    about:
      mode: inherit
      value: {  }
    abstract:
      mode: inherit
      value: {  }
    accessMode:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ transcript ? '["auditory","textual"]' : '["auditory"]' }}
            mode: html
          _id: V4G3uf6X7eu4soE5xGmRy
          type: antlers
          enabled: true
    accessModeSufficient:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ transcript ? '["auditory","textual"]' : '["auditory"]' }}
            mode: html
          _id: HCFgNip4LUPiQjQ1X-NBL
          type: antlers
          enabled: true
    accessibilityAPI:
      mode: inherit
      value: {  }
    accessibilityControl:
      mode: inherit
      value: {  }
    accessibilityFeature:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ transcript ? 'transcript' : null }}
            mode: html
          _id: edL6nf1nIcJ9408Mig8QI
          type: antlers
          enabled: true
    accessibilityHazard:
      mode: custom
      value:
        -
          value: none
          _id: gl-1Q_H8t4V0qT6KwiMDX
          type: text_short
          enabled: true
    accessibilitySummary:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ transcript ? 'Includes full text transcript.' : 'Audio-only; no transcript available.' }}
            mode: html
          _id: ssBhMuyL4OQuA7QDhdwSp
          type: antlers
          enabled: true
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
    associatedArticle:
      mode: inherit
      value: {  }
    associatedMedia:
      mode: inherit
      value: {  }
    audience:
      mode: custom
      value:
        -
          value:
            code: |-
              {{#

              TODO

              Add a fieldset
                - a field (checkboxes?) that allows the editor to select one or more audience types (i.e., BusinessAudience, EducationalAudience, MedicalAudience, Patient, PeopleAudience, ParentAudience, Researcher)
                - additional inputs (conditional based on the audience type selector) for the specific Schema.org properties of the audience.
                
              Then build a Audience object (multi-type entity, if necessary) and use it here.

              #}}
            mode: html
          _id: 60Fei_aRoFa7V1TXnzlYV
          type: antlers
          enabled: true
    audio:
      mode: inherit
      value: {  }
    author:
      mode: inherit
      value: {  }
    award:
      mode: inherit
      value: {  }
    bitrate:
      mode: inherit
      value: {  }
    caption:
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
    contentSize:
      mode: inherit
      value: {  }
    contentUrl:
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
    duration:
      mode: custom
      value:
        -
          value:
            code: '{{ duration | format_duration }}'
            mode: html
          _id: aJ_dsdZvjBQl3VScYY4j6
          type: antlers
          enabled: true
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
    embedUrl:
      mode: inherit
      value: {  }
    embeddedTextCaption:
      mode: inherit
      value: {  }
    encodesCreativeWork:
      mode: inherit
      value: {  }
    encoding:
      mode: inherit
      value: {  }
    encodingFormat:
      mode: inherit
      value: {  }
    endTime:
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
    height:
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
    ineligibleRegion:
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
    pattern:
      mode: inherit
      value: {  }
    playerType:
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
    productionCompany:
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
    regionsAllowed:
      mode: inherit
      value: {  }
    releasedEvent:
      mode: inherit
      value: {  }
    requiresSubscription:
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
    sha256:
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
    sponsor:
      mode: inherit
      value: {  }
    startTime:
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
    transcript:
      mode: custom
      value:
        -
          value:
            code: '{{ transcript | raw | bard_text | sanitize }}'
            mode: html
          _id: 5-9oNkI1GDa0FOef4EH7n
          type: antlers
          enabled: true
    translationOfWork:
      mode: inherit
      value: {  }
    translator:
      mode: inherit
      value: {  }
    typicalAgeRange:
      mode: inherit
      value: {  }
    uploadDate:
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
    width:
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
updated_at: 1774644007
---
