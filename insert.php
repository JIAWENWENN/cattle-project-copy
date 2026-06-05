<?php
$content = file_get_contents('app/Http/Controllers/WeeklyCattleReturnController.php');
$search = 'public function workflow(Request $request)';
$methodCode = <<<EOT
    public function downloadEndorsementForm(Request \$request)
    {
        [\$dateFrom, \$dateTo, \$filters, \$monthLabel, \$submissionLabel] = \$this->resolveDateRange(\$request);

        \$operatingUnits = \$this->getOperatingUnits();
        \$selectedUnit = trim((string) \$request->get('unit', ''));

        if (\$selectedUnit !== '' && !in_array(\$selectedUnit, \$operatingUnits, true)) {
            \$selectedUnit = '';
        }

        \$unitsToProcess = \$selectedUnit !== '' ? [\$selectedUnit] : \$operatingUnits;
        \$statsByUnit = [];
        foreach (\$unitsToProcess as \$unitName) {
            \$statsByUnit[\$unitName] = \$this->emptyStatsSet();
        }

        \$deceasedBeforeByCattleId = \App\Models\MortalityCase::query()
            ->whereNotNull('cattle_id')
            ->whereDate('death_date', '<', \$dateFrom->toDateString())
            ->pluck('cattle_id')
            ->map(fn (\$id) => (int) \$id)
            ->flip()
            ->toArray();

        \$soldBeforeTagByUnit = [];
        \$soldBeforeDocs = \App\Models\TransferDocument::query()
            ->where('type', \App\Models\TransferDocument::TYPE_SIV)
            ->whereDate('date', '<', \$dateFrom->toDateString())
            ->when(!empty(\$unitsToProcess), fn (\$q) => \$q->whereIn('from_location', \$unitsToProcess))
            ->with(['livestock:id,transfer_document_id,tag_no'])
            ->get(['id', 'from_location']);

        foreach (\$soldBeforeDocs as \$doc) {
            \$unit = (string) \$doc->from_location;
            if (!isset(\$soldBeforeTagByUnit[\$unit])) {
                \$soldBeforeTagByUnit[\$unit] = [];
            }
            foreach (\$doc->livestock as \$item) {
                \$tag = trim((string) \$item->tag_no);
                if (\$tag !== '') {
                    \$soldBeforeTagByUnit[\$unit][\$tag] = true;
                }
            }
        }

        \$openingCattle = \App\Models\Cattle::query()
            ->when(!empty(\$unitsToProcess), fn (\$q) => \$q->whereIn('location_block', \$unitsToProcess))
            ->get(['id', 'tag_no', 'category', 'location_block', 'birth_date', 'created_at', 'status']);

        foreach (\$openingCattle as \$cattle) {
            \$unit = trim((string) \$cattle->location_block);
            if (\$unit === '' || !isset(\$statsByUnit[\$unit])) {
                continue;
            }

            \$eventDate = \$cattle->birth_date
                ? \Carbon\Carbon::parse(\$cattle->birth_date)->endOfDay()
                : \Carbon\Carbon::parse(\$cattle->created_at)->endOfDay();

            if (\$eventDate->greaterThan(\$dateFrom->copy()->endOfDay())) {
                continue;
            }

            if (isset(\$deceasedBeforeByCattleId[(int) \$cattle->id])) {
                continue;
            }

            \$tag = trim((string) \$cattle->tag_no);
            if (\$tag !== '' && isset(\$soldBeforeTagByUnit[\$unit][\$tag])) {
                continue;
            }

            \$status = strtolower(trim((string) \$cattle->status));
            if (in_array(\$status, ['deceased', 'sold'], true)) {
                continue;
            }

            \$code = \$this->toCategoryCode(\$cattle->category);
            \$this->increase(\$statsByUnit[\$unit]['opening'], \$code);
        }

        \$calvingRecords = \App\Models\CalvingRecord::query()
            ->whereBetween('calving_date', [\$dateFrom->toDateString(), \$dateTo->toDateString()])
            ->when(!empty(\$unitsToProcess), fn (\$q) => \$q->whereIn('operating_unit', \$unitsToProcess))
            ->get(['operating_unit', 'sex']);

        foreach (\$calvingRecords as \$record) {
            \$unit = trim((string) \$record->operating_unit);
            if (\$unit === '' || !isset(\$statsByUnit[\$unit])) {
                continue;
            }

            \$code = strtoupper(trim((string) \$record->sex)) === 'MC' ? 'M/C' : 'F/C';
            \$this->increase(\$statsByUnit[\$unit]['calving'], \$code);
        }

        \$mortalityCases = \App\Models\MortalityCase::query()
            ->with('cattle:id,category,location_block')
            ->whereIn('status', ['approved', 'completed'])
            ->whereBetween('death_date', [\$dateFrom->toDateString(), \$dateTo->toDateString()])
            ->get(['id', 'cattle_id', 'category', 'location', 'death_date']);

        foreach (\$mortalityCases as \$case) {
            \$unit = trim((string) (\$case->location ?: \$case->cattle?->location_block));
            if (\$unit === '' || !isset(\$statsByUnit[\$unit])) {
                continue;
            }

            \$code = \$this->toCategoryCode(\$case->cattle?->category ?: \$case->category);
            \$this->increase(\$statsByUnit[\$unit]['mortality'], \$code);
        }

        \$saleDocs = \App\Models\TransferDocument::query()
            ->where('type', \App\Models\TransferDocument::TYPE_SIV)
            ->where('status', \App\Models\TransferDocument::STATUS_COMPLETED)
            ->whereBetween('date', [\$dateFrom->toDateString(), \$dateTo->toDateString()])
            ->when(!empty(\$unitsToProcess), fn (\$q) => \$q->whereIn('from_location', \$unitsToProcess))
            ->with('livestock:id,transfer_document_id,category')
            ->get(['id', 'from_location']);

        foreach (\$saleDocs as \$doc) {
            \$unit = trim((string) \$doc->from_location);
            if (\$unit === '' || !isset(\$statsByUnit[\$unit])) {
                continue;
            }

            foreach (\$doc->livestock as \$item) {
                \$code = \$this->toCategoryCode(\$item->category);
                \$this->increase(\$statsByUnit[\$unit]['sale'], \$code);
            }
        }

        \$transferDocs = \App\Models\TransferDocument::query()
            ->whereIn('type', [\App\Models\TransferDocument::TYPE_CTV, \App\Models\TransferDocument::TYPE_RECEIVAL])
            ->where('status', \App\Models\TransferDocument::STATUS_COMPLETED)
            ->whereBetween('date', [\$dateFrom->toDateString(), \$dateTo->toDateString()])
            ->where(function (\$query) use (\$unitsToProcess) {
                if (empty(\$unitsToProcess)) {
                    return;
                }

                \$query->whereIn('from_location', \$unitsToProcess)
                    ->orWhereIn('to_location', \$unitsToProcess);
            })
            ->with('livestock:id,transfer_document_id,category')
            ->get(['id', 'type', 'from_location', 'to_location']);

        foreach (\$transferDocs as \$doc) {
            \$fromUnit = trim((string) \$doc->from_location);
            \$toUnit = trim((string) \$doc->to_location);

            foreach (\$doc->livestock as \$item) {
                \$code = \$this->toCategoryCode(\$item->category);

                if (\$doc->type === \App\Models\TransferDocument::TYPE_CTV && \$fromUnit !== '' && isset(\$statsByUnit[\$fromUnit])) {
                    \$this->increase(\$statsByUnit[\$fromUnit]['transfer_out'], \$code);
                }

                if (\$toUnit !== '' && isset(\$statsByUnit[\$toUnit])) {
                    \$this->increase(\$statsByUnit[\$toUnit]['transfer_in'], \$code);
                }
            }
        }

        \$rows = [];
        foreach (\$statsByUnit as \$unit => \$stats) {
            \$closing = \$this->emptyBucket();
            foreach (self::CATEGORY_CODES as \$code) {
                \$opening = \$stats['opening'][\$code] ?? 0;
                \$calving = \$stats['calving'][\$code] ?? 0;
                \$mortality = \$stats['mortality'][\$code] ?? 0;
                \$sale = \$stats['sale'][\$code] ?? 0;
                \$transferIn = \$stats['transfer_in'][\$code] ?? 0;
                \$transferOut = \$stats['transfer_out'][\$code] ?? 0;
                \$closing[\$code] = max(0, \$opening + \$calving - \$mortality - \$sale + \$transferIn - \$transferOut);
            }

            \$row = [
                'herd' => \$unit,
                'opening' => \$this->withTotal(\$stats['opening']),
                'calving' => \$stats['calving'],
                'mortality' => \$stats['mortality'],
                'sale' => \$stats['sale'],
                'transfer_in' => \$stats['transfer_in'],
                'transfer_out' => \$stats['transfer_out'],
                'closing' => \$this->withTotal(\$closing),
            ];

            if (\$selectedUnit !== '' || \$this->rowHasAnyValue(\$row)) {
                \$rows[] = \$row;
            }
        }

        if (empty(\$rows) && \$selectedUnit !== '') {
            \$rows[] = [
                'herd' => \$selectedUnit,
                'opening' => \$this->withTotal(\$this->emptyBucket()),
                'calving' => \$this->emptyBucket(),
                'mortality' => \$this->emptyBucket(),
                'sale' => \$this->emptyBucket(),
                'transfer_in' => \$this->emptyBucket(),
                'transfer_out' => \$this->emptyBucket(),
                'closing' => \$this->withTotal(\$this->emptyBucket()),
            ];
        }

        \$totals = \$this->buildTotals(\$rows);
        
        \$workflow = null;
        if (\$selectedUnit !== '') {
            \$workflow = \App\Models\WeeklyCattleReturnWorkflow::where('period_from', \$dateFrom->toDateString())
                ->where('period_to', \$dateTo->toDateString())
                ->where('operating_unit', \$selectedUnit)
                ->first();
        }
        
        \$html = view('pdfs.weekly-return-form', [
            'rows' => \$rows,
            'totals' => \$totals,
            'categoryCodes' => self::CATEGORY_CODES,
            'workflow' => \$workflow,
            'monthLabel' => \$monthLabel,
            'submissionLabel' => \$submissionLabel,
        ])->render();

        \$dompdf = new \Dompdf\Dompdf();
        \$dompdf->loadHtml(\$html);
        \$dompdf->setPaper('A4', 'landscape');
        \$dompdf->render();

        \$filename = 'Weekly_Return_Form_' . (\$selectedUnit ?: 'All') . '_' . \$dateFrom->format('Ymd') . '.pdf';
        
        return response(\$dompdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . \$filename . '"');
    }
EOT;
$content = str_replace($search, $methodCode . "\n\n    " . $search, $content);
file_put_contents('app/Http/Controllers/WeeklyCattleReturnController.php', $content);
echo "Success\n";
