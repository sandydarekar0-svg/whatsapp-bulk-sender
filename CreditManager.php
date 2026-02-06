<?php
// classes/CreditManager.php

class CreditManager {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Add credits to user account
     */
    public function addCredits($userId, $amount, $description = '', $referenceId = '') {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO credits (user_id, amount, transaction_type, reference_id, description) 
                 VALUES (?, ?, 'purchase', ?, ?)"
            );
            $stmt->bind_param("iiss", $userId, $amount, $referenceId, $description);
            
            if (!$stmt->execute()) {
                return ['success' => false, 'message' => 'Failed to add credits'];
            }

            // Update user credits balance
            $this->updateUserBalance($userId);

            return [
                'success' => true,
                'message' => 'Credits added successfully',
                'transaction_id' => $this->db->lastInsertId()
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Use credits (deduct)
     */
    public function useCredits($userId, $amount, $description = '', $campaignId = '') {
        try {
            // Check available credits
            $currentCredits = $this->getBalance($userId);

            if ($currentCredits < $amount) {
                return [
                    'success' => false,
                    'message' => 'Insufficient credits',
                    'available' => $currentCredits,
                    'required' => $amount
                ];
            }

            $negAmount = -$amount;
            $stmt = $this->db->prepare(
                "INSERT INTO credits (user_id, amount, transaction_type, reference_id, description) 
                 VALUES (?, ?, 'used', ?, ?)"
            );
            $stmt->bind_param("iiss", $userId, $negAmount, $campaignId, $description);

            if (!$stmt->execute()) {
                return ['success' => false, 'message' => 'Failed to use credits'];
            }

            // Update user credits balance
            $this->updateUserBalance($userId);

            return [
                'success' => true,
                'message' => 'Credits used successfully',
                'transaction_id' => $this->db->lastInsertId()
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get user balance
     */
    public function getBalance($userId) {
        $stmt = $this->db->prepare(
            "SELECT SUM(amount) as balance FROM credits WHERE user_id = ?"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        return (int)$result['balance'] ?? 0;
    }

    /**
     * Update user balance in users table
     */
    private function updateUserBalance($userId) {
        $balance = $this->getBalance($userId);
        $stmt = $this->db->prepare("UPDATE users SET credits = ? WHERE id = ?");
        $stmt->bind_param("ii", $balance, $userId);
        $stmt->execute();
    }

    /**
     * Calculate message cost
     */
    public function getMessageCost($messageType = 'text') {
        $costs = [
            'text' => 0.5,
            'image' => 1.0,
            'document' => 1.5,
            'video' => 2.0
        ];

        return $costs[$messageType] ?? 0.5;
    }

    /**
     * Get credit history
     */
    public function getHistory($userId, $limit = 50, $offset = 0) {
        $stmt = $this->db->prepare(
            "SELECT * FROM credits WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?"
        );
        $stmt->bind_param("iii", $userId, $limit, $offset);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Reseller commission calculation
     */
    public function calculateResellerCommission($resellerId, $customerId) {
        $stmt = $this->db->prepare(
            "SELECT 
                COUNT(*) as message_count,
                SUM(CASE WHEN message_type = 'text' THEN 0.5
                        WHEN message_type = 'image' THEN 1.0
                        WHEN message_type = 'document' THEN 1.5
                        WHEN message_type = 'video' THEN 2.0 
                        ELSE 0.5 END) as total_cost
             FROM message_logs 
             WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)"
        );
        $stmt->bind_param("i", $customerId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        $commissionPercentage = 20;
        $commissionAmount = ($result['total_cost'] ?? 0) * ($commissionPercentage / 100);

        return [
            'message_count' => $result['message_count'] ?? 0,
            'total_cost' => $result['total_cost'] ?? 0,
            'commission_percentage' => $commissionPercentage,
            'commission_amount' => $commissionAmount
        ];
    }

    /**
     * Process reseller commission payments
     */
    public function processResellerPayment($resellerId, $customerId) {
        try {
            $commission = $this->calculateResellerCommission($resellerId, $customerId);

            if ($commission['commission_amount'] <= 0) {
                return ['success' => false, 'message' => 'No commission to pay'];
            }

            $stmt = $this->db->prepare(
                "INSERT INTO reseller_commission (reseller_id, customer_id, message_count, commission_amount) 
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param("iid", $resellerId, $customerId, $commission['message_count'], $commission['commission_amount']);

            if ($stmt->execute()) {
                // Add commission as credits
                $this->addCredits(
                    $resellerId,
                    intval($commission['commission_amount']),
                    "Commission from customer {$customerId}",
                    "COMMISSION_" . $customerId
                );

                return [
                    'success' => true,
                    'message' => 'Commission paid successfully',
                    'commission_amount' => $commission['commission_amount']
                ];
            }

            return ['success' => false, 'message' => 'Failed to process commission'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
