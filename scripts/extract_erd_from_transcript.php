<?php

$path = 'C:/Users/Admin/.cursor/projects/C-wamp64-www-cattle-project/agent-transcripts/2bf1862a-1d78-40a6-9870-63c62598600d/2bf1862a-1d78-40a6-9870-63c62598600d.jsonl';
$line = strtok(file_get_contents($path), "\n");
$data = json_decode($line, true);
$content = $data['message']['content'] ?? [];
$sql = '';
foreach ($content as $part) {
    if (($part['type'] ?? '') === 'text') {
        $sql .= $part['text'] ?? '';
    }
}
if ($sql === '') {
    fwrite(STDERR, "No SQL text found. Keys: " . implode(', ', array_keys($data['message'] ?? [])) . "\n");
    fwrite(STDERR, 'SQL len sample: ' . strlen(json_encode($data)) . "\n");
    exit(1);
}

preg_match_all('/CREATE TABLE(?: IF NOT EXISTS)? `([^`]+)`/', $sql, $tables);
preg_match_all(
    '/CONSTRAINT `([^`]+)` FOREIGN KEY \(`([^`]+)`\) REFERENCES `([^`]+)` \(`([^`]+)`\)/',
    $sql,
    $fks,
    PREG_SET_ORDER
);

$skip = [
    'cache', 'cache_locks', 'failed_jobs', 'jobs', 'job_batches',
    'migrations', 'password_reset_tokens', 'sessions',
];

$rels = [];
foreach ($fks as $fk) {
    [, , $col, $refTable, $refCol] = $fk;
    if (in_array($refTable, $skip, true)) {
        continue;
    }
    $rels[] = [$refTable, $col, $refTable, $refCol];
}

// Infer child table from CREATE TABLE blocks
$childByConstraint = [];
foreach ($tables[1] as $table) {
    if (in_array($table, $skip, true)) {
        continue;
    }
    if (!preg_match(
        '/CREATE TABLE(?: IF NOT EXISTS)? `' . preg_quote($table, '/') . '`[^;]+;/s',
        $sql,
        $block
    )) {
        continue;
    }
    preg_match_all(
        '/CONSTRAINT `([^`]+)` FOREIGN KEY \(`([^`]+)`\) REFERENCES `([^`]+)` \(`([^`]+)`\)/',
        $block[0],
        $tbFks,
        PREG_SET_ORDER
    );
    foreach ($tbFks as $tf) {
        $childByConstraint[] = [
            'child' => $table,
            'col' => $tf[2],
            'parent' => $tf[3],
            'pcol' => $tf[4],
        ];
    }
}

echo "TABLES (" . count($tables[1]) . "):\n";
foreach ($tables[1] as $t) {
    if (!in_array($t, $skip, true)) {
        echo "  - $t\n";
    }
}

echo "\nFOREIGN KEYS (" . count($childByConstraint) . "):\n";
foreach ($childByConstraint as $r) {
    echo "  {$r['child']}.{$r['col']} -> {$r['parent']}.{$r['pcol']}\n";
}
