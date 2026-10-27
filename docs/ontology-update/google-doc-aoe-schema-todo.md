# UAMS Health Find-a-Doc Schema To Do List

# General

* Add an argument to the schema functions that indicates which schema items to generate. This will avoid issues where the node identifiers in one schema item reference a property (or item) fully defined in the other unused schema item.

* Adjust node identifier stuff so that the same schema items aren’t being repeated over and over — just their node identifier

  * All schema functions

    * **MedicalWebPage** item

      * **mainEntity** property

  * **WebSite** schema function

    * **Organization** items

* Add a step to the **uamswp\_fad\_schema\_add\_to\_item\_values** function that checks all nested arrays for node identifiers (**@id**) to add to the **$node\_identifier\_list** array.

* Remove irrelevant metaboxes from taxonomy items (e.g., SEO; \_\_ Archive Settings; Layout Settings)

* Consider shifting to the use of the 'Thing \> CreativeWork \> WebContent \> HealthTopicContent' type in place of 'MedicalWebPage' and/or the 'CreativeWork' subtypes used for clinical resources.

* Review Google’s general structured data guidelines at [https://developers.google.com/search/docs/appearance/structured-data/sd-policies](https://developers.google.com/search/docs/appearance/structured-data/sd-policies)

* Review Google’s **required** structured data properties at [https://developers.google.com/search/docs/appearance/structured-data/local-business\#structured-data-type-definitions](https://developers.google.com/search/docs/appearance/structured-data/local-business#structured-data-type-definitions)

* Review Google’s **recommended** structured data properties at [https://developers.google.com/search/docs/appearance/structured-data/local-business\#structured-data-type-definitions](https://developers.google.com/search/docs/appearance/structured-data/local-business#structured-data-type-definitions)

* Adjust transients to allow for different output values based on nesting level checks

# Functions Needing Transients

* uamswp\_fad\_schema\_nucc\_code\_set\_id

* uamswp\_fad\_clinical\_specialization\_term

* uamswp\_fad\_clinical\_specialization\_provider

# Provider

* Replace all **degree** value definitions with a single consolidated function.

* Fix the provider name in breadcrumbs. It is currently displaying the post title.

* Make a decision on whether 'jobTitle' and/or 'hasOccupation' should include only the selected clinical specialization (e.g., 'Orthopaedic Spine Surgeon') or also its ancestors (e.g., 'Orthopaedic Spine Surgeon' will also display 'Orthopaedic Surgeon' and 'Physician').

* Find a way to validate whether a provider is an optician so the Optician type can be used in place of MedicalBusiness

## New Inputs

* Add a new set of inputs for the name

  * Message instructing editors to fully populate all fields and to not use initials

  * Required inputs:

    * Full first name

    * Full middle name

      * Button group to confirm presence/absence of middle name (default: null)

    * Nickname

      * Button group to confirm presence/absence of nickname (default: null)

    * Full last name

    * Generational suffix

      * Button group to confirm presence/absence of generational suffix (default: null)

    * Name display format selector with a message instructing editors to mirror the external name value defined in Epic  
      (default: Full First \+ Full Middle \+ Full Last \+ Suffix)

      * First name options

        * Include full first name

        * Include the first initial

      * Middle name options

        * Include full middle name

        * Include the middle initial (first instance)

        * Include all middle initials (all instances)

      * Nickname options

        * Include nickname

        * Do not include a nickname

      * Last name option

        * Full last name

      * Generational suffix option

        * Include generational suffix

        * Do not include generational suffix

  * Optional inputs

    * Alternate name repeater (e.g., former names, variant names)

  * Populate a hidden field in the provider data that is populated by all the name variants and is queried by provider search and site search

* Make any necessary adjustments to existing awards inputs on provider profile (e.g., award name, awarding organization, year awarded)

  * Enable proper display on a provider profile.

  * Provide necessary values for relevant schema properties.

  * Add instructions for editors to only include awards relevant to the provider’s clinical practice.

  * Create a section on the provider profile for displaying awards.

## Properties

* **aggregateRating (AggregateRating)**

  * **description**

    * Get description of the rating/review concept from Patient Experience. (FD20-3482) 

  * **reviewAspect**

    * Get info from Patient Experience about which facets of the provider is rated/reviewed. (FD20-3482) 

* **alternateName**

  * Populate values using new alternateName input, once it has been added.

  * Populate variable values using new name component inputs (e.g., full middle name, nickname), once they have been added.

* **alumniOf**

  * Populate additional values using **Education and Training Organization** values from the provider profile once the new data inputs have been added (e.g., alternateName, url, address).

* **areaServed**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **award (value is Text data type)**

  * Define the property value using new fields from the **Recognition Lists** taxonomy.

* **currenciesAccepted**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **duns**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **globalLocationNumber**

  * Determine what the appropriate value should be or should look like. See FD20-3482. 

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **hasCredential**

  * Include education and training (e.g., degrees, residency programs) in 'hasCredential' as 'EducationalOccupationalCredential'.

  * Add/adjust fields in the Provider editor:

    * Indicate the education types: Degree, Residency and Fellowship.

      * Use this indicator to populate the 'credentialCategory' property of the 'EducationalOccupationalCredential' type with a value to represent the Accreditation Council for Graduate Medical Education.

    * When Residency and Fellowship is selected:

      * Distinguish between sponsoring organization and the (optional) clinical site(s) of the program.

      * Indicate whether the program was accredited by the Accreditation Council for Graduate Medical Education.

        * Use this indicator to populate the 'recognizedBy' property of the 'EducationalOccupationalCredential' type with a value to represent the Accreditation Council for Graduate Medical Education.

        * If the program was ACGME-accredited, add a taxonomy selector to indicate the ACGME specialty or subspecialty of the program.

        * If the program was not ACGME-accredited, add a text input to indicate the specialty or subspecialty of the program.

    * When Degree is selected, add a field to indicate the specific degree (e.g., Doctor of Medicine, Master of Science).

    * Find a means of deduplicating the education and training list against the Clinical Degrees and Credentials list.

* **identifier (plus specific properties for duns, globalLocationNumber, usNPIet al.)**

  * Add values for properties with pending questions if they are determined to be relevant. See FD20-3482.

    * duns

    * globalLocationNumber

    * isicV4

    * iso6523Code

    * leiCode

    * naics

    * taxID

    * usNPI

    * vatID

* **isAccessibleForFree**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **isicV4**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **iso6523Code**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **keywords**

  * Add additional values from associated location attributes.

    * City \+ state (e.g., “Little Rock, Arkansas”)?

    * Arkansas region (e.g., central Arkansas)?

    * County? This would need a new input.

* **legalName**

  * Populate value as the provider’s full name using new name component inputs, once they have been added.

* **leiCode**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **makesOffer**

  * Determine what the appropriate value should be or should look like.

  * Design any necessary new inputs.

  * Populate values using new TBD data inputs or framework.

* **naics**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **paymentAccepted**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **potentialAction**

  * Determine what the appropriate value should be or should look like.

  * Design any necessary new inputs.

  * Populate values using new TBD data inputs or framework.

* **taxID**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **review**

  * Determine what the appropriate value should be or should look like.

  * Design any necessary new inputs.

  * Populate values using new TBD data inputs or framework.

* **vatID**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

# Location

* **Bug:** Make sure the descendant locations section displays properly on the location profile.

  * It currently displays the section but does not display any cards.

  * In ‘templates/parts/html/section/list/location.php’, line 130, the $location\_count is being set to 0 through the include for ‘/templates/parts/vars/page/queries/location.php’

* Pass field values to the function through the relevant argument variable.

* Figure out the proper method of conveying the telemedicine service and its typical/modified hours, according to the Google developer documentation or other documentation.

  * **department** property ([https://developers.google.com/search/docs/appearance/structured-data/local-business\#multiple-departments](https://developers.google.com/search/docs/appearance/structured-data/local-business#multiple-departments))

## New Inputs

* ContactPointOption ([https://schema.org/ContactPointOption](https://schema.org/ContactPointOption))

  * Add checkbox field to common schema field group for ContactPointOption

    * Add two options: ‘HearingImpairedSupported’, ‘TollFree’

  * Add required clone fields alongside every phone number field in the location editor (and anywhere else necessary).

  * Add the values to the contactOption property wherever the ContactPoint schema function is called.

  * If the value includes ‘TollFree’, change the additionalType value to ‘https://www.wikidata.org/wiki/Q348308’ (Wikidata entry for toll-free telephone number).

* ContactPoint language

  * Add Language taxonomy checkbox field to common schema field group.

  * Add required clone fields alongside every phone number field in the location editor (and anywhere else necessary).

  * Add the values to the availableLanguage property wherever the ContactPoint schema function is called.

## Properties

* **acceptedPaymentMethod**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **aggregateRating**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **areaServed**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **award**

  * Create a taxonomy similar to provider's 'Recognition List', but for locations.

  * Add necessary new inputs to location profile for the awards taxonomy.

  * Add a section to the location profile / subsection to display said information

  * Add the appropriate value to the schema.

* **contactPoint**

  * **contactPoint (email)**

    * Determine what the appropriate value should be or should look like. See FD20-3482.

    * Design any necessary new inputs.

    * Add the appropriate value to the schema.

  * **ContactPoint \> hoursAvailable**

    * Determine if the various contact points should have the same hoursAvailable (OpeningHoursSpecification) as the location itself.

* **currenciesAccepted**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **diversityPolicy**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **diversityStaffingReport**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **duns**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **ethicsPolicy**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **event**

  * Find means of populating schema values from relevant LiveWhale calendar events.

* **foundingDate**

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **funding**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **globalLocationNumber**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* [**hasCertification**](https://schema.org/hasCertification)

  * Determine what the appropriate value should be or should look like.

  * Determine overlap with **hasCredential** (if any).

  * Design any necessary new inputs.

  * Populate values using new TBD data inputs or framework.

* **hasCredential**

  * Determine what the appropriate value should be or should look like.

  * Determine overlap with **hasCertification** (if any).

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **hasDriveThroughService**

  * Determine what the appropriate value should be or should look like.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **identifier**

  * Add values for properties with pending questions if they are determined to be relevant. See FD20-3482.

    * duns

    * globalLocationNumber

    * isicV4

    * iso6523Code

    * leiCode

    * naics

    * taxID

    * usNPI

    * vatID

* **image**

  * **Bug:** De-duplicate the values. Currently, the array is being populated with the expected items plus node references to those same items.

* **isAcceptingNewPatients**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **isAccessibleForFree**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **isicV4**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **iso6523Code**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **knowsLanguage**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **legalName**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **leiCode**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **logo**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **makesOffer**

  * Determine what the appropriate value should be or should look like.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **maximumAttendeeCapacity**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **memberOf**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **naics**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **nonprofitStatus**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **numberOfEmployees**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **paymentAccepted**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **potentialAction**

  * Determine what the appropriate value should be or should look like.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **publicAccess**

  * Determine what the appropriate value should be or should look like.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **taxID**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **review**

  * Determine what the appropriate value should be or should look like.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **vatID**

  * Determine what the appropriate value should be or should look like. See FD20-3482.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

# Area of Expertise

* Apply the areas of expertise schema function to the single area of expertise templates

* Pass field values to the function through the relevant argument variable.

* Add system fallback images to the schema if the relevant featured image is blank

* Add system fallback text to the schema if the relevant text is blank

* Fix the ACF function (**expertise\_save\_post\_before** in **class.fad-acf-functions.php**) so that it properly gets the brand organization values from the associated locations and adds it to the area of expertise’s hidden brand organization field.

  * The provider version (location-to-provider) works properly, but not the expertise version (location-to-expertise).

## New Inputs

* 

## Properties

* **code and/or identifier**

  * Check for standard URL structures that use the ID/value; suppress the “URI or URL of the Code Description or Detail Page (url \> URL)” field based on the section in the “Coding System (codingSystem \> Text)” field if so; construct the URL value from that structure.

    * Check **‘code\_url\_pattern’** value from the **$MedicalCode\_values** array in the **uamswp\_fad\_schema\_code** function

* **citation**

  * Determine what the appropriate value should be or should look like. See notes on “study.”

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **contentLocation**

  * Determine what the appropriate value should be or should look like.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **disambiguatingDescription**

  * Determine what the appropriate value should be or should look like.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **encodingFormat**

  * Determine what the appropriate value should be or should look like.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **guideline**

  * Determine what the appropriate value should be or should look like.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **hasPart (MedicalWebPage only)**

  * Determine what the appropriate value should be or should look like.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **offers**

  * Determine what the appropriate value should be or should look like.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **potentialAction**

  * Determine what the appropriate value should be or should look like.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **primaryImageOfPage**

  * Determine what the appropriate value should be or should look like.

    * Use marketing landing page header background image, if it exists.

    * Type must be ‘ImageObject’

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **recognizingAuthority**

  * Determine what the appropriate value should be or should look like.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **speakable**

  * Identify all of the blocks on the page where there is a heading followed by paragraphs (written using complete sentences) — or just the paragraphs — where the combined text will never exceed 550 characters.

  * Wrap those text elements in an element (e.g., div) that is exclusive to those text elements if there is not already a wrapping element exclusive to those text elements.

  * If the text elements in those blocks have a wrapping element exclusive to them, add a unique \[id\] element to that wrapping element if it doesn't already have one.

  * Add the appropriate value to the schema.

    * Use the ‘SpeakableSpecification’ type.

    * Use the ‘cssSelector’ property with the relevant HTML ID value as the schema property value.

* **study**

  * Decide if a list of relevant studies should be associated with this post type and displayed on the page / in the subsection. See notes on “citation.”

  * Design any necessary new inputs for the profile.

  * Display the values on the front end.

  * Add the appropriate value to the schema.

* **text**

  * Determine if a text value can be generated when ACF / Gutenberg blocks are in use.

  * Add the appropriate value to the schema.

* **timeRequired**

  * Determine if word count can be determined when ACF / Gutenberg blocks are in use.

  * Add the appropriate value to the schema.

* **video**

  * Determine if featured video input is going to continue to be used on these templates.

  * Add the appropriate value to the schema.

## Fake Subpage Template

* Adjust the areas of expertise schema function (or create a new one) to support the difference in information on the area of expertise fake subpages.

  * Suppress properties not relevant to the current subpage (i.e., locations on the provider subpage)

* Add system fallback images to the schema if the relevant featured image is blank

* Add system fallback text to the schema if the relevant text is blank

# Clinical Resource

* Clinical Resource schema array function is currently disabled in includes\\find-a-doc.php until time can be spent getting it ready.

* Add field for indicating when UAMS is not the creator of the CreativeWork asset.

  * If the clinical resource is indicated to be syndicated, then just suppress this new field.

  * Merge into the schema function alongside (or modifying) the “Define the syndication source organization” section

* Add separate ‘CreativeWork’ schema items for each main asset (e.g., document, image, video) for when the clinical resource has more than one main asset.

  * Massive work in progress.

  * Define array listing each main asset ($clinical\_resource\_asset).

    * The key for each top-level item is either the WordPress asset ID or the video URL

    * The value of the top-level item is an associative array with each item being a schema property value or another useful value for that asset

  * Add an item to the $clinical\_resource\_item\_CreativeWork array for each asset.

    * The key for each top-level item is either the WordPress asset ID or the video URL

  * Change how uamswp\_fad\_schema\_add\_to\_item\_values() is called in relation to the CreativeWork schema item under the section for each schema property.

    * If the schema property value is asset-agnostic (meaning it is the same for all assets), then loop through the $clinical\_resource\_item\_CreativeWork array, calling uamswp\_fad\_schema\_add\_to\_item\_values() and adding the property to each item

    * If the schema property value is asset-specific (meaning it is different for each asset), then loop through the $clinical\_resource\_asset array, getting the specific property value, then calling uamswp\_fad\_schema\_add\_to\_item\_values() and adding the property to each item.

  * I have replaced all of the uamswp\_fad\_schema\_add\_to\_item\_values() instances for CreativeWork and have replaced them with the asset-agnostic method.

  * I am now going through all of the uamswp\_fad\_schema\_add\_to\_item\_values() instances for CreativeWork and updating them if they should instead use the asset-specific method. I have gotten through 'contentSize'.

  * I will also need to adjust 'url' and '@type' so that they can be added to each item in the $clinical\_resource\_item\_CreativeWork array. I will likely need to just define the relevant variables before the "Main media asset(s) info (common)" section, then actually add them to the items in the item in the $clinical\_resource\_item\_CreativeWork array after the "Main media asset(s) info (common)" section.

* Pass field values to the function through the relevant argument variable.

* Separate resource types (e.g., ImageObject) into separate functions, calling them in the clinical resource schema function

  * Apply VideoObject schema function to video property value within Provider schema function

## New Inputs

* 

## Properties

* **bitrate (CreativeWork — video)**

  * Determine whether the bitrate can be retrieved from YouTube and/or Vimeo.

  * Add the appropriate value to the schema.

* **duration (CreativeWork — video — Vimeo source)**

  * Get info for video from Vimeo API and use it to set value of **$clinical\_resource\_asset\_duration**.

* **headline (CreativeWork — article type resource)**

  * Define the value for the 'Article' type clinical resource CreativeWork by using the post title.

  * Define for the 'Video' type clinical resources by using the video title from the video platform (e.g., the name of the video from the YouTube API).

  * Define for the 'Document' type clinical resources by using the media asset title from within WordPress.

  * Define for the 'Infographic' type clinical resource by using the media asset title from within WordPress.

* **inLanguage (CreativeWork)**

  * Add input to clinical resources to indicate the language of the content. Use the same language taxonomy that is used for providers.

  * Add a facet to the clinical resource archive for the language of the content.

  * If there is a value (or if the value is not English), override the 'inLanguage' schema property value for the CreativeWork item.

* **isBasedOn (CreativeWork)**

  * Add input to clinical resources to indicate a resource from which each clinical resource's content is derived or from which it is a modification or adaptation.

  * If there is a value, set the schema property value for the CreativeWork item.

* **isPartOf (CreativeWork)**

  * Define the value for the CreativeWork using the MedicalWebPage item.

* **keywords**

  * Add values in the same manner they were added for provider schema.

* **mentions (MedicalWebPage)**

  * Add values in the same manner they were added for provider schema.

* **offers**

  * Determine what the appropriate value should be or should look like.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **potentialAction**

  * Determine what the appropriate value should be or should look like.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **primaryImageOfPage (CreativeWork — infographic — image asset)**

  * Define for the 'Infographic' type clinical resources by using the infographic asset.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **relatedLink**

  * Add values in the same manner they were added for provider schema.

* **significantLink**

  * Add values in the same manner they were added for provider schema.

* **specialty**

  * Add values in the same manner they were added for provider schema.

* **text**

  * Determine what the appropriate value should be or should look like.

  * Design any necessary new inputs.

  * Add the appropriate value to the schema.

* **thumbnailUrl**

  * Check if fallback value from the ACF input is needed for each clinical resource type if the post thumbnail does not exist.

  * Add the appropriate value to the schema.

* **uploadDate (CreativeWork — infographic & document)**

  * Use get\_post\_time( 'c', false, $post ), where $post is the ID of the clinical resource's media asset (i.e., infographic image, digital document file).

* **thumbnail**

  * Get info for video from Vimeo API

* **video (MedicalWebPage)**

  * If the clinical resource is the 'Video' type, set the property value using that clinical resource's VideoObject schema item.

* **videoQuality**

  * Get more info for video from YouTube API through YouTube Lyte plugin

  * Get info for video from Vimeo API

* **videoFrameSize**

  * Get more info for video from YouTube API through YouTube Lyte plugin

  * Get info for video from Vimeo API

# Conditions

## Filter Options Available in Inputs

* **Relationship field inputs**

  * Relationship fields to limit to only those with **field\_condition\_schema\_subtype (field\_schema\_medicalcondition\_subtype)** equal to **MedicalSignOrSymptom**; also exclude the current post from the list of fields

    * Signs or Symptoms of this Condition (signOrSymptom \> MedicalSignOrSymptom)  
      **field\_condition\_schema\_signorsymptom**

  * Relationship fields to limit to only those with **field\_treatment\_procedure\_schema\_therapeuticprocedure\_subtype (field\_schema\_therapeuticprocedure\_subtype)** equal to **MedicalTherapy**

    * Primary Prevention Therapies (primaryPrevention \> MedicalTherapy)  
      **field\_condition\_schema\_primaryprevention**

    * Secondary Prevention Therapies (secondaryPrevention \> MedicalTherapy)  
      **field\_condition\_schema\_secondaryprevention**

    * Possible Treatment(s) (possibleTreatment \> MedicalTherapy)  
      **field\_condition\_schema\_possibletreatment**

  * Relationship fields to limit to only those with **field\_treatment\_procedure\_schema\_medicalprocedure\_subtype (field\_schema\_medicalprocedure\_subtype)** equal to **PhysicalExam**

    * Physical Examination(s) That Can Identify or Detect This Sign (identifyingExam \> PhysicalExam)  
      **field\_condition\_schema\_identifyingexam**

  * Relationship fields to limit to only posts with **field\_treatment\_procedure\_schema\_medicalentity\_subtype (field\_schema\_medicalentity\_subtype\_availableservice)** equal to **MedicalTest**

    * Medical Test(s) That Can Identify or Detect This Sign (identifyingTest \> MedicalTest)  
      **field\_condition\_schema\_identifyingtest**

    * Medical Test(s) That Can Diagnose This Condition (typicalTest \> MedicalTest)  
      **field\_condition\_schema\_typicaltest**

    * Any clones of **field\_schema\_medicaltest**

## New Inputs

* 

## Properties

* **code and/or identifier**

  * Check for standard URL structures that use the ID/value; suppress the “URI or URL of the Code Description or Detail Page (url \> URL)” field based on the section in the “Coding System (codingSystem \> Text)” field if so; construct the URL value from that structure.

    * Check **‘code\_url\_pattern’** value from the **$MedicalCode\_values** array in the **uamswp\_fad\_schema\_code** function

# Treatment

## Filter Options Available in Inputs

* **Relationship field inputs**

  * Relationship fields to limit to only those with **field\_treatment\_procedure\_schema\_therapeuticprocedure\_subtype (field\_schema\_therapeuticprocedure\_subtype)** equal to **MedicalTherapy**; also exclude the current post from the list of fields

    * Duplicate Therapies (duplicateTherapy \> MedicalTherapy)  
      **field\_treatment\_procedure\_schema\_duplicatetherapy**

    * Any clones of **field\_schema\_medicaltherapy**

  * Relationship fields to limit to only those with **field\_condition\_schema\_medicalsignorsymptom\_subtype (field\_schema\_medicalsignorsymptom\_subtype)** equal to **MedicalSign**

    * Medical Sign(s) Identified or Detected by This Physical Exam (signDetected \> MedicalSign)  
      **field\_treatment\_procedure\_schema\_signdetected\_physicalexam**

    * Medical Sign(s) Identified or Detected by This Medical Test (signDetected \> MedicalSign)  
      **field\_treatment\_procedure\_schema\_signdetected\_medicaltest**

## New Inputs

* 

## Properties

* **code and/or identifier**

  * Check for standard URL structures that use the ID/value; suppress the “URI or URL of the Code Description or Detail Page (url \> URL)” field based on the section in the “Coding System (codingSystem \> Text)” field if so; construct the URL value from that structure.

    * Check **‘code\_url\_pattern’** value from the **$MedicalCode\_values** array in the **uamswp\_fad\_schema\_code** function

# Education and Training

## Education and Training Organization taxonomy

* Integrate values from Education and Training Organization taxonomy field group into this schema using terms from that taxonomy

  * Name (name), using the term title as the fallback value

  * Alternate Name (alternateName)

  * Subtype (@type)

  * Additional Type (additionalType)

  * URL (url)

## Non-clinical credentials taxonomy terms

* Modify existing degrees and credentials taxonomy

  * Filter the non-clinical items from being displayed on the front end of the provider profile.

## ACGME-Accredited Specialty and Subspecialty Programs

* Either

  * Create a new taxonomy for ACGME-accredited specialty and subspecialty programs.

  * Modify an existing taxonomy to allow for a list of ACGME-accredited specialty and subspecialty programs

# Building taxonomy

* Hide building-specific fields in the location editor if the Building taxonomy field has a value or is set to ‘None’, getting those values from the Building term instead.

  * Example fields: street address, city, state, ZIP code, map, parking map, parking instructions).

  * Consider adding an input to allow for overriding the building taxonomy term’s values for each/certain fields (e.g., parking instructions).

## Complex Taxonomy Extension to Building Taxonomy

* If the conditional logic of a field can be set based on whether the term has a parent, add one or more radio button (or button group) fields to indicate whether or not the term should inherit certain attributes from the parent term.

  * If the term inherits the values from the parent, consider whether those values should be omitted from the term’s schema.

  * Some relevant fields:

    * Address

    * Entrance GPS

    * Parking information

      * Parking GPS

      * Parking instructions

* Make sure the **Facility** taxonomy is hierarchical.

* Add instructions in a message field to the **Facility** taxonomy field group to indicate that *buildings* should only be nested under *complexes* (and should not be nested underneath other *buildings*).

* Add instructions in a message field to the **Facility** taxonomy field group to indicate that subentities of *buildings* (e.g., *rooms*, *floors*, *suites*) should not be added to this taxonomy.

* Consider limiting the **Facility** taxonomy hierarchy to two levels.

  * The only reason I can think of to not limit this would be for when a campus could contain another campus (e.g., *the main UAMS campus in Little Rock* theoretically contains *the UAMS Medical Center campus*).

* If the **Facility** taxonomy term has a parent, add that value to the schema item for the *building*/*complex* in the **containedInPlace** property.

* When a **Location** profile is associated with a **Facility** taxonomy term, and that term has a parent…

  * Add the parent term name to the front end below or beside the term name.

  * Add the value to the **Location**’s schema item in the **address** and **contactPoint** property values.

* Find a way to prevent selection of a *complex* from the **Facility** taxonomy field in the **Location** editor.

  * If this is not possible, do not display the term name on the front end if/when a *complex* is selected in the **Facility** taxonomy field in the **Location** editor.

# Parking Facility taxonomy

* Add fields to the **Facility** taxonomy to associate the **Parking Facility** term with each Building term.

* When a **Facility** term is associated with a **Location** profile…

  * Populate the parking GPS values from the **Parking Facility** term associated with the **Facility** term.

    * Front end map

    * Front end parking directions link on **Location** profile

    * Front end parking directions link on **Location** card

    * Schema

  * Populate the parking instructions values from the **Parking Facility** term associated with the **Facility** term.

* Add taxonomy field to the **Location** profile editor for **Parking Facility** taxonomy.

  * Hide field if **Facility** taxonomy field has a value or is set to ‘None’

  * Show field if an override toggle allows for an override of the **Parking Facility** term from the **Facility** term.

# Recognition List taxonomy

* Custom field group for Recognition List taxonomy

  * Add fields to the Recognition Lists taxonomy to enable consistent construction of a text value for this property (e.g., list name, publishing organization, year of list).

  * Display these lists with the other awards on a provider profile.

