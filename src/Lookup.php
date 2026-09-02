<?php
// The normalized schema replaces free-text/ENUM columns with small reference
// tables (roles, account_statuses, location_contexts, report_statuses,
// permit_scopes, symptom_types). This class resolves between the human-
// readable name the frontend uses and the id stored as a foreign key,
// caching each table's rows for the lifetime of the request so repeated
// lookups don't re-query.

class Lookup {
    private static array $cache = [];

    private static function rows(PDO $pdo, string $table): array {
        if (!isset(self::$cache[$table])) {
            $stmt = $pdo->query("SELECT * FROM {$table}");
            self::$cache[$table] = $stmt->fetchAll();
        }
        return self::$cache[$table];
    }

    public static function idByName(PDO $pdo, string $table, ?string $name): ?int {
        if ($name === null || $name === '') return null;
        foreach (self::rows($pdo, $table) as $row) {
            if ($row['name'] === $name) return (int) $row['id'];
        }
        return null;
    }

    public static function nameById(PDO $pdo, string $table, ?int $id): ?string {
        if ($id === null) return null;
        foreach (self::rows($pdo, $table) as $row) {
            if ((int) $row['id'] === $id) return $row['name'];
        }
        return null;
    }

    /** All names in a lookup table, in id order — used to validate input. */
    public static function names(PDO $pdo, string $table): array {
        return array_column(self::rows($pdo, $table), 'name');
    }

    public static function codeToSymptomId(PDO $pdo, string $code): ?int {
        foreach (self::rows($pdo, 'symptom_types') as $row) {
            if ($row['code'] === $code) return (int) $row['id'];
        }
        return null;
    }
}
