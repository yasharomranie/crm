@if (is_array($compliance ?? null) && filled($compliance['verdict'] ?? null))
    <span style="display: block; margin-top: 0.3rem;">
        <span @class(['mr-pill', 'good' => $compliance['verdict'] === 'ok', 'bad' => $compliance['verdict'] === 'conflict', 'warn' => $compliance['verdict'] === 'unclear'])>{{ ['ok' => '✓ با استراتژی سازگار است', 'conflict' => '⚠ با خط قرمز یا اصل استراتژی در تضاد است', 'unclear' => '؟ از نظر استراتژی نامشخص'][$compliance['verdict']] ?? '؟ نامشخص' }}</span>
        @if (filled($compliance['note'] ?? null))
            <span class="mr-sub" style="display: block;">{{ $compliance['note'] }}</span>
        @endif
    </span>
@endif
