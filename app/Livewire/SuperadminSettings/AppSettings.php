<?php

namespace App\Livewire\SuperadminSettings;

use App\Helper\Files;
use App\Models\LanguageSetting;
use Jantinnerezo\LivewireAlert\LivewireAlert;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\GlobalCurrency;
use App\Models\User;

class AppSettings extends Component
{
    use LivewireAlert, WithFileUploads;

    public $settings;
    public $appName;
    public $defaultLanguage;
    public $languageSettings;
    public $globalCurrencies;
    public $defaultCurrency;
    public $mapApiKey;
    public bool $requiresApproval;
    public $sessionDriver;

    public function mount()
    {
        $this->appName = $this->settings->name;
        $this->requiresApproval = $this->settings->requires_approval_after_signup;
        $this->defaultLanguage = $this->settings->locale;
        $this->languageSettings = LanguageSetting::where('active', 1)->get();
        $this->globalCurrencies = GlobalCurrency::where('status', 'enable')->get();
        $this->defaultCurrency = $this->settings->default_currency_id;
        $this->mapApiKey = $this->settings->google_map_api_key;
        $this->sessionDriver = $this->settings->session_driver;
    }

    public function submitForm()
    {

        $this->settings->name = $this->appName;
        $this->settings->requires_approval_after_signup = $this->requiresApproval;
        $this->settings->locale = $this->defaultLanguage;
        $this->settings->default_currency_id = $this->defaultCurrency;
        $this->settings->google_map_api_key = $this->mapApiKey ?? null;
        $this->settings->session_driver = $this->sessionDriver ?? null;
        $this->settings->save();

        cache()->forget('languages');

        if (languages()->count() == 1) {
            User::withOutGlobalScopes()->update(['locale' => $this->defaultLanguage]);
        }

        cache()->forget('global_setting');
        session()->forget('restaurantOrGlobalSetting');

        $this->redirect(route('superadmin.superadmin-settings.index'), navigate: true);

        $this->alert('success', __('messages.settingsUpdated'), [
            'toast' => true,
            'position' => 'top-end',
            'showCancelButton' => false,
            'cancelButtonText' => __('app.close')
        ]);
    }

    public function render()
    {
        return view('livewire.superadmin-settings.app-settings');
    }
}
