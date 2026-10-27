---
id: 1c6113fa-0aa1-48a6-b6ba-b72205426b8b
blueprint: schema_object
title: 'Occupation Category Code for International Standard Classification of Occupations 2008 / ISCO-08 (CategoryCode)'
schema:
  type:
    - CategoryCode
  properties:
    additionalType:
      mode: disable
      value: {  }
    alternateName:
      mode: disable
      value: {  }
    codeValue:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ value }}
            mode: html
          _id: wEAsmzLat8Mp_7BQ9uIDj
          type: antlers
          enabled: true
    description:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ enum_case enum="App\Enums\Isco08" :case="value" property="definition" }}
            mode: html
          _id: MoyLsoVJw9VS2enIx-pjf
          type: antlers
          enabled: true
    disambiguatingDescription:
      mode: disable
      value: {  }
    identifier:
      mode: disable
      value: {  }
    image:
      mode: disable
      value: {  }
    inCodeSet:
      mode: custom
      value:
        -
          value:
            - 2fac8bfe-fd98-46cb-ac40-d5a4150cd6cf
          _id: yV3FmhpPq9H4d41CldZE-
          type: thing_categorycodeset
          enabled: true
    inDefinedTermSet:
      mode: disable
      value: {  }
    mainEntityOfPage:
      mode: disable
      value: {  }
    name:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ enum_case enum="App\Enums\Isco08" :case="value" property="title" }}
            mode: html
          _id: R-V3FyS9tkwGxXCGG2_N2
          type: antlers
          enabled: true
    potentialAction:
      mode: disable
      value: {  }
    sameAs:
      mode: disable
      value: {  }
    subjectOf:
      mode: disable
      value: {  }
    termCode:
      mode: disable
      value: {  }
    url:
      mode: disable
      value: {  }
updated_by: cd0a8902-c35f-474a-89f5-d360ea4ca6ae
updated_at: 1776950091
parent: 7f455fcf-b472-4c11-91e6-b0b704941929
---
