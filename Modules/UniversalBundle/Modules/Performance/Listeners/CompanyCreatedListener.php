<?php

namespace Modules\Performance\Listeners;

use Modules\Performance\Entities\GoalType;
use Modules\Performance\Entities\KeyResultsMetrics;
use Modules\Performance\Entities\PerformanceSetting;

class CompanyCreatedListener
{

    /**
     * Handle the event.
     *
     * @param object $event
     * @return void
     */
    public function handle($event)
    {
        $company = $event->company;
        PerformanceSetting::addModuleSetting($company);
        $this->goalTypeData($company);
        $this->keyResultsData($company);
        $this->meetingSeeting($company);
    }

    /**
     * Insert default goal types for the created company
     *
     * @param \App\Models\Company $company
     * @return void
     */
    public function goalTypeData($company)
    {
        $data = GoalType::defaultGoalTypes($company);

        GoalType::insert($data);
    }

    /**
     * Insert default key result metrics for the created company
     *
     * @param \App\Models\Company $company
     * @return void
     */
    public function keyResultsData($company)
    {
        $data = KeyResultsMetrics::defaultKeyResultsMetrics($company);

        KeyResultsMetrics::insert($data);
    }

    /**
     * Insert default key result metrics for the created company
     *
     * @param \App\Models\Company $company
     * @return void
     */
    public function meetingSeeting($company)
    {
        $meetingSeeting = PerformanceSetting::firstOrCreate(['company_id' => $company->id]);

        if ($meetingSeeting) {
            $meetingSeeting->create_meeting_manager = 1;
            $meetingSeeting->create_meeting_participant = 1;
            $meetingSeeting->view_meeting_manager = 1;
            $meetingSeeting->view_meeting_participant = 1;
            $meetingSeeting->save();
        }
    }
}
