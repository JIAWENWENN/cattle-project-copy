<?php

namespace Database\Seeders;

use Carbon\Carbon;
use App\Models\FeedingRecord;
use Illuminate\Database\Seeder;

class FeedingRecordSeeder extends Seeder
{
    public function run(): void
    {
        FeedingRecord::truncate();

        // Base data from the user's spreadsheet (Date: 17/2)
        // Format: [trip, cattle, napier, opf, conc, plan, actual, remarks]
        $baseRows = [
            ['BL2B', 128, 2490, 0, 650, 3125, 3140, 'EPKC/FL1'],
            ['BL2D', 128, 2500, 0, 650, 3125, 3150, 'EPKC/FL1'],
            ['FL1F', 93, 1705, 0, 475, 2165, 2180, 'EPKC/WEAN'],
            ['FL1G', 78, 1195, 0, 325, 1513, 1520, 'EPKC/WEAN'],
            ['FL1B', 26, 300, 0, 50, 349, 350, 'FL1/WEAN'],
            ['FL1C', 63, 720, 0, 150, 846, 870, 'FL1/WEAN'],
            ['FL1D', 105, 1215, 0, 200, 1410, 1415, 'FL1/WEAN'],
            ['FL2B', 56, 665, 0, 100, 752, 765, 'FL1/WEAN'],
            ['FL2E', 95, 1100, 0, 200, 1276, 1300, 'EPKC/WEAN'],
            ['FL2F', 102, 1175, 0, 200, 1370, 1375, 'EPKC/WEAN'],
            ['FL2O', 87, 990, 0, 200, 1168, 1190, 'EPKC/WEAN'],
            ['FL2H', 98, 1125, 0, 200, 1316, 1325, 'EPKC/WEAN'],
            ['BL1A', 91, 900, 135, 200, 1222, 1235, 'FL1/WEAN'],
            ['BL1B', 98, 820, 125, 200, 1141, 1145, 'FL1/WEAN'],
            ['BL1C', 162, 1590, 245, 350, 2176, 2185, 'FL1/WEAN'],
            ['BL1D', 158, 1325, 190, 350, 1840, 1865, 'FL1/WEAN'],
            ['BL2A', 192, 1445, 740, 400, 2579, 2585, 'FL1/WEAN'],
            ['BL2C', 172, 1335, 630, 350, 2310, 2315, 'FL1/WEAN'],
            ['BL3A', 176, 1345, 665, 350, 2364, 2360, 'FL1/WEAN'],
            ['BL3B', 170, 1305, 630, 350, 2283, 2285, 'FL1/WEAN'],
            ['BL3C', 199, 1130, 570, 400, 2673, 2100, 'FL1/WEAN'],
            ['BL3D', 204, 1155, 570, 450, 2740, 2175, 'FL1/WEAN'],
            ['FL2C', 112, 730, 0, 325, 1051, 1055, 'WEANERS'],
            ['FL2D', 198, 785, 0, 375, 1143, 1160, 'WEANERS'],
        ];

        // TMR 30.1 entries
        $tmrRows = [
            ['FL1B', 28, 125, 125, 'TMR30.1'],
            ['FL2B', 23, 125, 125, 'TMR30.1'],
            ['FL1H', 46, 225, 200, 'TMR 30.1'],
            ['FL2A', 79, 675, 675, 'TMR 30.1'],
        ];

        // Generate for every day in February 2026
        for ($day = 1; $day <= 28; $day++) {
            $date = Carbon::create(2026, 2, $day)->toDateString();
            // Small daily variation factor (-3% to +3%)
            $vary = function ($val) use ($day) {
                $pct = (($day * 7 + $day) % 7 - 3) / 100;
                return max(0, (int) round($val * (1 + $pct)));
            };

            foreach ($baseRows as $r) {
                [$trip, $cattle, $napier, $opf, $conc, $plan, $actual, $remarks] = $r;

                // Use exact values for day 17, slight variations for others
                if ($day === 17) {
                    $nap = $napier; $op = $opf; $co = $conc;
                    $pl = $plan; $ac = $actual;
                } else {
                    $nap = $vary($napier);
                    $op = $vary($opf);
                    $co = $vary($conc);
                    $ac = $nap + $op + $co;
                    $pl = $ac - rand(-20, 20);
                }

                $diff = $ac - $pl;
                $napPlanned = $nap - $diff;

                if ($nap > 0) {
                    FeedingRecord::create([
                        'date' => $date, 'trip_no' => $trip,
                        'cattle_count' => $cattle, 'feed_type' => 'Napier',
                        'planned' => $napPlanned, 'actual_usage' => $nap,
                        'receive' => 0, 'carry_forward' => 0,
                        'balance' => 0, 'remarks' => $remarks,
                    ]);
                }
                if ($op > 0) {
                    FeedingRecord::create([
                        'date' => $date, 'trip_no' => $trip,
                        'cattle_count' => $cattle, 'feed_type' => 'OPF',
                        'planned' => $op, 'actual_usage' => $op,
                        'receive' => 0, 'carry_forward' => 0,
                        'balance' => 0, 'remarks' => $remarks,
                    ]);
                }
                if ($co > 0) {
                    FeedingRecord::create([
                        'date' => $date, 'trip_no' => $trip,
                        'cattle_count' => $cattle, 'feed_type' => 'Concentrate',
                        'planned' => $co, 'actual_usage' => $co,
                        'receive' => 0, 'carry_forward' => 0,
                        'balance' => 0, 'remarks' => $remarks,
                    ]);
                }
            }

            // TMR entries
            foreach ($tmrRows as $t) {
                [$trip, $cattle, $actual, $planned, $remarks] = $t;
                $ac = $day === 17 ? $actual : $vary($actual);
                $pl = $day === 17 ? $planned : $vary($planned);
                FeedingRecord::create([
                    'date' => $date, 'trip_no' => $trip,
                    'cattle_count' => $cattle, 'feed_type' => 'Concentrate',
                    'planned' => $pl, 'actual_usage' => $ac,
                    'receive' => 0, 'carry_forward' => 0,
                    'balance' => 0, 'remarks' => $remarks,
                ]);
            }
        }
    }
}
