<?php

namespace Database\Seeders;

use App\Models\Cattle;
use App\Models\Estate;
use App\Models\MortalityCase;
use App\Models\TransferDocument;
use App\Models\TransferLivestock;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WeeklyReturnRealDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $today = Carbon::today();
            $startOfMonth = $today->copy()->startOfMonth();

            $units = Estate::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name')
                ->map(fn ($name) => trim((string) $name))
                ->filter()
                ->values()
                ->all();

            if (empty($units)) {
                return;
            }

            $createdBy = User::query()->value('id') ?? 1;

            $categories = [
                'B/B' => 'Breeder Bull (B/B)',
                'B/C' => 'Breeder Cow (B/C)',
                'W/B' => 'Weaner Bull (W/B)',
                'H' => 'Heifer (H)',
                'M/C' => 'Male Calf (M/C)',
                'F/C' => 'Female Calf (F/C)',
            ];

            foreach ($units as $index => $unit) {
                $codes = array_keys($categories);
                $code = $codes[$index % count($codes)];
                $tag = sprintf('WR-%02d-%s', $index + 1, strtoupper(substr(md5($unit), 0, 6)));

                Cattle::query()->firstOrCreate(
                    ['tag_no' => $tag],
                    [
                        'category' => $categories[$code],
                        'breed' => 'Brahman',
                        'coat_colour' => 'Brown',
                        'birth_date' => '2025-01-15',
                        'gender' => in_array($code, ['B/B', 'W/B', 'M/C'], true) ? 'Male' : 'Female',
                        'receival_weight' => 220,
                        'general_condition' => 'Good',
                        'ownership' => 'Company',
                        'herd' => 'Main Herd',
                        'location_block' => $unit,
                        'location_phase' => 'Phase 1',
                        'status' => 'Active',
                    ]
                );
            }

            for ($week = 1; $week <= 4; $week++) {
                $weekDate = $startOfMonth->copy()->addDays(($week - 1) * 7);
                if ($weekDate->month !== $startOfMonth->month) {
                    break;
                }

                foreach ($units as $unitIndex => $unit) {
                    $codes = array_keys($categories);
                    $code = $codes[($unitIndex + $week - 1) % count($codes)];
                    $category = $categories[$code];
                    $unitSlug = strtoupper(substr(md5($unit), 0, 4));

                    $mortTag = sprintf('WRM-W%d-%s', $week, $unitSlug);
                    $mortCattle = Cattle::query()->firstOrCreate(
                        ['tag_no' => $mortTag],
                        [
                            'category' => $category,
                            'breed' => 'Brahman',
                            'coat_colour' => 'Black',
                            'birth_date' => '2025-02-01',
                            'gender' => in_array($code, ['B/B', 'W/B', 'M/C'], true) ? 'Male' : 'Female',
                            'receival_weight' => 210,
                            'general_condition' => 'Good',
                            'ownership' => 'Company',
                            'herd' => 'Main Herd',
                            'location_block' => $unit,
                            'location_phase' => 'Phase 1',
                            'status' => 'Active',
                        ]
                    );

                    MortalityCase::query()->firstOrCreate(
                        [
                            'cattle_id' => $mortCattle->id,
                            'death_date' => $weekDate->toDateString(),
                            'location' => $unit,
                        ],
                        [
                            'lmc_no' => sprintf('LMC-W%d-%s', $week, $unitSlug),
                            'reported_by' => 'System Seeder',
                            'time_of_death' => '09:00',
                            'cause_of_death' => 'Operational sample record',
                            'treatment' => 'N/A',
                            'category' => $category,
                            'herd' => 'Main Herd',
                            'block' => 'Block A',
                            'initial_notes' => sprintf('Weekly return sample W%d', $week),
                            'status' => 'completed',
                            'current_step' => 'approved',
                            'created_by' => $createdBy,
                            'endorsement_step' => 4,
                            'is_reopened' => false,
                        ]
                    );

                    $toUnit = $units[($unitIndex + 1) % count($units)];
                    $ctvTag = sprintf('WRT-W%d-%s', $week, $unitSlug);
                    Cattle::query()->firstOrCreate(
                        ['tag_no' => $ctvTag],
                        [
                            'category' => $category,
                            'breed' => 'Brahman',
                            'coat_colour' => 'Brown',
                            'birth_date' => '2025-01-20',
                            'gender' => in_array($code, ['B/B', 'W/B', 'M/C'], true) ? 'Male' : 'Female',
                            'receival_weight' => 230,
                            'general_condition' => 'Good',
                            'ownership' => 'Company',
                            'herd' => 'Main Herd',
                            'location_block' => $unit,
                            'location_phase' => 'Phase 1',
                            'status' => 'Active',
                        ]
                    );

                    $ctvDocNo = sprintf('CTV-W%d-%s', $week, $unitSlug);
                    $ctv = TransferDocument::query()->firstOrCreate(
                        ['document_no' => $ctvDocNo],
                        [
                            'type' => TransferDocument::TYPE_CTV,
                            'from_location' => $unit,
                            'to_location' => $toUnit,
                            'date' => $weekDate->toDateString(),
                            'time' => '10:00',
                            'vehicle_no' => 'SBA1234A',
                            'driver_name' => 'Transfer Driver',
                            'driver_tel' => '0123456789',
                            'driver_ic' => '900101-12-1234',
                            'total_cattle' => 1,
                            'total_value' => 0,
                            'status' => TransferDocument::STATUS_COMPLETED,
                            'current_step' => TransferDocument::STEP_COMPLETED,
                            'endorsement_step' => 8,
                            'created_by' => $createdBy,
                        ]
                    );

                    TransferLivestock::query()->firstOrCreate(
                        [
                            'transfer_document_id' => $ctv->id,
                            'tag_no' => $ctvTag,
                        ],
                        [
                            'category' => $category,
                            'colour' => 'Brown',
                            'weight' => 250,
                            'condition_good' => true,
                            'condition_not_good' => false,
                            'remarks' => 'Workflow completed transfer',
                            'purpose' => 'Relocation',
                            'yard' => 'Main',
                            'value' => 0,
                            'sort_order' => 0,
                        ]
                    );

                    Cattle::query()->where('tag_no', $ctvTag)->update(['location_block' => $toUnit]);

                    $saleTag = sprintf('WRS-W%d-%s', $week, $unitSlug);
                    Cattle::query()->firstOrCreate(
                        ['tag_no' => $saleTag],
                        [
                            'category' => $category,
                            'breed' => 'Brahman',
                            'coat_colour' => 'Red',
                            'birth_date' => '2025-01-10',
                            'gender' => in_array($code, ['B/B', 'W/B', 'M/C'], true) ? 'Male' : 'Female',
                            'receival_weight' => 240,
                            'general_condition' => 'Good',
                            'ownership' => 'Company',
                            'herd' => 'Main Herd',
                            'location_block' => $unit,
                            'location_phase' => 'Phase 1',
                            'status' => 'Active',
                        ]
                    );

                    $sivDocNo = sprintf('SIV-W%d-%s', $week, $unitSlug);
                    $siv = TransferDocument::query()->firstOrCreate(
                        ['document_no' => $sivDocNo],
                        [
                            'type' => TransferDocument::TYPE_SIV,
                            'from_location' => $unit,
                            'to_location' => null,
                            'date' => $weekDate->toDateString(),
                            'time' => '11:00',
                            'vehicle_no' => 'SBA5678B',
                            'driver_name' => 'Buyer Driver',
                            'driver_tel' => '0198887777',
                            'driver_ic' => '880202-10-3333',
                            'address' => 'Tawau',
                            'siv_no' => sprintf('SIV-%d-%s', $week, $unitSlug),
                            'receipt_no' => sprintf('RCP-%d-%s', $week, $unitSlug),
                            'customer_name' => 'Walk-in Customer',
                            'total_cattle' => 1,
                            'total_value' => 3500,
                            'status' => TransferDocument::STATUS_COMPLETED,
                            'current_step' => TransferDocument::STEP_COMPLETED,
                            'endorsement_step' => 4,
                            'created_by' => $createdBy,
                        ]
                    );

                    TransferLivestock::query()->firstOrCreate(
                        [
                            'transfer_document_id' => $siv->id,
                            'tag_no' => $saleTag,
                        ],
                        [
                            'category' => $category,
                            'colour' => 'Red',
                            'weight' => 280,
                            'condition_good' => true,
                            'condition_not_good' => false,
                            'remarks' => 'Workflow completed sale',
                            'purpose' => 'Sale',
                            'yard' => 'Sales',
                            'unit_cost' => 3500,
                            'value' => 3500,
                            'sort_order' => 0,
                        ]
                    );

                    Cattle::query()->where('tag_no', $saleTag)->update(['status' => 'Sold']);

                    $receivalTag = sprintf('WRR-W%d-%s', $week, $unitSlug);
                    Cattle::query()->firstOrCreate(
                        ['tag_no' => $receivalTag],
                        [
                            'category' => $category,
                            'breed' => 'Brahman',
                            'coat_colour' => 'Grey',
                            'birth_date' => '2025-01-05',
                            'gender' => in_array($code, ['B/B', 'W/B', 'M/C'], true) ? 'Male' : 'Female',
                            'receival_weight' => 235,
                            'general_condition' => 'Good',
                            'ownership' => 'Company',
                            'herd' => 'Main Herd',
                            'location_block' => $unit,
                            'location_phase' => 'Phase 1',
                            'status' => 'Active',
                        ]
                    );

                    $receivalDocNo = sprintf('RCV-W%d-%s', $week, $unitSlug);
                    $receival = TransferDocument::query()->firstOrCreate(
                        ['document_no' => $receivalDocNo],
                        [
                            'type' => TransferDocument::TYPE_RECEIVAL,
                            'from_location' => $unit,
                            'to_location' => $toUnit,
                            'date' => $weekDate->toDateString(),
                            'time' => '12:00',
                            'vehicle_no' => 'SBA2468C',
                            'driver_name' => 'Transporter',
                            'driver_tel' => '0172223333',
                            'driver_ic' => '870707-07-7777',
                            'customer_name' => 'Internal Receival',
                            'total_cattle' => 1,
                            'total_value' => 0,
                            'status' => TransferDocument::STATUS_COMPLETED,
                            'current_step' => TransferDocument::STEP_COMPLETED,
                            'endorsement_step' => 3,
                            'created_by' => $createdBy,
                        ]
                    );

                    TransferLivestock::query()->firstOrCreate(
                        [
                            'transfer_document_id' => $receival->id,
                            'tag_no' => $receivalTag,
                        ],
                        [
                            'category' => $category,
                            'colour' => 'Grey',
                            'weight' => 240,
                            'condition_good' => true,
                            'condition_not_good' => false,
                            'remarks' => 'Workflow completed receival',
                            'purpose' => 'Receival',
                            'yard' => 'Receival Yard',
                            'value' => 0,
                            'sort_order' => 0,
                        ]
                    );

                    Cattle::query()->where('tag_no', $receivalTag)->update(['location_block' => $toUnit]);
                }
            }
        });
    }
}
