/*  University of Arkansas for Medical Sciences - Alert 2.0 Beta
 *  Based on UW Alert Banner - https://github.com/uweb/UW-Alert-Banner
 *  (c) 2011 Chris Heiland
 *
 *  Script should be included like such:
 *
 *  <html>
 *  <head>
 *  <title>Page Title</title>
 *  </head>
 *  <body>
 *
 *  <script type="text/javascript" src="//www.uams.edu/web/alert/uamsalert.js"></script>
 *  </body>
 *  </html>
 *
 *  Original docs at:
 *  uw.edu/marketing/uw-alert-banner/
 *
 *--------------------------------------------------------------------------*/

import { sanitizeAlertHtml, safeAlertUrl, splitTeaser } from './uamsalert-sanitize.js';
import { resolveSiteStatus } from './uamsalert-site.js';

// Thanks Dane!
var strTestStatus = window.location.hash.indexOf('uamsalert') === -1 ? 'false' : 'true';
// Allow for local testing
var strDomain = (window.location.hostname == 'localhost') ? 'localhost' : 'www.uams.edu/web/alert';
var strDataFeed = '/data/?c=displayAlert&test='+strTestStatus;
var strBaseUrl = window.location.protocol + '//' + strDomain;
// var strBaseUrl = 'http://' + strDomain; // Test
// Statamic emits the resolved alert bucket as `data-uams-alert-site` on
// <body> (see the `{{ uams_alert_site_key }}` tag + `config/uamsalert.php`),
// replacing the WordPress theme's body-class names that Statamic never emits.
var strSiteStatus = resolveSiteStatus(document.body);

// The remote feed responds with JSONP that calls `displayAlert(...)`. Bundled
// by Vite this entry is `type="module"`, so the hoisted function is
// module-scoped — expose it on `window` so the feed's callback can reach it.
window.displayAlert = displayAlert;

var strScript = document.createElement('script');
strScript.setAttribute('src', strBaseUrl + strDataFeed);
document.getElementsByTagName('head')[0].appendChild(strScript);

// displayAlert - grab content to display message
function displayAlert(objAlertData)
{

    // Just in case w.com delivers us something bad
    // or We don't care if the feed is null
    if ((!objAlertData) || (objAlertData.found == 0))
        return false;

    var alertUrgent = "urgent-"+ strSiteStatus;
    var alertAlert = "alert-"+ strSiteStatus;
    var alertFyi = "fyi-"+ strSiteStatus;
    var alertTest = strSiteStatus;

    console.log( alertTest );
    console.log( objAlertData.posts.length );

    // Alert colors. Declared with `var` — the entry is now an ES module
    // (strict mode), which throws on assignment to an undeclared identifier.
    var arrAlertTypes = {};
        arrAlertTypes[alertUrgent] = "uamsalert-urgent";
        arrAlertTypes[alertAlert] = "uamsalert-alert";
        arrAlertTypes[alertFyi] = "uamsalert-fyi";

    // };
    for (var i = 0; i < objAlertData.posts.length; i++) {
        var arrCategories = objAlertData.posts[i].categories;
        // If there is a test alert
        if ( window.location.hash.indexOf('uamsalert') != -1 )
        {
            // Sometimes we don't get a category from the w.com test feed
            var objFakeCat = new Object();
            var strTestAlertColor = window.location.hash.replace('#','');
            objFakeCat.slug = strTestAlertColor;
            arrCategories['Fake Category'] = objFakeCat;
        }

        var strSuccess = false;
        for (var strCategory in arrCategories)
        {
            var objCategory = arrCategories[strCategory];
            // Quick way to determine color
            if (arrAlertTypes[objCategory.slug] || objFakeCat)
            {
                var strAlertTitle    = objAlertData.posts[i].title;
                var strAlertLink     = objAlertData.posts[i].URL;
                // var strAlertMessage  = objAlertData.posts[0].excerpt; // Not used
                var strAlertContent  = objAlertData.posts[i].content;
                var strAlertColor    = arrAlertTypes[objCategory.slug] ? arrAlertTypes[objCategory.slug] : objFakeCat.slug;
                strSuccess           = true;
            }
        }

        // Banners must have an actual color
        // Don't load anything unless we have something to present
        if (strSuccess)
        {
            addElement(strAlertTitle,strAlertLink,strAlertColor,strAlertContent); // Removed strAlertMessage

            // For the Statamic CMS, uamsalert.css is loaded via Vite in the layout; no need to inject a link here.

            setTimeout(function() {
                var strHeight = document.getElementById('uamsalert-alert-message').offsetHeight;
                var bodyTag = document.getElementsByTagName('body')[0];
                bodyTag.style.backgroundPosition='0px '+strHeight+'px';
            },10);
            break;
        }
    }
}

// addElement - display HTML on page right below the body page
// don't want the alert to show up randomly
function addElement(strAlertTitle,strAlertLink,strAlertColor,strAlertContent) // Removed strAlertMessage
{
    // Grab the tag to start the party
    var bodyTag = document.getElementsByTagName('body')[0];

    bodyTag.style.margin = '0px';
    bodyTag.style.padding = '0px';
    bodyTag.className += ' uams-alert';

    var wrapperSection = document.createElement('section');
    wrapperSection.setAttribute('id','uamsalert-alert-message');
    wrapperSection.setAttribute('class', 'uams-module less-padding cta-bar cta-bar-sm ' + strAlertColor + ' ' + strSiteStatus);

    var wrapperContainer = document.createElement('div');
    wrapperContainer.setAttribute('class', 'container-fluid');

    var wrapperRow = document.createElement('div');
    wrapperRow.setAttribute('class', 'row');

    var wrapperCol = document.createElement('div');
    wrapperCol.setAttribute('class', 'col-12');

    var wrapperInner = document.createElement('div');
    wrapperInner.setAttribute('class', 'inner-container');

    var wrapperHeading = document.createElement('div');
    wrapperHeading.setAttribute('class', 'cta-heading');

    var wrapperBody = document.createElement('div');
    wrapperBody.setAttribute('class', 'cta-body');

    var wrapperText = document.createElement('div');
    wrapperText.setAttribute('class', 'text-container');
    // Split on the teaser marker BEFORE sanitizing — DOMPurify strips HTML
    // comments, so the marker must be read while it still exists. The visible
    // (pre-marker) body is the rich feed HTML and is sanitized before display.
    var alertContentParts = splitTeaser(strAlertContent);
    wrapperText.innerHTML = sanitizeAlertHtml(alertContentParts.visible);
    if (alertContentParts.hasMore) {
        var alertLinkTrue = true; // Set to true if more link is used
    }

    var wrapperBtn = document.createElement('div');
    wrapperBtn.setAttribute('class', 'btn-container');

    var alertBoxTextDiv = document.createElement('div');
    alertBoxTextDiv.setAttribute('id','uamsalert-alert-inner');
    alertBoxTextDiv.setAttribute('class', strAlertColor + ' ' + strSiteStatus);

    var alertHeading = document.createElement('h1');

    // Header Text - No link. Feed title is plain text, so render it as text
    // (not HTML) to neutralize any markup the feed might carry.
    alertHeading.textContent = strAlertTitle;

    // Build the alert link
    var alertLink = document.createElement('a');
    alertLink.setAttribute('class', 'btn btn-white');
    alertLink.setAttribute('href', safeAlertUrl(strAlertLink));
    alertLink.setAttribute('title', strAlertTitle);
    var alertLinkText = document.createTextNode('Read More');
    alertLink.appendChild(alertLinkText);

    // Standard/Base Link - Inclement Weather
    var alertLinkBase = document.createElement('a');
    alertLinkBase.setAttribute('class', 'btn btn-outline-white');
    alertLinkBase.setAttribute('href', 'https://news.uams.edu/weather/');
    alertLinkBase.setAttribute('title', 'UAMS Inclement Weather');
    var alertLinkTextBase = document.createTextNode('All Updates');
    alertLinkBase.appendChild(alertLinkTextBase);
    var alertLinkBaseInclude = false; // Set to true if we want to include base link

    var alertLinkContainer = false;
    if(alertLinkTrue) {
        wrapperBtn.appendChild(alertLink); // Add more link to container
        alertLinkContainer = true; // Set to true so link container is added to body container
    }

    if(alertLinkBaseInclude) {
        wrapperBtn.appendChild(alertLinkBase); // Add standard / base link to container
        alertLinkContainer = true; // Set to true so link container is added to body container
    }

    // Start Building the Actual Div

    wrapperHeading.appendChild(alertHeading);

    wrapperBody.appendChild(wrapperText);

    // Add link container if needed
    if(alertLinkContainer) {
        wrapperBody.appendChild(wrapperBtn);
        wrapperSection.setAttribute('class', wrapperSection.getAttribute('class') + ' cta-bar-weighted');
    } else {
        wrapperSection.setAttribute('class', wrapperSection.getAttribute('class') + ' cta-bar-centered no-link');
    }

    wrapperInner.appendChild(wrapperHeading);
    wrapperInner.appendChild(wrapperBody);

    wrapperCol.appendChild(wrapperInner);

    wrapperRow.appendChild(wrapperCol);

    wrapperContainer.appendChild(wrapperRow);

    wrapperSection.appendChild(wrapperContainer);
    wrapperSection.appendChild(alertBoxTextDiv);

    bodyTag.insertBefore(wrapperSection, bodyTag.firstChild);
}
