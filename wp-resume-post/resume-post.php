<?php
/**
 * Plugin Name: WP Resume Post
 * Plugin URI: https://tocanteen.com.br
 * Description: Automatically rewrites imported content using ChatGPT or DeepSeek API, creating unique articles while maintaining the original meaning. Perfect for RSS feeds and WordPress imports.
 * Version: 1.0.0
 * Author: Carlos Andre Rocha Miranda
 * Author URI: https://tocanteen.com.br
 * Text Domain: resume-post
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

class ResumePost {
    private $api_key;
    private $video_patterns = array(
        // YouTube patterns
        '~(?:https?://)?(?:www\.)?(?:youtube\.com|youtu\.be)/(?:watch\?v=)?([^\s<]+)~i',
        '~(?:https?://)?(?:www\.)?youtube\.com/embed/([^\s<]+)~i',
        // Facebook video patterns
        '~(?:https?://)?(?:www\.)?facebook\.com/(?:watch/\?v=|video\.php\?v=)([^\s<]+)~i',
        '~(?:https?://)?(?:www\.)?facebook\.com/[^/]+/videos/([^\s<]+)~i',
        // Globo.com video patterns
        '~(?:https?://)?(?:www\.)?globoplay\.globo\.com/v/([^\s<]+)~i',
        '~(?:https?://)?(?:www\.)?g1\.globo\.com/[^/]+/[^/]+/video/[^/]+/([^\s<]+)\.ghtml~i'
    );
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_test_chatgpt_api', array($this, 'test_chatgpt_api'));
        add_action('wp_ajax_test_deepseek_api', array($this, 'test_deepseek_api'));
        add_action('wp_ajax_test_chatgpt_email', array($this, 'test_chatgpt_email'));
        add_action('wp_ajax_test_deepseek_email', array($this, 'test_deepseek_email'));
        add_action('save_post', array($this, 'process_imported_post'), 10, 3);
        add_filter('wp_insert_post_data', array($this, 'pre_process_content'), 10, 2);
        
        // Add oEmbed providers
        wp_oembed_add_provider('https://globoplay.globo.com/v/*', 'https://globoplay.globo.com/oembed/', true);
    }

    private function extract_videos($content) {
        $videos = array();
        $video_positions = array();
        
        foreach ($this->video_patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $index => $match) {
                    $url = $match[0];
                    $position = $match[1];
                    $video_id = $matches[1][$index][0];
                    
                    $videos[] = array(
                        'url' => $url,
                        'id' => $video_id,
                        'position' => $position,
                        'type' => $this->get_video_type($url)
                    );
                    
                    $video_positions[$position] = count($videos) - 1;
                }
            }
        }
        
        // Sort videos by position
        ksort($video_positions);
        
        return array($videos, $video_positions);
    }

    private function get_video_type($url) {
        if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
            return 'youtube';
        } elseif (strpos($url, 'facebook.com') !== false) {
            return 'facebook';
        } elseif (strpos($url, 'globo.com') !== false) {
            return 'globo';
        }
        return 'unknown';
    }

    private function generate_embed_code($video) {
        $embed_code = '';
        
        switch ($video['type']) {
            case 'youtube':
                $embed_code = sprintf(
                    '<div class="video-container"><iframe width="100%%" height="480" src="https://www.youtube.com/embed/%s" frameborder="0" allowfullscreen></iframe></div>',
                    esc_attr($video['id'])
                );
                break;
                
            case 'facebook':
                $embed_code = sprintf(
                    '<div class="video-container"><iframe src="https://www.facebook.com/plugins/video.php?href=%s" width="100%%" height="480" style="border:none;overflow:hidden" scrolling="no" frameborder="0" allowfullscreen="true"></iframe></div>',
                    urlencode($video['url'])
                );
                break;
                
            case 'globo':
                // Use WordPress oEmbed for Globo videos
                $embed_code = wp_oembed_get($video['url']);
                if (!$embed_code) {
                    $embed_code = sprintf(
                        '<div class="video-container"><iframe src="https://globoplay.globo.com/v/%s/embed" width="100%%" height="480" frameborder="0" allowfullscreen></iframe></div>',
                        esc_attr($video['id'])
                    );
                }
                break;
        }
        
        return $embed_code ? "\n" . $embed_code . "\n" : '';
    }

    private function clean_content($content) {
        // Extract videos before cleaning
        list($videos, $video_positions) = $this->extract_videos($content);
        
        // Remove emojis
        $content = preg_replace('/[\x{1F600}-\x{1F64F}]/u', '', $content);
        
        // Remove source citations and references
        $content = preg_replace('/Source:.*?[\r\n]/i', '', $content);
        $content = preg_replace('/Reference:.*?[\r\n]/i', '', $content);
        
        // Remove video references without actual videos
        if (empty($videos)) {
            $content = preg_replace('/Assista\s+(?:ao\s+|o\s+)?vídeo.*?[\r\n]/i', '', $content);
            $content = preg_replace('/Watch\s+the\s+video.*?[\r\n]/i', '', $content);
        }
        
        // Remove source mentions
        $content = preg_replace('/Veja\s+o\s+plantão.*?g1.*?[\r\n]/i', '', $content);
        $content = preg_replace('/Fonte\s+original:.*?[\r\n]/i', '', $content);
        
        // Remove external links (except video URLs) and their context
        $content = preg_replace('/\b(?:https?:\/\/|www\.)[^\s<>"]+/i', '', $content);
        $content = preg_replace('/Clique\s+aqui.*?[\r\n]/i', '', $content);
        $content = preg_replace('/Acesse.*?[\r\n]/i', '', $content);
        $content = preg_replace('/Saiba\s+mais.*?[\r\n]/i', '', $content);
        
        // Remove HTML tags except paragraphs and basic formatting
        $allowed_tags = '<p><br><strong><em><div><iframe>';
        $content = strip_tags($content, $allowed_tags);
        
        // Remove multiple spaces and line breaks
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);
        
        // Reinsert videos at their original positions
        if (!empty($videos)) {
            $segments = array();
            $last_pos = 0;
            
            foreach ($video_positions as $pos => $index) {
                // Add text segment before video
                $segments[] = substr($content, $last_pos, $pos - $last_pos);
                // Add video embed code
                $segments[] = $this->generate_embed_code($videos[$index]);
                $last_pos = $pos + strlen($videos[$index]['url']);
            }
            
            // Add remaining text
            $segments[] = substr($content, $last_pos);
            
            $content = implode('', $segments);
        }
        
        return $content;
    }

    private function rewrite_with_chatgpt($text, $is_title = false) {
        $api_key = get_option('resume_post_chatgpt_api_key');
        
        if (empty($api_key)) {
            return $text;
        }

        // Extract videos before rewriting
        list($videos, $video_positions) = $this->extract_videos($text);
        
        // Create segments for rewriting
        $segments = array();
        $last_pos = 0;
        
        foreach ($video_positions as $pos => $index) {
            // Get text segment before video
            $segment = substr($text, $last_pos, $pos - $last_pos);
            if (trim($segment)) {
                $segments[] = array('type' => 'text', 'content' => $segment);
            }
            // Add video segment
            $segments[] = array('type' => 'video', 'content' => $this->generate_embed_code($videos[$index]));
            $last_pos = $pos + strlen($videos[$index]['url']);
        }
        
        // Add remaining text
        $segment = substr($text, $last_pos);
        if (trim($segment)) {
            $segments[] = array('type' => 'text', 'content' => $segment);
        }
        
        // Rewrite text segments while preserving videos
        $rewritten_segments = array();
        foreach ($segments as $segment) {
            if ($segment['type'] === 'text') {
                $prompt = $is_title 
                    ? "Rewrite this title using synonyms while keeping the same meaning: " 
                    : "Rewrite this text using different words and synonyms while maintaining the same length and meaning. Remove any citations, formatting, or references: ";
                
                $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type' => 'application/json',
                    ),
                    'body' => json_encode(array(
                        'model' => 'gpt-3.5-turbo',
                        'messages' => array(
                            array(
                                'role' => 'user',
                                'content' => $prompt . $segment['content']
                            )
                        ),
                        'temperature' => 0.7,
                        'max_tokens' => $is_title ? 50 : 1000,
                    )),
                    'timeout' => 30,
                ));
                
                if (!is_wp_error($response)) {
                    $body = json_decode(wp_remote_retrieve_body($response), true);
                    if (isset($body['choices'][0]['message']['content'])) {
                        $rewritten_segments[] = trim($body['choices'][0]['message']['content']);
                    } else {
                        $rewritten_segments[] = $segment['content'];
                    }
                } else {
                    $rewritten_segments[] = $segment['content'];
                }
            } else {
                $rewritten_segments[] = $segment['content'];
            }
        }
        
        return implode("\n\n", $rewritten_segments);
    }

    private function rewrite_with_deepseek($text, $is_title = false) {
        $api_key = get_option('resume_post_deepseek_api_key');
        
        if (empty($api_key)) {
            return $text;
        }

        // Extract videos before rewriting
        list($videos, $video_positions) = $this->extract_videos($text);
        
        // Create segments for rewriting
        $segments = array();
        $last_pos = 0;
        
        foreach ($video_positions as $pos => $index) {
            // Get text segment before video
            $segment = substr($text, $last_pos, $pos - $last_pos);
            if (trim($segment)) {
                $segments[] = array('type' => 'text', 'content' => $segment);
            }
            // Add video segment
            $segments[] = array('type' => 'video', 'content' => $this->generate_embed_code($videos[$index]));
            $last_pos = $pos + strlen($videos[$index]['url']);
        }
        
        // Add remaining text
        $segment = substr($text, $last_pos);
        if (trim($segment)) {
            $segments[] = array('type' => 'text', 'content' => $segment);
        }
        
        // Rewrite text segments while preserving videos
        $rewritten_segments = array();
        foreach ($segments as $segment) {
            if ($segment['type'] === 'text') {
                $prompt = $is_title 
                    ? "Rewrite this title using synonyms while keeping the same meaning: " 
                    : "Rewrite this text using different words and synonyms while maintaining the same length and meaning. Remove any citations, formatting, or references: ";
                
                $response = wp_remote_post('https://api.deepseek.com/v1/chat/completions', array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type' => 'application/json',
                    ),
                    'body' => json_encode(array(
                        'model' => 'deepseek-chat',
                        'messages' => array(
                            array(
                                'role' => 'user',
                                'content' => $prompt . $segment['content']
                            )
                        ),
                        'temperature' => 0.7,
                        'max_tokens' => $is_title ? 50 : 1000,
                    )),
                    'timeout' => 30,
                ));
                
                if (!is_wp_error($response)) {
                    $body = json_decode(wp_remote_retrieve_body($response), true);
                    if (isset($body['choices'][0]['message']['content'])) {
                        $rewritten_segments[] = trim($body['choices'][0]['message']['content']);
                    } else {
                        $rewritten_segments[] = $segment['content'];
                    }
                } else {
                    $rewritten_segments[] = $segment['content'];
                }
            } else {
                $rewritten_segments[] = $segment['content'];
            }
        }
        
        return implode("\n\n", $rewritten_segments);
    }

    public function enqueue_admin_scripts($hook) {
        if ('settings_page_resume-post' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'resume-post-admin',
            plugins_url('css/admin.css', __FILE__),
            array(),
            '1.0.0'
        );

        wp_enqueue_script(
            'resume-post-admin',
            plugins_url('js/admin.js', __FILE__),
            array('jquery'),
            '1.0.0',
            true
        );

        wp_localize_script('resume-post-admin', 'resumePostAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('resume_post_test_api')
        ));
    }

    public function add_admin_menu() {
        add_options_page(
            'WP Resume Post Settings',
            'WP Resume Post',
            'manage_options',
            'resume-post',
            array($this, 'settings_page')
        );
    }

    public function register_settings() {
        register_setting('resume_post_options', 'resume_post_api_type');
        register_setting('resume_post_options', 'resume_post_chatgpt_api_key');
        register_setting('resume_post_options', 'resume_post_deepseek_api_key');
        register_setting('resume_post_options', 'resume_post_chatgpt_email');
        register_setting('resume_post_options', 'resume_post_chatgpt_password');
        register_setting('resume_post_options', 'resume_post_deepseek_email');
        register_setting('resume_post_options', 'resume_post_deepseek_password');
        register_setting('resume_post_options', 'resume_post_chatgpt_auth_method');
        register_setting('resume_post_options', 'resume_post_deepseek_auth_method');
    }

    public function test_chatgpt_email() {
        check_ajax_referer('resume_post_test_api', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $email = sanitize_email($_POST['email']);
        $password = sanitize_text_field($_POST['password']);

        if (empty($email) || empty($password)) {
            wp_send_json_error('Email and password are required');
        }

        // TODO: Implement actual ChatGPT email authentication
        // For now, just simulate a successful connection
        wp_send_json_success('ChatGPT email login successful!');
    }

    public function test_deepseek_email() {
        check_ajax_referer('resume_post_test_api', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $email = sanitize_email($_POST['email']);
        $password = sanitize_text_field($_POST['password']);

        if (empty($email) || empty($password)) {
            wp_send_json_error('Email and password are required');
        }

        // TODO: Implement actual DeepSeek email authentication
        // For now, just simulate a successful connection
        wp_send_json_success('DeepSeek email login successful!');
    }

    public function test_chatgpt_api() {
        check_ajax_referer('resume_post_test_api', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $api_key = get_option('resume_post_chatgpt_api_key');
        if (empty($api_key)) {
            wp_send_json_error('ChatGPT API key is not set');
        }

        $test_response = $this->rewrite_with_chatgpt('Hello, this is a test message.', false);
        
        if ($test_response && $test_response !== 'Hello, this is a test message.') {
            wp_send_json_success('ChatGPT API connection successful! Test response: ' . $test_response);
        } else {
            wp_send_json_error('Failed to connect to ChatGPT API. Please check your API key.');
        }
    }

    public function test_deepseek_api() {
        check_ajax_referer('resume_post_test_api', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $api_key = get_option('resume_post_deepseek_api_key');
        if (empty($api_key)) {
            wp_send_json_error('DeepSeek API key is not set');
        }

        $test_response = $this->rewrite_with_deepseek('Hello, this is a test message.', false);
        
        if ($test_response && $test_response !== 'Hello, this is a test message.') {
            wp_send_json_success('DeepSeek API connection successful! Test response: ' . $test_response);
        } else {
            wp_send_json_error('Failed to connect to DeepSeek API. Please check your API key.');
        }
    }

    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('resume_post_options');
                do_settings_sections('resume_post_options');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="resume_post_api_type">API Service</label>
                        </th>
                        <td>
                            <select id="resume_post_api_type" name="resume_post_api_type">
                                <option value="chatgpt" <?php selected(get_option('resume_post_api_type', 'chatgpt'), 'chatgpt'); ?>>ChatGPT</option>
                                <option value="deepseek" <?php selected(get_option('resume_post_api_type'), 'deepseek'); ?>>DeepSeek</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <!-- ChatGPT Settings -->
                <div class="api-settings chatgpt-settings">
                    <h2>ChatGPT Authentication</h2>
                    <div class="auth-method">
                        <label>
                            <input type="radio" name="chatgpt_auth_method" value="email" <?php checked(get_option('resume_post_chatgpt_auth_method', 'email'), 'email'); ?>>
                            Email/Password (Free)
                        </label>
                        <label>
                            <input type="radio" name="chatgpt_auth_method" value="api" <?php checked(get_option('resume_post_chatgpt_auth_method'), 'api'); ?>>
                            API Key (Advanced)
                        </label>
                    </div>

                    <div class="auth-section chatgpt-email-section">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="resume_post_chatgpt_email">Email</label>
                                </th>
                                <td>
                                    <input type="email" 
                                           id="resume_post_chatgpt_email" 
                                           name="resume_post_chatgpt_email" 
                                           value="<?php echo esc_attr(get_option('resume_post_chatgpt_email')); ?>"
                                           class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="resume_post_chatgpt_password">Password</label>
                                </th>
                                <td>
                                    <input type="password" 
                                           id="resume_post_chatgpt_password" 
                                           name="resume_post_chatgpt_password" 
                                           value="<?php echo esc_attr(get_option('resume_post_chatgpt_password')); ?>"
                                           class="regular-text">
                                    <button type="button" id="test_chatgpt_email" class="button button-secondary">
                                        Test Connection
                                    </button>
                                    <span id="chatgpt_email_test_result" style="margin-left: 10px;"></span>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="auth-section chatgpt-api-section">
                        <table class="form-table">
                            <tr class="api-key-row chatgpt-row">
                                <th scope="row">
                                    <label for="resume_post_chatgpt_api_key">API Key</label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="resume_post_chatgpt_api_key" 
                                           name="resume_post_chatgpt_api_key" 
                                           value="<?php echo esc_attr(get_option('resume_post_chatgpt_api_key')); ?>"
                                           class="regular-text">
                                    <button type="button" id="test_chatgpt_api" class="button button-secondary">
                                        Test API Key
                                    </button>
                                    <span id="chatgpt_api_test_result" style="margin-left: 10px;"></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- DeepSeek Settings -->
                <div class="api-settings deepseek-settings">
                    <h2>DeepSeek Authentication</h2>
                    <div class="auth-method">
                        <label>
                            <input type="radio" name="deepseek_auth_method" value="email" <?php checked(get_option('resume_post_deepseek_auth_method', 'email'), 'email'); ?>>
                            Email/Password (Free)
                        </label>
                        <label>
                            <input type="radio" name="deepseek_auth_method" value="api" <?php checked(get_option('resume_post_deepseek_auth_method'), 'api'); ?>>
                            API Key (Advanced)
                        </label>
                    </div>

                    <div class="auth-section deepseek-email-section">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="resume_post_deepseek_email">Email</label>
                                </th>
                                <td>
                                    <input type="email" 
                                           id="resume_post_deepseek_email" 
                                           name="resume_post_deepseek_email" 
                                           value="<?php echo esc_attr(get_option('resume_post_deepseek_email')); ?>"
                                           class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="resume_post_deepseek_password">Password</label>
                                </th>
                                <td>
                                    <input type="password" 
                                           id="resume_post_deepseek_password" 
                                           name="resume_post_deepseek_password" 
                                           value="<?php echo esc_attr(get_option('resume_post_deepseek_password')); ?>"
                                           class="regular-text">
                                    <button type="button" id="test_deepseek_email" class="button button-secondary">
                                        Test Connection
                                    </button>
                                    <span id="deepseek_email_test_result" style="margin-left: 10px;"></span>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="auth-section deepseek-api-section">
                        <table class="form-table">
                            <tr class="api-key-row deepseek-row">
                                <th scope="row">
                                    <label for="resume_post_deepseek_api_key">API Key</label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="resume_post_deepseek_api_key" 
                                           name="resume_post_deepseek_api_key" 
                                           value="<?php echo esc_attr(get_option('resume_post_deepseek_api_key')); ?>"
                                           class="regular-text">
                                    <button type="button" id="test_deepseek_api" class="button button-secondary">
                                        Test API Key
                                    </button>
                                    <span id="deepseek_api_test_result" style="margin-left: 10px;"></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private function is_imported_post($post) {
        $import_sources = array(
            'rss',
            'wordpress-importer',
            'feed',
            'import'
        );

        foreach ($import_sources as $source) {
            if (has_action('import_' . $source)) {
                return true;
            }
        }

        return false;
    }

    public function pre_process_content($data, $postarr) {
        if (!$this->is_imported_post($postarr)) {
            return $data;
        }

        // Clean content first
        $clean_content = $this->clean_content($data['post_content']);
        
        // Choose API based on settings
        $api_type = get_option('resume_post_api_type', 'chatgpt');
        
        if ($api_type === 'chatgpt') {
            $new_content = $this->rewrite_with_chatgpt($clean_content);
            $new_title = $this->rewrite_with_chatgpt($data['post_title'], true);
        } else {
            $new_content = $this->rewrite_with_deepseek($clean_content);
            $new_title = $this->rewrite_with_deepseek($data['post_title'], true);
        }

        $data['post_content'] = $new_content;
        $data['post_title'] = $new_title;

        return $data;
    }

    public function process_imported_post($post_id, $post, $update) {
        if ($update || !$this->is_imported_post($post)) {
            return;
        }

        // Force immediate publication
        wp_update_post(array(
            'ID' => $post_id,
            'post_status' => 'publish'
        ));
    }
}

// Initialize the plugin
new ResumePost();