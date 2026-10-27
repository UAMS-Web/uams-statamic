---
id: ddfd890c-cc51-4043-aef0-bff74e010bd9
blueprint: schema_object
title: 'Occupation Category Code for O*Net-SOC (CategoryCode)'
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
          _id: t6wBvZSYltYIWQhubyqyh
          type: antlers
          enabled: true
    description:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ onet_occupation_description :code="value" }}
            mode: html
          _id: aj3R_TPHycASNwbWIBiq_
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
            - 28cdee6e-d951-4129-b7f3-7ad10cc46754
          _id: '-qs2-H8Fu2w44_sFis8BD'
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
              {{ onet_occupation_title :code="value" }}
            mode: html
          _id: KvR3F08lWHucvaaD-nQhC
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
      mode: custom
      value:
        -
          value:
            code: |-
              {{ value ? ( 'https://www.onetonline.org/link/summary/' + value ) : '' }}
            mode: html
          _id: tgV2C2VN9-GY_w_XO73FV
          type: antlers
          enabled: true
updated_by: cd0a8902-c35f-474a-89f5-d360ea4ca6ae
updated_at: 1776950133
parent: 7f455fcf-b472-4c11-91e6-b0b704941929
---
