---
id: f15f2efa-258e-4b16-aa4e-9010bce7514c
blueprint: schema_object
title: 'Subject Matter Taxonomy Term (DefinedTerm)'
schema:
  type:
    - DefinedTerm
  properties:
    name:
      mode: custom
      value:
        -
          value:
            code: '{{ title | strip_tags | trim | sanitize }}'
            mode: html
          _id: WEN1J7Z9K4Rst_Rpq2VVX
          type: antlers
          enabled: true
    alternateName:
      mode: custom
      value:
        -
          value:
            code: |-
              {{# A clinical specialization reached through a subject field (#2823) keeps its alternate titles in title_specialization_patient_alt #}}
              {{ $alternate_name = alternate_name ?? title_specialization_patient_alt ?? null }}
              {{ if $alternate_name && ( $alternate_name | is_array ) }}
                  {{ $alternate_name | pluck('text') | to_json }}
              {{ elseif $alternate_name }}
                  {{ $alternate_name }}
              {{ /if }}
            mode: html
          _id: fYBVR5-3OT0IOXTzPPzp4
          type: antlers
          enabled: true
    sameAs:
      mode: custom
      value:
        -
          value:
            code: |-
              {{# A clinical specialization reached through a subject field (#2823) keeps its link in same_as_field #}}
              {{ $same_as = same_as ?? sameas ?? same_as_field ?? null }}
              {{ if $same_as && ( $same_as | is_array ) }}
                  {{ $same_as | pluck('url') | to_json }}
              {{ elseif $same_as }}
                  {{ $same_as }}
              {{ /if }}
            mode: html
          _id: odDtIyhmqP4glkhb6MYXG
          type: antlers
          enabled: true
    description:
      mode: custom
      value:
        -
          value:
            code: '{{ description | strip_tags | trim | sanitize }}'
            mode: html
          _id: odDtIyhmqP4glkhb6MYXH
          type: antlers
          enabled: true
    disambiguatingDescription:
      mode: custom
      value:
        -
          value:
            code: '{{ disambiguating_description | strip_tags | trim | sanitize }}'
            mode: html
          _id: odDtIyhmqP4glkhb6MYXI
          type: antlers
          enabled: true
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
updated_at: 1778524908
---
