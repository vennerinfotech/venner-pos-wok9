<div
    class="mx-4 p-6 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2 dark:border-gray-700 dark:bg-gray-800">

    {{-- Heading --}}
    <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
        <div
            class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm dark:border-gray-700 dark:bg-gray-800 mt-4 flex justify-between items-center">
            <div>
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white">
                    @lang('modules.settings.printerSetting')
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @lang('modules.settings.printerSettingDescription')
                </p>
            </div>
            <div>
                {{-- <x-button type='button' wire:click="showAddPrinter">
                    @lang('modules.printerSetting.addPrinter')
                </x-button> --}}
            </div>
        </div>

        {{-- Form  --}}
        @if ($showForm)
            <div class="grid gap-6 grid-cols-1 md:grid-cols-2 mt-6" id="printerSettingForm">
                <div class="md:col-span-2 bg-gray-50 dark:bg-gray-700/50 p-6 rounded-lg" id="printerSettingForm">

                    <form wire:submit.prevent="{{ $id ? 'update' : 'submitForm' }}" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                            <div class="border p-4 rounded-lg dark:border-gray-500 space-y-4">
                                <!-- Title -->
                                <div>
                                    <x-label for="title" :value="__('modules.printerSetting.title')" />
                                    <x-input id="title" type="text" wire:model="title" class="mt-1 block w-full"
                                        placeholder="{{ __('placeholders.addPrinterName') }}" />
                                    <x-input-error for="title" class="mt-2" />
                                </div>

                                <!-- KOT Multi-Select Dropdown -->
                                <div x-data="{ isOpenKots: false, selectedKots: @entangle('selectedKots') }" class="relative" @click.away="isOpenKots = false">
                                    <x-label value="{{ __('modules.printerSetting.kotSelection') }}" />

                                    <div @click="isOpenKots = !isOpenKots"
                                        class="mt-1 p-3 bg-gray-100 dark:bg-gray-800 dark:border-gray-600 border rounded-md cursor-pointer text-sm text-gray-700 dark:text-gray-200">
                                        @lang('modules.printerSetting.select')
                                    </div>

                                    <!-- Dropdown List -->
                                    <ul x-show="isOpenKots" x-transition
                                        class="absolute z-10 mt-1 w-full bg-white dark:bg-gray-900 rounded-md shadow-lg ring-1 ring-black ring-opacity-5 overflow-auto max-h-60 text-sm focus:outline-none">
                                        @foreach ($kots->where('is_active', true) as $kot)
                                            <li @click="$wire.toggleSelectKot({ id: {{ $kot->id }}, name: '{{ addslashes($kot->name) }}' })"
                                                wire:key="kot-{{ $kot->id }}"
                                                class="relative py-2 pl-3 pr-9 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800"
                                                :class="{
                                                    'bg-gray-100 dark:bg-gray-800': selectedKots.includes(
                                                        {{ $kot->id }})
                                                }">
                                                <div class="flex items-center justify-between">
                                                    <span
                                                        class="truncate text-gray-700 dark:text-gray-200">{{ $kot->name }}</span>
                                                    <span x-show="selectedKots.includes({{ $kot->id }})" x-cloak>
                                                        <svg class="w-5 h-5 text-green-600" fill="currentColor"
                                                            viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd"
                                                                d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z"
                                                                clip-rule="evenodd" />
                                                        </svg>
                                                    </span>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>

                                    <!-- Display selected KOTs (optional) -->
                                    @if (!empty($selectedKots))
                                        <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                            <strong>@lang('modules.printerSetting.selectedKitchens'):</strong>
                                            <span class="text-gray-700 dark:text-gray-300">
                                                {{ implode(', ', $kots->whereIn('id', $selectedKots)->pluck('name')->toArray()) }}
                                            </span>
                                        </div>
                                    @endif

                                    <x-input-error for="selectedKots" class="mt-2" />
                                </div>

                                <!-- Order Multi-Select Dropdown -->
                                <div x-data="{ isOpenOrders: @entangle('isOpenOrders'), selectedOrders: @entangle('selectedOrders') }" class="relative" @click.away="isOpenOrders = false">
                                    <x-label value="{{ __('modules.printerSetting.orderSelection') }}" />

                                    <div @click="isOpenOrders = !isOpenOrders"
                                        class="mt-1 p-3 bg-gray-100 dark:bg-gray-800 dark:border-gray-600 border rounded-md cursor-pointer text-sm text-gray-700 dark:text-gray-200">
                                        @lang('modules.printerSetting.select')
                                    </div>

                                    <!-- Dropdown List -->
                                    <ul x-show="isOpenOrders" x-transition
                                        class="absolute z-10 mt-1 w-full bg-white dark:bg-gray-900 rounded-md shadow-lg ring-1 ring-black ring-opacity-5 overflow-auto max-h-60 text-sm focus:outline-none">
                                        @foreach ($orders as $order)
                                            <li @click="$wire.toggleSelectOrder({ id: {{ $order->id }}, name: '{{ addslashes($order->name) }}' })"
                                                wire:key="order-{{ $order->id }}"
                                                class="relative py-2 pl-3 pr-9 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800"
                                                :class="{
                                                    'bg-gray-100 dark:bg-gray-800': selectedOrders.includes(
                                                        {{ $order->id }})
                                                }">
                                                <div class="flex items-center justify-between">
                                                    <span
                                                        class="truncate text-gray-700 dark:text-gray-200">{{ $order->name }}</span>
                                                    <span x-show="selectedOrders.includes({{ $order->id }})"
                                                        x-cloak>
                                                        <svg class="w-5 h-5 text-green-600" fill="currentColor"
                                                            viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd"
                                                                d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z"
                                                                clip-rule="evenodd" />
                                                        </svg>
                                                    </span>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>

                                    <!-- Display selected orders (optional) -->
                                    @if (!empty($selectedOrders))
                                        <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                            <strong>@lang('modules.printerSetting.selectedPosTerminal'):</strong>
                                            <span class="text-gray-700 dark:text-gray-300">
                                                {{ implode(', ', $orders->whereIn('id', $selectedOrders)->pluck('name')->toArray()) }}
                                            </span>
                                        </div>
                                    @endif

                                    <x-input-error for="selectedOrders" class="mt-2" />
                                </div>

                                <!-- Is Default Checkbox -->
                                <div class="mt-4">
                                    <label class="flex items-center">
                                        <input type="checkbox" wire:model="isDefault"
                                            class="form-checkbox text-blue-600 rounded-md" />
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                            @lang('modules.printerSetting.isDefault')
                                        </span>
                                    </label>
                                    <x-input-error for="isDefault" class="mt-2" />
                                </div>

                            </div>

                            <div class="border p-4 rounded-lg dark:border-gray-500 space-y-4">
                                <!-- Print Choice -->
                                <div>
                                    <x-label for="printChoice" :value="__('modules.printerSetting.printChoice')" />
                                    <select id="printChoice" wire:model.live="printChoice"
                                        class="mt-1 block w-full border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white rounded-md shadow-sm">
                                        <option value="">@lang('Select')</option>
                                        <option value="browserPopupPrint">@lang('modules.printerSetting.browserPopupPrint')</option>
                                        {{-- <option value="directPrint">@lang('modules.printerSetting.directPrint')</option> --}}
                                    </select>
                                    <x-input-error for="printChoice" class="mt-2" />
                                </div>

                                {{-- Printer Type --}}
                                @if ($printChoice == 'directPrint')
                                    <div>
                                        <x-label for="printerType"
                                            value="{{ __('modules.printerSetting.printerType') }}" />
                                        <select id="printerType" wire:model.live ="printerType"
                                            class="mt-1 block w-full border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white rounded-md shadow-sm">
                                            <option value="">@lang('Select')</option>
                                            <option value="network" selected>
                                                @lang('modules.printerSetting.networkPrinter')
                                            </option>
                                            <option value="windows">
                                                @lang('modules.printerSetting.usbPrinter')
                                            </option>
                                        </select>
                                        <x-input-error for="printerType" class="mt-2" />
                                    </div>
                                @endif

                                <!-- Show Windows Printer Share Name -->
                                @if ($printerType === 'windows' && $printChoice == 'directPrint')
                                    <div>
                                        <x-label for="shareName"
                                            value="{{ __('modules.printerSetting.shareName') }}" />
                                        <div class="relative">
                                            <x-input id="shareName" type="text" wire:model="shareName"
                                                class="mt-1 block w-full"
                                                placeholder="{{ __('placeholders.addPrinterSharedName') }}" />
                                        </div>
                                        <x-input-error for="shareName" class="mt-2" />
                                    </div>
                                @endif

                                <!-- Show Network Printer IP & Port only if printerType is network -->
                                @if ($printerType === 'network' && $printChoice == 'directPrint')
                                    <!-- Printer IP Address -->
                                    <div>
                                        <x-label for="printerIpAddress"
                                            value="{{ __('modules.printerSetting.printerIPAddress') }}" />
                                        <div class="relative">
                                            <x-input id="printerIpAddress" type="text"
                                                wire:model="printerIpAddress" class="mt-1 block w-full"
                                                placeholder="{{ __('placeholders.IpAddress') }}" />
                                            <div class="absolute top-1/2 right-2 transform -translate-y-1/2 group">
                                                <button type="button"
                                                    class="p-2 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg focus:outline-none">
                                                    <svg class="w-5 h-5" fill="none" stroke="#1C274C"
                                                        viewBox="0 0 24 24">
                                                        <circle cx="12" cy="12" r="10"
                                                            stroke-width="1.5">
                                                        </circle>
                                                        <path
                                                            d="M10.125 8.875C10.125 7.83947 10.9645 7 12 7C13.0355 7 13.875 7.83947 13.875 8.875C13.875 9.56245 13.505 10.1635 12.9534 10.4899C12.478 10.7711 12 11.1977 12 11.75V13"
                                                            stroke-width="1.5" stroke-linecap="round"></path>
                                                        <circle cx="12" cy="16" r="1" fill="#1C274C">
                                                        </circle>
                                                    </svg>
                                                </button>
                                                <div
                                                    class="absolute w-40 bottom-full mb-1 left-1/2 -translate-x-1/2 z-50 hidden group-hover:block px-4 py-2 text-s text-white bg-gray-900 rounded shadow-md">
                                                    {{ __('messages.printerIPAddress') }}
                                                </div>
                                            </div>
                                        </div>
                                        <x-input-error for="printerIpAddress" class="mt-2" />
                                    </div>

                                    <!-- Printer Port Address -->
                                    <div>
                                        <x-label for="printerPortAddress"
                                            value="{{ __('modules.printerSetting.printerPortAddress') }}" />
                                        <div class="relative">
                                            <x-input id="printerPortAddress" type="text"
                                                wire:model="printerPortAddress" class="mt-1 block w-full"
                                                placeholder="{{ __('placeholders.portAddress') }}" />
                                            <div class="absolute top-1/2 right-2 transform -translate-y-1/2 group">
                                                <button type="button"
                                                    class="p-2 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg focus:outline-none">
                                                    <svg class="w-5 h-5" fill="none" stroke="#1C274C"
                                                        viewBox="0 0 24 24">
                                                        <circle cx="12" cy="12" r="10"
                                                            stroke-width="1.5">
                                                        </circle>
                                                        <path
                                                            d="M10.125 8.875C10.125 7.83947 10.9645 7 12 7C13.0355 7 13.875 7.83947 13.875 8.875C13.875 9.56245 13.505 10.1635 12.9534 10.4899C12.478 10.7711 12 11.1977 12 11.75V13"
                                                            stroke-width="1.5" stroke-linecap="round"></path>
                                                        <circle cx="12" cy="16" r="1" fill="#1C274C">
                                                        </circle>
                                                    </svg>
                                                </button>
                                                <div
                                                    class="absolute w-40 bottom-full mb-1 left-1/2 -translate-x-1/2 z-50 hidden group-hover:block px-4 py-2 text-s text-white bg-gray-900 rounded shadow-md">
                                                    {{ __('messages.printerPortAddress') }}
                                                </div>
                                            </div>
                                        </div>
                                        <x-input-error for="printerPortAddress" class="mt-2" />
                                    </div>
                                @endif


                                @if ($printChoice == 'directPrint')
                                    <div>
                                        <x-label for="selectprintFormat"
                                            value="{{ __('modules.printerSetting.printFormat') }}" />
                                        <select id="selectprintFormat" wire:model.defer="selectprintFormat"
                                            class="mt-1 block w-full border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white rounded-md shadow-sm">
                                            <option value="">@lang('Select')</option>
                                            <option value="thermal56mm">@lang('modules.printerSetting.thermal56mm')</option>
                                            <option value="thermal80mm">@lang('modules.printerSetting.thermal80mm')</option>
                                            <option value="thermal112mm">@lang('modules.printerSetting.thermal112mm')</option>
                                        </select>
                                        <x-input-error for="selectprintFormat" class="mt-2" />
                                    </div>
                                @endif


                            </div>
                            <div class="flex justify-right pt-6">
                                @if ($id)
                                    <x-button type="submit">
                                        @lang('app.update')
                                    </x-button>
                                @else
                                    <x-button type="submit">
                                        @lang('app.save')
                                    </x-button>
                                @endif

                                <x-button-cancel wire:click="$toggle('showForm')" class="ml-3">
                                    @lang('app.cancel')
                                </x-button-cancel>
                            </div>

                        </div>
                    </form>

                </div>
            </div>
        @endif


    </div>
    {{-- Table --}}
    <div class="grid gap-6 grid-cols-1 md:grid-cols-2 mt-3">
        <div class="md:col-span-2 bg-gray-50 dark:bg-gray-700/50 p-6 rounded-lg">
            <div class="flex flex-col">
                <div class="overflow-x-auto">
                    <div class="inline-block min-w-full align-middle">


                        <div class="space-y-3" wire:key='printer-list-{{ microtime() }}'>
                            @forelse ($printers as $printer)
                                <div class="relative group flex bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden transition hover:shadow-xl"
                                    wire:key="printer-{{ $printer->id }}">
                                    <!-- Status Accent Bar -->
                                    <div class="w-2 bg-gradient-to-b {{ $printer->is_active ? 'from-green-400 to-green-600' : 'from-gray-300 to-gray-400' }}"></div>
                                    <div class="flex-1 p-5">
                                        <!-- Header -->
                                        <div class="flex items-center justify-between mb-4">
                                            <div class="flex items-center space-x-2">
                                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white truncate">
                                                    {{ $printer->name ? Str::title($printer->name) : '--' }}
                                                </h3>
                                                @if ($printer->is_default)
                                                    <span class="ml-2 px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                        @lang('modules.printerSetting.default')
                                                    </span>
                                                @endif
                                                @if ($printer->printing_choice == 'directPrint')
                                                    @if ($printer->printer_connected)
                                                        <span class="ml-2 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                            @lang('modules.printerSetting.connected')
                                                        </span>
                                                    @else
                                                        <span class="ml-2 px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                                            @lang('modules.printerSetting.disconnected')
                                                        </span>
                                                    @endif
                                                @endif
                                            </div>
                                        </div>

                                        <!-- Details Grid -->
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-2 text-sm text-gray-700 dark:text-gray-300 mb-4">
                                            <div class="flex items-center space-x-2">
                                                <span class="font-medium text-gray-600 dark:text-gray-400">@lang('modules.printerSetting.kitchens'):</span>
                                                <span class="truncate">
                                                    @php
                                                        $kotIds = is_array($printer->kots) ? $printer->kots : json_decode($printer->kots, true);
                                                    @endphp
                                                    @if (!empty($kotIds))
                                                        {{ collect($kotIds)->map(fn($id) => $kots->firstWhere('id', $id)?->name ?? $id)->implode(', ') }}
                                                    @else
                                                        --
                                                    @endif
                                                </span>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <span class="font-medium text-gray-600 dark:text-gray-400">@lang('modules.printerSetting.orders'):</span>
                                                <span class="truncate">
                                                    @php
                                                        $orderIds = is_array($printer->orders) ? $printer->orders : json_decode($printer->orders, true);
                                                    @endphp
                                                    @if (!empty($orderIds))
                                                        {{ collect($orderIds)->map(fn($id) => $orders->firstWhere('id', $id)?->name ?? $id)->implode(', ') }}
                                                    @else
                                                        --
                                                    @endif
                                                </span>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <span class="font-medium text-gray-600 dark:text-gray-400">@lang('modules.printerSetting.printingChoice'):</span>
                                                <span class="truncate">{{ $printer->printing_choice ? __('modules.printerSetting.' . $printer->printing_choice) : '--' }}</span>
                                            </div>
                                            {{-- <div class="flex items-center space-x-2">
                                                <span class="font-medium text-gray-600 dark:text-gray-400">@lang('modules.printerSetting.printFormat'):</span>
                                                <span class="truncate">{{ $printer->print_format ? __('modules.printerSetting.' . $printer->print_format) : '--' }}</span>
                                            </div> --}}
                                            @if ($printer->printing_choice == 'directPrint' && $printer->type == 'network')
                                                {{-- <div class="flex items-center space-x-2">
                                                    <span class="font-medium text-gray-600 dark:text-gray-400">@lang('modules.printerSetting.ipAddress'):</span>
                                                    <span class="truncate">{{ $printer->ip_address ?? '--' }}</span>
                                                </div> --}}
                                            @else
                                                {{-- <div class="flex items-center space-x-2">
                                                    <span class="font-medium text-gray-600 dark:text-gray-400">@lang('modules.printerSetting.shareName'):</span>
                                                    <span class="truncate">{{ $printer->share_name ?? '--' }}</span>
                                                </div> --}}
                                            @endif
                                            {{-- <div class="flex items-center space-x-2">
                                                <span class="font-medium text-gray-600 dark:text-gray-400">@lang('modules.printerSetting.port'):</span>
                                                <span class="truncate">{{ $printer->port ?? '--' }}</span>
                                            </div> --}}
                                            {{-- <div class="flex items-center space-x-2">
                                                <span class="font-medium text-gray-600 dark:text-gray-400">@lang('modules.printerSetting.printerType'):</span>
                                                <span class="truncate">
                                                    @if (!empty($printer->type))
                                                        @lang('modules.printerSetting.' . ($printer->type === 'windows' ? 'usbPrinter' : $printer->type))
                                                    @else
                                                        --
                                                    @endif
                                                </span>
                                            </div> --}}
                                        </div>

                                        <!-- Footer with Actions and Toggle -->
                                        <div class="flex items-center justify-between pt-3 border-t border-gray-200 dark:border-gray-600">
                                            <!-- Toggle Switch for Active Status -->
                                            <div class="flex items-center space-x-2">
                                                <label for="isActive-{{ $printer->id }}" class="text-sm text-gray-600 dark:text-gray-400">@lang('app.active')</label>
                                                <input type="checkbox" id="isActive-{{ $printer->id }}"
                                                    wire:click="togglePrinterStatus({{ $printer->id }})"
                                                    @if ($printer->is_active) checked @endif
                                                    class="form-toggle rounded-full border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500 transition">
                                            </div>

                                            <!-- Action Buttons -->
                                            <div class="flex space-x-1 opacity-0 group-hover:opacity-100 transition">
                                                <button wire:click='editPrinter({{ $printer->id }})' class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path d="M15.232 5.232l3.536 3.536M9 11l6 6M3 21h6l11-11a2.828 2.828 0 0 0-4-4L5 17v4z"/>
                                                    </svg>
                                                </button>
                                                @if (!$printer->is_default)
                                                    <button wire:click="showDeletePrinter({{ $printer->id }})" class="p-2 rounded-lg hover:bg-red-100 dark:hover:bg-red-900 transition-colors">
                                                        <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                            <path d="M6 19a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7H6v12zM19 7V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v2"/>
                                                        </svg>
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-8 text-gray-400 dark:text-gray-500">
                                    @lang('messages.noPrinterAdded')
                                </div>
                            @endforelse
                        </div>

                    </div>
                </div>
            </div>


        </div>



    </div>
    {{-- Delete Confirmation Modal --}}
    <x-confirmation-modal wire:model.live="confrimDeletePrinter">
        <x-slot name="title">
            @lang('modules.printerSetting.deletePrinter')?
        </x-slot>

        <x-slot name="content">
            @lang('modules.printerSetting.deletePritnerConfirm')
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button wire:click="$toggle('confrimDeletePrinter')" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-secondary-button>

            @if ($printer)
                <x-danger-button class="ml-3" wire:click='confirmdeletePrinter' wire:loading.attr="disabled">
                    {{ __('Delete') }}
                </x-danger-button>

            @endif
        </x-slot>
    </x-confirmation-modal>

