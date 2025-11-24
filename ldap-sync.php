<?php
/**
 * LDAP Bulk User Synchronization
 */
require_once 'bootstrap.php';
check_access_enhanced(['manage_users']);

$active_nav = 'users';
$page_title = __('LDAP User Synchronization', 'cftp_admin');

include_once ADMIN_VIEWS_DIR . DS . 'header.php';

// Check if LDAP is enabled
$ldap_enabled = get_option('ldap_signin_enabled') === 'true';
?>

<input type="hidden" name="csrf_token" id="csrf_token" value="<?php echo getCsrfToken(); ?>" />

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-sync"></i> <?php echo $page_title; ?>
                </h3>
            </div>
            <div class="card-body">
                <?php if (!$ldap_enabled): ?>
                    <div class="alert alert-warning">
                        <i class="fa fa-exclamation-triangle"></i>
                        <?php _e('LDAP authentication is not enabled. Please enable and configure LDAP settings first.', 'cftp_admin'); ?>
                        <a href="options.php?section=ldap" class="btn btn-sm btn-primary ms-3">
                            <?php _e('Go to LDAP Settings', 'cftp_admin'); ?>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="mb-4">
                        <h4><?php _e('Bulk User Synchronization', 'cftp_admin'); ?></h4>
                        <p><?php _e('This tool allows you to synchronize multiple users from your LDAP server to the local database. Users will be created with the default role specified in LDAP settings.', 'cftp_admin'); ?></p>

                        <div class="alert alert-info">
                            <h5><?php _e('Current Settings:', 'cftp_admin'); ?></h5>
                            <ul class="mb-0">
                                <li><strong><?php _e('LDAP Server:', 'cftp_admin'); ?></strong> <?php echo html_output(get_option('ldap_hosts')); ?></li>
                                <li><strong><?php _e('Search Base:', 'cftp_admin'); ?></strong> <?php echo html_output(get_option('ldap_search_base')); ?></li>
                                <li><strong><?php _e('Sync Filter:', 'cftp_admin'); ?></strong> <?php echo html_output(get_option('ldap_sync_filter', null, '(objectClass=person)')); ?></li>
                                <li><strong><?php _e('Default Role:', 'cftp_admin'); ?></strong>
                                    <?php
                                    $default_role_id = get_option('ldap_default_role');
                                    if ($default_role_id) {
                                        $role = \ProjectSend\Classes\Roles::getRoleById($default_role_id);
                                        if ($role && isset($role['name'])) {
                                            echo html_output($role['name']);
                                        } else {
                                            _e('Client', 'cftp_admin');
                                        }
                                    } else {
                                        _e('Client', 'cftp_admin');
                                    }
                                    ?>
                                </li>
                            </ul>
                            <a href="options.php?section=ldap" class="btn btn-sm btn-secondary mt-2">
                                <i class="fa fa-cog"></i> <?php _e('Change Settings', 'cftp_admin'); ?>
                            </a>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <button type="button" id="dry_run_sync" class="btn btn-warning btn-lg w-100">
                                <i class="fa fa-eye"></i> <?php _e('Preview Sync (Dry Run)', 'cftp_admin'); ?>
                            </button>
                            <small class="form-text text-muted mt-2">
                                <?php _e('See what would be synced without making any changes', 'cftp_admin'); ?>
                            </small>
                        </div>
                        <div class="col-md-6">
                            <button type="button" id="run_sync" class="btn btn-primary btn-lg w-100">
                                <i class="fa fa-sync"></i> <?php _e('Run Synchronization', 'cftp_admin'); ?>
                            </button>
                            <small class="form-text text-muted mt-2">
                                <?php _e('Create and update users from LDAP server', 'cftp_admin'); ?>
                            </small>
                        </div>
                    </div>

                    <div id="sync_progress" class="mb-4" style="display: none;">
                        <div class="alert alert-info">
                            <i class="fa fa-cog fa-spin"></i> <?php _e('Synchronization in progress...', 'cftp_admin'); ?>
                        </div>
                    </div>

                    <div id="sync_results" style="display: none;">
                        <h4><?php _e('Synchronization Results', 'cftp_admin'); ?></h4>
                        <div id="sync_results_content"></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($ldap_enabled): ?>
<script>
$(document).ready(function() {
    function runSync(dryRun) {
        var progressDiv = $('#sync_progress');
        var resultsDiv = $('#sync_results');
        var resultsContent = $('#sync_results_content');
        var dryRunBtn = $('#dry_run_sync');
        var runBtn = $('#run_sync');

        // Reset and show progress
        resultsDiv.hide();
        progressDiv.show();
        dryRunBtn.prop('disabled', true);
        runBtn.prop('disabled', true);

        $.ajax({
            url: 'process.php?do=ldap_sync_users',
            type: 'POST',
            data: {
                csrf_token: $('input[name="csrf_token"]').val(),
                dry_run: dryRun ? '1' : '0'
            },
            dataType: 'json',
            success: function(response) {
                progressDiv.hide();

                if (response.status === 'success') {
                    var stats = response.stats;
                    var isDryRun = response.dry_run;

                    var html = '<div class="alert alert-success">';
                    html += '<h5><i class="fa fa-check-circle"></i> ';
                    if (isDryRun) {
                        html += '<?php _e('Preview completed successfully', 'cftp_admin'); ?>';
                    } else {
                        html += '<?php _e('Synchronization completed successfully', 'cftp_admin'); ?>';
                    }
                    html += '</h5>';
                    html += '<ul class="mb-0">';
                    html += '<li><strong><?php _e('Total users found:', 'cftp_admin'); ?></strong> ' + stats.total_found + '</li>';
                    html += '<li><strong><?php _e('Users to be created:', 'cftp_admin'); ?></strong> ' + stats.created + '</li>';
                    html += '<li><strong><?php _e('Users to be updated:', 'cftp_admin'); ?></strong> ' + stats.updated + '</li>';
                    html += '<li><strong><?php _e('Users skipped:', 'cftp_admin'); ?></strong> ' + stats.skipped + '</li>';
                    if (stats.errors > 0) {
                        html += '<li class="text-danger"><strong><?php _e('Errors:', 'cftp_admin'); ?></strong> ' + stats.errors + '</li>';
                    }
                    html += '</ul>';
                    html += '</div>';

                    // Show user list if available
                    if (stats.users && stats.users.length > 0) {
                        html += '<div class="table-responsive mt-3">';
                        html += '<table class="table table-sm table-bordered">';
                        html += '<thead><tr>';
                        html += '<th><?php _e('Email', 'cftp_admin'); ?></th>';
                        html += '<th><?php _e('Action', 'cftp_admin'); ?></th>';
                        html += '<th><?php _e('Status', 'cftp_admin'); ?></th>';
                        html += '</tr></thead><tbody>';

                        for (var i = 0; i < Math.min(stats.users.length, 50); i++) {
                            var user = stats.users[i];
                            var actionText = '';
                            var statusClass = '';

                            if (user.action === 'create' || user.action === 'created') {
                                actionText = '<?php _e('Created', 'cftp_admin'); ?>';
                                statusClass = 'text-success';
                            } else if (user.action === 'update' || user.action === 'updated') {
                                actionText = '<?php _e('Updated', 'cftp_admin'); ?>';
                                statusClass = 'text-info';
                            }

                            html += '<tr>';
                            html += '<td>' + escapeHtml(user.email) + '</td>';
                            html += '<td class="' + statusClass + '">' + actionText + '</td>';
                            html += '<td>';
                            if (isDryRun) {
                                html += '<span class="badge bg-warning"><?php _e('Preview', 'cftp_admin'); ?></span>';
                            } else {
                                html += '<span class="badge bg-success"><?php _e('Completed', 'cftp_admin'); ?></span>';
                            }
                            html += '</td>';
                            html += '</tr>';
                        }

                        if (stats.users.length > 50) {
                            html += '<tr><td colspan="3" class="text-center text-muted">';
                            html += '<?php _e('Showing first 50 users...', 'cftp_admin'); ?>';
                            html += '</td></tr>';
                        }

                        html += '</tbody></table></div>';
                    }

                    // Show errors if any
                    if (response.errors && response.errors.length > 0) {
                        html += '<div class="alert alert-warning mt-3">';
                        html += '<h5><?php _e('Errors encountered:', 'cftp_admin'); ?></h5>';
                        html += '<ul class="mb-0">';
                        for (var i = 0; i < Math.min(response.errors.length, 10); i++) {
                            html += '<li>' + escapeHtml(response.errors[i]) + '</li>';
                        }
                        if (response.errors.length > 10) {
                            html += '<li class="text-muted"><?php _e('... and more', 'cftp_admin'); ?></li>';
                        }
                        html += '</ul></div>';
                    }

                    resultsContent.html(html);
                } else {
                    resultsContent.html('<div class="alert alert-danger"><i class="fa fa-times"></i> ' + escapeHtml(response.message) + '</div>');
                }

                resultsDiv.show();
                dryRunBtn.prop('disabled', false);
                runBtn.prop('disabled', false);
            },
            error: function(xhr, status, error) {
                progressDiv.hide();
                resultsContent.html('<div class="alert alert-danger"><i class="fa fa-times"></i> <?php _e('An error occurred during synchronization', 'cftp_admin'); ?>: ' + escapeHtml(error) + '</div>');
                resultsDiv.show();
                dryRunBtn.prop('disabled', false);
                runBtn.prop('disabled', false);
            }
        });
    }

    function escapeHtml(text) {
        if (!text) return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    $('#dry_run_sync').click(function() {
        runSync(true);
    });

    $('#run_sync').click(function() {
        if (confirm('<?php _e('Are you sure you want to synchronize users from LDAP? This will create new users and update existing LDAP users.', 'cftp_admin'); ?>')) {
            runSync(false);
        }
    });
});
</script>
<?php endif; ?>

<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
