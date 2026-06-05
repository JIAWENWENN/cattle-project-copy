<?php
$content = file_get_contents('app/Http/Controllers/DailyOperationController.php');

$replacements = [
    [
        "            'workflowDocuments' => \$this->getWorkflowDocuments(\$selectedLadang, (int) \$selectedMonth, (int) \$selectedYear),\n            'workflowCurrentStep' => \$this->getWorkflowCurrentStep(\$selectedLadang, (int) \$selectedMonth, (int) \$selectedYear),\n            'workflowIsCompleted' => \$this->isWorkflowCompleted(\$selectedLadang, (int) \$selectedMonth, (int) \$selectedYear),\n            'workflowCompletedAt' => \$this->getWorkflowCompletedAt(\$selectedLadang, (int) \$selectedMonth, (int) \$selectedYear),",
        "            'workflowDocuments' => \$this->getWorkflowDocuments(\$selectedLadang, (int) \$selectedMonth, (int) \$selectedYear, \$request->get('week', 'all')),\n            'workflowCurrentStep' => \$this->getWorkflowCurrentStep(\$selectedLadang, (int) \$selectedMonth, (int) \$selectedYear, \$request->get('week', 'all')),\n            'workflowIsCompleted' => \$this->isWorkflowCompleted(\$selectedLadang, (int) \$selectedMonth, (int) \$selectedYear, \$request->get('week', 'all')),\n            'workflowCompletedAt' => \$this->getWorkflowCompletedAt(\$selectedLadang, (int) \$selectedMonth, (int) \$selectedYear, \$request->get('week', 'all')),"
    ],
    [
        "'entries.*.remark' => ['nullable', 'string'],\n        ]);",
        "'entries.*.remark' => ['nullable', 'string'],\n            'week' => ['nullable', 'string'],\n        ]);"
    ],
    [
        "if (\$this->isWorkflowCompleted(\$validated['ladang'] ?? '', (int) \$validated['month'], (int) \$validated['year'])) {",
        "if (\$this->isWorkflowCompleted(\$validated['ladang'] ?? '', (int) \$validated['month'], (int) \$validated['year'], \$validated['week'] ?? 'all')) {"
    ],
    [
        "\$this->resetWorkflow(\$validated['ladang'] ?? '', (int) \$validated['month'], (int) \$validated['year']);",
        "\$this->resetWorkflow(\$validated['ladang'] ?? '', (int) \$validated['month'], (int) \$validated['year'], \$validated['week'] ?? 'all');"
    ],
    [
        "'document' => ['required', 'file', 'mimes:pdf', 'max:10240'],",
        "'week' => ['nullable', 'string'],\n            'document' => ['required', 'file', 'mimes:pdf', 'max:10240'],"
    ],
    [
        "\$dir = \$this->getWorkflowDirectory(\$validated['ladang'] ?? '', (int) \$validated['month'], (int) \$validated['year']);\n        \$stepNumber = \$stepIndex + 1;\n        \$filename = 'step_' . \$stepNumber . '.pdf';\n\n        \$state = \$this->getWorkflowState(\$validated['ladang'] ?? '', (int) \$validated['month'], (int) \$validated['year']);",
        "\$dir = \$this->getWorkflowDirectory(\$validated['ladang'] ?? '', (int) \$validated['month'], (int) \$validated['year'], \$validated['week'] ?? 'all');\n        \$stepNumber = \$stepIndex + 1;\n        \$filename = 'step_' . \$stepNumber . '.pdf';\n\n        \$state = \$this->getWorkflowState(\$validated['ladang'] ?? '', (int) \$validated['month'], (int) \$validated['year'], \$validated['week'] ?? 'all');"
    ],
    [
        "\$this->saveWorkflowState(\n            \$validated['ladang'] ?? '',\n            (int) \$validated['month'],\n            (int) \$validated['year'],\n            \$state\n        );",
        "\$this->saveWorkflowState(\n            \$validated['ladang'] ?? '',\n            (int) \$validated['month'],\n            (int) \$validated['year'],\n            \$validated['week'] ?? 'all',\n            \$state\n        );"
    ],
    [
        "\$this->notifyAdminsDomlWorkflowReadyForCompletion(\n                \$validated['ladang'] ?? '',\n                (int) \$validated['month'],\n                (int) \$validated['year'],\n                (int) \$user->id\n            );",
        "\$this->notifyAdminsDomlWorkflowReadyForCompletion(\n                \$validated['ladang'] ?? '',\n                (int) \$validated['month'],\n                (int) \$validated['year'],\n                \$validated['week'] ?? 'all',\n                (int) \$user->id\n            );"
    ],
    [
        "\$ladang = \$request->get('ladang', '');\n        \$month = (int) \$request->get('month', now()->month);\n        \$year = (int) \$request->get('year', now()->year);\n\n        \$state = \$this->getWorkflowState(\$ladang, \$month, \$year);\n        \$document = \$state['documents'][(string) \$stepIndex] ?? null;\n\n        \$dir = \$this->getWorkflowDirectory(\$ladang, \$month, \$year);",
        "\$ladang = \$request->get('ladang', '');\n        \$month = (int) \$request->get('month', now()->month);\n        \$year = (int) \$request->get('year', now()->year);\n        \$week = \$request->get('week', 'all');\n\n        \$state = \$this->getWorkflowState(\$ladang, \$month, \$year, \$week);\n        \$document = \$state['documents'][(string) \$stepIndex] ?? null;\n\n        \$dir = \$this->getWorkflowDirectory(\$ladang, \$month, \$year, \$week);"
    ],
    [
        "'year' => ['required', 'integer', 'min:2000', 'max:2100'],\n        ]);\n\n        \$state = \$this->getWorkflowState(\$validated['ladang'] ?? '', (int) \$validated['month'], (int) \$validated['year']);",
        "'year' => ['required', 'integer', 'min:2000', 'max:2100'],\n            'week' => ['nullable', 'string'],\n        ]);\n\n        \$state = \$this->getWorkflowState(\$validated['ladang'] ?? '', (int) \$validated['month'], (int) \$validated['year'], \$validated['week'] ?? 'all');"
    ],
    [
        "\$this->saveWorkflowState(\$validated['ladang'] ?? '', (int) \$validated['month'], (int) \$validated['year'], array_merge(\$state, [",
        "\$this->saveWorkflowState(\$validated['ladang'] ?? '', (int) \$validated['month'], (int) \$validated['year'], \$validated['week'] ?? 'all', array_merge(\$state, ["
    ],
    [
        "\$this->saveWorkflowState(\$validated['ladang'] ?? '', (int) \$validated['month'], (int) \$validated['year'], \$state);",
        "\$this->saveWorkflowState(\$validated['ladang'] ?? '', (int) \$validated['month'], (int) \$validated['year'], \$validated['week'] ?? 'all', \$state);"
    ],
    [
        "private function getWorkflowDocuments(?string \$estateName, int \$month, int \$year): array\n    {\n        \$state = \$this->getWorkflowState(\$estateName, \$month, \$year);",
        "private function getWorkflowDocuments(?string \$estateName, int \$month, int \$year, string \$week = 'all'): array\n    {\n        \$state = \$this->getWorkflowState(\$estateName, \$month, \$year, \$week);"
    ],
    [
        "private function getWorkflowCurrentStep(?string \$estateName, int \$month, int \$year): int\n    {\n        \$state = \$this->getWorkflowState(\$estateName, \$month, \$year);",
        "private function getWorkflowCurrentStep(?string \$estateName, int \$month, int \$year, string \$week = 'all'): int\n    {\n        \$state = \$this->getWorkflowState(\$estateName, \$month, \$year, \$week);"
    ],
    [
        "private function getWorkflowState(?string \$estateName, int \$month, int \$year): array\n    {\n        \$dir = \$this->getWorkflowDirectory(\$estateName, \$month, \$year);",
        "private function getWorkflowState(?string \$estateName, int \$month, int \$year, string \$week = 'all'): array\n    {\n        \$dir = \$this->getWorkflowDirectory(\$estateName, \$month, \$year, \$week);"
    ],
    [
        "private function saveWorkflowState(?string \$estateName, int \$month, int \$year, array \$state): void\n    {\n        \$dir = \$this->getWorkflowDirectory(\$estateName, \$month, \$year);",
        "private function saveWorkflowState(?string \$estateName, int \$month, int \$year, string \$week, array \$state): void\n    {\n        \$dir = \$this->getWorkflowDirectory(\$estateName, \$month, \$year, \$week);"
    ],
    [
        "private function isWorkflowCompleted(?string \$estateName, int \$month, int \$year): bool\n    {\n        \$state = \$this->getWorkflowState(\$estateName, \$month, \$year);",
        "private function isWorkflowCompleted(?string \$estateName, int \$month, int \$year, string \$week = 'all'): bool\n    {\n        \$state = \$this->getWorkflowState(\$estateName, \$month, \$year, \$week);"
    ],
    [
        "private function getWorkflowCompletedAt(?string \$estateName, int \$month, int \$year): ?string\n    {\n        \$state = \$this->getWorkflowState(\$estateName, \$month, \$year);",
        "private function getWorkflowCompletedAt(?string \$estateName, int \$month, int \$year, string \$week = 'all'): ?string\n    {\n        \$state = \$this->getWorkflowState(\$estateName, \$month, \$year, \$week);"
    ],
    [
        "private function resetWorkflow(?string \$estateName, int \$month, int \$year): void\n    {\n        \$this->saveWorkflowState(\$estateName, \$month, \$year, [",
        "private function resetWorkflow(?string \$estateName, int \$month, int \$year, string \$week = 'all'): void\n    {\n        \$this->saveWorkflowState(\$estateName, \$month, \$year, \$week, ["
    ],
    [
        "private function notifyAdminsDomlWorkflowReadyForCompletion(?string \$estateName, int \$month, int \$year, int \$createdBy): void\n    {\n        \$estateLabel = \$estateName ?: 'All Estates';\n        \$message = \"All DOML workflow steps for {\$estateLabel} ({\$month}/{\$year}) have been uploaded. Please review and mark as complete.\";",
        "private function notifyAdminsDomlWorkflowReadyForCompletion(?string \$estateName, int \$month, int \$year, string \$week, int \$createdBy): void\n    {\n        \$estateLabel = \$estateName ?: 'All Estates';\n        \$message = \"All DOML workflow steps for {\$estateLabel} ({\$month}/{\$year} Week {\$week}) have been uploaded. Please review and mark as complete.\";"
    ],
    [
        "private function getWorkflowDirectory(?string \$estateName, int \$month, int \$year): string\n    {\n        \$estate = trim((string) \$estateName);\n        \$estate = \$estate === '' ? 'all-estates' : strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', \$estate));\n        \$estate = trim(\$estate, '-');\n        if (\$estate === '') {\n            \$estate = 'all-estates';\n        }\n\n        return 'doml-workflow/' . \$estate . '/' . \$year . '/' . str_pad(\$month, 2, '0', STR_PAD_LEFT);\n    }",
        "private function getWorkflowDirectory(?string \$estateName, int \$month, int \$year, string \$week = 'all'): string\n    {\n        \$estate = trim((string) \$estateName);\n        \$estate = \$estate === '' ? 'all-estates' : strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', \$estate));\n        \$estate = trim(\$estate, '-');\n        if (\$estate === '') {\n            \$estate = 'all-estates';\n        }\n\n        \$base = 'doml-workflow/' . \$estate . '/' . \$year . '/' . str_pad(\$month, 2, '0', STR_PAD_LEFT);\n        if (\$week !== 'all') {\n            \$base .= '/week-' . \$week;\n        }\n\n        return \$base;\n    }"
    ]
];

foreach (\$replacements as \$rep) {
    if (strpos(\$content, \$rep[0]) === false) {
        echo "Failed to find: \n" . substr(\$rep[0], 0, 50) . "...\n";
    }
    \$content = str_replace(\$rep[0], \$rep[1], \$content);
}

file_put_contents('app/Http/Controllers/DailyOperationController.php', \$content);
echo "Done.\n";