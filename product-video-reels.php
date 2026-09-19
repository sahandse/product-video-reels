<?php
/**
 * Plugin Name: ویدئوی محصول و ریلز
 * Plugin URI: https://github.com/sahandse/product-video-reels
 * Description: افزودن ویدئوی افقی، عمودی و ریلز به محصولات ووکامرس از کتابخانه رسانه یا لینک خارجی.
 * Version: 1.1.0
 * Author: Sahand Rezvan
 * Author URI: https://github.com/sahandse
 * Text Domain: product-video-reels
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 */

defined('ABSPATH') || exit;

final class PVR_Plugin {
    const VERSION = '1.1.0';
    const OPTION  = 'pvr_settings';
    const META_URL = '_pvr_video_url';
    const META_LAYOUT = '_pvr_video_layout';
    const META_URLS = '_pvr_video_urls';

    public function __construct() {
        add_action('before_woocommerce_init', [$this, 'declare_hpos']);
        add_action('plugins_loaded', [$this, 'boot']);
    }

    public function declare_hpos() {
        if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
            Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                __FILE__,
                true
            );
        }
    }

    public function boot() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'woocommerce_notice']);
            return;
        }

        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);

        add_action('woocommerce_product_options_general_product_data', [$this, 'product_fields']);
        add_action('woocommerce_process_product_meta', [$this, 'save_product_fields']);
        add_action('woocommerce_single_product_summary', [$this, 'render_product_video'], 25);
    }

    public function woocommerce_notice() {
        echo '<div class="notice notice-error"><p>افزونه ویدئوی محصول و ریلز برای اجرا به WooCommerce نیاز دارد.</p></div>';
    }

    public function defaults() {
        return [
            'enabled' => 'yes',
            'default_layout' => 'reels',
            'autoplay' => 'no',
            'muted' => 'yes',
            'loop' => 'yes',
            'controls' => 'yes',
            'accent' => '#111827',
            'max_width' => 520,
        ];
    }

    public function settings() {
        return wp_parse_args((array)get_option(self::OPTION, []), $this->defaults());
    }

    public function register_settings() {
        register_setting('pvr_group', self::OPTION, [$this, 'sanitize_settings']);
    }

    public function sanitize_settings($in) {
        $d = $this->defaults();

        return [
            'enabled' => !empty($in['enabled']) ? 'yes' : 'no',
            'default_layout' => in_array($in['default_layout'] ?? '', ['horizontal','vertical','reels'], true)
                ? $in['default_layout']
                : $d['default_layout'],
            'autoplay' => !empty($in['autoplay']) ? 'yes' : 'no',
            'muted' => !empty($in['muted']) ? 'yes' : 'no',
            'loop' => !empty($in['loop']) ? 'yes' : 'no',
            'controls' => !empty($in['controls']) ? 'yes' : 'no',
            'accent' => sanitize_hex_color($in['accent'] ?? '') ?: $d['accent'],
            'max_width' => min(1200, max(240, absint($in['max_width'] ?? 520))),
        ];
    }

    public function admin_menu() {
        if (function_exists('s_store_register_submenu')) {
            s_store_register_submenu('product-video-reels', 'ویدئوی محصول و ریلز', [$this, 'settings_page'], 'manage_woocommerce', 'ویدئوی محصول و ریلز');
            return;
        }
        add_submenu_page(
            'woocommerce',
            'ویدئوی محصول و ریلز',
            'ویدئوی محصول',
            'manage_woocommerce',
            'product-video-reels',
            [$this, 'settings_page']
        );
    }

    public function admin_assets($hook) {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $is_product = $screen && 'product' === $screen->post_type;
        if (false === strpos($hook, 'product-video-reels') && !$is_product) return;
        wp_enqueue_style('pvr-admin', plugin_dir_url(__FILE__) . 'assets/admin.css', [], self::VERSION);
        if ($is_product) {
            wp_enqueue_media();
            wp_enqueue_script('pvr-admin', plugin_dir_url(__FILE__) . 'assets/admin.js', ['jquery'], self::VERSION, true);
        }
    }

    public function settings_page() {
        if (!current_user_can('manage_woocommerce')) return;
        $s = $this->settings();
        ?>
        <div class="wrap pvr-admin">
            <div class="pvr-hero">
                <div>
                    <h1>ویدئوی محصول و ریلز</h1>
                    <p>مدیریت نحوه نمایش ویدئو در صفحات محصولات ووکامرس.</p>
                </div>
                <span>v<?php echo esc_html(self::VERSION); ?></span>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('pvr_group'); ?>

                <div class="pvr-grid">
                    <section class="pvr-card">
                        <h2>تنظیمات عمومی</h2>

                        <label class="pvr-switch">
                            <span>فعال بودن افزونه</span>
                            <input type="checkbox" name="<?php echo self::OPTION; ?>[enabled]" value="1" <?php checked($s['enabled'],'yes'); ?>>
                        </label>

                        <label>حالت پیش‌فرض نمایش
                            <select name="<?php echo self::OPTION; ?>[default_layout]">
                                <option value="horizontal" <?php selected($s['default_layout'],'horizontal'); ?>>افقی</option>
                                <option value="vertical" <?php selected($s['default_layout'],'vertical'); ?>>عمودی</option>
                                <option value="reels" <?php selected($s['default_layout'],'reels'); ?>>ریلز</option>
                            </select>
                        </label>

                        <label>حداکثر عرض (px)
                            <input type="number" min="240" max="1200" name="<?php echo self::OPTION; ?>[max_width]" value="<?php echo esc_attr($s['max_width']); ?>">
                        </label>
                    </section>

                    <section class="pvr-card">
                        <h2>پخش ویدئو</h2>

                        <label class="pvr-switch"><span>پخش خودکار</span><input type="checkbox" name="<?php echo self::OPTION; ?>[autoplay]" value="1" <?php checked($s['autoplay'],'yes'); ?>></label>
                        <label class="pvr-switch"><span>شروع بی‌صدا</span><input type="checkbox" name="<?php echo self::OPTION; ?>[muted]" value="1" <?php checked($s['muted'],'yes'); ?>></label>
                        <label class="pvr-switch"><span>پخش تکراری</span><input type="checkbox" name="<?php echo self::OPTION; ?>[loop]" value="1" <?php checked($s['loop'],'yes'); ?>></label>
                        <label class="pvr-switch"><span>نمایش کنترل‌ها</span><input type="checkbox" name="<?php echo self::OPTION; ?>[controls]" value="1" <?php checked($s['controls'],'yes'); ?>></label>
                    </section>

                    <section class="pvr-card">
                        <h2>ظاهر</h2>
                        <label>رنگ اصلی
                            <input type="color" name="<?php echo self::OPTION; ?>[accent]" value="<?php echo esc_attr($s['accent']); ?>">
                        </label>
                    </section>

                    <section class="pvr-card">
                        <h2>گالری چندویدئویی</h2>
                        <p>در صفحه ویرایش هر محصول می‌توانید چند ویدئو را از Media Library انتخاب کنید یا لینک مستقیم وارد کنید.</p>
                    </section>
                </div>

                <?php submit_button('ذخیره تنظیمات'); ?>
            </form>
        </div>
        <?php
    }

    public function product_fields() {
        woocommerce_wp_text_input([
            'id' => self::META_URL,
            'label' => 'لینک ویدئو',
            'description' => 'لینک مستقیم فایل ویدئو یا فایل انتخاب‌شده از رسانه.',
            'desc_tip' => true,
            'type' => 'url',
        ]);

        woocommerce_wp_select([
            'id' => self::META_LAYOUT,
            'label' => 'حالت نمایش ویدئو',
            'options' => [
                '' => 'پیش‌فرض افزونه',
                'horizontal' => 'افقی',
                'vertical' => 'عمودی',
                'reels' => 'ریلز',
            ],
        ]);

        echo '<p class="form-field"><label for="' . esc_attr(self::META_URLS) . '">گالری ویدئوها</label>';
        echo '<textarea id="' . esc_attr(self::META_URLS) . '" name="' . esc_attr(self::META_URLS) . '" rows="5" style="width:50%" placeholder="هر خط یک لینک ویدئو">' . esc_textarea(implode("\n",(array)get_post_meta(get_the_ID(),self::META_URLS,true))) . '</textarea>';
        echo '<button type="button" class="button pvr-media-select" style="margin-right:8px">انتخاب از Media Library</button>';
        echo '<span class="description">چند فایل ویدئویی را انتخاب کنید؛ هر فایل در یک خط ذخیره می‌شود.</span></p>';

    }

    public function save_product_fields($post_id) {
        if (!current_user_can('edit_post', $post_id)) return;

        if (isset($_POST[self::META_URL])) {
            update_post_meta(
                $post_id,
                self::META_URL,
                esc_url_raw(wp_unslash($_POST[self::META_URL]))
            );
        }

        if (isset($_POST[self::META_LAYOUT])) {
            $layout = sanitize_text_field(wp_unslash($_POST[self::META_LAYOUT]));
            if (!in_array($layout, ['', 'horizontal','vertical','reels'], true)) $layout = '';
            update_post_meta($post_id, self::META_LAYOUT, $layout);
        }

        if (isset($_POST[self::META_URLS])) {
            $urls = array_values(array_filter(array_map('esc_url_raw', preg_split('/\r\n|\r|\n/', wp_unslash($_POST[self::META_URLS])))));
            update_post_meta($post_id, self::META_URLS, $urls);
        }
    }

    public function render_product_video() {
        $s = $this->settings();
        if ('yes' !== $s['enabled']) return;

        global $product;
        if (!$product) return;

        $urls = (array) get_post_meta($product->get_id(), self::META_URLS, true);
        $legacy = get_post_meta($product->get_id(), self::META_URL, true);
        if ($legacy) array_unshift($urls,$legacy);
        $urls = array_values(array_unique(array_filter(array_map('esc_url_raw',$urls))));
        if (!$urls) return;

        $layout = get_post_meta($product->get_id(), self::META_LAYOUT, true) ?: $s['default_layout'];
        $attrs = [];
        if ('yes' === $s['autoplay']) $attrs[] = 'autoplay';
        if ('yes' === $s['muted']) $attrs[] = 'muted';
        if ('yes' === $s['loop']) $attrs[] = 'loop';
        if ('yes' === $s['controls']) $attrs[] = 'controls';

        $aspect = 'horizontal' === $layout ? '16/9' : '9/16';
        echo '<div class="pvr-product-video pvr-' . esc_attr($layout) . '" style="--pvr-accent:' . esc_attr($s['accent']) . ';max-width:' . esc_attr((int)$s['max_width']) . 'px">';
        echo '<div class="pvr-video-gallery" style="display:flex;gap:12px;overflow-x:auto;scroll-snap-type:x mandatory">';
        foreach($urls as $url){
            echo '<video playsinline preload="metadata" ' . esc_attr(implode(' ', $attrs)) . ' style="flex:0 0 100%;width:100%;aspect-ratio:' . esc_attr($aspect) . ';object-fit:cover;border-radius:18px;background:#000;scroll-snap-align:start">';
            echo '<source src="' . esc_url($url) . '"></video>';
        }
        echo '</div>';
        if(count($urls)>1) echo '<small style="display:block;margin-top:6px;color:#777">برای دیدن ویدئوی بعدی ورق بزنید ←</small>';
        echo '</div>';
    }

}

new PVR_Plugin();
