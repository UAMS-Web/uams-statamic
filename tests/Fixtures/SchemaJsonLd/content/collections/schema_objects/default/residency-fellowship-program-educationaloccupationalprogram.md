---
id: 9b152f24-11c6-4836-9125-cd8d945b9f3f
blueprint: schema_object
title: 'Residency/Fellowship Program (EducationalOccupationalProgram)'
schema:
  type:
    - EducationalOccupationalProgram
  properties:
    name:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $rf_specialty_name }} {{ if type == 'medical_residency' }}Residency{{ else }}Fellowship{{ /if }}
            mode: html
          _id: tOg1PmX7jcMeeo50IhBGL
          type: antlers
          enabled: true
    programType:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ if type == 'medical_residency' }}Residency{{ else }}Fellowship{{ /if }}
            mode: html
          _id: q5OGbt6A5J0haAClJ9DqR
          type: antlers
          enabled: true
    provider:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ if organization }}
                  {{ organization }}
                      {{ if index == 0 }}
                          {{ if schema_object }}
                              {{ schema_template_node :id="schema_object | raw" }}
                          {{ else }}
                              {{
                                  $rf_org_properties = [
                                      'name' => name,
                                      'sameAs' => ( same_as | pluck('url') ),
                                      'alternateName' => ( alternate_name | pluck('text') ),
                                      'additionalType' => ( additional_type | pluck('text') ),
                                      'url' => official_website,
                                  ]
                              }}
                              {{ $rf_org_type = 'https://schema.org/' + ( schema_type['value'] ?? schema_type ) }}
                              {{ schema_object_tag :type="$rf_org_type" :properties="$rf_org_properties" }}
                          {{ /if }}
                      {{ /if }}
                  {{ /organization }}
              {{ /if }}
            mode: html
          _id: 6tLym5Tn3Isb54fJ_PdS-
          type: antlers
          enabled: true
    accreditedBy:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $rf_accreditor_json }}
            mode: html
          _id: VilHe0fqNCEOXkmwosoAt
          type: antlers
          enabled: true
updated_by: cd0a8902-c35f-474a-89f5-d360ea4ca6ae
updated_at: 1777902577
---
