<?php

use App\Models\Cart;
use App\Models\HomepageService;
use App\Models\Order;
use App\Models\User;
use App\Services\CheckoutOrderPlacementService;
use App\Services\DocumentNumberService;
use App\Services\HomepageServiceService;
use App\Services\OrderStatusService;
use App\Services\RefundService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $action, $encodedPayload, $barrierDirectory, $workerId] = $argv + [null, null, null, null, null];
$payload = json_decode(base64_decode((string) $encodedPayload, true), true, flags: JSON_THROW_ON_ERROR);
$database = (string) DB::connection()->getDatabaseName();

if (app()->environment() !== 'testing'
    || DB::getDriverName() !== 'mysql'
    || ! preg_match('/test|testing/i', $database)) {
    fwrite(STDERR, 'Concurrency workers require APP_ENV=testing and a clearly named MySQL test database.');
    exit(2);
}

file_put_contents($barrierDirectory.DIRECTORY_SEPARATOR."ready-{$workerId}", 'ready', LOCK_EX);
$deadline = hrtime(true) + 30_000_000_000;

while (! is_file($barrierDirectory.DIRECTORY_SEPARATOR.'release')) {
    if (hrtime(true) >= $deadline) {
        fwrite(STDERR, 'The concurrency release barrier timed out.');
        exit(3);
    }

    usleep(10_000);
}

try {
    $result = match ($action) {
        'document_number' => DB::transaction(fn () => [
            'successful' => true,
            'number' => app(DocumentNumberService::class)->next($payload['document_type']),
        ]),
        'checkout' => checkout($payload),
        'process_order' => processOrder($payload),
        'activate_homepage_service' => activateHomepageService($payload),
        'refund' => refund($payload),
        default => throw new RuntimeException("Unknown concurrency action [{$action}]."),
    };
} catch (ValidationException $exception) {
    $result = [
        'successful' => false,
        'validation_errors' => $exception->errors(),
    ];
} catch (Throwable $exception) {
    $result = [
        'successful' => false,
        'exception' => $exception::class,
        'message' => $exception->getMessage(),
    ];
}

fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR));

/** @param array<string, mixed> $payload */
function checkout(array $payload): array
{
    $cart = Cart::query()->findOrFail($payload['cart_id']);
    $customer = User::query()->findOrFail($payload['customer_id']);
    $result = app(CheckoutOrderPlacementService::class)->place($cart, $payload['checkout_data'], $customer);

    return [
        'successful' => $result->successful,
        'failure_codes' => $result->failureCodes(),
        'order_id' => $result->order?->getKey(),
    ];
}

/** @param array<string, mixed> $payload */
function processOrder(array $payload): array
{
    $order = app(OrderStatusService::class)->process(Order::query()->findOrFail($payload['order_id']));

    return [
        'successful' => true,
        'order_id' => $order->getKey(),
        'status' => $order->status,
    ];
}

/** @param array<string, mixed> $payload */
function refund(array $payload): array
{
    $refund = app(RefundService::class)->create(
        Order::query()->findOrFail($payload['order_id']),
        User::query()->findOrFail($payload['admin_id']),
        $payload['data'],
        $payload['idempotency_key'],
    );

    return ['successful' => true, 'refund_id' => $refund->id];
}

/** @param array<string, mixed> $payload */
function activateHomepageService(array $payload): array
{
    $service = app(HomepageServiceService::class)->update(
        HomepageService::query()->findOrFail($payload['service_id']),
        $payload['data']
    );

    return [
        'successful' => true,
        'service_id' => $service->getKey(),
    ];
}
