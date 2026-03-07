<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Voluntify') }} - Volunteer Management Platform</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=bebas-neue:400|dm-sans:400,500,600" rel="stylesheet" />

        @include('partials.head')

        <style>
            :root {
                --brand: #059669;
                --red: #E63946;
                --yellow: #F4D03F;
                --blue: #264653;
                --dark: #1A1A1A;
                --cream: #FAF8F0;
            }

            .font-bebas { font-family: 'Bebas Neue', sans-serif; }
            .font-dm { font-family: 'DM Sans', sans-serif; }

            /* Hero shapes */
            .shape {
                position: absolute;
                pointer-events: none;
                will-change: transform, opacity;
            }

            .shape-red-circle {
                width: clamp(200px, 35vw, 500px);
                height: clamp(200px, 35vw, 500px);
                background: var(--red);
                border-radius: 50%;
                top: -8%;
                right: -8%;
                animation: floatIn 1s cubic-bezier(0.16, 1, 0.3, 1) 0.2s both, drift 8s ease-in-out infinite 1.2s;
            }

            .shape-yellow-triangle {
                width: clamp(150px, 25vw, 350px);
                height: clamp(150px, 25vw, 350px);
                background: var(--yellow);
                clip-path: polygon(50% 0%, 0% 100%, 100% 100%);
                bottom: 8%;
                left: 5%;
                transform: rotate(15deg);
                animation: floatIn 1s cubic-bezier(0.16, 1, 0.3, 1) 0.5s both, drift 7s ease-in-out 0.5s infinite 1.5s;
            }

            .shape-blue-rect {
                width: clamp(120px, 18vw, 280px);
                height: clamp(180px, 28vw, 400px);
                background: var(--blue);
                top: 25%;
                left: -3%;
                transform: rotate(-8deg);
                animation: floatIn 1s cubic-bezier(0.16, 1, 0.3, 1) 0.4s both, drift 6s ease-in-out 1s infinite 1.4s;
            }

            .shape-emerald-circle {
                width: clamp(100px, 15vw, 220px);
                height: clamp(100px, 15vw, 220px);
                background: var(--brand);
                border-radius: 50%;
                top: 15%;
                right: 12%;
                animation: floatIn 1s cubic-bezier(0.16, 1, 0.3, 1) 0.7s both, drift 7s ease-in-out 0.3s infinite 1.7s;
            }

            @keyframes floatIn {
                from {
                    opacity: 0;
                    transform: scale(0.6) translateY(40px);
                }
                to {
                    opacity: 1;
                    transform: scale(1) translateY(0);
                }
            }

            /* Preserve the rotation for rotated shapes */
            .shape-yellow-triangle {
                animation: floatInRotated15 1s cubic-bezier(0.16, 1, 0.3, 1) 0.5s both, driftRotated15 7s ease-in-out infinite 1.5s;
            }
            .shape-blue-rect {
                animation: floatInRotatedNeg8 1s cubic-bezier(0.16, 1, 0.3, 1) 0.4s both, driftRotatedNeg8 6s ease-in-out infinite 1.4s;
            }

            @keyframes floatInRotated15 {
                from { opacity: 0; transform: rotate(15deg) scale(0.6) translateY(40px); }
                to { opacity: 1; transform: rotate(15deg) scale(1) translateY(0); }
            }
            @keyframes floatInRotatedNeg8 {
                from { opacity: 0; transform: rotate(-8deg) scale(0.6) translateY(40px); }
                to { opacity: 1; transform: rotate(-8deg) scale(1) translateY(0); }
            }

            @keyframes drift {
                0%, 100% { transform: translateY(0); }
                50% { transform: translateY(-15px); }
            }
            @keyframes driftRotated15 {
                0%, 100% { transform: rotate(15deg) translateY(0); }
                50% { transform: rotate(15deg) translateY(-12px); }
            }
            @keyframes driftRotatedNeg8 {
                0%, 100% { transform: rotate(-8deg) translateY(0); }
                50% { transform: rotate(-8deg) translateY(-10px); }
            }

            /* Hero text reveal */
            .hero-reveal {
                animation: revealUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) both;
            }
            .hero-reveal-1 { animation-delay: 0.6s; }
            .hero-reveal-2 { animation-delay: 0.75s; }
            .hero-reveal-3 { animation-delay: 0.9s; }
            .hero-reveal-4 { animation-delay: 1.05s; }
            .hero-reveal-5 { animation-delay: 1.2s; }

            @keyframes revealUp {
                from { opacity: 0; transform: translateY(30px); }
                to { opacity: 1; transform: translateY(0); }
            }

            /* Scroll reveal */
            .scroll-reveal {
                opacity: 0;
                transform: translateY(30px);
                transition: opacity 0.6s ease, transform 0.6s ease;
            }
            .scroll-reveal.is-visible {
                opacity: 1;
                transform: translateY(0);
            }

            /* Steps connector */
            .steps-connector {
                border-top: 2px dashed var(--brand);
            }
            .steps-connector-vertical {
                border-left: 2px dashed var(--brand);
            }
        </style>
    </head>
    <body class="font-dm antialiased" style="margin: 0; padding: 0;">

        {{-- NAV --}}
        <nav style="position: fixed; top: 0; left: 0; right: 0; z-index: 50; background: var(--dark);">
            <div style="max-width: 1200px; margin: 0 auto; padding: 1rem 1.5rem; display: flex; align-items: center; justify-content: space-between;">
                <a href="/" style="display: flex; align-items: center; gap: 0.75rem; text-decoration: none;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 42" style="width: 28px; height: 36px; color: #059669;">
                        <circle cx="16" cy="4" r="3.8" fill="currentColor"/>
                        <path fill="currentColor" d="M1 13h7.5L16 34l7.5-21H31L19.5 40q-3.5 3-7 0Z"/>
                    </svg>
                    <span class="font-bebas" style="font-size: 1.5rem; color: white; letter-spacing: 0.1em;">VOLUNTIFY</span>
                </a>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <a href="https://github.com/reneweiser/voluntify/tree/main/docs#readme" style="color: rgba(255,255,255,0.8); font-size: 0.875rem; font-weight: 500; text-decoration: none; transition: color 0.2s;"
                       onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,0.8)'">
                        Docs
                    </a>
                @if (Route::has('login'))
                        @auth
                            <a href="{{ route('dashboard') }}" style="color: white; font-size: 0.875rem; font-weight: 500; padding: 0.5rem 1.25rem; border: 1px solid var(--brand); border-radius: 4px; text-decoration: none; transition: background 0.2s;"
                               onmouseover="this.style.background='#059669'" onmouseout="this.style.background='transparent'">
                                Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" style="color: rgba(255,255,255,0.8); font-size: 0.875rem; font-weight: 500; text-decoration: none; transition: color 0.2s;"
                               onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,0.8)'">
                                Log in
                            </a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" style="color: white; font-size: 0.875rem; font-weight: 500; padding: 0.5rem 1.25rem; background: var(--brand); border-radius: 4px; text-decoration: none; transition: opacity 0.2s;"
                                   onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                                    Register
                                </a>
                            @endif
                        @endauth
                @endif
                </div>
            </div>
            <div style="height: 3px; background: var(--brand);"></div>
        </nav>

        {{-- HERO --}}
        <section style="position: relative; min-height: 100vh; background: var(--dark); display: flex; align-items: center; justify-content: center; overflow: hidden; padding: 6rem 1.5rem 4rem;">
            {{-- Decorative shapes --}}
            <div class="shape shape-red-circle" style="opacity: 0.85;"></div>
            <div class="shape shape-yellow-triangle" style="opacity: 0.8;"></div>
            <div class="shape shape-blue-rect" style="opacity: 0.75;"></div>
            <div class="shape shape-emerald-circle" style="opacity: 0.9;"></div>

            {{-- Content --}}
            <div style="position: relative; z-index: 10; text-align: center; max-width: 900px;">
                <p class="hero-reveal hero-reveal-1" style="color: var(--brand); font-size: 0.875rem; font-weight: 600; letter-spacing: 0.3em; margin-bottom: 1.5rem; opacity: 0;">
                    VOLUNTEER MANAGEMENT PLATFORM
                </p>
                <h1 class="font-bebas" style="line-height: 0.95; margin-bottom: 2rem;">
                    <span class="hero-reveal hero-reveal-2" style="display: block; font-size: clamp(3rem, 10vw, 8rem); color: white; opacity: 0;">ORGANIZE.</span>
                    <span class="hero-reveal hero-reveal-3" style="display: block; font-size: clamp(3rem, 10vw, 8rem); color: var(--yellow); opacity: 0;">MOBILIZE.</span>
                    <span class="hero-reveal hero-reveal-4" style="display: block; font-size: clamp(3rem, 10vw, 8rem); color: var(--brand); opacity: 0;">VOLUNTIFY.</span>
                </h1>
                <p class="hero-reveal hero-reveal-5" style="color: #a1a1aa; font-size: clamp(1rem, 2vw, 1.25rem); max-width: 550px; margin: 0 auto 2.5rem; line-height: 1.6; opacity: 0;">
                    Recruit volunteers, distribute QR tickets, and validate arrivals &mdash; all in one platform built for events that run smoothly.
                </p>
                <div class="hero-reveal hero-reveal-5" style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; opacity: 0;">
                    @guest
                        <a href="{{ route('login') }}" style="display: inline-block; padding: 0.875rem 2.5rem; background: var(--brand); color: white; font-weight: 600; font-size: 1rem; border-radius: 4px; text-decoration: none; letter-spacing: 0.05em; transition: opacity 0.2s;"
                           onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                            GET STARTED
                        </a>
                    @else
                        <a href="{{ route('dashboard') }}" style="display: inline-block; padding: 0.875rem 2.5rem; background: var(--brand); color: white; font-weight: 600; font-size: 1rem; border-radius: 4px; text-decoration: none; letter-spacing: 0.05em; transition: opacity 0.2s;"
                           onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                            GO TO DASHBOARD
                        </a>
                    @endguest
                    <a href="#how-it-works" style="display: inline-block; padding: 0.875rem 2.5rem; border: 2px solid var(--cream); color: var(--cream); font-weight: 600; font-size: 1rem; border-radius: 4px; text-decoration: none; letter-spacing: 0.05em; transition: background 0.2s, color 0.2s;"
                       onmouseover="this.style.background='var(--cream)'; this.style.color='var(--dark)'" onmouseout="this.style.background='transparent'; this.style.color='var(--cream)'">
                        SEE HOW IT WORKS
                    </a>
                </div>
            </div>
        </section>

        {{-- FEATURES --}}
        <section style="background: var(--cream); padding: clamp(4rem, 8vw, 8rem) 1.5rem;">
            <div style="max-width: 1100px; margin: 0 auto;">
                <div class="scroll-reveal" style="text-align: center; margin-bottom: 4rem;">
                    <h2 class="font-bebas" style="font-size: clamp(2rem, 5vw, 3.5rem); color: var(--dark); margin-bottom: 1rem;">
                        THREE SHAPES. ONE PLATFORM.
                    </h2>
                    <div style="display: flex; justify-content: center; gap: 0; margin: 0 auto; width: 180px; height: 4px;">
                        <div style="flex: 1; background: var(--red);"></div>
                        <div style="flex: 1; background: var(--yellow);"></div>
                        <div style="flex: 1; background: var(--brand);"></div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem;">
                    {{-- Card 1 --}}
                    <div class="scroll-reveal" style="background: white; border-radius: 8px; padding: 2rem; border-left: 4px solid var(--red); box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
                        <div style="width: 48px; height: 48px; background: var(--red); border-radius: 50%; margin-bottom: 1.25rem;"></div>
                        <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--dark); margin-bottom: 0.75rem;">Volunteer Recruiting</h3>
                        <p style="color: #555; line-height: 1.6; font-size: 0.95rem;">
                            Create events with jobs and shifts. Share a public signup page &mdash; volunteers register with just a name and email. No accounts needed.
                        </p>
                    </div>

                    {{-- Card 2 --}}
                    <div class="scroll-reveal" style="background: white; border-radius: 8px; padding: 2rem; border-left: 4px solid var(--yellow); box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
                        <div style="width: 0; height: 0; border-left: 24px solid transparent; border-right: 24px solid transparent; border-bottom: 42px solid var(--yellow); margin-bottom: 1.25rem;"></div>
                        <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--dark); margin-bottom: 0.75rem;">QR Tickets</h3>
                        <p style="color: #555; line-height: 1.6; font-size: 0.95rem;">
                            Every volunteer gets a secure QR ticket via magic link. JWT-encoded, time-rotating keys keep tickets tamper-proof and verifiable offline.
                        </p>
                    </div>

                    {{-- Card 3 --}}
                    <div class="scroll-reveal" style="background: white; border-radius: 8px; padding: 2rem; border-left: 4px solid var(--brand); box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
                        <div style="width: 42px; height: 42px; background: var(--brand); border-radius: 4px; margin-bottom: 1.25rem;"></div>
                        <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--dark); margin-bottom: 0.75rem;">Event Check-in</h3>
                        <p style="color: #555; line-height: 1.6; font-size: 0.95rem;">
                            Scan QR codes at the entrance with the offline-capable PWA scanner. Works without internet &mdash; syncs when back online.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        {{-- HOW IT WORKS --}}
        <section id="how-it-works" style="background: var(--blue); padding: clamp(4rem, 8vw, 8rem) 1.5rem;">
            <div style="max-width: 1000px; margin: 0 auto;">
                <h2 class="font-bebas scroll-reveal" style="font-size: clamp(2rem, 5vw, 3.5rem); color: white; text-align: center; margin-bottom: 4rem;">
                    HOW IT WORKS
                </h2>

                {{-- Desktop: horizontal --}}
                <div class="scroll-reveal" style="display: none;" id="steps-desktop">
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0; position: relative;">
                        {{-- Connector line --}}
                        <div style="position: absolute; top: 28px; left: 28px; right: 28px; height: 0; border-top: 2px dashed rgba(5, 150, 105, 0.5);"></div>

                        @foreach([
                            ['1', 'Create Your Event', 'Set up jobs, define shifts, and publish your event page in minutes.'],
                            ['2', 'Volunteers Sign Up', 'Share the link. Volunteers pick shifts and register instantly.'],
                            ['3', 'Tickets Go Out', 'Each volunteer receives a unique QR ticket via magic link email.'],
                            ['4', 'Scan & Check In', 'Staff scan QR codes at the door. Works offline, syncs later.'],
                        ] as $step)
                            <div style="text-align: center; position: relative; padding: 0 1rem;">
                                <div style="width: 56px; height: 56px; background: var(--brand); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; position: relative; z-index: 2;">
                                    <span class="font-bebas" style="font-size: 1.5rem; color: white;">{{ $step[0] }}</span>
                                </div>
                                <h3 style="font-size: 1.1rem; font-weight: 700; color: white; margin-bottom: 0.5rem;">{{ $step[1] }}</h3>
                                <p style="color: rgba(255,255,255,0.7); font-size: 0.875rem; line-height: 1.5;">{{ $step[2] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Mobile: vertical timeline --}}
                <div class="scroll-reveal" id="steps-mobile">
                    <div style="position: relative; padding-left: 3.5rem;">
                        {{-- Vertical connector --}}
                        <div style="position: absolute; top: 28px; bottom: 28px; left: 27px; width: 0; border-left: 2px dashed rgba(5, 150, 105, 0.5);"></div>

                        @foreach([
                            ['1', 'Create Your Event', 'Set up jobs, define shifts, and publish your event page in minutes.'],
                            ['2', 'Volunteers Sign Up', 'Share the link. Volunteers pick shifts and register instantly.'],
                            ['3', 'Tickets Go Out', 'Each volunteer receives a unique QR ticket via magic link email.'],
                            ['4', 'Scan & Check In', 'Staff scan QR codes at the door. Works offline, syncs later.'],
                        ] as $step)
                            <div style="position: relative; padding-bottom: 2.5rem;">
                                <div style="position: absolute; left: -3.5rem; width: 56px; height: 56px; background: var(--brand); border-radius: 50%; display: flex; align-items: center; justify-content: center; z-index: 2;">
                                    <span class="font-bebas" style="font-size: 1.5rem; color: white;">{{ $step[0] }}</span>
                                </div>
                                <div style="padding-top: 0.5rem;">
                                    <h3 style="font-size: 1.1rem; font-weight: 700; color: white; margin-bottom: 0.5rem;">{{ $step[1] }}</h3>
                                    <p style="color: rgba(255,255,255,0.7); font-size: 0.875rem; line-height: 1.5;">{{ $step[2] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        {{-- CTA --}}
        <section style="position: relative; background: var(--dark); padding: clamp(4rem, 10vw, 8rem) 1.5rem; overflow: hidden; text-align: center;">
            {{-- Decorative shapes --}}
            <div style="position: absolute; width: 200px; height: 200px; background: var(--red); border-radius: 50%; opacity: 0.1; top: -60px; left: -60px;"></div>
            <div style="position: absolute; width: 150px; height: 150px; background: var(--yellow); opacity: 0.1; bottom: -40px; right: 10%; clip-path: polygon(50% 0%, 0% 100%, 100% 100%);"></div>
            <div style="position: absolute; width: 120px; height: 180px; background: var(--brand); opacity: 0.1; top: 20%; right: -30px;"></div>

            <div class="scroll-reveal" style="position: relative; z-index: 10;">
                <h2 class="font-bebas" style="font-size: clamp(2.5rem, 7vw, 5rem); color: white; margin-bottom: 1.5rem; line-height: 1;">
                    READY TO <span style="color: var(--brand);">VOLUNTIFY</span>?
                </h2>
                <p style="color: #a1a1aa; font-size: 1.1rem; max-width: 500px; margin: 0 auto 2.5rem; line-height: 1.6;">
                    Start organizing smarter events today. Free to get started, no credit card required.
                </p>
                @guest
                    <a href="{{ route('login') }}" style="display: inline-block; padding: 1rem 3rem; background: var(--brand); color: white; font-weight: 700; font-size: 1.125rem; border-radius: 4px; text-decoration: none; letter-spacing: 0.05em; transition: opacity 0.2s;"
                       onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                        GET STARTED FREE
                    </a>
                @else
                    <a href="{{ route('dashboard') }}" style="display: inline-block; padding: 1rem 3rem; background: var(--brand); color: white; font-weight: 700; font-size: 1.125rem; border-radius: 4px; text-decoration: none; letter-spacing: 0.05em; transition: opacity 0.2s;"
                       onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                        GO TO DASHBOARD
                    </a>
                @endguest
            </div>
        </section>

        {{-- FOOTER --}}
        <footer style="background: #111; padding: 2rem 1.5rem; text-align: center;">
            <div style="display: flex; align-items: center; justify-content: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 42" style="width: 20px; height: 26px; color: #059669;">
                    <circle cx="16" cy="4" r="3.8" fill="currentColor"/>
                    <path fill="currentColor" d="M1 13h7.5L16 34l7.5-21H31L19.5 40q-3.5 3-7 0Z"/>
                </svg>
                <span class="font-bebas" style="font-size: 1.1rem; color: rgba(255,255,255,0.6); letter-spacing: 0.1em;">VOLUNTIFY</span>
            </div>
            <p style="margin-bottom: 0.5rem;">
                <a href="https://github.com/reneweiser/voluntify/tree/main/docs#readme" style="color: rgba(255,255,255,0.5); font-size: 0.8rem; text-decoration: none; transition: color 0.2s;"
                   onmouseover="this.style.color='rgba(255,255,255,0.8)'" onmouseout="this.style.color='rgba(255,255,255,0.5)'">
                    Documentation
                </a>
            </p>
            <p style="color: rgba(255,255,255,0.3); font-size: 0.8rem;">&copy; {{ date('Y') }} Voluntify. All rights reserved.</p>
        </footer>

        <script>
            // Responsive steps: show desktop (horizontal) on lg, mobile (vertical) otherwise
            function updateStepsLayout() {
                var desktop = document.getElementById('steps-desktop');
                var mobile = document.getElementById('steps-mobile');
                if (window.innerWidth >= 768) {
                    desktop.style.display = 'block';
                    mobile.style.display = 'none';
                } else {
                    desktop.style.display = 'none';
                    mobile.style.display = 'block';
                }
            }
            updateStepsLayout();
            window.addEventListener('resize', updateStepsLayout);

            // IntersectionObserver scroll reveal
            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.15 });

            document.querySelectorAll('.scroll-reveal').forEach(function(el) {
                observer.observe(el);
            });
        </script>
    </body>
</html>
