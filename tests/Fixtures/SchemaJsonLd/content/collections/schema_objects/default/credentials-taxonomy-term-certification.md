---
id: c3d0e5b2-8f9e-4c5d-ab1e-2f3a4b5c6d7e
blueprint: schema_object
title: 'Certification (Certification; EducationalOccupationalCredential)'
schema:
  type:
    - Certification
    - EducationalOccupationalCredential
  properties:
    about:
      mode: inherit_custom
      value:
        -
          value:
            code: |-
              {{ 
                  partial:schema_objects/subject_matter_from_specialties
                  emit="about"
              }}
            mode: html
          _id: 'cDVnEOnABR-q4Eo_l-bIH'
          type: antlers
          enabled: true
    additionalType:
      mode: inherit_custom
      value:
        -
          value:
            code: |-
              {{ 
                  if ( schema_primary_related_taxonomy_term.specialty_subspecialty_query ) 
                  && ( schema_primary_related_taxonomy_term.board_certification_query )
              }}
                  {{# Wikidata item for 'board certification' #}}
                  https://www.wikidata.org/wiki/Q4931289
              {{ elseif ( schema_primary_related_taxonomy_term.specialty_subspecialty_query ) }}
                  {{# Wikidata item for 'specialty certification' #}}
                  https://www.wikidata.org/wiki/Q139582137
              {{ else }}
                  Foo Bar Baz
              {{ /if }}
            mode: html
          _id: '-DVnEOnABR-q4Eo_l-bIH'
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{ if schema_primary_related_taxonomy_term.statutory_credential_query }}
                  {{# Wikidata item for 'statutory credential' #}}
                  https://www.wikidata.org/wiki/Q139581896
              {{ /if }}
            mode: html
          _id: '-DVnEOnABR-q4Eo_l-bII'
          type: antlers
          enabled: true
    alternateName:
      mode: inherit
      value: {  }
    assesses:
      mode: inherit
      value: {  }
    audience:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ if schema_primary_related_taxonomy_term.audience && ( schema_primary_related_taxonomy_term.audience | is_iterable ) }}
                  [{{ schema_primary_related_taxonomy_term.audience }}
                      {{ if index > 0 }},{{ /if }}

                      {{# Audience Type Base (Audience; PeopleAudience; MedicalAudience; Patient; ParentAudience; Researcher; BusinessAudience; EducationalAudience) #}}
                      {{
                          schema_template_node 
                          id="79eb0493-a940-496b-ace0-46f89ab0aa50" 
                          :type_override="schema_type.value" 
                          :instance_index="index"
                      }}
                  {{ /schema_primary_related_taxonomy_term.audience }}]
              {{ /if }}
            mode: html
          _id: aB1cD2eF3gH4iJ5kL6mN7s
          type: antlers
          enabled: true
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
    expires:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ if ( activity | certification_kind(type) ) != 'assessment_based_certificate' }}{{ activity_date_expires | format_schema_date }}{{ /if }}
            mode: html
          _id: Kc29Ex7mQpVt4LsWn8YbD
          type: antlers
          enabled: true
    keywords:
      mode: inherit_custom
      value:
        -
          value:
            code: |-
              {{ 
                  partial:schema_objects/subject_matter_from_specialties
                  emit="keywords"
              }}
            mode: html
          _id: 'eDVnEOnABR-q4Eo_l-bIH'
          type: antlers
          enabled: true
    name:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ schema_primary_related_taxonomy_term.name }}
            mode: html
          _id: aB1cD2eF3gH4iJ5kL6mN7o
          type: antlers
          enabled: true
    url:
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
    validFor:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ activity_valid_for }}
            mode: html
          _id: VRHgtzD3GODP4FLEKfuAF
          type: antlers
          enabled: true
    auditDate:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ if ( activity | certification_kind(type) ) != 'assessment_based_certificate' }}{{ activity_date_audit | format_schema_date }}{{ /if }}
            mode: html
          _id: Rq61D0evIJ8Qju4Sj1CQr
          type: antlers
          enabled: true
    certificationIdentification:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ activity_id }}
            mode: html
          _id: tlWP6FStQE9x3GBruS1Ik
          type: antlers
          enabled: true
    certificationStatus:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ if activity_active_query === true }}https://schema.org/CertificationActive{{ elseif activity_active_query === false }}https://schema.org/CertificationInactive{{ /if }}
            mode: html
          _id: O9l0EOlakTnytPOU7lS6S
          type: antlers
          enabled: true
    issuedBy:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ if schema_primary_related_taxonomy_term.organization && ( schema_primary_related_taxonomy_term.organization | is_iterable ) }}
                  [{{ schema_primary_related_taxonomy_term.organization }}
                      {{ if index > 0 }},{{ /if }}

                      {{# 
                          Get `schema_object` field value if it exists.
                          Otherwise, fall back to "Certifying Body Base (Organization)" `schema_objects` collection entry.
                      #}}
                      {{
                          schema_template_node 
                          id="{{ schema_object ?? 'eee70cb9-1b96-4fae-a2a1-b6df961b6c4e' }}" 
                          :instance_index="index"
                      }}
                  {{ /schema_primary_related_taxonomy_term.organization }}]
              {{ /if }}
            mode: html
          _id: xmVQZy6xfEiq1Y9vGiDqg
          type: antlers
          enabled: true
    validFrom:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ activity_date_issued | format_schema_date }}
            mode: html
          _id: LlVsYsuvzAz81ZBliHbnD
          type: antlers
          enabled: true
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
    certificationRating:
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
    hasMeasurement:
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
    logo:
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
