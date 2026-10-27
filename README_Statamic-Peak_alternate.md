# Alternate Changes Made Based on Statamic Peak

## Page Builder Fieldset

Create `resources/fieldsets/page_builder.yaml`:
```yaml
title: 'Page Builder'
fields:
  -
    handle: page_builder
    field:
      type: replicator
      localizable: true
      hide_display: true
      collapse: true
      listable: false
      display: 'Page Builder'
      button_label: 'Add block'
      sets:
        interactive:
          display: Interactive
          instructions: 'Interactive blocks.'
          icon: programming-script-code
          sets:
            uamsweb_block_action_bar:
              display: 'UAMS Action Bar'
              instructions: '3-4 items with text and link'
              icon: link
              fields:
                -
                  import: uamsweb_block_action_bar
            uamsweb_block_cta:
              display: 'UAMS Call-to-Action Bar'
              instructions: 'Large block with text and a link'
              icon: link
              fields:
                -
                  import: uamsweb_block_cta
            uamsweb_block_gallery:
              display: 'UAMS Gallery'
              instructions: 'Group of images'
              icon: media-image-picture-gallery
              fields:
                -
                  import: uamsweb_block_gallery
            uamsweb_block_image_side_by_side:
              display: 'UAMS Side-by-Side Image and Text'
              instructions: 'Large image beside text and link'
              icon: layout-two-columns
              fields:
                -
                  import: uamsweb_block_image_side_by_side
            uamsweb_block_link_list:
              display: 'UAMS Link List'
              icon: link
              fields:
                -
                  import: uamsweb_block_link_list
            uamsweb_block_livewhale:
              display: 'UAMS LiveWhale Calendar'
              icon: calendar-date
              fields:
                -
                  import: uamsweb_block_livewhale
            uamsweb_block_logo_list:
              display: 'UAMS Logo List'
              instructions: 'List of logos with optional links'
              icon: dashboard-layout
              fields:
                -
                  import: uamsweb_block_logo_list
            uamsweb_block_news:
              display: 'UAMS News'
              instructions: 'Dynamic news content'
              icon: dashboard-layout
              fields:
                -
                  import: uamsweb_block_news
            uamsweb_block_overlay:
              display: 'UAMS Text and Image Overlay'
              instructions: 'Text and a link in front of an image'
              icon: link
              fields:
                -
                  import: uamsweb_block_overlay
            uamsweb_block_people:
              display: 'UAMS People'
              instructions: 'List of people'
              icon: user-multiple
              fields:
                -
                  import: uamsweb_block_people
            uamsweb_block_stacked:
              display: 'UAMSWeb Stacked Image and Text'
              instructions: 'Image above text and a link'
              icon: dashboard-layout
              fields:
                -
                  import: uamsweb_block_stacked
        text:
          display: Text
          instructions: 'Text based blocks.'
          icon: text-formatting-initial-letter
          sets:
            uamsweb_block_call_out:
              display: 'UAMS Call-Out'
              instructions: 'Large heading and a paragraph'
              icon: text-formatting-initial-letter
              fields:
                -
                  import: uamsweb_block_call_out
            uamsweb_block_content:
              display: 'UAMS Content'
              instructions: 'Heading and rich text'
              icon: text-formatting-initial-letter
              fields:
                -
                  import: uamsweb_block_content
            uamsweb_block_counter:
              display: 'UAMS Counter List'
              instructions: 'Items adding up over time'
              icon: time-clock
              fields:
                -
                  import: uamsweb_block_counter
        uams_health:
          display: 'UAMS Health'
          icon: labs-idea-experimental-flask
          sets:
            uamsweb_block_fad_providers:
              display: Providers
              instructions: 'Filtered list of providers'
              icon: user-avatar
              fields:
                -
                  import: uamsweb_block_fad_providers
            uamsweb_block_fad_locations:
              display: Locations
              instructions: 'Filtered list of locations'
              icon: home-house
              fields:
                -
                  import: uamsweb_block_fad_locations
            uamsweb_block_fad_clinical_resources:
              display: 'Clinical Resources'
              instructions: 'Filtered list of clinical resources'
              icon: media-webcam-video
              fields:
                -
                  import: uamsweb_block_fad_clinical_resources
            uamsweb_block_fad_expertise:
              display: 'Areas of Expertise'
              instructions: 'Filtered list of areas of expertise'
              icon: favorite-award
              fields:
                -
                  import: uamsweb_block_fad_expertise
```

## Default Template View

Change `resources/views/default.antlers.html` from:
```antlers
{{ content }}
```
to:
```antlers
{{ if page_builder }}
    {{ page_builder scope="block" }}
        {{ partial src="page_builder/{type}" }}
    {{ /page_builder }}
{{ else }}
    {{ content }}
{{ /if }}
```
