<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $categoryMap = [
        'BREEDER' => 'BC',
        'BREEDER BULL' => 'BB',
        'HEIFER (H)' => 'H',
        'FEMALECALF' => 'FC',
        'FEMALE CALF' => 'FC',
        'MALECALF' => 'MC',
        'MALE CALF' => 'MC',
    ];

    public function up(): void
    {
        foreach ($this->categoryMap as $from => $to) {
            DB::table('cattle')
                ->whereRaw("UPPER(REPLACE(TRIM(category), ' ', '')) = ?", [str_replace(' ', '', $from)])
                ->orWhereRaw('UPPER(TRIM(category)) = ?', [$from])
                ->update(['category' => $to]);
        }
    }

    public function down(): void
    {
        //
    }
};
