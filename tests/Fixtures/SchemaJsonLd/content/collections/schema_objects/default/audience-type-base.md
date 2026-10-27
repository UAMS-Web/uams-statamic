---
id: 79eb0493-a940-496b-ace0-46f89ab0aa50
blueprint: schema_object
title: 'Audience Type Base (Audience; PeopleAudience; MedicalAudience; Patient; ParentAudience; Researcher; BusinessAudience; EducationalAudience)'
schema:
  type:
    - Audience
    - Patient
    - PeopleAudience
    - MedicalAudience
    - ParentAudience
    - Researcher
    - BusinessAudience
    - EducationalAudience
  properties:
    additionalType:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $additional_type = additional_type ?? additionaltype ?? null }}
              {{ if $additional_type && ( $additional_type | is_array ) }}
                  {{ $additional_type | pluck('uri') | to_json }}
              {{ elseif $additional_type }}
                  {{ $additional_type }}
              {{ /if }}
            mode: html
          _id: 60Fei_aRoFa7V1TXnzl00
          type: antlers
          enabled: true
    alternateName:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $alternate_name = alternate_name ?? alternatename ?? null }}
              {{ if $alternate_name && ( $alternate_name | is_array ) }}
                  {{ $alternate_name | pluck('text') | to_json }}
              {{ elseif $alternate_name }}
                  {{ $alternate_name }}
              {{ /if }}
            mode: html
          _id: 60Fei_aRoFa7V1TXnzl01
          type: antlers
          enabled: true
    audienceType:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ audience_type | strip_tags | sanitize }}
            mode: html
          _id: 60Fei_aRoFa7V1TXnzl02
          type: antlers
          enabled: true
    description:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ description | strip_tags | sanitize }}
            mode: html
          _id: 60Fei_aRoFa7V1TXnzl03
          type: antlers
          enabled: true
    disambiguatingDescription:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ disambiguating_description | strip_tags | sanitize }}
            mode: html
          _id: 60Fei_aRoFa7V1TXnzl04
          type: antlers
          enabled: true
    geographicArea:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ 
                  schema_thing_refs
                  mode="linked_entries"
                  field="geographic_area"
                  linked_entries_field="schema_object"
              }}
            mode: html
          _id: 60Fei_aRoFa7V1TXnzl05
          type: antlers
          enabled: true
    name:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ name | strip_tags | sanitize }}
            mode: html
          _id: 60Fei_aRoFa7V1TXnzl06
          type: antlers
          enabled: true
    sameAs:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $same_as = same_as ?? sameas ?? null }}
              {{ if $same_as && ( $same_as | is_array ) }}
                  {{ $same_as | pluck('url') | to_json }}
              {{ elseif $same_as }}
                  {{ $same_as }}
              {{ /if }}
            mode: html
          _id: 60Fei_aRoFa7V1TXnzl07
          type: antlers
          enabled: true
    diagnosis:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ if diagnosis && ( diagnosis | is_iterable ) }}
                  [{{ diagnosis }}
                      {{ if index > 0 }},{{ /if }}

                      {{# Conditions Collection — Collection Entry as Content (MedicalCondition) #}}
                      {{
                          schema_template_node
                          id="eb185cba-9b3d-4592-88bf-bfc39f60518a"
                          :instance_index="index"
                      }}
                  {{ /diagnosis }}]
              {{ /if }}
            mode: html
          _id: 60Fei_aRoFa7V1TXnzl09
          type: antlers
          enabled: true
    drug:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ if drug && ( drug | is_iterable ) }}
                  [{{ drug }}
                      {{ if index > 0 }},{{ /if }}

                      {{# Drugs Taxonomy — Taxonomy Term as Content (Drug) #}}
                      {{
                          schema_template_node
                          id="ebbce463-9196-4dac-9d0e-f0e5ab0f0827"
                          :instance_index="index"
                      }}
                  {{ /drug }}]
              {{ /if }}
            mode: html
          _id: 60Fei_aRoFa7V1TXnzl10
          type: antlers
          enabled: true
    healthCondition:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ if health_condition && ( health_condition | is_iterable ) }}
                  [{{ health_condition }}
                      {{ if index > 0 }},{{ /if }}

                      {{# Conditions Collection — Collection Entry as Content (MedicalCondition) #}}
                      {{
                          schema_template_node
                          id="eb185cba-9b3d-4592-88bf-bfc39f60518a"
                          :instance_index="index"
                      }}
                  {{ /health_condition }}]
              {{ /if }}
            mode: html
          _id: 60Fei_aRoFa7V1TXnzl11
          type: antlers
          enabled: true
    requiredGender:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ gender_required }}
            mode: html
          _id: 60Fei_aRoFa7V1TXnzl12
          type: antlers
          enabled: true
    requiredMaxAge:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $required_max_age = age_required.max.total_years ?? age_required.value.total_years }}
              {{ $required_max_age ? ( $required_max_age | floor ) : '' }}
            mode: html
          _id: 60Fei_aRoFa7V1TXnzl13
          type: antlers
          enabled: true
    requiredMinAge:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $required_min_age = age_required.min.total_years ?? age_required.value.total_years }}
              {{ $required_min_age ? ( $required_min_age | floor ) : '' }}
            mode: html
          _id: 60Fei_aRoFa7V1TXnzl14
          type: antlers
          enabled: true
    suggestedAge:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $suggested_age_value_years = age_required.value.total_years }}
              {{ $suggested_age_min_years = age_required.min.total_years }}
              {{ $suggested_age_max_years = age_required.max.total_years }}
              {{ $suggested_age_properties = [
                  'maxValue' => $suggested_age_max_years,
                  'minValue' => $suggested_age_min_years,
                  'unitCode' => 'ANN',
                  'unitText' => 'year',
                  'value' => $suggested_age_value_years,
              ] }}
              {{ 
                  schema_object type="https://schema.org/QuantitativeValue"
                  :properties="$suggested_age_properties"
              }}
            mode: html
          _id: 60Fei_aRoFa7V1TXnzl15
          type: antlers
          enabled: true
    suggestedGender:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ gender_suggested }}
            mode: html
          _id: 60Fei_aRoFa7V1TXnzl16
          type: antlers
          enabled: true
    suggestedMaxAge:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $suggested_max_age = age_suggested.max.total_years ?? age_suggested.value.total_years }}
              {{ $suggested_max_age ?? '' }}
            mode: html
          _id: 60Fei_aRoFa7V1TXnzl17
          type: antlers
          enabled: true
    suggestedMeasurement:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $measurement_suggested_properties = [
                  'maxValue' => max,
                  'minValue' => min,
                  'unitCode' => unit_code,
                  'unitText' => unit_text,
                  'value' => value,
              ] }}
              {{ 
                  schema_object type="https://schema.org/QuantitativeValue"
                  :properties="$measurement_suggested_properties"
              }}
            mode: html
          _id: 60Fei_aRoFa7V1TXnzl18
          type: antlers
          enabled: true
    suggestedMinAge:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $suggested_min_age = age_suggested.min.total_years ?? age_suggested.value.total_years }}
              {{ $suggested_min_age ?? '' }}
            mode: html
          _id: 60Fei_aRoFa7V1TXnzl19
          type: antlers
          enabled: true
    childMaxAge:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $child_max_age = child_age.max.total_years ?? child_age.value.total_years }}
              {{ $child_max_age ?? '' }}
            mode: html
          _id: 60Fei_aRoFa7V1TXnzl20
          type: antlers
          enabled: true
    childMinAge:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $child_min_age = child_age.min.total_years ?? child_age.value.total_years }}
              {{ $child_min_age ?? '' }}
            mode: html
          _id: 60Fei_aRoFa7V1TXnzl21
          type: antlers
          enabled: true
    numberOfEmployees:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $number_of_employees_properties = [
                  'maxValue' => max,
                  'minValue' => min,
                  'unitCode' => unit_code,
                  'unitText' => unit_text,
                  'value' => value,
              ] }}
              {{ 
                  schema_object type="https://schema.org/QuantitativeValue"
                  :properties="$number_of_employees_properties"
              }}
            mode: html
          _id: 60Fei_aRoFa7V1TXnzl22
          type: antlers
          enabled: true
    yearlyRevenue:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $yearly_revenue_properties = [
                  'maxValue' => max,
                  'minValue' => min,
                  'unitCode' => unit_code,
                  'unitText' => unit_text,
                  'value' => value,
              ] }}
              {{ 
                  schema_object type="https://schema.org/QuantitativeValue"
                  :properties="$yearly_revenue_properties"
              }}
            mode: html
          _id: 60Fei_aRoFa7V1TXnzl23
          type: antlers
          enabled: true
    yearsInOperation:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $years_in_operation_properties = [
                  'maxValue' => max,
                  'minValue' => min,
                  'unitCode' => unit_code,
                  'unitText' => unit_text,
                  'value' => value,
              ] }}
              {{ 
                  schema_object type="https://schema.org/QuantitativeValue"
                  :properties="$years_in_operation_properties"
              }}
            mode: html
          _id: 60Fei_aRoFa7V1TXnzl24
          type: antlers
          enabled: true
    educationalRole:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ educational_role | strip_tags | sanitize }}
            mode: html
          _id: 60Fei_aRoFa7V1TXnzl25
          type: antlers
          enabled: true
    additionalName:
      mode: disable
      value: {  }
    url:
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
    owner:
      mode: disable
      value: {  }
    potentialAction:
      mode: disable
      value: {  }
    subjectOf:
      mode: disable
      value: {  }
    address:
      mode: disable
      value: {  }
    affiliation:
      mode: disable
      value: {  }
    agentInteractionStatistic:
      mode: disable
      value: {  }
    alumniOf:
      mode: disable
      value: {  }
    award:
      mode: disable
      value: {  }
    birthDate:
      mode: disable
      value: {  }
    birthPlace:
      mode: disable
      value: {  }
    brand:
      mode: disable
      value: {  }
    callSign:
      mode: disable
      value: {  }
    children:
      mode: disable
      value: {  }
    colleague:
      mode: disable
      value: {  }
    contactPoint:
      mode: disable
      value: {  }
    deathDate:
      mode: disable
      value: {  }
    deathPlace:
      mode: disable
      value: {  }
    email:
      mode: disable
      value: {  }
    familyName:
      mode: disable
      value: {  }
    faxNumber:
      mode: disable
      value: {  }
    follows:
      mode: disable
      value: {  }
    funder:
      mode: disable
      value: {  }
    funding:
      mode: disable
      value: {  }
    gender:
      mode: disable
      value: {  }
    givenName:
      mode: disable
      value: {  }
    hasCertification:
      mode: disable
      value: {  }
    hasCredential:
      mode: disable
      value: {  }
    hasOccupation:
      mode: disable
      value: {  }
    hasOfferCatalog:
      mode: disable
      value: {  }
    hasPOS:
      mode: disable
      value: {  }
    height:
      mode: disable
      value: {  }
    homeLocation:
      mode: disable
      value: {  }
    honorificPrefix:
      mode: disable
      value: {  }
    honorificSuffix:
      mode: disable
      value: {  }
    interactionStatistic:
      mode: disable
      value: {  }
    jobTitle:
      mode: disable
      value: {  }
    knows:
      mode: disable
      value: {  }
    knowsAbout:
      mode: disable
      value: {  }
    knowsLanguage:
      mode: disable
      value: {  }
    lifeEvent:
      mode: disable
      value: {  }
    makesOffer:
      mode: disable
      value: {  }
    memberOf:
      mode: disable
      value: {  }
    nationality:
      mode: disable
      value: {  }
    netWorth:
      mode: disable
      value: {  }
    owns:
      mode: disable
      value: {  }
    parent:
      mode: disable
      value: {  }
    performerIn:
      mode: disable
      value: {  }
    pronouns:
      mode: disable
      value: {  }
    publishingPrinciples:
      mode: disable
      value: {  }
    relatedTo:
      mode: disable
      value: {  }
    seeks:
      mode: disable
      value: {  }
    sibling:
      mode: disable
      value: {  }
    skills:
      mode: disable
      value: {  }
    sponsor:
      mode: disable
      value: {  }
    spouse:
      mode: disable
      value: {  }
    telephone:
      mode: disable
      value: {  }
    weight:
      mode: disable
      value: {  }
    workLocation:
      mode: disable
      value: {  }
    worksFor:
      mode: disable
      value: {  }
updated_by: cd0a8902-c35f-474a-89f5-d360ea4ca6ae
updated_at: 1777577346
---
