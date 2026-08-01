=== LLMO Ready - Blog Optimizer ===
Contributors: llmoready
Tags: seo, schema, ai, blog, optimization
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.16
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds Schema.org markup with AI-optimized content to blog posts for better AI search visibility.

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
2. Get your free API key from [libers.ai](https://libers.ai)
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
- Featured image URL (if set)

**What we do NOT collect:**
- Personal user information
- Email addresses
- IP addresses
- Visitor data

For complete details, please review:
- [Privacy Policy](https://libers.ai/privacy)
- [Terms of Use](https://libers.ai/terms)

= External Service =

This plugin relies on the Libers Suite API for AI optimization functionality. The following data is sent to our servers:

- Post title, content, and excerpt
- Post author name (public)
- Publication date
- Post URL
- Featured image URL (if set)

**User Consent Required:** You must explicitly consent to data processing in plugin settings before any content is transmitted. No data is sent without your explicit consent.

**Service Terms:**
- Service: https://libers.ai
- Terms of Use: https://libers.ai/terms
- Privacy Policy: https://libers.ai/privacy
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
2. Enter your API key from [libers.ai](https://libers.ai)
3. Click "Test Connection" to verify
4. Configure auto-optimization settings
5. Select which post types to optimize

== Frequently Asked Questions ==

= Do I need an API key? =

Yes, you need a free API key from [libers.ai](https://libers.ai). The free tier includes optimization for up to 100 posts per month.

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

= Why does Connect return an API token in the URL? =

Connecting opens LLMO Ready in the browser and redirects back to your WordPress admin with a one-time token in the query string (same pattern as OAuth return URLs). Only administrators can complete the callback. The redirect includes a one-time `llmo_state` value that must match a short-lived server-side token. After saving, the plugin immediately redirects again so the token is removed from the address bar. You can also paste an API key manually in Settings.

== Screenshots ==

1. Settings page with API configuration
2. Bulk Optimizer dashboard
3. Post editor meta box showing optimization status
4. AI Readiness Score display
5. Generated Schema.org markup example

== Changelog ==

= 1.0.16 =
* Fixed: Bulk Optimizer stats SlowDBQuery warnings silenced with scoped phpcs disable (admin-only count)

= 1.0.15 =
* Fixed: FAQ CSS/JS load only when the FAQ shortcode or widget renders
* Fixed: Optimize AJAX checks edit_post capability per post
* Fixed: Connect callback requires one-time llmo_state (GET token flow documented)
* Fixed: AI score colors use CSS classes (no unescaped inline color)
* Fixed: Support forum URL uses plugin slug llmo-ready-blog-optimizer
* Fixed: LICENSE contains the full GPLv2 text
* Improved: Admin inline styles moved to admin.css
* Improved: External links use rel="noopener noreferrer"
* Improved: Organization type labels and breadcrumb "Home" are translatable
* Improved: Bulk optimizer paginates (50 posts per page)
* Improved: Admin assets on post screens only for enabled post types
* Improved: Remote API error messages are sanitized before display
* Improved: Removed blocking sleep(); Optimize Now queues and polls via AJAX

= 1.0.14 =
* Fixed: Enqueue admin JS via wp_enqueue_script instead of inline script tags
* Fixed: JSON-LD output uses JSON_HEX_TAG to prevent script breakout

= 1.0.13 =
* Fixed: Text Domain aligned to plugin slug `llmo-ready-blog-optimizer` (Plugin Check)

= 1.0.12 =
* Fixed: Short description kept under 150 characters (WordPress.org import warning)
* Fixed: Settings sanitize callbacks for consent, auto-optimize, and post types
* Fixed: Unchecking consent or auto-optimize now clears the stored option
* Fixed: Uninstall removes organization options, OG/pending meta, and cron events
* Fixed: readme discloses featured image URL in API payload
* Fixed: Removed duplicate Changelog section in readme.txt

= 1.0.11 =
* Fixed: Plugin URI and Author URI must differ (WordPress.org review). Plugin URI now points to the wordpress.org plugin page, Author URI to libers.ai

= 1.0.10 =
* Fix: Optimize Now waits for async API job instead of writing empty meta
* New: Open Graph tags (title/description/image) with Yoast/Rank Math duplicate protection
* New: Auto-optimize is fire-and-forget; cron polls pending posts every 5 minutes
* Fix: FAQ widget only renders on singular views

= 1.0.9 =
* Fix: Plugin- und API-URLs auf libers.ai migriert
* Fix: API-Client-Konstruktor übergab Website-Domain fälschlich als API-Endpoint
* Fix: Datenschutz-/Nutzungsbedingungen-Links aktualisiert

= 1.0.8 =
* New: Automatic API token transfer after login/registration (no manual copy needed)
* New: Connected status indicator on settings page
* Improved: Connect button redirects back to plugin with token automatically

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

= 1.0.16 =
Plugin Check: bulk stats without SlowDBQuery meta_key/meta_value warnings.

= 1.0.15 =
WordPress.org review hardening: capability checks, FAQ asset loading, connect state, no sleep(), full GPLv2, clean packaging.

= 1.0.12 =
WordPress.org compliance fixes: settings sanitization, consent clear, uninstall cleanup, and readme disclosure.

= 1.0.8 =
Automatic API token setup -- connect your account with one click.

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
* Documentation: https://docs.libers.ai/wordpress-plugin
* Support Forum: https://wordpress.org/support/plugin/llmo-ready-blog-optimizer/
* Email: support@libers.ai

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
* Featured image URL (if set)

**What is NOT sent:**
* User email addresses
* User passwords
* IP addresses
* Visitor tracking data
* Any personal user information

For complete details:
* [Privacy Policy](https://libers.ai/privacy)
* [Terms of Use](https://libers.ai/terms)
* [API Documentation](https://docs.libers.ai)
