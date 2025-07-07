<div>
    <div class="p-4 bg-white block  dark:bg-gray-800 dark:border-gray-700">
        <div class="flex mb-4">
            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl dark:text-white">@lang('menu.orders') ({{ $orders->count() }})</h1>
            <div class="ml-auto flex items-center gap-4">
                <div class="flex items-center gap-2">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" class="sr-only peer" wire:model.live="pollingEnabled">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                        <span class="ms-3 text-sm font-medium text-gray-900 dark:text-gray-300">@lang('app.autoRefresh')</span>
                    </label>
                    <x-select class="w-32 text-sm" wire:model.live="pollingInterval" :disabled="!$pollingEnabled">
                        <option value="5">5 @lang('app.seconds')</option>
                        <option value="10">10 @lang('app.seconds')</option>
                        <option value="15">15 @lang('app.seconds')</option>
                        <option value="30">30 @lang('app.seconds')</option>
                        <option value="60">1 @lang('app.minute')</option>
                    </x-select>

                    <x-select class="w-32 text-sm" wire:model.live.debounce.250ms='filterOrderType'>
                        <option value="">@lang('modules.order.all')</option>
                        <option value="dine_in">@lang('modules.order.dine_in')</option>
                        <option value="delivery">@lang('modules.order.delivery')</option>
                        <option value="pickup">@lang('modules.order.pickup')</option>
                    </x-select>

                </div>
            </div>
        </div>

        <div class="items-center justify-between block sm:flex ">
            <div class="lg:flex items-center mb-4 sm:mb-0">
                <form class="ltr:sm:pr-3 rtl:sm:pl-3" action="#" method="GET">

                    <div class="lg:flex gap-2 items-center">
                        <x-select id="dateRangeType" class="block w-fit" wire:model="dateRangeType"
                         wire:change="setDateRange">
                            <option value="today">@lang('app.today')</option>
                            <option value="currentWeek">@lang('app.currentWeek')</option>
                            <option value="lastWeek">@lang('app.lastWeek')</option>
                            <option value="last7Days">@lang('app.last7Days')</option>
                            <option value="currentMonth">@lang('app.currentMonth')</option>
                            <option value="lastMonth">@lang('app.lastMonth')</option>
                            <option value="currentYear">@lang('app.currentYear')</option>
                            <option value="lastYear">@lang('app.lastYear')</option>
                        </x-select>

                        <div id="date-range-picker" date-rangepicker class="flex items-center w-full">
                            <div class="relative">
                                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                                    <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M20 4a2 2 0 0 0-2-2h-2V1a1 1 0 0 0-2 0v1h-3V1a1 1 0 0 0-2 0v1H6V1a1 1 0 0 0-2 0v1H2a2 2 0 0 0-2 2v2h20V4ZM0 18a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8H0v10Zm5-8h10a1 1 0 0 1 0 2H5a1 1 0 0 1 0-2Z"/>
                                    </svg>
                                </div>
                                <input id="datepicker-range-start" name="start" type="text" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full ps-10 p-2.5  dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" wire:model.change='startDate' placeholder="@lang('app.selectStartDate')">
                                </div>
                                <span class="mx-4 text-gray-500">@lang('app.to')</span>
                                <div class="relative">
                                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                                    <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M20 4a2 2 0 0 0-2-2h-2V1a1 1 0 0 0-2 0v1h-3V1a1 1 0 0 0-2 0v1H6V1a1 1 0 0 0-2 0v1H2a2 2 0 0 0-2 2v2h20V4ZM0 18a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8H0v10Zm5-8h10a1 1 0 0 1 0 2H5a1 1 0 0 1 0-2Z"/>
                                    </svg>
                                </div>
                                <input id="datepicker-range-end" name="end" type="text" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full ps-10 p-2.5  dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" wire:model.live='endDate' placeholder="@lang('app.selectEndDate')">
                            </div>
                        </div>
                    </div>
                </form>


                <div class="inline-flex gap-2">
                    <x-select class="text-sm w-full" wire:model.live.debounce.250ms='filterOrders'>
                        <option value="">@lang('app.showAll') @lang('menu.orders')</option>
                        <option value="running">@lang('modules.order.running_orders')</option>
                        <option value="kot">@lang('modules.order.kot') ({{ $kotCount }})</option>
                        <option value="billed">@lang('modules.order.billed') ({{ $billedCount }})</option>
                        <option value="paid">@lang('modules.order.paid') ({{ $paidOrdersCount }})</option>
                        <option value="canceled">@lang('modules.order.canceled') ({{ $canceledOrdersCount }})</option>
                        <option value="out_for_delivery">@lang('modules.order.out_for_delivery') ({{ $outDeliveryOrdersCount }})</option>
                        <option value="payment_due">@lang('modules.order.payment_due') ({{ $paymentDueCount }})</option>
                        <option value="delivered">@lang('modules.order.delivered') ({{ $deliveredOrdersCount }})</option>
                    </x-select>

                    <x-select class="text-sm w-full" wire:model.live.debounce.250ms='filterWaiter'>
                        <option value="">@lang('app.showAll') @lang('modules.order.waiter')</option>
                        @foreach ($waiters as $waiter)
                            <option value="{{ $waiter->id }}">{{ $waiter->name }}</option>
                        @endforeach
                    </x-select>


                </div>

            </div>

            @if(user_can('Create Order'))
                <x-primary-link wire:navigate href="{{ route('pos.index') }}">@lang('modules.order.newOrder')</x-primary-link>
            @endif

        </div>
    </div>

    <div class="flex flex-col my-4 px-4">

        <!-- Card Section -->
        <div class="space-y-4">


            <div wire:loading>
                <div class="grid sm:grid-cols-3 2xl:grid-cols-4 gap-3 sm:gap-4">
                    @for ($i = 0; $i < 6; $i++)
                        <div class="flex-col gap-3 items-center border bg-white shadow-sm rounded-lg dark:bg-gray-700 dark:border-gray-600 p-3 animate-pulse">
                            <div class="group flex flex-col gap-3 items-center">
                                <div class="flex gap-4 justify-between w-full">
                                    <div class="flex gap-3 space-y-1">
                                        <!-- Table/Order Type Icon -->
                                        <div class="p-3 rounded-lg bg-gray-200 dark:bg-gray-600 w-10 h-10"></div>

                                        <!-- Customer Info -->
                                        <div>
                                            <div class="h-4 bg-gray-200 dark:bg-gray-600 rounded w-32 mb-1"></div>
                                            <div class="h-3 bg-gray-200 dark:bg-gray-600 rounded w-24"></div>
                                        </div>
                                    </div>

                                    <!-- Status -->
                                    <div class="ltr:text-right rtl:text-left">
                                        <div class="h-5 bg-gray-200 dark:bg-gray-600 rounded w-20 mb-1"></div>
                                        <div class="h-3 bg-gray-200 dark:bg-gray-600 rounded w-16"></div>
                                    </div>
                                </div>

                                <!-- Date and Items Count -->
                                <div class="flex w-full justify-between items-center">
                                    <div class="h-3 bg-gray-200 dark:bg-gray-600 rounded w-32"></div>
                                    <div class="h-3 bg-gray-200 dark:bg-gray-600 rounded w-16"></div>
                                </div>

                                <!-- Footer -->
                                <div class="flex w-full justify-between items-center border-t dark:border-gray-500 pt-3">
                                    <div class="h-5 bg-gray-200 dark:bg-gray-600 rounded w-24"></div>
                                    <div class="h-4 bg-gray-200 dark:bg-gray-600 rounded w-20"></div>
                                </div>
                            </div>
                        </div>
                    @endfor
                </div>
            </div>

            <div class="grid sm:grid-cols-3 2xl:grid-cols-4 gap-3 sm:gap-4" wire:loading.remove>
                @foreach ($orders as $item)
                    <x-order.order-card :order='$item' wire:key='order-{{ $item->id . microtime() }}' />
                @endforeach
            </div>
        </div>
        <!-- End Card Section -->


    </div>

    @script
    <script>
        const datepickerEl1 = document.getElementById('datepicker-range-start');

        datepickerEl1.addEventListener('changeDate', (event) => {
            $wire.dispatch('setStartDate', { start: datepickerEl1.value });
        });

        const datepickerEl2 = document.getElementById('datepicker-range-end');

        datepickerEl2.addEventListener('changeDate', (event) => {
            $wire.dispatch('setEndDate', { end: datepickerEl2.value });
        });

        // Handle polling
        let pollingInterval = null;

        function startPolling() {
            if (pollingInterval) {
                clearInterval(pollingInterval);
            }
            const interval = $wire.get('pollingInterval') * 1000;
            pollingInterval = setInterval(() => {
                if ($wire.get('pollingEnabled')) {
                    $wire.$refresh();
                }
            }, interval);
        }

        function stopPolling() {
            if (pollingInterval) {
                clearInterval(pollingInterval);
                pollingInterval = null;
            }
        }

        // Initialize polling
        if ($wire.get('pollingEnabled')) {
            startPolling();
        }

        // Watch for changes
        $wire.watch('pollingEnabled', (value) => {
            if (value) {
                startPolling();
            } else {
                stopPolling();
            }
        });

        $wire.watch('pollingInterval', (value) => {
            if ($wire.get('pollingEnabled')) {
                startPolling();
            }
        });

        // Cleanup on component destroy
        document.addEventListener('livewire:initialized', () => {
            return () => {
                stopPolling();
            };
        });
    </script>
    @endscript

</div>
