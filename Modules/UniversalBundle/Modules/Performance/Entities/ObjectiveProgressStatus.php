<?php

namespace Modules\Performance\Entities;

use App\Models\BaseModel;

class ObjectiveProgressStatus extends BaseModel
{

    public function objective()
    {
        return $this->belongsTo(Objective::class, 'objective_id');
    }

}
