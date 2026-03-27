<?php

namespace Pittacusw\Touchef\Tests\Unit;

use Illuminate\Support\Facades\Http;
use Pittacusw\Touchef\Touchef;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TouchefWrapperTest extends TestCase
{
    protected string $originalBasePath;

    /**
     * @var  array<int, string>
     */
    protected array $temporaryBasePaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalBasePath = base_path();

        config([
            'touchef.rut'   => '76111111-1',
            'touchef.token' => 'token-inicial',
        ]);
    }

    protected function tearDown(): void
    {
        $this->app->setBasePath($this->originalBasePath);

        foreach ($this->temporaryBasePaths as $temporaryBasePath) {
            $envPath = $temporaryBasePath . DIRECTORY_SEPARATOR . '.env';

            if (file_exists($envPath)) {
                @unlink($envPath);
            }

            if (is_dir($temporaryBasePath)) {
                @rmdir($temporaryBasePath);
            }
        }

        parent::tearDown();
    }

    public function test_login_persists_credentials_and_refreshes_the_runtime_token() : void {
        $basePath = $this->makeTemporaryBasePath("APP_NAME=Testing\n");
        $this->app->setBasePath($basePath);

        Http::fake([
            'https://api.frozenbox.cl/v1/login' => Http::response([
                'records' => 'token-nuevo',
            ], 200),
            'https://api.frozenbox.cl/v1/cliente' => Http::response([
                'records' => [
                    'name' => 'Demo',
                ],
            ], 200),
        ]);

        $touchef = new Touchef;
        $touchef->login('super-secret');
        $info = $touchef->info();

        $this->assertSame('Demo', $info->name);
        $this->assertSame('token-nuevo', config('touchef.token'));

        $env = file_get_contents($basePath . DIRECTORY_SEPARATOR . '.env');

        $this->assertStringContainsString('TOUCHEF_RUT=76111111-1', $env);
        $this->assertStringContainsString('TOUCHEF_TOKEN=token-nuevo', $env);

        Http::assertSent(function ($request) : bool {
            if ($request->url() !== 'https://api.frozenbox.cl/v1/cliente') {
                return FALSE;
            }

            return $this->headersContain($request, 'Bearer token-nuevo')
                && $this->headersContain($request, '761111111');
        });
    }

    public function test_create_document_uses_the_current_api_payload_shape() : void {
        Http::fake([
            'https://api.frozenbox.cl/v1/ventas' => Http::response([
                'records' => [
                    'id' => 45,
                ],
            ], 200),
        ]);

        $document = (new Touchef)->create_document(
            document_type: 33,
            issued_at: '2025-03-15',
            lines: [[
                'name'       => 'Servicio',
                'quantity'   => 1,
                'unit_price' => 1000,
            ]],
            mode: 'issue',
            receiver: [
                'rut'      => '98765432-1',
                'name'     => 'Cliente SpA',
                'activity' => 'Servicios',
                'address'  => 'Av. Siempre Viva 123',
                'county'   => 'Santiago',
            ],
            global_adjustments: [[
                'mode'         => 'discount',
                'reason'       => 'Promo',
                'value_type'   => 'amount',
                'value'        => 100,
                'tax_category' => 'taxable',
            ]],
            references: [[
                'document_type' => '33',
                'number'        => '1200',
                'date'          => '2025-03-01',
                'code'          => '1',
                'reason'        => 'Anula factura anterior',
            ]],
            transport: [
                'dispatch_address' => 'Bodega Central',
                'dispatch_county'  => 'Maipu',
            ],
            payment: [
                'due_date' => '2025-04-15',
            ],
            notifications: ['cliente@empresa.cl'],
        );

        $this->assertSame(45, $document->id);

        Http::assertSent(function ($request) : bool {
            if ($request->url() !== 'https://api.frozenbox.cl/v1/ventas') {
                return FALSE;
            }

            $data = $request->data();

            return $data['document_type'] === 33
                && $data['issued_at'] === '2025-03-15'
                && $data['mode'] === 'issue'
                && isset($data['receiver'], $data['lines'], $data['global_adjustments'], $data['references'], $data['transport'], $data['payment'], $data['notifications'])
                && !isset($data['documents_type'], $data['date'], $data['client'], $data['details'], $data['expiring_date'], $data['globals'], $data['draft']);
        });
    }

    public function test_it_uses_the_updated_endpoints_and_special_response_shapes() : void {
        $certificatePath = tempnam(sys_get_temp_dir(), 'pfx');
        file_put_contents($certificatePath, 'certificate-binary');

        Http::fake([
            'https://api.frozenbox.cl/v1/comunas' => Http::response([
                'records' => [
                    ['id' => 1, 'name' => 'Santiago'],
                ],
            ], 200),
            'https://api.frozenbox.cl/v1/certificado' => Http::response([
                'records' => [
                    'updated' => TRUE,
                ],
            ], 200),
            'https://api.frozenbox.cl/v1/ventas/cola' => Http::response([
                'queued' => 2,
            ], 200),
            'https://api.frozenbox.cl/v1/ventas/pdf' => Http::response([
                'records' => [
                    'document_type' => 33,
                    'number'        => 99,
                    'pdf'           => 'base64-demo',
                ],
            ], 200),
            'https://api.frozenbox.cl/v1/ventas/show/33/99' => Http::response([
                'records' => [
                    'uuid' => 'demo-uuid',
                ],
            ], 200),
            'https://api.frozenbox.cl/v1/ventas/track-id/77' => Http::response([
                'records' => [
                    'track_id' => '123456789012345',
                ],
            ], 200),
            'https://api.frozenbox.cl/v1/cliente' => Http::response([
                'records' => [
                    'id' => 9,
                ],
            ], 200),
        ]);

        $touchef = new Touchef;

        try {
            $counties    = $touchef->counties();
            $certificate = $touchef->update_certificate($certificatePath, 'secret');
            $queued      = $touchef->queue_documents([
                ['document_type' => 33],
                ['document_type' => 39],
            ]);
            $pdf      = $touchef->get_pdf(33, 99);
            $document = $touchef->show_by_number(33, 99);
            $track    = $touchef->track_id(77);
            $client   = $touchef->create_client(
                county_id: 1,
                economic_activity_id: 2,
                name: 'Nueva Empresa SpA',
                rut: '12345678-9',
                activity: 'Servicios de software',
                address: 'Av. Providencia 1234',
                resolution_date: '2020-01-15',
                resolution_number: 80,
                email: 'admin@nuevaempresa.cl',
                password: 'securepassword123',
                website: 'https://nuevaempresa.cl',
            );
        } finally {
            @unlink($certificatePath);
        }

        $this->assertSame('Santiago', $counties[0]->name);
        $this->assertTrue($certificate->updated);
        $this->assertSame(2, $queued->queued);
        $this->assertSame('base64-demo', $pdf->pdf);
        $this->assertSame('demo-uuid', $document->uuid);
        $this->assertSame('123456789012345', $track->track_id);
        $this->assertSame(9, $client->id);

        Http::assertSent(function ($request) : bool {
            if ($request->url() !== 'https://api.frozenbox.cl/v1/certificado') {
                return FALSE;
            }

            return $request['certificate'] === base64_encode('certificate-binary')
                && $request['pass'] === 'secret';
        });

        Http::assertSent(function ($request) : bool {
            return $request->url() === 'https://api.frozenbox.cl/v1/comunas';
        });
    }

    public function test_refresh_logout_send_email_and_put_helpers_follow_the_api_contract() : void {
        $basePath = $this->makeTemporaryBasePath("TOUCHEF_TOKEN=token-inicial\n");
        $this->app->setBasePath($basePath);

        Http::fake([
            'https://api.frozenbox.cl/v1/refresh-token' => Http::response([
                'records' => 'token-rotado',
            ], 200),
            'https://api.frozenbox.cl/v1/logout'           => Http::response([], 204),
            'https://api.frozenbox.cl/v1/enviar-dte/99/33' => Http::response([], 200),
            'https://api.frozenbox.cl/v1/compras/42'       => Http::response([
                'records' => [
                    'status' => 'ACD',
                ],
            ], 200),
            'https://api.frozenbox.cl/v1/cliente' => Http::response([
                'records' => [
                    'updated' => TRUE,
                ],
            ], 200),
        ]);

        $touchef = new Touchef;

        $token   = $touchef->refresh();
        $expense = $touchef->manage_expense(42, 'ACD');
        $client  = $touchef->update_client(
            county: 1,
            economic_activity: 5,
            name: 'Mi Empresa SpA',
            rut: '12345678-9',
            activity: 'Servicios de software',
            address: 'Av. Providencia 1234',
            resolution_date: '2020-01-15',
            resolution_number: 80,
            email: 'admin@miempresa.cl',
        );
        $touchef->send_email(99, 33);
        $touchef->logout();

        $this->assertSame('token-rotado', $token);
        $this->assertSame('ACD', $expense->status);
        $this->assertTrue($client->updated);
        $this->assertNull(config('touchef.token'));
        $this->assertStringContainsString('TOUCHEF_TOKEN=', file_get_contents($basePath . DIRECTORY_SEPARATOR . '.env'));

        Http::assertSent(function ($request) : bool {
            if ($request->url() !== 'https://api.frozenbox.cl/v1/compras/42') {
                return FALSE;
            }

            return $request->method() === 'PUT'
                && $request['type'] === 'ACD'
                && $this->headersContain($request, 'Bearer token-rotado');
        });

        Http::assertSent(function ($request) : bool {
            return $request->url() === 'https://api.frozenbox.cl/v1/enviar-dte/99/33'
                && $request->method() === 'POST';
        });
    }

    public function test_the_service_provider_registers_the_wrapper_binding() : void {
        $this->assertInstanceOf(Touchef::class, $this->app->make('Touchef'));
        $this->assertInstanceOf(Touchef::class, $this->app->make(Touchef::class));
    }

    public function test_it_aborts_when_a_mutation_request_fails() : void {
        Http::fake([
            'https://api.frozenbox.cl/v1/cliente' => Http::response([
                'errors' => [
                    'email' => ['The email field is required.'],
                ],
            ], 422),
        ]);

        $this->expectException(HttpException::class);

        (new Touchef)->update_client(
            county: 1,
            economic_activity: 5,
            name: 'Mi Empresa SpA',
            rut: '12345678-9',
            activity: 'Servicios de software',
            address: 'Av. Providencia 1234',
            resolution_date: '2020-01-15',
            resolution_number: 80,
            email: 'admin@miempresa.cl',
        );
    }

    public function test_update_certificate_throws_a_runtime_exception_when_the_file_cannot_be_read(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to read certificate file');

        (new Touchef)->update_certificate(__DIR__ . DIRECTORY_SEPARATOR . 'missing-demo.pfx', 'secret');
    }

    public function test_it_aborts_when_a_success_response_does_not_include_records(): void
    {
        Http::fake([
            'https://api.frozenbox.cl/v1/cliente' => Http::response([
                'data' => null,
            ], 200),
        ]);

        $this->expectException(HttpException::class);

        (new Touchef)->info();
    }

    protected function makeTemporaryBasePath(string $envContents): string
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'touchef-wrapper-' . uniqid();

        mkdir($directory);
        file_put_contents($directory . DIRECTORY_SEPARATOR . '.env', $envContents);
        $this->temporaryBasePaths[] = $directory;

        return $directory;
    }

    protected function headersContain(object $request, string $value): bool
    {
        foreach ($request->headers() as $headerValues) {
            if (in_array($value, $headerValues, TRUE)) {
                return TRUE;
            }
        }

        return FALSE;
    }
}
