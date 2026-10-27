---
id: 684d7210-aca0-44ba-b6ef-dcd0803c7e2a
blueprint: schema_object
title: 'Academic Fields of Study Term (DefinedTerm)'
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
          _id: jYemureyQOvClFTnoolAt
          type: antlers
          enabled: true
    additionalType:
      mode: inherit
      value: {  }
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
          _id: JSe88oLPQ4uXVrRCYNODO
          type: antlers
          enabled: true
    description:
      mode: inherit
      value: {  }
    disambiguatingDescription:
      mode: inherit
      value: {  }
    identifier:
      mode: inherit
      value: {  }
    image:
      mode: inherit
      value: {  }
    inDefinedTermSet:
      mode: inherit
      value: {  }
    mainEntityOfPage:
      mode: inherit
      value: {  }
    name:
      mode: custom
      value:
        -
          value:
            code: '{{ title | strip_tags | trim | sanitize }}'
            mode: html
          _id: gHsfit56F1LX2KkY7RzZQ
          type: antlers
          enabled: true
    owner:
      mode: inherit
      value: {  }
    potentialAction:
      mode: inherit
      value: {  }
    sameAs:
      mode: inherit
      value: {  }
    subjectOf:
      mode: inherit
      value: {  }
    termCode:
      mode: inherit
      value: {  }
    url:
      mode: inherit
      value: {  }
updated_by: cd0a8902-c35f-474a-89f5-d360ea4ca6ae
updated_at: 1778528566
---
