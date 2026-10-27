---
id: 7b3e9f42-1c8a-4d6b-9f21-0a5e3c7d8b40
blueprint: schema_object
title: 'MedicalOrganization / LocalBusiness Type Base (Clinical Location)'
schema:
  type:
    - MedicalOrganization
    - LocalBusiness
  properties:
    name:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ title | strip_tags | sanitize }}
            mode: html
          _id: loc7b3e9f42name0001
          type: antlers
          enabled: true
    alternateName:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $alternate_name = alternate_name ?? null }}
              {{ if $alternate_name && ( $alternate_name | is_array ) }}
                  {{ $alternate_name | pluck('text') | to_json }}
              {{ elseif $alternate_name }}
                  {{ $alternate_name }}
              {{ /if }}
            mode: html
          _id: loc7b3e9f42altname01
          type: antlers
          enabled: true
    description:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ about | strip_tags | sanitize }}
            mode: html
          _id: loc7b3e9f42descr0001
          type: antlers
          enabled: true
    url:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ if external_website_url }}{{ external_website_url }}{{ else }}{{ permalink }}{{ /if }}
            mode: html
          _id: loc7b3e9f42url000001
          type: antlers
          enabled: true
    sameAs:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $same_as = same_as ?? null }}
              {{ if $same_as && ( $same_as | is_array ) }}
                  {{ $same_as | pluck('url') | to_json }}
              {{ elseif $same_as }}
                  {{ $same_as }}
              {{ /if }}
            mode: html
          _id: loc7b3e9f42sameas01
          type: antlers
          enabled: true
    telephone:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ phone | strip_tags | sanitize }}
            mode: html
          _id: loc7b3e9f42phone001
          type: antlers
          enabled: true
    faxNumber:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ fax | strip_tags | sanitize }}
            mode: html
          _id: loc7b3e9f42fax00001
          type: antlers
          enabled: true
    additionalType:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ $schema_additional_type = schema_additional_type ?? null }}
              {{ if $schema_additional_type && ( $schema_additional_type | is_array ) }}
                  {{ $schema_additional_type | pluck('uri') | to_json }}
              {{ elseif $schema_additional_type }}
                  {{ $schema_additional_type }}
              {{ /if }}
            mode: html
          _id: loc7b3e9f42addtype1
          type: antlers
          enabled: true
    address:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ schema_place :address="address" }}
            mode: html
          _id: loc7b3e9f42address1
          type: antlers
          enabled: true
    geo:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ schema_geo_coordinates :coordinate="map" }}
            mode: html
          _id: loc7b3e9f42geo00001
          type: antlers
          enabled: true
    hasMap:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ if google_business_profile_cid }}https://www.google.com/maps?cid={{ google_business_profile_cid }}{{ /if }}
            mode: html
          _id: loc7b3e9f42hasmap01
          type: antlers
          enabled: true
    image:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ schema_thing_from_relationship mode="direct" current_field="featured_image" schema_object="0ec21000-e498-4d08-94ee-8c98d151c48c" }}
            mode: html
          _id: loc7b3e9f42image001
          type: antlers
          enabled: true
    photo:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ schema_thing_from_relationship mode="direct" current_field="gallery_image" schema_object="0ec21000-e498-4d08-94ee-8c98d151c48c" }}
            mode: html
          _id: loc7b3e9f42photo001
          type: antlers
          enabled: true
    acceptedPaymentMethod:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ if payment_query }}{{ schema_payment_methods :methods="accepted_payment_methods" :card_brands="accepted_card_brands" }}{{ /if }}
            mode: html
          _id: loc7b3e9f42acceptpm
          type: antlers
          enabled: true
    paymentAccepted:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ if payment_query }}{{ schema_payment_methods :methods="accepted_payment_methods" output="text" }}{{ /if }}
            mode: html
          _id: loc7b3e9f42paymenttx
          type: antlers
          enabled: true
    amenityFeature:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ schema_amenity_features :hearing_loop="google_business_profile_has_hearing_loop" :auracast="google_business_profile_has_auracast_broadcast_audio" :wheelchair_entrance="google_business_profile_has_wheelchair_accessible_entrance" :wheelchair_parking="google_business_profile_has_wheelchair_accessible_parking" :wheelchair_restroom="google_business_profile_has_wheelchair_accessible_restroom" }}
            mode: html
          _id: loc7b3e9f42amenity1
          type: antlers
          enabled: true
    medicalSpecialty:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ schema_org_term_uris :terms="schema_medicalspecialty" }}
            mode: html
          _id: loc7b3e9f42medspec1
          type: antlers
          enabled: true
    knowsLanguage:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ if interpreter_query }}{{ schema_languages :languages="interpreter_language" }}{{ /if }}
            mode: html
          _id: loc7b3e9f42language1
          type: antlers
          enabled: true
    parentOrganization:
      mode: custom
      value:
        -
          value:
            - 58185f50-22a4-48e7-9575-b86a1fdffef9
          _id: loc7b3e9f42parentorg
          type: thing_organization
          enabled: true
    areaServed:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ schema_administrative_areas :areas="regions" }}
            mode: html
          _id: loc7b3e9f42areaserv
          type: antlers
          enabled: true
    identifier:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ schema_location_identifiers :npi="npi" :npi_enabled="npi_query" :va_substation_id="va_substation_id" :ahaid="ahaid" :hospital_ccn_id="hospital_ccn_id" }}
            mode: html
          _id: loc7b3e9f42identif
          type: antlers
          enabled: true
    contactPoint:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ schema_location_contact_points :appointment_enabled="phone_appointment_query" :phone_appointment_new="phone_appointment_new" :phone_appointment_return="phone_appointment_return" :ac_enabled="ac_appointments_query" :ac_primary="ac_appointments_primary" :ac_specialty="ac_appointments_specialty" :phone_additional="phone_additional" }}
            mode: html
          _id: loc7b3e9f42contactp
          type: antlers
          enabled: true
    openingHoursSpecification:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ schema_location_hours :regular_hours="regular_hours" }}
            mode: html
          _id: loc7b3e9f42openhrs
          type: antlers
          enabled: true
    specialOpeningHoursSpecification:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ schema_location_hours output="special" :special_hours="special_hours" }}
            mode: html
          _id: loc7b3e9f42spechrs
          type: antlers
          enabled: true
updated_by: cd0a8902-c35f-474a-89f5-d360ea4ca6ae
updated_at: 1777902480
---
