---
id: c3d0e5b2-8f9e-4c5d-ab1e-2f3a4b5c6d7f
blueprint: schema_object
title: 'Academic Degree (EducationalOccupationalCredential)'
schema:
  type:
    - EducationalOccupationalCredential
  properties:
    about:
      mode: inherit_custom
      value:
        -
          value:
            code: |-
              {{#
              
                  TODO

                  Get `specialty` from `education` replicator set as `$education_specialty`.

                  Make a `schema_objects` entry for use in the `schema_template_node` tag that can support `about`-compatible specialty/domain for:
                    - Degree (i.e, major)
                    - Certification (i.e, specialty/subspecialty)
                    - Badge
                    - Certificate
                    - License
                    - MicroCredential
                    - QualityAssuranceCredential

              #}}
              {{ $education_specialty = null }}
              {{ if $education_specialty }}
                  {{#
                      schema_template_node
                      id=""
                      :instance_index="index"
                  #}}
              {{ /if }}
            mode: html
          _id: 'aDVnEOnABR-q4Eo_l-bIH'
          type: antlers
          enabled: true
    keywords:
      mode: inherit
      value: {  }
    additionalType:
      mode: inherit
      value: {  }
    alternateName:
      mode: inherit
      value: {  }
    assesses:
      mode: inherit
      value: {  }
    audience:
      mode: inherit
      value: {  }
    datePublished:
      mode: inherit
      value: {  }
    description:
      mode: inherit
      value: {  }
    disambiguatingDescription:
      mode: inherit
      value: {  }
    educationalLevel:
      mode: inherit
      value: {  }
    name:
      mode: inherit
      value: {  }
    validIn:
      mode: inherit
      value: {  }
    competencyRequired:
      mode: inherit
      value: {  }
    credentialCategory:
      mode: inherit
      value: {  }
    recognizedBy:
      mode: inherit
      value: {  }
    expires:
      mode: disable
      value: {  }
    url:
      mode: disable
      value: {  }
    validFor:
      mode: disable
      value: {  }
    abstract:
      mode: disable
      value: {  }
    accessMode:
      mode: disable
      value: {  }
    accessModeSufficient:
      mode: disable
      value: {  }
    accessibilityAPI:
      mode: disable
      value: {  }
    accessibilityControl:
      mode: disable
      value: {  }
    accessibilityFeature:
      mode: disable
      value: {  }
    accessibilityHazard:
      mode: disable
      value: {  }
    accessibilitySummary:
      mode: disable
      value: {  }
    accountablePerson:
      mode: disable
      value: {  }
    acquireLicensePage:
      mode: disable
      value: {  }
    aggregateRating:
      mode: disable
      value: {  }
    alternativeHeadline:
      mode: disable
      value: {  }
    archivedAt:
      mode: disable
      value: {  }
    associatedMedia:
      mode: disable
      value: {  }
    audio:
      mode: disable
      value: {  }
    author:
      mode: disable
      value: {  }
    award:
      mode: disable
      value: {  }
    character:
      mode: disable
      value: {  }
    citation:
      mode: disable
      value: {  }
    comment:
      mode: disable
      value: {  }
    commentCount:
      mode: disable
      value: {  }
    conditionsOfAccess:
      mode: disable
      value: {  }
    contentLocation:
      mode: disable
      value: {  }
    contentRating:
      mode: disable
      value: {  }
    contentReferenceTime:
      mode: disable
      value: {  }
    contributor:
      mode: disable
      value: {  }
    copyrightHolder:
      mode: disable
      value: {  }
    copyrightNotice:
      mode: disable
      value: {  }
    copyrightYear:
      mode: disable
      value: {  }
    correction:
      mode: disable
      value: {  }
    countryOfOrigin:
      mode: disable
      value: {  }
    creativeWorkStatus:
      mode: disable
      value: {  }
    creator:
      mode: disable
      value: {  }
    creditText:
      mode: disable
      value: {  }
    dateCreated:
      mode: disable
      value: {  }
    dateModified:
      mode: disable
      value: {  }
    digitalSourceType:
      mode: disable
      value: {  }
    discussionUrl:
      mode: disable
      value: {  }
    displayLocation:
      mode: disable
      value: {  }
    editEIDR:
      mode: disable
      value: {  }
    editor:
      mode: disable
      value: {  }
    educationalAlignment:
      mode: disable
      value: {  }
    educationalUse:
      mode: disable
      value: {  }
    encoding:
      mode: disable
      value: {  }
    encodingFormat:
      mode: disable
      value: {  }
    exampleOfWork:
      mode: disable
      value: {  }
    funder:
      mode: disable
      value: {  }
    funding:
      mode: disable
      value: {  }
    genre:
      mode: disable
      value: {  }
    hasPart:
      mode: disable
      value: {  }
    headline:
      mode: disable
      value: {  }
    identifier:
      mode: disable
      value: {  }
    image:
      mode: disable
      value: {  }
    inLanguage:
      mode: disable
      value: {  }
    interactionStatistic:
      mode: disable
      value: {  }
    interactivityType:
      mode: disable
      value: {  }
    interpretedAsClaim:
      mode: disable
      value: {  }
    isAccessibleForFree:
      mode: disable
      value: {  }
    isBasedOn:
      mode: disable
      value: {  }
    isFamilyFriendly:
      mode: disable
      value: {  }
    isPartOf:
      mode: disable
      value: {  }
    learningResourceType:
      mode: disable
      value: {  }
    license:
      mode: disable
      value: {  }
    locationCreated:
      mode: disable
      value: {  }
    mainEntity:
      mode: disable
      value: {  }
    mainEntityOfPage:
      mode: disable
      value: {  }
    maintainer:
      mode: disable
      value: {  }
    material:
      mode: disable
      value: {  }
    materialExtent:
      mode: disable
      value: {  }
    mentions:
      mode: disable
      value: {  }
    offers:
      mode: disable
      value: {  }
    owner:
      mode: disable
      value: {  }
    pattern:
      mode: disable
      value: {  }
    position:
      mode: disable
      value: {  }
    potentialAction:
      mode: disable
      value: {  }
    producer:
      mode: disable
      value: {  }
    provider:
      mode: disable
      value: {  }
    publication:
      mode: disable
      value: {  }
    publisher:
      mode: disable
      value: {  }
    publisherImprint:
      mode: disable
      value: {  }
    publishingPrinciples:
      mode: disable
      value: {  }
    recordedAt:
      mode: disable
      value: {  }
    releasedEvent:
      mode: disable
      value: {  }
    review:
      mode: disable
      value: {  }
    sameAs:
      mode: disable
      value: {  }
    schemaVersion:
      mode: disable
      value: {  }
    sdDatePublished:
      mode: disable
      value: {  }
    sdLicense:
      mode: disable
      value: {  }
    sdPublisher:
      mode: disable
      value: {  }
    size:
      mode: disable
      value: {  }
    sourceOrganization:
      mode: disable
      value: {  }
    spatial:
      mode: disable
      value: {  }
    spatialCoverage:
      mode: disable
      value: {  }
    sponsor:
      mode: disable
      value: {  }
    subjectOf:
      mode: disable
      value: {  }
    teaches:
      mode: disable
      value: {  }
    temporal:
      mode: disable
      value: {  }
    temporalCoverage:
      mode: disable
      value: {  }
    text:
      mode: disable
      value: {  }
    thumbnail:
      mode: disable
      value: {  }
    thumbnailUrl:
      mode: disable
      value: {  }
    timeRequired:
      mode: disable
      value: {  }
    translationOfWork:
      mode: disable
      value: {  }
    translator:
      mode: disable
      value: {  }
    typicalAgeRange:
      mode: disable
      value: {  }
    usageInfo:
      mode: disable
      value: {  }
    version:
      mode: disable
      value: {  }
    video:
      mode: disable
      value: {  }
    wordCount:
      mode: disable
      value: {  }
    workExample:
      mode: disable
      value: {  }
    workTranslation:
      mode: disable
      value: {  }
updated_by: cd0a8902-c35f-474a-89f5-d360ea4ca6ae
updated_at: 1777314949
parent: b2c9f4a1-7e8d-4b3c-9a6f-1d0e5f7a9b2c
---
