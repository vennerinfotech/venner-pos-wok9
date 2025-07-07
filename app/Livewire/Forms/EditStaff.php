<?php

namespace App\Livewire\Forms;

use App\Models\Role;
use App\Models\User;
use App\Models\Country;
use Livewire\Component;
use Jantinnerezo\LivewireAlert\LivewireAlert;

class EditStaff extends Component
{
    use LivewireAlert;

    public $member;
    public $roles;
    public $memberName;
    public $memberEmail;
    public $memberRole;
    public $restaurantPhoneNumber;
    public $restaurantPhoneCode;

    public function mount()
    {
        $this->roles = Role::where('display_name', '<>', 'Super Admin')->get();
        $this->memberName = $this->member->name;
        $this->memberEmail = $this->member->email;
        $this->restaurantPhoneNumber = $this->member->phone_number;
        $this->restaurantPhoneCode = $this->member->phone_code;
        $this->memberRole = $this->member->roles->pluck('name')[0] ?? null;
    }

    public function submitForm()
    {
        $this->validate([
            'memberName' => 'required',
            'memberEmail' => 'required|unique:users,email,' . $this->member->id,
            'restaurantPhoneNumber' => [
                'required',
                'regex:/^[0-9\s]{8,20}$/',
            ],
            'restaurantPhoneCode' => 'required',
        ]);

        $user = User::withoutGlobalScopes()->where('restaurant_id', restaurant()->id)->find($this->member->id);
        $user->name = $this->memberName;
        $user->email = $this->memberEmail;
        $user->phone_number = $this->restaurantPhoneNumber;
        $user->phone_code = $this->restaurantPhoneCode;
        $user->save();

        $user->syncRoles([$this->memberRole]);

        // Reset the value
        $this->memberName = '';
        $this->memberEmail = '';
        $this->memberRole = '';

        $this->dispatch('hideEditStaff');

        $this->alert('success', __('messages.memberUpdated'), [
            'toast' => true,
            'position' => 'top-end',
            'showCancelButton' => false,
            'cancelButtonText' => __('app.close')
        ]);
    }

    public function render()
    {
        $phonecodes = Country::pluck('phonecode')->unique()->filter()->values();

        return view('livewire.forms.edit-staff', [
            'phonecodes' => $phonecodes,
        ]);
    }

}
