<div>
    <form wire:submit="submitForm">
        @csrf
        <div class="space-y-4">

            <div>
                <x-label for="memberName" value="{{ __('modules.staff.name') }}" />
                <x-input id="memberName" class="block mt-1 w-full" type="text" autofocus wire:model='memberName' />
                <x-input-error for="memberName" class="mt-2" />
            </div>

            <div>
                <x-label for="memberEmail" value="{{ __('modules.staff.email') }}" />
                <x-input id="memberEmail" class="block mt-1 w-full" type="email" autofocus wire:model='memberEmail' />
                <x-input-error for="memberEmail" class="mt-2" />
            </div>
            <div>
            <x-label class="mt-4" for="restaurantPhoneNumber"
                value="{{ __('modules.settings.restaurantPhoneNumber') }}" />
            <div class="flex gap-2 mt-2">
                <!-- Phone Code Dropdown -->
                <select wire:model="restaurantPhoneCode" id="restaurantPhoneCode"
                    class="block w-24 rounded-md border-gray-300 dark:bg-gray-700 dark:text-white">
                    @foreach ($phonecodes as $phonecode)
                        <option value="{{ $phonecode }}">
                            +{{ $phonecode }}
                        </option>
                    @endforeach
                </select>

                <!-- Phone Number Input -->
                <x-input id="restaurantPhoneNumber" class="block w-full" type="tel"
                    wire:model='restaurantPhoneNumber' placeholder="1234567890" />
            </div>

            <x-input-error for="restaurantPhoneCode" class="mt-2" />
            <x-input-error for="restaurantPhoneNumber" class="mt-2" />
        </div>
        <div>
            <x-label for="memberPassword" value="{{ __('modules.staff.password') }}" />
            <x-input id="memberPassword" class="block mt-1 w-full" type="password" autofocus
                wire:model='memberPassword' />
            <x-input-error for="memberPassword" class="mt-2" />
        </div>

        <div>
            <x-label for="memberRole" value="{{ __('app.role') }}" />

            <x-select class="mt-1 block w-full" wire:model='memberRole'>
                @foreach ($roles as $role)
                    <option value="{{ $role->name }}">{{ __('modules.staff.' . $role->display_name) }}</option>
                @endforeach
            </x-select>

            <x-input-error for="memberRole" class="mt-2" />
        </div>

</div>

<div class="flex w-full pb-4 space-x-4 mt-6 rtl:space-x-reverse">
    <x-button>@lang('app.save')</x-button>
    <x-button-cancel wire:click="$dispatch('hideAddStaff')"
        wire:loading.attr="disabled">@lang('app.cancel')</x-button-cancel>
</div>
</form>
</div>
