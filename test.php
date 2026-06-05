<?php
class A {
    const WORKFLOW_STEPS = [
        ['label' => 'Prepared by'],
        ['label' => 'Verified by']
    ];
    public function test() {
        $stepIndex = 0;
        $nextStepIndex = 1;
        $msg = "The workflow step '{self::WORKFLOW_STEPS[$stepIndex]['label']}' has been completed. Please proceed with '{self::WORKFLOW_STEPS[$nextStepIndex]['label']}'.";
        echo $msg;
    }
}
(new A())->test();
