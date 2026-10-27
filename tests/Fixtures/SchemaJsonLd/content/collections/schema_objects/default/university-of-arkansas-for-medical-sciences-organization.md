---
id: 58185f50-22a4-48e7-9575-b86a1fdffef9
blueprint: schema_object
title: 'University of Arkansas for Medical Sciences (CollegeOrUniversity; MedicalOrganization; ResearchOrganization; GovernmentOrganization; LocalBusiness)'
schema:
  type:
    - CollegeOrUniversity
    - MedicalOrganization
    - ResearchOrganization
    - GovernmentOrganization
    - LocalBusiness
  properties:
    additionalProperty:
      mode: inherit
      value: {  }
    additionalType:
      mode: custom
      value:
        -
          value: 'https://www.wikidata.org/wiki/Q1916585'
          _id: 5aTZxRLeYhxLRqiaIDGBl
          type: url
          enabled: true
        -
          value: 'https://www.wikidata.org/wiki/Q23002039'
          _id: 1G_qvgCyBlHFsUsftDniD
          type: url
          enabled: true
    address:
      mode: custom
      value:
        -
          value:
            - 09aebc98-824d-4568-b684-414e401b9be7
          _id: jEGCWE8GLABxtX083Tdzz
          type: thing_postaladdress
          enabled: true
    alternateName:
      mode: custom
      value:
        -
          value: UAMS
          _id: oFlUgrkNZCVSPRCH39PRr
          type: text_short
          enabled: true
        -
          value: 'Arkansas Industrial University Medical Department'
          _id: nZtmgNIPbtb3-u0jZOA6U
          type: text_short
          enabled: true
    alumni:
      mode: inherit
      value: {  }
    areaServed:
      mode: custom
      value:
        -
          value:
            - e7d0ba0c-7cd4-4cf6-a7bd-bf6ae0259dd3
          _id: A3fez0wLfvIT3cYjYJ8qo
          type: thing_administrativearea
          enabled: true
    award:
      mode: inherit
      value: {  }
    brand:
      mode: inherit
      value: {  }
    companyRegistration:
      mode: inherit
      value: {  }
    contactPoint:
      mode: inherit
      value: {  }
    containedInPlace:
      mode: custom
      value:
        -
          value:
            - b764e8a2-6abf-4118-be7e-8964f02668d5
          _id: 7frEEgRA-HNEI7JOZJxXu
          type: thing_place
          enabled: true
    containsPlace:
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
    email:
      mode: inherit
      value: {  }
    employee:
      mode: custom
      value:
        -
          value:
            - 720003e3-1f68-4721-9b52-801d7c4fc55a
          _id: bL3MMdMbON2_Pt1jhpR8D
          type: thing_person
          enabled: true
    employees:
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
      mode: custom
      value:
        -
          value:
            code: |-
              {{
                  schema_thing_from_relationship
                  mode="global"
                  global_handle="uams_multisite_settings"
                  global_field="founder"
                  merge_source_context="false"
                  :site="source_site"
                  schema_object="820bc0fe-f4c3-4878-9ad4-ddaba3660e00"
                  merge_source_context="false"
              }}
            mode: html
          _id: zic9dy1woYn9u1F0s
          type: antlers
          enabled: true
    foundingDate:
      mode: custom
      value:
        -
          value:
            mode: html
            code: |-
              {{ $founding_date = { get_global handle="uams_multisite_settings" var="founding_date" :site="source_site" } }}
              {{ $founding_date | format_schema_date }}
          _id: Q_d3ApHXph-T2McKsveH4
          type: antlers
          enabled: true
    foundingLocation:
      mode: custom
      value:
        -
          value:
            mode: html
            code: |-
              {{ $founding_place_address = { get_global handle="uams_multisite_settings" var="founding_location" :site="source_site" } }}
              {{ schema_place :address="$founding_place_address" }}
          _id: b2O6ME9f2SrblP7tvpTN4
          type: antlers
          enabled: true
    funder:
      mode: inherit
      value: {  }
    funding:
      mode: inherit
      value: {  }
    geo:
      mode: custom
      value:
        -
          value:
            - 6068f833-5db6-4fd4-a392-34efbfc6026e
          _id: 7n_WZlfmZ2LYiNTvdD4fQ
          type: thing_geocoordinates
          enabled: true
    geoWithin:
      mode: inherit
      value: {  }
    hasCertification:
      mode: inherit
      value: {  }
    hasCredential:
      mode: inherit
      value: {  }
    hasMap:
      mode: inherit
      value: {  }
    hasOfferCatalog:
      mode: inherit
      value: {  }
    image:
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
    latitude:
      mode: custom
      value:
        -
          value: '34.74828329990028'
          _id: TWUxzRg_Y7QeQAvd25jyu
          type: number
          enabled: true
    legalAddress:
      mode: custom
      value:
        -
          value:
            - c29cecbc-9c19-49e5-8407-7da18ad2a670
          _id: 7ezW4aZhW7pSlbEoNdle5
          type: thing_postaladdress
          enabled: true
    legalName:
      mode: custom
      value:
        -
          value: 'University of Arkansas System doing business as the Board of Trustees for the University of Arkansas acting for and on behalf of the University of Arkansas for Medical Sciences'
          _id: JgW_AL_oBnTsMwl224AkG
          type: text_short
          enabled: true
    legalRepresentative:
      mode: custom
      value:
        -
          value:
            - 720003e3-1f68-4721-9b52-801d7c4fc55a
          _id: 6HKJhkEXQBKvKNrx0OU92
          type: thing_person
          enabled: true
    location:
      mode: custom
      value:
        -
          value:
            - 09aebc98-824d-4568-b684-414e401b9be7
          _id: uEKENZ-ZxhkKBCEIt1go_
          type: thing_postaladdress
          enabled: true
    logo:
      mode: custom
      value:
        -
          value:
            code: '{{ get_schema_logo_urls :site="( site_source ?? site )" }}'
            mode: html
          _id: AO0dowC5LoK4pbe8E6oT5
          type: antlers
          enabled: true
    longitude:
      mode: custom
      value:
        -
          value: '-92.32145208472011'
          _id: bZeY8MNmI9eDuHoJa43mD
          type: number
          enabled: true
    mainEntityOfPage:
      mode: custom
      value:
        -
          value:
            code: '{{ get_site :handle="( source_site ?? site )" var="permalink" }}'
            mode: html
          _id: DmgMupE2qum9x0zlfY4dS
          type: antlers
          enabled: true
    makesOffer:
      mode: inherit
      value: {  }
    memberOf:
      mode: custom
      value:
        -
          value:
            - cc9bf16f-31c6-48ee-8495-6992f6c61fa2
          _id: b4tVWtAcBgKarwt5aqNnI
          type: thing_organization
          enabled: true
    name:
      mode: custom
      value:
        -
          value:
            code: '{{ get_site :handle="( source_site ?? site )" var="name" }}'
            mode: html
          _id: cRNPy78SRwxH9ZEWMf8ip
          type: antlers
          enabled: true
    numberOfEmployees:
      mode: custom
      value:
        -
          value:
            - f698e4b8-740d-4874-aa59-a21456f15905
          _id: 8PgDwQJTdX8Uo1_sbcF82
          type: thing_quantitativevalue
          enabled: true
    ownershipFundingInfo:
      mode: inherit
      value: {  }
    owns:
      mode: inherit
      value: {  }
    parentOrganization:
      mode: custom
      value:
        -
          value:
            - cc9bf16f-31c6-48ee-8495-6992f6c61fa2
          _id: OncvRXVpQJs8ZmBphsl2i
          type: thing_organization
          enabled: true
    photo:
      mode: inherit
      value: {  }
    potentialAction:
      mode: inherit
      value: {  }
    publicAccess:
      mode: custom
      value:
        -
          value: true
          _id: vMreBlOfjIj357d31jWT6
          type: boolean
          enabled: true
    sameAs:
      mode: custom
      value:
        -
          value: 'https://www.wikidata.org/wiki/Q941298'
          _id: XsGY-_Mwe7UmCihWV-JU1
          type: url
          enabled: true
    skills:
      mode: inherit
      value: {  }
    slogan:
      mode: custom
      value:
        -
          value: 'For a Better State of Health'
          _id: '-GiQ46yJbhY3jz6sqqU6c'
          type: text_short
          enabled: true
    smokingAllowed:
      mode: custom
      value:
        -
          value: false
          _id: jbari6QTuIDzuXtD085c7
          type: boolean
          enabled: true
    sponsor:
      mode: inherit
      value: {  }
    subOrganization:
      mode: inherit
      value: {  }
    subjectOf:
      mode: custom
      value:
        -
          value:
            - fba76c84-b260-4080-9077-c87093cda74a
          _id: eubssucw4OUGs9hgs2I8C
          type: thing_creativework
          enabled: true
    telephone:
      mode: custom
      value:
        -
          value: '+15016867000'
          _id: t3UITU6sLBracqgcXuuGz
          type: text_short
          enabled: true
    tourBookingPage:
      mode: inherit
      value: {  }
    url:
      mode: custom
      value:
        -
          value:
            code: '{{ get_site :handle="( source_site ?? site )" var="permalink" }}'
            mode: html
          _id: AIPqQq52D-udNIws4CVEK
          type: antlers
          enabled: true
    healthPlanNetworkId:
      mode: inherit
      value: {  }
    isAcceptingNewPatients:
      mode: custom
      value:
        -
          value: true
          _id: MjBnqf-1wDkYAha1XMlKx
          type: boolean
          enabled: true
    medicalSpecialty:
      mode: inherit
      value: {  }
    branchOf:
      mode: custom
      value:
        -
          value:
            - cc9bf16f-31c6-48ee-8495-6992f6c61fa2
          _id: ffafshUEnIdWLp0VpGvd_
          type: thing_organization
          enabled: true
updated_by: cd0a8902-c35f-474a-89f5-d360ea4ca6ae
updated_at: 1773690027
source_site: default
naics:
  -
    id: VYaAz99xI8QlIM81So9rZ
    value: '611310'
    type: naics
    enabled: true
isic_rev_4_code:
  -
    id: j58e1aNNVZWsSFtay1B-F
    value: '8610'
    type: isic_rev_4_code
    enabled: true
  -
    id: 1hlio3sMgVPjqWzqb6_Jj
    value: '8620'
    type: isic_rev_4_code
    enabled: true
  -
    id: HpOTB1sGnIAksvgMR3esv
    value: '8690'
    type: isic_rev_4_code
    enabled: true
  -
    id: oTrlPsP_f5TUPJpTc_Ozp
    value: '8530'
    type: isic_rev_4_code
    enabled: true
  -
    id: yB5h0dvBqKehlstXiLG82
    value: '7210'
    type: isic_rev_4_code
    enabled: true
duns:
  -
    id: osW0RMuDOFd3bg-0Dwn8Z
    value: '122452563'
    type: duns
    enabled: true
ein:
  -
    id: 9kXVrh0F45GcHRrlc-x_P
    value: 71-6046242
    type: ein
    enabled: true
parent: 533c2e00-8d26-415f-ac53-aefb668913a2
---
