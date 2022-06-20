<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

    <head>
        <!-- Error: {{ session('error') }} -->

        @if (config('services.analytics.tracking_id'))
            <script async src="https://www.googletagmanager.com/gtag/js?id=UA-122229484-1"></script>
            <script>
                window.dataLayer = window.dataLayer || [];

                function gtag() {
                    dataLayer.push(arguments);
                }

                gtag('js', new Date());
                gtag('config', '{{ config('services.analytics.tracking_id') }}', {'anonymize_ip': true});

                function trackEvent(category, action) {
                    ga('send', 'event', category, action, this.src);
                }
            </script>
            <script>
                Vue.config.devtools = true;
            </script>
        @else
            <script>
                function gtag() {
                }
            </script>
        @endif

        <!-- Title -->
        @if(isset($company->account) && !$company->account->isPaid())
            <title>@yield('meta_title', '') — Invoice Ninja</title>
        @elseif(isset($company) && !is_null($company))
            <title>@yield('meta_title', '') — {{ $company->present()->name() }}</title>
        @else
            <title>@yield('meta_title', '')</title>
        @endif

        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="@yield('meta_description')"/>
        
        <!-- CSRF Token -->
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <!-- Scripts -->
        <script src="{{ mix('js/app.js') }}" defer></script>
        <script src="{{ asset('vendor/alpinejs@2.8.2/alpine.js') }}" defer></script>

        <!-- Fonts -->
        <link rel="dns-prefetch" href="https://fonts.gstatic.com">
        <link href="https://fonts.googleapis.com/css?family=Open+Sans&display=swap" rel="stylesheet" type="text/css">

        <!-- Styles -->
        <link href="{{ mix('css/app.css') }}" rel="stylesheet">

        @if(auth()->guard('vendor')->user() && !auth()->guard('vendor')->user()->user->account->isPaid())
            <link href="{{ asset('favicon.png') }}" rel="shortcut icon" type="image/png">
        @endif

        <link rel="canonical" href="{{ config('ninja.site_url') }}/{{ request()->path() }}"/>

        @if((bool) \App\Utils\Ninja::isSelfHost())
            <style>
                {!! $settings->portal_custom_css !!}
            </style>
        @endif

        @livewireStyles

        {{-- Feel free to push anything to header using @push('header') --}}
        @stack('head')

        @if((isset($company) && $company->account->isPaid() && !empty($settings->portal_custom_head)) || ((bool) \App\Utils\Ninja::isSelfHost() && !empty($settings->portal_custom_head)))
            <div class="py-1 text-sm text-center text-white bg-primary">
                {!! $settings->portal_custom_head !!}
            </div>
        @endif

        <link rel="stylesheet" type="text/css" href="{{ asset('vendor/cookieconsent@3/cookieconsent.min.css') }}" />
    </head>

    @include('portal.ninja2020.components.primary-color')

    <body class="antialiased">
        @if(session()->has('message'))
            <div class="py-1 text-sm text-center text-white bg-primary disposable-alert">
                {{ session('message') }}
            </div>
        @endif

        @component('portal.ninja2020.components.general.sidebar.vendor_main', ['settings' => $settings, 'sidebar' => $sidebar])
            @yield('body')
        @endcomponent

        @livewireScripts

        <script src="{{ asset('vendor/cookieconsent@3/cookieconsent.min.js') }}" data-cfasync="false"></script>
        <script>
            window.addEventListener("load", function(){
                if (! window.cookieconsent) {
                    return;
                }
                window.cookieconsent.initialise({
                    "palette": {
                        "popup": {
                            "background": "#000"
                        },
                        "button": {
                            "background": "#f1d600"
                        },
                    },
                    "content": {
                        "href": "{{ config('ninja.privacy_policy_url.hosted') }}",
                        "message": "This website uses cookies to ensure you get the best experience on our website.",
                        "dismiss": "Got it!",
                        "link": "Learn more",
                    }
                })}
            );
        </script>

        @if($company && $company->google_analytics_key)
            <script>
                (function (i, s, o, g, r, a, m) {
                    i['GoogleAnalyticsObject'] = r;
                    i[r] = i[r] || function () {
                                (i[r].q = i[r].q || []).push(arguments)
                            }, i[r].l = 1 * new Date();
                    a = s.createElement(o),
                            m = s.getElementsByTagName(o)[0];
                    a.async = 1;
                    a.src = g;
                    m.parentNode.insertBefore(a, m)
                })(window, document, 'script', '//www.google-analytics.com/analytics.js', 'ga');

                ga('create', '{{ $company->google_analytics_key }}', 'auto');
                ga('set', 'anonymizeIp', true);
                ga('send', 'pageview');

                function trackEvent(category, action) {
                    ga('send', 'event', category, action, this.src);
                }
            </script>
        @endif
        
    </body>

    <footer>
        @yield('footer')
        @stack('footer')

        @if((bool) \App\Utils\Ninja::isSelfHost() && !empty($settings->portal_custom_footer))
            <div class="py-1 text-sm text-center text-white bg-primary">
                {!! $settings->portal_custom_footer !!}
            </div>
        @endif
    </footer>

    @if((bool) \App\Utils\Ninja::isSelfHost())
        <script>
            {!! $settings->portal_custom_js !!}
        </script>
    @endif
</html>
