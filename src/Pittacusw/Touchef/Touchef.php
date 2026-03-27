<?php

namespace Pittacusw\Touchef;

use Freshwork\ChileanBundle\Rut;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class Touchef
{
    protected string $url = 'https://api.frozenbox.cl/v1/';

    protected PendingRequest $http;

    public function __construct(?string $rut = null)
    {
        if ($rut !== null && $rut !== '') {
            config(['touchef.rut' => $rut]);
        }

        $this->refreshHttpClient();
    }

    public function login(string $password): void
    {
        $token = $this->post('login', [
            'password' => $password,
        ]);

        $this->syncToken($token);
        $this->persistEnvValue('TOUCHEF_RUT', (string) config('touchef.rut'));
        $this->persistEnvValue('TOUCHEF_TOKEN', $token);
    }

    public function logout(): void
    {
        $this->request('post', 'logout');

        $this->syncToken(null);
        $this->persistEnvValue('TOUCHEF_TOKEN', '');
    }

    public function refresh(): string
    {
        $token = $this->post('refresh-token');

        $this->syncToken($token);
        $this->persistEnvValue('TOUCHEF_TOKEN', $token);

        return $token;
    }

    public function caf(): mixed
    {
        return $this->get('folios');
    }

    public function certificate(): mixed
    {
        return $this->get('certificado');
    }

    public function sii_status(int $id): mixed
    {
        return $this->get('ventas/sii-status/' . $id);
    }

    public function provider_status(int $id): mixed
    {
        return $this->get('ventas/provider-status/' . $id);
    }

    public function sales(int $year, int $month): mixed
    {
        return $this->get("ventas/$year/$month");
    }

    public function sales_summary(int $year, int $month): mixed
    {
        return $this->get("ventas/resumen/$year/$month");
    }

    public function sale(string $uuid): mixed
    {
        return $this->get("ventas/$uuid");
    }

    public function show_by_number(int $type, int $number): mixed
    {
        return $this->get("ventas/show/$type/$number");
    }

    public function expenses(int $year, int $month): mixed
    {
        return $this->get("compras/$year/$month");
    }

    public function expenses_summary(int $year, int $month): mixed
    {
        return $this->get("compras/resumen/$year/$month");
    }

    public function expense(string $uuid): mixed
    {
        return $this->get("compras/$uuid");
    }

    public function pending(): mixed
    {
        return $this->get('pendientes');
    }

    public function info(): mixed
    {
        return $this->get('cliente');
    }

    public function counties(): mixed
    {
        return $this->get('comunas');
    }

    public function economic_activities(): mixed
    {
        return $this->get('actividades');
    }

    public function get_data(string $rut): mixed
    {
        return $this->get('consulta-datos/' . $rut);
    }

    public function update_certificate(string $path, string $password): mixed
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException("Unable to read certificate file [{$path}].");
        }

        $file = base64_encode($contents);

        return $this->post('certificado', [
            'certificate' => $file,
            'pass' => $password,
        ]);
    }

    public function send_email(int $number, int $type): void
    {
        $this->request('post', 'enviar-dte/' . $number . '/' . $type);
    }

    public function create_document(
        int $document_type,
        string $issued_at,
        array $lines,
        string $mode = 'issue',
        ?array $receiver = null,
        array $global_adjustments = [],
        array $references = [],
        array $transport = [],
        array $payment = [],
        array $notifications = [],
    ): mixed {
        $payload = [
            'document_type' => $document_type,
            'issued_at' => $issued_at,
            'lines' => $lines,
            'mode' => $mode,
        ];

        if ($receiver !== null) {
            $payload['receiver'] = $receiver;
        }

        if ($global_adjustments !== []) {
            $payload['global_adjustments'] = $global_adjustments;
        }

        if ($references !== []) {
            $payload['references'] = $references;
        }

        if ($transport !== []) {
            $payload['transport'] = $transport;
        }

        if ($payment !== []) {
            $payload['payment'] = $payment;
        }

        if ($notifications !== []) {
            $payload['notifications'] = $notifications;
        }

        return $this->post('ventas', $payload);
    }

    public function queue_documents(array $documents): object
    {
        $request = $this->request('post', 'ventas/cola', [
            'documents' => $documents,
        ]);
        $object = $request->object();

        abort_if(! is_object($object) || ! isset($object->queued), $request->status());

        return $object;
    }

    public function get_pdf(int $document_type, int $number): mixed
    {
        return $this->post('ventas/pdf', [
            'document_type' => $document_type,
            'number' => $number,
        ]);
    }

    public function track_id(int $id): mixed
    {
        return $this->get('ventas/track-id/' . $id);
    }

    public function manage_expense(int $id, string $status): mixed
    {
        return $this->put("compras/$id", [
            'type' => $status,
        ]);
    }

    public function update_client(
        int $county,
        int $economic_activity,
        string $name,
        string $rut,
        string $activity,
        string $address,
        string $resolution_date,
        int $resolution_number,
        string $email,
    ): mixed {
        return $this->put('cliente', [
            'county_id' => $county,
            'economic_activity_id' => $economic_activity,
            'name' => $name,
            'rut' => $rut,
            'activity' => $activity,
            'address' => $address,
            'resolution_date' => $resolution_date,
            'resolution_number' => $resolution_number,
            'email' => $email,
        ]);
    }

    public function create_client(
        int $county_id,
        int $economic_activity_id,
        string $name,
        string $rut,
        string $activity,
        string $address,
        string $resolution_date,
        int $resolution_number,
        string $email,
        string $password,
        ?string $website = null,
    ): mixed {
        $payload = [
            'county_id' => $county_id,
            'economic_activity_id' => $economic_activity_id,
            'name' => $name,
            'rut' => $rut,
            'activity' => $activity,
            'address' => $address,
            'resolution_date' => $resolution_date,
            'resolution_number' => $resolution_number,
            'email' => $email,
            'password' => $password,
        ];

        if ($website !== null) {
            $payload['website'] = $website;
        }

        return $this->post('cliente', $payload);
    }

    protected function get(string $route): mixed
    {
        return $this->extractRecords($this->request('get', $route));
    }

    protected function post(string $route, array $data = []): mixed
    {
        return $this->extractRecords($this->request('post', $route, $data));
    }

    protected function put(string $route, array $data = []): mixed
    {
        return $this->extractRecords($this->request('put', $route, $data));
    }

    protected function request(string $method, string $route, array $data = []): Response
    {
        $request = $this->http->{$method}($this->url . $route, $data);

        abort_if($request->failed(), $request->status());

        return $request;
    }

    protected function extractRecords(Response $request): mixed
    {
        $object = $request->object();

        abort_if(! is_object($object) || ! isset($object->records), $request->status());

        return $object->records;
    }

    protected function syncToken(?string $token): void
    {
        config(['touchef.token' => $token]);

        $this->refreshHttpClient();
    }

    protected function refreshHttpClient(): void
    {
        $request = Http::timeout(60);
        $rut = config('touchef.rut');
        $token = config('touchef.token');

        if (! empty($rut)) {
            $request = $request->withHeaders([
                'business' => Rut::parse($rut)->format(Rut::FORMAT_ESCAPED),
            ]);
        }

        if (! empty($token)) {
            $request = $request->withToken($token);
        }

        $this->http = $request;
    }

    protected function persistEnvValue(string $key, string $value): void
    {
        $file = base_path('.env');

        if (! file_exists($file)) {
            return;
        }

        $env = file_get_contents($file);
        $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';
        $line = $key . '=' . $value;

        if (preg_match($pattern, $env) === 1) {
            $env = preg_replace($pattern, $line, $env) ?? $env;
        } else {
            $suffix = $env === '' || str_ends_with($env, PHP_EOL) ? '' : PHP_EOL;
            $env .= $suffix . $line . PHP_EOL;
        }

        file_put_contents($file, $env, LOCK_EX);
    }
}
