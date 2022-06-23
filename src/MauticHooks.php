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
        $id_exists = $this->storage->get_mautic_id_by_freemius_id($install->id);

        $attributes = array_merge([
            'pluginversion'         => $install->version,
            'siteurl'               => $install->url,
            'plan'                  => $install->plan_id,
            'freemiusinstallid'     => $install->id,
            'installstate'          => ($install->is_active ? 'activated' : ($install->is_uninstalled ? 'uninstalled' : 'unknown')),
            'wordpressversion'      => $install->platform_version,
            'phpversion'            => $install->programming_language_version,
            'freemiususerid'        => $install->user_id,
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
                                'name'          => $title ?: !empty($install->title) ? $install->title : $install->url,
                                'attributes'    => $attributes,
                            ]
                        ]
                    ],
                ]
            ]
        ];
        $contact = $this->create_or_update_contact($data);

        if(!$id_exists){
            $this->save_mautic_id($install->id, $contact);
        }

        return $contact;
    }

    protected function save_mautic_id($install_id, $contact){
        if(!isset($contact['customObjects']['data']) || empty($contact['customObjects']['data'])){
            throw new \Exception('Could not find/create custom objects for contact');
        }
        $all_objects = $contact['customObjects']['data'];

        $custom_object_id = array_search('installs', array_column($all_objects, 'alias'));
        if($custom_object_id === false){
            throw new \Exception('Installs custom object not found for contact');
        }

        if(!isset($all_objects[$custom_object_id]['data']) || empty($all_objects[$custom_object_id]['data'])){
            throw new \Exception('No installs found for contact');
        }

        $all_items = $all_objects[$custom_object_id]['data'];
        foreach($all_items as $item){
            if((int)$item['attributes']['freemiusinstallid'] != (int)$install_id){ continue; }
            $this->storage->store_id_match($install_id, $item['id']);
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
                'installstate' => 'activated'
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
                'installstate'         => 'uninstalled',
                'uninstallreasoninfo' => $this->request->data->reason_info,
                'uninstallreason'      => $this->request->data->reason_id
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
                'intrial'          => true,
                'trialplan'        => $this->request->data->trial_plan_id,
            ]
        );
    }

    public function install_trial_cancelled(){
        $this->add_or_update_install(
            [
                'intrial'    => false,
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

}