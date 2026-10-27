---
id: 113b8a1e-b794-4c36-99c8-62777daf8b5b
blueprint: schema_object
title: 'CreativeWork Type Base'
schema:
  type:
    - CreativeWork
  properties:
    about:
      mode: custom
      value:
        -
          value:
            code: |-
              {{# 
                  Get `subject_matter` terms from the `about` field in first non-empty context:
                    - `about` (`about` in current context)
                    - `replicator_row.about` (`about` in replicator row context)
                    - `source_entry.about` (`about` in source entry context)
              #}}
              {{
                  schema_thing_from_relationship
                  mode="direct"
                  current_field="about|replicator_row.about|source_entry.about"
                  direct_scope="subject_matter|clinical_specializations"
                  schema_object="f15f2efa-258e-4b16-aa4e-9010bce7514c"
              }}
            mode: html
          _id: 3GHXtDvmCK7cZC0XLGgub
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{#
                  Get `audio_series|video_series` terms from the `series` field in first non-empty context:
                    - `about` (`about` in current context)
                    - `replicator_row.about` (`about` in replicator row context)
                    - `source_entry.about` (`about` in source entry context)
                  
                  Get `subject_matter` terms `about` from `audio_series|video_series` terms
              #}}
              {{
                  schema_thing_from_relationship
                  mode="secondary"
                  current_field="series|replicator_row.series|source_entry.series"
                  secondary_context="terms"
                  secondary_scope="audio_series|video_series"
                  secondary_field="about"
                  nested_scope="subject_matter|clinical_specializations"
                  schema_object="f15f2efa-258e-4b16-aa4e-9010bce7514c"
              }}
            mode: html
          _id: 1DdZvd4W_I8rHusk4agMb
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{#
                  Get replicator rows from the `segments` field in current context; 
                  
                  Get assets from the `file` field in replicator rows.
                  
                  Get `subject_matter` terms from the `about` field in assets.
              #}}
              {{
                  schema_thing_from_relationship
                  mode="secondary"
                  current_field="segments"
                  current_field_replicator="true"
                  current_field_replicator_relationship_field="file"
                  secondary_context="assets"
                  secondary_field="about"
                  nested_scope="subject_matter|clinical_specializations"
                  schema_object="f15f2efa-258e-4b16-aa4e-9010bce7514c"
              }}
            mode: html
          _id: 4GHXtDvmCK7cZC0XLGgub
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{#
              
                  Get replicator rows from the `segments` field in current context.
                  
                  Get assets from the `file` field in replicator rows.
                  
                  Get `audio_series|video_series` terms from the `series` in assets.
                  
                  Get `subject_matter` terms from the `about` field in `audio_series|video_series` terms.
              
              #}}
              {{ 
                  schema_thing_from_relationship
                  mode="tertiary"
                  current_field="segments"
                  current_field_replicator="true"
                  current_field_replicator_relationship_field="file"
                  secondary_context="assets"
                  secondary_field="series"
                  intermediate_scope="audio_series|video_series"
                  tertiary_field="about"
                  nested_scope="subject_matter|clinical_specializations"
                  schema_object="f15f2efa-258e-4b16-aa4e-9010bce7514c"
              }}
            mode: html
          _id: '-E7zmTFzU8Gq-2N3YlGOt'
          type: antlers
          enabled: true
    abstract:
      mode: disable
      value: {  }
    accessMode:
      mode: custom
      value:
        -
          value: textual
          _id: lWw4FIbh1ydN9yHzXiZbp
          type: text_short
          enabled: true
        -
          value: visual
          _id: UUelKRPiYzk1VPL-V7KrM
          type: text_short
          enabled: true
    accessModeSufficient:
      mode: custom
      value:
        -
          value:
            - d027df25-a424-4cd3-b90a-1bfc6c7e0f68
          _id: GHvy40tLWJ05iepJtGKR-
          type: thing_itemlist
          enabled: true
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
      mode: custom
      value:
        -
          value:
            code: |-
              {{
                schema_thing_from_relationship
                mode="global"
                global_handle="uams_multisite_settings"
                global_field="accountable_person"
                merge_source_context="false"
              }}
            mode: html
          _id: qiKwTsYZr-sN8BjJKocl0
          type: antlers
          enabled: true
    acquireLicensePage:
      mode: disable
      value: {  }
    additionalType:
      mode: disable
      value: {  }
    aggregateRating:
      mode: disable
      value: {  }
    alternateName:
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
      mode: inherit
      value: {  }
    audio:
      mode: disable
      value: {  }
    author:
      mode: custom
      value:
        -
          value:
            - 58185f50-22a4-48e7-9575-b86a1fdffef9
          _id: BbMRQGOUYMgQS3faf78QB
          type: thing_organization
          enabled: true
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
      mode: inherit
      value: {  }
    copyrightHolder:
      mode: custom
      value:
        -
          value:
            code: |-
              {{
                  schema_thing_refs
                  mode="global"
                  global="uams_multisite_settings"
                  field="copyright_holder_schema_object"
                  :site="source_site"
              }}
            mode: html
          _id: ybGPCANmFxTh0qCnV9gdV
          type: antlers
          enabled: true
    copyrightNotice:
      mode: custom
      value:
        -
          value:
            mode: html
            code: |-
              {{
                  get_global
                  handle="uams_multisite_settings"
                  var="copyright_notice"
                  :site="source_site"
              }}
          _id: fYleUPGKHMUMdtKj3_jKb
          type: antlers
          enabled: true
    copyrightYear:
      mode: custom
      value:
        -
          value:
            code: |-
              {{
                  get_global
                  handle="uams_multisite_settings"
                  var="copyright_year"
                  :site="source_site"
              }}
            mode: html
          _id: Fw6vp6n8pyRImLYnHxlKd
          type: antlers
          enabled: true
    correction:
      mode: disable
      value: {  }
    countryOfOrigin:
      mode: custom
      value:
        -
          value:
            code: |-
              {{
                  schema_thing_refs
                  mode="global"
                  global="uams_multisite_settings"
                  field="country_of_origin"
                  :site="source_site"
              }}
            mode: html
          _id: UjTACg3wVfaJYPKXbgodd
          type: antlers
          enabled: true
    creativeWorkStatus:
      mode: custom
      value:
        -
          value: Published
          _id: 0kEN8RNd2qWcXhHYNsy95
          type: text_short
          enabled: true
    creator:
      mode: custom
      value:
        -
          value:
            - 58185f50-22a4-48e7-9575-b86a1fdffef9
          _id: ybGPCANmFxTh0qCnV1gdV
          type: thing_organization
          enabled: true
    creditText:
      mode: custom
      value:
        -
          value: 'University of Arkansas for Medical Sciences'
          _id: Bi25TGE7Oa6v1sr6Tn0bL
          type: text_short
          enabled: true
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
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $digital_source_type = digital_source_type ?? replicator_row:digital_source_type ?? source_entry:digital_source_type }}
              {{ digital_source_type | digital_source_type_value_schema }}
            mode: html
          _id: uXOSrDsC_af-AmaxAlxn6
          type: antlers
          enabled: true
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
      mode: custom
      value:
        -
          value: text/html
          _id: zdjynYMf4K0p0MQmUs4Ed
          type: text_short
          enabled: true
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
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $object_genre = source_entry:series ? source_entry:series:genre : ( replicator_row:series ? eplicator_row:series:genre : ( series ? series:genre : genre ) ) }}
              {{ if $object_genre && ( $object_genre | is_array ) }}
                {{ $object_genre | pluck('url') | to_json }}
              {{ elseif $object_genre }}
                {{ $object_genre }}
              {{ /if }}
            mode: html
          _id: hwegFXphnF016QRgkk5BM
          type: antlers
          enabled: true
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
      mode: custom
      value:
        -
          value:
            code: '{{ source_site:lang ?? source_site:locale ?? site:lang ?? site:locale }}'
            mode: html
          _id: z9xqmDTIPMgIwCp5Ybm6c
          type: antlers
          enabled: true
    interactionStatistic:
      mode: disable
      value: {  }
    interactivityType:
      mode: custom
      value:
        -
          value: expositive
          _id: RWvHMWk-RhaC-_EAIxQ4Y
          type: text_short
          enabled: true
    interpretedAsClaim:
      mode: disable
      value: {  }
    isAccessibleForFree:
      mode: custom
      value:
        -
          value: true
          _id: aNCPKR7OJc1bU_OmMOwPG
          type: boolean
          enabled: true
    isBasedOn:
      mode: disable
      value: {  }
    isFamilyFriendly:
      mode: custom
      value:
        -
          value: true
          _id: GwLnFg2XqBA8F9oSikeSa
          type: boolean
          enabled: true
    isPartOf:
      mode: disable
      value: {  }
    keywords:
      mode: custom
      value:
        -
          value:
            code: |-
              {{# 
                  Get `subject_matter` terms from the `about` field in first non-empty context:
                    - `about` (`about` in current context)
                    - `replicator_row.about` (`about` in replicator row context)
                    - `source_entry.about` (`about` in source entry context)
              #}}
              {{
                  schema_subject_matter_keywords
                  mode="direct"
                  current_field="about|replicator_row.about|source_entry.about"
                  direct_scope="subject_matter|clinical_specializations"
              }}
            mode: html
          _id: jjouelQUGWh4rW878wsLz
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{#
                  Get `audio_series|video_series` terms from the `series` field in first non-empty context:
                    - `about` (`about` in current context)
                    - `replicator_row.about` (`about` in replicator row context)
                    - `source_entry.about` (`about` in source entry context)
                  
                  Get `subject_matter` terms `about` from `audio_series|video_series` terms
              #}}
              {{
                  schema_subject_matter_keywords
                  mode="secondary"
                  current_field="series|replicator_row.series|source_entry.series"
                  secondary_context="terms"
                  secondary_scope="audio_series|video_series"
                  secondary_field="about"
                  nested_scope="subject_matter|clinical_specializations"
              }}
            mode: html
          _id: pQk2mN8vL5xR4tYbW9zJg
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{
                  schema_subject_matter_keywords
                  mode="secondary"
                  current_field="segments"
                  current_field_replicator="true"
                  current_field_replicator_relationship_field="file"
                  secondary_context="assets"
                  secondary_field="about"
                  nested_scope="subject_matter|clinical_specializations"
              }}
            mode: html
          _id: E0-ep0409YykcBgogNPzI
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{
                  schema_subject_matter_keywords
                  mode="tertiary"
                  current_field="segments"
                  current_field_replicator="true"
                  current_field_replicator_relationship_field="file"
                  secondary_context="assets"
                  secondary_field="series"
                  intermediate_scope="audio_series|video_series"
                  tertiary_field="about"
                  nested_scope="subject_matter|clinical_specializations"
              }}
            mode: html
          _id: sV7hF3cZ0nM6kD2pQ8wXe
          type: antlers
          enabled: true
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
      mode: inherit
      value: {  }
    maintainer:
      mode: custom
      value:
        -
          value:
            - 58185f50-22a4-48e7-9575-b86a1fdffef9
          _id: 8esG2IQg5SM6YbD608VOO
          type: thing_organization
          enabled: true
    material:
      mode: disable
      value: {  }
    materialExtent:
      mode: disable
      value: {  }
    mentions:
      mode: custom
      value:
        -
          value:
            code: |-
              {{# 
                  Get `subject_matter` terms from the `mentions` field in first non-empty context:
                    - `mentions` (`mentions` in current context)
                    - `replicator_row.mentions` (`mentions` in replicator row context)
                    - `source_entry.mentions` (`mentions` in source entry context)
              #}}
              {{
                  schema_thing_from_relationship
                  mode="direct"
                  current_field="mentions|replicator_row.mentions|source_entry.mentions"
                  direct_scope="subject_matter|clinical_specializations"
                  schema_object="f15f2efa-258e-4b16-aa4e-9010bce7514c"
              }}
            mode: html
          _id: _eAzS1_Ki_d8Zgpm7soej
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{
                  schema_thing_from_relationship
                  mode="secondary"
                  current_field="segments"
                  current_field_replicator="true"
                  current_field_replicator_relationship_field="file"
                  secondary_context="assets"
                  secondary_field="mentions"
                  nested_scope="subject_matter|clinical_specializations"
                  schema_object="f15f2efa-258e-4b16-aa4e-9010bce7514c"
              }}
            mode: html
          _id: _eAzS2_Ki_d8Zgpm7soej
          type: antlers
          enabled: true
    name:
      mode: disable
      value: {  }
    offers:
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
      mode: custom
      value:
        -
          value:
            code: |-
              {{
                  schema_thing_refs
                  mode="global"
                  global="uams_multisite_settings"
                  field="producer"
                  :site="source_site"
              }}
            mode: html
          _id: a9Q0m8F4cD2Zfl6DCp4Xh
          type: antlers
          enabled: true
    provider:
      mode: custom
      value:
        -
          value:
            - 58185f50-22a4-48e7-9575-b86a1fdffef9
          _id: z7lmer2qc5iHoJ5ea3CYm
          type: thing_organization
          enabled: true
    publication:
      mode: disable
      value: {  }
    publisher:
      mode: custom
      value:
        -
          value:
            code: |-
              {{
                  schema_thing_refs
                  mode="global"
                  global="uams_multisite_settings"
                  field="publisher"
                  :site="source_site"
              }}
            mode: html
          _id: uss1XGkTCzvjcy6AOzwE-
          type: antlers
          enabled: true
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
      mode: custom
      value: {  }
    sdDatePublished:
      mode: custom
      value:
        -
          value:
            code: '{{ ( updated_at ?? date ) | format("c") }}'
            mode: html
          _id: u9gtOrkU6Nzya_xM4Clwx
          type: antlers
          enabled: true
    sdLicense:
      mode: custom
      value: {  }
    sdPublisher:
      mode: custom
      value:
        -
          value:
            - 58185f50-22a4-48e7-9575-b86a1fdffef9
          _id: ukQj0UtuZ_hEV2kEUVhTZ
          type: thing_organization
          enabled: true
    size:
      mode: disable
      value: {  }
    sourceOrganization:
      mode: custom
      value:
        -
          value:
            code: |-
              {{
                  schema_thing_refs
                  mode="global"
                  global="uams_multisite_settings"
                  field="source_organization"
                  :site="source_site"
              }}
            mode: html
          _id: 2qI8I1zC_XLwdIws1zi7Q
          type: antlers
          enabled: true
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
      mode: custom
      value:
        -
          value: 18-
          _id: Vmm08sXUfO461dKVwyaY7
          type: text_short
          enabled: true
    url:
      mode: custom
      value:
        -
          value:
            code: '{{ permalink }}'
            mode: html
          _id: Kw3P3lZMkqIH0uOEOpG4g
          type: antlers
          enabled: true
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
updated_at: 1775166918
---
