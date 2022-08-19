<?php

namespace Pittacusw\Touchef;

use Freshwork\ChileanBundle\Rut;
use Illuminate\Support\Facades\Http;

class Touchef {

  protected $url = 'https://api.touchef.cl/v1/';
  protected $http;

  public function __construct($rut = NULL) {
    if (!empty($rut)) {
      config(['touchef.rut' => $rut]);
    }
    $this->http = Http::timeout(60)
                      ->withHeaders([
                                     'business' => Rut::parse(config('touchef.rut'))
                                                      ->format(Rut::FORMAT_ESCAPED)
                                    ])
                      ->withToken(config('touchef.token'));
  }

  public function login($password) {
    $response = $this->http->post($this->url . 'login', [
     'password' => $password
    ])
                           ->collect();
    $file     = base_path('.env');
    if (file_exists($file)) {
      $env = file_get_contents($file);
      if (str_contains($env, 'TOUCHEF_RUT')) {
        $env .= "\nTOUCHEF_RUT=" . config('touchef.rut') . "\n";
      } else {
        $env = str_replace('TOUCHEF_RUT=' . env('TOUCHEF_RUT'), 'TOUCHEF_RUT=' . $rut, $env);
      }
      if (str_contains($env, 'TOUCHEF_TOKEN')) {
        $env .= "\nTOUCHEF_TOKEN=" . $response['records'] . "\n";
      } else {
        $env = str_replace('TOUCHEF_TOKEN=' . env('TOUCHEF_TOKEN'), 'TOUCHEF_TOKEN=' . $response['records'], $env);
      }

      file_put_contents($file, $env);
    }
  }

  public function caf() {
    return $this->get('folios');
  }

  public function certificate() {
    return $this->get('certificado');
  }

  public function sii_status($id) {
    return $this->get('ventas/sii-status/' . $id);
  }

  public function provider_status($id) {
    return $this->get('ventas/provider-status/' . $id);
  }

  public function sales($year, $month) {
    return $this->get("ventas/$year/$month");
  }

  public function sales_summary($year, $month) {
    return $this->get("ventas/resumen/$year/$month");
  }

  public function sale($uuid) {
    return $this->get("ventas/$uuid");
  }

  public function expenses($year, $month) {
    return $this->get("compras/$year/$month");
  }

  public function expenses_summary($year, $month) {
    return $this->get("compras/resumen/$year/$month");
  }

  public function expense($uuid) {
    return $this->get("compras/$uuid");
  }

  public function pending() {
    return $this->get('pendientes');
  }

  public function info() {
    return $this->get('cliente');
  }

  public function counties() {
    return $this->get('counties');
  }

  public function economic_activities() {
    return $this->get('actividades');
  }

  public function get_data($rut) {
    return $this->get('consulta-datos/' . $rut);
  }

  public function update_certificate($path, $password) {
    $file = base64_encode(file_get_contents($path));

    return $this->http->post($this->url . 'certificate', [
     'certificate' => $file,
     'pass'        => $password
    ])
                      ->object()
     ->records;
  }

  public function send_email($number, $type) {
    $this->http->post($this->url . 'enviar-dte/' . $number . '/' . $type);
  }

  public function create_document(int $documents_type, $date, array $client, array $details, $expiring_date = NULL, array $transport = [], array $globals = [], array $references = [], bool $draft = FALSE) {
    $request = $this->http->post($this->url . 'ventas', [
     'documents_type' => $documents_type,
     'date'           => $date,
     'client'         => $client,
     'details'        => $details,
     'expiring_date'  => $expiring_date,
     'transport'      => $transport,
     'globals'        => $globals,
     'references'     => $references,
     'draft'          => $draft
    ]);
    $object  = $request->object();;
    abort_if(!isset($object->records), $request->status());

    return $object->records;
  }

  public function manage_expense($id, $status) {
    return $this->http->put($this->url . "compras/$id", [
     'type' => $status
    ])
                      ->object()
     ->records;
  }

  public function update_client($county, $economic_activity, $name, $rut, $activity, $address, $resolution_date, $resolution_number, $email) {
    return $this->http->put($this->url . 'cliente', [
     'county_id'            => $county,
     'economic_activity_id' => $economic_activity,
     'name'                 => $name,
     'rut'                  => $rut,
     'activity'             => $activity,
     'address'              => $address,
     'resolution_date'      => $resolution_date,
     'resolution_number'    => $resolution_number,
     'email'                => $email
    ])
                      ->object()
     ->records;
  }

  protected function get($route) {
    $request = $this->http->get($this->url . $route);
    $object  = $request->object();
    abort_if(!isset($object->records), $request->status());

    return $object->records;
  }

}