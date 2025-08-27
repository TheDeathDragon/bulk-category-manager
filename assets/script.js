/**
 * Frontend JavaScript for bulk category manager
 * 
 * @package BulkCategoryManager
 * @version 2.4.0
 */

(function($) {
    'use strict';
    
    let isProcessing = false;
    
    $(document).ready(function() {
        init();
        console.log('Bulk Category Manager: Frontend script loaded');
    });
    
    /**
     * Initialize all functions
     */
    function init() {
        initCheckboxes();
        initBulkActions();
        initTableInteractions();
        initKeyboardShortcuts();
        initTooltips();
        initPerPageSelector();
        initCategoryFilters();
    }
    
    /**
     * Initialize checkbox functionality
     */
    function initCheckboxes() {
        $('#select-all').on('change', function() {
            const isChecked = this.checked;
            $('.post-checkbox').prop('checked', isChecked);
            updateBulkActionButton();
            updateRowSelection();
        });
        $(document).on('change', '.post-checkbox', function() {
            updateSelectAllState();
            updateBulkActionButton();
            updateRowSelection();
        });
    }
    
    /**
     * Update select all state
     */
    function updateSelectAllState() {
        const $checkboxes = $('.post-checkbox');
        const totalCheckboxes = $checkboxes.length;
        const checkedCheckboxes = $checkboxes.filter(':checked').length;
        const $selectAll = $('#select-all');
        
        if (checkedCheckboxes === totalCheckboxes && totalCheckboxes > 0) {
            $selectAll.prop('checked', true).prop('indeterminate', false);
        } else if (checkedCheckboxes === 0) {
            $selectAll.prop('checked', false).prop('indeterminate', false);
        } else {
            $selectAll.prop('indeterminate', true);
        }
    }
    
    /**
     * Update row selection state
     */
    function updateRowSelection() {
        $('.post-checkbox').each(function() {
            const $row = $(this).closest('tr');
            if (this.checked) {
                $row.addClass('selected');
            } else {
                $row.removeClass('selected');
            }
        });
    }
    
    /**
     * Update bulk action button state
     */
    function updateBulkActionButton() {
        const checkedCount = $('.post-checkbox:checked').length;
        const $button = $('#bulk-move-btn');
        const $selectedCount = $('.selected-count');
        
        if (checkedCount > 0) {
            $button.prop('disabled', false);
            $button.text(`Move Selected (${checkedCount})`);
            $selectedCount.text(`${checkedCount} selected`).show();
        } else {
            $button.prop('disabled', true);
            $button.text('Move Selected');
            $selectedCount.hide();
        }
    }
    
    /**
     * Initialize bulk actions
     */
    function initBulkActions() {
        $('#bulk-category-form').on('submit', function(e) {
            e.preventDefault();
            
            if (isProcessing) {
                return;
            }
            
            processBulkMove();
        });
        $('#bulk-target-category').on('change', function() {
            const hasSelection = $('.post-checkbox:checked').length > 0;
            const hasCategory = $(this).val() !== '';
            
            $('#bulk-move-btn').prop('disabled', !(hasSelection && hasCategory));
        });
    }
    
    /**
     * Process bulk move
     */
    function processBulkMove() {
        const $checkedPosts = $('.post-checkbox:checked');
        const targetCategory = $('#bulk-target-category').val();
        const categoryText = $('#bulk-target-category option:selected').text();
        
        if ($checkedPosts.length === 0) {
            showNotice('error', bulkCategoryManager.strings.select_posts || 'Please select at least one post');
            return;
        }
        
        if (!targetCategory) {
            showNotice('error', bulkCategoryManager.strings.select_category || 'Please select target category');
            $('#bulk-target-category').focus();
            return;
        }
        
        const confirmMessage = `Are you sure you want to move ${$checkedPosts.length} selected posts to category "${categoryText}"?\n\n${bulkCategoryManager.strings.warning_replace || '⚠️ Warning: This will replace all current categories for these posts.'}`;
        
        if (!confirm(confirmMessage)) {
            return;
        }
        const postIds = $checkedPosts.map(function() {
            return $(this).val();
        }).get();
        executeAjaxMove(postIds, targetCategory);
    }
    
    /**
     * Execute AJAX move request
     */
    function executeAjaxMove(postIds, targetCategory) {
        isProcessing = true;
        showLoadingOverlay();
        
        const ajaxData = {
            action: 'bulk_move_categories',
            nonce: bulkCategoryManager.nonce,
            post_ids: postIds,
            target_category: targetCategory
        };
        
        $.ajax({
            url: bulkCategoryManager.ajax_url,
            type: 'POST',
            data: ajaxData,
            timeout: 30000,
            success: function(response) {
                handleAjaxSuccess(response);
            },
            error: function(xhr, status, error) {
                handleAjaxError(xhr, status, error);
            },
            complete: function() {
                isProcessing = false;
                hideLoadingOverlay();
            }
        });
    }
    
    /**
     * Handle AJAX success response
     */
    function handleAjaxSuccess(response) {
        if (response.success) {
            const data = response.data;
            let message = data.message;
            if (data.data.failed_count > 0) {
                message += '\n\nFailed posts:\n';
                data.data.failed_posts.forEach(function(post) {
                    message += `- ${post.title} (${post.reason})\n`;
                });
            }
            
            showNotice('success', message);
            resetForm();
            setTimeout(function() {
                window.location.reload();
            }, 2000);
            
        } else {
            showNotice('error', 'Operation failed: ' + (response.data.message || response.data));
        }
    }
    
    /**
     * Handle AJAX error
     */
    function handleAjaxError(xhr, status, error) {
        console.error('AJAX error:', { xhr, status, error });
        
        let errorMessage = bulkCategoryManager.strings.network_error || 'Network error occurred, please try again.';
        
        if (status === 'timeout') {
            errorMessage = 'Request timeout, please check network and try again.';
        } else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
            errorMessage = xhr.responseJSON.data.message;
        }
        
        showNotice('error', errorMessage);
    }
    
    /**
     * Show notice message
     */
    function showNotice(type, message) {
        $('.bcm-notice').remove();
        
        const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        const noticeHtml = `
            <div class="notice ${noticeClass} is-dismissible bcm-notice">
                <p>${escapeHtml(message)}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss notice</span>
                </button>
            </div>
        `;
        
        $('.wrap.bulk-category-manager').prepend(noticeHtml);
        $('html, body').animate({ scrollTop: 0 }, 500);
        if (type === 'success') {
            setTimeout(function() {
                $('.bcm-notice').fadeOut();
            }, 5000);
        }
    }
    
    /**
     * Reset form
     */
    function resetForm() {
        $('#select-all').prop('checked', false).prop('indeterminate', false);
        $('.post-checkbox').prop('checked', false);
        $('#bulk-target-category').val('');
        updateBulkActionButton();
        updateRowSelection();
    }
    
    /**
     * Show loading overlay
     */
    function showLoadingOverlay() {
        $('#bcm-loading-overlay').fadeIn(200);
        $('body').addClass('bcm-loading');
    }
    
    /**
     * Hide loading overlay
     */
    function hideLoadingOverlay() {
        $('#bcm-loading-overlay').fadeOut(200);
        $('body').removeClass('bcm-loading');
    }
    
    /**
     * Initialize table interactions
     */
    function initTableInteractions() {
        $(document).on('click', '.bcm-posts-table tbody tr', function(e) {
            if ($(e.target).is('a, button, input, .row-actions, .row-actions *')) {
                return;
            }
            
            const $checkbox = $(this).find('.post-checkbox');
            const $row = $(this);
            const newState = !$checkbox.prop('checked');
            $checkbox.prop('checked', newState);
            $checkbox.trigger('change');
        });
        $('.category-tag').on('click', function(e) {
            const href = $(this).attr('href');
            if (href && href !== window.location.href) {
                return;
            }
            e.preventDefault();
        });
    }
    
    /**
     * Initialize keyboard shortcuts
     */
    function initKeyboardShortcuts() {
        $(document).on('keydown', function(e) {
            if ($(e.target).is('input, textarea, select')) {
                return;
            }
            
            switch (e.key) {
                case 'a':
                case 'A':
                    if (e.ctrlKey || e.metaKey) {
                        e.preventDefault();
                        $('#select-all').prop('checked', true).trigger('change');
                    }
                    break;
                    
                case 'Escape':
                    e.preventDefault();
                    $('#select-all').prop('checked', false).trigger('change');
                    break;
                    
                case 'Enter':
                    if (e.ctrlKey || e.metaKey) {
                        e.preventDefault();
                        if (!$('#bulk-move-btn').prop('disabled')) {
                            $('#bulk-category-form').submit();
                        }
                    }
                    break;
            }
        });
    }
    
    /**
     * Initialize tooltips
     */
    function initTooltips() {
        $('#select-all').attr('title', 'Select all / Deselect all (Ctrl+A / Esc)');
        $('#bulk-move-btn').attr('title', 'Execute bulk move operation (Ctrl+Enter)');
    }
    
    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    /**
     * Initialize per page selector
     */
    function initPerPageSelector() {
        $('#per_page').on('change', function() {
            const $form = $(this).closest('form');
            const $select = $(this);
            $select.addClass('changing');
            const originalText = $select.find('option:selected').text();
            
            setTimeout(function() {
                $form.submit();
            }, 300);
        });
        $('#per_page').attr('title', 'Select posts per page, will auto-refresh after selection');
    }
    
    /**
     * Initialize category filters
     */
    function initCategoryFilters() {
        $('#cat').attr('title', 'Select category to show, only displays posts from this category');
        $('#exclude_cat').attr('title', 'Select category to exclude, will not display posts from this category');
        $('#exclude_cat').on('change', function() {
            const $this = $(this);
            const selectedText = $this.find('option:selected').text();
            
            if ($this.val() !== '0') {
                $this.addClass('has-exclusion');
                showCategoryExclusionTip(selectedText);
            } else {
                $this.removeClass('has-exclusion');
            }
        });
        $('#cat, #exclude_cat').on('change', function() {
            checkCategoryConflict();
        });
        checkCategoryConflict();
    }
    
    /**
     * Show category exclusion tip
     */
    function showCategoryExclusionTip(categoryName) {
        const $tip = $('<div class="category-exclusion-tip">').html(
            `<i>ℹ️</i> Will exclude all posts from category "${categoryName}"`
        );
        
        $('.filter-actions').before($tip);
        
        setTimeout(function() {
            $tip.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }
    
    /**
     * Check category filter conflicts
     */
    function checkCategoryConflict() {
        const includeCat = $('#cat').val();
        const excludeCat = $('#exclude_cat').val();
        
        if (includeCat !== '0' && excludeCat !== '0' && includeCat === excludeCat) {
            showCategoryConflictWarning();
        } else {
            removeCategoryConflictWarning();
        }
    }
    
    /**
     * Show category conflict warning
     */
    function showCategoryConflictWarning() {
        if ($('.category-conflict-warning').length === 0) {
            const $warning = $('<div class="category-conflict-warning">').html(
                `<i>⚠️</i> Warning: Cannot include and exclude the same category`
            );
            $('.filter-actions').before($warning);
        }
    }
    
    /**
     * Remove category conflict warning
     */
    function removeCategoryConflictWarning() {
        $('.category-conflict-warning').remove();
    }
    
    /**
     * Handle notice dismiss
     */
    $(document).on('click', '.notice-dismiss', function() {
        $(this).closest('.notice').fadeOut();
    });
    
    $(window).on('beforeunload', function(e) {
        if (isProcessing) {
            const message = 'Bulk move operation in progress, are you sure you want to leave the page?';
            e.returnValue = message;
            return message;
        }
    });
    
})(jQuery);