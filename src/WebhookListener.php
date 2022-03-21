<?php


namespace FSWebhooks;


abstract class WebhookListener
{
    protected $request, $type;

    public function __construct($request){
        $this->request = $request;
        $this->type = $request->type;
    }

    public function process(){
        $function_name = str_replace(".", "_", $this->type);
        if(method_exists($this, $function_name)){
            return $this->response($this->{$function_name}($this->request));
        }
        return $this->response("No Endpoint: $function_name", 200);
    }

    private function response($data, $status = 200){
        header("HTTP/1.1 ".$status." ".$this->requestStatus($status));
        return json_encode($data);
    }

    private function requestStatus($code){
        $status = array(
            200 => "OK",
            404 => "Not Found",
            405 => "Method Not Allowed",
            500 => "Internal Server Error"
        );
        return ($status[$code]) ? $status[$code]:$status[500];

    }
}
