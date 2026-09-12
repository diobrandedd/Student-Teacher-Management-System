<?php
declare(strict_types=1);

function subjects_all(): array
{
    return db()->query('SELECT id, code, title FROM subjects ORDER BY code, title')->fetchAll();
}

function subject_label(array $row): string
{
    return trim((string)($row['code'] ?? '')) . ' — ' . trim((string)($row['title'] ?? ''));
}
