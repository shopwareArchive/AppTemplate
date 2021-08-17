# Script
## Step 1
Clone this repo into you shopware docker code dir, into the folder `appdaysdemo`
```shell
git clone git@github.com:shopware/AppTemplate.git appdaysdemo
```
folder name needs to match to use the manifest file provided here, otherwise the urls won't match
Notice: the folder name should be lowercase, as otherwise it leads to networking problems

start containers by
```shell
swdc up
```

install composer dependencies
```shell
swdc pshell appdaysdemo
composer install
```

adjust .env file to following content
```dotenv
APP_ENV=dev
APP_NAME=AppDaysDemo
APP_SECRET=myAppSecret
APP_URL=http://appdaysdemo.dev.localhost
DATABASE_URL=mysql://root:root@mysql:3306/appdaysdemo
```

create `appdaysdemo` DB manually over `http://db.localhost/` -> necessary because DB will be created by `swdc build` command automatically, which is only for shopware projects

run migrations:
```shell
swdc pshell appdaysdemo
bin/console doctrine:migrations:migrate
```

inside platform create `AppDaysDemo` folder under `custom/apps` and create following `manifest.xml` in that folder
```xml
<?xml version="1.0" encoding="UTF-8"?>
<manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
          xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/platform/master/src/Core/Framework/App/Manifest/Schema/manifest-1.0.xsd">
    <meta>
        <name>AppDaysDemo</name>
        <label>Demo App for Stripe Payments</label>
        <label lang="de-DE">Demo App für Stripe Payments</label>
        <description>Example App - Do not use in production</description>
        <description lang="de-DE">Beispiel App - Nicht im Produktivbetrieb verwenden</description>
        <author>shopware AG</author>
        <copyright>(c) by shopware AG</copyright>
        <version>1.0.0</version>
        <license>MIT</license>
    </meta>

    <setup>
        <registrationUrl>http://appdaysdemo.dev.localhost/register</registrationUrl> <!-- replace local url with real one -->
        <secret>myAppSecret</secret>
    </setup>
</manifest>
```

install and activate app
```shell
swdc pshell platform
bin/console app:install --activate AppDaysDemo
```

## Step 2

Generate new migration:
```shell
swdc pshell appdaysdemo
bin/console doctrine:migrations:generate
```

update migration with following content:
```php
    public function up(Schema $schema): void
    {
        $sql = <<<'SQL'
        CREATE TABLE `order` (
    		transaction_id char(64)  		NOT NULL PRIMARY KEY,
            order_id       char(64)  		NOT NULL,
			shop_id        varchar(255)  	NOT NULL,
			status         varchar(255)  	NULL,
			session_id     varchar(255)  	NULL,
			return_url     varchar(4096)	NULL,
			CONSTRAINT `fk.order.shop_id`
                    FOREIGN KEY (`shop_id`)
                    REFERENCES `shop` (`shop_id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE
        )
        DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL;

        $this->addSql($sql);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE `order`');
    }
```

run migration
```shell
swdc pshell appdaysdemo
bin/console doctrine:migrations:migrate
```

create class `OrderRepository` in `src/Repository` with
```php
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function fetchOrder(string $transactionId): ?array
    {
        $value = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('`order`')
            ->where('transaction_id = :id')
            ->setParameter('id', $transactionId)
            ->execute()
            ->fetchAssociative();

        return $value !== false ? $value : null;
    }

    public function fetchOrdersByOrderId(string $orderId): array
    {
        return $this->connection->createQueryBuilder()
            ->select('*')
            ->from('`order`')
            ->where('order_id = :id')
            ->setParameter('id', $orderId)
            ->execute()
            ->fetchAllAssociative();
    }

    public function fetchColumn(string $column, string $transactionId): ?string
    {
        $value = $this->connection->createQueryBuilder()
            ->select(sprintf('`%s`', $column))
            ->from('`order`')
            ->where('transaction_id = :id')
            ->setParameter('id', $transactionId)
            ->execute()
            ->fetchColumn();

        return $value !== false ? $value : null;
    }

    public function insertNewOrder(array $data): void
    {
        $this->connection->insert('`order`', $data);
    }

    public function updateOrderStatus(string $status, string $transactionId): void
    {
        $this->connection->update(
            '`order`',
            ['status' => $status],
            ['transaction_id' => $transactionId]
        );
    }
```

## Step 3

Require Stripe php sdk and symfony twig pack:
```shell
swdc pshell appdaysdemo
composer require stripe/stripe-php
composer require symfony/twig-pack
```

Add stripe demo secrets to .env file
```dotenv
STRIPE_SECRET_KEY=sk_test_4eC39HqLyjWDarjtT1zdp7dc
STRIPE_PUBLIC_KEY=pk_test_TYooMQauvdEDq54NiTphI7jx
```

Configure stripe php client in `public/index.php`:
```php
Stripe::setApiKey($_SERVER['STRIPE_SECRET_KEY']);
```

Create SessionService in `src/Service/Stripe`, copy source from github

Create class `PaymentController` in `/src/Controller` which extends `AbstractController` with:
```php
    private ShopRepository $shopRepository;
    private OrderRepository $orderRepository;
    private SessionService $sessionService;

    public function __construct(ShopRepository $shopRepository, OrderRepository $orderRepository, SessionService $sessionService)
    {
        $this->shopRepository = $shopRepository;
        $this->orderRepository = $orderRepository;
        $this->sessionService = $sessionService;
    }

    /**
     * @Route("/payment/process", name="payment.process", methods={"POST"})
     */
    public function paymentStarted(Request $request): JsonResponse
    {
        $content = \json_decode($request->getContent(), true);

        $session = $this->sessionService->startSession(
            $content['order'],
            $content['orderTransaction'],
            $this->generateUrl(
                'payment.redirect.success',
                [
                    'transaction' => $content['orderTransaction']['id'],
                ],
                RouterInterface::ABSOLUTE_URL
            ),
            $this->generateUrl(
                'payment.redirect.cancel',
                [
                    'transaction' => $content['orderTransaction']['id'],
                ],
                RouterInterface::ABSOLUTE_URL
            )
        );

        $this->orderRepository->insertNewOrder([
            'transaction_id' => $content['orderTransaction']['id'],
            'order_id' => $content['order']['id'],
            'shop_id' => $content['source']['shopId'],
            'session_id' => $session->id,
            'return_url' => $content['returnUrl'],
        ]);

        return $this->sign(
            [
                'redirectUrl' => $this->generateUrl(
                    'payment.pay',
                    [
                        'transaction' => $content['orderTransaction']['id'],
                    ],
                    RouterInterface::ABSOLUTE_URL
                ),
            ],
            $content['source']['shopId']
        );
    }

    /**
     * @Route("/payment/finalize", name="payment.finalize", methods={"POST"})
     */
    public function paymentFinalized(Request $request): Response
    {
        $content = \json_decode($request->getContent(), true);

        $status = $this->orderRepository->fetchColumn('status', $content['orderTransaction']['id']);

        return $this->sign(
            ['status' => $status ?? 'fail'],
            $content['source']['shopId']
        );
    }

    private function sign(array $content, string $shopId): JsonResponse
    {
        $response = new JsonResponse($content);

        $shop = $this->shopRepository->getShopFromId($shopId);

        $hmac = \hash_hmac('sha256', $response->getContent(), $shop->getShopSecret());
        $response->headers->set('shopware-app-signature', $hmac);

        return $response;
    }
```

Create class `PayController` in `src/Controller` which extends `AbstractController`
```php
    private OrderRepository $orderRepository;

    public function __construct(OrderRepository $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    /**
     * @Route("/pay/{transaction}", name="payment.pay", methods={"GET"})
     */
    public function userForwarded(string $transaction): Response
    {
        $sessionId = $this->orderRepository->fetchColumn('session_id', $transaction);

        if ($sessionId === null) {
            throw new BadRequestHttpException('Invalid transaction');
        }

        return $this->render('base.html.twig', ['sessionId' => $sessionId, 'publicApiKey' => $_SERVER['STRIPE_PUBLIC_KEY']]);
    }
```

create `templates/base.html.twig` template with:
```twig
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{% block title %}Redirecting ...{% endblock %}</title>
    {% block stylesheets %}{% endblock %}
</head>

<body onload="sendReadyState()">
{% block body %}
    <p>Redirecting ...</p>
{% endblock %}
{% block javascripts %}
    <script src="https://js.stripe.com/v3/"></script>
{% endblock %}
<script>
    function sendReadyState() {
        // Create an instance of the Stripe object with your publishable API key
        var stripe = Stripe("{{ publicApiKey }}");
        var sessionId = "{{ sessionId }}";
        return stripe.redirectToCheckout({ sessionId });
    }
</script>
</body>
```

create `RedirectController` in `src/Controller` which extends `AbstractController` with
```php
    private OrderRepository $orderRepository;
    private SessionService $sessionService;

    public function __construct(OrderRepository $orderRepository, SessionService $sessionService)
    {
        $this->orderRepository = $orderRepository;
        $this->sessionService = $sessionService;
    }

    /**
     * @Route("/redirect/success/{transaction}", name="payment.redirect.success", methods={"GET"})
     */
    public function redirectSuccess(string $transaction): Response
    {
        $order = $this->updateStatus($transaction);

        return new RedirectResponse($order['return_url']);
    }

    /**
     * @Route("/redirect/cancel/{transaction}", name="payment.redirect.cancel", methods={"GET"})
     */
    public function redirectCancel(string $transaction): Response
    {
        $order = $this->updateStatus($transaction, 'cancel');

        return new RedirectResponse($order['return_url']);
    }

    private function updateStatus(string $transactionId, ?string $newStatus = null): array
    {
        $order = $this->orderRepository->fetchOrder($transactionId);

        if ($order === null) {
            throw new BadRequestHttpException('Invalid session');
        }

        if ($newStatus === null) {
            $newStatus = $this->sessionService->getPaymentStatusForSession($order['session_id']);
        }

        $this->orderRepository->updateOrderStatus($newStatus, $transactionId);

        return $order;
    }
```

add payment info to manifest in platform manifest file:
```xml
    <payments>
        <payment-method>
            <identifier>stripe</identifier>
            <name>Stripe payment</name>
            <name lang="de-DE">Zahlen mit Stripe</name>
            <description>This payment will be handled with Stripe - Do not use in production</description>
            <description lang="de-DE">Diese Zahlung wird mit Stripe durchgeführt - Nicht im Produktivbetrieb verwenden</description>
            <pay-url>http://appdaysdemo.dev.localhost/payment/process</pay-url>
            <finalize-url>http://appdaysdemo.dev.localhost/payment/finalize</finalize-url>
        </payment-method>
    </payments>
```

upgrade version in the manifest and update app by
```
swdc pshell platform
bin/console app:refresh
```

Activate stripe payment in admin and assign payment method to sales channel

Order and pay through stripe

## Step 4

Create `ActionController` in `src/Controller` which extends `AbstractController`
```php
private OrderRepository $orderRepository;
    private ShopRepository $shopRepository;
    private GuzzleClientFactory $clientFactory;
    private SessionService $sessionService;

    public function __construct(
        OrderRepository $orderRepository, 
        ShopRepository $shopRepository, 
        GuzzleClientFactory $clientFactory,
        SessionService $sessionService
    ) {
        $this->orderRepository = $orderRepository;
        $this->shopRepository = $shopRepository;
        $this->clientFactory = $clientFactory;
        $this->sessionService = $sessionService;
    }

    /**
     * @Route("/action/capture-payments", name="action.capture-payments", methods={"POST"})
     */
    public function capturePayments(RequestInterface $request): Response
    {
        $content = \json_decode($request->getBody()->getContents(), true);

        $data = $content['data'];

        $shop = $this->shopRepository->getShopFromId($content['source']['shopId']);
        $client = $this->clientFactory->createClient($shop);

        foreach ($data['ids'] as $orderId) {
            foreach ($this->orderRepository->fetchOrdersByOrderId($orderId) as $order) {
                $payment = $this->sessionService->getPaymentIntentFromSession($order['session_id']);
                if (!$payment) {
                    continue;
                }
                
                $payment->capture();

                $client->sendRequest(
                    new Request(
                        'POST',
                        sprintf('/api/_action/order_transaction/%s/state/paid', $order['transaction_id'])
                    )
                );
            }
        }

        return $this->sign([
            'actionType' => 'reload',
            'payload' => [],
        ], $content['source']['shopId']);

    }

    private function sign(array $content, string $shopId): JsonResponse
    {
        $response = new JsonResponse($content);

        $shop = $this->shopRepository->getShopFromId($shopId);

        $hmac = \hash_hmac('sha256', $response->getContent(), $shop->getShopSecret());
        $response->headers->set('shopware-app-signature', $hmac);

        return $response;
    }
```

Add action button config to app manifest:
```xml
    <admin>
        <action-button action="captureCharge" entity="order" view="detail" url="http://appdaysdemo.dev.localhost/action/capture-payments">
            <label>Capture payment</label>
            <label lang="de-DE">Zahlung einziehen</label>
        </action-button>

        <action-button action="captureCharges" entity="order" view="list" url="http://appdaysdemo.dev.localhost/action/capture-payments">
            <label>Capture payments</label>
            <label lang="de-DE">Zahlungen einziehen</label>
        </action-button>
    </admin>

    <permissions>
        <update>order_transaction</update>
        <create>state_machine_history</create>
    </permissions>
```
Note that you need additional permission to update the order_transaction state

Increase version in the manifest and update the app:
```shell
swdc pshell platform
bin/console app:refresh
```

Use the `Charge payments` action button in the admin order detail page on an open order
See that the payment state changes from `authorized` to `paid`

## Step 5

Create WebhookController in `src/Controller` which extends AbstractController with
```php
private OrderRepository $orderRepository;
    private SessionService $sessionService;
    private ShopRepository $shopRepository;
    private ClientFactoryInterface $clientFactory;

    public function __construct(
        OrderRepository $orderRepository, 
        SessionService $sessionService, 
        ShopRepository $shopRepository, 
        ClientFactoryInterface $clientFactory
    ) {
        $this->orderRepository = $orderRepository;
        $this->sessionService = $sessionService;
        $this->shopRepository = $shopRepository;
        $this->clientFactory = $clientFactory;
    }

    /**
     * @Route("/webhook/order-cancel", name="webhook.order-cancel", methods={"POST"})
     */
    public function onOrderCanceled(SymfonyRequest $request): Response
    {
        $data = \json_decode($request->getContent(), true);

        $charges = $this->getStripeChargesForOrder($data['data']['payload']['order']);

        if (\count($charges) === 0) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }
        
        $shop = $this->shopRepository->getShopFromId($data['source']['shopId']);
        $client = $this->clientFactory->createClient($shop);

        foreach ($charges as $transaction => $chargeIds) {
            foreach ($chargeIds as $charge) {
                Refund::create([
                    'charge' => $charge
                ]);
            }

            $this->orderRepository->updateOrderStatus('refunded', $transaction);

            $client->sendRequest(
                new Request(
                    'POST',
                    sprintf('/api/_action/order_transaction/%s/state/refund', $transaction)
                )
            );
        }
        
        return new Response('', Response::HTTP_NO_CONTENT);
    }

    private function getStripeChargesForOrder(array $order): array
    {
        $charges = [];
        foreach ($order['transactions'] as $transaction) {
            $order = $this->orderRepository->fetchOrder($transaction['id']);

            if ($order === null) {
                // The transaction was not paid via Stripe
                continue;
            }

            /** @var Charge $charge */
            foreach ($this->sessionService->getChargesForSession($order['session_id']) as $charge) {
                $charges[$transaction['id']][] = $charge->id;
            }
        }

        return $charges;
    }
```

Add webhook info to manifest.xml in platform:
```xml
    <permissions>
        <read>order</read>
        <update>order_transaction</update>
        <create>state_machine_history</create>
    </permissions>

    <webhooks>
        <webhook name="AppDaysDemo.onOrderCancel" url="http://appdaysdemo.dev.localhost/webhook/order-cancel" event="state_enter.order.state.cancelled"/>
    </webhooks>
```
Notice: that you additionally need the order read privilege

Increase version in the manifest and update the app:
```shell
swdc pshell platform
bin/console app:refresh
```

Set a stripe order state to cancelled in the admin and see that the payment is automatically updated to refunded

## Step 6

Update `RedirectController` to add creditCard infos to orderTransaction entities as custom fields: 
```php
        $order = $this->updateStatus($transaction);

        $stripeDetails = [];

        /** @var Charge $charge */
        foreach ($this->sessionService->getChargesForSession($order['session_id']) as $charge) {
            $stripeDetails[] = [
                'number' => '****' . $charge->payment_method_details->card->last4,
                'brand' => $charge->payment_method_details->card->brand,
                'expiry' => $charge->payment_method_details->card->exp_month . '/' . $charge->payment_method_details->card->exp_year,

            ];
        }

        if (\count($stripeDetails) === 0) {
            return RedirectResponse::create($order['return_url']);
        }

        $shop = $this->shopRepository->getShopFromId($order['shop_id']);
        $client = $this->clientFactory->createClient($shop);

        $client->sendRequest(
            new Request(
                'PATCH',
                'api/order-transaction/' . $transaction,
                [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/vnd.api+json',
                ],
                \json_encode([
                    'customFields' => [
                        'AppDaysDemoStripeDetails' => $stripeDetails,
                    ],
                ])
            )
        );

        return new RedirectResponse($order['return_url']);
```

Copy resources folder from `local/AppDaysDemo/resources` into platform `custom/apps/AppDaysDemo`

Increase version in the manifest and update the app and clear the cache:
```shell
swdc pshell platform
bin/console app:refresh
bin/console cache:clear
```

Make a new Stripe order and check in the storefront account order overview that the card details are displayed