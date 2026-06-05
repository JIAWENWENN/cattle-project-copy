<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$workflow = \App\Models\WeeklyCattleReturnWorkflow::find(16);
$currentStep = (int) ($workflow->endorsement_step ?? 0);
$stepIndex = 0;

if ($stepIndex === $currentStep && $currentStep < 3) {
    $workflow->endorsement_step = $currentStep + 1;
}

$workflow->save();
echo 'Saved endorsement_step: ' . $workflow->endorsement_step . PHP_EOL;

// Fetch fresh from db
$fresh = \App\Models\WeeklyCattleReturnWorkflow::find(16);
echo 'Fresh endorsement_step: ' . $fresh->endorsement_step . PHP_EOL;
