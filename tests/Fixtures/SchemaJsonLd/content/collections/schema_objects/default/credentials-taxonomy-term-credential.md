---
id: b2c9f4a1-7e8d-4b3c-9a6f-1d0e5f7a9b2c
blueprint: schema_object
title: 'Credentials Taxonomy Term — Credential Type (EducationalOccupationalCredential)'
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
              {{ $ctdl_value = ctdl.value ?? null }}
              {{
                  if
                      $ctdl_value
                      && $ctdl_value != 'Diploma'
                      && $ctdl_value != 'Degree'
              }}
                  {{ 
                      partial:schema_objects/subject_matter_from_specialties
                      emit="about"
                  }}
              {{ /if }}
            mode: html
          _id: zsIZ5Kf07CH5l_MB14-h-
          type: antlers
          enabled: true
    additionalType:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $ctdl_value = ctdl.value ?? null }}
              {{ $ctdl_uri = $ctdl_value ? ( 'https://purl.org/ctdl/terms/' + $ctdl_value ) : '' }}
              {{ $ctdl_uri }}
            mode: html
          _id: '-DVnEOnABR-q4Eo_l-bIJ'
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{ $ctdl_value = ctdl.value ?? null }}
              {{
                  if
                      $ctdl_value != 'Certification'
                      && certifications_term
              }}
                  {{ $statutory_credential_query = certifications_term.statutory_credential_query }}
              {{ else }}
                  {{ $statutory_credential_query = clinical_statutory_credential_query }}
              {{ /if }}
              {{ if $statutory_credential_query }}
                  {{# Wikidata item for 'statutory credential' #}}
                  https://www.wikidata.org/wiki/Q139581896
              {{ /if }}
            mode: html
          _id: '-DVnEOnABR-q4Eo_l-bIG'
          type: antlers
          enabled: true
    alternateName:
      mode: custom
      value:
        -
          value:
            code: |-
              {{
                  schema_pronounceable_text
                  :in_language="source_site:lang ?? source_site:locale ?? site:lang ?? site:locale"
                  :pairs="abbreviation"
                  speech_to_text_markup="IPA"
              }}
            mode: html
          _id: pQ8rS9tU0vW1xY2zA3bC4d
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{ $alternate_name = alternate_name ?? alternatename ?? null }}
              {{ 
                  if
                      $alternate_name
                      && ( $alternate_name | is_array )
              }}
                  {{ $alternate_name | pluck('text') | strip_tags_array | sanitize_array | to_json }}
              {{ elseif $alternate_name }}
                  {{ $alternate_name | strip_tags | sanitize }}
              {{ /if }}
            mode: html
          _id: dCuunea-YH9Uk4igiqL0W
          type: antlers
          enabled: true
    assesses:
      mode: inherit
      value: {  }
    audience:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $ctdl_value = ctdl.value ?? null }}
              {{
                  if
                      $ctdl_value == 'Certification'
                      && certifications_term
              }}
                  {{ $audience = certifications_term.audience }}
              {{ else }}
                  {{ $audience = audience }}
              {{ /if }}
              {{
                  if
                      audience
                      && ( audience | is_iterable )
              }}
                  [{{ $audience }}
                      {{ if index > 0 }},{{ /if }}

                      {{# Audience Type Base (Audience; PeopleAudience; MedicalAudience; Patient; ParentAudience; Researcher; BusinessAudience; EducationalAudience) #}}
                      {{
                          schema_template_node 
                          id="79eb0493-a940-496b-ace0-46f89ab0aa50" 
                          :type_override="schema_type.value" 
                          :instance_index="index"
                      }}
                  {{ /$audience }}]
              {{ /if }}
            mode: html
          _id: i4tXiIfNiNambcYH5ZkvX
          type: antlers
          enabled: true
    competencyRequired:
      mode: inherit
      value: {  }
    credentialCategory:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $ctdl_value = ctdl.value ?? null }}
              {{ if $ctdl_value }}
                  {{ $credential_category_properties = [
                      '@id' => 'https://purl.org/ctdl/terms/' + $ctdl_value,
                      'name' => $ctdl_value,
                      'inDefinedTermSet' => [
                          '@type' => 'https://schema.org/DefinedTermSet',
                          '@id' => 'https://purl.org/ctdl/terms/',
                          'name' => 'Credential Transparency Description Language',
                      ],
                  ] }}
                  {{
                      schema_object_tag type="https://schema.org/DefinedTerm"
                      :properties="$credential_category_properties"
                      :enabled="$ctdl_value"
                  }}
              {{ /if }}
            mode: html
          _id: eF5gH6iJ7kL8mN9oP0qR1s
          type: antlers
          enabled: true
    datePublished:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ activity_date_issued | format_schema_date }}
            mode: html
          _id: h92GZeFL4DuyeXyn-e-dE
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{ activity_end_date | format_schema_date }}
            mode: html
          _id: h92GZeFL4DuyeXyn-e-dF
          type: antlers
          enabled: true
    description:
      mode: custom
      value:
        -
          value:
            code: '{{ description | strip_tags | sanitize }}'
            mode: html
          _id: 3k07a3ZEI8H7MtrcI0Fnt
          type: antlers
          enabled: true
    disambiguatingDescription:
      mode: custom
      value:
        -
          value:
            code: '{{ disambiguating_description | strip_tags | sanitize }}'
            mode: html
          _id: BwdppIyiFE99u84idTpGc
          type: antlers
          enabled: true
    educationalLevel:
      mode: custom
      value:
        -
          value:
            code: |-
              {{
                  schema_template_node
                  id="0e009e39-c145-4f4e-8e6c-445d9f81dd22"
                  :instance_index="index"
              }}
            mode: html
          _id: lhm5FPYknTLToLqtKvGFZ
          type: antlers
          enabled: true
    expires:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ activity_date_expires | format_schema_date }}
            mode: html
          _id: nPnEQ42R_BzbUxEEX_NWv
          type: antlers
          enabled: true
    keywords:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $ctdl_value = ctdl.value ?? null }}
              {{ if $ctdl_value }}
                  {{
                      if
                          $ctdl_value != 'Diploma'
                          && $ctdl_value == 'Degree'
                  }}
                      {{ 
                          partial:schema_objects/subject_matter_from_specialties
                          emit="keywords"
                      }}
                  {{ /if }}
              {{ /if }}
            mode: html
          _id: asIZ5Kf07CH5l_MB14-h-
          type: antlers
          enabled: true
    name:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ ( full_name.text ?? full_name ) | strip_tags | sanitize }}
            mode: html
          _id: aB1cD2eF3gH4iJ5kL6mN7o
          type: antlers
          enabled: true
    recognizedBy:
      mode: inherit
      value: {  }
    sameAs:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $same_as = same_as ?? sameas ?? null }}
              {{
                  if
                      $same_as
                      && ( $same_as | is_array )
              }}
                {{ $same_as | pluck('url') | to_json }}
              {{ elseif $same_as }}
                {{ $same_as }}
              {{ /if }}
            mode: html
          _id: tU2vW3xY4zA5bC6dE7fG8h
          type: antlers
          enabled: true
    url:
      mode: custom
      value:
        -
          value:
            code: '{{ official_website }}'
            mode: html
          _id: FHipmxTtY10GzSwdZ63mS
          type: antlers
          enabled: true
    validFor:
      mode: inherit
      value: {  }
    validIn:
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
updated_at: 1776114928
---
