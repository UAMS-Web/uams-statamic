---
id: 3fb4a6da-54f2-4dc1-bf4b-75dc8a298460
blueprint: schema_object
title: 'WebSite Type Base'
schema:
  type:
    - WebSite
  properties:
    name:
      mode: custom
      value:
        -
          value:
            code: '{{ site:name }}'
            mode: html
          _id: d7aqsjSry8ESPX3_1oXru
          type: antlers
          enabled: true
    url:
      mode: custom
      value:
        -
          value:
            code: '{{ site:permalink }}'
            mode: html
          _id: pVy9KiKIIGuVynko2FJC9
          type: antlers
          enabled: true
    about:
      mode: custom
      value:
        -
          value:
            code: '{{ get_schema_slot slot="subject" }}'
            mode: html
          _id: NtnbdIi8Mbbf-IBWwJmSl
          type: antlers
          enabled: true
    digitalSourceType:
      mode: custom
      value:
        -
          value:
            - https://schema.org/CompositeWithTrainedAlgorithmicMediaDigitalSource
          _id: HbIujZvo93aYtwaTTv1Hx
          type: enumeration_iptcdigitalsourceenumeration
          enabled: true
    hasPart:
      mode: inherit
      value: {  }
    headline:
      mode: custom
      value:
        -
          value:
            code: '{{ site:name }}'
            mode: html
          _id: PsE1qmNkp15juqpfNswIi
          type: antlers
          enabled: true
    inLanguage:
      mode: inherit
      value: {  }
updated_by: cd0a8902-c35f-474a-89f5-d360ea4ca6ae
updated_at: 1773687455
parent: 113b8a1e-b794-4c36-99c8-62777daf8b5b
---
