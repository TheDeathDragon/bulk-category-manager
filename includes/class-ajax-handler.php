<?php
/**
 * AJAX handler for processing bulk category operations
 * 
 * @package BulkCategoryManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class BCM_Ajax_Handler {
    
    /**
     * Handle bulk move category request
     */
    public function handle_bulk_move() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'bulk_category_manager_nonce')) {
            wp_send_json_error([
                'message' => __('安全验证失败，请刷新页面重试。', 'bulk-category-manager'),
                'code' => 'nonce_failed'
            ]);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('权限不足，只有站点管理员才能执行此操作。', 'bulk-category-manager'),
                'code' => 'insufficient_permissions'
            ]);
        }
        $post_ids = isset($_POST['post_ids']) ? array_map('intval', $_POST['post_ids']) : [];
        $target_category = isset($_POST['target_category']) ? intval($_POST['target_category']) : 0;
        if (empty($post_ids)) {
            wp_send_json_error([
                'message' => __('请选择至少一篇文章。', 'bulk-category-manager'),
                'code' => 'no_posts_selected'
            ]);
        }
        if ($target_category <= 0) {
            wp_send_json_error([
                'message' => __('请选择有效的目标分类目录。', 'bulk-category-manager'),
                'code' => 'invalid_category'
            ]);
        }
        if (!term_exists($target_category, 'category')) {
            wp_send_json_error([
                'message' => __('选择的分类目录不存在，请重新选择。', 'bulk-category-manager'),
                'code' => 'category_not_exists'
            ]);
        }
        $target_category_obj = get_category($target_category);
        if (!$target_category_obj || is_wp_error($target_category_obj)) {
            wp_send_json_error([
                'message' => __('无法获取分类信息，请重试。', 'bulk-category-manager'),
                'code' => 'category_info_failed'
            ]);
        }
        $result = $this->process_bulk_move($post_ids, $target_category);
        
        if ($result['success']) {
            wp_send_json_success([
                'message' => $this->format_success_message($result),
                'data' => [
                    'moved_count' => $result['moved_count'],
                    'total_count' => $result['total_count'],
                    'failed_count' => $result['failed_count'],
                    'target_category' => $target_category_obj->name,
                    'failed_posts' => $result['failed_posts']
                ]
            ]);
        } else {
            wp_send_json_error([
                'message' => $result['message'],
                'code' => 'move_failed',
                'data' => $result
            ]);
        }
    }
    
    /**
     * Process bulk move operation
     */
    private function process_bulk_move($post_ids, $target_category) {
        $moved_count = 0;
        $failed_posts = [];
        $total_count = count($post_ids);
        
        foreach ($post_ids as $post_id) {
            $move_result = $this->move_single_post($post_id, $target_category);
            
            if ($move_result['success']) {
                $moved_count++;
            } else {
                $failed_posts[] = [
                    'id' => $post_id,
                    'title' => get_the_title($post_id) ?: __('(无标题)', 'bulk-category-manager'),
                    'reason' => $move_result['reason']
                ];
            }
        }
        
        $failed_count = count($failed_posts);
        $this->log_operation($moved_count, $failed_count, $target_category);
        
        return [
            'success' => $moved_count > 0,
            'moved_count' => $moved_count,
            'failed_count' => $failed_count,
            'total_count' => $total_count,
            'failed_posts' => $failed_posts,
            'message' => $moved_count === 0 ? __('没有文章被移动，请检查权限或重试。', 'bulk-category-manager') : ''
        ];
    }
    
    /**
     * Move single post to target category
     */
    private function move_single_post($post_id, $target_category) {
        $post = get_post($post_id);
        if (!$post) {
            return [
                'success' => false,
                'reason' => __('文章不存在', 'bulk-category-manager')
            ];
        }
        if ('post' !== $post->post_type) {
            return [
                'success' => false,
                'reason' => __('非标准文章类型', 'bulk-category-manager')
            ];
        }
        if (!current_user_can('edit_post', $post_id)) {
            return [
                'success' => false,
                'reason' => __('没有编辑权限', 'bulk-category-manager')
            ];
        }
        $result = wp_set_post_categories($post_id, [$target_category], false);
        
        if (is_wp_error($result)) {
            return [
                'success' => false,
                'reason' => $result->get_error_message()
            ];
        }
        do_action('bcm_post_category_moved', $post_id, $target_category);
        
        return [
            'success' => true,
            'reason' => ''
        ];
    }
    
    /**
     * Format success message
     */
    private function format_success_message($result) {
        $moved = $result['moved_count'];
        $failed = $result['failed_count'];
        $total = $result['total_count'];
        
        if ($failed === 0) {
            return sprintf(
                _n(
                    '成功移动了 %d 篇文章！',
                    '成功移动了 %d 篇文章！',
                    $moved,
                    'bulk-category-manager'
                ),
                $moved
            );
        } else {
            return sprintf(
                __('成功移动了 %1$d 篇文章，%2$d 篇文章移动失败。', 'bulk-category-manager'),
                $moved,
                $failed
            );
        }
    }
    
    /**
     * Log operation for debugging
     */
    private function log_operation($moved_count, $failed_count, $target_category) {
        if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
            return;
        }
        
        $user = wp_get_current_user();
        $category = get_category($target_category);
        
        $log_message = sprintf(
            '[Bulk Category Manager] User %s (ID: %d) performed bulk move: %d succeeded, %d failed, target category: %s (ID: %d)',
            $user->user_login,
            $user->ID,
            $moved_count,
            $failed_count,
            $category ? $category->name : 'Unknown',
            $target_category
        );
        
        error_log($log_message);
    }
}