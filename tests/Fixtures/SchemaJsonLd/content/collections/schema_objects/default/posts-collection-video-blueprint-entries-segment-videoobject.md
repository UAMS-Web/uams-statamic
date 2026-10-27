---
id: 6c7f84aa-846f-4960-9ca1-dd47fdaccbcb
blueprint: schema_object
title: 'Posts Collection — Video Blueprint Entries Segment (VideoObject)'
schema:
  type:
    - VideoObject
  properties:
    about:
      mode: inherit_custom
      value:
        -
          value:
            code: |-
              {{#
              
              TODO

              Get what we can from from the host (i.e., Vimeo, Box)

              YouTube Data API:
                  `topicDetails.topicCategories` (array<string>): A list of Wikipedia URLs that provide a high-level description of the video's content.
              
              #}}
            mode: html
          _id: vSegDesc_9Rn3oMq3
          type: antlers
          enabled: true
    abstract:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ replicator_row:excerpt | raw | bard_text | sanitize }}
              {{#
              
              TODO

              Get what we can from from the host (i.e., Vimeo, Box)

              YouTube Data API:
                  `snippet.description` (string)
                  `snippet.localized.description` (string)

              Fall back to `replicator_row:excerpt | raw | bard_text | sanitize`
              
              #}}
            mode: html
          _id: vSegDesc_9Rn3oMq3
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
    actor:
      mode: inherit
      value: {  }
    additionalType:
      mode: inherit
      value: {  }
    aggregateRating:
      mode: inherit
      value: {  }
    alternateName:
      mode: custom
      value:
        -
          value:
            code: |-
              {{#
              
              TODO

              Get what we can from from the host (i.e., Vimeo, Box)

              YouTube Data API:
                  `snippet.title` (string)
                  `snippet.localized.title` (string)
                  `localizations.(key).title` (string)

              If `snippet.title` is different than `snippet.localized.title`, then return `snippet.localized.title`

              If `snippet.title` is different than `localizations.(key).title`, then return `localizations.(key).title`

              And if `snippet.title` is different than `(replicator_row:title ?? source_entry:title) | strip_tags | sanitize`, then return `(replicator_row:title ?? source_entry:title) | strip_tags | sanitize`
              
              #}}
            mode: html
          _id: KzHibkCoLMwt6_dCMj44T
          type: antlers
          enabled: true
    alternativeHeadline:
      mode: custom
      value:
        -
          value:
            code: |-
              {{#
              
              TODO

              Get what we can from from the host (i.e., Vimeo, Box)

              YouTube Data API:
                  `snippet.title`
                  `snippet.localized.title`

              If `snippet.title` is different than `snippet.localized.title`, then return `snippet.localized.title`

              And if `snippet.title` is different than `(replicator_row:title ?? source_entry:title) | strip_tags | sanitize`, then return `(replicator_row:title ?? source_entry:title) | strip_tags | sanitize`
              
              #}}
            mode: html
          _id: KzHibkCoLMwt6_dCMj45T
          type: antlers
          enabled: true
    archivedAt:
      mode: inherit
      value: {  }
    assesses:
      mode: inherit
      value: {  }
    associatedArticle:
      mode: inherit
      value: {  }
    associatedMedia:
      mode: inherit
      value: {  }
    audience:
      mode: inherit
      value: {  }
    audio:
      mode: inherit
      value: {  }
    author:
      mode: inherit
      value: {  }
    award:
      mode: inherit
      value: {  }
    bitrate:
      mode: custom
      value:
        -
          value:
            code: |-
              {{#

              TODO

              Get what we can from from the host (i.e., Vimeo, Box)

              YouTube Data API:
                  `fileDetails.bitrateBps` (unsigned long)

              #}}
            mode: html
          _id: K-UlixwFSoXTzlSdpecZ3
          type: antlers
          enabled: true
    caption:
      mode: custom
      value:
        -
          value:
            code: |-
              {{#

              TODO

              Get what we can from from the host (i.e., Vimeo, Box)

              YouTube Data API:
                  `contentDetails.caption` (string: 'true', 'false'): Indicates whether captions are available for the video

              #}}
            mode: html
          _id: JVqTL8z1E3GeB9dsMKasg
          type: antlers
          enabled: true
    character:
      mode: inherit
      value: {  }
    citation:
      mode: inherit
      value: {  }
    comment:
      mode: inherit
      value: {  }
    commentCount:
      mode: custom
      value:
        -
          value:
            code: |-
              {{#

              TODO

              Get what we can from from the host (i.e., Vimeo, Box)

              YouTube Data API:
                  `statistics.commentCount` (string)

              #}}
            mode: html
          _id: jmEuBP2_L-N4SV2hEjh-Q
          type: antlers
          enabled: true
    conditionsOfAccess:
      mode: inherit
      value: {  }
    contentLocation:
      mode: inherit
      value: {  }
    contentRating:
      mode: custom
      value:
        -
          value:
            code: |-
              {{#

              TODO

              Get what we can from from the host (i.e., Vimeo, Box)

              YouTube Data API:
                  `contentDetails.contentRating` (object): Specifies the ratings that the video received under various rating schemes (e.g., `contentDetails.contentRating.mpaaRating` [string]; some are array<string>)

              #}}
            mode: html
          _id: jmEuBP2_L-N4SV3hEjh-Q
          type: antlers
          enabled: true
    contentReferenceTime:
      mode: inherit
      value: {  }
    contentSize:
      mode: custom
      value:
        -
          value:
            code: |-
              {{#

              TODO

              Get what we can from from the host (i.e., Vimeo, Box)

              YouTube Data API:
                  `"fileDetails.fileSize` (unsigned long)

              #}}
            mode: html
          _id: XILwa2BaEiN__gip8PnIy
          type: antlers
          enabled: true
    contentUrl:
      mode: custom
      value:
        -
          value:
            code: '{{ replicator_row:url }}'
            mode: html
          _id: vSegContentUrl_8Qm2nLp1
          type: antlers
          enabled: true
    contributor:
      mode: inherit
      value: {  }
    copyrightHolder:
      mode: inherit
      value: {  }
    copyrightNotice:
      mode: inherit
      value: {  }
    copyrightYear:
      mode: inherit
      value: {  }
    correction:
      mode: inherit
      value: {  }
    countryOfOrigin:
      mode: inherit
      value: {  }
    creativeWorkStatus:
      mode: custom
      value:
        -
          value:
            code: |-
              Published
              {{#

              TODO

              Get what we can from from the host (i.e., Vimeo, Box)

              YouTube Data API:
                  `status.privacyStatus` (string: 'private', 'public', 'unlisted')

              Fall back to 'Published'

              #}}
            mode: html
          _id: QTWgLNyhfBw3b2irNIcG3
          type: antlers
          enabled: true
    creator:
      mode: inherit
      value: {  }
    creditText:
      mode: inherit
      value: {  }
    dateCreated:
      mode: custom
      value:
        -
          value:
            code: |-
              {{#

              TODO

              Get what we can from from the host (i.e., Vimeo, Box)

              YouTube Data API:
                  `recordingDetails.recordingDate` (datetime)
                  `fileDetails.creationTime` (string)

              #}}
            mode: html
          _id: QTWgLNyhfBw3b2irNIcG3
          type: antlers
          enabled: true
    dateModified:
      mode: custom
      value:
        -
          value:
            code: |-
              {{#

              TODO

              Get what we can from from the host (i.e., Vimeo, Box)

              #}}
            mode: html
          _id: aGial0WFBg1QrxsTOiCvD
          type: antlers
          enabled: true
    datePublished:
      mode: custom
      value:
        -
          value:
            code: |-
              {{#

              TODO

              Get what we can from from the host (i.e., Vimeo, Box)

              YouTube Data API:
                  `snippet.publishedAt`
                  `status.publishAt`

              #}}
            mode: html
          _id: RIu_lFPZg-wOKj0QvWZtF
          type: antlers
          enabled: true
    description:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ replicator_row:excerpt | strip_tags | sanitize }}
              {{#
              
              TODO

              Get what we can from from the host (i.e., Vimeo, Box)

              YouTube Data API:
                  `snippet.description` (string)
                  `snippet.localized.description` (string)
                  `localizations.(key).description` (string)

              Fall back to `replicator_row:excerpt | strip_tags | sanitize`
              
              #}}
            mode: html
          _id: vSegDesc_9Rn3oMq2
          type: antlers
          enabled: true
    digitalSourceType:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ ( replicator_row:digital_source_type ) | digital_source_type_value_schema }}
            mode: html
          _id: uXOSrDsC_af-AmaxAlxn7
          type: antlers
          enabled: true
    director:
      mode: inherit
      value: {  }
    disambiguatingDescription:
      mode: inherit
      value: {  }
    discussionUrl:
      mode: custom
      value:
        -
          value:
            code: |-
              {{#

              TODO

              If the host provides one, URL + fragment identifier of comments section.

              YouTube: `https://www.youtube.com/watch?v=VIDEOID#comments`
              Vimeo: TBD
              Box: TBD

              #}}
            mode: html
          _id: 6KYwUk2NtV-ymdsaqnUTb
          type: antlers
          enabled: true
    duration:
      mode: custom
      value:
        -
          value:
            code: |-
              {{#

              TODO

              Get what we can from from the host (i.e., Vimeo, Box)

              YouTube Data API:
                  `contentDetails.duration` (string as ISO 8601 duration)
                  `fileDetails.durationMs` (unsigned long)

              #}}
            mode: html
          _id: C630nxp5qvdjN9KJIax_z
          type: antlers
          enabled: true
    editEIDR:
      mode: inherit
      value: {  }
    editor:
      mode: inherit
      value: {  }
    educationalAlignment:
      mode: inherit
      value: {  }
    educationalLevel:
      mode: inherit
      value: {  }
    educationalUse:
      mode: inherit
      value: {  }
    embedUrl:
      mode: custom
      value:
        -
          value:
            code: '{{ replicator_row:url | embed_url }}'
            mode: html
          _id: vSegEmbedUrl_0Sn4pNr3
          type: antlers
          enabled: true
    embeddedTextCaption:
      mode: inherit
      value: {  }
    encodesCreativeWork:
      mode: inherit
      value: {  }
    encoding:
      mode: inherit
      value: {  }
    encodingFormat:
      mode: custom
      value:
        -
          value:
            code: |-
              {{#

              TODO

              Get what we can from from the host (i.e., Vimeo, Box)

              #}}
            mode: html
          _id: z53Xl8UbocYShTYyg0ClR
          type: antlers
          enabled: true
    endTime:
      mode: inherit
      value: {  }
    exampleOfWork:
      mode: inherit
      value: {  }
    expires:
      mode: inherit
      value: {  }
    funder:
      mode: inherit
      value: {  }
    funding:
      mode: inherit
      value: {  }
    genre:
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
            code: |-
              {{ (replicator_row:title ?? source_entry:title) | strip_tags | sanitize }}
              {{#
              
              TODO

              Get what we can from from the host (i.e., Vimeo, Box)

              YouTube Data API:
                  `snippet.title`

              Fall back to `(replicator_row:title ?? source_entry:title) | strip_tags | sanitize`
              
              #}}
            mode: html
          _id: KzHibkCoLMwt6_dCMj43T
          type: antlers
          enabled: true
    height:
      mode: custom
      value:
        -
          value:
            code: |-
              {{#

              TODO

              Get what we can from from the host (i.e., Vimeo, Box)

              YouTube Data API:
                  `player.embedHeight` (long)

              #}}
            mode: html
          _id: KzHibkCoLMwt6_dCMj42T
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
            code: |-
              {{#

              TODO

              Get what we can from from the host (i.e., Vimeo, Box)

              YouTube Data API:
                  `snippet.thumbnails.(key).url` (string)
                  `snippet.thumbnails.(key).width` (unsigned integer)
                  `snippet.thumbnails.(key).height` (unsigned integer)
                  `contentDetails.hasCustomThumbnail` (boolean): Indicates whether the video uploader has provided a custom thumbnail image for the video. This property is only visible to the video uploader.

              #}}
            mode: html
          _id: qb8TQZ0LgWYPhMypVPZFN
          type: antlers
          enabled: true
    inLanguage:
      mode: inherit_custom
      value:
        -
          value:
            code: |-
              {{#

              TODO

              Get what we can from from the host (i.e., Vimeo, Box)

              YouTube Data API:
                  `snippet.defaultLanguage`(string)
                  `snippet.defaultAudioLanguage`(string)

              #}}
            mode: html
          _id: P1f3mluckzWCLqfVk6slV
          type: antlers
          enabled: true
    ineligibleRegion:
      mode: inherit
      value: {  }
    interactionStatistic:
      mode: custom
      value:
        -
          value:
            code: |-
              {{#

              TODO

              Get what we can from from the host (i.e., Vimeo, Box)

              YouTube Data API:
                  `statistics.viewCount` (string)
                  `statistics.likeCount` (string)
                  `statistics.dislikeCount` (string)
                  `statistics.favoriteCount` (string)

              #}}
            mode: html
          _id: P1f3mluckzWCLqfVk4slV
          type: antlers
          enabled: true
    interactivityType:
      mode: inherit
      value: {  }
    interpretedAsClaim:
      mode: inherit
      value: {  }
    isAccessibleForFree:
      mode: custom
      value:
        -
          value: true
          _id: vSegFree_1To5qOs4
          type: boolean
          enabled: true
    isBasedOn:
      mode: inherit
      value: {  }
    isFamilyFriendly:
      mode: inherit
      value: {  }
    isPartOf:
      mode: inherit
      value: {  }
    keywords:
      mode: inherit_custom
      value:
        -
          value:
            code: |-
              {{#

              TODO

              Get what we can from from the host (i.e., Vimeo, Box)

              YouTube Data API:
                  `snippet.tags`(array<string>)

              #}}
            mode: html
          _id: P1f3mluckzWCLqfVk5slV
          type: antlers
          enabled: true
    learningResourceType:
      mode: inherit
      value: {  }
    license:
      mode: inherit
      value: {  }
    locationCreated:
      mode: inherit
      value: {  }
    mainEntity:
      mode: inherit
      value: {  }
    mainEntityOfPage:
      mode: inherit
      value: {  }
    maintainer:
      mode: inherit
      value: {  }
    material:
      mode: inherit
      value: {  }
    materialExtent:
      mode: inherit
      value: {  }
    mentions:
      mode: inherit
      value: {  }
    musicBy:
      mode: inherit
      value: {  }
    name:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ (replicator_row:title ?? source_entry:title) | strip_tags | sanitize }}
              {{#
              
              TODO

              Get what we can from from the host (i.e., Vimeo, Box)

              YouTube Data API:
                  `snippet.title`

              Fall back to `(replicator_row:title ?? source_entry:title) | strip_tags | sanitize`
              
              #}}
            mode: html
          _id: vSegName_2Up6rPt5
          type: antlers
          enabled: true
    offers:
      mode: inherit
      value: {  }
    pattern:
      mode: inherit
      value: {  }
    playerType:
      mode: custom
      value:
        -
          value:
            code: |-
              {{#

              TODO

              Research what this value is for each host:

              YouTube: TBD
              Vimeo: TBD
              Box: TBD (HTML5 video player?)

              #}}
            mode: html
          _id: EE2gEaqmGHvlA6nUPlbO9
          type: antlers
          enabled: true
    position:
      mode: custom
      value:
        -
          value:
            code: '{{ replicator_row_position }}'
            mode: html
          _id: vSegPos_3Vq7sQu6
          type: antlers
          enabled: true
    potentialAction:
      mode: custom
      value:
        -
          value:
            code: |-
              {{# Entry permalink + fragment identifier using the `id` of segments replicator set #}}
              {{ $potentialAction_properties = [
                  'target' => source_entry:permalink + '#' + replicator_row_id
              ] }}
              {{ schema_object
                  type="https://schema.org/WatchAction"
                  :properties="$potentialAction_properties"
                  :enabled="replicator_row_id"
              }}
            mode: html
          _id: vSegWatch_4Wr8tRv7
          type: antlers
          enabled: true
    producer:
      mode: inherit
      value: {  }
    productionCompany:
      mode: inherit
      value: {  }
    provider:
      mode: inherit
      value: {  }
    publication:
      mode: inherit
      value: {  }
    publisher:
      mode: inherit
      value: {  }
    publisherImprint:
      mode: inherit
      value: {  }
    publishingPrinciples:
      mode: inherit
      value: {  }
    recordedAt:
      mode: inherit
      value: {  }
    regionsAllowed:
      mode: inherit
      value: {  }
    releasedEvent:
      mode: inherit
      value: {  }
    requiresSubscription:
      mode: custom
      value:
        -
          value: false
          _id: vSegSub_5Xs9uSw8
          type: boolean
          enabled: true
    review:
      mode: inherit
      value: {  }
    sameAs:
      mode: inherit
      value: {  }
    schemaVersion:
      mode: inherit
      value: {  }
    sdDatePublished:
      mode: inherit
      value: {  }
    sdLicense:
      mode: inherit
      value: {  }
    sdPublisher:
      mode: inherit
      value: {  }
    sha256:
      mode: inherit
      value: {  }
    size:
      mode: custom
      value:
        -
          value:
            code: |-
              {{#

              TODO

              Get what we can from from the host (i.e., Vimeo, Box)

              YouTube Data API:
                  `contentDetails.definition` (string: 'hd', 'sd')

              #}}
            mode: html
          _id: '-ECZQ_OK7KttQrekzNhkL'
          type: antlers
          enabled: true
    sourceOrganization:
      mode: inherit
      value: {  }
    spatial:
      mode: inherit
      value: {  }
    spatialCoverage:
      mode: inherit
      value: {  }
    sponsor:
      mode: inherit
      value: {  }
    startTime:
      mode: inherit
      value: {  }
    subjectOf:
      mode: inherit
      value: {  }
    teaches:
      mode: inherit
      value: {  }
    temporal:
      mode: inherit
      value: {  }
    temporalCoverage:
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
            code: |-
              {{#

              TODO

              Get what we can from from the host (i.e., Vimeo, Box)

              YouTube Data API:
                  `snippet.thumbnails.(key).url` (string)
                  `snippet.thumbnails.(key).width` (unsigned integer)
                  `snippet.thumbnails.(key).height` (unsigned integer)
                  `contentDetails.hasCustomThumbnail` (boolean): Indicates whether the video uploader has provided a custom thumbnail image for the video. This property is only visible to the video uploader.

              #}}
            mode: html
          _id: oi9bDPzHzZzUekvwjn-jZ
          type: antlers
          enabled: true
    thumbnailUrl:
      mode: custom
      value:
        -
          value:
            code: |-
              {{#

              TODO

              Get what we can from from the host (i.e., Vimeo, Box)

              YouTube Data API:
                  `snippet.thumbnails.(key).url` (string)
                  `snippet.thumbnails.(key).width` (unsigned integer)
                  `snippet.thumbnails.(key).height` (unsigned integer)
                  `contentDetails.hasCustomThumbnail` (boolean): Indicates whether the video uploader has provided a custom thumbnail image for the video. This property is only visible to the video uploader.

              #}}
            mode: html
          _id: Z44MH0o6fE9YicBr6AkaO
          type: antlers
          enabled: true
    timeRequired:
      mode: inherit
      value: {  }
    transcript:
      mode: custom
      value:
        -
          value:
            code: '{{ replicator_row:transcript | raw | bard_text | sanitize }}'
            mode: html
          _id: vSegTranscript_6Yt0vTx9
          type: antlers
          enabled: true
    translationOfWork:
      mode: inherit
      value: {  }
    translator:
      mode: inherit
      value: {  }
    typicalAgeRange:
      mode: inherit
      value: {  }
    uploadDate:
      mode: custom
      value:
        -
          value:
            code: |-
              {{#

              TODO

              Get what we can from from the host (i.e., Vimeo, Box)

              YouTube Data API:
                  `snippet.publishedAt`
                  `status.publishAt`

              #}}
            mode: html
          _id: b705BGAl665M_Qbf9QTMX
          type: antlers
          enabled: true
    url:
      mode: inherit
      value: {  }
    usageInfo:
      mode: inherit
      value: {  }
    version:
      mode: inherit
      value: {  }
    video:
      mode: inherit
      value: {  }
    videoFrameSize:
      mode: custom
      value:
        -
          value:
            code: |-
              {{#

              TODO

              Get what we can from from the host (i.e., Vimeo, Box)

              #}}
            mode: html
          _id: W46-1S6SYcCTfEzyjNxrM
          type: antlers
          enabled: true
    videoQuality:
      mode: custom
      value:
        -
          value:
            code: |-
              {{#

              TODO

              Get what we can from from the host (i.e., Vimeo, Box)

              YouTube Data API:
                  `contentDetails.definition` (string: 'hd', 'sd')

              #}}
            mode: html
          _id: KxdQDDIUjzs803VLjtB-c
          type: antlers
          enabled: true
    width:
      mode: custom
      value:
        -
          value:
            code: |-
              {{#

              TODO

              Get what we can from from the host (i.e., Vimeo, Box)

              YouTube Data API:
                  `player.embedWidth` (long)

              #}}
            mode: html
          _id: iiIPk8ZXwyAS5jdmNFCSq
          type: antlers
          enabled: true
    wordCount:
      mode: inherit
      value: {  }
    workExample:
      mode: inherit
      value: {  }
    workTranslation:
      mode: inherit
      value: {  }
updated_by: cd0a8902-c35f-474a-89f5-d360ea4ca6ae
updated_at: 1775224926
parent: bfafc219-a844-4cff-bb0f-d69ed508963e
---
