---
id: 2b59bc6d-069d-4cb6-9324-630bfb52fdb7
blueprint: schema_object
title: 'Generic External Media Representative (Person)'
schema:
  type:
    - Person
  properties:
    additionalName:
      mode: disable
      value: {  }
    additionalType:
      mode: disable
      value: {  }
    address:
      mode: disable
      value: {  }
    affiliation:
      mode: disable
      value: {  }
    agentInteractionStatistic:
      mode: disable
      value: {  }
    alternateName:
      mode: disable
      value: {  }
    alumniOf:
      mode: disable
      value: {  }
    award:
      mode: disable
      value: {  }
    birthDate:
      mode: disable
      value: {  }
    birthPlace:
      mode: disable
      value: {  }
    brand:
      mode: disable
      value: {  }
    callSign:
      mode: disable
      value: {  }
    children:
      mode: disable
      value: {  }
    colleague:
      mode: disable
      value: {  }
    contactPoint:
      mode: disable
      value: {  }
    deathDate:
      mode: disable
      value: {  }
    deathPlace:
      mode: disable
      value: {  }
    description:
      mode: disable
      value: {  }
    disambiguatingDescription:
      mode: disable
      value: {  }
    email:
      mode: disable
      value: {  }
    familyName:
      mode: disable
      value: {  }
    faxNumber:
      mode: disable
      value: {  }
    follows:
      mode: disable
      value: {  }
    funder:
      mode: disable
      value: {  }
    funding:
      mode: disable
      value: {  }
    gender:
      mode: disable
      value: {  }
    givenName:
      mode: disable
      value: {  }
    hasCertification:
      mode: disable
      value: {  }
    hasCredential:
      mode: disable
      value: {  }
    hasOccupation:
      mode: custom
      value:
        -
          value:
            code: |-
              {{# Get the "Occupation Category Code for O*Net-SOC (CategoryCode)" `schema_objects` collection entry #}}
              {{ $onet_soc_codeset_fragment = { schema_template_node id="28cdee6e-d951-4129-b7f3-7ad10cc46754" } }}
              {{# Trim and decode it #}}
              {{ $onet_soc_in_code_set = $onet_soc_codeset_fragment | trim | from_json }}
              {{# Define the schema object properties #}}
              {{ $occupation_properties = [
                  'name' => [
                      'journalist',
                      'news analyst',
                      'reporter',
                  ],
                  'occupationalCategory' => [
                      '@type' => 'https://schema.org/CategoryCode',
                      'codeValue' => '27-3023.00',
                      'description' => 'Narrate or write news stories, reviews, or commentary for print, broadcast, or other communications media such as newspapers, magazines, radio, or television. May collect and analyze information through interview, investigation, or observation.',
                      'name' => 'News Analysts, Reporters, and Journalists',
                      'url' => 'https://www.onetonline.org/link/summary/27-3023.00',
                      'inCodeSet' => $onet_soc_in_code_set,
                  ],
              ] }}
              {{# Build the schema object #}}
              {{
                  schema_object_tag
                      type="https://schema.org/Occupation"
                      :properties="$occupation_properties"
              }}
            mode: html
          _id: VpBJUIK30VZ_xEWaRkkaa
          type: antlers
          enabled: true
    hasOfferCatalog:
      mode: disable
      value: {  }
    hasPOS:
      mode: disable
      value: {  }
    height:
      mode: disable
      value: {  }
    homeLocation:
      mode: disable
      value: {  }
    honorificPrefix:
      mode: disable
      value: {  }
    honorificSuffix:
      mode: disable
      value: {  }
    identifier:
      mode: disable
      value: {  }
    image:
      mode: disable
      value: {  }
    interactionStatistic:
      mode: disable
      value: {  }
    jobTitle:
      mode: custom
      value:
        -
          value: reporter
          _id: o2773bWdmkcouRwJQr6CG
          type: text_short
          enabled: true
        -
          value: journalist
          _id: 6bgqMh2_rxLspwBBLVVCr
          type: text_short
          enabled: true
        -
          value: 'news analyst'
          _id: 6bgqMh2_rxLspwBBLVVCs
          type: text_short
          enabled: true
    knows:
      mode: disable
      value: {  }
    knowsAbout:
      mode: disable
      value: {  }
    knowsLanguage:
      mode: disable
      value: {  }
    mainEntityOfPage:
      mode: disable
      value: {  }
    makesOffer:
      mode: disable
      value: {  }
    memberOf:
      mode: disable
      value: {  }
    name:
      mode: custom
      value:
        -
          value: 'media representative'
          _id: CIyhWy4blq9R_xVbF-Gz1
          type: text_short
          enabled: true
    nationality:
      mode: disable
      value: {  }
    netWorth:
      mode: disable
      value: {  }
    owns:
      mode: disable
      value: {  }
    parent:
      mode: disable
      value: {  }
    performerIn:
      mode: disable
      value: {  }
    potentialAction:
      mode: disable
      value: {  }
    pronouns:
      mode: disable
      value: {  }
    publishingPrinciples:
      mode: disable
      value: {  }
    relatedTo:
      mode: disable
      value: {  }
    sameAs:
      mode: disable
      value: {  }
    seeks:
      mode: disable
      value: {  }
    sibling:
      mode: disable
      value: {  }
    skills:
      mode: disable
      value: {  }
    sponsor:
      mode: disable
      value: {  }
    spouse:
      mode: disable
      value: {  }
    subjectOf:
      mode: disable
      value: {  }
    telephone:
      mode: disable
      value: {  }
    url:
      mode: disable
      value: {  }
    weight:
      mode: disable
      value: {  }
    workLocation:
      mode: disable
      value: {  }
    worksFor:
      mode: disable
      value: {  }
    availableLanguage:
      mode: disable
      value: {  }
updated_by: cd0a8902-c35f-474a-89f5-d360ea4ca6ae
updated_at: 1775672992
---
