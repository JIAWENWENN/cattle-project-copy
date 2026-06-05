const fs = require('fs');
let content = fs.readFileSync('app/Http/Controllers/DailyOperationController.php', 'utf8');
content = content.replace(/\r\n/g, '\n');

const replacements = [
    [
        `            'workflowDocuments' => $this->getWorkflowDocuments($selectedLadang, (int) $selectedMonth, (int) $selectedYear),
            'workflowCurrentStep' => $this->getWorkflowCurrentStep($selectedLadang, (int) $selectedMonth, (int) $selectedYear),
            'workflowIsCompleted' => $this->isWorkflowCompleted($selectedLadang, (int) $selectedMonth, (int) $selectedYear),
            'workflowCompletedAt' => $this->getWorkflowCompletedAt($selectedLadang, (int) $selectedMonth, (int) $selectedYear),`,
        `            'workflowDocuments' => $this->getWorkflowDocuments($selectedLadang, (int) $selectedMonth, (int) $selectedYear, $request->get('week', 'all')),
            'workflowCurrentStep' => $this->getWorkflowCurrentStep($selectedLadang, (int) $selectedMonth, (int) $selectedYear, $request->get('week', 'all')),
            'workflowIsCompleted' => $this->isWorkflowCompleted($selectedLadang, (int) $selectedMonth, (int) $selectedYear, $request->get('week', 'all')),
            'workflowCompletedAt' => $this->getWorkflowCompletedAt($selectedLadang, (int) $selectedMonth, (int) $selectedYear, $request->get('week', 'all')),`
    ],
    [
        `'entries.*.remark' => ['nullable', 'string'],
        ]);`,
        `'entries.*.remark' => ['nullable', 'string'],
            'week' => ['nullable', 'string'],
        ]);`
    ],
    [
        `if ($this->isWorkflowCompleted($validated['ladang'] ?? '', (int) $validated['month'], (int) $validated['year'])) {`,
        `if ($this->isWorkflowCompleted($validated['ladang'] ?? '', (int) $validated['month'], (int) $validated['year'], $validated['week'] ?? 'all')) {`
    ],
    [
        `$this->resetWorkflow($validated['ladang'] ?? '', (int) $validated['month'], (int) $validated['year']);`,
        `$this->resetWorkflow($validated['ladang'] ?? '', (int) $validated['month'], (int) $validated['year'], $validated['week'] ?? 'all');`
    ],
    [
        `'document' => ['required', 'file', 'mimes:pdf', 'max:10240'],`,
        `'week' => ['nullable', 'string'],
            'document' => ['required', 'file', 'mimes:pdf', 'max:10240'],`
    ],
    [
        `$dir = $this->getWorkflowDirectory($validated['ladang'] ?? '', (int) $validated['month'], (int) $validated['year']);
        $stepNumber = $stepIndex + 1;
        $filename = 'step_' . $stepNumber . '.pdf';

        $state = $this->getWorkflowState($validated['ladang'] ?? '', (int) $validated['month'], (int) $validated['year']);`,
        `$dir = $this->getWorkflowDirectory($validated['ladang'] ?? '', (int) $validated['month'], (int) $validated['year'], $validated['week'] ?? 'all');
        $stepNumber = $stepIndex + 1;
        $filename = 'step_' . $stepNumber . '.pdf';

        $state = $this->getWorkflowState($validated['ladang'] ?? '', (int) $validated['month'], (int) $validated['year'], $validated['week'] ?? 'all');`
    ],
    [
        `$this->saveWorkflowState(
            $validated['ladang'] ?? '',
            (int) $validated['month'],
            (int) $validated['year'],
            $state
        );`,
        `$this->saveWorkflowState(
            $validated['ladang'] ?? '',
            (int) $validated['month'],
            (int) $validated['year'],
            $validated['week'] ?? 'all',
            $state
        );`
    ],
    [
        `$this->notifyAdminsDomlWorkflowReadyForCompletion(
                $validated['ladang'] ?? '',
                (int) $validated['month'],
                (int) $validated['year'],
                (int) $user->id
            );`,
        `$this->notifyAdminsDomlWorkflowReadyForCompletion(
                $validated['ladang'] ?? '',
                (int) $validated['month'],
                (int) $validated['year'],
                $validated['week'] ?? 'all',
                (int) $user->id
            );`
    ],
    [
        `$ladang = $request->get('ladang', '');
        $month = (int) $request->get('month', now()->month);
        $year = (int) $request->get('year', now()->year);

        $state = $this->getWorkflowState($ladang, $month, $year);
        $document = $state['documents'][(string) $stepIndex] ?? null;

        $dir = $this->getWorkflowDirectory($ladang, $month, $year);`,
        `$ladang = $request->get('ladang', '');
        $month = (int) $request->get('month', now()->month);
        $year = (int) $request->get('year', now()->year);
        $week = $request->get('week', 'all');

        $state = $this->getWorkflowState($ladang, $month, $year, $week);
        $document = $state['documents'][(string) $stepIndex] ?? null;

        $dir = $this->getWorkflowDirectory($ladang, $month, $year, $week);`
    ],
    [
        `'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $state = $this->getWorkflowState($validated['ladang'] ?? '', (int) $validated['month'], (int) $validated['year']);`,
        `'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'week' => ['nullable', 'string'],
        ]);

        $state = $this->getWorkflowState($validated['ladang'] ?? '', (int) $validated['month'], (int) $validated['year'], $validated['week'] ?? 'all');`
    ],
    [
        `$this->saveWorkflowState($validated['ladang'] ?? '', (int) $validated['month'], (int) $validated['year'], array_merge($state, [`,
        `$this->saveWorkflowState($validated['ladang'] ?? '', (int) $validated['month'], (int) $validated['year'], $validated['week'] ?? 'all', array_merge($state, [`
    ],
    [
        `$this->saveWorkflowState($validated['ladang'] ?? '', (int) $validated['month'], (int) $validated['year'], $state);`,
        `$this->saveWorkflowState($validated['ladang'] ?? '', (int) $validated['month'], (int) $validated['year'], $validated['week'] ?? 'all', $state);`
    ],
    [
        `private function getWorkflowDocuments(?string $estateName, int $month, int $year): array
    {
        $state = $this->getWorkflowState($estateName, $month, $year);`,
        `private function getWorkflowDocuments(?string $estateName, int $month, int $year, string $week = 'all'): array
    {
        $state = $this->getWorkflowState($estateName, $month, $year, $week);`
    ],
    [
        `private function getWorkflowCurrentStep(?string $estateName, int $month, int $year): int
    {
        $state = $this->getWorkflowState($estateName, $month, $year);`,
        `private function getWorkflowCurrentStep(?string $estateName, int $month, int $year, string $week = 'all'): int
    {
        $state = $this->getWorkflowState($estateName, $month, $year, $week);`
    ],
    [
        `private function getWorkflowState(?string $estateName, int $month, int $year): array
    {
        $dir = $this->getWorkflowDirectory($estateName, $month, $year);`,
        `private function getWorkflowState(?string $estateName, int $month, int $year, string $week = 'all'): array
    {
        $dir = $this->getWorkflowDirectory($estateName, $month, $year, $week);`
    ],
    [
        `private function saveWorkflowState(?string $estateName, int $month, int $year, array $state): void
    {
        $dir = $this->getWorkflowDirectory($estateName, $month, $year);`,
        `private function saveWorkflowState(?string $estateName, int $month, int $year, string $week, array $state): void
    {
        $dir = $this->getWorkflowDirectory($estateName, $month, $year, $week);`
    ],
    [
        `private function isWorkflowCompleted(?string $estateName, int $month, int $year): bool
    {
        $state = $this->getWorkflowState($estateName, $month, $year);`,
        `private function isWorkflowCompleted(?string $estateName, int $month, int $year, string $week = 'all'): bool
    {
        $state = $this->getWorkflowState($estateName, $month, $year, $week);`
    ],
    [
        `private function getWorkflowCompletedAt(?string $estateName, int $month, int $year): ?string
    {
        $state = $this->getWorkflowState($estateName, $month, $year);`,
        `private function getWorkflowCompletedAt(?string $estateName, int $month, int $year, string $week = 'all'): ?string
    {
        $state = $this->getWorkflowState($estateName, $month, $year, $week);`
    ],
    [
        `private function resetWorkflow(?string $estateName, int $month, int $year): void
    {
        $this->saveWorkflowState($estateName, $month, $year, [`,
        `private function resetWorkflow(?string $estateName, int $month, int $year, string $week = 'all'): void
    {
        $this->saveWorkflowState($estateName, $month, $year, $week, [`
    ],
    [
        `private function notifyAdminsDomlWorkflowReadyForCompletion(?string $estateName, int $month, int $year, int $createdBy): void
    {
        $estateLabel = $estateName ?: 'All Estates';
        $message = "All DOML workflow steps for {$estateLabel} ({$month}/{$year}) have been uploaded. Please review and mark as complete.";`,
        `private function notifyAdminsDomlWorkflowReadyForCompletion(?string $estateName, int $month, int $year, string $week, int $createdBy): void
    {
        $estateLabel = $estateName ?: 'All Estates';
        $message = "All DOML workflow steps for {$estateLabel} ({$month}/{$year} Week {$week}) have been uploaded. Please review and mark as complete.";`
    ],
    [
        `private function getWorkflowDirectory(?string $estateName, int $month, int $year): string
    {
        $estate = trim((string) $estateName);
        $estate = $estate === '' ? 'all-estates' : strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $estate));
        $estate = trim($estate, '-');
        if ($estate === '') {
            $estate = 'all-estates';
        }

        return 'doml-workflow/' . $estate . '/' . $year . '/' . str_pad($month, 2, '0', STR_PAD_LEFT);
    }`,
        `private function getWorkflowDirectory(?string $estateName, int $month, int $year, string $week = 'all'): string
    {
        $estate = trim((string) $estateName);
        $estate = $estate === '' ? 'all-estates' : strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $estate));
        $estate = trim($estate, '-');
        if ($estate === '') {
            $estate = 'all-estates';
        }

        $base = 'doml-workflow/' . $estate . '/' . $year . '/' . str_pad($month, 2, '0', STR_PAD_LEFT);
        if ($week !== 'all') {
            $base .= '/week-' . $week;
        }

        return $base;
    }`
    ]
];

replacements.forEach(rep => {
    let searchFor = rep[0].replace(/\r\n/g, '\n');
    let replaceWith = rep[1].replace(/\r\n/g, '\n');
    if (!content.includes(searchFor)) {
        console.log("Failed to find:\\n" + searchFor.substring(0, 50) + "...");
    }
    content = content.replace(searchFor, replaceWith);
});

fs.writeFileSync('app/Http/Controllers/DailyOperationController.php', content);
console.log('Done!');
