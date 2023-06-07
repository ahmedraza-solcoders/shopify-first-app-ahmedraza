@if(env('SHOPIFY_APPBRIDGE_ENABLED',false))
    <script src="https://unpkg.com/@shopify/app-bridge{{ env('SHOPIFY_APPBRIDGE_VERSION','latest') ? '@'.env('SHOPIFY_APPBRIDGE_VERSION','latest') : '' }}"></script>
    <script>
        var AppBridge = window['app-bridge'];
        var createApp = AppBridge.default;
        var app = createApp({
            apiKey: '{{ env("SHOPIFY_API_KEY") }}',
            host: '{{ $host }}',
            forceRedirect: true,
        });
    </script>
@endif
