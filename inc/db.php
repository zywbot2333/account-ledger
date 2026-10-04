<?php

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

/**
 * 自动建表：与 schema.sql 对照，只创建缺失的表。
 * 新库全量创建；老库增量补表（如升级新增的 admins 表）。
 */
function ensure_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $sql = (string)file_get_contents(__DIR__ . '/../schema.sql');

    // 按语句拆分（语句以 ";\n" 结尾，注释行以 -- 开头）
    $stmts = [];
    foreach (preg_split('/;\s*\n/', $sql) as $raw) {
        $stmt = trim(preg_replace('/^\s*--.*$/m', '', (string)$raw));
        if ($stmt === '') {
            continue;
        }
        if (preg_match('/CREATE TABLE IF NOT EXISTS\s+`?(\w+)`?/i', $stmt, $m)) {
            $stmts[$m[1]] = $stmt;
        }
    }
    if (!$stmts) {
        return;
    }

    $existing = db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($stmts as $table => $stmt) {
        if (!in_array($table, $existing, true)) {
            db()->exec($stmt);
        }
    }
}
