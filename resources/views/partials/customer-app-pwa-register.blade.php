{{-- Registers public/customer-app-sw.js scoped to '/app/' only - see that
    file's own top comment for why it's kept fully separate from the admin
    panel's service worker. --}}
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('{{ asset('customer-app-sw.js') }}', { scope: '/app/' }).catch(function () {});
        });
    }
</script>
