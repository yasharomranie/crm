<?php

namespace App\Filament\Pages;

use App\Domain\Ticketing\CashierShiftService;
use App\Domain\Ticketing\TicketingException;
use App\Domain\Ticketing\TicketIssuer;
use App\Enums\PaymentMethod;
use App\Enums\TicketSourceType;
use App\Exceptions\InsufficientWalletBalanceException;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\CashierShift;
use App\Models\Guest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\TicketVariant;
use App\Support\MobileNumber;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

    /** Cash the customer hands over, to work out the change. */
    public string $cashReceived = '';

    /** For a split payment: the part paid in cash (the rest goes on the card reader). */
    public string $splitCash = '';

    /** A scanned/typed ticket-type code from the barcode reader. */
    public string $barcode = '';

    public string $shiftFloat = '';

    public string $countedCash = '';

    public string $shiftNotes = '';

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

        $this->pushToCart($variant, max(1, $this->quantity), (int) $price->amount);

        $this->reset(['productId', 'ticketVariantId', 'quantity']);
        $this->quantity = 1;
    }

    /**
     * One-tap sale of a frequent ticket type (the buttons above the form).
     */
    public function quickAdd(int $variantId): void
    {
        $variant = TicketVariant::with('product')->where('is_active', true)->find($variantId);
        $price = $variant?->currentPrice();

        if (! $variant || ! $price) {
            Notification::make()->title('این نوع بلیت در حال حاضر قابل فروش نیست.')->warning()->send();

            return;
        }

        $this->pushToCart($variant, 1, (int) $price->amount);
    }

    /**
     * A barcode reader types the code and presses Enter: the code of a
     * ticket type adds one of it to the cart.
     */
    public function addByCode(): void
    {
        $code = trim($this->barcode);
        $this->barcode = '';

        if ($code === '') {
            return;
        }

        $variant = TicketVariant::query()->where('is_active', true)->where('code', $code)->first();

        if (! $variant) {
            Notification::make()->title("نوع بلیتی با کد «{$code}» پیدا نشد.")->warning()->send();

            return;
        }

        $this->quickAdd($variant->id);
    }

    private function pushToCart(TicketVariant $variant, int $quantity, int $unitPrice): void
    {
        foreach ($this->cart as $index => $line) {
            if ($line['ticket_variant_id'] === $variant->id) {
                $this->cart[$index]['quantity'] += $quantity;

                return;
            }
        }

        $this->cart[] = [
            'ticket_variant_id' => $variant->id,
            'label' => "{$variant->product->name} - {$variant->name}",
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
        ];
    }

    /**
     * The ticket types sold most over the last weeks, as one-tap buttons.
     *
     * @return \Illuminate\Support\Collection<int, TicketVariant>
     */
    public function getQuickVariants(): \Illuminate\Support\Collection
    {
        $ids = OrderItem::query()
            ->where('created_at', '>=', now()->subDays((int) config('ticketing.quick_sale_days', 30)))
            ->groupBy('ticket_variant_id')
            ->orderByRaw('sum(quantity) desc')
            ->limit(8)
            ->pluck('ticket_variant_id');

        return TicketVariant::query()->with('product')->where('is_active', true)->whereIn('id', $ids)->get()
            ->sortBy(fn (TicketVariant $variant) => $ids->search($variant->id))
            ->filter(fn (TicketVariant $variant) => $variant->currentPrice() !== null)
            ->values();
    }

    public function currentShift(): ?CashierShift
    {
        return Auth::user() ? app(CashierShiftService::class)->openFor(Auth::user()) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function shiftSummary(): ?array
    {
        $shift = $this->currentShift();

        return $shift ? app(CashierShiftService::class)->summary($shift) : null;
    }

    public function openShift(): void
    {
        try {
            app(CashierShiftService::class)->open(Auth::user(), (int) $this->shiftFloat);
        } catch (TicketingException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        $this->reset('shiftFloat');
        Notification::make()->title('شیفت شروع شد.')->success()->send();
    }

    public function closeShift(): void
    {
        $shift = $this->currentShift();

        if (! $shift) {
            return;
        }

        if (trim($this->countedCash) === '') {
            Notification::make()->title('مبلغ نقدی را که در صندوق شمرده‌اید وارد کنید.')->warning()->send();

            return;
        }

        try {
            $closed = app(CashierShiftService::class)->close($shift, (int) $this->countedCash, $this->shiftNotes ?: null);
        } catch (TicketingException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        $this->reset(['countedCash', 'shiftNotes']);

        Notification::make()
            ->title($closed->difference === 0 ? 'شیفت بسته شد؛ صندوق دقیقاً برابر است.' : 'شیفت بسته شد؛ اختلاف صندوق: '.number_format($closed->difference).' تومان')
            ->{$closed->difference === 0 ? 'success' : 'warning'}()
            ->persistent($closed->difference !== 0)
            ->send();
    }

    /**
     * @return \Illuminate\Support\Collection<int, CashierShift>
     */
    public function recentClosedShifts(): \Illuminate\Support\Collection
    {
        return CashierShift::query()->with('user')->whereNotNull('closed_at')->latest('closed_at')->limit(8)->get();
    }

    /**
     * Change to hand back when paying cash (null until an amount is typed
     * that covers the total; with a discount code the real total is only
     * known at checkout, which reports the change itself).
     */
    public function changeDue(): ?int
    {
        if ($this->paymentMethod !== PaymentMethod::Cash->value || trim($this->cashReceived) === '' || filled($this->discountCode)) {
            return null;
        }

        $change = (int) $this->cashReceived - $this->cartTotal();

        return $change >= 0 ? $change : null;
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
                $order = DB::transaction(function () use ($issuer) {
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
                        $this->recordPayments($order);
                    }

                    return $order;
                });
            }

            $order->update(['cashier_shift_id' => $this->currentShift()?->id]);

            $this->lastOrderId = $order->id;
            $this->reset(['cart', 'discountCode', 'customerName', 'customerPhone', 'isComplimentary', 'complimentaryReason', 'cashReceived', 'splitCash']);

            $change = $order->payments()->first()?->metadata['change'] ?? null;

            Notification::make()
                ->title('فروش با موفقیت ثبت شد.')
                ->body($change ? 'باقی‌مانده‌ی پول مشتری: '.number_format($change).' تومان' : null)
                ->success()
                ->persistent((bool) $change)
                ->send();
        } catch (TicketingException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }

    /**
     * Records how the order was paid: one payment, or - for a split - a
     * cash part and a card part that add up to the total.
     *
     * @throws TicketingException
     */
    private function recordPayments(Order $order): void
    {
        $total = (int) $order->final_amount;
        $base = ['status' => 'paid', 'paid_at' => now(), 'created_by' => Auth::id()];

        if ($this->paymentMethod === 'split') {
            $cash = (int) $this->splitCash;

            if ($cash <= 0 || $cash >= $total) {
                throw new TicketingException('در پرداخت ترکیبی، مبلغ نقد باید بیشتر از صفر و کمتر از جمع کل باشد.');
            }

            $order->payments()->create([...$base, 'method' => PaymentMethod::Cash, 'amount' => $cash]);
            $order->payments()->create([...$base, 'method' => PaymentMethod::Card, 'amount' => $total - $cash]);
            $order->update(['amount_paid' => $total]);

            return;
        }

        $metadata = null;

        if ($this->paymentMethod === PaymentMethod::Wallet->value) {
            $metadata = ['guest_id' => $this->debitWalletForOrder($order)];
        }

        if ($this->paymentMethod === PaymentMethod::Cash->value && trim($this->cashReceived) !== '') {
            if ((int) $this->cashReceived < $total) {
                throw new TicketingException('مبلغ دریافتی کمتر از جمع کل است.');
            }

            $metadata = ['cash_received' => (int) $this->cashReceived, 'change' => (int) $this->cashReceived - $total];
        }

        $order->payments()->create([...$base, 'method' => PaymentMethod::from($this->paymentMethod), 'amount' => $total, 'metadata' => $metadata]);
        $order->update(['amount_paid' => $total]);
    }

    /**
     * Wallet payment at the desk: the customer is found by the mobile
     * number typed in, and the debit shares the sale's database
     * transaction, so a refused payment leaves no order behind.
     *
     * @return int the guest whose wallet was charged
     *
     * @throws TicketingException
     */
    private function debitWalletForOrder(Order $order): int
    {
        $guest = filled($this->customerPhone) ? Guest::query()->where('mobile', MobileNumber::canonical($this->customerPhone))->first() : null;

        if (! $guest) {
            throw new TicketingException('برای پرداخت با کیف پول، شماره موبایل یک مشتری ثبت‌شده را وارد کنید.');
        }

        try {
            $guest->debitWallet((int) $order->final_amount, "خرید بلیط در گیشه - {$order->order_number}");
        } catch (InsufficientWalletBalanceException) {
            throw new TicketingException('موجودی کیف پول این مشتری برای این خرید کافی نیست.');
        }

        return $guest->id;
    }

    public function getLastOrder(): ?Order
    {
        return $this->lastOrderId
            ? Order::with('items.tickets.qrToken')->find($this->lastOrderId)
            : null;
    }
}
