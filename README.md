# LLMO Blog Optimizer

**AI-powered blog optimization for WordPress**

Automatically optimize your WordPress blog posts for better visibility in AI search engines like ChatGPT, Google SGE, and Perplexity.

## Features

- **Automatic Schema.org Markup** - Generates Article schema for better search visibility
- **AI-Powered FAQ Generation** - Creates relevant FAQs from your content
- **Key Takeaways** - Extracts the most important points from your articles
- **AI Readiness Score** - Shows how well your content is optimized for AI
- **Bulk Optimization** - Optimize multiple posts at once
- **Auto-Optimize** - Automatically optimize new posts when published
- **Post Type Support** - Works with posts, pages, and custom post types

## Installation

### From WordPress.org

1. Go to **Plugins > Add New**
2. Search for "LLMO Blog Optimizer"
3. Click **Install Now**
4. Activate the plugin

### Manual Installation

1. Download the latest release
2. Upload to `/wp-content/plugins/llmo-blog-optimizer/`
3. Activate through the WordPress admin

## Configuration

1. Go to **LLMO Optimizer > Settings**
2. Get your free API key from [llmoready.com](https://llmoready.com)
3. Enter your API key
4. Click **Test Connection**
5. Configure auto-optimization settings
6. Select which post types to optimize

## Usage

### Auto-Optimization

Enable auto-optimization in settings to automatically optimize new posts when published.

### Manual Optimization

1. Edit any blog post
2. Find the **LLMO Blog Optimizer** meta box in the sidebar
3. Click **Optimize Now**
4. Wait for optimization to complete

### Bulk Optimization

1. Go to **LLMO Optimizer > Bulk Optimizer**
2. Select posts to optimize or click **Optimize All Pending**
3. Monitor progress in real-time

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- LLMO Ready account (free tier available)

## API Integration

This plugin communicates with the LLMO Ready API to:
- Analyze article content
- Generate Schema.org markup
- Create FAQ sections
- Calculate AI readiness scores

All data is processed securely via HTTPS.

## Privacy

This plugin sends post content to the LLMO Ready API for analysis. The API:
- Does not permanently store your content
- Does not collect personal data
- Is GDPR compliant
- Uses HTTPS encryption

For more information, see our [Privacy Policy](https://llmoready.com/privacy).

## Support

- **Documentation**: https://llmoready.com/documentation/
- **Email**: support@llmoready.com

## Development

### File Structure

```
llmo-blog-optimizer/
├── admin/
│   ├── class-llmo-admin.php
│   └── class-llmo-bulk-optimizer.php
├── assets/
│   ├── css/
│   └── js/
├── includes/
│   ├── class-llmo-api-client.php
│   ├── class-llmo-article-detector.php
│   └── class-llmo-schema-generator.php
├── languages/
├── llmo-blog-optimizer.php
├── readme.txt
└── uninstall.php
```

### Contributing

Contributions are welcome! Please read [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

GPL v2 or later - see [LICENSE](LICENSE) for details.

## Changelog

### 1.0.0 (2026-04-07)
- Initial release
- Auto-optimization for new posts
- Bulk optimization tool
- Schema.org Article markup generation
- FAQ generation
- Key takeaways extraction
- AI Readiness Score
- Support for multiple post types

## Credits

Developed by [LLMO Ready](https://llmoready.com)
