<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$w = \App\Models\WeeklyCattleReturnWorkflow::find(16);
$docs = $w->endorsement_documents;
echo "ID 16 docs: " . json_encode($docs) . PHP_EOL;

$highestStep = -1;
for ($i = 0; $i < 4; $i++) {
    if (isset($docs[$i]) || isset($docs[(string)$i])) {
        $highestStep = $i;
    }
}
$expectedStep = $highestStep + 1;
echo "Expected step: " . $expectedStep . PHP_EOL;
echo "Current step: " . $w->endorsement_step . PHP_EOL;

if ($expectedStep > 0 && $w->endorsement_step < $expectedStep) {
    $w->endorsement_step = $expectedStep;
    $w->save();
    echo "Fixed to " . $expectedStep . PHP_EOL;
}
