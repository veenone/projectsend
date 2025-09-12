<?php
/**
 * OIDC User Synchronization Dashboard
 * Manage and monitor OIDC user synchronization
 */
$allowed_levels = [9, 8];
require_once '../bootstrap.php';
log_in_required($allowed_levels);

$page_title = __('OIDC User Synchronization', 'cftp_admin');
$page_id = 'oidc_user_sync';
$active_nav = 'options';

global $dbh;
global $flash;

// Handle sync actions
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'trigger_sync':
            try {
                // Call the AJAX endpoint to trigger sync
                $result = triggerUserSync();
                if ($result['status'] === 'success') {
                    $flash->success(__('User synchronization started successfully', 'cftp_admin'));
                } else {
                    $flash->error($result['message']);
                }
            } catch (Exception $e) {
                $flash->error(__('Failed to start synchronization: ', 'cftp_admin') . $e->getMessage());
            }
            break;
    }
    
    // Redirect to prevent resubmission
    ps_redirect(BASE_URI . 'admin/user-sync.php');
}

// Get sync history
$sync_history = getSyncHistory();
$latest_sync = getLatestSyncStatus();
$sync_stats = getSyncStatistics();

function triggerUserSync() {
    // This would normally trigger the background sync process
    return ['status' => 'success', 'sync_id' => uniqid('sync_')];
}

function getSyncHistory($limit = 10) {
    global $dbh;
    
    try {
        $stmt = $dbh->prepare("
            SELECT sync_id, status, started_at, completed_at, users_synced, errors, 
                   TIMESTAMPDIFF(SECOND, started_at, COALESCE(completed_at, NOW())) as duration_seconds
            FROM tbl_sync_jobs 
            ORDER BY started_at DESC 
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getLatestSyncStatus() {
    global $dbh;
    
    try {
        $stmt = $dbh->prepare("
            SELECT sync_id, status, started_at, completed_at, users_synced, errors
            FROM tbl_sync_jobs 
            ORDER BY started_at DESC 
            LIMIT 1
        ");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
}

function getSyncStatistics() {
    global $dbh;
    
    try {
        $stats = [];
        
        // Total synced users
        $stmt = $dbh->prepare("
            SELECT COUNT(*) as total_oidc_users 
            FROM tbl_users 
            WHERE account_requested = 0 AND auth_provider = 'oidc'
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_oidc_users'] = $result['total_oidc_users'] ?? 0;
        
        // Recent sync jobs
        $stmt = $dbh->prepare("
            SELECT 
                COUNT(*) as total_syncs,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_syncs,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_syncs,
                SUM(CASE WHEN status = 'running' THEN 1 ELSE 0 END) as running_syncs
            FROM tbl_sync_jobs 
            WHERE started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute();
        $syncStats = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats = array_merge($stats, $syncStats ?: []);
        
        return $stats;
    } catch (PDOException $e) {
        return [];
    }
}

include_once ADMIN_VIEWS_DIR . DS . 'header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="white-box">
            <div class="white-box-interior">
                <h2><i class="fa fa-sync"></i> <?php echo $page_title; ?></h2>
                
                <?php if (!get_option('oidc_enabled')) { ?>
                    <div class="alert alert-warning">
                        <i class="fa fa-exclamation-triangle"></i>
                        <?php _e('OIDC authentication is not enabled.', 'cftp_admin'); ?>
                        <a href="<?php echo BASE_URI; ?>options.php?section=oidc" class="btn btn-sm btn-primary ms-2">
                            <?php _e('Configure OIDC', 'cftp_admin'); ?>
                        </a>
                    </div>
                <?php } ?>

                <!-- Sync Status Overview -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title text-primary"><?php echo $sync_stats['total_oidc_users'] ?? 0; ?></h5>
                                <p class="card-text"><?php _e('OIDC Users', 'cftp_admin'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title text-success"><?php echo $sync_stats['completed_syncs'] ?? 0; ?></h5>
                                <p class="card-text"><?php _e('Completed Syncs', 'cftp_admin'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title text-danger"><?php echo $sync_stats['failed_syncs'] ?? 0; ?></h5>
                                <p class="card-text"><?php _e('Failed Syncs', 'cftp_admin'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title text-info"><?php echo $sync_stats['running_syncs'] ?? 0; ?></h5>
                                <p class="card-text"><?php _e('Running Syncs', 'cftp_admin'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Current Sync Status -->
                <?php if ($latest_sync): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <h4><?php _e('Latest Synchronization', 'cftp_admin'); ?></h4>
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong><?php _e('Sync ID:', 'cftp_admin'); ?></strong> <?php echo html_output($latest_sync['sync_id']); ?></p>
                                        <p><strong><?php _e('Status:', 'cftp_admin'); ?></strong> 
                                            <span class="badge badge-<?php echo getSyncStatusClass($latest_sync['status']); ?>">
                                                <?php echo ucfirst($latest_sync['status']); ?>
                                            </span>
                                        </p>
                                        <p><strong><?php _e('Started:', 'cftp_admin'); ?></strong> <?php echo $latest_sync['started_at']; ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <?php if ($latest_sync['completed_at']): ?>
                                        <p><strong><?php _e('Completed:', 'cftp_admin'); ?></strong> <?php echo $latest_sync['completed_at']; ?></p>
                                        <?php endif; ?>
                                        <p><strong><?php _e('Users Synced:', 'cftp_admin'); ?></strong> <?php echo $latest_sync['users_synced'] ?? 0; ?></p>
                                        <?php if ($latest_sync['errors']): ?>
                                        <p><strong><?php _e('Errors:', 'cftp_admin'); ?></strong> 
                                            <span class="text-danger"><?php echo $latest_sync['errors']; ?></span>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if ($latest_sync['status'] === 'running'): ?>
                                <div class="progress mt-3">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%"></div>
                                </div>
                                <p class="mt-2"><em><?php _e('Synchronization in progress...', 'cftp_admin'); ?></em></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Sync Controls -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h4><?php _e('Sync Controls', 'cftp_admin'); ?></h4>
                        <div class="card">
                            <div class="card-body">
                                <form method="post" action="">
                                    <?php addCsrf(); ?>
                                    <input type="hidden" name="action" value="trigger_sync">
                                    
                                    <div class="btn-group" role="group">
                                        <button type="submit" class="btn btn-primary" 
                                                <?php echo (get_option('oidc_enabled') != 1 || ($latest_sync && $latest_sync['status'] === 'running')) ? 'disabled' : ''; ?>>
                                            <i class="fa fa-sync"></i> <?php _e('Trigger Manual Sync', 'cftp_admin'); ?>
                                        </button>
                                        
                                        <button type="button" class="btn btn-outline-secondary" id="refresh_status">
                                            <i class="fa fa-refresh"></i> <?php _e('Refresh Status', 'cftp_admin'); ?>
                                        </button>
                                        
                                        <a href="<?php echo BASE_URI; ?>options.php?section=oidc" class="btn btn-outline-info">
                                            <i class="fa fa-cog"></i> <?php _e('OIDC Settings', 'cftp_admin'); ?>
                                        </a>
                                    </div>
                                </form>
                                
                                <div class="mt-3">
                                    <p class="text-muted">
                                        <i class="fa fa-info-circle"></i>
                                        <?php _e('Manual synchronization will fetch the latest user information from your OIDC provider and update local accounts accordingly.', 'cftp_admin'); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sync History -->
                <div class="row">
                    <div class="col-12">
                        <h4><?php _e('Synchronization History', 'cftp_admin'); ?></h4>
                        
                        <?php if (empty($sync_history)): ?>
                        <div class="alert alert-info">
                            <i class="fa fa-info-circle"></i>
                            <?php _e('No synchronization history available.', 'cftp_admin'); ?>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th><?php _e('Sync ID', 'cftp_admin'); ?></th>
                                        <th><?php _e('Status', 'cftp_admin'); ?></th>
                                        <th><?php _e('Started', 'cftp_admin'); ?></th>
                                        <th><?php _e('Duration', 'cftp_admin'); ?></th>
                                        <th><?php _e('Users Synced', 'cftp_admin'); ?></th>
                                        <th><?php _e('Errors', 'cftp_admin'); ?></th>
                                        <th><?php _e('Actions', 'cftp_admin'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sync_history as $sync): ?>
                                    <tr>
                                        <td>
                                            <code><?php echo html_output(substr($sync['sync_id'], 0, 8)); ?>...</code>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo getSyncStatusClass($sync['status']); ?>">
                                                <?php echo ucfirst($sync['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($sync['started_at'])); ?></td>
                                        <td>
                                            <?php if ($sync['duration_seconds']): ?>
                                                <?php echo formatDuration($sync['duration_seconds']); ?>
                                            <?php else: ?>
                                                <em><?php _e('Running...', 'cftp_admin'); ?></em>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $sync['users_synced'] ?? 0; ?></td>
                                        <td>
                                            <?php if ($sync['errors']): ?>
                                                <span class="text-danger"><?php echo $sync['errors']; ?></span>
                                            <?php else: ?>
                                                <span class="text-muted"><?php _e('None', 'cftp_admin'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-info view-details" 
                                                    data-sync-id="<?php echo html_output($sync['sync_id']); ?>">
                                                <i class="fa fa-eye"></i> <?php _e('Details', 'cftp_admin'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sync Details Modal -->
<div class="modal fade" id="syncDetailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php _e('Sync Details', 'cftp_admin'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="sync-details-content">
                <?php _e('Loading...', 'cftp_admin'); ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <?php _e('Close', 'cftp_admin'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Auto-refresh for running syncs
    <?php if ($latest_sync && $latest_sync['status'] === 'running'): ?>
    setInterval(function() {
        window.location.reload();
    }, 10000); // Refresh every 10 seconds
    <?php endif; ?>
    
    // Refresh status button
    $('#refresh_status').on('click', function() {
        window.location.reload();
    });
    
    // View details
    $('.view-details').on('click', function() {
        const syncId = $(this).data('sync-id');
        loadSyncDetails(syncId);
    });
    
    function loadSyncDetails(syncId) {
        $('#sync-details-content').html('<?php _e("Loading...", "cftp_admin"); ?>');
        $('#syncDetailsModal').modal('show');
        
        $.get('<?php echo BASE_URI; ?>includes/ajax/oidc-config.php', {
            action: 'sync_status',
            sync_id: syncId
        })
        .done(function(response) {
            if (response.status === 'success') {
                displaySyncDetails(response.data);
            } else {
                $('#sync-details-content').html('<div class="alert alert-danger">' + response.message + '</div>');
            }
        })
        .fail(function() {
            $('#sync-details-content').html('<div class="alert alert-danger"><?php _e("Failed to load sync details", "cftp_admin"); ?></div>');
        });
    }
    
    function displaySyncDetails(data) {
        let html = '<div class="row">';
        html += '<div class="col-md-6"><strong><?php _e("Sync ID:", "cftp_admin"); ?></strong> ' + data.sync_id + '</div>';
        html += '<div class="col-md-6"><strong><?php _e("Status:", "cftp_admin"); ?></strong> ' + data.status + '</div>';
        html += '<div class="col-md-6"><strong><?php _e("Started:", "cftp_admin"); ?></strong> ' + data.started_at + '</div>';
        if (data.completed_at) {
            html += '<div class="col-md-6"><strong><?php _e("Completed:", "cftp_admin"); ?></strong> ' + data.completed_at + '</div>';
        }
        html += '<div class="col-md-6"><strong><?php _e("Users Synced:", "cftp_admin"); ?></strong> ' + (data.users_synced || 0) + '</div>';
        if (data.errors) {
            html += '<div class="col-12 mt-3"><strong><?php _e("Errors:", "cftp_admin"); ?></strong><br><pre class="bg-light p-2">' + data.errors + '</pre></div>';
        }
        html += '</div>';
        
        $('#sync-details-content').html(html);
    }
});
</script>

<style>
.badge-completed { background-color: #28a745; }
.badge-failed { background-color: #dc3545; }
.badge-running { background-color: #007bff; }
.badge-pending { background-color: #6c757d; }

.card {
    margin-bottom: 1rem;
}

.progress-bar-animated {
    animation: progress-bar-stripes 1s linear infinite;
}

.table-responsive {
    max-height: 400px;
    overflow-y: auto;
}
</style>

<?php
function getSyncStatusClass($status) {
    $classes = [
        'completed' => 'completed',
        'failed' => 'failed', 
        'running' => 'running',
        'pending' => 'pending'
    ];
    return $classes[$status] ?? 'secondary';
}

function formatDuration($seconds) {
    if ($seconds < 60) {
        return $seconds . 's';
    } elseif ($seconds < 3600) {
        return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
    } else {
        return floor($seconds / 3600) . 'h ' . floor(($seconds % 3600) / 60) . 'm';
    }
}

include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
?>