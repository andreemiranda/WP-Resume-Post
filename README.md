# WP Resume Post WordPress Plugin

A powerful WordPress plugin that automatically rewrites imported content using ChatGPT API, creating unique articles while maintaining the original meaning.

## Description

WP Resume Post is designed to automatically process and rewrite content from imported posts, whether they come from RSS feeds, WordPress importers, or other import sources. The plugin uses ChatGPT's API to create unique content while preserving the original meaning of the text.

### Key Features

- Automatic content rewriting using ChatGPT
- Title optimization with synonyms
- Removal of source citations and references
- Cleanup of emojis and unnecessary formatting
- Immediate post publication after processing
- Simple configuration with API key setup

## Installation

1. Download the plugin zip file
2. Go to WordPress admin panel > Plugins > Add New
3. Click "Upload Plugin" and select the downloaded zip file
4. Click "Install Now" and then "Activate"
5. Go to Settings > WP Resume Post
6. Enter your ChatGPT API key and save the settings

## Configuration

1. Obtain a ChatGPT API key from OpenAI
2. Navigate to WordPress admin panel > Settings > WP Resume Post
3. Enter your API key in the provided field
4. Save the settings

## Development Setup

1. Clone the repository to your WordPress plugins directory:
   ```bash
   cd wp-content/plugins
   git clone [repository-url] wp-resume-post
   ```

2. Install dependencies (if any in the future):
   ```bash
   cd wp-resume-post
   composer install
   ```

3. Activate the plugin in WordPress admin panel

### Development Guidelines

- Follow WordPress coding standards
- Test thoroughly with different import sources
- Maintain proper error handling for API calls
- Keep the content processing efficient

## Usage

The plugin works automatically when:
1. Content is imported via RSS feeds
2. Posts are imported using WordPress importer
3. Any other import mechanism is used

No manual intervention is required after setup. The plugin will:
1. Detect imported content
2. Clean the content
3. Process it through ChatGPT
4. Update the post with new content
5. Publish it immediately

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- Valid ChatGPT API key
- Active internet connection for API calls

## Support

For support, please create an issue in the repository or contact us at:
[Your Contact Information]

## License

GNU General Public License v3.0
See LICENSE file for details.
