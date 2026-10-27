---
id: 820bc0fe-f4c3-4878-9ad4-ddaba3660e00
blueprint: schema_object
title: 'Person Type Base'
schema:
  type:
    - Person
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
      mode: custom
      value:
        -
          value:
            code: |-
              {{ if email }}
                  {{ $language = ( ( language | count ) > 1 ) ? language : ( language | first ) }}
                  {{ $contact_point_properties = [
                      'email' => email,
                      'contactType' => 'office',
                      'availableLanguage' => $language,
                  ] }}
                  {{ schema_object type="https://schema.org/ContactPoint" :properties="$contact_point_properties" }}
              {{ /if }}
            mode: html
          _id: R0sJG0bLDmKh1dhkCg4yO
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{ if telephone }}
                  {{ $language = ( ( language | count ) > 1 ) ? language : ( language | first ) }}
                  {{ $contact_point_properties = [
                      'telephone' => telephone,
                      'contactType' => 'office',
                      'availableLanguage' => $language,
                  ] }}
                  {{ schema_object type="https://schema.org/ContactPoint" :properties="$contact_point_properties" }}
              {{ /if }}
            mode: html
          _id: XStEK-OwDhkVCqGLkSx4s
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{ if mitel_extension }}
                  {{ $language = ( ( language | count ) > 1 ) ? language : ( language | first ) }}
                  {{ $contact_point_properties = [
                      'telephone' => telephone,
                      'contactType' => 'office',
                      'description' => 'Internal Mitel extension; only accessible via campus desk phones.'
                      'areaServed' => [
                        '@type' => 'https://schema.org/Place',
                        'name' => 'University of Arkansas for Medical Sciences campus'
                      ],
                      'availableLanguage' => $language,
                  ] }}
                  {{ schema_object type="https://schema.org/ContactPoint" :properties="$contact_point_properties" }}
              {{ /if }}
            mode: html
          _id: WKYeAB-SeU7D1Q3Z4GhTi
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{ if telephone_mobile }}
                  {{ $language = ( ( language | count ) > 1 ) ? language : ( language | first ) }}
                  {{ $contact_point_properties = [
                      'telephone' => telephone_mobile,
                      'contactType' => 'mobile',
                      'availableLanguage' => $language,
                  ] }}
                  {{ schema_object type="https://schema.org/ContactPoint" :properties="$contact_point_properties" }}
              {{ /if }}
            mode: html
          _id: nqiLAhO3bjip7Q9HEXs1H
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{ if fax }}
                  {{ $language = ( ( language | count ) > 1 ) ? language : ( language | first ) }}
                  {{ $contact_point_properties = [
                      'faxNumber' => fax,
                      'contactType' => 'office',
                      'availableLanguage' => $language,
                  ] }}
                  {{ schema_object type="https://schema.org/ContactPoint" :properties="$contact_point_properties" }}
              {{ /if }}
            mode: html
          _id: h-1PD9p6oD6wWUA8umXf4
          type: antlers
          enabled: true
    deathDate:
      mode: inherit
      value: {  }
    deathPlace:
      mode: inherit
      value: {  }
    description:
      mode: inherit
      value: {  }
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
      mode: inherit
      value: {  }
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
updated_by: cd0a8902-c35f-474a-89f5-d360ea4ca6ae
updated_at: 1776110178
parent: 1a834c0c-f4af-4223-be3b-ee45b218cd39
---
