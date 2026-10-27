---
id: f551d336-5322-4b21-b1ed-013d02c9fffe
blueprint: schema_object
title: 'Diploma (EducationalOccupationalCredential)'
schema:
  type:
    - EducationalOccupationalCredential
  properties:
    about:
      mode: custom
      value:
        -
          value:
            code: |-
              {{# Wikidata item for 'secondary education' #}}
              https://www.wikidata.org/wiki/Q13006147
            mode: html
          _id: zsIZ5Kf07CH5l_MB14-h1
          type: antlers
          enabled: true
    additionalType:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $activity = activity ?? null }}
              {{ $ctdl_uri = $activity ? ( 'https://purl.org/ctdl/terms/' + $activity ) : '' }}
              {{ $ctdl_uri }}
            mode: html
          _id: '-DVnEOnABR-q4Eo_l-bIK'
          type: antlers
          enabled: true
    alternateName:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $activity = activity ?? null }}
              {{ if $activity == 'SecondarySchoolDiploma' }}
                  {{
                      arr(
                          'secondary school diploma',
                          'high school degree'
                      ) | to_json
                  }}
              {{ elseif $activity == 'GeneralEducationDevelopment' }}
                  {{
                      arr(
                          'General Educational Development',
                          'GED',
                          'High School Equivalency Test',
                          'HiSET',
                          'high school equivalency',
                          'high school equivalency credential'
                          'Test Assessing Secondary Completion',
                          'TASC',
                          'California High School Proficiency Exam',
                          'CHSPE'
                      ) | to_json
                  }}
              {{ /if }}
            mode: html
          _id: '-DVnEOnABR-q4Eo_l-bIL'
          type: antlers
          enabled: true
    credentialCategory:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $activity = activity ?? null }}
              {{ if $activity }}
                  {{ $credential_category_properties = [
                      '@id' => 'https://purl.org/ctdl/terms/' + $activity,
                      'name' => $activity,
                      'inDefinedTermSet' => [
                          '@type' => 'https://schema.org/DefinedTermSet',
                          '@id' => 'https://purl.org/ctdl/terms/',
                          'name' => 'Credential Transparency Description Language',
                      ],
                  ] }}
                  {{
                      schema_object_tag type="https://schema.org/DefinedTerm"
                      :properties="$credential_category_properties"
                      :enabled="$activity"
                  }}
              {{ /if }}
            mode: html
          _id: '-DVnEOnABR-q4Eo_l-bIO'
          type: antlers
          enabled: true
    name:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $activity = activity ?? null }}
              {{ if $activity == 'SecondarySchoolDiploma' }}
                  high school diploma
              {{ elseif $activity == 'GeneralEducationDevelopment' }}
                  Certificate of High School Equivalency
              {{ /if }}
            mode: html
          _id: '-DVnEOnABR-q4Eo_l-bIM'
          type: antlers
          enabled: true
    sameAs:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $activity = activity ?? null }}
              {{ if $activity == 'SecondarySchoolDiploma' }}
                  https://www.wikidata.org/wiki/Q1312529
              {{ /if }}
            mode: html
          _id: '-DVnEOnABR-q4Eo_l-bIN'
          type: antlers
          enabled: true
    educationalLevel:
      mode: inherit
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
    assesses:
      mode: disable
      value: {  }
    associatedMedia:
      mode: disable
      value: {  }
    audience:
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
    competencyRequired:
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
    datePublished:
      mode: disable
      value: {  }
    description:
      mode: disable
      value: {  }
    digitalSourceType:
      mode: disable
      value: {  }
    disambiguatingDescription:
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
    expires:
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
    keywords:
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
    recognizedBy:
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
    url:
      mode: disable
      value: {  }
    usageInfo:
      mode: disable
      value: {  }
    validFor:
      mode: disable
      value: {  }
    validIn:
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
updated_at: 1777921214
---
