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
            return [ 'success' => false ];
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
            return [ 'success' => false ];
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
            $SQLusers = $stmt->fetch();

            $stmt = $this->pdo->prepare("SELECT * FROM `congratulations` WHERE `telegram_id` = ? LIMIT 50");
            $stmt->execute([$this->userRepository['id']]);
            $SQLcongratulations = $stmt->fetch();

            return [
                'success' => true,
                'user' => $SQLusers,
                'congratulations' => $SQLcongratulations
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
