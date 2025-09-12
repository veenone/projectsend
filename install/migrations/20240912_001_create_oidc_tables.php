<?php
namespace ProjectSend\Migrations;

use PDO;

class CreateOIDCTables {
    private $db;

    public function __construct(PDO $database) {
        $this->db = $database;
    }

    public function up() {
        // Create external user IDs table
        $this->db->exec("CREATE TABLE IF NOT EXISTS tbl_user_external_ids (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            provider VARCHAR(255) NOT NULL,
            external_id VARCHAR(255) NOT NULL,
            access_token TEXT,
            refresh_token TEXT,
            token_expiry DATETIME,
            UNIQUE KEY unique_external_user (provider, external_id),
            FOREIGN KEY (user_id) REFERENCES tbl_users(id) ON DELETE CASCADE
        )");

        // Create synchronization log table
        $this->db->exec("CREATE TABLE IF NOT EXISTS tbl_sync_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            provider VARCHAR(255) NOT NULL,
            sync_timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            sync_status ENUM('success', 'partial', 'failed') NOT NULL,
            sync_details TEXT,
            FOREIGN KEY (user_id) REFERENCES tbl_users(id) ON DELETE CASCADE
        )");

        // Create indexes for performance
        $this->db->exec("CREATE INDEX idx_external_user_provider ON tbl_user_external_ids(provider)");
        $this->db->exec("CREATE INDEX idx_sync_log_user ON tbl_sync_log(user_id, provider)");
    }

    public function down() {
        // Reversible migration
        $this->db->exec("DROP TABLE IF EXISTS tbl_sync_log");
        $this->db->exec("DROP TABLE IF EXISTS tbl_user_external_ids");
    }
}