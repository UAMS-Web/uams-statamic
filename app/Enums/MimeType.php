<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\EnumGeneration\EnumDataUtils;
use Attribute;

/**
 * Attribute for storing MIME type information on enum cases.
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class MimeTypeInfo
{
    /**
     * The MIME type string identifier.
     *
     * @var string The MIME type string (e.g., 'application/pdf')
     */
    public string $mimeType;

    /**
     * Array of file extensions associated with this MIME type.
     *
     * @var array<int,string> Array of file extensions (e.g., ['pdf'])
     */
    public array $extensions;

    /**
     * The MIME type category classification.
     *
     * @var string The MIME type category (e.g., 'application', 'image')
     */
    public string $category;

    /**
     * Initialize a new MimeTypeInfo attribute instance.
     *
     * @param  string  $mimeType  The MIME type string (e.g., 'application/pdf')
     * @param  array<int,string>  $extensions  Array of file extensions (e.g., ['pdf'])
     * @param  string  $category  The MIME type category (e.g., 'application', 'image')
     */
    public function __construct(
        string $mimeType,
        array $extensions = [],
        string $category = '',
    ) {
        $this->mimeType = $mimeType;
        $this->extensions = $extensions;
        $this->category = $category;
    }
}

/**
 * MIME Types enum with comprehensive file type mappings.
 *
 * This enum is intended for use wherever a standardized list of MIME types is
 * required, such as file upload validation, content type headers, or file
 * association lookups.
 *
 * This enum provides two ways to access attribute data:
 *
 *   1. **Getter Methods** (Recommended for common use):
 *      - `mimeType()`, `extensions()`
 *      - These methods use cached data for optimal performance
 *      - Only include the most commonly used attributes
 *      - Add new getter methods whenever an attribute becomes frequently used
 *
 *   2. **Direct Reflection** (For advanced/rare use cases):
 *      - Access any attribute directly via PHP's reflection API
 *      - Example:
 *        `$reflection = new \ReflectionEnumUnitCase(MimeType::class, 'APPLICATION_PDF');`
 *      - Then:
 *        `$info = $reflection->getAttributes(MimeTypeInfo::class)[0]->newInstance();`
 *      - Provides access to ALL attributes
 *
 * **Adding New Getter Methods:**
 *
 * When an attribute becomes commonly used, add a getter method following this
 *  pattern:
 *   ```php
 *   public function attributeName(): string
 *   {
 *       $data = self::mimeTypeData();
 *       return EnumDataUtils::getFieldValue($data, $this->value, 'attributeName', 'mime type');
 *   }
 *   ```
 *
 * Also update the `mimeTypeData()` method to include the new attribute in the
 * returned array.
 *
 * Each case's string value represents the MIME type identifier
 * (e.g., 'APPLICATION_PDF' for 'application/pdf'). The case name is a
 * human-readable identifier for the MIME type.
 *
 * Each case includes:
 *   - mimeType // The MIME type string (e.g., 'application/pdf')
 *   - extensions // Array of file extensions associated with this MIME type
 *     (e.g., ['pdf'])
 *   - category // The MIME type category (e.g., 'application', 'image', 'audio')
 *
 * Data Source:
 *   - mime-db database:
 *     https://github.com/jshttp/mime-db
 *   - IANA Media Types Registry:
 *     https://www.iana.org/assignments/media-types/media-types.xhtml
 *   - Apache HTTP Server MIME Types:
 *     https://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types
 *
 * This list of cases was generated on 2025-08-21 at 20:06:00 UTC
 * Total MIME types: 2564
 */
enum MimeType: string
{
    #[MimeTypeInfo(mimeType: 'application/1d-interleaved-parityfec', extensions: [], category: 'application')]
    case APPLICATION_1D_INTERLEAVED_PARITYFEC = 'APPLICATION_1D_INTERLEAVED_PARITYFEC';

    #[MimeTypeInfo(mimeType: 'application/3gpdash-qoe-report+xml', extensions: [], category: 'application')]
    case APPLICATION_3GPDASH_QOE_REPORT_XML = 'APPLICATION_3GPDASH_QOE_REPORT_XML';

    #[MimeTypeInfo(mimeType: 'application/3gpp-ims+xml', extensions: [], category: 'application')]
    case APPLICATION_3GPP_IMS_XML = 'APPLICATION_3GPP_IMS_XML';

    #[MimeTypeInfo(mimeType: 'application/3gpphal+json', extensions: [], category: 'application')]
    case APPLICATION_3GPPHAL_JSON = 'APPLICATION_3GPPHAL_JSON';

    #[MimeTypeInfo(mimeType: 'application/3gpphalforms+json', extensions: [], category: 'application')]
    case APPLICATION_3GPPHALFORMS_JSON = 'APPLICATION_3GPPHALFORMS_JSON';

    #[MimeTypeInfo(mimeType: 'application/a2l', extensions: ['a2l'], category: 'application')]
    case APPLICATION_A2L = 'APPLICATION_A2L';

    #[MimeTypeInfo(mimeType: 'application/ace+cbor', extensions: [], category: 'application')]
    case APPLICATION_ACE_CBOR = 'APPLICATION_ACE_CBOR';

    #[MimeTypeInfo(mimeType: 'application/ace+json', extensions: [], category: 'application')]
    case APPLICATION_ACE_JSON = 'APPLICATION_ACE_JSON';

    #[MimeTypeInfo(mimeType: 'application/ace-groupcomm+cbor', extensions: [], category: 'application')]
    case APPLICATION_ACE_GROUPCOMM_CBOR = 'APPLICATION_ACE_GROUPCOMM_CBOR';

    #[MimeTypeInfo(mimeType: 'application/ace-trl+cbor', extensions: [], category: 'application')]
    case APPLICATION_ACE_TRL_CBOR = 'APPLICATION_ACE_TRL_CBOR';

    #[MimeTypeInfo(mimeType: 'application/activemessage', extensions: [], category: 'application')]
    case APPLICATION_ACTIVEMESSAGE = 'APPLICATION_ACTIVEMESSAGE';

    #[MimeTypeInfo(mimeType: 'application/activity+json', extensions: [], category: 'application')]
    case APPLICATION_ACTIVITY_JSON = 'APPLICATION_ACTIVITY_JSON';

    #[MimeTypeInfo(mimeType: 'application/aif+cbor', extensions: [], category: 'application')]
    case APPLICATION_AIF_CBOR = 'APPLICATION_AIF_CBOR';

    #[MimeTypeInfo(mimeType: 'application/aif+json', extensions: [], category: 'application')]
    case APPLICATION_AIF_JSON = 'APPLICATION_AIF_JSON';

    #[MimeTypeInfo(mimeType: 'application/alto-cdni+json', extensions: [], category: 'application')]
    case APPLICATION_ALTO_CDNI_JSON = 'APPLICATION_ALTO_CDNI_JSON';

    #[MimeTypeInfo(mimeType: 'application/alto-cdnifilter+json', extensions: [], category: 'application')]
    case APPLICATION_ALTO_CDNIFILTER_JSON = 'APPLICATION_ALTO_CDNIFILTER_JSON';

    #[MimeTypeInfo(mimeType: 'application/alto-costmap+json', extensions: [], category: 'application')]
    case APPLICATION_ALTO_COSTMAP_JSON = 'APPLICATION_ALTO_COSTMAP_JSON';

    #[MimeTypeInfo(mimeType: 'application/alto-costmapfilter+json', extensions: [], category: 'application')]
    case APPLICATION_ALTO_COSTMAPFILTER_JSON = 'APPLICATION_ALTO_COSTMAPFILTER_JSON';

    #[MimeTypeInfo(mimeType: 'application/alto-directory+json', extensions: [], category: 'application')]
    case APPLICATION_ALTO_DIRECTORY_JSON = 'APPLICATION_ALTO_DIRECTORY_JSON';

    #[MimeTypeInfo(mimeType: 'application/alto-endpointcost+json', extensions: [], category: 'application')]
    case APPLICATION_ALTO_ENDPOINTCOST_JSON = 'APPLICATION_ALTO_ENDPOINTCOST_JSON';

    #[MimeTypeInfo(mimeType: 'application/alto-endpointcostparams+json', extensions: [], category: 'application')]
    case APPLICATION_ALTO_ENDPOINTCOSTPARAMS_JSON = 'APPLICATION_ALTO_ENDPOINTCOSTPARAMS_JSON';

    #[MimeTypeInfo(mimeType: 'application/alto-endpointprop+json', extensions: [], category: 'application')]
    case APPLICATION_ALTO_ENDPOINTPROP_JSON = 'APPLICATION_ALTO_ENDPOINTPROP_JSON';

    #[MimeTypeInfo(mimeType: 'application/alto-endpointpropparams+json', extensions: [], category: 'application')]
    case APPLICATION_ALTO_ENDPOINTPROPPARAMS_JSON = 'APPLICATION_ALTO_ENDPOINTPROPPARAMS_JSON';

    #[MimeTypeInfo(mimeType: 'application/alto-error+json', extensions: [], category: 'application')]
    case APPLICATION_ALTO_ERROR_JSON = 'APPLICATION_ALTO_ERROR_JSON';

    #[MimeTypeInfo(mimeType: 'application/alto-networkmap+json', extensions: [], category: 'application')]
    case APPLICATION_ALTO_NETWORKMAP_JSON = 'APPLICATION_ALTO_NETWORKMAP_JSON';

    #[MimeTypeInfo(mimeType: 'application/alto-networkmapfilter+json', extensions: [], category: 'application')]
    case APPLICATION_ALTO_NETWORKMAPFILTER_JSON = 'APPLICATION_ALTO_NETWORKMAPFILTER_JSON';

    #[MimeTypeInfo(mimeType: 'application/alto-propmap+json', extensions: [], category: 'application')]
    case APPLICATION_ALTO_PROPMAP_JSON = 'APPLICATION_ALTO_PROPMAP_JSON';

    #[MimeTypeInfo(mimeType: 'application/alto-propmapparams+json', extensions: [], category: 'application')]
    case APPLICATION_ALTO_PROPMAPPARAMS_JSON = 'APPLICATION_ALTO_PROPMAPPARAMS_JSON';

    #[MimeTypeInfo(mimeType: 'application/alto-tips+json', extensions: [], category: 'application')]
    case APPLICATION_ALTO_TIPS_JSON = 'APPLICATION_ALTO_TIPS_JSON';

    #[MimeTypeInfo(mimeType: 'application/alto-tipsparams+json', extensions: [], category: 'application')]
    case APPLICATION_ALTO_TIPSPARAMS_JSON = 'APPLICATION_ALTO_TIPSPARAMS_JSON';

    #[MimeTypeInfo(mimeType: 'application/alto-updatestreamcontrol+json', extensions: [], category: 'application')]
    case APPLICATION_ALTO_UPDATESTREAMCONTROL_JSON = 'APPLICATION_ALTO_UPDATESTREAMCONTROL_JSON';

    #[MimeTypeInfo(mimeType: 'application/alto-updatestreamparams+json', extensions: [], category: 'application')]
    case APPLICATION_ALTO_UPDATESTREAMPARAMS_JSON = 'APPLICATION_ALTO_UPDATESTREAMPARAMS_JSON';

    #[MimeTypeInfo(mimeType: 'application/aml', extensions: ['aml'], category: 'application')]
    case APPLICATION_AML = 'APPLICATION_AML';

    #[MimeTypeInfo(mimeType: 'application/andrew-inset', extensions: ['ez'], category: 'application')]
    case APPLICATION_ANDREW_INSET = 'APPLICATION_ANDREW_INSET';

    #[MimeTypeInfo(mimeType: 'application/appinstaller', extensions: ['appinstaller'], category: 'application')]
    case APPLICATION_APPINSTALLER = 'APPLICATION_APPINSTALLER';

    #[MimeTypeInfo(mimeType: 'application/applefile', extensions: [], category: 'application')]
    case APPLICATION_APPLEFILE = 'APPLICATION_APPLEFILE';

    #[MimeTypeInfo(mimeType: 'application/applixware', extensions: ['aw'], category: 'application')]
    case APPLICATION_APPLIXWARE = 'APPLICATION_APPLIXWARE';

    #[MimeTypeInfo(mimeType: 'application/appx', extensions: ['appx'], category: 'application')]
    case APPLICATION_APPX = 'APPLICATION_APPX';

    #[MimeTypeInfo(mimeType: 'application/appxbundle', extensions: ['appxbundle'], category: 'application')]
    case APPLICATION_APPXBUNDLE = 'APPLICATION_APPXBUNDLE';

    #[MimeTypeInfo(mimeType: 'application/at+jwt', extensions: [], category: 'application')]
    case APPLICATION_AT_JWT = 'APPLICATION_AT_JWT';

    #[MimeTypeInfo(mimeType: 'application/atf', extensions: ['atf'], category: 'application')]
    case APPLICATION_ATF = 'APPLICATION_ATF';

    #[MimeTypeInfo(mimeType: 'application/atfx', extensions: ['atfx'], category: 'application')]
    case APPLICATION_ATFX = 'APPLICATION_ATFX';

    #[MimeTypeInfo(mimeType: 'application/atom+xml', extensions: ['atom'], category: 'application')]
    case APPLICATION_ATOM_XML = 'APPLICATION_ATOM_XML';

    #[MimeTypeInfo(mimeType: 'application/atomcat+xml', extensions: ['atomcat'], category: 'application')]
    case APPLICATION_ATOMCAT_XML = 'APPLICATION_ATOMCAT_XML';

    #[MimeTypeInfo(mimeType: 'application/atomdeleted+xml', extensions: ['atomdeleted'], category: 'application')]
    case APPLICATION_ATOMDELETED_XML = 'APPLICATION_ATOMDELETED_XML';

    #[MimeTypeInfo(mimeType: 'application/atomicmail', extensions: [], category: 'application')]
    case APPLICATION_ATOMICMAIL = 'APPLICATION_ATOMICMAIL';

    #[MimeTypeInfo(mimeType: 'application/atomsvc+xml', extensions: ['atomsvc'], category: 'application')]
    case APPLICATION_ATOMSVC_XML = 'APPLICATION_ATOMSVC_XML';

    #[MimeTypeInfo(mimeType: 'application/atsc-dwd+xml', extensions: ['dwd'], category: 'application')]
    case APPLICATION_ATSC_DWD_XML = 'APPLICATION_ATSC_DWD_XML';

    #[MimeTypeInfo(mimeType: 'application/atsc-dynamic-event-message', extensions: [], category: 'application')]
    case APPLICATION_ATSC_DYNAMIC_EVENT_MESSAGE = 'APPLICATION_ATSC_DYNAMIC_EVENT_MESSAGE';

    #[MimeTypeInfo(mimeType: 'application/atsc-held+xml', extensions: ['held'], category: 'application')]
    case APPLICATION_ATSC_HELD_XML = 'APPLICATION_ATSC_HELD_XML';

    #[MimeTypeInfo(mimeType: 'application/atsc-rdt+json', extensions: [], category: 'application')]
    case APPLICATION_ATSC_RDT_JSON = 'APPLICATION_ATSC_RDT_JSON';

    #[MimeTypeInfo(mimeType: 'application/atsc-rsat+xml', extensions: ['rsat'], category: 'application')]
    case APPLICATION_ATSC_RSAT_XML = 'APPLICATION_ATSC_RSAT_XML';

    #[MimeTypeInfo(mimeType: 'application/atxml', extensions: ['atxml'], category: 'application')]
    case APPLICATION_ATXML = 'APPLICATION_ATXML';

    #[MimeTypeInfo(mimeType: 'application/auth-policy+xml', extensions: ['apxml'], category: 'application')]
    case APPLICATION_AUTH_POLICY_XML = 'APPLICATION_AUTH_POLICY_XML';

    #[MimeTypeInfo(mimeType: 'application/automationml-aml+xml', extensions: ['aml'], category: 'application')]
    case APPLICATION_AUTOMATIONML_AML_XML = 'APPLICATION_AUTOMATIONML_AML_XML';

    #[MimeTypeInfo(mimeType: 'application/automationml-amlx+zip', extensions: ['amlx'], category: 'application')]
    case APPLICATION_AUTOMATIONML_AMLX_ZIP = 'APPLICATION_AUTOMATIONML_AMLX_ZIP';

    #[MimeTypeInfo(mimeType: 'application/bacnet-xdd+zip', extensions: ['xdd'], category: 'application')]
    case APPLICATION_BACNET_XDD_ZIP = 'APPLICATION_BACNET_XDD_ZIP';

    #[MimeTypeInfo(mimeType: 'application/batch-smtp', extensions: [], category: 'application')]
    case APPLICATION_BATCH_SMTP = 'APPLICATION_BATCH_SMTP';

    #[MimeTypeInfo(mimeType: 'application/bdoc', extensions: ['bdoc'], category: 'application')]
    case APPLICATION_BDOC = 'APPLICATION_BDOC';

    #[MimeTypeInfo(mimeType: 'application/beep+xml', extensions: [], category: 'application')]
    case APPLICATION_BEEP_XML = 'APPLICATION_BEEP_XML';

    #[MimeTypeInfo(mimeType: 'application/bufr', extensions: [], category: 'application')]
    case APPLICATION_BUFR = 'APPLICATION_BUFR';

    #[MimeTypeInfo(mimeType: 'application/c2pa', extensions: ['c2pa'], category: 'application')]
    case APPLICATION_C2PA = 'APPLICATION_C2PA';

    #[MimeTypeInfo(mimeType: 'application/calendar+json', extensions: [], category: 'application')]
    case APPLICATION_CALENDAR_JSON = 'APPLICATION_CALENDAR_JSON';

    #[MimeTypeInfo(mimeType: 'application/calendar+xml', extensions: ['xcs'], category: 'application')]
    case APPLICATION_CALENDAR_XML = 'APPLICATION_CALENDAR_XML';

    #[MimeTypeInfo(mimeType: 'application/call-completion', extensions: [], category: 'application')]
    case APPLICATION_CALL_COMPLETION = 'APPLICATION_CALL_COMPLETION';

    #[MimeTypeInfo(mimeType: 'application/cals-1840', extensions: [], category: 'application')]
    case APPLICATION_CALS_1840 = 'APPLICATION_CALS_1840';

    #[MimeTypeInfo(mimeType: 'application/captive+json', extensions: [], category: 'application')]
    case APPLICATION_CAPTIVE_JSON = 'APPLICATION_CAPTIVE_JSON';

    #[MimeTypeInfo(mimeType: 'application/cbor', extensions: ['cbor'], category: 'application')]
    case APPLICATION_CBOR = 'APPLICATION_CBOR';

    #[MimeTypeInfo(mimeType: 'application/cbor-seq', extensions: [], category: 'application')]
    case APPLICATION_CBOR_SEQ = 'APPLICATION_CBOR_SEQ';

    #[MimeTypeInfo(mimeType: 'application/cccex', extensions: ['c3ex'], category: 'application')]
    case APPLICATION_CCCEX = 'APPLICATION_CCCEX';

    #[MimeTypeInfo(mimeType: 'application/ccmp+xml', extensions: ['ccmp'], category: 'application')]
    case APPLICATION_CCMP_XML = 'APPLICATION_CCMP_XML';

    #[MimeTypeInfo(mimeType: 'application/ccxml+xml', extensions: ['ccxml'], category: 'application')]
    case APPLICATION_CCXML_XML = 'APPLICATION_CCXML_XML';

    #[MimeTypeInfo(mimeType: 'application/cda+xml', extensions: [], category: 'application')]
    case APPLICATION_CDA_XML = 'APPLICATION_CDA_XML';

    #[MimeTypeInfo(mimeType: 'application/cdfx+xml', extensions: ['cdfx'], category: 'application')]
    case APPLICATION_CDFX_XML = 'APPLICATION_CDFX_XML';

    #[MimeTypeInfo(mimeType: 'application/cdmi-capability', extensions: ['cdmia'], category: 'application')]
    case APPLICATION_CDMI_CAPABILITY = 'APPLICATION_CDMI_CAPABILITY';

    #[MimeTypeInfo(mimeType: 'application/cdmi-container', extensions: ['cdmic'], category: 'application')]
    case APPLICATION_CDMI_CONTAINER = 'APPLICATION_CDMI_CONTAINER';

    #[MimeTypeInfo(mimeType: 'application/cdmi-domain', extensions: ['cdmid'], category: 'application')]
    case APPLICATION_CDMI_DOMAIN = 'APPLICATION_CDMI_DOMAIN';

    #[MimeTypeInfo(mimeType: 'application/cdmi-object', extensions: ['cdmio'], category: 'application')]
    case APPLICATION_CDMI_OBJECT = 'APPLICATION_CDMI_OBJECT';

    #[MimeTypeInfo(mimeType: 'application/cdmi-queue', extensions: ['cdmiq'], category: 'application')]
    case APPLICATION_CDMI_QUEUE = 'APPLICATION_CDMI_QUEUE';

    #[MimeTypeInfo(mimeType: 'application/cdni', extensions: [], category: 'application')]
    case APPLICATION_CDNI = 'APPLICATION_CDNI';

    #[MimeTypeInfo(mimeType: 'application/ce+cbor', extensions: [], category: 'application')]
    case APPLICATION_CE_CBOR = 'APPLICATION_CE_CBOR';

    #[MimeTypeInfo(mimeType: 'application/cea', extensions: ['cea'], category: 'application')]
    case APPLICATION_CEA = 'APPLICATION_CEA';

    #[MimeTypeInfo(mimeType: 'application/cea-2018+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_CEA_2018_XML = 'APPLICATION_CEA_2018_XML';

    #[MimeTypeInfo(mimeType: 'application/cellml+xml', extensions: ['cellml'], category: 'application')]
    case APPLICATION_CELLML_XML = 'APPLICATION_CELLML_XML';

    #[MimeTypeInfo(mimeType: 'application/cfw', extensions: [], category: 'application')]
    case APPLICATION_CFW = 'APPLICATION_CFW';

    #[MimeTypeInfo(mimeType: 'application/cid', extensions: [], category: 'application')]
    case APPLICATION_CID = 'APPLICATION_CID';

    #[MimeTypeInfo(mimeType: 'application/cid-edhoc+cbor-seq', extensions: [], category: 'application')]
    case APPLICATION_CID_EDHOC_CBOR_SEQ = 'APPLICATION_CID_EDHOC_CBOR_SEQ';

    #[MimeTypeInfo(mimeType: 'application/city+json', extensions: ['json'], category: 'application')]
    case APPLICATION_CITY_JSON = 'APPLICATION_CITY_JSON';

    #[MimeTypeInfo(mimeType: 'application/city+json-seq', extensions: [], category: 'application')]
    case APPLICATION_CITY_JSON_SEQ = 'APPLICATION_CITY_JSON_SEQ';

    #[MimeTypeInfo(mimeType: 'application/clr', extensions: ['1clr'], category: 'application')]
    case APPLICATION_CLR = 'APPLICATION_CLR';

    #[MimeTypeInfo(mimeType: 'application/clue+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_CLUE_XML = 'APPLICATION_CLUE_XML';

    #[MimeTypeInfo(mimeType: 'application/clue_info+xml', extensions: ['clue'], category: 'application')]
    case APPLICATION_CLUE_INFO_XML = 'APPLICATION_CLUE_INFO_XML';

    #[MimeTypeInfo(mimeType: 'application/cms', extensions: ['cmsc'], category: 'application')]
    case APPLICATION_CMS = 'APPLICATION_CMS';

    #[MimeTypeInfo(mimeType: 'application/cnrp+xml', extensions: [], category: 'application')]
    case APPLICATION_CNRP_XML = 'APPLICATION_CNRP_XML';

    #[MimeTypeInfo(mimeType: 'application/coap-eap', extensions: [], category: 'application')]
    case APPLICATION_COAP_EAP = 'APPLICATION_COAP_EAP';

    #[MimeTypeInfo(mimeType: 'application/coap-group+json', extensions: ['json'], category: 'application')]
    case APPLICATION_COAP_GROUP_JSON = 'APPLICATION_COAP_GROUP_JSON';

    #[MimeTypeInfo(mimeType: 'application/coap-payload', extensions: [], category: 'application')]
    case APPLICATION_COAP_PAYLOAD = 'APPLICATION_COAP_PAYLOAD';

    #[MimeTypeInfo(mimeType: 'application/commonground', extensions: [], category: 'application')]
    case APPLICATION_COMMONGROUND = 'APPLICATION_COMMONGROUND';

    #[MimeTypeInfo(mimeType: 'application/concise-problem-details+cbor', extensions: [], category: 'application')]
    case APPLICATION_CONCISE_PROBLEM_DETAILS_CBOR = 'APPLICATION_CONCISE_PROBLEM_DETAILS_CBOR';

    #[MimeTypeInfo(mimeType: 'application/conference-info+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_CONFERENCE_INFO_XML = 'APPLICATION_CONFERENCE_INFO_XML';

    #[MimeTypeInfo(mimeType: 'application/cose', extensions: ['cbor'], category: 'application')]
    case APPLICATION_COSE = 'APPLICATION_COSE';

    #[MimeTypeInfo(mimeType: 'application/cose-key', extensions: ['cbor'], category: 'application')]
    case APPLICATION_COSE_KEY = 'APPLICATION_COSE_KEY';

    #[MimeTypeInfo(mimeType: 'application/cose-key-set', extensions: ['cbor'], category: 'application')]
    case APPLICATION_COSE_KEY_SET = 'APPLICATION_COSE_KEY_SET';

    #[MimeTypeInfo(mimeType: 'application/cose-x509', extensions: [], category: 'application')]
    case APPLICATION_COSE_X509 = 'APPLICATION_COSE_X509';

    #[MimeTypeInfo(mimeType: 'application/cpl+xml', extensions: ['cpl', 'xml'], category: 'application')]
    case APPLICATION_CPL_XML = 'APPLICATION_CPL_XML';

    #[MimeTypeInfo(mimeType: 'application/csrattrs', extensions: ['csrattrs'], category: 'application')]
    case APPLICATION_CSRATTRS = 'APPLICATION_CSRATTRS';

    #[MimeTypeInfo(mimeType: 'application/csta+xml', extensions: [], category: 'application')]
    case APPLICATION_CSTA_XML = 'APPLICATION_CSTA_XML';

    #[MimeTypeInfo(mimeType: 'application/cstadata+xml', extensions: [], category: 'application')]
    case APPLICATION_CSTADATA_XML = 'APPLICATION_CSTADATA_XML';

    #[MimeTypeInfo(mimeType: 'application/csvm+json', extensions: ['json'], category: 'application')]
    case APPLICATION_CSVM_JSON = 'APPLICATION_CSVM_JSON';

    #[MimeTypeInfo(mimeType: 'application/cu-seeme', extensions: ['cu'], category: 'application')]
    case APPLICATION_CU_SEEME = 'APPLICATION_CU_SEEME';

    #[MimeTypeInfo(mimeType: 'application/cwl', extensions: ['cwl'], category: 'application')]
    case APPLICATION_CWL = 'APPLICATION_CWL';

    #[MimeTypeInfo(mimeType: 'application/cwl+json', extensions: ['cwl.json'], category: 'application')]
    case APPLICATION_CWL_JSON = 'APPLICATION_CWL_JSON';

    #[MimeTypeInfo(mimeType: 'application/cwl+yaml', extensions: ['cwl.yaml'], category: 'application')]
    case APPLICATION_CWL_YAML = 'APPLICATION_CWL_YAML';

    #[MimeTypeInfo(mimeType: 'application/cwt', extensions: [], category: 'application')]
    case APPLICATION_CWT = 'APPLICATION_CWT';

    #[MimeTypeInfo(mimeType: 'application/cybercash', extensions: [], category: 'application')]
    case APPLICATION_CYBERCASH = 'APPLICATION_CYBERCASH';

    #[MimeTypeInfo(mimeType: 'application/dart', extensions: [], category: 'application')]
    case APPLICATION_DART = 'APPLICATION_DART';

    #[MimeTypeInfo(mimeType: 'application/dash+xml', extensions: ['mpd'], category: 'application')]
    case APPLICATION_DASH_XML = 'APPLICATION_DASH_XML';

    #[MimeTypeInfo(mimeType: 'application/dash-patch+xml', extensions: ['mpp'], category: 'application')]
    case APPLICATION_DASH_PATCH_XML = 'APPLICATION_DASH_PATCH_XML';

    #[MimeTypeInfo(mimeType: 'application/dashdelta', extensions: ['mpdd'], category: 'application')]
    case APPLICATION_DASHDELTA = 'APPLICATION_DASHDELTA';

    #[MimeTypeInfo(mimeType: 'application/davmount+xml', extensions: ['davmount'], category: 'application')]
    case APPLICATION_DAVMOUNT_XML = 'APPLICATION_DAVMOUNT_XML';

    #[MimeTypeInfo(mimeType: 'application/dca-rft', extensions: [], category: 'application')]
    case APPLICATION_DCA_RFT = 'APPLICATION_DCA_RFT';

    #[MimeTypeInfo(mimeType: 'application/dcd', extensions: ['dcd'], category: 'application')]
    case APPLICATION_DCD = 'APPLICATION_DCD';

    #[MimeTypeInfo(mimeType: 'application/dec-dx', extensions: [], category: 'application')]
    case APPLICATION_DEC_DX = 'APPLICATION_DEC_DX';

    #[MimeTypeInfo(mimeType: 'application/dialog-info+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_DIALOG_INFO_XML = 'APPLICATION_DIALOG_INFO_XML';

    #[MimeTypeInfo(mimeType: 'application/dicom', extensions: ['dcm'], category: 'application')]
    case APPLICATION_DICOM = 'APPLICATION_DICOM';

    #[MimeTypeInfo(mimeType: 'application/dicom+json', extensions: ['json'], category: 'application')]
    case APPLICATION_DICOM_JSON = 'APPLICATION_DICOM_JSON';

    #[MimeTypeInfo(mimeType: 'application/dicom+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_DICOM_XML = 'APPLICATION_DICOM_XML';

    #[MimeTypeInfo(mimeType: 'application/did', extensions: ['did'], category: 'application')]
    case APPLICATION_DID = 'APPLICATION_DID';

    #[MimeTypeInfo(mimeType: 'application/dii', extensions: ['dii'], category: 'application')]
    case APPLICATION_DII = 'APPLICATION_DII';

    #[MimeTypeInfo(mimeType: 'application/dit', extensions: ['dit'], category: 'application')]
    case APPLICATION_DIT = 'APPLICATION_DIT';

    #[MimeTypeInfo(mimeType: 'application/dns', extensions: [], category: 'application')]
    case APPLICATION_DNS = 'APPLICATION_DNS';

    #[MimeTypeInfo(mimeType: 'application/dns+json', extensions: ['rfc'], category: 'application')]
    case APPLICATION_DNS_JSON = 'APPLICATION_DNS_JSON';

    #[MimeTypeInfo(mimeType: 'application/dns-message', extensions: [], category: 'application')]
    case APPLICATION_DNS_MESSAGE = 'APPLICATION_DNS_MESSAGE';

    #[MimeTypeInfo(mimeType: 'application/docbook+xml', extensions: ['dbk'], category: 'application')]
    case APPLICATION_DOCBOOK_XML = 'APPLICATION_DOCBOOK_XML';

    #[MimeTypeInfo(mimeType: 'application/dots+cbor', extensions: [], category: 'application')]
    case APPLICATION_DOTS_CBOR = 'APPLICATION_DOTS_CBOR';

    #[MimeTypeInfo(mimeType: 'application/dpop+jwt', extensions: [], category: 'application')]
    case APPLICATION_DPOP_JWT = 'APPLICATION_DPOP_JWT';

    #[MimeTypeInfo(mimeType: 'application/dskpp+xml', extensions: ['xmls'], category: 'application')]
    case APPLICATION_DSKPP_XML = 'APPLICATION_DSKPP_XML';

    #[MimeTypeInfo(mimeType: 'application/dssc+der', extensions: ['dssc'], category: 'application')]
    case APPLICATION_DSSC_DER = 'APPLICATION_DSSC_DER';

    #[MimeTypeInfo(mimeType: 'application/dssc+xml', extensions: ['xdssc'], category: 'application')]
    case APPLICATION_DSSC_XML = 'APPLICATION_DSSC_XML';

    #[MimeTypeInfo(mimeType: 'application/dvcs', extensions: ['dvc'], category: 'application')]
    case APPLICATION_DVCS = 'APPLICATION_DVCS';

    #[MimeTypeInfo(mimeType: 'application/eat+cwt', extensions: [], category: 'application')]
    case APPLICATION_EAT_CWT = 'APPLICATION_EAT_CWT';

    #[MimeTypeInfo(mimeType: 'application/eat+jwt', extensions: [], category: 'application')]
    case APPLICATION_EAT_JWT = 'APPLICATION_EAT_JWT';

    #[MimeTypeInfo(mimeType: 'application/eat-bun+cbor', extensions: [], category: 'application')]
    case APPLICATION_EAT_BUN_CBOR = 'APPLICATION_EAT_BUN_CBOR';

    #[MimeTypeInfo(mimeType: 'application/eat-bun+json', extensions: [], category: 'application')]
    case APPLICATION_EAT_BUN_JSON = 'APPLICATION_EAT_BUN_JSON';

    #[MimeTypeInfo(mimeType: 'application/eat-ucs+cbor', extensions: [], category: 'application')]
    case APPLICATION_EAT_UCS_CBOR = 'APPLICATION_EAT_UCS_CBOR';

    #[MimeTypeInfo(mimeType: 'application/eat-ucs+json', extensions: [], category: 'application')]
    case APPLICATION_EAT_UCS_JSON = 'APPLICATION_EAT_UCS_JSON';

    #[MimeTypeInfo(mimeType: 'application/ecmascript', extensions: ['ecma', 'es'], category: 'application')]
    case APPLICATION_ECMASCRIPT = 'APPLICATION_ECMASCRIPT';

    #[MimeTypeInfo(mimeType: 'application/edhoc+cbor-seq', extensions: [], category: 'application')]
    case APPLICATION_EDHOC_CBOR_SEQ = 'APPLICATION_EDHOC_CBOR_SEQ';

    #[MimeTypeInfo(mimeType: 'application/edi-consent', extensions: [], category: 'application')]
    case APPLICATION_EDI_CONSENT = 'APPLICATION_EDI_CONSENT';

    #[MimeTypeInfo(mimeType: 'application/edi-x12', extensions: [], category: 'application')]
    case APPLICATION_EDI_X12 = 'APPLICATION_EDI_X12';

    #[MimeTypeInfo(mimeType: 'application/edifact', extensions: [], category: 'application')]
    case APPLICATION_EDIFACT = 'APPLICATION_EDIFACT';

    #[MimeTypeInfo(mimeType: 'application/efi', extensions: ['efi'], category: 'application')]
    case APPLICATION_EFI = 'APPLICATION_EFI';

    #[MimeTypeInfo(mimeType: 'application/elm+json', extensions: [], category: 'application')]
    case APPLICATION_ELM_JSON = 'APPLICATION_ELM_JSON';

    #[MimeTypeInfo(mimeType: 'application/elm+xml', extensions: [], category: 'application')]
    case APPLICATION_ELM_XML = 'APPLICATION_ELM_XML';

    #[MimeTypeInfo(mimeType: 'application/emergencycalldata.cap+xml', extensions: [], category: 'application')]
    case APPLICATION_EMERGENCYCALLDATA_CAP_XML = 'APPLICATION_EMERGENCYCALLDATA_CAP_XML';

    #[MimeTypeInfo(mimeType: 'application/emergencycalldata.comment+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_EMERGENCYCALLDATA_COMMENT_XML = 'APPLICATION_EMERGENCYCALLDATA_COMMENT_XML';

    #[MimeTypeInfo(mimeType: 'application/emergencycalldata.control+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_EMERGENCYCALLDATA_CONTROL_XML = 'APPLICATION_EMERGENCYCALLDATA_CONTROL_XML';

    #[MimeTypeInfo(mimeType: 'application/emergencycalldata.deviceinfo+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_EMERGENCYCALLDATA_DEVICEINFO_XML = 'APPLICATION_EMERGENCYCALLDATA_DEVICEINFO_XML';

    #[MimeTypeInfo(mimeType: 'application/emergencycalldata.ecall.msd', extensions: [], category: 'application')]
    case APPLICATION_EMERGENCYCALLDATA_ECALL_MSD = 'APPLICATION_EMERGENCYCALLDATA_ECALL_MSD';

    #[MimeTypeInfo(mimeType: 'application/emergencycalldata.legacyesn+json', extensions: ['json'], category: 'application')]
    case APPLICATION_EMERGENCYCALLDATA_LEGACYESN_JSON = 'APPLICATION_EMERGENCYCALLDATA_LEGACYESN_JSON';

    #[MimeTypeInfo(mimeType: 'application/emergencycalldata.providerinfo+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_EMERGENCYCALLDATA_PROVIDERINFO_XML = 'APPLICATION_EMERGENCYCALLDATA_PROVIDERINFO_XML';

    #[MimeTypeInfo(mimeType: 'application/emergencycalldata.serviceinfo+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_EMERGENCYCALLDATA_SERVICEINFO_XML = 'APPLICATION_EMERGENCYCALLDATA_SERVICEINFO_XML';

    #[MimeTypeInfo(mimeType: 'application/emergencycalldata.subscriberinfo+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_EMERGENCYCALLDATA_SUBSCRIBERINFO_XML = 'APPLICATION_EMERGENCYCALLDATA_SUBSCRIBERINFO_XML';

    #[MimeTypeInfo(mimeType: 'application/emergencycalldata.veds+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_EMERGENCYCALLDATA_VEDS_XML = 'APPLICATION_EMERGENCYCALLDATA_VEDS_XML';

    #[MimeTypeInfo(mimeType: 'application/emma+xml', extensions: ['emma'], category: 'application')]
    case APPLICATION_EMMA_XML = 'APPLICATION_EMMA_XML';

    #[MimeTypeInfo(mimeType: 'application/emotionml+xml', extensions: ['emotionml'], category: 'application')]
    case APPLICATION_EMOTIONML_XML = 'APPLICATION_EMOTIONML_XML';

    #[MimeTypeInfo(mimeType: 'application/encaprtp', extensions: [], category: 'application')]
    case APPLICATION_ENCAPRTP = 'APPLICATION_ENCAPRTP';

    #[MimeTypeInfo(mimeType: 'application/entity-statement+jwt', extensions: [], category: 'application')]
    case APPLICATION_ENTITY_STATEMENT_JWT = 'APPLICATION_ENTITY_STATEMENT_JWT';

    #[MimeTypeInfo(mimeType: 'application/epp+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_EPP_XML = 'APPLICATION_EPP_XML';

    #[MimeTypeInfo(mimeType: 'application/epub+zip', extensions: ['epub', 'ocf'], category: 'application')]
    case APPLICATION_EPUB_ZIP = 'APPLICATION_EPUB_ZIP';

    #[MimeTypeInfo(mimeType: 'application/eshop', extensions: [], category: 'application')]
    case APPLICATION_ESHOP = 'APPLICATION_ESHOP';

    #[MimeTypeInfo(mimeType: 'application/example', extensions: [], category: 'application')]
    case APPLICATION_EXAMPLE = 'APPLICATION_EXAMPLE';

    #[MimeTypeInfo(mimeType: 'application/exi', extensions: ['exi'], category: 'application')]
    case APPLICATION_EXI = 'APPLICATION_EXI';

    #[MimeTypeInfo(mimeType: 'application/expect-ct-report+json', extensions: [], category: 'application')]
    case APPLICATION_EXPECT_CT_REPORT_JSON = 'APPLICATION_EXPECT_CT_REPORT_JSON';

    #[MimeTypeInfo(mimeType: 'application/express', extensions: ['exp'], category: 'application')]
    case APPLICATION_EXPRESS = 'APPLICATION_EXPRESS';

    #[MimeTypeInfo(mimeType: 'application/fastinfoset', extensions: ['finf'], category: 'application')]
    case APPLICATION_FASTINFOSET = 'APPLICATION_FASTINFOSET';

    #[MimeTypeInfo(mimeType: 'application/fastsoap', extensions: [], category: 'application')]
    case APPLICATION_FASTSOAP = 'APPLICATION_FASTSOAP';

    #[MimeTypeInfo(mimeType: 'application/fdf', extensions: ['fdf'], category: 'application')]
    case APPLICATION_FDF = 'APPLICATION_FDF';

    #[MimeTypeInfo(mimeType: 'application/fdt+xml', extensions: ['fdt'], category: 'application')]
    case APPLICATION_FDT_XML = 'APPLICATION_FDT_XML';

    #[MimeTypeInfo(mimeType: 'application/fhir+json', extensions: [], category: 'application')]
    case APPLICATION_FHIR_JSON = 'APPLICATION_FHIR_JSON';

    #[MimeTypeInfo(mimeType: 'application/fhir+xml', extensions: [], category: 'application')]
    case APPLICATION_FHIR_XML = 'APPLICATION_FHIR_XML';

    #[MimeTypeInfo(mimeType: 'application/fido.trusted-apps+json', extensions: [], category: 'application')]
    case APPLICATION_FIDO_TRUSTED_APPS_JSON = 'APPLICATION_FIDO_TRUSTED_APPS_JSON';

    #[MimeTypeInfo(mimeType: 'application/fits', extensions: ['fits'], category: 'application')]
    case APPLICATION_FITS = 'APPLICATION_FITS';

    #[MimeTypeInfo(mimeType: 'application/flexfec', extensions: [], category: 'application')]
    case APPLICATION_FLEXFEC = 'APPLICATION_FLEXFEC';

    #[MimeTypeInfo(mimeType: 'application/font-sfnt', extensions: [], category: 'application')]
    case APPLICATION_FONT_SFNT = 'APPLICATION_FONT_SFNT';

    #[MimeTypeInfo(mimeType: 'application/font-tdpfr', extensions: ['pfr'], category: 'application')]
    case APPLICATION_FONT_TDPFR = 'APPLICATION_FONT_TDPFR';

    #[MimeTypeInfo(mimeType: 'application/font-woff', extensions: [], category: 'application')]
    case APPLICATION_FONT_WOFF = 'APPLICATION_FONT_WOFF';

    #[MimeTypeInfo(mimeType: 'application/framework-attributes+xml', extensions: [], category: 'application')]
    case APPLICATION_FRAMEWORK_ATTRIBUTES_XML = 'APPLICATION_FRAMEWORK_ATTRIBUTES_XML';

    #[MimeTypeInfo(mimeType: 'application/geo+json', extensions: ['json', 'geojson'], category: 'application')]
    case APPLICATION_GEO_JSON = 'APPLICATION_GEO_JSON';

    #[MimeTypeInfo(mimeType: 'application/geo+json-seq', extensions: [], category: 'application')]
    case APPLICATION_GEO_JSON_SEQ = 'APPLICATION_GEO_JSON_SEQ';

    #[MimeTypeInfo(mimeType: 'application/geofeed+csv', extensions: [], category: 'application')]
    case APPLICATION_GEOFEED_CSV = 'APPLICATION_GEOFEED_CSV';

    #[MimeTypeInfo(mimeType: 'application/geopackage+sqlite3', extensions: ['gpkg'], category: 'application')]
    case APPLICATION_GEOPACKAGE_SQLITE3 = 'APPLICATION_GEOPACKAGE_SQLITE3';

    #[MimeTypeInfo(mimeType: 'application/geopose+json', extensions: [], category: 'application')]
    case APPLICATION_GEOPOSE_JSON = 'APPLICATION_GEOPOSE_JSON';

    #[MimeTypeInfo(mimeType: 'application/geoxacml+json', extensions: [], category: 'application')]
    case APPLICATION_GEOXACML_JSON = 'APPLICATION_GEOXACML_JSON';

    #[MimeTypeInfo(mimeType: 'application/geoxacml+xml', extensions: [], category: 'application')]
    case APPLICATION_GEOXACML_XML = 'APPLICATION_GEOXACML_XML';

    #[MimeTypeInfo(mimeType: 'application/gltf-buffer', extensions: ['bin', 'glbin', 'glbuf'], category: 'application')]
    case APPLICATION_GLTF_BUFFER = 'APPLICATION_GLTF_BUFFER';

    #[MimeTypeInfo(mimeType: 'application/gml+xml', extensions: ['gml', 'xml'], category: 'application')]
    case APPLICATION_GML_XML = 'APPLICATION_GML_XML';

    #[MimeTypeInfo(mimeType: 'application/gnap-binding-jws', extensions: [], category: 'application')]
    case APPLICATION_GNAP_BINDING_JWS = 'APPLICATION_GNAP_BINDING_JWS';

    #[MimeTypeInfo(mimeType: 'application/gnap-binding-jwsd', extensions: [], category: 'application')]
    case APPLICATION_GNAP_BINDING_JWSD = 'APPLICATION_GNAP_BINDING_JWSD';

    #[MimeTypeInfo(mimeType: 'application/gnap-binding-rotation-jws', extensions: [], category: 'application')]
    case APPLICATION_GNAP_BINDING_ROTATION_JWS = 'APPLICATION_GNAP_BINDING_ROTATION_JWS';

    #[MimeTypeInfo(mimeType: 'application/gnap-binding-rotation-jwsd', extensions: [], category: 'application')]
    case APPLICATION_GNAP_BINDING_ROTATION_JWSD = 'APPLICATION_GNAP_BINDING_ROTATION_JWSD';

    #[MimeTypeInfo(mimeType: 'application/gpx+xml', extensions: ['gpx'], category: 'application')]
    case APPLICATION_GPX_XML = 'APPLICATION_GPX_XML';

    #[MimeTypeInfo(mimeType: 'application/grib', extensions: [], category: 'application')]
    case APPLICATION_GRIB = 'APPLICATION_GRIB';

    #[MimeTypeInfo(mimeType: 'application/gxf', extensions: ['gxf'], category: 'application')]
    case APPLICATION_GXF = 'APPLICATION_GXF';

    #[MimeTypeInfo(mimeType: 'application/gzip', extensions: ['gz'], category: 'application')]
    case APPLICATION_GZIP = 'APPLICATION_GZIP';

    #[MimeTypeInfo(mimeType: 'application/h224', extensions: [], category: 'application')]
    case APPLICATION_H224 = 'APPLICATION_H224';

    #[MimeTypeInfo(mimeType: 'application/held+xml', extensions: ['heldxml'], category: 'application')]
    case APPLICATION_HELD_XML = 'APPLICATION_HELD_XML';

    #[MimeTypeInfo(mimeType: 'application/hjson', extensions: ['hjson'], category: 'application')]
    case APPLICATION_HJSON = 'APPLICATION_HJSON';

    #[MimeTypeInfo(mimeType: 'application/hl7v2+xml', extensions: [], category: 'application')]
    case APPLICATION_HL7V2_XML = 'APPLICATION_HL7V2_XML';

    #[MimeTypeInfo(mimeType: 'application/http', extensions: [], category: 'application')]
    case APPLICATION_HTTP = 'APPLICATION_HTTP';

    #[MimeTypeInfo(mimeType: 'application/hyperstudio', extensions: ['stk'], category: 'application')]
    case APPLICATION_HYPERSTUDIO = 'APPLICATION_HYPERSTUDIO';

    #[MimeTypeInfo(mimeType: 'application/ibe-key-request+xml', extensions: [], category: 'application')]
    case APPLICATION_IBE_KEY_REQUEST_XML = 'APPLICATION_IBE_KEY_REQUEST_XML';

    #[MimeTypeInfo(mimeType: 'application/ibe-pkg-reply+xml', extensions: [], category: 'application')]
    case APPLICATION_IBE_PKG_REPLY_XML = 'APPLICATION_IBE_PKG_REPLY_XML';

    #[MimeTypeInfo(mimeType: 'application/ibe-pp-data', extensions: [], category: 'application')]
    case APPLICATION_IBE_PP_DATA = 'APPLICATION_IBE_PP_DATA';

    #[MimeTypeInfo(mimeType: 'application/iges', extensions: [], category: 'application')]
    case APPLICATION_IGES = 'APPLICATION_IGES';

    #[MimeTypeInfo(mimeType: 'application/im-iscomposing+xml', extensions: [], category: 'application')]
    case APPLICATION_IM_ISCOMPOSING_XML = 'APPLICATION_IM_ISCOMPOSING_XML';

    #[MimeTypeInfo(mimeType: 'application/index', extensions: [], category: 'application')]
    case APPLICATION_INDEX = 'APPLICATION_INDEX';

    #[MimeTypeInfo(mimeType: 'application/index.cmd', extensions: [], category: 'application')]
    case APPLICATION_INDEX_CMD = 'APPLICATION_INDEX_CMD';

    #[MimeTypeInfo(mimeType: 'application/index.obj', extensions: [], category: 'application')]
    case APPLICATION_INDEX_OBJ = 'APPLICATION_INDEX_OBJ';

    #[MimeTypeInfo(mimeType: 'application/index.response', extensions: [], category: 'application')]
    case APPLICATION_INDEX_RESPONSE = 'APPLICATION_INDEX_RESPONSE';

    #[MimeTypeInfo(mimeType: 'application/index.vnd', extensions: [], category: 'application')]
    case APPLICATION_INDEX_VND = 'APPLICATION_INDEX_VND';

    #[MimeTypeInfo(mimeType: 'application/inkml+xml', extensions: ['ink', 'inkml'], category: 'application')]
    case APPLICATION_INKML_XML = 'APPLICATION_INKML_XML';

    #[MimeTypeInfo(mimeType: 'application/iotp', extensions: [], category: 'application')]
    case APPLICATION_IOTP = 'APPLICATION_IOTP';

    #[MimeTypeInfo(mimeType: 'application/ipfix', extensions: ['ipfix'], category: 'application')]
    case APPLICATION_IPFIX = 'APPLICATION_IPFIX';

    #[MimeTypeInfo(mimeType: 'application/ipp', extensions: [], category: 'application')]
    case APPLICATION_IPP = 'APPLICATION_IPP';

    #[MimeTypeInfo(mimeType: 'application/isup', extensions: [], category: 'application')]
    case APPLICATION_ISUP = 'APPLICATION_ISUP';

    #[MimeTypeInfo(mimeType: 'application/its+xml', extensions: ['its'], category: 'application')]
    case APPLICATION_ITS_XML = 'APPLICATION_ITS_XML';

    #[MimeTypeInfo(mimeType: 'application/java-archive', extensions: ['jar', 'war', 'ear'], category: 'application')]
    case APPLICATION_JAVA_ARCHIVE = 'APPLICATION_JAVA_ARCHIVE';

    #[MimeTypeInfo(mimeType: 'application/java-serialized-object', extensions: ['ser'], category: 'application')]
    case APPLICATION_JAVA_SERIALIZED_OBJECT = 'APPLICATION_JAVA_SERIALIZED_OBJECT';

    #[MimeTypeInfo(mimeType: 'application/java-vm', extensions: ['class'], category: 'application')]
    case APPLICATION_JAVA_VM = 'APPLICATION_JAVA_VM';

    #[MimeTypeInfo(mimeType: 'application/javascript', extensions: ['js'], category: 'application')]
    case APPLICATION_JAVASCRIPT = 'APPLICATION_JAVASCRIPT';

    #[MimeTypeInfo(mimeType: 'application/jf2feed+json', extensions: ['json'], category: 'application')]
    case APPLICATION_JF2FEED_JSON = 'APPLICATION_JF2FEED_JSON';

    #[MimeTypeInfo(mimeType: 'application/jose', extensions: [], category: 'application')]
    case APPLICATION_JOSE = 'APPLICATION_JOSE';

    #[MimeTypeInfo(mimeType: 'application/jose+json', extensions: [], category: 'application')]
    case APPLICATION_JOSE_JSON = 'APPLICATION_JOSE_JSON';

    #[MimeTypeInfo(mimeType: 'application/jrd+json', extensions: ['jrd'], category: 'application')]
    case APPLICATION_JRD_JSON = 'APPLICATION_JRD_JSON';

    #[MimeTypeInfo(mimeType: 'application/jscalendar+json', extensions: [], category: 'application')]
    case APPLICATION_JSCALENDAR_JSON = 'APPLICATION_JSCALENDAR_JSON';

    #[MimeTypeInfo(mimeType: 'application/jscontact+json', extensions: [], category: 'application')]
    case APPLICATION_JSCONTACT_JSON = 'APPLICATION_JSCONTACT_JSON';

    #[MimeTypeInfo(mimeType: 'application/json', extensions: ['json', 'map'], category: 'application')]
    case APPLICATION_JSON = 'APPLICATION_JSON';

    #[MimeTypeInfo(mimeType: 'application/json-patch+json', extensions: ['json-patch'], category: 'application')]
    case APPLICATION_JSON_PATCH_JSON = 'APPLICATION_JSON_PATCH_JSON';

    #[MimeTypeInfo(mimeType: 'application/json-patch-query+json', extensions: [], category: 'application')]
    case APPLICATION_JSON_PATCH_QUERY_JSON = 'APPLICATION_JSON_PATCH_QUERY_JSON';

    #[MimeTypeInfo(mimeType: 'application/json-seq', extensions: [], category: 'application')]
    case APPLICATION_JSON_SEQ = 'APPLICATION_JSON_SEQ';

    #[MimeTypeInfo(mimeType: 'application/json5', extensions: ['json5'], category: 'application')]
    case APPLICATION_JSON5 = 'APPLICATION_JSON5';

    #[MimeTypeInfo(mimeType: 'application/jsonml+json', extensions: ['jsonml'], category: 'application')]
    case APPLICATION_JSONML_JSON = 'APPLICATION_JSONML_JSON';

    #[MimeTypeInfo(mimeType: 'application/jsonpath', extensions: [], category: 'application')]
    case APPLICATION_JSONPATH = 'APPLICATION_JSONPATH';

    #[MimeTypeInfo(mimeType: 'application/jwk+json', extensions: [], category: 'application')]
    case APPLICATION_JWK_JSON = 'APPLICATION_JWK_JSON';

    #[MimeTypeInfo(mimeType: 'application/jwk-set+json', extensions: [], category: 'application')]
    case APPLICATION_JWK_SET_JSON = 'APPLICATION_JWK_SET_JSON';

    #[MimeTypeInfo(mimeType: 'application/jwk-set+jwt', extensions: [], category: 'application')]
    case APPLICATION_JWK_SET_JWT = 'APPLICATION_JWK_SET_JWT';

    #[MimeTypeInfo(mimeType: 'application/jwt', extensions: [], category: 'application')]
    case APPLICATION_JWT = 'APPLICATION_JWT';

    #[MimeTypeInfo(mimeType: 'application/kb+jwt', extensions: [], category: 'application')]
    case APPLICATION_KB_JWT = 'APPLICATION_KB_JWT';

    #[MimeTypeInfo(mimeType: 'application/kpml-request+xml', extensions: [], category: 'application')]
    case APPLICATION_KPML_REQUEST_XML = 'APPLICATION_KPML_REQUEST_XML';

    #[MimeTypeInfo(mimeType: 'application/kpml-response+xml', extensions: [], category: 'application')]
    case APPLICATION_KPML_RESPONSE_XML = 'APPLICATION_KPML_RESPONSE_XML';

    #[MimeTypeInfo(mimeType: 'application/ld+json', extensions: ['jsonld'], category: 'application')]
    case APPLICATION_LD_JSON = 'APPLICATION_LD_JSON';

    #[MimeTypeInfo(mimeType: 'application/lgr+xml', extensions: ['lgr'], category: 'application')]
    case APPLICATION_LGR_XML = 'APPLICATION_LGR_XML';

    #[MimeTypeInfo(mimeType: 'application/link-format', extensions: ['wlnk'], category: 'application')]
    case APPLICATION_LINK_FORMAT = 'APPLICATION_LINK_FORMAT';

    #[MimeTypeInfo(mimeType: 'application/linkset', extensions: [], category: 'application')]
    case APPLICATION_LINKSET = 'APPLICATION_LINKSET';

    #[MimeTypeInfo(mimeType: 'application/linkset+json', extensions: ['json'], category: 'application')]
    case APPLICATION_LINKSET_JSON = 'APPLICATION_LINKSET_JSON';

    #[MimeTypeInfo(mimeType: 'application/load-control+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_LOAD_CONTROL_XML = 'APPLICATION_LOAD_CONTROL_XML';

    #[MimeTypeInfo(mimeType: 'application/logout+jwt', extensions: [], category: 'application')]
    case APPLICATION_LOGOUT_JWT = 'APPLICATION_LOGOUT_JWT';

    #[MimeTypeInfo(mimeType: 'application/lost+xml', extensions: ['lostxml'], category: 'application')]
    case APPLICATION_LOST_XML = 'APPLICATION_LOST_XML';

    #[MimeTypeInfo(mimeType: 'application/lostsync+xml', extensions: ['lostsyncxml'], category: 'application')]
    case APPLICATION_LOSTSYNC_XML = 'APPLICATION_LOSTSYNC_XML';

    #[MimeTypeInfo(mimeType: 'application/lpf+zip', extensions: ['lpf'], category: 'application')]
    case APPLICATION_LPF_ZIP = 'APPLICATION_LPF_ZIP';

    #[MimeTypeInfo(mimeType: 'application/lxf', extensions: ['lxf'], category: 'application')]
    case APPLICATION_LXF = 'APPLICATION_LXF';

    #[MimeTypeInfo(mimeType: 'application/mac-binhex40', extensions: ['hqx'], category: 'application')]
    case APPLICATION_MAC_BINHEX40 = 'APPLICATION_MAC_BINHEX40';

    #[MimeTypeInfo(mimeType: 'application/mac-compactpro', extensions: ['cpt'], category: 'application')]
    case APPLICATION_MAC_COMPACTPRO = 'APPLICATION_MAC_COMPACTPRO';

    #[MimeTypeInfo(mimeType: 'application/macwriteii', extensions: [], category: 'application')]
    case APPLICATION_MACWRITEII = 'APPLICATION_MACWRITEII';

    #[MimeTypeInfo(mimeType: 'application/mads+xml', extensions: ['mads'], category: 'application')]
    case APPLICATION_MADS_XML = 'APPLICATION_MADS_XML';

    #[MimeTypeInfo(mimeType: 'application/manifest+json', extensions: ['webmanifest'], category: 'application')]
    case APPLICATION_MANIFEST_JSON = 'APPLICATION_MANIFEST_JSON';

    #[MimeTypeInfo(mimeType: 'application/marc', extensions: ['mrc'], category: 'application')]
    case APPLICATION_MARC = 'APPLICATION_MARC';

    #[MimeTypeInfo(mimeType: 'application/marcxml+xml', extensions: ['mrcx'], category: 'application')]
    case APPLICATION_MARCXML_XML = 'APPLICATION_MARCXML_XML';

    #[MimeTypeInfo(mimeType: 'application/mathematica', extensions: ['ma', 'nb', 'mb'], category: 'application')]
    case APPLICATION_MATHEMATICA = 'APPLICATION_MATHEMATICA';

    #[MimeTypeInfo(mimeType: 'application/mathml+xml', extensions: ['mathml'], category: 'application')]
    case APPLICATION_MATHML_XML = 'APPLICATION_MATHML_XML';

    #[MimeTypeInfo(mimeType: 'application/mathml-content+xml', extensions: [], category: 'application')]
    case APPLICATION_MATHML_CONTENT_XML = 'APPLICATION_MATHML_CONTENT_XML';

    #[MimeTypeInfo(mimeType: 'application/mathml-presentation+xml', extensions: [], category: 'application')]
    case APPLICATION_MATHML_PRESENTATION_XML = 'APPLICATION_MATHML_PRESENTATION_XML';

    #[MimeTypeInfo(mimeType: 'application/mbms-associated-procedure-description+xml', extensions: [], category: 'application')]
    case APPLICATION_MBMS_ASSOCIATED_PROCEDURE_DESCRIPTION_XML = 'APPLICATION_MBMS_ASSOCIATED_PROCEDURE_DESCRIPTION_XML';

    #[MimeTypeInfo(mimeType: 'application/mbms-deregister+xml', extensions: [], category: 'application')]
    case APPLICATION_MBMS_DEREGISTER_XML = 'APPLICATION_MBMS_DEREGISTER_XML';

    #[MimeTypeInfo(mimeType: 'application/mbms-envelope+xml', extensions: [], category: 'application')]
    case APPLICATION_MBMS_ENVELOPE_XML = 'APPLICATION_MBMS_ENVELOPE_XML';

    #[MimeTypeInfo(mimeType: 'application/mbms-msk+xml', extensions: [], category: 'application')]
    case APPLICATION_MBMS_MSK_XML = 'APPLICATION_MBMS_MSK_XML';

    #[MimeTypeInfo(mimeType: 'application/mbms-msk-response+xml', extensions: [], category: 'application')]
    case APPLICATION_MBMS_MSK_RESPONSE_XML = 'APPLICATION_MBMS_MSK_RESPONSE_XML';

    #[MimeTypeInfo(mimeType: 'application/mbms-protection-description+xml', extensions: [], category: 'application')]
    case APPLICATION_MBMS_PROTECTION_DESCRIPTION_XML = 'APPLICATION_MBMS_PROTECTION_DESCRIPTION_XML';

    #[MimeTypeInfo(mimeType: 'application/mbms-reception-report+xml', extensions: [], category: 'application')]
    case APPLICATION_MBMS_RECEPTION_REPORT_XML = 'APPLICATION_MBMS_RECEPTION_REPORT_XML';

    #[MimeTypeInfo(mimeType: 'application/mbms-register+xml', extensions: [], category: 'application')]
    case APPLICATION_MBMS_REGISTER_XML = 'APPLICATION_MBMS_REGISTER_XML';

    #[MimeTypeInfo(mimeType: 'application/mbms-register-response+xml', extensions: [], category: 'application')]
    case APPLICATION_MBMS_REGISTER_RESPONSE_XML = 'APPLICATION_MBMS_REGISTER_RESPONSE_XML';

    #[MimeTypeInfo(mimeType: 'application/mbms-schedule+xml', extensions: [], category: 'application')]
    case APPLICATION_MBMS_SCHEDULE_XML = 'APPLICATION_MBMS_SCHEDULE_XML';

    #[MimeTypeInfo(mimeType: 'application/mbms-user-service-description+xml', extensions: [], category: 'application')]
    case APPLICATION_MBMS_USER_SERVICE_DESCRIPTION_XML = 'APPLICATION_MBMS_USER_SERVICE_DESCRIPTION_XML';

    #[MimeTypeInfo(mimeType: 'application/mbox', extensions: ['mbox'], category: 'application')]
    case APPLICATION_MBOX = 'APPLICATION_MBOX';

    #[MimeTypeInfo(mimeType: 'application/media-policy-dataset+xml', extensions: ['mpf', 'xml'], category: 'application')]
    case APPLICATION_MEDIA_POLICY_DATASET_XML = 'APPLICATION_MEDIA_POLICY_DATASET_XML';

    #[MimeTypeInfo(mimeType: 'application/media_control+xml', extensions: [], category: 'application')]
    case APPLICATION_MEDIA_CONTROL_XML = 'APPLICATION_MEDIA_CONTROL_XML';

    #[MimeTypeInfo(mimeType: 'application/mediaservercontrol+xml', extensions: ['mscml'], category: 'application')]
    case APPLICATION_MEDIASERVERCONTROL_XML = 'APPLICATION_MEDIASERVERCONTROL_XML';

    #[MimeTypeInfo(mimeType: 'application/merge-patch+json', extensions: [], category: 'application')]
    case APPLICATION_MERGE_PATCH_JSON = 'APPLICATION_MERGE_PATCH_JSON';

    #[MimeTypeInfo(mimeType: 'application/metalink+xml', extensions: ['metalink'], category: 'application')]
    case APPLICATION_METALINK_XML = 'APPLICATION_METALINK_XML';

    #[MimeTypeInfo(mimeType: 'application/metalink4+xml', extensions: ['meta4'], category: 'application')]
    case APPLICATION_METALINK4_XML = 'APPLICATION_METALINK4_XML';

    #[MimeTypeInfo(mimeType: 'application/mets+xml', extensions: ['mets'], category: 'application')]
    case APPLICATION_METS_XML = 'APPLICATION_METS_XML';

    #[MimeTypeInfo(mimeType: 'application/mf4', extensions: ['mf4'], category: 'application')]
    case APPLICATION_MF4 = 'APPLICATION_MF4';

    #[MimeTypeInfo(mimeType: 'application/mikey', extensions: [], category: 'application')]
    case APPLICATION_MIKEY = 'APPLICATION_MIKEY';

    #[MimeTypeInfo(mimeType: 'application/mipc', extensions: ['h5'], category: 'application')]
    case APPLICATION_MIPC = 'APPLICATION_MIPC';

    #[MimeTypeInfo(mimeType: 'application/missing-blocks+cbor-seq', extensions: [], category: 'application')]
    case APPLICATION_MISSING_BLOCKS_CBOR_SEQ = 'APPLICATION_MISSING_BLOCKS_CBOR_SEQ';

    #[MimeTypeInfo(mimeType: 'application/mmt-aei+xml', extensions: ['maei'], category: 'application')]
    case APPLICATION_MMT_AEI_XML = 'APPLICATION_MMT_AEI_XML';

    #[MimeTypeInfo(mimeType: 'application/mmt-usd+xml', extensions: ['musd'], category: 'application')]
    case APPLICATION_MMT_USD_XML = 'APPLICATION_MMT_USD_XML';

    #[MimeTypeInfo(mimeType: 'application/mods+xml', extensions: ['mods'], category: 'application')]
    case APPLICATION_MODS_XML = 'APPLICATION_MODS_XML';

    #[MimeTypeInfo(mimeType: 'application/moss-keys', extensions: [], category: 'application')]
    case APPLICATION_MOSS_KEYS = 'APPLICATION_MOSS_KEYS';

    #[MimeTypeInfo(mimeType: 'application/moss-signature', extensions: [], category: 'application')]
    case APPLICATION_MOSS_SIGNATURE = 'APPLICATION_MOSS_SIGNATURE';

    #[MimeTypeInfo(mimeType: 'application/mosskey-data', extensions: [], category: 'application')]
    case APPLICATION_MOSSKEY_DATA = 'APPLICATION_MOSSKEY_DATA';

    #[MimeTypeInfo(mimeType: 'application/mosskey-request', extensions: [], category: 'application')]
    case APPLICATION_MOSSKEY_REQUEST = 'APPLICATION_MOSSKEY_REQUEST';

    #[MimeTypeInfo(mimeType: 'application/mp21', extensions: ['m21', 'mp21'], category: 'application')]
    case APPLICATION_MP21 = 'APPLICATION_MP21';

    #[MimeTypeInfo(mimeType: 'application/mp4', extensions: ['mp4', 'mpg4', 'mp4s', 'm4p'], category: 'application')]
    case APPLICATION_MP4 = 'APPLICATION_MP4';

    #[MimeTypeInfo(mimeType: 'application/mpeg4-generic', extensions: [], category: 'application')]
    case APPLICATION_MPEG4_GENERIC = 'APPLICATION_MPEG4_GENERIC';

    #[MimeTypeInfo(mimeType: 'application/mpeg4-iod', extensions: [], category: 'application')]
    case APPLICATION_MPEG4_IOD = 'APPLICATION_MPEG4_IOD';

    #[MimeTypeInfo(mimeType: 'application/mpeg4-iod-xmt', extensions: ['mp4', 'mpg4'], category: 'application')]
    case APPLICATION_MPEG4_IOD_XMT = 'APPLICATION_MPEG4_IOD_XMT';

    #[MimeTypeInfo(mimeType: 'application/mrb-consumer+xml', extensions: ['xdf'], category: 'application')]
    case APPLICATION_MRB_CONSUMER_XML = 'APPLICATION_MRB_CONSUMER_XML';

    #[MimeTypeInfo(mimeType: 'application/mrb-publish+xml', extensions: ['xdf'], category: 'application')]
    case APPLICATION_MRB_PUBLISH_XML = 'APPLICATION_MRB_PUBLISH_XML';

    #[MimeTypeInfo(mimeType: 'application/msc-ivr+xml', extensions: [], category: 'application')]
    case APPLICATION_MSC_IVR_XML = 'APPLICATION_MSC_IVR_XML';

    #[MimeTypeInfo(mimeType: 'application/msc-mixer+xml', extensions: [], category: 'application')]
    case APPLICATION_MSC_MIXER_XML = 'APPLICATION_MSC_MIXER_XML';

    #[MimeTypeInfo(mimeType: 'application/msix', extensions: ['msix'], category: 'application')]
    case APPLICATION_MSIX = 'APPLICATION_MSIX';

    #[MimeTypeInfo(mimeType: 'application/msixbundle', extensions: ['msixbundle'], category: 'application')]
    case APPLICATION_MSIXBUNDLE = 'APPLICATION_MSIXBUNDLE';

    #[MimeTypeInfo(mimeType: 'application/msword', extensions: ['doc', 'dot'], category: 'application')]
    case APPLICATION_MSWORD = 'APPLICATION_MSWORD';

    #[MimeTypeInfo(mimeType: 'application/mud+json', extensions: [], category: 'application')]
    case APPLICATION_MUD_JSON = 'APPLICATION_MUD_JSON';

    #[MimeTypeInfo(mimeType: 'application/multipart-core', extensions: [], category: 'application')]
    case APPLICATION_MULTIPART_CORE = 'APPLICATION_MULTIPART_CORE';

    #[MimeTypeInfo(mimeType: 'application/mxf', extensions: ['mxf'], category: 'application')]
    case APPLICATION_MXF = 'APPLICATION_MXF';

    #[MimeTypeInfo(mimeType: 'application/n-quads', extensions: ['nq'], category: 'application')]
    case APPLICATION_N_QUADS = 'APPLICATION_N_QUADS';

    #[MimeTypeInfo(mimeType: 'application/n-triples', extensions: ['nt'], category: 'application')]
    case APPLICATION_N_TRIPLES = 'APPLICATION_N_TRIPLES';

    #[MimeTypeInfo(mimeType: 'application/nasdata', extensions: [], category: 'application')]
    case APPLICATION_NASDATA = 'APPLICATION_NASDATA';

    #[MimeTypeInfo(mimeType: 'application/news-checkgroups', extensions: [], category: 'application')]
    case APPLICATION_NEWS_CHECKGROUPS = 'APPLICATION_NEWS_CHECKGROUPS';

    #[MimeTypeInfo(mimeType: 'application/news-groupinfo', extensions: [], category: 'application')]
    case APPLICATION_NEWS_GROUPINFO = 'APPLICATION_NEWS_GROUPINFO';

    #[MimeTypeInfo(mimeType: 'application/news-transmission', extensions: [], category: 'application')]
    case APPLICATION_NEWS_TRANSMISSION = 'APPLICATION_NEWS_TRANSMISSION';

    #[MimeTypeInfo(mimeType: 'application/nlsml+xml', extensions: [], category: 'application')]
    case APPLICATION_NLSML_XML = 'APPLICATION_NLSML_XML';

    #[MimeTypeInfo(mimeType: 'application/node', extensions: ['js', 'cjs'], category: 'application')]
    case APPLICATION_NODE = 'APPLICATION_NODE';

    #[MimeTypeInfo(mimeType: 'application/nss', extensions: [], category: 'application')]
    case APPLICATION_NSS = 'APPLICATION_NSS';

    #[MimeTypeInfo(mimeType: 'application/oauth-authz-req+jwt', extensions: [], category: 'application')]
    case APPLICATION_OAUTH_AUTHZ_REQ_JWT = 'APPLICATION_OAUTH_AUTHZ_REQ_JWT';

    #[MimeTypeInfo(mimeType: 'application/oblivious-dns-message', extensions: [], category: 'application')]
    case APPLICATION_OBLIVIOUS_DNS_MESSAGE = 'APPLICATION_OBLIVIOUS_DNS_MESSAGE';

    #[MimeTypeInfo(mimeType: 'application/ocsp-request', extensions: ['orq'], category: 'application')]
    case APPLICATION_OCSP_REQUEST = 'APPLICATION_OCSP_REQUEST';

    #[MimeTypeInfo(mimeType: 'application/ocsp-response', extensions: ['ors'], category: 'application')]
    case APPLICATION_OCSP_RESPONSE = 'APPLICATION_OCSP_RESPONSE';

    #[MimeTypeInfo(mimeType: 'application/octet-stream', extensions: ['bin', 'dms', 'lrf', 'mar', 'so', 'dist', 'distz', 'pkg', 'bpk', 'dump', 'elc', 'deploy', 'exe', 'dll', 'deb', 'dmg', 'iso', 'img', 'msi', 'msp', 'msm', 'buffer'], category: 'application')]
    case APPLICATION_OCTET_STREAM = 'APPLICATION_OCTET_STREAM';

    #[MimeTypeInfo(mimeType: 'application/oda', extensions: ['oda'], category: 'application')]
    case APPLICATION_ODA = 'APPLICATION_ODA';

    #[MimeTypeInfo(mimeType: 'application/odm+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_ODM_XML = 'APPLICATION_ODM_XML';

    #[MimeTypeInfo(mimeType: 'application/odx', extensions: ['odx'], category: 'application')]
    case APPLICATION_ODX = 'APPLICATION_ODX';

    #[MimeTypeInfo(mimeType: 'application/oebps-package+xml', extensions: ['opf'], category: 'application')]
    case APPLICATION_OEBPS_PACKAGE_XML = 'APPLICATION_OEBPS_PACKAGE_XML';

    #[MimeTypeInfo(mimeType: 'application/ogg', extensions: ['ogx'], category: 'application')]
    case APPLICATION_OGG = 'APPLICATION_OGG';

    #[MimeTypeInfo(mimeType: 'application/ohttp-keys', extensions: [], category: 'application')]
    case APPLICATION_OHTTP_KEYS = 'APPLICATION_OHTTP_KEYS';

    #[MimeTypeInfo(mimeType: 'application/omdoc+xml', extensions: ['omdoc'], category: 'application')]
    case APPLICATION_OMDOC_XML = 'APPLICATION_OMDOC_XML';

    #[MimeTypeInfo(mimeType: 'application/onenote', extensions: ['onetoc', 'onetoc2', 'onetmp', 'onepkg', 'one', 'onea'], category: 'application')]
    case APPLICATION_ONENOTE = 'APPLICATION_ONENOTE';

    #[MimeTypeInfo(mimeType: 'application/opc-nodeset+xml', extensions: [], category: 'application')]
    case APPLICATION_OPC_NODESET_XML = 'APPLICATION_OPC_NODESET_XML';

    #[MimeTypeInfo(mimeType: 'application/oscore', extensions: [], category: 'application')]
    case APPLICATION_OSCORE = 'APPLICATION_OSCORE';

    #[MimeTypeInfo(mimeType: 'application/oxps', extensions: ['oxps'], category: 'application')]
    case APPLICATION_OXPS = 'APPLICATION_OXPS';

    #[MimeTypeInfo(mimeType: 'application/p21', extensions: ['p21', 'stp', 'step', 'stpnc', '210', 'ifc'], category: 'application')]
    case APPLICATION_P21 = 'APPLICATION_P21';

    #[MimeTypeInfo(mimeType: 'application/p21+zip', extensions: ['stpz'], category: 'application')]
    case APPLICATION_P21_ZIP = 'APPLICATION_P21_ZIP';

    #[MimeTypeInfo(mimeType: 'application/p2p-overlay+xml', extensions: ['relo'], category: 'application')]
    case APPLICATION_P2P_OVERLAY_XML = 'APPLICATION_P2P_OVERLAY_XML';

    #[MimeTypeInfo(mimeType: 'application/parityfec', extensions: [], category: 'application')]
    case APPLICATION_PARITYFEC = 'APPLICATION_PARITYFEC';

    #[MimeTypeInfo(mimeType: 'application/passport', extensions: [], category: 'application')]
    case APPLICATION_PASSPORT = 'APPLICATION_PASSPORT';

    #[MimeTypeInfo(mimeType: 'application/patch-ops-error+xml', extensions: ['xer'], category: 'application')]
    case APPLICATION_PATCH_OPS_ERROR_XML = 'APPLICATION_PATCH_OPS_ERROR_XML';

    #[MimeTypeInfo(mimeType: 'application/pdf', extensions: ['pdf'], category: 'application')]
    case APPLICATION_PDF = 'APPLICATION_PDF';

    #[MimeTypeInfo(mimeType: 'application/pdx', extensions: ['pdx'], category: 'application')]
    case APPLICATION_PDX = 'APPLICATION_PDX';

    #[MimeTypeInfo(mimeType: 'application/pem-certificate-chain', extensions: ['pem'], category: 'application')]
    case APPLICATION_PEM_CERTIFICATE_CHAIN = 'APPLICATION_PEM_CERTIFICATE_CHAIN';

    #[MimeTypeInfo(mimeType: 'application/pgp-encrypted', extensions: ['pgp'], category: 'application')]
    case APPLICATION_PGP_ENCRYPTED = 'APPLICATION_PGP_ENCRYPTED';

    #[MimeTypeInfo(mimeType: 'application/pgp-keys', extensions: ['asc'], category: 'application')]
    case APPLICATION_PGP_KEYS = 'APPLICATION_PGP_KEYS';

    #[MimeTypeInfo(mimeType: 'application/pgp-signature', extensions: ['asc', 'sig'], category: 'application')]
    case APPLICATION_PGP_SIGNATURE = 'APPLICATION_PGP_SIGNATURE';

    #[MimeTypeInfo(mimeType: 'application/pics-rules', extensions: ['prf'], category: 'application')]
    case APPLICATION_PICS_RULES = 'APPLICATION_PICS_RULES';

    #[MimeTypeInfo(mimeType: 'application/pidf+xml', extensions: [], category: 'application')]
    case APPLICATION_PIDF_XML = 'APPLICATION_PIDF_XML';

    #[MimeTypeInfo(mimeType: 'application/pidf-diff+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_PIDF_DIFF_XML = 'APPLICATION_PIDF_DIFF_XML';

    #[MimeTypeInfo(mimeType: 'application/pkcs10', extensions: ['p10'], category: 'application')]
    case APPLICATION_PKCS10 = 'APPLICATION_PKCS10';

    #[MimeTypeInfo(mimeType: 'application/pkcs12', extensions: ['p12', 'pfx'], category: 'application')]
    case APPLICATION_PKCS12 = 'APPLICATION_PKCS12';

    #[MimeTypeInfo(mimeType: 'application/pkcs7-mime', extensions: ['p7m', 'p7c'], category: 'application')]
    case APPLICATION_PKCS7_MIME = 'APPLICATION_PKCS7_MIME';

    #[MimeTypeInfo(mimeType: 'application/pkcs7-signature', extensions: ['p7s'], category: 'application')]
    case APPLICATION_PKCS7_SIGNATURE = 'APPLICATION_PKCS7_SIGNATURE';

    #[MimeTypeInfo(mimeType: 'application/pkcs8', extensions: ['p8'], category: 'application')]
    case APPLICATION_PKCS8 = 'APPLICATION_PKCS8';

    #[MimeTypeInfo(mimeType: 'application/pkcs8-encrypted', extensions: ['p8e'], category: 'application')]
    case APPLICATION_PKCS8_ENCRYPTED = 'APPLICATION_PKCS8_ENCRYPTED';

    #[MimeTypeInfo(mimeType: 'application/pkix-attr-cert', extensions: ['ac'], category: 'application')]
    case APPLICATION_PKIX_ATTR_CERT = 'APPLICATION_PKIX_ATTR_CERT';

    #[MimeTypeInfo(mimeType: 'application/pkix-cert', extensions: ['cer'], category: 'application')]
    case APPLICATION_PKIX_CERT = 'APPLICATION_PKIX_CERT';

    #[MimeTypeInfo(mimeType: 'application/pkix-crl', extensions: ['crl'], category: 'application')]
    case APPLICATION_PKIX_CRL = 'APPLICATION_PKIX_CRL';

    #[MimeTypeInfo(mimeType: 'application/pkix-pkipath', extensions: ['pkipath'], category: 'application')]
    case APPLICATION_PKIX_PKIPATH = 'APPLICATION_PKIX_PKIPATH';

    #[MimeTypeInfo(mimeType: 'application/pkixcmp', extensions: ['pki'], category: 'application')]
    case APPLICATION_PKIXCMP = 'APPLICATION_PKIXCMP';

    #[MimeTypeInfo(mimeType: 'application/pls+xml', extensions: ['pls'], category: 'application')]
    case APPLICATION_PLS_XML = 'APPLICATION_PLS_XML';

    #[MimeTypeInfo(mimeType: 'application/poc-settings+xml', extensions: [], category: 'application')]
    case APPLICATION_POC_SETTINGS_XML = 'APPLICATION_POC_SETTINGS_XML';

    #[MimeTypeInfo(mimeType: 'application/postscript', extensions: ['ai', 'eps', 'ps'], category: 'application')]
    case APPLICATION_POSTSCRIPT = 'APPLICATION_POSTSCRIPT';

    #[MimeTypeInfo(mimeType: 'application/ppsp-tracker+json', extensions: [], category: 'application')]
    case APPLICATION_PPSP_TRACKER_JSON = 'APPLICATION_PPSP_TRACKER_JSON';

    #[MimeTypeInfo(mimeType: 'application/private-token-issuer-directory', extensions: [], category: 'application')]
    case APPLICATION_PRIVATE_TOKEN_ISSUER_DIRECTORY = 'APPLICATION_PRIVATE_TOKEN_ISSUER_DIRECTORY';

    #[MimeTypeInfo(mimeType: 'application/private-token-request', extensions: [], category: 'application')]
    case APPLICATION_PRIVATE_TOKEN_REQUEST = 'APPLICATION_PRIVATE_TOKEN_REQUEST';

    #[MimeTypeInfo(mimeType: 'application/private-token-response', extensions: [], category: 'application')]
    case APPLICATION_PRIVATE_TOKEN_RESPONSE = 'APPLICATION_PRIVATE_TOKEN_RESPONSE';

    #[MimeTypeInfo(mimeType: 'application/problem+json', extensions: [], category: 'application')]
    case APPLICATION_PROBLEM_JSON = 'APPLICATION_PROBLEM_JSON';

    #[MimeTypeInfo(mimeType: 'application/problem+xml', extensions: [], category: 'application')]
    case APPLICATION_PROBLEM_XML = 'APPLICATION_PROBLEM_XML';

    #[MimeTypeInfo(mimeType: 'application/provenance+xml', extensions: ['provx'], category: 'application')]
    case APPLICATION_PROVENANCE_XML = 'APPLICATION_PROVENANCE_XML';

    #[MimeTypeInfo(mimeType: 'application/provided-claims+jwt', extensions: [], category: 'application')]
    case APPLICATION_PROVIDED_CLAIMS_JWT = 'APPLICATION_PROVIDED_CLAIMS_JWT';

    #[MimeTypeInfo(mimeType: 'application/prs.alvestrand.titrax-sheet', extensions: [], category: 'application')]
    case APPLICATION_PRS_ALVESTRAND_TITRAX_SHEET = 'APPLICATION_PRS_ALVESTRAND_TITRAX_SHEET';

    #[MimeTypeInfo(mimeType: 'application/prs.cww', extensions: ['cww', 'cw'], category: 'application')]
    case APPLICATION_PRS_CWW = 'APPLICATION_PRS_CWW';

    #[MimeTypeInfo(mimeType: 'application/prs.cyn', extensions: [], category: 'application')]
    case APPLICATION_PRS_CYN = 'APPLICATION_PRS_CYN';

    #[MimeTypeInfo(mimeType: 'application/prs.hpub+zip', extensions: ['hpub'], category: 'application')]
    case APPLICATION_PRS_HPUB_ZIP = 'APPLICATION_PRS_HPUB_ZIP';

    #[MimeTypeInfo(mimeType: 'application/prs.implied-document+xml', extensions: [], category: 'application')]
    case APPLICATION_PRS_IMPLIED_DOCUMENT_XML = 'APPLICATION_PRS_IMPLIED_DOCUMENT_XML';

    #[MimeTypeInfo(mimeType: 'application/prs.implied-executable', extensions: [], category: 'application')]
    case APPLICATION_PRS_IMPLIED_EXECUTABLE = 'APPLICATION_PRS_IMPLIED_EXECUTABLE';

    #[MimeTypeInfo(mimeType: 'application/prs.implied-object+json', extensions: [], category: 'application')]
    case APPLICATION_PRS_IMPLIED_OBJECT_JSON = 'APPLICATION_PRS_IMPLIED_OBJECT_JSON';

    #[MimeTypeInfo(mimeType: 'application/prs.implied-object+json-seq', extensions: [], category: 'application')]
    case APPLICATION_PRS_IMPLIED_OBJECT_JSON_SEQ = 'APPLICATION_PRS_IMPLIED_OBJECT_JSON_SEQ';

    #[MimeTypeInfo(mimeType: 'application/prs.implied-object+yaml', extensions: [], category: 'application')]
    case APPLICATION_PRS_IMPLIED_OBJECT_YAML = 'APPLICATION_PRS_IMPLIED_OBJECT_YAML';

    #[MimeTypeInfo(mimeType: 'application/prs.implied-structure', extensions: [], category: 'application')]
    case APPLICATION_PRS_IMPLIED_STRUCTURE = 'APPLICATION_PRS_IMPLIED_STRUCTURE';

    #[MimeTypeInfo(mimeType: 'application/prs.mayfile', extensions: ['mayf'], category: 'application')]
    case APPLICATION_PRS_MAYFILE = 'APPLICATION_PRS_MAYFILE';

    #[MimeTypeInfo(mimeType: 'application/prs.nprend', extensions: ['rnd', 'rct'], category: 'application')]
    case APPLICATION_PRS_NPREND = 'APPLICATION_PRS_NPREND';

    #[MimeTypeInfo(mimeType: 'application/prs.plucker', extensions: [], category: 'application')]
    case APPLICATION_PRS_PLUCKER = 'APPLICATION_PRS_PLUCKER';

    #[MimeTypeInfo(mimeType: 'application/prs.rdf-xml-crypt', extensions: ['rdf-crypt'], category: 'application')]
    case APPLICATION_PRS_RDF_XML_CRYPT = 'APPLICATION_PRS_RDF_XML_CRYPT';

    #[MimeTypeInfo(mimeType: 'application/prs.vcfbzip2', extensions: ['vcf.bz2', 'vcfbzip2', 'vcfbz2'], category: 'application')]
    case APPLICATION_PRS_VCFBZIP2 = 'APPLICATION_PRS_VCFBZIP2';

    #[MimeTypeInfo(mimeType: 'application/prs.xsf+xml', extensions: ['xsf', 'xml'], category: 'application')]
    case APPLICATION_PRS_XSF_XML = 'APPLICATION_PRS_XSF_XML';

    #[MimeTypeInfo(mimeType: 'application/pskc+xml', extensions: ['pskcxml'], category: 'application')]
    case APPLICATION_PSKC_XML = 'APPLICATION_PSKC_XML';

    #[MimeTypeInfo(mimeType: 'application/pvd+json', extensions: [], category: 'application')]
    case APPLICATION_PVD_JSON = 'APPLICATION_PVD_JSON';

    #[MimeTypeInfo(mimeType: 'application/qsig', extensions: [], category: 'application')]
    case APPLICATION_QSIG = 'APPLICATION_QSIG';

    #[MimeTypeInfo(mimeType: 'application/raml+yaml', extensions: ['raml'], category: 'application')]
    case APPLICATION_RAML_YAML = 'APPLICATION_RAML_YAML';

    #[MimeTypeInfo(mimeType: 'application/raptorfec', extensions: [], category: 'application')]
    case APPLICATION_RAPTORFEC = 'APPLICATION_RAPTORFEC';

    #[MimeTypeInfo(mimeType: 'application/rdap+json', extensions: [], category: 'application')]
    case APPLICATION_RDAP_JSON = 'APPLICATION_RDAP_JSON';

    #[MimeTypeInfo(mimeType: 'application/rdf+xml', extensions: ['rdf', 'owl'], category: 'application')]
    case APPLICATION_RDF_XML = 'APPLICATION_RDF_XML';

    #[MimeTypeInfo(mimeType: 'application/reginfo+xml', extensions: ['rif', 'xml'], category: 'application')]
    case APPLICATION_REGINFO_XML = 'APPLICATION_REGINFO_XML';

    #[MimeTypeInfo(mimeType: 'application/relax-ng-compact-syntax', extensions: ['rnc'], category: 'application')]
    case APPLICATION_RELAX_NG_COMPACT_SYNTAX = 'APPLICATION_RELAX_NG_COMPACT_SYNTAX';

    #[MimeTypeInfo(mimeType: 'application/remote-printing', extensions: [], category: 'application')]
    case APPLICATION_REMOTE_PRINTING = 'APPLICATION_REMOTE_PRINTING';

    #[MimeTypeInfo(mimeType: 'application/reputon+json', extensions: [], category: 'application')]
    case APPLICATION_REPUTON_JSON = 'APPLICATION_REPUTON_JSON';

    #[MimeTypeInfo(mimeType: 'application/resolve-response+jwt', extensions: [], category: 'application')]
    case APPLICATION_RESOLVE_RESPONSE_JWT = 'APPLICATION_RESOLVE_RESPONSE_JWT';

    #[MimeTypeInfo(mimeType: 'application/resource-lists+xml', extensions: ['rl'], category: 'application')]
    case APPLICATION_RESOURCE_LISTS_XML = 'APPLICATION_RESOURCE_LISTS_XML';

    #[MimeTypeInfo(mimeType: 'application/resource-lists-diff+xml', extensions: ['rld'], category: 'application')]
    case APPLICATION_RESOURCE_LISTS_DIFF_XML = 'APPLICATION_RESOURCE_LISTS_DIFF_XML';

    #[MimeTypeInfo(mimeType: 'application/rfc+xml', extensions: ['xml', 'rfcxml'], category: 'application')]
    case APPLICATION_RFC_XML = 'APPLICATION_RFC_XML';

    #[MimeTypeInfo(mimeType: 'application/riscos', extensions: [], category: 'application')]
    case APPLICATION_RISCOS = 'APPLICATION_RISCOS';

    #[MimeTypeInfo(mimeType: 'application/rlmi+xml', extensions: [], category: 'application')]
    case APPLICATION_RLMI_XML = 'APPLICATION_RLMI_XML';

    #[MimeTypeInfo(mimeType: 'application/rls-services+xml', extensions: ['rs'], category: 'application')]
    case APPLICATION_RLS_SERVICES_XML = 'APPLICATION_RLS_SERVICES_XML';

    #[MimeTypeInfo(mimeType: 'application/route-apd+xml', extensions: ['rapd'], category: 'application')]
    case APPLICATION_ROUTE_APD_XML = 'APPLICATION_ROUTE_APD_XML';

    #[MimeTypeInfo(mimeType: 'application/route-s-tsid+xml', extensions: ['sls'], category: 'application')]
    case APPLICATION_ROUTE_S_TSID_XML = 'APPLICATION_ROUTE_S_TSID_XML';

    #[MimeTypeInfo(mimeType: 'application/route-usd+xml', extensions: ['rusd'], category: 'application')]
    case APPLICATION_ROUTE_USD_XML = 'APPLICATION_ROUTE_USD_XML';

    #[MimeTypeInfo(mimeType: 'application/rpki-checklist', extensions: ['sig'], category: 'application')]
    case APPLICATION_RPKI_CHECKLIST = 'APPLICATION_RPKI_CHECKLIST';

    #[MimeTypeInfo(mimeType: 'application/rpki-ghostbusters', extensions: ['gbr'], category: 'application')]
    case APPLICATION_RPKI_GHOSTBUSTERS = 'APPLICATION_RPKI_GHOSTBUSTERS';

    #[MimeTypeInfo(mimeType: 'application/rpki-manifest', extensions: ['mft'], category: 'application')]
    case APPLICATION_RPKI_MANIFEST = 'APPLICATION_RPKI_MANIFEST';

    #[MimeTypeInfo(mimeType: 'application/rpki-publication', extensions: [], category: 'application')]
    case APPLICATION_RPKI_PUBLICATION = 'APPLICATION_RPKI_PUBLICATION';

    #[MimeTypeInfo(mimeType: 'application/rpki-roa', extensions: ['roa'], category: 'application')]
    case APPLICATION_RPKI_ROA = 'APPLICATION_RPKI_ROA';

    #[MimeTypeInfo(mimeType: 'application/rpki-signed-tal', extensions: ['tak'], category: 'application')]
    case APPLICATION_RPKI_SIGNED_TAL = 'APPLICATION_RPKI_SIGNED_TAL';

    #[MimeTypeInfo(mimeType: 'application/rpki-updown', extensions: [], category: 'application')]
    case APPLICATION_RPKI_UPDOWN = 'APPLICATION_RPKI_UPDOWN';

    #[MimeTypeInfo(mimeType: 'application/rs-metadata+xml', extensions: [], category: 'application')]
    case APPLICATION_RS_METADATA_XML = 'APPLICATION_RS_METADATA_XML';

    #[MimeTypeInfo(mimeType: 'application/rsd+xml', extensions: ['rsd'], category: 'application')]
    case APPLICATION_RSD_XML = 'APPLICATION_RSD_XML';

    #[MimeTypeInfo(mimeType: 'application/rss+xml', extensions: ['rss'], category: 'application')]
    case APPLICATION_RSS_XML = 'APPLICATION_RSS_XML';

    #[MimeTypeInfo(mimeType: 'application/rtf', extensions: ['rtf'], category: 'application')]
    case APPLICATION_RTF = 'APPLICATION_RTF';

    #[MimeTypeInfo(mimeType: 'application/rtploopback', extensions: [], category: 'application')]
    case APPLICATION_RTPLOOPBACK = 'APPLICATION_RTPLOOPBACK';

    #[MimeTypeInfo(mimeType: 'application/rtx', extensions: [], category: 'application')]
    case APPLICATION_RTX = 'APPLICATION_RTX';

    #[MimeTypeInfo(mimeType: 'application/samlassertion+xml', extensions: [], category: 'application')]
    case APPLICATION_SAMLASSERTION_XML = 'APPLICATION_SAMLASSERTION_XML';

    #[MimeTypeInfo(mimeType: 'application/samlmetadata+xml', extensions: [], category: 'application')]
    case APPLICATION_SAMLMETADATA_XML = 'APPLICATION_SAMLMETADATA_XML';

    #[MimeTypeInfo(mimeType: 'application/sarif+json', extensions: ['sarif', 'sarif.json'], category: 'application')]
    case APPLICATION_SARIF_JSON = 'APPLICATION_SARIF_JSON';

    #[MimeTypeInfo(mimeType: 'application/sarif-external-properties+json', extensions: ['sarif-external-properties', 'sarif-external-properties.json'], category: 'application')]
    case APPLICATION_SARIF_EXTERNAL_PROPERTIES_JSON = 'APPLICATION_SARIF_EXTERNAL_PROPERTIES_JSON';

    #[MimeTypeInfo(mimeType: 'application/sbe', extensions: [], category: 'application')]
    case APPLICATION_SBE = 'APPLICATION_SBE';

    #[MimeTypeInfo(mimeType: 'application/sbml+xml', extensions: ['sbml'], category: 'application')]
    case APPLICATION_SBML_XML = 'APPLICATION_SBML_XML';

    #[MimeTypeInfo(mimeType: 'application/scaip+xml', extensions: [], category: 'application')]
    case APPLICATION_SCAIP_XML = 'APPLICATION_SCAIP_XML';

    #[MimeTypeInfo(mimeType: 'application/scim+json', extensions: ['scim'], category: 'application')]
    case APPLICATION_SCIM_JSON = 'APPLICATION_SCIM_JSON';

    #[MimeTypeInfo(mimeType: 'application/scvp-cv-request', extensions: ['scq'], category: 'application')]
    case APPLICATION_SCVP_CV_REQUEST = 'APPLICATION_SCVP_CV_REQUEST';

    #[MimeTypeInfo(mimeType: 'application/scvp-cv-response', extensions: ['scs'], category: 'application')]
    case APPLICATION_SCVP_CV_RESPONSE = 'APPLICATION_SCVP_CV_RESPONSE';

    #[MimeTypeInfo(mimeType: 'application/scvp-vp-request', extensions: ['spq'], category: 'application')]
    case APPLICATION_SCVP_VP_REQUEST = 'APPLICATION_SCVP_VP_REQUEST';

    #[MimeTypeInfo(mimeType: 'application/scvp-vp-response', extensions: ['spp'], category: 'application')]
    case APPLICATION_SCVP_VP_RESPONSE = 'APPLICATION_SCVP_VP_RESPONSE';

    #[MimeTypeInfo(mimeType: 'application/sd-jwt', extensions: [], category: 'application')]
    case APPLICATION_SD_JWT = 'APPLICATION_SD_JWT';

    #[MimeTypeInfo(mimeType: 'application/sd-jwt+json', extensions: [], category: 'application')]
    case APPLICATION_SD_JWT_JSON = 'APPLICATION_SD_JWT_JSON';

    #[MimeTypeInfo(mimeType: 'application/sdf+json', extensions: ['sdf.json'], category: 'application')]
    case APPLICATION_SDF_JSON = 'APPLICATION_SDF_JSON';

    #[MimeTypeInfo(mimeType: 'application/sdp', extensions: ['sdp'], category: 'application')]
    case APPLICATION_SDP = 'APPLICATION_SDP';

    #[MimeTypeInfo(mimeType: 'application/secevent+jwt', extensions: [], category: 'application')]
    case APPLICATION_SECEVENT_JWT = 'APPLICATION_SECEVENT_JWT';

    #[MimeTypeInfo(mimeType: 'application/senml+cbor', extensions: ['senmlc'], category: 'application')]
    case APPLICATION_SENML_CBOR = 'APPLICATION_SENML_CBOR';

    #[MimeTypeInfo(mimeType: 'application/senml+json', extensions: ['senml'], category: 'application')]
    case APPLICATION_SENML_JSON = 'APPLICATION_SENML_JSON';

    #[MimeTypeInfo(mimeType: 'application/senml+xml', extensions: ['senmlx'], category: 'application')]
    case APPLICATION_SENML_XML = 'APPLICATION_SENML_XML';

    #[MimeTypeInfo(mimeType: 'application/senml-etch+cbor', extensions: ['senml-etchc'], category: 'application')]
    case APPLICATION_SENML_ETCH_CBOR = 'APPLICATION_SENML_ETCH_CBOR';

    #[MimeTypeInfo(mimeType: 'application/senml-etch+json', extensions: ['senml-etchj'], category: 'application')]
    case APPLICATION_SENML_ETCH_JSON = 'APPLICATION_SENML_ETCH_JSON';

    #[MimeTypeInfo(mimeType: 'application/senml-exi', extensions: ['senmle'], category: 'application')]
    case APPLICATION_SENML_EXI = 'APPLICATION_SENML_EXI';

    #[MimeTypeInfo(mimeType: 'application/sensml+cbor', extensions: ['sensmlc'], category: 'application')]
    case APPLICATION_SENSML_CBOR = 'APPLICATION_SENSML_CBOR';

    #[MimeTypeInfo(mimeType: 'application/sensml+json', extensions: ['sensml'], category: 'application')]
    case APPLICATION_SENSML_JSON = 'APPLICATION_SENSML_JSON';

    #[MimeTypeInfo(mimeType: 'application/sensml+xml', extensions: ['sensmlx'], category: 'application')]
    case APPLICATION_SENSML_XML = 'APPLICATION_SENSML_XML';

    #[MimeTypeInfo(mimeType: 'application/sensml-exi', extensions: ['sensmle'], category: 'application')]
    case APPLICATION_SENSML_EXI = 'APPLICATION_SENSML_EXI';

    #[MimeTypeInfo(mimeType: 'application/sep+xml', extensions: [], category: 'application')]
    case APPLICATION_SEP_XML = 'APPLICATION_SEP_XML';

    #[MimeTypeInfo(mimeType: 'application/sep-exi', extensions: [], category: 'application')]
    case APPLICATION_SEP_EXI = 'APPLICATION_SEP_EXI';

    #[MimeTypeInfo(mimeType: 'application/session-info', extensions: [], category: 'application')]
    case APPLICATION_SESSION_INFO = 'APPLICATION_SESSION_INFO';

    #[MimeTypeInfo(mimeType: 'application/set-payment', extensions: [], category: 'application')]
    case APPLICATION_SET_PAYMENT = 'APPLICATION_SET_PAYMENT';

    #[MimeTypeInfo(mimeType: 'application/set-payment-initiation', extensions: ['setpay'], category: 'application')]
    case APPLICATION_SET_PAYMENT_INITIATION = 'APPLICATION_SET_PAYMENT_INITIATION';

    #[MimeTypeInfo(mimeType: 'application/set-registration', extensions: [], category: 'application')]
    case APPLICATION_SET_REGISTRATION = 'APPLICATION_SET_REGISTRATION';

    #[MimeTypeInfo(mimeType: 'application/set-registration-initiation', extensions: ['setreg'], category: 'application')]
    case APPLICATION_SET_REGISTRATION_INITIATION = 'APPLICATION_SET_REGISTRATION_INITIATION';

    #[MimeTypeInfo(mimeType: 'application/sgml', extensions: [], category: 'application')]
    case APPLICATION_SGML = 'APPLICATION_SGML';

    #[MimeTypeInfo(mimeType: 'application/sgml-open-catalog', extensions: [], category: 'application')]
    case APPLICATION_SGML_OPEN_CATALOG = 'APPLICATION_SGML_OPEN_CATALOG';

    #[MimeTypeInfo(mimeType: 'application/shf+xml', extensions: ['shf'], category: 'application')]
    case APPLICATION_SHF_XML = 'APPLICATION_SHF_XML';

    #[MimeTypeInfo(mimeType: 'application/sieve', extensions: ['siv', 'sieve'], category: 'application')]
    case APPLICATION_SIEVE = 'APPLICATION_SIEVE';

    #[MimeTypeInfo(mimeType: 'application/simple-filter+xml', extensions: ['cl', 'xml'], category: 'application')]
    case APPLICATION_SIMPLE_FILTER_XML = 'APPLICATION_SIMPLE_FILTER_XML';

    #[MimeTypeInfo(mimeType: 'application/simple-message-summary', extensions: [], category: 'application')]
    case APPLICATION_SIMPLE_MESSAGE_SUMMARY = 'APPLICATION_SIMPLE_MESSAGE_SUMMARY';

    #[MimeTypeInfo(mimeType: 'application/simplesymbolcontainer', extensions: [], category: 'application')]
    case APPLICATION_SIMPLESYMBOLCONTAINER = 'APPLICATION_SIMPLESYMBOLCONTAINER';

    #[MimeTypeInfo(mimeType: 'application/sipc', extensions: ['h5'], category: 'application')]
    case APPLICATION_SIPC = 'APPLICATION_SIPC';

    #[MimeTypeInfo(mimeType: 'application/slate', extensions: [], category: 'application')]
    case APPLICATION_SLATE = 'APPLICATION_SLATE';

    #[MimeTypeInfo(mimeType: 'application/smil', extensions: ['smil', 'smi', 'sml'], category: 'application')]
    case APPLICATION_SMIL = 'APPLICATION_SMIL';

    #[MimeTypeInfo(mimeType: 'application/smil+xml', extensions: ['smi', 'smil'], category: 'application')]
    case APPLICATION_SMIL_XML = 'APPLICATION_SMIL_XML';

    #[MimeTypeInfo(mimeType: 'application/smpte336m', extensions: [], category: 'application')]
    case APPLICATION_SMPTE336M = 'APPLICATION_SMPTE336M';

    #[MimeTypeInfo(mimeType: 'application/soap+fastinfoset', extensions: [], category: 'application')]
    case APPLICATION_SOAP_FASTINFOSET = 'APPLICATION_SOAP_FASTINFOSET';

    #[MimeTypeInfo(mimeType: 'application/soap+xml', extensions: [], category: 'application')]
    case APPLICATION_SOAP_XML = 'APPLICATION_SOAP_XML';

    #[MimeTypeInfo(mimeType: 'application/sparql-query', extensions: ['rq'], category: 'application')]
    case APPLICATION_SPARQL_QUERY = 'APPLICATION_SPARQL_QUERY';

    #[MimeTypeInfo(mimeType: 'application/sparql-results+xml', extensions: ['srx'], category: 'application')]
    case APPLICATION_SPARQL_RESULTS_XML = 'APPLICATION_SPARQL_RESULTS_XML';

    #[MimeTypeInfo(mimeType: 'application/spdx+json', extensions: ['spdx.json'], category: 'application')]
    case APPLICATION_SPDX_JSON = 'APPLICATION_SPDX_JSON';

    #[MimeTypeInfo(mimeType: 'application/spirits-event+xml', extensions: [], category: 'application')]
    case APPLICATION_SPIRITS_EVENT_XML = 'APPLICATION_SPIRITS_EVENT_XML';

    #[MimeTypeInfo(mimeType: 'application/sql', extensions: ['sql'], category: 'application')]
    case APPLICATION_SQL = 'APPLICATION_SQL';

    #[MimeTypeInfo(mimeType: 'application/srgs', extensions: ['gram'], category: 'application')]
    case APPLICATION_SRGS = 'APPLICATION_SRGS';

    #[MimeTypeInfo(mimeType: 'application/srgs+xml', extensions: ['grxml'], category: 'application')]
    case APPLICATION_SRGS_XML = 'APPLICATION_SRGS_XML';

    #[MimeTypeInfo(mimeType: 'application/sru+xml', extensions: ['sru'], category: 'application')]
    case APPLICATION_SRU_XML = 'APPLICATION_SRU_XML';

    #[MimeTypeInfo(mimeType: 'application/ssdl+xml', extensions: ['ssdl'], category: 'application')]
    case APPLICATION_SSDL_XML = 'APPLICATION_SSDL_XML';

    #[MimeTypeInfo(mimeType: 'application/sslkeylogfile', extensions: [], category: 'application')]
    case APPLICATION_SSLKEYLOGFILE = 'APPLICATION_SSLKEYLOGFILE';

    #[MimeTypeInfo(mimeType: 'application/ssml+xml', extensions: ['ssml'], category: 'application')]
    case APPLICATION_SSML_XML = 'APPLICATION_SSML_XML';

    #[MimeTypeInfo(mimeType: 'application/st2110-41', extensions: [], category: 'application')]
    case APPLICATION_ST2110_41 = 'APPLICATION_ST2110_41';

    #[MimeTypeInfo(mimeType: 'application/stix+json', extensions: ['stix'], category: 'application')]
    case APPLICATION_STIX_JSON = 'APPLICATION_STIX_JSON';

    #[MimeTypeInfo(mimeType: 'application/stratum', extensions: [], category: 'application')]
    case APPLICATION_STRATUM = 'APPLICATION_STRATUM';

    #[MimeTypeInfo(mimeType: 'application/suit-envelope+cose', extensions: ['cbor', 'suit'], category: 'application')]
    case APPLICATION_SUIT_ENVELOPE_COSE = 'APPLICATION_SUIT_ENVELOPE_COSE';

    #[MimeTypeInfo(mimeType: 'application/swid+cbor', extensions: ['coswid'], category: 'application')]
    case APPLICATION_SWID_CBOR = 'APPLICATION_SWID_CBOR';

    #[MimeTypeInfo(mimeType: 'application/swid+xml', extensions: ['swidtag'], category: 'application')]
    case APPLICATION_SWID_XML = 'APPLICATION_SWID_XML';

    #[MimeTypeInfo(mimeType: 'application/tamp-apex-update', extensions: ['tau'], category: 'application')]
    case APPLICATION_TAMP_APEX_UPDATE = 'APPLICATION_TAMP_APEX_UPDATE';

    #[MimeTypeInfo(mimeType: 'application/tamp-apex-update-confirm', extensions: ['auc'], category: 'application')]
    case APPLICATION_TAMP_APEX_UPDATE_CONFIRM = 'APPLICATION_TAMP_APEX_UPDATE_CONFIRM';

    #[MimeTypeInfo(mimeType: 'application/tamp-community-update', extensions: ['tcu'], category: 'application')]
    case APPLICATION_TAMP_COMMUNITY_UPDATE = 'APPLICATION_TAMP_COMMUNITY_UPDATE';

    #[MimeTypeInfo(mimeType: 'application/tamp-community-update-confirm', extensions: ['cuc'], category: 'application')]
    case APPLICATION_TAMP_COMMUNITY_UPDATE_CONFIRM = 'APPLICATION_TAMP_COMMUNITY_UPDATE_CONFIRM';

    #[MimeTypeInfo(mimeType: 'application/tamp-error', extensions: ['ter'], category: 'application')]
    case APPLICATION_TAMP_ERROR = 'APPLICATION_TAMP_ERROR';

    #[MimeTypeInfo(mimeType: 'application/tamp-sequence-adjust', extensions: ['tsa'], category: 'application')]
    case APPLICATION_TAMP_SEQUENCE_ADJUST = 'APPLICATION_TAMP_SEQUENCE_ADJUST';

    #[MimeTypeInfo(mimeType: 'application/tamp-sequence-adjust-confirm', extensions: ['sac'], category: 'application')]
    case APPLICATION_TAMP_SEQUENCE_ADJUST_CONFIRM = 'APPLICATION_TAMP_SEQUENCE_ADJUST_CONFIRM';

    #[MimeTypeInfo(mimeType: 'application/tamp-status-query', extensions: ['tsq'], category: 'application')]
    case APPLICATION_TAMP_STATUS_QUERY = 'APPLICATION_TAMP_STATUS_QUERY';

    #[MimeTypeInfo(mimeType: 'application/tamp-status-response', extensions: ['tsr'], category: 'application')]
    case APPLICATION_TAMP_STATUS_RESPONSE = 'APPLICATION_TAMP_STATUS_RESPONSE';

    #[MimeTypeInfo(mimeType: 'application/tamp-update', extensions: ['tur'], category: 'application')]
    case APPLICATION_TAMP_UPDATE = 'APPLICATION_TAMP_UPDATE';

    #[MimeTypeInfo(mimeType: 'application/tamp-update-confirm', extensions: ['tuc'], category: 'application')]
    case APPLICATION_TAMP_UPDATE_CONFIRM = 'APPLICATION_TAMP_UPDATE_CONFIRM';

    #[MimeTypeInfo(mimeType: 'application/tar', extensions: [], category: 'application')]
    case APPLICATION_TAR = 'APPLICATION_TAR';

    #[MimeTypeInfo(mimeType: 'application/taxii+json', extensions: [], category: 'application')]
    case APPLICATION_TAXII_JSON = 'APPLICATION_TAXII_JSON';

    #[MimeTypeInfo(mimeType: 'application/td+json', extensions: ['jsontd'], category: 'application')]
    case APPLICATION_TD_JSON = 'APPLICATION_TD_JSON';

    #[MimeTypeInfo(mimeType: 'application/tei+xml', extensions: ['tei', 'teicorpus'], category: 'application')]
    case APPLICATION_TEI_XML = 'APPLICATION_TEI_XML';

    #[MimeTypeInfo(mimeType: 'application/tetra_isi', extensions: [], category: 'application')]
    case APPLICATION_TETRA_ISI = 'APPLICATION_TETRA_ISI';

    #[MimeTypeInfo(mimeType: 'application/texinfo', extensions: ['texi', 'texinfo'], category: 'application')]
    case APPLICATION_TEXINFO = 'APPLICATION_TEXINFO';

    #[MimeTypeInfo(mimeType: 'application/thraud+xml', extensions: ['tfi'], category: 'application')]
    case APPLICATION_THRAUD_XML = 'APPLICATION_THRAUD_XML';

    #[MimeTypeInfo(mimeType: 'application/timestamp-query', extensions: ['tsq'], category: 'application')]
    case APPLICATION_TIMESTAMP_QUERY = 'APPLICATION_TIMESTAMP_QUERY';

    #[MimeTypeInfo(mimeType: 'application/timestamp-reply', extensions: ['tsr'], category: 'application')]
    case APPLICATION_TIMESTAMP_REPLY = 'APPLICATION_TIMESTAMP_REPLY';

    #[MimeTypeInfo(mimeType: 'application/timestamped-data', extensions: ['tsd'], category: 'application')]
    case APPLICATION_TIMESTAMPED_DATA = 'APPLICATION_TIMESTAMPED_DATA';

    #[MimeTypeInfo(mimeType: 'application/tlsrpt+gzip', extensions: ['gz'], category: 'application')]
    case APPLICATION_TLSRPT_GZIP = 'APPLICATION_TLSRPT_GZIP';

    #[MimeTypeInfo(mimeType: 'application/tlsrpt+json', extensions: ['json'], category: 'application')]
    case APPLICATION_TLSRPT_JSON = 'APPLICATION_TLSRPT_JSON';

    #[MimeTypeInfo(mimeType: 'application/tm+json', extensions: ['jsontm', 'tm.json', 'tm.jsonld'], category: 'application')]
    case APPLICATION_TM_JSON = 'APPLICATION_TM_JSON';

    #[MimeTypeInfo(mimeType: 'application/tnauthlist', extensions: [], category: 'application')]
    case APPLICATION_TNAUTHLIST = 'APPLICATION_TNAUTHLIST';

    #[MimeTypeInfo(mimeType: 'application/toc+cbor', extensions: [], category: 'application')]
    case APPLICATION_TOC_CBOR = 'APPLICATION_TOC_CBOR';

    #[MimeTypeInfo(mimeType: 'application/token-introspection+jwt', extensions: [], category: 'application')]
    case APPLICATION_TOKEN_INTROSPECTION_JWT = 'APPLICATION_TOKEN_INTROSPECTION_JWT';

    #[MimeTypeInfo(mimeType: 'application/toml', extensions: ['toml'], category: 'application')]
    case APPLICATION_TOML = 'APPLICATION_TOML';

    #[MimeTypeInfo(mimeType: 'application/trickle-ice-sdpfrag', extensions: [], category: 'application')]
    case APPLICATION_TRICKLE_ICE_SDPFRAG = 'APPLICATION_TRICKLE_ICE_SDPFRAG';

    #[MimeTypeInfo(mimeType: 'application/trig', extensions: ['trig'], category: 'application')]
    case APPLICATION_TRIG = 'APPLICATION_TRIG';

    #[MimeTypeInfo(mimeType: 'application/trust-chain+json', extensions: [], category: 'application')]
    case APPLICATION_TRUST_CHAIN_JSON = 'APPLICATION_TRUST_CHAIN_JSON';

    #[MimeTypeInfo(mimeType: 'application/trust-mark+jwt', extensions: [], category: 'application')]
    case APPLICATION_TRUST_MARK_JWT = 'APPLICATION_TRUST_MARK_JWT';

    #[MimeTypeInfo(mimeType: 'application/trust-mark-delegation+jwt', extensions: [], category: 'application')]
    case APPLICATION_TRUST_MARK_DELEGATION_JWT = 'APPLICATION_TRUST_MARK_DELEGATION_JWT';

    #[MimeTypeInfo(mimeType: 'application/ttml+xml', extensions: ['ttml'], category: 'application')]
    case APPLICATION_TTML_XML = 'APPLICATION_TTML_XML';

    #[MimeTypeInfo(mimeType: 'application/tve-trigger', extensions: [], category: 'application')]
    case APPLICATION_TVE_TRIGGER = 'APPLICATION_TVE_TRIGGER';

    #[MimeTypeInfo(mimeType: 'application/tzif', extensions: [], category: 'application')]
    case APPLICATION_TZIF = 'APPLICATION_TZIF';

    #[MimeTypeInfo(mimeType: 'application/tzif-leap', extensions: [], category: 'application')]
    case APPLICATION_TZIF_LEAP = 'APPLICATION_TZIF_LEAP';

    #[MimeTypeInfo(mimeType: 'application/ubjson', extensions: ['ubj'], category: 'application')]
    case APPLICATION_UBJSON = 'APPLICATION_UBJSON';

    #[MimeTypeInfo(mimeType: 'application/uccs+cbor', extensions: ['uccs'], category: 'application')]
    case APPLICATION_UCCS_CBOR = 'APPLICATION_UCCS_CBOR';

    #[MimeTypeInfo(mimeType: 'application/ujcs+json', extensions: ['ujcs'], category: 'application')]
    case APPLICATION_UJCS_JSON = 'APPLICATION_UJCS_JSON';

    #[MimeTypeInfo(mimeType: 'application/ulpfec', extensions: [], category: 'application')]
    case APPLICATION_ULPFEC = 'APPLICATION_ULPFEC';

    #[MimeTypeInfo(mimeType: 'application/urc-grpsheet+xml', extensions: ['gsheet'], category: 'application')]
    case APPLICATION_URC_GRPSHEET_XML = 'APPLICATION_URC_GRPSHEET_XML';

    #[MimeTypeInfo(mimeType: 'application/urc-ressheet+xml', extensions: ['rsheet'], category: 'application')]
    case APPLICATION_URC_RESSHEET_XML = 'APPLICATION_URC_RESSHEET_XML';

    #[MimeTypeInfo(mimeType: 'application/urc-targetdesc+xml', extensions: ['td'], category: 'application')]
    case APPLICATION_URC_TARGETDESC_XML = 'APPLICATION_URC_TARGETDESC_XML';

    #[MimeTypeInfo(mimeType: 'application/urc-uisocketdesc+xml', extensions: ['uis'], category: 'application')]
    case APPLICATION_URC_UISOCKETDESC_XML = 'APPLICATION_URC_UISOCKETDESC_XML';

    #[MimeTypeInfo(mimeType: 'application/vc', extensions: ['vc'], category: 'application')]
    case APPLICATION_VC = 'APPLICATION_VC';

    #[MimeTypeInfo(mimeType: 'application/vc+cose', extensions: [], category: 'application')]
    case APPLICATION_VC_COSE = 'APPLICATION_VC_COSE';

    #[MimeTypeInfo(mimeType: 'application/vc+jwt', extensions: [], category: 'application')]
    case APPLICATION_VC_JWT = 'APPLICATION_VC_JWT';

    #[MimeTypeInfo(mimeType: 'application/vcard+json', extensions: [], category: 'application')]
    case APPLICATION_VCARD_JSON = 'APPLICATION_VCARD_JSON';

    #[MimeTypeInfo(mimeType: 'application/vcard+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VCARD_XML = 'APPLICATION_VCARD_XML';

    #[MimeTypeInfo(mimeType: 'application/vemmi', extensions: [], category: 'application')]
    case APPLICATION_VEMMI = 'APPLICATION_VEMMI';

    #[MimeTypeInfo(mimeType: 'application/vividence.scriptfile', extensions: ['vsf', 'vtd', 'vd'], category: 'application')]
    case APPLICATION_VIVIDENCE_SCRIPTFILE = 'APPLICATION_VIVIDENCE_SCRIPTFILE';

    #[MimeTypeInfo(mimeType: 'application/vnd.1000minds.decision-model+xml', extensions: ['1km'], category: 'application')]
    case APPLICATION_VND_1000MINDS_DECISION_MODEL_XML = 'APPLICATION_VND_1000MINDS_DECISION_MODEL_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.1ob', extensions: ['ob'], category: 'application')]
    case APPLICATION_VND_1OB = 'APPLICATION_VND_1OB';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp-prose+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_PROSE_XML = 'APPLICATION_VND_3GPP_PROSE_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp-prose-pc3a+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_PROSE_PC3A_XML = 'APPLICATION_VND_3GPP_PROSE_PC3A_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp-prose-pc3ach+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_PROSE_PC3ACH_XML = 'APPLICATION_VND_3GPP_PROSE_PC3ACH_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp-prose-pc3ch+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_PROSE_PC3CH_XML = 'APPLICATION_VND_3GPP_PROSE_PC3CH_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp-prose-pc8+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_PROSE_PC8_XML = 'APPLICATION_VND_3GPP_PROSE_PC8_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp-v2x-local-service-information', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_V2X_LOCAL_SERVICE_INFORMATION = 'APPLICATION_VND_3GPP_V2X_LOCAL_SERVICE_INFORMATION';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.5gnas', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_5GNAS = 'APPLICATION_VND_3GPP_5GNAS';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.5gsa2x', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_5GSA2X = 'APPLICATION_VND_3GPP_5GSA2X';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.5gsa2x-local-service-information', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_5GSA2X_LOCAL_SERVICE_INFORMATION = 'APPLICATION_VND_3GPP_5GSA2X_LOCAL_SERVICE_INFORMATION';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.5gsv2x', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_5GSV2X = 'APPLICATION_VND_3GPP_5GSV2X';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.5gsv2x-local-service-information', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_5GSV2X_LOCAL_SERVICE_INFORMATION = 'APPLICATION_VND_3GPP_5GSV2X_LOCAL_SERVICE_INFORMATION';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.access-transfer-events+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_ACCESS_TRANSFER_EVENTS_XML = 'APPLICATION_VND_3GPP_ACCESS_TRANSFER_EVENTS_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.bsf+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_BSF_XML = 'APPLICATION_VND_3GPP_BSF_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.crs+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_CRS_XML = 'APPLICATION_VND_3GPP_CRS_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.current-location-discovery+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_CURRENT_LOCATION_DISCOVERY_XML = 'APPLICATION_VND_3GPP_CURRENT_LOCATION_DISCOVERY_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.gmop+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_GMOP_XML = 'APPLICATION_VND_3GPP_GMOP_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.gtpc', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_GTPC = 'APPLICATION_VND_3GPP_GTPC';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.interworking-data', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_INTERWORKING_DATA = 'APPLICATION_VND_3GPP_INTERWORKING_DATA';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.lpp', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_LPP = 'APPLICATION_VND_3GPP_LPP';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.mc-signalling-ear', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_MC_SIGNALLING_EAR = 'APPLICATION_VND_3GPP_MC_SIGNALLING_EAR';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.mcdata-affiliation-command+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_MCDATA_AFFILIATION_COMMAND_XML = 'APPLICATION_VND_3GPP_MCDATA_AFFILIATION_COMMAND_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.mcdata-info+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_MCDATA_INFO_XML = 'APPLICATION_VND_3GPP_MCDATA_INFO_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.mcdata-msgstore-ctrl-request+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_MCDATA_MSGSTORE_CTRL_REQUEST_XML = 'APPLICATION_VND_3GPP_MCDATA_MSGSTORE_CTRL_REQUEST_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.mcdata-payload', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_MCDATA_PAYLOAD = 'APPLICATION_VND_3GPP_MCDATA_PAYLOAD';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.mcdata-regroup+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_MCDATA_REGROUP_XML = 'APPLICATION_VND_3GPP_MCDATA_REGROUP_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.mcdata-service-config+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_MCDATA_SERVICE_CONFIG_XML = 'APPLICATION_VND_3GPP_MCDATA_SERVICE_CONFIG_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.mcdata-signalling', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_MCDATA_SIGNALLING = 'APPLICATION_VND_3GPP_MCDATA_SIGNALLING';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.mcdata-ue-config+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_MCDATA_UE_CONFIG_XML = 'APPLICATION_VND_3GPP_MCDATA_UE_CONFIG_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.mcdata-user-profile+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_MCDATA_USER_PROFILE_XML = 'APPLICATION_VND_3GPP_MCDATA_USER_PROFILE_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.mcptt-affiliation-command+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_MCPTT_AFFILIATION_COMMAND_XML = 'APPLICATION_VND_3GPP_MCPTT_AFFILIATION_COMMAND_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.mcptt-floor-request+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_MCPTT_FLOOR_REQUEST_XML = 'APPLICATION_VND_3GPP_MCPTT_FLOOR_REQUEST_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.mcptt-info+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_MCPTT_INFO_XML = 'APPLICATION_VND_3GPP_MCPTT_INFO_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.mcptt-location-info+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_MCPTT_LOCATION_INFO_XML = 'APPLICATION_VND_3GPP_MCPTT_LOCATION_INFO_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.mcptt-mbms-usage-info+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_MCPTT_MBMS_USAGE_INFO_XML = 'APPLICATION_VND_3GPP_MCPTT_MBMS_USAGE_INFO_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.mcptt-regroup+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_MCPTT_REGROUP_XML = 'APPLICATION_VND_3GPP_MCPTT_REGROUP_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.mcptt-service-config+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_MCPTT_SERVICE_CONFIG_XML = 'APPLICATION_VND_3GPP_MCPTT_SERVICE_CONFIG_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.mcptt-signed+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_MCPTT_SIGNED_XML = 'APPLICATION_VND_3GPP_MCPTT_SIGNED_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.mcptt-ue-config+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_MCPTT_UE_CONFIG_XML = 'APPLICATION_VND_3GPP_MCPTT_UE_CONFIG_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.mcptt-ue-init-config+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_MCPTT_UE_INIT_CONFIG_XML = 'APPLICATION_VND_3GPP_MCPTT_UE_INIT_CONFIG_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.mcptt-user-profile+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_MCPTT_USER_PROFILE_XML = 'APPLICATION_VND_3GPP_MCPTT_USER_PROFILE_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.mcvideo-affiliation-command+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_MCVIDEO_AFFILIATION_COMMAND_XML = 'APPLICATION_VND_3GPP_MCVIDEO_AFFILIATION_COMMAND_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.mcvideo-info+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_MCVIDEO_INFO_XML = 'APPLICATION_VND_3GPP_MCVIDEO_INFO_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.mcvideo-location-info+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_MCVIDEO_LOCATION_INFO_XML = 'APPLICATION_VND_3GPP_MCVIDEO_LOCATION_INFO_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.mcvideo-mbms-usage-info+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_MCVIDEO_MBMS_USAGE_INFO_XML = 'APPLICATION_VND_3GPP_MCVIDEO_MBMS_USAGE_INFO_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.mcvideo-regroup+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_MCVIDEO_REGROUP_XML = 'APPLICATION_VND_3GPP_MCVIDEO_REGROUP_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.mcvideo-service-config+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_MCVIDEO_SERVICE_CONFIG_XML = 'APPLICATION_VND_3GPP_MCVIDEO_SERVICE_CONFIG_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.mcvideo-transmission-request+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_MCVIDEO_TRANSMISSION_REQUEST_XML = 'APPLICATION_VND_3GPP_MCVIDEO_TRANSMISSION_REQUEST_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.mcvideo-ue-config+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_MCVIDEO_UE_CONFIG_XML = 'APPLICATION_VND_3GPP_MCVIDEO_UE_CONFIG_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.mcvideo-user-profile+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_MCVIDEO_USER_PROFILE_XML = 'APPLICATION_VND_3GPP_MCVIDEO_USER_PROFILE_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.mid-call+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_MID_CALL_XML = 'APPLICATION_VND_3GPP_MID_CALL_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.ngap', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_NGAP = 'APPLICATION_VND_3GPP_NGAP';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.pfcp', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_PFCP = 'APPLICATION_VND_3GPP_PFCP';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.pic-bw-large', extensions: ['plb'], category: 'application')]
    case APPLICATION_VND_3GPP_PIC_BW_LARGE = 'APPLICATION_VND_3GPP_PIC_BW_LARGE';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.pic-bw-small', extensions: ['psb'], category: 'application')]
    case APPLICATION_VND_3GPP_PIC_BW_SMALL = 'APPLICATION_VND_3GPP_PIC_BW_SMALL';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.pic-bw-var', extensions: ['pvb'], category: 'application')]
    case APPLICATION_VND_3GPP_PIC_BW_VAR = 'APPLICATION_VND_3GPP_PIC_BW_VAR';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.pinapp-info+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_PINAPP_INFO_XML = 'APPLICATION_VND_3GPP_PINAPP_INFO_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.s1ap', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_S1AP = 'APPLICATION_VND_3GPP_S1AP';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.seal-app-comm-requirements-info+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_SEAL_APP_COMM_REQUIREMENTS_INFO_XML = 'APPLICATION_VND_3GPP_SEAL_APP_COMM_REQUIREMENTS_INFO_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.seal-data-delivery-info+cbor', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_SEAL_DATA_DELIVERY_INFO_CBOR = 'APPLICATION_VND_3GPP_SEAL_DATA_DELIVERY_INFO_CBOR';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.seal-data-delivery-info+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_SEAL_DATA_DELIVERY_INFO_XML = 'APPLICATION_VND_3GPP_SEAL_DATA_DELIVERY_INFO_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.seal-group-doc+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_SEAL_GROUP_DOC_XML = 'APPLICATION_VND_3GPP_SEAL_GROUP_DOC_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.seal-info+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_SEAL_INFO_XML = 'APPLICATION_VND_3GPP_SEAL_INFO_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.seal-location-info+cbor', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_SEAL_LOCATION_INFO_CBOR = 'APPLICATION_VND_3GPP_SEAL_LOCATION_INFO_CBOR';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.seal-location-info+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_SEAL_LOCATION_INFO_XML = 'APPLICATION_VND_3GPP_SEAL_LOCATION_INFO_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.seal-mbms-usage-info+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_SEAL_MBMS_USAGE_INFO_XML = 'APPLICATION_VND_3GPP_SEAL_MBMS_USAGE_INFO_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.seal-mbs-usage-info+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_SEAL_MBS_USAGE_INFO_XML = 'APPLICATION_VND_3GPP_SEAL_MBS_USAGE_INFO_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.seal-network-qos-management-info+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_SEAL_NETWORK_QOS_MANAGEMENT_INFO_XML = 'APPLICATION_VND_3GPP_SEAL_NETWORK_QOS_MANAGEMENT_INFO_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.seal-network-resource-info+cbor', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_SEAL_NETWORK_RESOURCE_INFO_CBOR = 'APPLICATION_VND_3GPP_SEAL_NETWORK_RESOURCE_INFO_CBOR';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.seal-ue-config-info+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_SEAL_UE_CONFIG_INFO_XML = 'APPLICATION_VND_3GPP_SEAL_UE_CONFIG_INFO_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.seal-unicast-info+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_SEAL_UNICAST_INFO_XML = 'APPLICATION_VND_3GPP_SEAL_UNICAST_INFO_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.seal-user-profile-info+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_SEAL_USER_PROFILE_INFO_XML = 'APPLICATION_VND_3GPP_SEAL_USER_PROFILE_INFO_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.sms', extensions: ['sms'], category: 'application')]
    case APPLICATION_VND_3GPP_SMS = 'APPLICATION_VND_3GPP_SMS';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.sms+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_SMS_XML = 'APPLICATION_VND_3GPP_SMS_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.srvcc-ext+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_SRVCC_EXT_XML = 'APPLICATION_VND_3GPP_SRVCC_EXT_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.srvcc-info+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_SRVCC_INFO_XML = 'APPLICATION_VND_3GPP_SRVCC_INFO_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.state-and-event-info+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_STATE_AND_EVENT_INFO_XML = 'APPLICATION_VND_3GPP_STATE_AND_EVENT_INFO_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.ussd+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_USSD_XML = 'APPLICATION_VND_3GPP_USSD_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.v2x', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_V2X = 'APPLICATION_VND_3GPP_V2X';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp.vae-info+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP_VAE_INFO_XML = 'APPLICATION_VND_3GPP_VAE_INFO_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp2.bcmcsinfo+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_3GPP2_BCMCSINFO_XML = 'APPLICATION_VND_3GPP2_BCMCSINFO_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp2.sms', extensions: ['sms'], category: 'application')]
    case APPLICATION_VND_3GPP2_SMS = 'APPLICATION_VND_3GPP2_SMS';

    #[MimeTypeInfo(mimeType: 'application/vnd.3gpp2.tcap', extensions: ['tcap'], category: 'application')]
    case APPLICATION_VND_3GPP2_TCAP = 'APPLICATION_VND_3GPP2_TCAP';

    #[MimeTypeInfo(mimeType: 'application/vnd.3lightssoftware.imagescal', extensions: ['imgcal'], category: 'application')]
    case APPLICATION_VND_3LIGHTSSOFTWARE_IMAGESCAL = 'APPLICATION_VND_3LIGHTSSOFTWARE_IMAGESCAL';

    #[MimeTypeInfo(mimeType: 'application/vnd.3m.post-it-notes', extensions: ['pwn'], category: 'application')]
    case APPLICATION_VND_3M_POST_IT_NOTES = 'APPLICATION_VND_3M_POST_IT_NOTES';

    #[MimeTypeInfo(mimeType: 'application/vnd.accpac.simply.aso', extensions: ['aso'], category: 'application')]
    case APPLICATION_VND_ACCPAC_SIMPLY_ASO = 'APPLICATION_VND_ACCPAC_SIMPLY_ASO';

    #[MimeTypeInfo(mimeType: 'application/vnd.accpac.simply.imp', extensions: ['imp'], category: 'application')]
    case APPLICATION_VND_ACCPAC_SIMPLY_IMP = 'APPLICATION_VND_ACCPAC_SIMPLY_IMP';

    #[MimeTypeInfo(mimeType: 'application/vnd.acm.addressxfer+json', extensions: [], category: 'application')]
    case APPLICATION_VND_ACM_ADDRESSXFER_JSON = 'APPLICATION_VND_ACM_ADDRESSXFER_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.acm.chatbot+json', extensions: [], category: 'application')]
    case APPLICATION_VND_ACM_CHATBOT_JSON = 'APPLICATION_VND_ACM_CHATBOT_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.acucobol', extensions: ['acu'], category: 'application')]
    case APPLICATION_VND_ACUCOBOL = 'APPLICATION_VND_ACUCOBOL';

    #[MimeTypeInfo(mimeType: 'application/vnd.acucorp', extensions: ['atc', 'acutc'], category: 'application')]
    case APPLICATION_VND_ACUCORP = 'APPLICATION_VND_ACUCORP';

    #[MimeTypeInfo(mimeType: 'application/vnd.adobe.air-application-installer-package+zip', extensions: ['air'], category: 'application')]
    case APPLICATION_VND_ADOBE_AIR_APPLICATION_INSTALLER_PACKAGE_ZIP = 'APPLICATION_VND_ADOBE_AIR_APPLICATION_INSTALLER_PACKAGE_ZIP';

    #[MimeTypeInfo(mimeType: 'application/vnd.adobe.flash.movie', extensions: ['swf'], category: 'application')]
    case APPLICATION_VND_ADOBE_FLASH_MOVIE = 'APPLICATION_VND_ADOBE_FLASH_MOVIE';

    #[MimeTypeInfo(mimeType: 'application/vnd.adobe.formscentral.fcdt', extensions: ['fcdt'], category: 'application')]
    case APPLICATION_VND_ADOBE_FORMSCENTRAL_FCDT = 'APPLICATION_VND_ADOBE_FORMSCENTRAL_FCDT';

    #[MimeTypeInfo(mimeType: 'application/vnd.adobe.fxp', extensions: ['fxp', 'fxpl'], category: 'application')]
    case APPLICATION_VND_ADOBE_FXP = 'APPLICATION_VND_ADOBE_FXP';

    #[MimeTypeInfo(mimeType: 'application/vnd.adobe.partial-upload', extensions: [], category: 'application')]
    case APPLICATION_VND_ADOBE_PARTIAL_UPLOAD = 'APPLICATION_VND_ADOBE_PARTIAL_UPLOAD';

    #[MimeTypeInfo(mimeType: 'application/vnd.adobe.xdp+xml', extensions: ['xdp'], category: 'application')]
    case APPLICATION_VND_ADOBE_XDP_XML = 'APPLICATION_VND_ADOBE_XDP_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.adobe.xfdf', extensions: ['xfdf'], category: 'application')]
    case APPLICATION_VND_ADOBE_XFDF = 'APPLICATION_VND_ADOBE_XFDF';

    #[MimeTypeInfo(mimeType: 'application/vnd.aether.imp', extensions: [], category: 'application')]
    case APPLICATION_VND_AETHER_IMP = 'APPLICATION_VND_AETHER_IMP';

    #[MimeTypeInfo(mimeType: 'application/vnd.afpc.afplinedata', extensions: [], category: 'application')]
    case APPLICATION_VND_AFPC_AFPLINEDATA = 'APPLICATION_VND_AFPC_AFPLINEDATA';

    #[MimeTypeInfo(mimeType: 'application/vnd.afpc.afplinedata-pagedef', extensions: [], category: 'application')]
    case APPLICATION_VND_AFPC_AFPLINEDATA_PAGEDEF = 'APPLICATION_VND_AFPC_AFPLINEDATA_PAGEDEF';

    #[MimeTypeInfo(mimeType: 'application/vnd.afpc.cmoca-cmresource', extensions: [], category: 'application')]
    case APPLICATION_VND_AFPC_CMOCA_CMRESOURCE = 'APPLICATION_VND_AFPC_CMOCA_CMRESOURCE';

    #[MimeTypeInfo(mimeType: 'application/vnd.afpc.foca-charset', extensions: [], category: 'application')]
    case APPLICATION_VND_AFPC_FOCA_CHARSET = 'APPLICATION_VND_AFPC_FOCA_CHARSET';

    #[MimeTypeInfo(mimeType: 'application/vnd.afpc.foca-codedfont', extensions: [], category: 'application')]
    case APPLICATION_VND_AFPC_FOCA_CODEDFONT = 'APPLICATION_VND_AFPC_FOCA_CODEDFONT';

    #[MimeTypeInfo(mimeType: 'application/vnd.afpc.foca-codepage', extensions: [], category: 'application')]
    case APPLICATION_VND_AFPC_FOCA_CODEPAGE = 'APPLICATION_VND_AFPC_FOCA_CODEPAGE';

    #[MimeTypeInfo(mimeType: 'application/vnd.afpc.modca', extensions: ['list3820', 'listafp', 'afp', 'pseg3820'], category: 'application')]
    case APPLICATION_VND_AFPC_MODCA = 'APPLICATION_VND_AFPC_MODCA';

    #[MimeTypeInfo(mimeType: 'application/vnd.afpc.modca-cmtable', extensions: [], category: 'application')]
    case APPLICATION_VND_AFPC_MODCA_CMTABLE = 'APPLICATION_VND_AFPC_MODCA_CMTABLE';

    #[MimeTypeInfo(mimeType: 'application/vnd.afpc.modca-formdef', extensions: [], category: 'application')]
    case APPLICATION_VND_AFPC_MODCA_FORMDEF = 'APPLICATION_VND_AFPC_MODCA_FORMDEF';

    #[MimeTypeInfo(mimeType: 'application/vnd.afpc.modca-mediummap', extensions: [], category: 'application')]
    case APPLICATION_VND_AFPC_MODCA_MEDIUMMAP = 'APPLICATION_VND_AFPC_MODCA_MEDIUMMAP';

    #[MimeTypeInfo(mimeType: 'application/vnd.afpc.modca-objectcontainer', extensions: [], category: 'application')]
    case APPLICATION_VND_AFPC_MODCA_OBJECTCONTAINER = 'APPLICATION_VND_AFPC_MODCA_OBJECTCONTAINER';

    #[MimeTypeInfo(mimeType: 'application/vnd.afpc.modca-overlay', extensions: ['ovl'], category: 'application')]
    case APPLICATION_VND_AFPC_MODCA_OVERLAY = 'APPLICATION_VND_AFPC_MODCA_OVERLAY';

    #[MimeTypeInfo(mimeType: 'application/vnd.afpc.modca-pagesegment', extensions: ['psg'], category: 'application')]
    case APPLICATION_VND_AFPC_MODCA_PAGESEGMENT = 'APPLICATION_VND_AFPC_MODCA_PAGESEGMENT';

    #[MimeTypeInfo(mimeType: 'application/vnd.age', extensions: ['age'], category: 'application')]
    case APPLICATION_VND_AGE = 'APPLICATION_VND_AGE';

    #[MimeTypeInfo(mimeType: 'application/vnd.ah-barcode', extensions: [], category: 'application')]
    case APPLICATION_VND_AH_BARCODE = 'APPLICATION_VND_AH_BARCODE';

    #[MimeTypeInfo(mimeType: 'application/vnd.ahead.space', extensions: ['ahead'], category: 'application')]
    case APPLICATION_VND_AHEAD_SPACE = 'APPLICATION_VND_AHEAD_SPACE';

    #[MimeTypeInfo(mimeType: 'application/vnd.airzip.filesecure.azf', extensions: ['azf'], category: 'application')]
    case APPLICATION_VND_AIRZIP_FILESECURE_AZF = 'APPLICATION_VND_AIRZIP_FILESECURE_AZF';

    #[MimeTypeInfo(mimeType: 'application/vnd.airzip.filesecure.azs', extensions: ['azs'], category: 'application')]
    case APPLICATION_VND_AIRZIP_FILESECURE_AZS = 'APPLICATION_VND_AIRZIP_FILESECURE_AZS';

    #[MimeTypeInfo(mimeType: 'application/vnd.amadeus+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_AMADEUS_JSON = 'APPLICATION_VND_AMADEUS_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.amazon.ebook', extensions: ['azw'], category: 'application')]
    case APPLICATION_VND_AMAZON_EBOOK = 'APPLICATION_VND_AMAZON_EBOOK';

    #[MimeTypeInfo(mimeType: 'application/vnd.amazon.mobi8-ebook', extensions: ['azw3'], category: 'application')]
    case APPLICATION_VND_AMAZON_MOBI8_EBOOK = 'APPLICATION_VND_AMAZON_MOBI8_EBOOK';

    #[MimeTypeInfo(mimeType: 'application/vnd.americandynamics.acc', extensions: ['acc'], category: 'application')]
    case APPLICATION_VND_AMERICANDYNAMICS_ACC = 'APPLICATION_VND_AMERICANDYNAMICS_ACC';

    #[MimeTypeInfo(mimeType: 'application/vnd.amiga.ami', extensions: ['ami'], category: 'application')]
    case APPLICATION_VND_AMIGA_AMI = 'APPLICATION_VND_AMIGA_AMI';

    #[MimeTypeInfo(mimeType: 'application/vnd.amundsen.maze+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_AMUNDSEN_MAZE_XML = 'APPLICATION_VND_AMUNDSEN_MAZE_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.android.ota', extensions: ['ota'], category: 'application')]
    case APPLICATION_VND_ANDROID_OTA = 'APPLICATION_VND_ANDROID_OTA';

    #[MimeTypeInfo(mimeType: 'application/vnd.android.package-archive', extensions: ['apk'], category: 'application')]
    case APPLICATION_VND_ANDROID_PACKAGE_ARCHIVE = 'APPLICATION_VND_ANDROID_PACKAGE_ARCHIVE';

    #[MimeTypeInfo(mimeType: 'application/vnd.anki', extensions: ['apkg'], category: 'application')]
    case APPLICATION_VND_ANKI = 'APPLICATION_VND_ANKI';

    #[MimeTypeInfo(mimeType: 'application/vnd.anser-web-certificate-issue-initiation', extensions: ['cii'], category: 'application')]
    case APPLICATION_VND_ANSER_WEB_CERTIFICATE_ISSUE_INITIATION = 'APPLICATION_VND_ANSER_WEB_CERTIFICATE_ISSUE_INITIATION';

    #[MimeTypeInfo(mimeType: 'application/vnd.anser-web-funds-transfer-initiation', extensions: ['fti'], category: 'application')]
    case APPLICATION_VND_ANSER_WEB_FUNDS_TRANSFER_INITIATION = 'APPLICATION_VND_ANSER_WEB_FUNDS_TRANSFER_INITIATION';

    #[MimeTypeInfo(mimeType: 'application/vnd.antix.game-component', extensions: ['atx'], category: 'application')]
    case APPLICATION_VND_ANTIX_GAME_COMPONENT = 'APPLICATION_VND_ANTIX_GAME_COMPONENT';

    #[MimeTypeInfo(mimeType: 'application/vnd.apache.arrow.file', extensions: ['arrow'], category: 'application')]
    case APPLICATION_VND_APACHE_ARROW_FILE = 'APPLICATION_VND_APACHE_ARROW_FILE';

    #[MimeTypeInfo(mimeType: 'application/vnd.apache.arrow.stream', extensions: ['arrows'], category: 'application')]
    case APPLICATION_VND_APACHE_ARROW_STREAM = 'APPLICATION_VND_APACHE_ARROW_STREAM';

    #[MimeTypeInfo(mimeType: 'application/vnd.apache.parquet', extensions: ['parquet'], category: 'application')]
    case APPLICATION_VND_APACHE_PARQUET = 'APPLICATION_VND_APACHE_PARQUET';

    #[MimeTypeInfo(mimeType: 'application/vnd.apache.thrift.binary', extensions: [], category: 'application')]
    case APPLICATION_VND_APACHE_THRIFT_BINARY = 'APPLICATION_VND_APACHE_THRIFT_BINARY';

    #[MimeTypeInfo(mimeType: 'application/vnd.apache.thrift.compact', extensions: [], category: 'application')]
    case APPLICATION_VND_APACHE_THRIFT_COMPACT = 'APPLICATION_VND_APACHE_THRIFT_COMPACT';

    #[MimeTypeInfo(mimeType: 'application/vnd.apache.thrift.json', extensions: [], category: 'application')]
    case APPLICATION_VND_APACHE_THRIFT_JSON = 'APPLICATION_VND_APACHE_THRIFT_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.apexlang', extensions: ['apexlang', 'apex', 'axdl'], category: 'application')]
    case APPLICATION_VND_APEXLANG = 'APPLICATION_VND_APEXLANG';

    #[MimeTypeInfo(mimeType: 'application/vnd.api+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_API_JSON = 'APPLICATION_VND_API_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.aplextor.warrp+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_APLEXTOR_WARRP_JSON = 'APPLICATION_VND_APLEXTOR_WARRP_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.apothekende.reservation+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_APOTHEKENDE_RESERVATION_JSON = 'APPLICATION_VND_APOTHEKENDE_RESERVATION_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.apple.installer+xml', extensions: ['mpkg', 'dist', 'distz', 'pkg'], category: 'application')]
    case APPLICATION_VND_APPLE_INSTALLER_XML = 'APPLICATION_VND_APPLE_INSTALLER_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.apple.keynote', extensions: ['key'], category: 'application')]
    case APPLICATION_VND_APPLE_KEYNOTE = 'APPLICATION_VND_APPLE_KEYNOTE';

    #[MimeTypeInfo(mimeType: 'application/vnd.apple.mpegurl', extensions: ['m3u8', 'm3u'], category: 'application')]
    case APPLICATION_VND_APPLE_MPEGURL = 'APPLICATION_VND_APPLE_MPEGURL';

    #[MimeTypeInfo(mimeType: 'application/vnd.apple.numbers', extensions: ['numbers'], category: 'application')]
    case APPLICATION_VND_APPLE_NUMBERS = 'APPLICATION_VND_APPLE_NUMBERS';

    #[MimeTypeInfo(mimeType: 'application/vnd.apple.pages', extensions: ['pages'], category: 'application')]
    case APPLICATION_VND_APPLE_PAGES = 'APPLICATION_VND_APPLE_PAGES';

    #[MimeTypeInfo(mimeType: 'application/vnd.apple.pkpass', extensions: ['pkpass'], category: 'application')]
    case APPLICATION_VND_APPLE_PKPASS = 'APPLICATION_VND_APPLE_PKPASS';

    #[MimeTypeInfo(mimeType: 'application/vnd.arastra.swi', extensions: ['swi'], category: 'application')]
    case APPLICATION_VND_ARASTRA_SWI = 'APPLICATION_VND_ARASTRA_SWI';

    #[MimeTypeInfo(mimeType: 'application/vnd.aristanetworks.swi', extensions: ['swi'], category: 'application')]
    case APPLICATION_VND_ARISTANETWORKS_SWI = 'APPLICATION_VND_ARISTANETWORKS_SWI';

    #[MimeTypeInfo(mimeType: 'application/vnd.artisan+json', extensions: ['artisan'], category: 'application')]
    case APPLICATION_VND_ARTISAN_JSON = 'APPLICATION_VND_ARTISAN_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.artsquare', extensions: [], category: 'application')]
    case APPLICATION_VND_ARTSQUARE = 'APPLICATION_VND_ARTSQUARE';

    #[MimeTypeInfo(mimeType: 'application/vnd.astraea-software.iota', extensions: ['iota'], category: 'application')]
    case APPLICATION_VND_ASTRAEA_SOFTWARE_IOTA = 'APPLICATION_VND_ASTRAEA_SOFTWARE_IOTA';

    #[MimeTypeInfo(mimeType: 'application/vnd.audiograph', extensions: ['aep'], category: 'application')]
    case APPLICATION_VND_AUDIOGRAPH = 'APPLICATION_VND_AUDIOGRAPH';

    #[MimeTypeInfo(mimeType: 'application/vnd.autodesk.fbx', extensions: ['fbx'], category: 'application')]
    case APPLICATION_VND_AUTODESK_FBX = 'APPLICATION_VND_AUTODESK_FBX';

    #[MimeTypeInfo(mimeType: 'application/vnd.autopackage', extensions: ['package'], category: 'application')]
    case APPLICATION_VND_AUTOPACKAGE = 'APPLICATION_VND_AUTOPACKAGE';

    #[MimeTypeInfo(mimeType: 'application/vnd.avalon+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_AVALON_JSON = 'APPLICATION_VND_AVALON_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.avistar+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_AVISTAR_XML = 'APPLICATION_VND_AVISTAR_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.balsamiq.bmml+xml', extensions: ['bmml'], category: 'application')]
    case APPLICATION_VND_BALSAMIQ_BMML_XML = 'APPLICATION_VND_BALSAMIQ_BMML_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.balsamiq.bmpr', extensions: ['bmpr'], category: 'application')]
    case APPLICATION_VND_BALSAMIQ_BMPR = 'APPLICATION_VND_BALSAMIQ_BMPR';

    #[MimeTypeInfo(mimeType: 'application/vnd.banana-accounting', extensions: ['ac2'], category: 'application')]
    case APPLICATION_VND_BANANA_ACCOUNTING = 'APPLICATION_VND_BANANA_ACCOUNTING';

    #[MimeTypeInfo(mimeType: 'application/vnd.bbf.usp.error', extensions: [], category: 'application')]
    case APPLICATION_VND_BBF_USP_ERROR = 'APPLICATION_VND_BBF_USP_ERROR';

    #[MimeTypeInfo(mimeType: 'application/vnd.bbf.usp.msg', extensions: [], category: 'application')]
    case APPLICATION_VND_BBF_USP_MSG = 'APPLICATION_VND_BBF_USP_MSG';

    #[MimeTypeInfo(mimeType: 'application/vnd.bbf.usp.msg+json', extensions: [], category: 'application')]
    case APPLICATION_VND_BBF_USP_MSG_JSON = 'APPLICATION_VND_BBF_USP_MSG_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.bekitzur-stech+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_BEKITZUR_STECH_JSON = 'APPLICATION_VND_BEKITZUR_STECH_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.belightsoft.lhzd+zip', extensions: ['lhzd'], category: 'application')]
    case APPLICATION_VND_BELIGHTSOFT_LHZD_ZIP = 'APPLICATION_VND_BELIGHTSOFT_LHZD_ZIP';

    #[MimeTypeInfo(mimeType: 'application/vnd.belightsoft.lhzl+zip', extensions: ['lhzl'], category: 'application')]
    case APPLICATION_VND_BELIGHTSOFT_LHZL_ZIP = 'APPLICATION_VND_BELIGHTSOFT_LHZL_ZIP';

    #[MimeTypeInfo(mimeType: 'application/vnd.bint.med-content', extensions: [], category: 'application')]
    case APPLICATION_VND_BINT_MED_CONTENT = 'APPLICATION_VND_BINT_MED_CONTENT';

    #[MimeTypeInfo(mimeType: 'application/vnd.biopax.rdf+xml', extensions: ['owl'], category: 'application')]
    case APPLICATION_VND_BIOPAX_RDF_XML = 'APPLICATION_VND_BIOPAX_RDF_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.blink-idb-value-wrapper', extensions: [], category: 'application')]
    case APPLICATION_VND_BLINK_IDB_VALUE_WRAPPER = 'APPLICATION_VND_BLINK_IDB_VALUE_WRAPPER';

    #[MimeTypeInfo(mimeType: 'application/vnd.blueice.multipass', extensions: ['mpm'], category: 'application')]
    case APPLICATION_VND_BLUEICE_MULTIPASS = 'APPLICATION_VND_BLUEICE_MULTIPASS';

    #[MimeTypeInfo(mimeType: 'application/vnd.bluetooth.ep.oob', extensions: ['ep'], category: 'application')]
    case APPLICATION_VND_BLUETOOTH_EP_OOB = 'APPLICATION_VND_BLUETOOTH_EP_OOB';

    #[MimeTypeInfo(mimeType: 'application/vnd.bluetooth.le.oob', extensions: ['le'], category: 'application')]
    case APPLICATION_VND_BLUETOOTH_LE_OOB = 'APPLICATION_VND_BLUETOOTH_LE_OOB';

    #[MimeTypeInfo(mimeType: 'application/vnd.bmi', extensions: ['bmi'], category: 'application')]
    case APPLICATION_VND_BMI = 'APPLICATION_VND_BMI';

    #[MimeTypeInfo(mimeType: 'application/vnd.bpf', extensions: ['bpf'], category: 'application')]
    case APPLICATION_VND_BPF = 'APPLICATION_VND_BPF';

    #[MimeTypeInfo(mimeType: 'application/vnd.bpf3', extensions: ['bpf'], category: 'application')]
    case APPLICATION_VND_BPF3 = 'APPLICATION_VND_BPF3';

    #[MimeTypeInfo(mimeType: 'application/vnd.businessobjects', extensions: ['rep'], category: 'application')]
    case APPLICATION_VND_BUSINESSOBJECTS = 'APPLICATION_VND_BUSINESSOBJECTS';

    #[MimeTypeInfo(mimeType: 'application/vnd.byu.uapi+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_BYU_UAPI_JSON = 'APPLICATION_VND_BYU_UAPI_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.bzip3', extensions: ['bz3'], category: 'application')]
    case APPLICATION_VND_BZIP3 = 'APPLICATION_VND_BZIP3';

    #[MimeTypeInfo(mimeType: 'application/vnd.c3voc.schedule+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_C3VOC_SCHEDULE_XML = 'APPLICATION_VND_C3VOC_SCHEDULE_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.cab-jscript', extensions: [], category: 'application')]
    case APPLICATION_VND_CAB_JSCRIPT = 'APPLICATION_VND_CAB_JSCRIPT';

    #[MimeTypeInfo(mimeType: 'application/vnd.canon-cpdl', extensions: [], category: 'application')]
    case APPLICATION_VND_CANON_CPDL = 'APPLICATION_VND_CANON_CPDL';

    #[MimeTypeInfo(mimeType: 'application/vnd.canon-lips', extensions: [], category: 'application')]
    case APPLICATION_VND_CANON_LIPS = 'APPLICATION_VND_CANON_LIPS';

    #[MimeTypeInfo(mimeType: 'application/vnd.capasystems-pg+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_CAPASYSTEMS_PG_JSON = 'APPLICATION_VND_CAPASYSTEMS_PG_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.cel', extensions: ['cel'], category: 'application')]
    case APPLICATION_VND_CEL = 'APPLICATION_VND_CEL';

    #[MimeTypeInfo(mimeType: 'application/vnd.cendio.thinlinc.clientconf', extensions: ['tlclient'], category: 'application')]
    case APPLICATION_VND_CENDIO_THINLINC_CLIENTCONF = 'APPLICATION_VND_CENDIO_THINLINC_CLIENTCONF';

    #[MimeTypeInfo(mimeType: 'application/vnd.century-systems.tcp_stream', extensions: [], category: 'application')]
    case APPLICATION_VND_CENTURY_SYSTEMS_TCP_STREAM = 'APPLICATION_VND_CENTURY_SYSTEMS_TCP_STREAM';

    #[MimeTypeInfo(mimeType: 'application/vnd.chemdraw+xml', extensions: ['cdxml'], category: 'application')]
    case APPLICATION_VND_CHEMDRAW_XML = 'APPLICATION_VND_CHEMDRAW_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.chess-pgn', extensions: ['pgn'], category: 'application')]
    case APPLICATION_VND_CHESS_PGN = 'APPLICATION_VND_CHESS_PGN';

    #[MimeTypeInfo(mimeType: 'application/vnd.chipnuts.karaoke-mmd', extensions: ['mmd'], category: 'application')]
    case APPLICATION_VND_CHIPNUTS_KARAOKE_MMD = 'APPLICATION_VND_CHIPNUTS_KARAOKE_MMD';

    #[MimeTypeInfo(mimeType: 'application/vnd.ciedi', extensions: [], category: 'application')]
    case APPLICATION_VND_CIEDI = 'APPLICATION_VND_CIEDI';

    #[MimeTypeInfo(mimeType: 'application/vnd.cinderella', extensions: ['cdy'], category: 'application')]
    case APPLICATION_VND_CINDERELLA = 'APPLICATION_VND_CINDERELLA';

    #[MimeTypeInfo(mimeType: 'application/vnd.cirpack.isdn-ext', extensions: [], category: 'application')]
    case APPLICATION_VND_CIRPACK_ISDN_EXT = 'APPLICATION_VND_CIRPACK_ISDN_EXT';

    #[MimeTypeInfo(mimeType: 'application/vnd.citationstyles.style+xml', extensions: ['csl'], category: 'application')]
    case APPLICATION_VND_CITATIONSTYLES_STYLE_XML = 'APPLICATION_VND_CITATIONSTYLES_STYLE_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.claymore', extensions: ['cla'], category: 'application')]
    case APPLICATION_VND_CLAYMORE = 'APPLICATION_VND_CLAYMORE';

    #[MimeTypeInfo(mimeType: 'application/vnd.cloanto.rp9', extensions: ['rp9'], category: 'application')]
    case APPLICATION_VND_CLOANTO_RP9 = 'APPLICATION_VND_CLOANTO_RP9';

    #[MimeTypeInfo(mimeType: 'application/vnd.clonk.c4group', extensions: ['c4g', 'c4d', 'c4f', 'c4p', 'c4u'], category: 'application')]
    case APPLICATION_VND_CLONK_C4GROUP = 'APPLICATION_VND_CLONK_C4GROUP';

    #[MimeTypeInfo(mimeType: 'application/vnd.cluetrust.cartomobile-config', extensions: ['c11amc'], category: 'application')]
    case APPLICATION_VND_CLUETRUST_CARTOMOBILE_CONFIG = 'APPLICATION_VND_CLUETRUST_CARTOMOBILE_CONFIG';

    #[MimeTypeInfo(mimeType: 'application/vnd.cluetrust.cartomobile-config-pkg', extensions: ['c11amz'], category: 'application')]
    case APPLICATION_VND_CLUETRUST_CARTOMOBILE_CONFIG_PKG = 'APPLICATION_VND_CLUETRUST_CARTOMOBILE_CONFIG_PKG';

    #[MimeTypeInfo(mimeType: 'application/vnd.cncf.helm.chart.content.v1.tar+gzip', extensions: [], category: 'application')]
    case APPLICATION_VND_CNCF_HELM_CHART_CONTENT_V1_TAR_GZIP = 'APPLICATION_VND_CNCF_HELM_CHART_CONTENT_V1_TAR_GZIP';

    #[MimeTypeInfo(mimeType: 'application/vnd.cncf.helm.chart.provenance.v1.prov', extensions: ['text'], category: 'application')]
    case APPLICATION_VND_CNCF_HELM_CHART_PROVENANCE_V1_PROV = 'APPLICATION_VND_CNCF_HELM_CHART_PROVENANCE_V1_PROV';

    #[MimeTypeInfo(mimeType: 'application/vnd.cncf.helm.config.v1+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_CNCF_HELM_CONFIG_V1_JSON = 'APPLICATION_VND_CNCF_HELM_CONFIG_V1_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.coffeescript', extensions: ['coffee'], category: 'application')]
    case APPLICATION_VND_COFFEESCRIPT = 'APPLICATION_VND_COFFEESCRIPT';

    #[MimeTypeInfo(mimeType: 'application/vnd.collabio.xodocuments.document', extensions: ['xodt'], category: 'application')]
    case APPLICATION_VND_COLLABIO_XODOCUMENTS_DOCUMENT = 'APPLICATION_VND_COLLABIO_XODOCUMENTS_DOCUMENT';

    #[MimeTypeInfo(mimeType: 'application/vnd.collabio.xodocuments.document-template', extensions: ['xott'], category: 'application')]
    case APPLICATION_VND_COLLABIO_XODOCUMENTS_DOCUMENT_TEMPLATE = 'APPLICATION_VND_COLLABIO_XODOCUMENTS_DOCUMENT_TEMPLATE';

    #[MimeTypeInfo(mimeType: 'application/vnd.collabio.xodocuments.presentation', extensions: ['xodp'], category: 'application')]
    case APPLICATION_VND_COLLABIO_XODOCUMENTS_PRESENTATION = 'APPLICATION_VND_COLLABIO_XODOCUMENTS_PRESENTATION';

    #[MimeTypeInfo(mimeType: 'application/vnd.collabio.xodocuments.presentation-template', extensions: ['xotp'], category: 'application')]
    case APPLICATION_VND_COLLABIO_XODOCUMENTS_PRESENTATION_TEMPLATE = 'APPLICATION_VND_COLLABIO_XODOCUMENTS_PRESENTATION_TEMPLATE';

    #[MimeTypeInfo(mimeType: 'application/vnd.collabio.xodocuments.spreadsheet', extensions: ['xods'], category: 'application')]
    case APPLICATION_VND_COLLABIO_XODOCUMENTS_SPREADSHEET = 'APPLICATION_VND_COLLABIO_XODOCUMENTS_SPREADSHEET';

    #[MimeTypeInfo(mimeType: 'application/vnd.collabio.xodocuments.spreadsheet-template', extensions: ['xots'], category: 'application')]
    case APPLICATION_VND_COLLABIO_XODOCUMENTS_SPREADSHEET_TEMPLATE = 'APPLICATION_VND_COLLABIO_XODOCUMENTS_SPREADSHEET_TEMPLATE';

    #[MimeTypeInfo(mimeType: 'application/vnd.collection+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_COLLECTION_JSON = 'APPLICATION_VND_COLLECTION_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.collection.doc+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_COLLECTION_DOC_JSON = 'APPLICATION_VND_COLLECTION_DOC_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.collection.next+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_COLLECTION_NEXT_JSON = 'APPLICATION_VND_COLLECTION_NEXT_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.comicbook+zip', extensions: ['cbz'], category: 'application')]
    case APPLICATION_VND_COMICBOOK_ZIP = 'APPLICATION_VND_COMICBOOK_ZIP';

    #[MimeTypeInfo(mimeType: 'application/vnd.comicbook-rar', extensions: ['cbr'], category: 'application')]
    case APPLICATION_VND_COMICBOOK_RAR = 'APPLICATION_VND_COMICBOOK_RAR';

    #[MimeTypeInfo(mimeType: 'application/vnd.commerce-battelle', extensions: ['ica', 'icf', 'icd', 'icc'], category: 'application')]
    case APPLICATION_VND_COMMERCE_BATTELLE = 'APPLICATION_VND_COMMERCE_BATTELLE';

    #[MimeTypeInfo(mimeType: 'application/vnd.commonspace', extensions: ['csp', 'cst'], category: 'application')]
    case APPLICATION_VND_COMMONSPACE = 'APPLICATION_VND_COMMONSPACE';

    #[MimeTypeInfo(mimeType: 'application/vnd.contact.cmsg', extensions: ['cdbcmsg'], category: 'application')]
    case APPLICATION_VND_CONTACT_CMSG = 'APPLICATION_VND_CONTACT_CMSG';

    #[MimeTypeInfo(mimeType: 'application/vnd.coreos.ignition+json', extensions: ['ign', 'ignition'], category: 'application')]
    case APPLICATION_VND_COREOS_IGNITION_JSON = 'APPLICATION_VND_COREOS_IGNITION_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.cosmocaller', extensions: ['cmc'], category: 'application')]
    case APPLICATION_VND_COSMOCALLER = 'APPLICATION_VND_COSMOCALLER';

    #[MimeTypeInfo(mimeType: 'application/vnd.crick.clicker', extensions: ['clkx'], category: 'application')]
    case APPLICATION_VND_CRICK_CLICKER = 'APPLICATION_VND_CRICK_CLICKER';

    #[MimeTypeInfo(mimeType: 'application/vnd.crick.clicker.keyboard', extensions: ['clkk'], category: 'application')]
    case APPLICATION_VND_CRICK_CLICKER_KEYBOARD = 'APPLICATION_VND_CRICK_CLICKER_KEYBOARD';

    #[MimeTypeInfo(mimeType: 'application/vnd.crick.clicker.palette', extensions: ['clkp'], category: 'application')]
    case APPLICATION_VND_CRICK_CLICKER_PALETTE = 'APPLICATION_VND_CRICK_CLICKER_PALETTE';

    #[MimeTypeInfo(mimeType: 'application/vnd.crick.clicker.template', extensions: ['clkt'], category: 'application')]
    case APPLICATION_VND_CRICK_CLICKER_TEMPLATE = 'APPLICATION_VND_CRICK_CLICKER_TEMPLATE';

    #[MimeTypeInfo(mimeType: 'application/vnd.crick.clicker.wordbank', extensions: ['clkw'], category: 'application')]
    case APPLICATION_VND_CRICK_CLICKER_WORDBANK = 'APPLICATION_VND_CRICK_CLICKER_WORDBANK';

    #[MimeTypeInfo(mimeType: 'application/vnd.criticaltools.wbs+xml', extensions: ['wbs'], category: 'application')]
    case APPLICATION_VND_CRITICALTOOLS_WBS_XML = 'APPLICATION_VND_CRITICALTOOLS_WBS_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.cryptii.pipe+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_CRYPTII_PIPE_JSON = 'APPLICATION_VND_CRYPTII_PIPE_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.crypto-shade-file', extensions: ['ssv', 'ssvc'], category: 'application')]
    case APPLICATION_VND_CRYPTO_SHADE_FILE = 'APPLICATION_VND_CRYPTO_SHADE_FILE';

    #[MimeTypeInfo(mimeType: 'application/vnd.cryptomator.encrypted', extensions: ['c9r', 'c9s'], category: 'application')]
    case APPLICATION_VND_CRYPTOMATOR_ENCRYPTED = 'APPLICATION_VND_CRYPTOMATOR_ENCRYPTED';

    #[MimeTypeInfo(mimeType: 'application/vnd.cryptomator.vault', extensions: ['cryptomator'], category: 'application')]
    case APPLICATION_VND_CRYPTOMATOR_VAULT = 'APPLICATION_VND_CRYPTOMATOR_VAULT';

    #[MimeTypeInfo(mimeType: 'application/vnd.ctc-posml', extensions: ['pml'], category: 'application')]
    case APPLICATION_VND_CTC_POSML = 'APPLICATION_VND_CTC_POSML';

    #[MimeTypeInfo(mimeType: 'application/vnd.ctct.ws+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_CTCT_WS_XML = 'APPLICATION_VND_CTCT_WS_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.cups-pdf', extensions: [], category: 'application')]
    case APPLICATION_VND_CUPS_PDF = 'APPLICATION_VND_CUPS_PDF';

    #[MimeTypeInfo(mimeType: 'application/vnd.cups-postscript', extensions: [], category: 'application')]
    case APPLICATION_VND_CUPS_POSTSCRIPT = 'APPLICATION_VND_CUPS_POSTSCRIPT';

    #[MimeTypeInfo(mimeType: 'application/vnd.cups-ppd', extensions: ['ppd', 'ppd.gz'], category: 'application')]
    case APPLICATION_VND_CUPS_PPD = 'APPLICATION_VND_CUPS_PPD';

    #[MimeTypeInfo(mimeType: 'application/vnd.cups-raster', extensions: [], category: 'application')]
    case APPLICATION_VND_CUPS_RASTER = 'APPLICATION_VND_CUPS_RASTER';

    #[MimeTypeInfo(mimeType: 'application/vnd.cups-raw', extensions: [], category: 'application')]
    case APPLICATION_VND_CUPS_RAW = 'APPLICATION_VND_CUPS_RAW';

    #[MimeTypeInfo(mimeType: 'application/vnd.curl', extensions: ['curl'], category: 'application')]
    case APPLICATION_VND_CURL = 'APPLICATION_VND_CURL';

    #[MimeTypeInfo(mimeType: 'application/vnd.curl.car', extensions: ['car'], category: 'application')]
    case APPLICATION_VND_CURL_CAR = 'APPLICATION_VND_CURL_CAR';

    #[MimeTypeInfo(mimeType: 'application/vnd.curl.pcurl', extensions: ['pcurl'], category: 'application')]
    case APPLICATION_VND_CURL_PCURL = 'APPLICATION_VND_CURL_PCURL';

    #[MimeTypeInfo(mimeType: 'application/vnd.cyan.dean.root+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_CYAN_DEAN_ROOT_XML = 'APPLICATION_VND_CYAN_DEAN_ROOT_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.cybank', extensions: [], category: 'application')]
    case APPLICATION_VND_CYBANK = 'APPLICATION_VND_CYBANK';

    #[MimeTypeInfo(mimeType: 'application/vnd.cyclonedx+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_CYCLONEDX_JSON = 'APPLICATION_VND_CYCLONEDX_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.cyclonedx+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_CYCLONEDX_XML = 'APPLICATION_VND_CYCLONEDX_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.d2l.coursepackage1p0+zip', extensions: [], category: 'application')]
    case APPLICATION_VND_D2L_COURSEPACKAGE1P0_ZIP = 'APPLICATION_VND_D2L_COURSEPACKAGE1P0_ZIP';

    #[MimeTypeInfo(mimeType: 'application/vnd.d3m-dataset', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_D3M_DATASET = 'APPLICATION_VND_D3M_DATASET';

    #[MimeTypeInfo(mimeType: 'application/vnd.d3m-problem', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_D3M_PROBLEM = 'APPLICATION_VND_D3M_PROBLEM';

    #[MimeTypeInfo(mimeType: 'application/vnd.dart', extensions: ['dart'], category: 'application')]
    case APPLICATION_VND_DART = 'APPLICATION_VND_DART';

    #[MimeTypeInfo(mimeType: 'application/vnd.data-vision.rdz', extensions: ['rdz'], category: 'application')]
    case APPLICATION_VND_DATA_VISION_RDZ = 'APPLICATION_VND_DATA_VISION_RDZ';

    #[MimeTypeInfo(mimeType: 'application/vnd.datalog', extensions: ['dl'], category: 'application')]
    case APPLICATION_VND_DATALOG = 'APPLICATION_VND_DATALOG';

    #[MimeTypeInfo(mimeType: 'application/vnd.datapackage+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_DATAPACKAGE_JSON = 'APPLICATION_VND_DATAPACKAGE_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.dataresource+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_DATARESOURCE_JSON = 'APPLICATION_VND_DATARESOURCE_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.dbf', extensions: ['dbf'], category: 'application')]
    case APPLICATION_VND_DBF = 'APPLICATION_VND_DBF';

    #[MimeTypeInfo(mimeType: 'application/vnd.dcmp+xml', extensions: ['dcmp'], category: 'application')]
    case APPLICATION_VND_DCMP_XML = 'APPLICATION_VND_DCMP_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.debian.binary-package', extensions: ['deb', 'udeb'], category: 'application')]
    case APPLICATION_VND_DEBIAN_BINARY_PACKAGE = 'APPLICATION_VND_DEBIAN_BINARY_PACKAGE';

    #[MimeTypeInfo(mimeType: 'application/vnd.dece.data', extensions: ['uvf', 'uvvf', 'uvd', 'uvvd'], category: 'application')]
    case APPLICATION_VND_DECE_DATA = 'APPLICATION_VND_DECE_DATA';

    #[MimeTypeInfo(mimeType: 'application/vnd.dece.ttml+xml', extensions: ['uvt', 'uvvt'], category: 'application')]
    case APPLICATION_VND_DECE_TTML_XML = 'APPLICATION_VND_DECE_TTML_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.dece.unspecified', extensions: ['uvx', 'uvvx'], category: 'application')]
    case APPLICATION_VND_DECE_UNSPECIFIED = 'APPLICATION_VND_DECE_UNSPECIFIED';

    #[MimeTypeInfo(mimeType: 'application/vnd.dece.zip', extensions: ['uvz', 'uvvz'], category: 'application')]
    case APPLICATION_VND_DECE_ZIP = 'APPLICATION_VND_DECE_ZIP';

    #[MimeTypeInfo(mimeType: 'application/vnd.denovo.fcselayout-link', extensions: ['fe_launch'], category: 'application')]
    case APPLICATION_VND_DENOVO_FCSELAYOUT_LINK = 'APPLICATION_VND_DENOVO_FCSELAYOUT_LINK';

    #[MimeTypeInfo(mimeType: 'application/vnd.desmume.movie', extensions: ['dsm'], category: 'application')]
    case APPLICATION_VND_DESMUME_MOVIE = 'APPLICATION_VND_DESMUME_MOVIE';

    #[MimeTypeInfo(mimeType: 'application/vnd.dir-bi.plate-dl-nosuffix', extensions: [], category: 'application')]
    case APPLICATION_VND_DIR_BI_PLATE_DL_NOSUFFIX = 'APPLICATION_VND_DIR_BI_PLATE_DL_NOSUFFIX';

    #[MimeTypeInfo(mimeType: 'application/vnd.dm.delegation+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_DM_DELEGATION_XML = 'APPLICATION_VND_DM_DELEGATION_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.dna', extensions: ['dna'], category: 'application')]
    case APPLICATION_VND_DNA = 'APPLICATION_VND_DNA';

    #[MimeTypeInfo(mimeType: 'application/vnd.document+json', extensions: ['docjson'], category: 'application')]
    case APPLICATION_VND_DOCUMENT_JSON = 'APPLICATION_VND_DOCUMENT_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.dolby.mlp', extensions: ['mlp'], category: 'application')]
    case APPLICATION_VND_DOLBY_MLP = 'APPLICATION_VND_DOLBY_MLP';

    #[MimeTypeInfo(mimeType: 'application/vnd.dolby.mobile.1', extensions: [], category: 'application')]
    case APPLICATION_VND_DOLBY_MOBILE_1 = 'APPLICATION_VND_DOLBY_MOBILE_1';

    #[MimeTypeInfo(mimeType: 'application/vnd.dolby.mobile.2', extensions: [], category: 'application')]
    case APPLICATION_VND_DOLBY_MOBILE_2 = 'APPLICATION_VND_DOLBY_MOBILE_2';

    #[MimeTypeInfo(mimeType: 'application/vnd.doremir.scorecloud-binary-document', extensions: ['scld'], category: 'application')]
    case APPLICATION_VND_DOREMIR_SCORECLOUD_BINARY_DOCUMENT = 'APPLICATION_VND_DOREMIR_SCORECLOUD_BINARY_DOCUMENT';

    #[MimeTypeInfo(mimeType: 'application/vnd.dpgraph', extensions: ['dpg'], category: 'application')]
    case APPLICATION_VND_DPGRAPH = 'APPLICATION_VND_DPGRAPH';

    #[MimeTypeInfo(mimeType: 'application/vnd.dreamfactory', extensions: ['dfac'], category: 'application')]
    case APPLICATION_VND_DREAMFACTORY = 'APPLICATION_VND_DREAMFACTORY';

    #[MimeTypeInfo(mimeType: 'application/vnd.drive+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_DRIVE_JSON = 'APPLICATION_VND_DRIVE_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.ds-keypoint', extensions: ['kpxx'], category: 'application')]
    case APPLICATION_VND_DS_KEYPOINT = 'APPLICATION_VND_DS_KEYPOINT';

    #[MimeTypeInfo(mimeType: 'application/vnd.dtg.local', extensions: [], category: 'application')]
    case APPLICATION_VND_DTG_LOCAL = 'APPLICATION_VND_DTG_LOCAL';

    #[MimeTypeInfo(mimeType: 'application/vnd.dtg.local.flash', extensions: ['fla'], category: 'application')]
    case APPLICATION_VND_DTG_LOCAL_FLASH = 'APPLICATION_VND_DTG_LOCAL_FLASH';

    #[MimeTypeInfo(mimeType: 'application/vnd.dtg.local.html', extensions: ['html'], category: 'application')]
    case APPLICATION_VND_DTG_LOCAL_HTML = 'APPLICATION_VND_DTG_LOCAL_HTML';

    #[MimeTypeInfo(mimeType: 'application/vnd.dvb.ait', extensions: ['ait'], category: 'application')]
    case APPLICATION_VND_DVB_AIT = 'APPLICATION_VND_DVB_AIT';

    #[MimeTypeInfo(mimeType: 'application/vnd.dvb.dvbisl+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_DVB_DVBISL_XML = 'APPLICATION_VND_DVB_DVBISL_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.dvb.dvbj', extensions: ['class'], category: 'application')]
    case APPLICATION_VND_DVB_DVBJ = 'APPLICATION_VND_DVB_DVBJ';

    #[MimeTypeInfo(mimeType: 'application/vnd.dvb.esgcontainer', extensions: [], category: 'application')]
    case APPLICATION_VND_DVB_ESGCONTAINER = 'APPLICATION_VND_DVB_ESGCONTAINER';

    #[MimeTypeInfo(mimeType: 'application/vnd.dvb.ipdcdftnotifaccess', extensions: [], category: 'application')]
    case APPLICATION_VND_DVB_IPDCDFTNOTIFACCESS = 'APPLICATION_VND_DVB_IPDCDFTNOTIFACCESS';

    #[MimeTypeInfo(mimeType: 'application/vnd.dvb.ipdcesgaccess', extensions: [], category: 'application')]
    case APPLICATION_VND_DVB_IPDCESGACCESS = 'APPLICATION_VND_DVB_IPDCESGACCESS';

    #[MimeTypeInfo(mimeType: 'application/vnd.dvb.ipdcesgaccess2', extensions: [], category: 'application')]
    case APPLICATION_VND_DVB_IPDCESGACCESS2 = 'APPLICATION_VND_DVB_IPDCESGACCESS2';

    #[MimeTypeInfo(mimeType: 'application/vnd.dvb.ipdcesgpdd', extensions: [], category: 'application')]
    case APPLICATION_VND_DVB_IPDCESGPDD = 'APPLICATION_VND_DVB_IPDCESGPDD';

    #[MimeTypeInfo(mimeType: 'application/vnd.dvb.ipdcroaming', extensions: [], category: 'application')]
    case APPLICATION_VND_DVB_IPDCROAMING = 'APPLICATION_VND_DVB_IPDCROAMING';

    #[MimeTypeInfo(mimeType: 'application/vnd.dvb.iptv.alfec-base', extensions: [], category: 'application')]
    case APPLICATION_VND_DVB_IPTV_ALFEC_BASE = 'APPLICATION_VND_DVB_IPTV_ALFEC_BASE';

    #[MimeTypeInfo(mimeType: 'application/vnd.dvb.iptv.alfec-enhancement', extensions: [], category: 'application')]
    case APPLICATION_VND_DVB_IPTV_ALFEC_ENHANCEMENT = 'APPLICATION_VND_DVB_IPTV_ALFEC_ENHANCEMENT';

    #[MimeTypeInfo(mimeType: 'application/vnd.dvb.notif-aggregate-root+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_DVB_NOTIF_AGGREGATE_ROOT_XML = 'APPLICATION_VND_DVB_NOTIF_AGGREGATE_ROOT_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.dvb.notif-container+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_DVB_NOTIF_CONTAINER_XML = 'APPLICATION_VND_DVB_NOTIF_CONTAINER_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.dvb.notif-generic+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_DVB_NOTIF_GENERIC_XML = 'APPLICATION_VND_DVB_NOTIF_GENERIC_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.dvb.notif-ia-msglist+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_DVB_NOTIF_IA_MSGLIST_XML = 'APPLICATION_VND_DVB_NOTIF_IA_MSGLIST_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.dvb.notif-ia-registration-request+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_DVB_NOTIF_IA_REGISTRATION_REQUEST_XML = 'APPLICATION_VND_DVB_NOTIF_IA_REGISTRATION_REQUEST_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.dvb.notif-ia-registration-response+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_DVB_NOTIF_IA_REGISTRATION_RESPONSE_XML = 'APPLICATION_VND_DVB_NOTIF_IA_REGISTRATION_RESPONSE_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.dvb.notif-init+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_DVB_NOTIF_INIT_XML = 'APPLICATION_VND_DVB_NOTIF_INIT_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.dvb.pfr', extensions: ['pfr'], category: 'application')]
    case APPLICATION_VND_DVB_PFR = 'APPLICATION_VND_DVB_PFR';

    #[MimeTypeInfo(mimeType: 'application/vnd.dvb.service', extensions: ['svc'], category: 'application')]
    case APPLICATION_VND_DVB_SERVICE = 'APPLICATION_VND_DVB_SERVICE';

    #[MimeTypeInfo(mimeType: 'application/vnd.dxr', extensions: ['dxr'], category: 'application')]
    case APPLICATION_VND_DXR = 'APPLICATION_VND_DXR';

    #[MimeTypeInfo(mimeType: 'application/vnd.dynageo', extensions: ['geo'], category: 'application')]
    case APPLICATION_VND_DYNAGEO = 'APPLICATION_VND_DYNAGEO';

    #[MimeTypeInfo(mimeType: 'application/vnd.dzr', extensions: ['dzr'], category: 'application')]
    case APPLICATION_VND_DZR = 'APPLICATION_VND_DZR';

    #[MimeTypeInfo(mimeType: 'application/vnd.easykaraoke.cdgdownload', extensions: ['zip'], category: 'application')]
    case APPLICATION_VND_EASYKARAOKE_CDGDOWNLOAD = 'APPLICATION_VND_EASYKARAOKE_CDGDOWNLOAD';

    #[MimeTypeInfo(mimeType: 'application/vnd.ecdis-update', extensions: [], category: 'application')]
    case APPLICATION_VND_ECDIS_UPDATE = 'APPLICATION_VND_ECDIS_UPDATE';

    #[MimeTypeInfo(mimeType: 'application/vnd.ecip.rlp', extensions: [], category: 'application')]
    case APPLICATION_VND_ECIP_RLP = 'APPLICATION_VND_ECIP_RLP';

    #[MimeTypeInfo(mimeType: 'application/vnd.eclipse.ditto+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_ECLIPSE_DITTO_JSON = 'APPLICATION_VND_ECLIPSE_DITTO_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.ecowin.chart', extensions: ['mag'], category: 'application')]
    case APPLICATION_VND_ECOWIN_CHART = 'APPLICATION_VND_ECOWIN_CHART';

    #[MimeTypeInfo(mimeType: 'application/vnd.ecowin.filerequest', extensions: [], category: 'application')]
    case APPLICATION_VND_ECOWIN_FILEREQUEST = 'APPLICATION_VND_ECOWIN_FILEREQUEST';

    #[MimeTypeInfo(mimeType: 'application/vnd.ecowin.fileupdate', extensions: [], category: 'application')]
    case APPLICATION_VND_ECOWIN_FILEUPDATE = 'APPLICATION_VND_ECOWIN_FILEUPDATE';

    #[MimeTypeInfo(mimeType: 'application/vnd.ecowin.series', extensions: [], category: 'application')]
    case APPLICATION_VND_ECOWIN_SERIES = 'APPLICATION_VND_ECOWIN_SERIES';

    #[MimeTypeInfo(mimeType: 'application/vnd.ecowin.seriesrequest', extensions: [], category: 'application')]
    case APPLICATION_VND_ECOWIN_SERIESREQUEST = 'APPLICATION_VND_ECOWIN_SERIESREQUEST';

    #[MimeTypeInfo(mimeType: 'application/vnd.ecowin.seriesupdate', extensions: [], category: 'application')]
    case APPLICATION_VND_ECOWIN_SERIESUPDATE = 'APPLICATION_VND_ECOWIN_SERIESUPDATE';

    #[MimeTypeInfo(mimeType: 'application/vnd.efi.img', extensions: ['img'], category: 'application')]
    case APPLICATION_VND_EFI_IMG = 'APPLICATION_VND_EFI_IMG';

    #[MimeTypeInfo(mimeType: 'application/vnd.efi.iso', extensions: ['iso'], category: 'application')]
    case APPLICATION_VND_EFI_ISO = 'APPLICATION_VND_EFI_ISO';

    #[MimeTypeInfo(mimeType: 'application/vnd.eln+zip', extensions: ['eln'], category: 'application')]
    case APPLICATION_VND_ELN_ZIP = 'APPLICATION_VND_ELN_ZIP';

    #[MimeTypeInfo(mimeType: 'application/vnd.emclient.accessrequest+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_EMCLIENT_ACCESSREQUEST_XML = 'APPLICATION_VND_EMCLIENT_ACCESSREQUEST_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.enliven', extensions: ['nml'], category: 'application')]
    case APPLICATION_VND_ENLIVEN = 'APPLICATION_VND_ENLIVEN';

    #[MimeTypeInfo(mimeType: 'application/vnd.enphase.envoy', extensions: [], category: 'application')]
    case APPLICATION_VND_ENPHASE_ENVOY = 'APPLICATION_VND_ENPHASE_ENVOY';

    #[MimeTypeInfo(mimeType: 'application/vnd.eprints.data+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_EPRINTS_DATA_XML = 'APPLICATION_VND_EPRINTS_DATA_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.epson.esf', extensions: ['esf'], category: 'application')]
    case APPLICATION_VND_EPSON_ESF = 'APPLICATION_VND_EPSON_ESF';

    #[MimeTypeInfo(mimeType: 'application/vnd.epson.msf', extensions: ['msf'], category: 'application')]
    case APPLICATION_VND_EPSON_MSF = 'APPLICATION_VND_EPSON_MSF';

    #[MimeTypeInfo(mimeType: 'application/vnd.epson.quickanime', extensions: ['qam'], category: 'application')]
    case APPLICATION_VND_EPSON_QUICKANIME = 'APPLICATION_VND_EPSON_QUICKANIME';

    #[MimeTypeInfo(mimeType: 'application/vnd.epson.salt', extensions: ['slt'], category: 'application')]
    case APPLICATION_VND_EPSON_SALT = 'APPLICATION_VND_EPSON_SALT';

    #[MimeTypeInfo(mimeType: 'application/vnd.epson.ssf', extensions: ['ssf'], category: 'application')]
    case APPLICATION_VND_EPSON_SSF = 'APPLICATION_VND_EPSON_SSF';

    #[MimeTypeInfo(mimeType: 'application/vnd.ericsson.quickcall', extensions: ['qcall', 'qca'], category: 'application')]
    case APPLICATION_VND_ERICSSON_QUICKCALL = 'APPLICATION_VND_ERICSSON_QUICKCALL';

    #[MimeTypeInfo(mimeType: 'application/vnd.erofs', extensions: ['erofs', '0fs'], category: 'application')]
    case APPLICATION_VND_EROFS = 'APPLICATION_VND_EROFS';

    #[MimeTypeInfo(mimeType: 'application/vnd.espass-espass+zip', extensions: ['espass'], category: 'application')]
    case APPLICATION_VND_ESPASS_ESPASS_ZIP = 'APPLICATION_VND_ESPASS_ESPASS_ZIP';

    #[MimeTypeInfo(mimeType: 'application/vnd.eszigno3+xml', extensions: ['es3', 'et3'], category: 'application')]
    case APPLICATION_VND_ESZIGNO3_XML = 'APPLICATION_VND_ESZIGNO3_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.etsi.aoc+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_ETSI_AOC_XML = 'APPLICATION_VND_ETSI_AOC_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.etsi.asic-e+zip', extensions: ['asice', 'sce'], category: 'application')]
    case APPLICATION_VND_ETSI_ASIC_E_ZIP = 'APPLICATION_VND_ETSI_ASIC_E_ZIP';

    #[MimeTypeInfo(mimeType: 'application/vnd.etsi.asic-s+zip', extensions: ['asics', 'scs'], category: 'application')]
    case APPLICATION_VND_ETSI_ASIC_S_ZIP = 'APPLICATION_VND_ETSI_ASIC_S_ZIP';

    #[MimeTypeInfo(mimeType: 'application/vnd.etsi.cug+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_ETSI_CUG_XML = 'APPLICATION_VND_ETSI_CUG_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.etsi.iptvcommand+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_ETSI_IPTVCOMMAND_XML = 'APPLICATION_VND_ETSI_IPTVCOMMAND_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.etsi.iptvdiscovery+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_ETSI_IPTVDISCOVERY_XML = 'APPLICATION_VND_ETSI_IPTVDISCOVERY_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.etsi.iptvprofile+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_ETSI_IPTVPROFILE_XML = 'APPLICATION_VND_ETSI_IPTVPROFILE_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.etsi.iptvsad-bc+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_ETSI_IPTVSAD_BC_XML = 'APPLICATION_VND_ETSI_IPTVSAD_BC_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.etsi.iptvsad-cod+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_ETSI_IPTVSAD_COD_XML = 'APPLICATION_VND_ETSI_IPTVSAD_COD_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.etsi.iptvsad-npvr+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_ETSI_IPTVSAD_NPVR_XML = 'APPLICATION_VND_ETSI_IPTVSAD_NPVR_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.etsi.iptvservice+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_ETSI_IPTVSERVICE_XML = 'APPLICATION_VND_ETSI_IPTVSERVICE_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.etsi.iptvsync+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_ETSI_IPTVSYNC_XML = 'APPLICATION_VND_ETSI_IPTVSYNC_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.etsi.iptvueprofile+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_ETSI_IPTVUEPROFILE_XML = 'APPLICATION_VND_ETSI_IPTVUEPROFILE_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.etsi.mcid+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_ETSI_MCID_XML = 'APPLICATION_VND_ETSI_MCID_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.etsi.mheg5', extensions: [], category: 'application')]
    case APPLICATION_VND_ETSI_MHEG5 = 'APPLICATION_VND_ETSI_MHEG5';

    #[MimeTypeInfo(mimeType: 'application/vnd.etsi.overload-control-policy-dataset+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_ETSI_OVERLOAD_CONTROL_POLICY_DATASET_XML = 'APPLICATION_VND_ETSI_OVERLOAD_CONTROL_POLICY_DATASET_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.etsi.pstn+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_ETSI_PSTN_XML = 'APPLICATION_VND_ETSI_PSTN_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.etsi.sci+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_ETSI_SCI_XML = 'APPLICATION_VND_ETSI_SCI_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.etsi.simservs+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_ETSI_SIMSERVS_XML = 'APPLICATION_VND_ETSI_SIMSERVS_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.etsi.timestamp-token', extensions: ['tst'], category: 'application')]
    case APPLICATION_VND_ETSI_TIMESTAMP_TOKEN = 'APPLICATION_VND_ETSI_TIMESTAMP_TOKEN';

    #[MimeTypeInfo(mimeType: 'application/vnd.etsi.tsl+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_ETSI_TSL_XML = 'APPLICATION_VND_ETSI_TSL_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.etsi.tsl.der', extensions: [], category: 'application')]
    case APPLICATION_VND_ETSI_TSL_DER = 'APPLICATION_VND_ETSI_TSL_DER';

    #[MimeTypeInfo(mimeType: 'application/vnd.eu.kasparian.car+json', extensions: ['carjson'], category: 'application')]
    case APPLICATION_VND_EU_KASPARIAN_CAR_JSON = 'APPLICATION_VND_EU_KASPARIAN_CAR_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.eudora.data', extensions: [], category: 'application')]
    case APPLICATION_VND_EUDORA_DATA = 'APPLICATION_VND_EUDORA_DATA';

    #[MimeTypeInfo(mimeType: 'application/vnd.evolv.ecig.profile', extensions: ['ecigprofile'], category: 'application')]
    case APPLICATION_VND_EVOLV_ECIG_PROFILE = 'APPLICATION_VND_EVOLV_ECIG_PROFILE';

    #[MimeTypeInfo(mimeType: 'application/vnd.evolv.ecig.settings', extensions: ['ecig'], category: 'application')]
    case APPLICATION_VND_EVOLV_ECIG_SETTINGS = 'APPLICATION_VND_EVOLV_ECIG_SETTINGS';

    #[MimeTypeInfo(mimeType: 'application/vnd.evolv.ecig.theme', extensions: ['ecigtheme'], category: 'application')]
    case APPLICATION_VND_EVOLV_ECIG_THEME = 'APPLICATION_VND_EVOLV_ECIG_THEME';

    #[MimeTypeInfo(mimeType: 'application/vnd.exstream-empower+zip', extensions: ['mpw'], category: 'application')]
    case APPLICATION_VND_EXSTREAM_EMPOWER_ZIP = 'APPLICATION_VND_EXSTREAM_EMPOWER_ZIP';

    #[MimeTypeInfo(mimeType: 'application/vnd.exstream-package', extensions: ['pub', 'pkg'], category: 'application')]
    case APPLICATION_VND_EXSTREAM_PACKAGE = 'APPLICATION_VND_EXSTREAM_PACKAGE';

    #[MimeTypeInfo(mimeType: 'application/vnd.ezpix-album', extensions: ['ez2'], category: 'application')]
    case APPLICATION_VND_EZPIX_ALBUM = 'APPLICATION_VND_EZPIX_ALBUM';

    #[MimeTypeInfo(mimeType: 'application/vnd.ezpix-package', extensions: ['ez3'], category: 'application')]
    case APPLICATION_VND_EZPIX_PACKAGE = 'APPLICATION_VND_EZPIX_PACKAGE';

    #[MimeTypeInfo(mimeType: 'application/vnd.f-secure.mobile', extensions: [], category: 'application')]
    case APPLICATION_VND_F_SECURE_MOBILE = 'APPLICATION_VND_F_SECURE_MOBILE';

    #[MimeTypeInfo(mimeType: 'application/vnd.familysearch.gedcom+zip', extensions: ['gdz'], category: 'application')]
    case APPLICATION_VND_FAMILYSEARCH_GEDCOM_ZIP = 'APPLICATION_VND_FAMILYSEARCH_GEDCOM_ZIP';

    #[MimeTypeInfo(mimeType: 'application/vnd.fastcopy-disk-image', extensions: ['dim'], category: 'application')]
    case APPLICATION_VND_FASTCOPY_DISK_IMAGE = 'APPLICATION_VND_FASTCOPY_DISK_IMAGE';

    #[MimeTypeInfo(mimeType: 'application/vnd.fdf', extensions: ['fdf'], category: 'application')]
    case APPLICATION_VND_FDF = 'APPLICATION_VND_FDF';

    #[MimeTypeInfo(mimeType: 'application/vnd.fdsn.mseed', extensions: ['mseed', 'mseed3'], category: 'application')]
    case APPLICATION_VND_FDSN_MSEED = 'APPLICATION_VND_FDSN_MSEED';

    #[MimeTypeInfo(mimeType: 'application/vnd.fdsn.seed', extensions: ['seed', 'dataless'], category: 'application')]
    case APPLICATION_VND_FDSN_SEED = 'APPLICATION_VND_FDSN_SEED';

    #[MimeTypeInfo(mimeType: 'application/vnd.fdsn.stationxml+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_FDSN_STATIONXML_XML = 'APPLICATION_VND_FDSN_STATIONXML_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.ffsns', extensions: [], category: 'application')]
    case APPLICATION_VND_FFSNS = 'APPLICATION_VND_FFSNS';

    #[MimeTypeInfo(mimeType: 'application/vnd.fgb', extensions: ['fgb'], category: 'application')]
    case APPLICATION_VND_FGB = 'APPLICATION_VND_FGB';

    #[MimeTypeInfo(mimeType: 'application/vnd.ficlab.flb+zip', extensions: ['flb'], category: 'application')]
    case APPLICATION_VND_FICLAB_FLB_ZIP = 'APPLICATION_VND_FICLAB_FLB_ZIP';

    #[MimeTypeInfo(mimeType: 'application/vnd.filmit.zfc', extensions: ['zfc'], category: 'application')]
    case APPLICATION_VND_FILMIT_ZFC = 'APPLICATION_VND_FILMIT_ZFC';

    #[MimeTypeInfo(mimeType: 'application/vnd.fints', extensions: ['hbci', 'hbc', 'kom', 'upa', 'pkd'], category: 'application')]
    case APPLICATION_VND_FINTS = 'APPLICATION_VND_FINTS';

    #[MimeTypeInfo(mimeType: 'application/vnd.firemonkeys.cloudcell', extensions: [], category: 'application')]
    case APPLICATION_VND_FIREMONKEYS_CLOUDCELL = 'APPLICATION_VND_FIREMONKEYS_CLOUDCELL';

    #[MimeTypeInfo(mimeType: 'application/vnd.flographit', extensions: ['gph'], category: 'application')]
    case APPLICATION_VND_FLOGRAPHIT = 'APPLICATION_VND_FLOGRAPHIT';

    #[MimeTypeInfo(mimeType: 'application/vnd.fluxtime.clip', extensions: ['ftc'], category: 'application')]
    case APPLICATION_VND_FLUXTIME_CLIP = 'APPLICATION_VND_FLUXTIME_CLIP';

    #[MimeTypeInfo(mimeType: 'application/vnd.font-fontforge-sfd', extensions: ['sfd'], category: 'application')]
    case APPLICATION_VND_FONT_FONTFORGE_SFD = 'APPLICATION_VND_FONT_FONTFORGE_SFD';

    #[MimeTypeInfo(mimeType: 'application/vnd.framemaker', extensions: ['fm', 'frame', 'maker', 'book'], category: 'application')]
    case APPLICATION_VND_FRAMEMAKER = 'APPLICATION_VND_FRAMEMAKER';

    #[MimeTypeInfo(mimeType: 'application/vnd.freelog.comic', extensions: [], category: 'application')]
    case APPLICATION_VND_FREELOG_COMIC = 'APPLICATION_VND_FREELOG_COMIC';

    #[MimeTypeInfo(mimeType: 'application/vnd.frogans.fnc', extensions: ['fnc'], category: 'application')]
    case APPLICATION_VND_FROGANS_FNC = 'APPLICATION_VND_FROGANS_FNC';

    #[MimeTypeInfo(mimeType: 'application/vnd.frogans.ltf', extensions: ['ltf'], category: 'application')]
    case APPLICATION_VND_FROGANS_LTF = 'APPLICATION_VND_FROGANS_LTF';

    #[MimeTypeInfo(mimeType: 'application/vnd.fsc.weblaunch', extensions: ['fsc'], category: 'application')]
    case APPLICATION_VND_FSC_WEBLAUNCH = 'APPLICATION_VND_FSC_WEBLAUNCH';

    #[MimeTypeInfo(mimeType: 'application/vnd.fujifilm.fb.docuworks', extensions: ['xdw'], category: 'application')]
    case APPLICATION_VND_FUJIFILM_FB_DOCUWORKS = 'APPLICATION_VND_FUJIFILM_FB_DOCUWORKS';

    #[MimeTypeInfo(mimeType: 'application/vnd.fujifilm.fb.docuworks.binder', extensions: ['xbd'], category: 'application')]
    case APPLICATION_VND_FUJIFILM_FB_DOCUWORKS_BINDER = 'APPLICATION_VND_FUJIFILM_FB_DOCUWORKS_BINDER';

    #[MimeTypeInfo(mimeType: 'application/vnd.fujifilm.fb.docuworks.container', extensions: ['xct'], category: 'application')]
    case APPLICATION_VND_FUJIFILM_FB_DOCUWORKS_CONTAINER = 'APPLICATION_VND_FUJIFILM_FB_DOCUWORKS_CONTAINER';

    #[MimeTypeInfo(mimeType: 'application/vnd.fujifilm.fb.jfi+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_FUJIFILM_FB_JFI_XML = 'APPLICATION_VND_FUJIFILM_FB_JFI_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.fujitsu.oasys', extensions: ['oas'], category: 'application')]
    case APPLICATION_VND_FUJITSU_OASYS = 'APPLICATION_VND_FUJITSU_OASYS';

    #[MimeTypeInfo(mimeType: 'application/vnd.fujitsu.oasys2', extensions: ['oa2'], category: 'application')]
    case APPLICATION_VND_FUJITSU_OASYS2 = 'APPLICATION_VND_FUJITSU_OASYS2';

    #[MimeTypeInfo(mimeType: 'application/vnd.fujitsu.oasys3', extensions: ['oa3'], category: 'application')]
    case APPLICATION_VND_FUJITSU_OASYS3 = 'APPLICATION_VND_FUJITSU_OASYS3';

    #[MimeTypeInfo(mimeType: 'application/vnd.fujitsu.oasysgp', extensions: ['fg5'], category: 'application')]
    case APPLICATION_VND_FUJITSU_OASYSGP = 'APPLICATION_VND_FUJITSU_OASYSGP';

    #[MimeTypeInfo(mimeType: 'application/vnd.fujitsu.oasysprs', extensions: ['bh2'], category: 'application')]
    case APPLICATION_VND_FUJITSU_OASYSPRS = 'APPLICATION_VND_FUJITSU_OASYSPRS';

    #[MimeTypeInfo(mimeType: 'application/vnd.fujixerox.art-ex', extensions: [], category: 'application')]
    case APPLICATION_VND_FUJIXEROX_ART_EX = 'APPLICATION_VND_FUJIXEROX_ART_EX';

    #[MimeTypeInfo(mimeType: 'application/vnd.fujixerox.art4', extensions: [], category: 'application')]
    case APPLICATION_VND_FUJIXEROX_ART4 = 'APPLICATION_VND_FUJIXEROX_ART4';

    #[MimeTypeInfo(mimeType: 'application/vnd.fujixerox.ddd', extensions: ['ddd'], category: 'application')]
    case APPLICATION_VND_FUJIXEROX_DDD = 'APPLICATION_VND_FUJIXEROX_DDD';

    #[MimeTypeInfo(mimeType: 'application/vnd.fujixerox.docuworks', extensions: ['xdw'], category: 'application')]
    case APPLICATION_VND_FUJIXEROX_DOCUWORKS = 'APPLICATION_VND_FUJIXEROX_DOCUWORKS';

    #[MimeTypeInfo(mimeType: 'application/vnd.fujixerox.docuworks.binder', extensions: ['xbd'], category: 'application')]
    case APPLICATION_VND_FUJIXEROX_DOCUWORKS_BINDER = 'APPLICATION_VND_FUJIXEROX_DOCUWORKS_BINDER';

    #[MimeTypeInfo(mimeType: 'application/vnd.fujixerox.docuworks.container', extensions: ['xct'], category: 'application')]
    case APPLICATION_VND_FUJIXEROX_DOCUWORKS_CONTAINER = 'APPLICATION_VND_FUJIXEROX_DOCUWORKS_CONTAINER';

    #[MimeTypeInfo(mimeType: 'application/vnd.fujixerox.hbpl', extensions: [], category: 'application')]
    case APPLICATION_VND_FUJIXEROX_HBPL = 'APPLICATION_VND_FUJIXEROX_HBPL';

    #[MimeTypeInfo(mimeType: 'application/vnd.fut-misnet', extensions: [], category: 'application')]
    case APPLICATION_VND_FUT_MISNET = 'APPLICATION_VND_FUT_MISNET';

    #[MimeTypeInfo(mimeType: 'application/vnd.futoin+cbor', extensions: [], category: 'application')]
    case APPLICATION_VND_FUTOIN_CBOR = 'APPLICATION_VND_FUTOIN_CBOR';

    #[MimeTypeInfo(mimeType: 'application/vnd.futoin+json', extensions: [], category: 'application')]
    case APPLICATION_VND_FUTOIN_JSON = 'APPLICATION_VND_FUTOIN_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.fuzzysheet', extensions: ['fzs'], category: 'application')]
    case APPLICATION_VND_FUZZYSHEET = 'APPLICATION_VND_FUZZYSHEET';

    #[MimeTypeInfo(mimeType: 'application/vnd.g3pix.g3fc', extensions: ['g3fc', 'g3fc0', 'g3fc1', 'g3fc2', 'g3fc3', 'g3fc4', 'g3fc5', 'g3fc6', 'g3fc7', 'g3fc8', 'g3fc9'], category: 'application')]
    case APPLICATION_VND_G3PIX_G3FC = 'APPLICATION_VND_G3PIX_G3FC';

    #[MimeTypeInfo(mimeType: 'application/vnd.ga4gh.passport+jwt', extensions: [], category: 'application')]
    case APPLICATION_VND_GA4GH_PASSPORT_JWT = 'APPLICATION_VND_GA4GH_PASSPORT_JWT';

    #[MimeTypeInfo(mimeType: 'application/vnd.genomatix.tuxedo', extensions: ['txd'], category: 'application')]
    case APPLICATION_VND_GENOMATIX_TUXEDO = 'APPLICATION_VND_GENOMATIX_TUXEDO';

    #[MimeTypeInfo(mimeType: 'application/vnd.genozip', extensions: ['genozip'], category: 'application')]
    case APPLICATION_VND_GENOZIP = 'APPLICATION_VND_GENOZIP';

    #[MimeTypeInfo(mimeType: 'application/vnd.gentics.grd+json', extensions: ['grd'], category: 'application')]
    case APPLICATION_VND_GENTICS_GRD_JSON = 'APPLICATION_VND_GENTICS_GRD_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.gentoo.catmetadata+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_GENTOO_CATMETADATA_XML = 'APPLICATION_VND_GENTOO_CATMETADATA_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.gentoo.ebuild', extensions: ['ebuild'], category: 'application')]
    case APPLICATION_VND_GENTOO_EBUILD = 'APPLICATION_VND_GENTOO_EBUILD';

    #[MimeTypeInfo(mimeType: 'application/vnd.gentoo.eclass', extensions: ['eclass'], category: 'application')]
    case APPLICATION_VND_GENTOO_ECLASS = 'APPLICATION_VND_GENTOO_ECLASS';

    #[MimeTypeInfo(mimeType: 'application/vnd.gentoo.gpkg', extensions: ['gpkg.tar'], category: 'application')]
    case APPLICATION_VND_GENTOO_GPKG = 'APPLICATION_VND_GENTOO_GPKG';

    #[MimeTypeInfo(mimeType: 'application/vnd.gentoo.manifest', extensions: [], category: 'application')]
    case APPLICATION_VND_GENTOO_MANIFEST = 'APPLICATION_VND_GENTOO_MANIFEST';

    #[MimeTypeInfo(mimeType: 'application/vnd.gentoo.pkgmetadata+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_GENTOO_PKGMETADATA_XML = 'APPLICATION_VND_GENTOO_PKGMETADATA_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.gentoo.xpak', extensions: ['tbz2', 'xpak'], category: 'application')]
    case APPLICATION_VND_GENTOO_XPAK = 'APPLICATION_VND_GENTOO_XPAK';

    #[MimeTypeInfo(mimeType: 'application/vnd.geo+json', extensions: ['json', 'geojson'], category: 'application')]
    case APPLICATION_VND_GEO_JSON = 'APPLICATION_VND_GEO_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.geocube+xml', extensions: ['g3'], category: 'application')]
    case APPLICATION_VND_GEOCUBE_XML = 'APPLICATION_VND_GEOCUBE_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.geogebra.file', extensions: ['ggb'], category: 'application')]
    case APPLICATION_VND_GEOGEBRA_FILE = 'APPLICATION_VND_GEOGEBRA_FILE';

    #[MimeTypeInfo(mimeType: 'application/vnd.geogebra.pinboard', extensions: ['ggp'], category: 'application')]
    case APPLICATION_VND_GEOGEBRA_PINBOARD = 'APPLICATION_VND_GEOGEBRA_PINBOARD';

    #[MimeTypeInfo(mimeType: 'application/vnd.geogebra.slides', extensions: ['ggs'], category: 'application')]
    case APPLICATION_VND_GEOGEBRA_SLIDES = 'APPLICATION_VND_GEOGEBRA_SLIDES';

    #[MimeTypeInfo(mimeType: 'application/vnd.geogebra.tool', extensions: ['ggt'], category: 'application')]
    case APPLICATION_VND_GEOGEBRA_TOOL = 'APPLICATION_VND_GEOGEBRA_TOOL';

    #[MimeTypeInfo(mimeType: 'application/vnd.geometry-explorer', extensions: ['gex', 'gre'], category: 'application')]
    case APPLICATION_VND_GEOMETRY_EXPLORER = 'APPLICATION_VND_GEOMETRY_EXPLORER';

    #[MimeTypeInfo(mimeType: 'application/vnd.geonext', extensions: ['gxt'], category: 'application')]
    case APPLICATION_VND_GEONEXT = 'APPLICATION_VND_GEONEXT';

    #[MimeTypeInfo(mimeType: 'application/vnd.geoplan', extensions: ['g2w'], category: 'application')]
    case APPLICATION_VND_GEOPLAN = 'APPLICATION_VND_GEOPLAN';

    #[MimeTypeInfo(mimeType: 'application/vnd.geospace', extensions: ['g3w'], category: 'application')]
    case APPLICATION_VND_GEOSPACE = 'APPLICATION_VND_GEOSPACE';

    #[MimeTypeInfo(mimeType: 'application/vnd.gerber', extensions: ['gbr'], category: 'application')]
    case APPLICATION_VND_GERBER = 'APPLICATION_VND_GERBER';

    #[MimeTypeInfo(mimeType: 'application/vnd.globalplatform.card-content-mgt', extensions: [], category: 'application')]
    case APPLICATION_VND_GLOBALPLATFORM_CARD_CONTENT_MGT = 'APPLICATION_VND_GLOBALPLATFORM_CARD_CONTENT_MGT';

    #[MimeTypeInfo(mimeType: 'application/vnd.globalplatform.card-content-mgt-response', extensions: [], category: 'application')]
    case APPLICATION_VND_GLOBALPLATFORM_CARD_CONTENT_MGT_RESPONSE = 'APPLICATION_VND_GLOBALPLATFORM_CARD_CONTENT_MGT_RESPONSE';

    #[MimeTypeInfo(mimeType: 'application/vnd.gmx', extensions: ['gmx'], category: 'application')]
    case APPLICATION_VND_GMX = 'APPLICATION_VND_GMX';

    #[MimeTypeInfo(mimeType: 'application/vnd.gnu.taler.exchange+json', extensions: [], category: 'application')]
    case APPLICATION_VND_GNU_TALER_EXCHANGE_JSON = 'APPLICATION_VND_GNU_TALER_EXCHANGE_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.gnu.taler.merchant+json', extensions: [], category: 'application')]
    case APPLICATION_VND_GNU_TALER_MERCHANT_JSON = 'APPLICATION_VND_GNU_TALER_MERCHANT_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.google-apps.audio', extensions: [], category: 'application')]
    case APPLICATION_VND_GOOGLE_APPS_AUDIO = 'APPLICATION_VND_GOOGLE_APPS_AUDIO';

    #[MimeTypeInfo(mimeType: 'application/vnd.google-apps.document', extensions: ['gdoc'], category: 'application')]
    case APPLICATION_VND_GOOGLE_APPS_DOCUMENT = 'APPLICATION_VND_GOOGLE_APPS_DOCUMENT';

    #[MimeTypeInfo(mimeType: 'application/vnd.google-apps.drawing', extensions: ['gdraw'], category: 'application')]
    case APPLICATION_VND_GOOGLE_APPS_DRAWING = 'APPLICATION_VND_GOOGLE_APPS_DRAWING';

    #[MimeTypeInfo(mimeType: 'application/vnd.google-apps.drive-sdk', extensions: [], category: 'application')]
    case APPLICATION_VND_GOOGLE_APPS_DRIVE_SDK = 'APPLICATION_VND_GOOGLE_APPS_DRIVE_SDK';

    #[MimeTypeInfo(mimeType: 'application/vnd.google-apps.file', extensions: [], category: 'application')]
    case APPLICATION_VND_GOOGLE_APPS_FILE = 'APPLICATION_VND_GOOGLE_APPS_FILE';

    #[MimeTypeInfo(mimeType: 'application/vnd.google-apps.folder', extensions: [], category: 'application')]
    case APPLICATION_VND_GOOGLE_APPS_FOLDER = 'APPLICATION_VND_GOOGLE_APPS_FOLDER';

    #[MimeTypeInfo(mimeType: 'application/vnd.google-apps.form', extensions: ['gform'], category: 'application')]
    case APPLICATION_VND_GOOGLE_APPS_FORM = 'APPLICATION_VND_GOOGLE_APPS_FORM';

    #[MimeTypeInfo(mimeType: 'application/vnd.google-apps.fusiontable', extensions: [], category: 'application')]
    case APPLICATION_VND_GOOGLE_APPS_FUSIONTABLE = 'APPLICATION_VND_GOOGLE_APPS_FUSIONTABLE';

    #[MimeTypeInfo(mimeType: 'application/vnd.google-apps.jam', extensions: ['gjam'], category: 'application')]
    case APPLICATION_VND_GOOGLE_APPS_JAM = 'APPLICATION_VND_GOOGLE_APPS_JAM';

    #[MimeTypeInfo(mimeType: 'application/vnd.google-apps.mail-layout', extensions: [], category: 'application')]
    case APPLICATION_VND_GOOGLE_APPS_MAIL_LAYOUT = 'APPLICATION_VND_GOOGLE_APPS_MAIL_LAYOUT';

    #[MimeTypeInfo(mimeType: 'application/vnd.google-apps.map', extensions: ['gmap'], category: 'application')]
    case APPLICATION_VND_GOOGLE_APPS_MAP = 'APPLICATION_VND_GOOGLE_APPS_MAP';

    #[MimeTypeInfo(mimeType: 'application/vnd.google-apps.photo', extensions: [], category: 'application')]
    case APPLICATION_VND_GOOGLE_APPS_PHOTO = 'APPLICATION_VND_GOOGLE_APPS_PHOTO';

    #[MimeTypeInfo(mimeType: 'application/vnd.google-apps.presentation', extensions: ['gslides'], category: 'application')]
    case APPLICATION_VND_GOOGLE_APPS_PRESENTATION = 'APPLICATION_VND_GOOGLE_APPS_PRESENTATION';

    #[MimeTypeInfo(mimeType: 'application/vnd.google-apps.script', extensions: ['gscript'], category: 'application')]
    case APPLICATION_VND_GOOGLE_APPS_SCRIPT = 'APPLICATION_VND_GOOGLE_APPS_SCRIPT';

    #[MimeTypeInfo(mimeType: 'application/vnd.google-apps.shortcut', extensions: [], category: 'application')]
    case APPLICATION_VND_GOOGLE_APPS_SHORTCUT = 'APPLICATION_VND_GOOGLE_APPS_SHORTCUT';

    #[MimeTypeInfo(mimeType: 'application/vnd.google-apps.site', extensions: ['gsite'], category: 'application')]
    case APPLICATION_VND_GOOGLE_APPS_SITE = 'APPLICATION_VND_GOOGLE_APPS_SITE';

    #[MimeTypeInfo(mimeType: 'application/vnd.google-apps.spreadsheet', extensions: ['gsheet'], category: 'application')]
    case APPLICATION_VND_GOOGLE_APPS_SPREADSHEET = 'APPLICATION_VND_GOOGLE_APPS_SPREADSHEET';

    #[MimeTypeInfo(mimeType: 'application/vnd.google-apps.unknown', extensions: [], category: 'application')]
    case APPLICATION_VND_GOOGLE_APPS_UNKNOWN = 'APPLICATION_VND_GOOGLE_APPS_UNKNOWN';

    #[MimeTypeInfo(mimeType: 'application/vnd.google-apps.video', extensions: [], category: 'application')]
    case APPLICATION_VND_GOOGLE_APPS_VIDEO = 'APPLICATION_VND_GOOGLE_APPS_VIDEO';

    #[MimeTypeInfo(mimeType: 'application/vnd.google-earth.kml+xml', extensions: ['kml'], category: 'application')]
    case APPLICATION_VND_GOOGLE_EARTH_KML_XML = 'APPLICATION_VND_GOOGLE_EARTH_KML_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.google-earth.kmz', extensions: ['kmz'], category: 'application')]
    case APPLICATION_VND_GOOGLE_EARTH_KMZ = 'APPLICATION_VND_GOOGLE_EARTH_KMZ';

    #[MimeTypeInfo(mimeType: 'application/vnd.gov.sk.e-form+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_GOV_SK_E_FORM_XML = 'APPLICATION_VND_GOV_SK_E_FORM_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.gov.sk.e-form+zip', extensions: ['zip'], category: 'application')]
    case APPLICATION_VND_GOV_SK_E_FORM_ZIP = 'APPLICATION_VND_GOV_SK_E_FORM_ZIP';

    #[MimeTypeInfo(mimeType: 'application/vnd.gov.sk.xmldatacontainer+xml', extensions: ['xdcf'], category: 'application')]
    case APPLICATION_VND_GOV_SK_XMLDATACONTAINER_XML = 'APPLICATION_VND_GOV_SK_XMLDATACONTAINER_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.gpxsee.map+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_GPXSEE_MAP_XML = 'APPLICATION_VND_GPXSEE_MAP_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.grafeq', extensions: ['gqf', 'gqs'], category: 'application')]
    case APPLICATION_VND_GRAFEQ = 'APPLICATION_VND_GRAFEQ';

    #[MimeTypeInfo(mimeType: 'application/vnd.gridmp', extensions: [], category: 'application')]
    case APPLICATION_VND_GRIDMP = 'APPLICATION_VND_GRIDMP';

    #[MimeTypeInfo(mimeType: 'application/vnd.groove-account', extensions: ['gac'], category: 'application')]
    case APPLICATION_VND_GROOVE_ACCOUNT = 'APPLICATION_VND_GROOVE_ACCOUNT';

    #[MimeTypeInfo(mimeType: 'application/vnd.groove-help', extensions: ['ghf'], category: 'application')]
    case APPLICATION_VND_GROOVE_HELP = 'APPLICATION_VND_GROOVE_HELP';

    #[MimeTypeInfo(mimeType: 'application/vnd.groove-identity-message', extensions: ['gim'], category: 'application')]
    case APPLICATION_VND_GROOVE_IDENTITY_MESSAGE = 'APPLICATION_VND_GROOVE_IDENTITY_MESSAGE';

    #[MimeTypeInfo(mimeType: 'application/vnd.groove-injector', extensions: ['grv'], category: 'application')]
    case APPLICATION_VND_GROOVE_INJECTOR = 'APPLICATION_VND_GROOVE_INJECTOR';

    #[MimeTypeInfo(mimeType: 'application/vnd.groove-tool-message', extensions: ['gtm'], category: 'application')]
    case APPLICATION_VND_GROOVE_TOOL_MESSAGE = 'APPLICATION_VND_GROOVE_TOOL_MESSAGE';

    #[MimeTypeInfo(mimeType: 'application/vnd.groove-tool-template', extensions: ['tpl'], category: 'application')]
    case APPLICATION_VND_GROOVE_TOOL_TEMPLATE = 'APPLICATION_VND_GROOVE_TOOL_TEMPLATE';

    #[MimeTypeInfo(mimeType: 'application/vnd.groove-vcard', extensions: ['vcg'], category: 'application')]
    case APPLICATION_VND_GROOVE_VCARD = 'APPLICATION_VND_GROOVE_VCARD';

    #[MimeTypeInfo(mimeType: 'application/vnd.hal+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_HAL_JSON = 'APPLICATION_VND_HAL_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.hal+xml', extensions: ['hal'], category: 'application')]
    case APPLICATION_VND_HAL_XML = 'APPLICATION_VND_HAL_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.handheld-entertainment+xml', extensions: ['zmm'], category: 'application')]
    case APPLICATION_VND_HANDHELD_ENTERTAINMENT_XML = 'APPLICATION_VND_HANDHELD_ENTERTAINMENT_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.hbci', extensions: ['hbci', 'hbc', 'kom', 'upa', 'pkd', 'bpd'], category: 'application')]
    case APPLICATION_VND_HBCI = 'APPLICATION_VND_HBCI';

    #[MimeTypeInfo(mimeType: 'application/vnd.hc+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_HC_JSON = 'APPLICATION_VND_HC_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.hcl-bireports', extensions: ['rep'], category: 'application')]
    case APPLICATION_VND_HCL_BIREPORTS = 'APPLICATION_VND_HCL_BIREPORTS';

    #[MimeTypeInfo(mimeType: 'application/vnd.hdt', extensions: ['hdt'], category: 'application')]
    case APPLICATION_VND_HDT = 'APPLICATION_VND_HDT';

    #[MimeTypeInfo(mimeType: 'application/vnd.heroku+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_HEROKU_JSON = 'APPLICATION_VND_HEROKU_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.hhe.lesson-player', extensions: ['les'], category: 'application')]
    case APPLICATION_VND_HHE_LESSON_PLAYER = 'APPLICATION_VND_HHE_LESSON_PLAYER';

    #[MimeTypeInfo(mimeType: 'application/vnd.hp-hpgl', extensions: ['hpgl'], category: 'application')]
    case APPLICATION_VND_HP_HPGL = 'APPLICATION_VND_HP_HPGL';

    #[MimeTypeInfo(mimeType: 'application/vnd.hp-hpid', extensions: ['hpid', 'hpi'], category: 'application')]
    case APPLICATION_VND_HP_HPID = 'APPLICATION_VND_HP_HPID';

    #[MimeTypeInfo(mimeType: 'application/vnd.hp-hps', extensions: ['hps'], category: 'application')]
    case APPLICATION_VND_HP_HPS = 'APPLICATION_VND_HP_HPS';

    #[MimeTypeInfo(mimeType: 'application/vnd.hp-jlyt', extensions: ['jlt'], category: 'application')]
    case APPLICATION_VND_HP_JLYT = 'APPLICATION_VND_HP_JLYT';

    #[MimeTypeInfo(mimeType: 'application/vnd.hp-pcl', extensions: ['pcl'], category: 'application')]
    case APPLICATION_VND_HP_PCL = 'APPLICATION_VND_HP_PCL';

    #[MimeTypeInfo(mimeType: 'application/vnd.hp-pclxl', extensions: ['pclxl'], category: 'application')]
    case APPLICATION_VND_HP_PCLXL = 'APPLICATION_VND_HP_PCLXL';

    #[MimeTypeInfo(mimeType: 'application/vnd.hsl', extensions: ['hsl'], category: 'application')]
    case APPLICATION_VND_HSL = 'APPLICATION_VND_HSL';

    #[MimeTypeInfo(mimeType: 'application/vnd.httphone', extensions: ['do'], category: 'application')]
    case APPLICATION_VND_HTTPHONE = 'APPLICATION_VND_HTTPHONE';

    #[MimeTypeInfo(mimeType: 'application/vnd.hydrostatix.sof-data', extensions: ['sfd-hdstx'], category: 'application')]
    case APPLICATION_VND_HYDROSTATIX_SOF_DATA = 'APPLICATION_VND_HYDROSTATIX_SOF_DATA';

    #[MimeTypeInfo(mimeType: 'application/vnd.hyper+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_HYPER_JSON = 'APPLICATION_VND_HYPER_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.hyper-item+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_HYPER_ITEM_JSON = 'APPLICATION_VND_HYPER_ITEM_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.hyperdrive+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_HYPERDRIVE_JSON = 'APPLICATION_VND_HYPERDRIVE_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.hzn-3d-crossword', extensions: ['x3d'], category: 'application')]
    case APPLICATION_VND_HZN_3D_CROSSWORD = 'APPLICATION_VND_HZN_3D_CROSSWORD';

    #[MimeTypeInfo(mimeType: 'application/vnd.ibm.afplinedata', extensions: [], category: 'application')]
    case APPLICATION_VND_IBM_AFPLINEDATA = 'APPLICATION_VND_IBM_AFPLINEDATA';

    #[MimeTypeInfo(mimeType: 'application/vnd.ibm.electronic-media', extensions: ['emm'], category: 'application')]
    case APPLICATION_VND_IBM_ELECTRONIC_MEDIA = 'APPLICATION_VND_IBM_ELECTRONIC_MEDIA';

    #[MimeTypeInfo(mimeType: 'application/vnd.ibm.minipay', extensions: ['mpy'], category: 'application')]
    case APPLICATION_VND_IBM_MINIPAY = 'APPLICATION_VND_IBM_MINIPAY';

    #[MimeTypeInfo(mimeType: 'application/vnd.ibm.modcap', extensions: ['afp', 'listafp', 'list3820', 'pseg3820'], category: 'application')]
    case APPLICATION_VND_IBM_MODCAP = 'APPLICATION_VND_IBM_MODCAP';

    #[MimeTypeInfo(mimeType: 'application/vnd.ibm.rights-management', extensions: ['irm'], category: 'application')]
    case APPLICATION_VND_IBM_RIGHTS_MANAGEMENT = 'APPLICATION_VND_IBM_RIGHTS_MANAGEMENT';

    #[MimeTypeInfo(mimeType: 'application/vnd.ibm.secure-container', extensions: ['sc'], category: 'application')]
    case APPLICATION_VND_IBM_SECURE_CONTAINER = 'APPLICATION_VND_IBM_SECURE_CONTAINER';

    #[MimeTypeInfo(mimeType: 'application/vnd.iccprofile', extensions: ['icc', 'icm'], category: 'application')]
    case APPLICATION_VND_ICCPROFILE = 'APPLICATION_VND_ICCPROFILE';

    #[MimeTypeInfo(mimeType: 'application/vnd.ieee.1905', extensions: ['1905.1'], category: 'application')]
    case APPLICATION_VND_IEEE_1905 = 'APPLICATION_VND_IEEE_1905';

    #[MimeTypeInfo(mimeType: 'application/vnd.igloader', extensions: ['igl'], category: 'application')]
    case APPLICATION_VND_IGLOADER = 'APPLICATION_VND_IGLOADER';

    #[MimeTypeInfo(mimeType: 'application/vnd.imagemeter.folder+zip', extensions: ['imf'], category: 'application')]
    case APPLICATION_VND_IMAGEMETER_FOLDER_ZIP = 'APPLICATION_VND_IMAGEMETER_FOLDER_ZIP';

    #[MimeTypeInfo(mimeType: 'application/vnd.imagemeter.image+zip', extensions: ['imi'], category: 'application')]
    case APPLICATION_VND_IMAGEMETER_IMAGE_ZIP = 'APPLICATION_VND_IMAGEMETER_IMAGE_ZIP';

    #[MimeTypeInfo(mimeType: 'application/vnd.immervision-ivp', extensions: ['ivp'], category: 'application')]
    case APPLICATION_VND_IMMERVISION_IVP = 'APPLICATION_VND_IMMERVISION_IVP';

    #[MimeTypeInfo(mimeType: 'application/vnd.immervision-ivu', extensions: ['ivu'], category: 'application')]
    case APPLICATION_VND_IMMERVISION_IVU = 'APPLICATION_VND_IMMERVISION_IVU';

    #[MimeTypeInfo(mimeType: 'application/vnd.ims.imsccv1p1', extensions: ['imscc'], category: 'application')]
    case APPLICATION_VND_IMS_IMSCCV1P1 = 'APPLICATION_VND_IMS_IMSCCV1P1';

    #[MimeTypeInfo(mimeType: 'application/vnd.ims.imsccv1p2', extensions: ['imscc'], category: 'application')]
    case APPLICATION_VND_IMS_IMSCCV1P2 = 'APPLICATION_VND_IMS_IMSCCV1P2';

    #[MimeTypeInfo(mimeType: 'application/vnd.ims.imsccv1p3', extensions: ['imscc'], category: 'application')]
    case APPLICATION_VND_IMS_IMSCCV1P3 = 'APPLICATION_VND_IMS_IMSCCV1P3';

    #[MimeTypeInfo(mimeType: 'application/vnd.ims.lis.v2.result+json', extensions: ['jsonld', 'json'], category: 'application')]
    case APPLICATION_VND_IMS_LIS_V2_RESULT_JSON = 'APPLICATION_VND_IMS_LIS_V2_RESULT_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.ims.lti.v2.toolconsumerprofile+json', extensions: ['jsonld', 'json'], category: 'application')]
    case APPLICATION_VND_IMS_LTI_V2_TOOLCONSUMERPROFILE_JSON = 'APPLICATION_VND_IMS_LTI_V2_TOOLCONSUMERPROFILE_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.ims.lti.v2.toolproxy+json', extensions: ['jsonld', 'json'], category: 'application')]
    case APPLICATION_VND_IMS_LTI_V2_TOOLPROXY_JSON = 'APPLICATION_VND_IMS_LTI_V2_TOOLPROXY_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.ims.lti.v2.toolproxy.id+json', extensions: ['jsonld', 'json'], category: 'application')]
    case APPLICATION_VND_IMS_LTI_V2_TOOLPROXY_ID_JSON = 'APPLICATION_VND_IMS_LTI_V2_TOOLPROXY_ID_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.ims.lti.v2.toolsettings+json', extensions: ['jsonld', 'json'], category: 'application')]
    case APPLICATION_VND_IMS_LTI_V2_TOOLSETTINGS_JSON = 'APPLICATION_VND_IMS_LTI_V2_TOOLSETTINGS_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.ims.lti.v2.toolsettings.simple+json', extensions: ['jsonld', 'json'], category: 'application')]
    case APPLICATION_VND_IMS_LTI_V2_TOOLSETTINGS_SIMPLE_JSON = 'APPLICATION_VND_IMS_LTI_V2_TOOLSETTINGS_SIMPLE_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.informedcontrol.rms+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_INFORMEDCONTROL_RMS_XML = 'APPLICATION_VND_INFORMEDCONTROL_RMS_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.informix-visionary', extensions: ['vis'], category: 'application')]
    case APPLICATION_VND_INFORMIX_VISIONARY = 'APPLICATION_VND_INFORMIX_VISIONARY';

    #[MimeTypeInfo(mimeType: 'application/vnd.infotech.project', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_INFOTECH_PROJECT = 'APPLICATION_VND_INFOTECH_PROJECT';

    #[MimeTypeInfo(mimeType: 'application/vnd.infotech.project+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_INFOTECH_PROJECT_XML = 'APPLICATION_VND_INFOTECH_PROJECT_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.innopath.wamp.notification', extensions: [], category: 'application')]
    case APPLICATION_VND_INNOPATH_WAMP_NOTIFICATION = 'APPLICATION_VND_INNOPATH_WAMP_NOTIFICATION';

    #[MimeTypeInfo(mimeType: 'application/vnd.insors.igm', extensions: ['igm'], category: 'application')]
    case APPLICATION_VND_INSORS_IGM = 'APPLICATION_VND_INSORS_IGM';

    #[MimeTypeInfo(mimeType: 'application/vnd.intercon.formnet', extensions: ['xpw', 'xpx'], category: 'application')]
    case APPLICATION_VND_INTERCON_FORMNET = 'APPLICATION_VND_INTERCON_FORMNET';

    #[MimeTypeInfo(mimeType: 'application/vnd.intergeo', extensions: ['i2g'], category: 'application')]
    case APPLICATION_VND_INTERGEO = 'APPLICATION_VND_INTERGEO';

    #[MimeTypeInfo(mimeType: 'application/vnd.intertrust.digibox', extensions: [], category: 'application')]
    case APPLICATION_VND_INTERTRUST_DIGIBOX = 'APPLICATION_VND_INTERTRUST_DIGIBOX';

    #[MimeTypeInfo(mimeType: 'application/vnd.intertrust.nncp', extensions: [], category: 'application')]
    case APPLICATION_VND_INTERTRUST_NNCP = 'APPLICATION_VND_INTERTRUST_NNCP';

    #[MimeTypeInfo(mimeType: 'application/vnd.intu.qbo', extensions: ['qbo'], category: 'application')]
    case APPLICATION_VND_INTU_QBO = 'APPLICATION_VND_INTU_QBO';

    #[MimeTypeInfo(mimeType: 'application/vnd.intu.qfx', extensions: ['qfx'], category: 'application')]
    case APPLICATION_VND_INTU_QFX = 'APPLICATION_VND_INTU_QFX';

    #[MimeTypeInfo(mimeType: 'application/vnd.ipfs.ipns-record', extensions: ['ipns-record'], category: 'application')]
    case APPLICATION_VND_IPFS_IPNS_RECORD = 'APPLICATION_VND_IPFS_IPNS_RECORD';

    #[MimeTypeInfo(mimeType: 'application/vnd.ipld.car', extensions: ['car'], category: 'application')]
    case APPLICATION_VND_IPLD_CAR = 'APPLICATION_VND_IPLD_CAR';

    #[MimeTypeInfo(mimeType: 'application/vnd.ipld.dag-cbor', extensions: ['cbor'], category: 'application')]
    case APPLICATION_VND_IPLD_DAG_CBOR = 'APPLICATION_VND_IPLD_DAG_CBOR';

    #[MimeTypeInfo(mimeType: 'application/vnd.ipld.dag-json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_IPLD_DAG_JSON = 'APPLICATION_VND_IPLD_DAG_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.ipld.raw', extensions: [], category: 'application')]
    case APPLICATION_VND_IPLD_RAW = 'APPLICATION_VND_IPLD_RAW';

    #[MimeTypeInfo(mimeType: 'application/vnd.iptc.g2.catalogitem+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_IPTC_G2_CATALOGITEM_XML = 'APPLICATION_VND_IPTC_G2_CATALOGITEM_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.iptc.g2.conceptitem+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_IPTC_G2_CONCEPTITEM_XML = 'APPLICATION_VND_IPTC_G2_CONCEPTITEM_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.iptc.g2.knowledgeitem+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_IPTC_G2_KNOWLEDGEITEM_XML = 'APPLICATION_VND_IPTC_G2_KNOWLEDGEITEM_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.iptc.g2.newsitem+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_IPTC_G2_NEWSITEM_XML = 'APPLICATION_VND_IPTC_G2_NEWSITEM_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.iptc.g2.newsmessage+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_IPTC_G2_NEWSMESSAGE_XML = 'APPLICATION_VND_IPTC_G2_NEWSMESSAGE_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.iptc.g2.packageitem+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_IPTC_G2_PACKAGEITEM_XML = 'APPLICATION_VND_IPTC_G2_PACKAGEITEM_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.iptc.g2.planningitem+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_IPTC_G2_PLANNINGITEM_XML = 'APPLICATION_VND_IPTC_G2_PLANNINGITEM_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.ipunplugged.rcprofile', extensions: ['rcprofile'], category: 'application')]
    case APPLICATION_VND_IPUNPLUGGED_RCPROFILE = 'APPLICATION_VND_IPUNPLUGGED_RCPROFILE';

    #[MimeTypeInfo(mimeType: 'application/vnd.irepository.package+xml', extensions: ['irp'], category: 'application')]
    case APPLICATION_VND_IREPOSITORY_PACKAGE_XML = 'APPLICATION_VND_IREPOSITORY_PACKAGE_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.is-xpr', extensions: ['xpr'], category: 'application')]
    case APPLICATION_VND_IS_XPR = 'APPLICATION_VND_IS_XPR';

    #[MimeTypeInfo(mimeType: 'application/vnd.isac.fcs', extensions: ['fcs'], category: 'application')]
    case APPLICATION_VND_ISAC_FCS = 'APPLICATION_VND_ISAC_FCS';

    #[MimeTypeInfo(mimeType: 'application/vnd.iso11783-10+zip', extensions: ['zip'], category: 'application')]
    case APPLICATION_VND_ISO11783_10_ZIP = 'APPLICATION_VND_ISO11783_10_ZIP';

    #[MimeTypeInfo(mimeType: 'application/vnd.jam', extensions: ['jam'], category: 'application')]
    case APPLICATION_VND_JAM = 'APPLICATION_VND_JAM';

    #[MimeTypeInfo(mimeType: 'application/vnd.japannet-directory-service', extensions: [], category: 'application')]
    case APPLICATION_VND_JAPANNET_DIRECTORY_SERVICE = 'APPLICATION_VND_JAPANNET_DIRECTORY_SERVICE';

    #[MimeTypeInfo(mimeType: 'application/vnd.japannet-jpnstore-wakeup', extensions: [], category: 'application')]
    case APPLICATION_VND_JAPANNET_JPNSTORE_WAKEUP = 'APPLICATION_VND_JAPANNET_JPNSTORE_WAKEUP';

    #[MimeTypeInfo(mimeType: 'application/vnd.japannet-payment-wakeup', extensions: [], category: 'application')]
    case APPLICATION_VND_JAPANNET_PAYMENT_WAKEUP = 'APPLICATION_VND_JAPANNET_PAYMENT_WAKEUP';

    #[MimeTypeInfo(mimeType: 'application/vnd.japannet-registration', extensions: [], category: 'application')]
    case APPLICATION_VND_JAPANNET_REGISTRATION = 'APPLICATION_VND_JAPANNET_REGISTRATION';

    #[MimeTypeInfo(mimeType: 'application/vnd.japannet-registration-wakeup', extensions: [], category: 'application')]
    case APPLICATION_VND_JAPANNET_REGISTRATION_WAKEUP = 'APPLICATION_VND_JAPANNET_REGISTRATION_WAKEUP';

    #[MimeTypeInfo(mimeType: 'application/vnd.japannet-setstore-wakeup', extensions: [], category: 'application')]
    case APPLICATION_VND_JAPANNET_SETSTORE_WAKEUP = 'APPLICATION_VND_JAPANNET_SETSTORE_WAKEUP';

    #[MimeTypeInfo(mimeType: 'application/vnd.japannet-verification', extensions: [], category: 'application')]
    case APPLICATION_VND_JAPANNET_VERIFICATION = 'APPLICATION_VND_JAPANNET_VERIFICATION';

    #[MimeTypeInfo(mimeType: 'application/vnd.japannet-verification-wakeup', extensions: [], category: 'application')]
    case APPLICATION_VND_JAPANNET_VERIFICATION_WAKEUP = 'APPLICATION_VND_JAPANNET_VERIFICATION_WAKEUP';

    #[MimeTypeInfo(mimeType: 'application/vnd.jcp.javame.midlet-rms', extensions: ['rms'], category: 'application')]
    case APPLICATION_VND_JCP_JAVAME_MIDLET_RMS = 'APPLICATION_VND_JCP_JAVAME_MIDLET_RMS';

    #[MimeTypeInfo(mimeType: 'application/vnd.jisp', extensions: ['jisp'], category: 'application')]
    case APPLICATION_VND_JISP = 'APPLICATION_VND_JISP';

    #[MimeTypeInfo(mimeType: 'application/vnd.joost.joda-archive', extensions: ['joda'], category: 'application')]
    case APPLICATION_VND_JOOST_JODA_ARCHIVE = 'APPLICATION_VND_JOOST_JODA_ARCHIVE';

    #[MimeTypeInfo(mimeType: 'application/vnd.jsk.isdn-ngn', extensions: [], category: 'application')]
    case APPLICATION_VND_JSK_ISDN_NGN = 'APPLICATION_VND_JSK_ISDN_NGN';

    #[MimeTypeInfo(mimeType: 'application/vnd.kahootz', extensions: ['ktz', 'ktr'], category: 'application')]
    case APPLICATION_VND_KAHOOTZ = 'APPLICATION_VND_KAHOOTZ';

    #[MimeTypeInfo(mimeType: 'application/vnd.kde.karbon', extensions: ['karbon'], category: 'application')]
    case APPLICATION_VND_KDE_KARBON = 'APPLICATION_VND_KDE_KARBON';

    #[MimeTypeInfo(mimeType: 'application/vnd.kde.kchart', extensions: ['chrt'], category: 'application')]
    case APPLICATION_VND_KDE_KCHART = 'APPLICATION_VND_KDE_KCHART';

    #[MimeTypeInfo(mimeType: 'application/vnd.kde.kformula', extensions: ['kfo'], category: 'application')]
    case APPLICATION_VND_KDE_KFORMULA = 'APPLICATION_VND_KDE_KFORMULA';

    #[MimeTypeInfo(mimeType: 'application/vnd.kde.kivio', extensions: ['flw'], category: 'application')]
    case APPLICATION_VND_KDE_KIVIO = 'APPLICATION_VND_KDE_KIVIO';

    #[MimeTypeInfo(mimeType: 'application/vnd.kde.kontour', extensions: ['kon'], category: 'application')]
    case APPLICATION_VND_KDE_KONTOUR = 'APPLICATION_VND_KDE_KONTOUR';

    #[MimeTypeInfo(mimeType: 'application/vnd.kde.kpresenter', extensions: ['kpr', 'kpt', 'kwt', 'kwd'], category: 'application')]
    case APPLICATION_VND_KDE_KPRESENTER = 'APPLICATION_VND_KDE_KPRESENTER';

    #[MimeTypeInfo(mimeType: 'application/vnd.kde.kspread', extensions: ['ksp', 'kwt', 'kwd'], category: 'application')]
    case APPLICATION_VND_KDE_KSPREAD = 'APPLICATION_VND_KDE_KSPREAD';

    #[MimeTypeInfo(mimeType: 'application/vnd.kde.kword', extensions: ['kwd', 'kwt'], category: 'application')]
    case APPLICATION_VND_KDE_KWORD = 'APPLICATION_VND_KDE_KWORD';

    #[MimeTypeInfo(mimeType: 'application/vnd.kdl', extensions: ['kdl'], category: 'application')]
    case APPLICATION_VND_KDL = 'APPLICATION_VND_KDL';

    #[MimeTypeInfo(mimeType: 'application/vnd.kenameaapp', extensions: ['htke'], category: 'application')]
    case APPLICATION_VND_KENAMEAAPP = 'APPLICATION_VND_KENAMEAAPP';

    #[MimeTypeInfo(mimeType: 'application/vnd.keyman.kmp+zip', extensions: ['kmp'], category: 'application')]
    case APPLICATION_VND_KEYMAN_KMP_ZIP = 'APPLICATION_VND_KEYMAN_KMP_ZIP';

    #[MimeTypeInfo(mimeType: 'application/vnd.keyman.kmx', extensions: ['kmx'], category: 'application')]
    case APPLICATION_VND_KEYMAN_KMX = 'APPLICATION_VND_KEYMAN_KMX';

    #[MimeTypeInfo(mimeType: 'application/vnd.kidspiration', extensions: ['kia'], category: 'application')]
    case APPLICATION_VND_KIDSPIRATION = 'APPLICATION_VND_KIDSPIRATION';

    #[MimeTypeInfo(mimeType: 'application/vnd.kinar', extensions: ['kne', 'knp'], category: 'application')]
    case APPLICATION_VND_KINAR = 'APPLICATION_VND_KINAR';

    #[MimeTypeInfo(mimeType: 'application/vnd.koan', extensions: ['skp', 'skd', 'skt', 'skm'], category: 'application')]
    case APPLICATION_VND_KOAN = 'APPLICATION_VND_KOAN';

    #[MimeTypeInfo(mimeType: 'application/vnd.kodak-descriptor', extensions: ['sse'], category: 'application')]
    case APPLICATION_VND_KODAK_DESCRIPTOR = 'APPLICATION_VND_KODAK_DESCRIPTOR';

    #[MimeTypeInfo(mimeType: 'application/vnd.las', extensions: ['las'], category: 'application')]
    case APPLICATION_VND_LAS = 'APPLICATION_VND_LAS';

    #[MimeTypeInfo(mimeType: 'application/vnd.las.las+json', extensions: ['lasjson'], category: 'application')]
    case APPLICATION_VND_LAS_LAS_JSON = 'APPLICATION_VND_LAS_LAS_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.las.las+xml', extensions: ['lasxml'], category: 'application')]
    case APPLICATION_VND_LAS_LAS_XML = 'APPLICATION_VND_LAS_LAS_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.laszip', extensions: ['laz'], category: 'application')]
    case APPLICATION_VND_LASZIP = 'APPLICATION_VND_LASZIP';

    #[MimeTypeInfo(mimeType: 'application/vnd.ldev.productlicensing', extensions: [], category: 'application')]
    case APPLICATION_VND_LDEV_PRODUCTLICENSING = 'APPLICATION_VND_LDEV_PRODUCTLICENSING';

    #[MimeTypeInfo(mimeType: 'application/vnd.leap+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_LEAP_JSON = 'APPLICATION_VND_LEAP_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.liberty-request+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_LIBERTY_REQUEST_XML = 'APPLICATION_VND_LIBERTY_REQUEST_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.llamagraphics.life-balance.desktop', extensions: ['lbd'], category: 'application')]
    case APPLICATION_VND_LLAMAGRAPHICS_LIFE_BALANCE_DESKTOP = 'APPLICATION_VND_LLAMAGRAPHICS_LIFE_BALANCE_DESKTOP';

    #[MimeTypeInfo(mimeType: 'application/vnd.llamagraphics.life-balance.exchange+xml', extensions: ['lbe'], category: 'application')]
    case APPLICATION_VND_LLAMAGRAPHICS_LIFE_BALANCE_EXCHANGE_XML = 'APPLICATION_VND_LLAMAGRAPHICS_LIFE_BALANCE_EXCHANGE_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.logipipe.circuit+zip', extensions: ['lcs', 'lca'], category: 'application')]
    case APPLICATION_VND_LOGIPIPE_CIRCUIT_ZIP = 'APPLICATION_VND_LOGIPIPE_CIRCUIT_ZIP';

    #[MimeTypeInfo(mimeType: 'application/vnd.loom', extensions: ['loom'], category: 'application')]
    case APPLICATION_VND_LOOM = 'APPLICATION_VND_LOOM';

    #[MimeTypeInfo(mimeType: 'application/vnd.lotus-1-2-3', extensions: ['123'], category: 'application')]
    case APPLICATION_VND_LOTUS_1_2_3 = 'APPLICATION_VND_LOTUS_1_2_3';

    #[MimeTypeInfo(mimeType: 'application/vnd.lotus-approach', extensions: ['apr'], category: 'application')]
    case APPLICATION_VND_LOTUS_APPROACH = 'APPLICATION_VND_LOTUS_APPROACH';

    #[MimeTypeInfo(mimeType: 'application/vnd.lotus-freelance', extensions: ['pre', 'prz'], category: 'application')]
    case APPLICATION_VND_LOTUS_FREELANCE = 'APPLICATION_VND_LOTUS_FREELANCE';

    #[MimeTypeInfo(mimeType: 'application/vnd.lotus-notes', extensions: ['nsf'], category: 'application')]
    case APPLICATION_VND_LOTUS_NOTES = 'APPLICATION_VND_LOTUS_NOTES';

    #[MimeTypeInfo(mimeType: 'application/vnd.lotus-organizer', extensions: ['org', 'or3'], category: 'application')]
    case APPLICATION_VND_LOTUS_ORGANIZER = 'APPLICATION_VND_LOTUS_ORGANIZER';

    #[MimeTypeInfo(mimeType: 'application/vnd.lotus-screencam', extensions: ['scm'], category: 'application')]
    case APPLICATION_VND_LOTUS_SCREENCAM = 'APPLICATION_VND_LOTUS_SCREENCAM';

    #[MimeTypeInfo(mimeType: 'application/vnd.lotus-wordpro', extensions: ['lwp'], category: 'application')]
    case APPLICATION_VND_LOTUS_WORDPRO = 'APPLICATION_VND_LOTUS_WORDPRO';

    #[MimeTypeInfo(mimeType: 'application/vnd.macports.portpkg', extensions: ['portpkg'], category: 'application')]
    case APPLICATION_VND_MACPORTS_PORTPKG = 'APPLICATION_VND_MACPORTS_PORTPKG';

    #[MimeTypeInfo(mimeType: 'application/vnd.mapbox-vector-tile', extensions: ['mvt'], category: 'application')]
    case APPLICATION_VND_MAPBOX_VECTOR_TILE = 'APPLICATION_VND_MAPBOX_VECTOR_TILE';

    #[MimeTypeInfo(mimeType: 'application/vnd.marlin.drm.actiontoken+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_MARLIN_DRM_ACTIONTOKEN_XML = 'APPLICATION_VND_MARLIN_DRM_ACTIONTOKEN_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.marlin.drm.conftoken+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_MARLIN_DRM_CONFTOKEN_XML = 'APPLICATION_VND_MARLIN_DRM_CONFTOKEN_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.marlin.drm.license+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_MARLIN_DRM_LICENSE_XML = 'APPLICATION_VND_MARLIN_DRM_LICENSE_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.marlin.drm.mdcf', extensions: ['mdc'], category: 'application')]
    case APPLICATION_VND_MARLIN_DRM_MDCF = 'APPLICATION_VND_MARLIN_DRM_MDCF';

    #[MimeTypeInfo(mimeType: 'application/vnd.mason+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_MASON_JSON = 'APPLICATION_VND_MASON_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.maxar.archive.3tz+zip', extensions: ['3tz'], category: 'application')]
    case APPLICATION_VND_MAXAR_ARCHIVE_3TZ_ZIP = 'APPLICATION_VND_MAXAR_ARCHIVE_3TZ_ZIP';

    #[MimeTypeInfo(mimeType: 'application/vnd.maxmind.maxmind-db', extensions: ['mmdb'], category: 'application')]
    case APPLICATION_VND_MAXMIND_MAXMIND_DB = 'APPLICATION_VND_MAXMIND_MAXMIND_DB';

    #[MimeTypeInfo(mimeType: 'application/vnd.mcd', extensions: ['mcd'], category: 'application')]
    case APPLICATION_VND_MCD = 'APPLICATION_VND_MCD';

    #[MimeTypeInfo(mimeType: 'application/vnd.mdl', extensions: ['mdl'], category: 'application')]
    case APPLICATION_VND_MDL = 'APPLICATION_VND_MDL';

    #[MimeTypeInfo(mimeType: 'application/vnd.mdl-mbsdf', extensions: ['mbsdf'], category: 'application')]
    case APPLICATION_VND_MDL_MBSDF = 'APPLICATION_VND_MDL_MBSDF';

    #[MimeTypeInfo(mimeType: 'application/vnd.medcalcdata', extensions: ['mc1'], category: 'application')]
    case APPLICATION_VND_MEDCALCDATA = 'APPLICATION_VND_MEDCALCDATA';

    #[MimeTypeInfo(mimeType: 'application/vnd.mediastation.cdkey', extensions: ['cdkey'], category: 'application')]
    case APPLICATION_VND_MEDIASTATION_CDKEY = 'APPLICATION_VND_MEDIASTATION_CDKEY';

    #[MimeTypeInfo(mimeType: 'application/vnd.medicalholodeck.recordxr', extensions: ['rxr'], category: 'application')]
    case APPLICATION_VND_MEDICALHOLODECK_RECORDXR = 'APPLICATION_VND_MEDICALHOLODECK_RECORDXR';

    #[MimeTypeInfo(mimeType: 'application/vnd.meridian-slingshot', extensions: [], category: 'application')]
    case APPLICATION_VND_MERIDIAN_SLINGSHOT = 'APPLICATION_VND_MERIDIAN_SLINGSHOT';

    #[MimeTypeInfo(mimeType: 'application/vnd.mermaid', extensions: ['mmd', 'mermaid'], category: 'application')]
    case APPLICATION_VND_MERMAID = 'APPLICATION_VND_MERMAID';

    #[MimeTypeInfo(mimeType: 'application/vnd.mfer', extensions: ['mwf'], category: 'application')]
    case APPLICATION_VND_MFER = 'APPLICATION_VND_MFER';

    #[MimeTypeInfo(mimeType: 'application/vnd.mfmp', extensions: ['mfm'], category: 'application')]
    case APPLICATION_VND_MFMP = 'APPLICATION_VND_MFMP';

    #[MimeTypeInfo(mimeType: 'application/vnd.micro+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_MICRO_JSON = 'APPLICATION_VND_MICRO_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.micrografx.flo', extensions: ['flo'], category: 'application')]
    case APPLICATION_VND_MICROGRAFX_FLO = 'APPLICATION_VND_MICROGRAFX_FLO';

    #[MimeTypeInfo(mimeType: 'application/vnd.micrografx.igx', extensions: ['igx'], category: 'application')]
    case APPLICATION_VND_MICROGRAFX_IGX = 'APPLICATION_VND_MICROGRAFX_IGX';

    #[MimeTypeInfo(mimeType: 'application/vnd.microsoft.portable-executable', extensions: ['exe', 'dll'], category: 'application')]
    case APPLICATION_VND_MICROSOFT_PORTABLE_EXECUTABLE = 'APPLICATION_VND_MICROSOFT_PORTABLE_EXECUTABLE';

    #[MimeTypeInfo(mimeType: 'application/vnd.microsoft.windows.thumbnail-cache', extensions: [], category: 'application')]
    case APPLICATION_VND_MICROSOFT_WINDOWS_THUMBNAIL_CACHE = 'APPLICATION_VND_MICROSOFT_WINDOWS_THUMBNAIL_CACHE';

    #[MimeTypeInfo(mimeType: 'application/vnd.miele+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_MIELE_JSON = 'APPLICATION_VND_MIELE_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.mif', extensions: ['mif'], category: 'application')]
    case APPLICATION_VND_MIF = 'APPLICATION_VND_MIF';

    #[MimeTypeInfo(mimeType: 'application/vnd.minisoft-hp3000-save', extensions: [], category: 'application')]
    case APPLICATION_VND_MINISOFT_HP3000_SAVE = 'APPLICATION_VND_MINISOFT_HP3000_SAVE';

    #[MimeTypeInfo(mimeType: 'application/vnd.mitsubishi.misty-guard.trustweb', extensions: [], category: 'application')]
    case APPLICATION_VND_MITSUBISHI_MISTY_GUARD_TRUSTWEB = 'APPLICATION_VND_MITSUBISHI_MISTY_GUARD_TRUSTWEB';

    #[MimeTypeInfo(mimeType: 'application/vnd.mobius.daf', extensions: ['daf'], category: 'application')]
    case APPLICATION_VND_MOBIUS_DAF = 'APPLICATION_VND_MOBIUS_DAF';

    #[MimeTypeInfo(mimeType: 'application/vnd.mobius.dis', extensions: ['dis'], category: 'application')]
    case APPLICATION_VND_MOBIUS_DIS = 'APPLICATION_VND_MOBIUS_DIS';

    #[MimeTypeInfo(mimeType: 'application/vnd.mobius.mbk', extensions: ['mbk'], category: 'application')]
    case APPLICATION_VND_MOBIUS_MBK = 'APPLICATION_VND_MOBIUS_MBK';

    #[MimeTypeInfo(mimeType: 'application/vnd.mobius.mqy', extensions: ['mqy'], category: 'application')]
    case APPLICATION_VND_MOBIUS_MQY = 'APPLICATION_VND_MOBIUS_MQY';

    #[MimeTypeInfo(mimeType: 'application/vnd.mobius.msl', extensions: ['msl'], category: 'application')]
    case APPLICATION_VND_MOBIUS_MSL = 'APPLICATION_VND_MOBIUS_MSL';

    #[MimeTypeInfo(mimeType: 'application/vnd.mobius.plc', extensions: ['plc'], category: 'application')]
    case APPLICATION_VND_MOBIUS_PLC = 'APPLICATION_VND_MOBIUS_PLC';

    #[MimeTypeInfo(mimeType: 'application/vnd.mobius.txf', extensions: ['txf'], category: 'application')]
    case APPLICATION_VND_MOBIUS_TXF = 'APPLICATION_VND_MOBIUS_TXF';

    #[MimeTypeInfo(mimeType: 'application/vnd.modl', extensions: ['modl'], category: 'application')]
    case APPLICATION_VND_MODL = 'APPLICATION_VND_MODL';

    #[MimeTypeInfo(mimeType: 'application/vnd.mophun.application', extensions: ['mpn'], category: 'application')]
    case APPLICATION_VND_MOPHUN_APPLICATION = 'APPLICATION_VND_MOPHUN_APPLICATION';

    #[MimeTypeInfo(mimeType: 'application/vnd.mophun.certificate', extensions: ['mpc'], category: 'application')]
    case APPLICATION_VND_MOPHUN_CERTIFICATE = 'APPLICATION_VND_MOPHUN_CERTIFICATE';

    #[MimeTypeInfo(mimeType: 'application/vnd.motorola.flexsuite', extensions: [], category: 'application')]
    case APPLICATION_VND_MOTOROLA_FLEXSUITE = 'APPLICATION_VND_MOTOROLA_FLEXSUITE';

    #[MimeTypeInfo(mimeType: 'application/vnd.motorola.flexsuite.adsi', extensions: [], category: 'application')]
    case APPLICATION_VND_MOTOROLA_FLEXSUITE_ADSI = 'APPLICATION_VND_MOTOROLA_FLEXSUITE_ADSI';

    #[MimeTypeInfo(mimeType: 'application/vnd.motorola.flexsuite.fis', extensions: [], category: 'application')]
    case APPLICATION_VND_MOTOROLA_FLEXSUITE_FIS = 'APPLICATION_VND_MOTOROLA_FLEXSUITE_FIS';

    #[MimeTypeInfo(mimeType: 'application/vnd.motorola.flexsuite.gotap', extensions: [], category: 'application')]
    case APPLICATION_VND_MOTOROLA_FLEXSUITE_GOTAP = 'APPLICATION_VND_MOTOROLA_FLEXSUITE_GOTAP';

    #[MimeTypeInfo(mimeType: 'application/vnd.motorola.flexsuite.kmr', extensions: [], category: 'application')]
    case APPLICATION_VND_MOTOROLA_FLEXSUITE_KMR = 'APPLICATION_VND_MOTOROLA_FLEXSUITE_KMR';

    #[MimeTypeInfo(mimeType: 'application/vnd.motorola.flexsuite.ttc', extensions: [], category: 'application')]
    case APPLICATION_VND_MOTOROLA_FLEXSUITE_TTC = 'APPLICATION_VND_MOTOROLA_FLEXSUITE_TTC';

    #[MimeTypeInfo(mimeType: 'application/vnd.motorola.flexsuite.wem', extensions: [], category: 'application')]
    case APPLICATION_VND_MOTOROLA_FLEXSUITE_WEM = 'APPLICATION_VND_MOTOROLA_FLEXSUITE_WEM';

    #[MimeTypeInfo(mimeType: 'application/vnd.motorola.iprm', extensions: [], category: 'application')]
    case APPLICATION_VND_MOTOROLA_IPRM = 'APPLICATION_VND_MOTOROLA_IPRM';

    #[MimeTypeInfo(mimeType: 'application/vnd.mozilla.xul+xml', extensions: ['xul'], category: 'application')]
    case APPLICATION_VND_MOZILLA_XUL_XML = 'APPLICATION_VND_MOZILLA_XUL_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-3mfdocument', extensions: ['3mf'], category: 'application')]
    case APPLICATION_VND_MS_3MFDOCUMENT = 'APPLICATION_VND_MS_3MFDOCUMENT';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-artgalry', extensions: ['cil'], category: 'application')]
    case APPLICATION_VND_MS_ARTGALRY = 'APPLICATION_VND_MS_ARTGALRY';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-asf', extensions: ['asf'], category: 'application')]
    case APPLICATION_VND_MS_ASF = 'APPLICATION_VND_MS_ASF';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-cab-compressed', extensions: ['cab'], category: 'application')]
    case APPLICATION_VND_MS_CAB_COMPRESSED = 'APPLICATION_VND_MS_CAB_COMPRESSED';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-color.iccprofile', extensions: [], category: 'application')]
    case APPLICATION_VND_MS_COLOR_ICCPROFILE = 'APPLICATION_VND_MS_COLOR_ICCPROFILE';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-excel', extensions: ['xls', 'xlm', 'xla', 'xlc', 'xlt', 'xlw'], category: 'application')]
    case APPLICATION_VND_MS_EXCEL = 'APPLICATION_VND_MS_EXCEL';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-excel.addin.macroenabled.12', extensions: ['xlam'], category: 'application')]
    case APPLICATION_VND_MS_EXCEL_ADDIN_MACROENABLED_12 = 'APPLICATION_VND_MS_EXCEL_ADDIN_MACROENABLED_12';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-excel.sheet.binary.macroenabled.12', extensions: ['xlsb'], category: 'application')]
    case APPLICATION_VND_MS_EXCEL_SHEET_BINARY_MACROENABLED_12 = 'APPLICATION_VND_MS_EXCEL_SHEET_BINARY_MACROENABLED_12';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-excel.sheet.macroenabled.12', extensions: ['xlsm'], category: 'application')]
    case APPLICATION_VND_MS_EXCEL_SHEET_MACROENABLED_12 = 'APPLICATION_VND_MS_EXCEL_SHEET_MACROENABLED_12';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-excel.template.macroenabled.12', extensions: ['xltm'], category: 'application')]
    case APPLICATION_VND_MS_EXCEL_TEMPLATE_MACROENABLED_12 = 'APPLICATION_VND_MS_EXCEL_TEMPLATE_MACROENABLED_12';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-fontobject', extensions: ['eot'], category: 'application')]
    case APPLICATION_VND_MS_FONTOBJECT = 'APPLICATION_VND_MS_FONTOBJECT';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-htmlhelp', extensions: ['chm'], category: 'application')]
    case APPLICATION_VND_MS_HTMLHELP = 'APPLICATION_VND_MS_HTMLHELP';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-ims', extensions: ['ims'], category: 'application')]
    case APPLICATION_VND_MS_IMS = 'APPLICATION_VND_MS_IMS';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-lrm', extensions: ['lrm'], category: 'application')]
    case APPLICATION_VND_MS_LRM = 'APPLICATION_VND_MS_LRM';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-office.activex+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_MS_OFFICE_ACTIVEX_XML = 'APPLICATION_VND_MS_OFFICE_ACTIVEX_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-officetheme', extensions: ['thmx'], category: 'application')]
    case APPLICATION_VND_MS_OFFICETHEME = 'APPLICATION_VND_MS_OFFICETHEME';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-opentype', extensions: [], category: 'application')]
    case APPLICATION_VND_MS_OPENTYPE = 'APPLICATION_VND_MS_OPENTYPE';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-outlook', extensions: ['msg'], category: 'application')]
    case APPLICATION_VND_MS_OUTLOOK = 'APPLICATION_VND_MS_OUTLOOK';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-package.obfuscated-opentype', extensions: [], category: 'application')]
    case APPLICATION_VND_MS_PACKAGE_OBFUSCATED_OPENTYPE = 'APPLICATION_VND_MS_PACKAGE_OBFUSCATED_OPENTYPE';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-pki.seccat', extensions: ['cat'], category: 'application')]
    case APPLICATION_VND_MS_PKI_SECCAT = 'APPLICATION_VND_MS_PKI_SECCAT';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-pki.stl', extensions: ['stl'], category: 'application')]
    case APPLICATION_VND_MS_PKI_STL = 'APPLICATION_VND_MS_PKI_STL';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-playready.initiator+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_MS_PLAYREADY_INITIATOR_XML = 'APPLICATION_VND_MS_PLAYREADY_INITIATOR_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-powerpoint', extensions: ['ppt', 'pps', 'pot'], category: 'application')]
    case APPLICATION_VND_MS_POWERPOINT = 'APPLICATION_VND_MS_POWERPOINT';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-powerpoint.addin.macroenabled.12', extensions: ['ppam'], category: 'application')]
    case APPLICATION_VND_MS_POWERPOINT_ADDIN_MACROENABLED_12 = 'APPLICATION_VND_MS_POWERPOINT_ADDIN_MACROENABLED_12';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-powerpoint.presentation.macroenabled.12', extensions: ['pptm'], category: 'application')]
    case APPLICATION_VND_MS_POWERPOINT_PRESENTATION_MACROENABLED_12 = 'APPLICATION_VND_MS_POWERPOINT_PRESENTATION_MACROENABLED_12';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-powerpoint.slide.macroenabled.12', extensions: ['sldm'], category: 'application')]
    case APPLICATION_VND_MS_POWERPOINT_SLIDE_MACROENABLED_12 = 'APPLICATION_VND_MS_POWERPOINT_SLIDE_MACROENABLED_12';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-powerpoint.slideshow.macroenabled.12', extensions: ['ppsm'], category: 'application')]
    case APPLICATION_VND_MS_POWERPOINT_SLIDESHOW_MACROENABLED_12 = 'APPLICATION_VND_MS_POWERPOINT_SLIDESHOW_MACROENABLED_12';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-powerpoint.template.macroenabled.12', extensions: ['potm'], category: 'application')]
    case APPLICATION_VND_MS_POWERPOINT_TEMPLATE_MACROENABLED_12 = 'APPLICATION_VND_MS_POWERPOINT_TEMPLATE_MACROENABLED_12';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-printdevicecapabilities+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_MS_PRINTDEVICECAPABILITIES_XML = 'APPLICATION_VND_MS_PRINTDEVICECAPABILITIES_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-printing.printticket+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_MS_PRINTING_PRINTTICKET_XML = 'APPLICATION_VND_MS_PRINTING_PRINTTICKET_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-printschematicket+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_MS_PRINTSCHEMATICKET_XML = 'APPLICATION_VND_MS_PRINTSCHEMATICKET_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-project', extensions: ['mpp', 'mpt'], category: 'application')]
    case APPLICATION_VND_MS_PROJECT = 'APPLICATION_VND_MS_PROJECT';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-tnef', extensions: [], category: 'application')]
    case APPLICATION_VND_MS_TNEF = 'APPLICATION_VND_MS_TNEF';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-visio.viewer', extensions: ['vdx'], category: 'application')]
    case APPLICATION_VND_MS_VISIO_VIEWER = 'APPLICATION_VND_MS_VISIO_VIEWER';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-windows.devicepairing', extensions: [], category: 'application')]
    case APPLICATION_VND_MS_WINDOWS_DEVICEPAIRING = 'APPLICATION_VND_MS_WINDOWS_DEVICEPAIRING';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-windows.nwprinting.oob', extensions: [], category: 'application')]
    case APPLICATION_VND_MS_WINDOWS_NWPRINTING_OOB = 'APPLICATION_VND_MS_WINDOWS_NWPRINTING_OOB';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-windows.printerpairing', extensions: [], category: 'application')]
    case APPLICATION_VND_MS_WINDOWS_PRINTERPAIRING = 'APPLICATION_VND_MS_WINDOWS_PRINTERPAIRING';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-windows.wsd.oob', extensions: [], category: 'application')]
    case APPLICATION_VND_MS_WINDOWS_WSD_OOB = 'APPLICATION_VND_MS_WINDOWS_WSD_OOB';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-wmdrm.lic-chlg-req', extensions: [], category: 'application')]
    case APPLICATION_VND_MS_WMDRM_LIC_CHLG_REQ = 'APPLICATION_VND_MS_WMDRM_LIC_CHLG_REQ';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-wmdrm.lic-resp', extensions: [], category: 'application')]
    case APPLICATION_VND_MS_WMDRM_LIC_RESP = 'APPLICATION_VND_MS_WMDRM_LIC_RESP';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-wmdrm.meter-chlg-req', extensions: [], category: 'application')]
    case APPLICATION_VND_MS_WMDRM_METER_CHLG_REQ = 'APPLICATION_VND_MS_WMDRM_METER_CHLG_REQ';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-wmdrm.meter-resp', extensions: [], category: 'application')]
    case APPLICATION_VND_MS_WMDRM_METER_RESP = 'APPLICATION_VND_MS_WMDRM_METER_RESP';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-word.document.macroenabled.12', extensions: ['docm'], category: 'application')]
    case APPLICATION_VND_MS_WORD_DOCUMENT_MACROENABLED_12 = 'APPLICATION_VND_MS_WORD_DOCUMENT_MACROENABLED_12';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-word.template.macroenabled.12', extensions: ['dotm'], category: 'application')]
    case APPLICATION_VND_MS_WORD_TEMPLATE_MACROENABLED_12 = 'APPLICATION_VND_MS_WORD_TEMPLATE_MACROENABLED_12';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-works', extensions: ['wps', 'wks', 'wcm', 'wdb'], category: 'application')]
    case APPLICATION_VND_MS_WORKS = 'APPLICATION_VND_MS_WORKS';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-wpl', extensions: ['wpl'], category: 'application')]
    case APPLICATION_VND_MS_WPL = 'APPLICATION_VND_MS_WPL';

    #[MimeTypeInfo(mimeType: 'application/vnd.ms-xpsdocument', extensions: ['xps'], category: 'application')]
    case APPLICATION_VND_MS_XPSDOCUMENT = 'APPLICATION_VND_MS_XPSDOCUMENT';

    #[MimeTypeInfo(mimeType: 'application/vnd.msa-disk-image', extensions: ['msa'], category: 'application')]
    case APPLICATION_VND_MSA_DISK_IMAGE = 'APPLICATION_VND_MSA_DISK_IMAGE';

    #[MimeTypeInfo(mimeType: 'application/vnd.mseq', extensions: ['mseq'], category: 'application')]
    case APPLICATION_VND_MSEQ = 'APPLICATION_VND_MSEQ';

    #[MimeTypeInfo(mimeType: 'application/vnd.msgpack', extensions: [], category: 'application')]
    case APPLICATION_VND_MSGPACK = 'APPLICATION_VND_MSGPACK';

    #[MimeTypeInfo(mimeType: 'application/vnd.msign', extensions: [], category: 'application')]
    case APPLICATION_VND_MSIGN = 'APPLICATION_VND_MSIGN';

    #[MimeTypeInfo(mimeType: 'application/vnd.multiad.creator', extensions: ['crtr'], category: 'application')]
    case APPLICATION_VND_MULTIAD_CREATOR = 'APPLICATION_VND_MULTIAD_CREATOR';

    #[MimeTypeInfo(mimeType: 'application/vnd.multiad.creator.cif', extensions: ['cif'], category: 'application')]
    case APPLICATION_VND_MULTIAD_CREATOR_CIF = 'APPLICATION_VND_MULTIAD_CREATOR_CIF';

    #[MimeTypeInfo(mimeType: 'application/vnd.music-niff', extensions: [], category: 'application')]
    case APPLICATION_VND_MUSIC_NIFF = 'APPLICATION_VND_MUSIC_NIFF';

    #[MimeTypeInfo(mimeType: 'application/vnd.musician', extensions: ['mus'], category: 'application')]
    case APPLICATION_VND_MUSICIAN = 'APPLICATION_VND_MUSICIAN';

    #[MimeTypeInfo(mimeType: 'application/vnd.muvee.style', extensions: ['msty'], category: 'application')]
    case APPLICATION_VND_MUVEE_STYLE = 'APPLICATION_VND_MUVEE_STYLE';

    #[MimeTypeInfo(mimeType: 'application/vnd.mynfc', extensions: ['taglet'], category: 'application')]
    case APPLICATION_VND_MYNFC = 'APPLICATION_VND_MYNFC';

    #[MimeTypeInfo(mimeType: 'application/vnd.nacamar.ybrid+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_NACAMAR_YBRID_JSON = 'APPLICATION_VND_NACAMAR_YBRID_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.nato.bindingdataobject+cbor', extensions: ['bdob'], category: 'application')]
    case APPLICATION_VND_NATO_BINDINGDATAOBJECT_CBOR = 'APPLICATION_VND_NATO_BINDINGDATAOBJECT_CBOR';

    #[MimeTypeInfo(mimeType: 'application/vnd.nato.bindingdataobject+json', extensions: ['bdoj'], category: 'application')]
    case APPLICATION_VND_NATO_BINDINGDATAOBJECT_JSON = 'APPLICATION_VND_NATO_BINDINGDATAOBJECT_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.nato.bindingdataobject+xml', extensions: ['bdo'], category: 'application')]
    case APPLICATION_VND_NATO_BINDINGDATAOBJECT_XML = 'APPLICATION_VND_NATO_BINDINGDATAOBJECT_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.nato.openxmlformats-package.iepd+zip', extensions: ['iepd'], category: 'application')]
    case APPLICATION_VND_NATO_OPENXMLFORMATS_PACKAGE_IEPD_ZIP = 'APPLICATION_VND_NATO_OPENXMLFORMATS_PACKAGE_IEPD_ZIP';

    #[MimeTypeInfo(mimeType: 'application/vnd.ncd.control', extensions: [], category: 'application')]
    case APPLICATION_VND_NCD_CONTROL = 'APPLICATION_VND_NCD_CONTROL';

    #[MimeTypeInfo(mimeType: 'application/vnd.ncd.reference', extensions: [], category: 'application')]
    case APPLICATION_VND_NCD_REFERENCE = 'APPLICATION_VND_NCD_REFERENCE';

    #[MimeTypeInfo(mimeType: 'application/vnd.nearst.inv+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_NEARST_INV_JSON = 'APPLICATION_VND_NEARST_INV_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.nebumind.line', extensions: ['nebul', 'line'], category: 'application')]
    case APPLICATION_VND_NEBUMIND_LINE = 'APPLICATION_VND_NEBUMIND_LINE';

    #[MimeTypeInfo(mimeType: 'application/vnd.nervana', extensions: ['ent', 'entity', 'req', 'request', 'bkm', 'kcm'], category: 'application')]
    case APPLICATION_VND_NERVANA = 'APPLICATION_VND_NERVANA';

    #[MimeTypeInfo(mimeType: 'application/vnd.netfpx', extensions: ['often'], category: 'application')]
    case APPLICATION_VND_NETFPX = 'APPLICATION_VND_NETFPX';

    #[MimeTypeInfo(mimeType: 'application/vnd.neurolanguage.nlu', extensions: ['nlu'], category: 'application')]
    case APPLICATION_VND_NEUROLANGUAGE_NLU = 'APPLICATION_VND_NEUROLANGUAGE_NLU';

    #[MimeTypeInfo(mimeType: 'application/vnd.nimn', extensions: ['nimn'], category: 'application')]
    case APPLICATION_VND_NIMN = 'APPLICATION_VND_NIMN';

    #[MimeTypeInfo(mimeType: 'application/vnd.nintendo.nitro.rom', extensions: ['nds'], category: 'application')]
    case APPLICATION_VND_NINTENDO_NITRO_ROM = 'APPLICATION_VND_NINTENDO_NITRO_ROM';

    #[MimeTypeInfo(mimeType: 'application/vnd.nintendo.snes.rom', extensions: ['sfc', 'smc'], category: 'application')]
    case APPLICATION_VND_NINTENDO_SNES_ROM = 'APPLICATION_VND_NINTENDO_SNES_ROM';

    #[MimeTypeInfo(mimeType: 'application/vnd.nitf', extensions: ['ntf', 'nitf'], category: 'application')]
    case APPLICATION_VND_NITF = 'APPLICATION_VND_NITF';

    #[MimeTypeInfo(mimeType: 'application/vnd.noblenet-directory', extensions: ['nnd'], category: 'application')]
    case APPLICATION_VND_NOBLENET_DIRECTORY = 'APPLICATION_VND_NOBLENET_DIRECTORY';

    #[MimeTypeInfo(mimeType: 'application/vnd.noblenet-sealer', extensions: ['nns'], category: 'application')]
    case APPLICATION_VND_NOBLENET_SEALER = 'APPLICATION_VND_NOBLENET_SEALER';

    #[MimeTypeInfo(mimeType: 'application/vnd.noblenet-web', extensions: ['nnw'], category: 'application')]
    case APPLICATION_VND_NOBLENET_WEB = 'APPLICATION_VND_NOBLENET_WEB';

    #[MimeTypeInfo(mimeType: 'application/vnd.nokia.catalogs', extensions: [], category: 'application')]
    case APPLICATION_VND_NOKIA_CATALOGS = 'APPLICATION_VND_NOKIA_CATALOGS';

    #[MimeTypeInfo(mimeType: 'application/vnd.nokia.conml+wbxml', extensions: [], category: 'application')]
    case APPLICATION_VND_NOKIA_CONML_WBXML = 'APPLICATION_VND_NOKIA_CONML_WBXML';

    #[MimeTypeInfo(mimeType: 'application/vnd.nokia.conml+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_NOKIA_CONML_XML = 'APPLICATION_VND_NOKIA_CONML_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.nokia.iptv.config+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_NOKIA_IPTV_CONFIG_XML = 'APPLICATION_VND_NOKIA_IPTV_CONFIG_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.nokia.isds-radio-presets', extensions: [], category: 'application')]
    case APPLICATION_VND_NOKIA_ISDS_RADIO_PRESETS = 'APPLICATION_VND_NOKIA_ISDS_RADIO_PRESETS';

    #[MimeTypeInfo(mimeType: 'application/vnd.nokia.landmark+wbxml', extensions: [], category: 'application')]
    case APPLICATION_VND_NOKIA_LANDMARK_WBXML = 'APPLICATION_VND_NOKIA_LANDMARK_WBXML';

    #[MimeTypeInfo(mimeType: 'application/vnd.nokia.landmark+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_NOKIA_LANDMARK_XML = 'APPLICATION_VND_NOKIA_LANDMARK_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.nokia.landmarkcollection+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_NOKIA_LANDMARKCOLLECTION_XML = 'APPLICATION_VND_NOKIA_LANDMARKCOLLECTION_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.nokia.n-gage.ac+xml', extensions: ['ac'], category: 'application')]
    case APPLICATION_VND_NOKIA_N_GAGE_AC_XML = 'APPLICATION_VND_NOKIA_N_GAGE_AC_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.nokia.n-gage.data', extensions: ['ngdat'], category: 'application')]
    case APPLICATION_VND_NOKIA_N_GAGE_DATA = 'APPLICATION_VND_NOKIA_N_GAGE_DATA';

    #[MimeTypeInfo(mimeType: 'application/vnd.nokia.n-gage.symbian.install', extensions: ['n-gage'], category: 'application')]
    case APPLICATION_VND_NOKIA_N_GAGE_SYMBIAN_INSTALL = 'APPLICATION_VND_NOKIA_N_GAGE_SYMBIAN_INSTALL';

    #[MimeTypeInfo(mimeType: 'application/vnd.nokia.ncd', extensions: [], category: 'application')]
    case APPLICATION_VND_NOKIA_NCD = 'APPLICATION_VND_NOKIA_NCD';

    #[MimeTypeInfo(mimeType: 'application/vnd.nokia.pcd+wbxml', extensions: [], category: 'application')]
    case APPLICATION_VND_NOKIA_PCD_WBXML = 'APPLICATION_VND_NOKIA_PCD_WBXML';

    #[MimeTypeInfo(mimeType: 'application/vnd.nokia.pcd+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_NOKIA_PCD_XML = 'APPLICATION_VND_NOKIA_PCD_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.nokia.radio-preset', extensions: ['rpst'], category: 'application')]
    case APPLICATION_VND_NOKIA_RADIO_PRESET = 'APPLICATION_VND_NOKIA_RADIO_PRESET';

    #[MimeTypeInfo(mimeType: 'application/vnd.nokia.radio-presets', extensions: ['rpss'], category: 'application')]
    case APPLICATION_VND_NOKIA_RADIO_PRESETS = 'APPLICATION_VND_NOKIA_RADIO_PRESETS';

    #[MimeTypeInfo(mimeType: 'application/vnd.novadigm.edm', extensions: ['edm'], category: 'application')]
    case APPLICATION_VND_NOVADIGM_EDM = 'APPLICATION_VND_NOVADIGM_EDM';

    #[MimeTypeInfo(mimeType: 'application/vnd.novadigm.edx', extensions: ['edx'], category: 'application')]
    case APPLICATION_VND_NOVADIGM_EDX = 'APPLICATION_VND_NOVADIGM_EDX';

    #[MimeTypeInfo(mimeType: 'application/vnd.novadigm.ext', extensions: ['ext'], category: 'application')]
    case APPLICATION_VND_NOVADIGM_EXT = 'APPLICATION_VND_NOVADIGM_EXT';

    #[MimeTypeInfo(mimeType: 'application/vnd.ntt-local.content-share', extensions: [], category: 'application')]
    case APPLICATION_VND_NTT_LOCAL_CONTENT_SHARE = 'APPLICATION_VND_NTT_LOCAL_CONTENT_SHARE';

    #[MimeTypeInfo(mimeType: 'application/vnd.ntt-local.file-transfer', extensions: [], category: 'application')]
    case APPLICATION_VND_NTT_LOCAL_FILE_TRANSFER = 'APPLICATION_VND_NTT_LOCAL_FILE_TRANSFER';

    #[MimeTypeInfo(mimeType: 'application/vnd.ntt-local.ogw_remote-access', extensions: [], category: 'application')]
    case APPLICATION_VND_NTT_LOCAL_OGW_REMOTE_ACCESS = 'APPLICATION_VND_NTT_LOCAL_OGW_REMOTE_ACCESS';

    #[MimeTypeInfo(mimeType: 'application/vnd.ntt-local.sip-ta_remote', extensions: [], category: 'application')]
    case APPLICATION_VND_NTT_LOCAL_SIP_TA_REMOTE = 'APPLICATION_VND_NTT_LOCAL_SIP_TA_REMOTE';

    #[MimeTypeInfo(mimeType: 'application/vnd.ntt-local.sip-ta_tcp_stream', extensions: [], category: 'application')]
    case APPLICATION_VND_NTT_LOCAL_SIP_TA_TCP_STREAM = 'APPLICATION_VND_NTT_LOCAL_SIP_TA_TCP_STREAM';

    #[MimeTypeInfo(mimeType: 'application/vnd.oai.workflows', extensions: ['json', 'yaml'], category: 'application')]
    case APPLICATION_VND_OAI_WORKFLOWS = 'APPLICATION_VND_OAI_WORKFLOWS';

    #[MimeTypeInfo(mimeType: 'application/vnd.oai.workflows+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_OAI_WORKFLOWS_JSON = 'APPLICATION_VND_OAI_WORKFLOWS_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.oai.workflows+yaml', extensions: ['yaml', 'yml'], category: 'application')]
    case APPLICATION_VND_OAI_WORKFLOWS_YAML = 'APPLICATION_VND_OAI_WORKFLOWS_YAML';

    #[MimeTypeInfo(mimeType: 'application/vnd.oasis.opendocument.base', extensions: ['odb'], category: 'application')]
    case APPLICATION_VND_OASIS_OPENDOCUMENT_BASE = 'APPLICATION_VND_OASIS_OPENDOCUMENT_BASE';

    #[MimeTypeInfo(mimeType: 'application/vnd.oasis.opendocument.chart', extensions: ['odc'], category: 'application')]
    case APPLICATION_VND_OASIS_OPENDOCUMENT_CHART = 'APPLICATION_VND_OASIS_OPENDOCUMENT_CHART';

    #[MimeTypeInfo(mimeType: 'application/vnd.oasis.opendocument.chart-template', extensions: ['otc'], category: 'application')]
    case APPLICATION_VND_OASIS_OPENDOCUMENT_CHART_TEMPLATE = 'APPLICATION_VND_OASIS_OPENDOCUMENT_CHART_TEMPLATE';

    #[MimeTypeInfo(mimeType: 'application/vnd.oasis.opendocument.database', extensions: ['odb'], category: 'application')]
    case APPLICATION_VND_OASIS_OPENDOCUMENT_DATABASE = 'APPLICATION_VND_OASIS_OPENDOCUMENT_DATABASE';

    #[MimeTypeInfo(mimeType: 'application/vnd.oasis.opendocument.formula', extensions: ['odf'], category: 'application')]
    case APPLICATION_VND_OASIS_OPENDOCUMENT_FORMULA = 'APPLICATION_VND_OASIS_OPENDOCUMENT_FORMULA';

    #[MimeTypeInfo(mimeType: 'application/vnd.oasis.opendocument.formula-template', extensions: ['odft', 'otf'], category: 'application')]
    case APPLICATION_VND_OASIS_OPENDOCUMENT_FORMULA_TEMPLATE = 'APPLICATION_VND_OASIS_OPENDOCUMENT_FORMULA_TEMPLATE';

    #[MimeTypeInfo(mimeType: 'application/vnd.oasis.opendocument.graphics', extensions: ['odg'], category: 'application')]
    case APPLICATION_VND_OASIS_OPENDOCUMENT_GRAPHICS = 'APPLICATION_VND_OASIS_OPENDOCUMENT_GRAPHICS';

    #[MimeTypeInfo(mimeType: 'application/vnd.oasis.opendocument.graphics-template', extensions: ['otg'], category: 'application')]
    case APPLICATION_VND_OASIS_OPENDOCUMENT_GRAPHICS_TEMPLATE = 'APPLICATION_VND_OASIS_OPENDOCUMENT_GRAPHICS_TEMPLATE';

    #[MimeTypeInfo(mimeType: 'application/vnd.oasis.opendocument.image', extensions: ['odi'], category: 'application')]
    case APPLICATION_VND_OASIS_OPENDOCUMENT_IMAGE = 'APPLICATION_VND_OASIS_OPENDOCUMENT_IMAGE';

    #[MimeTypeInfo(mimeType: 'application/vnd.oasis.opendocument.image-template', extensions: ['oti'], category: 'application')]
    case APPLICATION_VND_OASIS_OPENDOCUMENT_IMAGE_TEMPLATE = 'APPLICATION_VND_OASIS_OPENDOCUMENT_IMAGE_TEMPLATE';

    #[MimeTypeInfo(mimeType: 'application/vnd.oasis.opendocument.presentation', extensions: ['odp'], category: 'application')]
    case APPLICATION_VND_OASIS_OPENDOCUMENT_PRESENTATION = 'APPLICATION_VND_OASIS_OPENDOCUMENT_PRESENTATION';

    #[MimeTypeInfo(mimeType: 'application/vnd.oasis.opendocument.presentation-template', extensions: ['otp'], category: 'application')]
    case APPLICATION_VND_OASIS_OPENDOCUMENT_PRESENTATION_TEMPLATE = 'APPLICATION_VND_OASIS_OPENDOCUMENT_PRESENTATION_TEMPLATE';

    #[MimeTypeInfo(mimeType: 'application/vnd.oasis.opendocument.spreadsheet', extensions: ['ods'], category: 'application')]
    case APPLICATION_VND_OASIS_OPENDOCUMENT_SPREADSHEET = 'APPLICATION_VND_OASIS_OPENDOCUMENT_SPREADSHEET';

    #[MimeTypeInfo(mimeType: 'application/vnd.oasis.opendocument.spreadsheet-template', extensions: ['ots'], category: 'application')]
    case APPLICATION_VND_OASIS_OPENDOCUMENT_SPREADSHEET_TEMPLATE = 'APPLICATION_VND_OASIS_OPENDOCUMENT_SPREADSHEET_TEMPLATE';

    #[MimeTypeInfo(mimeType: 'application/vnd.oasis.opendocument.text', extensions: ['odt'], category: 'application')]
    case APPLICATION_VND_OASIS_OPENDOCUMENT_TEXT = 'APPLICATION_VND_OASIS_OPENDOCUMENT_TEXT';

    #[MimeTypeInfo(mimeType: 'application/vnd.oasis.opendocument.text-master', extensions: ['odm'], category: 'application')]
    case APPLICATION_VND_OASIS_OPENDOCUMENT_TEXT_MASTER = 'APPLICATION_VND_OASIS_OPENDOCUMENT_TEXT_MASTER';

    #[MimeTypeInfo(mimeType: 'application/vnd.oasis.opendocument.text-master-template', extensions: ['otm'], category: 'application')]
    case APPLICATION_VND_OASIS_OPENDOCUMENT_TEXT_MASTER_TEMPLATE = 'APPLICATION_VND_OASIS_OPENDOCUMENT_TEXT_MASTER_TEMPLATE';

    #[MimeTypeInfo(mimeType: 'application/vnd.oasis.opendocument.text-template', extensions: ['ott'], category: 'application')]
    case APPLICATION_VND_OASIS_OPENDOCUMENT_TEXT_TEMPLATE = 'APPLICATION_VND_OASIS_OPENDOCUMENT_TEXT_TEMPLATE';

    #[MimeTypeInfo(mimeType: 'application/vnd.oasis.opendocument.text-web', extensions: ['oth'], category: 'application')]
    case APPLICATION_VND_OASIS_OPENDOCUMENT_TEXT_WEB = 'APPLICATION_VND_OASIS_OPENDOCUMENT_TEXT_WEB';

    #[MimeTypeInfo(mimeType: 'application/vnd.obn', extensions: [], category: 'application')]
    case APPLICATION_VND_OBN = 'APPLICATION_VND_OBN';

    #[MimeTypeInfo(mimeType: 'application/vnd.ocf+cbor', extensions: [], category: 'application')]
    case APPLICATION_VND_OCF_CBOR = 'APPLICATION_VND_OCF_CBOR';

    #[MimeTypeInfo(mimeType: 'application/vnd.oci.image.manifest.v1+json', extensions: [], category: 'application')]
    case APPLICATION_VND_OCI_IMAGE_MANIFEST_V1_JSON = 'APPLICATION_VND_OCI_IMAGE_MANIFEST_V1_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.oftn.l10n+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_OFTN_L10N_JSON = 'APPLICATION_VND_OFTN_L10N_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.oipf.contentaccessdownload+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OIPF_CONTENTACCESSDOWNLOAD_XML = 'APPLICATION_VND_OIPF_CONTENTACCESSDOWNLOAD_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.oipf.contentaccessstreaming+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OIPF_CONTENTACCESSSTREAMING_XML = 'APPLICATION_VND_OIPF_CONTENTACCESSSTREAMING_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.oipf.cspg-hexbinary', extensions: [], category: 'application')]
    case APPLICATION_VND_OIPF_CSPG_HEXBINARY = 'APPLICATION_VND_OIPF_CSPG_HEXBINARY';

    #[MimeTypeInfo(mimeType: 'application/vnd.oipf.dae.svg+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OIPF_DAE_SVG_XML = 'APPLICATION_VND_OIPF_DAE_SVG_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.oipf.dae.xhtml+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OIPF_DAE_XHTML_XML = 'APPLICATION_VND_OIPF_DAE_XHTML_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.oipf.mippvcontrolmessage+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OIPF_MIPPVCONTROLMESSAGE_XML = 'APPLICATION_VND_OIPF_MIPPVCONTROLMESSAGE_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.oipf.pae.gem', extensions: [], category: 'application')]
    case APPLICATION_VND_OIPF_PAE_GEM = 'APPLICATION_VND_OIPF_PAE_GEM';

    #[MimeTypeInfo(mimeType: 'application/vnd.oipf.spdiscovery+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OIPF_SPDISCOVERY_XML = 'APPLICATION_VND_OIPF_SPDISCOVERY_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.oipf.spdlist+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OIPF_SPDLIST_XML = 'APPLICATION_VND_OIPF_SPDLIST_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.oipf.ueprofile+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OIPF_UEPROFILE_XML = 'APPLICATION_VND_OIPF_UEPROFILE_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.oipf.userprofile+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OIPF_USERPROFILE_XML = 'APPLICATION_VND_OIPF_USERPROFILE_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.olpc-sugar', extensions: ['xo'], category: 'application')]
    case APPLICATION_VND_OLPC_SUGAR = 'APPLICATION_VND_OLPC_SUGAR';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma-scws-config', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_SCWS_CONFIG = 'APPLICATION_VND_OMA_SCWS_CONFIG';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma-scws-http-request', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_SCWS_HTTP_REQUEST = 'APPLICATION_VND_OMA_SCWS_HTTP_REQUEST';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma-scws-http-response', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_SCWS_HTTP_RESPONSE = 'APPLICATION_VND_OMA_SCWS_HTTP_RESPONSE';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.bcast.associated-procedure-parameter+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_BCAST_ASSOCIATED_PROCEDURE_PARAMETER_XML = 'APPLICATION_VND_OMA_BCAST_ASSOCIATED_PROCEDURE_PARAMETER_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.bcast.drm-trigger+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_BCAST_DRM_TRIGGER_XML = 'APPLICATION_VND_OMA_BCAST_DRM_TRIGGER_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.bcast.imd+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_BCAST_IMD_XML = 'APPLICATION_VND_OMA_BCAST_IMD_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.bcast.ltkm', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_BCAST_LTKM = 'APPLICATION_VND_OMA_BCAST_LTKM';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.bcast.notification+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_BCAST_NOTIFICATION_XML = 'APPLICATION_VND_OMA_BCAST_NOTIFICATION_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.bcast.provisioningtrigger', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_BCAST_PROVISIONINGTRIGGER = 'APPLICATION_VND_OMA_BCAST_PROVISIONINGTRIGGER';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.bcast.sgboot', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_BCAST_SGBOOT = 'APPLICATION_VND_OMA_BCAST_SGBOOT';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.bcast.sgdd+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_BCAST_SGDD_XML = 'APPLICATION_VND_OMA_BCAST_SGDD_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.bcast.sgdu', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_BCAST_SGDU = 'APPLICATION_VND_OMA_BCAST_SGDU';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.bcast.simple-symbol-container', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_BCAST_SIMPLE_SYMBOL_CONTAINER = 'APPLICATION_VND_OMA_BCAST_SIMPLE_SYMBOL_CONTAINER';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.bcast.smartcard-trigger+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_BCAST_SMARTCARD_TRIGGER_XML = 'APPLICATION_VND_OMA_BCAST_SMARTCARD_TRIGGER_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.bcast.sprov+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_BCAST_SPROV_XML = 'APPLICATION_VND_OMA_BCAST_SPROV_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.bcast.stkm', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_BCAST_STKM = 'APPLICATION_VND_OMA_BCAST_STKM';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.cab-address-book+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_CAB_ADDRESS_BOOK_XML = 'APPLICATION_VND_OMA_CAB_ADDRESS_BOOK_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.cab-feature-handler+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_CAB_FEATURE_HANDLER_XML = 'APPLICATION_VND_OMA_CAB_FEATURE_HANDLER_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.cab-pcc+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_CAB_PCC_XML = 'APPLICATION_VND_OMA_CAB_PCC_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.cab-subs-invite+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_CAB_SUBS_INVITE_XML = 'APPLICATION_VND_OMA_CAB_SUBS_INVITE_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.cab-user-prefs+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_CAB_USER_PREFS_XML = 'APPLICATION_VND_OMA_CAB_USER_PREFS_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.dcd', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_DCD = 'APPLICATION_VND_OMA_DCD';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.dcdc', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_DCDC = 'APPLICATION_VND_OMA_DCDC';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.dd2+xml', extensions: ['dd2', 'xml'], category: 'application')]
    case APPLICATION_VND_OMA_DD2_XML = 'APPLICATION_VND_OMA_DD2_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.drm.risd+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_DRM_RISD_XML = 'APPLICATION_VND_OMA_DRM_RISD_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.group-usage-list+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_GROUP_USAGE_LIST_XML = 'APPLICATION_VND_OMA_GROUP_USAGE_LIST_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.lwm2m+cbor', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_LWM2M_CBOR = 'APPLICATION_VND_OMA_LWM2M_CBOR';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.lwm2m+json', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_LWM2M_JSON = 'APPLICATION_VND_OMA_LWM2M_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.lwm2m+tlv', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_LWM2M_TLV = 'APPLICATION_VND_OMA_LWM2M_TLV';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.pal+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_PAL_XML = 'APPLICATION_VND_OMA_PAL_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.poc.detailed-progress-report+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_POC_DETAILED_PROGRESS_REPORT_XML = 'APPLICATION_VND_OMA_POC_DETAILED_PROGRESS_REPORT_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.poc.final-report+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_POC_FINAL_REPORT_XML = 'APPLICATION_VND_OMA_POC_FINAL_REPORT_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.poc.groups+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_POC_GROUPS_XML = 'APPLICATION_VND_OMA_POC_GROUPS_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.poc.invocation-descriptor+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_POC_INVOCATION_DESCRIPTOR_XML = 'APPLICATION_VND_OMA_POC_INVOCATION_DESCRIPTOR_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.poc.optimized-progress-report+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_POC_OPTIMIZED_PROGRESS_REPORT_XML = 'APPLICATION_VND_OMA_POC_OPTIMIZED_PROGRESS_REPORT_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.push', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_PUSH = 'APPLICATION_VND_OMA_PUSH';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.scidm.messages+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_SCIDM_MESSAGES_XML = 'APPLICATION_VND_OMA_SCIDM_MESSAGES_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.oma.xcap-directory+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OMA_XCAP_DIRECTORY_XML = 'APPLICATION_VND_OMA_XCAP_DIRECTORY_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.omads-email+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OMADS_EMAIL_XML = 'APPLICATION_VND_OMADS_EMAIL_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.omads-file+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OMADS_FILE_XML = 'APPLICATION_VND_OMADS_FILE_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.omads-folder+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OMADS_FOLDER_XML = 'APPLICATION_VND_OMADS_FOLDER_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.omaloc-supl-init', extensions: [], category: 'application')]
    case APPLICATION_VND_OMALOC_SUPL_INIT = 'APPLICATION_VND_OMALOC_SUPL_INIT';

    #[MimeTypeInfo(mimeType: 'application/vnd.oms.cellular-cose-content+cbor', extensions: [], category: 'application')]
    case APPLICATION_VND_OMS_CELLULAR_COSE_CONTENT_CBOR = 'APPLICATION_VND_OMS_CELLULAR_COSE_CONTENT_CBOR';

    #[MimeTypeInfo(mimeType: 'application/vnd.onepager', extensions: ['tam'], category: 'application')]
    case APPLICATION_VND_ONEPAGER = 'APPLICATION_VND_ONEPAGER';

    #[MimeTypeInfo(mimeType: 'application/vnd.onepagertamp', extensions: ['tamp'], category: 'application')]
    case APPLICATION_VND_ONEPAGERTAMP = 'APPLICATION_VND_ONEPAGERTAMP';

    #[MimeTypeInfo(mimeType: 'application/vnd.onepagertamx', extensions: ['tamx'], category: 'application')]
    case APPLICATION_VND_ONEPAGERTAMX = 'APPLICATION_VND_ONEPAGERTAMX';

    #[MimeTypeInfo(mimeType: 'application/vnd.onepagertat', extensions: ['tat'], category: 'application')]
    case APPLICATION_VND_ONEPAGERTAT = 'APPLICATION_VND_ONEPAGERTAT';

    #[MimeTypeInfo(mimeType: 'application/vnd.onepagertatp', extensions: ['tatp'], category: 'application')]
    case APPLICATION_VND_ONEPAGERTATP = 'APPLICATION_VND_ONEPAGERTATP';

    #[MimeTypeInfo(mimeType: 'application/vnd.onepagertatx', extensions: ['tatx'], category: 'application')]
    case APPLICATION_VND_ONEPAGERTATX = 'APPLICATION_VND_ONEPAGERTATX';

    #[MimeTypeInfo(mimeType: 'application/vnd.onvif.metadata', extensions: [], category: 'application')]
    case APPLICATION_VND_ONVIF_METADATA = 'APPLICATION_VND_ONVIF_METADATA';

    #[MimeTypeInfo(mimeType: 'application/vnd.openblox.game+xml', extensions: ['obgx'], category: 'application')]
    case APPLICATION_VND_OPENBLOX_GAME_XML = 'APPLICATION_VND_OPENBLOX_GAME_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openblox.game-binary', extensions: ['obg'], category: 'application')]
    case APPLICATION_VND_OPENBLOX_GAME_BINARY = 'APPLICATION_VND_OPENBLOX_GAME_BINARY';

    #[MimeTypeInfo(mimeType: 'application/vnd.openeye.oeb', extensions: ['oeb'], category: 'application')]
    case APPLICATION_VND_OPENEYE_OEB = 'APPLICATION_VND_OPENEYE_OEB';

    #[MimeTypeInfo(mimeType: 'application/vnd.openofficeorg.extension', extensions: ['oxt'], category: 'application')]
    case APPLICATION_VND_OPENOFFICEORG_EXTENSION = 'APPLICATION_VND_OPENOFFICEORG_EXTENSION';

    #[MimeTypeInfo(mimeType: 'application/vnd.openstreetmap.data+xml', extensions: ['osm'], category: 'application')]
    case APPLICATION_VND_OPENSTREETMAP_DATA_XML = 'APPLICATION_VND_OPENSTREETMAP_DATA_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.opentimestamps.ots', extensions: ['ots'], category: 'application')]
    case APPLICATION_VND_OPENTIMESTAMPS_OTS = 'APPLICATION_VND_OPENTIMESTAMPS_OTS';

    #[MimeTypeInfo(mimeType: 'application/vnd.openvpi.dspx+json', extensions: ['dspx'], category: 'application')]
    case APPLICATION_VND_OPENVPI_DSPX_JSON = 'APPLICATION_VND_OPENVPI_DSPX_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.custom-properties+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_CUSTOM_PROPERTIES_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_CUSTOM_PROPERTIES_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.customxmlproperties+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_CUSTOMXMLPROPERTIES_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_CUSTOMXMLPROPERTIES_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.drawing+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_DRAWING_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_DRAWING_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.drawingml.chart+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_DRAWINGML_CHART_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_DRAWINGML_CHART_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.drawingml.chartshapes+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_DRAWINGML_CHARTSHAPES_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_DRAWINGML_CHARTSHAPES_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.drawingml.diagramcolors+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_DRAWINGML_DIAGRAMCOLORS_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_DRAWINGML_DIAGRAMCOLORS_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.drawingml.diagramdata+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_DRAWINGML_DIAGRAMDATA_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_DRAWINGML_DIAGRAMDATA_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.drawingml.diagramlayout+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_DRAWINGML_DIAGRAMLAYOUT_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_DRAWINGML_DIAGRAMLAYOUT_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.drawingml.diagramstyle+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_DRAWINGML_DIAGRAMSTYLE_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_DRAWINGML_DIAGRAMSTYLE_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.extended-properties+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_EXTENDED_PROPERTIES_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_EXTENDED_PROPERTIES_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.presentationml.commentauthors+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_COMMENTAUTHORS_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_COMMENTAUTHORS_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.presentationml.comments+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_COMMENTS_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_COMMENTS_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.presentationml.handoutmaster+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_HANDOUTMASTER_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_HANDOUTMASTER_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.presentationml.notesmaster+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_NOTESMASTER_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_NOTESMASTER_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.presentationml.notesslide+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_NOTESSLIDE_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_NOTESSLIDE_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.presentationml.presentation', extensions: ['pptx'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_PRESENTATION = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_PRESENTATION';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_PRESENTATION_MAIN_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_PRESENTATION_MAIN_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.presentationml.presprops+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_PRESPROPS_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_PRESPROPS_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.presentationml.slide', extensions: ['sldx'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_SLIDE = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_SLIDE';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.presentationml.slide+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_SLIDE_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_SLIDE_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.presentationml.slidelayout+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_SLIDELAYOUT_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_SLIDELAYOUT_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.presentationml.slidemaster+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_SLIDEMASTER_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_SLIDEMASTER_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.presentationml.slideshow', extensions: ['ppsx'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_SLIDESHOW = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_SLIDESHOW';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.presentationml.slideshow.main+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_SLIDESHOW_MAIN_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_SLIDESHOW_MAIN_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.presentationml.slideupdateinfo+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_SLIDEUPDATEINFO_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_SLIDEUPDATEINFO_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.presentationml.tablestyles+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_TABLESTYLES_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_TABLESTYLES_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.presentationml.tags+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_TAGS_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_TAGS_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.presentationml.template', extensions: ['potx'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_TEMPLATE = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_TEMPLATE';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.presentationml.template.main+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_TEMPLATE_MAIN_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_TEMPLATE_MAIN_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.presentationml.viewprops+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_VIEWPROPS_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_VIEWPROPS_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.calcchain+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_CALCCHAIN_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_CALCCHAIN_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.chartsheet+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_CHARTSHEET_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_CHARTSHEET_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.comments+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_COMMENTS_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_COMMENTS_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.connections+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_CONNECTIONS_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_CONNECTIONS_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.dialogsheet+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_DIALOGSHEET_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_DIALOGSHEET_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.externallink+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_EXTERNALLINK_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_EXTERNALLINK_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.pivotcachedefinition+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_PIVOTCACHEDEFINITION_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_PIVOTCACHEDEFINITION_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.pivotcacherecords+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_PIVOTCACHERECORDS_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_PIVOTCACHERECORDS_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.pivottable+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_PIVOTTABLE_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_PIVOTTABLE_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.querytable+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_QUERYTABLE_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_QUERYTABLE_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.revisionheaders+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_REVISIONHEADERS_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_REVISIONHEADERS_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.revisionlog+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_REVISIONLOG_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_REVISIONLOG_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sharedstrings+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_SHAREDSTRINGS_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_SHAREDSTRINGS_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', extensions: ['xlsx'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_SHEET = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_SHEET';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_SHEET_MAIN_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_SHEET_MAIN_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheetmetadata+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_SHEETMETADATA_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_SHEETMETADATA_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_STYLES_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_STYLES_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.table+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_TABLE_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_TABLE_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.tablesinglecells+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_TABLESINGLECELLS_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_TABLESINGLECELLS_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.template', extensions: ['xltx'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_TEMPLATE = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_TEMPLATE';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.template.main+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_TEMPLATE_MAIN_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_TEMPLATE_MAIN_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.usernames+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_USERNAMES_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_USERNAMES_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.volatiledependencies+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_VOLATILEDEPENDENCIES_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_VOLATILEDEPENDENCIES_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_WORKSHEET_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_SPREADSHEETML_WORKSHEET_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.theme+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_THEME_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_THEME_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.themeoverride+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_THEMEOVERRIDE_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_THEMEOVERRIDE_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.vmldrawing', extensions: [], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_VMLDRAWING = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_VMLDRAWING';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_WORDPROCESSINGML_COMMENTS_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_WORDPROCESSINGML_COMMENTS_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', extensions: ['docx'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_WORDPROCESSINGML_DOCUMENT = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_WORDPROCESSINGML_DOCUMENT';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.glossary+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_WORDPROCESSINGML_DOCUMENT_GLOSSARY_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_WORDPROCESSINGML_DOCUMENT_GLOSSARY_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_WORDPROCESSINGML_DOCUMENT_MAIN_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_WORDPROCESSINGML_DOCUMENT_MAIN_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.endnotes+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_WORDPROCESSINGML_ENDNOTES_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_WORDPROCESSINGML_ENDNOTES_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.fonttable+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_WORDPROCESSINGML_FONTTABLE_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_WORDPROCESSINGML_FONTTABLE_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_WORDPROCESSINGML_FOOTER_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_WORDPROCESSINGML_FOOTER_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_WORDPROCESSINGML_FOOTNOTES_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_WORDPROCESSINGML_FOOTNOTES_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_WORDPROCESSINGML_NUMBERING_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_WORDPROCESSINGML_NUMBERING_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_WORDPROCESSINGML_SETTINGS_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_WORDPROCESSINGML_SETTINGS_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_WORDPROCESSINGML_STYLES_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_WORDPROCESSINGML_STYLES_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.template', extensions: ['dotx'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_WORDPROCESSINGML_TEMPLATE = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_WORDPROCESSINGML_TEMPLATE';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.template.main+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_WORDPROCESSINGML_TEMPLATE_MAIN_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_WORDPROCESSINGML_TEMPLATE_MAIN_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.websettings+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_WORDPROCESSINGML_WEBSETTINGS_XML = 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_WORDPROCESSINGML_WEBSETTINGS_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-package.core-properties+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_PACKAGE_CORE_PROPERTIES_XML = 'APPLICATION_VND_OPENXMLFORMATS_PACKAGE_CORE_PROPERTIES_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_PACKAGE_DIGITAL_SIGNATURE_XMLSIGNATURE_XML = 'APPLICATION_VND_OPENXMLFORMATS_PACKAGE_DIGITAL_SIGNATURE_XMLSIGNATURE_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.openxmlformats-package.relationships+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_OPENXMLFORMATS_PACKAGE_RELATIONSHIPS_XML = 'APPLICATION_VND_OPENXMLFORMATS_PACKAGE_RELATIONSHIPS_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.oracle.resource+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_ORACLE_RESOURCE_JSON = 'APPLICATION_VND_ORACLE_RESOURCE_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.orange.indata', extensions: [], category: 'application')]
    case APPLICATION_VND_ORANGE_INDATA = 'APPLICATION_VND_ORANGE_INDATA';

    #[MimeTypeInfo(mimeType: 'application/vnd.osa.netdeploy', extensions: ['ndc'], category: 'application')]
    case APPLICATION_VND_OSA_NETDEPLOY = 'APPLICATION_VND_OSA_NETDEPLOY';

    #[MimeTypeInfo(mimeType: 'application/vnd.osgeo.mapguide.package', extensions: ['mgp'], category: 'application')]
    case APPLICATION_VND_OSGEO_MAPGUIDE_PACKAGE = 'APPLICATION_VND_OSGEO_MAPGUIDE_PACKAGE';

    #[MimeTypeInfo(mimeType: 'application/vnd.osgi.bundle', extensions: ['jar'], category: 'application')]
    case APPLICATION_VND_OSGI_BUNDLE = 'APPLICATION_VND_OSGI_BUNDLE';

    #[MimeTypeInfo(mimeType: 'application/vnd.osgi.dp', extensions: ['dp'], category: 'application')]
    case APPLICATION_VND_OSGI_DP = 'APPLICATION_VND_OSGI_DP';

    #[MimeTypeInfo(mimeType: 'application/vnd.osgi.subsystem', extensions: ['esa'], category: 'application')]
    case APPLICATION_VND_OSGI_SUBSYSTEM = 'APPLICATION_VND_OSGI_SUBSYSTEM';

    #[MimeTypeInfo(mimeType: 'application/vnd.otps.ct-kip+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_OTPS_CT_KIP_XML = 'APPLICATION_VND_OTPS_CT_KIP_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.oxli.countgraph', extensions: ['oxlicg'], category: 'application')]
    case APPLICATION_VND_OXLI_COUNTGRAPH = 'APPLICATION_VND_OXLI_COUNTGRAPH';

    #[MimeTypeInfo(mimeType: 'application/vnd.pagerduty+json', extensions: [], category: 'application')]
    case APPLICATION_VND_PAGERDUTY_JSON = 'APPLICATION_VND_PAGERDUTY_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.palm', extensions: ['pdb', 'pqa', 'oprc', 'prc'], category: 'application')]
    case APPLICATION_VND_PALM = 'APPLICATION_VND_PALM';

    #[MimeTypeInfo(mimeType: 'application/vnd.panoply', extensions: ['plp'], category: 'application')]
    case APPLICATION_VND_PANOPLY = 'APPLICATION_VND_PANOPLY';

    #[MimeTypeInfo(mimeType: 'application/vnd.paos.xml', extensions: [], category: 'application')]
    case APPLICATION_VND_PAOS_XML = 'APPLICATION_VND_PAOS_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.patentdive', extensions: ['dive'], category: 'application')]
    case APPLICATION_VND_PATENTDIVE = 'APPLICATION_VND_PATENTDIVE';

    #[MimeTypeInfo(mimeType: 'application/vnd.patientecommsdoc', extensions: [], category: 'application')]
    case APPLICATION_VND_PATIENTECOMMSDOC = 'APPLICATION_VND_PATIENTECOMMSDOC';

    #[MimeTypeInfo(mimeType: 'application/vnd.pawaafile', extensions: ['paw'], category: 'application')]
    case APPLICATION_VND_PAWAAFILE = 'APPLICATION_VND_PAWAAFILE';

    #[MimeTypeInfo(mimeType: 'application/vnd.pcos', extensions: [], category: 'application')]
    case APPLICATION_VND_PCOS = 'APPLICATION_VND_PCOS';

    #[MimeTypeInfo(mimeType: 'application/vnd.pg.format', extensions: ['str'], category: 'application')]
    case APPLICATION_VND_PG_FORMAT = 'APPLICATION_VND_PG_FORMAT';

    #[MimeTypeInfo(mimeType: 'application/vnd.pg.osasli', extensions: ['ei6'], category: 'application')]
    case APPLICATION_VND_PG_OSASLI = 'APPLICATION_VND_PG_OSASLI';

    #[MimeTypeInfo(mimeType: 'application/vnd.piaccess.application-licence', extensions: ['pil'], category: 'application')]
    case APPLICATION_VND_PIACCESS_APPLICATION_LICENCE = 'APPLICATION_VND_PIACCESS_APPLICATION_LICENCE';

    #[MimeTypeInfo(mimeType: 'application/vnd.picsel', extensions: ['efif'], category: 'application')]
    case APPLICATION_VND_PICSEL = 'APPLICATION_VND_PICSEL';

    #[MimeTypeInfo(mimeType: 'application/vnd.pmi.widget', extensions: ['wg'], category: 'application')]
    case APPLICATION_VND_PMI_WIDGET = 'APPLICATION_VND_PMI_WIDGET';

    #[MimeTypeInfo(mimeType: 'application/vnd.pmtiles', extensions: ['pmtiles'], category: 'application')]
    case APPLICATION_VND_PMTILES = 'APPLICATION_VND_PMTILES';

    #[MimeTypeInfo(mimeType: 'application/vnd.poc.group-advertisement+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_POC_GROUP_ADVERTISEMENT_XML = 'APPLICATION_VND_POC_GROUP_ADVERTISEMENT_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.pocketlearn', extensions: ['plf'], category: 'application')]
    case APPLICATION_VND_POCKETLEARN = 'APPLICATION_VND_POCKETLEARN';

    #[MimeTypeInfo(mimeType: 'application/vnd.powerbuilder6', extensions: ['pbd'], category: 'application')]
    case APPLICATION_VND_POWERBUILDER6 = 'APPLICATION_VND_POWERBUILDER6';

    #[MimeTypeInfo(mimeType: 'application/vnd.powerbuilder6-s', extensions: ['pbd'], category: 'application')]
    case APPLICATION_VND_POWERBUILDER6_S = 'APPLICATION_VND_POWERBUILDER6_S';

    #[MimeTypeInfo(mimeType: 'application/vnd.powerbuilder7', extensions: ['pbd'], category: 'application')]
    case APPLICATION_VND_POWERBUILDER7 = 'APPLICATION_VND_POWERBUILDER7';

    #[MimeTypeInfo(mimeType: 'application/vnd.powerbuilder7-s', extensions: ['pbd'], category: 'application')]
    case APPLICATION_VND_POWERBUILDER7_S = 'APPLICATION_VND_POWERBUILDER7_S';

    #[MimeTypeInfo(mimeType: 'application/vnd.powerbuilder75', extensions: ['pbd'], category: 'application')]
    case APPLICATION_VND_POWERBUILDER75 = 'APPLICATION_VND_POWERBUILDER75';

    #[MimeTypeInfo(mimeType: 'application/vnd.powerbuilder75-s', extensions: ['pbd'], category: 'application')]
    case APPLICATION_VND_POWERBUILDER75_S = 'APPLICATION_VND_POWERBUILDER75_S';

    #[MimeTypeInfo(mimeType: 'application/vnd.pp.systemverify+xml', extensions: ['systemverify'], category: 'application')]
    case APPLICATION_VND_PP_SYSTEMVERIFY_XML = 'APPLICATION_VND_PP_SYSTEMVERIFY_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.preminet', extensions: ['preminet'], category: 'application')]
    case APPLICATION_VND_PREMINET = 'APPLICATION_VND_PREMINET';

    #[MimeTypeInfo(mimeType: 'application/vnd.previewsystems.box', extensions: ['box', 'vbox'], category: 'application')]
    case APPLICATION_VND_PREVIEWSYSTEMS_BOX = 'APPLICATION_VND_PREVIEWSYSTEMS_BOX';

    #[MimeTypeInfo(mimeType: 'application/vnd.procrate.brushset', extensions: ['brushset'], category: 'application')]
    case APPLICATION_VND_PROCRATE_BRUSHSET = 'APPLICATION_VND_PROCRATE_BRUSHSET';

    #[MimeTypeInfo(mimeType: 'application/vnd.procreate.brush', extensions: ['brush'], category: 'application')]
    case APPLICATION_VND_PROCREATE_BRUSH = 'APPLICATION_VND_PROCREATE_BRUSH';

    #[MimeTypeInfo(mimeType: 'application/vnd.procreate.dream', extensions: ['drm'], category: 'application')]
    case APPLICATION_VND_PROCREATE_DREAM = 'APPLICATION_VND_PROCREATE_DREAM';

    #[MimeTypeInfo(mimeType: 'application/vnd.proteus.magazine', extensions: ['mgz'], category: 'application')]
    case APPLICATION_VND_PROTEUS_MAGAZINE = 'APPLICATION_VND_PROTEUS_MAGAZINE';

    #[MimeTypeInfo(mimeType: 'application/vnd.psfs', extensions: ['psfs'], category: 'application')]
    case APPLICATION_VND_PSFS = 'APPLICATION_VND_PSFS';

    #[MimeTypeInfo(mimeType: 'application/vnd.pt.mundusmundi', extensions: [], category: 'application')]
    case APPLICATION_VND_PT_MUNDUSMUNDI = 'APPLICATION_VND_PT_MUNDUSMUNDI';

    #[MimeTypeInfo(mimeType: 'application/vnd.publishare-delta-tree', extensions: ['qps'], category: 'application')]
    case APPLICATION_VND_PUBLISHARE_DELTA_TREE = 'APPLICATION_VND_PUBLISHARE_DELTA_TREE';

    #[MimeTypeInfo(mimeType: 'application/vnd.pvi.ptid1', extensions: ['ptid', 'pti'], category: 'application')]
    case APPLICATION_VND_PVI_PTID1 = 'APPLICATION_VND_PVI_PTID1';

    #[MimeTypeInfo(mimeType: 'application/vnd.pwg-multiplexed', extensions: [], category: 'application')]
    case APPLICATION_VND_PWG_MULTIPLEXED = 'APPLICATION_VND_PWG_MULTIPLEXED';

    #[MimeTypeInfo(mimeType: 'application/vnd.pwg-xhtml-print+xml', extensions: ['xhtml', 'xhtm'], category: 'application')]
    case APPLICATION_VND_PWG_XHTML_PRINT_XML = 'APPLICATION_VND_PWG_XHTML_PRINT_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.pyon+json', extensions: ['pyon'], category: 'application')]
    case APPLICATION_VND_PYON_JSON = 'APPLICATION_VND_PYON_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.qualcomm.brew-app-res', extensions: ['bar'], category: 'application')]
    case APPLICATION_VND_QUALCOMM_BREW_APP_RES = 'APPLICATION_VND_QUALCOMM_BREW_APP_RES';

    #[MimeTypeInfo(mimeType: 'application/vnd.quarantainenet', extensions: [], category: 'application')]
    case APPLICATION_VND_QUARANTAINENET = 'APPLICATION_VND_QUARANTAINENET';

    #[MimeTypeInfo(mimeType: 'application/vnd.quark.quarkxpress', extensions: ['qxd', 'qxt', 'qwd', 'qwt', 'qxl', 'qxb'], category: 'application')]
    case APPLICATION_VND_QUARK_QUARKXPRESS = 'APPLICATION_VND_QUARK_QUARKXPRESS';

    #[MimeTypeInfo(mimeType: 'application/vnd.quobject-quoxdocument', extensions: ['quox', 'quiz'], category: 'application')]
    case APPLICATION_VND_QUOBJECT_QUOXDOCUMENT = 'APPLICATION_VND_QUOBJECT_QUOXDOCUMENT';

    #[MimeTypeInfo(mimeType: 'application/vnd.r74n.sandboxels+json', extensions: ['sbxls'], category: 'application')]
    case APPLICATION_VND_R74N_SANDBOXELS_JSON = 'APPLICATION_VND_R74N_SANDBOXELS_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.radisys.moml+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_RADISYS_MOML_XML = 'APPLICATION_VND_RADISYS_MOML_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.radisys.msml+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_RADISYS_MSML_XML = 'APPLICATION_VND_RADISYS_MSML_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.radisys.msml-audit+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_RADISYS_MSML_AUDIT_XML = 'APPLICATION_VND_RADISYS_MSML_AUDIT_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.radisys.msml-audit-conf+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_RADISYS_MSML_AUDIT_CONF_XML = 'APPLICATION_VND_RADISYS_MSML_AUDIT_CONF_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.radisys.msml-audit-conn+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_RADISYS_MSML_AUDIT_CONN_XML = 'APPLICATION_VND_RADISYS_MSML_AUDIT_CONN_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.radisys.msml-audit-dialog+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_RADISYS_MSML_AUDIT_DIALOG_XML = 'APPLICATION_VND_RADISYS_MSML_AUDIT_DIALOG_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.radisys.msml-audit-stream+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_RADISYS_MSML_AUDIT_STREAM_XML = 'APPLICATION_VND_RADISYS_MSML_AUDIT_STREAM_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.radisys.msml-conf+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_RADISYS_MSML_CONF_XML = 'APPLICATION_VND_RADISYS_MSML_CONF_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.radisys.msml-dialog+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_RADISYS_MSML_DIALOG_XML = 'APPLICATION_VND_RADISYS_MSML_DIALOG_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.radisys.msml-dialog-base+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_RADISYS_MSML_DIALOG_BASE_XML = 'APPLICATION_VND_RADISYS_MSML_DIALOG_BASE_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.radisys.msml-dialog-fax-detect+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_RADISYS_MSML_DIALOG_FAX_DETECT_XML = 'APPLICATION_VND_RADISYS_MSML_DIALOG_FAX_DETECT_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.radisys.msml-dialog-fax-sendrecv+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_RADISYS_MSML_DIALOG_FAX_SENDRECV_XML = 'APPLICATION_VND_RADISYS_MSML_DIALOG_FAX_SENDRECV_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.radisys.msml-dialog-group+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_RADISYS_MSML_DIALOG_GROUP_XML = 'APPLICATION_VND_RADISYS_MSML_DIALOG_GROUP_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.radisys.msml-dialog-speech+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_RADISYS_MSML_DIALOG_SPEECH_XML = 'APPLICATION_VND_RADISYS_MSML_DIALOG_SPEECH_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.radisys.msml-dialog-transform+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_RADISYS_MSML_DIALOG_TRANSFORM_XML = 'APPLICATION_VND_RADISYS_MSML_DIALOG_TRANSFORM_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.rainstor.data', extensions: ['tree'], category: 'application')]
    case APPLICATION_VND_RAINSTOR_DATA = 'APPLICATION_VND_RAINSTOR_DATA';

    #[MimeTypeInfo(mimeType: 'application/vnd.rapid', extensions: [], category: 'application')]
    case APPLICATION_VND_RAPID = 'APPLICATION_VND_RAPID';

    #[MimeTypeInfo(mimeType: 'application/vnd.rar', extensions: ['rar'], category: 'application')]
    case APPLICATION_VND_RAR = 'APPLICATION_VND_RAR';

    #[MimeTypeInfo(mimeType: 'application/vnd.realvnc.bed', extensions: ['bed'], category: 'application')]
    case APPLICATION_VND_REALVNC_BED = 'APPLICATION_VND_REALVNC_BED';

    #[MimeTypeInfo(mimeType: 'application/vnd.recordare.musicxml', extensions: ['mxl'], category: 'application')]
    case APPLICATION_VND_RECORDARE_MUSICXML = 'APPLICATION_VND_RECORDARE_MUSICXML';

    #[MimeTypeInfo(mimeType: 'application/vnd.recordare.musicxml+xml', extensions: ['musicxml', 'xml'], category: 'application')]
    case APPLICATION_VND_RECORDARE_MUSICXML_XML = 'APPLICATION_VND_RECORDARE_MUSICXML_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.relpipe', extensions: ['rp'], category: 'application')]
    case APPLICATION_VND_RELPIPE = 'APPLICATION_VND_RELPIPE';

    #[MimeTypeInfo(mimeType: 'application/vnd.renlearn.rlprint', extensions: [], category: 'application')]
    case APPLICATION_VND_RENLEARN_RLPRINT = 'APPLICATION_VND_RENLEARN_RLPRINT';

    #[MimeTypeInfo(mimeType: 'application/vnd.resilient.logic', extensions: ['rlm', 'reload'], category: 'application')]
    case APPLICATION_VND_RESILIENT_LOGIC = 'APPLICATION_VND_RESILIENT_LOGIC';

    #[MimeTypeInfo(mimeType: 'application/vnd.restful+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_RESTFUL_JSON = 'APPLICATION_VND_RESTFUL_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.rig.cryptonote', extensions: ['cryptonote'], category: 'application')]
    case APPLICATION_VND_RIG_CRYPTONOTE = 'APPLICATION_VND_RIG_CRYPTONOTE';

    #[MimeTypeInfo(mimeType: 'application/vnd.rim.cod', extensions: ['cod'], category: 'application')]
    case APPLICATION_VND_RIM_COD = 'APPLICATION_VND_RIM_COD';

    #[MimeTypeInfo(mimeType: 'application/vnd.rn-realmedia', extensions: ['rm'], category: 'application')]
    case APPLICATION_VND_RN_REALMEDIA = 'APPLICATION_VND_RN_REALMEDIA';

    #[MimeTypeInfo(mimeType: 'application/vnd.rn-realmedia-vbr', extensions: ['rmvb'], category: 'application')]
    case APPLICATION_VND_RN_REALMEDIA_VBR = 'APPLICATION_VND_RN_REALMEDIA_VBR';

    #[MimeTypeInfo(mimeType: 'application/vnd.route66.link66+xml', extensions: ['link66'], category: 'application')]
    case APPLICATION_VND_ROUTE66_LINK66_XML = 'APPLICATION_VND_ROUTE66_LINK66_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.rs-274x', extensions: ['gbr'], category: 'application')]
    case APPLICATION_VND_RS_274X = 'APPLICATION_VND_RS_274X';

    #[MimeTypeInfo(mimeType: 'application/vnd.ruckus.download', extensions: [], category: 'application')]
    case APPLICATION_VND_RUCKUS_DOWNLOAD = 'APPLICATION_VND_RUCKUS_DOWNLOAD';

    #[MimeTypeInfo(mimeType: 'application/vnd.s3sms', extensions: [], category: 'application')]
    case APPLICATION_VND_S3SMS = 'APPLICATION_VND_S3SMS';

    #[MimeTypeInfo(mimeType: 'application/vnd.sailingtracker.track', extensions: ['st'], category: 'application')]
    case APPLICATION_VND_SAILINGTRACKER_TRACK = 'APPLICATION_VND_SAILINGTRACKER_TRACK';

    #[MimeTypeInfo(mimeType: 'application/vnd.sar', extensions: ['sar'], category: 'application')]
    case APPLICATION_VND_SAR = 'APPLICATION_VND_SAR';

    #[MimeTypeInfo(mimeType: 'application/vnd.sbm.cid', extensions: [], category: 'application')]
    case APPLICATION_VND_SBM_CID = 'APPLICATION_VND_SBM_CID';

    #[MimeTypeInfo(mimeType: 'application/vnd.sbm.mid2', extensions: [], category: 'application')]
    case APPLICATION_VND_SBM_MID2 = 'APPLICATION_VND_SBM_MID2';

    #[MimeTypeInfo(mimeType: 'application/vnd.scribus', extensions: ['scd', 'scd.gz', 'sla', 'sla.gz', 'slaz'], category: 'application')]
    case APPLICATION_VND_SCRIBUS = 'APPLICATION_VND_SCRIBUS';

    #[MimeTypeInfo(mimeType: 'application/vnd.sealed.3df', extensions: ['s3df'], category: 'application')]
    case APPLICATION_VND_SEALED_3DF = 'APPLICATION_VND_SEALED_3DF';

    #[MimeTypeInfo(mimeType: 'application/vnd.sealed.csf', extensions: ['scsf'], category: 'application')]
    case APPLICATION_VND_SEALED_CSF = 'APPLICATION_VND_SEALED_CSF';

    #[MimeTypeInfo(mimeType: 'application/vnd.sealed.doc', extensions: ['sdoc', 'sdo', 's1w'], category: 'application')]
    case APPLICATION_VND_SEALED_DOC = 'APPLICATION_VND_SEALED_DOC';

    #[MimeTypeInfo(mimeType: 'application/vnd.sealed.eml', extensions: ['seml', 'sem'], category: 'application')]
    case APPLICATION_VND_SEALED_EML = 'APPLICATION_VND_SEALED_EML';

    #[MimeTypeInfo(mimeType: 'application/vnd.sealed.mht', extensions: ['smht', 'smh'], category: 'application')]
    case APPLICATION_VND_SEALED_MHT = 'APPLICATION_VND_SEALED_MHT';

    #[MimeTypeInfo(mimeType: 'application/vnd.sealed.net', extensions: [], category: 'application')]
    case APPLICATION_VND_SEALED_NET = 'APPLICATION_VND_SEALED_NET';

    #[MimeTypeInfo(mimeType: 'application/vnd.sealed.ppt', extensions: ['sppt', 'spp', 's1p'], category: 'application')]
    case APPLICATION_VND_SEALED_PPT = 'APPLICATION_VND_SEALED_PPT';

    #[MimeTypeInfo(mimeType: 'application/vnd.sealed.tiff', extensions: ['stif'], category: 'application')]
    case APPLICATION_VND_SEALED_TIFF = 'APPLICATION_VND_SEALED_TIFF';

    #[MimeTypeInfo(mimeType: 'application/vnd.sealed.xls', extensions: ['sxls', 'sxl', 's1e'], category: 'application')]
    case APPLICATION_VND_SEALED_XLS = 'APPLICATION_VND_SEALED_XLS';

    #[MimeTypeInfo(mimeType: 'application/vnd.sealedmedia.softseal.html', extensions: ['stml', 'stm', 's1h'], category: 'application')]
    case APPLICATION_VND_SEALEDMEDIA_SOFTSEAL_HTML = 'APPLICATION_VND_SEALEDMEDIA_SOFTSEAL_HTML';

    #[MimeTypeInfo(mimeType: 'application/vnd.sealedmedia.softseal.pdf', extensions: ['spdf', 'spd', 's1a'], category: 'application')]
    case APPLICATION_VND_SEALEDMEDIA_SOFTSEAL_PDF = 'APPLICATION_VND_SEALEDMEDIA_SOFTSEAL_PDF';

    #[MimeTypeInfo(mimeType: 'application/vnd.seemail', extensions: ['see'], category: 'application')]
    case APPLICATION_VND_SEEMAIL = 'APPLICATION_VND_SEEMAIL';

    #[MimeTypeInfo(mimeType: 'application/vnd.seis+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_SEIS_JSON = 'APPLICATION_VND_SEIS_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.sema', extensions: ['sema'], category: 'application')]
    case APPLICATION_VND_SEMA = 'APPLICATION_VND_SEMA';

    #[MimeTypeInfo(mimeType: 'application/vnd.semd', extensions: ['semd'], category: 'application')]
    case APPLICATION_VND_SEMD = 'APPLICATION_VND_SEMD';

    #[MimeTypeInfo(mimeType: 'application/vnd.semf', extensions: ['semf'], category: 'application')]
    case APPLICATION_VND_SEMF = 'APPLICATION_VND_SEMF';

    #[MimeTypeInfo(mimeType: 'application/vnd.shade-save-file', extensions: ['ssv'], category: 'application')]
    case APPLICATION_VND_SHADE_SAVE_FILE = 'APPLICATION_VND_SHADE_SAVE_FILE';

    #[MimeTypeInfo(mimeType: 'application/vnd.shana.informed.formdata', extensions: ['ifm'], category: 'application')]
    case APPLICATION_VND_SHANA_INFORMED_FORMDATA = 'APPLICATION_VND_SHANA_INFORMED_FORMDATA';

    #[MimeTypeInfo(mimeType: 'application/vnd.shana.informed.formtemplate', extensions: ['itp'], category: 'application')]
    case APPLICATION_VND_SHANA_INFORMED_FORMTEMPLATE = 'APPLICATION_VND_SHANA_INFORMED_FORMTEMPLATE';

    #[MimeTypeInfo(mimeType: 'application/vnd.shana.informed.interchange', extensions: ['iif'], category: 'application')]
    case APPLICATION_VND_SHANA_INFORMED_INTERCHANGE = 'APPLICATION_VND_SHANA_INFORMED_INTERCHANGE';

    #[MimeTypeInfo(mimeType: 'application/vnd.shana.informed.package', extensions: ['ipk'], category: 'application')]
    case APPLICATION_VND_SHANA_INFORMED_PACKAGE = 'APPLICATION_VND_SHANA_INFORMED_PACKAGE';

    #[MimeTypeInfo(mimeType: 'application/vnd.shootproof+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_SHOOTPROOF_JSON = 'APPLICATION_VND_SHOOTPROOF_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.shopkick+json', extensions: [], category: 'application')]
    case APPLICATION_VND_SHOPKICK_JSON = 'APPLICATION_VND_SHOPKICK_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.shp', extensions: ['shp'], category: 'application')]
    case APPLICATION_VND_SHP = 'APPLICATION_VND_SHP';

    #[MimeTypeInfo(mimeType: 'application/vnd.shx', extensions: ['shx'], category: 'application')]
    case APPLICATION_VND_SHX = 'APPLICATION_VND_SHX';

    #[MimeTypeInfo(mimeType: 'application/vnd.sigrok.session', extensions: ['sr'], category: 'application')]
    case APPLICATION_VND_SIGROK_SESSION = 'APPLICATION_VND_SIGROK_SESSION';

    #[MimeTypeInfo(mimeType: 'application/vnd.simtech-mindmapper', extensions: ['twd', 'twds'], category: 'application')]
    case APPLICATION_VND_SIMTECH_MINDMAPPER = 'APPLICATION_VND_SIMTECH_MINDMAPPER';

    #[MimeTypeInfo(mimeType: 'application/vnd.siren+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_SIREN_JSON = 'APPLICATION_VND_SIREN_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.sirtx.vmv0', extensions: ['vmv0'], category: 'application')]
    case APPLICATION_VND_SIRTX_VMV0 = 'APPLICATION_VND_SIRTX_VMV0';

    #[MimeTypeInfo(mimeType: 'application/vnd.sketchometry', extensions: ['sketcho', 'sketch'], category: 'application')]
    case APPLICATION_VND_SKETCHOMETRY = 'APPLICATION_VND_SKETCHOMETRY';

    #[MimeTypeInfo(mimeType: 'application/vnd.smaf', extensions: ['mmf'], category: 'application')]
    case APPLICATION_VND_SMAF = 'APPLICATION_VND_SMAF';

    #[MimeTypeInfo(mimeType: 'application/vnd.smart.notebook', extensions: ['notebook'], category: 'application')]
    case APPLICATION_VND_SMART_NOTEBOOK = 'APPLICATION_VND_SMART_NOTEBOOK';

    #[MimeTypeInfo(mimeType: 'application/vnd.smart.teacher', extensions: ['teacher'], category: 'application')]
    case APPLICATION_VND_SMART_TEACHER = 'APPLICATION_VND_SMART_TEACHER';

    #[MimeTypeInfo(mimeType: 'application/vnd.smintio.portals.archive', extensions: ['sipa'], category: 'application')]
    case APPLICATION_VND_SMINTIO_PORTALS_ARCHIVE = 'APPLICATION_VND_SMINTIO_PORTALS_ARCHIVE';

    #[MimeTypeInfo(mimeType: 'application/vnd.snesdev-page-table', extensions: ['ptrom', 'pt'], category: 'application')]
    case APPLICATION_VND_SNESDEV_PAGE_TABLE = 'APPLICATION_VND_SNESDEV_PAGE_TABLE';

    #[MimeTypeInfo(mimeType: 'application/vnd.software602.filler.form+xml', extensions: ['fo'], category: 'application')]
    case APPLICATION_VND_SOFTWARE602_FILLER_FORM_XML = 'APPLICATION_VND_SOFTWARE602_FILLER_FORM_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.software602.filler.form-xml-zip', extensions: ['zfo'], category: 'application')]
    case APPLICATION_VND_SOFTWARE602_FILLER_FORM_XML_ZIP = 'APPLICATION_VND_SOFTWARE602_FILLER_FORM_XML_ZIP';

    #[MimeTypeInfo(mimeType: 'application/vnd.solent.sdkm+xml', extensions: ['sdkm', 'sdkd'], category: 'application')]
    case APPLICATION_VND_SOLENT_SDKM_XML = 'APPLICATION_VND_SOLENT_SDKM_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.spotfire.dxp', extensions: ['dxp'], category: 'application')]
    case APPLICATION_VND_SPOTFIRE_DXP = 'APPLICATION_VND_SPOTFIRE_DXP';

    #[MimeTypeInfo(mimeType: 'application/vnd.spotfire.sfs', extensions: ['sfs'], category: 'application')]
    case APPLICATION_VND_SPOTFIRE_SFS = 'APPLICATION_VND_SPOTFIRE_SFS';

    #[MimeTypeInfo(mimeType: 'application/vnd.sqlite3', extensions: ['db', 'sqlite', 'sqlite3'], category: 'application')]
    case APPLICATION_VND_SQLITE3 = 'APPLICATION_VND_SQLITE3';

    #[MimeTypeInfo(mimeType: 'application/vnd.sss-cod', extensions: [], category: 'application')]
    case APPLICATION_VND_SSS_COD = 'APPLICATION_VND_SSS_COD';

    #[MimeTypeInfo(mimeType: 'application/vnd.sss-dtf', extensions: ['not'], category: 'application')]
    case APPLICATION_VND_SSS_DTF = 'APPLICATION_VND_SSS_DTF';

    #[MimeTypeInfo(mimeType: 'application/vnd.sss-ntf', extensions: [], category: 'application')]
    case APPLICATION_VND_SSS_NTF = 'APPLICATION_VND_SSS_NTF';

    #[MimeTypeInfo(mimeType: 'application/vnd.stardivision.calc', extensions: ['sdc'], category: 'application')]
    case APPLICATION_VND_STARDIVISION_CALC = 'APPLICATION_VND_STARDIVISION_CALC';

    #[MimeTypeInfo(mimeType: 'application/vnd.stardivision.draw', extensions: ['sda'], category: 'application')]
    case APPLICATION_VND_STARDIVISION_DRAW = 'APPLICATION_VND_STARDIVISION_DRAW';

    #[MimeTypeInfo(mimeType: 'application/vnd.stardivision.impress', extensions: ['sdd'], category: 'application')]
    case APPLICATION_VND_STARDIVISION_IMPRESS = 'APPLICATION_VND_STARDIVISION_IMPRESS';

    #[MimeTypeInfo(mimeType: 'application/vnd.stardivision.math', extensions: ['smf'], category: 'application')]
    case APPLICATION_VND_STARDIVISION_MATH = 'APPLICATION_VND_STARDIVISION_MATH';

    #[MimeTypeInfo(mimeType: 'application/vnd.stardivision.writer', extensions: ['sdw', 'vor'], category: 'application')]
    case APPLICATION_VND_STARDIVISION_WRITER = 'APPLICATION_VND_STARDIVISION_WRITER';

    #[MimeTypeInfo(mimeType: 'application/vnd.stardivision.writer-global', extensions: ['sgl'], category: 'application')]
    case APPLICATION_VND_STARDIVISION_WRITER_GLOBAL = 'APPLICATION_VND_STARDIVISION_WRITER_GLOBAL';

    #[MimeTypeInfo(mimeType: 'application/vnd.stepmania.package', extensions: ['smzip'], category: 'application')]
    case APPLICATION_VND_STEPMANIA_PACKAGE = 'APPLICATION_VND_STEPMANIA_PACKAGE';

    #[MimeTypeInfo(mimeType: 'application/vnd.stepmania.stepchart', extensions: ['sm'], category: 'application')]
    case APPLICATION_VND_STEPMANIA_STEPCHART = 'APPLICATION_VND_STEPMANIA_STEPCHART';

    #[MimeTypeInfo(mimeType: 'application/vnd.street-stream', extensions: [], category: 'application')]
    case APPLICATION_VND_STREET_STREAM = 'APPLICATION_VND_STREET_STREAM';

    #[MimeTypeInfo(mimeType: 'application/vnd.sun.wadl+xml', extensions: ['wadl'], category: 'application')]
    case APPLICATION_VND_SUN_WADL_XML = 'APPLICATION_VND_SUN_WADL_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.sun.xml.calc', extensions: ['sxc'], category: 'application')]
    case APPLICATION_VND_SUN_XML_CALC = 'APPLICATION_VND_SUN_XML_CALC';

    #[MimeTypeInfo(mimeType: 'application/vnd.sun.xml.calc.template', extensions: ['stc'], category: 'application')]
    case APPLICATION_VND_SUN_XML_CALC_TEMPLATE = 'APPLICATION_VND_SUN_XML_CALC_TEMPLATE';

    #[MimeTypeInfo(mimeType: 'application/vnd.sun.xml.draw', extensions: ['sxd'], category: 'application')]
    case APPLICATION_VND_SUN_XML_DRAW = 'APPLICATION_VND_SUN_XML_DRAW';

    #[MimeTypeInfo(mimeType: 'application/vnd.sun.xml.draw.template', extensions: ['std'], category: 'application')]
    case APPLICATION_VND_SUN_XML_DRAW_TEMPLATE = 'APPLICATION_VND_SUN_XML_DRAW_TEMPLATE';

    #[MimeTypeInfo(mimeType: 'application/vnd.sun.xml.impress', extensions: ['sxi'], category: 'application')]
    case APPLICATION_VND_SUN_XML_IMPRESS = 'APPLICATION_VND_SUN_XML_IMPRESS';

    #[MimeTypeInfo(mimeType: 'application/vnd.sun.xml.impress.template', extensions: ['sti'], category: 'application')]
    case APPLICATION_VND_SUN_XML_IMPRESS_TEMPLATE = 'APPLICATION_VND_SUN_XML_IMPRESS_TEMPLATE';

    #[MimeTypeInfo(mimeType: 'application/vnd.sun.xml.math', extensions: ['sxm'], category: 'application')]
    case APPLICATION_VND_SUN_XML_MATH = 'APPLICATION_VND_SUN_XML_MATH';

    #[MimeTypeInfo(mimeType: 'application/vnd.sun.xml.writer', extensions: ['sxw'], category: 'application')]
    case APPLICATION_VND_SUN_XML_WRITER = 'APPLICATION_VND_SUN_XML_WRITER';

    #[MimeTypeInfo(mimeType: 'application/vnd.sun.xml.writer.global', extensions: ['sxg'], category: 'application')]
    case APPLICATION_VND_SUN_XML_WRITER_GLOBAL = 'APPLICATION_VND_SUN_XML_WRITER_GLOBAL';

    #[MimeTypeInfo(mimeType: 'application/vnd.sun.xml.writer.template', extensions: ['stw'], category: 'application')]
    case APPLICATION_VND_SUN_XML_WRITER_TEMPLATE = 'APPLICATION_VND_SUN_XML_WRITER_TEMPLATE';

    #[MimeTypeInfo(mimeType: 'application/vnd.superfile.super', extensions: ['super'], category: 'application')]
    case APPLICATION_VND_SUPERFILE_SUPER = 'APPLICATION_VND_SUPERFILE_SUPER';

    #[MimeTypeInfo(mimeType: 'application/vnd.sus-calendar', extensions: ['sus', 'susp'], category: 'application')]
    case APPLICATION_VND_SUS_CALENDAR = 'APPLICATION_VND_SUS_CALENDAR';

    #[MimeTypeInfo(mimeType: 'application/vnd.svd', extensions: ['svd'], category: 'application')]
    case APPLICATION_VND_SVD = 'APPLICATION_VND_SVD';

    #[MimeTypeInfo(mimeType: 'application/vnd.swiftview-ics', extensions: [], category: 'application')]
    case APPLICATION_VND_SWIFTVIEW_ICS = 'APPLICATION_VND_SWIFTVIEW_ICS';

    #[MimeTypeInfo(mimeType: 'application/vnd.sybyl.mol2', extensions: ['ml2', 'mol2', 'sy2'], category: 'application')]
    case APPLICATION_VND_SYBYL_MOL2 = 'APPLICATION_VND_SYBYL_MOL2';

    #[MimeTypeInfo(mimeType: 'application/vnd.sycle+xml', extensions: ['scl'], category: 'application')]
    case APPLICATION_VND_SYCLE_XML = 'APPLICATION_VND_SYCLE_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.syft+json', extensions: ['syft.json'], category: 'application')]
    case APPLICATION_VND_SYFT_JSON = 'APPLICATION_VND_SYFT_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.symbian.install', extensions: ['sis', 'sisx'], category: 'application')]
    case APPLICATION_VND_SYMBIAN_INSTALL = 'APPLICATION_VND_SYMBIAN_INSTALL';

    #[MimeTypeInfo(mimeType: 'application/vnd.syncml+xml', extensions: ['xsm'], category: 'application')]
    case APPLICATION_VND_SYNCML_XML = 'APPLICATION_VND_SYNCML_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.syncml.dm+wbxml', extensions: ['bdm'], category: 'application')]
    case APPLICATION_VND_SYNCML_DM_WBXML = 'APPLICATION_VND_SYNCML_DM_WBXML';

    #[MimeTypeInfo(mimeType: 'application/vnd.syncml.dm+xml', extensions: ['xdm'], category: 'application')]
    case APPLICATION_VND_SYNCML_DM_XML = 'APPLICATION_VND_SYNCML_DM_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.syncml.dm.notification', extensions: [], category: 'application')]
    case APPLICATION_VND_SYNCML_DM_NOTIFICATION = 'APPLICATION_VND_SYNCML_DM_NOTIFICATION';

    #[MimeTypeInfo(mimeType: 'application/vnd.syncml.dmddf+wbxml', extensions: ['ddf'], category: 'application')]
    case APPLICATION_VND_SYNCML_DMDDF_WBXML = 'APPLICATION_VND_SYNCML_DMDDF_WBXML';

    #[MimeTypeInfo(mimeType: 'application/vnd.syncml.dmddf+xml', extensions: ['ddf'], category: 'application')]
    case APPLICATION_VND_SYNCML_DMDDF_XML = 'APPLICATION_VND_SYNCML_DMDDF_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.syncml.dmtnds+wbxml', extensions: [], category: 'application')]
    case APPLICATION_VND_SYNCML_DMTNDS_WBXML = 'APPLICATION_VND_SYNCML_DMTNDS_WBXML';

    #[MimeTypeInfo(mimeType: 'application/vnd.syncml.dmtnds+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_SYNCML_DMTNDS_XML = 'APPLICATION_VND_SYNCML_DMTNDS_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.syncml.ds.notification', extensions: [], category: 'application')]
    case APPLICATION_VND_SYNCML_DS_NOTIFICATION = 'APPLICATION_VND_SYNCML_DS_NOTIFICATION';

    #[MimeTypeInfo(mimeType: 'application/vnd.tableschema+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_TABLESCHEMA_JSON = 'APPLICATION_VND_TABLESCHEMA_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.tao.intent-module-archive', extensions: ['tao'], category: 'application')]
    case APPLICATION_VND_TAO_INTENT_MODULE_ARCHIVE = 'APPLICATION_VND_TAO_INTENT_MODULE_ARCHIVE';

    #[MimeTypeInfo(mimeType: 'application/vnd.tcpdump.pcap', extensions: ['pcap', 'cap', 'dmp'], category: 'application')]
    case APPLICATION_VND_TCPDUMP_PCAP = 'APPLICATION_VND_TCPDUMP_PCAP';

    #[MimeTypeInfo(mimeType: 'application/vnd.think-cell.ppttc+json', extensions: ['ppttc'], category: 'application')]
    case APPLICATION_VND_THINK_CELL_PPTTC_JSON = 'APPLICATION_VND_THINK_CELL_PPTTC_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.tmd.mediaflex.api+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_VND_TMD_MEDIAFLEX_API_XML = 'APPLICATION_VND_TMD_MEDIAFLEX_API_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.tml', extensions: ['vfr', 'viaframe'], category: 'application')]
    case APPLICATION_VND_TML = 'APPLICATION_VND_TML';

    #[MimeTypeInfo(mimeType: 'application/vnd.tmobile-livetv', extensions: ['tmo'], category: 'application')]
    case APPLICATION_VND_TMOBILE_LIVETV = 'APPLICATION_VND_TMOBILE_LIVETV';

    #[MimeTypeInfo(mimeType: 'application/vnd.tri.onesource', extensions: [], category: 'application')]
    case APPLICATION_VND_TRI_ONESOURCE = 'APPLICATION_VND_TRI_ONESOURCE';

    #[MimeTypeInfo(mimeType: 'application/vnd.trid.tpt', extensions: ['tpt'], category: 'application')]
    case APPLICATION_VND_TRID_TPT = 'APPLICATION_VND_TRID_TPT';

    #[MimeTypeInfo(mimeType: 'application/vnd.triscape.mxs', extensions: ['mxs'], category: 'application')]
    case APPLICATION_VND_TRISCAPE_MXS = 'APPLICATION_VND_TRISCAPE_MXS';

    #[MimeTypeInfo(mimeType: 'application/vnd.trueapp', extensions: ['tra'], category: 'application')]
    case APPLICATION_VND_TRUEAPP = 'APPLICATION_VND_TRUEAPP';

    #[MimeTypeInfo(mimeType: 'application/vnd.truedoc', extensions: [], category: 'application')]
    case APPLICATION_VND_TRUEDOC = 'APPLICATION_VND_TRUEDOC';

    #[MimeTypeInfo(mimeType: 'application/vnd.ubisoft.webplayer', extensions: ['cab'], category: 'application')]
    case APPLICATION_VND_UBISOFT_WEBPLAYER = 'APPLICATION_VND_UBISOFT_WEBPLAYER';

    #[MimeTypeInfo(mimeType: 'application/vnd.ufdl', extensions: ['ufd', 'ufdl', 'frm'], category: 'application')]
    case APPLICATION_VND_UFDL = 'APPLICATION_VND_UFDL';

    #[MimeTypeInfo(mimeType: 'application/vnd.uic.osdm+json', extensions: [], category: 'application')]
    case APPLICATION_VND_UIC_OSDM_JSON = 'APPLICATION_VND_UIC_OSDM_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.uiq.theme', extensions: ['utz'], category: 'application')]
    case APPLICATION_VND_UIQ_THEME = 'APPLICATION_VND_UIQ_THEME';

    #[MimeTypeInfo(mimeType: 'application/vnd.umajin', extensions: ['umj'], category: 'application')]
    case APPLICATION_VND_UMAJIN = 'APPLICATION_VND_UMAJIN';

    #[MimeTypeInfo(mimeType: 'application/vnd.unity', extensions: ['unityweb'], category: 'application')]
    case APPLICATION_VND_UNITY = 'APPLICATION_VND_UNITY';

    #[MimeTypeInfo(mimeType: 'application/vnd.uoml+xml', extensions: ['uoml', 'uo'], category: 'application')]
    case APPLICATION_VND_UOML_XML = 'APPLICATION_VND_UOML_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.uplanet.alert', extensions: [], category: 'application')]
    case APPLICATION_VND_UPLANET_ALERT = 'APPLICATION_VND_UPLANET_ALERT';

    #[MimeTypeInfo(mimeType: 'application/vnd.uplanet.alert-wbxml', extensions: [], category: 'application')]
    case APPLICATION_VND_UPLANET_ALERT_WBXML = 'APPLICATION_VND_UPLANET_ALERT_WBXML';

    #[MimeTypeInfo(mimeType: 'application/vnd.uplanet.bearer-choice', extensions: [], category: 'application')]
    case APPLICATION_VND_UPLANET_BEARER_CHOICE = 'APPLICATION_VND_UPLANET_BEARER_CHOICE';

    #[MimeTypeInfo(mimeType: 'application/vnd.uplanet.bearer-choice-wbxml', extensions: [], category: 'application')]
    case APPLICATION_VND_UPLANET_BEARER_CHOICE_WBXML = 'APPLICATION_VND_UPLANET_BEARER_CHOICE_WBXML';

    #[MimeTypeInfo(mimeType: 'application/vnd.uplanet.cacheop', extensions: [], category: 'application')]
    case APPLICATION_VND_UPLANET_CACHEOP = 'APPLICATION_VND_UPLANET_CACHEOP';

    #[MimeTypeInfo(mimeType: 'application/vnd.uplanet.cacheop-wbxml', extensions: [], category: 'application')]
    case APPLICATION_VND_UPLANET_CACHEOP_WBXML = 'APPLICATION_VND_UPLANET_CACHEOP_WBXML';

    #[MimeTypeInfo(mimeType: 'application/vnd.uplanet.channel', extensions: [], category: 'application')]
    case APPLICATION_VND_UPLANET_CHANNEL = 'APPLICATION_VND_UPLANET_CHANNEL';

    #[MimeTypeInfo(mimeType: 'application/vnd.uplanet.channel-wbxml', extensions: [], category: 'application')]
    case APPLICATION_VND_UPLANET_CHANNEL_WBXML = 'APPLICATION_VND_UPLANET_CHANNEL_WBXML';

    #[MimeTypeInfo(mimeType: 'application/vnd.uplanet.list', extensions: [], category: 'application')]
    case APPLICATION_VND_UPLANET_LIST = 'APPLICATION_VND_UPLANET_LIST';

    #[MimeTypeInfo(mimeType: 'application/vnd.uplanet.list-wbxml', extensions: [], category: 'application')]
    case APPLICATION_VND_UPLANET_LIST_WBXML = 'APPLICATION_VND_UPLANET_LIST_WBXML';

    #[MimeTypeInfo(mimeType: 'application/vnd.uplanet.listcmd', extensions: [], category: 'application')]
    case APPLICATION_VND_UPLANET_LISTCMD = 'APPLICATION_VND_UPLANET_LISTCMD';

    #[MimeTypeInfo(mimeType: 'application/vnd.uplanet.listcmd-wbxml', extensions: [], category: 'application')]
    case APPLICATION_VND_UPLANET_LISTCMD_WBXML = 'APPLICATION_VND_UPLANET_LISTCMD_WBXML';

    #[MimeTypeInfo(mimeType: 'application/vnd.uplanet.signal', extensions: [], category: 'application')]
    case APPLICATION_VND_UPLANET_SIGNAL = 'APPLICATION_VND_UPLANET_SIGNAL';

    #[MimeTypeInfo(mimeType: 'application/vnd.uri-map', extensions: ['urim', 'urimap'], category: 'application')]
    case APPLICATION_VND_URI_MAP = 'APPLICATION_VND_URI_MAP';

    #[MimeTypeInfo(mimeType: 'application/vnd.valve.source.material', extensions: ['vmt'], category: 'application')]
    case APPLICATION_VND_VALVE_SOURCE_MATERIAL = 'APPLICATION_VND_VALVE_SOURCE_MATERIAL';

    #[MimeTypeInfo(mimeType: 'application/vnd.vcx', extensions: ['vcx'], category: 'application')]
    case APPLICATION_VND_VCX = 'APPLICATION_VND_VCX';

    #[MimeTypeInfo(mimeType: 'application/vnd.vd-study', extensions: ['sxi', 'mxi', 'study-inter', 'model-inter'], category: 'application')]
    case APPLICATION_VND_VD_STUDY = 'APPLICATION_VND_VD_STUDY';

    #[MimeTypeInfo(mimeType: 'application/vnd.vectorworks', extensions: ['mcd', 'vwx'], category: 'application')]
    case APPLICATION_VND_VECTORWORKS = 'APPLICATION_VND_VECTORWORKS';

    #[MimeTypeInfo(mimeType: 'application/vnd.vel+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_VEL_JSON = 'APPLICATION_VND_VEL_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.veraison.tsm-report+cbor', extensions: [], category: 'application')]
    case APPLICATION_VND_VERAISON_TSM_REPORT_CBOR = 'APPLICATION_VND_VERAISON_TSM_REPORT_CBOR';

    #[MimeTypeInfo(mimeType: 'application/vnd.veraison.tsm-report+json', extensions: [], category: 'application')]
    case APPLICATION_VND_VERAISON_TSM_REPORT_JSON = 'APPLICATION_VND_VERAISON_TSM_REPORT_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.verifier-attestation+jwt', extensions: [], category: 'application')]
    case APPLICATION_VND_VERIFIER_ATTESTATION_JWT = 'APPLICATION_VND_VERIFIER_ATTESTATION_JWT';

    #[MimeTypeInfo(mimeType: 'application/vnd.verimatrix.vcas', extensions: [], category: 'application')]
    case APPLICATION_VND_VERIMATRIX_VCAS = 'APPLICATION_VND_VERIMATRIX_VCAS';

    #[MimeTypeInfo(mimeType: 'application/vnd.veritone.aion+json', extensions: ['json', 'aion', 'vtnstd'], category: 'application')]
    case APPLICATION_VND_VERITONE_AION_JSON = 'APPLICATION_VND_VERITONE_AION_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.veryant.thin', extensions: ['istc', 'isws'], category: 'application')]
    case APPLICATION_VND_VERYANT_THIN = 'APPLICATION_VND_VERYANT_THIN';

    #[MimeTypeInfo(mimeType: 'application/vnd.ves.encrypted', extensions: ['ves'], category: 'application')]
    case APPLICATION_VND_VES_ENCRYPTED = 'APPLICATION_VND_VES_ENCRYPTED';

    #[MimeTypeInfo(mimeType: 'application/vnd.vidsoft.vidconference', extensions: ['vsc'], category: 'application')]
    case APPLICATION_VND_VIDSOFT_VIDCONFERENCE = 'APPLICATION_VND_VIDSOFT_VIDCONFERENCE';

    #[MimeTypeInfo(mimeType: 'application/vnd.visio', extensions: ['vsd', 'vst', 'vss', 'vsw', 'vsdx', 'vtx'], category: 'application')]
    case APPLICATION_VND_VISIO = 'APPLICATION_VND_VISIO';

    #[MimeTypeInfo(mimeType: 'application/vnd.visionary', extensions: ['vis'], category: 'application')]
    case APPLICATION_VND_VISIONARY = 'APPLICATION_VND_VISIONARY';

    #[MimeTypeInfo(mimeType: 'application/vnd.vividence.scriptfile', extensions: ['vsc'], category: 'application')]
    case APPLICATION_VND_VIVIDENCE_SCRIPTFILE = 'APPLICATION_VND_VIVIDENCE_SCRIPTFILE';

    #[MimeTypeInfo(mimeType: 'application/vnd.vocalshaper.vsp4', extensions: ['vsp4'], category: 'application')]
    case APPLICATION_VND_VOCALSHAPER_VSP4 = 'APPLICATION_VND_VOCALSHAPER_VSP4';

    #[MimeTypeInfo(mimeType: 'application/vnd.vsf', extensions: ['vsf'], category: 'application')]
    case APPLICATION_VND_VSF = 'APPLICATION_VND_VSF';

    #[MimeTypeInfo(mimeType: 'application/vnd.wantverse', extensions: ['wv'], category: 'application')]
    case APPLICATION_VND_WANTVERSE = 'APPLICATION_VND_WANTVERSE';

    #[MimeTypeInfo(mimeType: 'application/vnd.wap.sic', extensions: ['sic'], category: 'application')]
    case APPLICATION_VND_WAP_SIC = 'APPLICATION_VND_WAP_SIC';

    #[MimeTypeInfo(mimeType: 'application/vnd.wap.slc', extensions: ['slc'], category: 'application')]
    case APPLICATION_VND_WAP_SLC = 'APPLICATION_VND_WAP_SLC';

    #[MimeTypeInfo(mimeType: 'application/vnd.wap.wbxml', extensions: ['wbxml'], category: 'application')]
    case APPLICATION_VND_WAP_WBXML = 'APPLICATION_VND_WAP_WBXML';

    #[MimeTypeInfo(mimeType: 'application/vnd.wap.wmlc', extensions: ['wmlc'], category: 'application')]
    case APPLICATION_VND_WAP_WMLC = 'APPLICATION_VND_WAP_WMLC';

    #[MimeTypeInfo(mimeType: 'application/vnd.wap.wmlscriptc', extensions: ['wmlsc'], category: 'application')]
    case APPLICATION_VND_WAP_WMLSCRIPTC = 'APPLICATION_VND_WAP_WMLSCRIPTC';

    #[MimeTypeInfo(mimeType: 'application/vnd.wasmflow.wafl', extensions: ['wafl'], category: 'application')]
    case APPLICATION_VND_WASMFLOW_WAFL = 'APPLICATION_VND_WASMFLOW_WAFL';

    #[MimeTypeInfo(mimeType: 'application/vnd.webturbo', extensions: ['wtb'], category: 'application')]
    case APPLICATION_VND_WEBTURBO = 'APPLICATION_VND_WEBTURBO';

    #[MimeTypeInfo(mimeType: 'application/vnd.wfa.dpp', extensions: ['na'], category: 'application')]
    case APPLICATION_VND_WFA_DPP = 'APPLICATION_VND_WFA_DPP';

    #[MimeTypeInfo(mimeType: 'application/vnd.wfa.p2p', extensions: ['p2p'], category: 'application')]
    case APPLICATION_VND_WFA_P2P = 'APPLICATION_VND_WFA_P2P';

    #[MimeTypeInfo(mimeType: 'application/vnd.wfa.wsc', extensions: ['wsc'], category: 'application')]
    case APPLICATION_VND_WFA_WSC = 'APPLICATION_VND_WFA_WSC';

    #[MimeTypeInfo(mimeType: 'application/vnd.windows.devicepairing', extensions: [], category: 'application')]
    case APPLICATION_VND_WINDOWS_DEVICEPAIRING = 'APPLICATION_VND_WINDOWS_DEVICEPAIRING';

    #[MimeTypeInfo(mimeType: 'application/vnd.wmc', extensions: ['wmc'], category: 'application')]
    case APPLICATION_VND_WMC = 'APPLICATION_VND_WMC';

    #[MimeTypeInfo(mimeType: 'application/vnd.wmf.bootstrap', extensions: [], category: 'application')]
    case APPLICATION_VND_WMF_BOOTSTRAP = 'APPLICATION_VND_WMF_BOOTSTRAP';

    #[MimeTypeInfo(mimeType: 'application/vnd.wolfram.mathematica', extensions: ['nb'], category: 'application')]
    case APPLICATION_VND_WOLFRAM_MATHEMATICA = 'APPLICATION_VND_WOLFRAM_MATHEMATICA';

    #[MimeTypeInfo(mimeType: 'application/vnd.wolfram.mathematica.package', extensions: ['m'], category: 'application')]
    case APPLICATION_VND_WOLFRAM_MATHEMATICA_PACKAGE = 'APPLICATION_VND_WOLFRAM_MATHEMATICA_PACKAGE';

    #[MimeTypeInfo(mimeType: 'application/vnd.wolfram.player', extensions: ['nbp'], category: 'application')]
    case APPLICATION_VND_WOLFRAM_PLAYER = 'APPLICATION_VND_WOLFRAM_PLAYER';

    #[MimeTypeInfo(mimeType: 'application/vnd.wordlift', extensions: [], category: 'application')]
    case APPLICATION_VND_WORDLIFT = 'APPLICATION_VND_WORDLIFT';

    #[MimeTypeInfo(mimeType: 'application/vnd.wordperfect', extensions: ['wpd'], category: 'application')]
    case APPLICATION_VND_WORDPERFECT = 'APPLICATION_VND_WORDPERFECT';

    #[MimeTypeInfo(mimeType: 'application/vnd.wqd', extensions: ['wqd'], category: 'application')]
    case APPLICATION_VND_WQD = 'APPLICATION_VND_WQD';

    #[MimeTypeInfo(mimeType: 'application/vnd.wrq-hp3000-labelled', extensions: [], category: 'application')]
    case APPLICATION_VND_WRQ_HP3000_LABELLED = 'APPLICATION_VND_WRQ_HP3000_LABELLED';

    #[MimeTypeInfo(mimeType: 'application/vnd.wt.stf', extensions: ['stf'], category: 'application')]
    case APPLICATION_VND_WT_STF = 'APPLICATION_VND_WT_STF';

    #[MimeTypeInfo(mimeType: 'application/vnd.wv.csp+wbxml', extensions: ['wv'], category: 'application')]
    case APPLICATION_VND_WV_CSP_WBXML = 'APPLICATION_VND_WV_CSP_WBXML';

    #[MimeTypeInfo(mimeType: 'application/vnd.wv.csp+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_WV_CSP_XML = 'APPLICATION_VND_WV_CSP_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.wv.ssp+xml', extensions: [], category: 'application')]
    case APPLICATION_VND_WV_SSP_XML = 'APPLICATION_VND_WV_SSP_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.xacml+json', extensions: ['json'], category: 'application')]
    case APPLICATION_VND_XACML_JSON = 'APPLICATION_VND_XACML_JSON';

    #[MimeTypeInfo(mimeType: 'application/vnd.xara', extensions: ['xar'], category: 'application')]
    case APPLICATION_VND_XARA = 'APPLICATION_VND_XARA';

    #[MimeTypeInfo(mimeType: 'application/vnd.xarin.cpj', extensions: ['cpj'], category: 'application')]
    case APPLICATION_VND_XARIN_CPJ = 'APPLICATION_VND_XARIN_CPJ';

    #[MimeTypeInfo(mimeType: 'application/vnd.xecrets-encrypted', extensions: ['axx'], category: 'application')]
    case APPLICATION_VND_XECRETS_ENCRYPTED = 'APPLICATION_VND_XECRETS_ENCRYPTED';

    #[MimeTypeInfo(mimeType: 'application/vnd.xfdl', extensions: ['xfdl', 'xfd', 'frm'], category: 'application')]
    case APPLICATION_VND_XFDL = 'APPLICATION_VND_XFDL';

    #[MimeTypeInfo(mimeType: 'application/vnd.xfdl.webform', extensions: [], category: 'application')]
    case APPLICATION_VND_XFDL_WEBFORM = 'APPLICATION_VND_XFDL_WEBFORM';

    #[MimeTypeInfo(mimeType: 'application/vnd.xmi+xml', extensions: ['xmi'], category: 'application')]
    case APPLICATION_VND_XMI_XML = 'APPLICATION_VND_XMI_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.xmpie.cpkg', extensions: ['cpkg'], category: 'application')]
    case APPLICATION_VND_XMPIE_CPKG = 'APPLICATION_VND_XMPIE_CPKG';

    #[MimeTypeInfo(mimeType: 'application/vnd.xmpie.dpkg', extensions: ['dpkg'], category: 'application')]
    case APPLICATION_VND_XMPIE_DPKG = 'APPLICATION_VND_XMPIE_DPKG';

    #[MimeTypeInfo(mimeType: 'application/vnd.xmpie.plan', extensions: ['dpkg'], category: 'application')]
    case APPLICATION_VND_XMPIE_PLAN = 'APPLICATION_VND_XMPIE_PLAN';

    #[MimeTypeInfo(mimeType: 'application/vnd.xmpie.ppkg', extensions: ['ppkg'], category: 'application')]
    case APPLICATION_VND_XMPIE_PPKG = 'APPLICATION_VND_XMPIE_PPKG';

    #[MimeTypeInfo(mimeType: 'application/vnd.xmpie.xlim', extensions: ['xlim'], category: 'application')]
    case APPLICATION_VND_XMPIE_XLIM = 'APPLICATION_VND_XMPIE_XLIM';

    #[MimeTypeInfo(mimeType: 'application/vnd.yamaha.hv-dic', extensions: ['hvd'], category: 'application')]
    case APPLICATION_VND_YAMAHA_HV_DIC = 'APPLICATION_VND_YAMAHA_HV_DIC';

    #[MimeTypeInfo(mimeType: 'application/vnd.yamaha.hv-script', extensions: ['hvs'], category: 'application')]
    case APPLICATION_VND_YAMAHA_HV_SCRIPT = 'APPLICATION_VND_YAMAHA_HV_SCRIPT';

    #[MimeTypeInfo(mimeType: 'application/vnd.yamaha.hv-voice', extensions: ['hvp'], category: 'application')]
    case APPLICATION_VND_YAMAHA_HV_VOICE = 'APPLICATION_VND_YAMAHA_HV_VOICE';

    #[MimeTypeInfo(mimeType: 'application/vnd.yamaha.openscoreformat', extensions: ['osf'], category: 'application')]
    case APPLICATION_VND_YAMAHA_OPENSCOREFORMAT = 'APPLICATION_VND_YAMAHA_OPENSCOREFORMAT';

    #[MimeTypeInfo(mimeType: 'application/vnd.yamaha.openscoreformat.osfpvg+xml', extensions: ['osfpvg', 'xml'], category: 'application')]
    case APPLICATION_VND_YAMAHA_OPENSCOREFORMAT_OSFPVG_XML = 'APPLICATION_VND_YAMAHA_OPENSCOREFORMAT_OSFPVG_XML';

    #[MimeTypeInfo(mimeType: 'application/vnd.yamaha.remote-setup', extensions: [], category: 'application')]
    case APPLICATION_VND_YAMAHA_REMOTE_SETUP = 'APPLICATION_VND_YAMAHA_REMOTE_SETUP';

    #[MimeTypeInfo(mimeType: 'application/vnd.yamaha.smaf-audio', extensions: ['saf'], category: 'application')]
    case APPLICATION_VND_YAMAHA_SMAF_AUDIO = 'APPLICATION_VND_YAMAHA_SMAF_AUDIO';

    #[MimeTypeInfo(mimeType: 'application/vnd.yamaha.smaf-phrase', extensions: ['spf'], category: 'application')]
    case APPLICATION_VND_YAMAHA_SMAF_PHRASE = 'APPLICATION_VND_YAMAHA_SMAF_PHRASE';

    #[MimeTypeInfo(mimeType: 'application/vnd.yamaha.through-ngn', extensions: [], category: 'application')]
    case APPLICATION_VND_YAMAHA_THROUGH_NGN = 'APPLICATION_VND_YAMAHA_THROUGH_NGN';

    #[MimeTypeInfo(mimeType: 'application/vnd.yamaha.tunnel-udpencap', extensions: [], category: 'application')]
    case APPLICATION_VND_YAMAHA_TUNNEL_UDPENCAP = 'APPLICATION_VND_YAMAHA_TUNNEL_UDPENCAP';

    #[MimeTypeInfo(mimeType: 'application/vnd.yaoweme', extensions: ['yme'], category: 'application')]
    case APPLICATION_VND_YAOWEME = 'APPLICATION_VND_YAOWEME';

    #[MimeTypeInfo(mimeType: 'application/vnd.yellowriver-custom-menu', extensions: ['cmp'], category: 'application')]
    case APPLICATION_VND_YELLOWRIVER_CUSTOM_MENU = 'APPLICATION_VND_YELLOWRIVER_CUSTOM_MENU';

    #[MimeTypeInfo(mimeType: 'application/vnd.zul', extensions: ['zir', 'zirz'], category: 'application')]
    case APPLICATION_VND_ZUL = 'APPLICATION_VND_ZUL';

    #[MimeTypeInfo(mimeType: 'application/vnd.zzazz.deck+xml', extensions: ['zaz'], category: 'application')]
    case APPLICATION_VND_ZZAZZ_DECK_XML = 'APPLICATION_VND_ZZAZZ_DECK_XML';

    #[MimeTypeInfo(mimeType: 'application/voicexml+xml', extensions: ['vxml'], category: 'application')]
    case APPLICATION_VOICEXML_XML = 'APPLICATION_VOICEXML_XML';

    #[MimeTypeInfo(mimeType: 'application/voucher-cms+json', extensions: ['vcj'], category: 'application')]
    case APPLICATION_VOUCHER_CMS_JSON = 'APPLICATION_VOUCHER_CMS_JSON';

    #[MimeTypeInfo(mimeType: 'application/voucher-jws+json', extensions: ['vjj'], category: 'application')]
    case APPLICATION_VOUCHER_JWS_JSON = 'APPLICATION_VOUCHER_JWS_JSON';

    #[MimeTypeInfo(mimeType: 'application/vp', extensions: ['vp'], category: 'application')]
    case APPLICATION_VP = 'APPLICATION_VP';

    #[MimeTypeInfo(mimeType: 'application/vp+cose', extensions: [], category: 'application')]
    case APPLICATION_VP_COSE = 'APPLICATION_VP_COSE';

    #[MimeTypeInfo(mimeType: 'application/vp+jwt', extensions: [], category: 'application')]
    case APPLICATION_VP_JWT = 'APPLICATION_VP_JWT';

    #[MimeTypeInfo(mimeType: 'application/vq-rtcpxr', extensions: [], category: 'application')]
    case APPLICATION_VQ_RTCPXR = 'APPLICATION_VQ_RTCPXR';

    #[MimeTypeInfo(mimeType: 'application/wasm', extensions: ['wasm'], category: 'application')]
    case APPLICATION_WASM = 'APPLICATION_WASM';

    #[MimeTypeInfo(mimeType: 'application/watcherinfo+xml', extensions: ['wif', 'xml'], category: 'application')]
    case APPLICATION_WATCHERINFO_XML = 'APPLICATION_WATCHERINFO_XML';

    #[MimeTypeInfo(mimeType: 'application/webpush-options+json', extensions: ['json'], category: 'application')]
    case APPLICATION_WEBPUSH_OPTIONS_JSON = 'APPLICATION_WEBPUSH_OPTIONS_JSON';

    #[MimeTypeInfo(mimeType: 'application/whoispp-query', extensions: [], category: 'application')]
    case APPLICATION_WHOISPP_QUERY = 'APPLICATION_WHOISPP_QUERY';

    #[MimeTypeInfo(mimeType: 'application/whoispp-response', extensions: [], category: 'application')]
    case APPLICATION_WHOISPP_RESPONSE = 'APPLICATION_WHOISPP_RESPONSE';

    #[MimeTypeInfo(mimeType: 'application/widget', extensions: ['wgt'], category: 'application')]
    case APPLICATION_WIDGET = 'APPLICATION_WIDGET';

    #[MimeTypeInfo(mimeType: 'application/winhlp', extensions: ['hlp'], category: 'application')]
    case APPLICATION_WINHLP = 'APPLICATION_WINHLP';

    #[MimeTypeInfo(mimeType: 'application/wita', extensions: [], category: 'application')]
    case APPLICATION_WITA = 'APPLICATION_WITA';

    #[MimeTypeInfo(mimeType: 'application/wordperfect5.1', extensions: [], category: 'application')]
    case APPLICATION_WORDPERFECT5_1 = 'APPLICATION_WORDPERFECT5_1';

    #[MimeTypeInfo(mimeType: 'application/wsdl+xml', extensions: ['wsdl'], category: 'application')]
    case APPLICATION_WSDL_XML = 'APPLICATION_WSDL_XML';

    #[MimeTypeInfo(mimeType: 'application/wspolicy+xml', extensions: ['wspolicy'], category: 'application')]
    case APPLICATION_WSPOLICY_XML = 'APPLICATION_WSPOLICY_XML';

    #[MimeTypeInfo(mimeType: 'application/x-7z-compressed', extensions: ['7z'], category: 'application')]
    case APPLICATION_X_7Z_COMPRESSED = 'APPLICATION_X_7Z_COMPRESSED';

    #[MimeTypeInfo(mimeType: 'application/x-abiword', extensions: ['abw'], category: 'application')]
    case APPLICATION_X_ABIWORD = 'APPLICATION_X_ABIWORD';

    #[MimeTypeInfo(mimeType: 'application/x-ace-compressed', extensions: ['ace'], category: 'application')]
    case APPLICATION_X_ACE_COMPRESSED = 'APPLICATION_X_ACE_COMPRESSED';

    #[MimeTypeInfo(mimeType: 'application/x-amf', extensions: [], category: 'application')]
    case APPLICATION_X_AMF = 'APPLICATION_X_AMF';

    #[MimeTypeInfo(mimeType: 'application/x-apple-diskimage', extensions: ['dmg'], category: 'application')]
    case APPLICATION_X_APPLE_DISKIMAGE = 'APPLICATION_X_APPLE_DISKIMAGE';

    #[MimeTypeInfo(mimeType: 'application/x-arj', extensions: ['arj'], category: 'application')]
    case APPLICATION_X_ARJ = 'APPLICATION_X_ARJ';

    #[MimeTypeInfo(mimeType: 'application/x-authorware-bin', extensions: ['aab', 'x32', 'u32', 'vox'], category: 'application')]
    case APPLICATION_X_AUTHORWARE_BIN = 'APPLICATION_X_AUTHORWARE_BIN';

    #[MimeTypeInfo(mimeType: 'application/x-authorware-map', extensions: ['aam'], category: 'application')]
    case APPLICATION_X_AUTHORWARE_MAP = 'APPLICATION_X_AUTHORWARE_MAP';

    #[MimeTypeInfo(mimeType: 'application/x-authorware-seg', extensions: ['aas'], category: 'application')]
    case APPLICATION_X_AUTHORWARE_SEG = 'APPLICATION_X_AUTHORWARE_SEG';

    #[MimeTypeInfo(mimeType: 'application/x-bcpio', extensions: ['bcpio'], category: 'application')]
    case APPLICATION_X_BCPIO = 'APPLICATION_X_BCPIO';

    #[MimeTypeInfo(mimeType: 'application/x-bdoc', extensions: ['bdoc'], category: 'application')]
    case APPLICATION_X_BDOC = 'APPLICATION_X_BDOC';

    #[MimeTypeInfo(mimeType: 'application/x-bittorrent', extensions: ['torrent'], category: 'application')]
    case APPLICATION_X_BITTORRENT = 'APPLICATION_X_BITTORRENT';

    #[MimeTypeInfo(mimeType: 'application/x-blender', extensions: ['blend'], category: 'application')]
    case APPLICATION_X_BLENDER = 'APPLICATION_X_BLENDER';

    #[MimeTypeInfo(mimeType: 'application/x-blorb', extensions: ['blb', 'blorb'], category: 'application')]
    case APPLICATION_X_BLORB = 'APPLICATION_X_BLORB';

    #[MimeTypeInfo(mimeType: 'application/x-bzip', extensions: ['bz'], category: 'application')]
    case APPLICATION_X_BZIP = 'APPLICATION_X_BZIP';

    #[MimeTypeInfo(mimeType: 'application/x-bzip2', extensions: ['bz2', 'boz'], category: 'application')]
    case APPLICATION_X_BZIP2 = 'APPLICATION_X_BZIP2';

    #[MimeTypeInfo(mimeType: 'application/x-cbr', extensions: ['cbr', 'cba', 'cbt', 'cbz', 'cb7'], category: 'application')]
    case APPLICATION_X_CBR = 'APPLICATION_X_CBR';

    #[MimeTypeInfo(mimeType: 'application/x-cdlink', extensions: ['vcd'], category: 'application')]
    case APPLICATION_X_CDLINK = 'APPLICATION_X_CDLINK';

    #[MimeTypeInfo(mimeType: 'application/x-cfs-compressed', extensions: ['cfs'], category: 'application')]
    case APPLICATION_X_CFS_COMPRESSED = 'APPLICATION_X_CFS_COMPRESSED';

    #[MimeTypeInfo(mimeType: 'application/x-chat', extensions: ['chat'], category: 'application')]
    case APPLICATION_X_CHAT = 'APPLICATION_X_CHAT';

    #[MimeTypeInfo(mimeType: 'application/x-chess-pgn', extensions: ['pgn'], category: 'application')]
    case APPLICATION_X_CHESS_PGN = 'APPLICATION_X_CHESS_PGN';

    #[MimeTypeInfo(mimeType: 'application/x-chrome-extension', extensions: ['crx'], category: 'application')]
    case APPLICATION_X_CHROME_EXTENSION = 'APPLICATION_X_CHROME_EXTENSION';

    #[MimeTypeInfo(mimeType: 'application/x-cocoa', extensions: ['cco'], category: 'application')]
    case APPLICATION_X_COCOA = 'APPLICATION_X_COCOA';

    #[MimeTypeInfo(mimeType: 'application/x-compress', extensions: [], category: 'application')]
    case APPLICATION_X_COMPRESS = 'APPLICATION_X_COMPRESS';

    #[MimeTypeInfo(mimeType: 'application/x-compressed', extensions: ['rar'], category: 'application')]
    case APPLICATION_X_COMPRESSED = 'APPLICATION_X_COMPRESSED';

    #[MimeTypeInfo(mimeType: 'application/x-conference', extensions: ['nsc'], category: 'application')]
    case APPLICATION_X_CONFERENCE = 'APPLICATION_X_CONFERENCE';

    #[MimeTypeInfo(mimeType: 'application/x-cpio', extensions: ['cpio'], category: 'application')]
    case APPLICATION_X_CPIO = 'APPLICATION_X_CPIO';

    #[MimeTypeInfo(mimeType: 'application/x-csh', extensions: ['csh'], category: 'application')]
    case APPLICATION_X_CSH = 'APPLICATION_X_CSH';

    #[MimeTypeInfo(mimeType: 'application/x-deb', extensions: [], category: 'application')]
    case APPLICATION_X_DEB = 'APPLICATION_X_DEB';

    #[MimeTypeInfo(mimeType: 'application/x-debian-package', extensions: ['deb', 'udeb'], category: 'application')]
    case APPLICATION_X_DEBIAN_PACKAGE = 'APPLICATION_X_DEBIAN_PACKAGE';

    #[MimeTypeInfo(mimeType: 'application/x-dgc-compressed', extensions: ['dgc'], category: 'application')]
    case APPLICATION_X_DGC_COMPRESSED = 'APPLICATION_X_DGC_COMPRESSED';

    #[MimeTypeInfo(mimeType: 'application/x-director', extensions: ['dir', 'dcr', 'dxr', 'cst', 'cct', 'cxt', 'w3d', 'fgd', 'swa'], category: 'application')]
    case APPLICATION_X_DIRECTOR = 'APPLICATION_X_DIRECTOR';

    #[MimeTypeInfo(mimeType: 'application/x-doom', extensions: ['wad'], category: 'application')]
    case APPLICATION_X_DOOM = 'APPLICATION_X_DOOM';

    #[MimeTypeInfo(mimeType: 'application/x-dtbncx+xml', extensions: ['ncx'], category: 'application')]
    case APPLICATION_X_DTBNCX_XML = 'APPLICATION_X_DTBNCX_XML';

    #[MimeTypeInfo(mimeType: 'application/x-dtbook+xml', extensions: ['dtb'], category: 'application')]
    case APPLICATION_X_DTBOOK_XML = 'APPLICATION_X_DTBOOK_XML';

    #[MimeTypeInfo(mimeType: 'application/x-dtbresource+xml', extensions: ['res'], category: 'application')]
    case APPLICATION_X_DTBRESOURCE_XML = 'APPLICATION_X_DTBRESOURCE_XML';

    #[MimeTypeInfo(mimeType: 'application/x-dvi', extensions: ['dvi'], category: 'application')]
    case APPLICATION_X_DVI = 'APPLICATION_X_DVI';

    #[MimeTypeInfo(mimeType: 'application/x-envoy', extensions: ['evy'], category: 'application')]
    case APPLICATION_X_ENVOY = 'APPLICATION_X_ENVOY';

    #[MimeTypeInfo(mimeType: 'application/x-eva', extensions: ['eva'], category: 'application')]
    case APPLICATION_X_EVA = 'APPLICATION_X_EVA';

    #[MimeTypeInfo(mimeType: 'application/x-font-bdf', extensions: ['bdf'], category: 'application')]
    case APPLICATION_X_FONT_BDF = 'APPLICATION_X_FONT_BDF';

    #[MimeTypeInfo(mimeType: 'application/x-font-dos', extensions: [], category: 'application')]
    case APPLICATION_X_FONT_DOS = 'APPLICATION_X_FONT_DOS';

    #[MimeTypeInfo(mimeType: 'application/x-font-framemaker', extensions: [], category: 'application')]
    case APPLICATION_X_FONT_FRAMEMAKER = 'APPLICATION_X_FONT_FRAMEMAKER';

    #[MimeTypeInfo(mimeType: 'application/x-font-ghostscript', extensions: ['gsf'], category: 'application')]
    case APPLICATION_X_FONT_GHOSTSCRIPT = 'APPLICATION_X_FONT_GHOSTSCRIPT';

    #[MimeTypeInfo(mimeType: 'application/x-font-libgrx', extensions: [], category: 'application')]
    case APPLICATION_X_FONT_LIBGRX = 'APPLICATION_X_FONT_LIBGRX';

    #[MimeTypeInfo(mimeType: 'application/x-font-linux-psf', extensions: ['psf'], category: 'application')]
    case APPLICATION_X_FONT_LINUX_PSF = 'APPLICATION_X_FONT_LINUX_PSF';

    #[MimeTypeInfo(mimeType: 'application/x-font-pcf', extensions: ['pcf'], category: 'application')]
    case APPLICATION_X_FONT_PCF = 'APPLICATION_X_FONT_PCF';

    #[MimeTypeInfo(mimeType: 'application/x-font-snf', extensions: ['snf'], category: 'application')]
    case APPLICATION_X_FONT_SNF = 'APPLICATION_X_FONT_SNF';

    #[MimeTypeInfo(mimeType: 'application/x-font-speedo', extensions: [], category: 'application')]
    case APPLICATION_X_FONT_SPEEDO = 'APPLICATION_X_FONT_SPEEDO';

    #[MimeTypeInfo(mimeType: 'application/x-font-sunos-news', extensions: [], category: 'application')]
    case APPLICATION_X_FONT_SUNOS_NEWS = 'APPLICATION_X_FONT_SUNOS_NEWS';

    #[MimeTypeInfo(mimeType: 'application/x-font-type1', extensions: ['pfa', 'pfb', 'pfm', 'afm'], category: 'application')]
    case APPLICATION_X_FONT_TYPE1 = 'APPLICATION_X_FONT_TYPE1';

    #[MimeTypeInfo(mimeType: 'application/x-font-vfont', extensions: [], category: 'application')]
    case APPLICATION_X_FONT_VFONT = 'APPLICATION_X_FONT_VFONT';

    #[MimeTypeInfo(mimeType: 'application/x-freearc', extensions: ['arc'], category: 'application')]
    case APPLICATION_X_FREEARC = 'APPLICATION_X_FREEARC';

    #[MimeTypeInfo(mimeType: 'application/x-futuresplash', extensions: ['spl'], category: 'application')]
    case APPLICATION_X_FUTURESPLASH = 'APPLICATION_X_FUTURESPLASH';

    #[MimeTypeInfo(mimeType: 'application/x-gca-compressed', extensions: ['gca'], category: 'application')]
    case APPLICATION_X_GCA_COMPRESSED = 'APPLICATION_X_GCA_COMPRESSED';

    #[MimeTypeInfo(mimeType: 'application/x-glulx', extensions: ['ulx'], category: 'application')]
    case APPLICATION_X_GLULX = 'APPLICATION_X_GLULX';

    #[MimeTypeInfo(mimeType: 'application/x-gnumeric', extensions: ['gnumeric'], category: 'application')]
    case APPLICATION_X_GNUMERIC = 'APPLICATION_X_GNUMERIC';

    #[MimeTypeInfo(mimeType: 'application/x-gramps-xml', extensions: ['gramps'], category: 'application')]
    case APPLICATION_X_GRAMPS_XML = 'APPLICATION_X_GRAMPS_XML';

    #[MimeTypeInfo(mimeType: 'application/x-gtar', extensions: ['gtar'], category: 'application')]
    case APPLICATION_X_GTAR = 'APPLICATION_X_GTAR';

    #[MimeTypeInfo(mimeType: 'application/x-gzip', extensions: [], category: 'application')]
    case APPLICATION_X_GZIP = 'APPLICATION_X_GZIP';

    #[MimeTypeInfo(mimeType: 'application/x-hdf', extensions: ['hdf'], category: 'application')]
    case APPLICATION_X_HDF = 'APPLICATION_X_HDF';

    #[MimeTypeInfo(mimeType: 'application/x-httpd-php', extensions: ['php'], category: 'application')]
    case APPLICATION_X_HTTPD_PHP = 'APPLICATION_X_HTTPD_PHP';

    #[MimeTypeInfo(mimeType: 'application/x-install-instructions', extensions: ['install'], category: 'application')]
    case APPLICATION_X_INSTALL_INSTRUCTIONS = 'APPLICATION_X_INSTALL_INSTRUCTIONS';

    #[MimeTypeInfo(mimeType: 'application/x-ipynb+json', extensions: ['ipynb'], category: 'application')]
    case APPLICATION_X_IPYNB_JSON = 'APPLICATION_X_IPYNB_JSON';

    #[MimeTypeInfo(mimeType: 'application/x-iso9660-image', extensions: ['iso'], category: 'application')]
    case APPLICATION_X_ISO9660_IMAGE = 'APPLICATION_X_ISO9660_IMAGE';

    #[MimeTypeInfo(mimeType: 'application/x-iwork-keynote-sffkey', extensions: ['key'], category: 'application')]
    case APPLICATION_X_IWORK_KEYNOTE_SFFKEY = 'APPLICATION_X_IWORK_KEYNOTE_SFFKEY';

    #[MimeTypeInfo(mimeType: 'application/x-iwork-numbers-sffnumbers', extensions: ['numbers'], category: 'application')]
    case APPLICATION_X_IWORK_NUMBERS_SFFNUMBERS = 'APPLICATION_X_IWORK_NUMBERS_SFFNUMBERS';

    #[MimeTypeInfo(mimeType: 'application/x-iwork-pages-sffpages', extensions: ['pages'], category: 'application')]
    case APPLICATION_X_IWORK_PAGES_SFFPAGES = 'APPLICATION_X_IWORK_PAGES_SFFPAGES';

    #[MimeTypeInfo(mimeType: 'application/x-java-archive-diff', extensions: ['jardiff'], category: 'application')]
    case APPLICATION_X_JAVA_ARCHIVE_DIFF = 'APPLICATION_X_JAVA_ARCHIVE_DIFF';

    #[MimeTypeInfo(mimeType: 'application/x-java-jnlp-file', extensions: ['jnlp'], category: 'application')]
    case APPLICATION_X_JAVA_JNLP_FILE = 'APPLICATION_X_JAVA_JNLP_FILE';

    #[MimeTypeInfo(mimeType: 'application/x-javascript', extensions: [], category: 'application')]
    case APPLICATION_X_JAVASCRIPT = 'APPLICATION_X_JAVASCRIPT';

    #[MimeTypeInfo(mimeType: 'application/x-keepass2', extensions: ['kdbx'], category: 'application')]
    case APPLICATION_X_KEEPASS2 = 'APPLICATION_X_KEEPASS2';

    #[MimeTypeInfo(mimeType: 'application/x-latex', extensions: ['latex'], category: 'application')]
    case APPLICATION_X_LATEX = 'APPLICATION_X_LATEX';

    #[MimeTypeInfo(mimeType: 'application/x-lua-bytecode', extensions: ['luac'], category: 'application')]
    case APPLICATION_X_LUA_BYTECODE = 'APPLICATION_X_LUA_BYTECODE';

    #[MimeTypeInfo(mimeType: 'application/x-lzh-compressed', extensions: ['lzh', 'lha'], category: 'application')]
    case APPLICATION_X_LZH_COMPRESSED = 'APPLICATION_X_LZH_COMPRESSED';

    #[MimeTypeInfo(mimeType: 'application/x-makeself', extensions: ['run'], category: 'application')]
    case APPLICATION_X_MAKESELF = 'APPLICATION_X_MAKESELF';

    #[MimeTypeInfo(mimeType: 'application/x-mie', extensions: ['mie'], category: 'application')]
    case APPLICATION_X_MIE = 'APPLICATION_X_MIE';

    #[MimeTypeInfo(mimeType: 'application/x-mobipocket-ebook', extensions: ['prc', 'mobi'], category: 'application')]
    case APPLICATION_X_MOBIPOCKET_EBOOK = 'APPLICATION_X_MOBIPOCKET_EBOOK';

    #[MimeTypeInfo(mimeType: 'application/x-mpegurl', extensions: [], category: 'application')]
    case APPLICATION_X_MPEGURL = 'APPLICATION_X_MPEGURL';

    #[MimeTypeInfo(mimeType: 'application/x-ms-application', extensions: ['application'], category: 'application')]
    case APPLICATION_X_MS_APPLICATION = 'APPLICATION_X_MS_APPLICATION';

    #[MimeTypeInfo(mimeType: 'application/x-ms-shortcut', extensions: ['lnk'], category: 'application')]
    case APPLICATION_X_MS_SHORTCUT = 'APPLICATION_X_MS_SHORTCUT';

    #[MimeTypeInfo(mimeType: 'application/x-ms-wmd', extensions: ['wmd'], category: 'application')]
    case APPLICATION_X_MS_WMD = 'APPLICATION_X_MS_WMD';

    #[MimeTypeInfo(mimeType: 'application/x-ms-wmz', extensions: ['wmz'], category: 'application')]
    case APPLICATION_X_MS_WMZ = 'APPLICATION_X_MS_WMZ';

    #[MimeTypeInfo(mimeType: 'application/x-ms-xbap', extensions: ['xbap'], category: 'application')]
    case APPLICATION_X_MS_XBAP = 'APPLICATION_X_MS_XBAP';

    #[MimeTypeInfo(mimeType: 'application/x-msaccess', extensions: ['mdb'], category: 'application')]
    case APPLICATION_X_MSACCESS = 'APPLICATION_X_MSACCESS';

    #[MimeTypeInfo(mimeType: 'application/x-msbinder', extensions: ['obd'], category: 'application')]
    case APPLICATION_X_MSBINDER = 'APPLICATION_X_MSBINDER';

    #[MimeTypeInfo(mimeType: 'application/x-mscardfile', extensions: ['crd'], category: 'application')]
    case APPLICATION_X_MSCARDFILE = 'APPLICATION_X_MSCARDFILE';

    #[MimeTypeInfo(mimeType: 'application/x-msclip', extensions: ['clp'], category: 'application')]
    case APPLICATION_X_MSCLIP = 'APPLICATION_X_MSCLIP';

    #[MimeTypeInfo(mimeType: 'application/x-msdos-program', extensions: ['exe'], category: 'application')]
    case APPLICATION_X_MSDOS_PROGRAM = 'APPLICATION_X_MSDOS_PROGRAM';

    #[MimeTypeInfo(mimeType: 'application/x-msdownload', extensions: ['exe', 'dll', 'com', 'bat', 'msi'], category: 'application')]
    case APPLICATION_X_MSDOWNLOAD = 'APPLICATION_X_MSDOWNLOAD';

    #[MimeTypeInfo(mimeType: 'application/x-msmediaview', extensions: ['mvb', 'm13', 'm14'], category: 'application')]
    case APPLICATION_X_MSMEDIAVIEW = 'APPLICATION_X_MSMEDIAVIEW';

    #[MimeTypeInfo(mimeType: 'application/x-msmetafile', extensions: ['wmf', 'wmz', 'emf', 'emz'], category: 'application')]
    case APPLICATION_X_MSMETAFILE = 'APPLICATION_X_MSMETAFILE';

    #[MimeTypeInfo(mimeType: 'application/x-msmoney', extensions: ['mny'], category: 'application')]
    case APPLICATION_X_MSMONEY = 'APPLICATION_X_MSMONEY';

    #[MimeTypeInfo(mimeType: 'application/x-mspublisher', extensions: ['pub'], category: 'application')]
    case APPLICATION_X_MSPUBLISHER = 'APPLICATION_X_MSPUBLISHER';

    #[MimeTypeInfo(mimeType: 'application/x-msschedule', extensions: ['scd'], category: 'application')]
    case APPLICATION_X_MSSCHEDULE = 'APPLICATION_X_MSSCHEDULE';

    #[MimeTypeInfo(mimeType: 'application/x-msterminal', extensions: ['trm'], category: 'application')]
    case APPLICATION_X_MSTERMINAL = 'APPLICATION_X_MSTERMINAL';

    #[MimeTypeInfo(mimeType: 'application/x-mswrite', extensions: ['wri'], category: 'application')]
    case APPLICATION_X_MSWRITE = 'APPLICATION_X_MSWRITE';

    #[MimeTypeInfo(mimeType: 'application/x-netcdf', extensions: ['nc', 'cdf'], category: 'application')]
    case APPLICATION_X_NETCDF = 'APPLICATION_X_NETCDF';

    #[MimeTypeInfo(mimeType: 'application/x-ns-proxy-autoconfig', extensions: ['pac'], category: 'application')]
    case APPLICATION_X_NS_PROXY_AUTOCONFIG = 'APPLICATION_X_NS_PROXY_AUTOCONFIG';

    #[MimeTypeInfo(mimeType: 'application/x-nzb', extensions: ['nzb'], category: 'application')]
    case APPLICATION_X_NZB = 'APPLICATION_X_NZB';

    #[MimeTypeInfo(mimeType: 'application/x-perl', extensions: ['pl', 'pm'], category: 'application')]
    case APPLICATION_X_PERL = 'APPLICATION_X_PERL';

    #[MimeTypeInfo(mimeType: 'application/x-pilot', extensions: ['prc', 'pdb'], category: 'application')]
    case APPLICATION_X_PILOT = 'APPLICATION_X_PILOT';

    #[MimeTypeInfo(mimeType: 'application/x-pkcs12', extensions: ['p12', 'pfx'], category: 'application')]
    case APPLICATION_X_PKCS12 = 'APPLICATION_X_PKCS12';

    #[MimeTypeInfo(mimeType: 'application/x-pkcs7-certificates', extensions: ['p7b', 'spc'], category: 'application')]
    case APPLICATION_X_PKCS7_CERTIFICATES = 'APPLICATION_X_PKCS7_CERTIFICATES';

    #[MimeTypeInfo(mimeType: 'application/x-pkcs7-certreqresp', extensions: ['p7r'], category: 'application')]
    case APPLICATION_X_PKCS7_CERTREQRESP = 'APPLICATION_X_PKCS7_CERTREQRESP';

    #[MimeTypeInfo(mimeType: 'application/x-pki-message', extensions: [], category: 'application')]
    case APPLICATION_X_PKI_MESSAGE = 'APPLICATION_X_PKI_MESSAGE';

    #[MimeTypeInfo(mimeType: 'application/x-rar-compressed', extensions: ['rar'], category: 'application')]
    case APPLICATION_X_RAR_COMPRESSED = 'APPLICATION_X_RAR_COMPRESSED';

    #[MimeTypeInfo(mimeType: 'application/x-redhat-package-manager', extensions: ['rpm'], category: 'application')]
    case APPLICATION_X_REDHAT_PACKAGE_MANAGER = 'APPLICATION_X_REDHAT_PACKAGE_MANAGER';

    #[MimeTypeInfo(mimeType: 'application/x-research-info-systems', extensions: ['ris'], category: 'application')]
    case APPLICATION_X_RESEARCH_INFO_SYSTEMS = 'APPLICATION_X_RESEARCH_INFO_SYSTEMS';

    #[MimeTypeInfo(mimeType: 'application/x-sea', extensions: ['sea'], category: 'application')]
    case APPLICATION_X_SEA = 'APPLICATION_X_SEA';

    #[MimeTypeInfo(mimeType: 'application/x-sh', extensions: ['sh'], category: 'application')]
    case APPLICATION_X_SH = 'APPLICATION_X_SH';

    #[MimeTypeInfo(mimeType: 'application/x-shar', extensions: ['shar'], category: 'application')]
    case APPLICATION_X_SHAR = 'APPLICATION_X_SHAR';

    #[MimeTypeInfo(mimeType: 'application/x-shockwave-flash', extensions: ['swf'], category: 'application')]
    case APPLICATION_X_SHOCKWAVE_FLASH = 'APPLICATION_X_SHOCKWAVE_FLASH';

    #[MimeTypeInfo(mimeType: 'application/x-silverlight-app', extensions: ['xap'], category: 'application')]
    case APPLICATION_X_SILVERLIGHT_APP = 'APPLICATION_X_SILVERLIGHT_APP';

    #[MimeTypeInfo(mimeType: 'application/x-sql', extensions: ['sql'], category: 'application')]
    case APPLICATION_X_SQL = 'APPLICATION_X_SQL';

    #[MimeTypeInfo(mimeType: 'application/x-stuffit', extensions: ['sit'], category: 'application')]
    case APPLICATION_X_STUFFIT = 'APPLICATION_X_STUFFIT';

    #[MimeTypeInfo(mimeType: 'application/x-stuffitx', extensions: ['sitx'], category: 'application')]
    case APPLICATION_X_STUFFITX = 'APPLICATION_X_STUFFITX';

    #[MimeTypeInfo(mimeType: 'application/x-subrip', extensions: ['srt'], category: 'application')]
    case APPLICATION_X_SUBRIP = 'APPLICATION_X_SUBRIP';

    #[MimeTypeInfo(mimeType: 'application/x-sv4cpio', extensions: ['sv4cpio'], category: 'application')]
    case APPLICATION_X_SV4CPIO = 'APPLICATION_X_SV4CPIO';

    #[MimeTypeInfo(mimeType: 'application/x-sv4crc', extensions: ['sv4crc'], category: 'application')]
    case APPLICATION_X_SV4CRC = 'APPLICATION_X_SV4CRC';

    #[MimeTypeInfo(mimeType: 'application/x-t3vm-image', extensions: ['t3'], category: 'application')]
    case APPLICATION_X_T3VM_IMAGE = 'APPLICATION_X_T3VM_IMAGE';

    #[MimeTypeInfo(mimeType: 'application/x-tads', extensions: ['gam'], category: 'application')]
    case APPLICATION_X_TADS = 'APPLICATION_X_TADS';

    #[MimeTypeInfo(mimeType: 'application/x-tar', extensions: ['tar'], category: 'application')]
    case APPLICATION_X_TAR = 'APPLICATION_X_TAR';

    #[MimeTypeInfo(mimeType: 'application/x-tcl', extensions: ['tcl', 'tk'], category: 'application')]
    case APPLICATION_X_TCL = 'APPLICATION_X_TCL';

    #[MimeTypeInfo(mimeType: 'application/x-tex', extensions: ['tex'], category: 'application')]
    case APPLICATION_X_TEX = 'APPLICATION_X_TEX';

    #[MimeTypeInfo(mimeType: 'application/x-tex-tfm', extensions: ['tfm'], category: 'application')]
    case APPLICATION_X_TEX_TFM = 'APPLICATION_X_TEX_TFM';

    #[MimeTypeInfo(mimeType: 'application/x-texinfo', extensions: ['texinfo', 'texi'], category: 'application')]
    case APPLICATION_X_TEXINFO = 'APPLICATION_X_TEXINFO';

    #[MimeTypeInfo(mimeType: 'application/x-tgif', extensions: ['obj'], category: 'application')]
    case APPLICATION_X_TGIF = 'APPLICATION_X_TGIF';

    #[MimeTypeInfo(mimeType: 'application/x-ustar', extensions: ['ustar'], category: 'application')]
    case APPLICATION_X_USTAR = 'APPLICATION_X_USTAR';

    #[MimeTypeInfo(mimeType: 'application/x-virtualbox-hdd', extensions: ['hdd'], category: 'application')]
    case APPLICATION_X_VIRTUALBOX_HDD = 'APPLICATION_X_VIRTUALBOX_HDD';

    #[MimeTypeInfo(mimeType: 'application/x-virtualbox-ova', extensions: ['ova'], category: 'application')]
    case APPLICATION_X_VIRTUALBOX_OVA = 'APPLICATION_X_VIRTUALBOX_OVA';

    #[MimeTypeInfo(mimeType: 'application/x-virtualbox-ovf', extensions: ['ovf'], category: 'application')]
    case APPLICATION_X_VIRTUALBOX_OVF = 'APPLICATION_X_VIRTUALBOX_OVF';

    #[MimeTypeInfo(mimeType: 'application/x-virtualbox-vbox', extensions: ['vbox'], category: 'application')]
    case APPLICATION_X_VIRTUALBOX_VBOX = 'APPLICATION_X_VIRTUALBOX_VBOX';

    #[MimeTypeInfo(mimeType: 'application/x-virtualbox-vbox-extpack', extensions: ['vbox-extpack'], category: 'application')]
    case APPLICATION_X_VIRTUALBOX_VBOX_EXTPACK = 'APPLICATION_X_VIRTUALBOX_VBOX_EXTPACK';

    #[MimeTypeInfo(mimeType: 'application/x-virtualbox-vdi', extensions: ['vdi'], category: 'application')]
    case APPLICATION_X_VIRTUALBOX_VDI = 'APPLICATION_X_VIRTUALBOX_VDI';

    #[MimeTypeInfo(mimeType: 'application/x-virtualbox-vhd', extensions: ['vhd'], category: 'application')]
    case APPLICATION_X_VIRTUALBOX_VHD = 'APPLICATION_X_VIRTUALBOX_VHD';

    #[MimeTypeInfo(mimeType: 'application/x-virtualbox-vmdk', extensions: ['vmdk'], category: 'application')]
    case APPLICATION_X_VIRTUALBOX_VMDK = 'APPLICATION_X_VIRTUALBOX_VMDK';

    #[MimeTypeInfo(mimeType: 'application/x-wais-source', extensions: ['src'], category: 'application')]
    case APPLICATION_X_WAIS_SOURCE = 'APPLICATION_X_WAIS_SOURCE';

    #[MimeTypeInfo(mimeType: 'application/x-web-app-manifest+json', extensions: ['webapp'], category: 'application')]
    case APPLICATION_X_WEB_APP_MANIFEST_JSON = 'APPLICATION_X_WEB_APP_MANIFEST_JSON';

    #[MimeTypeInfo(mimeType: 'application/x-www-form-urlencoded', extensions: [], category: 'application')]
    case APPLICATION_X_WWW_FORM_URLENCODED = 'APPLICATION_X_WWW_FORM_URLENCODED';

    #[MimeTypeInfo(mimeType: 'application/x-x509-ca-cert', extensions: ['der', 'crt', 'pem'], category: 'application')]
    case APPLICATION_X_X509_CA_CERT = 'APPLICATION_X_X509_CA_CERT';

    #[MimeTypeInfo(mimeType: 'application/x-x509-ca-ra-cert', extensions: [], category: 'application')]
    case APPLICATION_X_X509_CA_RA_CERT = 'APPLICATION_X_X509_CA_RA_CERT';

    #[MimeTypeInfo(mimeType: 'application/x-x509-next-ca-cert', extensions: [], category: 'application')]
    case APPLICATION_X_X509_NEXT_CA_CERT = 'APPLICATION_X_X509_NEXT_CA_CERT';

    #[MimeTypeInfo(mimeType: 'application/x-xfig', extensions: ['fig'], category: 'application')]
    case APPLICATION_X_XFIG = 'APPLICATION_X_XFIG';

    #[MimeTypeInfo(mimeType: 'application/x-xliff+xml', extensions: ['xlf'], category: 'application')]
    case APPLICATION_X_XLIFF_XML = 'APPLICATION_X_XLIFF_XML';

    #[MimeTypeInfo(mimeType: 'application/x-xpinstall', extensions: ['xpi'], category: 'application')]
    case APPLICATION_X_XPINSTALL = 'APPLICATION_X_XPINSTALL';

    #[MimeTypeInfo(mimeType: 'application/x-xz', extensions: ['xz'], category: 'application')]
    case APPLICATION_X_XZ = 'APPLICATION_X_XZ';

    #[MimeTypeInfo(mimeType: 'application/x-zip-compressed', extensions: ['zip'], category: 'application')]
    case APPLICATION_X_ZIP_COMPRESSED = 'APPLICATION_X_ZIP_COMPRESSED';

    #[MimeTypeInfo(mimeType: 'application/x-zmachine', extensions: ['z1', 'z2', 'z3', 'z4', 'z5', 'z6', 'z7', 'z8'], category: 'application')]
    case APPLICATION_X_ZMACHINE = 'APPLICATION_X_ZMACHINE';

    #[MimeTypeInfo(mimeType: 'application/x400-bp', extensions: [], category: 'application')]
    case APPLICATION_X400_BP = 'APPLICATION_X400_BP';

    #[MimeTypeInfo(mimeType: 'application/xacml+xml', extensions: [], category: 'application')]
    case APPLICATION_XACML_XML = 'APPLICATION_XACML_XML';

    #[MimeTypeInfo(mimeType: 'application/xaml+xml', extensions: ['xaml'], category: 'application')]
    case APPLICATION_XAML_XML = 'APPLICATION_XAML_XML';

    #[MimeTypeInfo(mimeType: 'application/xcap-att+xml', extensions: ['xav'], category: 'application')]
    case APPLICATION_XCAP_ATT_XML = 'APPLICATION_XCAP_ATT_XML';

    #[MimeTypeInfo(mimeType: 'application/xcap-caps+xml', extensions: ['xca'], category: 'application')]
    case APPLICATION_XCAP_CAPS_XML = 'APPLICATION_XCAP_CAPS_XML';

    #[MimeTypeInfo(mimeType: 'application/xcap-diff+xml', extensions: ['xdf'], category: 'application')]
    case APPLICATION_XCAP_DIFF_XML = 'APPLICATION_XCAP_DIFF_XML';

    #[MimeTypeInfo(mimeType: 'application/xcap-el+xml', extensions: ['xel'], category: 'application')]
    case APPLICATION_XCAP_EL_XML = 'APPLICATION_XCAP_EL_XML';

    #[MimeTypeInfo(mimeType: 'application/xcap-error+xml', extensions: ['xer'], category: 'application')]
    case APPLICATION_XCAP_ERROR_XML = 'APPLICATION_XCAP_ERROR_XML';

    #[MimeTypeInfo(mimeType: 'application/xcap-ns+xml', extensions: ['xns'], category: 'application')]
    case APPLICATION_XCAP_NS_XML = 'APPLICATION_XCAP_NS_XML';

    #[MimeTypeInfo(mimeType: 'application/xcon-conference-info+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_XCON_CONFERENCE_INFO_XML = 'APPLICATION_XCON_CONFERENCE_INFO_XML';

    #[MimeTypeInfo(mimeType: 'application/xcon-conference-info-diff+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_XCON_CONFERENCE_INFO_DIFF_XML = 'APPLICATION_XCON_CONFERENCE_INFO_DIFF_XML';

    #[MimeTypeInfo(mimeType: 'application/xenc+xml', extensions: ['xenc', 'xml'], category: 'application')]
    case APPLICATION_XENC_XML = 'APPLICATION_XENC_XML';

    #[MimeTypeInfo(mimeType: 'application/xfdf', extensions: ['xfdf'], category: 'application')]
    case APPLICATION_XFDF = 'APPLICATION_XFDF';

    #[MimeTypeInfo(mimeType: 'application/xhtml+xml', extensions: ['xhtml', 'xht'], category: 'application')]
    case APPLICATION_XHTML_XML = 'APPLICATION_XHTML_XML';

    #[MimeTypeInfo(mimeType: 'application/xhtml-voice+xml', extensions: ['mxml', 'xhvml', 'xvml', 'xvm'], category: 'application')]
    case APPLICATION_XHTML_VOICE_XML = 'APPLICATION_XHTML_VOICE_XML';

    #[MimeTypeInfo(mimeType: 'application/xliff+xml', extensions: ['xlf'], category: 'application')]
    case APPLICATION_XLIFF_XML = 'APPLICATION_XLIFF_XML';

    #[MimeTypeInfo(mimeType: 'application/xml', extensions: ['xml', 'xsl', 'xsd', 'rng'], category: 'application')]
    case APPLICATION_XML = 'APPLICATION_XML';

    #[MimeTypeInfo(mimeType: 'application/xml-dtd', extensions: ['dtd', 'mod'], category: 'application')]
    case APPLICATION_XML_DTD = 'APPLICATION_XML_DTD';

    #[MimeTypeInfo(mimeType: 'application/xml-external-parsed-entity', extensions: ['xml', 'ent'], category: 'application')]
    case APPLICATION_XML_EXTERNAL_PARSED_ENTITY = 'APPLICATION_XML_EXTERNAL_PARSED_ENTITY';

    #[MimeTypeInfo(mimeType: 'application/xml-patch+xml', extensions: ['xml'], category: 'application')]
    case APPLICATION_XML_PATCH_XML = 'APPLICATION_XML_PATCH_XML';

    #[MimeTypeInfo(mimeType: 'application/xmpp+xml', extensions: [], category: 'application')]
    case APPLICATION_XMPP_XML = 'APPLICATION_XMPP_XML';

    #[MimeTypeInfo(mimeType: 'application/xop+xml', extensions: ['xop'], category: 'application')]
    case APPLICATION_XOP_XML = 'APPLICATION_XOP_XML';

    #[MimeTypeInfo(mimeType: 'application/xproc+xml', extensions: ['xpl'], category: 'application')]
    case APPLICATION_XPROC_XML = 'APPLICATION_XPROC_XML';

    #[MimeTypeInfo(mimeType: 'application/xslt+xml', extensions: ['xsl', 'xslt'], category: 'application')]
    case APPLICATION_XSLT_XML = 'APPLICATION_XSLT_XML';

    #[MimeTypeInfo(mimeType: 'application/xspf+xml', extensions: ['xspf'], category: 'application')]
    case APPLICATION_XSPF_XML = 'APPLICATION_XSPF_XML';

    #[MimeTypeInfo(mimeType: 'application/xv+xml', extensions: ['mxml', 'xhvml', 'xvml', 'xvm'], category: 'application')]
    case APPLICATION_XV_XML = 'APPLICATION_XV_XML';

    #[MimeTypeInfo(mimeType: 'application/yaml', extensions: ['yaml', 'yml'], category: 'application')]
    case APPLICATION_YAML = 'APPLICATION_YAML';

    #[MimeTypeInfo(mimeType: 'application/yang', extensions: ['yang'], category: 'application')]
    case APPLICATION_YANG = 'APPLICATION_YANG';

    #[MimeTypeInfo(mimeType: 'application/yang-data+cbor', extensions: [], category: 'application')]
    case APPLICATION_YANG_DATA_CBOR = 'APPLICATION_YANG_DATA_CBOR';

    #[MimeTypeInfo(mimeType: 'application/yang-data+json', extensions: [], category: 'application')]
    case APPLICATION_YANG_DATA_JSON = 'APPLICATION_YANG_DATA_JSON';

    #[MimeTypeInfo(mimeType: 'application/yang-data+xml', extensions: [], category: 'application')]
    case APPLICATION_YANG_DATA_XML = 'APPLICATION_YANG_DATA_XML';

    #[MimeTypeInfo(mimeType: 'application/yang-patch+json', extensions: [], category: 'application')]
    case APPLICATION_YANG_PATCH_JSON = 'APPLICATION_YANG_PATCH_JSON';

    #[MimeTypeInfo(mimeType: 'application/yang-patch+xml', extensions: [], category: 'application')]
    case APPLICATION_YANG_PATCH_XML = 'APPLICATION_YANG_PATCH_XML';

    #[MimeTypeInfo(mimeType: 'application/yang-sid+json', extensions: ['sid'], category: 'application')]
    case APPLICATION_YANG_SID_JSON = 'APPLICATION_YANG_SID_JSON';

    #[MimeTypeInfo(mimeType: 'application/yin+xml', extensions: ['yin'], category: 'application')]
    case APPLICATION_YIN_XML = 'APPLICATION_YIN_XML';

    #[MimeTypeInfo(mimeType: 'application/zip', extensions: ['zip'], category: 'application')]
    case APPLICATION_ZIP = 'APPLICATION_ZIP';

    #[MimeTypeInfo(mimeType: 'application/zip+dotlottie', extensions: ['lottie'], category: 'application')]
    case APPLICATION_ZIP_DOTLOTTIE = 'APPLICATION_ZIP_DOTLOTTIE';

    #[MimeTypeInfo(mimeType: 'application/zlib', extensions: [], category: 'application')]
    case APPLICATION_ZLIB = 'APPLICATION_ZLIB';

    #[MimeTypeInfo(mimeType: 'application/zstd', extensions: ['zst'], category: 'application')]
    case APPLICATION_ZSTD = 'APPLICATION_ZSTD';

    #[MimeTypeInfo(mimeType: 'audio/1d-interleaved-parityfec', extensions: [], category: 'audio')]
    case AUDIO_1D_INTERLEAVED_PARITYFEC = 'AUDIO_1D_INTERLEAVED_PARITYFEC';

    #[MimeTypeInfo(mimeType: 'audio/32kadpcm', extensions: ['726'], category: 'audio')]
    case AUDIO_32KADPCM = 'AUDIO_32KADPCM';

    #[MimeTypeInfo(mimeType: 'audio/3gpp', extensions: ['3gp', '3gpp'], category: 'audio')]
    case AUDIO_3GPP = 'AUDIO_3GPP';

    #[MimeTypeInfo(mimeType: 'audio/3gpp2', extensions: ['3g2'], category: 'audio')]
    case AUDIO_3GPP2 = 'AUDIO_3GPP2';

    #[MimeTypeInfo(mimeType: 'audio/aac', extensions: ['adts', 'aac', 'loas', 'ass'], category: 'audio')]
    case AUDIO_AAC = 'AUDIO_AAC';

    #[MimeTypeInfo(mimeType: 'audio/ac3', extensions: [], category: 'audio')]
    case AUDIO_AC3 = 'AUDIO_AC3';

    #[MimeTypeInfo(mimeType: 'audio/adpcm', extensions: ['adp'], category: 'audio')]
    case AUDIO_ADPCM = 'AUDIO_ADPCM';

    #[MimeTypeInfo(mimeType: 'audio/amr', extensions: ['amr'], category: 'audio')]
    case AUDIO_AMR = 'AUDIO_AMR';

    #[MimeTypeInfo(mimeType: 'audio/amr-wb', extensions: ['awb'], category: 'audio')]
    case AUDIO_AMR_WB = 'AUDIO_AMR_WB';

    #[MimeTypeInfo(mimeType: 'audio/aptx', extensions: [], category: 'audio')]
    case AUDIO_APTX = 'AUDIO_APTX';

    #[MimeTypeInfo(mimeType: 'audio/asc', extensions: [], category: 'audio')]
    case AUDIO_ASC = 'AUDIO_ASC';

    #[MimeTypeInfo(mimeType: 'audio/atrac-advanced-lossless', extensions: ['aal', 'aa3', 'omg'], category: 'audio')]
    case AUDIO_ATRAC_ADVANCED_LOSSLESS = 'AUDIO_ATRAC_ADVANCED_LOSSLESS';

    #[MimeTypeInfo(mimeType: 'audio/atrac-x', extensions: ['atx', 'aa3', 'omg'], category: 'audio')]
    case AUDIO_ATRAC_X = 'AUDIO_ATRAC_X';

    #[MimeTypeInfo(mimeType: 'audio/atrac3', extensions: ['at3', 'aa3', 'omg'], category: 'audio')]
    case AUDIO_ATRAC3 = 'AUDIO_ATRAC3';

    #[MimeTypeInfo(mimeType: 'audio/basic', extensions: ['au', 'snd'], category: 'audio')]
    case AUDIO_BASIC = 'AUDIO_BASIC';

    #[MimeTypeInfo(mimeType: 'audio/bv16', extensions: [], category: 'audio')]
    case AUDIO_BV16 = 'AUDIO_BV16';

    #[MimeTypeInfo(mimeType: 'audio/bv32', extensions: [], category: 'audio')]
    case AUDIO_BV32 = 'AUDIO_BV32';

    #[MimeTypeInfo(mimeType: 'audio/clearmode', extensions: [], category: 'audio')]
    case AUDIO_CLEARMODE = 'AUDIO_CLEARMODE';

    #[MimeTypeInfo(mimeType: 'audio/cn', extensions: [], category: 'audio')]
    case AUDIO_CN = 'AUDIO_CN';

    #[MimeTypeInfo(mimeType: 'audio/dat12', extensions: [], category: 'audio')]
    case AUDIO_DAT12 = 'AUDIO_DAT12';

    #[MimeTypeInfo(mimeType: 'audio/dls', extensions: ['dls'], category: 'audio')]
    case AUDIO_DLS = 'AUDIO_DLS';

    #[MimeTypeInfo(mimeType: 'audio/dsr-es201108', extensions: [], category: 'audio')]
    case AUDIO_DSR_ES201108 = 'AUDIO_DSR_ES201108';

    #[MimeTypeInfo(mimeType: 'audio/dsr-es202050', extensions: [], category: 'audio')]
    case AUDIO_DSR_ES202050 = 'AUDIO_DSR_ES202050';

    #[MimeTypeInfo(mimeType: 'audio/dsr-es202211', extensions: [], category: 'audio')]
    case AUDIO_DSR_ES202211 = 'AUDIO_DSR_ES202211';

    #[MimeTypeInfo(mimeType: 'audio/dsr-es202212', extensions: [], category: 'audio')]
    case AUDIO_DSR_ES202212 = 'AUDIO_DSR_ES202212';

    #[MimeTypeInfo(mimeType: 'audio/dv', extensions: [], category: 'audio')]
    case AUDIO_DV = 'AUDIO_DV';

    #[MimeTypeInfo(mimeType: 'audio/dvi4', extensions: [], category: 'audio')]
    case AUDIO_DVI4 = 'AUDIO_DVI4';

    #[MimeTypeInfo(mimeType: 'audio/eac3', extensions: [], category: 'audio')]
    case AUDIO_EAC3 = 'AUDIO_EAC3';

    #[MimeTypeInfo(mimeType: 'audio/encaprtp', extensions: [], category: 'audio')]
    case AUDIO_ENCAPRTP = 'AUDIO_ENCAPRTP';

    #[MimeTypeInfo(mimeType: 'audio/evrc', extensions: ['evc'], category: 'audio')]
    case AUDIO_EVRC = 'AUDIO_EVRC';

    #[MimeTypeInfo(mimeType: 'audio/evrc-qcp', extensions: ['qcp'], category: 'audio')]
    case AUDIO_EVRC_QCP = 'AUDIO_EVRC_QCP';

    #[MimeTypeInfo(mimeType: 'audio/evrc0', extensions: [], category: 'audio')]
    case AUDIO_EVRC0 = 'AUDIO_EVRC0';

    #[MimeTypeInfo(mimeType: 'audio/evrc1', extensions: [], category: 'audio')]
    case AUDIO_EVRC1 = 'AUDIO_EVRC1';

    #[MimeTypeInfo(mimeType: 'audio/evrcb', extensions: ['evb'], category: 'audio')]
    case AUDIO_EVRCB = 'AUDIO_EVRCB';

    #[MimeTypeInfo(mimeType: 'audio/evrcb0', extensions: [], category: 'audio')]
    case AUDIO_EVRCB0 = 'AUDIO_EVRCB0';

    #[MimeTypeInfo(mimeType: 'audio/evrcb1', extensions: [], category: 'audio')]
    case AUDIO_EVRCB1 = 'AUDIO_EVRCB1';

    #[MimeTypeInfo(mimeType: 'audio/evrcnw', extensions: ['enw'], category: 'audio')]
    case AUDIO_EVRCNW = 'AUDIO_EVRCNW';

    #[MimeTypeInfo(mimeType: 'audio/evrcnw0', extensions: [], category: 'audio')]
    case AUDIO_EVRCNW0 = 'AUDIO_EVRCNW0';

    #[MimeTypeInfo(mimeType: 'audio/evrcnw1', extensions: [], category: 'audio')]
    case AUDIO_EVRCNW1 = 'AUDIO_EVRCNW1';

    #[MimeTypeInfo(mimeType: 'audio/evrcwb', extensions: ['evw'], category: 'audio')]
    case AUDIO_EVRCWB = 'AUDIO_EVRCWB';

    #[MimeTypeInfo(mimeType: 'audio/evrcwb0', extensions: [], category: 'audio')]
    case AUDIO_EVRCWB0 = 'AUDIO_EVRCWB0';

    #[MimeTypeInfo(mimeType: 'audio/evrcwb1', extensions: [], category: 'audio')]
    case AUDIO_EVRCWB1 = 'AUDIO_EVRCWB1';

    #[MimeTypeInfo(mimeType: 'audio/evs', extensions: ['3gp', '3gpp'], category: 'audio')]
    case AUDIO_EVS = 'AUDIO_EVS';

    #[MimeTypeInfo(mimeType: 'audio/example', extensions: [], category: 'audio')]
    case AUDIO_EXAMPLE = 'AUDIO_EXAMPLE';

    #[MimeTypeInfo(mimeType: 'audio/flac', extensions: ['flac'], category: 'audio')]
    case AUDIO_FLAC = 'AUDIO_FLAC';

    #[MimeTypeInfo(mimeType: 'audio/flexfec', extensions: [], category: 'audio')]
    case AUDIO_FLEXFEC = 'AUDIO_FLEXFEC';

    #[MimeTypeInfo(mimeType: 'audio/fwdred', extensions: [], category: 'audio')]
    case AUDIO_FWDRED = 'AUDIO_FWDRED';

    #[MimeTypeInfo(mimeType: 'audio/g711-0', extensions: [], category: 'audio')]
    case AUDIO_G711_0 = 'AUDIO_G711_0';

    #[MimeTypeInfo(mimeType: 'audio/g719', extensions: [], category: 'audio')]
    case AUDIO_G719 = 'AUDIO_G719';

    #[MimeTypeInfo(mimeType: 'audio/g722', extensions: [], category: 'audio')]
    case AUDIO_G722 = 'AUDIO_G722';

    #[MimeTypeInfo(mimeType: 'audio/g7221', extensions: [], category: 'audio')]
    case AUDIO_G7221 = 'AUDIO_G7221';

    #[MimeTypeInfo(mimeType: 'audio/g723', extensions: [], category: 'audio')]
    case AUDIO_G723 = 'AUDIO_G723';

    #[MimeTypeInfo(mimeType: 'audio/g726-16', extensions: [], category: 'audio')]
    case AUDIO_G726_16 = 'AUDIO_G726_16';

    #[MimeTypeInfo(mimeType: 'audio/g726-24', extensions: [], category: 'audio')]
    case AUDIO_G726_24 = 'AUDIO_G726_24';

    #[MimeTypeInfo(mimeType: 'audio/g726-32', extensions: [], category: 'audio')]
    case AUDIO_G726_32 = 'AUDIO_G726_32';

    #[MimeTypeInfo(mimeType: 'audio/g726-40', extensions: [], category: 'audio')]
    case AUDIO_G726_40 = 'AUDIO_G726_40';

    #[MimeTypeInfo(mimeType: 'audio/g728', extensions: [], category: 'audio')]
    case AUDIO_G728 = 'AUDIO_G728';

    #[MimeTypeInfo(mimeType: 'audio/g729', extensions: [], category: 'audio')]
    case AUDIO_G729 = 'AUDIO_G729';

    #[MimeTypeInfo(mimeType: 'audio/g7291', extensions: [], category: 'audio')]
    case AUDIO_G7291 = 'AUDIO_G7291';

    #[MimeTypeInfo(mimeType: 'audio/g729d', extensions: [], category: 'audio')]
    case AUDIO_G729D = 'AUDIO_G729D';

    #[MimeTypeInfo(mimeType: 'audio/g729e', extensions: [], category: 'audio')]
    case AUDIO_G729E = 'AUDIO_G729E';

    #[MimeTypeInfo(mimeType: 'audio/gsm', extensions: [], category: 'audio')]
    case AUDIO_GSM = 'AUDIO_GSM';

    #[MimeTypeInfo(mimeType: 'audio/gsm-efr', extensions: [], category: 'audio')]
    case AUDIO_GSM_EFR = 'AUDIO_GSM_EFR';

    #[MimeTypeInfo(mimeType: 'audio/gsm-hr-08', extensions: [], category: 'audio')]
    case AUDIO_GSM_HR_08 = 'AUDIO_GSM_HR_08';

    #[MimeTypeInfo(mimeType: 'audio/ilbc', extensions: ['lbc'], category: 'audio')]
    case AUDIO_ILBC = 'AUDIO_ILBC';

    #[MimeTypeInfo(mimeType: 'audio/ip-mr_v2.5', extensions: [], category: 'audio')]
    case AUDIO_IP_MR_V2_5 = 'AUDIO_IP_MR_V2_5';

    #[MimeTypeInfo(mimeType: 'audio/isac', extensions: [], category: 'audio')]
    case AUDIO_ISAC = 'AUDIO_ISAC';

    #[MimeTypeInfo(mimeType: 'audio/l16', extensions: ['wav'], category: 'audio')]
    case AUDIO_L16 = 'AUDIO_L16';

    #[MimeTypeInfo(mimeType: 'audio/l20', extensions: [], category: 'audio')]
    case AUDIO_L20 = 'AUDIO_L20';

    #[MimeTypeInfo(mimeType: 'audio/l24', extensions: [], category: 'audio')]
    case AUDIO_L24 = 'AUDIO_L24';

    #[MimeTypeInfo(mimeType: 'audio/l8', extensions: [], category: 'audio')]
    case AUDIO_L8 = 'AUDIO_L8';

    #[MimeTypeInfo(mimeType: 'audio/lpc', extensions: [], category: 'audio')]
    case AUDIO_LPC = 'AUDIO_LPC';

    #[MimeTypeInfo(mimeType: 'audio/matroska', extensions: ['mka'], category: 'audio')]
    case AUDIO_MATROSKA = 'AUDIO_MATROSKA';

    #[MimeTypeInfo(mimeType: 'audio/melp', extensions: [], category: 'audio')]
    case AUDIO_MELP = 'AUDIO_MELP';

    #[MimeTypeInfo(mimeType: 'audio/melp1200', extensions: [], category: 'audio')]
    case AUDIO_MELP1200 = 'AUDIO_MELP1200';

    #[MimeTypeInfo(mimeType: 'audio/melp2400', extensions: [], category: 'audio')]
    case AUDIO_MELP2400 = 'AUDIO_MELP2400';

    #[MimeTypeInfo(mimeType: 'audio/melp600', extensions: [], category: 'audio')]
    case AUDIO_MELP600 = 'AUDIO_MELP600';

    #[MimeTypeInfo(mimeType: 'audio/mhas', extensions: ['mhas'], category: 'audio')]
    case AUDIO_MHAS = 'AUDIO_MHAS';

    #[MimeTypeInfo(mimeType: 'audio/midi', extensions: ['mid', 'midi', 'kar', 'rmi'], category: 'audio')]
    case AUDIO_MIDI = 'AUDIO_MIDI';

    #[MimeTypeInfo(mimeType: 'audio/midi-clip', extensions: ['midi2'], category: 'audio')]
    case AUDIO_MIDI_CLIP = 'AUDIO_MIDI_CLIP';

    #[MimeTypeInfo(mimeType: 'audio/mobile-xmf', extensions: ['mxmf'], category: 'audio')]
    case AUDIO_MOBILE_XMF = 'AUDIO_MOBILE_XMF';

    #[MimeTypeInfo(mimeType: 'audio/mp3', extensions: ['mp3'], category: 'audio')]
    case AUDIO_MP3 = 'AUDIO_MP3';

    #[MimeTypeInfo(mimeType: 'audio/mp4', extensions: ['m4a', 'mp4a', 'm4b', 'mp4', 'mpg4'], category: 'audio')]
    case AUDIO_MP4 = 'AUDIO_MP4';

    #[MimeTypeInfo(mimeType: 'audio/mp4a-latm', extensions: [], category: 'audio')]
    case AUDIO_MP4A_LATM = 'AUDIO_MP4A_LATM';

    #[MimeTypeInfo(mimeType: 'audio/mpa', extensions: [], category: 'audio')]
    case AUDIO_MPA = 'AUDIO_MPA';

    #[MimeTypeInfo(mimeType: 'audio/mpa-robust', extensions: [], category: 'audio')]
    case AUDIO_MPA_ROBUST = 'AUDIO_MPA_ROBUST';

    #[MimeTypeInfo(mimeType: 'audio/mpeg', extensions: ['mpga', 'mp2', 'mp2a', 'mp3', 'm2a', 'm3a', 'mp1'], category: 'audio')]
    case AUDIO_MPEG = 'AUDIO_MPEG';

    #[MimeTypeInfo(mimeType: 'audio/mpeg4-generic', extensions: [], category: 'audio')]
    case AUDIO_MPEG4_GENERIC = 'AUDIO_MPEG4_GENERIC';

    #[MimeTypeInfo(mimeType: 'audio/musepack', extensions: [], category: 'audio')]
    case AUDIO_MUSEPACK = 'AUDIO_MUSEPACK';

    #[MimeTypeInfo(mimeType: 'audio/ogg', extensions: ['oga', 'ogg', 'spx', 'opus'], category: 'audio')]
    case AUDIO_OGG = 'AUDIO_OGG';

    #[MimeTypeInfo(mimeType: 'audio/opus', extensions: ['opus'], category: 'audio')]
    case AUDIO_OPUS = 'AUDIO_OPUS';

    #[MimeTypeInfo(mimeType: 'audio/parityfec', extensions: [], category: 'audio')]
    case AUDIO_PARITYFEC = 'AUDIO_PARITYFEC';

    #[MimeTypeInfo(mimeType: 'audio/pcma', extensions: [], category: 'audio')]
    case AUDIO_PCMA = 'AUDIO_PCMA';

    #[MimeTypeInfo(mimeType: 'audio/pcma-wb', extensions: [], category: 'audio')]
    case AUDIO_PCMA_WB = 'AUDIO_PCMA_WB';

    #[MimeTypeInfo(mimeType: 'audio/pcmu', extensions: [], category: 'audio')]
    case AUDIO_PCMU = 'AUDIO_PCMU';

    #[MimeTypeInfo(mimeType: 'audio/pcmu-wb', extensions: [], category: 'audio')]
    case AUDIO_PCMU_WB = 'AUDIO_PCMU_WB';

    #[MimeTypeInfo(mimeType: 'audio/prs.sid', extensions: ['sid', 'psid'], category: 'audio')]
    case AUDIO_PRS_SID = 'AUDIO_PRS_SID';

    #[MimeTypeInfo(mimeType: 'audio/qcelp', extensions: [], category: 'audio')]
    case AUDIO_QCELP = 'AUDIO_QCELP';

    #[MimeTypeInfo(mimeType: 'audio/raptorfec', extensions: [], category: 'audio')]
    case AUDIO_RAPTORFEC = 'AUDIO_RAPTORFEC';

    #[MimeTypeInfo(mimeType: 'audio/red', extensions: [], category: 'audio')]
    case AUDIO_RED = 'AUDIO_RED';

    #[MimeTypeInfo(mimeType: 'audio/rtp-enc-aescm128', extensions: [], category: 'audio')]
    case AUDIO_RTP_ENC_AESCM128 = 'AUDIO_RTP_ENC_AESCM128';

    #[MimeTypeInfo(mimeType: 'audio/rtp-midi', extensions: [], category: 'audio')]
    case AUDIO_RTP_MIDI = 'AUDIO_RTP_MIDI';

    #[MimeTypeInfo(mimeType: 'audio/rtploopback', extensions: [], category: 'audio')]
    case AUDIO_RTPLOOPBACK = 'AUDIO_RTPLOOPBACK';

    #[MimeTypeInfo(mimeType: 'audio/rtx', extensions: [], category: 'audio')]
    case AUDIO_RTX = 'AUDIO_RTX';

    #[MimeTypeInfo(mimeType: 'audio/s3m', extensions: ['s3m'], category: 'audio')]
    case AUDIO_S3M = 'AUDIO_S3M';

    #[MimeTypeInfo(mimeType: 'audio/scip', extensions: [], category: 'audio')]
    case AUDIO_SCIP = 'AUDIO_SCIP';

    #[MimeTypeInfo(mimeType: 'audio/silk', extensions: ['sil'], category: 'audio')]
    case AUDIO_SILK = 'AUDIO_SILK';

    #[MimeTypeInfo(mimeType: 'audio/smv', extensions: ['smv'], category: 'audio')]
    case AUDIO_SMV = 'AUDIO_SMV';

    #[MimeTypeInfo(mimeType: 'audio/smv-qcp', extensions: ['qcp'], category: 'audio')]
    case AUDIO_SMV_QCP = 'AUDIO_SMV_QCP';

    #[MimeTypeInfo(mimeType: 'audio/smv0', extensions: [], category: 'audio')]
    case AUDIO_SMV0 = 'AUDIO_SMV0';

    #[MimeTypeInfo(mimeType: 'audio/sofa', extensions: ['sofa'], category: 'audio')]
    case AUDIO_SOFA = 'AUDIO_SOFA';

    #[MimeTypeInfo(mimeType: 'audio/sp-midi', extensions: ['mid'], category: 'audio')]
    case AUDIO_SP_MIDI = 'AUDIO_SP_MIDI';

    #[MimeTypeInfo(mimeType: 'audio/speex', extensions: [], category: 'audio')]
    case AUDIO_SPEEX = 'AUDIO_SPEEX';

    #[MimeTypeInfo(mimeType: 'audio/t140c', extensions: [], category: 'audio')]
    case AUDIO_T140C = 'AUDIO_T140C';

    #[MimeTypeInfo(mimeType: 'audio/t38', extensions: [], category: 'audio')]
    case AUDIO_T38 = 'AUDIO_T38';

    #[MimeTypeInfo(mimeType: 'audio/telephone-event', extensions: [], category: 'audio')]
    case AUDIO_TELEPHONE_EVENT = 'AUDIO_TELEPHONE_EVENT';

    #[MimeTypeInfo(mimeType: 'audio/tetra_acelp', extensions: [], category: 'audio')]
    case AUDIO_TETRA_ACELP = 'AUDIO_TETRA_ACELP';

    #[MimeTypeInfo(mimeType: 'audio/tetra_acelp_bb', extensions: [], category: 'audio')]
    case AUDIO_TETRA_ACELP_BB = 'AUDIO_TETRA_ACELP_BB';

    #[MimeTypeInfo(mimeType: 'audio/tone', extensions: [], category: 'audio')]
    case AUDIO_TONE = 'AUDIO_TONE';

    #[MimeTypeInfo(mimeType: 'audio/tsvcis', extensions: [], category: 'audio')]
    case AUDIO_TSVCIS = 'AUDIO_TSVCIS';

    #[MimeTypeInfo(mimeType: 'audio/uemclip', extensions: [], category: 'audio')]
    case AUDIO_UEMCLIP = 'AUDIO_UEMCLIP';

    #[MimeTypeInfo(mimeType: 'audio/ulpfec', extensions: [], category: 'audio')]
    case AUDIO_ULPFEC = 'AUDIO_ULPFEC';

    #[MimeTypeInfo(mimeType: 'audio/usac', extensions: ['loas', 'xhe'], category: 'audio')]
    case AUDIO_USAC = 'AUDIO_USAC';

    #[MimeTypeInfo(mimeType: 'audio/vdvi', extensions: [], category: 'audio')]
    case AUDIO_VDVI = 'AUDIO_VDVI';

    #[MimeTypeInfo(mimeType: 'audio/vmr-wb', extensions: [], category: 'audio')]
    case AUDIO_VMR_WB = 'AUDIO_VMR_WB';

    #[MimeTypeInfo(mimeType: 'audio/vnd.3gpp.iufp', extensions: [], category: 'audio')]
    case AUDIO_VND_3GPP_IUFP = 'AUDIO_VND_3GPP_IUFP';

    #[MimeTypeInfo(mimeType: 'audio/vnd.4sb', extensions: [], category: 'audio')]
    case AUDIO_VND_4SB = 'AUDIO_VND_4SB';

    #[MimeTypeInfo(mimeType: 'audio/vnd.audiokoz', extensions: ['koz'], category: 'audio')]
    case AUDIO_VND_AUDIOKOZ = 'AUDIO_VND_AUDIOKOZ';

    #[MimeTypeInfo(mimeType: 'audio/vnd.blockfact.facta', extensions: ['facta'], category: 'audio')]
    case AUDIO_VND_BLOCKFACT_FACTA = 'AUDIO_VND_BLOCKFACT_FACTA';

    #[MimeTypeInfo(mimeType: 'audio/vnd.celp', extensions: [], category: 'audio')]
    case AUDIO_VND_CELP = 'AUDIO_VND_CELP';

    #[MimeTypeInfo(mimeType: 'audio/vnd.cisco.nse', extensions: [], category: 'audio')]
    case AUDIO_VND_CISCO_NSE = 'AUDIO_VND_CISCO_NSE';

    #[MimeTypeInfo(mimeType: 'audio/vnd.cmles.radio-events', extensions: [], category: 'audio')]
    case AUDIO_VND_CMLES_RADIO_EVENTS = 'AUDIO_VND_CMLES_RADIO_EVENTS';

    #[MimeTypeInfo(mimeType: 'audio/vnd.cns.anp1', extensions: [], category: 'audio')]
    case AUDIO_VND_CNS_ANP1 = 'AUDIO_VND_CNS_ANP1';

    #[MimeTypeInfo(mimeType: 'audio/vnd.cns.inf1', extensions: [], category: 'audio')]
    case AUDIO_VND_CNS_INF1 = 'AUDIO_VND_CNS_INF1';

    #[MimeTypeInfo(mimeType: 'audio/vnd.dece.audio', extensions: ['uva', 'uvva'], category: 'audio')]
    case AUDIO_VND_DECE_AUDIO = 'AUDIO_VND_DECE_AUDIO';

    #[MimeTypeInfo(mimeType: 'audio/vnd.digital-winds', extensions: ['eol'], category: 'audio')]
    case AUDIO_VND_DIGITAL_WINDS = 'AUDIO_VND_DIGITAL_WINDS';

    #[MimeTypeInfo(mimeType: 'audio/vnd.dlna.adts', extensions: [], category: 'audio')]
    case AUDIO_VND_DLNA_ADTS = 'AUDIO_VND_DLNA_ADTS';

    #[MimeTypeInfo(mimeType: 'audio/vnd.dolby.heaac.1', extensions: [], category: 'audio')]
    case AUDIO_VND_DOLBY_HEAAC_1 = 'AUDIO_VND_DOLBY_HEAAC_1';

    #[MimeTypeInfo(mimeType: 'audio/vnd.dolby.heaac.2', extensions: [], category: 'audio')]
    case AUDIO_VND_DOLBY_HEAAC_2 = 'AUDIO_VND_DOLBY_HEAAC_2';

    #[MimeTypeInfo(mimeType: 'audio/vnd.dolby.mlp', extensions: ['mlp'], category: 'audio')]
    case AUDIO_VND_DOLBY_MLP = 'AUDIO_VND_DOLBY_MLP';

    #[MimeTypeInfo(mimeType: 'audio/vnd.dolby.mps', extensions: [], category: 'audio')]
    case AUDIO_VND_DOLBY_MPS = 'AUDIO_VND_DOLBY_MPS';

    #[MimeTypeInfo(mimeType: 'audio/vnd.dolby.pl2', extensions: [], category: 'audio')]
    case AUDIO_VND_DOLBY_PL2 = 'AUDIO_VND_DOLBY_PL2';

    #[MimeTypeInfo(mimeType: 'audio/vnd.dolby.pl2x', extensions: [], category: 'audio')]
    case AUDIO_VND_DOLBY_PL2X = 'AUDIO_VND_DOLBY_PL2X';

    #[MimeTypeInfo(mimeType: 'audio/vnd.dolby.pl2z', extensions: [], category: 'audio')]
    case AUDIO_VND_DOLBY_PL2Z = 'AUDIO_VND_DOLBY_PL2Z';

    #[MimeTypeInfo(mimeType: 'audio/vnd.dolby.pulse.1', extensions: [], category: 'audio')]
    case AUDIO_VND_DOLBY_PULSE_1 = 'AUDIO_VND_DOLBY_PULSE_1';

    #[MimeTypeInfo(mimeType: 'audio/vnd.dra', extensions: ['dra'], category: 'audio')]
    case AUDIO_VND_DRA = 'AUDIO_VND_DRA';

    #[MimeTypeInfo(mimeType: 'audio/vnd.dts', extensions: ['dts', 'wav', 'cpt'], category: 'audio')]
    case AUDIO_VND_DTS = 'AUDIO_VND_DTS';

    #[MimeTypeInfo(mimeType: 'audio/vnd.dts.hd', extensions: ['dtshd'], category: 'audio')]
    case AUDIO_VND_DTS_HD = 'AUDIO_VND_DTS_HD';

    #[MimeTypeInfo(mimeType: 'audio/vnd.dts.uhd', extensions: ['various', 'such'], category: 'audio')]
    case AUDIO_VND_DTS_UHD = 'AUDIO_VND_DTS_UHD';

    #[MimeTypeInfo(mimeType: 'audio/vnd.dvb.file', extensions: ['dvb'], category: 'audio')]
    case AUDIO_VND_DVB_FILE = 'AUDIO_VND_DVB_FILE';

    #[MimeTypeInfo(mimeType: 'audio/vnd.everad.plj', extensions: ['plj'], category: 'audio')]
    case AUDIO_VND_EVERAD_PLJ = 'AUDIO_VND_EVERAD_PLJ';

    #[MimeTypeInfo(mimeType: 'audio/vnd.hns.audio', extensions: ['rm'], category: 'audio')]
    case AUDIO_VND_HNS_AUDIO = 'AUDIO_VND_HNS_AUDIO';

    #[MimeTypeInfo(mimeType: 'audio/vnd.lucent.voice', extensions: ['lvp'], category: 'audio')]
    case AUDIO_VND_LUCENT_VOICE = 'AUDIO_VND_LUCENT_VOICE';

    #[MimeTypeInfo(mimeType: 'audio/vnd.ms-playready.media.pya', extensions: ['pya'], category: 'audio')]
    case AUDIO_VND_MS_PLAYREADY_MEDIA_PYA = 'AUDIO_VND_MS_PLAYREADY_MEDIA_PYA';

    #[MimeTypeInfo(mimeType: 'audio/vnd.nokia.mobile-xmf', extensions: ['mxmf'], category: 'audio')]
    case AUDIO_VND_NOKIA_MOBILE_XMF = 'AUDIO_VND_NOKIA_MOBILE_XMF';

    #[MimeTypeInfo(mimeType: 'audio/vnd.nortel.vbk', extensions: ['vbk'], category: 'audio')]
    case AUDIO_VND_NORTEL_VBK = 'AUDIO_VND_NORTEL_VBK';

    #[MimeTypeInfo(mimeType: 'audio/vnd.nuera.ecelp4800', extensions: ['ecelp4800'], category: 'audio')]
    case AUDIO_VND_NUERA_ECELP4800 = 'AUDIO_VND_NUERA_ECELP4800';

    #[MimeTypeInfo(mimeType: 'audio/vnd.nuera.ecelp7470', extensions: ['ecelp7470'], category: 'audio')]
    case AUDIO_VND_NUERA_ECELP7470 = 'AUDIO_VND_NUERA_ECELP7470';

    #[MimeTypeInfo(mimeType: 'audio/vnd.nuera.ecelp9600', extensions: ['ecelp9600'], category: 'audio')]
    case AUDIO_VND_NUERA_ECELP9600 = 'AUDIO_VND_NUERA_ECELP9600';

    #[MimeTypeInfo(mimeType: 'audio/vnd.octel.sbc', extensions: [], category: 'audio')]
    case AUDIO_VND_OCTEL_SBC = 'AUDIO_VND_OCTEL_SBC';

    #[MimeTypeInfo(mimeType: 'audio/vnd.presonus.multitrack', extensions: ['multitrack'], category: 'audio')]
    case AUDIO_VND_PRESONUS_MULTITRACK = 'AUDIO_VND_PRESONUS_MULTITRACK';

    #[MimeTypeInfo(mimeType: 'audio/vnd.qcelp', extensions: [], category: 'audio')]
    case AUDIO_VND_QCELP = 'AUDIO_VND_QCELP';

    #[MimeTypeInfo(mimeType: 'audio/vnd.rhetorex.32kadpcm', extensions: [], category: 'audio')]
    case AUDIO_VND_RHETOREX_32KADPCM = 'AUDIO_VND_RHETOREX_32KADPCM';

    #[MimeTypeInfo(mimeType: 'audio/vnd.rip', extensions: ['rip'], category: 'audio')]
    case AUDIO_VND_RIP = 'AUDIO_VND_RIP';

    #[MimeTypeInfo(mimeType: 'audio/vnd.rn-realaudio', extensions: [], category: 'audio')]
    case AUDIO_VND_RN_REALAUDIO = 'AUDIO_VND_RN_REALAUDIO';

    #[MimeTypeInfo(mimeType: 'audio/vnd.sealedmedia.softseal.mpeg', extensions: ['smp3', 'smp', 's1m'], category: 'audio')]
    case AUDIO_VND_SEALEDMEDIA_SOFTSEAL_MPEG = 'AUDIO_VND_SEALEDMEDIA_SOFTSEAL_MPEG';

    #[MimeTypeInfo(mimeType: 'audio/vnd.vmx.cvsd', extensions: [], category: 'audio')]
    case AUDIO_VND_VMX_CVSD = 'AUDIO_VND_VMX_CVSD';

    #[MimeTypeInfo(mimeType: 'audio/vnd.wave', extensions: [], category: 'audio')]
    case AUDIO_VND_WAVE = 'AUDIO_VND_WAVE';

    #[MimeTypeInfo(mimeType: 'audio/vorbis', extensions: [], category: 'audio')]
    case AUDIO_VORBIS = 'AUDIO_VORBIS';

    #[MimeTypeInfo(mimeType: 'audio/vorbis-config', extensions: [], category: 'audio')]
    case AUDIO_VORBIS_CONFIG = 'AUDIO_VORBIS_CONFIG';

    #[MimeTypeInfo(mimeType: 'audio/wav', extensions: ['wav'], category: 'audio')]
    case AUDIO_WAV = 'AUDIO_WAV';

    #[MimeTypeInfo(mimeType: 'audio/wave', extensions: ['wav'], category: 'audio')]
    case AUDIO_WAVE = 'AUDIO_WAVE';

    #[MimeTypeInfo(mimeType: 'audio/webm', extensions: ['weba'], category: 'audio')]
    case AUDIO_WEBM = 'AUDIO_WEBM';

    #[MimeTypeInfo(mimeType: 'audio/x-aac', extensions: ['aac'], category: 'audio')]
    case AUDIO_X_AAC = 'AUDIO_X_AAC';

    #[MimeTypeInfo(mimeType: 'audio/x-aiff', extensions: ['aif', 'aiff', 'aifc'], category: 'audio')]
    case AUDIO_X_AIFF = 'AUDIO_X_AIFF';

    #[MimeTypeInfo(mimeType: 'audio/x-caf', extensions: ['caf'], category: 'audio')]
    case AUDIO_X_CAF = 'AUDIO_X_CAF';

    #[MimeTypeInfo(mimeType: 'audio/x-flac', extensions: ['flac'], category: 'audio')]
    case AUDIO_X_FLAC = 'AUDIO_X_FLAC';

    #[MimeTypeInfo(mimeType: 'audio/x-m4a', extensions: ['m4a'], category: 'audio')]
    case AUDIO_X_M4A = 'AUDIO_X_M4A';

    #[MimeTypeInfo(mimeType: 'audio/x-matroska', extensions: ['mka'], category: 'audio')]
    case AUDIO_X_MATROSKA = 'AUDIO_X_MATROSKA';

    #[MimeTypeInfo(mimeType: 'audio/x-mpegurl', extensions: ['m3u'], category: 'audio')]
    case AUDIO_X_MPEGURL = 'AUDIO_X_MPEGURL';

    #[MimeTypeInfo(mimeType: 'audio/x-ms-wax', extensions: ['wax'], category: 'audio')]
    case AUDIO_X_MS_WAX = 'AUDIO_X_MS_WAX';

    #[MimeTypeInfo(mimeType: 'audio/x-ms-wma', extensions: ['wma'], category: 'audio')]
    case AUDIO_X_MS_WMA = 'AUDIO_X_MS_WMA';

    #[MimeTypeInfo(mimeType: 'audio/x-pn-realaudio', extensions: ['ram', 'ra'], category: 'audio')]
    case AUDIO_X_PN_REALAUDIO = 'AUDIO_X_PN_REALAUDIO';

    #[MimeTypeInfo(mimeType: 'audio/x-pn-realaudio-plugin', extensions: ['rmp'], category: 'audio')]
    case AUDIO_X_PN_REALAUDIO_PLUGIN = 'AUDIO_X_PN_REALAUDIO_PLUGIN';

    #[MimeTypeInfo(mimeType: 'audio/x-realaudio', extensions: ['ra'], category: 'audio')]
    case AUDIO_X_REALAUDIO = 'AUDIO_X_REALAUDIO';

    #[MimeTypeInfo(mimeType: 'audio/x-tta', extensions: [], category: 'audio')]
    case AUDIO_X_TTA = 'AUDIO_X_TTA';

    #[MimeTypeInfo(mimeType: 'audio/x-wav', extensions: ['wav'], category: 'audio')]
    case AUDIO_X_WAV = 'AUDIO_X_WAV';

    #[MimeTypeInfo(mimeType: 'audio/xm', extensions: ['xm'], category: 'audio')]
    case AUDIO_XM = 'AUDIO_XM';

    #[MimeTypeInfo(mimeType: 'chemical/x-cdx', extensions: ['cdx'], category: 'chemical')]
    case CHEMICAL_X_CDX = 'CHEMICAL_X_CDX';

    #[MimeTypeInfo(mimeType: 'chemical/x-cif', extensions: ['cif'], category: 'chemical')]
    case CHEMICAL_X_CIF = 'CHEMICAL_X_CIF';

    #[MimeTypeInfo(mimeType: 'chemical/x-cmdf', extensions: ['cmdf'], category: 'chemical')]
    case CHEMICAL_X_CMDF = 'CHEMICAL_X_CMDF';

    #[MimeTypeInfo(mimeType: 'chemical/x-cml', extensions: ['cml'], category: 'chemical')]
    case CHEMICAL_X_CML = 'CHEMICAL_X_CML';

    #[MimeTypeInfo(mimeType: 'chemical/x-csml', extensions: ['csml'], category: 'chemical')]
    case CHEMICAL_X_CSML = 'CHEMICAL_X_CSML';

    #[MimeTypeInfo(mimeType: 'chemical/x-pdb', extensions: [], category: 'chemical')]
    case CHEMICAL_X_PDB = 'CHEMICAL_X_PDB';

    #[MimeTypeInfo(mimeType: 'chemical/x-xyz', extensions: ['xyz'], category: 'chemical')]
    case CHEMICAL_X_XYZ = 'CHEMICAL_X_XYZ';

    #[MimeTypeInfo(mimeType: 'font/collection', extensions: ['ttc', 'font'], category: 'font')]
    case FONT_COLLECTION = 'FONT_COLLECTION';

    #[MimeTypeInfo(mimeType: 'font/otf', extensions: ['otf', 'font'], category: 'font')]
    case FONT_OTF = 'FONT_OTF';

    #[MimeTypeInfo(mimeType: 'font/sfnt', extensions: ['font'], category: 'font')]
    case FONT_SFNT = 'FONT_SFNT';

    #[MimeTypeInfo(mimeType: 'font/ttf', extensions: ['ttf', 'font'], category: 'font')]
    case FONT_TTF = 'FONT_TTF';

    #[MimeTypeInfo(mimeType: 'font/woff', extensions: ['woff'], category: 'font')]
    case FONT_WOFF = 'FONT_WOFF';

    #[MimeTypeInfo(mimeType: 'font/woff2', extensions: ['woff2'], category: 'font')]
    case FONT_WOFF2 = 'FONT_WOFF2';

    #[MimeTypeInfo(mimeType: 'haptics/hjif', extensions: ['haptic'], category: 'haptics')]
    case HAPTICS_HJIF = 'HAPTICS_HJIF';

    #[MimeTypeInfo(mimeType: 'haptics/hmpg', extensions: ['haptic'], category: 'haptics')]
    case HAPTICS_HMPG = 'HAPTICS_HMPG';

    #[MimeTypeInfo(mimeType: 'haptics/ivs', extensions: ['haptic'], category: 'haptics')]
    case HAPTICS_IVS = 'HAPTICS_IVS';

    #[MimeTypeInfo(mimeType: 'image/aces', extensions: ['exr'], category: 'image')]
    case IMAGE_ACES = 'IMAGE_ACES';

    #[MimeTypeInfo(mimeType: 'image/apng', extensions: ['apng'], category: 'image')]
    case IMAGE_APNG = 'IMAGE_APNG';

    #[MimeTypeInfo(mimeType: 'image/avci', extensions: ['avci'], category: 'image')]
    case IMAGE_AVCI = 'IMAGE_AVCI';

    #[MimeTypeInfo(mimeType: 'image/avcs', extensions: ['avcs'], category: 'image')]
    case IMAGE_AVCS = 'IMAGE_AVCS';

    #[MimeTypeInfo(mimeType: 'image/avif', extensions: ['avif', 'heif', 'heifs', 'hif'], category: 'image')]
    case IMAGE_AVIF = 'IMAGE_AVIF';

    #[MimeTypeInfo(mimeType: 'image/bmp', extensions: ['bmp', 'dib'], category: 'image')]
    case IMAGE_BMP = 'IMAGE_BMP';

    #[MimeTypeInfo(mimeType: 'image/cgm', extensions: ['cgm'], category: 'image')]
    case IMAGE_CGM = 'IMAGE_CGM';

    #[MimeTypeInfo(mimeType: 'image/dicom-rle', extensions: ['drle'], category: 'image')]
    case IMAGE_DICOM_RLE = 'IMAGE_DICOM_RLE';

    #[MimeTypeInfo(mimeType: 'image/dpx', extensions: ['dpx'], category: 'image')]
    case IMAGE_DPX = 'IMAGE_DPX';

    #[MimeTypeInfo(mimeType: 'image/emf', extensions: ['emf'], category: 'image')]
    case IMAGE_EMF = 'IMAGE_EMF';

    #[MimeTypeInfo(mimeType: 'image/example', extensions: [], category: 'image')]
    case IMAGE_EXAMPLE = 'IMAGE_EXAMPLE';

    #[MimeTypeInfo(mimeType: 'image/fits', extensions: ['fits'], category: 'image')]
    case IMAGE_FITS = 'IMAGE_FITS';

    #[MimeTypeInfo(mimeType: 'image/g3fax', extensions: ['g3'], category: 'image')]
    case IMAGE_G3FAX = 'IMAGE_G3FAX';

    #[MimeTypeInfo(mimeType: 'image/gif', extensions: ['gif'], category: 'image')]
    case IMAGE_GIF = 'IMAGE_GIF';

    #[MimeTypeInfo(mimeType: 'image/heic', extensions: ['heif', 'heic', 'hif'], category: 'image')]
    case IMAGE_HEIC = 'IMAGE_HEIC';

    #[MimeTypeInfo(mimeType: 'image/heic-sequence', extensions: ['heifs', 'heics', 'hif'], category: 'image')]
    case IMAGE_HEIC_SEQUENCE = 'IMAGE_HEIC_SEQUENCE';

    #[MimeTypeInfo(mimeType: 'image/heif', extensions: ['heif', 'heic'], category: 'image')]
    case IMAGE_HEIF = 'IMAGE_HEIF';

    #[MimeTypeInfo(mimeType: 'image/heif-sequence', extensions: ['heifs', 'heics'], category: 'image')]
    case IMAGE_HEIF_SEQUENCE = 'IMAGE_HEIF_SEQUENCE';

    #[MimeTypeInfo(mimeType: 'image/hej2k', extensions: ['hej2'], category: 'image')]
    case IMAGE_HEJ2K = 'IMAGE_HEJ2K';

    #[MimeTypeInfo(mimeType: 'image/ief', extensions: ['ief'], category: 'image')]
    case IMAGE_IEF = 'IMAGE_IEF';

    #[MimeTypeInfo(mimeType: 'image/j2c', extensions: ['j2c', 'j2k'], category: 'image')]
    case IMAGE_J2C = 'IMAGE_J2C';

    #[MimeTypeInfo(mimeType: 'image/jaii', extensions: ['jaii'], category: 'image')]
    case IMAGE_JAII = 'IMAGE_JAII';

    #[MimeTypeInfo(mimeType: 'image/jais', extensions: ['jais'], category: 'image')]
    case IMAGE_JAIS = 'IMAGE_JAIS';

    #[MimeTypeInfo(mimeType: 'image/jls', extensions: ['jls'], category: 'image')]
    case IMAGE_JLS = 'IMAGE_JLS';

    #[MimeTypeInfo(mimeType: 'image/jp2', extensions: ['jp2', 'jpg2'], category: 'image')]
    case IMAGE_JP2 = 'IMAGE_JP2';

    #[MimeTypeInfo(mimeType: 'image/jpeg', extensions: ['jpg', 'jpeg', 'jpe'], category: 'image')]
    case IMAGE_JPEG = 'IMAGE_JPEG';

    #[MimeTypeInfo(mimeType: 'image/jph', extensions: ['jph'], category: 'image')]
    case IMAGE_JPH = 'IMAGE_JPH';

    #[MimeTypeInfo(mimeType: 'image/jphc', extensions: ['jhc'], category: 'image')]
    case IMAGE_JPHC = 'IMAGE_JPHC';

    #[MimeTypeInfo(mimeType: 'image/jpm', extensions: ['jpm', 'jpgm'], category: 'image')]
    case IMAGE_JPM = 'IMAGE_JPM';

    #[MimeTypeInfo(mimeType: 'image/jpx', extensions: ['jpx', 'jpf'], category: 'image')]
    case IMAGE_JPX = 'IMAGE_JPX';

    #[MimeTypeInfo(mimeType: 'image/jxl', extensions: ['jxl'], category: 'image')]
    case IMAGE_JXL = 'IMAGE_JXL';

    #[MimeTypeInfo(mimeType: 'image/jxr', extensions: ['jxr'], category: 'image')]
    case IMAGE_JXR = 'IMAGE_JXR';

    #[MimeTypeInfo(mimeType: 'image/jxra', extensions: ['jxra'], category: 'image')]
    case IMAGE_JXRA = 'IMAGE_JXRA';

    #[MimeTypeInfo(mimeType: 'image/jxrs', extensions: ['jxrs'], category: 'image')]
    case IMAGE_JXRS = 'IMAGE_JXRS';

    #[MimeTypeInfo(mimeType: 'image/jxs', extensions: ['jxs'], category: 'image')]
    case IMAGE_JXS = 'IMAGE_JXS';

    #[MimeTypeInfo(mimeType: 'image/jxsc', extensions: ['jxsc'], category: 'image')]
    case IMAGE_JXSC = 'IMAGE_JXSC';

    #[MimeTypeInfo(mimeType: 'image/jxsi', extensions: ['jxsi'], category: 'image')]
    case IMAGE_JXSI = 'IMAGE_JXSI';

    #[MimeTypeInfo(mimeType: 'image/jxss', extensions: ['jxss'], category: 'image')]
    case IMAGE_JXSS = 'IMAGE_JXSS';

    #[MimeTypeInfo(mimeType: 'image/ktx', extensions: ['ktx'], category: 'image')]
    case IMAGE_KTX = 'IMAGE_KTX';

    #[MimeTypeInfo(mimeType: 'image/ktx2', extensions: ['ktx2'], category: 'image')]
    case IMAGE_KTX2 = 'IMAGE_KTX2';

    #[MimeTypeInfo(mimeType: 'image/naplps', extensions: [], category: 'image')]
    case IMAGE_NAPLPS = 'IMAGE_NAPLPS';

    #[MimeTypeInfo(mimeType: 'image/pjpeg', extensions: ['jfif'], category: 'image')]
    case IMAGE_PJPEG = 'IMAGE_PJPEG';

    #[MimeTypeInfo(mimeType: 'image/png', extensions: ['png'], category: 'image')]
    case IMAGE_PNG = 'IMAGE_PNG';

    #[MimeTypeInfo(mimeType: 'image/prs.btif', extensions: ['btif', 'btf'], category: 'image')]
    case IMAGE_PRS_BTIF = 'IMAGE_PRS_BTIF';

    #[MimeTypeInfo(mimeType: 'image/prs.pti', extensions: ['pti'], category: 'image')]
    case IMAGE_PRS_PTI = 'IMAGE_PRS_PTI';

    #[MimeTypeInfo(mimeType: 'image/pwg-raster', extensions: [], category: 'image')]
    case IMAGE_PWG_RASTER = 'IMAGE_PWG_RASTER';

    #[MimeTypeInfo(mimeType: 'image/sgi', extensions: ['sgi'], category: 'image')]
    case IMAGE_SGI = 'IMAGE_SGI';

    #[MimeTypeInfo(mimeType: 'image/svg+xml', extensions: ['svg', 'svgz'], category: 'image')]
    case IMAGE_SVG_XML = 'IMAGE_SVG_XML';

    #[MimeTypeInfo(mimeType: 'image/t38', extensions: ['t38'], category: 'image')]
    case IMAGE_T38 = 'IMAGE_T38';

    #[MimeTypeInfo(mimeType: 'image/tiff', extensions: ['tif', 'tiff'], category: 'image')]
    case IMAGE_TIFF = 'IMAGE_TIFF';

    #[MimeTypeInfo(mimeType: 'image/tiff-fx', extensions: ['tfx'], category: 'image')]
    case IMAGE_TIFF_FX = 'IMAGE_TIFF_FX';

    #[MimeTypeInfo(mimeType: 'image/vnd.adobe.photoshop', extensions: ['psd'], category: 'image')]
    case IMAGE_VND_ADOBE_PHOTOSHOP = 'IMAGE_VND_ADOBE_PHOTOSHOP';

    #[MimeTypeInfo(mimeType: 'image/vnd.airzip.accelerator.azv', extensions: ['azv'], category: 'image')]
    case IMAGE_VND_AIRZIP_ACCELERATOR_AZV = 'IMAGE_VND_AIRZIP_ACCELERATOR_AZV';

    #[MimeTypeInfo(mimeType: 'image/vnd.blockfact.facti', extensions: ['facti'], category: 'image')]
    case IMAGE_VND_BLOCKFACT_FACTI = 'IMAGE_VND_BLOCKFACT_FACTI';

    #[MimeTypeInfo(mimeType: 'image/vnd.clip', extensions: ['clip'], category: 'image')]
    case IMAGE_VND_CLIP = 'IMAGE_VND_CLIP';

    #[MimeTypeInfo(mimeType: 'image/vnd.cns.inf2', extensions: [], category: 'image')]
    case IMAGE_VND_CNS_INF2 = 'IMAGE_VND_CNS_INF2';

    #[MimeTypeInfo(mimeType: 'image/vnd.dece.graphic', extensions: ['uvi', 'uvvi', 'uvg', 'uvvg'], category: 'image')]
    case IMAGE_VND_DECE_GRAPHIC = 'IMAGE_VND_DECE_GRAPHIC';

    #[MimeTypeInfo(mimeType: 'image/vnd.djvu', extensions: ['djvu', 'djv'], category: 'image')]
    case IMAGE_VND_DJVU = 'IMAGE_VND_DJVU';

    #[MimeTypeInfo(mimeType: 'image/vnd.dvb.subtitle', extensions: ['sub'], category: 'image')]
    case IMAGE_VND_DVB_SUBTITLE = 'IMAGE_VND_DVB_SUBTITLE';

    #[MimeTypeInfo(mimeType: 'image/vnd.dwg', extensions: ['dwg'], category: 'image')]
    case IMAGE_VND_DWG = 'IMAGE_VND_DWG';

    #[MimeTypeInfo(mimeType: 'image/vnd.dxf', extensions: ['dxf'], category: 'image')]
    case IMAGE_VND_DXF = 'IMAGE_VND_DXF';

    #[MimeTypeInfo(mimeType: 'image/vnd.fastbidsheet', extensions: ['fbs'], category: 'image')]
    case IMAGE_VND_FASTBIDSHEET = 'IMAGE_VND_FASTBIDSHEET';

    #[MimeTypeInfo(mimeType: 'image/vnd.fpx', extensions: ['fpx'], category: 'image')]
    case IMAGE_VND_FPX = 'IMAGE_VND_FPX';

    #[MimeTypeInfo(mimeType: 'image/vnd.fst', extensions: ['fst'], category: 'image')]
    case IMAGE_VND_FST = 'IMAGE_VND_FST';

    #[MimeTypeInfo(mimeType: 'image/vnd.fujixerox.edmics-mmr', extensions: ['mmr'], category: 'image')]
    case IMAGE_VND_FUJIXEROX_EDMICS_MMR = 'IMAGE_VND_FUJIXEROX_EDMICS_MMR';

    #[MimeTypeInfo(mimeType: 'image/vnd.fujixerox.edmics-rlc', extensions: ['rlc'], category: 'image')]
    case IMAGE_VND_FUJIXEROX_EDMICS_RLC = 'IMAGE_VND_FUJIXEROX_EDMICS_RLC';

    #[MimeTypeInfo(mimeType: 'image/vnd.globalgraphics.pgb', extensions: ['pgb'], category: 'image')]
    case IMAGE_VND_GLOBALGRAPHICS_PGB = 'IMAGE_VND_GLOBALGRAPHICS_PGB';

    #[MimeTypeInfo(mimeType: 'image/vnd.microsoft.icon', extensions: ['ico'], category: 'image')]
    case IMAGE_VND_MICROSOFT_ICON = 'IMAGE_VND_MICROSOFT_ICON';

    #[MimeTypeInfo(mimeType: 'image/vnd.mix', extensions: [], category: 'image')]
    case IMAGE_VND_MIX = 'IMAGE_VND_MIX';

    #[MimeTypeInfo(mimeType: 'image/vnd.mozilla.apng', extensions: ['apng', 'png'], category: 'image')]
    case IMAGE_VND_MOZILLA_APNG = 'IMAGE_VND_MOZILLA_APNG';

    #[MimeTypeInfo(mimeType: 'image/vnd.ms-dds', extensions: ['dds'], category: 'image')]
    case IMAGE_VND_MS_DDS = 'IMAGE_VND_MS_DDS';

    #[MimeTypeInfo(mimeType: 'image/vnd.ms-modi', extensions: ['mdi'], category: 'image')]
    case IMAGE_VND_MS_MODI = 'IMAGE_VND_MS_MODI';

    #[MimeTypeInfo(mimeType: 'image/vnd.ms-photo', extensions: ['wdp'], category: 'image')]
    case IMAGE_VND_MS_PHOTO = 'IMAGE_VND_MS_PHOTO';

    #[MimeTypeInfo(mimeType: 'image/vnd.net-fpx', extensions: ['npx'], category: 'image')]
    case IMAGE_VND_NET_FPX = 'IMAGE_VND_NET_FPX';

    #[MimeTypeInfo(mimeType: 'image/vnd.pco.b16', extensions: ['b16'], category: 'image')]
    case IMAGE_VND_PCO_B16 = 'IMAGE_VND_PCO_B16';

    #[MimeTypeInfo(mimeType: 'image/vnd.radiance', extensions: ['pic', 'hdr', 'rgbe', 'xyze'], category: 'image')]
    case IMAGE_VND_RADIANCE = 'IMAGE_VND_RADIANCE';

    #[MimeTypeInfo(mimeType: 'image/vnd.sealed.png', extensions: ['spng', 'spn', 's1n'], category: 'image')]
    case IMAGE_VND_SEALED_PNG = 'IMAGE_VND_SEALED_PNG';

    #[MimeTypeInfo(mimeType: 'image/vnd.sealedmedia.softseal.gif', extensions: ['sgif', 'sgi', 's1g'], category: 'image')]
    case IMAGE_VND_SEALEDMEDIA_SOFTSEAL_GIF = 'IMAGE_VND_SEALEDMEDIA_SOFTSEAL_GIF';

    #[MimeTypeInfo(mimeType: 'image/vnd.sealedmedia.softseal.jpg', extensions: ['sjpg', 'sjp', 's1j'], category: 'image')]
    case IMAGE_VND_SEALEDMEDIA_SOFTSEAL_JPG = 'IMAGE_VND_SEALEDMEDIA_SOFTSEAL_JPG';

    #[MimeTypeInfo(mimeType: 'image/vnd.svf', extensions: ['dxf'], category: 'image')]
    case IMAGE_VND_SVF = 'IMAGE_VND_SVF';

    #[MimeTypeInfo(mimeType: 'image/vnd.tencent.tap', extensions: ['tap'], category: 'image')]
    case IMAGE_VND_TENCENT_TAP = 'IMAGE_VND_TENCENT_TAP';

    #[MimeTypeInfo(mimeType: 'image/vnd.valve.source.texture', extensions: ['vtf'], category: 'image')]
    case IMAGE_VND_VALVE_SOURCE_TEXTURE = 'IMAGE_VND_VALVE_SOURCE_TEXTURE';

    #[MimeTypeInfo(mimeType: 'image/vnd.wap.wbmp', extensions: ['wbmp'], category: 'image')]
    case IMAGE_VND_WAP_WBMP = 'IMAGE_VND_WAP_WBMP';

    #[MimeTypeInfo(mimeType: 'image/vnd.xiff', extensions: ['xif'], category: 'image')]
    case IMAGE_VND_XIFF = 'IMAGE_VND_XIFF';

    #[MimeTypeInfo(mimeType: 'image/vnd.zbrush.pcx', extensions: ['pcx'], category: 'image')]
    case IMAGE_VND_ZBRUSH_PCX = 'IMAGE_VND_ZBRUSH_PCX';

    #[MimeTypeInfo(mimeType: 'image/webp', extensions: ['webp'], category: 'image')]
    case IMAGE_WEBP = 'IMAGE_WEBP';

    #[MimeTypeInfo(mimeType: 'image/wmf', extensions: ['wmf'], category: 'image')]
    case IMAGE_WMF = 'IMAGE_WMF';

    #[MimeTypeInfo(mimeType: 'image/x-3ds', extensions: ['3ds'], category: 'image')]
    case IMAGE_X_3DS = 'IMAGE_X_3DS';

    #[MimeTypeInfo(mimeType: 'image/x-adobe-dng', extensions: ['dng'], category: 'image')]
    case IMAGE_X_ADOBE_DNG = 'IMAGE_X_ADOBE_DNG';

    #[MimeTypeInfo(mimeType: 'image/x-cmu-raster', extensions: ['ras'], category: 'image')]
    case IMAGE_X_CMU_RASTER = 'IMAGE_X_CMU_RASTER';

    #[MimeTypeInfo(mimeType: 'image/x-cmx', extensions: ['cmx'], category: 'image')]
    case IMAGE_X_CMX = 'IMAGE_X_CMX';

    #[MimeTypeInfo(mimeType: 'image/x-emf', extensions: [], category: 'image')]
    case IMAGE_X_EMF = 'IMAGE_X_EMF';

    #[MimeTypeInfo(mimeType: 'image/x-freehand', extensions: ['fh', 'fhc', 'fh4', 'fh5', 'fh7'], category: 'image')]
    case IMAGE_X_FREEHAND = 'IMAGE_X_FREEHAND';

    #[MimeTypeInfo(mimeType: 'image/x-icon', extensions: ['ico'], category: 'image')]
    case IMAGE_X_ICON = 'IMAGE_X_ICON';

    #[MimeTypeInfo(mimeType: 'image/x-jng', extensions: ['jng'], category: 'image')]
    case IMAGE_X_JNG = 'IMAGE_X_JNG';

    #[MimeTypeInfo(mimeType: 'image/x-mrsid-image', extensions: ['sid'], category: 'image')]
    case IMAGE_X_MRSID_IMAGE = 'IMAGE_X_MRSID_IMAGE';

    #[MimeTypeInfo(mimeType: 'image/x-ms-bmp', extensions: ['bmp'], category: 'image')]
    case IMAGE_X_MS_BMP = 'IMAGE_X_MS_BMP';

    #[MimeTypeInfo(mimeType: 'image/x-pcx', extensions: ['pcx'], category: 'image')]
    case IMAGE_X_PCX = 'IMAGE_X_PCX';

    #[MimeTypeInfo(mimeType: 'image/x-pict', extensions: ['pic', 'pct'], category: 'image')]
    case IMAGE_X_PICT = 'IMAGE_X_PICT';

    #[MimeTypeInfo(mimeType: 'image/x-portable-anymap', extensions: ['pnm'], category: 'image')]
    case IMAGE_X_PORTABLE_ANYMAP = 'IMAGE_X_PORTABLE_ANYMAP';

    #[MimeTypeInfo(mimeType: 'image/x-portable-bitmap', extensions: ['pbm'], category: 'image')]
    case IMAGE_X_PORTABLE_BITMAP = 'IMAGE_X_PORTABLE_BITMAP';

    #[MimeTypeInfo(mimeType: 'image/x-portable-graymap', extensions: ['pgm'], category: 'image')]
    case IMAGE_X_PORTABLE_GRAYMAP = 'IMAGE_X_PORTABLE_GRAYMAP';

    #[MimeTypeInfo(mimeType: 'image/x-portable-pixmap', extensions: ['ppm'], category: 'image')]
    case IMAGE_X_PORTABLE_PIXMAP = 'IMAGE_X_PORTABLE_PIXMAP';

    #[MimeTypeInfo(mimeType: 'image/x-rgb', extensions: ['rgb'], category: 'image')]
    case IMAGE_X_RGB = 'IMAGE_X_RGB';

    #[MimeTypeInfo(mimeType: 'image/x-tga', extensions: ['tga'], category: 'image')]
    case IMAGE_X_TGA = 'IMAGE_X_TGA';

    #[MimeTypeInfo(mimeType: 'image/x-wmf', extensions: [], category: 'image')]
    case IMAGE_X_WMF = 'IMAGE_X_WMF';

    #[MimeTypeInfo(mimeType: 'image/x-xbitmap', extensions: ['xbm'], category: 'image')]
    case IMAGE_X_XBITMAP = 'IMAGE_X_XBITMAP';

    #[MimeTypeInfo(mimeType: 'image/x-xcf', extensions: [], category: 'image')]
    case IMAGE_X_XCF = 'IMAGE_X_XCF';

    #[MimeTypeInfo(mimeType: 'image/x-xpixmap', extensions: ['xpm'], category: 'image')]
    case IMAGE_X_XPIXMAP = 'IMAGE_X_XPIXMAP';

    #[MimeTypeInfo(mimeType: 'image/x-xwindowdump', extensions: ['xwd'], category: 'image')]
    case IMAGE_X_XWINDOWDUMP = 'IMAGE_X_XWINDOWDUMP';

    #[MimeTypeInfo(mimeType: 'message/bhttp', extensions: [], category: 'message')]
    case MESSAGE_BHTTP = 'MESSAGE_BHTTP';

    #[MimeTypeInfo(mimeType: 'message/cpim', extensions: [], category: 'message')]
    case MESSAGE_CPIM = 'MESSAGE_CPIM';

    #[MimeTypeInfo(mimeType: 'message/delivery-status', extensions: [], category: 'message')]
    case MESSAGE_DELIVERY_STATUS = 'MESSAGE_DELIVERY_STATUS';

    #[MimeTypeInfo(mimeType: 'message/disposition-notification', extensions: ['disposition-notification'], category: 'message')]
    case MESSAGE_DISPOSITION_NOTIFICATION = 'MESSAGE_DISPOSITION_NOTIFICATION';

    #[MimeTypeInfo(mimeType: 'message/example', extensions: [], category: 'message')]
    case MESSAGE_EXAMPLE = 'MESSAGE_EXAMPLE';

    #[MimeTypeInfo(mimeType: 'message/external-body', extensions: [], category: 'message')]
    case MESSAGE_EXTERNAL_BODY = 'MESSAGE_EXTERNAL_BODY';

    #[MimeTypeInfo(mimeType: 'message/feedback-report', extensions: [], category: 'message')]
    case MESSAGE_FEEDBACK_REPORT = 'MESSAGE_FEEDBACK_REPORT';

    #[MimeTypeInfo(mimeType: 'message/global', extensions: ['u8msg'], category: 'message')]
    case MESSAGE_GLOBAL = 'MESSAGE_GLOBAL';

    #[MimeTypeInfo(mimeType: 'message/global-delivery-status', extensions: ['u8dsn'], category: 'message')]
    case MESSAGE_GLOBAL_DELIVERY_STATUS = 'MESSAGE_GLOBAL_DELIVERY_STATUS';

    #[MimeTypeInfo(mimeType: 'message/global-disposition-notification', extensions: ['u8mdn'], category: 'message')]
    case MESSAGE_GLOBAL_DISPOSITION_NOTIFICATION = 'MESSAGE_GLOBAL_DISPOSITION_NOTIFICATION';

    #[MimeTypeInfo(mimeType: 'message/global-headers', extensions: ['u8hdr'], category: 'message')]
    case MESSAGE_GLOBAL_HEADERS = 'MESSAGE_GLOBAL_HEADERS';

    #[MimeTypeInfo(mimeType: 'message/http', extensions: [], category: 'message')]
    case MESSAGE_HTTP = 'MESSAGE_HTTP';

    #[MimeTypeInfo(mimeType: 'message/imdn+xml', extensions: ['cl', 'xml'], category: 'message')]
    case MESSAGE_IMDN_XML = 'MESSAGE_IMDN_XML';

    #[MimeTypeInfo(mimeType: 'message/mls', extensions: [], category: 'message')]
    case MESSAGE_MLS = 'MESSAGE_MLS';

    #[MimeTypeInfo(mimeType: 'message/news', extensions: [], category: 'message')]
    case MESSAGE_NEWS = 'MESSAGE_NEWS';

    #[MimeTypeInfo(mimeType: 'message/ohttp-req', extensions: [], category: 'message')]
    case MESSAGE_OHTTP_REQ = 'MESSAGE_OHTTP_REQ';

    #[MimeTypeInfo(mimeType: 'message/ohttp-res', extensions: [], category: 'message')]
    case MESSAGE_OHTTP_RES = 'MESSAGE_OHTTP_RES';

    #[MimeTypeInfo(mimeType: 'message/partial', extensions: [], category: 'message')]
    case MESSAGE_PARTIAL = 'MESSAGE_PARTIAL';

    #[MimeTypeInfo(mimeType: 'message/rfc822', extensions: ['eml', 'mime', 'mht', 'mhtml'], category: 'message')]
    case MESSAGE_RFC822 = 'MESSAGE_RFC822';

    #[MimeTypeInfo(mimeType: 'message/s-http', extensions: [], category: 'message')]
    case MESSAGE_S_HTTP = 'MESSAGE_S_HTTP';

    #[MimeTypeInfo(mimeType: 'message/sip', extensions: [], category: 'message')]
    case MESSAGE_SIP = 'MESSAGE_SIP';

    #[MimeTypeInfo(mimeType: 'message/sipfrag', extensions: [], category: 'message')]
    case MESSAGE_SIPFRAG = 'MESSAGE_SIPFRAG';

    #[MimeTypeInfo(mimeType: 'message/tracking-status', extensions: [], category: 'message')]
    case MESSAGE_TRACKING_STATUS = 'MESSAGE_TRACKING_STATUS';

    #[MimeTypeInfo(mimeType: 'message/vnd.si.simp', extensions: [], category: 'message')]
    case MESSAGE_VND_SI_SIMP = 'MESSAGE_VND_SI_SIMP';

    #[MimeTypeInfo(mimeType: 'message/vnd.wfa.wsc', extensions: ['wsc'], category: 'message')]
    case MESSAGE_VND_WFA_WSC = 'MESSAGE_VND_WFA_WSC';

    #[MimeTypeInfo(mimeType: 'model/3mf', extensions: ['3mf'], category: 'model')]
    case MODEL_3MF = 'MODEL_3MF';

    #[MimeTypeInfo(mimeType: 'model/e57', extensions: [], category: 'model')]
    case MODEL_E57 = 'MODEL_E57';

    #[MimeTypeInfo(mimeType: 'model/example', extensions: [], category: 'model')]
    case MODEL_EXAMPLE = 'MODEL_EXAMPLE';

    #[MimeTypeInfo(mimeType: 'model/gltf+json', extensions: ['gltf'], category: 'model')]
    case MODEL_GLTF_JSON = 'MODEL_GLTF_JSON';

    #[MimeTypeInfo(mimeType: 'model/gltf-binary', extensions: ['glb'], category: 'model')]
    case MODEL_GLTF_BINARY = 'MODEL_GLTF_BINARY';

    #[MimeTypeInfo(mimeType: 'model/iges', extensions: ['igs', 'iges'], category: 'model')]
    case MODEL_IGES = 'MODEL_IGES';

    #[MimeTypeInfo(mimeType: 'model/jt', extensions: ['jt'], category: 'model')]
    case MODEL_JT = 'MODEL_JT';

    #[MimeTypeInfo(mimeType: 'model/mesh', extensions: ['msh', 'mesh', 'silo'], category: 'model')]
    case MODEL_MESH = 'MODEL_MESH';

    #[MimeTypeInfo(mimeType: 'model/mtl', extensions: ['mtl'], category: 'model')]
    case MODEL_MTL = 'MODEL_MTL';

    #[MimeTypeInfo(mimeType: 'model/obj', extensions: ['obj'], category: 'model')]
    case MODEL_OBJ = 'MODEL_OBJ';

    #[MimeTypeInfo(mimeType: 'model/prc', extensions: ['prc'], category: 'model')]
    case MODEL_PRC = 'MODEL_PRC';

    #[MimeTypeInfo(mimeType: 'model/step', extensions: ['p21', 'stp', 'step', 'stpnc', '210'], category: 'model')]
    case MODEL_STEP = 'MODEL_STEP';

    #[MimeTypeInfo(mimeType: 'model/step+xml', extensions: ['stpx'], category: 'model')]
    case MODEL_STEP_XML = 'MODEL_STEP_XML';

    #[MimeTypeInfo(mimeType: 'model/step+zip', extensions: ['stpz'], category: 'model')]
    case MODEL_STEP_ZIP = 'MODEL_STEP_ZIP';

    #[MimeTypeInfo(mimeType: 'model/step-xml+zip', extensions: ['stpxz'], category: 'model')]
    case MODEL_STEP_XML_ZIP = 'MODEL_STEP_XML_ZIP';

    #[MimeTypeInfo(mimeType: 'model/stl', extensions: ['stl'], category: 'model')]
    case MODEL_STL = 'MODEL_STL';

    #[MimeTypeInfo(mimeType: 'model/u3d', extensions: ['u3d'], category: 'model')]
    case MODEL_U3D = 'MODEL_U3D';

    #[MimeTypeInfo(mimeType: 'model/vnd.bary', extensions: ['bary'], category: 'model')]
    case MODEL_VND_BARY = 'MODEL_VND_BARY';

    #[MimeTypeInfo(mimeType: 'model/vnd.cld', extensions: ['cld'], category: 'model')]
    case MODEL_VND_CLD = 'MODEL_VND_CLD';

    #[MimeTypeInfo(mimeType: 'model/vnd.collada+xml', extensions: ['dae'], category: 'model')]
    case MODEL_VND_COLLADA_XML = 'MODEL_VND_COLLADA_XML';

    #[MimeTypeInfo(mimeType: 'model/vnd.dwf', extensions: ['dwf'], category: 'model')]
    case MODEL_VND_DWF = 'MODEL_VND_DWF';

    #[MimeTypeInfo(mimeType: 'model/vnd.flatland.3dml', extensions: ['3dml'], category: 'model')]
    case MODEL_VND_FLATLAND_3DML = 'MODEL_VND_FLATLAND_3DML';

    #[MimeTypeInfo(mimeType: 'model/vnd.gdl', extensions: ['gdl', 'gsm', 'win', 'dor', 'lmp', 'rsm', 'msm', 'ism'], category: 'model')]
    case MODEL_VND_GDL = 'MODEL_VND_GDL';

    #[MimeTypeInfo(mimeType: 'model/vnd.gs-gdl', extensions: ['gdl', 'gsm', 'win', 'dor', 'lmp', 'rsm', 'msm', 'ism'], category: 'model')]
    case MODEL_VND_GS_GDL = 'MODEL_VND_GS_GDL';

    #[MimeTypeInfo(mimeType: 'model/vnd.gtw', extensions: ['gtw'], category: 'model')]
    case MODEL_VND_GTW = 'MODEL_VND_GTW';

    #[MimeTypeInfo(mimeType: 'model/vnd.moml+xml', extensions: ['xml'], category: 'model')]
    case MODEL_VND_MOML_XML = 'MODEL_VND_MOML_XML';

    #[MimeTypeInfo(mimeType: 'model/vnd.mts', extensions: ['mts'], category: 'model')]
    case MODEL_VND_MTS = 'MODEL_VND_MTS';

    #[MimeTypeInfo(mimeType: 'model/vnd.opengex', extensions: ['ogex'], category: 'model')]
    case MODEL_VND_OPENGEX = 'MODEL_VND_OPENGEX';

    #[MimeTypeInfo(mimeType: 'model/vnd.parasolid.transmit.binary', extensions: ['x_b', 'xmt_bin'], category: 'model')]
    case MODEL_VND_PARASOLID_TRANSMIT_BINARY = 'MODEL_VND_PARASOLID_TRANSMIT_BINARY';

    #[MimeTypeInfo(mimeType: 'model/vnd.parasolid.transmit.text', extensions: ['x_t', 'xmt_txt'], category: 'model')]
    case MODEL_VND_PARASOLID_TRANSMIT_TEXT = 'MODEL_VND_PARASOLID_TRANSMIT_TEXT';

    #[MimeTypeInfo(mimeType: 'model/vnd.pytha.pyox', extensions: ['pyo', 'pyox'], category: 'model')]
    case MODEL_VND_PYTHA_PYOX = 'MODEL_VND_PYTHA_PYOX';

    #[MimeTypeInfo(mimeType: 'model/vnd.rosette.annotated-data-model', extensions: [], category: 'model')]
    case MODEL_VND_ROSETTE_ANNOTATED_DATA_MODEL = 'MODEL_VND_ROSETTE_ANNOTATED_DATA_MODEL';

    #[MimeTypeInfo(mimeType: 'model/vnd.sap.vds', extensions: ['vds'], category: 'model')]
    case MODEL_VND_SAP_VDS = 'MODEL_VND_SAP_VDS';

    #[MimeTypeInfo(mimeType: 'model/vnd.usda', extensions: ['usda'], category: 'model')]
    case MODEL_VND_USDA = 'MODEL_VND_USDA';

    #[MimeTypeInfo(mimeType: 'model/vnd.usdz+zip', extensions: ['usdz'], category: 'model')]
    case MODEL_VND_USDZ_ZIP = 'MODEL_VND_USDZ_ZIP';

    #[MimeTypeInfo(mimeType: 'model/vnd.valve.source.compiled-map', extensions: ['bsp'], category: 'model')]
    case MODEL_VND_VALVE_SOURCE_COMPILED_MAP = 'MODEL_VND_VALVE_SOURCE_COMPILED_MAP';

    #[MimeTypeInfo(mimeType: 'model/vnd.vtu', extensions: ['vtu'], category: 'model')]
    case MODEL_VND_VTU = 'MODEL_VND_VTU';

    #[MimeTypeInfo(mimeType: 'model/vrml', extensions: ['wrl', 'vrml'], category: 'model')]
    case MODEL_VRML = 'MODEL_VRML';

    #[MimeTypeInfo(mimeType: 'model/x3d+binary', extensions: ['x3db', 'x3dbz'], category: 'model')]
    case MODEL_X3D_BINARY = 'MODEL_X3D_BINARY';

    #[MimeTypeInfo(mimeType: 'model/x3d+fastinfoset', extensions: ['x3db'], category: 'model')]
    case MODEL_X3D_FASTINFOSET = 'MODEL_X3D_FASTINFOSET';

    #[MimeTypeInfo(mimeType: 'model/x3d+vrml', extensions: ['x3dv', 'x3dvz'], category: 'model')]
    case MODEL_X3D_VRML = 'MODEL_X3D_VRML';

    #[MimeTypeInfo(mimeType: 'model/x3d+xml', extensions: ['x3d', 'x3dz'], category: 'model')]
    case MODEL_X3D_XML = 'MODEL_X3D_XML';

    #[MimeTypeInfo(mimeType: 'multipart/alternative', extensions: [], category: 'multipart')]
    case MULTIPART_ALTERNATIVE = 'MULTIPART_ALTERNATIVE';

    #[MimeTypeInfo(mimeType: 'multipart/appledouble', extensions: [], category: 'multipart')]
    case MULTIPART_APPLEDOUBLE = 'MULTIPART_APPLEDOUBLE';

    #[MimeTypeInfo(mimeType: 'multipart/byteranges', extensions: [], category: 'multipart')]
    case MULTIPART_BYTERANGES = 'MULTIPART_BYTERANGES';

    #[MimeTypeInfo(mimeType: 'multipart/digest', extensions: [], category: 'multipart')]
    case MULTIPART_DIGEST = 'MULTIPART_DIGEST';

    #[MimeTypeInfo(mimeType: 'multipart/encrypted', extensions: [], category: 'multipart')]
    case MULTIPART_ENCRYPTED = 'MULTIPART_ENCRYPTED';

    #[MimeTypeInfo(mimeType: 'multipart/example', extensions: [], category: 'multipart')]
    case MULTIPART_EXAMPLE = 'MULTIPART_EXAMPLE';

    #[MimeTypeInfo(mimeType: 'multipart/form-data', extensions: [], category: 'multipart')]
    case MULTIPART_FORM_DATA = 'MULTIPART_FORM_DATA';

    #[MimeTypeInfo(mimeType: 'multipart/header-set', extensions: [], category: 'multipart')]
    case MULTIPART_HEADER_SET = 'MULTIPART_HEADER_SET';

    #[MimeTypeInfo(mimeType: 'multipart/mixed', extensions: [], category: 'multipart')]
    case MULTIPART_MIXED = 'MULTIPART_MIXED';

    #[MimeTypeInfo(mimeType: 'multipart/multilingual', extensions: [], category: 'multipart')]
    case MULTIPART_MULTILINGUAL = 'MULTIPART_MULTILINGUAL';

    #[MimeTypeInfo(mimeType: 'multipart/parallel', extensions: [], category: 'multipart')]
    case MULTIPART_PARALLEL = 'MULTIPART_PARALLEL';

    #[MimeTypeInfo(mimeType: 'multipart/related', extensions: [], category: 'multipart')]
    case MULTIPART_RELATED = 'MULTIPART_RELATED';

    #[MimeTypeInfo(mimeType: 'multipart/report', extensions: [], category: 'multipart')]
    case MULTIPART_REPORT = 'MULTIPART_REPORT';

    #[MimeTypeInfo(mimeType: 'multipart/signed', extensions: [], category: 'multipart')]
    case MULTIPART_SIGNED = 'MULTIPART_SIGNED';

    #[MimeTypeInfo(mimeType: 'multipart/vnd.bint.med-plus', extensions: ['bmed'], category: 'multipart')]
    case MULTIPART_VND_BINT_MED_PLUS = 'MULTIPART_VND_BINT_MED_PLUS';

    #[MimeTypeInfo(mimeType: 'multipart/voice-message', extensions: ['vpm'], category: 'multipart')]
    case MULTIPART_VOICE_MESSAGE = 'MULTIPART_VOICE_MESSAGE';

    #[MimeTypeInfo(mimeType: 'multipart/x-mixed-replace', extensions: [], category: 'multipart')]
    case MULTIPART_X_MIXED_REPLACE = 'MULTIPART_X_MIXED_REPLACE';

    #[MimeTypeInfo(mimeType: 'text/1d-interleaved-parityfec', extensions: [], category: 'text')]
    case TEXT_1D_INTERLEAVED_PARITYFEC = 'TEXT_1D_INTERLEAVED_PARITYFEC';

    #[MimeTypeInfo(mimeType: 'text/cache-manifest', extensions: ['appcache', 'manifest'], category: 'text')]
    case TEXT_CACHE_MANIFEST = 'TEXT_CACHE_MANIFEST';

    #[MimeTypeInfo(mimeType: 'text/calendar', extensions: ['ics', 'ifb'], category: 'text')]
    case TEXT_CALENDAR = 'TEXT_CALENDAR';

    #[MimeTypeInfo(mimeType: 'text/calender', extensions: [], category: 'text')]
    case TEXT_CALENDER = 'TEXT_CALENDER';

    #[MimeTypeInfo(mimeType: 'text/cmd', extensions: [], category: 'text')]
    case TEXT_CMD = 'TEXT_CMD';

    #[MimeTypeInfo(mimeType: 'text/coffeescript', extensions: ['coffee', 'litcoffee'], category: 'text')]
    case TEXT_COFFEESCRIPT = 'TEXT_COFFEESCRIPT';

    #[MimeTypeInfo(mimeType: 'text/cql', extensions: ['cql'], category: 'text')]
    case TEXT_CQL = 'TEXT_CQL';

    #[MimeTypeInfo(mimeType: 'text/cql-expression', extensions: [], category: 'text')]
    case TEXT_CQL_EXPRESSION = 'TEXT_CQL_EXPRESSION';

    #[MimeTypeInfo(mimeType: 'text/cql-identifier', extensions: [], category: 'text')]
    case TEXT_CQL_IDENTIFIER = 'TEXT_CQL_IDENTIFIER';

    #[MimeTypeInfo(mimeType: 'text/css', extensions: ['css'], category: 'text')]
    case TEXT_CSS = 'TEXT_CSS';

    #[MimeTypeInfo(mimeType: 'text/csv', extensions: ['csv'], category: 'text')]
    case TEXT_CSV = 'TEXT_CSV';

    #[MimeTypeInfo(mimeType: 'text/csv-schema', extensions: ['csvs'], category: 'text')]
    case TEXT_CSV_SCHEMA = 'TEXT_CSV_SCHEMA';

    #[MimeTypeInfo(mimeType: 'text/directory', extensions: [], category: 'text')]
    case TEXT_DIRECTORY = 'TEXT_DIRECTORY';

    #[MimeTypeInfo(mimeType: 'text/dns', extensions: ['soa', 'zone'], category: 'text')]
    case TEXT_DNS = 'TEXT_DNS';

    #[MimeTypeInfo(mimeType: 'text/ecmascript', extensions: ['es', 'mjs'], category: 'text')]
    case TEXT_ECMASCRIPT = 'TEXT_ECMASCRIPT';

    #[MimeTypeInfo(mimeType: 'text/encaprtp', extensions: [], category: 'text')]
    case TEXT_ENCAPRTP = 'TEXT_ENCAPRTP';

    #[MimeTypeInfo(mimeType: 'text/enriched', extensions: [], category: 'text')]
    case TEXT_ENRICHED = 'TEXT_ENRICHED';

    #[MimeTypeInfo(mimeType: 'text/example', extensions: [], category: 'text')]
    case TEXT_EXAMPLE = 'TEXT_EXAMPLE';

    #[MimeTypeInfo(mimeType: 'text/fhirpath', extensions: [], category: 'text')]
    case TEXT_FHIRPATH = 'TEXT_FHIRPATH';

    #[MimeTypeInfo(mimeType: 'text/flexfec', extensions: [], category: 'text')]
    case TEXT_FLEXFEC = 'TEXT_FLEXFEC';

    #[MimeTypeInfo(mimeType: 'text/fwdred', extensions: [], category: 'text')]
    case TEXT_FWDRED = 'TEXT_FWDRED';

    #[MimeTypeInfo(mimeType: 'text/gff3', extensions: ['gff3'], category: 'text')]
    case TEXT_GFF3 = 'TEXT_GFF3';

    #[MimeTypeInfo(mimeType: 'text/grammar-ref-list', extensions: [], category: 'text')]
    case TEXT_GRAMMAR_REF_LIST = 'TEXT_GRAMMAR_REF_LIST';

    #[MimeTypeInfo(mimeType: 'text/hl7v2', extensions: [], category: 'text')]
    case TEXT_HL7V2 = 'TEXT_HL7V2';

    #[MimeTypeInfo(mimeType: 'text/html', extensions: ['html', 'htm', 'shtml'], category: 'text')]
    case TEXT_HTML = 'TEXT_HTML';

    #[MimeTypeInfo(mimeType: 'text/jade', extensions: ['jade'], category: 'text')]
    case TEXT_JADE = 'TEXT_JADE';

    #[MimeTypeInfo(mimeType: 'text/javascript', extensions: ['js', 'mjs'], category: 'text')]
    case TEXT_JAVASCRIPT = 'TEXT_JAVASCRIPT';

    #[MimeTypeInfo(mimeType: 'text/jcr-cnd', extensions: ['cnd'], category: 'text')]
    case TEXT_JCR_CND = 'TEXT_JCR_CND';

    #[MimeTypeInfo(mimeType: 'text/jsx', extensions: ['jsx'], category: 'text')]
    case TEXT_JSX = 'TEXT_JSX';

    #[MimeTypeInfo(mimeType: 'text/less', extensions: ['less'], category: 'text')]
    case TEXT_LESS = 'TEXT_LESS';

    #[MimeTypeInfo(mimeType: 'text/markdown', extensions: ['md', 'markdown'], category: 'text')]
    case TEXT_MARKDOWN = 'TEXT_MARKDOWN';

    #[MimeTypeInfo(mimeType: 'text/mathml', extensions: ['mml'], category: 'text')]
    case TEXT_MATHML = 'TEXT_MATHML';

    #[MimeTypeInfo(mimeType: 'text/mdx', extensions: ['mdx'], category: 'text')]
    case TEXT_MDX = 'TEXT_MDX';

    #[MimeTypeInfo(mimeType: 'text/mizar', extensions: ['miz'], category: 'text')]
    case TEXT_MIZAR = 'TEXT_MIZAR';

    #[MimeTypeInfo(mimeType: 'text/n3', extensions: ['n3'], category: 'text')]
    case TEXT_N3 = 'TEXT_N3';

    #[MimeTypeInfo(mimeType: 'text/parameters', extensions: [], category: 'text')]
    case TEXT_PARAMETERS = 'TEXT_PARAMETERS';

    #[MimeTypeInfo(mimeType: 'text/parityfec', extensions: [], category: 'text')]
    case TEXT_PARITYFEC = 'TEXT_PARITYFEC';

    #[MimeTypeInfo(mimeType: 'text/plain', extensions: ['txt', 'text', 'conf', 'def', 'list', 'log', 'in', 'ini'], category: 'text')]
    case TEXT_PLAIN = 'TEXT_PLAIN';

    #[MimeTypeInfo(mimeType: 'text/provenance-notation', extensions: ['provn'], category: 'text')]
    case TEXT_PROVENANCE_NOTATION = 'TEXT_PROVENANCE_NOTATION';

    #[MimeTypeInfo(mimeType: 'text/prs.fallenstein.rst', extensions: ['txt', 'rst'], category: 'text')]
    case TEXT_PRS_FALLENSTEIN_RST = 'TEXT_PRS_FALLENSTEIN_RST';

    #[MimeTypeInfo(mimeType: 'text/prs.lines.tag', extensions: ['dsc'], category: 'text')]
    case TEXT_PRS_LINES_TAG = 'TEXT_PRS_LINES_TAG';

    #[MimeTypeInfo(mimeType: 'text/prs.prop.logic', extensions: ['txt'], category: 'text')]
    case TEXT_PRS_PROP_LOGIC = 'TEXT_PRS_PROP_LOGIC';

    #[MimeTypeInfo(mimeType: 'text/prs.texi', extensions: ['texi'], category: 'text')]
    case TEXT_PRS_TEXI = 'TEXT_PRS_TEXI';

    #[MimeTypeInfo(mimeType: 'text/raptorfec', extensions: [], category: 'text')]
    case TEXT_RAPTORFEC = 'TEXT_RAPTORFEC';

    #[MimeTypeInfo(mimeType: 'text/red', extensions: [], category: 'text')]
    case TEXT_RED = 'TEXT_RED';

    #[MimeTypeInfo(mimeType: 'text/rfc822-headers', extensions: [], category: 'text')]
    case TEXT_RFC822_HEADERS = 'TEXT_RFC822_HEADERS';

    #[MimeTypeInfo(mimeType: 'text/richtext', extensions: ['rtx'], category: 'text')]
    case TEXT_RICHTEXT = 'TEXT_RICHTEXT';

    #[MimeTypeInfo(mimeType: 'text/rtf', extensions: ['rtf'], category: 'text')]
    case TEXT_RTF = 'TEXT_RTF';

    #[MimeTypeInfo(mimeType: 'text/rtp-enc-aescm128', extensions: [], category: 'text')]
    case TEXT_RTP_ENC_AESCM128 = 'TEXT_RTP_ENC_AESCM128';

    #[MimeTypeInfo(mimeType: 'text/rtploopback', extensions: [], category: 'text')]
    case TEXT_RTPLOOPBACK = 'TEXT_RTPLOOPBACK';

    #[MimeTypeInfo(mimeType: 'text/rtx', extensions: [], category: 'text')]
    case TEXT_RTX = 'TEXT_RTX';

    #[MimeTypeInfo(mimeType: 'text/sgml', extensions: ['sgml', 'sgm'], category: 'text')]
    case TEXT_SGML = 'TEXT_SGML';

    #[MimeTypeInfo(mimeType: 'text/shaclc', extensions: ['shaclc', 'shc'], category: 'text')]
    case TEXT_SHACLC = 'TEXT_SHACLC';

    #[MimeTypeInfo(mimeType: 'text/shex', extensions: ['shex'], category: 'text')]
    case TEXT_SHEX = 'TEXT_SHEX';

    #[MimeTypeInfo(mimeType: 'text/slim', extensions: ['slim', 'slm'], category: 'text')]
    case TEXT_SLIM = 'TEXT_SLIM';

    #[MimeTypeInfo(mimeType: 'text/spdx', extensions: ['spdx'], category: 'text')]
    case TEXT_SPDX = 'TEXT_SPDX';

    #[MimeTypeInfo(mimeType: 'text/strings', extensions: [], category: 'text')]
    case TEXT_STRINGS = 'TEXT_STRINGS';

    #[MimeTypeInfo(mimeType: 'text/stylus', extensions: ['stylus', 'styl'], category: 'text')]
    case TEXT_STYLUS = 'TEXT_STYLUS';

    #[MimeTypeInfo(mimeType: 'text/t140', extensions: [], category: 'text')]
    case TEXT_T140 = 'TEXT_T140';

    #[MimeTypeInfo(mimeType: 'text/tab-separated-values', extensions: ['tsv'], category: 'text')]
    case TEXT_TAB_SEPARATED_VALUES = 'TEXT_TAB_SEPARATED_VALUES';

    #[MimeTypeInfo(mimeType: 'text/troff', extensions: ['t', 'tr', 'roff', 'man', 'me', 'ms'], category: 'text')]
    case TEXT_TROFF = 'TEXT_TROFF';

    #[MimeTypeInfo(mimeType: 'text/turtle', extensions: ['ttl'], category: 'text')]
    case TEXT_TURTLE = 'TEXT_TURTLE';

    #[MimeTypeInfo(mimeType: 'text/ulpfec', extensions: [], category: 'text')]
    case TEXT_ULPFEC = 'TEXT_ULPFEC';

    #[MimeTypeInfo(mimeType: 'text/uri-list', extensions: ['uri', 'uris', 'urls'], category: 'text')]
    case TEXT_URI_LIST = 'TEXT_URI_LIST';

    #[MimeTypeInfo(mimeType: 'text/vcard', extensions: ['vcard', 'vcf'], category: 'text')]
    case TEXT_VCARD = 'TEXT_VCARD';

    #[MimeTypeInfo(mimeType: 'text/vnd.a', extensions: ['a'], category: 'text')]
    case TEXT_VND_A = 'TEXT_VND_A';

    #[MimeTypeInfo(mimeType: 'text/vnd.abc', extensions: ['abc'], category: 'text')]
    case TEXT_VND_ABC = 'TEXT_VND_ABC';

    #[MimeTypeInfo(mimeType: 'text/vnd.ascii-art', extensions: ['ascii'], category: 'text')]
    case TEXT_VND_ASCII_ART = 'TEXT_VND_ASCII_ART';

    #[MimeTypeInfo(mimeType: 'text/vnd.curl', extensions: ['curl'], category: 'text')]
    case TEXT_VND_CURL = 'TEXT_VND_CURL';

    #[MimeTypeInfo(mimeType: 'text/vnd.curl.dcurl', extensions: ['dcurl'], category: 'text')]
    case TEXT_VND_CURL_DCURL = 'TEXT_VND_CURL_DCURL';

    #[MimeTypeInfo(mimeType: 'text/vnd.curl.mcurl', extensions: ['mcurl'], category: 'text')]
    case TEXT_VND_CURL_MCURL = 'TEXT_VND_CURL_MCURL';

    #[MimeTypeInfo(mimeType: 'text/vnd.curl.scurl', extensions: ['scurl'], category: 'text')]
    case TEXT_VND_CURL_SCURL = 'TEXT_VND_CURL_SCURL';

    #[MimeTypeInfo(mimeType: 'text/vnd.debian.copyright', extensions: [], category: 'text')]
    case TEXT_VND_DEBIAN_COPYRIGHT = 'TEXT_VND_DEBIAN_COPYRIGHT';

    #[MimeTypeInfo(mimeType: 'text/vnd.dmclientscript', extensions: ['dms'], category: 'text')]
    case TEXT_VND_DMCLIENTSCRIPT = 'TEXT_VND_DMCLIENTSCRIPT';

    #[MimeTypeInfo(mimeType: 'text/vnd.dvb.subtitle', extensions: ['sub'], category: 'text')]
    case TEXT_VND_DVB_SUBTITLE = 'TEXT_VND_DVB_SUBTITLE';

    #[MimeTypeInfo(mimeType: 'text/vnd.esmertec.theme-descriptor', extensions: ['jtd'], category: 'text')]
    case TEXT_VND_ESMERTEC_THEME_DESCRIPTOR = 'TEXT_VND_ESMERTEC_THEME_DESCRIPTOR';

    #[MimeTypeInfo(mimeType: 'text/vnd.exchangeable', extensions: ['vfk'], category: 'text')]
    case TEXT_VND_EXCHANGEABLE = 'TEXT_VND_EXCHANGEABLE';

    #[MimeTypeInfo(mimeType: 'text/vnd.familysearch.gedcom', extensions: ['ged'], category: 'text')]
    case TEXT_VND_FAMILYSEARCH_GEDCOM = 'TEXT_VND_FAMILYSEARCH_GEDCOM';

    #[MimeTypeInfo(mimeType: 'text/vnd.ficlab.flt', extensions: ['flt'], category: 'text')]
    case TEXT_VND_FICLAB_FLT = 'TEXT_VND_FICLAB_FLT';

    #[MimeTypeInfo(mimeType: 'text/vnd.fly', extensions: ['fly'], category: 'text')]
    case TEXT_VND_FLY = 'TEXT_VND_FLY';

    #[MimeTypeInfo(mimeType: 'text/vnd.fmi.flexstor', extensions: ['flx'], category: 'text')]
    case TEXT_VND_FMI_FLEXSTOR = 'TEXT_VND_FMI_FLEXSTOR';

    #[MimeTypeInfo(mimeType: 'text/vnd.gml', extensions: ['gml'], category: 'text')]
    case TEXT_VND_GML = 'TEXT_VND_GML';

    #[MimeTypeInfo(mimeType: 'text/vnd.graphviz', extensions: ['gv'], category: 'text')]
    case TEXT_VND_GRAPHVIZ = 'TEXT_VND_GRAPHVIZ';

    #[MimeTypeInfo(mimeType: 'text/vnd.hans', extensions: ['hans'], category: 'text')]
    case TEXT_VND_HANS = 'TEXT_VND_HANS';

    #[MimeTypeInfo(mimeType: 'text/vnd.hgl', extensions: ['hgl'], category: 'text')]
    case TEXT_VND_HGL = 'TEXT_VND_HGL';

    #[MimeTypeInfo(mimeType: 'text/vnd.in3d.3dml', extensions: ['3dml'], category: 'text')]
    case TEXT_VND_IN3D_3DML = 'TEXT_VND_IN3D_3DML';

    #[MimeTypeInfo(mimeType: 'text/vnd.in3d.spot', extensions: ['spot', 'spo'], category: 'text')]
    case TEXT_VND_IN3D_SPOT = 'TEXT_VND_IN3D_SPOT';

    #[MimeTypeInfo(mimeType: 'text/vnd.iptc.newsml', extensions: ['xml'], category: 'text')]
    case TEXT_VND_IPTC_NEWSML = 'TEXT_VND_IPTC_NEWSML';

    #[MimeTypeInfo(mimeType: 'text/vnd.iptc.nitf', extensions: ['xml'], category: 'text')]
    case TEXT_VND_IPTC_NITF = 'TEXT_VND_IPTC_NITF';

    #[MimeTypeInfo(mimeType: 'text/vnd.latex-z', extensions: [], category: 'text')]
    case TEXT_VND_LATEX_Z = 'TEXT_VND_LATEX_Z';

    #[MimeTypeInfo(mimeType: 'text/vnd.motorola.reflex', extensions: [], category: 'text')]
    case TEXT_VND_MOTOROLA_REFLEX = 'TEXT_VND_MOTOROLA_REFLEX';

    #[MimeTypeInfo(mimeType: 'text/vnd.ms-mediapackage', extensions: ['mpf'], category: 'text')]
    case TEXT_VND_MS_MEDIAPACKAGE = 'TEXT_VND_MS_MEDIAPACKAGE';

    #[MimeTypeInfo(mimeType: 'text/vnd.net2phone.commcenter.command', extensions: ['ccc'], category: 'text')]
    case TEXT_VND_NET2PHONE_COMMCENTER_COMMAND = 'TEXT_VND_NET2PHONE_COMMCENTER_COMMAND';

    #[MimeTypeInfo(mimeType: 'text/vnd.radisys.msml-basic-layout', extensions: [], category: 'text')]
    case TEXT_VND_RADISYS_MSML_BASIC_LAYOUT = 'TEXT_VND_RADISYS_MSML_BASIC_LAYOUT';

    #[MimeTypeInfo(mimeType: 'text/vnd.senx.warpscript', extensions: ['mc2'], category: 'text')]
    case TEXT_VND_SENX_WARPSCRIPT = 'TEXT_VND_SENX_WARPSCRIPT';

    #[MimeTypeInfo(mimeType: 'text/vnd.si.uricatalogue', extensions: ['uric'], category: 'text')]
    case TEXT_VND_SI_URICATALOGUE = 'TEXT_VND_SI_URICATALOGUE';

    #[MimeTypeInfo(mimeType: 'text/vnd.sosi', extensions: ['sos'], category: 'text')]
    case TEXT_VND_SOSI = 'TEXT_VND_SOSI';

    #[MimeTypeInfo(mimeType: 'text/vnd.sun.j2me.app-descriptor', extensions: ['jad'], category: 'text')]
    case TEXT_VND_SUN_J2ME_APP_DESCRIPTOR = 'TEXT_VND_SUN_J2ME_APP_DESCRIPTOR';

    #[MimeTypeInfo(mimeType: 'text/vnd.trolltech.linguist', extensions: ['ts'], category: 'text')]
    case TEXT_VND_TROLLTECH_LINGUIST = 'TEXT_VND_TROLLTECH_LINGUIST';

    #[MimeTypeInfo(mimeType: 'text/vnd.typst', extensions: ['typ'], category: 'text')]
    case TEXT_VND_TYPST = 'TEXT_VND_TYPST';

    #[MimeTypeInfo(mimeType: 'text/vnd.vcf', extensions: ['vcf'], category: 'text')]
    case TEXT_VND_VCF = 'TEXT_VND_VCF';

    #[MimeTypeInfo(mimeType: 'text/vnd.wap.si', extensions: ['si'], category: 'text')]
    case TEXT_VND_WAP_SI = 'TEXT_VND_WAP_SI';

    #[MimeTypeInfo(mimeType: 'text/vnd.wap.sl', extensions: ['sl'], category: 'text')]
    case TEXT_VND_WAP_SL = 'TEXT_VND_WAP_SL';

    #[MimeTypeInfo(mimeType: 'text/vnd.wap.wml', extensions: ['wml'], category: 'text')]
    case TEXT_VND_WAP_WML = 'TEXT_VND_WAP_WML';

    #[MimeTypeInfo(mimeType: 'text/vnd.wap.wmlscript', extensions: ['wmls'], category: 'text')]
    case TEXT_VND_WAP_WMLSCRIPT = 'TEXT_VND_WAP_WMLSCRIPT';

    #[MimeTypeInfo(mimeType: 'text/vnd.zoo.kcl', extensions: ['kcl'], category: 'text')]
    case TEXT_VND_ZOO_KCL = 'TEXT_VND_ZOO_KCL';

    #[MimeTypeInfo(mimeType: 'text/vtt', extensions: ['vtt'], category: 'text')]
    case TEXT_VTT = 'TEXT_VTT';

    #[MimeTypeInfo(mimeType: 'text/wgsl', extensions: ['wgsl'], category: 'text')]
    case TEXT_WGSL = 'TEXT_WGSL';

    #[MimeTypeInfo(mimeType: 'text/x-asm', extensions: ['s', 'asm'], category: 'text')]
    case TEXT_X_ASM = 'TEXT_X_ASM';

    #[MimeTypeInfo(mimeType: 'text/x-c', extensions: ['c', 'cc', 'cxx', 'cpp', 'h', 'hh', 'dic'], category: 'text')]
    case TEXT_X_C = 'TEXT_X_C';

    #[MimeTypeInfo(mimeType: 'text/x-component', extensions: ['htc'], category: 'text')]
    case TEXT_X_COMPONENT = 'TEXT_X_COMPONENT';

    #[MimeTypeInfo(mimeType: 'text/x-fortran', extensions: ['f', 'for', 'f77', 'f90'], category: 'text')]
    case TEXT_X_FORTRAN = 'TEXT_X_FORTRAN';

    #[MimeTypeInfo(mimeType: 'text/x-gwt-rpc', extensions: [], category: 'text')]
    case TEXT_X_GWT_RPC = 'TEXT_X_GWT_RPC';

    #[MimeTypeInfo(mimeType: 'text/x-handlebars-template', extensions: ['hbs'], category: 'text')]
    case TEXT_X_HANDLEBARS_TEMPLATE = 'TEXT_X_HANDLEBARS_TEMPLATE';

    #[MimeTypeInfo(mimeType: 'text/x-java-source', extensions: ['java'], category: 'text')]
    case TEXT_X_JAVA_SOURCE = 'TEXT_X_JAVA_SOURCE';

    #[MimeTypeInfo(mimeType: 'text/x-jquery-tmpl', extensions: [], category: 'text')]
    case TEXT_X_JQUERY_TMPL = 'TEXT_X_JQUERY_TMPL';

    #[MimeTypeInfo(mimeType: 'text/x-lua', extensions: ['lua'], category: 'text')]
    case TEXT_X_LUA = 'TEXT_X_LUA';

    #[MimeTypeInfo(mimeType: 'text/x-markdown', extensions: ['mkd'], category: 'text')]
    case TEXT_X_MARKDOWN = 'TEXT_X_MARKDOWN';

    #[MimeTypeInfo(mimeType: 'text/x-nfo', extensions: ['nfo'], category: 'text')]
    case TEXT_X_NFO = 'TEXT_X_NFO';

    #[MimeTypeInfo(mimeType: 'text/x-opml', extensions: ['opml'], category: 'text')]
    case TEXT_X_OPML = 'TEXT_X_OPML';

    #[MimeTypeInfo(mimeType: 'text/x-org', extensions: ['org'], category: 'text')]
    case TEXT_X_ORG = 'TEXT_X_ORG';

    #[MimeTypeInfo(mimeType: 'text/x-pascal', extensions: ['p', 'pas'], category: 'text')]
    case TEXT_X_PASCAL = 'TEXT_X_PASCAL';

    #[MimeTypeInfo(mimeType: 'text/x-processing', extensions: ['pde'], category: 'text')]
    case TEXT_X_PROCESSING = 'TEXT_X_PROCESSING';

    #[MimeTypeInfo(mimeType: 'text/x-sass', extensions: ['sass'], category: 'text')]
    case TEXT_X_SASS = 'TEXT_X_SASS';

    #[MimeTypeInfo(mimeType: 'text/x-scss', extensions: ['scss'], category: 'text')]
    case TEXT_X_SCSS = 'TEXT_X_SCSS';

    #[MimeTypeInfo(mimeType: 'text/x-setext', extensions: ['etx'], category: 'text')]
    case TEXT_X_SETEXT = 'TEXT_X_SETEXT';

    #[MimeTypeInfo(mimeType: 'text/x-sfv', extensions: ['sfv'], category: 'text')]
    case TEXT_X_SFV = 'TEXT_X_SFV';

    #[MimeTypeInfo(mimeType: 'text/x-suse-ymp', extensions: ['ymp'], category: 'text')]
    case TEXT_X_SUSE_YMP = 'TEXT_X_SUSE_YMP';

    #[MimeTypeInfo(mimeType: 'text/x-uuencode', extensions: ['uu'], category: 'text')]
    case TEXT_X_UUENCODE = 'TEXT_X_UUENCODE';

    #[MimeTypeInfo(mimeType: 'text/x-vcalendar', extensions: ['vcs'], category: 'text')]
    case TEXT_X_VCALENDAR = 'TEXT_X_VCALENDAR';

    #[MimeTypeInfo(mimeType: 'text/x-vcard', extensions: ['vcf'], category: 'text')]
    case TEXT_X_VCARD = 'TEXT_X_VCARD';

    #[MimeTypeInfo(mimeType: 'text/xml', extensions: ['xml'], category: 'text')]
    case TEXT_XML = 'TEXT_XML';

    #[MimeTypeInfo(mimeType: 'text/xml-external-parsed-entity', extensions: ['xml', 'ent'], category: 'text')]
    case TEXT_XML_EXTERNAL_PARSED_ENTITY = 'TEXT_XML_EXTERNAL_PARSED_ENTITY';

    #[MimeTypeInfo(mimeType: 'text/yaml', extensions: ['yaml', 'yml'], category: 'text')]
    case TEXT_YAML = 'TEXT_YAML';

    #[MimeTypeInfo(mimeType: 'video/1d-interleaved-parityfec', extensions: [], category: 'video')]
    case VIDEO_1D_INTERLEAVED_PARITYFEC = 'VIDEO_1D_INTERLEAVED_PARITYFEC';

    #[MimeTypeInfo(mimeType: 'video/3gpp', extensions: ['3gp', '3gpp'], category: 'video')]
    case VIDEO_3GPP = 'VIDEO_3GPP';

    #[MimeTypeInfo(mimeType: 'video/3gpp-tt', extensions: [], category: 'video')]
    case VIDEO_3GPP_TT = 'VIDEO_3GPP_TT';

    #[MimeTypeInfo(mimeType: 'video/3gpp2', extensions: ['3g2'], category: 'video')]
    case VIDEO_3GPP2 = 'VIDEO_3GPP2';

    #[MimeTypeInfo(mimeType: 'video/av1', extensions: [], category: 'video')]
    case VIDEO_AV1 = 'VIDEO_AV1';

    #[MimeTypeInfo(mimeType: 'video/bmpeg', extensions: [], category: 'video')]
    case VIDEO_BMPEG = 'VIDEO_BMPEG';

    #[MimeTypeInfo(mimeType: 'video/bt656', extensions: [], category: 'video')]
    case VIDEO_BT656 = 'VIDEO_BT656';

    #[MimeTypeInfo(mimeType: 'video/celb', extensions: [], category: 'video')]
    case VIDEO_CELB = 'VIDEO_CELB';

    #[MimeTypeInfo(mimeType: 'video/dv', extensions: [], category: 'video')]
    case VIDEO_DV = 'VIDEO_DV';

    #[MimeTypeInfo(mimeType: 'video/encaprtp', extensions: [], category: 'video')]
    case VIDEO_ENCAPRTP = 'VIDEO_ENCAPRTP';

    #[MimeTypeInfo(mimeType: 'video/evc', extensions: [], category: 'video')]
    case VIDEO_EVC = 'VIDEO_EVC';

    #[MimeTypeInfo(mimeType: 'video/example', extensions: [], category: 'video')]
    case VIDEO_EXAMPLE = 'VIDEO_EXAMPLE';

    #[MimeTypeInfo(mimeType: 'video/ffv1', extensions: [], category: 'video')]
    case VIDEO_FFV1 = 'VIDEO_FFV1';

    #[MimeTypeInfo(mimeType: 'video/flexfec', extensions: [], category: 'video')]
    case VIDEO_FLEXFEC = 'VIDEO_FLEXFEC';

    #[MimeTypeInfo(mimeType: 'video/h261', extensions: ['h261'], category: 'video')]
    case VIDEO_H261 = 'VIDEO_H261';

    #[MimeTypeInfo(mimeType: 'video/h263', extensions: ['h263'], category: 'video')]
    case VIDEO_H263 = 'VIDEO_H263';

    #[MimeTypeInfo(mimeType: 'video/h263-1998', extensions: [], category: 'video')]
    case VIDEO_H263_1998 = 'VIDEO_H263_1998';

    #[MimeTypeInfo(mimeType: 'video/h263-2000', extensions: [], category: 'video')]
    case VIDEO_H263_2000 = 'VIDEO_H263_2000';

    #[MimeTypeInfo(mimeType: 'video/h264', extensions: ['h264'], category: 'video')]
    case VIDEO_H264 = 'VIDEO_H264';

    #[MimeTypeInfo(mimeType: 'video/h264-rcdo', extensions: [], category: 'video')]
    case VIDEO_H264_RCDO = 'VIDEO_H264_RCDO';

    #[MimeTypeInfo(mimeType: 'video/h264-svc', extensions: [], category: 'video')]
    case VIDEO_H264_SVC = 'VIDEO_H264_SVC';

    #[MimeTypeInfo(mimeType: 'video/h265', extensions: [], category: 'video')]
    case VIDEO_H265 = 'VIDEO_H265';

    #[MimeTypeInfo(mimeType: 'video/h266', extensions: [], category: 'video')]
    case VIDEO_H266 = 'VIDEO_H266';

    #[MimeTypeInfo(mimeType: 'video/iso.segment', extensions: ['m4s'], category: 'video')]
    case VIDEO_ISO_SEGMENT = 'VIDEO_ISO_SEGMENT';

    #[MimeTypeInfo(mimeType: 'video/jpeg', extensions: ['jpgv'], category: 'video')]
    case VIDEO_JPEG = 'VIDEO_JPEG';

    #[MimeTypeInfo(mimeType: 'video/jpeg2000', extensions: [], category: 'video')]
    case VIDEO_JPEG2000 = 'VIDEO_JPEG2000';

    #[MimeTypeInfo(mimeType: 'video/jpeg2000-scl', extensions: [], category: 'video')]
    case VIDEO_JPEG2000_SCL = 'VIDEO_JPEG2000_SCL';

    #[MimeTypeInfo(mimeType: 'video/jpm', extensions: ['jpm', 'jpgm'], category: 'video')]
    case VIDEO_JPM = 'VIDEO_JPM';

    #[MimeTypeInfo(mimeType: 'video/jxsv', extensions: [], category: 'video')]
    case VIDEO_JXSV = 'VIDEO_JXSV';

    #[MimeTypeInfo(mimeType: 'video/lottie+json', extensions: ['lot'], category: 'video')]
    case VIDEO_LOTTIE_JSON = 'VIDEO_LOTTIE_JSON';

    #[MimeTypeInfo(mimeType: 'video/matroska', extensions: ['mkv'], category: 'video')]
    case VIDEO_MATROSKA = 'VIDEO_MATROSKA';

    #[MimeTypeInfo(mimeType: 'video/matroska-3d', extensions: ['mk3d'], category: 'video')]
    case VIDEO_MATROSKA_3D = 'VIDEO_MATROSKA_3D';

    #[MimeTypeInfo(mimeType: 'video/mj2', extensions: ['mj2', 'mjp2'], category: 'video')]
    case VIDEO_MJ2 = 'VIDEO_MJ2';

    #[MimeTypeInfo(mimeType: 'video/mp1s', extensions: [], category: 'video')]
    case VIDEO_MP1S = 'VIDEO_MP1S';

    #[MimeTypeInfo(mimeType: 'video/mp2p', extensions: [], category: 'video')]
    case VIDEO_MP2P = 'VIDEO_MP2P';

    #[MimeTypeInfo(mimeType: 'video/mp2t', extensions: ['ts', 'm2t', 'm2ts', 'mts'], category: 'video')]
    case VIDEO_MP2T = 'VIDEO_MP2T';

    #[MimeTypeInfo(mimeType: 'video/mp4', extensions: ['mp4', 'mp4v', 'mpg4'], category: 'video')]
    case VIDEO_MP4 = 'VIDEO_MP4';

    #[MimeTypeInfo(mimeType: 'video/mp4v-es', extensions: [], category: 'video')]
    case VIDEO_MP4V_ES = 'VIDEO_MP4V_ES';

    #[MimeTypeInfo(mimeType: 'video/mpeg', extensions: ['mpeg', 'mpg', 'mpe', 'm1v', 'm2v'], category: 'video')]
    case VIDEO_MPEG = 'VIDEO_MPEG';

    #[MimeTypeInfo(mimeType: 'video/mpeg4-generic', extensions: [], category: 'video')]
    case VIDEO_MPEG4_GENERIC = 'VIDEO_MPEG4_GENERIC';

    #[MimeTypeInfo(mimeType: 'video/mpv', extensions: [], category: 'video')]
    case VIDEO_MPV = 'VIDEO_MPV';

    #[MimeTypeInfo(mimeType: 'video/nv', extensions: [], category: 'video')]
    case VIDEO_NV = 'VIDEO_NV';

    #[MimeTypeInfo(mimeType: 'video/ogg', extensions: ['ogv'], category: 'video')]
    case VIDEO_OGG = 'VIDEO_OGG';

    #[MimeTypeInfo(mimeType: 'video/parityfec', extensions: [], category: 'video')]
    case VIDEO_PARITYFEC = 'VIDEO_PARITYFEC';

    #[MimeTypeInfo(mimeType: 'video/pointer', extensions: [], category: 'video')]
    case VIDEO_POINTER = 'VIDEO_POINTER';

    #[MimeTypeInfo(mimeType: 'video/quicktime', extensions: ['qt', 'mov'], category: 'video')]
    case VIDEO_QUICKTIME = 'VIDEO_QUICKTIME';

    #[MimeTypeInfo(mimeType: 'video/raptorfec', extensions: [], category: 'video')]
    case VIDEO_RAPTORFEC = 'VIDEO_RAPTORFEC';

    #[MimeTypeInfo(mimeType: 'video/raw', extensions: [], category: 'video')]
    case VIDEO_RAW = 'VIDEO_RAW';

    #[MimeTypeInfo(mimeType: 'video/rtp-enc-aescm128', extensions: [], category: 'video')]
    case VIDEO_RTP_ENC_AESCM128 = 'VIDEO_RTP_ENC_AESCM128';

    #[MimeTypeInfo(mimeType: 'video/rtploopback', extensions: [], category: 'video')]
    case VIDEO_RTPLOOPBACK = 'VIDEO_RTPLOOPBACK';

    #[MimeTypeInfo(mimeType: 'video/rtx', extensions: [], category: 'video')]
    case VIDEO_RTX = 'VIDEO_RTX';

    #[MimeTypeInfo(mimeType: 'video/scip', extensions: [], category: 'video')]
    case VIDEO_SCIP = 'VIDEO_SCIP';

    #[MimeTypeInfo(mimeType: 'video/smpte291', extensions: [], category: 'video')]
    case VIDEO_SMPTE291 = 'VIDEO_SMPTE291';

    #[MimeTypeInfo(mimeType: 'video/smpte292m', extensions: [], category: 'video')]
    case VIDEO_SMPTE292M = 'VIDEO_SMPTE292M';

    #[MimeTypeInfo(mimeType: 'video/ulpfec', extensions: [], category: 'video')]
    case VIDEO_ULPFEC = 'VIDEO_ULPFEC';

    #[MimeTypeInfo(mimeType: 'video/vc1', extensions: [], category: 'video')]
    case VIDEO_VC1 = 'VIDEO_VC1';

    #[MimeTypeInfo(mimeType: 'video/vc2', extensions: [], category: 'video')]
    case VIDEO_VC2 = 'VIDEO_VC2';

    #[MimeTypeInfo(mimeType: 'video/vnd.blockfact.factv', extensions: ['factv'], category: 'video')]
    case VIDEO_VND_BLOCKFACT_FACTV = 'VIDEO_VND_BLOCKFACT_FACTV';

    #[MimeTypeInfo(mimeType: 'video/vnd.cctv', extensions: [], category: 'video')]
    case VIDEO_VND_CCTV = 'VIDEO_VND_CCTV';

    #[MimeTypeInfo(mimeType: 'video/vnd.dece.hd', extensions: ['uvh', 'uvvh'], category: 'video')]
    case VIDEO_VND_DECE_HD = 'VIDEO_VND_DECE_HD';

    #[MimeTypeInfo(mimeType: 'video/vnd.dece.mobile', extensions: ['uvm', 'uvvm'], category: 'video')]
    case VIDEO_VND_DECE_MOBILE = 'VIDEO_VND_DECE_MOBILE';

    #[MimeTypeInfo(mimeType: 'video/vnd.dece.mp4', extensions: ['uvu', 'uvvu'], category: 'video')]
    case VIDEO_VND_DECE_MP4 = 'VIDEO_VND_DECE_MP4';

    #[MimeTypeInfo(mimeType: 'video/vnd.dece.pd', extensions: ['uvp', 'uvvp'], category: 'video')]
    case VIDEO_VND_DECE_PD = 'VIDEO_VND_DECE_PD';

    #[MimeTypeInfo(mimeType: 'video/vnd.dece.sd', extensions: ['uvs', 'uvvs'], category: 'video')]
    case VIDEO_VND_DECE_SD = 'VIDEO_VND_DECE_SD';

    #[MimeTypeInfo(mimeType: 'video/vnd.dece.video', extensions: ['uvv', 'uvvv'], category: 'video')]
    case VIDEO_VND_DECE_VIDEO = 'VIDEO_VND_DECE_VIDEO';

    #[MimeTypeInfo(mimeType: 'video/vnd.directv.mpeg', extensions: [], category: 'video')]
    case VIDEO_VND_DIRECTV_MPEG = 'VIDEO_VND_DIRECTV_MPEG';

    #[MimeTypeInfo(mimeType: 'video/vnd.directv.mpeg-tts', extensions: [], category: 'video')]
    case VIDEO_VND_DIRECTV_MPEG_TTS = 'VIDEO_VND_DIRECTV_MPEG_TTS';

    #[MimeTypeInfo(mimeType: 'video/vnd.dlna.mpeg-tts', extensions: [], category: 'video')]
    case VIDEO_VND_DLNA_MPEG_TTS = 'VIDEO_VND_DLNA_MPEG_TTS';

    #[MimeTypeInfo(mimeType: 'video/vnd.dvb.file', extensions: ['dvb'], category: 'video')]
    case VIDEO_VND_DVB_FILE = 'VIDEO_VND_DVB_FILE';

    #[MimeTypeInfo(mimeType: 'video/vnd.fvt', extensions: ['fvt'], category: 'video')]
    case VIDEO_VND_FVT = 'VIDEO_VND_FVT';

    #[MimeTypeInfo(mimeType: 'video/vnd.hns.video', extensions: ['rm'], category: 'video')]
    case VIDEO_VND_HNS_VIDEO = 'VIDEO_VND_HNS_VIDEO';

    #[MimeTypeInfo(mimeType: 'video/vnd.iptvforum.1dparityfec-1010', extensions: [], category: 'video')]
    case VIDEO_VND_IPTVFORUM_1DPARITYFEC_1010 = 'VIDEO_VND_IPTVFORUM_1DPARITYFEC_1010';

    #[MimeTypeInfo(mimeType: 'video/vnd.iptvforum.1dparityfec-2005', extensions: [], category: 'video')]
    case VIDEO_VND_IPTVFORUM_1DPARITYFEC_2005 = 'VIDEO_VND_IPTVFORUM_1DPARITYFEC_2005';

    #[MimeTypeInfo(mimeType: 'video/vnd.iptvforum.2dparityfec-1010', extensions: [], category: 'video')]
    case VIDEO_VND_IPTVFORUM_2DPARITYFEC_1010 = 'VIDEO_VND_IPTVFORUM_2DPARITYFEC_1010';

    #[MimeTypeInfo(mimeType: 'video/vnd.iptvforum.2dparityfec-2005', extensions: [], category: 'video')]
    case VIDEO_VND_IPTVFORUM_2DPARITYFEC_2005 = 'VIDEO_VND_IPTVFORUM_2DPARITYFEC_2005';

    #[MimeTypeInfo(mimeType: 'video/vnd.iptvforum.ttsavc', extensions: [], category: 'video')]
    case VIDEO_VND_IPTVFORUM_TTSAVC = 'VIDEO_VND_IPTVFORUM_TTSAVC';

    #[MimeTypeInfo(mimeType: 'video/vnd.iptvforum.ttsmpeg2', extensions: [], category: 'video')]
    case VIDEO_VND_IPTVFORUM_TTSMPEG2 = 'VIDEO_VND_IPTVFORUM_TTSMPEG2';

    #[MimeTypeInfo(mimeType: 'video/vnd.motorola.video', extensions: [], category: 'video')]
    case VIDEO_VND_MOTOROLA_VIDEO = 'VIDEO_VND_MOTOROLA_VIDEO';

    #[MimeTypeInfo(mimeType: 'video/vnd.motorola.videop', extensions: [], category: 'video')]
    case VIDEO_VND_MOTOROLA_VIDEOP = 'VIDEO_VND_MOTOROLA_VIDEOP';

    #[MimeTypeInfo(mimeType: 'video/vnd.mpegurl', extensions: ['mxu', 'm4u'], category: 'video')]
    case VIDEO_VND_MPEGURL = 'VIDEO_VND_MPEGURL';

    #[MimeTypeInfo(mimeType: 'video/vnd.ms-playready.media.pyv', extensions: ['pyv'], category: 'video')]
    case VIDEO_VND_MS_PLAYREADY_MEDIA_PYV = 'VIDEO_VND_MS_PLAYREADY_MEDIA_PYV';

    #[MimeTypeInfo(mimeType: 'video/vnd.nokia.interleaved-multimedia', extensions: ['nim'], category: 'video')]
    case VIDEO_VND_NOKIA_INTERLEAVED_MULTIMEDIA = 'VIDEO_VND_NOKIA_INTERLEAVED_MULTIMEDIA';

    #[MimeTypeInfo(mimeType: 'video/vnd.nokia.mp4vr', extensions: ['mp4'], category: 'video')]
    case VIDEO_VND_NOKIA_MP4VR = 'VIDEO_VND_NOKIA_MP4VR';

    #[MimeTypeInfo(mimeType: 'video/vnd.nokia.videovoip', extensions: [], category: 'video')]
    case VIDEO_VND_NOKIA_VIDEOVOIP = 'VIDEO_VND_NOKIA_VIDEOVOIP';

    #[MimeTypeInfo(mimeType: 'video/vnd.objectvideo', extensions: ['mp4'], category: 'video')]
    case VIDEO_VND_OBJECTVIDEO = 'VIDEO_VND_OBJECTVIDEO';

    #[MimeTypeInfo(mimeType: 'video/vnd.planar', extensions: ['yuv'], category: 'video')]
    case VIDEO_VND_PLANAR = 'VIDEO_VND_PLANAR';

    #[MimeTypeInfo(mimeType: 'video/vnd.radgamettools.bink', extensions: ['bik', 'bk2'], category: 'video')]
    case VIDEO_VND_RADGAMETTOOLS_BINK = 'VIDEO_VND_RADGAMETTOOLS_BINK';

    #[MimeTypeInfo(mimeType: 'video/vnd.radgamettools.smacker', extensions: ['smk'], category: 'video')]
    case VIDEO_VND_RADGAMETTOOLS_SMACKER = 'VIDEO_VND_RADGAMETTOOLS_SMACKER';

    #[MimeTypeInfo(mimeType: 'video/vnd.sealed.mpeg1', extensions: ['smpg', 's11'], category: 'video')]
    case VIDEO_VND_SEALED_MPEG1 = 'VIDEO_VND_SEALED_MPEG1';

    #[MimeTypeInfo(mimeType: 'video/vnd.sealed.mpeg4', extensions: ['smpg', 's14'], category: 'video')]
    case VIDEO_VND_SEALED_MPEG4 = 'VIDEO_VND_SEALED_MPEG4';

    #[MimeTypeInfo(mimeType: 'video/vnd.sealed.swf', extensions: ['sswf', 'ssw'], category: 'video')]
    case VIDEO_VND_SEALED_SWF = 'VIDEO_VND_SEALED_SWF';

    #[MimeTypeInfo(mimeType: 'video/vnd.sealedmedia.softseal.mov', extensions: ['smov', 'smo', 's1q'], category: 'video')]
    case VIDEO_VND_SEALEDMEDIA_SOFTSEAL_MOV = 'VIDEO_VND_SEALEDMEDIA_SOFTSEAL_MOV';

    #[MimeTypeInfo(mimeType: 'video/vnd.uvvu.mp4', extensions: ['uvu', 'uvvu'], category: 'video')]
    case VIDEO_VND_UVVU_MP4 = 'VIDEO_VND_UVVU_MP4';

    #[MimeTypeInfo(mimeType: 'video/vnd.vivo', extensions: ['viv'], category: 'video')]
    case VIDEO_VND_VIVO = 'VIDEO_VND_VIVO';

    #[MimeTypeInfo(mimeType: 'video/vnd.youtube.yt', extensions: ['yt'], category: 'video')]
    case VIDEO_VND_YOUTUBE_YT = 'VIDEO_VND_YOUTUBE_YT';

    #[MimeTypeInfo(mimeType: 'video/vp8', extensions: [], category: 'video')]
    case VIDEO_VP8 = 'VIDEO_VP8';

    #[MimeTypeInfo(mimeType: 'video/vp9', extensions: [], category: 'video')]
    case VIDEO_VP9 = 'VIDEO_VP9';

    #[MimeTypeInfo(mimeType: 'video/webm', extensions: ['webm'], category: 'video')]
    case VIDEO_WEBM = 'VIDEO_WEBM';

    #[MimeTypeInfo(mimeType: 'video/x-f4v', extensions: ['f4v'], category: 'video')]
    case VIDEO_X_F4V = 'VIDEO_X_F4V';

    #[MimeTypeInfo(mimeType: 'video/x-fli', extensions: ['fli'], category: 'video')]
    case VIDEO_X_FLI = 'VIDEO_X_FLI';

    #[MimeTypeInfo(mimeType: 'video/x-flv', extensions: ['flv'], category: 'video')]
    case VIDEO_X_FLV = 'VIDEO_X_FLV';

    #[MimeTypeInfo(mimeType: 'video/x-m4v', extensions: ['m4v'], category: 'video')]
    case VIDEO_X_M4V = 'VIDEO_X_M4V';

    #[MimeTypeInfo(mimeType: 'video/x-matroska', extensions: ['mkv', 'mk3d', 'mks'], category: 'video')]
    case VIDEO_X_MATROSKA = 'VIDEO_X_MATROSKA';

    #[MimeTypeInfo(mimeType: 'video/x-mng', extensions: ['mng'], category: 'video')]
    case VIDEO_X_MNG = 'VIDEO_X_MNG';

    #[MimeTypeInfo(mimeType: 'video/x-ms-asf', extensions: ['asf', 'asx'], category: 'video')]
    case VIDEO_X_MS_ASF = 'VIDEO_X_MS_ASF';

    #[MimeTypeInfo(mimeType: 'video/x-ms-vob', extensions: ['vob'], category: 'video')]
    case VIDEO_X_MS_VOB = 'VIDEO_X_MS_VOB';

    #[MimeTypeInfo(mimeType: 'video/x-ms-wm', extensions: ['wm'], category: 'video')]
    case VIDEO_X_MS_WM = 'VIDEO_X_MS_WM';

    #[MimeTypeInfo(mimeType: 'video/x-ms-wmv', extensions: ['wmv'], category: 'video')]
    case VIDEO_X_MS_WMV = 'VIDEO_X_MS_WMV';

    #[MimeTypeInfo(mimeType: 'video/x-ms-wmx', extensions: ['wmx'], category: 'video')]
    case VIDEO_X_MS_WMX = 'VIDEO_X_MS_WMX';

    #[MimeTypeInfo(mimeType: 'video/x-ms-wvx', extensions: ['wvx'], category: 'video')]
    case VIDEO_X_MS_WVX = 'VIDEO_X_MS_WVX';

    #[MimeTypeInfo(mimeType: 'video/x-msvideo', extensions: ['avi'], category: 'video')]
    case VIDEO_X_MSVIDEO = 'VIDEO_X_MSVIDEO';

    #[MimeTypeInfo(mimeType: 'video/x-sgi-movie', extensions: ['movie'], category: 'video')]
    case VIDEO_X_SGI_MOVIE = 'VIDEO_X_SGI_MOVIE';

    #[MimeTypeInfo(mimeType: 'video/x-smv', extensions: ['smv'], category: 'video')]
    case VIDEO_X_SMV = 'VIDEO_X_SMV';

    #[MimeTypeInfo(mimeType: 'x-conference/x-cooltalk', extensions: ['ice'], category: 'x-conference')]
    case X_CONFERENCE_X_COOLTALK = 'X_CONFERENCE_X_COOLTALK';

    #[MimeTypeInfo(mimeType: 'x-shader/x-fragment', extensions: [], category: 'x-shader')]
    case X_SHADER_X_FRAGMENT = 'X_SHADER_X_FRAGMENT';

    #[MimeTypeInfo(mimeType: 'x-shader/x-vertex', extensions: [], category: 'x-shader')]
    case X_SHADER_X_VERTEX = 'X_SHADER_X_VERTEX';

    /**
     * Lazily initializes and returns the MIME type data map.
     *
     * @return array<string, array{mimeType: string, extensions: array<int,string>, category: string}>
     */
    private static function mimeTypeData(): array
    {
        // Define data extractor function to transform MimeTypeInfo attributes into array format
        $dataExtractor = fn ($info, $caseValue) => [
            'mimeType' => $info->mimeType,
            'extensions' => $info->extensions,
            'category' => $info->category,
        ];

        // Build lazy data map using EnumDataUtils to cache MIME type data for all enum cases
        $result = EnumDataUtils::buildLazyDataMap(
            self::class,
            MimeTypeInfo::class,
            $dataExtractor
        );

        // Clean up variables to free up memory
        unset(
            $dataExtractor
        );

        // Return the cached data map containing MIME type information for all enum cases
        return $result;
    }

    /**
     * Get the MIME type string.
     *
     * @return string The MIME type string
     */
    public function mimeType(): string
    {
        // Resolve from cached MIME type data
        $data = self::mimeTypeData();

        // Throw if MIME type is not found
        return EnumDataUtils::getFieldValue(
            $data,
            $this->value,
            'mimeType',
            'mime type'
        );
    }

    /**
     * Get the file extensions associated with this MIME type.
     *
     * @return array<int,string> Array of file extensions
     */
    public function extensions(): array
    {
        // Resolve from cached MIME type data
        $data = self::mimeTypeData();

        // Throw if MIME type is not found
        return EnumDataUtils::getArrayFieldValue(
            $data,
            $this->value,
            'extensions',
            'mime type'
        );
    }

    /**
     * Get the primary file extension (first in the array).
     *
     * @return ?string The primary file extension or null if none
     */
    public function primaryExtension(): ?string
    {
        // Get the array of file extensions associated with this MIME type
        $extensions = $this->extensions();

        // Return the first extension if available, otherwise return null
        return ! empty($extensions) ? $extensions[0] : null;
    }

    /**
     * Get the MIME type category.
     *
     * @return string The MIME type category
     */
    public function category(): string
    {
        // Resolve from cached MIME type data
        $data = self::mimeTypeData();

        // Throw if MIME type is not found
        return EnumDataUtils::getFieldValue(
            $data,
            $this->value,
            'category',
            'mime type'
        );
    }

    /**
     * Find an enum case by its MIME type string.
     *
     * @param  string  $mimeType  The MIME type to search for (e.g., 'application/pdf')
     * @return ?self The matching enum case or null if not found
     */
    public static function findByMimeType(
        string $mimeType
    ): ?self {
        // Loop through all enum cases and return the first one that matches the MIME type
        foreach (self::cases() as $case) {
            // Check if the current case's MIME type matches the MIME type
            if ($case->mimeType() === $mimeType) {
                // Return the current case
                return $case;
            }
        }

        // If no match is found, return null
        return null;
    }

    /**
     * Find an enum case by one of its file extensions.
     *
     * @param  string  $extension  The file extension to search for (e.g., 'pdf', 'jpg')
     * @return ?self The matching enum case or null if not found
     */
    public static function findByExtension(
        string $extension
    ): ?self {
        // Normalize extension (remove leading dot if present, convert to lowercase)
        $normalizedExtension = \strtolower(\ltrim(
            $extension,
            '.'
        ));

        // Loop through all enum cases and return the first one that matches the file extension
        foreach (self::cases() as $case) {
            // Get the extensions for the current case
            $extensions = $case->extensions();

            // Loop through all extensions for the current case and return the first one that matches the file extension
            foreach ($extensions as $caseExtension) {
                // Check if the current extension matches the normalized extension
                if (\strtolower($caseExtension) === $normalizedExtension) {
                    // Return the current case
                    return $case;
                }
            }
        }

        // If no match is found, return null
        return null;
    }

    /**
     * Find all enum cases by category.
     *
     * @param  string  $category  The category to search for (e.g., 'image', 'application')
     * @return array<int,self> Array of matching enum cases
     */
    public static function findByCategory(
        string $category
    ): array {
        // Initialize the matching cases array
        $matchingCases = [];

        // Loop through all enum cases and collect those that match the category
        foreach (self::cases() as $case) {
            // Check if the current case's category matches the category
            if ($case->category() === $category) {
                // Add the current case to the matching cases array
                $matchingCases[] = $case;
            }
        }

        // Return the array of enum cases that match the specified category
        return $matchingCases;
    }

    /**
     * Find all enum cases by multiple categories.
     *
     * @param  array<int,string>  $categories  Array of categories to search for
     * @return array<int,self> Array of matching enum cases
     */
    public static function findByCategories(
        array $categories
    ): array {
        // Initialize the matching cases array
        $matchingCases = [];

        // Loop through all enum cases and collect those that match any of the categories
        foreach (self::cases() as $case) {
            // Check if the current case's category matches any of the categories
            if (
                \in_array(
                    $case->category(),
                    $categories,
                    true
                )
            ) {
                // Add the current case to the matching cases array
                $matchingCases[] = $case;
            }
        }

        // Return the array of enum cases that match any of the specified categories
        return $matchingCases;
    }

    /**
     * Get all unique MIME type categories.
     *
     * @return array<int,string> Array of unique category names (e.g., ['application', 'image', 'audio', 'video', 'text', 'font', 'model', 'message', 'chemical', 'x-conference'])
     */
    public static function getCategories(): array
    {
        // Initialize the categories array to store unique category names
        $categories = [];

        // Loop through all enum cases and collect unique categories
        foreach (self::cases() as $case) {
            // Get the category for the current enum case
            $category = $case->category();

            // Check if the category is not already in the categories array
            if (
                ! \in_array(
                    $category,
                    $categories,
                    true
                )
            ) {
                // Add the category to the categories array
                $categories[] = $category;
            }
        }

        // Sort categories alphabetically for consistent ordering
        sort($categories);

        // Return the array of unique category names
        return $categories;
    }
}
