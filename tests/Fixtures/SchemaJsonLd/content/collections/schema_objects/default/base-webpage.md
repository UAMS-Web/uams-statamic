---
id: 8c6025f0-3ff5-4724-ae79-41855dab2ff8
blueprint: schema_object
title: 'WebPage Type Base'
schema:
  type:
    - WebPage
  properties:
    digitalSourceType:
      mode: inherit
      value: {  }
    mainEntityOfPage:
      mode: disable
      value: {  }
    hasPart:
      mode: custom
      value:
        -
          value:
            - 631e2902-905b-4f2c-82aa-3f8c93b8e747
            - 94d4997f-a818-45e6-91a0-1ee1cbf17c03
            - e6f313dd-51fd-4430-b7cd-997b6ae03bca
          _id: 2AP_11deWhJM9DKCaczbx
          type: thing_creativework
          enabled: true
        -
          value:
            code: '{{ get_schema_slot slot="main_content" }}'
            mode: html
          _id: 5Gu2eEBco43l5orMiigSe
          type: antlers
          enabled: true
    speakable:
      mode: custom
      value:
        -
          value:
            - ee631903-f87f-4f64-9dd2-3a38a7f6c4c8
          _id: M40RFH_HWLFVHagFWoAiU
          type: thing_speakablespecification
          enabled: true
updated_by: cd0a8902-c35f-474a-89f5-d360ea4ca6ae
updated_at: 1774637612
parent: 9bb7ad28-6178-4b70-bb15-10c57ba1f0dc
---
