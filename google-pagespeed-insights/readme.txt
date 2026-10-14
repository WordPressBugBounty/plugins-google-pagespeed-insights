=== Insights from Google PageSpeed ===
Contributors: mattkeys, plugstack
Tags: SEO, performance, page speed, pagespeed insights, core web vitals
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.2
Stable tag: 5.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Use Insights from Google PageSpeed to improve your site's performance, Core Web Vitals, search engine ranking and your visitors' browsing experience.

== Description ==

Google PageSpeed Insights is a tool that empowers you to make decisions that increase the performance of your website. Recommendations from Google PageSpeed are based upon current industry best practices for desktop and mobile web performance.

Through the addition of advanced data visualization, tagging, filtering, and snapshot technology, Insights from Google PageSpeed provides a comprehensive solution for any webmaster looking to increase their site performance, their search engine ranking, and their visitors' browsing experience.

Version 5 brings the plugin up to date with the PageSpeed Insights you know from pagespeed.web.dev: every page now gets all Lighthouse scores and real-user Core Web Vitals, in a new and faster dashboard in wp-admin.

= Detailed Page Reporting =

Sort your page reports by their PageSpeed score to prioritize the largest areas of opportunity on your site. Page reports utilize easy to read visualizations that allow you to quickly focus in on the most important data to improve your site's performance.

Each report lists the insights and diagnostics Google found, with the estimated savings, the resources involved and WordPress specific tips. You can also see the page's score history, a screenshot and a filmstrip of how it loads.

= Performance, Accessibility, Best Practices, SEO and AI Agents =

Every page is scored in all five Lighthouse categories, the same ones you see on pagespeed.web.dev. Performance comes with the lab metrics behind it: Largest Contentful Paint, Total Blocking Time, Cumulative Layout Shift, First Contentful Paint and Speed Index.

The AI Agents category (Lighthouse "Agentic Browsing") checks how well AI agents and answer engines can read and use your pages, for example whether you have a valid llms.txt file. Google still marks it as experimental.

= Core Web Vitals from Real Users =

When Google has enough Chrome UX Report data for a page or for your whole site, reports show how real visitors experienced it: LCP, INP and CLS, the share of good, average and poor visits, and whether the page passes the Core Web Vitals assessment.

= Report Summaries =

Report Summaries are a powerful and exclusive feature of Insights from Google PageSpeed. Summaries display your average scores, the largest areas for improvement across ALL reports, as well as your best and lowest performing pages. Report Summaries can be filtered to narrow results by: Pages, Posts, Categories, Custom URLs, and Custom Post Types.

Using Report Summaries allows you to 'zoom out' from the page level and better understand the big picture of your site's performance. Charts show how your scores change over time and how many of your pages are good, need work or are poor.

= Desktop and Mobile Page Reports =

Best practices for site performance differ greatly between Desktop and Mobile device usage. Configure Insights from Google PageSpeed to generate Desktop reports, Mobile reports, or both!

Toggle back and forth between report types to see specific suggestions for improving the performance and experience on each platform.

= Report Snapshots =

The Report Snapshot tool builds on the power of Report Summaries, to provide historical "Point In Time" data about your website.

Take a snapshot of any Report Summary screen to store that data for future retrieval. Add comments to your snapshots to provide additional meaning, such as "Before Installing W3 Total Cache." Additionally, filter a Report Summary before taking a Snapshot to save a summary of the filtered data.

= Snapshot Comparison Tool =

The Snapshot Comparison Tool is an amazing utility that lets you visualize side-by-side results from any two similar Report Snapshots.

Take a Report Snapshot before installing new plugins, or before implementing performance recommendations. Take another snapshot when you are finished and compare your results to measure the effect of your changes.

= Add/Import Custom URLs =

Easily add additional URLs for inclusion in PageSpeed Reports. Even add URLs for non-WordPress sites, even if they are not hosted on your server. URLs can be added manually, imported from a CSV file (such as a Search Console export), or you can upload a properly formatted XML sitemap to add multiple pages at once.

Custom URLs can be tagged and filtered in Report Summaries. Take Report Snapshots of your Custom URLs just like you would with any other report type.

= Scheduled Report Checks =

Configure Insights from Google PageSpeed to automatically recheck your site on a Daily, Weekly, Bi-Monthly, or Monthly basis.

With each scan, any new pages or posts will be discovered, and existing reports will be updated to reflect any changes to your site, as well as any changes in the PageSpeed recommendations.

= Additional Languages =

Translations are managed on translate.wordpress.org, and WordPress installs the one for your site's language automatically. Thanks to the translators of earlier versions:

* Russian Translation provided by: Ivanka from [coupofy.com](http://coupofy.com)
* Spanish Translation provided by: Ogi Djuraskovic from [firstsiteguide.com](http://firstsiteguide.com)
* Serbian Translation provided by: Ogi Djuraskovic from [firstsiteguide.com](http://firstsiteguide.com)

== Installation ==

1. In wp-admin, go to Plugins > Add New, search for "Insights from Google PageSpeed" and click "Install Now", then "Activate".
2. Go to Tools > Pagespeed Insights > Settings and enter your Google API key.
3. Click "Start scan".

Full documentation: [plugstack.dev/docs/google-pagespeed-insights](https://plugstack.dev/docs/google-pagespeed-insights/)

= Getting an API key =

The plugin uses the free Google PageSpeed Insights API, which needs an API key from your Google account.

1. Open the [PageSpeed Insights API in the Google Cloud Console](https://console.cloud.google.com/apis/library/pagespeedonline.googleapis.com), create or pick a project and click "Enable".
2. Go to APIs & Services > Credentials and create an API key.
3. Paste the key into Tools > Pagespeed Insights > Settings, click "Check key" and save.

If you restrict the key, allow requests from your server's IP address. HTTP referrer restrictions do not work, because the requests are sent by your server.

A [step-by-step guide with screenshots](https://plugstack.dev/docs/google-pagespeed-insights/google-api-key/) is in the documentation.

= Requirements =

* WordPress 6.6 or newer
* PHP 7.2 or newer
* A Google API key
* Pages must be publicly reachable, because Google loads them from its own servers

== Frequently Asked Questions ==

= I entered my API key, but no reports show up. =

* Google needs to load each page to create its report, so your pages must be publicly accessible on the internet. Local and password protected sites can't be checked; add public URLs under Custom URLs instead.
* Check that the key is correct and that the "PageSpeed Insights API" is enabled for it in the Google Cloud Console.
* Turn on "Log API errors" in Settings > Advanced and look at the logs after the next scan.

= Scans stop before every page is checked. =

* Your server may be stopping the scan request. Raise "Maximum execution time" in Settings > Advanced.
* Some hosts don't allow that. In that case set "Maximum script run time": the scan then restarts itself after that time. Start low (30 seconds) and raise it step by step.

= The scan does not start. =

Scans run in the background through a request from your site to itself (a "loopback" request). If a maintenance or "coming soon" mode, a plugin that requires a login for the whole site, or your host blocks that request, the plugin shows a warning after about 20 seconds. Tools > Site Health shows whether loopback requests work.

= A page was added to Ignored URLs. =

Google could not load it. Make sure the page is public, then click "Reactivate" on the Ignored screen to try again. You can also test the page at [pagespeed.web.dev](https://pagespeed.web.dev/).

= Why are my scores lower than on pagespeed.web.dev? =

Lab scores change from run to run, mostly because of how fast your server responds at that moment. Recheck the page to compare, and look at the Real-user experience section, which is not affected by single runs.

= Why is there no real-user data? =

Google only publishes Chrome UX Report data for pages and sites with enough visitors. Smaller sites often have none; the lab results still show what to improve.

= What is the AI Agents score? =

It is the Lighthouse "Agentic Browsing" category. It checks things like a valid llms.txt file and a well-formed accessibility tree, which help AI agents and answer engines use your site. Google marks it as experimental, so the checks may change.

= How do I delete all reports and start over? =

Go to Settings > Delete data and click "Delete reports" (keeps custom URLs and snapshots) or "Delete everything".

== External services ==

This plugin connects to the following third-party service.

= Google PageSpeed Insights API =

Used to generate the reports shown by this plugin. Nothing is sent to this API until you enter your own Google API key.

* What is sent: the URL of each page being checked, your Google API key, the report type (desktop or mobile), the report language and the Lighthouse categories to check.
* When: every time a page is checked. This happens when you start a scan or recheck a report, after adding custom URLs or reactivating ignored URLs, and during scheduled scans if automatic rechecks are on. When you click "Check key" in the Settings, only the API key is sent, to confirm it works.
* Endpoint: https://www.googleapis.com/pagespeedonline/v5/runPagespeed
* [Google APIs Terms of Service](https://developers.google.com/terms)
* [Google Terms of Service](https://policies.google.com/terms)
* [Google Privacy Policy](https://policies.google.com/privacy)

The charts in wp-admin are drawn by the plugin itself; no chart library is loaded from a third-party server.

The ShortPixel suggestion shown next to image audits is an affiliate link and is only loaded when you click it.

== Screenshots ==

1. Overview: category scores, score trend, Performance distribution, lab metrics and top opportunities.
2. Reports list with trends, Core Web Vitals and category scores.
3. Page report with all categories, lab metrics, real-user data and score history.
4. Audits grouped by category, with estimated savings and WordPress tips.
5. Snapshot comparison.
6. Settings.

== Changelog ==

= 5.0.0 =
* New admin interface built with React and the WordPress components, with charts and trends
* Reports now include all Lighthouse categories: Performance, Accessibility, Best Practices, SEO and AI Agents (Agentic Browsing)
* Current Lighthouse metrics (LCP, TBT, CLS, FCP, Speed Index) and Core Web Vitals from real users (LCP, INP, CLS)
* Score history for every page and a daily site trend
* Audits are grouped like on pagespeed.web.dev, with estimated savings and WordPress tips
* Import custom URLs from a CSV file (for example a Search Console or crawler export)
* Fixed: XML sitemap import was rejected on sites that do not allow XML uploads
* Fixed: rechecking a page now updates its mobile report too, not only the desktop one
* Fixed: an invalid API key is reported clearly again with the current Google API
* New "Check key" button: a new API key is verified before the settings are saved
* Fixed: a PHP notice when a checked page has no real-user (Chrome UX Report) data
* A clear warning when a scan cannot start because the site blocks requests to itself (maintenance mode, a login wall or the host), with a link to Site Health
* When the daily API quota is used up, the scan stops with a clear message instead of sending a failing request for every page; a per-minute limit is waited out and the page is tried again
* The ShortPixel image suggestion works again with current Lighthouse image audits
* Charts are drawn locally; the admin no longer loads Google Charts
* If a screen fails to load, it now shows a message with Reload and Try again buttons instead of a blank page
* Requires WordPress 6.6 and PHP 7.2

= 4.1.0 =
* Tested with WordPress 7.1
* Security hardening: database queries, input validation, output escaping, capability and nonce checks
* Translations: text domain now matches the plugin slug, so WordPress.org language packs work
* Fixed: plugin settings no longer change when the plugin is updated
* Minor code quality fixes
* Removed the NitroPack promotion box (the partner offer is no longer active)

= 4.0.8 =
* Fix PHP 8 warnings

= 4.0.7 =
* Add support for XML sitemaps of type text/xml
* Bugfix some error messages not appearing when they should
* Add additional nonce checks for Cross-Site Request Forgery (CSRF) protection

= 4.0.6 =
* Additional validating, sanitizing, and escaping the plugin codebase

= 4.0.5 =
* Bugfix some validation rule reports not showing properly (some results may need to be rechecked to see the fixes)
* Better styling for readability in report details
* Additional validating, sanitizing, and escaping the plugin codebase

= 4.0.4 =
* Bugfix improperly escaped data (xss security issue)
* Rename plugin to "Insights from Google PageSpeed"

= 4.0.3 =
* Bugfix loading screenshots 'filmstrip' images broken

= 4.0.2 =
* Bugfix google has introduced a couple new report types that were not handled by JS templating in this plugin. Added support for the new type and also tried to more gracefully handle this issue if/when this happens again in the future.

= 4.0.1 =
* Bugfix removed reference to 'legend' template file that was deleted in v4.0.0 and was causing PHP warnings

= 4.0.0 =
* NOTICE: This upgrade will remove any existing reports or snapshots created by older versions of this plugin as they are not compatible with the newest version of the pagespeed API (v5)
* Migrating to the latest version of the Google Pagespeed Insights API (v5). This comes with pretty big changes to reporting and is incompatible with reports generated from previous versions of the API (and this plugin)
* Replaced the Google API PHP library previously used by this plugin in favor of a much smaller and simpler class
* Bugfix SQL Mutex lock key not unique per installation causing issues when trying to run this plugin on multiple sites sharing the same SQL server

= 3.0.5 =
* Fixed PHP warning when saving settings

= 3.0.4 =
* Fixed bug where WordPress heartbeat API filter function failed to return properly
* Added additional options for max runtime to support scenarios where even 60 seconds was over the server max run time

= 3.0.3 =
* Fixed bug where Maximum Script Run Time option could not be set to "No Limit" after previously being set to a higher value

= 3.0.2 =
* Fixed bug introduced in v3.0.1 effecting servers running PHP 5.4 where a PHP error is produced while trying to perform actions in the plugin like saving options

= 3.0.1 =
* Added snapshot comments to the view snapshot / compare snapshot templates
* Fixed bug with snapshot report type label reading 'both' when it should read either desktop or mobile
* Fixed bug with snapshot report description label not loading translatable string
* Fixed bug preventing snapshot comments from being displayed in snapshots list table
* Improved hardening against authenticated XSS attacks
* Improved adherence to WordPress coding standards and best practices

= 3.0.0 =
* Includes all previously "premium" functionality for free. This includes report snapshots, snapshot comparison tool, custom URL reporting, and scheduled report checks.
* Added in URL hotlinking in report details for paths to assets (images/scripts/etc)

= 2.0.3 =
* Updating to latest google api php library 2.2.0 to resolve issues with PHP 7.1

= 2.0.2 =
* Fixed bug which improperly returned WP Cron schedules on cron_schedules filter

= 2.0.1 =
* Removed phpseclib unit tests from Google PHP API library to avoid false-positive with WordFence

= 2.0.0 =
* Major rewrite for better compatibility and performance
* Updated to the latest Google Pagespeed Insights API verison and library
* Fixed issues with bulk installers not generating DB tables
* Consolidated scan methods to a single more reliable method inspired by WP cron
* Better identify, communicate, and resolve issues with environments that have difficulties with scanning pages or API errors
* Improved API error logging
* Added 'abort scan' functionality to cancel an in-progress scan
* Added 'Maximum Script Run Time' option to advanced configuration to allow scans to run in shorter intervals for web hosts which have script timeouts that cannot be overridden
* Update the codebase to adhere better to WordPress coding standards and best practices

= 1.0.6 =
* Fixed error with WP_List_Table introduced with WordPress 4.3
* Added Russian translation. Thank you Ivanka from [coupofy.com](http://coupofy.com)

= 1.0.5 =
* Fixed problem with temp directory used by Google API which was not writable on many shared hosting environments, and prevented the plugin from working properly. Replaced sys_get_temp_dir function with WordPress get_temp_dir function to resolve.
* Added Spanish and Serbian translations. Thank you Ogi Djuraskovic from [firstsiteguide.com](http://firstsiteguide.com) for providing these.

= 1.0.4 =
* Added auto-update capability

= 1.0.3 =
* Tweaked interactions with WPDB so that it will properly get the table prefix for WordPress multisite installations.

= 1.0.2 =
* Tweaked styles to look better in the new WordPress 3.8 admin theme.
* Fixed accidental use of some php shorttags (<? instead of <?php) that was causing activation errors for some. (Thank you bekar09 for first finding this error)
* Tweaked ajax.js for better performance (Thanks to Pippin for the suggestion)
* Tweaked use of get_pages() for better performance (Thanks to Pippin for the suggestion)
* Fixed a number of php notices

= 1.0.1 =
* Fixed a potential conflict with other plugins that also utilize the Google API PHP library. The API is now only included if the Google_Client class does not already exist.
* Added additional checking during plugin activation to fail fast if the server does not meet the minimum plugin requirements.

= 1.0.0 =
* Initial Release

== Upgrade Notice ==

= 5.0.0 =
New interface with all Lighthouse categories, Core Web Vitals and score history. Requires WordPress 6.6 and PHP 7.2. Recheck your pages after updating to fill in the new data.

= 4.1.0 =
Security hardening and WordPress 7.1 compatibility. Recommended update.

= 4.0.8 =
* Fix PHP 8 warnings

= 4.0.7 =
* Add support for XML sitemaps of type text/xml
* Bugfix some error messages not appearing when they should
* Add additional nonce checks for Cross-Site Request Forgery (CSRF) protection

= 4.0.6 =
* Additional validating, sanitizing, and escaping the plugin codebase

= 4.0.5 =
* Bugfix some validation rule reports not showing properly (some results may need to be rechecked to see the fixes)
* Better styling for readability in report details
* Additional validating, sanitizing, and escaping the plugin codebase

= 4.0.4 =
* Bugfix improperly escaped data (xss security issue)
* Rename plugin to "Insights from Google PageSpeed"

= 4.0.3 =
* Bugfix loading screenshots 'filmstrip' images broken

= 4.0.2 =
* Bugfix google has introduced a couple new report types that were not handled by JS templating in this plugin. Added support for the new type and also tried to more gracefully handle this issue if/when this happens again in the future.

= 4.0.1 =
* Bugfix removed reference to 'legend' template file that was deleted in v4.0.0 and was causing PHP warnings

= 4.0.0 =
* NOTICE: This upgrade will remove any existing reports or snapshots created by older versions of this plugin as they are not compatible with the newest version of the pagespeed API (v5)