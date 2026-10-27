---
id: dcf826e2-f8eb-4f9b-8011-2e11d21b2235
blueprint: schema_object
title: 'Contact For Media Inquiries — News Release Level (CommunicateAction)'
schema:
  type:
    - CommunicateAction
  properties:
    about:
      mode: inherit
      value: {  }
    actionProcess:
      mode: inherit
      value: {  }
    actionStatus:
      mode: inherit
      value: {  }
    additionalType:
      mode: inherit
      value: {  }
    agent:
      mode: inherit
      value: {  }
    alternateName:
      mode: inherit
      value: {  }
    description:
      mode: inherit
      value: {  }
    disambiguatingDescription:
      mode: inherit
      value: {  }
    endTime:
      mode: inherit
      value: {  }
    error:
      mode: inherit
      value: {  }
    identifier:
      mode: inherit
      value: {  }
    image:
      mode: inherit
      value: {  }
    inLanguage:
      mode: inherit
      value: {  }
    instrument:
      mode: inherit
      value: {  }
    location:
      mode: inherit
      value: {  }
    mainEntityOfPage:
      mode: inherit
      value: {  }
    name:
      mode: inherit
      value: {  }
    object:
      mode: inherit
      value: {  }
    participant:
      mode: inherit
      value: {  }
    potentialAction:
      mode: inherit
      value: {  }
    provider:
      mode: inherit
      value: {  }
    recipient:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ schema_thing_from_relationship
                mode="secondary"
                current_field="media_contact|source_entry.media_contact"
                secondary_context="terms"
                secondary_scope="media_contact_groups"
                secondary_field="people"
                nested_scope="people"
                merge_source_context="false"
                schema_object="53e08839-fade-4c73-b4d5-007896d296ac"
              }}
            mode: html
          _id: d-mymWeckvvmAWx19CTHu
          type: antlers
          enabled: true
    result:
      mode: inherit
      value: {  }
    sameAs:
      mode: inherit
      value: {  }
    startTime:
      mode: inherit
      value: {  }
    subjectOf:
      mode: inherit
      value: {  }
    target:
      mode: inherit
      value: {  }
    url:
      mode: inherit
      value: {  }
updated_by: cd0a8902-c35f-474a-89f5-d360ea4ca6ae
updated_at: 1775670088
parent: af97172c-8bd9-4015-ac23-0121be432c6b
---
