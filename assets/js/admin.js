/**
 * LLMO Blog Optimizer - Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        var strings = (window.llmoAdmin && llmoAdmin.strings) ? llmoAdmin.strings : {};
        var pollInterval = (llmoAdmin && llmoAdmin.pollInterval) ? llmoAdmin.pollInterval : 10000;
        var pollMaxAttempts = (llmoAdmin && llmoAdmin.pollMaxAttempts) ? llmoAdmin.pollMaxAttempts : 30;
        var confirmTimer = null;
        var $confirmButton = null;
        var elapsedTimer = null;

        if (llmoAdmin && llmoAdmin.redirectUrl) {
            window.location.replace(llmoAdmin.redirectUrl);
            return;
        }

        function pollUntilDone(postId, attempt) {
            attempt = attempt || 0;

            var deferred = $.Deferred();

            if (attempt >= pollMaxAttempts) {
                deferred.resolve({ status: 'timeout' });
                return deferred.promise();
            }

            $.ajax({
                url: llmoAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'llmo_poll_optimization',
                    nonce: llmoAdmin.nonce,
                    post_id: postId
                }
            }).done(function(response) {
                if (response && response.success && response.data && response.data.status === 'done') {
                    deferred.resolve(response.data);
                    return;
                }

                if (response && !response.success) {
                    deferred.reject(response);
                    return;
                }

                window.setTimeout(function() {
                    pollUntilDone(postId, attempt + 1).done(deferred.resolve).fail(deferred.reject);
                }, pollInterval);
            }).fail(function() {
                window.setTimeout(function() {
                    pollUntilDone(postId, attempt + 1).done(deferred.resolve).fail(deferred.reject);
                }, pollInterval);
            });

            return deferred.promise();
        }

        function queueAndPoll(postId) {
            var deferred = $.Deferred();

            $.ajax({
                url: llmoAdmin.ajaxurl,
                type: 'POST',
                timeout: 120000,
                data: {
                    action: 'llmo_optimize_post',
                    nonce: llmoAdmin.nonce,
                    post_id: postId
                }
            }).done(function(response) {
                if (!response || !response.success) {
                    deferred.reject(response);
                    return;
                }

                pollUntilDone(postId).done(deferred.resolve).fail(deferred.reject);
            }).fail(function() {
                deferred.reject();
            });

            return deferred.promise();
        }

        function optimizationMessage(response) {
            var data = (response && response.data) ? response.data : {};
            if (data.code === 'insufficient_tokens' && data.required !== undefined && data.balance !== undefined && strings.insufficient) {
                return strings.insufficient.replace('%1$s', data.required).replace('%2$s', data.balance);
            }
            if (data.code === 'invalid_page' && strings.noText) {
                return strings.noText;
            }
            return data.message || strings.error || 'Error';
        }

        function resetReoptimize() {
            if (confirmTimer) {
                window.clearTimeout(confirmTimer);
                confirmTimer = null;
            }
            if ($confirmButton) {
                $confirmButton.text($confirmButton.data('originalText') || strings.reoptimize || 'Re-optimize');
                $confirmButton.removeData('confirming');
                $confirmButton.siblings('.llmo-reoptimize-cancel').remove();
                $confirmButton = null;
            }
        }

        function armReoptimize($button) {
            if ($button.data('confirming') === 1) {
                resetReoptimize();
                return true;
            }

            resetReoptimize();
            $button.data('originalText', $button.text());
            $button.data('confirming', 1);
            $button.text(strings.confirmReoptimize || 'Really optimize again? This uses tokens.');
            var $cancel = $('<button type="button" class="button llmo-reoptimize-cancel"></button>');
            $cancel.text(strings.cancelReoptimize || 'Cancel');
            $button.after($cancel);
            $confirmButton = $button;
            confirmTimer = window.setTimeout(resetReoptimize, 5000);
            $cancel.on('click', function(event) {
                event.preventDefault();
                resetReoptimize();
            });
            return false;
        }

        function showMetaResult($button, message) {
            var $box = $button.closest('.llmo-meta-box');
            var $live = $box.find('.llmo-meta-live');
            if (!$live.length) {
                $live = $('<div class="llmo-meta-live"></div>');
                $box.prepend($live);
            }
            $live.text(message);
            $live.removeAttr('hidden').show();
        }

        function ensureMetaScore($box, score) {
            if (score === undefined || score === null || score === '') {
                return;
            }
            var $score = $box.find('.llmo-meta-score');
            if (!$score.length) {
                $score = $('<p class="llmo-meta-score"></p>');
                var $live = $box.find('.llmo-meta-live');
                if ($live.length) {
                    $live.after($score);
                } else {
                    $box.prepend($score);
                }
            }
            $score.empty();
            $score.append($('<strong></strong>').text(strings.scoreLabel || ''));
            if (strings.scoreTooltip) {
                $score.append($('<span class="dashicons dashicons-editor-help llmo-score-help"></span>').attr('title', strings.scoreTooltip));
            }
            $score.append($('<br>'));
            $score.append($('<span class="llmo-score"></span>').text(String(score) + '/100'));
        }

        $('#llmo-test-connection').on('click', function() {
            var $button = $(this);
            var $status = $('#llmo-connection-status');
            var apiKey = $('#llmo_blog_optimizer_api_key').val();

            if (!apiKey) {
                $status.removeClass('success').addClass('error').text(strings.error || 'Error');
                return;
            }

            $button.prop('disabled', true).text(strings.testing || 'Testing...');
            $status.text('');

            $.ajax({
                url: llmoAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'llmo_test_connection',
                    nonce: llmoAdmin.nonce,
                    api_key: apiKey
                },
                success: function(response) {
                    if (response.success) {
                        $status.removeClass('error').addClass('success').text('✓ ' + response.data.message);
                    } else {
                        $status.removeClass('success').addClass('error').text('✗ ' + response.data.message);
                    }
                },
                error: function() {
                    $status.removeClass('success').addClass('error').text('✗ ' + (strings.error || 'Error'));
                },
                complete: function() {
                    $button.prop('disabled', false).text(strings.testConnection || 'Test Connection');
                }
            });
        });

        $(document).on('click', '.llmo-optimize, .llmo-reoptimize', function() {
            var $button = $(this);
            var postId = $button.data('post-id');
            var isReoptimize = $button.hasClass('llmo-reoptimize');
            var $box = $button.closest('.llmo-meta-box');

            if (isReoptimize && !armReoptimize($button)) {
                return;
            }

            $button.prop('disabled', true).text(strings.optimizing || 'Optimizing...');
            $box.find('.llmo-optimize-hint').show();
            $box.find('.llmo-meta-live').attr('hidden', 'hidden').hide();

            queueAndPoll(postId).done(function(data) {
                if (data && data.status === 'timeout') {
                    showMetaResult($button, (strings.rowStillRunning || 'Still running on the server') + ' ' + (strings.reloadLater || ''));
                    $button.prop('disabled', false).text(strings.reoptimize || 'Re-optimize');
                    $box.find('.llmo-optimize-hint').hide();
                    return;
                }
                showMetaResult($button, strings.optimizedState || strings.optimized || 'Optimized');
                $box.find('.llmo-meta-state').text(strings.optimizedState || 'Optimized');
                $box.find('.llmo-meta-pending').hide();
                ensureMetaScore($box, data && data.score);
                $button.removeClass('llmo-optimize').addClass('button-secondary llmo-reoptimize').removeClass('button-primary');
                $button.prop('disabled', false).text(strings.reoptimize || 'Re-optimize');
                $box.find('.llmo-optimize-hint').hide();
            }).fail(function(response) {
                showMetaResult($button, optimizationMessage(response));
                $button.prop('disabled', false).text(isReoptimize ? (strings.reoptimize || 'Re-optimize') : (strings.optimizeNow || 'Optimize Now'));
                $box.find('.llmo-optimize-hint').hide();
            });
        });

        if (!$('#llmo-progress').length) {
            return;
        }

        $('#llmo-bulk-notice').on('click', '.notice-dismiss', function(event) {
            event.preventDefault();
            $('#llmo-bulk-notice').attr('hidden', 'hidden').hide();
        });

        $('#llmo-select-all').on('change', function() {
            $('.llmo-post-checkbox').prop('checked', $(this).prop('checked'));
        });

        function showBulkNotice(message, level, linkUrl) {
            var $notice = $('#llmo-bulk-notice');
            $notice.removeClass('notice-success notice-warning notice-error');
            if (level === 'success') {
                $notice.addClass('notice-success');
            } else if (level === 'warning') {
                $notice.addClass('notice-warning');
            } else {
                $notice.addClass('notice-error');
            }
            var $p = $notice.find('p');
            $p.empty();
            $p.append(document.createTextNode(message));
            if (linkUrl) {
                $p.append(document.createTextNode(' '));
                $p.append($('<a></a>').attr('href', linkUrl).text(strings.viewPost || 'View post'));
            }
            $notice.removeAttr('hidden').show();
        }

        function rowById(postId) {
            return $('.llmo-bulk-row').filter(function() {
                return String($(this).attr('data-post-id')) === String(postId);
            });
        }

        function formatDuration(seconds) {
            var mins = Math.floor(seconds / 60);
            var secs = seconds % 60;
            return mins + ':' + (secs < 10 ? '0' : '') + secs;
        }

        function stopElapsed() {
            if (elapsedTimer) {
                window.clearInterval(elapsedTimer);
                elapsedTimer = null;
            }
        }

        function startElapsed(n, total, title) {
            var started = Date.now();
            function tick() {
                var seconds = Math.floor((Date.now() - started) / 1000);
                var template = strings.progressCurrent || 'Post %1$s of %2$s is being optimized: %3$s (%4$s)';
                var text = template
                    .replace('%1$s', String(n))
                    .replace('%2$s', String(total))
                    .replace('%4$s', formatDuration(seconds))
                    .replace('%3$s', title);
                $('#llmo-progress-current-text').text(text);
            }
            stopElapsed();
            $('#llmo-progress-current').removeAttr('hidden').show();
            tick();
            elapsedTimer = window.setInterval(tick, 1000);
        }

        function setRowState(postId, state, detail) {
            var $cell = rowById(postId).find('.llmo-row-state');
            $cell.empty();
            if (state === 'running') {
                $cell.append('<span class="spinner is-active llmo-row-spinner" aria-hidden="true"></span>');
                $cell.append(document.createTextNode(strings.rowRunning || 'Running'));
                return;
            }
            if (state === 'waiting') {
                $cell.text(strings.rowWaiting || 'Waiting');
                return;
            }
            if (state === 'done') {
                $cell.text((strings.optimizedState || 'Optimized') + (detail ? ' (' + detail + ')' : ''));
                return;
            }
            if (state === 'skipped') {
                $cell.text((strings.rowSkipped || 'Skipped') + (detail ? ': ' + detail : ''));
                return;
            }
            if (state === 'server') {
                $cell.text(strings.rowStillRunning || 'Still running on the server');
                return;
            }
            $cell.text(detail || '');
        }

        function applyScore(postId, score) {
            if (score === undefined || score === null || score === '') {
                return;
            }
            var $cell = rowById(postId).find('.llmo-row-score');
            $cell.empty();
            $cell.append($('<span class="llmo-bulk-score"></span>').text(String(score) + '/100'));
        }

        function statCount(selector) {
            return parseInt($(selector).attr('data-count'), 10) || 0;
        }

        function shareText(count, total) {
            var template = strings.statShare || '%1$s posts (%2$s%%)';
            var pct = total > 0 ? Math.round((count / total) * 100) : 0;
            return template.replace('%1$s', String(count)).replace('%2$s', String(pct));
        }

        function markRowOptimized(postId) {
            var $row = rowById(postId);
            var wasOptimized = String($row.attr('data-optimized')) === '1';
            $row.attr('data-optimized', '1');
            $row.find('.llmo-post-checkbox, .llmo-optimize-single').attr('data-optimized', '1');
            $row.find('.llmo-optimize-single').text(strings.reoptimize || 'Re-optimize');
            var $mark = $row.find('.llmo-bulk-mark');
            $mark.removeClass('llmo-bulk-mark--pending').addClass('llmo-bulk-mark--ok').text('✓');
            if (wasOptimized) {
                return;
            }
            var total = statCount('#llmo-stat-total');
            var optimized = statCount('#llmo-stat-optimized') + 1;
            var pending = Math.max(0, statCount('#llmo-stat-pending') - 1);
            $('#llmo-stat-optimized').attr('data-count', optimized).text(shareText(optimized, total));
            $('#llmo-stat-pending').attr('data-count', pending).text(shareText(pending, total));
        }

        function restoreUntouched(postIds) {
            postIds.forEach(function(postId) {
                var $row = rowById(postId);
                var optimized = String($row.attr('data-optimized')) === '1';
                $row.find('.llmo-row-state').text(optimized ? (strings.optimizedState || 'Optimized') : (strings.pending || 'Pending'));
            });
        }

        function updateProgress(completed, total, active) {
            var donePct = total > 0 ? (completed / total) * 100 : 0;
            var activePct = active && total > 0 ? (0.5 / total) * 100 : 0;
            $('#llmo-progress-bar').css('width', donePct + '%');
            $('#llmo-progress-bar-active').css('width', activePct + '%');
            $('#llmo-progress-text').text(completed + ' / ' + total);
        }

        var bulkRunning = false;

        function setBulkLocked(locked) {
            bulkRunning = locked;
            $('#llmo-optimize-all-pending, #llmo-optimize-selected, .llmo-optimize-single').prop('disabled', locked);
            if (locked) {
                $('#llmo-bulk-running').removeAttr('hidden').show();
            } else {
                $('#llmo-bulk-running').attr('hidden', 'hidden').hide();
            }
        }

        function finishRun(state) {
            stopElapsed();
            setBulkLocked(false);
            $('#llmo-progress-current').attr('hidden', 'hidden').hide();
            updateProgress(state.completed, state.total, false);
            if (state.keepNotice) {
                return;
            }
            if (state.optimized > 0) {
                var lead = state.optimized === 1
                    ? (strings.bulkDoneOne || '1 post optimized.')
                    : (strings.bulkDoneMany || '%s posts optimized.').replace('%s', String(state.optimized));
                var text = lead;
                if (llmoAdmin.showFaq === 'yes' && strings.bulkFaq) {
                    text += ' ' + strings.bulkFaq;
                }
                if (state.skipped > 0 && strings.skippedCount) {
                    text += ' ' + strings.skippedCount.replace('%s', String(state.skipped));
                }
                if (state.timedOut > 0 && strings.reloadLater) {
                    text += ' ' + strings.reloadLater;
                }
                var level = (state.skipped > 0 || state.timedOut > 0) ? 'warning' : 'success';
                showBulkNotice(text, level, state.firstUrl);
                return;
            }
            if (state.timedOut > 0) {
                showBulkNotice(strings.reloadLater || 'Reload this page later.', 'warning');
                return;
            }
            if (state.skipReason) {
                showBulkNotice(state.skipReason, 'warning');
            }
        }

        function optimizePosts(postIds) {
            if (bulkRunning || !postIds.length) {
                return;
            }

            resetReoptimize();

            var total = postIds.length;
            var index = 0;
            var state = {
                total: total,
                completed: 0,
                optimized: 0,
                skipped: 0,
                timedOut: 0,
                firstUrl: '',
                skipReason: '',
                keepNotice: false
            };

            setBulkLocked(true);
            $('#llmo-bulk-notice').attr('hidden', 'hidden').hide();
            $('#llmo-progress').show();
            updateProgress(0, total, true);
            postIds.forEach(function(postId) {
                setRowState(postId, 'waiting');
            });

            function step() {
                if (index >= total) {
                    state.completed = total;
                    finishRun(state);
                    return;
                }

                var postId = postIds[index];
                var $row = rowById(postId);
                var title = $row.attr('data-title') || ('#' + postId);
                var started = Date.now();
                setRowState(postId, 'running');
                startElapsed(index + 1, total, title);
                updateProgress(index, total, true);

                queueAndPoll(postId).done(function(data) {
                    var seconds = Math.max(0, Math.round((Date.now() - started) / 1000));
                    if (data && data.status === 'timeout') {
                        state.timedOut++;
                        setRowState(postId, 'server');
                        showBulkNotice(strings.reloadLater || 'Reload this page later.', 'warning');
                    } else {
                        state.optimized++;
                        if (!state.firstUrl) {
                            state.firstUrl = $row.attr('data-view-url') || '';
                        }
                        setRowState(postId, 'done', formatDuration(seconds));
                        applyScore(postId, data && data.score);
                        markRowOptimized(postId);
                    }
                    index++;
                    state.completed = index;
                    step();
                }).fail(function(response) {
                    var data = (response && response.data) ? response.data : {};
                    var message = optimizationMessage(response);
                    state.skipped++;
                    if (!state.skipReason) {
                        state.skipReason = message;
                    }
                    setRowState(postId, 'skipped', message);
                    index++;
                    state.completed = index;
                    if (data.code === 'insufficient_tokens') {
                        restoreUntouched(postIds.slice(index));
                        showBulkNotice(message, 'error');
                        state.keepNotice = true;
                        finishRun(state);
                        return;
                    }
                    step();
                });
            }

            step();
        }

        $('#llmo-optimize-all-pending').on('click', function() {
            var pendingPosts = [];
            $('.llmo-post-checkbox').each(function() {
                if ($(this).attr('data-optimized') !== '1') {
                    pendingPosts.push($(this).val());
                }
            });

            if (pendingPosts.length === 0) {
                showBulkNotice(strings.noPending || 'No pending posts to optimize', 'warning');
                return;
            }

            optimizePosts(pendingPosts);
        });

        $('#llmo-optimize-selected').on('click', function() {
            var selectedPosts = $('.llmo-post-checkbox:checked').map(function() {
                return $(this).val();
            }).get();

            if (selectedPosts.length === 0) {
                showBulkNotice(strings.selectPosts || 'Please select posts to optimize', 'warning');
                return;
            }

            optimizePosts(selectedPosts);
        });

        $(document).on('click', '.llmo-optimize-single', function() {
            if (bulkRunning) {
                return;
            }
            var $button = $(this);
            var optimized = String($button.attr('data-optimized')) === '1';
            if (optimized && !armReoptimize($button)) {
                return;
            }
            optimizePosts([$button.data('post-id')]);
        });
    });

})(jQuery);
