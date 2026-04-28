=== LLMO Ready - Blog Optimizer ===
Contributors: llmoready
Tags: seo, schema, ai, blog, optimization
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically adds Schema.org JSON-LD markup with AI-optimized content for better visibility in AI search engines (ChatGPT, Google SGE, Perplexity).

== Description ==

**LLMO Ready - Blog Optimizer**

Automatically adds Schema.org JSON-LD markup with AI-optimized content from LLMO Ready to blog posts for better visibility in generative AI search engines (ChatGPT, Google SGE, Perplexity).

LLMO Blog Optimizer automatically enhances your blog posts for better visibility in AI-powered search engines like ChatGPT, Google SGE, and Perplexity. The plugin uses advanced AI to analyze your content and generate structured data, FAQs, and key takeaways.

= Features =

* **Automatic Schema.org Markup** - Generates Article schema for better search visibility
* **AI-Powered FAQ Generation** - Creates relevant FAQs from your content
* **Key Takeaways** - Extracts the most important points from your articles
* **AI Readiness Score** - Shows how well your content is optimized for AI
* **Bulk Optimization** - Optimize multiple posts at once
* **Auto-Optimize** - Automatically optimize new posts when published
* **Post Type Support** - Works with posts, pages, and custom post types
* **Easy Setup** - Simple configuration with API key

= How It Works =

1. Install and activate the plugin
2. Get your free API key from [llmoready.com](https://llmoready.com)
3. Enter your API key in Settings
4. Your posts will be automatically optimized when published
5. Or use the Bulk Optimizer to optimize existing posts

= Requirements =

* WordPress 5.8 or higher
* PHP 7.4 or higher
* LLMO Ready account (free tier available)

= Privacy & Data =

This plugin sends your post content to the LLMO Ready API for analysis and optimization. By using this plugin, you consent to this data processing.

**What data is sent:**
- Post title, content, and excerpt
- Post author name
- Publication date
- Post URL

**What we do NOT collect:**
- Personal user information
- Email addresses
- IP addresses
- Visitor data

For complete details, please review:
- [Privacy Policy](https://llmoready.com/privacy)
- [Terms of Use](https://llmoready.com/terms)

= External Service =

This plugin relies on the LLMOReady.com API for AI optimization functionality. The following data is sent to our servers:

- Post title, content, and excerpt
- Post author name (public)
- Publication date
- Post URL

**User Consent Required:** You must explicitly consent to data processing in plugin settings before any content is transmitted. No data is sent without your explicit consent.

**Service Terms:**
- Service: https://llmoready.com
- Terms of Use: https://llmoready.com/terms
- Privacy Policy: https://llmoready.com/privacy
- Location: Germany (GDPR compliant)

== Installation ==

= Automatic Installation =

1. Go to Plugins > Add New
2. Search for "LLMO Blog Optimizer"
3. Click "Install Now"
4. Activate the plugin

= Manual Installation =

1. Download the plugin ZIP file
2. Go to Plugins > Add New > Upload Plugin
3. Choose the ZIP file and click "Install Now"
4. Activate the plugin

= Configuration =

1. Go to LLMO Optimizer > Settings
2. Enter your API key from [llmoready.com](https://llmoready.com)
3. Click "Test Connection" to verify
4. Configure auto-optimization settings
5. Select which post types to optimize

== Frequently Asked Questions ==

= Do I need an API key? =

Yes, you need a free API key from [llmoready.com](https://llmoready.com). The free tier includes optimization for up to 100 posts per month.

= Which post types are supported? =

The plugin works with posts, pages, and any custom post types. You can select which post types to optimize in the settings.

= Will this slow down my site? =

No, optimization happens in the background via API calls. Your site performance is not affected.

= Can I optimize existing posts? =

Yes! Use the Bulk Optimizer tool to optimize all your existing posts at once.

= What happens to my optimizations if I deactivate the plugin? =

All optimization data is stored as post meta and will remain even if you deactivate the plugin.

= Is my content sent to external servers? =

Yes, your post content is sent to the LLMO Ready API for analysis. The API is GDPR compliant and does not store your content permanently.

== Screenshots ==

1. Settings page with API configuration
2. Bulk Optimizer dashboard
3. Post editor meta box showing optimization status
4. AI Readiness Score display
5. Generated Schema.org markup example

== Changelog ==

= 1.0.0 =
* Initial release
* Auto-optimization for new posts
* Bulk optimization tool
* Schema.org Article markup generation
* FAQ generation
* Key takeaways extraction
* AI Readiness Score
* Support for multiple post types

== Changelog ==

= 1.0.7 =
* Fixed: Removed hidden files (.gitignore) from distribution package
* Fixed: Auto-analysis for existing users logging in via plugin Connect button

= 1.0.6 =
* New: Free registration page for WordPress plugin users (no Stripe checkout)
* New: Onboarding flow with direct link from plugin settings to LLMO Ready registration
* New: First name and last name fields in plugin registration
* New: WordPress logo pattern background on registration page
* Improved: Plugin settings page with clearer connect/register buttons

= 1.0.5 =
* Fixed: API key link points to correct URL (app.llmoready.com/websites)

= 1.0.4 =
* Fixed: Added Plugin URI header with llmoready.com for "Details anzeigen" link
* Fixed: translators comments format for WordPress.org compliance
* Fixed: wp_unslash/sanitize order in nonce verification

= 1.0.3 =
* Fixed: Removed Update URI header (not allowed for WordPress.org)
* Fixed: Shortened readme.txt short description to under 150 characters
* Fixed: Consistent plugin listing with other LLMO Ready plugins

= 1.0.2 =
* Fixed: Plugin URI changed to wordpress.org for proper plugin details link
* Fixed: Consistent plugin listing with other LLMO Ready plugins

= 1.0.1 =
* Fixed: WordPress Plugin Check compliance errors
* Fixed: Security - Added sanitize_text_field to nonce verification
* Fixed: Naming - Prefixed global variables in uninstall.php
* Removed: Development markdown files from plugin root
* Added: Consent mechanism for GDPR compliance
* Added: Privacy policy and terms links in settings

= 1.0.0 =
* Initial release of LLMO Ready - Blog Optimizer

== Upgrade Notice ==

= 1.0.7 =
Fix: auto-analysis for existing users, clean distribution package.

= 1.0.6 =
New streamlined registration flow for plugin users. Create your free account directly from the plugin.

= 1.0.5 =
Fixed API key link to point to working websites page.

= 1.0.4 =
Plugin listing now shows "Details anzeigen" link and internal readme.txt details page.

= 1.0.3 =
Plugin listing improvements.

= 1.0.2 =
Plugin listing improvements for WordPress admin consistency.

= 1.0.1 =
Bug fixes and WordPress.org compliance improvements. Please update to ensure compatibility with WordPress.org submission guidelines.

= 1.0.0 =
Initial release.

== Support ==

For support, please visit:
* Documentation: https://docs.llmoready.com/wordpress-plugin
* Support Forum: https://wordpress.org/support/plugin/llmo-blog-optimizer/
* Email: support@llmoready.com

== Privacy Policy ==

This plugin sends post content to the LLMO Ready API for analysis and optimization.

**User Consent:**
You must explicitly consent to data processing in the plugin settings before any content is sent to our API.

**Data Processing:**
* Post content is sent via secure HTTPS connection
* Content is analyzed by AI and optimization data is returned
* We do not permanently store your post content
* We do not collect personal user data
* We do not track visitors to your site

**GDPR Compliance:**
This plugin is GDPR compliant. Users must opt-in via checkbox in settings.

**What is sent:**
* Post title, content, excerpt
* Post author name (public information)
* Publication date
* Post URL

**What is NOT sent:**
* User email addresses
* User passwords
* IP addresses
* Visitor tracking data
* Any personal user information

For complete details:
* [Privacy Policy](https://llmoready.com/privacy)
* [Terms of Use](https://llmoready.com/terms)
* [API Documentation](https://docs.llmoready.com)
