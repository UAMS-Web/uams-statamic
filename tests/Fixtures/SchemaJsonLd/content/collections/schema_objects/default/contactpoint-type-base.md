---
id: f4243602-77ef-44dc-80ba-fc721b4b99cb
blueprint: schema_object
title: 'ContactPoint Type Base'
schema:
  type:
    - ContactPoint
  properties:
    additionalType:
      mode: inherit
      value: {  }
    alternateName:
      mode: inherit
      value: {  }
    areaServed:
      mode: inherit
      value: {  }
    availableLanguage:
      mode: inherit
      value: {  }
    contactOption:
      mode: inherit
      value: {  }
    contactType:
      mode: inherit
      value: {  }
    description:
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
          _id: qLlWxKMWJyEnSYjvK1E1T
          type: antlers
          enabled: true
    disambiguatingDescription:
      mode: inherit
      value: {  }
    email:
      mode: inherit
      value: {  }
    faxNumber:
      mode: inherit
      value: {  }
    hoursAvailable:
      mode: inherit
      value: {  }
    identifier:
      mode: inherit
      value: {  }
    image:
      mode: inherit
      value: {  }
    mainEntityOfPage:
      mode: inherit
      value: {  }
    name:
      mode: inherit
      value: {  }
    potentialAction:
      mode: inherit
      value: {  }
    productSupported:
      mode: inherit
      value: {  }
    sameAs:
      mode: inherit
      value: {  }
    subjectOf:
      mode: inherit
      value: {  }
    telephone:
      mode: inherit
      value: {  }
    url:
      mode: inherit
      value: {  }
updated_by: cd0a8902-c35f-474a-89f5-d360ea4ca6ae
updated_at: 1775656798
parent: 1a834c0c-f4af-4223-be3b-ee45b218cd39
---
