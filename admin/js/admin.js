/**
 * Podcast Builder Pro — Admin JavaScript
 */
(function ($) {
    'use strict';

    var PBP = window.PBP_Admin || {};

    // =============================================
    // INIT
    // =============================================
    $(document).ready(function () {
        initTabs();
        initDirectoryTable();
        initAddDirectoryModal();
        initLicense();
        initCoverImageUpload();
        initDynamicProviders();
        initCopyFeedUrl();
    });

    // =============================================
    // TABS
    // =============================================
    function initTabs() {
        var $tabs = $('.pbp-tab');
        $tabs.on('click', function (e) {
            e.preventDefault();
            var target = $(this).data('tab');
            $tabs.removeClass('active');
            $(this).addClass('active');
            $('.pbp-tab-content').hide();
            $('#' + target).show();
        });
    }

    // =============================================
    // COPY FEED URL
    // =============================================
    window.pbpCopyFeedUrl = function (inputId) {
        var id  = inputId || 'pbp-feed-url';
        var el  = document.getElementById(id);
        var val = el ? el.value || el.textContent : (PBP.feed_url || '');
        if (navigator.clipboard && val) {
            navigator.clipboard.writeText(val).then(function () {
                pbpToast('✅ Đã copy RSS Feed URL!', 'success');
            });
        }
    };

    // =============================================
    // DIRECTORY TABLE
    // =============================================
    function initDirectoryTable() {

        // Save directory
        $(document).on('click', '.pbp-dir-save', function () {
            var $btn  = $(this);
            var id    = $btn.data('id');
            var $row  = $btn.closest('tr');
            var status      = $row.find('.pbp-dir-status').val();
            var profile_url = $row.find('.pbp-dir-profile').val();
            var notes       = $row.find('.pbp-dir-notes').val() || '';

            $btn.prop('disabled', true).text('⏳');

            $.post(PBP.ajax_url, {
                action:      'pbp_update_directory',
                nonce:       PBP.nonce,
                id:          id,
                status:      status,
                profile_url: profile_url,
                notes:       notes,
            }, function (res) {
                if (res.success) {
                    pbpToast('✅ Đã lưu!', 'success');
                    $btn.text('✅').prop('disabled', false);
                    setTimeout(function () { $btn.text('💾 Lưu'); }, 2000);
                } else {
                    pbpToast('❌ ' + (res.data.message || 'Lỗi'), 'error');
                    $btn.text('💾 Lưu').prop('disabled', false);
                }
            }).fail(function () {
                pbpToast('❌ Request thất bại.', 'error');
                $btn.text('💾 Lưu').prop('disabled', false);
            });
        });

        // Delete directory
        $(document).on('click', '.pbp-dir-delete', function () {
            if (!confirm('Xóa directory này?')) return;
            var $btn = $(this);
            var id   = $btn.data('id');
            $.post(PBP.ajax_url, {
                action: 'pbp_delete_directory',
                nonce:  PBP.nonce,
                id:     id,
            }, function (res) {
                if (res.success) {
                    $btn.closest('tr').fadeOut(300, function () { $(this).remove(); });
                    pbpToast('🗑 Đã xóa.', 'success');
                } else {
                    pbpToast('❌ ' + (res.data.message || 'Lỗi'), 'error');
                }
            });
        });
    }

    // =============================================
    // ADD DIRECTORY MODAL
    // =============================================
    function initAddDirectoryModal() {
        var $modal = $('#pbp-add-dir-modal');

        $('#pbp-add-dir-btn').on('click', function () {
            $modal.fadeIn(200);
        });

        $('#pbp-add-dir-cancel').on('click', function () {
            $modal.fadeOut(200);
        });

        $modal.on('click', function (e) {
            if ($(e.target).is($modal)) $modal.fadeOut(200);
        });

        $('#pbp-add-dir-submit').on('click', function () {
            var label = $('#pbp-new-label').val().trim();
            var url   = $('#pbp-new-url').val().trim();
            var da    = $('#pbp-new-da').val();

            if (!label || !url) {
                pbpToast('⚠️ Vui lòng nhập tên và URL.', 'warning');
                return;
            }

            $.post(PBP.ajax_url, {
                action:     'pbp_add_directory',
                nonce:      PBP.nonce,
                label:      label,
                submit_url: url,
                da:         da,
            }, function (res) {
                if (res.success) {
                    pbpToast('✅ Đã thêm directory.', 'success');
                    $modal.fadeOut(200);
                    setTimeout(function () { location.reload(); }, 800);
                } else {
                    pbpToast('❌ ' + (res.data.message || 'Lỗi'), 'error');
                }
            });
        });
    }

    // =============================================
    // LICENSE
    // =============================================
    function initLicense() {
        $('#pbp-activate-btn').on('click', function () {
            var key = $('#pbp-license-key-input').val().trim();
            if (!key) {
                pbpToast('⚠️ Nhập license key.', 'warning');
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true).text('Đang kích hoạt...');
            $('#pbp-license-msg').html('<em>⏳ Đang xác thực...</em>');

            $.post(PBP.ajax_url, {
                action:      'pbp_activate_license',
                nonce:       PBP.nonce,
                license_key: key,
            }, function (res) {
                $btn.prop('disabled', false).text('Kích hoạt');
                if (res.success) {
                    $('#pbp-license-msg').html('<span style="color:#15803d;font-weight:600;">✅ ' + res.data.message + '</span>');
                    setTimeout(function () { location.reload(); }, 1200);
                } else {
                    $('#pbp-license-msg').html('<span style="color:#991b1b;">❌ ' + (res.data.message || 'Lỗi') + '</span>');
                }
            }).fail(function () {
                $btn.prop('disabled', false).text('Kích hoạt');
                $('#pbp-license-msg').html('<span style="color:#991b1b;">❌ Không thể kết nối server.</span>');
            });
        });

        $('#pbp-deactivate-btn').on('click', function () {
            if (!confirm('Hủy kích hoạt license trên domain này?')) return;
            $.post(PBP.ajax_url, {
                action: 'pbp_deactivate_license',
                nonce:  PBP.nonce,
            }, function (res) {
                if (res.success) {
                    pbpToast('License đã được hủy.', 'success');
                    setTimeout(function () { location.reload(); }, 800);
                }
            });
        });
    }

    // =============================================
    // COVER IMAGE UPLOAD (WP Media)
    // =============================================
    function initCoverImageUpload() {
        var frame;
        $('#pbp-select-cover').on('click', function (e) {
            e.preventDefault();
            if (frame) { frame.open(); return; }
            frame = wp.media({
                title:    'Chọn Cover Image',
                button:   { text: 'Dùng ảnh này' },
                multiple: false,
                library:  { type: 'image' },
            });
            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();
                $('#pbp-cover-img-id').val(attachment.id);
                // Show preview
                var $preview = $('#pbp-select-cover').prev('img');
                if ($preview.length) {
                    $preview.attr('src', attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url);
                } else {
                    $('<img>').attr({ src: attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url, style: 'width:100px;height:100px;object-fit:cover;margin-bottom:8px;display:block;' }).insertBefore('#pbp-select-cover');
                }
            });
            frame.open();
        });
    }

    // =============================================
    // DYNAMIC PROVIDERS (AI model & TTS voice select)
    // =============================================
    function initDynamicProviders() {
        // AI provider change → update models
        $('#pbp-ai-provider').on('change', function () {
            var provider = $(this).val();
            $.post(PBP.ajax_url, {
                action:   'pbp_get_models',
                nonce:    PBP.nonce,
                provider: provider,
            }, function (res) {
                if (!res.success) return;
                var $select = $('#pbp-ai-model');
                $select.empty();
                $.each(res.data, function (val, label) {
                    $select.append($('<option>').val(val).text(label));
                });
            });
        });

        // TTS provider change → update voices
        $('#pbp-tts-provider').on('change', function () {
            var provider = $(this).val();
            var isManual = (provider === 'manual');
            $('#pbp-tts-key-row').toggle(!isManual);

            $.post(PBP.ajax_url, {
                action:   'pbp_get_voices',
                nonce:    PBP.nonce,
                provider: provider,
            }, function (res) {
                if (!res.success) return;
                var voices = res.data;
                ['#pbp-voice-a', '#pbp-voice-b'].forEach(function (sel) {
                    var $s = $(sel);
                    $s.empty();
                    if (voices.length === 0) {
                        $s.append($('<option>').val('').text('— Không áp dụng —'));
                    } else {
                        voices.forEach(function (v) {
                            $s.append($('<option>').val(v).text(v));
                        });
                    }
                });
            });
        });
    }

    // =============================================
    // TOAST NOTIFICATIONS
    // =============================================
    function pbpToast(msg, type) {
        type = type || 'info';
        var colors = {
            success: { bg: '#f0fdf4', border: '#22c55e', text: '#15803d' },
            error:   { bg: '#fef2f2', border: '#ef4444', text: '#991b1b' },
            warning: { bg: '#fffbeb', border: '#f59e0b', text: '#92400e' },
            info:    { bg: '#f0f9ff', border: '#06b6d4', text: '#164e63' },
        };
        var c = colors[type] || colors.info;
        var $toast = $('<div>').css({
            position:     'fixed',
            bottom:       '24px',
            right:        '24px',
            background:   c.bg,
            border:       '1px solid ' + c.border,
            color:        c.text,
            padding:      '12px 20px',
            borderRadius: '10px',
            fontWeight:   '600',
            fontSize:     '14px',
            zIndex:       99999,
            boxShadow:    '0 4px 20px rgba(0,0,0,.12)',
            opacity:      0,
            maxWidth:     '360px',
        }).text(msg).appendTo('body');

        $toast.animate({ opacity: 1, bottom: '28px' }, 250);
        setTimeout(function () {
            $toast.animate({ opacity: 0, bottom: '20px' }, 300, function () { $toast.remove(); });
        }, 3000);
    }

    window.pbpToast = pbpToast;

})(jQuery);
