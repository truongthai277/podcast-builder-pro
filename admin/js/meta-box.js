/**
 * Podcast Builder Pro — Meta Box JavaScript
 */
(function ($) {
    'use strict';

    var cfg = window.PBP || {};

    $(document).ready(function () {
        initGenerateButton();
        initToggleButtons();
    });

    // =============================================
    // GENERATE PODCAST BUTTON
    // =============================================
    function initGenerateButton() {
        $('#pbp-btn-generate').on('click', function () {
            var $btn    = $(this);
            var postId  = $btn.data('post-id');
            var status  = $btn.data('status');

            // Confirm if already generated
            if (status === 'done') {
                if (!confirm(cfg.strings.confirm_regen || 'Tạo lại sẽ ghi đè dữ liệu cũ. Tiếp tục?')) return;
            }

            // Save post first (prompt user)
            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                var isDirty = wp.data.select('core/editor').isEditedPostDirty();
                if (isDirty) {
                    alert('⚠️ Vui lòng lưu bài viết trước khi tạo podcast.');
                    return;
                }
            }

            startGeneration($btn, postId);
        });
    }

    function startGeneration($btn, postId) {
        // UI: loading state
        $btn.prop('disabled', true).text(cfg.strings.generating || 'Đang tạo...');
        $('#pbp-progress').show();
        animateProgress();

        $.post(cfg.ajax_url, {
            action:  'pbp_generate_episode',
            nonce:   cfg.nonce,
            post_id: postId,
        }, function (res) {
            stopProgress();

            if (res.success) {
                $('#pbp-progress .pbp-progress-msg').text(cfg.strings.done || '✅ Xong!');
                setTimeout(function () {
                    // Reload to reflect new status
                    location.reload();
                }, 1200);
            } else {
                var errMsg = (cfg.strings.error || 'Lỗi: ') + (res.data ? res.data.message : 'Unknown error');
                showError(errMsg);
                $btn.prop('disabled', false).text('🔄 Thử lại');
                $('#pbp-progress').hide();
            }
        }).fail(function (xhr) {
            stopProgress();
            showError('❌ Request thất bại (HTTP ' + xhr.status + '). Kiểm tra PHP error log.');
            $btn.prop('disabled', false).text('🔄 Thử lại');
            $('#pbp-progress').hide();
        });
    }

    // =============================================
    // PROGRESS ANIMATION
    // =============================================
    var progressTimer = null;
    var progressSteps = [
        'Đang kết nối AI...',
        'AI đang viết kịch bản podcast...',
        'Đang tạo audio TTS...',
        'Đang xây dựng show notes...',
        'Đang lưu vào database...',
        'Gần xong rồi...',
    ];
    var progressIdx = 0;

    function animateProgress() {
        progressIdx = 0;
        updateProgressMsg();
        progressTimer = setInterval(function () {
            progressIdx = (progressIdx + 1) % progressSteps.length;
            updateProgressMsg();
        }, 8000);
    }

    function updateProgressMsg() {
        $('#pbp-progress .pbp-progress-msg').text(progressSteps[progressIdx]);
    }

    function stopProgress() {
        if (progressTimer) {
            clearInterval(progressTimer);
            progressTimer = null;
        }
    }

    // =============================================
    // SHOW ERROR IN META BOX
    // =============================================
    function showError(msg) {
        var $existing = $('#pbp-error-notice');
        if ($existing.length) {
            $existing.text(msg);
        } else {
            $('<div id="pbp-error-notice" class="pbp-notice pbp-notice-error">')
                .text(msg)
                .insertBefore('#pbp-progress');
        }
    }

    // =============================================
    // TOGGLE BUTTONS (script / show notes preview)
    // =============================================
    function initToggleButtons() {
        $(document).on('click', '.pbp-btn-toggle', function () {
            var target = $(this).data('target');
            var $target = $('#' + target);
            $target.toggle(200);
            $(this).toggleClass('active');
        });
    }

})(jQuery);
