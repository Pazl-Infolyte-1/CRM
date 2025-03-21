<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Gender extends Component
{

    public $gender;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct($gender)
    {
        $this->gender = $gender;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return View|Closure|string
     */
    public function render()
    {
        return view('components.gender');
    }

}
