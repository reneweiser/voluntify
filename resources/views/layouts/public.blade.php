<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        @yield('meta')

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=bebas-neue:400" rel="stylesheet" />
    </head>
    <body class="public-page font-sans antialiased" style="margin: 0; padding: 0; background: var(--dark); color: #a1a1aa;">

        {{-- Skip to content --}}
        <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-[999] focus:bg-white focus:text-black focus:px-4 focus:py-2 focus:rounded">
            {{ __('Skip to content') }}
        </a>

        {{-- Decorative shapes (echoing hero) --}}
        <div style="position: absolute; pointer-events: none; z-index: 0; inset: 0; overflow: hidden;" aria-hidden="true">
            <div style="position: absolute; width: 300px; height: 300px; background: var(--red); border-radius: 50%; opacity: 0.03; top: -100px; right: -80px;"></div>
            <div style="position: absolute; width: 200px; height: 200px; background: var(--yellow); opacity: 0.03; bottom: 15%; left: -60px; clip-path: polygon(50% 0%, 0% 100%, 100% 100%);"></div>
            <div style="position: absolute; width: 150px; height: 220px; background: var(--brand); opacity: 0.04; top: 40%; right: -40px;"></div>
        </div>

        {{-- Nav (matches landing page) --}}
        <nav aria-label="{{ __('Main navigation') }}" style="position: fixed; top: 0; left: 0; right: 0; z-index: 50; background: var(--dark);">
            <div style="max-width: 1200px; margin: 0 auto; padding: 1rem 1.5rem; display: flex; align-items: center;">
                <a href="/" style="display: flex; align-items: center; gap: 0.75rem; text-decoration: none;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 42" style="width: 28px; height: 36px; color: #059669;">
                        <circle cx="16" cy="4" r="3.8" fill="currentColor"/>
                        <path fill="currentColor" d="M1 13h7.5L16 34l7.5-21H31L19.5 40q-3.5 3-7 0Z"/>
                    </svg>
                    <span class="font-bebas" style="font-size: 1.5rem; color: white; letter-spacing: 0.1em;">VOLUNTIFY</span>
                </a>
            </div>
            <div style="height: 3px; background: var(--brand);"></div>
        </nav>

        {{-- Main content --}}
        <main id="main-content" style="position: relative; z-index: 1; max-width: 896px; margin: 0 auto; padding: 5rem 1.5rem 3rem;">
            {{ $slot }}
        </main>

        {{-- Footer (matches landing page) --}}
        <footer style="position: relative; z-index: 1; background: #111; padding: 2rem 1.5rem; text-align: center;">
            <div style="display: flex; align-items: center; justify-content: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 42" style="width: 20px; height: 26px; color: #059669;">
                    <circle cx="16" cy="4" r="3.8" fill="currentColor"/>
                    <path fill="currentColor" d="M1 13h7.5L16 34l7.5-21H31L19.5 40q-3.5 3-7 0Z"/>
                </svg>
                <span class="font-bebas" style="font-size: 1.1rem; color: rgba(255,255,255,0.6); letter-spacing: 0.1em;">VOLUNTIFY</span>
            </div>
            <p style="margin-bottom: 0.75rem; display: flex; align-items: center; justify-content: center; gap: 1rem;">
                <a href="https://github.com/reneweiser/voluntify/tree/main/docs#readme" style="color: rgba(255,255,255,0.5); font-size: 0.8rem; text-decoration: none; transition: color 0.2s;"
                   onmouseover="this.style.color='rgba(255,255,255,0.8)'" onmouseout="this.style.color='rgba(255,255,255,0.5)'">
                    Documentation
                </a>
                <a href="https://github.com/reneweiser/voluntify" style="color: rgba(255,255,255,0.5); font-size: 0.8rem; text-decoration: none; transition: color 0.2s; display: inline-flex; align-items: center; gap: 0.35rem;"
                   onmouseover="this.style.color='rgba(255,255,255,0.8)'" onmouseout="this.style.color='rgba(255,255,255,0.5)'">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" style="width: 16px; height: 16px;">
                        <path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27s1.36.09 2 .27c1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.01 8.01 0 0016 8c0-4.42-3.58-8-8-8z"/>
                    </svg>
                    GitHub
                </a>
            </p>
            <p style="margin-bottom: 0.5rem;">
                <a href="https://reneweiser.de" style="color: rgba(255,255,255,0.3); font-size: 0.8rem; text-decoration: none; transition: color 0.2s;"
                   onmouseover="this.style.color='rgba(255,255,255,0.5)'" onmouseout="this.style.color='rgba(255,255,255,0.3)'">
                    Made by Rene Weiser
                </a>
            </p>
            <p style="color: rgba(255,255,255,0.3); font-size: 0.8rem;">&copy; {{ date('Y') }} Voluntify. {{ __('All rights reserved.') }}</p>
        </footer>

        @fluxScripts
    </body>
</html>
