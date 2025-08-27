<?php
/**
 * Admin page class for rendering the bulk category management interface
 * 
 * @package BulkCategoryManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class BCM_Admin_Page {
    
    /**
     * Render the admin page
     */
    public function render_page() {
        $current_category = isset($_GET['cat']) ? intval($_GET['cat']) : 0;
        $exclude_category = isset($_GET['exclude_cat']) ? intval($_GET['exclude_cat']) : 0;
        $search_keyword = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $per_page_options = [20, 30, 50, 100];
        $per_page = isset($_GET['per_page']) && in_array(intval($_GET['per_page']), $per_page_options) 
                   ? intval($_GET['per_page']) : 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $args = [
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $current_page,
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        $tax_queries = [];
        
        if ($current_category > 0) {
            $tax_queries[] = [
                'taxonomy' => 'category',
                'field'    => 'term_id',
                'terms'    => $current_category,
                'operator' => 'IN'
            ];
        }
        
        if ($exclude_category > 0) {
            $tax_queries[] = [
                'taxonomy' => 'category',
                'field'    => 'term_id',
                'terms'    => $exclude_category,
                'operator' => 'NOT IN'
            ];
        }
        
        if (!empty($tax_queries)) {
            if (count($tax_queries) > 1) {
                $tax_queries['relation'] = 'AND';
            }
            $args['tax_query'] = $tax_queries;
        }
        
        if (!empty($search_keyword)) {
            add_filter('posts_search', [$this, 'search_by_title_only'], 500, 2);
            $args['s'] = $search_keyword;
        }
        
        $posts_query = new WP_Query($args);
        $categories = get_categories(['hide_empty' => false, 'orderby' => 'name']);
        
        ?>
        <div class="wrap bulk-category-manager">
            <h1><?php _e('批量分类管理器', 'bulk-category-manager'); ?>
                <span class="version">v<?php echo BCM_VERSION; ?></span>
            </h1>
            
            <div class="bcm-description">
                <p><?php _e('文章分类批量管理工具，支持筛选、搜索和批量移动文章分类目录。', 'bulk-category-manager'); ?></p>
            </div>
            
            <div class="bcm-filter-form">
                <form method="get" action="" class="filter-form">
                    <input type="hidden" name="page" value="bulk-category-manager">
                    
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="cat"><?php _e('包含分类：', 'bulk-category-manager'); ?></label>
                            <select name="cat" id="cat">
                                <option value="0"><?php _e('所有分类', 'bulk-category-manager'); ?></option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category->term_id; ?>" 
                                            <?php selected($current_category, $category->term_id); ?>>
                                        <?php printf('%s (%d)', esc_html($category->name), $category->count); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="exclude_cat"><?php _e('排除分类：', 'bulk-category-manager'); ?></label>
                            <select name="exclude_cat" id="exclude_cat">
                                <option value="0"><?php _e('不排除', 'bulk-category-manager'); ?></option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category->term_id; ?>" 
                                            <?php selected($exclude_category, $category->term_id); ?>>
                                        <?php printf('%s (%d)', esc_html($category->name), $category->count); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="per_page"><?php _e('每页显示：', 'bulk-category-manager'); ?></label>
                            <select name="per_page" id="per_page">
                                <?php foreach ($per_page_options as $option): ?>
                                    <option value="<?php echo $option; ?>" <?php selected($per_page, $option); ?>>
                                        <?php printf(__('%d 篇文章', 'bulk-category-manager'), $option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="s"><?php _e('标题搜索：', 'bulk-category-manager'); ?></label>
                            <input type="text" name="s" id="s" value="<?php echo esc_attr($search_keyword); ?>" 
                                   placeholder="<?php esc_attr_e('仅搜索文章标题...', 'bulk-category-manager'); ?>">
                        </div>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="button button-primary"><?php _e('筛选', 'bulk-category-manager'); ?></button>
                        <a href="<?php echo admin_url('tools.php?page=bulk-category-manager'); ?>" 
                           class="button"><?php _e('重置', 'bulk-category-manager'); ?></a>
                    </div>
                </form>
            </div>
            
            <?php if ($posts_query->have_posts()): ?>
                <form id="bulk-category-form" method="post" class="bcm-main-form">
                    <?php wp_nonce_field('bulk_category_manager_nonce', 'bulk_category_nonce'); ?>
                    
                    <div class="bcm-bulk-actions">
                        <div class="bulk-action-group">
                            <label for="bulk-target-category"><?php _e('移动到分类：', 'bulk-category-manager'); ?></label>
                            <select id="bulk-target-category" name="target_category" class="target-category-select">
                                <option value=""><?php _e('-- 请选择分类目录 --', 'bulk-category-manager'); ?></option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category->term_id; ?>">
                                        <?php printf('%s (%d 篇文章)', esc_html($category->name), $category->count); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="bulk-action-buttons">
                            <button type="submit" class="button button-primary" id="bulk-move-btn" disabled>
                                <?php _e('移动选中文章', 'bulk-category-manager'); ?>
                            </button>
                            <span class="selected-count"></span>
                        </div>
                    </div>
                    
                    <table class="wp-list-table widefat fixed striped posts bcm-posts-table">
                        <thead>
                            <tr>
                                <td class="check-column">
                                    <input type="checkbox" id="select-all" title="<?php esc_attr_e('全选/取消全选', 'bulk-category-manager'); ?>">
                                </td>
                                <th scope="col" class="column-title column-primary">
                                    <?php _e('标题', 'bulk-category-manager'); ?>
                                </th>
                                <th scope="col" class="column-author">
                                    <?php _e('作者', 'bulk-category-manager'); ?>
                                </th>
                                <th scope="col" class="column-categories">
                                    <?php _e('分类', 'bulk-category-manager'); ?>
                                </th>
                                <th scope="col" class="column-date">
                                    <?php _e('发布时间', 'bulk-category-manager'); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($posts_query->have_posts()): $posts_query->the_post(); ?>
                                <tr data-post-id="<?php echo get_the_ID(); ?>">
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="post_ids[]" value="<?php echo get_the_ID(); ?>" 
                                               class="post-checkbox" title="<?php esc_attr_e('选择这篇文章', 'bulk-category-manager'); ?>">
                                    </th>
                                    <td class="column-title column-primary">
                                        <strong>
                                            <a href="<?php echo get_edit_post_link(); ?>" target="_blank" class="post-title-link">
                                                <?php echo esc_html(get_the_title() ?: __('(无标题)', 'bulk-category-manager')); ?>
                                            </a>
                                        </strong>
                                        <div class="row-actions">
                                            <span class="view">
                                                <a href="<?php echo get_permalink(); ?>" target="_blank">
                                                    <?php _e('查看', 'bulk-category-manager'); ?>
                                                </a>
                                            </span>
                                            <?php if (current_user_can('edit_post', get_the_ID())): ?>
                                                | <span class="edit">
                                                    <a href="<?php echo get_edit_post_link(); ?>" target="_blank">
                                                        <?php _e('编辑', 'bulk-category-manager'); ?>
                                                    </a>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="column-author">
                                        <a href="<?php echo admin_url('edit.php?author=' . get_the_author_meta('ID')); ?>">
                                            <?php echo get_the_author(); ?>
                                        </a>
                                    </td>
                                    <td class="column-categories">
                                        <?php
                                        $post_categories = get_the_category();
                                        if ($post_categories) {
                                            foreach ($post_categories as $cat) {
                                                $category_link = admin_url('tools.php?page=bulk-category-manager&cat=' . $cat->term_id);
                                                echo sprintf(
                                                    '<a href="%s" class="category-tag" title="%s">%s</a> ',
                                                    esc_url($category_link),
                                                    esc_attr(sprintf(__('筛选分类：%s', 'bulk-category-manager'), $cat->name)),
                                                    esc_html($cat->name)
                                                );
                                            }
                                        } else {
                                            echo '<span class="no-category">' . __('未分类', 'bulk-category-manager') . '</span>';
                                        }
                                        ?>
                                    </td>
                                    <td class="column-date">
                                        <abbr title="<?php echo get_the_date('c'); ?>">
                                            <?php echo get_the_date('Y-m-d H:i'); ?>
                                        </abbr>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </form>
                
                <?php $this->render_pagination($posts_query, $current_page, $current_category, $exclude_category, $search_keyword, $per_page); ?>
                
                <div class="bcm-posts-info">
                    <p class="posts-count">
                        <?php
                        printf(
                            __('显示第 %d - %d 篇文章，共 %d 篇文章', 'bulk-category-manager'),
                            (($current_page - 1) * $per_page + 1),
                            min($current_page * $per_page, $posts_query->found_posts),
                            $posts_query->found_posts
                        );
                        ?>
                    </p>
                </div>
                
            <?php else: ?>
                <div class="bcm-no-posts">
                    <div class="no-posts-message">
                        <h3><?php _e('没有找到符合条件的文章', 'bulk-category-manager'); ?></h3>
                        <p><?php _e('请尝试调整筛选条件或搜索关键字。', 'bulk-category-manager'); ?></p>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php wp_reset_postdata(); ?>
        </div>
        
        <div id="bcm-loading-overlay" class="bcm-loading-overlay" style="display: none;">
            <div class="loading-content">
                <div class="spinner is-active"></div>
                <p><?php _e('正在移动文章，请稍候...', 'bulk-category-manager'); ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render pagination navigation
     */
    private function render_pagination($posts_query, $current_page, $current_category = 0, $exclude_category = 0, $search_keyword = '', $per_page = 20) {
        if ($posts_query->max_num_pages <= 1) {
            return;
        }
        $base_args = ['page' => 'bulk-category-manager'];
        if ($current_category > 0) {
            $base_args['cat'] = $current_category;
        }
        if ($exclude_category > 0) {
            $base_args['exclude_cat'] = $exclude_category;
        }
        if (!empty($search_keyword)) {
            $base_args['s'] = $search_keyword;
        }
        if ($per_page != 20) {
            $base_args['per_page'] = $per_page;
        }
        
        $pagination_args = [
            'base' => add_query_arg(array_merge($base_args, ['paged' => '%#%'])),
            'format' => '',
            'total' => $posts_query->max_num_pages,
            'current' => $current_page,
            'show_all' => false,
            'end_size' => 1,
            'mid_size' => 2,
            'prev_next' => true,
            'prev_text' => '&laquo; ' . __('上一页', 'bulk-category-manager'),
            'next_text' => __('下一页', 'bulk-category-manager') . ' &raquo;',
            'type' => 'plain',
            'add_args' => false
        ];
        
        echo '<div class="bcm-pagination">';
        echo paginate_links($pagination_args);
        echo '</div>';
    }
    
    /**
     * Limit search to post titles only
     */
    public function search_by_title_only($search, $wp_query) {
        global $wpdb;
        
        if (empty($search)) {
            return $search;
        }
        
        $q = $wp_query->query_vars;
        $n = !empty($q['exact']) ? '' : '%';
        $search = '';
        $searchand = '';
        
        foreach ((array) $q['search_terms'] as $term) {
            $term = esc_sql(like_escape($term));
            $search .= "{$searchand}($wpdb->posts.post_title LIKE '{$n}{$term}{$n}')";
            $searchand = ' AND ';
        }
        
        if (!empty($search)) {
            $search = " AND ({$search}) ";
            if (!is_user_logged_in()) {
                $search .= " AND ($wpdb->posts.post_password = '') ";
            }
        }
        
        remove_filter('posts_search', [$this, 'search_by_title_only'], 500);
        
        return $search;
    }
}