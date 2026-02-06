<?php
// classes/WhatsAppAPI.php

class WhatsAppAPI {
    private $db;
    private $businessAccountId;
    private $apiToken;
    private $webhookSecret;

    public function __construct($db) {
        $this->db = $db;
        $this->businessAccountId = WHATSAPP_BUSINESS_ACCOUNT_ID;
        $this->apiToken = WHATSAPP_API_TOKEN;
    }

    /**
     * Send message via official WhatsApp API
     */
    public function sendMessage($phoneNumber, $message, $mediaUrl = null, $mediaType = null, $templateId = null) {
        try {
            $url = WHATSAPP_API_URL . "/{$this->businessAccountId}/messages";

            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $this->formatPhoneNumber($phoneNumber),
                'type' => $this->getMessageType($mediaType)
            ];

            if ($templateId) {
                $payload['template'] = ['name' => $templateId];
            } else {
                $payload['text'] = ['body' => $message];
            }

            if ($mediaUrl && $mediaType) {
                $payload = array_merge($payload, $this->addMedia($mediaUrl, $mediaType));
            }

            $response = $this->makeRequest($url, 'POST', json_encode($payload));

            if (isset($response['messages'][0]['id'])) {
                return [
                    'success' => true,
                    'message_id' => $response['messages'][0]['id'],
                    'status' => 'sent'
                ];
            }

            return [
                'success' => false,
                'error' => $response['error']['message'] ?? 'Unknown error'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send batch messages
     */
    public function sendBatchMessages($contacts, $templateId, $userId) {
        $successCount = 0;
        $failureCount = 0;
        $results = [];

        foreach ($contacts as $contact) {
            $result = $this->sendMessage(
                $contact['phone'],
                $contact['message'] ?? '',
                $contact['media_url'] ?? null,
                $contact['media_type'] ?? null,
                $templateId
            );

            if ($result['success']) {
                $successCount++;
                $this->logMessage($userId, $contact['phone'], $result['message_id'], 'sent');
            } else {
                $failureCount++;
                $this->logMessage($userId, $contact['phone'], null, 'failed', $result['error']);
            }

            $results[] = [
                'phone' => $contact['phone'],
                'status' => $result['success'] ? 'sent' : 'failed',
                'message_id' => $result['message_id'] ?? null,
                'error' => $result['error'] ?? null
            ];

            // Rate limiting
            usleep(100000); // 100ms delay between messages
        }

        return [
            'success' => true,
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'results' => $results
        ];
    }

    /**
     * Send message via personal WhatsApp connection (Selenium/Puppeteer)
     */
    public function sendPersonalMessage($phoneNumber, $message, $sessionId) {
        try {
            // Call to Node.js backend running Puppeteer
            $url = 'http://localhost:3001/send-message';
            
            $payload = [
                'phone' => $this->formatPhoneNumber($phoneNumber),
                'message' => $message,
                'session_id' => $sessionId
            ];

            $response = $this->makeRequest($url, 'POST', json_encode($payload));

            return [
                'success' => $response['success'] ?? false,
                'message_id' => $response['message_id'] ?? null,
                'error' => $response['error'] ?? null
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get message status
     */
    public function getMessageStatus($messageId) {
        try {
            $url = WHATSAPP_API_URL . "/{$messageId}";
            $response = $this->makeRequest($url, 'GET');

            return [
                'success' => true,
                'status' => $response['status'] ?? 'unknown',
                'timestamp' => $response['timestamp'] ?? null
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload media file
     */
    public function uploadMedia($filePath, $mimeType) {
        try {
            $url = WHATSAPP_API_URL . "/{$this->businessAccountId}/media";

            $cfile = new CURLFile($filePath, $mimeType);
            $post = ['file' => $cfile];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->apiToken
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($response, true);

            if (isset($data['id'])) {
                return [
                    'success' => true,
                    'media_id' => $data['id'],
                    'url' => $data['url'] ?? null
                ];
            }

            return [
                'success' => false,
                'error' => $data['error']['message'] ?? 'Upload failed'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle incoming webhook
     */
    public function handleWebhook($requestBody, $signature) {
        // Verify webhook signature
        if (!$this->verifyWebhookSignature($requestBody, $signature)) {
            return ['success' => false, 'message' => 'Invalid signature'];
        }

        $data = json_decode($requestBody, true);

        if ($data['entry'][0]['changes'][0]['field'] === 'messages') {
            $message = $data['entry'][0]['changes'][0]['value'];
            
            // Update message status
            $this->updateMessageStatus(
                $message['messages'][0]['id'],
                'delivered'
            );
        }

        if ($data['entry'][0]['changes'][0]['field'] === 'message_status') {
            $status = $data['entry'][0]['changes'][0]['value'];
            
            // Update delivery status
            $this->updateMessageStatus(
                $status['messages'][0]['id'],
                $status['messages'][0]['status']
            );
        }

        return ['success' => true];
    }

    /**
     * Helper: Format phone number
     */
    private function formatPhoneNumber($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Add country code if missing (assuming default country)
        if (strlen($phone) === 10) {
            $phone = '1' . $phone; // US
        }
        
        return $phone;
    }

    /**
     * Helper: Get message type
     */
    private function getMessageType($mediaType) {
        $types = [
            'image' => 'image',
            'document' => 'document',
            'video' => 'video',
            'audio' => 'audio'
        ];

        return $types[$mediaType] ?? 'text';
    }

    /**
     * Helper: Add media to payload
     */
    private function addMedia($mediaUrl, $mediaType) {
        $type = $this->getMessageType($mediaType);
        
        return [
            $type => [
                'link' => $mediaUrl
            ]
        ];
    }

    /**
     * Helper: Make HTTP request
     */
    private function makeRequest($url, $method = 'GET', $data = null) {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiToken
        ]);

        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new Exception("HTTP {$httpCode}: {$response}");
        }

        return json_decode($response, true) ?: [];
    }

    /**
     * Verify webhook signature
     */
    private function verifyWebhookSignature($body, $signature) {
        $hash = hash_hmac('sha256', $body, WHATSAPP_API_TOKEN);
        return hash_equals($hash, $signature);
    }

    /**
     * Log message
     */
    private function logMessage($userId, $phone, $messageId, $status, $error = null) {
        $stmt = $this->db->prepare(
            "INSERT INTO message_logs (user_id, phone_number, external_id, status) 
             VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("isss", $userId, $phone, $messageId, $status);
        $stmt->execute();
    }

    /**
     * Update message status
     */
    private function updateMessageStatus($messageId, $status) {
        $stmt = $this->db->prepare(
            "UPDATE message_logs SET status = ? WHERE external_id = ?"
        );
        $stmt->bind_param("ss", $status, $messageId);
        $stmt->execute();
    }
}
