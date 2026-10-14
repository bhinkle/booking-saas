<?php
declare(strict_types=1);
interface BillingGateway
{
    public function signup(array $order, string $paymentToken, DateTimeImmutable $date): array;
}
