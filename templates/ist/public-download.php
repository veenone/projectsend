<?php
/*
Public download template for IST Professional theme
*/
$ld = 'ist_template';

// Determine logged-in state
$is_logged_in = defined('CURRENT_USER_ID') && CURRENT_USER_ID !== null;

// Get client info if logged in
if ($is_logged_in) {
    $client_info = get_client_by_id(CURRENT_USER_ID);
}

$window_title = __('File Download', 'ist_template') . ' - ' . $file->title;

$page_id = 'ist_template_download';

// Get template and branding information
$this_template = get_option('selected_clients_template');
$this_template_url = BASE_URI.'templates/'.$this_template.'/';
$logo_file_info = generate_logo_url();

$body_class = array('template', 'ist-template', 'ist-download', 'hide_title');

?>
<!DOCTYPE html>
<html lang="<?php echo SITE_LANG; ?>" class="h-full">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo html_output($window_title . ' &raquo; ' . SYSTEM_NAME); ?></title>
    <?php meta_favicon(); ?>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#CDDCFF',
                            100: '#96AFFF',
                            200: '#7382E6',
                            300: '#5564A5',
                            400: '#323C64',
                            500: '#7382E6',
                            600: '#5564A5',
                            700: '#323C64',
                            800: '#252f4a',
                            900: '#1a2238'
                        },
                        gray: {
                            50: '#f9fafb',
                            100: '#f3f4f6',
                            200: '#e5e7eb',
                            800: '#1f2937',
                            900: '#111827'
                        }
                    }
                }
            }
        }
    </script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Custom CSS -->
    <style>
        /* IST Brand Colors */
        :root {
            --ist-main: #7382E6;
            --ist-dark-1: #323C64;
            --ist-dark-2: #5564A5;
            --ist-light-1: #96AFFF;
            --ist-light-2: #CDDCFF;
            --ist-text-primary: #1f2937;
            --ist-text-secondary: #4b5563;
        }

        .dark :root {
            --ist-main: #96AFFF;
            --ist-dark-1: #CDDCFF;
            --ist-dark-2: #7382E6;
            --ist-light-1: #5564A5;
            --ist-light-2: #323C64;
            --ist-text-primary: #f3f4f6;
            --ist-text-secondary: #d1d5db;
        }

        /* Custom gradient backgrounds */
        .ist-gradient-header {
            background: linear-gradient(135deg, #323C64 0%, #5564A5 50%, #7382E6 100%);
        }

        .ist-gradient-card {
            background: linear-gradient(135deg, #7382E6 0%, #5564A5 100%);
        }

        .dark .ist-gradient-header {
            background: linear-gradient(135deg, #323C64 0%, #5564A5 100%);
        }

        .dark .ist-gradient-card {
            background: linear-gradient(135deg, #5564A5 0%, #7382E6 100%);
        }

        /* Link styles */
        a:not([class*="bg-"]):not([class*="border-"]):not(.btn) {
            color: var(--ist-main) !important;
            transition: color 0.2s ease;
        }

        a:not([class*="bg-"]):not([class*="border-"]):not(.btn):hover {
            color: var(--ist-dark-2) !important;
        }

        /* Ensure proper text colors for spans and general text */
        span {
            color: var(--ist-text-primary) !important;
        }

        /* Keep badge and utility class colors */
        span[class*="text-"],
        span[class*="bg-"],
        .badge,
        .badge span {
            color: inherit !important;
        }

        /* Headings */
        h1, h2, h3, h4, h5, h6 {
            color: var(--ist-text-primary) !important;
        }

        /* Paragraphs */
        p:not([class*="text-"]) {
            color: var(--ist-text-secondary) !important;
        }
    </style>

    <script>
        // Dark mode toggle functionality
        function initTheme() {
            if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark')
            } else {
                document.documentElement.classList.remove('dark')
            }
        }

        function toggleTheme() {
            if (localStorage.theme === 'dark') {
                localStorage.theme = 'light'
                document.documentElement.classList.remove('dark')
            } else {
                localStorage.theme = 'dark'
                document.documentElement.classList.add('dark')
            }
        }

        // Initialize theme on load
        initTheme();
    </script>

    <script>
        window.base_url = '<?php echo BASE_URI; ?>';
        window.isPublicContext = true;
    </script>

    <?php render_custom_assets('head'); ?>
</head>

<body class="h-full bg-gray-50 dark:bg-gray-900 transition-colors duration-200">
    <?php render_custom_assets('body_top'); ?>

    <!-- Top Navigation Bar -->
    <nav class="ist-gradient-header shadow-sm border-b border-gray-700">
        <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <!-- Logo/Title -->
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <?php if ($logo_file_info && $logo_file_info['exists']): ?>
                            <img src="<?php echo $logo_file_info['url']; ?>" alt="<?php echo get_option('this_install_title'); ?>" class="h-10 w-auto">
                        <?php else: ?>
                            <h1 class="text-xl font-bold text-white">
                                <?php echo html_output(get_option('this_install_title')); ?>
                            </h1>
                        <?php endif; ?>
                    </div>
                    <div class="ml-4 px-3 py-1 bg-white bg-opacity-20 text-white text-sm font-medium rounded-full">
                        <?php echo __('Document Access', 'ist_template'); ?>
                    </div>
                </div>

                <!-- Navigation Menu -->
                <div class="flex items-center space-x-6">
                    <?php if ($is_logged_in) { ?>
                        <!-- User Info (if logged in) -->
                        <div class="hidden md:flex items-center text-sm text-white">
                            <i class="fas fa-user mr-2"></i>
                            <?php echo htmlspecialchars($client_info['name']); ?>
                        </div>

                        <!-- Public Files Link -->
                        <a href="<?php echo BASE_URI; ?>public.php"
                           class="p-2 rounded-lg text-white hover:bg-white hover:bg-opacity-20 transition-colors duration-200"
                           title="<?php _e('Public Files', 'ist_template'); ?>">
                            <i class="fas fa-globe"></i>
                        </a>

                        <!-- Private Files Link -->
                        <a href="<?php echo CLIENT_VIEW_FILE_LIST_URL; ?>"
                           class="p-2 rounded-lg text-white hover:bg-white hover:bg-opacity-20 transition-colors duration-200"
                           title="<?php _e('Private Files', 'ist_template'); ?>">
                            <i class="fas fa-lock"></i>
                        </a>

                        <!-- Dashboard Link -->
                        <a href="<?php echo BASE_URI; ?>manage-files.php"
                           class="p-2 rounded-lg text-white hover:bg-white hover:bg-opacity-20 transition-colors duration-200"
                           title="<?php _e('Dashboard', 'ist_template'); ?>">
                            <i class="fas fa-tachometer-alt"></i>
                        </a>

                        <?php if (current_user_can_upload()) { ?>
                        <a href="<?php echo BASE_URI; ?>upload.php"
                           class="p-2 rounded-lg text-white hover:bg-white hover:bg-opacity-20 transition-colors duration-200"
                           title="<?php _e('Upload Files', 'ist_template'); ?>">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </a>
                        <?php } ?>

                        <!-- Logout -->
                        <a href="<?php echo BASE_URI; ?>process.php?do=logout"
                           class="p-2 rounded-lg text-white hover:bg-white hover:bg-opacity-20 transition-colors duration-200"
                           title="<?php _e('Logout', 'ist_template'); ?>">
                            <i class="fas fa-sign-out-alt"></i>
                        </a>
                    <?php } else { ?>
                        <!-- Public Files Link -->
                        <a href="<?php echo BASE_URI; ?>public.php"
                           class="p-2 rounded-lg text-white hover:bg-white hover:bg-opacity-20 transition-colors duration-200"
                           title="<?php _e('Public Files', 'ist_template'); ?>">
                            <i class="fas fa-globe"></i>
                        </a>

                        <!-- Login Link -->
                        <a href="<?php echo BASE_URI; ?>index.php"
                           class="px-4 py-2 bg-white text-primary-700 rounded-lg hover:bg-gray-100 transition-colors duration-200">
                            <i class="fas fa-sign-in-alt mr-2"></i>
                            <?php _e('Login', 'ist_template'); ?>
                        </a>
                    <?php } ?>

                    <!-- Dark Mode Toggle -->
                    <button onclick="toggleTheme()"
                            class="p-2 rounded-lg text-white hover:bg-white hover:bg-opacity-20 transition-colors duration-200"
                            title="<?php _e('Toggle dark mode', 'ist_template'); ?>">
                        <i class="fas fa-moon dark:hidden"></i>
                        <i class="fas fa-sun hidden dark:inline"></i>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                        <?php echo __('Document Download', 'ist_template'); ?>
                    </h2>
                    <p class="text-gray-600 dark:text-gray-300">
                        <?php echo __('Download and view document information', 'ist_template'); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Download Content -->
        <?php if ($can_view): ?>
            <!-- File Information Card -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <!-- Card Header -->
                <div class="ist-gradient-card px-6 py-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-16 h-16 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                                <i class="fas <?php echo get_file_type_icon($file->extension); ?> text-2xl text-white"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-white">
                                <?php echo html_output($file->filename_original); ?>
                            </h3>
                            <?php if ($file->filename_original != $file->title): ?>
                                <p class="text-white text-opacity-90 text-sm">
                                    <?php echo html_output($file->title); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Card Content -->
                <div class="p-6">
                    <!-- File Details Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <!-- File Size -->
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-hdd text-primary-500 dark:text-primary-100"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        <?php echo __('File Size', 'ist_template'); ?>
                                    </p>
                                    <p class="text-lg font-semibold text-gray-900 dark:text-white">
                                        <?php echo $file->size_formatted; ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- File Type -->
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-tag text-primary-500 dark:text-primary-100"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        <?php echo __('File Type', 'ist_template'); ?>
                                    </p>
                                    <p class="text-lg font-semibold text-gray-900 dark:text-white">
                                        <?php echo strtoupper($file->extension); ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Dimensions (if image) -->
                        <?php if (file_is_image($file->full_path)): ?>
                            <?php $dimensions = $file->getDimensions(); ?>
                            <?php if (!empty($dimensions)): ?>
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-expand-arrows-alt text-primary-500 dark:text-primary-100"></i>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                                <?php echo __('Dimensions', 'ist_template'); ?>
                                            </p>
                                            <p class="text-lg font-semibold text-gray-900 dark:text-white">
                                                <?php echo $dimensions['width'] . ' × ' . $dimensions['height']; ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Description -->
                    <?php if (!empty($file->description)): ?>
                        <div class="mb-6">
                            <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-3">
                                <?php echo __('Description', 'ist_template'); ?>
                            </h4>
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                                    <?php echo nl2br(html_output($file->description)); ?>
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- S3 Metadata -->
                    <?php
                    if (!empty($file->s3_metadata)) {
                        $s3_metadata_decoded = json_decode($file->s3_metadata, true);
                        if ($s3_metadata_decoded && is_array($s3_metadata_decoded)):
                    ?>
                        <div class="mb-6">
                            <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-3">
                                <i class="fas fa-info-circle mr-2"></i><?php echo __('Document Information', 'ist_template'); ?>
                            </h4>
                            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-primary-900 dark:to-primary-800 rounded-lg p-4 border border-primary-200 dark:border-primary-700">
                                <div class="space-y-3">
                                    <?php if (!empty($s3_metadata_decoded['current-version'])): ?>
                                        <div class="flex items-start">
                                            <span class="font-semibold text-gray-700 dark:text-gray-200 min-w-[180px]"><?php echo __('Current Version', 'ist_template'); ?>:</span>
                                            <span class="text-gray-900 dark:text-white"><?php echo html_output($s3_metadata_decoded['current-version']); ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($s3_metadata_decoded['version'])): ?>
                                        <div class="flex items-start">
                                            <span class="font-semibold text-gray-700 dark:text-gray-200 min-w-[180px]"><?php echo __('Version', 'ist_template'); ?>:</span>
                                            <span class="text-gray-900 dark:text-white"><?php echo html_output($s3_metadata_decoded['version']); ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($s3_metadata_decoded['sharepoint-url'])): ?>
                                        <div class="flex items-start">
                                            <span class="font-semibold text-gray-700 dark:text-gray-200 min-w-[180px]"><?php echo __('SharePoint URL', 'ist_template'); ?>:</span>
                                            <a href="<?php echo html_output($s3_metadata_decoded['sharepoint-url']); ?>" target="_blank" class="text-primary-600 dark:text-primary-300 hover:underline">
                                                <i class="fas fa-external-link-alt mr-1"></i><?php echo __('Open in SharePoint', 'ist_template'); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($s3_metadata_decoded['created-by'])): ?>
                                        <div class="flex items-start">
                                            <span class="font-semibold text-gray-700 dark:text-gray-200 min-w-[180px]"><?php echo __('Created By', 'ist_template'); ?>:</span>
                                            <span class="text-gray-900 dark:text-white"><?php echo html_output($s3_metadata_decoded['created-by']); ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($s3_metadata_decoded['created-by-title'])): ?>
                                        <div class="flex items-start">
                                            <span class="font-semibold text-gray-700 dark:text-gray-200 min-w-[180px]"><?php echo __('Created By Title', 'ist_template'); ?>:</span>
                                            <span class="text-gray-900 dark:text-white"><?php echo html_output($s3_metadata_decoded['created-by-title']); ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($s3_metadata_decoded['modified-date'])): ?>
                                        <div class="flex items-start">
                                            <span class="font-semibold text-gray-700 dark:text-gray-200 min-w-[180px]"><?php echo __('Modified Date', 'ist_template'); ?>:</span>
                                            <span class="text-gray-900 dark:text-white"><?php echo html_output($s3_metadata_decoded['modified-date']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php
                        endif;
                    }
                    ?>

                    <!-- Preview Section -->
                    <?php if (get_option('public_listing_enable_preview') == 1): ?>
                        <div class="mb-6">
                            <?php if (file_is_image($file->full_path)): ?>
                                <?php
                                $thumbnail = make_thumbnail($file->full_path, null, 400, 300);
                                if (!empty($thumbnail['thumbnail']['url'])): ?>
                                    <div class="text-center">
                                        <img src="<?php echo $thumbnail['thumbnail']['url']; ?>"
                                             alt="<?php echo html_output($file->title); ?>"
                                             class="max-w-full h-auto rounded-lg border border-gray-200 dark:border-gray-600 mx-auto">
                                    </div>
                                <?php endif; ?>
                            <?php elseif ($file->embeddable): ?>
                                <div class="text-center">
                                    <button onclick="showPreview('<?php echo BASE_URI; ?>process.php?do=get_preview&file_id=<?php echo $file->id; ?>')"
                                            class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors duration-200">
                                        <i class="fas fa-eye mr-2"></i>
                                        <?php echo __('Preview Document', 'ist_template'); ?>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Download Actions -->
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                        <div class="flex flex-col sm:flex-row gap-4 justify-center">
                            <?php if ($can_download): ?>
                                <a href="<?php echo $file->public_url . '&download'; ?>"
                                   class="inline-flex items-center justify-center px-6 py-3 bg-primary-500 text-white font-medium rounded-lg hover:bg-primary-600 transition-colors duration-200 shadow-sm">
                                    <i class="fas fa-download mr-2"></i>
                                    <?php echo __('Download Document', 'ist_template'); ?>
                                </a>
                            <?php else: ?>
                                <div class="inline-flex items-center justify-center px-6 py-3 bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300 font-medium rounded-lg">
                                    <i class="fas fa-ban mr-2"></i>
                                    <?php echo __('Download Not Available', 'ist_template'); ?>
                                </div>
                            <?php endif; ?>

                            <a href="<?php echo BASE_URI; ?>public.php"
                               class="inline-flex items-center justify-center px-6 py-3 bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300 font-medium rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors duration-200">
                                <i class="fas fa-arrow-left mr-2"></i>
                                <?php echo __('Back to Documents', 'ist_template'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Access Denied -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center">
                <div class="w-16 h-16 bg-red-100 dark:bg-red-900 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-ban text-2xl text-red-600 dark:text-red-400"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                    <?php echo __('Access Denied', 'ist_template'); ?>
                </h3>
                <p class="text-gray-600 dark:text-gray-400 mb-6">
                    <?php echo __('You do not have permission to view this document.', 'ist_template'); ?>
                </p>
                <a href="<?php echo BASE_URI; ?>public.php"
                   class="inline-flex items-center px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 transition-colors duration-200">
                    <i class="fas fa-arrow-left mr-2"></i>
                    <?php echo __('Back to Documents', 'ist_template'); ?>
                </a>
            </div>
        <?php endif; ?>

    </main>

    <!-- Footer -->
    <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 mt-12">
        <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="text-center text-sm text-gray-600 dark:text-gray-300">
                <?php render_footer_text(); ?>
            </div>
        </div>
    </footer>

    <!-- Preview Modal -->
    <div id="previewModal" class="fixed inset-0 z-50 hidden">
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" onclick="closePreview()"></div>
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-4xl w-full max-h-full overflow-hidden">
                <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        <?php echo __('Document Preview', 'ist_template'); ?>
                    </h3>
                    <button onclick="closePreview()" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors duration-200">
                        <i class="fas fa-times text-gray-500 dark:text-gray-400"></i>
                    </button>
                </div>
                <div id="previewContent" class="p-4 overflow-auto max-h-96">
                    <!-- Preview content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        function showPreview(url) {
            const modal = document.getElementById('previewModal');
            const content = document.getElementById('previewContent');

            content.innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-2xl text-primary-500"></i><p class="mt-2 text-gray-600 dark:text-gray-400">Loading preview...</p></div>';
            modal.classList.remove('hidden');

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    let previewHtml = '';

                    switch(data.type) {
                        case 'image':
                            previewHtml = `<img src="${data.file_url}" alt="${data.name}" class="max-w-full h-auto mx-auto rounded-lg">`;
                            break;
                        case 'video':
                            previewHtml = `<video controls class="max-w-full h-auto mx-auto rounded-lg">
                                            <source src="${data.file_url}" type="${data.mime_type}">
                                            Your browser does not support video playback.
                                          </video>`;
                            break;
                        case 'audio':
                            previewHtml = `<audio controls class="w-full">
                                            <source src="${data.file_url}" type="${data.mime_type}">
                                            Your browser does not support audio playback.
                                          </audio>`;
                            break;
                        case 'pdf':
                            previewHtml = `<iframe src="${data.file_url}" class="w-full h-96 border border-gray-200 dark:border-gray-600 rounded-lg"></iframe>`;
                            break;
                        default:
                            previewHtml = `<div class="text-center py-8">
                                            <i class="fas fa-file-alt text-4xl text-gray-400 mb-4"></i>
                                            <p class="text-gray-600 dark:text-gray-400">Preview not available for this document type.</p>
                                          </div>`;
                    }

                    content.innerHTML = previewHtml;
                })
                .catch(error => {
                    content.innerHTML = '<div class="text-center py-8 text-red-600 dark:text-red-400"><i class="fas fa-exclamation-triangle text-2xl mb-2"></i><p>Error loading preview.</p></div>';
                });
        }

        function closePreview() {
            document.getElementById('previewModal').classList.add('hidden');
            document.getElementById('previewContent').innerHTML = '';
        }

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePreview();
            }
        });
    </script>

    <?php render_custom_assets('body_bottom'); ?>

</body>
</html>
