---
id: 68d328fb-64cb-4e6b-9646-22659c71f3ba
blueprint: schema_object
title: 'high school student (OrganizationRole)'
schema:
  type:
    - OrganizationRole
  properties:
    roleName:
      mode: custom
      value:
        -
          value: 'high school student'
          _id: 2cg9jHExLztM4J1VtcXCC
          type: text_short
          enabled: true
    alternateName:
      mode: custom
      value:
        -
          value: 'secondary student'
          _id: 2cg9jHExLztM4J1VtcXCD
          type: text_short
          enabled: true
        -
          value: 'secondary school student'
          _id: 2cg9jHExLztM4J1VtcXCE
          type: text_short
          enabled: true
    sameAs:
      mode: custom
      value:
        -
          value: 'https://www.wikidata.org/wiki/Q15360275'
          _id: OaWcbWasrWLVCnIePQHQe
          type: url
          enabled: true
    alumniOf:
      mode: inherit
      value: {  }
    endDate:
      mode: inherit
      value: {  }
    startDate:
      mode: disable
      value: {  }
    description:
      mode: disable
      value: {  }
    name:
      mode: disable
      value: {  }
    url:
      mode: disable
      value: {  }
updated_by: cd0a8902-c35f-474a-89f5-d360ea4ca6ae
updated_at: 1777923676
---
