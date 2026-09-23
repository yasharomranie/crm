<?php

namespace App\Filament\Pages;

use App\Domain\Ticketing\GateValidationService;
use App\Enums\GateScanResult;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\Gate;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * A USB/Bluetooth QR scanner behaves as a keyboard - it "types" the QR
 * payload into whatever input is focused, then sends Enter. That's the
 * whole interaction model here (see the blade view's auto-focus +
 * keydown.enter handler): no camera/JS QR library needed, and it works
 * with real, cheap hardware gate scanners exactly as sold. Section 54
 * (no offline mode in v1) means every scan is a live call to
 * GateValidationService, which itself fails closed if the DB is
 * unreachable.
 */
class GateScanner extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'filament.pages.gate-scanner';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|\UnitEnum|null $navigationGroup = 'گیشه بلیط';

    protected static ?string $navigationLabel = 'اسکن بلیط (گیت)';

    protected static ?string $title = 'اسکن بلیط';

    public static function accessControlPermission(): string
    {
        return 'gate-scanner.access';
    }

    public ?int $gateId = null;

    public string $qrToken = '';

    public ?string $lastResult = null;

    public ?string $lastReason = null;

    public ?string $lastTicketNumber = null;

    public ?string $lastUsageSummary = null;

    /**
     * @return Collection<int, Gate>
     */
    public function getGates(): Collection
    {
        return Gate::query()->where('is_active', true)->orderBy('name')->get();
    }

    public function scan(): void
    {
        $token = trim($this->qrToken);
        $this->qrToken = '';

        if ($token === '') {
            return;
        }

        if (! $this->gateId) {
            $this->lastResult = GateScanResult::Rejected->value;
            $this->lastReason = 'ابتدا گیت را انتخاب کنید.';

            return;
        }

        $gate = Gate::find($this->gateId);

        if (! $gate) {
            return;
        }

        $result = app(GateValidationService::class)->scan($token, $gate, Auth::user(), [
            'device_identifier' => request()->header('X-Device-Id'),
            'ip_address' => request()->ip(),
        ]);

        $this->lastResult = $result['result']->value;
        $this->lastReason = $result['reason']?->getLabel();
        $this->lastTicketNumber = $result['ticket']?->ticket_number;

        if ($result['result'] === GateScanResult::Success && $result['ticket']) {
            $ticket = $result['ticket'];
            $this->lastUsageSummary = "مصرف {$result['usage_number']} از {$ticket->allowed_uses}";
        } else {
            $this->lastUsageSummary = null;
        }

        $this->dispatch('scan-handled');
    }
}
