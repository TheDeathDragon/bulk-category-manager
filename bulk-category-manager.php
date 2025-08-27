<?php
/**
 * Plugin Name: 批量分类管理器
 * Description: 为WordPress站点管理员提供专业的批量文章分类管理功能，支持筛选、搜索和批量移动文章分类目录
 * Version: 2.4.0
 * Requires at least: 5.0
 * Requires PHP: 8.0
 * Author: Shiro
 * Text Domain: bulk-category-manager
 * Network: false
 */

if (!defined('ABSPATH')) {
    exit;
}
define('BCM_VERSION', '2.4.0');
define('BCM_PLUGIN_FILE', __FILE__);
define('BCM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BCM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BCM_ASSETS_URL', BCM_PLUGIN_URL . 'assets/');

/**
 * Main plugin class for bulk category management
 */
class BulkCategoryManager {
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize plugin
     */
    private function init() {
        $this->load_dependencies();
        
        add_action('init', [$this, 'load_textdomain']);
        add_action('admin_init', [$this, 'check_requirements']);
        
        if ($this->can_user_access()) {
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
            add_action('wp_ajax_bulk_move_categories', [$this, 'handle_ajax_bulk_move']);
        }
        
        register_activation_hook(BCM_PLUGIN_FILE, [$this, 'on_activation']);
        register_deactivation_hook(BCM_PLUGIN_FILE, [$this, 'on_deactivation']);
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once BCM_PLUGIN_DIR . 'includes/class-admin-page.php';
        require_once BCM_PLUGIN_DIR . 'includes/class-ajax-handler.php';
    }
    
    /**
     * Load text domain for internationalization
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'bulk-category-manager',
            false,
            dirname(plugin_basename(BCM_PLUGIN_FILE)) . '/languages'
        );
    }
    
    /**
     * Check system requirements
     */
    public function check_requirements() {
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                printf(
                    __('批量分类管理器需要PHP 8.0或更高版本。您当前的PHP版本是 %s。', 'bulk-category-manager'),
                    PHP_VERSION
                );
                echo '</p></div>';
            });
            return false;
        }
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                printf(
                    __('批量分类管理器需要WordPress 5.0或更高版本。您当前的WordPress版本是 %s。', 'bulk-category-manager'),
                    get_bloginfo('version')
                );
                echo '</p></div>';
            });
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if current user can access the plugin
     */
    public function can_user_access(): bool {
        return current_user_can('manage_options');
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        $page_title = __('批量分类管理器', 'bulk-category-manager');
        $menu_title = __('批量分类管理', 'bulk-category-manager');
        
        add_management_page(
            $page_title,
            $menu_title,
            'manage_options',
            'bulk-category-manager',
            [new BCM_Admin_Page(), 'render_page']
        );
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts($hook) {
        if ('tools_page_bulk-category-manager' !== $hook) {
            return;
        }
        wp_enqueue_style(
            'bulk-category-manager',
            BCM_ASSETS_URL . 'style.css',
            [],
            BCM_VERSION
        );
        wp_enqueue_script(
            'bulk-category-manager',
            BCM_ASSETS_URL . 'script.js',
            ['jquery'],
            BCM_VERSION,
            true
        );
        wp_localize_script('bulk-category-manager', 'bulkCategoryManager', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bulk_category_manager_nonce'),
            'strings' => [
                'select_posts' => __('请选择至少一篇文章', 'bulk-category-manager'),
                'select_category' => __('请选择目标分类目录', 'bulk-category-manager'),
                'confirm_move' => __('确定要移动选中的文章到指定分类目录吗？', 'bulk-category-manager'),
                'warning_replace' => __('⚠️ 注意：这将替换文章当前的所有分类目录。', 'bulk-category-manager'),
                'moving' => __('正在移动文章...', 'bulk-category-manager'),
                'moved_success' => __('文章移动成功！', 'bulk-category-manager'),
                'moved_error' => __('移动过程中发生错误', 'bulk-category-manager'),
                'network_error' => __('发生网络错误，请重试。', 'bulk-category-manager')
            ]
        ]);
    }
    
    /**
     * Handle AJAX bulk move request
     */
    public function handle_ajax_bulk_move() {
        $ajax_handler = new BCM_Ajax_Handler();
        $ajax_handler->handle_bulk_move();
    }
    
    /**
     * Plugin activation hook
     */
    public function on_activation() {
        if (!$this->check_requirements()) {
            wp_die(__('系统不满足插件运行要求。', 'bulk-category-manager'));
        }
        
        update_option('bulk_category_manager_version', BCM_VERSION);
        update_option('bulk_category_manager_activated_time', time());
    }
    
    /**
     * Plugin deactivation hook
     */
    public function on_deactivation() {
        delete_transient('bcm_categories_cache');
    }
    
    /**
     * Get plugin version
     */
    public function get_version() {
        return BCM_VERSION;
    }
    
    /**
     * Get plugin data
     */
    public function get_plugin_data() {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return get_plugin_data(BCM_PLUGIN_FILE);
    }
}

add_action('plugins_loaded', function() {
    BulkCategoryManager::get_instance();
});

add_filter('plugin_action_links_' . plugin_basename(BCM_PLUGIN_FILE), function($links) {
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        admin_url('tools.php?page=bulk-category-manager'),
        __('管理', 'bulk-category-manager')
    );
    array_unshift($links, $settings_link);
    return $links;
});

add_filter('plugin_row_meta', function($links, $file) {
    if (plugin_basename(BCM_PLUGIN_FILE) === $file) {
        $row_meta = [
            'docs' => sprintf(
                '<a href="%s" target="_blank">%s</a>',
                'https://shiro.la',
                __('文档', 'bulk-category-manager')
            ),
            'support' => sprintf(
                '<a href="%s" target="_blank">%s</a>',
                'https://shiro.la',
                __('支持', 'bulk-category-manager')
            )
        ];
        return array_merge($links, $row_meta);
    }
    return $links;
}, 10, 2);