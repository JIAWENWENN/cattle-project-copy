<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$workflows = \App\Models\WeeklyCattleReturnWorkflow::all();
foreach ($workflows as $w) {
    $docs = $w->endorsement_documents ?? [];
    
    // Find the highest step uploaded
    $highestStep = -1;
    for ($i = 0; $i < 4; $i++) {
        if (isset($docs[$i]) || isset($docs[(string)$i])) {
            $highestStep = $i;
        }
    }
    
    $expectedStep = $highestStep + 1;
    if ($w->is_completed) {
        $expectedStep = 4;
    }
    
    if ($expectedStep > 0 && (int)$w->endorsement_step < $expectedStep && (int)$w->endorsement_step < 4) {
        $w->endorsement_step = $expectedStep;
        $w->save();
        echo 'Fixed workflow ' . $w->id . ' to step ' . $expectedStep . PHP_EOL;
    }
}
