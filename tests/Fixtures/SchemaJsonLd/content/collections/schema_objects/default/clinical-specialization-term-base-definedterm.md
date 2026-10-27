---
id: ab9c43d8-6cd9-4e2c-a752-633ea2747649
blueprint: schema_object
title: 'Clinical Specialization Term Base (DefinedTerm)'
schema:
  type:
    - DefinedTerm
  properties:
    alternateName:
      mode: custom
      value:
        -
          value:
            code: '{{ code_name_display }}'
            mode: html
          _id: GqLPxSf5uykB4755BLDXQ
          type: antlers
          enabled: true
        -
          value:
            code: '{{ title_specialization_patient }}'
            mode: html
          _id: rU6_bVrMbRFxD8xDji53L
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{ if title_specialization_patient_alt && ( title_specialization_patient_alt | is_array ) }}
                  {{ title_specialization_patient_alt | pluck('text') | to_json }}
              {{ elseif title_specialization_patient_alt }}
                  {{ title_specialization_patient_alt }}
              {{ /if }}
            mode: html
          _id: goZ-ZRz_43m4e8_iL2NLj
          type: antlers
          enabled: true
    description:
      mode: custom
      value:
        -
          value:
            code: '{{ code_definition }}'
            mode: html
          _id: MyKRLMqSXEocsEcnIn6Wx
          type: antlers
          enabled: true
    name:
      mode: custom
      value:
        -
          value:
            code: '{{ code_name }}'
            mode: html
          _id: gs224-tEI9MSRDHG_ShID
          type: antlers
          enabled: true
    sameAs:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ if same_as_field && ( same_as_field | is_array ) }}
                  {{ same_as_field | pluck('url') | to_json }}
              {{ elseif same_as_field }}
                  {{ same_as_field }}
              {{ /if }}
            mode: html
          _id: RbYrGHkg1p3q9HKz7bV-V
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{ medicalspecialty_member ? ( 'https://schema.org/' + medicalspecialty_member ) : '' }}
            mode: html
          _id: qivzIQuV3gXDjyqs3mJ1F
          type: antlers
          enabled: true
    disambiguatingDescription:
      mode: disable
      value: {  }
    about:
      mode: disable
      value: {  }
    additionalType:
      mode: disable
      value: {  }
    identifier:
      mode: disable
      value: {  }
    image:
      mode: disable
      value: {  }
    inDefinedTermSet:
      mode: disable
      value: {  }
    mainEntityOfPage:
      mode: disable
      value: {  }
    owner:
      mode: disable
      value: {  }
    potentialAction:
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
updated_at: 1778528032
cms_specialty_code:
  -
    id: Sj5v8obPR0R6CbgYlNC_2
    value: '{{ cms_code }}'
    type: antlers
    enabled: true
isco_08_code:
  -
    id: ygr2WRvaFnq_BUi4GBvcL
    value: '{{ isco08_code }}'
    type: antlers
    enabled: true
onet_soc_occupation_code:
  -
    id: sF4orsAYQlL3qTIoeCKaG
    value: '{{ onetsoc_code }}'
    type: antlers
    enabled: true
---
