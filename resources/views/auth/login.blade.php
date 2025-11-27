<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light-style layout-menu-fixed" dir="ltr"
      data-theme="theme-default" data-assets-path="{{ asset('sneat/assets/') }}" data-template="vertical-menu-template-free">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta name="csrf-token" content="{{ csrf_token() }}"/>

    <title>Login - {{ config('app.name','SalesMP3') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('sneat/assets/img/favicon/favicon.ico') }}"/>

    <!-- Fonts & CSS -->
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="{{ asset('sneat/assets/vendor/fonts/boxicons.css') }}"/>
    <link rel="stylesheet" href="{{ asset('sneat/assets/vendor/css/core.css') }}"/>
    <link rel="stylesheet" href="{{ asset('sneat/assets/vendor/css/theme-default.css') }}"/>
    <link rel="stylesheet" href="{{ asset('sneat/assets/css/demo.css') }}"/>

    <style>
        body { background:#f5f7fb; }
        .card-login { max-width: 420px; margin: 6rem auto; }
        .brand { font-weight:700; letter-spacing:.4px; font-size:1.1rem; }
    </style>
</head>
<body>

    <main class="py-5">
        <div class="container">

            {{-- Flash messages --}}
            @if(session('status'))
                <div class="alert alert-success alert-dismissible" role="alert">
                    {{ session('status') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card card-login shadow-sm">
                <div class="card-body p-4">
                    <h4 class="mb-3 text-center">Login</h4>
                    <p class="text-center text-muted small mb-4">
                        Masuk menggunakan <strong>name</strong> atau <strong>email</strong>.
                    </p>

                    <form method="POST" action="{{ route('login.attempt') }}">
                        @csrf

                        {{-- Login (name/email) --}}
                        <div class="mb-3">
                            <label for="login" class="form-label">Name atau Email</label>
                            <input id="login" name="login" type="text"
                                   class="form-control @error('login') is-invalid @enderror"
                                   value="{{ old('login') }}" required autofocus
                                   placeholder="contoh: john atau john@example.com">
                            @error('login') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- Password --}}
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input id="password" name="password" type="password"
                                   class="form-control @error('password') is-invalid @enderror"
                                   required>
                            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- Remember --}}
                        <div class="mb-3 form-check">
                            <input type="checkbox" name="remember" id="remember"
                                   class="form-check-input" {{ old('remember') ? 'checked' : '' }}>
                            <label for="remember" class="form-check-label">Remember me</label>
                        </div>

                        {{-- Global login error --}}
                        @if($errors->has('login'))
                            <div class="alert alert-danger">{{ $errors->first('login') }}</div>
                        @endif

                        <button type="submit" class="btn btn-primary w-100">Login</button>
                    </form>
                </div>
            </div>

        </div>
    </main>

    <footer class="py-3 text-center text-muted">
        © {{ date('Y') }} {{ config('app.name','SalesMP3') }}
    </footer>

    <!-- JS -->
    <script src="{{ asset('sneat/assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('sneat/assets/vendor/js/bootstrap.js') }}"></script>

</body>
</html>
