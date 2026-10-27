---
id: 0e009e39-c145-4f4e-8e6c-445d9f81dd22
blueprint: schema_object
title: 'ISCED-A term code from CTDL Credential Class (DefinedTerm)'
schema:
  type:
    - DefinedTerm
  properties:
    alternateName:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $ctdl_value = activity ?? null }}
              {{ $ctdl_value = ( ( $ctdl_value == 'SecondarySchoolDiploma' ) || ( $ctdl_value == 'GeneralEducationDevelopment' ) ) ? $ctdl_value : ctdl.value }}
              {{ $isced_a_2011 = { get_config key="{ 'crosswalk.mappings.CredentialRegistry.' + $ctdl_value + '.IscedA2011' }" } }}
              {{ if $isced_a_2011 }}
                  ISCED-A 2011 level {{ $isced_a_2011 }}
              {{ /if }}
            mode: html
          _id: Kv6XF1WPn93RbGGkdYkYy
          type: antlers
          enabled: true
    inDefinedTermSet:
      mode: inherit
      value: {  }
    name:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $ctdl_value = activity ?? null }}
              {{ $ctdl_value = ( ( $ctdl_value == 'SecondarySchoolDiploma' ) || ( $ctdl_value == 'GeneralEducationDevelopment' ) ) ? $ctdl_value : ctdl.value }}
              {{ $isced_a_2011 = { get_config key="{ 'crosswalk.mappings.CredentialRegistry.' + $ctdl_value + '.IscedA2011' }" } }}
              {{ if $isced_a_2011 }}
                  {{ enum_case enum="App\Enums\IscedA2011" :case="$isced_a_2011" method="labelsWithAncestors" arg_separator=", " }}
              {{ /if }}
            mode: html
          _id: '-kZQ2c40m2H7_wOyALqAy'
          type: antlers
          enabled: true
    termCode:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $ctdl_value = activity ?? null }}
              {{ $ctdl_value = ( ( $ctdl_value == 'SecondarySchoolDiploma' ) || ( $ctdl_value == 'GeneralEducationDevelopment' ) ) ? $ctdl_value : ctdl.value }}
              {{ get_config key="{ 'crosswalk.mappings.CredentialRegistry.' + $ctdl_value + '.IscedA2011' }" }}
            mode: html
          _id: coXxODfDLrsXVPSfCKgJD
          type: antlers
          enabled: true
    about:
      mode: disable
      value: {  }
    additionalType:
      mode: disable
      value: {  }
    description:
      mode: disable
      value: {  }
    disambiguatingDescription:
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
    sameAs:
      mode: disable
      value: {  }
    subjectOf:
      mode: disable
      value: {  }
    url:
      mode: disable
      value: {  }
updated_by: cd0a8902-c35f-474a-89f5-d360ea4ca6ae
updated_at: 1777669607
---
