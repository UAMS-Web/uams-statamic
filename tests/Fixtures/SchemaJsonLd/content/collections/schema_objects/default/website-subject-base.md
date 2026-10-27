---
id: 533c2e00-8d26-415f-ac53-aefb668913a2
blueprint: schema_object
title: 'Website Subject Base (Organization; Person)'
schema:
  type:
    - Organization
    - Person
  properties:
    acceptedPaymentMethod:
      mode: inherit
      value: {  }
    actionableFeedbackPolicy:
      mode: inherit
      value: {  }
    additionalType:
      mode: inherit
      value: {  }
    address:
      mode: inherit
      value: {  }
    agentInteractionStatistic:
      mode: inherit
      value: {  }
    aggregateRating:
      mode: inherit
      value: {  }
    alternateName:
      mode: inherit
      value: {  }
    alumni:
      mode: inherit
      value: {  }
    areaServed:
      mode: inherit
      value: {  }
    award:
      mode: inherit
      value: {  }
    brand:
      mode: inherit
      value: {  }
    contactPoint:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ schema_thing_from_relationship
                mode="secondary"
                global_handle="uams_multisite_settings"
                global_field="media_contact"
                secondary_context="terms"
                secondary_scope="media_contact_groups"
                secondary_field="people"
                nested_scope="people"
                merge_source_context="false"
                schema_object="53e08839-fade-4c73-b4d5-007896d296ac"
              }}
            mode: html
          _id: D7tG4e7F8_DdDFdguiMOD
          type: antlers
          enabled: true
    correctionsPolicy:
      mode: inherit
      value: {  }
    department:
      mode: inherit
      value: {  }
    description:
      mode: inherit
      value: {  }
    disambiguatingDescription:
      mode: inherit
      value: {  }
    dissolutionDate:
      mode: inherit
      value: {  }
    diversityPolicy:
      mode: inherit
      value: {  }
    diversityStaffingReport:
      mode: inherit
      value: {  }
    email:
      mode: inherit
      value: {  }
    employee:
      mode: inherit
      value: {  }
    ethicsPolicy:
      mode: inherit
      value: {  }
    event:
      mode: inherit
      value: {  }
    faxNumber:
      mode: inherit
      value: {  }
    founder:
      mode: inherit
      value: {  }
    foundingDate:
      mode: inherit
      value: {  }
    foundingLocation:
      mode: inherit
      value: {  }
    funder:
      mode: inherit
      value: {  }
    funding:
      mode: inherit
      value: {  }
    hasCertification:
      mode: inherit
      value: {  }
    hasCredential:
      mode: inherit
      value: {  }
    hasMemberProgram:
      mode: inherit
      value: {  }
    hasMerchantReturnPolicy:
      mode: inherit
      value: {  }
    hasOfferCatalog:
      mode: inherit
      value: {  }
    hasPOS:
      mode: inherit
      value: {  }
    hasProductReturnPolicy:
      mode: inherit
      value: {  }
    hasShippingService:
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
    keywords:
      mode: inherit
      value: {  }
    knowsAbout:
      mode: inherit
      value: {  }
    knowsLanguage:
      mode: inherit
      value: {  }
    legalAddress:
      mode: inherit
      value: {  }
    legalName:
      mode: inherit
      value: {  }
    legalRepresentative:
      mode: inherit
      value: {  }
    location:
      mode: inherit
      value: {  }
    logo:
      mode: custom
      value:
        -
          value:
            code: '{{ get_schema_logo_urls }}'
            mode: html
          _id: vqK2rc3LmGkFW9fIATQcX
          type: antlers
          enabled: true
    mainEntityOfPage:
      mode: custom
      value:
        -
          value:
            code: '{{ source_site:permalink ?? site:permalink }}'
            mode: html
          _id: Z0VCAggjPbE35hLFzShAj
          type: antlers
          enabled: true
    makesOffer:
      mode: inherit
      value: {  }
    member:
      mode: inherit
      value: {  }
    memberOf:
      mode: inherit
      value: {  }
    name:
      mode: custom
      value:
        -
          value:
            code: '{{ source_site:name ?? site:name }}'
            mode: html
          _id: Xn5bf8pUec92VzoIKP6-x
          type: antlers
          enabled: true
    nonprofitStatus:
      mode: inherit
      value: {  }
    numberOfEmployees:
      mode: inherit
      value: {  }
    ownershipFundingInfo:
      mode: inherit
      value: {  }
    owns:
      mode: inherit
      value: {  }
    parentOrganization:
      mode: inherit
      value: {  }
    potentialAction:
      mode: custom
      value:
        -
          value:
            - 70ecf1e8-fdc3-4e6f-858c-04c939374b22
          _id: LpADeTcuTELIxVCgP-V0j
          type: thing_action
          enabled: true
    publishingPrinciples:
      mode: inherit
      value: {  }
    review:
      mode: inherit
      value: {  }
    sameAs:
      mode: inherit
      value: {  }
    seeks:
      mode: inherit
      value: {  }
    skills:
      mode: inherit
      value: {  }
    slogan:
      mode: inherit
      value: {  }
    sponsor:
      mode: inherit
      value: {  }
    subOrganization:
      mode: inherit
      value: {  }
    subjectOf:
      mode: inherit
      value: {  }
    telephone:
      mode: inherit
      value: {  }
    unnamedSourcesPolicy:
      mode: inherit
      value: {  }
    url:
      mode: custom
      value:
        -
          value:
            code: '{{ source_site:permalink ?? site:permalink }}'
            mode: html
          _id: Gj6eRIm_Tr7x-ebm67GEQ
          type: antlers
          enabled: true
    additionalName:
      mode: inherit
      value: {  }
    affiliation:
      mode: inherit
      value: {  }
    alumniOf:
      mode: inherit
      value: {  }
    birthDate:
      mode: inherit
      value: {  }
    birthPlace:
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
    deathDate:
      mode: inherit
      value: {  }
    deathPlace:
      mode: inherit
      value: {  }
    familyName:
      mode: inherit
      value: {  }
    follows:
      mode: inherit
      value: {  }
    gender:
      mode: inherit
      value: {  }
    givenName:
      mode: inherit
      value: {  }
    hasOccupation:
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
    jobTitle:
      mode: inherit
      value: {  }
    knows:
      mode: inherit
      value: {  }
    nationality:
      mode: inherit
      value: {  }
    netWorth:
      mode: inherit
      value: {  }
    parent:
      mode: inherit
      value: {  }
    performerIn:
      mode: inherit
      value: {  }
    pronouns:
      mode: inherit
      value: {  }
    relatedTo:
      mode: inherit
      value: {  }
    sibling:
      mode: inherit
      value: {  }
    spouse:
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
updated_at: 1775673427
---
