<?php
declare(strict_types=1);
require_once __DIR__ . '/BillingGateway.php';

// Classic Payment API recurring module, sandbox only. No production transport.
final class NmiGateway implements BillingGateway
{
    public function __construct(private string $securityKey) {}

    public static function nextDate(DateTimeImmutable $date): DateTimeImmutable
    {
        $next = $date->modify('first day of next month');
        return $next->setDate((int) $next->format('Y'), (int) $next->format('m'), min((int) $date->format('d'), (int) $next->format('t')));
    }

    public static function payload(array $order, string $paymentToken, DateTimeImmutable $date): array
    {
        if ($paymentToken === '' || strlen($paymentToken) > 512 || $order['currency'] !== 'USD') throw new InvalidArgumentException('Unsupported payment details.');
        $monthly = (int) $order['monthly_amount_minor'];
        $setup = (int) $order['setup_amount_minor'];
        if ($monthly <= 0 || $setup < 0) throw new InvalidArgumentException('Invalid plan pricing.');
        return [
            'type' => 'sale', 'payment_token' => $paymentToken,
            'amount' => number_format(($monthly + $setup) / 100, 2, '.', ''),
            'currency' => 'USD', 'orderid' => 'signup-' . $order['id'],
            'recurring' => 'add_subscription', 'plan_payments' => '0',
            'plan_amount' => number_format($monthly / 100, 2, '.', ''),
            'month_frequency' => '1', 'day_of_month' => $date->format('j'),
            'start_date' => self::nextDate($date)->format('Ymd'),
            'company' => $order['billing_name'], 'email' => $order['billing_email'],
            'phone' => $order['phone'], 'address1' => $order['address1'], 'address2' => $order['address2'],
            'city' => $order['city'], 'state' => $order['region'], 'zip' => $order['postal_code'], 'country' => $order['country'],
        ];
    }

    public function signup(array $order, string $paymentToken, DateTimeImmutable $date): array
    {
        if ($this->securityKey === '' || !function_exists('curl_init')) throw new RuntimeException('Gateway configuration is unavailable.');
        $payload = self::payload($order, $paymentToken, $date);
        $payload['security_key'] = $this->securityKey;
        $curl = curl_init('https://sandbox.nmi.com/api/transact.php');
        curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($payload), CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 45, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_FOLLOWLOCATION => false]);
        $response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($response === false || $status !== 200) throw new RuntimeException('Gateway result requires review.');
        parse_str($response, $result);
        $code = is_string($result['response'] ?? null) ? $result['response'] : '';
        // Return only references and classified status, never raw provider data.
        $transaction = $result['transactionid'] ?? null;
        $subscription = $result['subscription_id'] ?? $result['subscriptionid'] ?? null;
        return [
            'status' => $code === '2' ? 'declined' : ($code === '1' && is_string($transaction) && $transaction !== '' && is_string($subscription) && $subscription !== '' ? 'approved_pending_review' : 'review_required'),
            'transaction' => is_string($transaction) ? substr($transaction, 0, 191) : null,
            'subscription' => is_string($subscription) ? substr($subscription, 0, 191) : null,
        ];
    }
}
