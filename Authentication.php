<?php
// classes/Authentication.php

class Authentication {
    private $db;
    private $table = 'users';

    public function __construct($db) {
        $this->db = $db;
    }

    public function generateJWT($userId, $role) {
        $issuedAt = time();
        $expire = $issuedAt + JWT_EXPIRY;
        
        $payload = [
            'iat' => $issuedAt,
            'exp' => $expire,
            'userId' => $userId,
            'role' => $role,
            'iss' => APP_URL
        ];

        $header = json_encode(['typ' => 'JWT', 'alg' => JWT_ALGORITHM]);
        $payload = json_encode($payload);

        $headerEncoded = $this->base64UrlEncode($header);
        $payloadEncoded = $this->base64UrlEncode($payload);
        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', 
                "$headerEncoded.$payloadEncoded", 
                JWT_SECRET, 
                true
            )
        );

        return "$headerEncoded.$payloadEncoded.$signature";
    }

    public function verifyJWT($token) {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return false;
        }

        $header = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[0])), true);
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
        $signature = $parts[2];

        $valid = hash_hmac('sha256', 
            "{$parts[0]}.{$parts[1]}", 
            JWT_SECRET, 
            true
        );
        $validB64 = $this->base64UrlEncode($valid);

        if ($signature !== $validB64) {
            return false;
        }

        if ($payload['exp'] < time()) {
            return false;
        }

        return $payload;
    }

    public function register($username, $email, $password, $phone, $role = 'user', $parentId = null) {
        // Validate input
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }

        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters'];
        }

        // Check if user exists
        $stmt = $this->db->prepare("SELECT id FROM {$this->table} WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            return ['success' => false, 'message' => 'Email or username already exists'];
        }

        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        // Generate API key
        $apiKey = bin2hex(random_bytes(32));
        $apiSecret = bin2hex(random_bytes(32));

        // Insert user
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} (username, email, password, phone, role, parent_id, api_key, api_secret, credits) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $credits = ($role === 'reseller') ? 10000 : 100;
        $stmt->bind_param("sssssissi", $username, $email, $hashedPassword, $phone, $role, $parentId, $apiKey, $apiSecret, $credits);

        if ($stmt->execute()) {
            $userId = $this->db->lastInsertId();
            return [
                'success' => true,
                'message' => 'Registration successful',
                'user_id' => $userId,
                'api_key' => $apiKey,
                'api_secret' => $apiSecret
            ];
        }

        return ['success' => false, 'message' => 'Registration failed'];
    }

    public function login($username, $password) {
        $stmt = $this->db->prepare(
            "SELECT id, password, role, status FROM {$this->table} WHERE username = ? OR email = ?"
        );
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'Invalid credentials'];
        }

        $user = $result->fetch_assoc();

        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => 'Account is suspended or inactive'];
        }

        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Invalid credentials'];
        }

        $jwt = $this->generateJWT($user['id'], $user['role']);

        return [
            'success' => true,
            'message' => 'Login successful',
            'token' => $jwt,
            'user_id' => $user['id'],
            'role' => $user['role']
        ];
    }

    public function getCurrentUser($token) {
        $payload = $this->verifyJWT($token);
        
        if (!$payload) {
            return false;
        }

        $stmt = $this->db->prepare(
            "SELECT id, username, email, phone, role, credits, status, parent_id FROM {$this->table} WHERE id = ?"
        );
        $stmt->bind_param("i", $payload['userId']);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_assoc();
    }

    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
