<?php
require_once __DIR__ . '/../Lookup.php';

class UserRepository {

    private const SELECT = "
        SELECT u.id, u.name, u.email, u.password_hash, u.created_at,
               r.name AS role, s.name AS status
        FROM users u
        JOIN roles r ON r.id = u.role_id
        JOIN account_statuses s ON s.id = u.status_id
    ";

    public static function findByEmail(PDO $pdo, string $email): ?array {
        $stmt = $pdo->prepare(self::SELECT . ' WHERE u.email = ?');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public static function findById(PDO $pdo, int $id): ?array {
        $stmt = $pdo->prepare(self::SELECT . ' WHERE u.id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function roleOf(PDO $pdo, int $id): ?string {
        $user = self::findById($pdo, $id);
        return $user['role'] ?? null;
    }

    public static function emailExists(PDO $pdo, string $email): bool {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        return (bool) $stmt->fetch();
    }

    public static function create(PDO $pdo, string $name, string $email, string $passwordHash, string $role): int {
        $roleId = Lookup::idByName($pdo, 'roles', $role);
        $statusId = Lookup::idByName($pdo, 'account_statuses', 'Active');
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role_id, status_id) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$name, $email, $passwordHash, $roleId, $statusId]);
        return (int) $pdo->lastInsertId();
    }

    public static function listAll(PDO $pdo): array {
        $stmt = $pdo->query(self::SELECT . ' ORDER BY u.created_at ASC, u.id ASC');
        return array_map(fn($r) => [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'email' => $r['email'],
            'role' => $r['role'],
            'status' => $r['status'],
        ], $stmt->fetchAll());
    }

    public static function updateRoleAndStatus(PDO $pdo, int $id, ?string $role, ?string $status): void {
        $fields = [];
        $params = [];
        if ($role !== null) {
            $fields[] = 'role_id = ?';
            $params[] = Lookup::idByName($pdo, 'roles', $role);
        }
        if ($status !== null) {
            $fields[] = 'status_id = ?';
            $params[] = Lookup::idByName($pdo, 'account_statuses', $status);
        }
        if (!$fields) return;
        $params[] = $id;
        $pdo->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
    }

    /** Public-facing shape used by the session/signin/signup responses. */
    public static function profile(array $user): array {
        return [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'status' => $user['status'],
        ];
    }
}
