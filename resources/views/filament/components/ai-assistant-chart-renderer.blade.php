{{--
    Shared by both AI assistant surfaces (the full "دستیار هوشمند" page and
    the panel-wide floating widget - see HasAiAssistantEngine's doc comment
    for why the two must never drift) - defines the one global function
    both use to draw a chat message's chart (see
    HasAiAssistantEngine::buildChartForTool()) onto the <canvas> Alpine's
    x-init handed it.

    A plain <script> tag here (not @script/@endscript) deliberately - this
    is a static, non-reactive utility function, not a Livewire component's
    own behaviour, and @once (not @script) is what dedupes a plain script
    tag included from more than one place on the same page load. A plain
    inline <script> tag placed directly inside a chat message bubble would
    NOT work for this: Livewire inserts new messages via a DOM morph, and a
    <script> tag added that way never executes - only Alpine's x-init does,
    since Alpine re-initializes newly morphed-in elements. See
    resources/views/filament/components/chartjs-assets.blade.php for why
    Chart.js itself is a self-hosted copy rather than a CDN.
--}}
@once
    <script>
        window.renderAiAssistantChart = function (canvas, spec) {
            if (! canvas || ! window.Chart || ! spec) {
                return;
            }

            // Guards a bubble that happens to get re-processed without its
            // canvas element itself being replaced - Chart.js throws
            // "Canvas is already in use" rather than drawing a second
            // chart on top of the first.
            const existing = window.Chart.getChart(canvas);

            if (existing) {
                existing.destroy();
            }

            const palette = ['#9c6b1f', '#57534e', '#15803d', '#b91c1c', '#2563eb', '#a855f7', '#0d9488', '#ca8a04'];
            const isDoughnut = spec.type === 'doughnut';

            new window.Chart(canvas, {
                type: spec.type,
                data: {
                    labels: spec.labels,
                    datasets: spec.datasets.map((dataset, index) => ({
                        label: dataset.label,
                        data: dataset.data,
                        backgroundColor: isDoughnut ? palette : palette[index % palette.length],
                        borderWidth: isDoughnut ? 1 : 0,
                    })),
                },
                options: {
                    // Persian labels (a form title, a workflow step, an
                    // approver's name) read far better as horizontal bars
                    // than squeezed under a vertical axis.
                    indexAxis: spec.type === 'bar' ? 'y' : 'x',
                    plugins: {
                        legend: { display: isDoughnut, position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                    },
                    scales: isDoughnut ? {} : { x: { beginAtZero: true, ticks: { precision: 0 } } },
                },
            });
        };
    </script>
@endonce
