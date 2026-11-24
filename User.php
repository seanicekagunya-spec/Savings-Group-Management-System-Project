<?php
/* Domain entity (SRS table USER). The hash is private: other code can only ask verifyPassword(). */
class User {
    public function __construct(
        public readonly int $id, public readonly string $name, public readonly string $phone,
        public readonly string $email, public readonly string $systemRole, public readonly string $status,
        private string $passwordHash, private string $createdAt) {}

    public static function fromRow(array $r): User {
        return new User((int)$r['user_id'], $r['full_name'], $r['phone'], $r['email'], $r['system_role'], $r['status'], $r['password_hash'], $r['created_at']);
    }
    public function firstName(): string { return explode(' ', $this->name)[0]; }
    public function isAdmin(): bool { return $this->systemRole === 'Administrator'; }
    public function isActive(): bool { return $this->status === 'Active'; }
    public function verifyPassword(string $password): bool { return password_verify($password, $this->passwordHash); }
    public function toPublic(): array {              // what may leave the server: never the hash
        return ['id' => $this->id, 'name' => $this->name, 'firstName' => $this->firstName(), 'phone' => $this->phone,
                'email' => $this->email, 'systemRole' => $this->systemRole, 'joined' => substr($this->createdAt, 0, 10)];
    }
}