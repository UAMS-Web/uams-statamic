---
id: 0f108925-12ef-40b9-b758-3fe4fb278be5
blueprint: schema_object
title: 'Residency/Fellowship Certificate of Completion (EducationalOccupationalCredential)'
schema:
  type:
    - EducationalOccupationalCredential
  properties:
    name:
      mode: custom
      value:
        -
          value:
            code: |-
              Certificate of Completion — {{ specialty }}{{ if index == 0 }}{{ title }}{{ /if }}{{ /specialty }}
            mode: html
          _id: zQ6jlkG-SJti-7Ip4eNFL
          type: antlers
          enabled: true
    credentialCategory:
      mode: custom
      value:
        -
          value:
            code: |-
              certificate
            mode: html
          _id: KtM5q0-YrOQusU5zwWVad
          type: antlers
          enabled: true
    educationalLevel:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ if type == 'medical_residency' }}Postgraduate residency training{{ else }}Postgraduate fellowship training{{ /if }}
            mode: html
          _id: 6FfMDT3IWAn4g9EuoZUMI
          type: antlers
          enabled: true
    dateCreated:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ activity_end_date | format_schema_date }}
            mode: html
          _id: psYgSxry81zeXVMotnlQc
          type: antlers
          enabled: true
    recognizedBy:
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
          _id: 7Vyy6Pz4dRWSBHXi25Jow
          type: antlers
          enabled: true
    about:
      mode: custom
      value:
        -
          value:
            code: |-
              {{# Resolve specialty-derived values here, where the specialty term is augmented, #}}
              {{# then pass them to the nested program node (specialty does not re-augment at depth). #}}
              {{ $rf_specialty_name = '' }}
              {{ $rf_accreditor_json = '' }}
              {{ specialty }}
                  {{ if index == 0 }}
                      {{ $rf_specialty_name = title }}
                      {{ $rf_accreditor_json = id | schema_training_accreditor_json }}
                  {{ /if }}
              {{ /specialty }}

              {{# Residency/Fellowship Program (EducationalOccupationalProgram) #}}
              {{
                  schema_template_node
                  id="9b152f24-11c6-4836-9125-cd8d945b9f3f"
                  :instance_index="schema_template_effective_instance_index"
              }}
            mode: html
          _id: zlbf7EPS0cfBu5m-4Cvql
          type: antlers
          enabled: true
updated_by: cd0a8902-c35f-474a-89f5-d360ea4ca6ae
updated_at: 1777902577
---
