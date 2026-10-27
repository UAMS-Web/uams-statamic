---
id: bf9f4263-e3eb-4c45-ab9d-126f769eda38
blueprint: schema_object
title: 'Organization Type Base (Organization) — Different from Website Subject Base'
schema:
  type:
    - Organization
  properties:
    additionalType:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $additional_type = additional_type ?? additionaltype ?? null }}
              {{ if $additional_type && ( $additional_type | is_array ) }}
                  {{ $additional_type | pluck('uri') | to_json }}
              {{ elseif $additional_type }}
                  {{ $additional_type }}
              {{ /if }}
            mode: html
          _id: 61Fei_aRoFa7V1TXnzl03
          type: antlers
          enabled: true
    alternateName:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $alternate_name = alternate_name ?? alternatename ?? null }}
              {{ if $alternate_name && ( $alternate_name | is_array ) }}
                  {{ $alternate_name | pluck('text') | to_json }}
              {{ elseif $alternate_name }}
                  {{ $alternate_name }}
              {{ /if }}
            mode: html
          _id: 61Fei_aRoFa7V1TXnzl02
          type: antlers
          enabled: true
    description:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ description | strip_tags | sanitize }}
            mode: html
          _id: 60Fei_aRoFa7V1TXnzl33
          type: antlers
          enabled: true
    disambiguatingDescription:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ disambiguating_description | strip_tags | sanitize }}
            mode: html
          _id: 60Fei_aRoFa7V1TXnzl34
          type: antlers
          enabled: true
    name:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ name | strip_tags | sanitize }}
            mode: html
          _id: 61Fei_aRoFa7V1TXnzl01
          type: antlers
          enabled: true
    sameAs:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $same_as = same_as ?? sameas ?? null }}
              {{ if $same_as && ( $same_as | is_array ) }}
                  {{ $same_as | pluck('url') | to_json }}
              {{ elseif $same_as }}
                  {{ $same_as }}
              {{ /if }}
            mode: html
          _id: 61Fei_aRoFa7V1TXnzl00
          type: antlers
          enabled: true
    url:
      mode: custom
      value:
        -
          value:
            code: '{{ official_website }}'
            mode: html
          _id: 61Fei_aRoFa7V1TXnzl04
          type: antlers
          enabled: true
    acceptedPaymentMethod:
      mode: disable
      value: {  }
    actionableFeedbackPolicy:
      mode: disable
      value: {  }
    address:
      mode: disable
      value: {  }
    agentInteractionStatistic:
      mode: disable
      value: {  }
    aggregateRating:
      mode: disable
      value: {  }
    alumni:
      mode: disable
      value: {  }
    areaServed:
      mode: disable
      value: {  }
    award:
      mode: disable
      value: {  }
    brand:
      mode: disable
      value: {  }
    contactPoint:
      mode: disable
      value: {  }
    correctionsPolicy:
      mode: disable
      value: {  }
    department:
      mode: disable
      value: {  }
    dissolutionDate:
      mode: disable
      value: {  }
    diversityPolicy:
      mode: disable
      value: {  }
    diversityStaffingReport:
      mode: disable
      value: {  }
    email:
      mode: disable
      value: {  }
    employee:
      mode: disable
      value: {  }
    ethicsPolicy:
      mode: disable
      value: {  }
    event:
      mode: disable
      value: {  }
    faxNumber:
      mode: disable
      value: {  }
    founder:
      mode: disable
      value: {  }
    foundingDate:
      mode: disable
      value: {  }
    foundingLocation:
      mode: disable
      value: {  }
    funder:
      mode: disable
      value: {  }
    funding:
      mode: disable
      value: {  }
    hasCertification:
      mode: disable
      value: {  }
    hasCredential:
      mode: disable
      value: {  }
    hasMemberProgram:
      mode: disable
      value: {  }
    hasMerchantReturnPolicy:
      mode: disable
      value: {  }
    hasOfferCatalog:
      mode: disable
      value: {  }
    hasPOS:
      mode: disable
      value: {  }
    hasShippingService:
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
    keywords:
      mode: disable
      value: {  }
    knowsAbout:
      mode: disable
      value: {  }
    knowsLanguage:
      mode: disable
      value: {  }
    legalAddress:
      mode: disable
      value: {  }
    legalName:
      mode: disable
      value: {  }
    legalRepresentative:
      mode: disable
      value: {  }
    location:
      mode: disable
      value: {  }
    logo:
      mode: disable
      value: {  }
    mainEntityOfPage:
      mode: disable
      value: {  }
    makesOffer:
      mode: disable
      value: {  }
    member:
      mode: disable
      value: {  }
    memberOf:
      mode: disable
      value: {  }
    nonprofitStatus:
      mode: disable
      value: {  }
    numberOfEmployees:
      mode: disable
      value: {  }
    owner:
      mode: disable
      value: {  }
    ownershipFundingInfo:
      mode: disable
      value: {  }
    owns:
      mode: disable
      value: {  }
    parentOrganization:
      mode: disable
      value: {  }
    potentialAction:
      mode: disable
      value: {  }
    publishingPrinciples:
      mode: disable
      value: {  }
    review:
      mode: disable
      value: {  }
    seeks:
      mode: disable
      value: {  }
    skills:
      mode: disable
      value: {  }
    slogan:
      mode: disable
      value: {  }
    sponsor:
      mode: disable
      value: {  }
    subOrganization:
      mode: disable
      value: {  }
    subjectOf:
      mode: disable
      value: {  }
    telephone:
      mode: disable
      value: {  }
    unnamedSourcesPolicy:
      mode: disable
      value: {  }
updated_by: cd0a8902-c35f-474a-89f5-d360ea4ca6ae
updated_at: 1777902480
---
