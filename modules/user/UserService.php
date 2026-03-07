<?php

use FFI\Exception;

class UserService
{
    public array $userRepository;
    private PDO $pdo;

    public function __construct(array $userRepository, PDO $pdo)
    {
        $this->userRepository = $userRepository;
        $this->pdo = $pdo;
    }

    public function ensureUserExists(): bool
    {
        try {
            if ($this->isUserExist()) {
                return true;
            }

            return $this->createUser();
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    private function isUserExist(): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM `users` WHERE `telegram_id` = ? LIMIT 1");
        $stmt->execute([$this->userRepository['id']]);
        return $stmt->rowCount() > 0;
    }

    private function createUser(): bool
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO `users`(`telegram_id`, `first_name`, `last_name`) VALUES(?, ?, ?)"
        );
        $stmt->execute([
            $this->userRepository['id'],
            $this->userRepository['first_name'],
            $this->userRepository['last_name']
        ]);

        return $this->pdo->lastInsertId() > 0;
    }

    public function getStage(): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT `stage` FROM `users` WHERE telegram_id = ?");
            $stmt->execute([$this->userRepository['id']]);
            $result = $stmt->fetch();
            return [
                'success' => $result ? true : false,
                'fields' => $result
            ];
        } catch (Exception $e) {
            error_log($e->getMessage());
            return ['success' => false];
        }
    }

    public function getRecipientRepository($recipientId): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM `users` WHERE `telegram_id` = ?");
            $stmt->execute([$recipientId]);
            $result = $stmt->fetch();

            if (empty($result)) {
                return [
                    'success' => false
                ];
            }

            return [
                'success' => true,
                'fields' => $result
            ];
        } catch (Exception $e) {
            error_log($e->getMessage());
            return ['success' => false];
        }
    }

    public function setUserStage(string $newStage): bool
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE `users` SET `stage` = ? WHERE `telegram_id` = ?");
            $stmt->execute([$newStage, $this->userRepository['id']]);
            return true;
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    public function getUser(): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM `users` WHERE `telegram_id` = ? LIMIT 1");
            $stmt->execute([$this->userRepository['id']]);
            $user = $stmt->fetch();

            if (!$user) {
                return ['success' => false];
            }

            return [
                'success' => true,
                'fields' => $user
            ];
        } catch (Exception $e) {
            error_log($e->getMessage());
            return ['success' => false];
        }
    }

    public function getCountSendCongratulations(): int
    {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `congratulations` WHERE `from_id` = ?");
            $stmt->execute([$this->userRepository['id']]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log($e->getMessage());
            return 0;
        }
    }

    public function getCountTakedCongratulations(): int
    {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `congratulations` WHERE `recipient_id` = ?");
            $stmt->execute([$this->userRepository['id']]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log($e->getMessage());
            return 0;
        }
    }

    public function getCongratulations(): array
    {
        # this request maked with AI (someday I'll learn join...)
        try {
            $stmt = $this->pdo->prepare("
            SELECT 
                c.*,
                u_from.first_name as from_name,
                u_recipient.first_name as recipient_name
            FROM `congratulations` c
            LEFT JOIN `users` u_from ON c.from_id = u_from.telegram_id
            LEFT JOIN `users` u_recipient ON c.recipient_id = u_recipient.telegram_id
            WHERE c.recipient_id = ? OR c.from_id = ?
            ORDER BY c.created_at DESC
            LIMIT 50
        ");
            $stmt->execute([$this->userRepository['id'], $this->userRepository['id']]);
            $congratulations = $stmt->fetchAll();

            if (!$congratulations) {
                return ['success' => false];
            }

            return [
                'success' => true,
                'fields' => $congratulations
            ];
        } catch (Exception $e) {
            error_log($e->getMessage());
            return ['success' => false];
        }
    }

    public function saveCongratulations(string $text, $from, $recipient, $type): bool
    {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO `congratulations`(`recipient_id`, `from_id`, `text`, `is_anonym`) VALUES(?, ?, ?, ?)");
            $stmt->execute([
                $recipient,
                $from,
                $text,
                $type
            ]);

            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }
}
