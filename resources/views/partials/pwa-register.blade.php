{{-- Registers the service worker (public/sw.js) - see its own top comment
    for what it does and, just as importantly, what it deliberately leaves
    untouched. --}}
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('{{ asset('sw.js') }}').catch(() => {});
        });
    }
</script>
