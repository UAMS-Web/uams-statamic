---
id: 496550bb-510f-487c-8723-616f583c844a
blueprint: schema_object
title: 'MediaObject Type Base'
schema:
  type:
    - MediaObject
  properties:
    about:
      mode: inherit
      value: {  }
    abstract:
      mode: custom
      value:
        -
          value:
            code: '{{ caption | strip_tags | sanitize }}'
            mode: html
          _id: ANFeD5ddnR-zM13cyT2XT
          type: antlers
          enabled: true
        -
          value:
            code: '{{ alt | strip_tags | sanitize }}'
            mode: html
          _id: 7pYyJ4dDUJTGRJ9JTfqrG
          type: antlers
          enabled: true
        -
          value:
            code: '{{ excerpt | strip_tags | sanitize }}'
            mode: html
          _id: Y7F0pELgN5PfJuOoVOx6F
          type: antlers
          enabled: true
    accessMode:
      mode: custom
      value:
        -
          value:
            code: |-
              {{# 

              TODO

              Approved vocabulary: https://www.w3.org/community/reports/a11y-discov-vocab/CG-FINAL-vocabulary-20260326/#accessMode-vocabulary 

              #}}
            mode: html
          _id: M9yM00McIVMShVlli7a8w
          type: antlers
          enabled: true
    accessModeSufficient:
      mode: custom
      value:
        -
          value:
            code: |-
              {{# 

              TODO

              Approved vocabulary: https://www.w3.org/community/reports/a11y-discov-vocab/CG-FINAL-vocabulary-20260326/#accessModeSufficient-vocabulary

              #}}
            mode: html
          _id: C2qAIY3pxpChsznL3axPa
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
              {{# 

              TODO

              Approved vocabulary: https://www.w3.org/community/reports/a11y-discov-vocab/CG-FINAL-vocabulary-20260326/#accessibilityFeature-vocabulary

              #}}
            mode: html
          _id: DQeTTKwbwbqogmbGFQR8W
          type: antlers
          enabled: true
    accessibilityHazard:
      mode: custom
      value:
        -
          value:
            code: |-
              {{# 

              TODO

              Approved vocabulary: https://www.w3.org/community/reports/a11y-discov-vocab/CG-FINAL-vocabulary-20260326/#accessibilityHazard-vocabulary

              #}}
            mode: html
          _id: mBSQQx7PmtzRQ_O9jmWE4
          type: antlers
          enabled: true
    accessibilitySummary:
      mode: custom
      value:
        -
          value:
            code: |-
              {{# 

              TODO

              #}}
            mode: html
          _id: ZLAlktX8eC5ExmkX0s7nq
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
      mode: custom
      value:
        -
          value:
            code: |-
              {{# 

              TODO

              Maybe compute a field value that gets the `news_release` blueprint entries from the `post` collection where `featured_image_*` is the current asset.

              And then use whatever that winds up being to generate schema thing objects / refs here.

              #}}
            mode: html
          _id: j5zKo7u46lISizhSpPeOG
          type: antlers
          enabled: true
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
            code: '{{ schema_object type="https://schema.org/Person" :name="creator" }}'
            mode: html
          _id: ljkKOfAY2fHXPLV6cr2SX
          type: antlers
          enabled: true
        -
          value:
            code: '{{ schema_object type="https://schema.org/Organization" :name="creator_organization" }}'
            mode: html
          _id: 9lcox_L_JbOta4WZ67eqN
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{
                  schema_thing_from_relationship
                  mode="direct"
                  current_field="creator.generic_creator|replicator_row.creator.generic_creator|source_entry.creator.generic_creator"
              }}
            mode: html
          _id: N9sLsj4iYiVrSM6Bm7qf6
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
          _id: jyyKcY_XfPranA_WNTkKe
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
          _id: uLhRYesc8n8P-L3pyoMyb
          type: antlers
          enabled: true
    award:
      mode: inherit
      value: {  }
    bitrate:
      mode: custom
      value:
        -
          value:
            code: '{{ bitrate | format_bitrate }}'
            mode: html
          _id: ljkKOfAY3fHXPLV6cr2SX
          type: antlers
          enabled: true
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
      mode: custom
      value:
        -
          value:
            code: '{{ size | format_bytes }}'
            mode: html
          _id: gahxoWr9HzPgAuOFlxRO6
          type: antlers
          enabled: true
    contentUrl:
      mode: custom
      value:
        -
          value:
            code: '{{ permalink }}'
            mode: html
          _id: PN-48zaEytVhulI8yIeZH
          type: antlers
          enabled: true
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
          _id: tIeLVB4cuXWEE3ENrv-E1
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
          _id: TMPGnS8QwufuFvojJs5cU
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
          _id: cpkyGZ4slLvhkPBFSQaA23
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
          _id: eF1QKZV4eZZbMz0wzE31h
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
          _id: yv9z02V4NRf3BhNCG2vEi
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
            code: '{{ schema_object type="https://schema.org/Person" :name="creator" }}'
            mode: html
          _id: pp7R5gQXDL7wrArklsjPt
          type: antlers
          enabled: true
        -
          value:
            code: '{{ schema_object type="https://schema.org/Organization" :name="creator_organization" }}'
            mode: html
          _id: ZsyS7ZYGPrSCj5MT4iPNp
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{
                  schema_thing_from_relationship
                  mode="direct"
                  current_field="creator.generic_creator|replicator_row.creator.generic_creator|source_entry.creator.generic_creator"
              }}
            mode: html
          _id: 9QY_8BZs1YFS3Vk-C_J9r
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
          _id: NaSSX-ZHltX8mFzsY7meu
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
          _id: 5ZVEUSkco2--haFrTYraM
          type: antlers
          enabled: true
    creditText:
      mode: custom
      value:
        -
          value:
            code: '{{ credit_text }}'
            mode: html
          _id: w-dOBfc0Av8RcQvuPjwUl
          type: antlers
          enabled: true
    dateCreated:
      mode: inherit
      value: {  }
    dateModified:
      mode: custom
      value:
        -
          value:
            code: '{{ updated_at | format("c") }}'
            mode: html
          _id: weqHWbv9foAAz07jyZmGd
          type: antlers
          enabled: true
    datePublished:
      mode: custom
      value:
        -
          value:
            code: '{{ date | format("c") }}'
            mode: html
          _id: RsCxAK6tKFJB0GKRdd_ft
          type: antlers
          enabled: true
    description:
      mode: custom
      value:
        -
          value:
            code: '{{ caption | strip_tags | sanitize }}'
            mode: html
          _id: wnR3u29mG4fRz0LN7mgu-
          type: antlers
          enabled: true
        -
          value:
            code: '{{ alt | strip_tags | sanitize }}'
            mode: html
          _id: FuVpwAYTNCGM0d7mOhINs
          type: antlers
          enabled: true
        -
          value:
            code: '{{ excerpt | strip_tags | sanitize }}'
            mode: html
          _id: CaYLQ3UBYPjUt4eD7dR-Y
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
    duration:
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
          _id: tbSB_Hhi7_aCotuAfNgUs
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
    embedUrl:
      mode: inherit
      value: {  }
    encodesCreativeWork:
      mode: inherit
      value: {  }
    encoding:
      mode: inherit
      value: {  }
    encodingFormat:
      mode: custom
      value:
        -
          value:
            code: '{{ mime_type }}'
            mode: html
          _id: 1GCHY_1C2fGu5usfKfr-8
          type: antlers
          enabled: true
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
      mode: custom
      value:
        -
          value:
            code: |-
              {{# Switch statement to define genre by container, then fallback to series:genre/genre #}}
              {{ $object_genre = switch(
                  ( container == 'portraits' || container == 'portraits_wide' ) => 'http://vocab.getty.edu/aat/300451604',
                  ( container == 'logos' || container == 'logos_unrestricted' ) => 'http://vocab.getty.edu/aat/300028715',
                  ( container == 'news_releases' ) => 'https://vocab.getty.edu/aat/300026437',
                  () => null
              ) }}
              {{ if ! $object_genre }}
                {{ $object_genre = source_entry:series ? source_entry:series:genre : ( replicator_row:series ? eplicator_row:series:genre : ( series ? series:genre : genre ) ) }}
              {{ /if }}
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
      mode: inherit
      value: {  }
    headline:
      mode: custom
      value:
        -
          value:
            code: '{{ title | strip_tags | sanitize }}'
            mode: html
          _id: OjB3Ab8Vpwsd1EBIRktcX
          type: antlers
          enabled: true
    height:
      mode: custom
      value:
        -
          value:
            code: '{{ height }}'
            mode: html
          _id: tq_XxxEivKCfOVK9vRpAU
          type: antlers
          enabled: true
        -
          value:
            code: "{{ height ? ( height + ' px' ) : null }}"
            mode: html
          _id: 0KXyK0RVfImtOSROeozca
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{ $height_properties = [
                  'value' => height,
                  'unitCode' => 'E37',
                  'unitText' => 'pixel'
              ] }}
              {{ 
                  schema_object type="https://schema.org/QuantitativeValue"
                  :properties="$height_properties"
                  :enabled="height"
              }}
            mode: html
          _id: Xmhq25FLPpVsazGEOT3ao
          type: antlers
          enabled: true
    identifier:
      mode: inherit
      value: {  }
    image:
      mode: inherit
      value: {  }
    inLanguage:
      mode: custom
      value:
        -
          value:
            code: |-
              {{# Prefer replicator_row:language, else root language (flattened asset) #}}
              {{ $in_language_src = replicator_row:language ?? language }}

              {{# Skip when no language value is available #}}
              {{ if $in_language_src }}
                  {{# List-shaped select values or augmented option lists #}}
                  {{ if ($in_language_src | is_array) }}
                      {{# First item tells option objects apart from plain codes #}}
                      {{ $in_language_first = $in_language_src | first }}

                      {{# Object rows: collect `value` and JSON-encode #}}
                      {{ if $in_language_first:value }}
                          {{ $in_language_src | pluck('value') | to_json }}

                      {{# One scalar in a single-element list #}}
                      {{ elseif ($in_language_src | count) == 1 }}
                          {{ $in_language_first }}

                      {{# Multiple scalar codes as JSON #}}
                      {{ else }}
                          {{ $in_language_src | to_json }}

                      {{ /if }}

                  {{# Single augmented object with a `value` property #}}
                  {{ elseif $in_language_src:value }}
                      {{ $in_language_src:value }}

                  {{# Otherwise, plain select string or other scalar #}}
                  {{ else }}
                      {{ $in_language_src }}

                  {{ /if }}
              {{ /if }}
            mode: html
          _id: OjB3Ab8Vpwsd2EBIRktcX
          type: antlers
          enabled: true
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
      mode: custom
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
      mode: custom
      value:
        -
          value:
            code: |-
              {{# 

              TODO

              Add relevant fields to relevant asset container blueprints to allow for defining who or what the asset is about (what is its main entity).

              For example, associating a `people` collection entry with a `portraits`/`portraits_wide` asset. Or computing said value based on the relevant `assets` fieldtype fields in the relevant blueprints (e.g., the portrait input on a `people` collection entry).

              And then use whatever that winds up being to generate schema thing objects / refs here.

              #}}
            mode: html
          _id: o-NMh3A9e8OfzZ9r8p_2G
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
    mentions:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ schema_thing_from_relationship
                  mode="direct"
                  current_field="mentions"
                  direct_scope="subject_matter|clinical_specializations"
                  schema_object="f15f2efa-258e-4b16-aa4e-9010bce7514c"
              }}
            mode: html
          _id: _eAzS3_Ki_d8Zgpm8soej
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{ schema_thing_from_relationship
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
          _id: _eAzS4_Ki_d8Zgpm9soej
          type: antlers
          enabled: true
    name:
      mode: custom
      value:
        -
          value:
            code: '{{ title | strip_tags | sanitize }}'
            mode: html
          _id: rIej2gIPGBJ4bgksVa9U-
          type: antlers
          enabled: true
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
          _id: kGpF4FG8dnPmZ9z9kh182
          type: antlers
          enabled: true
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
      mode: custom
      value:
        -
          value:
            code: '{{ date | format("c") }}'
            mode: html
          _id: tZa_PWa3DaQ1OhTFjoEIT
          type: antlers
          enabled: true
    url:
      mode: custom
      value:
        -
          value:
            code: '{{ permalink }}'
            mode: html
          _id: 4aNvWDOy4e8AYHkd_zkhR
          type: antlers
          enabled: true
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
      mode: custom
      value:
        -
          value:
            code: '{{ width }}'
            mode: html
          _id: WsCDyaTcaqRTIfjJvMAHv
          type: antlers
          enabled: true
        -
          value:
            code: "{{ width ? ( width + ' px' ) : null }}"
            mode: html
          _id: dihHGLrjaWfF-Tzdm_LIX
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{ $width_properties = [
                  'value' => width,
                  'unitCode' => 'E37'
                  'unitText' => 'pixel'
              ] }}
              {{ 
                  schema_object type="https://schema.org/QuantitativeValue"
                  :properties="$width_properties"
                  :enabled="width"
              }}
            mode: html
          _id: 5oPiSt9SVIKJPfbg7Th6n
          type: antlers
          enabled: true
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
updated_at: 1774910263
parent: 113b8a1e-b794-4c36-99c8-62777daf8b5b
---
