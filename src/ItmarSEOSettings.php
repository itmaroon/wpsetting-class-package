<?php

namespace Itmar\WpSettingClassPackage;

if (!defined('ABSPATH')) exit;

class ItmarSEOSettings
{
    private static $instance = null;

    private $enable_option = 'itmar_ogp_enabled';
    private $image_option = 'itmar_ogp_default_image';
    private $image_square = 'itmar_ogp_square_image';
    private $twitter_card_option = 'itmar_ogp_twitter_card';
    private $site_name_option = 'itmar_ogp_site_name';
    private $gsc_property_type = 'itmar_gsc_property_type';
    private $google_verification = 'itmar_google_verification';
    private $ga_measurement_id = 'itmar_ga_measurement_id';
    private $gtm_container_id = 'itmar_gtm_container_id';
    private $gtm_output_body = 'itmar_gtm_output_body';
    private $noindex_search = 'itmar_noindex_search';
    private $noindex_archive = 'itmar_noindex_archive';
    private $noindex_404 = 'itmar_noindex_404';

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // OGPタグ出力
        add_action('wp_head', [$this, 'output_ogp_tags']);

        // カスタムヘッダーコード出力
        add_action('wp_head', [$this, 'output_custom_header_code'], 99);

        // スクリプトのエンキュー
        add_action('admin_enqueue_scripts', [$this, 'enqueue_settings_scripts']);
    }

    /** OGPタグ出力 */
    public function output_ogp_tags()
    {
        if (!get_option($this->enable_option, 0)) return;

        $site_name = get_option($this->site_name_option, get_bloginfo('name'));
        $default_image = get_option($this->image_option, '');
        $square_image = get_option($this->image_square, '');
        $twitter_card = get_option($this->twitter_card_option, 'summary');
        $twitter_image = $twitter_card === 'summary' ? $square_image : $default_image;

?>
        <!-- ItmarSEOSettings OGP Tags -->
        <meta property="og:type" content="website" />
        <meta property="og:title" content="<?php echo esc_attr($site_name); ?>" />
        <meta property="og:description" content="<?php echo esc_attr(get_bloginfo('description')); ?>" />
        <meta property="og:image" content="<?php echo esc_url($default_image); ?>" />
        <meta property="og:url" content="<?php echo esc_url(home_url()); ?>" />
        <meta property="og:site_name" content="<?php echo esc_attr(get_bloginfo('name')); ?>" />
        <meta property="og:locale" content="ja_JP" />
        <!-- Twitter 専用 -->
        <meta name="twitter:card" content="<?php echo esc_attr($twitter_card); ?>" />
        <meta name="twitter:image" content="<?php echo esc_url($twitter_image); ?>" />
    <?php
    }

    /** Google SEOヘッダーコード出力 */
    public function output_custom_header_code()
    {
        //GSC設定タグ
        $property_type = get_option($this->gsc_property_type);
        $verification = get_option($this->google_verification);

        if ($property_type === 'prefix' && !empty($verification)) {
            echo '<meta name="google-site-verification" content="' . esc_attr($verification) . '">' . "\n";
        }
        //GA4設定タグ
        $ga_id = get_option($this->ga_measurement_id);
        if (!empty($ga_id)) {
            echo <<<EOD
            <!-- Google Analytics -->
            <script async src="https://www.googletagmanager.com/gtag/js?id={$ga_id}"></script>
            <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '{$ga_id}');
            </script>

            EOD;
        }
        //GTM設定タグ
        $gtm_id = get_option($this->gtm_container_id);
        if (!empty($gtm_id)) {
            echo <<<EOD
            <!-- Google Tag Manager -->
            <script>
            (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;
            j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;
            f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{$gtm_id}');
            </script>
            <!-- End Google Tag Manager -->
            EOD;
            echo "\n";
        }
        $output_body = get_option($this->gtm_output_body);
        if (!empty($gtm_id) && $output_body) {
            echo <<<EOD
            <!-- Google Tag Manager (noscript) -->
            <noscript>
            <iframe src="https://www.googletagmanager.com/ns.html?id={$gtm_id}"
            height="0" width="0" style="display:none;visibility:hidden"></iframe>
            </noscript>
            <!-- End Google Tag Manager (noscript) -->
            EOD;
            echo "\n";
        }
        //noindex設定
        if (
            (is_search() && get_option($this->noindex_search)) ||
            ((is_category() || is_tag() || is_date() || is_author() || is_archive()) && get_option($this->noindex_archive)) ||
            (is_404() && get_option($this->noindex_404))
        ) {
            echo '<meta name="robots" content="noindex, nofollow">' . "\n";
        }
    }

    /** 設定画面用スクリプト */
    public function enqueue_settings_scripts($hook)
    {
        if ($hook !== 'settings_page_itmar-extrasetting-settings') {
            return;
        }

        wp_enqueue_media();

        wp_add_inline_script('jquery-core', "
            jQuery(document).ready(function($) {

                function openMediaUploader(buttonSelector, inputSelector, previewSelector) {
                    $(buttonSelector).click(function(e) {
                        e.preventDefault();
                        var frame = wp.media({
                            title: 'Select or Upload Image',
                            button: { text: 'Use this image' },
                            multiple: false
                        });
                        frame.on('select', function() {
                            var attachment = frame.state().get('selection').first().toJSON();
                            $(inputSelector).val(attachment.url);
                            $(previewSelector).html('<img src=\"' + attachment.url + '\" style=\"max-width:150px; height:auto;\" />');
                        });
                        frame.open();
                    });
                }

                // 共通処理で各ボタンに設定
                openMediaUploader('#itmar_ogp_default_image_button', '#itmar_ogp_default_image', '#itmar_ogp_default_image_preview');
                openMediaUploader('#itmar_ogp_square_image_button', '#itmar_ogp_square_image', '#itmar_ogp_square_image_preview');

                // Exampleコードのトグル
                $('#itmar_seo_help_toggle').click(function() {
                    $('#itmar_seo_help_content').slideToggle();
                });

                // GSCのプロパティタイプ選択に応じて入力欄を表示
                $('input[name=\"itmar_gsc_property_type\"]').change(function () {
                    if ($(this).val() === 'prefix') {
                        $('#itmar_gsc_html_tag_input').slideDown();
                    } else {
                        $('#itmar_gsc_html_tag_input').slideUp();
                    }
                });
                
            });
        ");
    }

    /** 🔹 設定保存処理 */
    public function save_settings()
    {
        update_option($this->enable_option, isset($_POST[$this->enable_option]) ? 1 : 0);
        update_option($this->site_name_option, sanitize_text_field($_POST[$this->site_name_option] ?? ''));
        update_option($this->image_option, esc_url_raw($_POST[$this->image_option] ?? ''));
        update_option($this->image_square, esc_url_raw($_POST[$this->image_square] ?? ''));
        update_option($this->twitter_card_option, sanitize_text_field($_POST[$this->twitter_card_option] ?? 'summary'));
        if (isset($_POST[$this->gsc_property_type])) {
            update_option($this->gsc_property_type, sanitize_text_field($_POST[$this->gsc_property_type]));
        }

        if (isset($_POST[$this->google_verification])) {
            update_option($this->google_verification, sanitize_text_field($_POST[$this->google_verification]));
        }

        if (isset($_POST[$this->ga_measurement_id])) {
            update_option($this->ga_measurement_id, sanitize_text_field($_POST[$this->ga_measurement_id]));
        }
        if (isset($_POST[$this->gtm_container_id])) {
            update_option($this->gtm_container_id, sanitize_text_field($_POST[$this->gtm_container_id]));
        }
        update_option($this->gtm_output_body, isset($_POST[$this->gtm_output_body]) ? 1 : 0);

        update_option($this->noindex_search, isset($_POST[$this->noindex_search]) ? 1 : 0);
        update_option($this->noindex_archive, isset($_POST[$this->noindex_archive]) ? 1 : 0);
        update_option($this->noindex_404, isset($_POST[$this->noindex_404]) ? 1 : 0);
    }

    /** 設定画面HTML出力 */
    public function render_settings_section()
    {
    ?>
        <h2><?php echo __("OGP Settings", "wp-extra-settings"); ?></h2>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php echo __("Enable OGP Tags", "wp-extra-settings"); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="itmar_ogp_enabled" value="1" <?php checked(1, get_option('itmar_ogp_enabled', 0)); ?> />
                        <?php echo __("Output OGP meta tags in &lt;head&gt; section", "wp-extra-settings"); ?>
                    </label>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php echo __("Site Name for OGP", "wp-extra-settings"); ?></th>
                <td>
                    <input type="text" name="itmar_ogp_site_name" value="<?php echo esc_attr(get_option('itmar_ogp_site_name', get_bloginfo('name'))); ?>" class="regular-text" />
                    <p class="description"><?php echo __("If empty, the default site title will be used.", "wp-extra-settings"); ?></p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php echo __("Default OGP Image URL", "wp-extra-settings"); ?></th>
                <td>
                    <input type="text" id="itmar_ogp_default_image" name="itmar_ogp_default_image" value="<?php echo esc_url(get_option('itmar_ogp_default_image', '')); ?>" class="regular-text" />
                    <input type="button" class="button" id="itmar_ogp_default_image_button" value="<?php echo __("Select Image", "wp-extra-settings"); ?>" />
                    <div id="itmar_ogp_default_image_preview" style="margin-top:10px;">
                        <?php if ($img = esc_url(get_option('itmar_ogp_default_image', ''))): ?>
                            <img src="<?php echo $img; ?>" style="max-width:150px; height:auto;" />
                        <?php endif; ?>
                    </div>
                    <p class="description"><?php echo __("Select an image from media library or input URL.", "wp-extra-settings"); ?></p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php echo __("Square OGP Image URL", "wp-extra-settings"); ?></th>
                <td>
                    <input type="text" id="itmar_ogp_square_image" name="itmar_ogp_square_image" value="<?php echo esc_url(get_option('itmar_ogp_square_image', '')); ?>" class="regular-text" />
                    <input type="button" class="button" id="itmar_ogp_square_image_button" value="<?php echo __("Select Image", "wp-extra-settings"); ?>" />
                    <div id="itmar_ogp_square_image_preview" style="margin-top:10px;">
                        <?php if ($img = esc_url(get_option('itmar_ogp_square_image', ''))): ?>
                            <img src="<?php echo $img; ?>" style="max-width:150px; height:auto;" />
                        <?php endif; ?>
                    </div>
                    <p class="description"><?php echo __("Select an image from media library or input URL.", "wp-extra-settings"); ?></p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php echo __("Twitter Card Type", "wp-extra-settings"); ?></th>
                <td>
                    <select name="itmar_ogp_twitter_card">
                        <option value="summary" <?php selected('summary', get_option('itmar_ogp_twitter_card', 'summary')); ?>>summary</option>
                        <option value="summary_large_image" <?php selected('summary_large_image', get_option('itmar_ogp_twitter_card', 'summary')); ?>>summary_large_image</option>
                    </select>
                    <p class="description"><?php echo __("Select Twitter card type.", "wp-extra-settings"); ?></p>
                </td>
            </tr>

        </table>

        <h2><?php echo __("Google SEO Settings", "wp-extra-settings"); ?></h2>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php echo __("Google Search Console Setup", "wp-extra-settings"); ?></th>
                <td>
                    <p>
                        <?php echo __("Follow the steps below to connect your site with Google Search Console.", "wp-extra-settings"); ?>
                    </p>
                    <ol style="margin-left:20px;">
                        <li>
                            <a href="https://search.google.com/search-console/welcome" target="_blank">
                                <?php echo __("Open Google Search Console", "wp-extra-settings"); ?>
                            </a>
                        </li>
                        <li>
                            <?php echo __("Click 'Add Property' and choose one of the following types:", "wp-extra-settings"); ?>
                            <ul style="margin-left:20px;">
                                <li><strong><?php echo __("Domain"); ?></strong> – <?php echo __("Verifies all subdomains and protocols via DNS (recommended). No further steps needed here.", "wp-extra-settings"); ?></li>
                                <li><strong><?php echo __("URL Prefix"); ?></strong> – <?php echo __("Verifies a specific URL (e.g., https://example.com) via HTML tag.", "wp-extra-settings"); ?></li>
                            </ul>
                        </li>
                        <li>
                            <?php echo __("Select the verification method you used:", "wp-extra-settings"); ?><br>
                            <label>
                                <input type="radio" name="<?php echo esc_attr($this->gsc_property_type); ?>" value="domain"
                                    <?php checked(get_option($this->gsc_property_type), 'domain'); ?> />
                                <?php echo __("Domain (via DNS)", "wp-extra-settings"); ?>
                            </label><br>
                            <label>
                                <input type="radio" name="<?php echo esc_attr($this->gsc_property_type); ?>" value="prefix"
                                    <?php checked(get_option($this->gsc_property_type), 'prefix'); ?> />
                                <?php echo __("URL Prefix (via HTML tag)", "wp-extra-settings"); ?>
                            </label>
                        </li>
                    </ol>

                    <div id="itmar_gsc_html_tag_input" style="margin-top:10px; display: <?php echo get_option($this->gsc_property_type) === 'prefix' ? 'block' : 'none'; ?>;">
                        <label for="<?php echo esc_attr($this->google_verification); ?>">
                            <?php echo __("Paste the content value from the HTML tag:", "wp-extra-settings"); ?>
                        </label><br>
                        <input type="text" name="<?php echo esc_attr($this->google_verification); ?>" id="<?php echo esc_attr($this->google_verification); ?>" class="regular-text" value="<?php echo esc_attr(get_option($this->google_verification, '')); ?>" />
                        <p class="description">
                            <?php echo __("Use the following link to open the settings page:", "wp-extra-settings"); ?><br>
                            <a href="https://search.google.com/search-console/settings" target="_blank">https://search.google.com/search-console/settings</a><br>
                            <?php echo __("From there, go to 'Ownership verification' → 'HTML tag', and copy the content value from the tag.", "wp-extra-settings"); ?>
                        </p>
                    </div>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php echo __("Google Analytics (GA4) Setup", "wp-extra-settings"); ?></th>
                <td>
                    <p><?php echo __("If you haven't set up Google Analytics yet, follow the steps below:", "wp-extra-settings"); ?></p>
                    <ol style="margin-left:20px;">
                        <li>
                            <?php echo __("Go to"); ?>
                            <a href="https://analytics.google.com/analytics/web/" target="_blank">
                                <?php echo __("Google Analytics", "wp-extra-settings"); ?>
                            </a>
                            <?php echo __("and sign in with your Google account.", "wp-extra-settings"); ?><br>
                            <?php echo __("If this is your first time, you'll see a button labeled", "wp-extra-settings"); ?>
                            <strong><?php echo __("Start Measuring", "wp-extra-settings"); ?></strong>
                            <?php echo __("– click it to begin setup.", "wp-extra-settings"); ?>
                        </li>
                        <li>
                            <?php echo __("Create a new Analytics account. This is typically your organization or site name.", "wp-extra-settings"); ?>
                            <br />
                            <?php echo __("Next, create a property (e.g., MyWebsite) and choose your time zone and currency.", "wp-extra-settings"); ?>
                            (<a href="https://support.google.com/analytics/answer/9304153?hl=ja" target="_blank">
                                <?php echo __("See official setup guide", "wp-extra-settings"); ?>
                            </a>)
                        </li>
                        <li>
                            <?php echo __("In the 'Data Streams' step, choose", "wp-extra-settings"); ?> <strong><?php echo __("Web"); ?></strong>
                            <?php echo __("and enter your site's URL and stream name.", "wp-extra-settings"); ?>
                        </li>
                        <li>
                            <?php echo __("After setup, your 'Measurement ID' will be shown in this format:", "wp-extra-settings"); ?> <code>G-XXXXXXXXXX</code>
                            <?php echo __("– copy and paste it below.", "wp-extra-settings"); ?>
                        </li>
                    </ol>

                    <label for="<?php echo esc_attr($this->ga_measurement_id); ?>">
                        <?php echo __("Measurement ID", "wp-extra-settings"); ?>
                    </label><br>
                    <input type="text"
                        name="<?php echo esc_attr($this->ga_measurement_id); ?>"
                        id="<?php echo esc_attr($this->ga_measurement_id); ?>"
                        class="regular-text"
                        value="<?php echo esc_attr(get_option($this->ga_measurement_id, '')); ?>"
                        placeholder="G-XXXXXXXXXX" />
                    <p class="description">
                        <?php echo __("Starts with 'G-', e.g. G-ABC123XYZ", "wp-extra-settings"); ?>
                    </p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php echo __("Google Tag Manager (GTM)", "wp-extra-settings"); ?></th>
                <td>
                    <p>
                        <?php echo __("To use Google Tag Manager for managing various tracking codes (Google Analytics, Ads, etc.), follow the steps below:", "wp-extra-settings"); ?>
                    </p>
                    <ol style="margin-left:20px;">
                        <li>
                            <a href="https://tagmanager.google.com/" target="_blank">
                                <?php echo __("Open Google Tag Manager", "wp-extra-settings"); ?>
                            </a>
                            <?php echo __("and sign in with your Google account.", "wp-extra-settings"); ?>
                        </li>
                        <li>
                            <?php echo __("Click 'Create Account', enter your company or site name, select your country, and choose 'Web' as the target platform.", "wp-extra-settings"); ?>
                        </li>
                        <li>
                            <?php echo __("After setup, you’ll get a GTM Container ID that looks like", "wp-extra-settings"); ?> <code>GTM-XXXXXXX</code>.
                            <?php echo __("Paste it below.", "wp-extra-settings"); ?>
                        </li>
                    </ol>

                    <label for="<?php echo esc_attr($this->gtm_container_id); ?>">
                        <?php echo __("GTM Container ID", "wp-extra-settings"); ?>
                    </label><br>
                    <input type="text"
                        name="<?php echo esc_attr($this->gtm_container_id); ?>"
                        id="<?php echo esc_attr($this->gtm_container_id); ?>"
                        class="regular-text"
                        value="<?php echo esc_attr(get_option($this->gtm_container_id, '')); ?>"
                        placeholder="GTM-XXXXXXX" />
                    <p class="description"><?php echo __("Example: GTM-ABC123X", "wp-extra-settings"); ?></p>

                    <label>
                        <input type="checkbox"
                            name="<?php echo esc_attr($this->gtm_output_body); ?>"
                            value="1"
                            <?php checked(get_option($this->gtm_output_body), 1); ?> />
                        <?php echo __("Also output the noscript GTM code after the opening &lt;body&gt; tag (recommended)", "wp-extra-settings"); ?>
                    </label>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php echo __("Noindex Settings by Page Type", "wp-extra-settings"); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($this->noindex_search); ?>" value="1"
                            <?php checked(get_option($this->noindex_search), 1); ?> />
                        <?php echo __("Noindex on search result pages (?s=...)", "wp-extra-settings"); ?>
                    </label><br>

                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($this->noindex_archive); ?>" value="1"
                            <?php checked(get_option($this->noindex_archive), 1); ?> />
                        <?php echo __("Noindex on archive pages (category, tag, date)", "wp-extra-settings"); ?>
                    </label><br>

                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($this->noindex_404); ?>" value="1"
                            <?php checked(get_option($this->noindex_404), 1); ?> />
                        <?php echo __("Noindex on 404 not found pages", "wp-extra-settings"); ?>
                    </label>

                    <p class="description">
                        <?php echo __("Prevents selected types of pages from being indexed by search engines.", "wp-extra-settings"); ?>
                    </p>
                </td>
            </tr>

        </table>
<?php
    }
}
