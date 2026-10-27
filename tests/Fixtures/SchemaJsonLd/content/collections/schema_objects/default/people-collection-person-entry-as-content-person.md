---
id: 430ada55-4f2e-4dab-907f-4d51fbbe26bc
blueprint: schema_object
title: 'People Collection — Person Entry as Content (Person; WebPageElement)'
schema:
  type:
    - Person
    - WebPageElement
  properties:
    additionalName:
      mode: inherit
      value: {  }
    additionalType:
      mode: inherit
      value: {  }
    address:
      mode: inherit
      value: {  }
    affiliation:
      mode: inherit
      value: {  }
    agentInteractionStatistic:
      mode: inherit
      value: {  }
    alternateName:
      mode: inherit
      value: {  }
    alumniOf:
      mode: inherit
      value: {  }
    award:
      mode: inherit
      value: {  }
    birthDate:
      mode: inherit
      value: {  }
    birthPlace:
      mode: inherit
      value: {  }
    brand:
      mode: inherit
      value: {  }
    callSign:
      mode: inherit
      value: {  }
    children:
      mode: inherit
      value: {  }
    colleague:
      mode: inherit
      value: {  }
    contactPoint:
      mode: inherit
      value: {  }
    deathDate:
      mode: inherit
      value: {  }
    deathPlace:
      mode: inherit
      value: {  }
    description:
      mode: inherit_custom
      value:
        -
          value:
            code: '{{ bio | raw | bard_text | sanitize }}'
            mode: html
          _id: Gu2T0WhC8vEvVpNYB4nY5
          type: antlers
          enabled: true
    disambiguatingDescription:
      mode: inherit
      value: {  }
    email:
      mode: inherit
      value: {  }
    familyName:
      mode: inherit
      value: {  }
    faxNumber:
      mode: inherit
      value: {  }
    follows:
      mode: inherit
      value: {  }
    funder:
      mode: inherit
      value: {  }
    funding:
      mode: inherit
      value: {  }
    gender:
      mode: inherit
      value: {  }
    givenName:
      mode: inherit
      value: {  }
    hasCertification:
      mode: inherit
      value: {  }
    hasCredential:
      mode: inherit
      value: {  }
    hasOccupation:
      mode: inherit
      value: {  }
    hasOfferCatalog:
      mode: inherit
      value: {  }
    hasPOS:
      mode: inherit
      value: {  }
    height:
      mode: inherit
      value: {  }
    homeLocation:
      mode: inherit
      value: {  }
    honorificPrefix:
      mode: inherit
      value: {  }
    honorificSuffix:
      mode: inherit
      value: {  }
    identifier:
      mode: inherit
      value: {  }
    image:
      mode: inherit
      value: {  }
    interactionStatistic:
      mode: inherit
      value: {  }
    jobTitle:
      mode: inherit
      value: {  }
    knows:
      mode: inherit
      value: {  }
    knowsAbout:
      mode: custom
      value:
        -
          value:
            code: '{{ schema_thing_from_relationship mode="direct" current_field="transitive_services" }}'
            mode: html
          _id: people1067knowsservices
          type: antlers
          enabled: true
        -
          value:
            code: '{{ schema_thing_from_relationship mode="direct" current_field="transitive_conditions" }}'
            mode: html
          _id: people1067knowsconditions
          type: antlers
          enabled: true
        -
          value:
            code: '{{ if has_special_interest }}{{ schema_template_node id="58dfa6bb-ef74-4143-b803-57b582865d8c" }}{{ /if }}'
            mode: html
          _id: people1067knowsspecialrole
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{#
                  Clinical Specialization entries (#2684), as the base node's loop renders them.
                  `knows_about_entries` lists them first, so the `index > 0` comma is correct.
              #}}
              [{{ knows_about_entries }}
                  {{ if collection:handle == 'clinical_specializations' }}
                      {{ if index > 0 }},{{ /if }}

                      {{ if extension_query === true }}
                          {{# Clinical Specialization Term Base (DefinedTerm) #}}
                          {{ schema_template_node id="ab9c43d8-6cd9-4e2c-a752-633ea2747649" :instance_index="index" }}

                      {{ else }}
                          {{# Clinical Specialization Term Canonical to Health Care Provider Taxonomy Code Set (DefinedTerm) #}}
                          {{ schema_template_node id="1cd75e43-3e72-4568-938b-0efd85059474" :instance_index="index" }}

                      {{ /if }}
                  {{ /if }}
              {{ /knows_about_entries }}]
            mode: html
          _id: people2684knowsspecializations
          type: antlers
          enabled: true
    knowsLanguage:
      mode: inherit
      value: {  }
    mainEntityOfPage:
      mode: inherit
      value: {  }
    makesOffer:
      mode: inherit
      value: {  }
    memberOf:
      mode: inherit
      value: {  }
    name:
      mode: inherit
      value: {  }
    nationality:
      mode: inherit
      value: {  }
    netWorth:
      mode: inherit
      value: {  }
    owns:
      mode: inherit
      value: {  }
    parent:
      mode: inherit
      value: {  }
    performerIn:
      mode: inherit
      value: {  }
    potentialAction:
      mode: inherit
      value: {  }
    pronouns:
      mode: inherit
      value: {  }
    publishingPrinciples:
      mode: inherit
      value: {  }
    relatedTo:
      mode: inherit
      value: {  }
    sameAs:
      mode: inherit
      value: {  }
    seeks:
      mode: inherit
      value: {  }
    sibling:
      mode: inherit
      value: {  }
    skills:
      mode: inherit
      value: {  }
    sponsor:
      mode: inherit
      value: {  }
    spouse:
      mode: inherit
      value: {  }
    subjectOf:
      mode: inherit
      value: {  }
    telephone:
      mode: inherit
      value: {  }
    url:
      mode: inherit
      value: {  }
    weight:
      mode: inherit
      value: {  }
    workLocation:
      mode: inherit
      value: {  }
    worksFor:
      mode: inherit
      value: {  }
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
    aggregateRating:
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
    cssSelector:
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
    digitalSourceType:
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
    genre:
      mode: inherit
      value: {  }
    hasPart:
      mode: inherit
      value: {  }
    headline:
      mode: inherit
      value: {  }
    inLanguage:
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
    offers:
      mode: inherit
      value: {  }
    pattern:
      mode: inherit
      value: {  }
    position:
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
    recordedAt:
      mode: inherit
      value: {  }
    releasedEvent:
      mode: inherit
      value: {  }
    review:
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
    xpath:
      mode: inherit
      value: {  }
updated_by: cd0a8902-c35f-474a-89f5-d360ea4ca6ae
updated_at: 1777228480
parent: 820bc0fe-f4c3-4878-9ad4-ddaba3660e00
---
