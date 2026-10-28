<?php

namespace App\Services;

use App\Models\CustomRequest;
use App\Models\Order;
use App\Models\User;
use App\Notifications\AppNotification;

class Notifier
{
    public const IN_APP = ['database'];
    public const BOTH = ['database', 'mail'];
    public const MAIL = ['mail'];

    private const ORDER_STATUS = [
        'en_attente' => 'En attente',
        'confirmee' => 'Confirmée',
        'expediee' => 'Expédiée',
        'livree' => 'Livrée',
        'annulee' => 'Annulée',
    ];

    public static function fcfa($value): string
    {
        return number_format((float) $value, 0, ',', ' ') . ' FCFA';
    }

    public function user(
        ?User $user,
        string $type,
        string $title,
        string $body,
        ?string $url = null,
        array $channels = self::IN_APP
    ): void {
        if (! $user) {
            return;
        }

        try {
            $user->notify(new AppNotification($type, $title, $body, $url, $channels));
        } catch (\Throwable $e) {
            // Une notification ratée (serveur mail indisponible...) ne doit jamais bloquer l'action principale
            report($e);
        }
    }

    public function admins(
        string $type,
        string $title,
        string $body,
        ?string $url = null,
        array $channels = self::IN_APP
    ): void {
        User::role('admin')->get()->each(
            fn (User $admin) => $this->user($admin, $type, $title, $body, $url, $channels)
        );
    }

    public function orderPlaced(Order $order, User $customer, ?CustomRequest $from = null): void
    {
        $id = $order->id;
        $total = self::fcfa($order->total);
        $deposit = (float) $order->deposit_amount;

        $this->user(
            $customer,
            'order',
            $from ? "Devis accepté : commande #{$id}" : "Commande #{$id} enregistrée",
            "Nous avons bien reçu votre commande de {$total}."
                . ($deposit > 0
                    ? ' Un acompte de ' . self::fcfa($deposit) . ' est demandé pour la confirmer : vous recevrez un lien de paiement par WhatsApp.'
                    : ''),
            "/commandes/{$id}",
            self::BOTH
        );

        $this->admins(
            'order',
            $from ? "Devis accepté : commande #{$id}" : "Nouvelle commande #{$id}",
            "{$customer->name} a passé une commande de {$total}" . ($from ? " (demande sur mesure #{$from->id})." : '.'),
            "/admin/commandes/{$id}",
            self::BOTH
        );
    }

    public function orderChanged(Order $order, ?string $status, ?string $payment): void
    {
        $customer = User::find($order->user_id);
        if (! $customer) {
            return;
        }

        $id = $order->id;
        $pickup = $order->delivery_mode === 'retrait';
        $url = "/commandes/{$id}";

        if ($status && $status !== 'en_attente') {
            $body = match ($status) {
                'confirmee' => "Votre commande #{$id} est confirmée : nous la préparons.",
                'expediee' => $pickup
                    ? "Votre commande #{$id} est prête : vous pouvez venir la retirer en magasin."
                    : "Votre commande #{$id} est en route.",
                'livree' => $pickup
                    ? "Votre commande #{$id} a été retirée. Merci de votre confiance !"
                    : "Votre commande #{$id} a été livrée. Merci de votre confiance !",
                'annulee' => "Votre commande #{$id} a été annulée. Pour toute question, écrivez-nous.",
                default => null,
            };

            if ($body) {
                $this->user(
                    $customer,
                    'order',
                    "Commande #{$id} : " . mb_strtolower(self::ORDER_STATUS[$status] ?? $status),
                    $body,
                    $url,
                    self::BOTH
                );
            }
        }

        if ($payment && $payment !== 'non_paye') {
            [$title, $body] = match ($payment) {
                'acompte_paye' => [
                    "Acompte reçu pour la commande #{$id}",
                    'Nous avons bien reçu votre acompte de ' . self::fcfa($order->deposit_amount) . '. Merci !',
                ],
                'paye' => [
                    "Paiement complet pour la commande #{$id}",
                    'Votre commande est entièrement réglée. Merci !',
                ],
                'echoue' => [
                    "Paiement échoué pour la commande #{$id}",
                    "Le paiement de votre commande #{$id} n'a pas abouti. Écrivez-nous pour trouver une solution.",
                ],
                default => [null, null],
            };

            if ($title) {
                $this->user($customer, 'payment', $title, $body, $url, self::BOTH);
            }
        }
    }

    public function requestCreated(CustomRequest $request, User $customer): void
    {
        $this->admins(
            'custom_request',
            "Nouvelle demande sur mesure #{$request->id}",
            "{$customer->name} demande un devis : « {$request->title} ».",
            "/admin/sur-mesure/{$request->id}",
            self::BOTH
        );
    }

    public function quoteSent(CustomRequest $request): void
    {
        $request->loadMissing('user');

        $body = 'Nous vous proposons un devis de ' . self::fcfa($request->quoted_price) . '.'
            . ($request->lead_time_days ? " Délai de confection : {$request->lead_time_days} jours." : '')
            . ($request->valid_until ? " Il est valable jusqu'au " . $request->valid_until->format('d/m/Y') . '.' : '');

        $this->user(
            $request->user,
            'custom_request',
            "Votre devis est prêt : {$request->title}",
            $body,
            "/mes-demandes/{$request->id}",
            self::BOTH
        );
    }

    public function requestRejected(CustomRequest $request): void
    {
        $request->loadMissing('user');

        $this->user(
            $request->user,
            'custom_request',
            "Demande sur mesure #{$request->id} : non réalisable",
            "Nous ne pouvons pas réaliser votre demande « {$request->title} »."
                . ($request->admin_note ? " Raison : {$request->admin_note}" : ''),
            "/mes-demandes/{$request->id}",
            self::BOTH
        );
    }
}