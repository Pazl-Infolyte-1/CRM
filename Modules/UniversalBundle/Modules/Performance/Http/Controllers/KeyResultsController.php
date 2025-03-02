<?php

namespace Modules\Performance\Http\Controllers;

use App\Helper\Reply;
use App\Http\Controllers\AccountBaseController;
use App\Models\EmployeeDetails;
use Modules\Performance\Entities\GoalType;
use Modules\Performance\Entities\KeyResults;
use Modules\Performance\Entities\KeyResultsMetrics;
use Modules\Performance\Entities\Objective;
use Modules\Performance\Entities\PerformanceSetting;
use Modules\Performance\Http\Requests\CreateKeyResultsRequest;

class KeyResultsController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'performance::app.keyResults';

        $this->middleware(function ($request, $next) {
            abort_403(!in_array(PerformanceSetting::MODULE_NAME, $this->user->modules));
            return $next($request);
        });
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        abort(404);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->pageTitle = __('performance::app.addKeyResults');
        $objectiveId = request()->objectiveId;
        $this->objectiveId  = [];

        if ($objectiveId) {
            $this->objectiveId = Objective::findOrFail($objectiveId);
        }

        $this->metrics = KeyResultsMetrics::all();
        $this->objectives = Objective::all();

        $this->meetingId = request()->meetingId;

        $this->currentUrl = ($this->meetingId && $this->meetingId != 'null' && $this->meetingId != null)
            ? route('meetings.show', ['meeting' => $this->meetingId]) . '?view=action'
            : (request()->currentUrl ?: route('objectives.index'));

        if (request()->ajax()) {
            $html = view('performance::key-results.ajax.create', $this->data)->render();
            return Reply::dataOnly(['status' => 'success', 'html' => $html, 'title' => $this->pageTitle]);
        }

        $this->view = 'performance::key-results.ajax.create';

        return view('performance::key-results.create', $this->data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateKeyResultsRequest $request)
    {
        $keyResult = new KeyResults();
        $keyResult->objective_id = $request->objective_id;
        $keyResult->title = $request->title;
        $keyResult->description = $request->description;
        $keyResult->metrics_id = $request->metrics_id;
        $keyResult->target_value = $request->target_value;
        $keyResult->current_value = $request->current_value;
        $keyResult->original_current_value = $request->current_value;

        if ($request->current_value != 0 && $request->target_value != 0 && $request->current_value == $request->target_value) {
            $percentage = round(($request->current_value / $request->target_value) * 100, 2);
            $keyResult->key_percentage = $percentage;

        }
        else {
            $keyResult->key_percentage = 0.00;
        }

        $keyResult->save();

        return Reply::successWithData(__('messages.recordSaved'), ['redirectUrl' => $request->currentUrl]);
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        $this->pageTitle = __('performance::app.keyResult');
        $this->view = 'performance::key-results.ajax.show';
        $this->keyResult = KeyResults::findOrFail($id);
        $this->managePermission = $this->checkManageAccess($this->keyResult->objective_id);

        if (request()->ajax()) {
            return $this->returnAjax($this->view);
        }

        return view('performance::key-results.create', $this->data);
    }

    protected function checkManageAccess($id)
    {
        $objective = Objective::with('owners')->findOrFail($id);
        $ownerIds = $objective->owners->pluck('id')->toArray();
        $goal = GoalType::find($objective->goal_type);

        $managerIds = EmployeeDetails::whereNotNull('reporting_to')
            ->whereIn('user_id', $ownerIds)
            ->pluck('reporting_to')
            ->toArray();

        $currentUserRoleIds = user()->roles()->pluck('id')->toArray();
        $manageByRoles = json_decode($goal->manage_by_roles, true) ?? [];

        return (user()->hasRole('admin') ||
            $objective->created_by == user()->id ||
            ($goal && $goal->manage_by_owner == 1 && in_array(user()->id, $ownerIds)) ||
            ($goal && $goal->manage_by_manager == 1 && in_array(user()->id, $managerIds)) ||
            (!empty($manageByRoles) && array_intersect($currentUserRoleIds, $manageByRoles)));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $this->pageTitle = __('performance::app.editKeyResults');

        $this->metrics = KeyResultsMetrics::all();
        $this->keyResult = KeyResults::findOrFail($id);
        $this->objectives = Objective::all();
        $this->currentUrl = request()->currentUrl ? request()->currentUrl : route('objectives.index');

        if (request()->ajax()) {
            $html = view('performance::key-results.ajax.edit', $this->data)->render();
            return Reply::dataOnly(['status' => 'success', 'html' => $html, 'title' => $this->pageTitle]);
        }

        $this->view = 'performance::key-results.ajax.edit';

        return view('performance::key-results.create', $this->data);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CreateKeyResultsRequest $request, $id)
    {
        $keyResult = KeyResults::findOrFail($id);
        $keyResult->objective_id = $request->objective_id;
        $keyResult->title = $request->title;
        $keyResult->description = $request->description;
        $keyResult->metrics_id = $request->metrics_id;
        $keyResult->target_value = $request->target_value;

        $latestCheckIn = $keyResult->checkIns()->latest()->first();

        if ($latestCheckIn) {
            $keyResult->current_value = $latestCheckIn->current_value;
        }
        else {
            $keyResult->current_value = $request->current_value;
        }

        $keyResult->original_current_value = $request->current_value;
        $keyResult->save();

        return Reply::successWithData(__('messages.updateSuccess'), ['redirectUrl' => $request->currentUrl]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $keyResult = KeyResults::findOrFail($id);

        if ($keyResult) {
            $keyResult->checkIns()->delete();
            $keyResult->delete();

            return Reply::success(__('messages.deleteSuccess'));
        }

        return Reply::error(__('performance::messages.keyResultsNotFound'));
    }

    public function showDescription($id)
    {
        $this->objective = KeyResults::findOrFail($id);
        return view('performance::objectives.ajax.show-description', $this->data);
    }

}
