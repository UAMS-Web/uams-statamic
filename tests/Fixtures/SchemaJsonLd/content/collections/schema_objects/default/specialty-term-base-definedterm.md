---
id: 945499ea-b33d-43cc-b1f6-3a17e1bbacd7
blueprint: schema_object
title: 'Specialty Term Base (DefinedTerm)'
schema:
  type:
    - DefinedTerm
  properties:
    about:
      mode: custom
      value:
        -
          value:
            code: |-
              [{{ subject_matter }}
                  {{ if index > 0 }},{{ /if }}

                  {{ if collection:handle == 'clinical_specializations' }}
                      {{ if extension_query === true }}
                          {{# Clinical Specialization Term Base (DefinedTerm), #2823 #}}
                          {{ schema_template_node id="ab9c43d8-6cd9-4e2c-a752-633ea2747649" :instance_index="index" }}
                      {{ else }}
                          {{# Clinical Specialization Term Canonical to Health Care Provider Taxonomy Code Set (DefinedTerm), #2823 #}}
                          {{ schema_template_node id="1cd75e43-3e72-4568-938b-0efd85059474" :instance_index="index" }}
                      {{ /if }}
                  {{ else }}
                      {{# Subject Matter Taxonomy Term (DefinedTerm) #}}
                      {{ schema_template_node id="f15f2efa-258e-4b16-aa4e-9010bce7514c" }}
                  {{ /if }}
              {{ /subject_matter }}]
            mode: html
          _id: xBNwnaQVRvrRg3zjPIUfC
          type: antlers
          enabled: true
    alternateName:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ if alternate_name && ( alternate_name | is_array ) }}
                  {{ alternate_name | pluck('text') | to_json }}
              {{ elseif alternate_name }}
                  {{ alternate_name }}
              {{ /if }}
            mode: html
          _id: FOAO7uiBNx0BUR30C4TBl
          type: antlers
          enabled: true
    description:
      mode: custom
      value:
        -
          value:
            code: '{{ description | strip_tags | trim | sanitize }}'
            mode: html
          _id: b3awwDPqrhfoGUNZda0Ah
          type: antlers
          enabled: true
    disambiguatingDescription:
      mode: custom
      value:
        -
          value:
            code: '{{ disambiguating_description | strip_tags | trim | sanitize }}'
            mode: html
          _id: E_-KLJIPvni7ETyhEbrXU
          type: antlers
          enabled: true
    name:
      mode: custom
      value:
        -
          value:
            code: '{{ title | strip_tags | trim | sanitize }}'
            mode: html
          _id: qsrJresvsT1PaLUq4u940
          type: antlers
          enabled: true
    sameAs:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ if same_as && ( same_as | is_array ) }}
                  {{ same_as | pluck('url') | to_json }}
              {{ elseif same_as }}
                  {{ same_as }}
              {{ /if }}
            mode: html
          _id: JjiUU0l1GOCEX2onwA2Y1
          type: antlers
          enabled: true
    subjectOf:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ if program_requirements_url && ( program_requirements_url | is_array ) }}
                  {{ program_requirements_url | pluck('url') | to_json }}
              {{ elseif program_requirements_url }}
                  {{ program_requirements_url }}
              {{ /if }}
            mode: html
          _id: Ryw55qL2kLoon7ur0ARRt
          type: antlers
          enabled: true
    url:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ if official_website && ( official_website | is_array ) }}
                  {{ official_website | pluck('url') | to_json }}
              {{ elseif official_website }}
                  {{ official_website }}
              {{ /if }}
            mode: html
          _id: fN6ZJHZuHw_uh99zP7ETb
          type: antlers
          enabled: true
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
    termCode:
      mode: disable
      value: {  }
updated_by: cd0a8902-c35f-474a-89f5-d360ea4ca6ae
updated_at: 1778526238
---
