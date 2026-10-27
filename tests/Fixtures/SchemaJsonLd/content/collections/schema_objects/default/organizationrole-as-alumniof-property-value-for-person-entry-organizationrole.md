---
id: 44e2bd94-bfb9-4a34-93a1-f5968997baa4
blueprint: schema_object
title: 'OrganizationRole as `alumniOf` Property Value for Person Entry (OrganizationRole)'
schema:
  type:
    - OrganizationRole
  properties:
    alumniOf:
      mode: custom
      value:
        -
          value:
            code: |-
              {{# alumniOf includes the sponsoring institution plus any participating sites. #}}
              {{ if organization || participating_sites }}
                  [{{ $alumni_need_comma = false }}
                  {{ organization }}
                      {{ if $alumni_need_comma }},{{ /if }}{{ $alumni_need_comma = true }}
                      {{ if schema_object }}
                          {{#
                              Entries field `schema_object` augments to a Value wrapper: conditionals
                              see it as set, but `:id="schema_object"` stringifies empty unless unwrapped.
                          #}}
                          {{ schema_template_node :id="schema_object | raw" }}
                      {{ else }}
                          {{
                              $alumni_of_properties = [
                                  'name' => name,
                                  'sameAs' => ( same_as | pluck('url') ),
                                  'alternateName' => ( alternate_name | pluck('text') ),
                                  'additional_type' => ( additional_type | pluck('text') ),
                                  'url' => official_website,
                              ]
                          }}
                          {{ $schema_type_segment = schema_type['value'] ?? schema_type }}
                          {{ $full_schema_type_url = 'https://schema.org/' + $schema_type_segment }}
                          {{
                            schema_object_tag 
                            :type="$full_schema_type_url"
                            :properties="$alumni_of_properties"
                          }}
                      {{ /if }}
                  {{ /organization }}
                  {{ participating_sites }}
                      {{ if $alumni_need_comma }},{{ /if }}{{ $alumni_need_comma = true }}
                      {{ if schema_object }}
                          {{ schema_template_node :id="schema_object | raw" }}
                      {{ else }}
                          {{
                              $alumni_of_properties = [
                                  'name' => name,
                                  'sameAs' => ( same_as | pluck('url') ),
                                  'alternateName' => ( alternate_name | pluck('text') ),
                                  'additional_type' => ( additional_type | pluck('text') ),
                                  'url' => official_website,
                              ]
                          }}
                          {{ $schema_type_segment = schema_type['value'] ?? schema_type }}
                          {{ $full_schema_type_url = 'https://schema.org/' + $schema_type_segment }}
                          {{
                            schema_object_tag 
                            :type="$full_schema_type_url"
                            :properties="$alumni_of_properties"
                          }}
                      {{ /if }}
                  {{ /participating_sites }}]
              {{ /if }}
            mode: html
          _id: Skjr15IW6Vg75qTRA2w6T
          type: antlers
          enabled: true
    startDate:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ activity_start_date | format_schema_date }}
            mode: html
          _id: y4sRV0i7DqDcrq1r_gETx
          type: antlers
          enabled: true
    endDate:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ activity_end_date | format_schema_date }}
            mode: html
          _id: wkeqrvzL5O1b1o-8breY3
          type: antlers
          enabled: true
    additionalType:
      mode: disable
      value: {  }
    affiliation:
      mode: disable
      value: {  }
    alternateName:
      mode: disable
      value: {  }
    attendee:
      mode: disable
      value: {  }
    author:
      mode: disable
      value: {  }
    contributor:
      mode: disable
      value: {  }
    creator:
      mode: disable
      value: {  }
    creditedTo:
      mode: disable
      value: {  }
    description:
      mode: disable
      value: {  }
    disambiguatingDescription:
      mode: disable
      value: {  }
    founder:
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
    member:
      mode: disable
      value: {  }
    memberOf:
      mode: disable
      value: {  }
    name:
      mode: disable
      value: {  }
    numberedPosition:
      mode: disable
      value: {  }
    organizer:
      mode: disable
      value: {  }
    owner:
      mode: disable
      value: {  }
    participant:
      mode: disable
      value: {  }
    performer:
      mode: disable
      value: {  }
    potentialAction:
      mode: disable
      value: {  }
    producer:
      mode: disable
      value: {  }
    publisher:
      mode: disable
      value: {  }
    roleName:
      mode: disable
      value: {  }
    sameAs:
      mode: disable
      value: {  }
    subjectOf:
      mode: disable
      value: {  }
    translator:
      mode: disable
      value: {  }
    url:
      mode: disable
      value: {  }
    worksFor:
      mode: disable
      value: {  }
updated_by: cd0a8902-c35f-474a-89f5-d360ea4ca6ae
updated_at: 1777128608
parent: ac5c2a99-d812-48ef-9da7-7994eacd0bd3
---
