<?php
namespace ProjectSend\Classes;

use PDO;

class UserMapper {
    private $db;

    public function __construct(PDO $database) {
        $this->db = $database;
    }

    public function findByExternalId($provider, $externalId) {
        $stmt = $this->db->prepare("
            SELECT u.* 
            FROM tbl_users u
            JOIN tbl_user_external_ids e ON u.id = e.user_id
            WHERE e.provider = :provider AND e.external_id = :external_id
        ");
        
        $stmt->execute([
            ':provider' => $provider,
            ':external_id' => $externalId
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createFromExternalProvider($claims) {
        // Begin transaction for atomic user creation
        $this->db->beginTransaction();

        try {
            // Create user
            $stmt = $this->db->prepare("
                INSERT INTO tbl_users 
                (username, email, name, active, role) 
                VALUES (:username, :email, :name, 1, 'user')
            ");

            $username = $claims['preferred_username'] ?? $claims['email'] ?? $claims['sub'];
            $stmt->execute([
                ':username' => $username,
                ':email' => $claims['email'] ?? null,
                ':name' => $claims['name'] ?? $username
            ]);

            $userId = $this->db->lastInsertId();

            // Link external ID
            $externalStmt = $this->db->prepare("
                INSERT INTO tbl_user_external_ids 
                (user_id, provider, external_id) 
                VALUES (:user_id, :provider, :external_id)
            ");

            $externalStmt->execute([
                ':user_id' => $userId,
                ':provider' => 'oidc', // Configurable provider name
                ':external_id' => $claims['sub']
            ]);

            $this->db->commit();

            return $this->findById($userId);
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function findById($userId) {
        $stmt = $this->db->prepare("SELECT * FROM tbl_users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}