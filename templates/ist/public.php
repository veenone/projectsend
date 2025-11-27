<?php
/*
Public files template for IST Professional theme
*/
$ld = 'ist_template';

// Get count from files array
$count = isset($files['pagination']['total']) ? $files['pagination']['total'] : 0;

// Handle per_page parameter for public view
$default_per_page = get_option('pagination_results_per_page');
if (isset($_GET['per_page']) && in_array($_GET['per_page'], [5, 10, 15, 20, 25, 50, 100])) {
    define('TEMPLATE_RESULTS_PER_PAGE', (int)$_GET['per_page']);
} else {
    define('TEMPLATE_RESULTS_PER_PAGE', $default_per_page);
}
define('TEMPLATE_THUMBNAILS_WIDTH', '120');
define('TEMPLATE_THUMBNAILS_HEIGHT', '120');

// Flash errors
if (!$count) {
    if (isset($no_results_error)) {
        switch ($no_results_error) {
            case 'search':
                $flash->error(__('Your search keywords returned no results.', 'ist_template'));
                break;
            case 'filter':
                $flash->error(__('The filters you selected returned no results.', 'ist_template'));
                break;
        }
    } else {
        $flash->warning(__('There are no public files available.', 'ist_template'));
    }
}

// Determine logged-in state
$is_logged_in = defined('CURRENT_USER_ID') && CURRENT_USER_ID !== null;

// Get client info if logged in
if ($is_logged_in) {
    $client_info = get_client_by_id(CURRENT_USER_ID);
}

$window_title = __('Public Document Center', 'ist_template');

$page_id = 'ist_template_public';

// Get template and branding information
$this_template = get_option('selected_clients_template');
$this_template_url = BASE_URI.'templates/'.$this_template.'/';
$logo_file_info = generate_logo_url();

$body_class = array('template', 'ist-template', 'ist-public', 'hide_title');

// Results count
$elements_found_count = $count;
$count_for_pagination = $count;

// Pagination
$pagination_page = (isset($_GET["page"])) ? $_GET["page"] : 1;

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
            --ist-text-primary: #323C64;
            --ist-text-secondary: #5564A5;
        }

        .dark :root {
            --ist-main: #96AFFF;
            --ist-dark-1: #CDDCFF;
            --ist-dark-2: #7382E6;
            --ist-light-1: #5564A5;
            --ist-light-2: #323C64;
            --ist-text-primary: #CDDCFF;
            --ist-text-secondary: #96AFFF;
        }

        /* Custom gradient backgrounds */
        .ist-gradient-header {
            background: linear-gradient(135deg, #323C64 0%, #5564A5 50%, #7382E6 100%);
        }

        .dark .ist-gradient-header {
            background: linear-gradient(135deg, #323C64 0%, #5564A5 100%);
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

        /* File card title and description colors */
        .file-card h3 {
            color: var(--ist-text-primary) !important;
        }

        .file-card p {
            color: var(--ist-text-secondary) !important;
        }

        /* Headings */
        h1, h2, h3, h4, h5, h6 {
            color: var(--ist-text-primary) !important;
        }

        /* Paragraphs */
        p:not([class*="text-"]) {
            color: var(--ist-text-secondary) !important;
        }

        /* Breadcrumb and navigation links */
        .breadcrumb a,
        nav a:not([class*="bg-"]):not([class*="border-"]) {
            color: var(--ist-main) !important;
        }

        .breadcrumb a:hover,
        nav a:not([class*="bg-"]):not([class*="border-"]):hover {
            color: var(--ist-dark-2) !important;
        }

        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
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
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark')
                localStorage.theme = 'light'
            } else {
                document.documentElement.classList.add('dark')
                localStorage.theme = 'dark'
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
                        <?php
                        $navbar_brand = get_navbar_brand_info();
                        $brand_url = !empty($navbar_brand['link_url']) ? $navbar_brand['link_url'] : null;

                        // Start link wrapper if URL configured
                        if ($brand_url) {
                            echo '<a href="' . html_output($brand_url) . '">';
                        }

                        if ($navbar_brand['logo_exists']) {
                            echo '<img src="' . $navbar_brand['logo_url'] . '" alt="' . html_output($navbar_brand['title']) . '" class="h-10 w-auto">';
                        } else {
                            echo '<h1 class="text-xl font-bold text-white">' . html_output($navbar_brand['title']) . '</h1>';
                        }

                        // Close link wrapper
                        if ($brand_url) {
                            echo '</a>';
                        }
                        ?>
                    </div>
                    <div class="ml-4 px-3 py-1 bg-white bg-opacity-20 text-white text-sm font-medium rounded-full">
                        <?php echo __('Public Access', 'ist_template'); ?>
                    </div>
                </div>

                <!-- Navigation Menu -->
                <div class="flex items-center space-x-6">
                    <!-- Files Count -->
                    <div class="hidden md:flex text-sm text-white">
                        <i class="fas fa-globe mr-2"></i>
                        <?php echo sprintf(__('%d public documents', 'ist_template'), $count_for_pagination); ?>
                    </div>

                    <?php if ($is_logged_in) { ?>
                        <!-- User Info (if logged in) -->
                        <div class="hidden md:flex items-center text-sm text-white">
                            <i class="fas fa-user mr-2"></i>
                            <?php echo htmlspecialchars($client_info['name']); ?>
                        </div>

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
                        <?php echo __('Public Document Center', 'ist_template'); ?>
                    </h2>
                    <p class="text-gray-600 dark:text-gray-300">
                        <?php echo __('Browse and download publicly available documents', 'ist_template'); ?>
                    </p>
                </div>

                <!-- Quick Stats -->
                <div class="mt-4 sm:mt-0 flex space-x-4">
                    <div class="bg-white dark:bg-gray-800 px-4 py-2 rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="text-sm text-gray-600 dark:text-gray-300"><?php echo __('Total Files', 'ist_template'); ?></div>
                        <div class="text-xl font-semibold text-gray-900 dark:text-white"><?php echo $count_for_pagination; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Groups Navigation -->
        <?php
        $groups = get_groups(['public' => true]);
        if (!empty($groups) && $mode !== 'group'): ?>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                <i class="fas fa-layer-group mr-2"></i>
                <?php echo __('Browse by Group', 'ist_template'); ?>
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <?php foreach ($groups as $group):
                    $group_file_count = count_public_files_in_group($group['id']); ?>
                    <a href="<?php echo BASE_URI; ?>public.php?group=<?php echo $group['id']; ?>&token=<?php echo $group['public_token']; ?>"
                       class="block p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-600 hover:border-primary-500 dark:hover:border-primary-400 transition-colors duration-200">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <i class="fas fa-folder text-primary-500 dark:text-primary-300 mr-3"></i>
                                <span class="font-medium text-gray-900 dark:text-white"><?php echo html_output($group['name']); ?></span>
                            </div>
                            <span class="bg-primary-100 dark:bg-primary-800 text-primary-800 dark:text-primary-100 text-xs font-medium px-2.5 py-0.5 rounded-full">
                                <?php echo $group_file_count; ?>
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Group Breadcrumb (when viewing a group) -->
        <?php if ($mode === 'group' && isset($group_props)): ?>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-6">
            <div class="flex items-center space-x-2 text-sm">
                <a href="<?php echo BASE_URI; ?>public.php" class="flex items-center text-primary-500 dark:text-primary-400 hover:text-primary-600 dark:hover:text-primary-300">
                    <i class="fas fa-home mr-1"></i>
                    <?php echo __('All Public Files', 'ist_template'); ?>
                </a>
                <span class="text-gray-400 dark:text-gray-500">></span>
                <span class="flex items-center text-gray-700 dark:text-gray-300">
                    <i class="fas fa-folder mr-1"></i>
                    <?php echo html_output($group_props['name']); ?>
                </span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Search and Filters -->
        <?php if ($count > 0 || isset($_GET['search'])): ?>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
            <form action="<?php echo BASE_URI; ?>public.php" method="get" class="flex flex-col lg:flex-row gap-4">
                <?php if (isset($_GET['group'])): ?>
                    <input type="hidden" name="group" value="<?php echo htmlspecialchars($_GET['group']); ?>">
                <?php endif; ?>
                <?php if (isset($_GET['token'])): ?>
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token']); ?>">
                <?php endif; ?>

                <!-- Search Input -->
                <div class="flex-1">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <input type="text"
                               name="search"
                               value="<?php echo isset($_GET['search']) ? html_output($_GET['search']) : ''; ?>"
                               placeholder="<?php echo __('Search documents...', 'ist_template'); ?>"
                               class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    </div>
                </div>

                <!-- Search Button -->
                <button type="submit"
                        class="px-6 py-2 bg-primary-500 hover:bg-primary-600 text-white rounded-lg font-medium transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                    <i class="fas fa-check mr-2"></i>
                    <?php echo __('Apply', 'ist_template'); ?>
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Folder Navigation -->
        <?php
            $current_url = get_form_action_with_existing_parameters('public.php');
            $current_folder = (isset($_GET['folder_id'])) ? (int)$_GET['folder_id'] : null;
            include_once LAYOUT_DIR . DS . 'breadcrumbs.php';
            include_once LAYOUT_DIR . DS . 'folders-nav.php';
        ?>

        <!-- Files Grid -->
        <?php if (isset($count) && $count > 0 && isset($files['files_ids']) && !empty($files['files_ids'])) { ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-5">
            <?php foreach ($files['files_ids'] as $file_id):
                $file = new \ProjectSend\Classes\Files($file_id);

                // Skip expired files in public view
                if ($file->expired) {
                    continue;
                }
            ?>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 hover:shadow-md transition-shadow duration-200 overflow-hidden file-card cursor-pointer flex flex-col" data-file-id="<?php echo $file->id; ?>" data-expired="<?php echo $file->expired ? 'true' : 'false'; ?>">

                <!-- File Icon/Thumbnail -->
                <div class="p-6 text-center relative">
                    <?php if ($file->isImage() && !$file->expired):
                        $thumbnail = make_thumbnail($file->full_path, null, TEMPLATE_THUMBNAILS_WIDTH, TEMPLATE_THUMBNAILS_HEIGHT);
                        if (!empty($thumbnail['thumbnail']['url'])): ?>
                            <img src="<?php echo html_output($thumbnail['thumbnail']['url']); ?>"
                                 alt="<?php echo html_output($file->title); ?>"
                                 class="w-20 h-20 mx-auto rounded-lg object-cover">
                        <?php else: ?>
                            <div class="w-20 h-20 mx-auto bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                                <i class="<?php echo get_file_type_icon($file->extension); ?> text-3xl text-gray-400 dark:text-gray-500"></i>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="w-20 h-20 mx-auto bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                            <i class="<?php echo get_file_type_icon($file->extension); ?> text-3xl text-gray-400 dark:text-gray-500"></i>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- File Details -->
                <div class="px-6 pb-4 flex-1 flex flex-col">
                    <!-- Top Content -->
                    <div class="flex-1">
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-2 line-clamp-2" title="<?php echo html_output($file->title); ?>">
                            <?php echo html_output($file->title); ?>
                        </h3>

                        <?php if (!empty($file->description)): ?>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3 line-clamp-2">
                            <?php echo nl2br(html_output($file->description)); ?>
                        </p>
                        <?php endif; ?>
                    </div>

                    <!-- Bottom Content - Always at bottom -->
                    <div class="mt-auto">
                        <!-- File Meta -->
                        <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mb-3">
                            <span><?php echo $file->size_formatted; ?></span>
                            <span><?php echo format_date($file->uploaded_date); ?></span>
                        </div>

                    <!-- Action Buttons - 2 rows layout -->
                    <div class="space-y-2">
                        <!-- Row 1: Info and Preview/View -->
                        <div class="flex gap-2">
                            <!-- Info Button -->
                            <button type="button"
                               class="get-info flex items-center justify-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-700 dark:hover:text-gray-200 rounded-md text-xs font-semibold transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2 dark:focus:ring-offset-gray-800"
                               data-file-id="<?php echo $file->id; ?>"
                               title="<?php echo __('File Info', 'ist_template'); ?>">
                                <i class="fas fa-info-circle mr-1.5 text-xs"></i>
                                <span class="uppercase tracking-wider"><?php echo __('Info', 'ist_template'); ?></span>
                            </button>

                            <?php if ($file->embeddable): ?>
                            <!-- Preview Button -->
                            <button type="button"
                               class="get-preview flex-1 flex items-center justify-center px-3 py-1.5 border border-primary-500 text-primary-500 hover:bg-primary-500 hover:text-white rounded-md text-xs font-semibold transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800"
                               data-file-id="<?php echo $file->id; ?>"
                               data-file-name="<?php echo html_output($file->filename_original); ?>"
                               data-file-type="<?php echo $file->embeddable_type; ?>"
                               data-file-url="<?php echo html_entity_decode($file->download_link) . '&inline=1'; ?>">
                                <i class="fas fa-eye mr-1.5 text-xs"></i>
                                <span class="uppercase tracking-wider"><?php echo __('Preview', 'ist_template'); ?></span>
                            </button>
                            <?php else: ?>
                            <!-- View Details Button (for non-previewable files) -->
                            <a href="<?php echo BASE_URI; ?>download.php?id=<?php echo $file->id; ?>&token=<?php echo $file->public_token; ?>"
                               class="flex-1 flex items-center justify-center px-3 py-1.5 border border-primary-500 text-primary-500 hover:bg-primary-500 hover:text-white rounded-md text-xs font-semibold transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                                <i class="fas fa-eye mr-1.5 text-xs"></i>
                                <span class="uppercase tracking-wider"><?php echo __('View', 'ist_template'); ?></span>
                            </a>
                            <?php endif; ?>
                        </div>

                        <!-- Row 2: Download (full width) -->
                        <a href="<?php echo $file->download_link; ?>"
                           class="w-full flex items-center justify-center px-3 py-1.5 bg-primary-500 hover:bg-primary-600 text-white rounded-md text-xs font-semibold transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                            <i class="fas fa-download mr-1.5 text-xs"></i>
                            <span class="uppercase tracking-wider"><?php echo __('Download', 'ist_template'); ?></span>
                        </a>
                    </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

            <!-- Pagination -->
            <?php
            if (isset($files['pagination']) && isset($files['pagination']['total']) && $files['pagination']['total'] > TEMPLATE_RESULTS_PER_PAGE) {
                $pagination = new \ProjectSend\Classes\Layout\Pagination;
                echo '<div class="flex justify-center">';
                echo $pagination->make([
                    'link' => 'public.php',
                    'current' => $pagination_page,
                    'item_count' => $files['pagination']['total'],
                    'items_per_page' => TEMPLATE_RESULTS_PER_PAGE,
                ]);
                echo '</div>';
            }
            ?>

        <?php } else { ?>
            <!-- No Files Message -->
            <div class="text-center py-12">
                <div class="mx-auto w-24 h-24 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mb-6">
                    <i class="fas fa-folder-open text-3xl text-gray-400"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                    <?php echo __('No public documents found', 'ist_template'); ?>
                </h3>
                <p class="text-gray-600 dark:text-gray-300 mb-6 max-w-md mx-auto">
                    <?php if (isset($_GET['search']) && !empty($_GET['search'])) { ?>
                        <?php echo __('Your search returned no results. Try different keywords or browse all files.', 'ist_template'); ?>
                    <?php } else { ?>
                        <?php echo __('There are currently no documents available for public access.', 'ist_template'); ?>
                    <?php } ?>
                </p>

                <?php if (isset($_GET['search']) && !empty($_GET['search'])) { ?>
                    <a href="<?php echo BASE_URI; ?>public.php"
                       class="inline-flex items-center px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 transition-colors duration-200">
                        <i class="fas fa-list mr-2"></i>
                        <?php echo __('Browse All Files', 'ist_template'); ?>
                    </a>
                <?php } elseif ($is_logged_in && current_user_can_upload()) { ?>
                    <a href="<?php echo BASE_URI; ?>upload.php"
                       class="inline-flex items-center px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 transition-colors duration-200">
                        <i class="fas fa-cloud-upload-alt mr-2"></i>
                        <?php echo __('Upload Files', 'ist_template'); ?>
                    </a>
                <?php } ?>
            </div>
        <?php } ?>

    </main>

    <!-- Footer -->
    <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 mt-12">
        <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="text-center text-sm text-gray-600 dark:text-gray-300">
                <?php render_footer_text(); ?>
            </div>
        </div>
    </footer>

    <script>
        // Per page functionality
        function updatePerPage(value) {
            const url = new URL(window.location);
            url.searchParams.set('per_page', value);
            window.location = url.toString();
        }

        // Initialize tooltips if needed
        document.addEventListener('DOMContentLoaded', function() {
            // Add any JavaScript initialization here
            console.log('IST Template Public View loaded');
        });
    </script>

    <?php render_custom_assets('body_bottom'); ?>

    <!-- Preview Modal -->
    <div id="preview_modal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div id="preview_modal_overlay" class="fixed inset-0 bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75 transition-opacity"></div>

            <!-- Modal panel -->
            <div id="preview_modal_panel" class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle" style="width: 600px; max-width: 90vw;">
                <div class="bg-white dark:bg-gray-800" style="height: 800px; max-height: 90vh; display: flex; flex-direction: column;">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 id="preview_modal_title" class="text-lg font-medium text-gray-900 dark:text-white truncate pr-4"></h3>
                        <div class="flex items-center space-x-2">
                            <button type="button" id="preview_fullscreen_toggle" class="p-2 text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700" title="<?php echo __('Toggle Fullscreen', 'ist_template'); ?>">
                                <i class="fas fa-expand"></i>
                            </button>
                            <button type="button" id="preview_modal_close" class="p-2 text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <!-- Body -->
                    <div id="preview_modal_body" class="flex-1 overflow-auto p-0">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Preview Modal Styles */
        #preview_modal.fullscreen #preview_modal_panel {
            width: 100% !important;
            max-width: 100% !important;
            height: 100vh !important;
            max-height: 100vh !important;
            margin: 0 !important;
            border-radius: 0 !important;
        }
        #preview_modal.fullscreen #preview_modal_panel > div {
            height: 100vh !important;
            max-height: 100vh !important;
            border-radius: 0 !important;
        }
        #preview_modal .pdf-preview-container,
        #preview_modal .pdf-preview-container object,
        #preview_modal .pdf-preview-container iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
        #preview_modal_body img {
            max-width: 100%;
            height: auto;
            display: block;
            margin: 0 auto;
        }
        #preview_modal_body video,
        #preview_modal_body audio {
            width: 100%;
        }
    </style>

    <!-- Info Modal -->
    <div id="info_modal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="info-modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div id="info_modal_overlay" class="fixed inset-0 bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75 transition-opacity"></div>

            <!-- Modal panel -->
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white dark:bg-gray-800">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white flex items-center">
                            <i class="fas fa-info-circle mr-2 text-primary-500"></i>
                            <?php echo __('File Information', 'ist_template'); ?>
                        </h3>
                        <button type="button" id="info_modal_close" class="p-2 text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <!-- Body -->
                    <div class="px-6 py-4 max-h-[70vh] overflow-y-auto">
                        <!-- Loading indicator -->
                        <div id="info_loading" class="text-center py-8">
                            <i class="fas fa-spinner fa-spin text-2xl text-primary-500"></i>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400"><?php echo __('Loading...', 'ist_template'); ?></p>
                        </div>

                        <!-- Content -->
                        <div id="info_content" class="hidden">
                            <dl class="space-y-4">
                                <!-- Basic Info Section -->
                                <div class="pb-3 border-b border-gray-200 dark:border-gray-700">
                                    <h4 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-3"><?php echo __('Basic Information', 'ist_template'); ?></h4>
                                    <div class="space-y-3">
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400"><?php echo __('Title', 'ist_template'); ?></dt>
                                            <dd id="info_file_title" class="mt-1 text-sm text-gray-900 dark:text-white font-semibold"></dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400"><?php echo __('Filename', 'ist_template'); ?></dt>
                                            <dd id="info_file_name" class="mt-1 text-sm text-gray-900 dark:text-white break-all"></dd>
                                        </div>
                                        <div id="info_description_row">
                                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400"><?php echo __('Description', 'ist_template'); ?></dt>
                                            <dd id="info_file_description" class="mt-1 text-sm text-gray-900 dark:text-white"></dd>
                                        </div>
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400"><?php echo __('File Type', 'ist_template'); ?></dt>
                                                <dd id="info_file_type" class="mt-1 text-sm text-gray-900 dark:text-white"></dd>
                                            </div>
                                            <div>
                                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400"><?php echo __('File Size', 'ist_template'); ?></dt>
                                                <dd id="info_file_size" class="mt-1 text-sm text-gray-900 dark:text-white"></dd>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400"><?php echo __('Upload Date', 'ist_template'); ?></dt>
                                                <dd id="info_file_date" class="mt-1 text-sm text-gray-900 dark:text-white"></dd>
                                            </div>
                                            <div>
                                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400"><?php echo __('Expires', 'ist_template'); ?></dt>
                                                <dd id="info_file_expiry" class="mt-1 text-sm text-gray-900 dark:text-white"></dd>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- S3 Metadata Section -->
                                <div id="info_s3_section" class="hidden">
                                    <h4 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-3"><?php echo __('Document Metadata', 'ist_template'); ?></h4>
                                    <div id="info_s3_metadata" class="space-y-3">
                                        <!-- S3 metadata will be populated here -->
                                    </div>
                                </div>
                            </dl>
                        </div>
                    </div>
                    <!-- Footer -->
                    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700 border-t border-gray-200 dark:border-gray-600 flex justify-end">
                        <button type="button" id="info_modal_close_btn" class="px-4 py-2 bg-primary-500 hover:bg-primary-600 text-white rounded-lg font-medium transition-colors duration-200">
                            <?php echo __('Close', 'ist_template'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Preview Modal
        const modal = document.getElementById('preview_modal');
        const modalPanel = document.getElementById('preview_modal_panel');
        const modalTitle = document.getElementById('preview_modal_title');
        const modalBody = document.getElementById('preview_modal_body');
        const modalClose = document.getElementById('preview_modal_close');
        const modalOverlay = document.getElementById('preview_modal_overlay');
        const fullscreenToggle = document.getElementById('preview_fullscreen_toggle');

        // Info Modal
        const infoModal = document.getElementById('info_modal');
        const infoModalOverlay = document.getElementById('info_modal_overlay');
        const infoModalClose = document.getElementById('info_modal_close');
        const infoModalCloseBtn = document.getElementById('info_modal_close_btn');
        const infoLoading = document.getElementById('info_loading');
        const infoContent = document.getElementById('info_content');

        // S3 metadata key labels
        const metadataLabels = {
            'created-by': '<?php echo __('Created By', 'ist_template'); ?>',
            'created-by-email': '<?php echo __('Creator Email', 'ist_template'); ?>',
            'created-by-title': '<?php echo __('Creator Title', 'ist_template'); ?>',
            'modified-by': '<?php echo __('Modified By', 'ist_template'); ?>',
            'modified-date': '<?php echo __('Modified Date', 'ist_template'); ?>',
            'is-versioned': '<?php echo __('Versioned', 'ist_template'); ?>',
            'is-current-version': '<?php echo __('Current Version', 'ist_template'); ?>',
            'version-id': '<?php echo __('Version ID', 'ist_template'); ?>',
            'content-type': '<?php echo __('Content Type', 'ist_template'); ?>',
            'crawl-depth': '<?php echo __('Crawl Depth', 'ist_template'); ?>',
            'discovered-from': '<?php echo __('Discovered From', 'ist_template'); ?>',
            'enriched-files': '<?php echo __('Enriched Files', 'ist_template'); ?>',
            'enriched-version-label': '<?php echo __('Enriched Version Label', 'ist_template'); ?>',
            'sharepoint-file-size': '<?php echo __('SharePoint File Size', 'ist_template'); ?>',
            'sharepoint-url': '<?php echo __('SharePoint URL', 'ist_template'); ?>',
            'version-url': '<?php echo __('Version URL', 'ist_template'); ?>'
        };

        // Open info modal and fetch data
        function openInfoModal(fileId) {
            // Show modal with loading state
            infoLoading.classList.remove('hidden');
            infoContent.classList.add('hidden');
            infoModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';

            // Fetch file metadata
            fetch(window.base_url + 'process.php?do=get_file_metadata&file_id=' + fileId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Populate basic info
                        document.getElementById('info_file_title').textContent = data.title || '-';
                        document.getElementById('info_file_name').textContent = data.filename || '-';
                        document.getElementById('info_file_description').textContent = data.description || '-';
                        document.getElementById('info_file_type').textContent = data.type || '-';
                        document.getElementById('info_file_size').textContent = data.size || '-';
                        document.getElementById('info_file_date').textContent = data.upload_date || '-';
                        document.getElementById('info_file_expiry').textContent = data.expiry || '-';

                        // Hide description row if empty
                        const descRow = document.getElementById('info_description_row');
                        if (!data.description || data.description === '') {
                            descRow.style.display = 'none';
                        } else {
                            descRow.style.display = 'block';
                        }

                        // Populate S3 metadata if available
                        const s3Section = document.getElementById('info_s3_section');
                        const s3Container = document.getElementById('info_s3_metadata');
                        s3Container.innerHTML = '';

                        if (data.s3_metadata && Object.keys(data.s3_metadata).length > 0) {
                            s3Section.classList.remove('hidden');

                            // Define display order for metadata
                            const displayOrder = ['created-by', 'created-by-email', 'created-by-title', 'modified-by', 'modified-date', 'is-versioned', 'is-current-version', 'version-id', 'content-type', 'crawl-depth', 'discovered-from', 'enriched-files', 'enriched-version-label', 'sharepoint-file-size', 'sharepoint-url', 'version-url'];

                            // First add ordered items
                            displayOrder.forEach(key => {
                                if (data.s3_metadata[key] !== undefined) {
                                    addMetadataRow(s3Container, key, data.s3_metadata[key]);
                                }
                            });

                            // Then add any remaining items not in the order list
                            Object.keys(data.s3_metadata).forEach(key => {
                                if (!displayOrder.includes(key)) {
                                    addMetadataRow(s3Container, key, data.s3_metadata[key]);
                                }
                            });
                        } else {
                            s3Section.classList.add('hidden');
                        }

                        // Show content, hide loading
                        infoLoading.classList.add('hidden');
                        infoContent.classList.remove('hidden');
                    } else {
                        closeInfoModal();
                        alert('<?php echo __('Failed to load file information', 'ist_template'); ?>');
                    }
                })
                .catch(error => {
                    console.error('Error fetching file metadata:', error);
                    closeInfoModal();
                    alert('<?php echo __('Failed to load file information', 'ist_template'); ?>');
                });
        }

        // Helper function to add metadata row
        function addMetadataRow(container, key, value) {
            const label = metadataLabels[key] || formatMetadataKey(key);
            const displayValue = formatMetadataValue(key, value);

            const div = document.createElement('div');
            div.innerHTML = `
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">${label}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">${displayValue}</dd>
            `;
            container.appendChild(div);
        }

        // Format metadata key to readable label
        function formatMetadataKey(key) {
            return key.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        }

        // Format metadata value
        function formatMetadataValue(key, value) {
            if (value === 'true' || value === true) return '<?php echo __('Yes', 'ist_template'); ?>';
            if (value === 'false' || value === false) return '<?php echo __('No', 'ist_template'); ?>';
            return value || '-';
        }

        // Close info modal
        function closeInfoModal() {
            infoModal.classList.add('hidden');
            document.body.style.overflow = '';
            // Reset to loading state for next open
            infoLoading.classList.remove('hidden');
            infoContent.classList.add('hidden');
        }

        // Info modal event listeners
        document.querySelectorAll('.get-info').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const fileId = this.dataset.fileId;
                openInfoModal(fileId);
            });
        });

        infoModalClose.addEventListener('click', closeInfoModal);
        infoModalCloseBtn.addEventListener('click', closeInfoModal);
        infoModalOverlay.addEventListener('click', closeInfoModal);

        // Open preview modal
        function openModal(fileName, fileType, fileUrl) {
            modalTitle.textContent = fileName;

            let content = '';
            switch (fileType) {
                case 'video':
                    content = `
                        <div class="w-full h-full flex items-center justify-center p-4">
                            <video controls class="max-w-full max-h-full">
                                <source src="${fileUrl}">
                                Your browser does not support the video tag.
                            </video>
                        </div>`;
                    break;
                case 'audio':
                    content = `
                        <div class="w-full h-full flex items-center justify-center p-4">
                            <audio controls class="w-full max-w-md">
                                <source src="${fileUrl}">
                                Your browser does not support the audio tag.
                            </audio>
                        </div>`;
                    break;
                case 'pdf':
                    // Add PDF viewer parameters to disable toolbar (read-only)
                    const pdfUrl = fileUrl + '#toolbar=0&navpanes=0&scrollbar=1&view=FitH';
                    content = `
                        <div class="pdf-preview-container" style="height: 100%;">
                            <object data="${pdfUrl}" type="application/pdf" style="width: 100%; height: 100%;">
                                <iframe src="${pdfUrl}" style="width: 100%; height: 100%; border: none;">
                                    <p>Your browser does not support PDFs. <a href="${fileUrl}" target="_blank">Download the PDF</a>.</p>
                                </iframe>
                            </object>
                        </div>`;
                    break;
                case 'image':
                    content = `
                        <div class="w-full h-full flex items-center justify-center p-4">
                            <img src="${fileUrl}" alt="${fileName}" class="max-w-full max-h-full object-contain">
                        </div>`;
                    break;
                default:
                    content = `<div class="p-4 text-center text-gray-500">Preview not available for this file type.</div>`;
            }

            modalBody.innerHTML = content;
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        // Close modal
        function closeModal() {
            modal.classList.add('hidden');
            modal.classList.remove('fullscreen');
            fullscreenToggle.querySelector('i').classList.remove('fa-compress');
            fullscreenToggle.querySelector('i').classList.add('fa-expand');
            modalBody.innerHTML = '';
            document.body.style.overflow = '';
        }

        // Toggle fullscreen
        function toggleFullscreen() {
            modal.classList.toggle('fullscreen');
            const icon = fullscreenToggle.querySelector('i');
            if (modal.classList.contains('fullscreen')) {
                icon.classList.remove('fa-expand');
                icon.classList.add('fa-compress');
            } else {
                icon.classList.remove('fa-compress');
                icon.classList.add('fa-expand');
            }
        }

        // Event listeners
        document.querySelectorAll('.get-preview').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const fileName = this.dataset.fileName;
                const fileType = this.dataset.fileType;
                const fileUrl = this.dataset.fileUrl;
                openModal(fileName, fileType, fileUrl);
            });
        });

        modalClose.addEventListener('click', closeModal);
        modalOverlay.addEventListener('click', closeModal);
        fullscreenToggle.addEventListener('click', toggleFullscreen);

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (!modal.classList.contains('hidden')) {
                    closeModal();
                }
                if (!infoModal.classList.contains('hidden')) {
                    closeInfoModal();
                }
            }
        });
    });
    </script>

</body>
</html>
