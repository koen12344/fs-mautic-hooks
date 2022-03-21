<?php

namespace FSWebhooks;

use Mautic\MauticApi;

class MauticHooks extends WebhookListener
{
    private $contact_api;
    /**
     * @var \Mautic\Api\Api
     */
    private $company_api;
    /**
     * @var \Mautic\Api\Api
     */
    private $user_api;


    public function __construct($request, $base_url, $auth)
    {
        parent::__construct($request);
        $mautic_api = new MauticApi();
        $this->contact_api = $mautic_api->newApi('contacts', $auth, $base_url);
        $this->company_api = $mautic_api->newApi('companies', $auth, $base_url);
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

    protected function get_company_by_freemius_id($id){
        $companies = $this->company_api->getList(
            "freemius_install_id:{$id}",
            0,
            1
        );
        if($companies['total'] === 0){
            return false;
        }
        return reset($companies['companies']);
    }

    protected function create_or_update_company($fields = []){
        $install = $this->request->objects->install;
        $data = [
            'companyname'           => !empty($install->title) ? $install->title : $install->url,
            'companywebsite'        => $install->url,
            'plan'                  => $install->plan_id,
            'freemius_install_id'   => $install->id,
        ];
        $data = array_merge($data, $fields);

        $company = $this->get_company_by_freemius_id($install->id);
        if($company){
            $company = $this->company_api->edit($company['id'], $data, false)['company'];
        }else{
            $company = $this->company_api->create($data)['company'];
        }

        $contact = $this->create_or_update_contact();

        $this->company_api->addContact($company['id'], $contact['id']);
        return $company;
    }

    /*
     * Freemius webhook handlers start here
     */

    public function user_created(){
        $this->create_or_update_contact();
    }

    public function install_activated(){
        $this->create_or_update_company(
            [
                'install_state' => 'activated'
            ]
        );
    }

    public function install_deactivated(){
        $this->create_or_update_company(
            [
                'install_state' => 'deactivated'
            ]
        );
    }

    public function install_uninstalled(){
        $this->create_or_update_company(
            [
                'install_state'         => 'uninstalled',
                'uninstall_reason_info' => $this->request->data->reason_info,
                'uninstall_reason'      => $this->request->data->reason_id
            ]
        );
    }

    public function install_url_updated(){
        $this->create_or_update_company(
            [
                'companywebsite'    => $this->request->data->to
            ]
        );
    }

    public function install_plan_changed(){
        $this->create_or_update_company(
            [
                'plan'  => $this->request->data->to
            ]
        );
    }

    public function user_marketing_opted_in(){
        $contact = $this->create_or_update_contact();
        $this->contact_api->removeDNC($contact['id']);
    }

    public function user_marketing_opted_out(){
        $contact = $this->create_or_update_contact();
        $this->contact_api->addDNC($contact['id']);
    }

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

    public function install_platform_version_updated(){
        $this->create_or_update_company(
            [
                'wordpress_version' => $this->request->data->to
            ]
        );
    }

    public function install_programming_language_version_updated(){
        $this->create_or_update_company(
            [
                'php_version' => $this->request->data->to
            ]
        );
    }

    public function install_version_upgraded(){
        $this->create_or_update_company(
            [
                'plugin_version' => $this->request->data->to
            ]
        );
    }

    public function install_version_downgrade(){
        $this->install_version_upgraded();
    }
}