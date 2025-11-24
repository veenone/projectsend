<?php
/**
 * Add navbar brand configuration options
 */
function upgrade_2025112202()
{
    global $dbh;

    $new_options = [
        'navbar_brand_url' => '',
        'navbar_brand_logo' => '',
    ];

    foreach ($new_options as $option_name => $default_value) {
        if (!option_exists_2025112202($option_name)) {
            $stmt = $dbh->prepare("INSERT INTO " . TABLE_OPTIONS . " (name, value) VALUES (:name, :value)");
            $stmt->execute([
                ':name' => $option_name,
                ':value' => $default_value
            ]);
            error_log("ProjectSend: Added option $option_name for configurable navbar branding");
        }
    }
}

function option_exists_2025112202($name)
{
    global $dbh;
    $stmt = $dbh->prepare("SELECT COUNT(*) as count FROM " . TABLE_OPTIONS . " WHERE name = :name");
    $stmt->execute([':name' => $name]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] > 0;
}
