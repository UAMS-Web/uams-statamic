# Schema.org Planning for "University of Arkansas for Medical Sciences" as the Organization

## additionalProperty

https://schema.org/additionalProperty

**Property Description:** A property-value pair representing an additional characteristic of the entity, e.g. a product feature or another characteristic for which there is no matching property in schema.org.

**Application:** A "catch-all" for data that has no standard field, such as "Bed Count" for the hospital or "Research Tier."

## alumni

https://schema.org/alumni

**Property Description:** Alumni of an organization.

**Application:** Use this to list notable graduates to boost the institution’s authority.

## award

https://schema.org/award

**Property Description:** An award won by or for this item.

**Application:** Used for rankings like "U.S. News & World Report Best Hospitals."

## brand

https://schema.org/brand

**Property Description:** The brand(s) associated with a product or service, or the brand(s) maintained by an organization or business person.

**Application:** "UAMS Health" is the clinical brand maintained by the UAMS organization.

## contactPoint

https://schema.org/contactPoint

**Property Description:** A contact point for a person or organization.

**Application:** Essential for providing specific phone numbers (e.g., Appointments, Main Operator, Media Relations).

## containsPlace

https://schema.org/containsPlace

**Property Description:** The basic containment relation between a place and one that contains it.

**Application:** Useful for describing how specific buildings (like the Jackson T. Stephens Spine & Neurosciences Institute) are contained within the UAMS campus.

## department

https://schema.org/department

**Property Description:** A relationship between an organization and a department of that organization, also described as an organization (allowing different urls, logos, opening hours). For example: a store with a pharmacy, or a bakery with a cafe.

**Application:** Use this to nest the Colleges (Medicine, Nursing, etc.) or clinical departments.

## description

https://schema.org/description

**Property Description:** A description of the item.

**Application:** A concise summary of UAMS as Arkansas' only academic health sciences center.

## disambiguatingDescription

https://schema.org/disambiguatingDescription

**Property Description:** A sub property of description. A short description of the item used to disambiguate from other, similar items. Information from other properties (in particular, name) may be necessary for the description to be useful for disambiguation.

**Application:** TBD

## email

https://schema.org/email

**Property Description:** Email address.

**Application:** Best used within a ContactPoint or department block rather than for the whole university.

## ethicsPolicy

https://schema.org/ethicsPolicy

**Property Description:** Statement about ethics policy, e.g. of a NewsMediaOrganization regarding journalistic and publishing practices, or of a Restaurant, a page describing food source policies. In the case of a NewsMediaOrganization, an ethicsPolicy is typically a statement describing the personal, organizational, and corporate standards of behavior expected by the organization.

**Application:** Link to the UAMS Administrative Guide or the Conflict of Interest Office policies.

## event

https://schema.org/event

**Property Description:** Upcoming or past event associated with this place, organization, or action.

**Application:** Links to the UAMS campus calendar for seminars or community health events.

## faxNumber

https://schema.org/faxNumber

**Property Description:** The fax number.

**Application:** Best used within a ContactPoint or department block rather than for the whole university.

## funder

https://schema.org/funder

**Property Description:** A person or organization that supports (sponsors) something through some kind of financial contribution.

**Application:** Use this to link to state appropriations or specific federal NIH/HRSA grants.

## funding

https://schema.org/funding

**Property Description:** A Grant that directly or indirectly provide funding or sponsorship for this item. See also ownershipFundingInfo.

**Application:** Use this to link to state appropriations or specific federal NIH/HRSA grants.

## geoWithin

https://schema.org/geoWithin

**Property Description:** Represents a relationship between two geometries (or the places they represent), relating a geometry to one that contains it, i.e. it is inside (i.e. within) its interior. As defined in DE-9IM.

**Application:** Useful for describing how specific buildings (like the Jackson T. Stephens Spine & Neurosciences Institute) are contained within the UAMS campus.

## globalLocationNumber

https://schema.org/globalLocationNumber

**Property Description:** The Global Location Number (GLN, sometimes also referred to as International Location Number or ILN) of the respective organization, person, or place. The GLN is a 13-digit number used to identify parties and physical locations.

**Application:** Used primarily for supply chain and pharmacy transactions. If UAMS has a GLN through GS1 for its pharmacy/shipping docks, it belongs here.

## hasCertification

https://schema.org/hasCertification

**Property Description:** Certification information about a product, organization, service, place, or person.

**Application:** Essential for listing Magnet Status for nursing or LCME accreditation for the medical school.

## hasCredential

https://schema.org/hasCredential

**Property Description:** A credential awarded to the Person or Organization.

**Application:** Essential for listing Magnet Status for nursing or LCME accreditation for the medical school.

## hasMap

https://schema.org/hasMap

**Property Description:** A URL to a map of the place.

**Application:** A URL to a Google Maps pin or a campus map PDF.

## hasOfferCatalog

https://schema.org/hasOfferCatalog

**Property Description:** Indicates an OfferCatalog listing for this Organization, Person, or Service.

**Application:** Use this to list "educational services" (degree programs) or "medical services" (specialized clinics).

## healthPlanNetworkId

https://schema.org/healthPlanNetworkId

**Property Description:** Name or unique ID of network. (Networks are often reused across different insurance plans.)

**Application:** Used to identify which insurance networks (e.g., Arkansas Blue Cross Blue Shield) UAMS is a part of.

## identifier

https://schema.org/identifier

**Property Description:** The identifier property represents any kind of identifier for any kind of Thing, such as ISBNs, GTIN codes, UUIDs etc. Schema.org provides dedicated properties for representing many of these, either as textual strings or as URL (URI) links. See background notes for more details.

**Application:** This is the "bucket" for other IDs like the following:
- Primary Employer Identification Number (EIN): 71-6046242
- Federal/SAM.gov UEI: VDFYLZPJEAV6
- Primary North American Industry Classification System (NAICS) Code: 611310
- Commercial and Government Entity (CAGE) Code: 1QJY4
- Office of Postsecondary Education (OPE) ID Number (formerly known as FICE code): 00110900
- Integrated Postsecondary Education Data System (IPEDS) Number: 106263
- Primary HHS / OHRP Human Subjects FWA Number: 00001119
- Primary PHS/OLAW Assurance Number: A3063-01

## image

https://schema.org/image

**Property Description:** An image of the item. This can be a URL or a fully described ImageObject.

**Application:** Visual assets for the Knowledge Panel.

## iso6523Code

https://schema.org/iso6523Code

**Property Description:** An organization identifier as defined in ISO 6523(-1).

**Application:** TBD

## keywords

https://schema.org/keywords

**Property Description:** Keywords or tags used to describe some item. Multiple textual entries in a keywords list are typically delimited by commas, or by repeating the property.

**Application:** Use these to list clinical and research expertise topics.

## knowsAbout

https://schema.org/knowsAbout

**Property Description:** Of a Person, and less typically of an Organization, to indicate a topic that is known about — suggesting possible expertise but not implying it. We do not distinguish skill levels here, or relate this to educational content, events, objectives or JobPosting descriptions.

**Application:** Use these to list clinical and research expertise topics.

## knowsLanguage

https://schema.org/knowsLanguage

**Property Description:** Of a Person, and less typically of an Organization, to indicate a known language. We do not distinguish skill levels or reading/writing/speaking/signing here. Use language codes from the IETF BCP 47 standard.

**Application:** To indicate that UAMS offers services in English, Spanish, and Marshallese.

## logo

https://schema.org/logo

**Property Description:** An associated logo.

**Application:** Visual assets for the Knowledge Panel.

## makesOffer

https://schema.org/makesOffer

**Property Description:** A pointer to products or services offered by the organization or person.

**Application:** Use this to list "educational services" (degree programs) or "medical services" (specialized clinics).

## medicalSpecialty

https://schema.org/medicalSpecialty

**Property Description:** A medical specialty of the provider.

**Application:** List UAMS’s key areas of expertise (e.g., Oncology, Geriatrics, Myeloma).

## memberOf

https://schema.org/memberOf

**Property Description:** An Organization (or ProgramMembership) to which this Person or Organization belongs.

**Application:** TBD

## ownershipFundingInfo

https://schema.org/ownershipFundingInfo

**Property Description:** For an Organization (often but not necessarily a NewsMediaOrganization), a description of organizational ownership structure; funding and grants. In a news/media setting, this is with particular reference to editorial independence. Note that the funder is also available and can be used to make basic funder information machine-readable.

**Application:** TBD

## owns

https://schema.org/owns

**Property Description:** Things owned by the organization or person.

**Application:** Can be used to list physical properties or patented medical technologies developed by UAMS research.

## photo

https://schema.org/photo

**Property Description:** A photograph of this place.

**Application:** Visual assets for the Knowledge Panel.

## potentialAction

https://schema.org/potentialAction

**Property Description:** Indicates a potential Action, which describes an idealized action in which this thing would play an 'object' role.

**Application:** Can be used to prompt users to "Schedule an Appointment" or "Apply for Admission."

## skills

https://schema.org/skills

**Property Description:** A statement of knowledge, skill, ability, task or any other assertion expressing a competency that is either claimed by a person, an organization or desired or required to fulfill a role or to work in an occupation.

**Application:** Use these to list clinical and research expertise topics.

## sponsor

https://schema.org/sponsor

**Property Description:** A person or organization that supports a thing through a pledge, promise, or financial contribution. E.g. a sponsor of a Medical Study or a corporate sponsor of an event.

**Application:** TBD

## subOrganization

https://schema.org/subOrganization

**Property Description:** A relationship between two organizations where the first includes the second, e.g., as a subsidiary. See also: the more specific 'department' property.

**Application:** Use this for semi-autonomous entities like UAMS Health or the Winthrop P. Rockefeller Cancer Institute.

## tourBookingPage

https://schema.org/tourBookingPage

**Property Description:** A page providing information on how to book a tour of some Place, such as an Accommodation or ApartmentComplex in a real estate setting, as well as other kinds of tours as appropriate.

**Application:** If UAMS offers virtual or in-person campus tours for prospective students.
