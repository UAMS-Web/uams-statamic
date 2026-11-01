---
id: a7d3f1c8-52e6-4b0f-9c14-6e8b2d70f3a1
blueprint: schema_object
title: 'Membership Level (Role)'
schema:
  type:
    - Role
  properties:
    roleName:
      mode: custom
      value:
        -
          value:
            code: '{{ membership_level.label }}'
            mode: html
          _id: mLvlRoleNameBlockAaaa1
          type: antlers
          enabled: true
    memberOf:
      mode: custom
      value:
        -
          value:
            code: |-
              {{ organization }}
                  {{ $member_org_schema = schema_object ? (schema_object | raw) : 'b755acff-0495-4ede-8701-966efa5f68a0' }}
                  {{
                      schema_template_node
                      :id="$member_org_schema"
                      :instance_index="index"
                  }}
              {{ /organization }}
            mode: html
          _id: mLvlRoleMemberOfBlock2
          type: antlers
          enabled: true
---
