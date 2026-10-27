---
id: 9bb7ad28-6178-4b70-bb15-10c57ba1f0dc
blueprint: schema_object
title: 'WebPage and Content Type Base (CreativeWork)'
schema:
  type:
    - CreativeWork
  properties:
    about:
      mode: inherit
      value: {  }
    abstract:
      mode: custom
      value:
        -
          value:
            code: '{{ excerpt ?? description }}'
            mode: html
          _id: zdPnMkL6AYw1aRAsN0BLv
          type: antlers
          enabled: true
    accessMode:
      mode: inherit
      value: {  }
    accessModeSufficient:
      mode: inherit
      value: {  }
    accessibilityAPI:
      mode: inherit
      value: {  }
    accessibilityControl:
      mode: inherit
      value: {  }
    accessibilityFeature:
      mode: inherit
      value: {  }
    accessibilityHazard:
      mode: inherit
      value: {  }
    accessibilitySummary:
      mode: inherit
      value: {  }
    accountablePerson:
      mode: inherit
      value: {  }
    acquireLicensePage:
      mode: inherit
      value: {  }
    additionalType:
      mode: inherit
      value: {  }
    alternateName:
      mode: custom
      value:
        -
          value:
            code: '{{ title_short | strip_tags | sanitize }}'
            mode: html
          _id: lYqK4hwXdp5bAb5hPExEi
          type: antlers
          enabled: true
    alternativeHeadline:
      mode: custom
      value:
        -
          value:
            code: '{{ title_short | strip_tags | sanitize }}'
            mode: html
          _id: LjMaeoPfFIOg2VYCxxZKN
          type: antlers
          enabled: true
    archivedAt:
      mode: inherit
      value: {  }
    associatedMedia:
      mode: inherit
      value: {  }
    audience:
      mode: inherit
      value: {  }
    author:
      mode: inherit
      value: {  }
    citation:
      mode: inherit
      value: {  }
    comment:
      mode: inherit
      value: {  }
    commentCount:
      mode: inherit
      value: {  }
    conditionsOfAccess:
      mode: inherit
      value: {  }
    contentRating:
      mode: inherit
      value: {  }
    contentReferenceTime:
      mode: inherit
      value: {  }
    contributor:
      mode: inherit
      value: {  }
    creativeWorkStatus:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ if ( published === false ) || ( published == 'false' ) }}Draft{{ else }}Published{{ /if }}
            mode: html
          _id: kugKZ4B3Kqh6o5mEc6Lxp
          type: antlers
          enabled: true
    creditText:
      mode: inherit
      value: {  }
    dateCreated:
      mode: custom
      value:
        -
          value:
            code: '{{ ( date ?? updated_at ) | format("c") }}'
            mode: html
          _id: hgeA9YOo5WqF2czw4KfZw
          type: antlers
          enabled: true
    dateModified:
      mode: custom
      value:
        -
          value:
            code: '{{ ( updated_at ?? date ) | format("c") }}'
            mode: html
          _id: P8pitrYqOR_Ivd2rbnC4n
          type: antlers
          enabled: true
    datePublished:
      mode: custom
      value:
        -
          value:
            code: '{{ ( date ?? updated_at ) | format("c") }}'
            mode: html
          _id: DyIFUfiUH4Q7Vges5iV72
          type: antlers
          enabled: true
    description:
      mode: custom
      value:
        -
          value:
            code: '{{ ( excerpt ?? description ) | sanitize }}'
            mode: html
          _id: pJtFH_vcPzmBCx3pvwH7e
          type: antlers
          enabled: true
    digitalSourceType:
      mode: inherit
      value: {  }
    disambiguatingDescription:
      mode: inherit
      value: {  }
    discussionUrl:
      mode: inherit
      value: {  }
    editor:
      mode: inherit
      value: {  }
    educationalLevel:
      mode: inherit
      value: {  }
    educationalUse:
      mode: inherit
      value: {  }
    funder:
      mode: inherit
      value: {  }
    funding:
      mode: inherit
      value: {  }
    hasPart:
      mode: inherit
      value: {  }
    headline:
      mode: custom
      value:
        -
          value:
            code: '{{ title | strip_tags | sanitize }}'
            mode: html
          _id: BE1D6Gcch2fOTuVa0yU2d
          type: antlers
          enabled: true
    identifier:
      mode: inherit
      value: {  }
    image:
      mode: custom
      value:
        -
          value:
            code: '{{ schema_thing_from_relationship mode="direct" current_field="featured_image_16_9|featured_image|thumbnail" schema_object="0ec21000-e498-4d08-94ee-8c98d151c48c" }}'
            mode: html
          _id: GJy3pCyZS7YDNIe2UR-GQ
          type: antlers
          enabled: true
        -
          value:
            code: '{{ schema_thing_from_relationship mode="direct" current_field="featured_image_4_3" schema_object="0ec21000-e498-4d08-94ee-8c98d151c48c" }}'
            mode: html
          _id: GJy3pCyZS7YDNIe3UR-GQ
          type: antlers
          enabled: true
        -
          value:
            code: '{{ schema_thing_from_relationship mode="direct" current_field="featured_image_1_1" schema_object="0ec21000-e498-4d08-94ee-8c98d151c48c" }}'
            mode: html
          _id: GJy3pCyZS7YDNIe4UR-GQ
          type: antlers
          enabled: true
    interactionStatistic:
      mode: inherit
      value: {  }
    interpretedAsClaim:
      mode: inherit
      value: {  }
    isBasedOn:
      mode: inherit
      value: {  }
    isBasedOnUrl:
      mode: inherit
      value: {  }
    isFamilyFriendly:
      mode: inherit
      value: {  }
    isPartOf:
      mode: custom
      value:
        -
          value:
            code: '{{ get_schema_slot slot="website" }}'
            mode: html
          _id: zBB-AL75EFckdA3K1DVOD
          type: antlers
          enabled: true
    keywords:
      mode: inherit
      value: {  }
    learningResourceType:
      mode: inherit
      value: {  }
    license:
      mode: inherit
      value: {  }
    mainEntity:
      mode: inherit
      value: {  }
    mainEntityOfPage:
      mode: custom
      value:
        -
          value:
            code: '{{ permalink }}'
            mode: html
          _id: kUhpXWMrphk6AZLKr5Ogt
          type: antlers
          enabled: true
    mentions:
      mode: inherit
      value: {  }
    name:
      mode: custom
      value:
        -
          value:
            code: '{{ title | strip_tags | sanitize }}'
            mode: html
          _id: yplEvqTAnEgTdETBVokw9
          type: antlers
          enabled: true
    position:
      mode: inherit
      value: {  }
    potentialAction:
      mode: inherit
      value: {  }
    producer:
      mode: inherit
      value: {  }
    provider:
      mode: inherit
      value: {  }
    publishingPrinciples:
      mode: inherit
      value: {  }
    review:
      mode: inherit
      value: {  }
    reviews:
      mode: inherit
      value: {  }
    sameAs:
      mode: inherit
      value: {  }
    schemaVersion:
      mode: inherit
      value: {  }
    sdLicense:
      mode: inherit
      value: {  }
    sponsor:
      mode: inherit
      value: {  }
    subjectOf:
      mode: inherit
      value: {  }
    text:
      mode: inherit
      value: {  }
    thumbnail:
      mode: custom
      value:
        -
          value:
            code: '{{ schema_thing_from_relationship mode="direct" current_field="featured_image_16_9|featured_image|thumbnail" schema_object="0ec21000-e498-4d08-94ee-8c98d151c48c" }}'
            mode: html
          _id: v-opzoj6lW1qWrkLRvrOf
          type: antlers
          enabled: true
        -
          value:
            code: '{{ schema_thing_from_relationship mode="direct" current_field="featured_image_4_3" schema_object="0ec21000-e498-4d08-94ee-8c98d151c48c" }}'
            mode: html
          _id: v-opzoj6lW1qWrkLRvr1f
          type: antlers
          enabled: true
        -
          value:
            code: '{{ schema_thing_from_relationship mode="direct" current_field="featured_image_1_1" schema_object="0ec21000-e498-4d08-94ee-8c98d151c48c" }}'
            mode: html
          _id: v-opzoj6lW1qWrkLRvr2f
          type: antlers
          enabled: true
    thumbnailUrl:
      mode: custom
      value:
        -
          value:
            code: '{{ featured_image_16_9 ? featured_image_16_9:permalink : ( featured_image ? featured_image:permalink : ( thumbnail ? thumbnail:permalink : null) )  }}'
            mode: html
          _id: v-opzoj5lW1qWrkLRvrOf
          type: antlers
          enabled: true
        -
          value:
            code: '{{ featured_image_4_3:permalink }}'
            mode: html
          _id: v-opzoj5lW1qWrkLRvr1f
          type: antlers
          enabled: true
        -
          value:
            code: '{{ featured_image_1_1:permalink }}'
            mode: html
          _id: v-opzoj5lW1qWrkLRvr2f
          type: antlers
          enabled: true
    translator:
      mode: inherit
      value: {  }
    typicalAgeRange:
      mode: inherit
      value: {  }
    usageInfo:
      mode: inherit
      value: {  }
updated_by: cd0a8902-c35f-474a-89f5-d360ea4ca6ae
updated_at: 1774887553
parent: 113b8a1e-b794-4c36-99c8-62777daf8b5b
---
