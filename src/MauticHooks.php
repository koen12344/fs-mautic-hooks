<?php

namespace FSWebhooks;

use FSWebhooks\TokenStorage\StorageAdapter;
use Mautic\MauticApi;

class MauticHooks extends WebhookListener
{
    private $contact_api;
    /**
     * @var \Mautic\Api\Api
     */
    //private $company_api;
    /**
     * @var \Mautic\Api\Api
     */
    private $user_api;
    private $storage;


    public function __construct($request, $base_url, $auth, StorageAdapter $storage)
    {
        parent::__construct($request);
        $mautic_api = new MauticApi();
        $this->contact_api = $mautic_api->newApi('contacts', $auth, $base_url);
        $this->storage = $storage;
    }

    protected function get_contact_by_freemius_id($id){
        $contacts = $this->contact_api->getList(
            "freemius_id:{$id}",
            0,
            1
        );
        if($contacts['total'] === 0){
            return false;
        }
        return reset($contacts['contacts']);
    }

    protected function create_or_update_contact($fields = []){
        $user = $this->request->objects->user;

        $existing_contact = $this->get_contact_by_freemius_id($user->id);

        $data = array(
            'email'             => $user->email,
            'ipAddress'         => $user->ip,
            'firstname'         => $user->first,
            'lastname'          => $user->last,
            'freemius_id'       => $user->id,
        );
        $data = array_merge($data, $fields);

        if($existing_contact){
            $contact = $this->contact_api->edit($existing_contact['id'], $data, false)['contact'];
        }else{
            $contact = $this->contact_api->create($data)['contact'];
        }

        return $contact;
    }

    protected function add_or_update_install($fields = [], $title = null){
        $install = $this->request->objects->install;
        $id_exists = $this->storage->get_mautic_id_by_freemius_id($install->id, 'installs');

        $attributes = array_merge([
            'plugin1'              => $this->request->plugin_id,
            'pluginversion'         => $install->version,
            'siteurl'               => $install->url,
            'plan'                  => $install->plan_id,
            'freemiusinstallid'     => $install->id,
            'installstate'          => ($install->is_active ? 'activated' : ($install->is_uninstalled ? 'uninstalled' : 'unknown')),
            'wordpressversion'      => $install->platform_version,
            'phpversion'            => $install->programming_language_version,
            'freemiususerid'        => $install->user_id,
            'install-date'          => $install->created,
        ], $fields);

        $data = [
            'includeCustomObjects' => !$id_exists,
            'customObjects'     => [
                'data'      => [
                    [
                        'alias' => 'installs',
                        'data'  => [
                            [
                                'id'            => $id_exists ?: null,
                                'name'          => $title ?: (!empty($install->title) ? $install->title : $install->url),
                                'attributes'    => $attributes,
                            ]
                        ]
                    ],
                ]
            ]
        ];
        $contact = $this->create_or_update_contact($data);

        if(!$id_exists){
            $this->save_mautic_id($install->id, $contact, 'installs');
        }

        return $contact;
    }

    protected function add_or_update_license($fields = [], $title = null){
        $license = $this->request->objects->license;
        $id_exists = $this->storage->get_mautic_id_by_freemius_id($license->id, 'licenses');

        $attributes = array_merge([
            'plugin12'              => $this->request->plugin_id,
            'created'               => $license->created,
            'updated'               => $license->updated,
            'expiration'            => $license->expiration,
            'plan1'                  => $license->plan_id,
            'freemius-license-id'   => $license->id,
            'is-lifetime'           => !$license->expiration ? 'yes' : 'no',
            'quota'                 => $license->quota,
        ], $fields);

        $data = [
            'includeCustomObjects' => !$id_exists,
            'customObjects'     => [
                'data'      => [
                    [
                        'alias' => 'licenses',
                        'data'  => [
                            [
                                'id'            => $id_exists ?: null,
                                'name'          => $license->id,
                                'attributes'    => $attributes,
                            ]
                        ]
                    ],
                ]
            ]
        ];
        $contact = $this->create_or_update_contact($data);

        if(!$id_exists){
            $this->save_mautic_id($license->id, $contact, 'licenses');
        }

        return $contact;
    }

    protected function add_or_update_subscription($fields = []){
        $subscription = $this->request->objects->subscription;
        $id_exists = $this->storage->get_mautic_id_by_freemius_id($subscription->id, 'subscriptions');

        $attributes = array_merge([
            'plugin123'                 => $this->request->plugin_id,
            'created1'                  => $subscription->created,
            'updated1'                  => $subscription->updated,
            'next-payment'              => $subscription->next_payment,
            'billing-cycle'             => $subscription->billing_cycle,
            'total-gross'               => $subscription->total_gross,
            'amount-per-cycle'          => $subscription->amount_per_cycle,
            'freemius-subscription-id'  => $subscription->id,
            'freemius-user-id'          => $subscription->user_id,
            'canceled-at'               => !empty($subscription->cancelled_at)? $subscription->cancelled_at : null,
        ], $fields);

        $data = [
            'includeCustomObjects' => !$id_exists,
            'customObjects'     => [
                'data'      => [
                    [
                        'alias' => 'subscriptions',
                        'data'  => [
                            [
                                'id'            => $id_exists ?: null,
                                'name'          => $subscription->id,
                                'attributes'    => $attributes,
                            ]
                        ]
                    ],
                ]
            ]
        ];
        $contact = $this->create_or_update_contact($data);

        if(!$id_exists){
            $this->save_mautic_id($subscription->id, $contact, 'subscriptions');
        }

        return $contact;
    }

    protected function save_mautic_id($install_id, $contact, $type){
        if(empty($contact['customObjects']['data'])){
            throw new \Exception('Could not find/create custom objects for contact');
        }
        $all_objects = $contact['customObjects']['data'];

        $custom_object_id = array_search($type, array_column($all_objects, 'alias'));
        if($custom_object_id === false){
            throw new \Exception($type.' custom object not found for contact');
        }

        if(empty($all_objects[$custom_object_id]['data'])){
            throw new \Exception('No '.$type.' found for contact');
        }

        $mautic_id_field = ($type === 'installs' ? 'freemiusinstallid' : 'freemius-license-id');

        switch($type){
            case 'installs':
                $mautic_id_field = 'freemiusinstallid';
                break;
            case 'licenses':
                $mautic_id_field = 'freemius-license-id';
                break;
            case 'subscriptions':
                $mautic_id_field = 'freemius-subscription-id';
                break;
        }


        $all_items = $all_objects[$custom_object_id]['data'];
        foreach($all_items as $item){
            if((int)$item['attributes'][$mautic_id_field] != (int)$install_id){ continue; }
            $this->storage->store_id_match($install_id, $item['id'], $type);
        }

    }

    /*
     * Freemius webhook handlers start here
     */

    // -- User hooks

    public function user_created(){
        $this->create_or_update_contact();
    }

    // -- Install hooks

    public function install_platform_version_updated(){
        $this->add_or_update_install(
            [
                'wordpressversion' => $this->request->data->to
            ]
        );
    }

    public function install_programming_language_version_updated(){
        $this->add_or_update_install(
            [
                'phpversion' => $this->request->data->to
            ]
        );
    }

    public function install_version_upgraded(){
        $this->add_or_update_install(
            [
                'pluginversion' => $this->request->data->to
            ]
        );
    }
        public function install_version_downgrade(){
            $this->install_version_upgraded();
        }

    public function install_installed(){
        $this->add_or_update_install();
    }

    public function install_activated(){
        $this->add_or_update_install(
            [
                'installstate' => 'activated',
                'uninstallreasoninfo'   => null,
                'uninstallreason'       => null,
                'uninstall-date'        => null,
            ]
        );
    }

    public function install_deactivated(){
        $this->add_or_update_install(
            [
                'installstate' => 'deactivated'
            ]
        );
    }

    public function install_uninstalled(){
        $this->add_or_update_install(
            [
                'installstate'          => 'uninstalled',
                'uninstallreasoninfo'   => $this->request->data->reason_info,
                'uninstallreason'       => $this->request->data->reason_id,
                'uninstall-date'        => $this->request->created,
            ]
        );
    }

    public function install_premium_activated(){
        $this->add_or_update_install(
            [
                'plan'                  => $this->request->objects->install->plan_id,
            ]
        );
    }
        public function install_premium_deactivated(){
            $this->install_premium_activated();
        }



    public function install_trial_started(){
        $this->add_or_update_install(
            [
                'intrial'          => 'yes',
                'trialplan'        => $this->request->data->trial_plan_id,
            ]
        );
    }

    public function install_trial_cancelled(){
        $this->add_or_update_install(
            [
                'intrial'    => 'no',
                'trialplan'  => false,
            ]
        );
    }

    public function install_url_updated(){
        $this->add_or_update_install(
            [
                'siteurl'    => $this->request->data->to
            ]
        );
    }

    public function install_title_updated(){
        $to = $this->request->data->to;
        if(empty($to)){ return; }
        $this->add_or_update_install([], $to);
    }

    public function install_plan_changed(){
        $this->add_or_update_install(
            [
                'plan'  => $this->request->data->to
            ]
        );
    }

    // -- License hooks

    public function license_activated(){
        $this->add_or_update_license();
    }

    public function license_deactivated(){
        $this->add_or_update_license();
    }

    public function license_expired(){
        $this->add_or_update_license();
    }

    public function license_deleted(){
        $this->add_or_update_license();
    }

    public function license_cancelled(){
        $this->add_or_update_license();
    }

    public function license_extended(){
        $this->add_or_update_license();
    }

    public function license_shortened(){
        $this->add_or_update_license();
    }
    // -- Marketing hooks

    public function user_marketing_opted_in(){
        $contact = $this->create_or_update_contact();
        $this->contact_api->removeDNC($contact['id']);
    }

    public function user_marketing_opted_out(){
        $contact = $this->create_or_update_contact();
        $this->contact_api->addDNC($contact['id']);
    }

    // -- Beta tester hooks

    public function user_beta_program_opted_in(){
        $this->create_or_update_contact(
            [
                'beta_tester'   => true
            ]
        );
    }

    public function user_beta_program_opted_out(){
        $this->create_or_update_contact(
            [
                'beta_tester'   => false
            ]
        );
    }

    // -- Affiliate hooks

    public function affiliate_approved(){
        $this->create_or_update_contact(
            [
                'affiliate' => true
            ]
        );
    }

    public function affiliate_deleted(){
        $this->create_or_update_contact(
            [
                'affiliate' => false
            ]
        );
    }
        public function affiliate_blocked(){
            $this->affiliate_deleted();
        }
        public function affiliate_unapproved(){
            $this->affiliate_deleted();
        }


    // -- Subscription hooks

    public function subscription_cancelled(){
        $this->add_or_update_subscription([
            'cancelled' => 'yes'
        ]);
    }

    public function subscription_created(){
        $this->add_or_update_subscription();
    }

}