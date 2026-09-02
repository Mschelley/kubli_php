<?php
require_once __DIR__ . '/../Lookup.php';

class ReportRepository {

    private const SELECT = "
        SELECT r.id, r.species, r.description, r.base_level, r.level,
               r.coords, r.lat, r.lng, r.photo_url, r.scope_id, r.created_at,
               c.name AS context, rs.name AS status, ps.name AS scope,
               u.id AS submitted_by, u.name AS submitted_by_name
        FROM reports r
        JOIN location_contexts c ON c.id = r.context_id
        JOIN report_statuses rs ON rs.id = r.status_id
        LEFT JOIN permit_scopes ps ON ps.id = r.scope_id
        JOIN users u ON u.id = r.submitted_by
    ";

    /** All reports, newest first, each with its symptom codes attached. */
    public static function listAll(PDO $pdo): array {
        $stmt = $pdo->query(self::SELECT . ' ORDER BY r.created_at DESC, r.id DESC');
        $rows = $stmt->fetchAll();

        $symptomsByReport = self::symptomCodesByReport($pdo);

        return array_map(function ($r) use ($symptomsByReport) {
            return [
                'id' => (int) $r['id'],
                'species' => $r['species'],
                'description' => $r['description'],
                'context' => $r['context'],
                'symptoms' => $symptomsByReport[$r['id']] ?? [],
                'base_level' => (int) $r['base_level'],
                'level' => (int) $r['level'],
                'coords' => $r['coords'],
                'lat' => $r['lat'] !== null ? (float) $r['lat'] : null,
                'lng' => $r['lng'] !== null ? (float) $r['lng'] : null,
                'photo_url' => $r['photo_url'],
                'status' => $r['status'],
                'scope' => $r['scope'],
                'submitted_by' => (int) $r['submitted_by'],
                'submitted_by_name' => $r['submitted_by_name'],
                'created_at' => $r['created_at'],
            ];
        }, $rows);
    }

    /** Map of report_id => [symptom codes], one query for every report. */
    private static function symptomCodesByReport(PDO $pdo): array {
        $stmt = $pdo->query('
            SELECT rsy.report_id, st.code
            FROM report_symptoms rsy
            JOIN symptom_types st ON st.id = rsy.symptom_type_id
        ');
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[$row['report_id']][] = $row['code'];
        }
        return $map;
    }

    public static function create(PDO $pdo, array $fields): int {
        $contextId = Lookup::idByName($pdo, 'location_contexts', $fields['context']);
        $statusId = Lookup::idByName($pdo, 'report_statuses', 'Pending Review');

        $stmt = $pdo->prepare('INSERT INTO reports
            (species, description, context_id, base_level, level, coords, lat, lng, photo_url, status_id, submitted_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $fields['species'], $fields['description'], $contextId,
            $fields['base_level'], $fields['level'], $fields['coords'],
            $fields['lat'], $fields['lng'], $fields['photo_url'],
            $statusId, $fields['submitted_by'],
        ]);
        $reportId = (int) $pdo->lastInsertId();

        $insertSymptom = $pdo->prepare('INSERT INTO report_symptoms (report_id, symptom_type_id) VALUES (?, ?)');
        foreach ($fields['symptoms'] as $code) {
            $symptomId = Lookup::codeToSymptomId($pdo, $code);
            if ($symptomId !== null) $insertSymptom->execute([$reportId, $symptomId]);
        }

        return $reportId;
    }

    public static function updateStatusAndScope(PDO $pdo, int $id, ?string $status, bool $touchScope, ?string $scope): void {
        $fields = [];
        $params = [];
        if ($status !== null) {
            $fields[] = 'status_id = ?';
            $params[] = Lookup::idByName($pdo, 'report_statuses', $status);
        }
        if ($touchScope) {
            $fields[] = 'scope_id = ?';
            $params[] = $scope ? Lookup::idByName($pdo, 'permit_scopes', $scope) : null;
        }
        if (!$fields) return;
        $params[] = $id;
        $pdo->prepare('UPDATE reports SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
    }
}
