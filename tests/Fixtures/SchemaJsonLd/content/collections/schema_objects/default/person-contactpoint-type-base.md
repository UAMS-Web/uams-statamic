---
id: 1a834c0c-f4af-4223-be3b-ee45b218cd39
blueprint: schema_object
title: 'Person / ContactPoint Type Base'
schema:
  type:
    - Person
    - ContactPoint
  properties:
    additionalName:
      mode: custom
      value:
        -
          value:
            code: |-
              {{
                  schema_pronounceable_text
                  :in_language="source_site:lang ?? source_site:locale ?? site:lang ?? site:locale"
                  :pairs="legal_name_pairs.middle_name"
                  speech_to_text_markup="IPA"
              }}
            mode: html
          _id: 4Vw7b0oQKoZwCwXNlX5OK
          type: antlers
          enabled: true
    additionalType:
      mode: inherit
      value: {  }
    address:
      mode: inherit
      value: {  }
    affiliation:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ if uams_worker_query }}
                  {{ schema_template_node id="58185f50-22a4-48e7-9575-b86a1fdffef9" }}
              {{ /if }}
            mode: html
          _id: e_C6d3-79jjfvfdxQOF5T
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{ if member_of && ( member_of | is_iterable ) }}
                  [{{ $member_need_comma = false }}{{ member_of }}
                      {{# A row whose organization was deleted emits nothing, so the comma cannot key on `index`. #}}
                      {{ if organization }}
                      {{ if $member_need_comma }},{{ /if }}{{ $member_need_comma = true }}

                      {{#
                          Get `schema_object` field value if it exists.
                          Otherwise, fall back to "Membership Organization Base (Organization)" `schema_objects` collection entry.
                      #}}
                      {{# Every level is an affiliation with the organization itself, so no Role here. #}}
                      {{ organization }}
                          {{ $member_org_schema = schema_object ? (schema_object | raw) : 'b755acff-0495-4ede-8701-966efa5f68a0' }}
                          {{
                              schema_template_node
                              :id="$member_org_schema"
                              :instance_index="index"
                          }}
                      {{ /organization }}
                      {{ /if }}
                  {{ /member_of }}]
              {{ /if }}
            mode: html
          _id: s6ECvPW2eBrMH4OsukAnq
          type: antlers
          enabled: true
    agentInteractionStatistic:
      mode: inherit
      value: {  }
    alternateName:
      mode: custom
      value:
        -
          value:
            code: |-
              {{
                  schema_pronounceable_text
                  :in_language="source_site:lang ?? source_site:locale ?? site:lang ?? site:locale"
                  :pairs="alternate_name_list"
                  speech_to_text_markup="IPA"
              }}
            mode: html
          _id: xuD-95G8zjA8R8bvgenbG
          type: antlers
          enabled: true
    alumniOf:
      mode: custom
      value:
        -
          value:
            code: |-
              {{
                  if
                      education
                      && ( education | is_iterable )
              }}
                  [{{ education }}
                      {{ if index > 0 }},{{ /if }}

                      {{ if activity.ctdl.ceterms_type == 'Credential' }}
                          {{ if type == 'secondary' }}
                              {{ if activity == 'SecondarySchoolDiploma' }}
                                  {{# high school student (OrganizationRole) #}}
                                  {{
                                      schema_template_node
                                      id="68d328fb-64cb-4e6b-9646-22659c71f3ba"
                                      :instance_index="index"
                                  }}
                              {{ /if }}
                          {{ elseif activity.ctdl.ceterms_credential_type == 'Degree' }}
                              {{ if activity.ctdl.ceterms_degree_type == 'AssociateDegree' }}
                                  {{# undergraduate student (OrganizationRole) #}}
                                  {{
                                      schema_template_node
                                      id="ddc27374-7281-487d-92a5-a1baa0777605"
                                      :instance_index="index"
                                  }}
                              {{ elseif activity.ctdl.ceterms_degree_type == 'BachelorDegree' }}
                                  {{# undergraduate student (OrganizationRole) #}}
                                  {{
                                      schema_template_node
                                      id="ddc27374-7281-487d-92a5-a1baa0777605"
                                      :instance_index="index"
                                  }}
                              {{
                                  elseif
                                      activity.ctdl.ceterms_degree_type == 'MasterDegree'
                                      || activity.ctdl.ceterms_degree_type == 'SpecialistDegree'
                              }}
                                  {{# graduate student (OrganizationRole) #}}
                                  {{
                                      schema_template_node
                                      id="3bff76d6-1ff1-42d1-8914-d2397394c9ee"
                                      :instance_index="index"
                                  }}
                              {{ elseif activity.ctdl.ceterms_degree_type == 'DoctoralDegree' }}
                                  {{
                                      if activity.full_name == 'Doctor of Medicine'
                                      || activity.full_name == 'Doctor of Osteopathic Medicine'
                                  }}
                                      {{# medical student (OrganizationRole) #}}
                                      {{
                                          schema_template_node
                                          id="1c5e8e18-e977-4b65-903d-3882e5ee452e"
                                          :instance_index="index"
                                      }}
                                  {{
                                      elseif activity.full_name == 'Doctor of Pharmacy'
                                  }}
                                      {{# pharmacy student (OrganizationRole) #}}
                                      {{
                                          schema_template_node
                                          id="1c5e8e18-e977-4b65-903d-3882e5ee452g"
                                          :instance_index="index"
                                      }}
                                  {{
                                      elseif activity.full_name == 'Juris Doctor'
                                  }}
                                      {{# law student (OrganizationRole) #}}
                                      {{
                                          schema_template_node
                                          id="1c5e8e18-e977-4b65-903d-3882e5ee452f"
                                          :instance_index="index"
                                      }}
                                  {{
                                      elseif activity.full_name == 'Juris Doctor'
                                  }}
                                      {{# doctoral student — Doctor of Philosophy (OrganizationRole) #}}
                                      {{
                                          schema_template_node
                                          id="1c5e8e18-e977-4b65-903d-3882e5ee452f"
                                          :instance_index="index"
                                      }}
                                  {{ else }}
                                      {{# doctoral student — general (OrganizationRole) #}}
                                      {{
                                          schema_template_node
                                          id="1c5e8e18-e977-4b65-903d-3882e5ee452h"
                                          :instance_index="index"
                                      }}
                                  {{ /if }}
                              {{ /if }}

                          {{ elseif activity.ctdl.ceterms_credential_type == 'Degree' }}
                              {{ if activity.ctdl.ceterms_degree_type == 'AssociateDegree' }}
                                  {{# undergraduate student (OrganizationRole) #}}
                                  {{
                                      schema_template_node
                                      id="ddc27374-7281-487d-92a5-a1baa0777605"
                                      :instance_index="index"
                                  }}
                              {{ elseif activity.ctdl.ceterms_degree_type == 'BachelorDegree' }}
                                  {{# undergraduate student (OrganizationRole) #}}
                                  {{
                                      schema_template_node
                                      id="ddc27374-7281-487d-92a5-a1baa0777605"
                                      :instance_index="index"
                                  }}
                              {{
                                  elseif activity.ctdl.ceterms_degree_type == 'MasterDegree'
                                  || activity.ctdl.ceterms_degree_type == 'SpecialistDegree'
                              }}
                                  {{# graduate student (OrganizationRole) #}}
                                  {{
                                      schema_template_node
                                      id="3bff76d6-1ff1-42d1-8914-d2397394c9ee"
                                      :instance_index="index"
                                  }}
                              {{ elseif activity.ctdl.ceterms_degree_type == 'DoctoralDegree' }}
                                  {{
                                      if activity.full_name == 'Doctor of Medicine'
                                      || activity.full_name == 'Doctor of Osteopathic Medicine'
                                  }}
                                      {{# medical student (OrganizationRole) #}}
                                      {{
                                          schema_template_node
                                          id="1c5e8e18-e977-4b65-903d-3882e5ee452e"
                                          :instance_index="index"
                                      }}
                                  {{
                                      elseif activity.full_name == 'Doctor of Pharmacy'
                                  }}
                                      {{# pharmacy student (OrganizationRole) #}}
                                      {{
                                          schema_template_node
                                          id="1c5e8e18-e977-4b65-903d-3882e5ee452g"
                                          :instance_index="index"
                                      }}
                                  {{
                                      elseif activity.full_name == 'Juris Doctor'
                                  }}
                                      {{# law student (OrganizationRole) #}}
                                      {{
                                          schema_template_node
                                          id="1c5e8e18-e977-4b65-903d-3882e5ee452f"
                                          :instance_index="index"
                                      }}
                                  {{
                                      elseif activity.full_name == 'Juris Doctor'
                                  }}
                                      {{# doctoral student — Doctor of Philosophy (OrganizationRole) #}}
                                      {{
                                          schema_template_node
                                          id="1c5e8e18-e977-4b65-903d-3882e5ee452f"
                                          :instance_index="index"
                                      }}
                                  {{ else }}
                                      {{# doctoral student — general (OrganizationRole) #}}
                                      {{
                                          schema_template_node
                                          id="1c5e8e18-e977-4b65-903d-3882e5ee452h"
                                          :instance_index="index"
                                      }}
                                  {{ /if }}
                              {{ /if }}
                          {{ /if }}
                      {{ /if }}
                  {{ /education }}]
              {{ /if }}
            mode: html
          _id: Skjr15IW6Vg75qTRA2w6U
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{ if training && ( training | is_iterable ) }}
                  [{{ training }}
                      {{ if index > 0 }},{{ /if }}
                      {{ if type == 'medical_fellowship' }}
                          {{# medical fellow (OrganizationRole) #}}
                          {{
                              schema_template_node
                              id="1dc22f00-d8f0-4490-9dc9-951771572b4b"
                              :instance_index="index"
                          }}
                      {{ elseif type == 'medical_residency' }}
                          {{# medical fellow (OrganizationRole) #}}
                          {{
                              schema_template_node
                              id="8430fc31-278d-4eae-bb38-6910ec497a38"
                              :instance_index="index"
                          }}
                      {{ elseif type == 'medical_internship' }}
                          {{# medical intern (OrganizationRole) #}}
                          {{
                              schema_template_node
                              id="ca52f0f9-3862-4b90-97a2-46f9d1b6e6c3"
                              :instance_index="index"
                          }}
                      {{
                          elseif type == 'medical_postdoctoral_fellowship'
                          || type == 'postdoctoral_fellowship'
                      }}
                          {{# postdoctoral research fellow (OrganizationRole) #}}
                          {{
                              schema_template_node
                              id="0302e824-43b5-4959-870d-3c1c62f58f14"
                              :instance_index="index"
                          }}
                      {{ elseif type == 'internship' }}
                          {{# non-medical intern (OrganizationRole) #}}
                          {{
                              schema_template_node
                              id="d7a175ca-a8ab-471f-a459-e572fc42f4ff"
                              :instance_index="index"
                          }}
                      {{
                          elseif type == 'medical_externship'
                          || type == 'externship'
                      }}
                          {{# extern (OrganizationRole) #}}
                          {{
                              schema_template_node
                              id="41b3f56f-edb1-4786-9bc8-314e8792e762"
                              :instance_index="index"
                          }}
                      {{
                          elseif type == 'medical_apprenticeship'
                          || type == 'apprenticeship'
                      }}
                          {{# apprentice (OrganizationRole) #}}
                          {{
                              schema_template_node
                              id="3f2e3545-6f8f-420b-b351-8780285a18c5"
                              :instance_index="index"
                          }}
                      {{ else }}
                          {{# trainee (OrganizationRole) #}}
                          {{
                              schema_template_node
                              id="450dc11a-505f-4d61-9709-2d3195657007"
                              :instance_index="index"
                          }}
                      {{ /if }}
                  {{ /training }}]
              {{ /if }}
            mode: html
          _id: Skjr15IW6Vg75qTRA2w6V
          type: antlers
          enabled: true
    award:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ if recognition_lists && ( recognition_lists | is_iterable ) }}
                  {{# Build a list of strings so output is valid JSON for decode (see jobTitle `to_json`) #}}
                  {{ $award_strings = [] }}

                  {{# Loop through the replicator rows #}}
                  {{ recognition_lists }}
                      {{# Get `award_name` computed value from the `recognition_lists` taxonomy term #}}
                      {{ $award_name = recognition_list.award_name }}

                      {{# Get the `category` field value from the replicator row #}}
                      {{ $award_category = category | strip_tags | trim }}

                      {{# Join the two values with an em dash #}}
                      {{ $award_full_name = $award_name + ( $award_category ? ( ' — ' + $award_category ) : '' ) }}

                      {{# Append sanitized award label when present #}}
                      {{ if $award_full_name }}
                          {{# Sanitize the award label #}}
                          {{ $award_label = $award_full_name | sanitize }}

                          {{# Append the award label to the array #}}
                          {{ $award_strings = $award_strings | merge_array([$award_label]) }}
                      {{ /if }}
                  {{ /recognition_lists }}

                  {{# Emit the award list #}}
                  {{ $award_strings | to_json }}
              {{ /if }}
            mode: html
          _id: UxCHBV1KywaL2b8vLx4lX
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{ if awards && ( awards | is_iterable ) }}
                  {{# Build a list of strings so output is valid JSON for decode (see jobTitle `to_json`) #}}
                  {{ $award_strings = [] }}

                  {{# Loop through the replicator rows #}}
                  {{ awards }}
                      {{# Get award name #}}
                      {{ $award_name = title }}

                      {{# Get the award date #}}
                      {{ $award_date = award_date }}

                      {{# Join the two values with an em dash #}}
                      {{ $award_full_name = $award_name + ( $award_date ? ( ' (' + ( $award_date | ap_style_date ) ) + ')' : '' ) }}

                      {{# Append sanitized award label when present #}}
                      {{ if $award_full_name }}
                          {{# Sanitize the award label #}}
                          {{ $award_label = $award_full_name | sanitize }}

                          {{# Append the award label to the array #}}
                          {{ $award_strings = $award_strings | merge_array([$award_label]) }}
                      {{ /if }}
                  {{ /awards }}

                  {{# Emit the award list #}}
                  {{ $award_strings | to_json }}
              {{ /if }}
            mode: html
          _id: UxCHBV1KywaL2b8vLx5lX
          type: antlers
          enabled: true
    birthDate:
      mode: inherit
      value: {  }
    birthPlace:
      mode: inherit
      value: {  }
    brand:
      mode: inherit
      value: {  }
    callSign:
      mode: inherit
      value: {  }
    children:
      mode: inherit
      value: {  }
    colleague:
      mode: inherit
      value: {  }
    contactPoint:
      mode: inherit
      value: {  }
    deathDate:
      mode: inherit
      value: {  }
    deathPlace:
      mode: inherit
      value: {  }
    description:
      mode: inherit_custom
      value:
        -
          value:
            code: '{{ if bio_short }}{{ bio_short | raw | bard_text | sanitize }}{{ /if }}'
            mode: html
          _id: UxCHBV1KywaL2b8vLx3lX
          type: antlers
          enabled: true
    disambiguatingDescription:
      mode: inherit
      value: {  }
    email:
      mode: custom
      value:
        -
          value:
            code: '{{ if email }}mailto:{{ email }}{{ /if }}'
            mode: html
          _id: EON-q2QZ0TujJNdnyUl8G
          type: antlers
          enabled: true
    familyName:
      mode: custom
      value:
        -
          value:
            code: |-
              {{
                  schema_pronounceable_text
                  :in_language="source_site:lang ?? source_site:locale ?? site:lang ?? site:locale"
                  :pairs="legal_name_pairs.last_name"
                  speech_to_text_markup="IPA"
              }}
            mode: html
          _id: Zp30-kLxMp3FW_csFr2UJ
          type: antlers
          enabled: true
    faxNumber:
      mode: custom
      value:
        -
          value:
            code: '{{ fax }}'
            mode: html
          _id: l30jDvXjvPek6GJfeCOxC
          type: antlers
          enabled: true
    follows:
      mode: inherit
      value: {  }
    funder:
      mode: inherit
      value: {  }
    funding:
      mode: inherit
      value: {  }
    gender:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ if gender }}
                  {{ $gender_case_normalized = ( gender | lower | ucfirst ) }}
                  {{# Check the `$gender_case_normalized` value against https://schema.org/GenderType enumeration members #}}
                  {{ if ! ( ('Female', 'Male') | in_array($gender_case_normalized) ) }}
                      {{ 'https://schema.org/' + $gender_case_normalized }}
                  {{ else }}
                      {{ gender }}
                  {{ /if }}
              {{ /if }}
            mode: html
          _id: QcNxiJ0StxjORuQOixkDp
          type: antlers
          enabled: true
    givenName:
      mode: custom
      value:
        -
          value:
            code: |-
              {{
                  schema_pronounceable_text
                  :in_language="source_site:lang ?? source_site:locale ?? site:lang ?? site:locale"
                  :pairs="legal_name_pairs.first_name"
                  speech_to_text_markup="IPA"
              }}
            mode: html
          _id: UgBWcKTa-SkUsg5QUx652
          type: antlers
          enabled: true
    hasCertification:
      mode: custom
      value:
        -
          value:
            code: |-
              {{# Initialize slug list used to skip duplicate certification emits #}}
              {{ $certifications_list = [] }}
              {{# Walk each person certifications replicator row #}}
              {{ certifications }}
                  {{# Resolve the selected certification taxonomy term for this row #}}
                  {{ activity }}
                      {{# Record this row's term slug so credentials can dedupe against it #}}
                      {{ $certifications_list = $certifications_list | merge_array([slug]) }}
                  {{ /activity }}
              {{ /certifications }}
              {{# Begin JSON array; track whether a prior fragment needs a comma #}}
              {{
                  if
                      ( certifications && ( certifications | is_iterable ) )
                      || ( credentials && ( credentials | is_iterable ) )
              }}
                  [{{ $need_comma = false }}
                  {{# Emit certification schema for every certifications replicator row #}}
                  {{ certifications }}
                      {{# Add comma between array elements after the first fragment #}}
                      {{ if $need_comma }},{{ /if }}{{ $need_comma = true }}
                      {{# Certification (Certification; EducationalOccupationalCredential) #}}
                      {{
                          schema_template_node
                          id="c3d0e5b2-8f9e-4c5d-ab1e-2f3a4b5c6d7e"
                          :instance_index="index"
                      }}
                  {{ /certifications }}
                  {{# Add certification nodes from credentials not already in certifications #}}
                  {{ credentials }}
                      {{# Read CTDL type label for this credentials row #}}
                      {{ $ctdl_value = ctdl.value ?? null }}
                      {{# Restrict to rows classified as Certification #}}
                      {{ if $ctdl_value == 'Certification' }}
                          {{# Scope the linked certifications taxonomy term (if any) #}}
                          {{ certifications_term }}
                              {{# Emit only when this term was not covered by certifications rows #}}
                              {{ if ! ( $certifications_list | in_array(slug) ) }}
                                  {{# Comma before this fragment when the array already has content #}}
                                  {{ if $need_comma }},{{ /if }}{{ $need_comma = true }}
                                  {{# Certification (Certification; EducationalOccupationalCredential) #}}
                                  {{
                                      schema_template_node
                                      id="c3d0e5b2-8f9e-4c5d-ab1e-2f3a4b5c6d7e"
                                      :instance_index="index"
                                  }}
                              {{ /if }}
                          {{ /certifications_term }}
                      {{ /if }}
                  {{ /credentials }}]
              {{ /if }}
            mode: html
          _id: nW4YIWG7sN_vFCIdz6f3g
          type: antlers
          enabled: true
    hasCredential:
      mode: custom
      value:
        -
          value:
            code: |-
              {{# Initialize slug lists for deduplicating credential emits #}}
              {{ $certification_slugs = [] }}
              {{ $degree_slugs = [] }}

              {{# Record certifications taxonomy slugs from person certifications rows #}}
              {{ certifications }}
                  {{ activity }}
                      {{ $certification_slugs = $certification_slugs | merge_array([slug]) }}
                  {{ /activity }}
              {{ /certifications }}

              {{# Record credentials taxonomy slugs from education rows (degree terms on activity) #}}
              {{ education }}
                  {{ activity }}
                      {{ $degree_slugs = $degree_slugs | merge_array([slug]) }}
                  {{ /activity }}
              {{ /education }}

              {{# Begin JSON array; track comma between fragments #}}
              {{
                  if
                      ( certifications && ( certifications | is_iterable ) )
                      || ( education && ( education | is_iterable ) )
                      || ( credentials && ( credentials | is_iterable ) )
                      || ( training && ( training | is_iterable ) )
              }}
                  [{{ $need_comma = false }}

                  {{# Emit certification combo for each certifications replicator row #}}
                  {{ certifications }}
                      {{# Open the next certifications replicator row for this emit pass #}}
                      {{ if $need_comma }},{{ /if }}{{ $need_comma = true }}

                      {{# Certification (Certification; EducationalOccupationalCredential) #}}
                      {{
                          schema_template_node
                          id="c3d0e5b2-8f9e-4c5d-ab1e-2f3a4b5c6d7e"
                          :instance_index="index"
                      }}
                  {{ /certifications }}

                  {{# Emit degree EducationalOccupationalCredential for each education row with activity #}}
                  {{ education }}
                      {{# Capture education replicator index for nested template instance_index #}}
                      {{ $education_row_index = index }}

                      {{ if type == 'post_secondary' }}
                          {{# Scope the credentials-taxonomy term chosen as this education row activity #}}
                          {{ activity }}
                              {{# Add comma between array elements after the first fragment #}}
                              {{ if $need_comma }},{{ /if }}{{ $need_comma = true }}

                              {{# Academic Degree (EducationalOccupationalCredential) #}}
                              {{
                                  schema_template_node
                                  id="c3d0e5b2-8f9e-4c5d-ab1e-2f3a4b5c6d7f"
                                  :instance_index="$education_row_index"
                              }}
                          {{ /activity }}

                      {{ elseif type == 'secondary' }}
                          {{# Add comma between array elements after the first fragment #}}
                          {{ if $need_comma }},{{ /if }}{{ $need_comma = true }}
                          {{# Diploma (EducationalOccupationalCredential) #}}
                          {{
                              schema_template_node
                              id="f551d336-5322-4b21-b1ed-013d02c9fffe"
                              :instance_index="$education_row_index"
                          }}
                      {{ /if }}
                  {{ /education }}

                  {{# Emit a Certificate of Completion for each completed residency/fellowship training row #}}
                  {{ training }}
                      {{#
                          A training set produces a credential iff it is a residency or fellowship,
                          has a resolvable sponsoring institution, and has a completion date on or
                          before today (in-progress and future training produce no credential).
                      #}}
                      {{
                          if
                              (
                                  type == 'medical_residency'
                                  || type == 'medical_fellowship'
                                  || type == 'medical_postdoctoral_fellowship'
                                  || type == 'postdoctoral_fellowship'
                              )
                              && organization
                              && ( activity_end_date | partial_date_completed )
                      }}
                          {{# Add comma between array elements after the first fragment #}}
                          {{ if $need_comma }},{{ /if }}{{ $need_comma = true }}

                          {{# Residency/Fellowship Certificate of Completion (EducationalOccupationalCredential) #}}
                          {{
                              schema_template_node
                              id="0f108925-12ef-40b9-b758-3fe4fb278be5"
                              :instance_index="index"
                          }}
                      {{ /if }}
                  {{ /training }}

                  {{# Append credential emits from the person credentials term list (CTDL Credential root only) #}}
                  {{ credentials }}
                      {{# Capture person credentials list index for nested template instance_index #}}
                      {{ $credential_row_index = index }}

                      {{# Read CTDL root class (ceterms_type) from this credentials taxonomy term #}}
                      {{ $ctdl_root = ctdl.ceterms_type ?? null }}

                      {{# Process only terms classified under CTDL Credential #}}
                      {{ if $ctdl_root == 'Credential' }}
                          {{# Read CTDL credential subtype (ceterms_credential_type) for branching #}}
                          {{ $credential_subtype = ctdl.ceterms_credential_type ?? null }}

                          {{# Branch for certification-type credentials (linked certifications taxonomy) #}}
                          {{ if $credential_subtype == 'Certification' }}
                              {{# Scope linked certification taxonomy terms on this credential term #}}
                              {{ certifications_term }}
                                  {{# Dedupe only against certifications replicator activity slugs #}}
                                  {{ if ! ( $certification_slugs | in_array(slug) ) }}
                                      {{# Add comma between array elements after the first fragment #}}
                                      {{ if $need_comma }},{{ /if }}{{ $need_comma = true }}

                                      {{# Same certification combo template as certifications replicator rows #}}
                                      {{
                                          schema_template_node
                                          id="c3d0e5b2-8f9e-4c5d-ab1e-2f3a4b5c6d7e"
                                          :instance_index="$credential_row_index"
                                      }}
                                  {{ /if }}
                              {{ /certifications_term }}

                          {{# Branch for degree-type credentials (dedupe against education activity slugs) #}}
                          {{ elseif $credential_subtype == 'Degree' }}
                              {{# Dedupe only against education replicator activity slugs (credentials taxonomy) #}}
                              {{ if ! ( $degree_slugs | in_array(slug) ) }}
                                  {{# Add comma between array elements after the first fragment #}}
                                  {{ if $need_comma }},{{ /if }}{{ $need_comma = true }}

                                  {{# Credentials Taxonomy Term — Credential Type (EducationalOccupationalCredential) #}}
                                  {{
                                      schema_template_node
                                      id="b2c9f4a1-7e8d-4b3c-9a6f-1d0e5f7a9b2c"
                                      :instance_index="$credential_row_index"
                                  }}
                              {{ /if }}

                          {{ else }}
                              {{# Branch for other Credential subtypes (license, badge, certificate, etc.) #}}
                              {{ if $need_comma }},{{ /if }}{{ $need_comma = true }}

                              {{# Other credential kinds (license, badge, etc.) #}}
                              {{
                                  schema_template_node
                                  id="b2c9f4a1-7e8d-4b3c-9a6f-1d0e5f7a9b2c"
                                  :instance_index="$credential_row_index"
                              }}
                          {{ /if }}
                      {{ /if }}
                  {{ /credentials }}]
              {{ /if }}
            mode: html
          _id: VznEbHiNFNVsptKeswyfT
          type: antlers
          enabled: true
    hasOccupation:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ if job_title && ( job_title | is_iterable ) }}
                  [{{ job_title }}
                      {{ if index > 0 }},{{ /if }}
                      {{
                          schema_template_node
                          id="d4f58c4f-ee5c-4536-af1c-6e3dc3f2da4f"
                          :instance_index="index"
                      }}
                  {{ /job_title }}]
              {{ /if }}
            mode: html
          _id: f_IglA0Ib5XUQTiC2Hcjd
          type: antlers
          enabled: true
    hasOfferCatalog:
      mode: inherit
      value: {  }
    hasPOS:
      mode: inherit
      value: {  }
    height:
      mode: inherit
      value: {  }
    homeLocation:
      mode: inherit
      value: {  }
    honorificPrefix:
      mode: custom
      value:
        -
          value:
            code: |-
              {{
                  schema_pronounceable_text
                  :in_language="source_site:lang ?? source_site:locale ?? site:lang ?? site:locale"
                  :pairs="prefix"
                  speech_to_text_markup="IPA"
              }}
            mode: html
          _id: 6QM-xsKY_meIyEG5u21MY
          type: antlers
          enabled: true
    honorificSuffix:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ credentials }}
                  {{ unless no_results }}
                      {{
                          schema_pronounceable_text
                          :in_language="source_site:lang ?? source_site:locale ?? site:lang ?? site:locale"
                          :pairs="abbreviation"
                          speech_to_text_markup="IPA"
                      }}
                  {{ /unless }}
              {{ /credentials }}
            mode: html
          _id: AgxDuFqXMgyZfK0PrYOkh
          type: antlers
          enabled: true
    identifier:
      mode: inherit
      value: {  }
    image:
      mode: inherit
      value: {  }
    interactionStatistic:
      mode: inherit
      value: {  }
    jobTitle:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ if job_title && ( job_title | is_iterable ) }}
                {{ entry:job_title | pluck('title.value') | to_json }}
              {{ elseif job_title }}
                {{ job_title | strip_tags | trim | sanitize }}
              {{ /if }}
            mode: html
          _id: C4BGnAVB2vRa4vuo29MKM
          type: antlers
          enabled: true
    knows:
      mode: inherit
      value: {  }
    knowsAbout:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ knows_about_strings | to_json }}
            mode: html
          _id: C4BGnAVB2vRa4vuo30MKM
          type: antlers
          enabled: true
        -
          value:
            code: |-
              [{{ knows_about_terms }}
                  {{ if index > 0 }},{{ /if }}

                  {{ if taxonomy == 'fields_of_study' }}
                      {{# Academic Fields of Study Term (DefinedTerm) #}}
                      {{ schema_template_node id="684d7210-aca0-44ba-b6ef-dcd0803c7e2a" :instance_index="index" }}

                  {{ elseif taxonomy == 'specialties_aoa' }}
                      {{# AOA Medical Specialty Term (DefinedTerm) #}}
                      {{ schema_template_node id="826c23bb-ac5e-4d93-9bc8-f8ea586f6020" :instance_index="index" }}

                  {{ elseif taxonomy == 'specialties_acgme' }}
                      {{# ACGME Medical Specialty Term (DefinedTerm) #}}
                      {{ schema_template_node id="c51819d6-a933-4b7e-b0c4-2f73a4070ddf" :instance_index="index" }}

                  {{ elseif taxonomy == 'specialties_non_accredited' }}
                      {{# Medical Specialty Term Base (DefinedTerm) #}}
                      {{ schema_template_node id="d042e988-ce8b-41ad-bd0c-ac07e079642a" :instance_index="index" }}

                  {{ elseif taxonomy == 'research_specialties_acgme' }}
                      {{# ACGME Medical Research Specialty Term (DefinedTerm) #}}
                      {{ schema_template_node id="bd2a6a52-dea5-47c1-84b1-5eea55c0619d" :instance_index="index" }}

                  {{ elseif taxonomy == 'research_specialties_non_accredited' }}
                      {{# Research Specialty Base (DefinedTerm) #}}
                      {{ schema_template_node id="c96b3cd8-bf21-4abd-9740-c3656c17b431" :instance_index="index" }}

                  {{ elseif taxonomy == 'other_specialties' }}
                      {{# Specialty Term Base (DefinedTerm) #}}
                      {{ schema_template_node id="945499ea-b33d-43cc-b1f6-3a17e1bbacd7" :instance_index="index" }}

                  {{ /if }}
              {{ /knows_about_terms }}]
            mode: html
          _id: C4BGnAVB2vRa4vuo28MKM
          type: antlers
          enabled: true
        -
          value:
            code: |-
              {{#
                  Clinical Specialization entries (#2596), then Subject Matter entries
                  (#2822). `knows_about_entries` lists those two groups first, ahead of any
                  other collection's entries, so the `index > 0` comma is correct across
                  them. Other collections' entries are not emitted yet.
              #}}
              [{{ knows_about_entries }}
                  {{ if collection:handle == 'subject_matter' }}
                      {{ if index > 0 }},{{ /if }}

                      {{# Subject Matter Term (DefinedTerm) #}}
                      {{ schema_template_node id="f15f2efa-258e-4b16-aa4e-9010bce7514c" :instance_index="index" }}

                  {{ elseif collection:handle == 'clinical_specializations' }}
                      {{ if index > 0 }},{{ /if }}

                      {{ if extension_query === true }}
                          {{# Clinical Specialization Term Base (DefinedTerm) #}}
                          {{ schema_template_node id="ab9c43d8-6cd9-4e2c-a752-633ea2747649" :instance_index="index" }}

                      {{ else }}
                          {{# Clinical Specialization Term Canonical to Health Care Provider Taxonomy Code Set (DefinedTerm) #}}
                          {{ schema_template_node id="1cd75e43-3e72-4568-938b-0efd85059474" :instance_index="index" }}

                      {{ /if }}
                  {{ /if }}
              {{ /knows_about_entries }}]
            mode: html
          _id: C4BGnAVB2vRa4vuo31MKM
          type: antlers
          enabled: true
    knowsLanguage:
      mode: custom
      value:
        -
          value:
            code: |-
              {{
                  schema_language
                  :codes="language"
                  standard="bcp47"
              }}
            mode: html
          _id: xkplxhpUSx4GuFVtISzxB
          type: antlers
          enabled: true
    mainEntityOfPage:
      mode: custom
      value:
        -
          value:
            code: '{{ permalink }}'
            mode: html
          _id: 6xItHcO7RAa-R233qNeFL
          type: antlers
          enabled: true
    makesOffer:
      mode: inherit
      value: {  }
    memberOf:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ if member_of && ( member_of | is_iterable ) }}
                  [{{ $member_need_comma = false }}{{ member_of }}
                      {{# A row whose organization was deleted emits nothing, so the comma cannot key on `index`. #}}
                      {{ if organization }}
                      {{ if $member_need_comma }},{{ /if }}{{ $member_need_comma = true }}

                      {{#
                          Get `schema_object` field value if it exists.
                          Otherwise, fall back to "Membership Organization Base (Organization)" `schema_objects` collection entry.
                      #}}
                      {{ if membership_level.value && membership_level.value != 'member' }}
                          {{# A level above Member is carried as a Role around the organization. #}}
                          {{
                              schema_template_node
                              id="a7d3f1c8-52e6-4b0f-9c14-6e8b2d70f3a1"
                              :instance_index="index"
                          }}
                      {{ else }}
                          {{ organization }}
                              {{
                                  schema_template_node
                                  id="{{ schema_object ?? 'b755acff-0495-4ede-8701-966efa5f68a0' }}"
                                  :instance_index="index"
                              }}
                          {{ /organization }}
                      {{ /if }}
                      {{ /if }}
                  {{ /member_of }}]
              {{ /if }}
            mode: html
          _id: 0qt6kccrrvGWKxXwdkzwx
          type: antlers
          enabled: true
    name:
      mode: custom
      value:
        -
          value:
            code: |-
              {{
                  schema_pronounceable_text
                  :in_language="source_site:lang ?? source_site:locale ?? site:lang ?? site:locale"
                  :pairs="display_name_full"
                  speech_to_text_markup="IPA"
              }}
            mode: html
          _id: V2i4UsNSZ8vppmggxb12a
          type: antlers
          enabled: true
    nationality:
      mode: custom
      value:
        -
          value:
            code: |-
              {{
                  schema_country
                  :code="nationality"
              }}
            mode: html
          _id: oZPgo7yLUrShwG2LEPEcA
          type: antlers
          enabled: true
    netWorth:
      mode: inherit
      value: {  }
    owns:
      mode: inherit
      value: {  }
    parent:
      mode: inherit
      value: {  }
    performerIn:
      mode: inherit
      value: {  }
    potentialAction:
      mode: inherit
      value: {  }
    pronouns:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ pronouns }}
                  {{ unless no_results }}
                      {{ schema_pronouns
                          :name="title"
                          :description="description"
                          :nominative="nominative"
                          :accusative="accusative"
                          :dependent_possessive="dependent_possessive"
                          :independent_possessive="independent_possessive"
                          :reflexive="reflexive"
                      }}
                  {{ /unless }}
              {{ /pronouns }}
            mode: html
          _id: fxtGdZtoemsA2QFlE0ExL
          type: antlers
          enabled: true
    publishingPrinciples:
      mode: inherit
      value: {  }
    relatedTo:
      mode: inherit
      value: {  }
    sameAs:
      mode: custom
      value:
        -
          value:
            code: '{{ clinical_profile_url }}'
            mode: html
          _id: fxtGdZtoemsA2QFlE0ExK
          type: antlers
          enabled: true
        -
          value:
            code: '{{ academic_profile_url }}'
            mode: html
          _id: 7pN9wIxZUbSKks3lJXkA0
          type: antlers
          enabled: true
        -
          value:
            code: '{{ research_profile_url }}'
            mode: html
          _id: mlY0NxQafNuYAeB4Hsa8d
          type: antlers
          enabled: true
        -
          value:
            code: '{{ twitter }}'
            mode: html
          _id: FMGLMmjoN4M4SLJuQy3hY
          type: antlers
          enabled: true
    seeks:
      mode: inherit
      value: {  }
    sibling:
      mode: inherit
      value: {  }
    skills:
      mode: inherit
      value: {  }
    sponsor:
      mode: inherit
      value: {  }
    spouse:
      mode: inherit
      value: {  }
    subjectOf:
      mode: inherit
      value: {  }
    telephone:
      mode: custom
      value:
        -
          value:
            code: '{{ telephone }}'
            mode: html
          _id: 8c_m--dPEMxi6t7g13AwP
          type: antlers
          enabled: true
        -
          value:
            code: '{{ telephone_mobile }}'
            mode: html
          _id: MOgbV8CC-VYtna2CnO40b
          type: antlers
          enabled: true
    url:
      mode: custom
      value:
        -
          value:
            code: '{{ permalink }}'
            mode: html
          _id: sjnu59ogUzLj_Hci-5KMt
          type: antlers
          enabled: true
    weight:
      mode: inherit
      value: {  }
    workLocation:
      mode: inherit
      value: {  }
    worksFor:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ if uams_worker_query }}
                  {{
                      schema_template_node
                      id="58185f50-22a4-48e7-9575-b86a1fdffef9"
                  }}
              {{ /if }}
            mode: html
          _id: kRoBgtwSGp_RR09rNs0B4
          type: antlers
          enabled: true
    areaServed:
      mode: inherit
      value: {  }
    availableLanguage:
      mode: custom
      value:
        -
          value:
            code: |-
              {{
                  schema_language
                  :codes="language"
                  standard="bcp47"
              }}
            mode: html
          _id: y8Tj5sP17aaex_VYB-yBi
          type: antlers
          enabled: true
    contactOption:
      mode: inherit
      value: {  }
    contactType:
      mode: inherit
      value: {  }
    hoursAvailable:
      mode: inherit
      value: {  }
    productSupported:
      mode: inherit
      value: {  }
updated_by: cd0a8902-c35f-474a-89f5-d360ea4ca6ae
updated_at: 1776946792
---
