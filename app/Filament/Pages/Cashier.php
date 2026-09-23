<?php

namespace App\Filament\Pages;

use App\Domain\Ticketing\TicketingException;
use App\Domain\Ticketing\TicketIssuer;
use App\Enums\PaymentMethod;
use App\Enums\TicketSourceType;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\Order;
use App\Models\Product;
use App\Models\TicketVariant;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * The fast Product -> Variant -> Quantity -> Discount -> Payment -> Issue
 * -> Print flow from the spec (section 21-23). One sale can carry several
 * lines (e.g. two adult round-trips + one child outbound) via the
 * in-memory $cart, all issued together as a single Order by
 * TicketIssuer::issue() when "ثبت فروش" is pressed.
 */
class Cashier extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'filament.pages.cashier';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|\UnitEnum|null $navigationGroup = 'گیشه بلیط';

    protected static ?string $navigationLabel = 'فروش بلیط (صندوق)';

    protected static ?string $title = 'فروش بلیط';

    public static function accessControlPermission(): string
    {
        return 'cashier.access';
    }

    public ?int $productId = null;

    public ?int $ticketVariantId = null;

    public int $quantity = 1;

    /** @var array<int, array{ticket_variant_id: int, label: string, quantity: int, unit_price: int}> */
    public array $cart = [];

    public string $discountCode = '';

    public string $customerName = '';

    public string $customerPhone = '';

    public string $paymentMethod = 'cash';

    public bool $isComplimentary = false;

    public string $complimentaryReason = '';

    public ?int $lastOrderId = null;

    public function canIssueComplimentary(): bool
    {
        return Auth::user()?->can('cashier.issue_complimentary') ?? false;
    }

    /**
     * @return Collection<int, Product>
     */
    public function getProducts(): Collection
    {
        return Product::query()->where('is_active', true)->orderBy('sort_order')->get();
    }

    /**
     * @return Collection<int, TicketVariant>
     */
    public function getVariants(): Collection
    {
        if (! $this->productId) {
            return new Collection;
        }

        return TicketVariant::query()
            ->where('product_id', $this->productId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    public function addToCart(): void
    {
        $variant = TicketVariant::find($this->ticketVariantId);

        if (! $variant) {
            Notification::make()->title('یک نوع بلیت انتخاب کنید.')->warning()->send();

            return;
        }

        $price = $variant->currentPrice();

        if (! $price) {
            Notification::make()->title('برای این نوع بلیت قیمتی تعریف نشده است.')->danger()->send();

            return;
        }

        $this->cart[] = [
            'ticket_variant_id' => $variant->id,
            'label' => "{$variant->product->name} - {$variant->name}",
            'quantity' => max(1, $this->quantity),
            'unit_price' => $price->amount,
        ];

        $this->reset(['productId', 'ticketVariantId', 'quantity']);
        $this->quantity = 1;
    }

    public function removeFromCart(int $index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
    }

    public function cartTotal(): int
    {
        return collect($this->cart)->sum(fn ($line) => $line['unit_price'] * $line['quantity']);
    }

    public function checkout(): void
    {
        if (empty($this->cart)) {
            Notification::make()->title('سبد فروش خالی است.')->warning()->send();

            return;
        }

        if ($this->isComplimentary && ! $this->canIssueComplimentary()) {
            Notification::make()->title('شما اجازه‌ی صدور بلیت رایگان را ندارید.')->danger()->send();

            return;
        }

        if ($this->isComplimentary && trim($this->complimentaryReason) === '') {
            Notification::make()->title('برای بلیت رایگان، دلیل صدور را وارد کنید.')->warning()->send();

            return;
        }

        try {
            $issuer = app(TicketIssuer::class);

            if ($this->isComplimentary) {
                $order = null;

                foreach ($this->cart as $line) {
                    $variant = TicketVariant::with('product')->findOrFail($line['ticket_variant_id']);
                    $order = $issuer->issueComplimentary(
                        $variant->product,
                        $variant,
                        $line['quantity'],
                        $this->complimentaryReason,
                        Auth::user(),
                        $this->customerName ?: null,
                        $this->customerPhone ?: null,
                    );
                }
            } else {
                $order = $issuer->issue([
                    'source_type' => TicketSourceType::Cashier,
                    'items' => collect($this->cart)->map(fn ($line) => [
                        'ticket_variant_id' => $line['ticket_variant_id'],
                        'quantity' => $line['quantity'],
                    ])->all(),
                    'customer_name' => $this->customerName ?: null,
                    'customer_phone' => $this->customerPhone ?: null,
                    'discount_code' => $this->discountCode ?: null,
                    'created_by' => Auth::id(),
                ]);

                if ($order->final_amount > 0) {
                    $order->payments()->create([
                        'method' => PaymentMethod::from($this->paymentMethod),
                        'amount' => $order->final_amount,
                        'status' => 'paid',
                        'paid_at' => now(),
                        'created_by' => Auth::id(),
                    ]);
                    $order->update(['amount_paid' => $order->final_amount]);
                }
            }

            $this->lastOrderId = $order->id;
            $this->reset(['cart', 'discountCode', 'customerName', 'customerPhone', 'isComplimentary', 'complimentaryReason']);

            Notification::make()->title('فروش با موفقیت ثبت شد.')->success()->send();
        } catch (TicketingException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }

    public function getLastOrder(): ?Order
    {
        return $this->lastOrderId
            ? Order::with('items.tickets.qrToken')->find($this->lastOrderId)
            : null;
    }
}
