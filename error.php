<?php
/**
 * Error page display
 *
 * Displays various error types with proper styling following the system theme.
 * Supports: 401, 403, 404, csrf, database, requirements errors
 */
use ProjectSend\Classes\Session;

define('IS_ERROR_PAGE', true);

require_once 'bootstrap.php';

$error_type = (!empty($_GET['e'])) ? $_GET['e'] : '401';

// Define error configurations
$error_configs = [
    '401' => [
        'code' => 401,
        'title' => __('Access Denied', 'cftp_admin'),
        'subtitle' => __('Authentication Required', 'cftp_admin'),
        'message' => __("Your account type doesn't allow you to view this page. Please contact a system administrator if you need to access this function.", 'cftp_admin'),
        'icon' => 'fa-ban',
        'color' => 'warning'
    ],
    '403' => [
        'code' => 403,
        'title' => __('Forbidden', 'cftp_admin'),
        'subtitle' => __('Access Denied', 'cftp_admin'),
        'message' => __("You don't have permission to access this resource. If you believe this is an error, please contact the administrator.", 'cftp_admin'),
        'icon' => 'fa-lock',
        'color' => 'danger'
    ],
    '404' => [
        'code' => 404,
        'title' => __('Page Not Found', 'cftp_admin'),
        'subtitle' => __('Resource Unavailable', 'cftp_admin'),
        'message' => __("The page you're looking for doesn't exist or has been moved. Please check the URL or navigate back to the homepage.", 'cftp_admin'),
        'icon' => 'fa-search',
        'color' => 'info'
    ],
    '500' => [
        'code' => 500,
        'title' => __('Server Error', 'cftp_admin'),
        'subtitle' => __('Internal Server Error', 'cftp_admin'),
        'message' => __("Something went wrong on our end. Please try again later or contact the administrator if the problem persists.", 'cftp_admin'),
        'icon' => 'fa-exclamation-triangle',
        'color' => 'danger'
    ],
    'csrf' => [
        'code' => 403,
        'title' => __('Security Error', 'cftp_admin'),
        'subtitle' => __('Token Mismatch', 'cftp_admin'),
        'message' => __("The security token could not be validated. This may happen if your session expired. Please refresh the page and try again.", 'cftp_admin'),
        'icon' => 'fa-shield',
        'color' => 'warning'
    ],
    'database' => [
        'code' => 503,
        'title' => __('Database Error', 'cftp_admin'),
        'subtitle' => __('Connection Failed', 'cftp_admin'),
        'message' => (Session::has('database_connection_error')) ? Session::get('database_connection_error') : __("Cannot connect to the database. Please check your configuration or contact the administrator.", 'cftp_admin'),
        'icon' => 'fa-database',
        'color' => 'danger'
    ],
    'requirements' => [
        'code' => 503,
        'title' => __('Requirements Error', 'cftp_admin'),
        'subtitle' => __('System Requirements Not Met', 'cftp_admin'),
        'message' => '',
        'icon' => 'fa-cogs',
        'color' => 'warning'
    ]
];

// Get error configuration
$config = isset($error_configs[$error_type]) ? $error_configs[$error_type] : $error_configs['401'];

// Set HTTP response code
http_response_code($config['code']);

// Handle special cases
if ($error_type === 'database') {
    Session::remove('database_connection_error');
}

if ($error_type === 'requirements') {
    $errors = get_server_requirements_errors();
    $config['message'] = implode('<br>', $errors);
}

// Page variables
$page_title = $config['title'];
$error_code = $config['code'];
$error_subtitle = $config['subtitle'];
$error_message = $config['message'];
$error_icon = $config['icon'];
$error_color = $config['color'];

// Determine if user is logged in for navigation
$is_logged_in = function_exists('user_is_logged_in') && user_is_logged_in();
$home_url = $is_logged_in ? BASE_URI . 'index.php' : BASE_URI;
?>
<!doctype html>
<html lang="<?php echo defined('SITE_LANG') ? SITE_LANG : 'en'; ?>">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?php echo html_output($page_title . ' &raquo; ' . get_option('this_install_title')); ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <?php meta_favicon(); ?>

    <?php
    // Load system theme functions and assets
    if (!defined('IS_INSTALL') && file_exists(ROOT_DIR . '/includes/functions.system-themes.php')) {
        require_once ROOT_DIR . '/includes/functions.system-themes.php';
        load_system_theme_assets();
    }

    render_assets('js', 'head');
    render_assets('css', 'head');
    render_custom_assets('head');
    ?>

    <style>
        /* Error Page Styles */
        .error-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--ist-bg-secondary, #f8f9fa) 0%, var(--ist-bg-tertiary, #e9ecef) 100%);
            padding: 2rem;
        }

        .error-container {
            max-width: 600px;
            width: 100%;
            text-align: center;
        }

        .error-card {
            background: var(--ist-bg-primary, #ffffff);
            border-radius: 16px;
            box-shadow: var(--ist-shadow-lg, 0 8px 24px rgba(0, 0, 0, 0.12));
            padding: 3rem 2rem;
            border: 1px solid var(--ist-border-color, #dee2e6);
        }

        .error-icon-wrapper {
            width: 120px;
            height: 120px;
            margin: 0 auto 2rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .error-icon-wrapper.warning {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeeba 100%);
            border: 3px solid #ffc107;
        }

        .error-icon-wrapper.danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border: 3px solid #dc3545;
        }

        .error-icon-wrapper.info {
            background: linear-gradient(135deg, var(--ist-light-2, #CDDCFF) 0%, var(--ist-light-1, #96AFFF) 100%);
            border: 3px solid var(--ist-primary, #7382E6);
        }

        .error-icon-wrapper i {
            font-size: 3.5rem;
        }

        .error-icon-wrapper.warning i {
            color: #856404;
        }

        .error-icon-wrapper.danger i {
            color: #721c24;
        }

        .error-icon-wrapper.info i {
            color: var(--ist-dark-1, #323C64);
        }

        .error-code {
            font-size: 5rem;
            font-weight: 700;
            color: var(--ist-text-muted, #6c757d);
            line-height: 1;
            margin-bottom: 0.5rem;
            opacity: 0.3;
        }

        .error-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--ist-text-primary, #323C64) !important;
            margin-bottom: 0.5rem;
        }

        .error-subtitle {
            font-size: 1.1rem;
            color: var(--ist-text-secondary, #5564A5) !important;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .error-message {
            color: var(--ist-text-secondary, #666);
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 2rem;
            padding: 1rem;
            background: var(--ist-bg-secondary, #f8f9fa);
            border-radius: 8px;
            border-left: 4px solid var(--ist-primary, #7382E6);
        }

        .error-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .error-actions .btn {
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .error-actions .btn-primary {
            background: var(--ist-primary, #7382E6);
            border-color: var(--ist-primary, #7382E6);
            color: #ffffff !important;
        }

        .error-actions .btn-primary:hover {
            background: var(--ist-primary-dark, #5564A5);
            border-color: var(--ist-primary-dark, #5564A5);
            transform: translateY(-2px);
            box-shadow: var(--ist-shadow-md, 0 4px 12px rgba(0, 0, 0, 0.15));
        }

        .error-actions .btn-outline-secondary {
            color: var(--ist-text-primary, #323C64) !important;
            border-color: var(--ist-border-color, #dee2e6);
            background: transparent;
        }

        .error-actions .btn-outline-secondary:hover {
            background: var(--ist-bg-hover, #e9ecef);
            border-color: var(--ist-primary, #7382E6);
            color: var(--ist-primary, #7382E6) !important;
        }

        .error-footer {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--ist-border-color, #dee2e6);
        }

        .error-footer p {
            color: var(--ist-text-muted, #999);
            font-size: 0.875rem;
            margin: 0;
        }

        .error-footer a {
            color: var(--ist-primary, #7382E6) !important;
            text-decoration: none;
        }

        .error-footer a:hover {
            text-decoration: underline;
        }

        /* Dark Mode Support */
        [data-theme="dark"] .error-page,
        body.dark-mode .error-page {
            background: linear-gradient(135deg, var(--ist-bg-primary, #1a1d2e) 0%, var(--ist-bg-secondary, #252839) 100%);
        }

        [data-theme="dark"] .error-card,
        body.dark-mode .error-card {
            background: var(--ist-bg-secondary, #252839);
            border-color: var(--ist-border-color, #3a3f52);
        }

        [data-theme="dark"] .error-message,
        body.dark-mode .error-message {
            background: var(--ist-bg-tertiary, #2f3349);
        }

        [data-theme="dark"] .error-icon-wrapper.info,
        body.dark-mode .error-icon-wrapper.info {
            background: linear-gradient(135deg, var(--ist-dark-2, #5564A5) 0%, var(--ist-dark-1, #323C64) 100%);
        }

        [data-theme="dark"] .error-icon-wrapper.info i,
        body.dark-mode .error-icon-wrapper.info i {
            color: var(--ist-light-2, #CDDCFF);
        }

        /* Animation */
        @keyframes float {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        .error-icon-wrapper {
            animation: float 3s ease-in-out infinite;
        }

        /* Responsive */
        @media (max-width: 576px) {
            .error-card {
                padding: 2rem 1.5rem;
            }

            .error-code {
                font-size: 3.5rem;
            }

            .error-title {
                font-size: 1.5rem;
            }

            .error-icon-wrapper {
                width: 100px;
                height: 100px;
            }

            .error-icon-wrapper i {
                font-size: 2.5rem;
            }

            .error-actions {
                flex-direction: column;
            }

            .error-actions .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>

<body class="backend error_page">
    <div class="error-page">
        <div class="error-container">
            <div class="error-card">
                <!-- Error Icon -->
                <div class="error-icon-wrapper <?php echo $error_color; ?>">
                    <i class="fa <?php echo $error_icon; ?>"></i>
                </div>

                <!-- Error Code -->
                <div class="error-code"><?php echo $error_code; ?></div>

                <!-- Error Title -->
                <h1 class="error-title"><?php echo $page_title; ?></h1>

                <!-- Error Subtitle -->
                <p class="error-subtitle"><?php echo $error_subtitle; ?></p>

                <!-- Error Message -->
                <div class="error-message">
                    <?php echo $error_message; ?>
                </div>

                <!-- Action Buttons -->
                <div class="error-actions">
                    <a href="<?php echo $home_url; ?>" class="btn btn-primary">
                        <i class="fa fa-home"></i>
                        <?php _e('Go to Homepage', 'cftp_admin'); ?>
                    </a>
                    <button type="button" onclick="goBack()" class="btn btn-outline-secondary">
                        <i class="fa fa-arrow-left"></i>
                        <?php _e('Go Back', 'cftp_admin'); ?>
                    </button>
                </div>
                <script>
                function goBack() {
                    if (window.history.length > 1 && document.referrer) {
                        window.history.back();
                    } else {
                        window.location.href = '<?php echo $home_url; ?>';
                    }
                }
                </script>

                <!-- Footer -->
                <div class="error-footer">
                    <p>
                        <?php _e('Need help?', 'cftp_admin'); ?>
                        <a href="mailto:<?php echo get_option('admin_email'); ?>">
                            <?php _e('Contact Administrator', 'cftp_admin'); ?>
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <?php render_custom_assets('body_bottom'); ?>
</body>

</html>
<?php
exit;
