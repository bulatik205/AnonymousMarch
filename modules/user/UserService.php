<?php
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
}
