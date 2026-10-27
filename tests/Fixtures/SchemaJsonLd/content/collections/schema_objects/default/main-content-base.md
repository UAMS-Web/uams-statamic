---
id: 31478350-9064-4665-835f-e2bc7aa768fc
blueprint: schema_object
title: 'Main Content Base'
schema:
  type:
    - CreativeWork
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
      mode: inherit_custom
      value:
        -
          value:
            code: |-
              {{
                  schema_thing_from_relationship
                  mode="direct"
                  current_field="creator.generic_creator|replicator_row.creator.generic_creator|source_entry.creator.generic_creator"
              }}
            mode: html
          _id: N9sLsj4iYiVrSM6Bm7qf5
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{
                  schema_thing_from_relationship
                  mode="direct"
                  current_field="creator.host|replicator_row.creator.host|source_entry.creator.host"
              }}
            mode: html
          _id: jyyKcY_XfPranA_WNTkKd
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{
                  schema_thing_from_relationship
                  mode="direct"
                  current_field="creator.writer|replicator_row.creator.writer|source_entry.creator.writer"
              }}
            mode: html
          _id: uLhRYesc8n8P-L2pyoMyb
          type: antlers
          enabled: true
    award:
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
      mode: custom
      value:
        -
          value:
            code: '{{ schema_thing_refs mode="linked_entries" field="geographic_location_content" linked_entries_field="schema_object" }}'
            mode: html
          _id: tgbLHcITqcOeLa-ZAYhJz
          type: antlers
          enabled: true
    contentRating:
      mode: inherit
      value: {  }
    contentReferenceTime:
      mode: inherit
      value: {  }
    contributor:
      mode: inherit_custom
      value:
        -
          value:
            code: |-
              {{
                  schema_thing_from_relationship
                  mode="direct"
                  current_field="creator.audio_editor|replicator_row.creator.audio_editor|source_entry.creator.audio_editor"
              }}
            mode: html
          _id: tIeLVB4cuXWEE3ENrv-EO
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{
                  schema_thing_from_relationship
                  mode="direct"
                  current_field="creator.guest|replicator_row.creator.guest|source_entry.creator.guest"
              }}
            mode: html
          _id: TMPGnS8QwufuFvojJs3cU
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{
                  schema_thing_from_relationship
                  mode="direct"
                  current_field="creator.sound_recordist|replicator_row.creator.sound_recordist|source_entry.creator.sound_recordist"
              }}
            mode: html
          _id: cpkyGZ4slLvhkPBFSQaA1
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{
                  schema_thing_from_relationship
                  mode="direct"
                  current_field="creator.video_editor|replicator_row.creator.video_editor|source_entry.creator.video_editor"
              }}
            mode: html
          _id: eF1QKZV4eZZbMz0wzE30h
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{
                  schema_thing_from_relationship
                  mode="direct"
                  current_field="creator.videographer|replicator_row.creator.videographer|source_entry.creator.videographer"
              }}
            mode: html
          _id: yv9z02V4NRf3BhNCG1vEi
          type: antlers
          enabled: true
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
      mode: inherit_custom
      value:
        -
          value:
            code: |-
              {{
                  schema_thing_from_relationship
                  mode="direct"
                  current_field="creator.generic_creator|replicator_row.creator.generic_creator|source_entry.creator.generic_creator"
              }}
            mode: html
          _id: 9QY_8BZs1YFS3Vk-C_J8r
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{
                  schema_thing_from_relationship
                  mode="direct"
                  current_field="creator.host|replicator_row.creator.host|source_entry.creator.host"
              }}
            mode: html
          _id: NaSSX-ZHltX8mFzsY6meu
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{
                  schema_thing_from_relationship
                  mode="direct"
                  current_field="creator.writer|replicator_row.creator.writer|source_entry.creator.writer"
              }}
            mode: html
          _id: 5ZVEUSkco1--haFrTYraM
          type: antlers
          enabled: true
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
      mode: inherit_custom
      value:
        -
          value:
            code: |-
              {{
                  schema_thing_from_relationship
                  mode="direct"
                  current_field="creator.editor|replicator_row.creator.editor|source_entry.creator.editor"
              }}
            mode: html
          _id: tbSB_Hhi6_aCotuAfNgUs
          type: antlers
          enabled: true
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
    learningResourceType:
      mode: inherit
      value: {  }
    license:
      mode: inherit
      value: {  }
    locationCreated:
      mode: custom
      value:
        -
          value:
            code: '{{ schema_thing_refs mode="linked_entries" field="geographic_location" linked_entries_field="schema_object" }}'
            mode: html
          _id: 06SLU_NcpVAjQ9RdJNT-T
          type: antlers
          enabled: true
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
    position:
      mode: inherit
      value: {  }
    potentialAction:
      mode: inherit
      value: {  }
    producer:
      mode: inherit_custom
      value:
        -
          value:
            code: |-
              {{
                  schema_thing_from_relationship
                  mode="direct"
                  current_field="creator.producer|replicator_row.creator.producer|source_entry.creator.producer"
              }}
            mode: html
          _id: kGpF4FG8dnPmZ9z9kh181
          type: antlers
          enabled: true
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
updated_at: 1775156242
parent: 9bb7ad28-6178-4b70-bb15-10c57ba1f0dc
---
