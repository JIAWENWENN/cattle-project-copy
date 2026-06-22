<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Cattle;
use App\Models\Estate;
use App\Http\Controllers\HealthTreatmentController;

$estates = Estate::with('pastureBlocks')->where('is_active', true)->orderBy('name')->get();
$cattle = Cattle::where('status', 'Active')->where('tag_no', '0002')->get(['id', 'tag_no', 'category', 'coat_colour', 'location_block', 'location_phase', 'operating_unit']);

$controller = new HealthTreatmentController();
$method = new ReflectionMethod($controller, 'resolveOperatingUnits');
$method->setAccessible(true);
$resolved = $method->invoke($controller, $cattle, $estates);

echo json_encode($resolved->first(), JSON_PRETTY_PRINT) . PHP_EOL;
