<?php

namespace App\Http\Controllers\SuperAdmin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{SuperadminPaymentGateway, RestaurantPayment, Restaurant, Package, GlobalSubscription, User, GlobalInvoice};
use App\Notifications\RestaurantUpdatedPlan;
use Illuminate\Support\Facades\{Notification, Session, Redirect, Config, Http};
use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\{Plan, PaymentDefinition, Currency, MerchantPreferences, Patch, PatchRequest, Agreement, Payer};
use PayPal\Exception\PayPalConnectionException;
use PayPal\Common\PayPalModel;
use App\Models\EmailSetting;

class PaypalController extends Controller
{
    private $_api_context, $paypalClientId, $paypalSecret;

    public function __construct()
    {
        $credential = SuperadminPaymentGateway::first();
        $this->paypalClientId = $credential->paypal_mode === 'sandbox' ? $credential->test_paypal_client_id : $credential->live_paypal_client_id;
        $this->paypalSecret = $credential->paypal_mode === 'sandbox' ? $credential->test_paypal_secret : $credential->live_paypal_secret;

        config(['paypal.settings.mode' => $credential->paypal_mode]);
        $paypal_conf = Config::get('paypal');

        $this->_api_context = new ApiContext(
            new OAuthTokenCredential($this->paypalClientId, $this->paypalSecret)
        );
        $this->_api_context->setConfig($paypal_conf['settings']);
    }

    public function initiatePayment(Request $request)
    {
        $restaurantPayment = RestaurantPayment::findOrFail($request->payment_id);
        $package = Package::findOrFail($request->input('package_id'));

        return $package->package_type->value === 'lifetime'
            ? $this->handleLifetimePayment($restaurantPayment, $package)
            : $this->handleSubscriptionPayment($request, $restaurantPayment, $package);
    }

    private function handleLifetimePayment($restaurantPayment, $package)
    {
        $amount = $package->price;
        $currency = $package->currency->currency_code;
    
        if (!$amount || !$currency) {
            Session::put('error', 'Invalid package details');
            return redirect()->route('dashboard');
        }
    
        $restaurantPayment->update([
            'amount' => $amount,
            'status' => 'pending',
            'payment_date_time' => now(),
        ]);
    
        $paypalData = [
            "intent" => "CAPTURE",
            "purchase_units" => [[
                "amount" => [
                    "currency_code" => $currency,
                    "value" => number_format($amount, 2, '.', '')
                ],
                "reference_id" => (string) $restaurantPayment->id
            ]],
            "application_context" => [
                "return_url" => url('/paypal/lifetime/success?payment_id=' . $restaurantPayment->id),  // Ensure full URL here
                "cancel_url" => url('/dashboard'),  // Ensure full URL for cancel
                "user_action" => "PAY_NOW"  // Optional, can help in some cases
            ]
        ];
    
        // Debugging log to check JSON being sent to PayPal    
        $auth = base64_encode("$this->paypalClientId:$this->paypalSecret");
    
        $response = Http::withHeaders([
            'Authorization' => "Basic $auth",
            'Content-Type' => 'application/json'
        ])->post('https://api-m.sandbox.paypal.com/v2/checkout/orders', $paypalData);
    
    
        if ($response->successful()) {
            $paypalResponse = $response->json();
            $approvalLink = collect($paypalResponse['links'])->firstWhere('rel', 'approve')['href'];
            return redirect($approvalLink);
        } else {
            return redirect()->route('dashboard')->withErrors(['error' => 'Unable to initiate PayPal payment.']);
        }
    }

    public function paypalLifetimeSuccess(Request $request)
    {
        $token = $request->query('token');  // Assuming 'token' is actually the order ID
    
        $restaurantPayment = RestaurantPayment::findOrFail($request->payment_id);

        // Capture the payment from PayPal using the order ID
        $response = Http::withBasicAuth($this->paypalClientId, $this->paypalSecret)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->send('POST', "https://api-m.sandbox.paypal.com/v2/checkout/orders/{$token}/capture");
        
        if (!$response->successful()) {
            // Log the error and update the payment status to failed
            $restaurantPayment->update(['status' => 'failed']);
            return redirect()->route('dashboard')->withErrors(['error' => 'Payment capture failed.']);
        }
    
        // Payment capture was successful
        $data = $response->json();
    
        $restaurant = Restaurant::findOrFail($restaurantPayment->restaurant_id);
        $package = Package::findOrFail($restaurantPayment->package_id);
    
        // Update restaurant details based on the successful payment
        $restaurant->update([
            'package_id' => $package->id,
            'package_type' => 'lifetime',
            'trial_ends_at' => null,
            'license_expire_on' => null,
            'is_active' => true,
            'status' => 'active'
        ]);
    
        // Update the payment status to 'paid'
        $restaurantPayment->update([
            'status' => 'paid',
            'payment_date_time' => now(),
            'amount' => $package->price
        ]);
    
        // Deactivate any active subscriptions before assigning new one
        GlobalSubscription::where('restaurant_id', $restaurant->id)
            ->where('subscription_status', 'active')
            ->update(['subscription_status' => 'inactive']);
    
        // Create a new subscription record for the lifetime plan
        $subscription = GlobalSubscription::create([
            'transaction_id' => $data['id'],
            'restaurant_id' => $restaurant->id,
            'package_id' => $package->id,
            'package_type' => 'lifetime',
            'gateway_name' => 'paypal',
            'subscription_status' => 'active',
            'subscribed_on_date' => now(),
            'quantity' => 1,
            'currency_id' => $package->currency_id
        ]);
    
        // Create the corresponding invoice for the new subscription
        GlobalInvoice::create([
            'restaurant_id' => $restaurant->id,
            'package_id' => $package->id,
            'transaction_id' => $data['id'],
            'currency_id' => $package->currency_id,
            'package_type' => 'lifetime',
            'total' => $package->price,
            'status' => 'active',
            'global_subscription_id' => $subscription->id,
            'gateway_name' => 'paypal',
        ]);
    
        // Clear session data and display success message
        session()->forget('restaurant');
        session()->flash('flash.banner', __('messages.planUpgraded'));
        session()->flash('flash.bannerStyle', 'success');
        session()->flash('flash.link', route('settings.index', ['tab' => 'billing']));
    
        return redirect()->route('dashboard')->with('livewire', true);
    }

    private function handleSubscriptionPayment(Request $request, $restaurantPayment, $package)
    {
        $planType = $request->input('package_type');
        $frequency = $planType === 'annual' ? 'year' : 'month';
        $amount = $planType === 'annual' ? $package->annual_price : $package->monthly_price;
        // Validate price and currency
        if (!$amount || !$package->currency->currency_code) {
            Session::put('error', 'Invalid package details');
            return redirect()->route('dashboard');
        }
        // Create new plan dynamically
        $plan = new Plan();
        $plan->setName('#'.$package->package_name)
            ->setDescription('Payment for package '.$package->package_name)
            ->setType('INFINITE');

        $paymentDefinition = new PaymentDefinition();
        $paymentDefinition->setName('Payment for package '.$package->package_name)
            ->setType('REGULAR')
            ->setFrequency($frequency)
            ->setFrequencyInterval(1)
            ->setCycles("0")
            ->setAmount(new Currency(array('value' => $amount, 'currency' => $package->currency->currency_code)));

        $merchantPreferences = new MerchantPreferences();
        $merchantPreferences->setReturnUrl(route('billing.paypal-recurring').'?success=true&invoice_id='.$package->id)
            ->setCancelUrl(route('billing.paypal-recurring').'?success=false&invoice_id='.$package->id)
            ->setAutoBillAmount('yes')
            ->setInitialFailAmountAction('CONTINUE')
            ->setMaxFailAttempts('0');

        // Set Payment Definition directly (not as array)
        $plan->setPaymentDefinitions(array($paymentDefinition));
        $plan->setMerchantPreferences($merchantPreferences);

        try {
            $output = $plan->create($this->_api_context);
        } catch (\Exception $ex) {

            if (\Config::get('app.debug')) {
                \Session::put('error', $ex->getMessage());
                return Redirect::route('dashboard');
            }
            else {
                \Session::put('error', 'Some error occur, sorry for inconvenient');
                return Redirect::route('dashboard');
            }

        }

        try {
            $patch = new Patch();
            $value = new PayPalModel('{
                "state":"ACTIVE"
                }');
            $patch->setOp('replace')
                ->setPath('/')
                ->setValue($value);

            $patchRequest = new PatchRequest();
            $patchRequest->addPatch($patch);
            $output->update($patchRequest, $this->_api_context);
            $newPlan = Plan::get($output->getId(), $this->_api_context);

        } catch (\Exception $ex) {
            if (\Config::get('app.debug')) {
                \Session::put('error', 'Connection timeout');
                return Redirect::route('dashboard');
            }
            else {
                \Session::put('error', 'Some error occur, sorry for inconvenient');
                return Redirect::route('dashboard');
            }
        }

        $restaurant = Restaurant::findOrFail(restaurant()->id);

        // Calculating next billing date
        $today = now()->addDay(); // Payment will deduct after 1 day
        $startingDate = $today->toIso8601String();

        $agreement = new Agreement();
        $agreement->setName($package->package_name)
            ->setDescription('Payment for package # ' .$package->package_name)
            ->setStartDate($startingDate);

        $plan1 = new Plan();
        $plan1->setId($newPlan->getId());
        $agreement->setPlan($plan1);

        $payer = new Payer();
        $payer->setPaymentMethod('paypal');
        $agreement->setPayer($payer);

        try {
            // Create Agreement
            $agreement = $agreement->create($this->_api_context);
            $approvalUrl = $agreement->getApprovalLink();

        } catch (\Exception $ex) {

            if (\Config::get('app.debug')) {
                \Session::put('error', 'Connection timeout');
                return Redirect::route('dashboard');

            }
            else {

                \Session::put('error', 'Some error occur, sorry for inconvenient');
                return Redirect::route('dashboard');
            }

        }
        Session::put('paypal_payment_id', $newPlan->getId());

        $restaurantPayment->amount = $amount;
        $restaurantPayment->status = 'paid';
        $restaurantPayment->payment_date_time = now()->toDateTimeString();
        $restaurantPayment->paypal_payment_id = $newPlan->getId();
        $restaurantPayment->save();

        $restaurant = Restaurant::find($restaurantPayment->restaurant_id);
        $restaurant->package_id = $restaurantPayment->package_id;
        $restaurant->package_type = $restaurantPayment->package_type;
        $restaurant->trial_ends_at = null;
        $restaurant->is_active = true;
        $restaurant->status = 'active';
        $restaurant->license_expire_on = null;
        $restaurant->save();

        // Deactivate existing subscriptions
        GlobalSubscription::where('restaurant_id', $restaurant->id)
            ->where('subscription_status', 'active')
            ->update(['subscription_status' => 'inactive']);
        
        // Store subscription data
        $paypalSubscription = new GlobalSubscription();
        $paypalSubscription->restaurant_id = restaurant()->id;
        $paypalSubscription->package_id = $package->id;
        $paypalSubscription->package_type = $planType;
        $paypalSubscription->gateway_name = 'paypal';
        $paypalSubscription->subscription_status = 'active';
        $paypalSubscription->subscribed_on_date = now()->format('Y-m-d H:i:s');
        $paypalSubscription->currency_id = $package->currency_id;
        $paypalSubscription->quantity = 1;
        $paypalSubscription->save();

        // Create invoice for PayPal
        $paypalInvoice = new GlobalInvoice();
        $paypalInvoice->restaurant_id = restaurant()->id;
        $paypalInvoice->package_id = $package->id;
        $paypalInvoice->currency_id = $package->currency_id;
        $paypalInvoice->package_type = $paypalSubscription->package_type;
        $paypalInvoice->total = $amount;
        $paypalInvoice->amount = $amount;
        $paypalInvoice->status = 'active';
        $paypalInvoice->plan_id = $newPlan->getId();
        $paypalInvoice->billing_frequency = $frequency;
        $paypalInvoice->billing_interval = 1;
        $paypalInvoice->global_subscription_id = $paypalSubscription->id;
        $paypalInvoice->gateway_name = 'paypal';
        $paypalInvoice->save();
        // Redirect to PayPal for approval
        if (isset($approvalUrl)) {
            return Redirect::away($approvalUrl);
        }
        \Session::put('error', 'Unknown error occurred');
        return Redirect::route('dashboard');
    }

    public function payWithPaypalRecurrring(Request $requestObject)
    {
        /** Get the payment ID before session clear **/
        $payment_id = Session::get('paypal_payment_id');
        $clientPayment = GlobalInvoice::where('plan_id', $payment_id)->first();
        $restaurant = restaurant();
        /** clear the session payment ID **/
        Session::forget('paypal_payment_id');

        if($requestObject->get('success') == true && $requestObject->has('token') && $requestObject->get('success') != 'false' )
        {
            $token = $requestObject->get('token');
            $agreement = new Agreement();

            try {
                $agreement->execute($token, $this->_api_context);

                if($agreement->getState() == 'Active' || $agreement->getState() == 'Pending') {
                    // Calculating next billing date
                    $today = now();

                    $clientPayment->transaction_id = $agreement->getId();
                    $clientPayment->subscription_id = $agreement->getId();

                    if($agreement->getState() == 'Active') {
                        $clientPayment->status = 'active';
                    }

                    $clientPayment->pay_date = now();
                    $clientPayment->gateway_name = 'paypal';
                    $clientPayment->save();

                    $subscription = GlobalSubscription::where('id', $clientPayment->global_subscription_id)->first();
                    if ($subscription) {
                        $subscription->transaction_id = $agreement->getId();
                        $subscription->subscription_id = $agreement->getId();
                        $subscription->save();
                    }
                    $restaurant->package_id = $clientPayment->package_id;
                    $restaurant->package_type = ($clientPayment->billing_frequency == 'year') ? 'annual' : 'monthly';
                    $restaurant->status = 'active';
                    $restaurant->license_expire_on = null;
                    $restaurant->save();

                    if( $restaurant->package_type == 'monthly') {
                        $today = $today->addMonth();

                    } else {
                        $today = $today->addYear();
                    }

                    $clientPayment->next_pay_date = $today->format('Y-m-d');
                    $clientPayment->save();
                    $emailSetting = EmailSetting::first();

                    if ($emailSetting->mail_driver === 'smtp' && $emailSetting->verified) {
                        $generatedBy = User::withoutGlobalScopes()->whereNull('restaurant_id')->first();
                        Notification::send($generatedBy, new RestaurantUpdatedPlan($restaurant, $clientPayment->package_id));
        
                        // Notify restaurant admin
                        $restaurantAdmin = $restaurant->restaurantAdmin($restaurant);
                        Notification::send($restaurantAdmin, new RestaurantUpdatedPlan($restaurant, $clientPayment->package_id));
                    }
                    session()->forget('restaurant');
                    request()->session()->flash('flash.banner', __('messages.planUpgraded'));
                    request()->session()->flash('flash.bannerStyle', 'success');
                    request()->session()->flash('flash.link', route('settings.index', ['tab' => 'billing']));

                    return redirect()->route('dashboard')->with('livewire', true);

                }

                return redirect()->route('dashboard')->with([
                    'flash.banner' => __('messages.paymentError'),
                    'flash.bannerStyle' => 'danger'
                ]);

            } catch (PayPalConnectionException $ex) {
                $errCode = $ex->getCode();
                $errData = json_decode($ex->getData());

                if ($errCode == 400 && $errData->name == 'INVALID_CURRENCY'){
                    \Session::put('error', $errData->message);
                    return Redirect::route('dashboard');

                }
                elseif (\Config::get('app.debug')) {
                    \Session::put('error', 'Connection timeout');
                    return Redirect::route('dashboard');

                }
                else {
                    \Session::put('error', 'Some error occur, sorry for inconvenient '.$errData->message);
                    return Redirect::route('dashboard');

                }
            }


        }
        else if($requestObject->get('fail') == true || $requestObject->get('success') == 'false')
        {
            \Session::put('error', 'Payment failed');
            return Redirect::route('dashboard');

        }else {
            abort(403);

        }

    }

    public function verifyBillingIPN(Request $request)
    {
        $txnType = $request->get('txn_type');
        if ($txnType == 'recurring_payment') {
            $recurringPaymentId = $request->get('recurring_payment_id');
            $eventId = $request->get('ipn_track_id');

            $event = GlobalInvoice::where('gateway_name', 'paypal')->where('event_id', $eventId)->count();
            if($event == 0)
            {
                $payment = GlobalInvoice::where('gateway_name', 'paypal')->where('transaction_id', $recurringPaymentId)->first();
                info('Payment: ' . json_encode($payment));
                $today = now();
                if($payment->package_type == 'annual') {
                    $nextPaymentDate = $today->addYear();

                } else if($payment->package_type == 'monthly') {
                    $nextPaymentDate = $today->addMonth();

                }

                $paypalInvoice = new GlobalInvoice();
                $paypalInvoice->transaction_id = $recurringPaymentId;
                $paypalInvoice->restaurant_id = $payment->restaurant_id;
                $paypalInvoice->currency_id = $payment->currency_id;
                $paypalInvoice->total = $payment->total;
                $paypalInvoice->status = 'paid';
                $paypalInvoice->plan_id = $payment->plan_id;
                $paypalInvoice->billing_frequency = $payment->billing_frequency;
                $paypalInvoice->event_id = $eventId;
                $paypalInvoice->billing_interval = 1;
                $paypalInvoice->paid_on = $today;
                $paypalInvoice->next_pay_date = $nextPaymentDate;
                $paypalInvoice->global_subscription_id = $payment->global_subscription_id;
                $paypalInvoice->save();
                $restaurant = Restaurant::findOrFail($payment->restaurant_id);
                $restaurant->status = 'active';
                $restaurant->save();
                $generatedBy = User::whereNull('restaurant_id')->get();

                Notification::send($generatedBy, new RestaurantUpdatedPlan($restaurant, $payment->plan_id));
                return response('IPN Handled', 200);
            }

        }

    }
}