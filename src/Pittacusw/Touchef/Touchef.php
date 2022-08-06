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
  $this->http = Http::withHeaders([
                                   'business' => Rut::parse(config('touchef.rut'))
                                                    ->format(Rut::FORMAT_ESCAPED)
                                  ])
                    ->withToken(config('touchef.token'));
 }

 public function login($password) {
  $response = $this->http->post($this->url . 'login', [
   'password' => $password
  ])
                         ->object();
  $file     = base_path('.env');
  if (file_exists($file)) {
   $env = file_get_contents($file);
   if (strpos($env, 'TOUCHEF_RUT') === FALSE) {
    $env .= "\nTOUCHEF_RUT=" . config('touchef.rut') . "\n";
   } else {
    $env = str_replace('TOUCHEF_RUT=' . env('TOUCHEF_RUT'), 'TOUCHEF_RUT=' . $rut, $env);
   }
   if (strpos($env, 'TOUCHEF_TOKEN') === FALSE) {
    $env .= "\nTOUCHEF_TOKEN=" . $response->records . "\n";
   } else {
    $env = str_replace('TOUCHEF_TOKEN=' . env('TOUCHEF_TOKEN'), 'TOUCHEF_TOKEN=' . $response->records, $env);
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

 public function sale($id) {
  return $this->get("ventas/$id");
 }

 public function expenses($year, $month) {
  return $this->get("compras/$year/$month");
 }

 public function expense($id) {
  return $this->get("compras/$id");
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

 public function update_certificate($path, $password) {
  $file = base64_encode(file_get_contents($path));

  return $this->http->post('certificate', [
   'certificate' => $file,
   'pass'        => $password
  ])
                    ->object()
   ->records;
 }

 public function create_document(int $documents_type, $date, array $client, array $details, $expiring_date = NULL, array $transport = [], array $globals = [], array $references = [], bool $draft = FALSE) {
  return $this->http->post('ventas', [
   'documents_type' => $documents_type,
   'date'           => $date,
   'client'         => $client,
   'details'        => $details,
   'expiring_date'  => $expiring_date,
   'transport'      => $transport,
   'globals'        => $globals,
   'references'     => $references,
   'draft'          => $draft
  ])
                    ->object()
   ->records;
 }

 public function manage_expense($id, $status) {
  return $this->http->put("compras/$id", [
   'type' => $status
  ])
                    ->object()
   ->records;
 }

 public function update_client($county, $economic_activity, $name, $rut, $activity, $address, $resolution_date, $resolution_number, $email) {
  return $this->http->put('cliente', [
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
  return $this->http->get('pendientes')
                    ->object()
   ->records;
 }

}