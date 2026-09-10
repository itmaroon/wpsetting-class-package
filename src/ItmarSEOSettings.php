<?php

namespace Itmar\WpsettingClassPackage;

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

        // GTM の noscript は <body> 直後に出す
        add_action('wp_body_open', [$this, 'output_gtm_noscript']);

        // スクリプトのエンキュー
        add_action('admin_enqueue_scripts', [$this, 'enqueue_settings_scripts']);
    }

    /**
     * OGPタグ出力
     *
     * 個別ページではその投稿の情報を出す。全ページでサイト共通の値を出していると、
     * どのページを共有しても同じURL・同じタイトルとして扱われ、SNS 上で個別の
     * ページが区別されない。
     */
    public function output_ogp_tags()
    {
        if (!get_option($this->enable_option, 0)) return;

        $site_name = get_option($this->site_name_option, get_bloginfo('name'));
        if ('' === $site_name) {
            $site_name = get_bloginfo('name');
        }
        $default_image = get_option($this->image_option, '');
        $square_image = get_option($this->image_square, '');
        $twitter_card = get_option($this->twitter_card_option, 'summary');

        // 既定はサイト全体の値
        $og_type  = 'website';
        $og_title = $site_name;
        $og_desc  = get_bloginfo('description');
        $og_url   = home_url('/');
        $og_image = $default_image;
        $sq_image = $square_image;

        if (is_singular()) {
            $post = get_queried_object();
            if ($post instanceof \WP_Post) {
                $og_type  = is_page() ? 'website' : 'article';
                $og_title = get_the_title($post);
                $og_url   = get_permalink($post);

                $excerpt = has_excerpt($post)
                    ? get_the_excerpt($post)
                    : wp_trim_words(wp_strip_all_tags(strip_shortcodes($post->post_content)), 60, '…');
                if ('' !== trim((string) $excerpt)) {
                    $og_desc = $excerpt;
                }

                $thumbnail = get_the_post_thumbnail_url($post, 'full');
                if ($thumbnail) {
                    $og_image = $thumbnail;
                    $sq_image = $thumbnail;
                }
            }
        }

        $twitter_image = $twitter_card === 'summary' ? $sq_image : $og_image;
        $og_locale     = $this->get_og_locale();

?>
        <!-- ItmarSEOSettings OGP Tags -->
        <meta property="og:type" content="<?php echo esc_attr($og_type); ?>" />
        <meta property="og:title" content="<?php echo esc_attr($og_title); ?>" />
        <meta property="og:description" content="<?php echo esc_attr($og_desc); ?>" />
        <?php if ('' !== $og_image) : ?>
            <meta property="og:image" content="<?php echo esc_url($og_image); ?>" />
        <?php endif; ?>
        <meta property="og:url" content="<?php echo esc_url($og_url); ?>" />
        <meta property="og:site_name" content="<?php echo esc_attr(get_bloginfo('name')); ?>" />
        <meta property="og:locale" content="<?php echo esc_attr($og_locale); ?>" />
        <!-- Twitter 専用 -->
        <meta name="twitter:card" content="<?php echo esc_attr($twitter_card); ?>" />
        <?php if ('' !== $twitter_image) : ?>
            <meta name="twitter:image" content="<?php echo esc_url($twitter_image); ?>" />
        <?php endif; ?>
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
        $ga_id = $this->get_valid_id($this->ga_measurement_id, '/^G-[A-Z0-9\-]+$/i');
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
        $gtm_id = $this->get_valid_id($this->gtm_container_id, '/^GTM-[A-Z0-9]+$/i');
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
        //noindex設定
        // is_archive() をそのまま使うと投稿タイプアーカイブまで巻き込むため、
        // 設定画面のラベルどおりターム系と日付・著者に限定する。
        $is_listing_archive = is_category() || is_tag() || is_tax() || is_date() || is_author();
        if (
            (is_search() && get_option($this->noindex_search)) ||
            ($is_listing_archive && get_option($this->noindex_archive)) ||
            (is_404() && get_option($this->noindex_404))
        ) {
            echo '<meta name="robots" content="noindex, nofollow">' . "\n";
        }
    }

    /**
     * OGP 用のロケール文字列を返す。
     *
     * OGP は language_TERRITORY 形式を期待するが、WordPress の get_locale() は
     * 日本語なら "ja" のように地域を持たないことがある。主要な言語だけ補う。
     */
    private function get_og_locale()
    {
        $locale = get_locale();
        if (str_contains($locale, '_')) {
            return $locale;
        }

        $fallback = array(
            'ja' => 'ja_JP',
            'ko' => 'ko_KR',
            'zh' => 'zh_CN',
            'en' => 'en_US',
            'de' => 'de_DE',
            'fr' => 'fr_FR',
            'es' => 'es_ES',
            'it' => 'it_IT',
            'th' => 'th_TH',
        );

        return $fallback[$locale] ?? $locale;
    }

    /**
     * 計測IDを検証して返す。想定の形式でなければ空文字。
     *
     * インラインスクリプトへ素の値を差し込むため、形式を固定しておく。
     */
    private function get_valid_id($option_name, $pattern)
    {
        $value = trim((string) get_option($option_name, ''));
        return ('' !== $value && preg_match($pattern, $value)) ? $value : '';
    }

    /**
     * GTM の noscript を <body> 直後に出力する。
     *
     * iframe は <head> 内に置けないため、wp_head ではなく wp_body_open に出す。
     * ブロックテーマは wp_body_open を必ず呼ぶ。
     */
    public function output_gtm_noscript()
    {
        $gtm_id = $this->get_valid_id($this->gtm_container_id, '/^GTM-[A-Z0-9]+$/i');
        if (empty($gtm_id) || !get_option($this->gtm_output_body)) {
            return;
        }

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
        <h2><?php echo esc_html__("OGP Settings", "wpsetting-class-package"); ?></h2>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php echo esc_html__("Enable OGP Tags", "wpsetting-class-package"); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="itmar_ogp_enabled" value="1" <?php checked(1, get_option('itmar_ogp_enabled', 0)); ?> />
                        <?php echo esc_html__("Output OGP meta tags in &lt;head&gt; section", "wpsetting-class-package"); ?>
                    </label>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php echo esc_html__("Site Name for OGP", "wpsetting-class-package"); ?></th>
                <td>
                    <input type="text" name="itmar_ogp_site_name" value="<?php echo esc_attr(get_option('itmar_ogp_site_name', get_bloginfo('name'))); ?>" class="regular-text" />
                    <p class="description"><?php echo esc_html__("If empty, the default site title will be used.", "wpsetting-class-package"); ?></p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php echo esc_html__("Default OGP Image URL", "wpsetting-class-package"); ?></th>
                <td>
                    <input type="text" id="itmar_ogp_default_image" name="itmar_ogp_default_image" value="<?php echo esc_url(get_option('itmar_ogp_default_image', '')); ?>" class="regular-text" />
                    <input type="button" class="button" id="itmar_ogp_default_image_button" value="<?php echo esc_html__("Select Image", "wpsetting-class-package"); ?>" />
                    <div id="itmar_ogp_default_image_preview" style="margin-top:10px;">
                        <?php if ($img = esc_url(get_option('itmar_ogp_default_image', ''))): ?>
                            <img src="<?php echo $img; ?>" style="max-width:150px; height:auto;" />
                        <?php endif; ?>
                    </div>
                    <p class="description"><?php echo esc_html__("Select an image from media library or input URL.", "wpsetting-class-package"); ?></p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php echo esc_html__("Square OGP Image URL", "wpsetting-class-package"); ?></th>
                <td>
                    <input type="text" id="itmar_ogp_square_image" name="itmar_ogp_square_image" value="<?php echo esc_url(get_option('itmar_ogp_square_image', '')); ?>" class="regular-text" />
                    <input type="button" class="button" id="itmar_ogp_square_image_button" value="<?php echo esc_html__("Select Image", "wpsetting-class-package"); ?>" />
                    <div id="itmar_ogp_square_image_preview" style="margin-top:10px;">
                        <?php if ($img = esc_url(get_option('itmar_ogp_square_image', ''))): ?>
                            <img src="<?php echo $img; ?>" style="max-width:150px; height:auto;" />
                        <?php endif; ?>
                    </div>
                    <p class="description"><?php echo esc_html__("Select an image from media library or input URL.", "wpsetting-class-package"); ?></p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php echo esc_html__("Twitter Card Type", "wpsetting-class-package"); ?></th>
                <td>
                    <select name="itmar_ogp_twitter_card">
                        <option value="summary" <?php selected('summary', get_option('itmar_ogp_twitter_card', 'summary')); ?>>summary</option>
                        <option value="summary_large_image" <?php selected('summary_large_image', get_option('itmar_ogp_twitter_card', 'summary')); ?>>summary_large_image</option>
                    </select>
                    <p class="description"><?php echo esc_html__("Select Twitter card type.", "wpsetting-class-package"); ?></p>
                </td>
            </tr>

        </table>

        <h2><?php echo esc_html__("Google SEO Settings", "wpsetting-class-package"); ?></h2>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php echo esc_html__("Google Search Console Setup", "wpsetting-class-package"); ?></th>
                <td>
                    <p>
                        <?php echo esc_html__("Follow the steps below to connect your site with Google Search Console.", "wpsetting-class-package"); ?>
                    </p>
                    <ol style="margin-left:20px;">
                        <li>
                            <a href="https://search.google.com/search-console/welcome" target="_blank">
                                <?php echo esc_html__("Open Google Search Console", "wpsetting-class-package"); ?>
                            </a>
                        </li>
                        <li>
                            <?php echo esc_html__("Click 'Add Property' and choose one of the following types:", "wpsetting-class-package"); ?>
                            <ul style="margin-left:20px;">
                                <li><strong><?php echo esc_html__("Domain", "wpsetting-class-package"); ?></strong> – <?php echo esc_html__("Verifies all subdomains and protocols via DNS (recommended). No further steps needed here.", "wpsetting-class-package"); ?></li>
                                <li><strong><?php echo esc_html__("URL Prefix", "wpsetting-class-package"); ?></strong> – <?php echo esc_html__("Verifies a specific URL (e.g., https://example.com) via HTML tag.", "wpsetting-class-package"); ?></li>
                            </ul>
                        </li>
                        <li>
                            <?php echo esc_html__("Select the verification method you used:", "wpsetting-class-package"); ?><br>
                            <label>
                                <input type="radio" name="<?php echo esc_attr($this->gsc_property_type); ?>" value="domain"
                                    <?php checked(get_option($this->gsc_property_type), 'domain'); ?> />
                                <?php echo esc_html__("Domain (via DNS)", "wpsetting-class-package"); ?>
                            </label><br>
                            <label>
                                <input type="radio" name="<?php echo esc_attr($this->gsc_property_type); ?>" value="prefix"
                                    <?php checked(get_option($this->gsc_property_type), 'prefix'); ?> />
                                <?php echo esc_html__("URL Prefix (via HTML tag)", "wpsetting-class-package"); ?>
                            </label>
                        </li>
                    </ol>

                    <div id="itmar_gsc_html_tag_input" style="margin-top:10px; display: <?php echo get_option($this->gsc_property_type) === 'prefix' ? 'block' : 'none'; ?>;">
                        <label for="<?php echo esc_attr($this->google_verification); ?>">
                            <?php echo esc_html__("Paste the content value from the HTML tag:", "wpsetting-class-package"); ?>
                        </label><br>
                        <input type="text" name="<?php echo esc_attr($this->google_verification); ?>" id="<?php echo esc_attr($this->google_verification); ?>" class="regular-text" value="<?php echo esc_attr(get_option($this->google_verification, '')); ?>" />
                        <p class="description">
                            <?php echo esc_html__("Use the following link to open the settings page:", "wpsetting-class-package"); ?><br>
                            <a href="https://search.google.com/search-console/settings" target="_blank">https://search.google.com/search-console/settings</a><br>
                            <?php echo esc_html__("From there, go to 'Ownership verification' → 'HTML tag', and copy the content value from the tag.", "wpsetting-class-package"); ?>
                        </p>
                    </div>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php echo esc_html__("Google Analytics (GA4) Setup", "wpsetting-class-package"); ?></th>
                <td>
                    <p><?php echo esc_html__("If you haven't set up Google Analytics yet, follow the steps below:", "wpsetting-class-package"); ?></p>
                    <ol style="margin-left:20px;">
                        <li>
                            <?php echo esc_html__("Go to", "wpsetting-class-package"); ?>
                            <a href="https://analytics.google.com/analytics/web/" target="_blank">
                                <?php echo esc_html__("Google Analytics", "wpsetting-class-package"); ?>
                            </a>
                            <?php echo esc_html__("and sign in with your Google account.", "wpsetting-class-package"); ?><br>
                            <?php echo esc_html__("If this is your first time, you'll see a button labeled", "wpsetting-class-package"); ?>
                            <strong><?php echo esc_html__("Start Measuring", "wpsetting-class-package"); ?></strong>
                            <?php echo esc_html__("– click it to begin setup.", "wpsetting-class-package"); ?>
                        </li>
                        <li>
                            <?php echo esc_html__("Create a new Analytics account. This is typically your organization or site name.", "wpsetting-class-package"); ?>
                            <br />
                            <?php echo esc_html__("Next, create a property (e.g., MyWebsite) and choose your time zone and currency.", "wpsetting-class-package"); ?>
                            (<a href="https://support.google.com/analytics/answer/9304153?hl=ja" target="_blank">
                                <?php echo esc_html__("See official setup guide", "wpsetting-class-package"); ?>
                            </a>)
                        </li>
                        <li>
                            <?php echo esc_html__("In the 'Data Streams' step, choose", "wpsetting-class-package"); ?> <strong><?php echo esc_html__("Web", "wpsetting-class-package"); ?></strong>
                            <?php echo esc_html__("and enter your site's URL and stream name.", "wpsetting-class-package"); ?>
                        </li>
                        <li>
                            <?php echo esc_html__("After setup, your 'Measurement ID' will be shown in this format:", "wpsetting-class-package"); ?> <code>G-XXXXXXXXXX</code>
                            <?php echo esc_html__("– copy and paste it below.", "wpsetting-class-package"); ?>
                        </li>
                    </ol>

                    <label for="<?php echo esc_attr($this->ga_measurement_id); ?>">
                        <?php echo esc_html__("Measurement ID", "wpsetting-class-package"); ?>
                    </label><br>
                    <input type="text"
                        name="<?php echo esc_attr($this->ga_measurement_id); ?>"
                        id="<?php echo esc_attr($this->ga_measurement_id); ?>"
                        class="regular-text"
                        value="<?php echo esc_attr(get_option($this->ga_measurement_id, '')); ?>"
                        placeholder="G-XXXXXXXXXX" />
                    <p class="description">
                        <?php echo esc_html__("Starts with 'G-', e.g. G-ABC123XYZ", "wpsetting-class-package"); ?>
                    </p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php echo esc_html__("Google Tag Manager (GTM)", "wpsetting-class-package"); ?></th>
                <td>
                    <p>
                        <?php echo esc_html__("To use Google Tag Manager for managing various tracking codes (Google Analytics, Ads, etc.), follow the steps below:", "wpsetting-class-package"); ?>
                    </p>
                    <ol style="margin-left:20px;">
                        <li>
                            <a href="https://tagmanager.google.com/" target="_blank">
                                <?php echo esc_html__("Open Google Tag Manager", "wpsetting-class-package"); ?>
                            </a>
                            <?php echo esc_html__("and sign in with your Google account.", "wpsetting-class-package"); ?>
                        </li>
                        <li>
                            <?php echo esc_html__("Click 'Create Account', enter your company or site name, select your country, and choose 'Web' as the target platform.", "wpsetting-class-package"); ?>
                        </li>
                        <li>
                            <?php echo esc_html__("After setup, you’ll get a GTM Container ID that looks like", "wpsetting-class-package"); ?> <code>GTM-XXXXXXX</code>.
                            <?php echo esc_html__("Paste it below.", "wpsetting-class-package"); ?>
                        </li>
                    </ol>

                    <label for="<?php echo esc_attr($this->gtm_container_id); ?>">
                        <?php echo esc_html__("GTM Container ID", "wpsetting-class-package"); ?>
                    </label><br>
                    <input type="text"
                        name="<?php echo esc_attr($this->gtm_container_id); ?>"
                        id="<?php echo esc_attr($this->gtm_container_id); ?>"
                        class="regular-text"
                        value="<?php echo esc_attr(get_option($this->gtm_container_id, '')); ?>"
                        placeholder="GTM-XXXXXXX" />
                    <p class="description"><?php echo esc_html__("Example: GTM-ABC123X", "wpsetting-class-package"); ?></p>
                    <?php if ('' !== trim((string) get_option($this->ga_measurement_id, '')) && '' !== trim((string) get_option($this->gtm_container_id, ''))) : ?>
                        <p class="description" style="color:#b32d2e;">
                            <?php echo esc_html__("Both a GA4 Measurement ID and a GTM Container ID are set. If GTM also fires a GA4 tag, page views will be counted twice. Use only one of them.", "wpsetting-class-package"); ?>
                        </p>
                    <?php endif; ?>

                    <label>
                        <input type="checkbox"
                            name="<?php echo esc_attr($this->gtm_output_body); ?>"
                            value="1"
                            <?php checked(get_option($this->gtm_output_body), 1); ?> />
                        <?php echo esc_html__("Also output the noscript GTM code after the opening &lt;body&gt; tag (recommended)", "wpsetting-class-package"); ?>
                    </label>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php echo esc_html__("Noindex Settings by Page Type", "wpsetting-class-package"); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($this->noindex_search); ?>" value="1"
                            <?php checked(get_option($this->noindex_search), 1); ?> />
                        <?php echo esc_html__("Noindex on search result pages (?s=...)", "wpsetting-class-package"); ?>
                    </label><br>

                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($this->noindex_archive); ?>" value="1"
                            <?php checked(get_option($this->noindex_archive), 1); ?> />
                        <?php echo esc_html__("Noindex on archive pages (category, tag, date)", "wpsetting-class-package"); ?>
                    </label><br>

                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($this->noindex_404); ?>" value="1"
                            <?php checked(get_option($this->noindex_404), 1); ?> />
                        <?php echo esc_html__("Noindex on 404 not found pages", "wpsetting-class-package"); ?>
                    </label>

                    <p class="description">
                        <?php echo esc_html__("Prevents selected types of pages from being indexed by search engines.", "wpsetting-class-package"); ?>
                    </p>
                </td>
            </tr>
        </table>
<?php
    }
}
