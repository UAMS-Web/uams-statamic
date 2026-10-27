---
id: d4f58c4f-ee5c-4536-af1c-6e3dc3f2da4f
blueprint: schema_object
title: 'Person Entry Occupation From job_title (Occupation)'
schema:
  type:
    - Occupation
  properties:
    name:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ title.value | strip_tags | trim | sanitize }}
            mode: html
          _id: V57Zr83tJt4A9TGadB_qM
          type: antlers
          enabled: true
    occupationLocation:
      mode: custom
      value:
        -
          value:
            code: |-
              {{
                  schema_thing_refs
                  mode="linked_entries"
                  field="role_work_location|source_entry.work_location"
                  linked_entries_field="schema_object"
              }}
            mode: html
          _id: LJYQxDhLzdUq8YSena-Ic
          type: antlers
          enabled: true
    occupationalCategory:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ if title.occupation.category_code.isco_08 && ( title.occupation.category_code.isco_08 | is_iterable ) }}
                  [{{ title.occupation.category_code.isco_08 }}{{ unless first }},{{ /unless }}{{ schema_template_node id="1c6113fa-0aa1-48a6-b6ba-b72205426b8b" instance_index="{{ index }}" }}{{ /title.occupation.category_code.isco_08 }}]
              {{ /if }}
            mode: html
          _id: Q5occaSxEkKLhGUij7UWE
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{ if title.occupation.category_code.onet_soc && ( title.occupation.category_code.onet_soc | is_iterable ) }}
                  [{{ title.occupation.category_code.onet_soc }}{{ unless first }},{{ /unless }}{{ schema_template_node id="ddfd890c-cc51-4043-aef0-bff74e010bd9" instance_index="{{ index }}" }}{{ /title.occupation.category_code.onet_soc }}]
              {{ /if }}
            mode: html
          _id: Q5occaSxEkKLhGUij7UWD
          type: antlers
          enabled: true
    sameAs:
      mode: custom
      value:
        -
          value:
            code: '{{ title.occupation.same_as | to_json }}'
            mode: html
          _id: d08RyKdqBWsi2QzKtELhM
          type: antlers
          enabled: true
    additionalType:
      mode: disable
      value: {  }
    alternateName:
      mode: disable
      value: {  }
    description:
      mode: disable
      value: {  }
    disambiguatingDescription:
      mode: disable
      value: {  }
    educationRequirements:
      mode: disable
      value: {  }
    estimatedSalary:
      mode: disable
      value: {  }
    experienceRequirements:
      mode: disable
      value: {  }
    identifier:
      mode: disable
      value: {  }
    image:
      mode: disable
      value: {  }
    mainEntityOfPage:
      mode: disable
      value: {  }
    potentialAction:
      mode: disable
      value: {  }
    qualifications:
      mode: disable
      value: {  }
    responsibilities:
      mode: disable
      value: {  }
    skills:
      mode: disable
      value: {  }
    subjectOf:
      mode: disable
      value: {  }
    url:
      mode: disable
      value: {  }
updated_by: cd0a8902-c35f-474a-89f5-d360ea4ca6ae
updated_at: 1776946540
---
